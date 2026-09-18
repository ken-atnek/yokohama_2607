<?php
declare(strict_types=1);

namespace DandyNotification\Service;

use DandyNotification\Exception\SecurityException;
use DandyNotification\RepositorySet;
use JsonException;
use PDO;
use Throwable;

final class UnsubscribeService
{
    private const UNSUBSCRIBE_CSRF_SCOPE = 'notification_unsubscribe';
    private const UNSUBSCRIBE_ALL_CSRF_SCOPE = 'notification_unsubscribe_all';
    private const NOT_FOUND_MESSAGE = '対象の登録が見つかりませんでした。';

    public function __construct(
        private readonly PDO $pdo,
        private readonly RepositorySet $repositories,
        private readonly DeliveryService $deliveryService,
        private readonly SettingsService $settingsService,
        private readonly SettingsAuthenticationService $authenticationService
    ) {
    }

    public function previewSubscription(string $subscriptionIdValue): array
    {
        $session = $this->authenticationService->validateSettingsSession();
        if (!$session['valid']) {
            return $session['reason'] === 'maintenance'
                ? $this->authenticationService->maintenanceResponse()
                : $this->authenticationService->authenticationRequiredResponse();
        }

        $subscriptionId = $this->parsePositiveInt($subscriptionIdValue);
        if ($subscriptionId === null) {
            return $this->notFoundResponse();
        }

        $subscription = $this->repositories->subscriptions()->findByIdAndSubscriber(
            $subscriptionId,
            (int) $session['subscriber_id']
        );
        if ($subscription === null || !in_array((string) $subscription['status'], ['active', 'paused'], true)) {
            return $this->notFoundResponse();
        }

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'state' => 'ready',
                'subscriptionId' => (int) $subscription['subscription_id'],
                'shopName' => $this->displayName($subscription['shop_name_snapshot'], (string) $subscription['shop_id']),
                'castName' => $this->displayName($subscription['cast_name_snapshot'], (string) $subscription['cast_id']),
                'csrfToken' => $this->settingsService->csrfToken(self::UNSUBSCRIBE_CSRF_SCOPE),
            ],
        ];
    }

    public function confirmSubscription(string $subscriptionIdValue, string $csrfToken, string $requestId): array
    {
        if (!$this->authenticationService->settingsMutationAvailable()) {
            return $this->authenticationService->maintenanceResponse();
        }

        $session = $this->authenticationService->validateSettingsSession();
        if (!$session['valid']) {
            return $this->authenticationService->authenticationRequiredResponse();
        }

        try {
            $this->settingsService->requireValidPost(self::UNSUBSCRIBE_CSRF_SCOPE, $csrfToken);
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

        $subscriptionId = $this->parsePositiveInt($subscriptionIdValue);
        if ($subscriptionId === null) {
            return $this->notFoundResponse();
        }

        $subscriberId = (int) $session['subscriber_id'];
        $this->pdo->beginTransaction();
        try {
            $subscriber = $this->repositories->subscribers()->findByIdForSettingsUpdate($subscriberId);
            if ($subscriber === null || !in_array((string) $subscriber['status'], ['active', 'suppressed'], true)) {
                $this->pdo->rollBack();
                $this->authenticationService->clearSettingsSession();
                return $this->authenticationService->authenticationRequiredResponse();
            }

            $subscription = $this->repositories->subscriptions()->findByIdAndSubscriberForUpdate(
                $subscriptionId,
                $subscriberId
            );
            if ($subscription === null) {
                $this->pdo->rollBack();
                return $this->notFoundResponse();
            }

            if ((string) $subscription['status'] === 'unsubscribed') {
                $this->pdo->commit();
                return [
                    'status' => 200,
                    'body' => [
                        'ok' => true,
                        'state' => 'already_unsubscribed',
                    ],
                ];
            }

            if (!in_array((string) $subscription['status'], ['active', 'paused'], true)) {
                $this->pdo->rollBack();
                return $this->notFoundResponse();
            }

            $this->repositories->deliveries()->cancelQueuedDeferredBySubscription($subscriberId, $subscriptionId);
            $this->repositories->subscriptions()->markUnsubscribed($subscriptionId);
            $snapshot = $this->deliveryService->buildTemplateDataSnapshot('subscription_removed', [
                'request_id' => $requestId,
                'shop_id' => (string) $subscription['shop_id'],
                'cast_id' => (int) $subscription['cast_id'],
                'shop_name' => $this->displayName($subscription['shop_name_snapshot'], (string) $subscription['shop_id']),
                'cast_name' => $this->displayName($subscription['cast_name_snapshot'], (string) $subscription['cast_id']),
            ]);
            $this->repositories->deliveries()->insertDelivery(
                'subscription_removed',
                $subscriberId,
                $subscriptionId,
                (string) $subscriber['email'],
                $snapshot
            );
            $this->repositories->auditLogs()->insertUserAction([
                'subscriber_id' => $subscriberId,
                'subscription_id' => $subscriptionId,
                'action' => 'subscription_unsubscribed',
                'result' => 'success',
                'request_id' => $requestId,
                'email_hash' => hash('sha256', (string) $subscriber['email'], true),
                'shop_id' => (string) $subscription['shop_id'],
                'cast_id' => (int) $subscription['cast_id'],
                'metadata' => $this->metadata([
                    'reason_code' => 'subscription_removed',
                    'delivery_mail_type' => 'subscription_removed',
                ]),
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'state' => 'completed',
                'redirectUrl' => '/wgmailsys/settings/',
            ],
        ];
    }

    public function previewAll(): array
    {
        $session = $this->authenticationService->validateSettingsSession();
        if (!$session['valid']) {
            return $session['reason'] === 'maintenance'
                ? $this->authenticationService->maintenanceResponse()
                : $this->authenticationService->authenticationRequiredResponse();
        }

        $subscriptions = $this->repositories->subscriptions()->findVisibleBySubscriber((int) $session['subscriber_id']);

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'state' => 'ready',
                'subscriptionCount' => count($subscriptions),
                'csrfToken' => $this->settingsService->csrfToken(self::UNSUBSCRIBE_ALL_CSRF_SCOPE),
            ],
        ];
    }

    public function confirmAll(string $csrfToken, string $requestId): array
    {
        if (!$this->authenticationService->settingsMutationAvailable()) {
            return $this->authenticationService->maintenanceResponse();
        }

        $session = $this->authenticationService->validateSettingsSession();
        if (!$session['valid']) {
            return $this->authenticationService->authenticationRequiredResponse();
        }

        try {
            $this->settingsService->requireValidPost(self::UNSUBSCRIBE_ALL_CSRF_SCOPE, $csrfToken);
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

        $subscriberId = (int) $session['subscriber_id'];
        $this->pdo->beginTransaction();
        try {
            $subscriber = $this->repositories->subscribers()->findByIdForSettingsUpdate($subscriberId);
            if ($subscriber === null) {
                $this->pdo->rollBack();
                $this->clearAfterUnsubscribeAll();
                return $this->authenticationService->authenticationRequiredResponse();
            }

            if ((string) $subscriber['status'] === 'unsubscribed') {
                $this->pdo->commit();
                $this->clearAfterUnsubscribeAll();
                return [
                    'status' => 200,
                    'body' => [
                        'ok' => true,
                        'state' => 'already_unsubscribed',
                    ],
                ];
            }

            if (!in_array((string) $subscriber['status'], ['active', 'suppressed'], true)) {
                $this->pdo->rollBack();
                $this->clearAfterUnsubscribeAll();
                return $this->authenticationService->authenticationRequiredResponse();
            }

            $this->repositories->subscriptions()->findAllBySubscriberForUpdate($subscriberId);
            $cancelledCount = $this->repositories->deliveries()->cancelQueuedDeferredBySubscriber($subscriberId);
            $revokedTokenCount = $this->repositories->tokens()->revokeUnusedBySubscriber($subscriberId);
            $unsubscribedSubscriptionCount = $this->repositories->subscriptions()->markAllUnsubscribedBySubscriber($subscriberId);
            $this->repositories->subscribers()->markUnsubscribedAll($subscriberId);

            $snapshot = $this->deliveryService->buildTemplateDataSnapshot('unsubscribe_all', [
                'request_id' => $requestId,
            ]);
            $this->repositories->deliveries()->insertDelivery(
                'unsubscribe_all',
                $subscriberId,
                null,
                (string) $subscriber['email'],
                $snapshot
            );
            $this->repositories->auditLogs()->insertUserAction([
                'subscriber_id' => $subscriberId,
                'action' => 'subscriber_unsubscribed_all',
                'result' => 'success',
                'request_id' => $requestId,
                'email_hash' => hash('sha256', (string) $subscriber['email'], true),
                'metadata' => $this->metadata([
                    'reason_code' => 'subscriber_unsubscribed_all',
                    'cancelled_delivery_count' => (string) $cancelledCount,
                    'revoked_token_count' => (string) $revokedTokenCount,
                    'unsubscribed_subscription_count' => (string) $unsubscribedSubscriptionCount,
                ]),
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        $this->clearAfterUnsubscribeAll();

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'state' => 'completed',
            ],
        ];
    }

    private function clearAfterUnsubscribeAll(): void
    {
        $this->authenticationService->clearAllAuthenticationState();
        $this->settingsService->invalidateCsrf(self::UNSUBSCRIBE_CSRF_SCOPE);
        $this->settingsService->invalidateCsrf(self::UNSUBSCRIBE_ALL_CSRF_SCOPE);
        $this->settingsService->session()->regenerateId();
    }

    private function notFoundResponse(): array
    {
        return [
            'status' => 404,
            'body' => [
                'ok' => false,
                'state' => 'not_found',
                'message' => self::NOT_FOUND_MESSAGE,
            ],
        ];
    }

    private function parsePositiveInt(string $value): ?int
    {
        if (preg_match('/^[1-9]\d{0,18}$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    private function displayName(mixed $value, string $fallback): string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return $fallback;
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
