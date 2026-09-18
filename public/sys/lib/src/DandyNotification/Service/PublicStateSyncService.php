<?php
declare(strict_types=1);

namespace DandyNotification\Service;

use DandyNotification\Log\JsonLogger;
use DandyNotification\RepositorySet;
use InvalidArgumentException;
use JsonException;
use PDO;
use Throwable;

final class PublicStateSyncService
{
    private const VALID_SHOP_IDS = ['yh-dandy', 'yh-clubd', 'yh-mrd'];
    private const STATE_PUBLIC = 'public';
    private const STATE_HIDDEN = 'hidden';
    private const STATE_INVALID_VIEW = 'invalid_view';
    private const STATE_CAST_MISSING = 'cast_missing';
    private const STATE_UNKNOWN = 'unknown';

    public function __construct(
        private readonly PDO $pdo,
        private readonly RepositorySet $repositories,
        private readonly JsonLogger $logger
    ) {
    }

    public function syncDirectViewChange(
        string $shopId,
        int $castId,
        ?string $oldView,
        ?string $newView,
        string $requestId
    ): array {
        return $this->syncCast('direct', $shopId, $castId, $oldView, $newView, $requestId, true);
    }

    public function syncDirectDelete(
        string $shopId,
        int $castId,
        ?string $oldView,
        string $requestId
    ): array {
        return $this->syncCast('direct', $shopId, $castId, $oldView, null, $requestId, true);
    }

    public function reconcileCast(
        string $shopId,
        int $castId,
        string $requestId,
        bool $apply
    ): array {
        return $this->syncCast('reconciliation', $shopId, $castId, null, null, $requestId, $apply);
    }

    private function syncCast(
        string $source,
        string $shopId,
        int $castId,
        ?string $oldView,
        ?string $newView,
        string $requestId,
        bool $apply
    ): array {
        $state = self::STATE_UNKNOWN;
        $failureStage = 'begin_transaction';
        try {
            $this->assertTarget($shopId, $castId);

            if (!$apply) {
                $failureStage = 'load_girl';
                return $this->previewCast($source, $shopId, $castId, $oldView, $newView, $requestId);
            }

            $failureStage = 'begin_transaction';
            $this->pdo->beginTransaction();
            $failureStage = 'load_girl';
            $girl = $this->repositories->girls()->findByShopAndCastForUpdate($shopId, $castId);
            $state = $this->detectState($girl);
            $anomalies = $this->repositories->deliveries()->countCastScheduleUpdateAnomalies($shopId, $castId);
            $subscriptionChanged = 0;
            $deliveryCancelled = 0;
            $failureStage = 'update_subscription';
            if ($state === self::STATE_PUBLIC) {
                $subscriptionChanged = $this->repositories->subscriptions()
                    ->restoreCastHiddenPausedByCast($shopId, $castId);
            } else {
                $subscriptionChanged = $this->repositories->subscriptions()
                    ->pauseActiveByCastHidden($shopId, $castId);
                $failureStage = 'cancel_delivery';
                $deliveryCancelled = $this->repositories->deliveries()
                    ->cancelCancellableCastScheduleUpdates($shopId, $castId);
            }

            $auditInserted = false;
            if ($subscriptionChanged > 0) {
                $failureStage = 'insert_audit';
                $this->insertAudit(
                    $source,
                    $shopId,
                    $castId,
                    $oldView,
                    $newView,
                    $this->observedView($girl),
                    $state,
                    $subscriptionChanged,
                    $requestId
                );
                $auditInserted = true;
            }

            $failureStage = 'commit';
            $this->pdo->commit();

            $result = $this->result(
                $source,
                $shopId,
                $castId,
                $oldView,
                $newView,
                $state,
                $this->observedView($girl),
                $subscriptionChanged,
                $deliveryCancelled,
                $anomalies,
                $auditInserted,
                true
            );
            if ($anomalies > 0) {
                $this->logError('public state sync delivery anomaly', $requestId, [
                    'component' => 'Phase7/public_state_sync',
                    'shop_id' => $shopId,
                    'cast_id' => $castId,
                    'current_public_state' => $state,
                    'failure_stage' => 'cancel_delivery',
                    'error' => [
                        'category' => 'delivery_anomaly',
                    ],
                ] + $result);
            }
            $this->logInfo('public state sync completed', $requestId, $result);

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logError('public state sync failed', $requestId, [
                'component' => 'Phase7/public_state_sync',
                'shop_id' => $shopId,
                'cast_id' => $castId,
                'current_public_state' => $state,
                'failure_stage' => $failureStage,
                'error' => $this->exceptionContext($exception),
            ]);

            throw $exception;
        }
    }

