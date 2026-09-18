<?php
declare(strict_types=1);

namespace DandyNotification\Cli;

use DandyNotification\ApplicationContext;
use DandyNotification\Support\RequestId;
use JsonException;
use PDO;
use Throwable;

final class CreateDeliveriesCommand
{
    private const EXIT_OK = 0;
    private const EXIT_ERROR = 1;

    public function __construct(private readonly ApplicationContext $context)
    {
    }

    public function run(): int
    {
        $requestId = RequestId::generate();
        $startedAt = microtime(true);
        $lockName = 'dandy_notification_phase9_delivery_create:' . $this->context->config()->appEnvironment();
        $lockAcquired = false;
        $exitCode = self::EXIT_ERROR;
        $failureStage = 'config';

        $summary = [
            'mode' => 'apply',
            'request_id' => $requestId,
            'status' => 'error',
            'counters' => $this->initialCounters(),
            'error_count' => 1,
            'failure_event_id' => null,
            'target_invalid_samples' => [],
            'duration_seconds' => 0.0,
            'exit_code' => self::EXIT_ERROR,
        ];

        if (!$this->context->featureFlags()->notificationDeliveryCreateEnabled()) {
            $summary['status'] = 'flag_off_skip';
            $summary['counters']['flag_off_skip'] = 1;
            $summary['error_count'] = 0;
            $summary['exit_code'] = self::EXIT_OK;
            $this->writeSummary($summary, $startedAt);

            return self::EXIT_OK;
        }

        try {
            $failureStage = 'config';
            $pdo = $this->context->pdo();
            $batchSize = $this->context->config()->deliveryCreateBatchSize();
            $failureStage = 'preflight';
            $this->preflight($pdo, $lockName);
            $failureStage = 'advisory_lock';
            $lockAcquired = $this->acquireLock($pdo, $lockName);

            if (!$lockAcquired) {
                $summary['status'] = 'already_running';
                $summary['counters']['already_running'] = 1;
                $summary['error_count'] = 0;
                $summary['exit_code'] = self::EXIT_OK;
                $this->writeSummary($summary, $startedAt);

                return self::EXIT_OK;
            }

            $failureStage = 'create_delivery_batch';
            $result = $this->context->services()->scheduleDeliveryCreation()
                ->createOneBatch($requestId, $batchSize);
            $summary['status'] = $result['success'] ? 'completed' : 'failed';
            $summary['counters'] = array_replace($summary['counters'], $result['counters']);
            $summary['error_count'] = (int) $result['error_count'];
            $summary['failure_event_id'] = $result['failure_event_id'];
            $summary['target_invalid_samples'] = $result['target_invalid_samples'] ?? [];
            $exitCode = $summary['error_count'] === 0 ? self::EXIT_OK : self::EXIT_ERROR;
            $summary['exit_code'] = $exitCode;
            $this->writeSummary($summary, $startedAt);
        } catch (Throwable $exception) {
            $summary['status'] = 'fatal_error';
            $summary['counters']['fatal_error_count']++;
            $summary['error_count'] = 1;
            $summary['exit_code'] = self::EXIT_ERROR;
            $this->logError('delivery create command failed', $requestId, [
                'component' => 'Phase9/delivery_create',
                'failure_stage' => $failureStage,
                'error' => $this->exceptionContext($exception),
            ]);
            $this->writeSummary($summary, $startedAt);
            $exitCode = self::EXIT_ERROR;
        } finally {
            if ($lockAcquired && !$this->releaseLock($this->context->existingPdo(), $lockName, $requestId)) {
                $exitCode = self::EXIT_ERROR;
            }
        }

        return $exitCode;
    }

    private function preflight(PDO $pdo, string $lockName): void
    {
        $databaseName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($databaseName) || $databaseName === '') {
            throw new \RuntimeException('Database is not selected.');
        }

