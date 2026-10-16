<?php
declare(strict_types=1);

namespace DandyNotification\Service;

use DateTimeImmutable;
use DateTimeZone;
use DandyNotification\Config\FeatureFlags;
use DandyNotification\Log\JsonLogger;
use DandyNotification\RepositorySet;
use JsonException;
use PDO;
use Throwable;

final class ScheduleEventService
{
    private const SOURCE_TYPE_SCHEDULE_SAVE = 'schedule_save';
    private const COMPONENT = 'Phase8/schedule_event';
    private const TOKYO_TIMEZONE = 'Asia/Tokyo';

    public function __construct(
        private readonly PDO $pdo,
        private readonly RepositorySet $repositories,
        private readonly FeatureFlags $featureFlags,
        private readonly JsonLogger $logger,
        private readonly ScheduleEventDecisionService $decisionService,
        private readonly NotificationEventService $notificationEventService
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->featureFlags->enabled('notification_event_enqueue_enabled');
    }

    public function snapshot(string $shopId, int $castId, string $scheduleDate): array
    {
        return $this->decisionService->canonicalize(
            $this->findScheduleRow($shopId, $castId, $scheduleDate),
            $scheduleDate
        );
    }

    public function enqueueForChange(
        string $requestId,
        string $shopId,
        int $castId,
        string $scheduleDate,
        array $beforeSnapshot
    ): array {
        if (!$this->isEnabled()) {
            return $this->result(false, false, null, 'feature_off');
        }

        $afterSnapshot = $this->snapshot($shopId, $castId, $scheduleDate);
        $eventType = $this->decisionService->decide($beforeSnapshot, $afterSnapshot, $scheduleDate);
        if ($eventType === null) {
            return $this->result(false, false, null, 'no_event');
        }

        $girl = $this->repositories->girls()->findByShopAndCast($shopId, $castId);
        if ($girl === null) {
            $this->logOperationalError($requestId, $shopId, $castId, $scheduleDate, $eventType, 'resolve_cast', 'cast_not_found');

            return $this->result(false, false, $eventType, 'cast_not_found');
        }

        $shopName = $this->findShopName($shopId);
        if ($shopName === null) {
            $this->logOperationalError($requestId, $shopId, $castId, $scheduleDate, $eventType, 'resolve_shop', 'shop_not_found');

            return $this->result(false, false, $eventType, 'shop_not_found');
        }

        $this->notificationEventService->assertSupportedEventType($eventType);
        $eventKey = $this->notificationEventService->eventKeyHash(
            $this->eventKeyMaterial($requestId, $shopId, $castId, $scheduleDate, $eventType, $beforeSnapshot, $afterSnapshot)
        );

        $inserted = $this->repositories->events()->insertScheduleEvent([
            'event_key' => $eventKey,
            'event_type' => $eventType,
            'source_type' => self::SOURCE_TYPE_SCHEDULE_SAVE,
            'shop_id' => $shopId,
            'cast_id' => $castId,
            'shop_name_snapshot' => $shopName,
            'cast_name_snapshot' => $this->displayName($girl['name'] ?? null, (string) $castId),
            'schedule_date' => $scheduleDate,
            'before_payload' => $this->jsonPayload($beforeSnapshot),
            'after_payload' => $this->jsonPayload($afterSnapshot),
        ]);

        return $this->result($inserted, !$inserted, $eventType, $inserted ? 'inserted' : 'duplicate');
    }

