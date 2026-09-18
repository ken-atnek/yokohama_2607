<?php
declare(strict_types=1);

namespace DandyNotification\Mail;

final class TokenMaterial
{
    private function __construct(
        private readonly string $rawToken,
        private readonly string $tokenHash
    ) {
    }

    public static function generate(): self
    {
        $rawToken = bin2hex(random_bytes(32));

        return new self($rawToken, hash('sha256', $rawToken, true));
    }

    public function rawToken(): string
    {
        return $this->rawToken;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }
}
