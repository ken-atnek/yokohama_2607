<?php
declare(strict_types=1);

namespace DandyNotification\Service;

use DandyNotification\Exception\ValidationException;

final class NotificationEventService
{
    private const EVENT_TYPES = [
        'schedule_created',
        'schedule_start_changed',
        'schedule_changed_to_off',
        'schedule_changed_to_work',
    ];

    public function isSupportedEventType(string $eventType): bool
    {
        return in_array($eventType, self::EVENT_TYPES, true);
    }

    public function assertSupportedEventType(string $eventType): void
    {
        if (!$this->isSupportedEventType($eventType)) {
            throw new ValidationException('notification event type is not supported.');
        }
    }

    public function eventKeyHash(array $parts): string
    {
        $json = json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return hash('sha256', $json, true);
    }
}
