<?php
declare(strict_types=1);

namespace DandyNotification\Mail;

final class MailTransportResult
{
    public function __construct(
        private readonly bool $success,
        private readonly ?string $providerMessageId = null
    ) {
    }

    public static function success(?string $providerMessageId = null): self
    {
        return new self(true, $providerMessageId);
    }

    public static function failure(): self
    {
        return new self(false, null);
    }

    public function successValue(): bool
    {
        return $this->success;
    }

    public function providerMessageId(): ?string
    {
        return $this->providerMessageId;
    }
}
