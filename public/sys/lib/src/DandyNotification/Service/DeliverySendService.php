<?php
declare(strict_types=1);

namespace DandyNotification\Service;

use DateTimeImmutable;
use DateTimeZone;
use DandyNotification\Config\FeatureFlags;
use DandyNotification\Exception\MailTransportException;
use DandyNotification\Exception\RenderFailureException;
use DandyNotification\Log\JsonLogger;
use DandyNotification\Mail\MailTransportInterface;
use DandyNotification\Mail\NotificationMailRenderer;
use DandyNotification\Mail\TokenMaterial;
use DandyNotification\Repository\DeliveryRepository;
use DandyNotification\Repository\TokenRepository;
use PDO;
use RuntimeException;
use Throwable;

final class DeliverySendService
{
    public const ERROR_RENDER_FAILURE = 'render_failure';
    public const ERROR_TRANSPORT_FAILURE = 'transport_failure';
    public const ERROR_SEND_OUTCOME_UNKNOWN = 'send_outcome_unknown';
    public const ERROR_GROUP_INVARIANT_VIOLATION = 'group_invariant_violation';

    private const TOKEN_TTL_SECONDS = 3600;

    public function __construct(
        private readonly PDO $pdo,
        private readonly DeliveryRepository $deliveries,
        private readonly TokenRepository $tokens,
        private readonly NotificationMailRenderer $renderer,
        private readonly MailTransportInterface $transport,
        private readonly FeatureFlags $featureFlags,
        private readonly JsonLogger $logger,
        private readonly ?\Closure $sentUpdateFailureHook = null,
        private readonly ?\Closure $claimUpdateFailureHook = null
    ) {
    }

    public function sendOneBatch(string $requestId, string $workerId, int $batchSize, int $staleThresholdSeconds): array
    {
        $counters = $this->initialCounters();
        if (!$this->featureFlags->notificationSendEnabled()) {
            $counters['flag_off_noop'] = 1;

            return $this->result(true, $counters);
        }

        if ($this->featureFlags->notificationDryRun()) {
            $counters['dry_run_noop'] = 1;

            return $this->result(true, $counters);
        }

        $batchSize = max(1, min($batchSize, 100));
        $staleThresholdSeconds = max(1, $staleThresholdSeconds);
        $staleCutoffUtc = $this->utcNow()->modify('-' . $staleThresholdSeconds . ' seconds')->format('Y-m-d H:i:s.u');

        $processedStaleUnits = $this->recoverStaleUnits($requestId, $staleCutoffUtc, $batchSize, $counters);
        $remainingUnits = max(0, $batchSize - $processedStaleUnits);
        if ($remainingUnits === 0) {
            return $this->result($counters['transaction_error_count'] === 0, $counters);
        }

        foreach ($this->deliveries->findEligibleMailUnitRepresentatives($remainingUnits) as $candidate) {
            $claim = $this->claimCandidate($candidate, $requestId, $workerId, $counters);
            if ($claim === null) {
                continue;
            }

            $this->renderAndSendClaim($claim, $requestId, $counters);
        }

        return $this->result($counters['transaction_error_count'] === 0, $counters);
    }

    public static function retryDelaySeconds(int $attemptCount): int
    {
        return match ($attemptCount) {
            1 => 300,
            2 => 900,
            3 => 3600,
            default => 21600,
        };
    }

    private function recoverStaleUnits(string $requestId, string $staleCutoffUtc, int $batchSize, array &$counters): int
    {
        $processedUnits = 0;
        foreach ($this->deliveries->findStaleMailUnitRepresentatives($staleCutoffUtc, $batchSize) as $candidate) {
            try {
                $this->pdo->beginTransaction();
                $representative = $this->deliveries->lockRepresentativeById((int) $candidate['delivery_id']);
                if ($representative === null) {
                    $this->pdo->rollBack();
                    $counters['locked_skip']++;
                    continue;
                }

                $rows = $this->deliveries->lockMailUnitRows($representative);
                $recoveryRows = $this->staleRecoveryRows($rows, $staleCutoffUtc);
                if ($recoveryRows === []) {
                    $this->pdo->rollBack();
                    continue;
                }

                $recoveryIds = $this->deliveryIds($recoveryRows);
                $updatedRows = $this->deliveries->markRowsFailed(
                    $recoveryIds,
                    self::ERROR_SEND_OUTCOME_UNKNOWN,
                    'sending delivery exceeded stale threshold'
                );
                $this->assertAffectedRows(count($recoveryIds), $updatedRows, 'stale failure transition count mismatch');
                $this->pdo->commit();
                $processedUnits++;
                $counters['stale_send_outcome_unknown']++;
            } catch (Throwable $exception) {
                $this->rollbackQuietly();
                $counters['transaction_error_count']++;
                $this->logError('delivery stale recovery failed', $requestId, $exception, [
                    'failure_stage' => 'stale_recovery',
                ]);
            }
        }

        return $processedUnits;
    }

