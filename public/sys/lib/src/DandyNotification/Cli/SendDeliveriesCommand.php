<?php
declare(strict_types=1);

namespace DandyNotification\Cli;

use DandyNotification\ApplicationContext;
use DandyNotification\Mail\NativeMailTransport;
use DandyNotification\Mail\NotificationMailRenderer;
use DandyNotification\Service\DeliverySendService;
use DandyNotification\Support\RequestId;
use DandyNotification\Validation\TemplateDataValidator;
use JsonException;
use PDO;
use Throwable;

final class SendDeliveriesCommand
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
        $exitCode = self::EXIT_ERROR;
        $failureStage = 'config';
        $summary = [
            'mode' => 'apply',
            'request_id' => $requestId,
            'worker_id' => $this->workerId($requestId),
            'status' => 'error',
            'counters' => $this->initialCounters(),
            'error_count' => 1,
            'duration_seconds' => 0.0,
            'exit_code' => self::EXIT_ERROR,
        ];

        if (!$this->context->featureFlags()->notificationSendEnabled()) {
            $summary['status'] = 'send_flag_off_noop';
            $summary['counters']['flag_off_noop'] = 1;
            $summary['error_count'] = 0;
            $summary['exit_code'] = self::EXIT_OK;
            $this->writeSummary($summary, $startedAt);

            return self::EXIT_OK;
        }

        if ($this->context->featureFlags()->notificationDryRun()) {
            $summary['status'] = 'dry_run_noop';
            $summary['counters']['dry_run_noop'] = 1;
            $summary['error_count'] = 0;
            $summary['exit_code'] = self::EXIT_OK;
            $this->writeSummary($summary, $startedAt);

            return self::EXIT_OK;
        }

        try {
            $failureStage = 'url_preflight';
            $this->preflightNotificationUrls();

            $failureStage = 'mail_preflight';
            $this->preflightMailConfig();

            $failureStage = 'preflight';
            $pdo = $this->context->pdo();
            $this->preflight($pdo);

            $failureStage = 'send_delivery_batch';
            $repositories = $this->context->repositories();
            $service = new DeliverySendService(
                $pdo,
                $repositories->deliveries(),
                $repositories->tokens(),
                new NotificationMailRenderer($this->context->config(), new TemplateDataValidator()),
                new NativeMailTransport(envelopeFrom: $this->context->config()->mailEnvelopeFrom()),
                $this->context->featureFlags(),
                $this->context->logger()
            );
            $result = $service->sendOneBatch(
                $requestId,
                (string) $summary['worker_id'],
                $this->context->config()->deliverySendBatchSize(),
                $this->context->config()->deliverySendStaleThresholdSeconds()
            );

            $summary['status'] = $result['success'] ? 'completed' : 'failed';
            $summary['counters'] = array_replace($summary['counters'], $result['counters']);
            $summary['error_count'] = (int) $result['error_count'];
            $exitCode = $summary['error_count'] === 0 ? self::EXIT_OK : self::EXIT_ERROR;
            $summary['exit_code'] = $exitCode;
            $this->writeSummary($summary, $startedAt);
        } catch (Throwable $exception) {
            $summary['status'] = 'fatal_error';
            $summary['counters']['fatal_error_count']++;
            $summary['error_count'] = 1;
            $summary['exit_code'] = self::EXIT_ERROR;
            $this->logError('delivery send command failed', $requestId, [
                'component' => 'Phase10/delivery_send',
                'failure_stage' => $failureStage,
                'exception' => $exception,
            ]);
            $this->writeSummary($summary, $startedAt);
            $exitCode = self::EXIT_ERROR;
        }

        return $exitCode;
    }

    private function preflightNotificationUrls(): void
    {
        $urls = $this->context->config()->notificationUrlConfig();
        $environment = $this->context->config()->appEnvironment();
        $this->assertWgMailSysUrl(
            $urls['confirmation_base_url'] ?? null,
            '/wgmailsys/confirm/',
            'notification_url.confirmation_base_url',
            $environment
        );
        $this->assertWgMailSysUrl(
            $urls['settings_base_url'] ?? null,
            '/wgmailsys/settings/',
            'notification_url.settings_base_url',
            $environment
        );
    }

    private function preflightMailConfig(): void
    {
        if ($this->context->config()->mailEnvelopeFrom() === null) {
            throw new \RuntimeException('mail.envelope_from is required for notification send.');
        }
    }

    private function preflight(PDO $pdo): void
    {
        $databaseName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($databaseName) || $databaseName === '') {
            throw new \RuntimeException('Database is not selected.');
        }

        $this->assertTables($pdo, [
            't_notification_subscriber',
            't_notification_subscription',
            't_notification_token',
            't_notification_delivery',
        ]);
        $this->assertColumns($pdo, [
            't_notification_token' => [
                'token_id',
                'subscriber_id',
                'subscription_id',
                'purpose',
                'token_hash',
                'request_id',
                'expires_at',
                'used_at',
                'revoked_at',
            ],
            't_notification_delivery' => [
                'delivery_id',
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
                'attempt_count',
                'max_attempts',
                'locked_at',
                'locked_by',
                'provider_message_id',
                'sent_at',
                'deferred_at',
                'failed_at',
                'last_error_code',
                'last_error_message',
            ],
        ]);
        $this->assertIndexes($pdo, [
            't_notification_delivery' => ['idx_tndl_queue', 'idx_tndl_group', 'idx_tndl_lock'],
        ]);
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

    private function assertWgMailSysUrl(mixed $value, string $requiredPath, string $configPath, string $environment): void
    {
        $parts = $this->parseAbsoluteUrl($value, $configPath);
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $path = (string) ($parts['path'] ?? '');

        if (!is_string($value) || !str_ends_with($value, '/')) {
            throw new \RuntimeException($configPath . ' must end with slash.');
        }

        if (isset($parts['query']) || $path !== $requiredPath) {
            throw new \RuntimeException($configPath . ' is invalid.');
        }

        if ($environment === 'production') {
            if ($scheme !== 'https' || $host !== 'www.dandy-g.jp' || array_key_exists('port', $parts)) {
                throw new \RuntimeException($configPath . ' is invalid for production.');
            }

            return;
        }

        if ($environment === 'staging') {
            if ($scheme !== 'https' || $host !== 'demo-yokohama.dandy-g.jp' || array_key_exists('port', $parts)) {
                throw new \RuntimeException($configPath . ' is invalid for staging.');
            }

            return;
        }

        if ($scheme !== 'http' || !in_array($host, ['localhost', '127.0.0.1'], true) || !array_key_exists('port', $parts)) {
            throw new \RuntimeException($configPath . ' is invalid for local.');
        }
    }

    private function parseAbsoluteUrl(mixed $value, string $configPath): array
    {
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException($configPath . ' must be a non-empty URL.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new \RuntimeException($configPath . ' must not contain control characters.');
        }

        $parts = parse_url($value);
        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            throw new \RuntimeException($configPath . ' must be an absolute URL without credentials or fragment.');
        }

        if (array_key_exists('port', $parts) && ((int) $parts['port'] < 1 || (int) $parts['port'] > 65535)) {
            throw new \RuntimeException($configPath . ' port is invalid.');
        }

        return $parts;
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

    private function workerId(string $requestId): string
    {
        $host = php_uname('n') ?: 'unknown';
        $pid = getmypid();
        $id = 'phase10:' . $host . ':' . (is_int($pid) ? (string) $pid : '0') . ':' . $requestId;
        $id = preg_replace('/[^A-Za-z0-9_.:\-]/', '_', $id) ?? 'phase10:unknown';

        return substr($id, 0, 128);
    }

    private function writeSummary(array $summary, float $startedAt): void
    {
        $summary['duration_seconds'] = round(microtime(true) - $startedAt, 6);
        $this->logInfo('delivery send summary', (string) $summary['request_id'], $summary);
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
            'fatal_error_count' => 0,
        ];
    }
}
