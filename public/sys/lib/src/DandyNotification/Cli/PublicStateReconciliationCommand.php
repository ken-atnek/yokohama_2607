<?php
declare(strict_types=1);

namespace DandyNotification\Cli;

use DandyNotification\ApplicationContext;
use DandyNotification\Support\RequestId;
use JsonException;
use PDO;
use Throwable;

final class PublicStateReconciliationCommand
{
    private const EXIT_OK = 0;
    private const EXIT_ERROR = 1;
    private const VALID_SHOP_IDS = ['yh-dandy', 'yh-clubd', 'yh-mrd'];
    private const VALID_SUBSCRIPTION_STATUSES = ['pending', 'active', 'paused', 'unsubscribed'];
    private const VALID_PAUSE_REASONS = ['cast_hidden', 'user_paused', 'admin_paused'];
    private const VALID_DELIVERY_STATUSES = ['queued', 'sending', 'deferred', 'sent', 'failed', 'suppressed', 'cancelled'];

    public function __construct(private readonly ApplicationContext $context)
    {
    }

    public function run(bool $apply): int
    {
        $requestId = RequestId::generate();
        $startedAt = microtime(true);
        $counters = $this->initialCounters();
        $lockName = 'dandy_notification_phase7_public_state_reconciliation:' . $this->context->config()->appEnvironment();
        $lockAcquired = false;
        $exitCode = self::EXIT_ERROR;
        $failureStage = 'config';

        if ($apply && !$this->context->featureFlags()->notificationPublicStateReconciliationEnabled()) {
            $counters['flag_off_skip'] = 1;
            $exitCode = self::EXIT_OK;
            $this->writeSummary($requestId, $apply, $counters, [], 0, $startedAt, $exitCode);

            return $exitCode;
        }

        try {
            $failureStage = 'config';
            $pdo = $this->context->pdo();
            $batchSize = $this->context->config()->publicStateReconciliationBatchSize();
            $failureStage = 'preflight';
            $this->preflight($pdo, $lockName);
            $failureStage = 'advisory_lock';
            $lockAcquired = $this->acquireLock($pdo, $lockName);

            if (!$lockAcquired) {
                $counters['already_running'] = 1;
                $exitCode = self::EXIT_OK;
                $this->writeSummary($requestId, $apply, $counters, [], 0, $startedAt, $exitCode);

                return $exitCode;
            }

            $failureStage = 'run_upper_bound';
            $upperKey = $this->context->repositories()->subscriptions()->findPublicStateReconciliationUpperKey();
            $upperSubscriptionId = $this->context->repositories()->subscriptions()->maxSubscriptionId();
            $failureStage = 'consistency_scan';
            $initialConsistency = $this->checkSubscriptionConsistency($upperSubscriptionId, $batchSize);
            $failureStage = 'delivery_anomaly_scan';
            $counters['delivery_anomaly_count'] = $this->context->repositories()->deliveries()
                ->countPublicStateScheduleUpdateAnomalies();
            $this->logInfo('public state reconciliation initial consistency checked', $requestId, [
                'upper_subscription_id' => $upperSubscriptionId,
                'consistency' => $initialConsistency,
                'delivery_anomaly_count' => $counters['delivery_anomaly_count'],
            ]);
            if ($counters['delivery_anomaly_count'] > 0) {
                $this->logError('public state reconciliation delivery anomaly detected', $requestId, [
                    'component' => 'Phase7/reconciliation',
                    'current_public_state' => 'unknown',
                    'failure_stage' => 'cancel_delivery',
                    'error' => [
                        'category' => 'delivery_anomaly',
                    ],
                    'delivery_anomaly_count' => $counters['delivery_anomaly_count'],
                ]);
            }

            if ($upperKey !== null) {
                $lastShopId = '';
                $lastCastId = 0;
                $upperShopId = (string) $upperKey['shop_id'];
                $upperCastId = (int) $upperKey['cast_id'];

                do {
                    $failureStage = 'candidate_scan';
                    $candidates = $this->context->repositories()->subscriptions()
                        ->fetchPublicStateReconciliationCandidateKeys(
                            $lastShopId,
                            $lastCastId,
                            $upperShopId,
                            $upperCastId,
                            $batchSize
                        );

                    foreach ($candidates as $candidate) {
                        $shopId = (string) $candidate['shop_id'];
                        $castId = (int) $candidate['cast_id'];
                        $lastShopId = $shopId;
                        $lastCastId = $castId;
                        $this->reconcileOne($requestId, $apply, $shopId, $castId, $counters);
                    }
                } while ($candidates !== []);
            }

            $failureStage = 'consistency_scan';
            $finalConsistency = $this->checkSubscriptionConsistency($upperSubscriptionId, $batchSize);
            $this->copyConsistencyToCounters($counters, $finalConsistency);
            $errorCount = $counters['transaction_error_count']
                + $counters['fatal_error_count']
                + $counters['delivery_anomaly_count']
                + array_sum($finalConsistency);
            $exitCode = $errorCount === 0 ? self::EXIT_OK : self::EXIT_ERROR;
            $this->writeSummary($requestId, $apply, $counters, $finalConsistency, $errorCount, $startedAt, $exitCode);
        } catch (Throwable $exception) {
            $counters['fatal_error_count']++;
            $this->logError('public state reconciliation failed', $requestId, [
                'component' => 'Phase7/reconciliation',
                'current_public_state' => 'unknown',
                'failure_stage' => $failureStage,
                'error' => $this->exceptionContext($exception),
            ]);
            $this->writeSummary($requestId, $apply, $counters, [], 1, $startedAt, self::EXIT_ERROR);
            $exitCode = self::EXIT_ERROR;
        } finally {
            if ($lockAcquired && !$this->releaseLock($this->context->existingPdo(), $lockName, $requestId)) {
                $exitCode = self::EXIT_ERROR;
            }
        }

        return $exitCode;
    }

