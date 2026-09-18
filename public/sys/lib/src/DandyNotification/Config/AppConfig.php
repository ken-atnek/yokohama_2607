<?php
declare(strict_types=1);

namespace DandyNotification\Config;

final class AppConfig
{
    public function __construct(private readonly array $values)
    {
    }

    public function database(): array
    {
        return $this->values['database'];
    }

    public function appEnvironment(): string
    {
        return (string) $this->values['app']['environment'];
    }

    public function appTimezone(): string
    {
        return (string) $this->values['app']['timezone'];
    }

    public function sessionConfig(): array
    {
        return [
            'name' => (string) $this->values['app']['session_name'],
            'cookie' => $this->values['app']['session_cookie'],
        ];
    }

    public function logPath(): string
    {
        return (string) $this->values['log']['path'];
    }

    public function logLevel(): string
    {
        return (string) $this->values['log']['level'];
    }

    public function featureFlags(): FeatureFlags
    {
        return FeatureFlags::fromArray($this->values['feature']);
    }

    public function emptyMailConfig(): array
    {
        return $this->values['empty_mail'];
    }

    public function notificationUrlConfig(): array
    {
        return $this->values['notification_url'];
    }

    public function mailEnvelopeFrom(): ?string
    {
        $value = $this->values['mail']['envelope_from'] ?? null;
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    public function confirmationConfig(): array
    {
        return $this->values['confirmation'];
    }

    public function settingsConfig(): array
    {
        return $this->values['settings'];
    }

    public function publicStateReconciliationBatchSize(): int
    {
        return (int) $this->values['public_state']['notification_public_state_reconciliation_batch_size'];
    }

    public function deliveryCreateBatchSize(): int
    {
        return (int) $this->values['delivery_create']['notification_delivery_create_batch_size'];
    }

    public function deliverySendBatchSize(): int
    {
        return (int) $this->values['delivery_send']['notification_send_batch_size'];
    }

    public function deliverySendStaleThresholdSeconds(): int
    {
        return (int) $this->values['delivery_send']['notification_send_stale_threshold_seconds'];
    }

    public function all(): array
    {
        return $this->values;
    }
}
