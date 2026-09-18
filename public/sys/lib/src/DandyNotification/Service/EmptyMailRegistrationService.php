<?php
declare(strict_types=1);

namespace DandyNotification\Service;

use DandyNotification\Exception\PdoDiagnosticException;
use DandyNotification\Mail\EmptyMailRequest;
use DandyNotification\RepositorySet;
use JsonException;
use PDO;
use PDOException;
use Throwable;

final class EmptyMailRegistrationService
{
    private const VERIFICATION_TOKEN_TTL_SECONDS = 3600;
    private const SHOP_NAMES = [
        'yh-dandy' => '横浜ダンディー',
        'yh-clubd' => 'クラブダンディー',
        'yh-mrd' => 'ミスターダンディー',
    ];

    private const ACTION_REGISTERED_PENDING = 'empty_mail_registered_pending';
    private const ACTION_SUBSCRIPTION_ACTIVATED = 'empty_mail_subscription_activated';
    private const ACTION_ALREADY_ACTIVE = 'empty_mail_already_active';
    private const ACTION_VERIFICATION_IN_PROGRESS = 'empty_mail_verification_in_progress';
    private const ACTION_IGNORED = 'empty_mail_ignored';
    private const ACTION_REJECTED = 'empty_mail_rejected';

    private string $diagnosticStage = 'unknown';