    private function previewCast(
        string $source,
        string $shopId,
        int $castId,
        ?string $oldView,
        ?string $newView,
        string $requestId
    ): array {
        $girl = $this->repositories->girls()->findByShopAndCast($shopId, $castId);
        $state = $this->detectState($girl);
        $anomalies = $this->repositories->deliveries()->countCastScheduleUpdateAnomalies($shopId, $castId);
        $subscriptionChanged = 0;
        $deliveryCancelled = 0;

        if ($state === self::STATE_PUBLIC) {
            $subscriptionChanged = $this->repositories->subscriptions()
                ->countCastHiddenPausedByCast($shopId, $castId);
        } else {
            $subscriptionChanged = $this->repositories->subscriptions()
                ->countActiveByCast($shopId, $castId);
            $deliveryCancelled = $this->repositories->deliveries()
                ->countCancellableCastScheduleUpdates($shopId, $castId);
        }

        $result = $this->result(
            $source,
            $shopId,
            $castId,
            $oldView,
            $newView,
            $state,
            $this->observedView($girl),
            $subscriptionChanged,
            $deliveryCancelled,
            $anomalies,
            false,
            false
        );
        if ($anomalies > 0) {
            $this->logError('public state sync dry run delivery anomaly', $requestId, [
                'component' => 'Phase7/public_state_sync',
                'shop_id' => $shopId,
                'cast_id' => $castId,
                'current_public_state' => $state,
                'failure_stage' => 'cancel_delivery',
                'error' => [
                    'category' => 'delivery_anomaly',
                ],
            ] + $result);
        }
        $this->logInfo('public state sync dry run completed', $requestId, $result);

        return $result;
    }

    private function insertAudit(
        string $source,
        string $shopId,
        int $castId,
        ?string $oldView,
        ?string $newView,
        ?string $observedView,
        string $state,
        int $affectedCount,
        string $requestId
    ): void {
        $this->repositories->auditLogs()->insertSystemAction([
            'subscriber_id' => null,
            'subscription_id' => null,
            'actor_id' => null,
            'action' => $state === self::STATE_PUBLIC ? 'cast_hidden_restored' : 'cast_hidden_paused',
            'result' => 'success',
            'request_id' => $requestId,
            'email_hash' => null,
            'shop_id' => $shopId,
            'cast_id' => $castId,
            'metadata' => $this->metadata([
                'source' => $source,
                'shop_id' => $shopId,
                'cast_id' => $castId,
                'old_view' => $oldView,
                'new_view' => $newView,
                'observed_view' => $observedView,
                'affected_count' => $affectedCount,
                'reason' => $this->reasonForState($state),
            ]),
        ]);
    }

    private function detectState(?array $girl): string
    {
        if ($girl === null) {
            return self::STATE_CAST_MISSING;
        }

        $view = $this->observedView($girl);
        if ($view === '1') {
            return self::STATE_PUBLIC;
        }

        if ($view === '0') {
            return self::STATE_HIDDEN;
        }

        return self::STATE_INVALID_VIEW;
    }

    private function observedView(?array $girl): ?string
    {
        if ($girl === null || !array_key_exists('view', $girl) || $girl['view'] === null) {
            return null;
        }

        return (string) $girl['view'];
    }

    private function assertTarget(string $shopId, int $castId): void
    {
        if (!in_array($shopId, self::VALID_SHOP_IDS, true)) {
            throw new InvalidArgumentException('Invalid public state sync shop_id.');
        }

        if ($castId < 1) {
            throw new InvalidArgumentException('Invalid public state sync cast_id.');
        }
    }