    private function reconcileOne(
        string $requestId,
        bool $apply,
        string $shopId,
        int $castId,
        array &$counters
    ): void {
        $counters['checked_cast_count']++;
        try {
            $result = $this->context->services()->publicStateSync()
                ->reconcileCast($shopId, $castId, $requestId, $apply);

            $state = (string) $result['public_state'];
            $subscriptionCount = (int) $result['subscription_changed_count'];
            $deliveryCount = (int) $result['delivery_cancelled_count'];

            if ($state === 'invalid_view') {
                $counters['invalid_view_count']++;
            } elseif ($state === 'cast_missing') {
                $counters['missing_cast_count']++;
            }

            if ($apply) {
                if ($state === 'public') {
                    if ($subscriptionCount > 0) {
                        $counters['restored_cast_count']++;
                    }
                    $counters['restored_subscription_count'] += $subscriptionCount;
                } else {
                    if ($subscriptionCount > 0) {
                        $counters['paused_cast_count']++;
                    }
                    $counters['paused_subscription_count'] += $subscriptionCount;
                    $counters['cancelled_delivery_count'] += $deliveryCount;
                }
            } else {
                if ($state === 'public') {
                    if ($subscriptionCount > 0) {
                        $counters['restore_candidate_cast_count']++;
                    }
                    $counters['restore_candidate_subscription_count'] += $subscriptionCount;
                } else {
                    if ($subscriptionCount > 0) {
                        $counters['pause_candidate_cast_count']++;
                    }
                    $counters['pause_candidate_subscription_count'] += $subscriptionCount;
                    $counters['cancel_delivery_candidate_count'] += $deliveryCount;
                }
            }
            $counters['audit_inserted_count'] += $result['audit_inserted'] ? 1 : 0;
        } catch (Throwable $exception) {
            $counters['transaction_error_count']++;
            $this->logError('public state reconciliation cast failed', $requestId, [
                'component' => 'Phase7/reconciliation',
                'shop_id' => $shopId,
                'cast_id' => $castId,
                'current_public_state' => 'unknown',
                'failure_stage' => 'update_subscription',
                'error' => $this->exceptionContext($exception),
            ]);
        }
    }

    private function preflight(PDO $pdo, string $lockName): void
    {
        $databaseName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($databaseName) || $databaseName === '') {
            throw new \RuntimeException('Database is not selected.');
        }

