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

final class RegistrationConfirmationService
{
    private const SESSION_KEY = 'notification_confirmation';
    private const CSRF_SCOPE = 'notification_confirmation';
    private const INVALID_MESSAGE = 'このURLは無効か、有効期限が切れています。お手数ですが、女の子詳細ページからもう一度登録してください。';
    private const MAINTENANCE_MESSAGE = '現在この機能はご利用いただけません。時間をおいて再度お試しください。';

    private const PUBLIC_STATE_UNAVAILABLE_MESSAGE = '現在この女の子の出勤お知らせ登録を完了できません。';

    public function __construct(
        private readonly PDO $pdo,
        private readonly RepositorySet $repositories,
        private readonly DeliveryService $deliveryService,
        private readonly SettingsService $settingsService,
        private readonly FeatureFlags $featureFlags,
        private readonly AppConfig $config,
        private readonly JsonLogger $logger
    ) {
    }

    public function preview(string $rawToken, string $requestId): array
    {
        $this->clearConfirmationState();

        if (!$this->confirmationAvailable()) {
            return $this->maintenanceResponse();
        }

        if (preg_match('/^[a-f0-9]{64}$/', $rawToken) !== 1) {
            $this->logInvalidPreview(
                $requestId,
                'invalid_token_format',
                $this->invalidTokenShape($rawToken)
            );
            return $this->invalidResponse();
        }

        $tokenHash = hash('sha256', $rawToken, true);
        unset($rawToken);

        $validation = $this->validateTokenGraph(
            $this->repositories->tokens()->findUsableVerificationByHash($tokenHash),
            false
        );

        if (!$validation['valid']) {
            $this->logInvalidPreview($requestId, $validation['reason']);
            return $this->invalidResponse();
        }

        $subscription = $validation['subscription'];
        $this->settingsService->session()->regenerateId();
        $this->settingsService->session()->set(self::SESSION_KEY, [
            'token_hash_hex' => bin2hex($tokenHash),
            'subscriber_id' => (int) $validation['subscriber']['subscriber_id'],
            'subscription_id' => (int) $subscription['subscription_id'],
            'created_at' => $this->nowIsoUtc(),
        ]);

        $csrfToken = $this->settingsService->csrfToken(self::CSRF_SCOPE);

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'state' => 'ready',
                'shopName' => $this->displayName($subscription['shop_name_snapshot'], (string) $subscription['shop_id']),
                'castName' => $this->displayName($subscription['cast_name_snapshot'], (string) $subscription['cast_id']),
                'csrfToken' => $csrfToken,
            ],
        ];
    }

    public function confirm(string $csrfToken, string $requestId): array
    {
        if (!$this->confirmationAvailable()) {
            $this->clearConfirmationState();
            return $this->maintenanceResponse();
        }

        try {
            $this->settingsService->requireValidPost(self::CSRF_SCOPE, $csrfToken);
        } catch (SecurityException) {
            $this->clearConfirmationState();
            $this->logInvalidConfirm($requestId, 'invalid_csrf');
            return $this->invalidResponse(403);
        }

        $sessionState = $this->settingsService->session()->get(self::SESSION_KEY);
        if (!is_array($sessionState)
            || !isset($sessionState['token_hash_hex'])
            || !is_string($sessionState['token_hash_hex'])
            || preg_match('/^[a-f0-9]{64}$/', $sessionState['token_hash_hex']) !== 1
        ) {
            $this->clearConfirmationState();
            $this->logInvalidConfirm($requestId, 'session_token_hash_missing');
            return $this->invalidResponse();
        }

        $tokenHash = hex2bin($sessionState['token_hash_hex']);
        if ($tokenHash === false) {
            $this->clearConfirmationState();
            $this->logInvalidConfirm($requestId, 'session_token_hash_invalid');
            return $this->invalidResponse();
        }

        $this->pdo->beginTransaction();
        try {
            $validation = $this->validateTokenGraph(
                $this->repositories->tokens()->findUsableVerificationByHashForUpdate($tokenHash),
                true
            );

            if (!$validation['valid']) {
                $this->pdo->rollBack();
                $this->clearConfirmationState();
                $this->logInvalidConfirm($requestId, $validation['reason']);
                return $this->invalidResponse();
            }

            $token = $validation['token'];
            $subscriber = $validation['subscriber'];
            $subscription = $validation['subscription'];
            $subscriberId = (int) $subscriber['subscriber_id'];
            $subscriptionId = (int) $subscription['subscription_id'];
            $shopId = (string) $subscription['shop_id'];
            $castId = (int) $subscription['cast_id'];
            $shopName = $this->displayName($subscription['shop_name_snapshot'], $shopId);
            $castName = $this->displayName($subscription['cast_name_snapshot'], (string) $castId);
            $consentVersion = $this->consentVersion();

            $publicStateValidation = $this->validatePublicStateForActivation($subscription);
            if (!$publicStateValidation['valid']) {
                $this->pdo->rollBack();
                $this->clearConfirmationState();
                $this->logInvalidConfirm($requestId, (string) $publicStateValidation['reason']);
                return $this->publicStateUnavailableResponse();
            }

            $this->repositories->subscribers()->activateAfterEmailVerification($subscriberId, $consentVersion);
            if ($this->repositories->subscriptions()->activateVerifiedSubscription($subscriptionId) !== 1) {
                $this->pdo->rollBack();
                $this->clearConfirmationState();
                $this->logInvalidConfirm($requestId, 'subscription_activation_guard_failed');
                return $this->invalidResponse();
            }

            if (!$this->repositories->tokens()->markVerificationUsed((int) $token['token_id'])) {
                $this->pdo->rollBack();
                $this->clearConfirmationState();
                $this->logInvalidConfirm($requestId, 'token_mark_used_failed');
                return $this->invalidResponse();
            }

            $snapshot = $this->deliveryService->buildTemplateDataSnapshot('registration_complete', [
                'request_id' => $requestId,
                'shop_id' => $shopId,
                'cast_id' => $castId,
                'shop_name' => $shopName,
                'cast_name' => $castName,
            ]);
            $this->repositories->deliveries()->insertDelivery(
                'registration_complete',
                $subscriberId,
                $subscriptionId,
                (string) $subscriber['email'],
                $snapshot
            );

            $this->repositories->auditLogs()->insertUserAction([
                'subscriber_id' => $subscriberId,
                'subscription_id' => $subscriptionId,
                'action' => 'confirmation_completed',
                'result' => 'success',
                'request_id' => $requestId,
                'email_hash' => hash('sha256', (string) $subscriber['email'], true),
                'shop_id' => $shopId,
                'cast_id' => $castId,
                'metadata' => $this->metadata([
                    'reason_code' => 'email_verified',
                    'consent_version' => $consentVersion,
                    'delivery_mail_type' => 'registration_complete',
                ]),
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->clearConfirmationState();
            throw $exception;
        }

        $this->clearConfirmationState();
        $this->settingsService->session()->regenerateId();

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'state' => 'completed',
                'redirectUrl' => '/wgmailsys/complete/',
            ],
        ];
    }

    private function confirmationAvailable(): bool
    {
        return $this->featureFlags->notificationConfirmationEnabled()
            && $this->featureFlags->notificationDeliveryCreateEnabled();
    }

    private function validateTokenGraph(?array $token, bool $forUpdate): array
    {
        if ($token === null) {
            return $this->invalidValidation('token_not_found');
        }

        if ((string) ($token['purpose'] ?? '') !== 'verify_email') {
            return $this->invalidValidation('invalid_purpose');
        }

        $subscriptionId = isset($token['subscription_id']) ? (int) $token['subscription_id'] : 0;
        if ($subscriptionId < 1) {
            return $this->invalidValidation('subscription_missing');
        }

        $subscriberId = (int) $token['subscriber_id'];
        $subscriber = $forUpdate
            ? $this->repositories->subscribers()->findByIdForUpdate($subscriberId)
            : $this->repositories->subscribers()->findById($subscriberId);
        if ($subscriber === null) {
            return $this->invalidValidation('subscriber_missing');
        }

        $subscription = $this->repositories->subscriptions()->findById($subscriptionId);
        if ($subscription === null) {
            return $this->invalidValidation('subscription_missing');
        }

        if ((int) $subscription['subscriber_id'] !== $subscriberId) {
            return $this->invalidValidation('subscription_subscriber_mismatch');
        }

        $subscriberStatus = (string) $subscriber['status'];
        if (!in_array($subscriberStatus, ['pending', 'active'], true)
            || $subscriber['unsubscribed_at'] !== null
            || $subscriber['suppressed_at'] !== null
        ) {
            return $this->invalidValidation('invalid_subscriber_status');
        }

        if ($this->repositories->suppressions()->existsActiveByEmail((string) $subscriber['email'])) {
            return $this->invalidValidation('suppressed');
        }

        if ((string) $subscription['status'] !== 'pending') {
            return $this->invalidValidation('invalid_subscription_status');
        }

        return [
            'valid' => true,
            'reason' => null,
            'token' => $token,
            'subscriber' => $subscriber,
            'subscription' => $subscription,
        ];
    }

    private function validatePublicStateForActivation(array $subscription): array
    {
        $shopId = (string) ($subscription['shop_id'] ?? '');
        $castId = (int) ($subscription['cast_id'] ?? 0);
        if ($shopId === '' || $castId < 1) {
            return $this->invalidValidation('cast_public_state_invalid_target');
        }

        $girl = $this->repositories->girls()->findByCastForUpdate($castId);
        if ($girl === null) {
            return $this->invalidValidation('cast_public_state_missing');
        }

        if ((string) ($girl['att_shop'] ?? '') !== $shopId) {
            return $this->invalidValidation('cast_public_state_shop_mismatch');
        }

        if ((string) ($girl['view'] ?? '') !== '1') {
            return $this->invalidValidation('cast_public_state_not_public');
        }

        return [
            'valid' => true,
            'reason' => null,
            'token' => null,
            'subscriber' => null,
            'subscription' => null,
        ];
    }

    private function clearConfirmationState(): void
    {
        $this->settingsService->session()->remove(self::SESSION_KEY);
        $this->settingsService->invalidateCsrf(self::CSRF_SCOPE);
    }

    private function invalidValidation(string $reason): array
    {
        return [
            'valid' => false,
            'reason' => $reason,
            'token' => null,
            'subscriber' => null,
            'subscription' => null,
        ];
    }

    private function maintenanceResponse(): array
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

    private function publicStateUnavailableResponse(): array
    {
        return [
            'status' => 200,
            'body' => [
                'ok' => false,
                'state' => 'public_state_unavailable',
                'message' => self::PUBLIC_STATE_UNAVAILABLE_MESSAGE,
            ],
        ];
    }

    private function nowIsoUtc(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }

    private function displayName(mixed $value, string $fallback): string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return $fallback;
    }

    private function consentVersion(): string
    {
        $confirmationConfig = $this->config->confirmationConfig();

        return (string) $confirmationConfig['consent_version'];
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

    private function invalidTokenShape(string $rawToken): array
    {
        return [
            'token_length' => strlen($rawToken),
            'has_whitespace' => preg_match('/\s/', $rawToken) === 1,
            'has_non_hex' => preg_match('/[^a-f0-9]/', $rawToken) === 1,
            'has_uppercase' => preg_match('/[A-F]/', $rawToken) === 1,
        ];
    }

    private function logInvalidPreview(string $requestId, string $reason, array $context = []): void
    {
        $this->logger->info('notification confirmation preview rejected', $context + [
            'request_id' => $requestId,
            'reason_code' => $reason,
        ]);
    }

    private function logInvalidConfirm(string $requestId, string $reason): void
    {
        $this->logger->info('notification confirmation confirm rejected', [
            'request_id' => $requestId,
            'reason_code' => $reason,
        ]);
    }
}