    private function reasonForState(string $state): string
    {
        if ($state === self::STATE_PUBLIC || $state === self::STATE_HIDDEN) {
            return 'cast_hidden';
        }

        return $state;
    }

    private function result(
        string $source,
        string $shopId,
        int $castId,
        ?string $oldView,
        ?string $newView,
        string $state,
        ?string $observedView,
        int $subscriptionChanged,
        int $deliveryCancelled,
        int $deliveryAnomalyCount,
        bool $auditInserted,
        bool $applied
    ): array {
        return [
            'source' => $source,
            'shop_id' => $shopId,
            'cast_id' => $castId,
            'old_view' => $oldView,
            'new_view' => $newView,
            'observed_view' => $observedView,
            'public_state' => $state,
            'reason' => $this->reasonForState($state),
            'subscription_changed_count' => $subscriptionChanged,
            'delivery_cancelled_count' => $deliveryCancelled,
            'delivery_anomaly_count' => $deliveryAnomalyCount,
            'audit_inserted' => $auditInserted,
            'applied' => $applied,
        ];
    }

    private function metadata(array $metadata): string
    {
        try {
            return json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new JsonException('Public state audit metadata JSON encoding failed.', 0, $exception);
        }
    }

    private function logInfo(string $message, string $requestId, array $context): void
    {
        try {
            $this->logger->info($message, ['request_id' => $requestId] + $context);
        } catch (Throwable) {
        }
    }

    private function logError(string $message, string $requestId, array $context): void
    {
        try {
            $this->logger->error($message, ['request_id' => $requestId] + $context);
        } catch (Throwable $exception) {
            $this->fallbackErrorLog($message, ['request_id' => $requestId] + $context, $exception);
        }
    }

    private function exceptionContext(Throwable $exception): array
    {
        return [
            'class' => $exception::class,
            'message' => $this->safeErrorMessage($exception->getMessage()),
        ];
    }

    private function fallbackErrorLog(string $message, array $context, Throwable $loggerException): void
    {
        $context['failure_stage'] = (string) ($context['failure_stage'] ?? 'logger');
        $context['error'] = $context['error'] ?? [
            'category' => 'logger',
            'class' => $loggerException::class,
        ];

        $fields = [
            'time' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
            'component' => (string) ($context['component'] ?? 'Phase7/public_state_sync'),
            'request_id' => (string) ($context['request_id'] ?? ''),
            'shop_id' => (string) ($context['shop_id'] ?? ''),
            'cast_id' => (string) ($context['cast_id'] ?? ''),
            'current_public_state' => (string) ($context['current_public_state'] ?? self::STATE_UNKNOWN),
            'failure_stage' => (string) ($context['failure_stage'] ?? 'logger'),
            'error_category' => $this->errorCategory($context['error']),
            'message' => $message,
        ];

        try {
            error_log('[dandy_notification_phase7] ' . $this->formatFallbackFields($fields));
        } catch (Throwable) {
        }
    }

    private function errorCategory(mixed $error): string
    {
        if (is_array($error) && isset($error['category'])) {
            return $this->safeFallbackValue((string) $error['category']);
        }

        if (is_array($error) && isset($error['class'])) {
            return $this->safeFallbackValue((string) $error['class']);
        }

        return 'error';
    }

    private function formatFallbackFields(array $fields): string
    {
        $parts = [];
        foreach ($fields as $key => $value) {
            $parts[] = $key . '=' . $this->safeFallbackValue((string) $value);
        }

        return implode(' ', $parts);
    }

    private function safeErrorMessage(string $message): string
    {
        $message = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted]', $message) ?? '[redacted]';
        $message = preg_replace('/(password|passwd|secret|token|session|email|recipient)(\s*[=:]\s*)\S+/i', '$1$2[redacted]', $message) ?? '[redacted]';

        return mb_substr($message, 0, 160);
    }

    private function safeFallbackValue(string $value): string
    {
        $value = $this->safeErrorMessage($value);
        $value = preg_replace('/[\r\n\t ]+/', '_', $value) ?? '[redacted]';

        return mb_substr($value, 0, 120);
    }
}