    /**
     * @param array<string, mixed> $emptyMailConfig
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly RepositorySet $repositories,
        private readonly DeliveryService $deliveryService,
        private readonly array $emptyMailConfig
    ) {
    }

    public function handle(EmptyMailRequest $request, string $requestId): string
    {
        $attempt = 0;
        do {
            try {
                return $this->handleOnce($request, $requestId);
            } catch (PDOException $exception) {
                $this->rollbackIfNeeded();
                if ($attempt === 0 && $this->shouldRetry($exception)) {
                    $attempt++;
                    continue;
                }

                throw PdoDiagnosticException::from($this->diagnosticStage, $exception);
            }
        } while ($attempt <= 1);

        throw new PDOException('Empty mail registration retry failed.');
    }

    private function handleOnce(EmptyMailRequest $request, string $requestId): string
    {
        $email = $request->email();
        $emailHash = hash('sha256', $email, true);
        $castId = $request->castId();

        $this->diagnosticStage = 'transaction_begin';
        $this->pdo->beginTransaction();
        try {
            $this->diagnosticStage = 'rate_limit';
            $rateLimitReason = $this->detectRateLimit($emailHash, $castId);
            if ($rateLimitReason !== null) {
                return $this->finishIgnored(
                    $requestId,
                    $emailHash,
                    null,
                    null,
                    null,
                    $castId,
                    $rateLimitReason
                );
            }

            $this->diagnosticStage = 'girl_lookup';
            $girl = $this->repositories->girls()->findById($castId);
            if ($girl === null) {
                return $this->finishRejected($requestId, $emailHash, null, null, null, $castId, 'cast_not_found');
            }

            if ((string) $girl['view'] !== '1') {
                return $this->finishRejected($requestId, $emailHash, null, null, null, $castId, 'cast_hidden');
            }

            $shopId = trim((string) $girl['att_shop']);
            if ($shopId === '') {
                return $this->finishRejected($requestId, $emailHash, null, null, null, $castId, 'shop_missing');
            }

            if (!in_array($shopId, $this->allowedShopIds(), true)) {
                return $this->finishRejected($requestId, $emailHash, null, null, $shopId, $castId, 'shop_not_allowed');
            }

            $this->diagnosticStage = 'suppression_lookup';
            if ($this->repositories->suppressions()->existsActiveByEmail($email)) {
                return $this->finishIgnored($requestId, $emailHash, null, null, $shopId, $castId, 'suppressed');
            }

            $shopName = self::SHOP_NAMES[$shopId] ?? $shopId;
            $castName = $this->castName($girl, $castId);
            $this->diagnosticStage = 'subscriber_lookup';
            $subscriber = $this->repositories->subscribers()->findByEmailForUpdate($email);

            if ($subscriber === null) {
                return $this->registerPendingSubscriber(
                    $email,
                    $requestId,
                    $emailHash,
                    $shopId,
                    $castId,
                    $shopName,
                    $castName,
                    'new_subscriber'
                );
            }

            $subscriberId = (int) $subscriber['subscriber_id'];
            $status = (string) $subscriber['status'];

            if ($status === 'suppressed') {
                return $this->finishIgnored(
                    $requestId,
                    $emailHash,
                    $subscriberId,
                    null,
                    $shopId,
                    $castId,
                    'subscriber_suppressed'
                );
            }

            if ($status === 'pending' || $status === 'unsubscribed') {
                return $this->queueVerificationForPendingSubscriber(
                    $email,
                    $subscriberId,
                    $requestId,
                    $emailHash,
                    $shopId,
                    $castId,
                    $shopName,
                    $castName,
                    $status === 'unsubscribed' ? 'resubscribe_pending' : 'pending_subscriber',
                    $status
                );
            }

            if ($status === 'active') {
                return $this->activateSubscriptionForActiveSubscriber(
                    $email,
                    $subscriberId,
                    $requestId,
                    $emailHash,
                    $shopId,
                    $castId,
                    $shopName,
                    $castName
                );
            }

            return $this->finishRejected(
                $requestId,
                $emailHash,
                $subscriberId,
                null,
                $shopId,
                $castId,
                'invalid_subscriber_status'
            );
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function shouldRetry(PDOException $exception): bool
    {
        $sqlState = is_string($exception->errorInfo[0] ?? null)
            ? (string) $exception->errorInfo[0]
            : (string) $exception->getCode();
        $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;

        if ($sqlState === '23000' && $driverCode === 1062) {
            return true;
        }

        return $sqlState === '40001' || in_array($driverCode, [1205, 1213], true);
    }

    private function registerPendingSubscriber(
        string $email,
        string $requestId,
        string $emailHash,
        string $shopId,
        int $castId,
        string $shopName,
        string $castName,
        string $reason
    ): string {
        $this->diagnosticStage = 'subscriber_insert';
        $subscriberId = $this->repositories->subscribers()->insertPending($email);
        $this->diagnosticStage = 'subscription_insert';
        $subscriptionId = $this->repositories->subscriptions()->insertPending(
            $subscriberId,
            $shopId,
            $castId,
            $shopName,
            $castName
        );

        $this->diagnosticStage = 'token_revoke';
        $this->repositories->tokens()->revokeUnusedVerificationTokens($subscriberId, $subscriptionId);
        $this->diagnosticStage = 'delivery_cancel';
        $this->repositories->deliveries()->cancelPendingVerificationDeliveries($subscriberId, $subscriptionId);
        $deliveryId = $this->insertVerificationDelivery(
            $email,
            $subscriberId,
            $subscriptionId,
            $requestId,
            $shopId,
            $castId,
            $shopName,
            $castName
        );

        $this->insertAudit([
            'subscriber_id' => $subscriberId,
            'subscription_id' => $subscriptionId,
            'action' => self::ACTION_REGISTERED_PENDING,
            'result' => 'success',
            'request_id' => $requestId,
            'email_hash' => $emailHash,
            'shop_id' => $shopId,
            'cast_id' => $castId,
            'metadata' => $this->metadata([
                'reason_code' => $reason,
                'reason' => $reason,
                'mail_type' => 'verify_email',
                'delivery_id' => $deliveryId,
            ]),
        ]);

        $this->commit();

        return 'registered_pending';
    }

    private function queueVerificationForPendingSubscriber(
        string $email,
        int $subscriberId,
        string $requestId,
        string $emailHash,
        string $shopId,
        int $castId,
        string $shopName,
        string $castName,
        string $reason,
        string $subscriberStatus
    ): string {
        $this->diagnosticStage = 'subscription_lookup';
        $subscription = $this->repositories->subscriptions()->findBySubscriberShopCastForUpdate(
            $subscriberId,
            $shopId,
            $castId
        );

        if ($subscription === null) {
            $this->diagnosticStage = 'subscription_insert';
            $subscriptionId = $this->repositories->subscriptions()->insertPending(
                $subscriberId,
                $shopId,
                $castId,
                $shopName,
                $castName
            );
        } else {
            $subscriptionId = (int) $subscription['subscription_id'];
            $this->diagnosticStage = 'sending_verification_check';
            if ($this->repositories->deliveries()->hasSendingVerificationDelivery($subscriberId, $subscriptionId)) {
                return $this->finishVerificationInProgress(
                    $requestId,
                    $emailHash,
                    $subscriberId,
                    $subscriptionId,
                    $shopId,
                    $castId
                );
            }

            $subscriptionStatus = (string) $subscription['status'];
            $pauseReason = $subscription['pause_reason'] === null ? null : (string) $subscription['pause_reason'];

            if ($subscriptionStatus === 'paused' && $pauseReason === 'admin_paused') {
                return $this->finishIgnored(
                    $requestId,
                    $emailHash,
                    $subscriberId,
                    $subscriptionId,
                    $shopId,
                    $castId,
                    'admin_paused'
                );
            }

            if ($subscriptionStatus === 'active') {
                return $this->finishRejected(
                    $requestId,
                    $emailHash,
                    $subscriberId,
                    $subscriptionId,
                    $shopId,
                    $castId,
                    'inconsistent_active_subscription'
                );
            }

            if (!$this->canQueueVerificationForExistingSubscription($subscriptionStatus, $pauseReason)) {
                return $this->finishRejected(
                    $requestId,
                    $emailHash,
                    $subscriberId,
                    $subscriptionId,
                    $shopId,
                    $castId,
                    'invalid_subscription_state'
                );
            }

            if ($subscriberStatus === 'unsubscribed') {
                $this->diagnosticStage = 'subscriber_update';
                $this->repositories->subscribers()->markPendingFromEmptyMail($subscriberId);
            } else {
                $this->diagnosticStage = 'subscriber_update';
                $this->repositories->subscribers()->touchRegistrationRequest($subscriberId);
            }

            $this->diagnosticStage = 'subscription_update';
            $this->repositories->subscriptions()->markPending($subscriptionId, $shopName, $castName);
        }

        if ($subscription === null) {
            if ($subscriberStatus === 'unsubscribed') {
                $this->diagnosticStage = 'subscriber_update';
                $this->repositories->subscribers()->markPendingFromEmptyMail($subscriberId);
            } else {
                $this->diagnosticStage = 'subscriber_update';
                $this->repositories->subscribers()->touchRegistrationRequest($subscriberId);
            }
        }

        $this->diagnosticStage = 'token_revoke';
        $this->repositories->tokens()->revokeUnusedVerificationTokens($subscriberId, $subscriptionId);
        $this->diagnosticStage = 'delivery_cancel';
        $this->repositories->deliveries()->cancelPendingVerificationDeliveries($subscriberId, $subscriptionId);
        $deliveryId = $this->insertVerificationDelivery(
            $email,
            $subscriberId,
            $subscriptionId,
            $requestId,
            $shopId,
            $castId,
            $shopName,
            $castName
        );

        $this->insertAudit([
            'subscriber_id' => $subscriberId,
            'subscription_id' => $subscriptionId,
            'action' => self::ACTION_REGISTERED_PENDING,
            'result' => 'success',
            'request_id' => $requestId,
            'email_hash' => $emailHash,
            'shop_id' => $shopId,
            'cast_id' => $castId,
            'metadata' => $this->metadata([
                'reason_code' => $reason,
                'reason' => $reason,
                'mail_type' => 'verify_email',
                'delivery_id' => $deliveryId,
            ]),
        ]);

        $this->commit();

        return 'registered_pending';
    }

    private function activateSubscriptionForActiveSubscriber(
        string $email,
        int $subscriberId,
        string $requestId,
        string $emailHash,
        string $shopId,
        int $castId,
        string $shopName,
        string $castName
    ): string {
        $this->diagnosticStage = 'subscription_lookup';
        $subscription = $this->repositories->subscriptions()->findBySubscriberShopCastForUpdate(
            $subscriberId,
            $shopId,
            $castId
        );

        if ($subscription !== null && (string) $subscription['status'] === 'active') {
            $subscriptionId = (int) $subscription['subscription_id'];
            $this->insertAudit([
                'subscriber_id' => $subscriberId,
                'subscription_id' => $subscriptionId,
                'action' => self::ACTION_ALREADY_ACTIVE,
                'result' => 'success',
                'request_id' => $requestId,
                'email_hash' => $emailHash,
                'shop_id' => $shopId,
                'cast_id' => $castId,
                'metadata' => $this->metadata($this->reasonMetadata('already_active')),
            ]);
            $this->commit();

            return 'already_active';
        }

        if ($subscription === null) {
            $this->diagnosticStage = 'subscriber_update';
            $this->repositories->subscribers()->touchRegistrationRequest($subscriberId);
            $this->diagnosticStage = 'subscription_insert';
            $subscriptionId = $this->repositories->subscriptions()->insertActive(
                $subscriberId,
                $shopId,
                $castId,
                $shopName,
                $castName
            );
        } else {
            $subscriptionId = (int) $subscription['subscription_id'];
            $subscriptionStatus = (string) $subscription['status'];
            $pauseReason = $subscription['pause_reason'] === null ? null : (string) $subscription['pause_reason'];

            if ($subscriptionStatus === 'paused' && $pauseReason === 'admin_paused') {
                return $this->finishIgnored(
                    $requestId,
                    $emailHash,
                    $subscriberId,
                    $subscriptionId,
                    $shopId,
                    $castId,
                    'admin_paused'
                );
            }

            if (!$this->canActivateExistingSubscription($subscriptionStatus, $pauseReason)) {
                return $this->finishRejected(
                    $requestId,
                    $emailHash,
                    $subscriberId,
                    $subscriptionId,
                    $shopId,
                    $castId,
                    'invalid_subscription_state'
                );
            }

            $this->diagnosticStage = 'subscriber_update';
            $this->repositories->subscribers()->touchRegistrationRequest($subscriberId);
            $this->diagnosticStage = 'subscription_update';
            $this->repositories->subscriptions()->markActive($subscriptionId, $shopName, $castName);
        }

        $deliveryId = $this->insertSubscriptionAddedDelivery(
            $email,
            $subscriberId,
            $subscriptionId,
            $requestId,
            $shopId,
            $castId,
            $shopName,
            $castName
        );

        $this->insertAudit([
            'subscriber_id' => $subscriberId,
            'subscription_id' => $subscriptionId,
            'action' => self::ACTION_SUBSCRIPTION_ACTIVATED,
            'result' => 'success',
            'request_id' => $requestId,
            'email_hash' => $emailHash,
            'shop_id' => $shopId,
            'cast_id' => $castId,
            'metadata' => $this->metadata([
                'reason_code' => 'active_subscriber',
                'reason' => 'active_subscriber',
                'mail_type' => 'subscription_added',
                'delivery_id' => $deliveryId,
            ]),
        ]);

        $this->commit();

        return 'subscription_activated';
    }

    private function canActivateExistingSubscription(string $status, ?string $pauseReason): bool
    {
        if (in_array($status, ['pending', 'unsubscribed'], true)) {
            return $pauseReason === null;
        }

        if ($status === 'paused') {
            return in_array($pauseReason, ['cast_hidden', 'user_paused'], true);
        }

        return false;
    }

    private function canQueueVerificationForExistingSubscription(string $status, ?string $pauseReason): bool
    {
        if (in_array($status, ['pending', 'unsubscribed'], true)) {
            return $pauseReason === null;
        }

        if ($status === 'paused') {
            return in_array($pauseReason, ['cast_hidden', 'user_paused'], true);
        }

        return false;
    }

    private function insertVerificationDelivery(
        string $email,
        int $subscriberId,
        int $subscriptionId,
        string $requestId,
        string $shopId,
        int $castId,
        string $shopName,
        string $castName
    ): int {
        $snapshot = $this->deliveryService->buildTemplateDataSnapshot('verify_email', [
            'request_id' => $requestId,
            'shop_id' => $shopId,
            'cast_id' => $castId,
            'shop_name' => $shopName,
            'cast_name' => $castName,
            'token_ttl_seconds' => self::VERIFICATION_TOKEN_TTL_SECONDS,
        ]);

        $this->diagnosticStage = 'delivery_insert';
        return $this->repositories->deliveries()->insertDelivery(
            'verify_email',
            $subscriberId,
            $subscriptionId,
            $email,
            $snapshot
        );
    }

    private function insertSubscriptionAddedDelivery(
        string $email,
        int $subscriberId,
        int $subscriptionId,
        string $requestId,
        string $shopId,
        int $castId,
        string $shopName,
        string $castName
    ): int {
        $snapshot = $this->deliveryService->buildTemplateDataSnapshot('subscription_added', [
            'request_id' => $requestId,
            'shop_id' => $shopId,
            'cast_id' => $castId,
            'shop_name' => $shopName,
            'cast_name' => $castName,
        ]);

        $this->diagnosticStage = 'delivery_insert';
        return $this->repositories->deliveries()->insertDelivery(
            'subscription_added',
            $subscriberId,
            $subscriptionId,
            $email,
            $snapshot
        );
    }

    private function finishRejected(
        string $requestId,
        ?string $emailHash,
        ?int $subscriberId,
        ?int $subscriptionId,
        ?string $shopId,
        ?int $castId,
        string $reason
    ): string {
        $this->insertAudit([
            'subscriber_id' => $subscriberId,
            'subscription_id' => $subscriptionId,
            'action' => self::ACTION_REJECTED,
            'result' => 'failure',
            'request_id' => $requestId,
            'email_hash' => $emailHash,
            'shop_id' => $shopId,
            'cast_id' => $castId,
            'metadata' => $this->metadata($this->reasonMetadata($reason)),
        ]);
        $this->commit();

        return 'rejected:' . $reason;
    }

    private function finishIgnored(
        string $requestId,
        ?string $emailHash,
        ?int $subscriberId,
        ?int $subscriptionId,
        ?string $shopId,
        ?int $castId,
        string $reason
    ): string {
        $this->insertAudit([
            'subscriber_id' => $subscriberId,
            'subscription_id' => $subscriptionId,
            'action' => self::ACTION_IGNORED,
            'result' => 'success',
            'request_id' => $requestId,
            'email_hash' => $emailHash,
            'shop_id' => $shopId,
            'cast_id' => $castId,
            'metadata' => $this->metadata($this->reasonMetadata($reason)),
        ]);
        $this->commit();

        return 'ignored:' . $reason;
    }

    private function finishVerificationInProgress(
        string $requestId,
        string $emailHash,
        int $subscriberId,
        int $subscriptionId,
        string $shopId,
        int $castId
    ): string {
        $this->insertAudit([
            'subscriber_id' => $subscriberId,
            'subscription_id' => $subscriptionId,
            'action' => self::ACTION_VERIFICATION_IN_PROGRESS,
            'result' => 'success',
            'request_id' => $requestId,
            'email_hash' => $emailHash,
            'shop_id' => $shopId,
            'cast_id' => $castId,
            'metadata' => $this->metadata($this->reasonMetadata('verification_delivery_in_progress')),
        ]);
        $this->commit();

        return 'verification_delivery_in_progress';
    }

    /**
     * @param array<string, mixed> $auditLog
     */
    private function insertAudit(array $auditLog): void
    {
        $this->diagnosticStage = 'audit_insert';
        $this->repositories->auditLogs()->insert($auditLog);
    }

