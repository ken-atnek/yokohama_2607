<?php
declare(strict_types=1);

namespace DandyNotification\Config;

use DandyNotification\Exception\ConfigException;

final class FeatureFlags
{
    private const DEFAULTS = [
        'notification_empty_mail_receive_enabled' => false,
        'notification_confirmation_enabled' => false,
        'notification_settings_enabled' => false,
        'notification_event_enqueue_enabled' => false,
        'notification_delivery_create_enabled' => false,
        'notification_send_enabled' => false,
        'notification_dry_run' => true,
        'notification_legacy_migration_enabled' => false,
        'notification_admin_enabled' => false,
        'notification_public_state_sync_enabled' => false,
        'notification_public_state_reconciliation_enabled' => false,
    ];

    private function __construct(private readonly array $flags)
    {
    }

    public static function defaults(): self
    {
        return new self(self::DEFAULTS);
    }

    public static function fromArray(array $flags): self
    {
        $merged = self::DEFAULTS;
        foreach ($flags as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, $merged)) {
                throw new ConfigException('Unknown notification feature flag: ' . (string) $key);
            }

            if (!is_bool($value)) {
                throw new ConfigException('Notification feature flag must be boolean: ' . $key);
            }

            $merged[$key] = $value;
        }

        return new self($merged);
    }

    public function enabled(string $key): bool
    {
        return (bool) ($this->flags[$key] ?? false);
    }

    public function notificationSendEnabled(): bool
    {
        return $this->enabled('notification_send_enabled');
    }

    public function notificationEmptyMailReceiveEnabled(): bool
    {
        return $this->enabled('notification_empty_mail_receive_enabled');
    }

    public function notificationConfirmationEnabled(): bool
    {
        return $this->enabled('notification_confirmation_enabled');
    }

    public function notificationSettingsEnabled(): bool
    {
        return $this->enabled('notification_settings_enabled');
    }

    public function notificationDeliveryCreateEnabled(): bool
    {
        return $this->enabled('notification_delivery_create_enabled');
    }

    public function notificationDryRun(): bool
    {
        return (bool) ($this->flags['notification_dry_run'] ?? true);
    }

    public function notificationPublicStateSyncEnabled(): bool
    {
        return $this->enabled('notification_public_state_sync_enabled');
    }

    public function notificationPublicStateReconciliationEnabled(): bool
    {
        return $this->enabled('notification_public_state_reconciliation_enabled');
    }

    public function all(): array
    {
        return $this->flags;
    }
}
