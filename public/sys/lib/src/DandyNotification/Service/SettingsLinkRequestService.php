<?php

declare(strict_types=1);

namespace DandyNotification\Service;

use DandyNotification\Config\AppConfig;
use DandyNotification\Config\FeatureFlags;
use DandyNotification\Exception\SecurityException;
use DandyNotification\Exception\ValidationException;
use DandyNotification\RepositorySet;
use DandyNotification\Validation\EmailAddress;
use JsonException;
use PDO;
use Throwable;

final class SettingsLinkRequestService
{
    private const CSRF_SCOPE = 'notification_settings_request';
    private const ACCEPTED_MESSAGE = '入力されたメールアドレスの登録状況を確認し、設定用メールを送信します。';
    private const VALIDATION_MESSAGE = 'メールアドレスを正しく入力してください。';

    public function __construct(
        private readonly PDO $pdo,
        private readonly RepositorySet $repositories,
        private readonly DeliveryService $deliveryService,
        private readonly SettingsService $settingsService,
        private readonly SettingsAuthenticationService $authenticationService,
        private readonly AppConfig $config,
        private readonly FeatureFlags $featureFlags
    ) {}

    public function prepare(): array
    {
        if (!$this->featureFlags->notificationSettingsEnabled()) {
            return $this->authenticationService->maintenanceResponse();
        }

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'state' => 'ready',
                'csrfToken' => $this->settingsService->csrfToken(self::CSRF_SCOPE),
            ],
        ];
    }

    public function request(string $email, string $csrfToken, string $requestId): array
    {
        if (!$this->featureFlags->notificationSettingsEnabled()) {
            $this->settingsService->invalidateCsrf(self::CSRF_SCOPE);
            return $this->authenticationService->maintenanceResponse();
        }

        try {
            $this->settingsService->requireValidPost(self::CSRF_SCOPE, $csrfToken);
        } catch (SecurityException) {
            return [
                'status' => 403,
                'body' => [
                    'ok' => false,
                    'state' => 'forbidden',
                    'message' => 'リクエスト形式が不正です。',
                ],
            ];
        }

        try {
            $normalizedEmail = EmailAddress::normalize($email);
        } catch (ValidationException) {
            return [
                'status' => 400,
                'body' => [
                    'ok' => false,
                    'state' => 'validation_error',
                    'message' => self::VALIDATION_MESSAGE,
                ],
            ];
        }

        if (!$this->mutationAvailable()) {
            $this->settingsService->invalidateCsrf(self::CSRF_SCOPE);
            return $this->acceptedResponse();
        }

        $subscriber = null;
        $reasonCode = 'subscriber_not_found';

        $this->pdo->beginTransaction();
        try {
            $subscriber = $this->repositories->subscribers()->findByEmailForSettingsLinkUpdate($normalizedEmail);
            if ($subscriber !== null) {
                $subscriberId = (int) $subscriber['subscriber_id'];
                $status = (string) $subscriber['status'];
                if ($status === 'pending') {
                    $reasonCode = 'subscriber_pending';
                } elseif ($status === 'unsubscribed') {
                    $reasonCode = 'subscriber_unsubscribed';
                } elseif ($status === 'suppressed') {
                    $reasonCode = 'subscriber_suppressed';
                } elseif (
                    $status === 'active'
                    && $this->repositories->suppressions()->existsActiveByEmail($normalizedEmail)
                ) {
                    $reasonCode = 'subscriber_suppressed';
                } elseif ($status === 'active') {
                    $snapshot = $this->deliveryService->buildTemplateDataSnapshot('settings_link', [
                        'request_id' => $requestId,
                        'token_ttl_seconds' => $this->manageTokenTtlSeconds(),
                    ]);
                    $this->repositories->deliveries()->insertDelivery(
                        'settings_link',
                        $subscriberId,
                        null,
                        $normalizedEmail,
                        $snapshot
                    );
                    $this->repositories->subscribers()->touchSettingsTokenRequest($subscriberId);
                    $reasonCode = 'settings_link_queued';
                }
            }

            $this->repositories->auditLogs()->insertUserAction([
                'subscriber_id' => $subscriber !== null ? (int) $subscriber['subscriber_id'] : null,
                'action' => 'settings_link_requested',
                'result' => 'success',
                'request_id' => $requestId,
                'email_hash' => hash('sha256', $normalizedEmail, true),
                'metadata' => $this->metadata([
                    'reason_code' => $reasonCode,
                ]),
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        $this->settingsService->invalidateCsrf(self::CSRF_SCOPE);

        return $this->acceptedResponse();
    }

    private function acceptedResponse(): array
    {
        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'state' => 'accepted',
                'message' => self::ACCEPTED_MESSAGE,
            ],
        ];
    }

    private function mutationAvailable(): bool
    {
        return $this->featureFlags->notificationSettingsEnabled()
            && $this->featureFlags->notificationDeliveryCreateEnabled();
    }

    private function manageTokenTtlSeconds(): int
    {
        $settings = $this->config->settingsConfig();

        return (int) $settings['manage_token_ttl_seconds'];
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
