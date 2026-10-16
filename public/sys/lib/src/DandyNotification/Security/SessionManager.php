<?php
declare(strict_types=1);

namespace DandyNotification\Security;

use DandyNotification\Exception\SecurityException;

final class SessionManager
{
    public function __construct(private readonly array $config)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent()) {
            throw new SecurityException('Headers already sent before session start.');
        }

        $cookie = $this->config['cookie'] ?? [];
        $name = (string) ($this->config['name'] ?? 'DANDY_NOTIFICATION_SESSION');
        $this->validateSessionName($name);

        $secure = $this->boolOption($cookie, 'secure', true);
        $domain = $this->stringOption($cookie, 'domain', '');
        $path = $this->stringOption($cookie, 'path', '/');
        $this->validateCookieString('domain', $domain, true);
        $this->validateCookieString('path', $path, false);

        $sameSite = (string) ($cookie['samesite'] ?? 'Lax');
        $this->validateSameSite($sameSite, $secure);

        $lifetime = $this->intOption($cookie, 'lifetime', 86400);
        if ($lifetime < 0) {
            throw new SecurityException('Session cookie lifetime is invalid.');
        }

        $this->setIni('session.use_strict_mode', '1');
        $this->setIni('session.use_only_cookies', '1');
        $this->setIni('session.use_trans_sid', '0');

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $this->boolOption($cookie, 'httponly', true),
            'samesite' => $sameSite,
        ]);

        if (session_start() !== true) {
            throw new SecurityException('Session start failed.');
        }
    }

    public function regenerateId(): void
    {
        // Call this after authentication state changes, such as registration completion.
        $this->start();
        $this->assertSessionRegenerated(session_regenerate_id(true));
    }

    public function get(string $key): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function cookieOptions(): array
    {
        return $this->config['cookie'] ?? [];
    }

    private function validateSessionName(string $name): void
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{1,127}$/', $name) !== 1) {
            throw new SecurityException('Session name is invalid.');
        }
    }

    private function validateCookieString(string $key, string $value, bool $allowEmpty): void
    {
        if (!$allowEmpty && $value === '') {
            throw new SecurityException('Session cookie ' . $key . ' must not be empty.');
        }

        if (preg_match('/[\r\n]/', $value) === 1) {
            throw new SecurityException('Session cookie ' . $key . ' must not contain line breaks.');
        }
    }

    private function validateSameSite(string $sameSite, bool $secure): void
    {
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new SecurityException('Session cookie SameSite is invalid.');
        }

        if ($sameSite === 'None' && !$secure) {
            throw new SecurityException('Session cookie secure is required when SameSite=None.');
        }
    }

    private function boolOption(array $options, string $key, bool $default): bool
    {
        $value = $options[$key] ?? $default;
        if (!is_bool($value)) {
            throw new SecurityException('Session cookie ' . $key . ' must be boolean.');
        }

        return $value;
    }

    private function intOption(array $options, string $key, int $default): int
    {
        $value = $options[$key] ?? $default;
        if (!is_int($value)) {
            throw new SecurityException('Session cookie ' . $key . ' must be integer.');
        }

        return $value;
    }

    private function stringOption(array $options, string $key, string $default): string
    {
        $value = $options[$key] ?? $default;
        if (!is_string($value)) {
            throw new SecurityException('Session cookie ' . $key . ' must be string.');
        }

        return $value;
    }

    private function assertSessionRegenerated(bool $regenerated): void
    {
        if (!$regenerated) {
            throw new SecurityException('Session ID regeneration failed.');
        }
    }

    private function setIni(string $key, string $value): void
    {
        if (ini_set($key, $value) === false) {
            throw new SecurityException('Session ini setting failed.');
        }
    }
}
