<?php
declare(strict_types=1);

namespace DandyNotification\Service;

use DateTimeImmutable;
use DateTimeZone;
use DandyNotification\Exception\EventDataValidationException;
use DandyNotification\Exception\TargetPlanningException;
use DandyNotification\Exception\ValidationException;
use DandyNotification\RepositorySet;
use PDO;
use PDOException;
use Throwable;

final class ScheduleDeliveryCreationService
{
    private const MAIL_TYPE = 'schedule_update';
    private const TOKYO_TIMEZONE = 'Asia/Tokyo';
    private const NEXT_SCHEDULE_LOOKAHEAD_DAYS = 365;
    private const TARGET_INVALID_SAMPLE_LIMIT = 20;

    public function __construct(
        private readonly PDO $pdo,
        private readonly RepositorySet $repositories,
        private readonly DeliveryService $deliveryService,
        private readonly ScheduleDeliveryPlanningService $planningService,
        private readonly ScheduleEventDecisionService $decisionService
    ) {
    }

    public function createOneBatch(string $requestId, int $batchSize, ?DateTimeImmutable $queueCreatedAtUtc = null): array
    {
        $queueCreatedAtUtc = ($queueCreatedAtUtc ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
        $events = $this->repositories->events()->findPendingBatch($batchSize);
        $counters = $this->initialCounters();
        if ($events === []) {
            return $this->result(true, $counters, 0, null);
        }

        $eventIds = array_map(static fn (array $event): int => (int) $event['event_id'], $events);
        $currentEventId = null;

        $this->pdo->beginTransaction();
        try {
            $claimedCount = $this->repositories->events()->claimPendingBatch($eventIds, $requestId);
            if ($claimedCount !== count($eventIds)) {
                throw new \RuntimeException('Pending event claim count mismatch.');
            }

            $counters['event_claimed'] = $claimedCount;
            $plan = $this->buildDeliveryPlan($requestId, $events, $queueCreatedAtUtc, $currentEventId);
            $counters['event_zero_target'] = $plan['zero_target_count'];
            $counters['delivery_duplicate'] = $plan['duplicate_count'];
            $counters['subscriber_groups'] = count($plan['groups']);
            $counters['target_invalid_skipped'] = $plan['target_invalid_skipped'];
            $counters['event_all_targets_invalid'] = $plan['event_all_targets_invalid'];

            foreach ($plan['items'] as $item) {
                $event = $item['event'];
                $target = $item['target'];
                $group = $plan['groups'][$item['subscriber_id']];
                $currentEventId = (int) $event['event_id'];
                $summary = $plan['summaries'][$item['subscriber_id']][$item['bucket_key']];
                $nextSchedules = $this->findNextWorkSchedules(
                    (string) $event['shop_id'],
                    (int) $event['cast_id'],
                    (string) $group['delivery_base_date']
                );
                $templateData = $this->planningService->buildTemplateData(
                    $requestId,
                    $group['update_count'],
                    $group['message_group_key'],
                    $queueCreatedAtUtc,
                    $group['available_at_utc'],
                    (string) $group['delivery_base_date'],
                    $event,
                    $summary,
                    $nextSchedules
                );
                $this->assertEventTemplateDataIsValid($templateData);
                $templateDataSnapshot = $this->deliveryService->buildTemplateDataSnapshot(self::MAIL_TYPE, $templateData);
                $deliveryId = $this->repositories->deliveries()->insertScheduleUpdateDelivery(
                    (int) $event['event_id'],
                    (int) $target['subscriber_id'],
                    (int) $target['subscription_id'],
                    (string) $target['subscriber_email'],
                    $templateDataSnapshot,
                    $group['message_group_key'],
                    $this->formatMysqlUtc($group['available_at_utc'])
                );

                if ($deliveryId === null) {
                    $counters['delivery_duplicate']++;
                    continue;
                }

                $counters['delivery_created']++;
            }

            $currentEventId = null;
            $counters['event_processed'] = $this->repositories->events()->markProcessed($eventIds);
            $this->pdo->commit();

            return $this->result(true, $counters, 0, null, $plan['target_invalid_samples']);
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $counters['transaction_error_count']++;
            $failureEventId = null;
            if (!$this->isSystemLevelFailure($exception)) {
                $failureEventId = $this->identifyPoisonEvent($requestId, $events, $queueCreatedAtUtc);
            }

            if ($failureEventId !== null) {
                $failureStatus = $this->recordFailureAttempt($failureEventId, $exception);
                if ($failureStatus === 'failed') {
                    $counters['event_failed']++;
                } else {
                    $counters['event_retried']++;
                }
            } else {
                $counters['failure_not_attributed']++;
            }

            return $this->result(false, $counters, 1, $failureEventId);
        }
    }

    private function buildDeliveryPlan(
        string $requestId,
        array $events,
        DateTimeImmutable $queueCreatedAtUtc,
        ?int &$currentEventId
    ): array {
        $items = [];
        $groups = [];
        $summaryEvents = [];
        $zeroTargetCount = 0;
        $duplicateCount = 0;
        $targetInvalidSkipped = 0;
        $eventAllTargetsInvalid = 0;
        $targetInvalidSamples = [];
        $invalidSubscriberIds = [];

        foreach ($events as $event) {
            $currentEventId = (int) $event['event_id'];
            $targetCount = 0;
            $validTargetCount = 0;
            $eventTargetInvalidSkipped = 0;
            $targets = $this->repositories->subscriptions()->findActiveDeliveryTargetsByCast(
                (string) $event['shop_id'],
                (int) $event['cast_id']
            );

            foreach ($targets as $target) {
                if ($this->repositories->suppressions()->existsActiveByEmail((string) $target['subscriber_email'])) {
                    continue;
                }

                $targetCount++;
                $subscriberId = (int) $target['subscriber_id'];
                if ($this->repositories->deliveries()->findByEventAndSubscription(
                    (int) $event['event_id'],
                    (int) $target['subscription_id']
                ) !== null) {
                    $duplicateCount++;
                    $validTargetCount++;
                    continue;
                }

                if (isset($invalidSubscriberIds[$subscriberId])) {
                    $targetInvalidSkipped++;
                    $eventTargetInvalidSkipped++;
                    $this->appendTargetInvalidSample(
                        $targetInvalidSamples,
                        $event,
                        $target,
                        $invalidSubscriberIds[$subscriberId]
                    );
                    continue;
                }

                if (!isset($groups[$subscriberId])) {
                    try {
                        $availableAtUtc = $this->planningService->calculateAvailableAtUtc(
                            $queueCreatedAtUtc,
                            (int) $target['quiet_hours_enabled'] === 1,
                            $this->nullableInt($target['quiet_start_minute'] ?? null),
                            $this->nullableInt($target['quiet_end_minute'] ?? null),
                            (string) ($target['timezone'] ?? self::TOKYO_TIMEZONE)
                        );
                        $deliveryBaseDate = $this->planningService->deliveryBaseDate(
                            $availableAtUtc,
                            (string) ($target['timezone'] ?? self::TOKYO_TIMEZONE)
                        );
                    } catch (TargetPlanningException $exception) {
                        $invalidSubscriberIds[$subscriberId] = $exception->category();
                        $targetInvalidSkipped++;
                        $eventTargetInvalidSkipped++;
                        $this->appendTargetInvalidSample(
                            $targetInvalidSamples,
                            $event,
                            $target,
                            $exception->category()
                        );
                        continue;
                    }

                    $groups[$subscriberId] = [
                        'message_group_key' => $this->planningService->generateMessageGroupKey(),
                        'available_at_utc' => $availableAtUtc,
                        'delivery_base_date' => $deliveryBaseDate,
                        'update_count' => 0,
                    ];
                }

                $validTargetCount++;
                $bucketKey = $this->eventBucketKey($event);
                $items[] = [
                    'event' => $event,
                    'target' => $target,
                    'subscriber_id' => $subscriberId,
                    'bucket_key' => $bucketKey,
                ];
                $groups[$subscriberId]['update_count']++;
                $summaryEvents[$subscriberId][$bucketKey][] = $event;
            }

            if ($targetCount === 0) {
                $zeroTargetCount++;
            } elseif ($validTargetCount === 0 && $eventTargetInvalidSkipped > 0) {
                $eventAllTargetsInvalid++;
            }
        }

        $summaries = [];
        foreach ($summaryEvents as $subscriberId => $buckets) {
            foreach ($buckets as $bucketKey => $bucketEvents) {
                $summaries[$subscriberId][$bucketKey] = $this->planningService->displaySummary($bucketEvents);
            }
        }

        return [
            'items' => $items,
            'groups' => $groups,
            'summaries' => $summaries,
            'zero_target_count' => $zeroTargetCount,
            'duplicate_count' => $duplicateCount,
            'target_invalid_skipped' => $targetInvalidSkipped,
            'event_all_targets_invalid' => $eventAllTargetsInvalid,
            'target_invalid_samples' => $targetInvalidSamples,
        ];
    }

    private function identifyPoisonEvent(string $requestId, array $events, DateTimeImmutable $queueCreatedAtUtc): ?int
    {
        foreach ($events as $event) {
            try {
                $this->validateSingleEventForPoison($requestId, $event, $queueCreatedAtUtc);
            } catch (Throwable $exception) {
                if ($this->isSystemLevelFailure($exception)) {
                    return null;
                }

                return $this->canAttributeFailureToEvent($exception) ? (int) $event['event_id'] : null;
            }
        }

        return null;
    }

    private function validateSingleEventForPoison(
        string $requestId,
        array $event,
        DateTimeImmutable $queueCreatedAtUtc
    ): void {
        $currentEventId = null;
        $plan = $this->buildDeliveryPlan($requestId, [$event], $queueCreatedAtUtc, $currentEventId);
        foreach ($plan['items'] as $item) {
            $group = $plan['groups'][$item['subscriber_id']];
            $summary = $plan['summaries'][$item['subscriber_id']][$item['bucket_key']];
            $nextSchedules = $this->findNextWorkSchedules(
                (string) $item['event']['shop_id'],
                (int) $item['event']['cast_id'],
                (string) $group['delivery_base_date']
            );
            $templateData = $this->planningService->buildTemplateData(
                $requestId,
                $group['update_count'],
                $group['message_group_key'],
                $queueCreatedAtUtc,
                $group['available_at_utc'],
                (string) $group['delivery_base_date'],
                $item['event'],
                $summary,
                $nextSchedules
            );
            $this->assertEventTemplateDataIsValid($templateData);
            $this->deliveryService->buildTemplateDataSnapshot(self::MAIL_TYPE, $templateData);
        }
    }

    private function isSystemLevelFailure(Throwable $exception): bool
    {
        return $exception instanceof PDOException;
    }

    private function canAttributeFailureToEvent(Throwable $exception): bool
    {
        if ($this->isSystemLevelFailure($exception)) {
            return false;
        }

        if ($exception instanceof EventDataValidationException) {
            return true;
        }

        return false;
    }

    private function assertEventTemplateDataIsValid(array $templateData): void
    {
        try {
            $this->deliveryService->buildTemplateDataSnapshot(self::MAIL_TYPE, [
                'request_id' => 'phase9-event-data-validation',
                'update_count' => 1,
                'events' => $templateData['events'] ?? [],
            ]);
        } catch (ValidationException $exception) {
            throw new EventDataValidationException('Event data validation failed.', 0, $exception);
        }
    }

    private function appendTargetInvalidSample(
        array &$samples,
        array $event,
        array $target,
        string $category
    ): void {
        if (count($samples) >= self::TARGET_INVALID_SAMPLE_LIMIT) {
            return;
        }

        $samples[] = [
            'event_id' => (int) $event['event_id'],
            'subscriber_id' => (int) $target['subscriber_id'],
            'subscription_id' => (int) $target['subscription_id'],
            'shop_id' => (string) $event['shop_id'],
            'cast_id' => (int) $event['cast_id'],
            'error_category' => $category,
        ];
    }

    private function recordFailureAttempt(int $eventId, Throwable $exception): string
    {
        $this->pdo->beginTransaction();
        try {
            $this->repositories->events()->recordProcessingFailure($eventId, $this->safeErrorMessage($exception));
            $event = $this->repositories->events()->findById($eventId);
            $this->pdo->commit();
        } catch (Throwable $failureException) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $failureException;
        }

        return is_array($event) ? (string) $event['status'] : 'pending';
    }