    private function commit(): void
    {
        $this->diagnosticStage = 'commit';
        $this->pdo->commit();
    }

    private function detectRateLimit(string $emailHash, int $castId): ?string
    {
        $limits = $this->emptyMailConfig['rate_limit'] ?? [];

        if ($this->isRateLimited('global', $limits, fn (int $window): int => (
            $this->repositories->auditLogs()->countRecentGlobalAttempts($window)
        ))) {
            return 'rate_limit_global';
        }

        if ($this->isRateLimited('email', $limits, fn (int $window): int => (
            $this->repositories->auditLogs()->countRecentByEmailHash($emailHash, $window)
        ))) {
            return 'rate_limit_email';
        }

        if ($this->isRateLimited('email_cast', $limits, fn (int $window): int => (
            $this->repositories->auditLogs()->countRecentByEmailHashAndCast($emailHash, $castId, $window)
        ))) {
            return 'rate_limit_email_cast';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $limits
     */
    private function isRateLimited(string $scope, array $limits, callable $counter): bool
    {
        $rule = $limits[$scope] ?? [];
        $window = $rule['window_seconds'] ?? null;
        $maxAttempts = $rule['max_attempts'] ?? null;

        if (!is_int($window) || !is_int($maxAttempts)) {
            return false;
        }

        return $counter($window) >= $maxAttempts;
    }

    /**
     * @return list<string>
     */
    private function allowedShopIds(): array
    {
        $shopIds = $this->emptyMailConfig['allowed_shop_ids'] ?? [];

        return is_array($shopIds) ? array_values($shopIds) : [];
    }

    private function castName(array $girl, int $castId): string
    {
        $name = trim((string) ($girl['name'] ?? ''));

        return $name === '' ? 'cast-' . (string) $castId : $name;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function metadata(array $metadata): string
    {
        try {
            return json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return '{"reason":"metadata_encoding_failed"}';
        }
    }

    /**
     * @return array{reason_code: string, reason: string}
     */
    private function reasonMetadata(string $reason): array
    {
        return [
            'reason_code' => $reason,
            'reason' => $reason,
        ];
    }
}