        $this->assertTables($pdo, [
            't_notification_subscriber',
            't_notification_subscription',
            't_notification_event',
            't_notification_delivery',
            't_notification_suppression',
            't_work_list',
        ]);
        $this->assertColumns($pdo, [
            't_notification_subscriber' => [
                'subscriber_id',
                'email',
                'status',
                'quiet_hours_enabled',
                'quiet_start_minute',
                'quiet_end_minute',
                'timezone',
            ],
            't_notification_subscription' => [
                'subscription_id',
                'subscriber_id',
                'shop_id',
                'cast_id',
                'status',
                'pause_reason',
            ],
            't_notification_event' => [
                'event_id',
                'event_type',
                'shop_id',
                'cast_id',
                'schedule_date',
                'status',
                'available_at',
                'attempt_count',
                'max_attempts',
                'locked_at',
                'locked_by',
                'processed_at',
                'failed_at',
                'last_error',
            ],
            't_notification_delivery' => [
                'delivery_id',
                'event_id',
                'subscriber_id',
                'subscription_id',
                'mail_type',
                'recipient_email_snapshot',
                'status',
                'template_key',
                'template_version',
                'template_data_snapshot',
                'message_group_key',
                'available_at',
            ],
            't_notification_suppression' => ['email', 'status', 'expires_at'],
            't_work_list' => ['g_id', 'shop_id', 'win', 'wout'],
        ]);
        $this->assertIndexes($pdo, [
            't_notification_event' => ['idx_tnev_queue'],
            't_notification_delivery' => ['uq_tndl_event_subscription', 'idx_tndl_group'],
            't_notification_subscription' => ['idx_tnsp_cast_status'],
        ]);
        $this->assertAdvisoryLockUsable($pdo, $lockName);
    }

    private function assertTables(PDO $pdo, array $requiredTables): void
    {
        $placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
        $statement = $pdo->prepare(
            "SELECT TABLE_NAME
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ({$placeholders})"
        );
        $statement->execute($requiredTables);
        $found = $statement->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($found) || count(array_diff($requiredTables, $found)) > 0) {
            throw new \RuntimeException('Required tables are missing.');
        }
    }

    private function assertColumns(PDO $pdo, array $requiredColumnsByTable): void
    {
        foreach ($requiredColumnsByTable as $tableName => $columns) {
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $statement = $pdo->prepare(
                "SELECT COLUMN_NAME
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME IN ({$placeholders})"
            );
            $statement->execute(array_merge([(string) $tableName], $columns));
            $found = $statement->fetchAll(PDO::FETCH_COLUMN);
            if (!is_array($found) || count(array_diff($columns, $found)) > 0) {
                throw new \RuntimeException('Required columns are missing: ' . (string) $tableName);
            }
        }
    }

    private function assertIndexes(PDO $pdo, array $requiredIndexesByTable): void
    {
        foreach ($requiredIndexesByTable as $tableName => $indexes) {
            $placeholders = implode(',', array_fill(0, count($indexes), '?'));
            $statement = $pdo->prepare(
                "SELECT DISTINCT INDEX_NAME
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND INDEX_NAME IN ({$placeholders})"
            );
            $statement->execute(array_merge([(string) $tableName], $indexes));
            $found = $statement->fetchAll(PDO::FETCH_COLUMN);
            if (!is_array($found) || count(array_diff($indexes, $found)) > 0) {
                throw new \RuntimeException('Required indexes are missing: ' . (string) $tableName);
            }
        }
    }

    private function assertAdvisoryLockUsable(PDO $pdo, string $lockName): void
    {
        $statement = $pdo->prepare('SELECT IS_FREE_LOCK(:lock_name)');
        $statement->execute(['lock_name' => $lockName]);
        $result = $statement->fetchColumn();
        if ($result === false || $result === null || !in_array((string) $result, ['0', '1'], true)) {
            throw new \RuntimeException('Advisory lock is unavailable.');
        }
    }

    private function acquireLock(PDO $pdo, string $lockName): bool
    {
        $statement = $pdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
        $statement->execute(['lock_name' => $lockName]);
        $result = $statement->fetchColumn();
        if ($result === false || $result === null) {
            throw new \RuntimeException('Advisory lock acquire returned an unexpected result.');
        }

        if ((string) $result === '1') {
            return true;
        }

        if ((string) $result === '0') {
            return false;
        }

        throw new \RuntimeException('Advisory lock acquire returned an unexpected result.');
    }

    private function releaseLock(?PDO $pdo, string $lockName, string $requestId): bool
    {
        if ($pdo === null) {
            $this->logError('delivery create lock release failed', $requestId, [
                'component' => 'Phase9/delivery_create',
                'failure_stage' => 'advisory_lock',
                'error' => $this->exceptionContext(new \RuntimeException('Advisory lock release connection is missing.')),
            ]);

            return false;
        }

        try {
            $statement = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $statement->execute(['lock_name' => $lockName]);
            $result = $statement->fetchColumn();
            if ($result === false || $result === null || (string) $result !== '1') {
                throw new \RuntimeException('Advisory lock release returned an unexpected result.');
            }

            return true;
        } catch (Throwable $exception) {
            $this->logError('delivery create lock release failed', $requestId, [
                'component' => 'Phase9/delivery_create',
                'failure_stage' => 'advisory_lock',
                'error' => $this->exceptionContext($exception),
            ]);

            return false;
        }
    }

    private function writeSummary(array $summary, float $startedAt): void
    {
        $summary['duration_seconds'] = round(microtime(true) - $startedAt, 6);
        $this->logInfo(
            'delivery create summary',
            (string) $summary['request_id'],
            $summary
        );
        try {
            echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        } catch (JsonException) {
            echo '{"status":"json_error","error_count":1,"exit_code":1}' . PHP_EOL;
        }
    }

    private function logInfo(string $message, string $requestId, array $context): void
    {
        try {
            $this->context->logger()->info($message, ['request_id' => $requestId] + $context);
        } catch (Throwable) {
        }
    }

    private function logError(string $message, string $requestId, array $context): void
    {
        try {
            $this->context->logger()->error($message, ['request_id' => $requestId] + $context);
        } catch (Throwable) {
        }
    }

    private function exceptionContext(Throwable $exception): array
    {
        return [
            'class' => $exception::class,
            'message' => $this->safeErrorMessage($exception->getMessage()),
        ];
    }

    private function safeErrorMessage(string $message): string
    {
        $message = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted]', $message) ?? '[redacted]';
        $message = preg_replace('/(password|passwd|secret|token|session|email|recipient)(\s*[=:]\s*)\S+/i', '$1$2[redacted]', $message) ?? '[redacted]';

        return mb_substr($message, 0, 160);
    }

    private function initialCounters(): array
    {
        return [
            'event_claimed' => 0,
            'event_processed' => 0,
            'event_zero_target' => 0,
            'delivery_created' => 0,
            'delivery_duplicate' => 0,
            'subscriber_groups' => 0,
            'event_failed' => 0,
            'event_retried' => 0,
            'transaction_error_count' => 0,
            'target_invalid_skipped' => 0,
            'event_all_targets_invalid' => 0,
            'fatal_error_count' => 0,
            'already_running' => 0,
            'flag_off_skip' => 0,
        ];
    }
}