    private function findNextWorkSchedules(string $shopId, int $castId, string $baseDate): array
    {
        [$startTimestamp, $endTimestamp] = $this->lookaheadRange($baseDate);
        $statement = $this->pdo->prepare(
            'SELECT g_id, shop_id, win, wout, brin, brout, wuptime, wait, warea_row
             FROM t_work_list
             WHERE g_id = :cast_id
               AND shop_id = :shop_id
               AND win BETWEEN :start_time AND :end_time
             ORDER BY win ASC
             LIMIT 730'
        );
        $statement->execute([
            'cast_id' => $castId,
            'shop_id' => $shopId,
            'start_time' => $startTimestamp,
            'end_time' => $endTimestamp,
        ]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $seenDates = [];
        $schedules = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $scheduleDate = $this->scheduleDateFromWorkRow($row);
            if ($scheduleDate === null || $scheduleDate < $baseDate || isset($seenDates[$scheduleDate])) {
                continue;
            }

            $seenDates[$scheduleDate] = true;
            $snapshot = $this->decisionService->canonicalize($row, $scheduleDate);
            if ((string) $snapshot['state'] !== ScheduleEventDecisionService::STATE_WORK) {
                continue;
            }

            $schedules[] = [
                'schedule_date' => $scheduleDate,
                'start_time' => (string) $snapshot['start_time'],
                'end_time' => (string) $snapshot['end_time'],
            ];
            if (count($schedules) >= 3) {
                break;
            }
        }

        return $schedules;
    }

