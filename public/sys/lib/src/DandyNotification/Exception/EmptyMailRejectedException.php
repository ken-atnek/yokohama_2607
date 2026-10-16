<?php
declare(strict_types=1);

namespace DandyNotification\Exception;

use RuntimeException;

final class EmptyMailRejectedException extends RuntimeException
{
    public function __construct(private readonly string $reason)
    {
        parent::__construct('Empty mail request was rejected.');
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