    private function claimCandidate(array $candidate, string $requestId, string $workerId, array &$counters): ?array
    {
        $tokenMaterial = null;
        try {
            $this->pdo->beginTransaction();
            $representative = $this->deliveries->lockRepresentativeById((int) $candidate['delivery_id']);
            if ($representative === null) {
                $this->pdo->rollBack();
                $counters['locked_skip']++;

                return null;
            }

            $rows = $this->deliveries->lockMailUnitRows($representative);
            if (!$this->isEligibleUnit($rows)) {
                $this->pdo->rollBack();
                $counters['not_eligible_skip']++;

                return null;
            }

            if ($this->isScheduleUpdateUnit($rows) && !$this->hasScheduleGroupInvariant($rows)) {
                $expectedRows = count($this->unsentRows($rows));
                $updatedRows = $this->deliveries->markUnsentRowsFailed(
                    $this->deliveryIds($rows),
                    self::ERROR_GROUP_INVARIANT_VIOLATION,
                    'schedule_update group invariant violation'
                );
                $this->assertAffectedRows($expectedRows, $updatedRows, 'group invariant failure transition count mismatch');
                $this->pdo->commit();
                $counters['group_invariant_violation']++;

                return null;
            }

            $mailType = (string) $rows[0]['mail_type'];
            $tokenId = null;
            $rawToken = null;
            if ($this->requiresToken($mailType)) {
                $tokenMaterial = TokenMaterial::generate();
                $tokenId = $this->insertTokenForDelivery($rows[0], $mailType, $tokenMaterial, $requestId);
                $rawToken = $tokenMaterial->rawToken();
            }

            $deliveryIds = $this->deliveryIds($rows);
            if ($this->claimUpdateFailureHook instanceof \Closure) {
                ($this->claimUpdateFailureHook)($rows, $deliveryIds);
            }
            $updatedRows = $this->deliveries->markRowsSending($deliveryIds, $workerId);
            $this->assertAffectedRows(count($deliveryIds), $updatedRows, 'claim transition count mismatch');
            $this->pdo->commit();
            $counters['mail_unit_claimed']++;
            $counters['delivery_rows_claimed'] += count($deliveryIds);

            return [
                'rows' => $rows,
                'delivery_ids' => $deliveryIds,
                'mail_type' => $mailType,
                'attempt_count' => (int) $rows[0]['attempt_count'] + 1,
                'max_attempts' => (int) $rows[0]['max_attempts'],
                'token_id' => $tokenId,
                'raw_token' => $rawToken,
            ];
        } catch (Throwable $exception) {
            $this->rollbackQuietly();
            $tokenMaterial = null;
            $counters['transaction_error_count']++;
            $this->logError('delivery claim failed', $requestId, $exception, [
                'failure_stage' => 'claim',
                'delivery_id' => (int) ($candidate['delivery_id'] ?? 0),
            ]);

            return null;
        }
    }

    private function renderAndSendClaim(array $claim, string $requestId, array &$counters): void
    {
        try {
            $verificationUrl = null;
            $settingsUrl = null;
            if ($claim['mail_type'] === 'verify_email') {
                $verificationUrl = $this->renderer->buildVerificationUrl((string) $claim['raw_token']);
            } elseif ($claim['mail_type'] === 'settings_link') {
                $settingsUrl = $this->renderer->buildSettingsUrl((string) $claim['raw_token']);
            }

            $mail = $this->renderer->render($claim['rows'], $verificationUrl, $settingsUrl);
        } catch (RenderFailureException $exception) {
            $this->markDefiniteFailure($claim, self::ERROR_RENDER_FAILURE, $exception->getMessage(), $requestId, $counters);

            return;
        }

        try {
            $transportResult = $this->transport->send($mail);
        } catch (MailTransportException $exception) {
            $this->markDefiniteFailure($claim, self::ERROR_TRANSPORT_FAILURE, $exception->getMessage(), $requestId, $counters);

            return;
        }

        if (!$transportResult->successValue()) {
            $this->markDefiniteFailure($claim, self::ERROR_TRANSPORT_FAILURE, 'mail transport returned false', $requestId, $counters);

            return;
        }

        try {
            $this->pdo->beginTransaction();
            if ($this->sentUpdateFailureHook instanceof \Closure) {
                ($this->sentUpdateFailureHook)($claim);
            }
            $updatedRows = $this->deliveries->markRowsSent($claim['delivery_ids'], $transportResult->providerMessageId());
            $this->assertAffectedRows(count($claim['delivery_ids']), $updatedRows, 'sent transition count mismatch');
            $this->pdo->commit();
            $counters['mail_unit_sent']++;
            $counters['delivery_rows_sent'] += count($claim['delivery_ids']);
        } catch (Throwable $exception) {
            $this->rollbackQuietly();
            $counters['sent_update_error_count']++;
            $counters['transaction_error_count']++;
            $this->logError('delivery sent update failed', $requestId, $exception, [
                'failure_stage' => 'mark_sent',
                'mail_type' => (string) $claim['mail_type'],
            ]);
        }
    }

