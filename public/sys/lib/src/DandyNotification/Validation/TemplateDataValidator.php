<?php
declare(strict_types=1);

namespace DandyNotification\Validation;

use DandyNotification\Exception\ValidationException;
use JsonException;

final class TemplateDataValidator
{
    private const MAX_STRING_LENGTH = 255;
    private const MAX_JSON_BYTES = 16384;
    private const MAX_ARRAY_ITEMS = 500;
    private const MAX_NESTING_DEPTH = 5;
    private const MAX_SCHEDULE_EVENTS = 500;
    private const MAX_SCHEDULE_SHOPS = 20;
    private const MAX_NEXT_SCHEDULES = 3;
    private const MAX_EVENT_TYPES = 4;

    private const MAIL_SCHEMAS = [
        'verify_email' => [
            'required' => ['request_id', 'shop_id', 'cast_id', 'shop_name', 'cast_name', 'token_ttl_seconds'],
            'optional' => [],
        ],
        'registration_complete' => [
            'required' => ['request_id', 'shop_id', 'cast_id', 'shop_name', 'cast_name'],
            'optional' => [],
        ],
        'subscription_added' => [
            'required' => ['request_id', 'shop_id', 'cast_id', 'shop_name', 'cast_name'],
            'optional' => [],
        ],
        'subscription_removed' => [
            'required' => ['request_id', 'shop_id', 'cast_id', 'shop_name', 'cast_name'],
            'optional' => [],
        ],
        'settings_link' => [
            'required' => ['request_id', 'token_ttl_seconds'],
            'optional' => [],
        ],
        'unsubscribe_all' => [
            'required' => ['request_id'],
            'optional' => [],
        ],
        'schedule_update' => [
            'required' => ['request_id', 'update_count', 'events'],
            'optional' => ['message_group_key', 'shops', 'created_at', 'available_at', 'delivery_base_date', 'summary', 'next_schedules'],
        ],
    ];

    private const SCHEDULE_EVENT_SCHEMA = [
        'required' => ['event_type', 'shop_id', 'cast_id', 'shop_name', 'cast_name', 'schedule_date'],
        'optional' => [
            'event_id',
            'before_start_time',
            'before_end_time',
            'after_start_time',
            'after_end_time',
            'before_status',
            'after_status',
            'before_special_code',
            'after_special_code',
        ],
    ];

    private const SCHEDULE_SHOP_SCHEMA = [
        'required' => ['shop_id', 'shop_name', 'event_count'],
        'optional' => ['events'],
    ];

    private const SCHEDULE_SUMMARY_SCHEMA = [
        'required' => ['display_event_type', 'display_reason', 'latest_event_id', 'event_count', 'event_types'],
        'optional' => [],
    ];

    private const NEXT_SCHEDULE_SCHEMA = [
        'required' => ['schedule_date', 'start_time', 'end_time'],
        'optional' => [],
    ];

    private const EVENT_TYPES = [
        'schedule_created',
        'schedule_start_changed',
        'schedule_changed_to_off',
        'schedule_changed_to_work',
    ];

    private const FORBIDDEN_KEY_PARTS = [
        'token',
        'raw',
        'password',
        'secret',
        'url',
        'uri',
        'link',
        'body',
        'message',
        'content',
        'html',
        'text',
        'email',
        'recipient',
        'address',
        'authorization',
        'credential',
        'api_key',
        'cookie',
        'session',
        'header',
    ];

    private const FORBIDDEN_KEY_EXCEPTIONS = [
        'message_group_key',
        'token_ttl_seconds',
    ];

    public function validate(string $mailType, array $data): array
    {
        if (!array_key_exists($mailType, self::MAIL_SCHEMAS)) {
            throw new ValidationException('mail_type is not supported.');
        }

        $this->assertSafeTree($data, 0);
        $this->assertObjectSchema(
            $data,
            self::MAIL_SCHEMAS[$mailType]['required'],
            self::MAIL_SCHEMAS[$mailType]['optional'],
            $mailType
        );

        if ($mailType === 'schedule_update') {
            $this->validateScheduleUpdate($data);
        } else {
            foreach ($data as $key => $value) {
                $this->assertCommonField((string) $key, $value);
            }
        }

        $this->assertJsonSize($data);

        return $data;
    }

