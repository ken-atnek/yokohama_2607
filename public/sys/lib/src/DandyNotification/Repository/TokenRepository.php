<?php
declare(strict_types=1);

namespace DandyNotification\Repository;

final class TokenRepository extends AbstractRepository
{
    private const VERIFICATION_COLUMNS = 'token_id, subscriber_id, subscription_id, purpose, token_hash, request_id, expires_at, used_at, revoked_at, created_at';
    private const MANAGE_COLUMNS = 'token_id, subscriber_id, subscription_id, purpose, token_hash, request_id, expires_at, used_at, revoked_at, created_at';

    public function findById(int $tokenId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM t_notification_token WHERE token_id = :token_id LIMIT 1',
            ['token_id' => $tokenId]
        );
    }

    public function findUsableByHash(string $purpose, string $tokenHash): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM t_notification_token WHERE purpose = :purpose AND token_hash = :token_hash AND used_at IS NULL AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP(6) LIMIT 1',
            [
                'purpose' => $purpose,
                'token_hash' => $tokenHash,
            ]
        );
    }

    public function findUsableVerificationByHash(string $tokenHash): ?array
    {
        return $this->fetchOne(
            'SELECT ' . self::VERIFICATION_COLUMNS . "
             FROM t_notification_token
             WHERE purpose = 'verify_email'
               AND token_hash = :token_hash
               AND subscription_id IS NOT NULL
               AND used_at IS NULL
               AND revoked_at IS NULL
               AND expires_at > UTC_TIMESTAMP(6)
             LIMIT 1",
            ['token_hash' => $tokenHash]
        );
    }

    public function findUsableVerificationByHashForUpdate(string $tokenHash): ?array
    {
        return $this->fetchOne(
            'SELECT ' . self::VERIFICATION_COLUMNS . "
             FROM t_notification_token
             WHERE purpose = 'verify_email'
               AND token_hash = :token_hash
               AND subscription_id IS NOT NULL
               AND used_at IS NULL
               AND revoked_at IS NULL
               AND expires_at > UTC_TIMESTAMP(6)
             LIMIT 1
             FOR UPDATE",
            ['token_hash' => $tokenHash]
        );
    }

    public function findUsableManageSettingsByHash(string $tokenHash): ?array
    {
        return $this->fetchOne(
            'SELECT ' . self::MANAGE_COLUMNS . "
             FROM t_notification_token
             WHERE purpose = 'manage_settings'
               AND subscription_id IS NULL
               AND token_hash = :token_hash
               AND used_at IS NULL
               AND revoked_at IS NULL
               AND expires_at > UTC_TIMESTAMP(6)
             LIMIT 1",
            ['token_hash' => $tokenHash]
        );
    }

    public function findUsableManageSettingsByHashForUpdate(string $tokenHash): ?array
    {
        return $this->fetchOne(
            'SELECT ' . self::MANAGE_COLUMNS . "
             FROM t_notification_token
             WHERE purpose = 'manage_settings'
               AND subscription_id IS NULL
               AND token_hash = :token_hash
               AND used_at IS NULL
               AND revoked_at IS NULL
               AND expires_at > UTC_TIMESTAMP(6)
             LIMIT 1
             FOR UPDATE",
            ['token_hash' => $tokenHash]
        );
    }

    public function markVerificationUsed(int $tokenId): bool
    {
        $statement = $this->prepareAndExecute(
            "UPDATE t_notification_token
             SET used_at = UTC_TIMESTAMP(6)
             WHERE token_id = :token_id
               AND purpose = 'verify_email'
               AND subscription_id IS NOT NULL
               AND used_at IS NULL
               AND revoked_at IS NULL
               AND expires_at > UTC_TIMESTAMP(6)",
            ['token_id' => $tokenId]
        );

        return $statement->rowCount() === 1;
    }

    public function markManageSettingsUsed(int $tokenId): bool
    {
        $statement = $this->prepareAndExecute(
            "UPDATE t_notification_token
             SET used_at = UTC_TIMESTAMP(6)
             WHERE token_id = :token_id
               AND purpose = 'manage_settings'
               AND subscription_id IS NULL
               AND used_at IS NULL
               AND revoked_at IS NULL
               AND expires_at > UTC_TIMESTAMP(6)",
            ['token_id' => $tokenId]
        );

        return $statement->rowCount() === 1;
    }

    public function insertToken(
        int $subscriberId,
        ?int $subscriptionId,
        string $purpose,
        string $tokenHash,
        string $requestId
    ): int {
        $this->prepareAndExecute(
            'INSERT INTO t_notification_token
                (subscriber_id, subscription_id, purpose, token_hash, request_id, expires_at, created_at)
             VALUES
                (:subscriber_id, :subscription_id, :purpose, :token_hash, :request_id, DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 3600 SECOND), UTC_TIMESTAMP(6))',
            [
                'subscriber_id' => $subscriberId,
                'subscription_id' => $subscriptionId,
                'purpose' => $purpose,
                'token_hash' => $tokenHash,
                'request_id' => $requestId,
            ]
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function revokeById(int $tokenId): bool
    {
        $statement = $this->prepareAndExecute(
            'UPDATE t_notification_token
             SET revoked_at = UTC_TIMESTAMP(6)
             WHERE token_id = :token_id
               AND used_at IS NULL
               AND revoked_at IS NULL',
            ['token_id' => $tokenId]
        );

        return $statement->rowCount() === 1;
    }

    public function revokeUnusedVerificationTokens(int $subscriberId, int $subscriptionId): void
    {
        $this->prepareAndExecute(
            "UPDATE t_notification_token
             SET revoked_at = UTC_TIMESTAMP(6)
             WHERE subscriber_id = :subscriber_id
               AND subscription_id = :subscription_id
               AND purpose = 'verify_email'
               AND used_at IS NULL
               AND revoked_at IS NULL",
            [
                'subscriber_id' => $subscriberId,
                'subscription_id' => $subscriptionId,
            ]
        );
    }

    public function revokeUnusedBySubscriber(int $subscriberId): int
    {
        $statement = $this->prepareAndExecute(
            'UPDATE t_notification_token
             SET revoked_at = UTC_TIMESTAMP(6)
             WHERE subscriber_id = :subscriber_id
               AND used_at IS NULL
               AND revoked_at IS NULL',
            ['subscriber_id' => $subscriberId]
        );

        return $statement->rowCount();
    }
}
