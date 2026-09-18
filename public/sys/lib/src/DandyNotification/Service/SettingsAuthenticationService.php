<?php
declare(strict_types=1);

namespace DandyNotification\Service;

use DateTimeImmutable;
use DateTimeZone;
use DandyNotification\Config\AppConfig;
use DandyNotification\Config\FeatureFlags;
use DandyNotification\Exception\SecurityException;
use DandyNotification\Log\JsonLogger;
use DandyNotification\RepositorySet;
use JsonException;
use PDO;
use Throwable;

final class SettingsAuthenticationService
{
    public const SETTINGS_SESSION_KEY = 'notification_settings_auth';
    public const SETTINGS_CSRF_SCOPE = 'notification_settings';

    private const PREVIEW_SESSION_KEY = 'notification_settings_auth_preview';
    private const AUTH_CSRF_SCOPE = 'notification_settings_auth';
    private const INVALID_MESSAGE = 'このURLは無効か、有効期限が切れています。設定リンクをもう一度取得してください。';
    private const AUTH_REQUIRED_MESSAGE = '設定画面を開くには、設定リンクから認証してください。';
    private const MAINTENANCE_MESSAGE = '現在この機能はご利用いただけません。時間をおいて再度お試しください。';

    public function __construct(
        private readonly PDO $pdo,
        private readonly RepositorySet $repositories,
        private readonly SettingsService $settingsService,
        private readonly FeatureFlags $featureFlags,
        private readonly AppConfig $config,
        private readonly JsonLogger $logger
    ) {
    }