        $notificationTables = [
            't_notification_subscriber',
            't_notification_subscription',
            't_notification_token',
            't_notification_event',
            't_notification_delivery',
            't_notification_suppression',
            't_notification_audit_log',
            't_girl_master2',
        ];
        $this->assertTables($pdo, $notificationTables);
        $this->assertColumns($pdo, [
            't_notification_subscription' => [
                'subscription_id',
                'subscriber_id',
                'shop_id',
                'cast_id',
                'status',
                'pause_reason',
                'requested_at',
                'activated_at',
                'paused_at',
                'unsubscribed_at',
            ],
            't_notification_event' => ['event_id', 'event_type', 'shop_id', 'cast_id'],
            't_notification_delivery' => [
                'delivery_id',
                'event_id',
                'subscriber_id',
                'subscription_id',
                'mail_type',
                'status',
                'cancelled_at',
                'last_error_code',
                'last_error_message',
                'sent_at',
                'attempt_count',
                'max_attempts',
            ],
            't_notification_audit_log' => [
                'audit_log_id',
                'subscriber_id',
                'subscription_id',
                'actor_type',
                'action',
                'result',
                'request_id',
                'shop_id',
                'cast_id',
                'metadata',
            ],
            't_girl_master2' => ['g_id', 'att_shop', 'view'],
        ]);
        $this->assertIndexes($pdo, [
            't_notification_subscription' => ['idx_tnsp_cast_status', 'idx_tnsp_pause'],
            't_notification_event' => ['idx_tnev_cast_date'],
            't_notification_delivery' => ['idx_tndl_queue', 'idx_tndl_subscription_subscriber'],
            't_notification_audit_log' => ['idx_tnal_action', 'idx_tnal_request'],
        ]);
        $this->assertShopAllowlist();
        $this->assertColumnNullable($pdo, 't_notification_subscription', 'pause_reason');
        $this->assertCheckValues($pdo, 't_notification_subscription', 'status', self::VALID_SUBSCRIPTION_STATUSES, 'subscription status');
        $this->assertCheckValues($pdo, 't_notification_subscription', 'pause_reason', self::VALID_PAUSE_REASONS, 'subscription pause_reason');
        $this->assertCheckValues($pdo, 't_notification_delivery', 'status', self::VALID_DELIVERY_STATUSES, 'delivery status');
        $this->assertAdvisoryLockUsable($pdo, $lockName);
    }

    private function assertShopAllowlist(): void
    {
        $configuredShopIds = $this->context->config()->emptyMailConfig()['allowed_shop_ids'] ?? [];
        if (!is_array($configuredShopIds)) {
            throw new \RuntimeException('Public state shop allowlist config is invalid.');
        }

        $configured = array_values(array_map('strval', $configuredShopIds));
        $expected = self::VALID_SHOP_IDS;
        sort($configured);
        sort($expected);

        if ($configured !== $expected) {
            throw new \RuntimeException('Public state shop allowlist mismatch.');
        }
    }

    private function assertTables(PDO $pdo, array $requiredTables): void
    {
        $placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
        $statement = $pdo->prepare(
            "SELECT TABLE_NAME, ENGINE
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ({$placeholders})"
        );
        $statement->execute($requiredTables);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $found = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $tableName = (string) $row['TABLE_NAME'];
            $found[] = $tableName;
            if (strcasecmp((string) $row['ENGINE'], 'InnoDB') !== 0) {
                throw new \RuntimeException('Required table is not InnoDB: ' . $tableName);
            }
        }

        if (count(array_diff($requiredTables, $found)) > 0) {
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

    private function assertColumnNullable(PDO $pdo, string $tableName, string $columnName): void
    {
        $statement = $pdo->prepare(
            'SELECT IS_NULLABLE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name
             LIMIT 1'
        );
        $statement->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);
        if ((string) $statement->fetchColumn() !== 'YES') {
            throw new \RuntimeException('Required nullable column mismatch: ' . $tableName . '.' . $columnName);
        }
    }

    private function assertCheckValues(PDO $pdo, string $tableName, string $columnName, array $values, string $label): void
    {
        $clauses = $this->checkClausesFromInformationSchema($pdo, $tableName);
        if ($clauses === []) {
            $clauses = $this->checkClausesFromShowCreate($pdo, $tableName);
        }

        $columnClauses = array_values(array_filter(
            $clauses,
            fn (string $clause): bool => $this->checkClauseReferencesColumn($clause, $columnName)
        ));
        if ($columnClauses === []) {
            throw new \RuntimeException('Required check clause missing: ' . $label);
        }

        $expected = $this->sortedUniqueStrings($values);
        foreach ($columnClauses as $clause) {
            if ($this->extractCheckQuotedLiterals($clause) === $expected) {
                return;
            }
        }

        throw new \RuntimeException('Required check values mismatch: ' . $label);
    }

    /**
     * @return list<string>
     */
    private function checkClausesFromInformationSchema(PDO $pdo, string $tableName): array
    {
        try {
            $statement = $pdo->prepare(
                "SELECT cc.CHECK_CLAUSE
                 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
                 INNER JOIN INFORMATION_SCHEMA.CHECK_CONSTRAINTS cc
                    ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
                   AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
                 WHERE tc.TABLE_SCHEMA = DATABASE()
                   AND tc.TABLE_NAME = :table_name
                   AND tc.CONSTRAINT_TYPE = 'CHECK'"
            );
            $statement->execute(['table_name' => $tableName]);
            $rows = $statement->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $rows), fn (string $clause): bool => $clause !== ''));
    }

    /**
     * @return list<string>
     */
    private function checkClausesFromShowCreate(PDO $pdo, string $tableName): array
    {
        $statement = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $tableName) . '`');
        if ($statement === false) {
            throw new \RuntimeException('SHOW CREATE TABLE failed: ' . $tableName);
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $createTable = is_array($row) ? implode("\n", array_map('strval', $row)) : '';
        if ($createTable === '') {
            throw new \RuntimeException('SHOW CREATE TABLE returned empty result: ' . $tableName);
        }

        return $this->extractCheckClausesFromShowCreate($createTable);
    }

    /**
     * @return list<string>
     */
    private function extractCheckClausesFromShowCreate(string $createTable): array
    {
        $clauses = [];
        $offset = 0;
        while (($checkPosition = stripos($createTable, 'CHECK', $offset)) !== false) {
            $openPosition = strpos($createTable, '(', $checkPosition);
            if ($openPosition === false) {
                break;
            }

            $closePosition = $this->findBalancedCloseParenthesis($createTable, $openPosition);
            if ($closePosition === null) {
                break;
            }

            $clauses[] = substr($createTable, $openPosition + 1, $closePosition - $openPosition - 1);
            $offset = $closePosition + 1;
        }

        return $clauses;
    }

    private function findBalancedCloseParenthesis(string $sql, int $openPosition): ?int
    {
        $length = strlen($sql);
        $depth = 0;
        $inSingleQuote = false;
        $inBacktick = false;

        for ($i = $openPosition; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($inSingleQuote) {
                if ($char === "'" && $next === "'") {
                    $i++;
                    continue;
                }

                if ($char === '\\') {
                    $i++;
                    continue;
                }

                if ($char === "'") {
                    $inSingleQuote = false;
                }

                continue;
            }

            if ($inBacktick) {
                if ($char === '`') {
                    $inBacktick = false;
                }

                continue;
            }

            if ($char === "'") {
                $inSingleQuote = true;
                continue;
            }

            if ($char === '`') {
                $inBacktick = true;
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function checkClauseReferencesColumn(string $clause, string $columnName): bool
    {
        $withoutQuotedStrings = preg_replace("/'(?:''|\\\\'|[^'])*'/", "''", $clause);
        $target = is_string($withoutQuotedStrings) ? $withoutQuotedStrings : $clause;

        return preg_match('/`' . preg_quote($columnName, '/') . '`/i', $target) === 1
            || preg_match('/(?<![A-Za-z0-9_])' . preg_quote($columnName, '/') . '(?![A-Za-z0-9_])/i', $target) === 1;
    }

    /**
     * @return list<string>
     */
    private function extractCheckQuotedLiterals(string $clause): array
    {
        preg_match_all("/'(?:''|\\\\'|[^'])*'/", $clause, $matches);
        $values = [];
        foreach ($matches[0] ?? [] as $quotedValue) {
            $value = substr((string) $quotedValue, 1, -1);
            $value = str_replace(["''", "\\'"], ["'", "'"], $value);
            $values[] = $value;
        }

        return $this->sortedUniqueStrings($values);
    }

    /**
     * @param array<int, string> $values
     * @return list<string>
     */
    private function sortedUniqueStrings(array $values): array
    {
        $unique = array_values(array_unique(array_map('strval', $values)));
        sort($unique);

        return $unique;
    }

    private function checkSubscriptionConsistency(int $upperSubscriptionId, int $batchSize): array
    {
        $result = [
            'invalid_subscription_state_count' => 0,
            'duplicate_subscription_group_count' => 0,
            'unknown_subscription_shop_count' => 0,
        ];

        if ($upperSubscriptionId < 1) {
            return $result;
        }

        $lastSubscriptionId = 0;
        do {
            $rows = $this->context->repositories()->subscriptions()
                ->fetchPublicStateConsistencyRows($lastSubscriptionId, $upperSubscriptionId, $batchSize);

            foreach ($rows as $row) {
                $lastSubscriptionId = (int) $row['subscription_id'];
                if (!in_array((string) $row['shop_id'], self::VALID_SHOP_IDS, true)) {
                    $result['unknown_subscription_shop_count']++;
                }

                if ($this->isInvalidSubscriptionState($row)) {
                    $result['invalid_subscription_state_count']++;
                }
            }
        } while ($rows !== []);

        $result['duplicate_subscription_group_count'] = $this->context->repositories()->subscriptions()
            ->countDuplicateLogicalSubscriptionGroups($upperSubscriptionId);

        return $result;
    }

    private function isInvalidSubscriptionState(array $row): bool
    {
        $status = (string) $row['status'];
        $pauseReason = $row['pause_reason'] === null ? null : (string) $row['pause_reason'];

        if (!in_array($status, ['pending', 'active', 'paused', 'unsubscribed'], true)) {
            return true;
        }

        if ($status === 'paused') {
            return $pauseReason === null
                || !in_array($pauseReason, self::VALID_PAUSE_REASONS, true);
        }

        return $pauseReason !== null;
    }

    private function copyConsistencyToCounters(array &$counters, array $consistency): void
    {
        foreach ([
            'invalid_subscription_state_count',
            'duplicate_subscription_group_count',
            'unknown_subscription_shop_count',
        ] as $key) {
            $counters[$key] = (int) ($consistency[$key] ?? 0);
        }
    }

    private function acquireLock(PDO $pdo, string $lockName): bool
    {
        $statement = $pdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
        $statement->execute(['lock_name' => $lockName]);

        return self::normalizeAdvisoryLockAcquireResult($statement->fetchColumn());
    }

    private function releaseLock(?PDO $pdo, string $lockName, string $requestId): bool
    {
        if ($pdo === null) {
            $this->logError('public state reconciliation lock release failed', $requestId, [
                'component' => 'Phase7/reconciliation',
                'current_public_state' => 'unknown',
                'failure_stage' => 'advisory_lock',
                'error' => $this->exceptionContext(new \RuntimeException('Advisory lock release connection is missing.')),
            ]);

            return false;
        }

        try {
            $statement = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $statement->execute(['lock_name' => $lockName]);
            $result = $statement->fetchColumn();
            if (!self::isAdvisoryLockReleaseSuccessful($result)) {
                throw new \RuntimeException('Advisory lock release returned an unexpected result.');
            }

            return true;
        } catch (Throwable $exception) {
            $this->logError('public state reconciliation lock release failed', $requestId, [
                'component' => 'Phase7/reconciliation',
                'current_public_state' => 'unknown',
                'failure_stage' => 'advisory_lock',
                'error' => $this->exceptionContext($exception),
            ]);

            return false;
        }
    }

    private static function normalizeAdvisoryLockAcquireResult(mixed $result): bool
    {
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

    private static function isAdvisoryLockReleaseSuccessful(mixed $result): bool
    {
        return $result !== false && $result !== null && (string) $result === '1';
    }

    private function initialCounters(): array
    {
        return [
            'checked_cast_count' => 0,
            'paused_cast_count' => 0,
            'paused_subscription_count' => 0,
            'restored_cast_count' => 0,
            'restored_subscription_count' => 0,
            'cancelled_delivery_count' => 0,
            'pause_candidate_cast_count' => 0,
            'pause_candidate_subscription_count' => 0,
            'restore_candidate_cast_count' => 0,
            'restore_candidate_subscription_count' => 0,
            'cancel_delivery_candidate_count' => 0,
            'invalid_subscription_state_count' => 0,
            'duplicate_subscription_group_count' => 0,
            'unknown_subscription_shop_count' => 0,
            'delivery_anomaly_count' => 0,
            'invalid_view_count' => 0,
            'missing_cast_count' => 0,
            'audit_inserted_count' => 0,
            'transaction_error_count' => 0,
            'fatal_error_count' => 0,
            'already_running' => 0,
            'flag_off_skip' => 0,
        ];
    }

    private function writeSummary(
        string $requestId,
        bool $apply,
        array $counters,
        array $consistency,
        int $errorCount,
        float $startedAt,
        int $exitCode
    ): void {
        $summary = [
            'mode' => $apply ? 'apply' : 'dry-run',
            'request_id' => $requestId,
            'counters' => $counters,
            'consistency' => $consistency,
            'error_count' => $errorCount,
            'duration' => round(microtime(true) - $startedAt, 6),
            'duration_seconds' => round(microtime(true) - $startedAt, 6),
            'exit_code' => $exitCode,
        ];

        $this->logInfo('public state reconciliation summary', $requestId, $summary);

        try {
            echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        } catch (JsonException) {
            echo '{"mode":"' . ($apply ? 'apply' : 'dry-run') . '","request_id":"' . $requestId . '","error_count":1,"exit_code":1}' . PHP_EOL;
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
            'component' => (string) ($context['component'] ?? 'Phase7/reconciliation'),
            'request_id' => (string) ($context['request_id'] ?? ''),
            'shop_id' => (string) ($context['shop_id'] ?? ''),
            'cast_id' => (string) ($context['cast_id'] ?? ''),
            'current_public_state' => (string) ($context['current_public_state'] ?? 'unknown'),
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
