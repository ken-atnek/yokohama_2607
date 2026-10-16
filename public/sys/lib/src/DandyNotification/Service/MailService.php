<?php
declare(strict_types=1);

namespace DandyNotification\Service;

use DandyNotification\Config\FeatureFlags;
use DandyNotification\Log\JsonLogger;
use RuntimeException;

final class MailService
{
    public function __construct(
        private readonly FeatureFlags $featureFlags,
        private readonly JsonLogger $logger
    ) {
    }

    public function canSend(): bool
    {
        return $this->featureFlags->notificationSendEnabled()
            && !$this->featureFlags->notificationDryRun();
    }

    public function isDryRun(): bool
    {
        return $this->featureFlags->notificationDryRun();
    }

    public function send(array $delivery): void
    {
        if (!$this->canSend()) {
            $this->logger->info('notification mail send skipped', [
                'delivery_id' => $delivery['delivery_id'] ?? null,
                'mail_type' => $delivery['mail_type'] ?? null,
                'send_enabled' => $this->featureFlags->notificationSendEnabled(),
                'dry_run' => $this->featureFlags->notificationDryRun(),
            ]);

            return;
        }

        throw new RuntimeException('Real mail sending is not implemented in phase 2.');
    }
}
