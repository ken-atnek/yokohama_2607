<?php
declare(strict_types=1);

namespace DandyNotification\Repository;

use PDOException;

final class EventRepository extends AbstractRepository
{
    public function findById(int $eventId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM t_notification_event WHERE event_id = :event_id LIMIT 1',
            ['event_id' => $eventId]
        );
    }

    public function findByEventKey(string $eventKey): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM t_notification_event WHERE event_key = :event_key LIMIT 1',
            ['event_key' => $eventKey]
        );
    }

    public function findPendingBatch(int $limit): array
    {
        $limit = max(1, min($limit, 500));

        return $this->fetchAll(
            "SELECT * FROM t_notification_event WHERE status = 'pending' AND available_at <= UTC_TIMESTAMP(6) ORDER BY available_at, event_id LIMIT :limit",
            ['limit' => $limit]
        );
    }

    public function claimPendingBatch(array $eventIds, string $lockedBy): int
    {
        if ($eventIds === []) {
            return 0;
        }

        $params = ['locked_by' => $lockedBy];
        $placeholders = $this->integerPlaceholders('event_id', $eventIds, $params);
        $statement = $this->prepareAndExecute(
            "UPDATE t_notification_event
             SET status = 'processing',
                 locked_at = UTC_TIMESTAMP(6),
                 locked_by = :locked_by
             WHERE status = 'pending'
               AND event_id IN ({$placeholders})",
            $params
        );

        return $statement->rowCount();
    }

    public function markProcessed(array $eventIds): int
    {
        if ($eventIds === []) {
            return 0;
        }

        $params = [];
        $placeholders = $this->integerPlaceholders('event_id', $eventIds, $params);
        $statement = $this->prepareAndExecute(
            "UPDATE t_notification_event
             SET status = 'processed',
                 processed_at = UTC_TIMESTAMP(6),
                 failed_at = NULL,
                 last_error = NULL,
                 locked_at = NULL,
                 locked_by = NULL
             WHERE event_id IN ({$placeholders})",
            $params
        );

        return $statement->rowCount();
    }

    public function recordProcessingFailure(int $eventId, string $lastError): int
    {
        $statement = $this->prepareAndExecute(
            "UPDATE t_notification_event
             SET status = CASE
                     WHEN attempt_count + 1 >= max_attempts THEN 'failed'
                     ELSE 'pending'
                 END,
                 failed_at = CASE
                     WHEN attempt_count + 1 >= max_attempts THEN UTC_TIMESTAMP(6)
                     ELSE failed_at
                 END,
                 attempt_count = attempt_count + 1,
                 last_error = :last_error,
                 locked_at = NULL,
                 locked_by = NULL
             WHERE event_id = :event_id
               AND status <> 'processed'",
            [
                'event_id' => $eventId,
                'last_error' => $lastError,
            ]
        );

        return $statement->rowCount();
    }

    public function insertScheduleEvent(array $event): bool
    {
        try {
            $this->prepareAndExecute(
                "INSERT INTO t_notification_event
                    (event_key, event_type, source_type, shop_id, cast_id, shop_name_snapshot, cast_name_snapshot,
                     schedule_date, occurred_at, before_payload, after_payload)
                 VALUES
                    (:event_key, :event_type, :source_type, :shop_id, :cast_id, :shop_name_snapshot, :cast_name_snapshot,
                     :schedule_date, UTC_TIMESTAMP(6), :before_payload, :after_payload)",
                [
                    'event_key' => $event['event_key'],
                    'event_type' => $event['event_type'],
                    'source_type' => $event['source_type'],
                    'shop_id' => $event['shop_id'],
                    'cast_id' => $event['cast_id'],
                    'shop_name_snapshot' => $event['shop_name_snapshot'],
                    'cast_name_snapshot' => $event['cast_name_snapshot'],
                    'schedule_date' => $event['schedule_date'],
                    'before_payload' => $event['before_payload'],
                    'after_payload' => $event['after_payload'],
                ]
            );
        } catch (PDOException $exception) {
            if ($this->isDuplicateKey($exception)) {
                return false;
            }

            throw $exception;
        }

        return true;
    }

    private function integerPlaceholders(string $prefix, array $values, array &$params): string
    {
        $placeholders = [];
        foreach (array_values($values) as $index => $value) {
            $name = $prefix . '_' . $index;
            $placeholders[] = ':' . $name;
            $params[$name] = (int) $value;
        }

        return implode(', ', $placeholders);
    }

    private function isDuplicateKey(PDOException $exception): bool
    {
        $sqlState = is_string($exception->errorInfo[0] ?? null)
            ? (string) $exception->errorInfo[0]
            : (string) $exception->getCode();
        $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;

        return $sqlState === '23000' && $driverCode === 1062;
    }
}
