<?php
declare(strict_types=1);

namespace DandyNotification\Mail;

final class EmptyMailRequest
{
    public function __construct(
        private readonly string $email,
        private readonly int $castId
    ) {
    }

    public function email(): string
    {
        return $this->email;
    }

    public function castId(): int
    {
        return $this->castId;
    }
}
