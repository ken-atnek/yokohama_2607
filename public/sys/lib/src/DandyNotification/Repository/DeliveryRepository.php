<?php
declare(strict_types=1);

namespace DandyNotification\Repository;

use DandyNotification\Database\DatabaseCapabilities;
use PDOException;

final class DeliveryRepository extends AbstractRepository
{
    private const VALID_PUBLIC_STATE_SHOPS = "'yh-dandy', 'yh-clubd', 'yh-mrd'";

    public function findById(int $deliveryId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM t_notification_delivery WHERE delivery_id = :delivery_id LIMIT 1',
            ['delivery_id' => $deliveryId]
        );
    }

    public function findByEventAndSubscription(int $eventId, int $subscriptionId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM t_notification_delivery WHERE event_id = :event_id AND subscription_id = :subscription_id LIMIT 1',
            [
                'event_id' => $eventId,
                'subscription_id' => $subscriptionId,
            ]
        );
    }

    public function findQueuedBatch(int $limit): array
    {
        $limit = max(1, min($limit, 500));

        return $this->fetchAll(
            "SELECT * FROM t_notification_delivery WHERE status = 'queued' AND available_at <= UTC_TIMESTAMP(6) ORDER BY available_at, delivery_id LIMIT :limit",
            ['limit' => $limit]
        );
    }

    public function hasSendingVerificationDelivery(int $subscriberId, int $subscriptionId): bool
    {
        return $this->fetchOne(
            "SELECT delivery_id
             FROM t_notification_delivery
             WHERE subscriber_id = :subscriber_id
               AND subscription_id = :subscription_id
               AND mail_type = 'verify_email'
               AND status = 'sending'
             LIMIT 1",
            [
                'subscriber_id' => $subscriberId,
                'subscription_id' => $subscriptionId,
            ]
        ) !== null;
    }

    public function cancelPendingVerificationDeliveries(int $subscriberId, int $subscriptionId): void
    {
        $this->prepareAndExecute(
            "UPDATE t_notification_delivery
             SET status = 'cancelled',
                 cancelled_at = UTC_TIMESTAMP(6),
                 last_error_code = NULL,
                 last_error_message = NULL
             WHERE subscriber_id = :subscriber_id
               AND subscription_id = :subscription_id
               AND mail_type = 'verify_email'
               AND status IN ('queued', 'deferred')",
            [
                'subscriber_id' => $subscriberId,
                'subscription_id' => $subscriptionId,
            ]
        );
    }

    public function cancelQueuedDeferredBySubscription(int $subscriberId, int $subscriptionId): int
    {
        $statement = $this->prepareAndExecute(
            "UPDATE t_notification_delivery
             SET status = 'cancelled',
                 cancelled_at = UTC_TIMESTAMP(6),
                 last_error_code = NULL,
                 last_error_message = NULL
             WHERE subscriber_id = :subscriber_id
               AND subscription_id = :subscription_id
               AND status IN ('queued', 'deferred')",
            [
                'subscriber_id' => $subscriberId,
                'subscription_id' => $subscriptionId,
            ]
        );

        return $statement->rowCount();
    }

    public function cancelQueuedDeferredBySubscriber(int $subscriberId): int
    {
        $statement = $this->prepareAndExecute(
            "UPDATE t_notification_delivery
             SET status = 'cancelled',
                 cancelled_at = UTC_TIMESTAMP(6),
                 last_error_code = NULL,
                 last_error_message = NULL
             WHERE subscriber_id = :subscriber_id
               AND status IN ('queued', 'deferred')",
            ['subscriber_id' => $subscriberId]
        );

        return $statement->rowCount();
    }

    public function insertDelivery(
        string $mailType,
        int $subscriberId,
        ?int $subscriptionId,
        string $recipientEmail,
        string $templateDataSnapshot,
        ?string $subjectSnapshot = null
    ): int {
        $this->prepareAndExecute(
            "INSERT INTO t_notification_delivery
                (event_id, subscriber_id, subscription_id, mail_type, recipient_email_snapshot, status,
                 template_key, template_version, subject_snapshot, template_data_snapshot, available_at)
             VALUES
                (NULL, :subscriber_id, :subscription_id, :mail_type, :recipient_email, 'queued',
                 :template_key, 'v1', :subject_snapshot, :template_data_snapshot, UTC_TIMESTAMP(6))",
            [
                'subscriber_id' => $subscriberId,
                'subscription_id' => $subscriptionId,
                'mail_type' => $mailType,
                'recipient_email' => $recipientEmail,
                'template_key' => $mailType,
                'subject_snapshot' => $subjectSnapshot,
                'template_data_snapshot' => $templateDataSnapshot,
            ]
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function insertScheduleUpdateDelivery(
        int $eventId,
        int $subscriberId,
        int $subscriptionId,
        string $recipientEmail,
        string $templateDataSnapshot,
        string $messageGroupKey,
        string $availableAtUtc,
        ?string $subjectSnapshot = null
    ): ?int {
        try {
            $this->prepareAndExecute(
                "INSERT INTO t_notification_delivery
                    (event_id, subscriber_id, subscription_id, mail_type, recipient_email_snapshot, status,
                     template_key, template_version, subject_snapshot, template_data_snapshot, message_group_key, available_at)
                 VALUES
                    (:event_id, :subscriber_id, :subscription_id, 'schedule_update', :recipient_email, 'queued',
                     'schedule_update', 'v1', :subject_snapshot, :template_data_snapshot, :message_group_key, :available_at)",
                [
                    'event_id' => $eventId,
                    'subscriber_id' => $subscriberId,
                    'subscription_id' => $subscriptionId,
                    'recipient_email' => $recipientEmail,
                    'subject_snapshot' => $subjectSnapshot,
                    'template_data_snapshot' => $templateDataSnapshot,
                    'message_group_key' => $messageGroupKey,
                    'available_at' => $availableAtUtc,
                ]
            );
        } catch (PDOException $exception) {
            if ($this->isDuplicateKey($exception)) {
                return null;
            }

            throw $exception;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function countCancellableCastScheduleUpdates(string $shopId, int $castId): int
    {
        return (int) ($this->fetchOne(
            "SELECT COUNT(*) AS target_count
             FROM t_notification_delivery d
             INNER JOIN t_notification_event e
                ON e.event_id = d.event_id
             INNER JOIN t_notification_subscription s
                ON s.subscription_id = d.subscription_id
             WHERE d.status IN ('queued', 'deferred')
               AND d.mail_type = 'schedule_update'
               AND e.event_type IN ('schedule_created', 'schedule_start_changed', 'schedule_changed_to_off', 'schedule_changed_to_work')
               AND e.shop_id IN (" . self::VALID_PUBLIC_STATE_SHOPS . ")
               AND s.shop_id IN (" . self::VALID_PUBLIC_STATE_SHOPS . ")
               AND e.shop_id = s.shop_id
               AND e.cast_id = s.cast_id
               AND e.shop_id = :event_shop_id
               AND e.cast_id = :event_cast_id
               AND s.shop_id = :subscription_shop_id
               AND s.cast_id = :subscription_cast_id",
            [
                'event_shop_id' => $shopId,
                'event_cast_id' => $castId,
                'subscription_shop_id' => $shopId,
                'subscription_cast_id' => $castId,
            ]
        )['target_count'] ?? 0);
    }

    public function cancelCancellableCastScheduleUpdates(string $shopId, int $castId): int
    {
        $statement = $this->prepareAndExecute(
            "UPDATE t_notification_delivery d
             INNER JOIN t_notification_event e
                ON e.event_id = d.event_id
             INNER JOIN t_notification_subscription s
                ON s.subscription_id = d.subscription_id
             SET d.status = 'cancelled',
                 d.cancelled_at = UTC_TIMESTAMP(6)
             WHERE d.status IN ('queued', 'deferred')
               AND d.mail_type = 'schedule_update'
               AND e.event_type IN ('schedule_created', 'schedule_start_changed', 'schedule_changed_to_off', 'schedule_changed_to_work')
               AND e.shop_id IN (" . self::VALID_PUBLIC_STATE_SHOPS . ")
               AND s.shop_id IN (" . self::VALID_PUBLIC_STATE_SHOPS . ")
               AND e.shop_id = s.shop_id
               AND e.cast_id = s.cast_id
               AND e.shop_id = :event_shop_id
               AND e.cast_id = :event_cast_id
               AND s.shop_id = :subscription_shop_id
               AND s.cast_id = :subscription_cast_id",
            [
                'event_shop_id' => $shopId,
                'event_cast_id' => $castId,
                'subscription_shop_id' => $shopId,
                'subscription_cast_id' => $castId,
            ]
        );

        return $statement->rowCount();
    }

    public function countCastScheduleUpdateAnomalies(string $shopId, int $castId): int
    {
        return (int) ($this->fetchOne(
            "SELECT COUNT(*) AS anomaly_count
             FROM t_notification_delivery d
             LEFT JOIN t_notification_event e
                ON e.event_id = d.event_id
             LEFT JOIN t_notification_subscription s
                ON s.subscription_id = d.subscription_id
             WHERE d.status IN ('queued', 'deferred')
               AND d.mail_type = 'schedule_update'
               AND (
                   (e.event_id IS NULL AND s.shop_id = :orphan_subscription_shop_id AND s.cast_id = :orphan_subscription_cast_id)
                   OR (s.subscription_id IS NULL AND e.shop_id = :orphan_event_shop_id AND e.cast_id = :orphan_event_cast_id)
                   OR (
                       e.event_id IS NOT NULL
                       AND s.subscription_id IS NOT NULL
                       AND (
                           (e.shop_id = :event_shop_id AND e.cast_id = :event_cast_id)
                           OR (s.shop_id = :subscription_shop_id AND s.cast_id = :subscription_cast_id)
                       )
                       AND (
                           e.event_type NOT IN ('schedule_created', 'schedule_start_changed', 'schedule_changed_to_off', 'schedule_changed_to_work')
                           OR e.shop_id NOT IN (" . self::VALID_PUBLIC_STATE_SHOPS . ")
                           OR s.shop_id NOT IN (" . self::VALID_PUBLIC_STATE_SHOPS . ")
                           OR e.shop_id <> s.shop_id
                           OR e.cast_id <> s.cast_id
                       )
                   )
               )",
            [
                'orphan_subscription_shop_id' => $shopId,
                'orphan_subscription_cast_id' => $castId,
                'orphan_event_shop_id' => $shopId,
                'orphan_event_cast_id' => $castId,
                'event_shop_id' => $shopId,
                'event_cast_id' => $castId,
                'subscription_shop_id' => $shopId,
                'subscription_cast_id' => $castId,
            ]
        )['anomaly_count'] ?? 0);
    }

    public function countPublicStateScheduleUpdateAnomalies(): int
    {
        return (int) ($this->fetchOne(
            "SELECT COUNT(*) AS anomaly_count
             FROM t_notification_delivery d
             LEFT JOIN t_notification_event e
                ON e.event_id = d.event_id
             LEFT JOIN t_notification_subscription s
                ON s.subscription_id = d.subscription_id
             WHERE d.status IN ('queued', 'deferred')
               AND d.mail_type = 'schedule_update'
               AND (
                   e.event_id IS NULL
                   OR s.subscription_id IS NULL
                   OR e.event_type NOT IN ('schedule_created', 'schedule_start_changed', 'schedule_changed_to_off', 'schedule_changed_to_work')
                   OR e.shop_id NOT IN (" . self::VALID_PUBLIC_STATE_SHOPS . ")
                   OR s.shop_id NOT IN (" . self::VALID_PUBLIC_STATE_SHOPS . ")
                   OR e.shop_id <> s.shop_id
                   OR e.cast_id <> s.cast_id
               )"
        )['anomaly_count'] ?? 0);
    }

    public function findEligibleMailUnitRepresentatives(int $limit): array
    {
        $limit = max(1, min($limit, 100));

        return $this->fetchAll(
            "SELECT unit_delivery_id AS delivery_id, mail_type
             FROM (
                 SELECT d.delivery_id AS unit_delivery_id, d.mail_type AS mail_type
                 FROM t_notification_delivery d
                 WHERE d.mail_type <> 'schedule_update'
                   AND d.status IN ('queued', 'deferred')
                   AND d.available_at <= UTC_TIMESTAMP(6)
                 UNION ALL
                 SELECT MIN(g.delivery_id) AS unit_delivery_id, 'schedule_update' AS mail_type
                 FROM t_notification_delivery e
                 INNER JOIN t_notification_delivery g
                    ON g.mail_type = 'schedule_update'
                   AND g.message_group_key = e.message_group_key
                 WHERE e.mail_type = 'schedule_update'
                   AND e.message_group_key IS NOT NULL
                   AND e.status IN ('queued', 'deferred')
                   AND e.available_at <= UTC_TIMESTAMP(6)
                 GROUP BY e.message_group_key
             ) units
             ORDER BY unit_delivery_id
             LIMIT :limit",
            ['limit' => $limit]
        );
    }

    public function findStaleMailUnitRepresentatives(string $cutoffUtc, int $limit): array
    {
        $limit = max(1, min($limit, 100));

        return $this->fetchAll(
            "SELECT unit_delivery_id AS delivery_id, mail_type
             FROM (
                 SELECT d.delivery_id AS unit_delivery_id, d.mail_type AS mail_type
                 FROM t_notification_delivery d
                 WHERE d.mail_type <> 'schedule_update'
                   AND d.status = 'sending'
                   AND d.locked_at IS NOT NULL
                   AND d.locked_at <= :single_cutoff_utc
                 UNION ALL
                 SELECT MIN(g.delivery_id) AS unit_delivery_id, 'schedule_update' AS mail_type
                 FROM t_notification_delivery e
                 INNER JOIN t_notification_delivery g
                    ON g.mail_type = 'schedule_update'
                   AND g.message_group_key = e.message_group_key
                 WHERE e.mail_type = 'schedule_update'
                   AND e.message_group_key IS NOT NULL
                   AND e.status = 'sending'
                   AND e.locked_at IS NOT NULL
                   AND e.locked_at <= :group_cutoff_utc
                 GROUP BY e.message_group_key
             ) units
             ORDER BY unit_delivery_id
             LIMIT :limit",
            [
                'single_cutoff_utc' => $cutoffUtc,
                'group_cutoff_utc' => $cutoffUtc,
                'limit' => $limit,
            ]
        );
    }

    public function lockRepresentativeById(int $deliveryId): ?array
    {
        $lockClause = DatabaseCapabilities::supportsForUpdateSkipLocked($this->pdo)
            ? 'FOR UPDATE SKIP LOCKED'
            : 'FOR UPDATE';

        return $this->fetchOne(
            "SELECT *
             FROM t_notification_delivery
             WHERE delivery_id = :delivery_id
             LIMIT 1
             {$lockClause}",
            ['delivery_id' => $deliveryId]
        );
    }

    public function lockMailUnitRows(array $representative): array
    {
        if (($representative['mail_type'] ?? null) === 'schedule_update'
            && is_string($representative['message_group_key'] ?? null)
            && $representative['message_group_key'] !== ''
        ) {
            return $this->fetchAll(
                "SELECT *
                 FROM t_notification_delivery
                 WHERE mail_type = 'schedule_update'
                   AND message_group_key = :message_group_key
                 ORDER BY delivery_id
                 FOR UPDATE",
                ['message_group_key' => $representative['message_group_key']]
            );
        }

        return $this->fetchAll(
            'SELECT *
             FROM t_notification_delivery
             WHERE delivery_id = :delivery_id
             ORDER BY delivery_id
             FOR UPDATE',
            ['delivery_id' => (int) $representative['delivery_id']]
        );
    }

    public function markRowsSending(array $deliveryIds, string $workerId): int
    {
        return $this->updateByIds(
            $deliveryIds,
            "UPDATE t_notification_delivery
             SET status = 'sending',
                 locked_at = UTC_TIMESTAMP(6),
                 locked_by = :worker_id,
                 attempt_count = attempt_count + 1,
                 last_error_code = NULL,
                 last_error_message = NULL,
                 deferred_at = NULL,
                 failed_at = NULL
             WHERE delivery_id IN (%s)",
            ['worker_id' => $workerId]
        );
    }

    public function markRowsSent(array $deliveryIds, ?string $providerMessageId): int
    {
        return $this->updateByIds(
            $deliveryIds,
            "UPDATE t_notification_delivery
             SET status = 'sent',
                 provider_message_id = :provider_message_id,
                 sent_at = UTC_TIMESTAMP(6),
                 locked_at = NULL,
                 locked_by = NULL,
                 last_error_code = NULL,
                 last_error_message = NULL
             WHERE delivery_id IN (%s)",
            ['provider_message_id' => $providerMessageId]
        );
    }

    public function markRowsDeferred(
        array $deliveryIds,
        int $retryDelaySeconds,
        string $lastErrorCode,
        string $lastErrorMessage
    ): int {
        return $this->updateByIds(
            $deliveryIds,
            "UPDATE t_notification_delivery
             SET status = 'deferred',
                 available_at = TIMESTAMPADD(SECOND, :retry_delay_seconds, UTC_TIMESTAMP(6)),
                 deferred_at = UTC_TIMESTAMP(6),
                 locked_at = NULL,
                 locked_by = NULL,
                 last_error_code = :last_error_code,
                 last_error_message = :last_error_message
             WHERE delivery_id IN (%s)",
            [
                'retry_delay_seconds' => $retryDelaySeconds,
                'last_error_code' => $lastErrorCode,
                'last_error_message' => $lastErrorMessage,
            ]
        );
    }

    public function markRowsFailed(array $deliveryIds, string $lastErrorCode, string $lastErrorMessage): int
    {
        return $this->updateByIds(
            $deliveryIds,
            "UPDATE t_notification_delivery
             SET status = 'failed',
                 failed_at = UTC_TIMESTAMP(6),
                 locked_at = NULL,
                 locked_by = NULL,
                 last_error_code = :last_error_code,
                 last_error_message = :last_error_message
             WHERE delivery_id IN (%s)",
            [
                'last_error_code' => $lastErrorCode,
                'last_error_message' => $lastErrorMessage,
            ]
        );
    }

    public function markUnsentRowsFailed(array $deliveryIds, string $lastErrorCode, string $lastErrorMessage): int
    {
        return $this->updateByIds(
            $deliveryIds,
            "UPDATE t_notification_delivery
             SET status = 'failed',
                 failed_at = UTC_TIMESTAMP(6),
                 locked_at = NULL,
                 locked_by = NULL,
                 last_error_code = :last_error_code,
                 last_error_message = :last_error_message
             WHERE delivery_id IN (%s)
               AND status <> 'sent'",
            [
                'last_error_code' => $lastErrorCode,
                'last_error_message' => $lastErrorMessage,
            ]
        );
    }

    public function countByIdsAndStatus(array $deliveryIds, string $status): int
    {
        if ($deliveryIds === []) {
            return 0;
        }

        $placeholders = $this->idPlaceholders($deliveryIds);
        $params = ['status' => $status] + $this->idParams($deliveryIds);
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS count_value
             FROM t_notification_delivery
             WHERE status = :status
               AND delivery_id IN ({$placeholders})",
            $params
        );

        return (int) ($row['count_value'] ?? 0);
    }

    private function isDuplicateKey(PDOException $exception): bool
    {
        $sqlState = is_string($exception->errorInfo[0] ?? null)
            ? (string) $exception->errorInfo[0]
            : (string) $exception->getCode();
        $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;

        return $sqlState === '23000' && $driverCode === 1062;
    }

    private function updateByIds(array $deliveryIds, string $sqlTemplate, array $params): int
    {
        if ($deliveryIds === []) {
            return 0;
        }

        $placeholders = $this->idPlaceholders($deliveryIds);
        $statement = $this->prepareAndExecute(
            sprintf($sqlTemplate, $placeholders),
            $params + $this->idParams($deliveryIds)
        );

        return $statement->rowCount();
    }

    private function idPlaceholders(array $deliveryIds): string
    {
        return implode(',', array_map(
            static fn (int $index): string => ':delivery_id_' . $index,
            array_keys(array_values($deliveryIds))
        ));
    }

    private function idParams(array $deliveryIds): array
    {
        $params = [];
        foreach (array_values($deliveryIds) as $index => $deliveryId) {
            $params['delivery_id_' . $index] = (int) $deliveryId;
        }

        return $params;
    }
}
