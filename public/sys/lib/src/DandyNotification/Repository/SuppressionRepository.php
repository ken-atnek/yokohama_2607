<?php
declare(strict_types=1);

namespace DandyNotification\Repository;

final class SuppressionRepository extends AbstractRepository
{
    public function findById(int $suppressionId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM t_notification_suppression WHERE suppression_id = :suppression_id LIMIT 1',
            ['suppression_id' => $suppressionId]
        );
    }

    public function findActiveByEmail(string $email): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM t_notification_suppression WHERE email = :email AND status = 'active' AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP(6)) LIMIT 1",
            ['email' => $email]
        );
    }

    public function existsActiveByEmail(string $email): bool
    {
        return $this->fetchOne(
            "SELECT suppression_id
             FROM t_notification_suppression
             WHERE email = :email
               AND status = 'active'
               AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP(6))
             LIMIT 1",
            ['email' => $email]
        ) !== null;
    }
}
