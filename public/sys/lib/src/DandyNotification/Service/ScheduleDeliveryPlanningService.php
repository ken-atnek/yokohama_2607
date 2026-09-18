<?php
declare(strict_types=1);

namespace DandyNotification\Service;

use DateTimeImmutable;
use DateTimeZone;
use DandyNotification\Exception\EventDataValidationException;
use DandyNotification\Exception\TargetPlanningException;
use JsonException;

final class ScheduleDeliveryPlanningService
{
    private const UTC_TIMEZONE = 'UTC';
    private const DEFAULT_TIMEZONE = 'Asia/Tokyo';

    private const DISPLAY_REASONS = [
        ScheduleEventDecisionService::EVENT_SCHEDULE_CREATED => '出勤予定が決まりました',
        ScheduleEventDecisionService::EVENT_SCHEDULE_START_CHANGED => '出勤開始時刻が変更されました',
        ScheduleEventDecisionService::EVENT_SCHEDULE_CHANGED_TO_OFF => '出勤予定がお休みに変更されました',
        ScheduleEventDecisionService::EVENT_SCHEDULE_CHANGED_TO_WORK => 'お休みから出勤へ変更されました',
    ];

    public function generateMessageGroupKey(): string
    {
        $key = random_bytes(16);
        if (strlen($key) !== 16) {
            throw new \RuntimeException('message_group_key generation failed.');
        }

        return $key;
    }

    public function calculateAvailableAtUtc(
        DateTimeImmutable $queueCreatedAtUtc,
        bool $quietHoursEnabled,
        ?int $quietStartMinute,
        ?int $quietEndMinute,
        string $timezone
    ): DateTimeImmutable {
        $queueCreatedAtUtc = $queueCreatedAtUtc->setTimezone($this->utcTimeZone());
        if (!$quietHoursEnabled) {
            return $queueCreatedAtUtc;
        }

        if ($quietStartMinute === null
            || $quietEndMinute === null
            || $quietStartMinute < 0
            || $quietStartMinute > 1439
            || $quietEndMinute < 0
            || $quietEndMinute > 1439
            || $quietStartMinute === $quietEndMinute
        ) {
            throw TargetPlanningException::invalidQuietHours();
        }

        $timeZone = $this->timeZone($timezone);
        $local = $queueCreatedAtUtc->setTimezone($timeZone);
        $currentMinute = ((int) $local->format('G')) * 60 + (int) $local->format('i');
        $inQuietHours = $quietStartMinute < $quietEndMinute
            ? ($currentMinute >= $quietStartMinute && $currentMinute < $quietEndMinute)
            : ($currentMinute >= $quietStartMinute || $currentMinute < $quietEndMinute);

        if (!$inQuietHours) {
            return $queueCreatedAtUtc;
        }

        $endLocal = $this->localMinuteDateTime($local, $quietEndMinute, $timeZone);
        if ($quietStartMinute > $quietEndMinute && $currentMinute >= $quietStartMinute) {
            $endLocal = $endLocal->modify('+1 day');
        }

        return $endLocal->setTimezone($this->utcTimeZone());
    }

    public function deliveryBaseDate(DateTimeImmutable $availableAtUtc, string $timezone): string
    {
        return $availableAtUtc
            ->setTimezone($this->timeZone($timezone))
            ->format('Y-m-d');
    }

    public function displayReason(string $eventType): string
    {
        if (!array_key_exists($eventType, self::DISPLAY_REASONS)) {
            throw new EventDataValidationException('Unsupported schedule event type.');
        }

        return self::DISPLAY_REASONS[$eventType];
    }

    public function displaySummary(array $events): array
    {
        if ($events === []) {
            throw new \InvalidArgumentException('summary events must not be empty.');
        }

        usort(
            $events,
            static fn (array $a, array $b): int => [
                (string) ($a['occurred_at'] ?? ''),
                (int) ($a['event_id'] ?? 0),
            ] <=> [
                (string) ($b['occurred_at'] ?? ''),
                (int) ($b['event_id'] ?? 0),
            ]
        );

        $eventTypes = [];
        foreach ($events as $event) {
            $eventType = (string) ($event['event_type'] ?? '');
            if (!in_array($eventType, $eventTypes, true)) {
                $eventTypes[] = $eventType;
            }
        }

        $latest = $events[array_key_last($events)];
        $displayEventType = (string) $latest['event_type'];

        return [
            'display_event_type' => $displayEventType,
            'display_reason' => $this->displayReason($displayEventType),
            'latest_event_id' => (int) $latest['event_id'],
            'event_count' => count($events),
            'event_types' => $eventTypes,
        ];
    }

