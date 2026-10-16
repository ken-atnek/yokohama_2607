<?php
declare(strict_types=1);

namespace DandyNotification\Mail;

final class ParsedMailHeaders
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(private readonly array $headers)
    {
    }

    public function value(string $name): ?string
    {
        $values = $this->values($name);

        return $values[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function values(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    public function has(string $name): bool
    {
        return $this->value($name) !== null;
    }
}
