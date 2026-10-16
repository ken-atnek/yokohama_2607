<?php
declare(strict_types=1);

namespace DandyNotification\Repository;

use DateTimeImmutable;
use DateTimeZone;

final class AuditRepository extends AbstractRepository
{
    public function findById(int $auditLogId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM t_notification_audit_log WHERE audit_log_id = :audit_log_id LIMIT 1',
            ['audit_log_id' => $auditLogId]
        );
    }

    public function findByRequestId(string $requestId): array
    {
        return $this->fetchAll(
            'SELECT * FROM t_notification_audit_log WHERE request_id = :request_id ORDER BY created_at, audit_log_id',
            ['request_id' => $requestId]
        );
    }

    public function insert(array $auditLog): int
    {
        return $this->insertWithActorType('cli', $auditLog);
    }

    public function insertUserAction(array $auditLog): int
    {
        return $this->insertWithActorType('user', $auditLog);
    }

    public function insertSystemAction(array $auditLog): int
    {
        return $this->insertWithActorType('system', $auditLog);
    }

    private function insertWithActorType(string $actorType, array $auditLog): int
    {
        $this->prepareAndExecute(
            'INSERT INTO t_notification_audit_log
                (subscriber_id, subscription_id, actor_type, actor_id, action, result, request_id,
                 email_hash, shop_id, cast_id, ip_hash, user_agent_hash, metadata)
             VALUES
                (:subscriber_id, :subscription_id, :actor_type, :actor_id, :action, :result, :request_id,
                 :email_hash, :shop_id, :cast_id, NULL, NULL, :metadata)',
            [
                'subscriber_id' => $auditLog['subscriber_id'] ?? null,
                'subscription_id' => $auditLog['subscription_id'] ?? null,
                'actor_type' => $actorType,
                'actor_id' => $auditLog['actor_id'] ?? null,
                'action' => $auditLog['action'],
                'result' => $auditLog['result'],
                'request_id' => $auditLog['request_id'] ?? null,
                'email_hash' => $auditLog['email_hash'] ?? null,
                'shop_id' => $auditLog['shop_id'] ?? null,
                'cast_id' => $auditLog['cast_id'] ?? null,
                'metadata' => $auditLog['metadata'] ?? '{}',
            ]
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function countRecentGlobalAttempts(int $windowSeconds): int
    {
        return (int) ($this->fetchOne(
            "SELECT COUNT(*) AS attempt_count
             FROM t_notification_audit_log
             WHERE actor_type = 'cli'
                AND action IN ('empty_mail_registered_pending', 'empty_mail_subscription_activated', 'empty_mail_already_active', 'empty_mail_verification_in_progress', 'empty_mail_ignored', 'empty_mail_rejected')
               AND created_at >= :created_after",
            ['created_after' => $this->createdAfter($windowSeconds)]
        )['attempt_count'] ?? 0);
    }

    public function countRecentByEmailHash(string $emailHash, int $windowSeconds): int
    {
        return (int) ($this->fetchOne(
            "SELECT COUNT(*) AS attempt_count
             FROM t_notification_audit_log
             WHERE actor_type = 'cli'
                AND action IN ('empty_mail_registered_pending', 'empty_mail_subscription_activated', 'empty_mail_already_active', 'empty_mail_verification_in_progress', 'empty_mail_ignored', 'empty_mail_rejected')
               AND email_hash = :email_hash
               AND created_at >= :created_after",
            [
                'email_hash' => $emailHash,
                'created_after' => $this->createdAfter($windowSeconds),
            ]
        )['attempt_count'] ?? 0);
    }

    public function countRecentByEmailHashAndCast(string $emailHash, int $castId, int $windowSeconds): int
    {
        return (int) ($this->fetchOne(
            "SELECT COUNT(*) AS attempt_count
             FROM t_notification_audit_log
             WHERE actor_type = 'cli'
                AND action IN ('empty_mail_registered_pending', 'empty_mail_subscription_activated', 'empty_mail_already_active', 'empty_mail_verification_in_progress', 'empty_mail_ignored', 'empty_mail_rejected')
               AND email_hash = :email_hash
               AND cast_id = :cast_id
               AND created_at >= :created_after",
            [
                'email_hash' => $emailHash,
                'cast_id' => $castId,
                'created_after' => $this->createdAfter($windowSeconds),
            ]
        )['attempt_count'] ?? 0);
    }

    private function createdAfter(int $windowSeconds): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-' . $windowSeconds . ' seconds')
            ->format('Y-m-d H:i:s.u');
    }
}