    public function buildTemplateData(
        string $requestId,
        int $updateCount,
        string $messageGroupKey,
        DateTimeImmutable $queueCreatedAtUtc,
        DateTimeImmutable $availableAtUtc,
        string $deliveryBaseDate,
        array $event,
        array $summary,
        array $nextSchedules
    ): array {
        return [
            'request_id' => $requestId,
            'update_count' => $updateCount,
            'message_group_key' => bin2hex($messageGroupKey),
            'created_at' => $this->formatUtcForSnapshot($queueCreatedAtUtc),
            'available_at' => $this->formatUtcForSnapshot($availableAtUtc),
            'delivery_base_date' => $deliveryBaseDate,
            'events' => [$this->eventSnapshot($event)],
            'summary' => $summary,
            'next_schedules' => $this->nextScheduleSnapshots($nextSchedules),
        ];
    }

    private function eventSnapshot(array $event): array
    {
        $before = $this->decodePayload($event['before_payload'] ?? null);
        $after = $this->decodePayload($event['after_payload'] ?? null);

        return [
            'event_id' => (int) $event['event_id'],
            'event_type' => (string) $event['event_type'],
            'shop_id' => (string) $event['shop_id'],
            'cast_id' => (int) $event['cast_id'],
            'shop_name' => $this->displayName($event['shop_name_snapshot'] ?? null, (string) $event['shop_id']),
            'cast_name' => $this->displayName($event['cast_name_snapshot'] ?? null, (string) $event['cast_id']),
            'schedule_date' => $this->scheduleDate($event['schedule_date'] ?? ''),
            'before_status' => $this->nullablePayloadString($before, 'state'),
            'before_start_time' => $this->nullablePayloadString($before, 'start_time'),
            'before_end_time' => $this->nullablePayloadString($before, 'end_time'),
            'before_special_code' => $this->nullablePayloadString($before, 'special_code'),
            'after_status' => $this->nullablePayloadString($after, 'state'),
            'after_start_time' => $this->nullablePayloadString($after, 'start_time'),
            'after_end_time' => $this->nullablePayloadString($after, 'end_time'),
            'after_special_code' => $this->nullablePayloadString($after, 'special_code'),
        ];
    }

    private function nextScheduleSnapshots(array $nextSchedules): array
    {
        return array_map(
            static fn (array $schedule): array => [
                'schedule_date' => (string) $schedule['schedule_date'],
                'start_time' => (string) $schedule['start_time'],
                'end_time' => (string) $schedule['end_time'],
            ],
            $nextSchedules
        );
    }

    private function decodePayload(mixed $payload): array
    {
        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
    }

    private function nullablePayloadString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function scheduleDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    private function displayName(mixed $value, string $fallback): string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return $fallback;
    }

    private function localMinuteDateTime(DateTimeImmutable $local, int $minute, DateTimeZone $timeZone): DateTimeImmutable
    {
        return new DateTimeImmutable(
            sprintf('%s %02d:%02d:00', $local->format('Y-m-d'), intdiv($minute, 60), $minute % 60),
            $timeZone
        );
    }

    private function formatUtcForSnapshot(DateTimeImmutable $dateTime): string
    {
        return $dateTime
            ->setTimezone($this->utcTimeZone())
            ->format('Y-m-d\TH:i:s\Z');
    }

    private function timeZone(string $timezone): DateTimeZone
    {
        $timezone = trim($timezone) !== '' ? $timezone : self::DEFAULT_TIMEZONE;
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw TargetPlanningException::invalidTimezone();
        }

        return new DateTimeZone($timezone);
    }

    private function utcTimeZone(): DateTimeZone
    {
        return new DateTimeZone(self::UTC_TIMEZONE);
    }
}
