<?php
declare(strict_types=1);

namespace DandyNotification\Security;

use DandyNotification\Exception\SecurityException;

final class CsrfTokenManager
{
    private const SESSION_KEY = '_dandy_notification_csrf';

    public function __construct(private readonly SessionManager $session)
    {
    }

    public function generate(string $scope): string
    {
        $token = bin2hex(random_bytes(32));
        $tokens = $this->session->get(self::SESSION_KEY);
        if (!is_array($tokens)) {
            $tokens = [];
        }

        $tokens[$scope] = hash('sha256', $token);
        $this->session->set(self::SESSION_KEY, $tokens);

        return $token;
    }

    public function verify(string $scope, ?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $tokens = $this->session->get(self::SESSION_KEY);
        if (!is_array($tokens) || !isset($tokens[$scope]) || !is_string($tokens[$scope])) {
            return false;
        }

        return hash_equals($tokens[$scope], hash('sha256', $token));
    }

    public function invalidate(string $scope): void
    {
        $tokens = $this->session->get(self::SESSION_KEY);
        if (!is_array($tokens)) {
            return;
        }

        unset($tokens[$scope]);

        if ($tokens === []) {
            $this->session->remove(self::SESSION_KEY);
            return;
        }

        $this->session->set(self::SESSION_KEY, $tokens);
    }

    public function requireValidPost(string $scope, ?string $token): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new SecurityException('POST method is required.');
        }

        if (!$this->verify($scope, $token)) {
            throw new SecurityException('Invalid CSRF token.');
        }
    }
}