    private function lookaheadRange(string $baseDate): array
    {
        if (!$this->decisionService->isValidScheduleDate($baseDate)) {
            throw new \InvalidArgumentException('Invalid delivery base date.');
        }

        $start = new DateTimeImmutable($baseDate . ' 00:00:00', new DateTimeZone(self::TOKYO_TIMEZONE));

        return [
            $start->getTimestamp(),
            $start->modify('+' . self::NEXT_SCHEDULE_LOOKAHEAD_DAYS . ' days')->getTimestamp() - 1,
        ];
    }

    private function scheduleDateFromWorkRow(array $row): ?string
    {
        if (!is_scalar($row['win'] ?? null) || !is_numeric($row['win'])) {
            return null;
        }

        $timestamp = (int) $row['win'];
        if ($timestamp < 1) {
            return null;
        }

        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone(self::TOKYO_TIMEZONE))
            ->format('Y-m-d');
    }

    private function eventBucketKey(array $event): string
    {
        return implode('|', [
            (string) $event['shop_id'],
            (string) ((int) $event['cast_id']),
            $this->scheduleDate($event['schedule_date'] ?? ''),
        ]);
    }

    private function scheduleDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function formatMysqlUtc(DateTimeImmutable $dateTime): string
    {
        return $dateTime
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s.u');
    }

    private function safeErrorMessage(Throwable $exception): string
    {
        $message = $exception::class . ': ' . $exception->getMessage();
        $message = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted]', $message) ?? '[redacted]';
        $message = preg_replace('/(password|passwd|secret|token|session|email|recipient)(\s*[=:]\s*)\S+/i', '$1$2[redacted]', $message) ?? '[redacted]';
        $message = preg_replace('/[\r\n\t ]+/', ' ', $message) ?? '[redacted]';

        return mb_substr($message, 0, 500);
    }

    private function initialCounters(): array
    {
        return [
            'event_claimed' => 0,
            'event_processed' => 0,
            'event_zero_target' => 0,
            'delivery_created' => 0,
            'delivery_duplicate' => 0,
            'subscriber_groups' => 0,
            'event_failed' => 0,
            'event_retried' => 0,
            'failure_not_attributed' => 0,
            'transaction_error_count' => 0,
            'target_invalid_skipped' => 0,
            'event_all_targets_invalid' => 0,
        ];
    }

    private function result(
        bool $success,
        array $counters,
        int $errorCount,
        ?int $failureEventId,
        array $targetInvalidSamples = []
    ): array {
        return [
            'success' => $success,
            'counters' => $counters,
            'error_count' => $errorCount,
            'failure_event_id' => $failureEventId,
            'target_invalid_samples' => $targetInvalidSamples,
        ];
    }
}
