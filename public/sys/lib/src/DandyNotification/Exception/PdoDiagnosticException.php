<?php
declare(strict_types=1);

namespace DandyNotification\Exception;

use PDOException;

final class PdoDiagnosticException extends PDOException
{
    private readonly ?string $sqlState;

    private readonly int|string|null $driverCode;

    public function __construct(
        private readonly string $stage,
        private readonly string $exceptionClass,
        PDOException $previous
    ) {
        parent::__construct('Database operation failed.', 0, $previous);

        $this->errorInfo = $previous->errorInfo;
        $this->sqlState = self::extractSqlState($previous);
        $this->driverCode = self::extractDriverCode($previous);
    }

    public static function from(string $stage, PDOException $previous): self
    {
        if ($previous instanceof self) {
            return $previous;
        }

        return new self($stage, $previous::class, $previous);
    }

    public function stage(): string
    {
        return $this->stage;
    }

    public function exceptionClass(): string
    {
        return $this->exceptionClass;
    }

    public function sqlState(): ?string
    {
        return $this->sqlState;
    }

    public function driverCode(): int|string|null
    {
        return $this->driverCode;
    }

    public static function extractSqlState(PDOException $exception): ?string
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        if (self::isSqlState($sqlState)) {
            return $sqlState;
        }

        $code = (string) $exception->getCode();

        return self::isSqlState($code) ? $code : null;
    }

    public static function extractDriverCode(PDOException $exception): int|string|null
    {
        $driverCode = $exception->errorInfo[1] ?? null;
        if (is_int($driverCode) || is_string($driverCode)) {
            return $driverCode;
        }

        return null;
    }

    private static function isSqlState(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9A-Z]{5}$/', $value) === 1;
    }
}
