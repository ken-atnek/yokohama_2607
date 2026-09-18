<?php
declare(strict_types=1);

namespace DandyNotification\Repository;

final class SubscriptionRepository extends AbstractRepository
{
    private const VALID_PUBLIC_STATE_SHOPS = "'yh-dandy', 'yh-clubd', 'yh-mrd'";

    public function findById(int $subscriptionId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM t_notification_subscription WHERE subscription_id = :subscription_id LIMIT 1',
            ['subscription_id' => $subscriptionId]
        );
    }

    public function findBySubscriberShopCast(int $subscriberId, string $shopId, int $castId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM t_notification_subscription WHERE subscriber_id = :subscriber_id AND shop_id = :shop_id AND cast_id = :cast_id LIMIT 1',
            [
                'subscriber_id' => $subscriberId,
                'shop_id' => $shopId,
                'cast_id' => $castId,
            ]
        );
    }

    public function findBySubscriberShopCastForUpdate(int $subscriberId, string $shopId, int $castId): ?array
    {
        return $this->fetchOne(
            'SELECT subscription_id, subscriber_id, shop_id, cast_id, status, pause_reason
             FROM t_notification_subscription
             WHERE subscriber_id = :subscriber_id
               AND shop_id = :shop_id
               AND cast_id = :cast_id
             LIMIT 1
             FOR UPDATE',
            [
                'subscriber_id' => $subscriberId,
                'shop_id' => $shopId,
                'cast_id' => $castId,
            ]
        );
    }

    public function findByIdForUpdate(int $subscriptionId): ?array
    {
        return $this->fetchOne(
            'SELECT subscription_id, subscriber_id, shop_id, cast_id, shop_name_snapshot, cast_name_snapshot,
                    status, pause_reason, activated_at, unsubscribed_at
             FROM t_notification_subscription
             WHERE subscription_id = :subscription_id
             LIMIT 1
             FOR UPDATE',
            ['subscription_id' => $subscriptionId]
        );
    }

    public function findVisibleBySubscriber(int $subscriberId): array
    {
        return $this->fetchAll(
            "SELECT subscription_id, subscriber_id, shop_id, cast_id, shop_name_snapshot, cast_name_snapshot,
                    status, pause_reason, updated_at
             FROM t_notification_subscription
             WHERE subscriber_id = :subscriber_id
               AND status IN ('active', 'paused')
             ORDER BY shop_id, cast_name_snapshot, cast_id, subscription_id",
            ['subscriber_id' => $subscriberId]
        );
    }

    public function findByIdAndSubscriber(int $subscriptionId, int $subscriberId): ?array
    {
        return $this->fetchOne(
            'SELECT subscription_id, subscriber_id, shop_id, cast_id, shop_name_snapshot, cast_name_snapshot,
                    status, pause_reason, updated_at
             FROM t_notification_subscription
             WHERE subscription_id = :subscription_id
               AND subscriber_id = :subscriber_id
             LIMIT 1',
            [
                'subscription_id' => $subscriptionId,
                'subscriber_id' => $subscriberId,
            ]
        );
    }

    public function findByIdAndSubscriberForUpdate(int $subscriptionId, int $subscriberId): ?array
    {
        return $this->fetchOne(
            'SELECT subscription_id, subscriber_id, shop_id, cast_id, shop_name_snapshot, cast_name_snapshot,
                    status, pause_reason, activated_at, unsubscribed_at
             FROM t_notification_subscription
             WHERE subscription_id = :subscription_id
               AND subscriber_id = :subscriber_id
             LIMIT 1
             FOR UPDATE',
            [
                'subscription_id' => $subscriptionId,
                'subscriber_id' => $subscriberId,
            ]
        );
    }

    public function findAllBySubscriberForUpdate(int $subscriberId): array
    {
        return $this->fetchAll(
            'SELECT subscription_id, subscriber_id, shop_id, cast_id, shop_name_snapshot, cast_name_snapshot,
                    status, pause_reason
             FROM t_notification_subscription
             WHERE subscriber_id = :subscriber_id
             FOR UPDATE',
            ['subscriber_id' => $subscriberId]
        );
    }

    public function activateVerifiedSubscription(int $subscriptionId): int
    {
        $statement = $this->prepareAndExecute(
            "UPDATE t_notification_subscription
             SET status = 'active',
                 pause_reason = NULL,
                 activated_at = COALESCE(activated_at, UTC_TIMESTAMP(6)),
                 paused_at = NULL,
                 unsubscribed_at = NULL
             WHERE subscription_id = :subscription_id
               AND status = 'pending'",
            ['subscription_id' => $subscriptionId]
        );

        return $statement->rowCount();
    }

    public function insertPending(
        int $subscriberId,
        string $shopId,
        int $castId,
        string $shopName,
        string $castName
    ): int {
        $this->prepareAndExecute(
            "INSERT INTO t_notification_subscription
                (subscriber_id, shop_id, cast_id, shop_name_snapshot, cast_name_snapshot, status, registration_source, requested_at)
             VALUES
                (:subscriber_id, :shop_id, :cast_id, :shop_name, :cast_name, 'pending', 'empty_mail', UTC_TIMESTAMP(6))",
            [
                'subscriber_id' => $subscriberId,
                'shop_id' => $shopId,
                'cast_id' => $castId,
                'shop_name' => $shopName,
                'cast_name' => $castName,
            ]
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function insertActive(
        int $subscriberId,
        string $shopId,
        int $castId,
        string $shopName,
        string $castName
    ): int {
        $this->prepareAndExecute(
            "INSERT INTO t_notification_subscription
                (subscriber_id, shop_id, cast_id, shop_name_snapshot, cast_name_snapshot, status, pause_reason, registration_source, requested_at, activated_at)
             VALUES
                (:subscriber_id, :shop_id, :cast_id, :shop_name, :cast_name, 'active', NULL, 'empty_mail', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
            [
                'subscriber_id' => $subscriberId,
                'shop_id' => $shopId,
                'cast_id' => $castId,
                'shop_name' => $shopName,
                'cast_name' => $castName,
            ]
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function markPending(int $subscriptionId, string $shopName, string $castName): void
    {
        $this->prepareAndExecute(
            "UPDATE t_notification_subscription
             SET status = 'pending',
                 pause_reason = NULL,
                 shop_name_snapshot = :shop_name,
                 cast_name_snapshot = :cast_name,
                 requested_at = UTC_TIMESTAMP(6),
                 activated_at = NULL,
                 paused_at = NULL,
                 unsubscribed_at = NULL
             WHERE subscription_id = :subscription_id",
            [
                'subscription_id' => $subscriptionId,
                'shop_name' => $shopName,
                'cast_name' => $castName,
            ]
        );
    }

    public function markActive(int $subscriptionId, string $shopName, string $castName): void
    {
        $this->prepareAndExecute(
            "UPDATE t_notification_subscription
             SET status = 'active',
                 pause_reason = NULL,
                 shop_name_snapshot = :shop_name,
                 cast_name_snapshot = :cast_name,
                 activated_at = COALESCE(activated_at, UTC_TIMESTAMP(6)),
                 paused_at = NULL,
                 unsubscribed_at = NULL
             WHERE subscription_id = :subscription_id",
            [
                'subscription_id' => $subscriptionId,
                'shop_name' => $shopName,
                'cast_name' => $castName,
            ]
        );
    }

    public function findActiveByCast(string $shopId, int $castId): array
    {
        return $this->fetchAll(
            "SELECT * FROM t_notification_subscription WHERE shop_id = :shop_id AND cast_id = :cast_id AND status = 'active' ORDER BY subscription_id",
            [
                'shop_id' => $shopId,
                'cast_id' => $castId,
            ]
        );
    }

    public function findActiveDeliveryTargetsByCast(string $shopId, int $castId): array
    {
        return $this->fetchAll(
            "SELECT
                 s.subscription_id,
                 s.subscriber_id,
                 s.shop_id,
                 s.cast_id,
                 s.shop_name_snapshot,
                 s.cast_name_snapshot,
                 b.email AS subscriber_email,
                 b.quiet_hours_enabled,
                 b.quiet_start_minute,
                 b.quiet_end_minute,
                 b.timezone
             FROM t_notification_subscription s
             INNER JOIN t_notification_subscriber b
                ON b.subscriber_id = s.subscriber_id
             WHERE s.shop_id = :shop_id
               AND s.cast_id = :cast_id
               AND s.status = 'active'
               AND s.pause_reason IS NULL
               AND b.status = 'active'
             ORDER BY s.subscriber_id, s.subscription_id",
            [
                'shop_id' => $shopId,
                'cast_id' => $castId,
            ]
        );
    }

    public function markUnsubscribed(int $subscriptionId): void
    {
        $this->prepareAndExecute(
            "UPDATE t_notification_subscription
             SET status = 'unsubscribed',
                 pause_reason = NULL,
                 paused_at = NULL,
                 unsubscribed_at = UTC_TIMESTAMP(6)
             WHERE subscription_id = :subscription_id",
            ['subscription_id' => $subscriptionId]
        );
    }

    public function markAllUnsubscribedBySubscriber(int $subscriberId): int
    {
        $statement = $this->prepareAndExecute(
            "UPDATE t_notification_subscription
             SET status = 'unsubscribed',
                 pause_reason = NULL,
                 paused_at = NULL,
                 unsubscribed_at = UTC_TIMESTAMP(6)
             WHERE subscriber_id = :subscriber_id
               AND status IN ('active', 'pending', 'paused')",
            ['subscriber_id' => $subscriberId]
        );

        return $statement->rowCount();
    }

    public function countActiveByCast(string $shopId, int $castId): int
    {
        return (int) ($this->fetchOne(
            "SELECT COUNT(*) AS target_count
             FROM t_notification_subscription
             WHERE shop_id = :shop_id
               AND cast_id = :cast_id
               AND status = 'active'
               AND pause_reason IS NULL
               AND NOT EXISTS (
                   SELECT 1
                   FROM (
                       SELECT subscriber_id, shop_id, cast_id
                       FROM t_notification_subscription
                       GROUP BY subscriber_id, shop_id, cast_id
                       HAVING COUNT(*) > 1
                   ) duplicate_groups
                   WHERE duplicate_groups.subscriber_id = t_notification_subscription.subscriber_id
                     AND duplicate_groups.shop_id = t_notification_subscription.shop_id
                     AND duplicate_groups.cast_id = t_notification_subscription.cast_id
               )",
            [
                'shop_id' => $shopId,
                'cast_id' => $castId,
            ]
        )['target_count'] ?? 0);
    }

    public function countCastHiddenPausedByCast(string $shopId, int $castId): int
    {
        return (int) ($this->fetchOne(
            "SELECT COUNT(*) AS target_count
             FROM t_notification_subscription
             WHERE shop_id = :shop_id
               AND cast_id = :cast_id
               AND status = 'paused'
               AND pause_reason = 'cast_hidden'
               AND NOT EXISTS (
                   SELECT 1
                   FROM (
                       SELECT subscriber_id, shop_id, cast_id
                       FROM t_notification_subscription
                       GROUP BY subscriber_id, shop_id, cast_id
                       HAVING COUNT(*) > 1
                   ) duplicate_groups
                   WHERE duplicate_groups.subscriber_id = t_notification_subscription.subscriber_id
                     AND duplicate_groups.shop_id = t_notification_subscription.shop_id
                     AND duplicate_groups.cast_id = t_notification_subscription.cast_id
               )",
            [
                'shop_id' => $shopId,
                'cast_id' => $castId,
            ]
        )['target_count'] ?? 0);
    }

    public function pauseActiveByCastHidden(string $shopId, int $castId): int
    {
        $statement = $this->prepareAndExecute(
            "UPDATE t_notification_subscription
             SET status = 'paused',
                 pause_reason = 'cast_hidden',
                 paused_at = UTC_TIMESTAMP(6)
             WHERE subscription_id IN (
                 SELECT subscription_id
                 FROM (
                     SELECT target.subscription_id
                     FROM t_notification_subscription target
                     LEFT JOIN (
                         SELECT subscriber_id, shop_id, cast_id
                         FROM t_notification_subscription
                         GROUP BY subscriber_id, shop_id, cast_id
                         HAVING COUNT(*) > 1
                     ) duplicate_groups
                        ON duplicate_groups.subscriber_id = target.subscriber_id
                       AND duplicate_groups.shop_id = target.shop_id
                       AND duplicate_groups.cast_id = target.cast_id
                     WHERE target.shop_id = :shop_id
                       AND target.cast_id = :cast_id
                       AND target.status = 'active'
                       AND target.pause_reason IS NULL
                       AND duplicate_groups.subscriber_id IS NULL
                 ) eligible_subscriptions
             )",
            [
                'shop_id' => $shopId,
                'cast_id' => $castId,
            ]
        );

        return $statement->rowCount();
    }

    public function restoreCastHiddenPausedByCast(string $shopId, int $castId): int
    {
        $statement = $this->prepareAndExecute(
            "UPDATE t_notification_subscription
             SET status = 'active',
                 pause_reason = NULL,
                 paused_at = NULL
             WHERE subscription_id IN (
                 SELECT subscription_id
                 FROM (
                     SELECT target.subscription_id
                     FROM t_notification_subscription target
                     LEFT JOIN (
                         SELECT subscriber_id, shop_id, cast_id
                         FROM t_notification_subscription
                         GROUP BY subscriber_id, shop_id, cast_id
                         HAVING COUNT(*) > 1
                     ) duplicate_groups
                        ON duplicate_groups.subscriber_id = target.subscriber_id
                       AND duplicate_groups.shop_id = target.shop_id
                       AND duplicate_groups.cast_id = target.cast_id
                     WHERE target.shop_id = :shop_id
                       AND target.cast_id = :cast_id
                       AND target.status = 'paused'
                       AND target.pause_reason = 'cast_hidden'
                       AND duplicate_groups.subscriber_id IS NULL
                 ) eligible_subscriptions
             )",
            [
                'shop_id' => $shopId,
                'cast_id' => $castId,
            ]
        );

        return $statement->rowCount();
    }

    public function findPublicStateReconciliationUpperKey(): ?array
    {
        return $this->fetchOne(
            "SELECT shop_id, cast_id
             FROM (
                 SELECT shop_id, cast_id
                 FROM t_notification_subscription s
                 WHERE s.shop_id IN (" . self::VALID_PUBLIC_STATE_SHOPS . ")
                   AND (
                       s.status = 'active'
                       OR (s.status = 'paused' AND s.pause_reason = 'cast_hidden')
                   )
                   AND NOT EXISTS (
                       SELECT 1
                       FROM (
                           SELECT subscriber_id, shop_id, cast_id
                           FROM t_notification_subscription
                           GROUP BY subscriber_id, shop_id, cast_id
                           HAVING COUNT(*) > 1
                       ) duplicate_groups
                       WHERE duplicate_groups.subscriber_id = s.subscriber_id
                         AND duplicate_groups.shop_id = s.shop_id
                         AND duplicate_groups.cast_id = s.cast_id
                   )
                 UNION
                 SELECT e.shop_id, e.cast_id
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
             ) candidates
             ORDER BY shop_id DESC, cast_id DESC
             LIMIT 1"
        );
    }

    public function fetchPublicStateReconciliationCandidateKeys(
        string $lastShopId,
        int $lastCastId,
        string $upperShopId,
        int $upperCastId,
        int $limit
    ): array {
        return $this->fetchAll(
            "SELECT shop_id, cast_id
             FROM (
                 SELECT shop_id, cast_id
                 FROM t_notification_subscription s
                 WHERE s.shop_id IN (" . self::VALID_PUBLIC_STATE_SHOPS . ")
                   AND (
                       s.status = 'active'
                       OR (s.status = 'paused' AND s.pause_reason = 'cast_hidden')
                   )
                   AND NOT EXISTS (
                       SELECT 1
                       FROM (
                           SELECT subscriber_id, shop_id, cast_id
                           FROM t_notification_subscription
                           GROUP BY subscriber_id, shop_id, cast_id
                           HAVING COUNT(*) > 1
                       ) duplicate_groups
                       WHERE duplicate_groups.subscriber_id = s.subscriber_id
                         AND duplicate_groups.shop_id = s.shop_id
                         AND duplicate_groups.cast_id = s.cast_id
                   )
                 UNION
                 SELECT e.shop_id, e.cast_id
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
             ) candidates
             WHERE (shop_id > :last_shop_id_gt OR (shop_id = :last_shop_id_eq AND cast_id > :last_cast_id))
               AND (shop_id < :upper_shop_id_lt OR (shop_id = :upper_shop_id_eq AND cast_id <= :upper_cast_id))
             ORDER BY shop_id, cast_id
             LIMIT :limit",
            [
                'last_shop_id_gt' => $lastShopId,
                'last_shop_id_eq' => $lastShopId,
                'last_cast_id' => $lastCastId,
                'upper_shop_id_lt' => $upperShopId,
                'upper_shop_id_eq' => $upperShopId,
                'upper_cast_id' => $upperCastId,
                'limit' => $limit,
            ]
        );
    }

    public function maxSubscriptionId(): int
    {
        return (int) ($this->fetchOne(
            'SELECT COALESCE(MAX(subscription_id), 0) AS max_subscription_id FROM t_notification_subscription'
        )['max_subscription_id'] ?? 0);
    }

    public function countPublicStateConsistencyAnomalies(int $upperSubscriptionId): array
    {
        if ($upperSubscriptionId < 1) {
            return [
                'invalid_status_pause' => 0,
                'duplicate_subscription' => 0,
                'unknown_shop' => 0,
            ];
        }

        $invalid = (int) ($this->fetchOne(
            "SELECT COUNT(*) AS anomaly_count
             FROM t_notification_subscription
             WHERE subscription_id <= :upper_subscription_id
               AND (
                   status NOT IN ('pending', 'active', 'paused', 'unsubscribed')
                   OR (status = 'paused' AND (pause_reason IS NULL OR pause_reason NOT IN ('cast_hidden', 'user_paused', 'admin_paused')))
                   OR (status <> 'paused' AND pause_reason IS NOT NULL)
               )",
            ['upper_subscription_id' => $upperSubscriptionId]
        )['anomaly_count'] ?? 0);

        $duplicates = (int) ($this->fetchOne(
            "SELECT COUNT(*) AS anomaly_count
             FROM (
                 SELECT subscriber_id, shop_id, cast_id
                 FROM t_notification_subscription
                 WHERE subscription_id <= :upper_subscription_id
                 GROUP BY subscriber_id, shop_id, cast_id
                 HAVING COUNT(*) > 1
             ) duplicate_groups",
            ['upper_subscription_id' => $upperSubscriptionId]
        )['anomaly_count'] ?? 0);

        $unknownShops = (int) ($this->fetchOne(
            "SELECT COUNT(*) AS anomaly_count
             FROM t_notification_subscription
             WHERE subscription_id <= :upper_subscription_id
               AND shop_id NOT IN ('yh-dandy', 'yh-clubd', 'yh-mrd')",
            ['upper_subscription_id' => $upperSubscriptionId]
        )['anomaly_count'] ?? 0);

        return [
            'invalid_status_pause' => $invalid,
            'duplicate_subscription' => $duplicates,
            'unknown_shop' => $unknownShops,
        ];
    }

    public function fetchPublicStateConsistencyRows(
        int $lastSubscriptionId,
        int $upperSubscriptionId,
        int $limit
    ): array {
        if ($upperSubscriptionId < 1) {
            return [];
        }

        return $this->fetchAll(
            "SELECT subscription_id, subscriber_id, shop_id, cast_id, status, pause_reason
             FROM t_notification_subscription
             WHERE subscription_id > :last_subscription_id
               AND subscription_id <= :upper_subscription_id
             ORDER BY subscription_id
             LIMIT :limit",
            [
                'last_subscription_id' => $lastSubscriptionId,
                'upper_subscription_id' => $upperSubscriptionId,
                'limit' => $limit,
            ]
        );
    }

    public function countDuplicateLogicalSubscriptionGroups(int $upperSubscriptionId): int
    {
        if ($upperSubscriptionId < 1) {
            return 0;
        }

        return (int) ($this->fetchOne(
            "SELECT COUNT(*) AS anomaly_count
             FROM (
                 SELECT subscriber_id, shop_id, cast_id
                 FROM t_notification_subscription
                 WHERE subscription_id <= :upper_subscription_id
                 GROUP BY subscriber_id, shop_id, cast_id
                 HAVING COUNT(*) > 1
             ) duplicate_groups",
            ['upper_subscription_id' => $upperSubscriptionId]
        )['anomaly_count'] ?? 0);
    }
}