    public function preview(string $rawToken, string $requestId): array
    {
        $this->clearPreviewState();

        if (!$this->settingsAvailable()) {
            return $this->maintenanceResponse();
        }

        if (preg_match('/^[a-f0-9]{64}$/', $rawToken) !== 1) {
            $this->insertRejectedAudit($requestId, null, null, 'invalid_token_format');
            return $this->invalidResponse();
        }

        $tokenHash = hash('sha256', $rawToken, true);
        unset($rawToken);

        $validation = $this->validateManageTokenGraph(
            $this->repositories->tokens()->findUsableManageSettingsByHash($tokenHash),
            false
        );

        if (!$validation['valid']) {
            $this->insertRejectedAudit(
                $requestId,
                $validation['subscriber_id'],
                $validation['email_hash'],
                $validation['reason']
            );
            return $this->invalidResponse();
        }

        $subscriberId = (int) $validation['subscriber']['subscriber_id'];
        $this->settingsService->session()->regenerateId();
        $this->settingsService->session()->set(self::PREVIEW_SESSION_KEY, [
            'token_hash_hex' => bin2hex($tokenHash),
            'subscriber_id' => $subscriberId,
            'created_at' => $this->nowIsoUtc(),
        ]);

        $csrfToken = $this->settingsService->csrfToken(self::AUTH_CSRF_SCOPE);

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'state' => 'ready',
                'csrfToken' => $csrfToken,
            ],
        ];
    }

    public function authenticate(string $csrfToken, string $requestId): array
    {
        if (!$this->settingsMutationAvailable()) {
            $this->clearPreviewState();
            return $this->maintenanceResponse();
        }

        $sessionState = $this->settingsService->session()->get(self::PREVIEW_SESSION_KEY);
        try {
            $this->settingsService->requireValidPost(self::AUTH_CSRF_SCOPE, $csrfToken);
        } catch (SecurityException) {
            $this->clearPreviewState();
            $this->insertRejectedAuditFromSession($requestId, $sessionState, 'invalid_csrf');
            return $this->invalidResponse(403);
        }

        if (!is_array($sessionState)
            || !isset($sessionState['token_hash_hex'])
            || !is_string($sessionState['token_hash_hex'])
            || preg_match('/^[a-f0-9]{64}$/', $sessionState['token_hash_hex']) !== 1
        ) {
            $this->clearPreviewState();
            $this->insertRejectedAuditFromSession($requestId, $sessionState, 'session_token_hash_missing');
            return $this->invalidResponse();
        }

        $tokenHash = hex2bin($sessionState['token_hash_hex']);
        if ($tokenHash === false) {
            $this->clearPreviewState();
            $this->insertRejectedAuditFromSession($requestId, $sessionState, 'session_token_hash_invalid');
            return $this->invalidResponse();
        }

        $this->pdo->beginTransaction();
        try {
            $validation = $this->validateManageTokenGraph(
                $this->repositories->tokens()->findUsableManageSettingsByHashForUpdate($tokenHash),
                true
            );

            if (!$validation['valid']) {
                $this->pdo->rollBack();
                $this->clearPreviewState();
                $this->insertRejectedAudit(
                    $requestId,
                    $validation['subscriber_id'],
                    $validation['email_hash'],
                    $validation['reason']
                );
                return $this->invalidResponse();
            }

            $token = $validation['token'];
            $subscriber = $validation['subscriber'];
            $subscriberId = (int) $subscriber['subscriber_id'];

            if (!$this->repositories->tokens()->markManageSettingsUsed((int) $token['token_id'])) {
                $this->pdo->rollBack();
                $this->clearPreviewState();
                $this->insertRejectedAudit($requestId, $subscriberId, $this->emailHash((string) $subscriber['email']), 'token_mark_used_failed');
                return $this->invalidResponse();
            }

            $this->repositories->auditLogs()->insertUserAction([
                'subscriber_id' => $subscriberId,
                'action' => 'settings_authenticated',
                'result' => 'success',
                'request_id' => $requestId,
                'email_hash' => $this->emailHash((string) $subscriber['email']),
                'metadata' => $this->metadata([
                    'reason_code' => 'settings_session_issued',
                ]),
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->clearPreviewState();
            throw $exception;
        }

        $this->clearPreviewState();
        $this->settingsService->session()->regenerateId();
        $authenticatedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->settingsService->session()->set(self::SETTINGS_SESSION_KEY, [
            'subscriber_id' => $subscriberId,
            'authenticated_at' => $authenticatedAt->format('Y-m-d\TH:i:s\Z'),
            'expires_at' => $authenticatedAt
                ->modify('+' . $this->sessionLifetimeSeconds() . ' seconds')
                ->format('Y-m-d\TH:i:s\Z'),
        ]);

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'state' => 'authenticated',
            ],
        ];
    }

    public function validateSettingsSession(): array
    {
        if (!$this->settingsAvailable()) {
            $this->clearSettingsSession();
            return $this->invalidSession('maintenance');
        }

        $sessionState = $this->settingsService->session()->get(self::SETTINGS_SESSION_KEY);
        if (!is_array($sessionState)
            || !isset($sessionState['subscriber_id'], $sessionState['expires_at'])
            || !is_int($sessionState['subscriber_id'])
            || !is_string($sessionState['expires_at'])
            || $sessionState['subscriber_id'] < 1
        ) {
            $this->clearSettingsSession();
            return $this->invalidSession('session_missing');
        }

        $expiresAt = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s\Z',
            $sessionState['expires_at'],
            new DateTimeZone('UTC')
        );
        if (!$expiresAt instanceof DateTimeImmutable || $expiresAt <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            $this->clearSettingsSession();
            return $this->invalidSession('session_expired');
        }

        $subscriber = $this->repositories->subscribers()->findByIdForSettings($sessionState['subscriber_id']);
        if ($subscriber === null) {
            $this->clearSettingsSession();
            return $this->invalidSession('subscriber_invalid_status');
        }

        $status = (string) ($subscriber['status'] ?? '');
        if (!in_array($status, ['active', 'suppressed'], true)) {
            $this->clearSettingsSession();
            return $this->invalidSession('subscriber_invalid_status');
        }

        return [
            'valid' => true,
            'reason' => null,
            'subscriber_id' => (int) $subscriber['subscriber_id'],
            'subscriber' => $subscriber,
            'suppressed' => $status === 'suppressed'
                || $this->repositories->suppressions()->existsActiveByEmail((string) $subscriber['email']),
        ];
    }

    public function settingsCsrfToken(): string
    {
        return $this->settingsService->csrfToken(self::SETTINGS_CSRF_SCOPE);
    }

    public function requireSettingsCsrf(string $csrfToken): void
    {
        $this->settingsService->requireValidPost(self::SETTINGS_CSRF_SCOPE, $csrfToken);
    }

    public function clearPreviewState(): void
    {
        $this->settingsService->session()->remove(self::PREVIEW_SESSION_KEY);
        $this->settingsService->invalidateCsrf(self::AUTH_CSRF_SCOPE);
    }

    public function clearSettingsSession(): void
    {
        $this->settingsService->session()->remove(self::SETTINGS_SESSION_KEY);
        $this->settingsService->invalidateCsrf(self::SETTINGS_CSRF_SCOPE);
    }

    public function clearAllAuthenticationState(): void
    {
        $this->clearPreviewState();
        $this->clearSettingsSession();
    }

    public function authenticationRequiredResponse(int $status = 401): array
    {
        return [
            'status' => $status,
            'body' => [
                'ok' => false,
                'state' => 'authentication_required',
                'message' => self::AUTH_REQUIRED_MESSAGE,
            ],
        ];
    }

    public function maintenanceResponse(): array
    {
        return [
            'status' => 503,
            'body' => [
                'ok' => false,
                'state' => 'maintenance',
                'message' => self::MAINTENANCE_MESSAGE,
            ],
        ];
    }

    public function settingsAvailable(): bool
    {
        return $this->featureFlags->notificationSettingsEnabled();
    }

    public function settingsMutationAvailable(): bool
    {
        return $this->featureFlags->notificationSettingsEnabled()
            && $this->featureFlags->notificationDeliveryCreateEnabled();
    }

    private function validateManageTokenGraph(?array $token, bool $forUpdate): array
    {
        if ($token === null) {
            return $this->invalidTokenValidation('token_not_found');
        }

        if ((string) ($token['purpose'] ?? '') !== 'manage_settings'
            || $token['subscription_id'] !== null
        ) {
            return $this->invalidTokenValidation('invalid_purpose');
        }

        $subscriberId = (int) $token['subscriber_id'];
        $subscriber = $forUpdate
            ? $this->repositories->subscribers()->findByIdForSettingsUpdate($subscriberId)
            : $this->repositories->subscribers()->findByIdForSettings($subscriberId);
        if ($subscriber === null) {
            return $this->invalidTokenValidation('subscriber_missing', $subscriberId);
        }

        if ((string) $subscriber['status'] !== 'active') {
            return $this->invalidTokenValidation(
                'subscriber_invalid_status',
                $subscriberId,
                $this->emailHash((string) $subscriber['email'])
            );
        }

        return [
            'valid' => true,
            'reason' => null,
            'subscriber_id' => $subscriberId,
            'email_hash' => $this->emailHash((string) $subscriber['email']),
            'token' => $token,
            'subscriber' => $subscriber,
        ];
    }

    private function invalidTokenValidation(string $reason, ?int $subscriberId = null, ?string $emailHash = null): array
    {
        return [
            'valid' => false,
            'reason' => $reason,
            'subscriber_id' => $subscriberId,
            'email_hash' => $emailHash,
            'token' => null,
            'subscriber' => null,
        ];
    }

    private function invalidSession(string $reason): array
    {
        return [
            'valid' => false,
            'reason' => $reason,
            'subscriber_id' => null,
            'subscriber' => null,
            'suppressed' => false,
        ];
    }

    private function invalidResponse(int $status = 200): array
    {
        return [
            'status' => $status,
            'body' => [
                'ok' => false,
                'state' => 'invalid_or_expired',
                'message' => self::INVALID_MESSAGE,
            ],
        ];
    }

    private function insertRejectedAuditFromSession(string $requestId, mixed $sessionState, string $reason): void
    {
        $subscriberId = is_array($sessionState) && isset($sessionState['subscriber_id']) && is_int($sessionState['subscriber_id'])
            ? $sessionState['subscriber_id']
            : null;
        $emailHash = null;

        if ($subscriberId !== null) {
            $subscriber = $this->repositories->subscribers()->findByIdForSettings($subscriberId);
            if ($subscriber !== null && isset($subscriber['email'])) {
                $emailHash = $this->emailHash((string) $subscriber['email']);
            }
        }

        $this->insertRejectedAudit($requestId, $subscriberId, $emailHash, $reason);
    }

    private function insertRejectedAudit(string $requestId, ?int $subscriberId, ?string $emailHash, string $reason): void
    {
        $this->repositories->auditLogs()->insertUserAction([
            'subscriber_id' => $subscriberId,
            'action' => 'settings_authentication_rejected',
            'result' => 'failure',
            'request_id' => $requestId,
            'email_hash' => $emailHash,
            'metadata' => $this->metadata([
                'reason_code' => $reason,
            ]),
        ]);

        $this->logger->info('notification settings authentication rejected', [
            'request_id' => $requestId,
            'reason_code' => $reason,
        ]);
    }

    private function sessionLifetimeSeconds(): int
    {
        $settings = $this->config->settingsConfig();

        return (int) $settings['session_lifetime_seconds'];
    }

    private function nowIsoUtc(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }

    private function emailHash(string $email): string
    {
        return hash('sha256', $email, true);
    }

    /**
     * @param array<string, string> $metadata
     */
    private function metadata(array $metadata): string
    {
        try {
            return json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new JsonException('Audit metadata JSON encoding failed.', 0, $exception);
        }
    }
}