    public function encode(string $mailType, array $data): string
    {
        try {
            return json_encode(
                $this->validate($mailType, $data),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new ValidationException('template_data_snapshot JSON encoding failed.', 0, $exception);
        }
    }

    private function validateScheduleUpdate(array $data): void
    {
        $this->assertCommonField('request_id', $data['request_id']);
        $this->assertCommonField('update_count', $data['update_count']);

        if (isset($data['message_group_key'])) {
            $this->assertCommonField('message_group_key', $data['message_group_key']);
        }

        if (isset($data['created_at'])) {
            $this->assertCommonField('created_at', $data['created_at']);
        }

        if (isset($data['available_at'])) {
            $this->assertCommonField('available_at', $data['available_at']);
        }

        if (isset($data['delivery_base_date'])) {
            $this->assertCommonField('delivery_base_date', $data['delivery_base_date']);
        }

        $this->assertList($data['events'], 'schedule_update.events', self::MAX_SCHEDULE_EVENTS);
        foreach ($data['events'] as $event) {
            $this->validateScheduleEvent($event, 'schedule_update.events[]');
        }

        if (isset($data['summary'])) {
            $this->validateScheduleSummary($data['summary']);
        }

        if (isset($data['next_schedules'])) {
            $this->assertList($data['next_schedules'], 'schedule_update.next_schedules', self::MAX_NEXT_SCHEDULES);
            foreach ($data['next_schedules'] as $schedule) {
                $this->validateNextSchedule($schedule);
            }
        }

        if (isset($data['shops'])) {
            $this->assertList($data['shops'], 'schedule_update.shops', self::MAX_SCHEDULE_SHOPS);
            foreach ($data['shops'] as $shop) {
                $this->validateScheduleShop($shop);
            }
        }
    }

    private function validateScheduleShop(mixed $shop): void
    {
        if (!is_array($shop) || array_is_list($shop)) {
            throw new ValidationException('schedule_update.shops[] must be an object.');
        }

        $this->assertObjectSchema(
            $shop,
            self::SCHEDULE_SHOP_SCHEMA['required'],
            self::SCHEDULE_SHOP_SCHEMA['optional'],
            'schedule_update.shops[]'
        );
        $this->assertCommonField('shop_id', $shop['shop_id']);
        $this->assertCommonField('shop_name', $shop['shop_name']);
        $this->assertCommonField('event_count', $shop['event_count']);

        if (isset($shop['events'])) {
            $this->assertList($shop['events'], 'schedule_update.shops[].events', self::MAX_SCHEDULE_EVENTS);
            foreach ($shop['events'] as $event) {
                $this->validateScheduleEvent($event, 'schedule_update.shops[].events[]');
            }
        }
    }

    private function validateScheduleEvent(mixed $event, string $path): void
    {
        if (!is_array($event) || array_is_list($event)) {
            throw new ValidationException($path . ' must be an object.');
        }

        $this->assertObjectSchema(
            $event,
            self::SCHEDULE_EVENT_SCHEMA['required'],
            self::SCHEDULE_EVENT_SCHEMA['optional'],
            $path
        );

        foreach ($event as $key => $value) {
            $this->assertCommonField((string) $key, $value);
        }
    }

    private function validateScheduleSummary(mixed $summary): void
    {
        if (!is_array($summary) || array_is_list($summary)) {
            throw new ValidationException('schedule_update.summary must be an object.');
        }

        $this->assertObjectSchema(
            $summary,
            self::SCHEDULE_SUMMARY_SCHEMA['required'],
            self::SCHEDULE_SUMMARY_SCHEMA['optional'],
            'schedule_update.summary'
        );
        $this->assertCommonField('display_event_type', $summary['display_event_type']);
        $this->assertCommonField('display_reason', $summary['display_reason']);
        $this->assertCommonField('latest_event_id', $summary['latest_event_id']);
        $this->assertCommonField('event_count', $summary['event_count']);
        $this->assertEventTypes($summary['event_types']);
    }

    private function validateNextSchedule(mixed $schedule): void
    {
        if (!is_array($schedule) || array_is_list($schedule)) {
            throw new ValidationException('schedule_update.next_schedules[] must be an object.');
        }

        $this->assertObjectSchema(
            $schedule,
            self::NEXT_SCHEDULE_SCHEMA['required'],
            self::NEXT_SCHEDULE_SCHEMA['optional'],
            'schedule_update.next_schedules[]'
        );
        $this->assertCommonField('schedule_date', $schedule['schedule_date']);
        $this->assertCommonField('start_time', $schedule['start_time']);
        $this->assertCommonField('end_time', $schedule['end_time']);
    }

    private function assertObjectSchema(array $data, array $required, array $optional, string $path): void
    {
        if (array_is_list($data)) {
            throw new ValidationException($path . ' must be an object.');
        }

        foreach ($required as $key) {
            if (!array_key_exists($key, $data)) {
                throw new ValidationException($path . ' is missing required key: ' . $key);
            }
        }

        $allowed = array_flip(array_merge($required, $optional));
        foreach ($data as $key => $_value) {
            if (!is_string($key) || !array_key_exists($key, $allowed)) {
                throw new ValidationException($path . ' contains a non-whitelisted key.');
            }
        }
    }

    private function assertList(mixed $value, string $path, int $maxItems): void
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new ValidationException($path . ' must be a list.');
        }