    private function markDefiniteFailure(
        array $claim,
        string $errorCode,
        string $message,
        string $requestId,
        array &$counters
    ): void {
        try {
            $this->pdo->beginTransaction();
            $tokenRevoked = false;
            $outcomeCounter = null;
            if (is_int($claim['token_id'])) {
                if (!$this->tokens->revokeById($claim['token_id'])) {
                    throw new RuntimeException('token revoke failed.');
                }
                $tokenRevoked = true;
            }

            if ((int) $claim['attempt_count'] >= (int) $claim['max_attempts']) {
                $updatedRows = $this->deliveries->markRowsFailed($claim['delivery_ids'], $errorCode, $this->safeErrorMessage($message));
                $this->assertAffectedRows(count($claim['delivery_ids']), $updatedRows, 'failed transition count mismatch');
                $outcomeCounter = 'mail_unit_failed';
            } else {
                $updatedRows = $this->deliveries->markRowsDeferred(
                    $claim['delivery_ids'],
                    self::retryDelaySeconds((int) $claim['attempt_count']),
                    $errorCode,
                    $this->safeErrorMessage($message)
                );
                $this->assertAffectedRows(count($claim['delivery_ids']), $updatedRows, 'deferred transition count mismatch');
                $outcomeCounter = 'mail_unit_deferred';
            }

            $this->pdo->commit();
            if ($tokenRevoked) {
                $counters['token_revoked']++;
            }
            if (is_string($outcomeCounter)) {
                $counters[$outcomeCounter]++;
            }
            $counters[$errorCode]++;
        } catch (Throwable $exception) {
            $this->rollbackQuietly();
            $counters['transaction_error_count']++;
            $this->logError('delivery failure transition failed', $requestId, $exception, [
                'failure_stage' => $errorCode,
                'mail_type' => (string) $claim['mail_type'],
            ]);
        }
    }

    private function insertTokenForDelivery(array $row, string $mailType, TokenMaterial $tokenMaterial, string $requestId): int
    {
        $subscriberId = (int) $row['subscriber_id'];
        $subscriptionId = isset($row['subscription_id']) ? (int) $row['subscription_id'] : null;

        if ($mailType === 'verify_email') {
            if ($subscriptionId === null || $subscriptionId <= 0) {
                throw new RenderFailureException('verify_email subscription_id is missing.');
            }

            return $this->tokens->insertToken(
                $subscriberId,
                $subscriptionId,
                'verify_email',
                $tokenMaterial->tokenHash(),
                $requestId
            );
        }

        if ($mailType === 'settings_link') {
            return $this->tokens->insertToken(
                $subscriberId,
                null,
                'manage_settings',
                $tokenMaterial->tokenHash(),
                $requestId
            );
        }

        throw new RenderFailureException('token purpose is unsupported.');
    }

    private function isEligibleUnit(array $rows): bool
    {
        if ($rows === []) {
            return false;
        }

        if (!$this->isScheduleUpdateUnit($rows)) {
            return count($rows) === 1
                && in_array((string) $rows[0]['status'], ['queued', 'deferred'], true)
                && $this->isAvailable((string) $rows[0]['available_at']);
        }

        foreach ($rows as $row) {
            if (in_array((string) $row['status'], ['queued', 'deferred'], true)
                && $this->isAvailable((string) $row['available_at'])
            ) {
                return true;
            }
        }

        return false;
    }

    private function staleRecoveryRows(array $rows, string $staleCutoffUtc): array
    {
        if ($rows === []) {
            return [];
        }

        if (!$this->isScheduleUpdateUnit($rows)) {
            return $this->isStaleSendingRow($rows[0], $staleCutoffUtc) ? $rows : [];
        }

        if (!$this->hasStaleSendingRow($rows, $staleCutoffUtc)) {
            return [];
        }

        if ($this->isConsistentStaleSendingUnit($rows, $staleCutoffUtc)) {
            return $rows;
        }

        return $this->unsentRows($rows);
    }

