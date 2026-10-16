<?php
declare(strict_types=1);

namespace DandyNotification\Exception;

final class TargetPlanningException extends \InvalidArgumentException
{
    public function __construct(
        private readonly string $category,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function invalidTimezone(): self
    {
        return new self('invalid_timezone', 'Invalid subscriber timezone.');
    }

    public static function invalidQuietHours(): self
    {
        return new self('invalid_quiet_hours', 'Invalid quiet-hours setting.');
    }

    public function category(): string
    {
        return $this->category;
    }
}