        if (count($value) > $maxItems) {
            throw new ValidationException($path . ' contains too many items.');
        }
    }

    private function assertCommonField(string $key, mixed $value): void
    {
        match ($key) {
            'request_id' => $this->assertString($key, $value, 64),
            'shop_id' => $this->assertString($key, $value, 32),
            'shop_name', 'cast_name' => $this->assertString($key, $value, 100),
            'cast_id', 'event_id', 'latest_event_id' => $this->assertPositiveInt($key, $value),
            'token_ttl_seconds' => $this->assertTokenTtlSeconds($value),
            'expires_at', 'created_at', 'available_at' => $this->assertUtcDateTime($key, $value),
            'message_group_key' => $this->assertString($key, $value, 64),
            'update_count' => $this->assertBoundedInt($key, $value, 1, self::MAX_SCHEDULE_EVENTS),
            'event_count' => $this->assertBoundedInt($key, $value, 0, self::MAX_SCHEDULE_EVENTS),
            'event_type', 'display_event_type' => $this->assertEventType($value),
            'schedule_date', 'delivery_base_date' => $this->assertDate($key, $value),
            'start_time', 'end_time' => $this->assertTime($key, $value),
            'before_start_time', 'before_end_time', 'after_start_time', 'after_end_time' => $this->assertNullableTime($key, $value),
            'before_status', 'after_status' => $this->assertNullableString($key, $value, 32),
            'before_special_code', 'after_special_code' => $this->assertNullableString($key, $value, 16),
            'display_reason' => $this->assertString($key, $value, 100),
            default => throw new ValidationException('template_data_snapshot contains an unsupported field.'),
        };
    }

    private function assertSafeTree(mixed $value, int $depth): void
    {
        if ($depth > self::MAX_NESTING_DEPTH) {
            throw new ValidationException('template_data_snapshot is too deeply nested.');
        }

        if (is_array($value)) {
            if (count($value) > self::MAX_ARRAY_ITEMS) {
                throw new ValidationException('template_data_snapshot contains too many array items.');
            }

            foreach ($value as $key => $nestedValue) {
                if (is_string($key) && $this->isForbiddenKey($key)) {
                    throw new ValidationException('template_data_snapshot contains a forbidden key.');
                }

                $this->assertSafeTree($nestedValue, $depth + 1);
            }

            return;
        }

        if (is_string($value)) {
            $this->assertSafeStringValue($value);
            return;
        }

        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return;
        }

        throw new ValidationException('template_data_snapshot contains an unsupported value.');
    }

    private function assertSafeStringValue(string $value): void
    {
        if ($this->stringLength($value) > self::MAX_STRING_LENGTH) {
            throw new ValidationException('template_data_snapshot contains an overlong string.');
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value) === 1) {
            throw new ValidationException('template_data_snapshot must not contain full email addresses.');
        }

        if (preg_match('/https?:\/\//i', $value) === 1
            || preg_match('/mailto:/i', $value) === 1
            || str_contains($value, '//')
            || preg_match('/[?&](?:token|key)=/i', $value) === 1
            || preg_match('#^/[^\s]*(?:verify|auth|settings?|wgmailsys|code=|token=|key=)#i', $value) === 1
        ) {
            throw new ValidationException('template_data_snapshot must not contain URLs or token parameters.');
        }
    }

    private function isForbiddenKey(string $key): bool
    {
        $normalized = strtolower($key);
        if (in_array($normalized, self::FORBIDDEN_KEY_EXCEPTIONS, true)) {
            return false;
        }

        foreach (self::FORBIDDEN_KEY_PARTS as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }

    private function assertString(string $key, mixed $value, int $maxLength): void
    {
        if (!is_string($value) || $value === '' || $this->stringLength($value) > $maxLength) {
            throw new ValidationException($key . ' is invalid.');
        }
    }

    private function assertNullableString(string $key, mixed $value, int $maxLength): void
    {
        if ($value === null) {
            return;
        }

        $this->assertString($key, $value, $maxLength);
    }

    private function assertPositiveInt(string $key, mixed $value): void
    {
        $this->assertBoundedInt($key, $value, 1, PHP_INT_MAX);
    }

    private function assertTokenTtlSeconds(mixed $value): void
    {
        if ($value !== 3600) {
            throw new ValidationException('token_ttl_seconds is invalid.');
        }
    }

    private function assertBoundedInt(string $key, mixed $value, int $min, int $max): void
    {
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new ValidationException($key . ' is invalid.');
        }
    }

    private function assertEventType(mixed $value): void
    {
        if (!is_string($value) || !in_array($value, self::EVENT_TYPES, true)) {
            throw new ValidationException('event_type is invalid.');
        }
    }

    private function assertEventTypes(mixed $value): void
    {
        $this->assertList($value, 'schedule_update.summary.event_types', self::MAX_EVENT_TYPES);
        foreach ($value as $eventType) {
            $this->assertEventType($eventType);
        }
    }

    private function assertDate(string $key, mixed $value): void
    {
        if (!is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
            throw new ValidationException($key . ' is invalid.');
        }

        if (!checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            throw new ValidationException($key . ' is invalid.');
        }
    }

    private function assertNullableTime(string $key, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $this->assertTime($key, $value);
    }

    private function assertTime(string $key, mixed $value): void
    {
        if (!is_string($value) || preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value) !== 1) {
            throw new ValidationException($key . ' is invalid.');
        }
    }

    private function assertUtcDateTime(string $key, mixed $value): void
    {
        if (!is_string($value)
            || preg_match('/^(\d{4})-(\d{2})-(\d{2})T([01]\d|2[0-3]):([0-5]\d):([0-5]\d)Z$/', $value, $matches) !== 1
        ) {
            throw new ValidationException($key . ' is invalid.');
        }

        if (!checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            throw new ValidationException($key . ' is invalid.');
        }
    }

    private function assertJsonSize(array $data): void
    {
        try {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ValidationException('template_data_snapshot JSON encoding failed.', 0, $exception);
        }

        if (strlen($json) > self::MAX_JSON_BYTES) {
            throw new ValidationException('template_data_snapshot JSON is too large.');
        }
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }
}