    private function findScheduleRow(string $shopId, int $castId, string $scheduleDate): ?array
    {
        [$startTime, $endTime] = $this->scheduleDateRange($scheduleDate);
        $statement = $this->pdo->prepare(
            'SELECT g_id, shop_id, win, wout, brin, brout, wuptime, wait, warea_row
             FROM t_work_list
             WHERE g_id = :cast_id
               AND shop_id = :shop_id
               AND win BETWEEN :start_time AND :end_time
             ORDER BY win ASC
             LIMIT 1'
        );
        $statement->execute([
            'cast_id' => $castId,
            'shop_id' => $shopId,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function findShopName(string $shopId): ?string
    {
        $statement = $this->pdo->prepare('SELECT shop_name FROM t_shop_master WHERE shop_id = :shop_id LIMIT 1');
        $statement->execute(['shop_id' => $shopId]);
        $shopName = $statement->fetchColumn();
        if (!is_string($shopName) || trim($shopName) === '') {
            return null;
        }

        return $shopName;
    }

    private function scheduleDateRange(string $scheduleDate): array
    {
        if (!$this->decisionService->isValidScheduleDate($scheduleDate)) {
            throw new \InvalidArgumentException('Invalid schedule date.');
        }

        $start = new DateTimeImmutable($scheduleDate . ' 00:00:00', new DateTimeZone(self::TOKYO_TIMEZONE));

        return [
            $start->getTimestamp(),
            $start->modify('+1 day')->getTimestamp() - 1,
        ];
    }

    private function eventKeyMaterial(
        string $requestId,
        string $shopId,
        int $castId,
        string $scheduleDate,
        string $eventType,
        array $beforeSnapshot,
        array $afterSnapshot
    ): array {
        return [
            'version' => 'phase8-v1',
            'request_id' => $requestId,
            'shop_id' => $shopId,
            'cast_id' => $castId,
            'schedule_date' => $scheduleDate,
            'event_type' => $eventType,
            'before' => $this->orderedSnapshot($beforeSnapshot),
            'after' => $this->orderedSnapshot($afterSnapshot),
        ];
    }

    private function orderedSnapshot(array $snapshot): array
    {
        return [
            'state' => (string) ($snapshot['state'] ?? ScheduleEventDecisionService::STATE_UNREGISTERED),
            'start_time' => $snapshot['start_time'] ?? null,
            'end_time' => $snapshot['end_time'] ?? null,
            'special_code' => $snapshot['special_code'] ?? null,
        ];
    }

    private function jsonPayload(array $snapshot): string
    {
        try {
            return json_encode($this->orderedSnapshot($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Schedule event payload encoding failed.', 0, $exception);
        }
    }

    private function displayName(mixed $value, string $fallback): string
    {
        $name = is_scalar($value) ? trim((string) $value) : '';

        return $name !== '' ? $name : $fallback;
    }

    private function result(bool $inserted, bool $duplicate, ?string $eventType, string $reason): array
    {
        return [
            'inserted' => $inserted,
            'duplicate' => $duplicate,
            'event_type' => $eventType,
            'reason' => $reason,
        ];
    }

    private function logOperationalError(
        string $requestId,
        string $shopId,
        int $castId,
        string $scheduleDate,
        ?string $eventType,
        string $failureStage,
        mixed $error
    ): void {
        $payload = [
            'component' => self::COMPONENT,
            'request_id' => $requestId,
            'shop_id' => $shopId,
            'cast_id' => $castId,
            'schedule_date' => $scheduleDate,
            'event_type' => $eventType,
            'failure_stage' => $failureStage,
            'error' => $this->errorContext($error),
        ];

        try {
            $this->logger->error('schedule event enqueue failed', $payload);
        } catch (Throwable $exception) {
            $this->fallbackErrorLog($payload, $exception);
        }
    }

    private function errorContext(mixed $error): array
    {
        if ($error instanceof Throwable) {
            return [
                'class' => $error::class,
                'message' => $this->safeErrorMessage($error->getMessage()),
            ];
        }

        return [
            'category' => $this->safeErrorMessage((string) $error),
        ];
    }

    private function fallbackErrorLog(array $payload, Throwable $loggerException): void
    {
        $error = is_array($payload['error'] ?? null) ? $payload['error'] : ['category' => 'error'];
        $fields = [
            'time' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
            'component' => self::COMPONENT,
            'request_id' => (string) ($payload['request_id'] ?? ''),
            'shop_id' => (string) ($payload['shop_id'] ?? ''),
            'cast_id' => (string) ($payload['cast_id'] ?? ''),
            'schedule_date' => (string) ($payload['schedule_date'] ?? ''),
            'event_type' => (string) ($payload['event_type'] ?? ''),
            'failure_stage' => (string) ($payload['failure_stage'] ?? 'logger'),
            'error_category' => (string) ($error['category'] ?? $error['class'] ?? $loggerException::class),
        ];
        $parts = [];
        foreach ($fields as $key => $value) {
            $parts[] = $key . '=' . $this->safeFallbackValue($value);
        }

        try {
            error_log('[dandy_notification_phase8] ' . implode(' ', $parts));
        } catch (Throwable) {
        }
    }

    private function safeErrorMessage(string $message): string
    {
        $message = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted]', $message);
        $message = preg_replace('/(password|passwd|secret|token|session|email|recipient)(\s*[=:]\s*)\S+/i', '$1$2[redacted]', (string) $message);

        return substr((string) $message, 0, 160);
    }

    private function safeFallbackValue(string $value): string
    {
        $value = $this->safeErrorMessage($value);
        $value = preg_replace('/[\r\n\t ]+/', '_', $value);

        return substr((string) $value, 0, 120);
    }
}
