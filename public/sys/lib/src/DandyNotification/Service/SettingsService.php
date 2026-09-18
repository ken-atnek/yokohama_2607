<?php
declare(strict_types=1);

namespace DandyNotification\Service;

use DandyNotification\Security\CsrfTokenManager;
use DandyNotification\Security\SessionManager;

final class SettingsService
{
    public function __construct(
        private readonly SessionManager $session,
        private readonly CsrfTokenManager $csrf
    ) {
    }

    public function session(): SessionManager
    {
        return $this->session;
    }

    public function csrf(): CsrfTokenManager
    {
        return $this->csrf;
    }

    public function csrfToken(string $scope): string
    {
        return $this->csrf->generate($scope);
    }

    public function invalidateCsrf(string $scope): void
    {
        $this->csrf->invalidate($scope);
    }

    public function requireValidPost(string $scope, ?string $token): void
    {
        $this->csrf->requireValidPost($scope, $token);
    }
}
