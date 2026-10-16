<?php
declare(strict_types=1);

namespace DandyNotification\Repository;

final class SubscriberRepository extends AbstractRepository
{
    public function findById(int $subscriberId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM t_notification_subscriber WHERE subscriber_id = :subscriber_id LIMIT 1',
            ['subscriber_id' => $subscriberId]
        );
    }

    public function findByEmail(string $email): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM t_notification_subscriber WHERE email = :email LIMIT 1',
            ['email' => $email]
        );
    }

    public function findByEmailForUpdate(string $email): ?array
    {
        return $this->fetchOne(
            'SELECT subscriber_id, email, status, registration_source, last_registration_request_at, unsubscribed_at, suppressed_at
             FROM t_notification_subscriber
             WHERE email = :email
             LIMIT 1
             FOR UPDATE',
            ['email' => $email]
        );
    }

    public function findByEmailForSettingsLinkUpdate(string $email): ?array
    {
        return $this->fetchOne(
            'SELECT subscriber_id, email, status, registration_source, last_settings_token_requested_at, unsubscribed_at, suppressed_at
             FROM t_notification_subscriber
             WHERE email = :email
             LIMIT 1
             FOR UPDATE',
            ['email' => $email]
        );
    }

    public function findByIdForUpdate(int $subscriberId): ?array
    {
        return $this->fetchOne(
            'SELECT subscriber_id, email, status, registration_source, verified_at, consented_at, consent_version, unsubscribed_at, suppressed_at
             FROM t_notification_subscriber
             WHERE subscriber_id = :subscriber_id
             LIMIT 1
             FOR UPDATE',
            ['subscriber_id' => $subscriberId]
        );
    }

    public function findByIdForSettings(int $subscriberId): ?array
    {
        return $this->fetchOne(
            'SELECT subscriber_id, email, status, quiet_hours_enabled, quiet_start_minute, quiet_end_minute, updated_at, unsubscribed_at, suppressed_at
             FROM t_notification_subscriber
             WHERE subscriber_id = :subscriber_id
             LIMIT 1',
            ['subscriber_id' => $subscriberId]
        );
    }

    public function findByIdForSettingsUpdate(int $subscriberId): ?array
    {
        return $this->fetchOne(
            'SELECT subscriber_id, email, status, quiet_hours_enabled, quiet_start_minute, quiet_end_minute, updated_at, unsubscribed_at, suppressed_at
             FROM t_notification_subscriber
             WHERE subscriber_id = :subscriber_id
             LIMIT 1
             FOR UPDATE',
            ['subscriber_id' => $subscriberId]
        );
    }

    public function activateAfterEmailVerification(int $subscriberId, string $consentVersion): void
    {
        $this->prepareAndExecute(
            "UPDATE t_notification_subscriber
             SET status = 'active',
                 verified_at = COALESCE(verified_at, UTC_TIMESTAMP(6)),
                 consented_at = UTC_TIMESTAMP(6),
                 consent_version = :consent_version,
                 unsubscribed_at = NULL
             WHERE subscriber_id = :subscriber_id",
            [
                'subscriber_id' => $subscriberId,
                'consent_version' => $consentVersion,
            ]
        );
    }

    public function insertPending(string $email): int
    {
        $this->prepareAndExecute(
            "INSERT INTO t_notification_subscriber
                (email, status, registration_source, last_registration_request_at)
             VALUES
                (:email, 'pending', 'empty_mail', UTC_TIMESTAMP(6))",
            ['email' => $email]
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function markPendingFromEmptyMail(int $subscriberId): void
    {
        $this->prepareAndExecute(
            "UPDATE t_notification_subscriber
             SET status = 'pending',
                 registration_source = 'empty_mail',
                 unsubscribed_at = NULL,
                 last_registration_request_at = UTC_TIMESTAMP(6)
             WHERE subscriber_id = :subscriber_id",
            ['subscriber_id' => $subscriberId]
        );
    }

    public function touchRegistrationRequest(int $subscriberId): void
    {
        $this->prepareAndExecute(
            'UPDATE t_notification_subscriber
             SET last_registration_request_at = UTC_TIMESTAMP(6)
             WHERE subscriber_id = :subscriber_id',
            ['subscriber_id' => $subscriberId]
        );
    }

    public function touchSettingsTokenRequest(int $subscriberId): void
    {
        $this->prepareAndExecute(
            'UPDATE t_notification_subscriber
             SET last_settings_token_requested_at = UTC_TIMESTAMP(6)
             WHERE subscriber_id = :subscriber_id',
            ['subscriber_id' => $subscriberId]
        );
    }

    public function updateQuietHours(int $subscriberId, bool $enabled, ?int $startMinute, ?int $endMinute): void
    {
        $this->prepareAndExecute(
            'UPDATE t_notification_subscriber
             SET quiet_hours_enabled = :enabled,
                 quiet_start_minute = :start_minute,
                 quiet_end_minute = :end_minute
             WHERE subscriber_id = :subscriber_id',
            [
                'subscriber_id' => $subscriberId,
                'enabled' => $enabled ? 1 : 0,
                'start_minute' => $startMinute,
                'end_minute' => $endMinute,
            ]
        );
    }

    public function markUnsubscribedAll(int $subscriberId): void
    {
        $this->prepareAndExecute(
            "UPDATE t_notification_subscriber
             SET status = 'unsubscribed',
                 unsubscribed_at = UTC_TIMESTAMP(6)
             WHERE subscriber_id = :subscriber_id",
            ['subscriber_id' => $subscriberId]
        );
    }
}
