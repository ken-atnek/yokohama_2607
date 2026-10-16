<?php
declare(strict_types=1);

namespace DandyNotification\Log;

final class SensitiveDataSanitizer
{
    private const REDACTED = '[redacted]';
    private const REDACTED_URL = '[redacted-url]';
    private const TOKEN_PARAMETER_PATTERN = 'token|raw_token|code|verification_code|auth_code|key|pk|t|csrf|session';

    private const NON_SENSITIVE_KEYS = [
        'status_code',
        'http_code',
        'response_code',
    ];

    private const EXACT_SENSITIVE_KEYS = [
        'code',
        'verification_code',
        'auth_code',
        'pk',
        'csrf',
        'session',
        'session_key',
        'set_cookie',
        'return_path',
        'password',
        'raw',
        'raw_token',
        'token',
        'secret',
        'authorization',
        'credential',
        'api_key',
        'cookie',
        'session_id',
        'header',
        'body',
        'url',
    ];

    private const SEGMENT_SENSITIVE_KEYS = [
        'password',
        'token',
        'raw',
        'secret',
        'authorization',
        'credential',
        'api_key',
        'cookie',
        'session',
        'session_key',
        'set_cookie',
        'return_path',
        'body',
        'url',
        'header',
        'csrf',
        'pk',
    ];

    public function sanitizeContext(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $sanitized[(string) $key] = $this->sanitizeValue((string) $key, $value);
        }

        return $sanitized;
    }

    public function sanitizeMessage(string $message): string
    {
        $message = preg_replace('/mailto:[^\s<>"\']+/i', self::REDACTED_URL, $message) ?? $message;
        $message = preg_replace('/https?:\/\/\S+/i', self::REDACTED_URL, $message) ?? $message;
        $message = $this->maskRelativeSensitiveUrls($message);
        $message = preg_replace(
            '/([?&](?:' . self::TOKEN_PARAMETER_PATTERN . ')=)[^&\s<>"\']+/i',
            '$1' . self::REDACTED,
            $message
        ) ?? $message;
        $message = $this->maskEmails($message);

        return $message;
    }

    private function sanitizeValue(string $key, mixed $value): mixed
    {
        if (strtolower(trim($key)) === 'token_length' && is_int($value)) {
            return $value;
        }

        if ($this->isSensitiveKey($key)) {
            if ($this->isEmailKey($key) && is_string($value)) {
                return $this->maskEmail($value);
            }

            return self::REDACTED;
        }

        if (is_object($value)) {
            return '[object ' . $value::class . ']';
        }

        if (is_resource($value)) {
            return '[resource ' . get_resource_type($value) . ']';
        }

        if (is_array($value)) {
            $nested = [];
            foreach ($value as $nestedKey => $nestedValue) {
                $nested[(string) $nestedKey] = $this->sanitizeValue((string) $nestedKey, $nestedValue);
            }

            return $nested;
        }

        if (is_string($value)) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $this->maskEmail($value);
            }

            return $this->sanitizeMessage($value);
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower(trim($key));

        if (in_array($key, self::NON_SENSITIVE_KEYS, true)) {
            return false;
        }

        if (str_contains($key, 'email') && !str_contains($key, 'hash')) {
            return true;
        }

        if (in_array($key, self::EXACT_SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (self::SEGMENT_SENSITIVE_KEYS as $segment) {
            if ($this->hasKeySegment($key, $segment)) {
                return true;
            }
        }

        return false;
    }

    private function isEmailKey(string $key): bool
    {
        $key = strtolower($key);

        return str_contains($key, 'email') && !str_contains($key, 'hash');
    }

    private function maskEmails(string $value): string
    {
        return preg_replace_callback(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            fn (array $matches): string => $this->maskEmail($matches[0]),
            $value
        ) ?? $value;
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return self::REDACTED;
        }

        $local = $parts[0];
        $domain = $parts[1];
        $visible = $local === '' ? '*' : substr($local, 0, 1) . '***';

        return $visible . '@' . $domain;
    }

    private function maskRelativeSensitiveUrls(string $message): string
    {
        $message = preg_replace(
            '~(?<![A-Za-z0-9])/[^\s<>"\']*(?:verify|auth|settings|wgmailsys)[^\s<>"\']*~i',
            self::REDACTED_URL,
            $message
        ) ?? $message;

        return preg_replace(
            '~(?<![A-Za-z0-9])\?[^\s<>"\']*(?:' . self::TOKEN_PARAMETER_PATTERN . ')=[^\s<>"\']*~i',
            self::REDACTED_URL,
            $message
        ) ?? $message;
    }

    private function hasKeySegment(string $key, string $segment): bool
    {
        return preg_match('/(?:^|[_\-.])' . preg_quote($segment, '/') . '(?=$|[_\-.])/', $key) === 1;
    }
}
