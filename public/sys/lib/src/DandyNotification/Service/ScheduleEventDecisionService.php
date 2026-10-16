<?php
declare(strict_types=1);

namespace DandyNotification\Service;

use DateTimeImmutable;
use DateTimeZone;

final class ScheduleEventDecisionService
{
    public const STATE_UNREGISTERED = 'UNREGISTERED';
    public const STATE_WORK = 'WORK';
    public const STATE_OFF = 'OFF';
    public const STATE_TEL = 'TEL';
    public const STATE_HOLIDAY = 'HOLIDAY';
    public const STATE_LAST = 'LAST';
    public const STATE_OTHER_SPECIAL = 'OTHER_SPECIAL';

    public const EVENT_SCHEDULE_CREATED = 'schedule_created';
    public const EVENT_SCHEDULE_START_CHANGED = 'schedule_start_changed';
    public const EVENT_SCHEDULE_CHANGED_TO_OFF = 'schedule_changed_to_off';
    public const EVENT_SCHEDULE_CHANGED_TO_WORK = 'schedule_changed_to_work';

    private const TOKYO_TIMEZONE = 'Asia/Tokyo';

    public function canonicalize(?array $row, string $scheduleDate): array
    {
        if ($row === null || $row === []) {
            return $this->snapshot(self::STATE_UNREGISTERED, null, null, null);
        }

        $win = $this->timestamp($row['win'] ?? null);
        if ($win === null) {
            return $this->snapshot(self::STATE_UNREGISTERED, null, null, null);
        }

        $wout = $this->timestamp($row['wout'] ?? null);
        if ($wout !== null && $this->isDayStartPair($win, $wout, $scheduleDate)) {
            return $this->snapshot(self::STATE_UNREGISTERED, null, null, null);
        }

        $winSecond = $this->secondCode($win);
        $woutSecond = $wout === null ? null : $this->secondCode($wout);

        if (in_array($winSecond, ['02', '08'], true)) {
            return $this->snapshot(self::STATE_TEL, null, null, $winSecond);
        }

        if ($winSecond === '04') {
            return $this->snapshot(self::STATE_HOLIDAY, null, null, $winSecond);
        }

        if (in_array($winSecond, ['03', '13'], true)) {
            return $this->snapshot(self::STATE_OFF, null, null, $winSecond);
        }

        if ($woutSecond === '09') {
            return $this->snapshot(self::STATE_LAST, null, null, $woutSecond);
        }

        if (in_array($winSecond, ['05', '11', '12', '14', '21', '22', '23', '24', '25'], true)) {
            return $this->snapshot(self::STATE_OTHER_SPECIAL, null, null, $winSecond);
        }

        if ($winSecond !== '00' || ($woutSecond !== null && $woutSecond !== '00')) {
            return $this->snapshot(self::STATE_OTHER_SPECIAL, null, null, $winSecond !== '00' ? $winSecond : $woutSecond);
        }

        if ($wout !== null && $win < $wout) {
            return $this->snapshot(
                self::STATE_WORK,
                $this->formatHourMinute($win),
                $this->formatHourMinute($wout),
                null
            );
        }

        return $this->snapshot(self::STATE_UNREGISTERED, null, null, null);
    }

    public function decide(array $before, array $after, string $scheduleDate, ?DateTimeImmutable $now = null): ?string
    {
        if ($this->isPastScheduleDate($scheduleDate, $now)) {
            return null;
        }

        $beforeState = (string) ($before['state'] ?? '');
        $afterState = (string) ($after['state'] ?? '');

        if ($this->containsExcludedState($beforeState, $afterState)) {
            return null;
        }

        if ($beforeState === self::STATE_UNREGISTERED && $afterState === self::STATE_WORK) {
            return self::EVENT_SCHEDULE_CREATED;
        }

        if ($beforeState === self::STATE_WORK && $afterState === self::STATE_OFF) {
            return self::EVENT_SCHEDULE_CHANGED_TO_OFF;
        }

        if ($beforeState === self::STATE_OFF && $afterState === self::STATE_WORK) {
            return self::EVENT_SCHEDULE_CHANGED_TO_WORK;
        }

        if ($beforeState === self::STATE_WORK
            && $afterState === self::STATE_WORK
            && (string) ($before['start_time'] ?? '') !== (string) ($after['start_time'] ?? '')
        ) {
            return self::EVENT_SCHEDULE_START_CHANGED;
        }

        return null;
    }

    public function isPastScheduleDate(string $scheduleDate, ?DateTimeImmutable $now = null): bool
    {
        if (!$this->isValidScheduleDate($scheduleDate)) {
            return true;
        }

        $now = $now ?? new DateTimeImmutable('now', $this->tokyoTimeZone());
        $today = $now->setTimezone($this->tokyoTimeZone())->format('Y-m-d');

        return $scheduleDate < $today;
    }

    public function isValidScheduleDate(string $scheduleDate): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduleDate) !== 1) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $scheduleDate));

        return checkdate($month, $day, $year);
    }

    private function containsExcludedState(string $beforeState, string $afterState): bool
    {
        $excluded = [
            self::STATE_TEL,
            self::STATE_HOLIDAY,
            self::STATE_LAST,
            self::STATE_OTHER_SPECIAL,
        ];

        return in_array($beforeState, $excluded, true) || in_array($afterState, $excluded, true);
    }

    private function snapshot(string $state, ?string $startTime, ?string $endTime, ?string $specialCode): array
    {
        return [
            'state' => $state,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'special_code' => $specialCode,
        ];
    }

    private function timestamp(mixed $value): ?int
    {
        if (!is_scalar($value) || !is_numeric($value)) {
            return null;
        }

        $timestamp = (int) $value;

        return $timestamp > 0 ? $timestamp : null;
    }

    private function secondCode(int $timestamp): string
    {
        return gmdate('s', $timestamp);
    }

    private function formatHourMinute(int $timestamp): string
    {
        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone($this->tokyoTimeZone())
            ->format('H:i');
    }

    private function isDayStartPair(int $win, int $wout, string $scheduleDate): bool
    {
        if (!$this->isValidScheduleDate($scheduleDate)) {
            return false;
        }

        $dayStart = (new DateTimeImmutable($scheduleDate . ' 00:00:00', $this->tokyoTimeZone()))->getTimestamp();

        return $win === $dayStart && $wout === $dayStart;
    }

    private function tokyoTimeZone(): DateTimeZone
    {
        return new DateTimeZone(self::TOKYO_TIMEZONE);
    }
}