    private function hasStaleSendingRow(array $rows, string $staleCutoffUtc): bool
    {
        foreach ($rows as $row) {
            if ($this->isStaleSendingRow($row, $staleCutoffUtc)) {
                return true;
            }
        }

        return false;
    }

    private function isStaleSendingRow(array $row, string $staleCutoffUtc): bool
    {
        return ($row['status'] ?? null) === 'sending'
            && is_string($row['locked_at'] ?? null)
            && $row['locked_at'] <= $staleCutoffUtc;
    }

    private function isConsistentStaleSendingUnit(array $rows, string $staleCutoffUtc): bool
    {
        if ($rows === []) {
            return false;
        }

        $first = $rows[0];
        foreach ($rows as $row) {
            if (!$this->isStaleSendingRow($row, $staleCutoffUtc)) {
                return false;
            }

            foreach (['attempt_count', 'locked_at', 'locked_by'] as $field) {
                if ((string) ($row[$field] ?? '') !== (string) ($first[$field] ?? '')) {
                    return false;
                }
            }
        }

        return true;
    }

    private function hasScheduleGroupInvariant(array $rows): bool
    {
        if ($rows === []) {
            return false;
        }

        $fields = [
            'subscriber_id',
            'recipient_email_snapshot',
            'mail_type',
            'available_at',
            'status',
            'attempt_count',
            'max_attempts',
            'template_key',
            'template_version',
        ];
        $first = $rows[0];
        foreach ($rows as $row) {
            foreach ($fields as $field) {
                if ((string) ($row[$field] ?? '') !== (string) ($first[$field] ?? '')) {
                    return false;
                }
            }
        }

        return true;
    }

    private function isScheduleUpdateUnit(array $rows): bool
    {
        return ($rows[0]['mail_type'] ?? null) === 'schedule_update';
    }

    private function requiresToken(string $mailType): bool
    {
        return in_array($mailType, ['verify_email', 'settings_link'], true);
    }

    private function isAvailable(string $availableAt): bool
    {
        try {
            return new DateTimeImmutable($availableAt, new DateTimeZone('UTC')) <= $this->utcNow();
        } catch (Throwable) {
            return false;
        }
    }

    private function deliveryIds(array $rows): array
    {
        return array_map(static fn (array $row): int => (int) $row['delivery_id'], $rows);
    }

    private function unsentRows(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => ($row['status'] ?? null) !== 'sent'
        ));
    }

    private function assertAffectedRows(int $expected, int $actual, string $message): void
    {
        if ($actual !== $expected) {
            throw new RuntimeException($message . ' expected=' . $expected . ' actual=' . $actual);
        }
    }

    private function utcNow(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function safeErrorMessage(string $message): string
    {
        $message = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted]', $message) ?? '[redacted]';
        $message = preg_replace('/https?:\/\/\S+|mailto:\S+/i', '[redacted-url]', $message) ?? '[redacted]';
        $message = preg_replace('/([?&](?:token|raw_token|code|key|pk|csrf|session)=)[^&\s]+/i', '$1[redacted]', $message) ?? '[redacted]';

        return mb_substr($message, 0, 190);
    }

    private function rollbackQuietly(): void
    {
        if ($this->pdo->inTransaction()) {
            try {
                $this->pdo->rollBack();
            } catch (Throwable) {
            }
        }
    }

    private function logError(string $message, string $requestId, Throwable $exception, array $context = []): void
    {
        try {
            $this->logger->error($message, [
                'request_id' => $requestId,
                'exception' => $exception,
            ] + $context);
        } catch (Throwable) {
        }
    }

    private function result(bool $success, array $counters): array
    {
        return [
            'success' => $success,
            'counters' => $counters,
            'error_count' => $success ? 0 : 1,
        ];
    }

    private function initialCounters(): array
    {
        return [
            'mail_unit_claimed' => 0,
            'delivery_rows_claimed' => 0,
            'mail_unit_sent' => 0,
            'delivery_rows_sent' => 0,
            'mail_unit_deferred' => 0,
            'mail_unit_failed' => 0,
            'render_failure' => 0,
            'transport_failure' => 0,
            'send_outcome_unknown' => 0,
            'group_invariant_violation' => 0,
            'stale_send_outcome_unknown' => 0,
            'token_revoked' => 0,
            'sent_update_error_count' => 0,
            'transaction_error_count' => 0,
            'locked_skip' => 0,
            'not_eligible_skip' => 0,
            'flag_off_noop' => 0,
            'dry_run_noop' => 0,
        ];
    }
}
