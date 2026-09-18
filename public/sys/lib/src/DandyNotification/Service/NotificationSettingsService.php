<?php
declare(strict_types=1);

namespace DandyNotification\Service;

use DandyNotification\Exception\SecurityException;
use DandyNotification\RepositorySet;
use JsonException;
use PDO;
use Throwable;

final class NotificationSettingsService
{
    private const VALIDATION_MESSAGE = '入力内容を確認してください。';
    private const RESTRICTED_MESSAGE = '現在の状態ではこの設定を変更できません。';

    public function __construct(
        private readonly PDO $pdo,
        private readonly RepositorySet $repositories,
        private readonly SettingsAuthenticationService $authenticationService
    ) {
    }

    public function load(): array
    {
        $session = $this->authenticationService->validateSettingsSession();
        if (!$session['valid']) {
            return $session['reason'] === 'maintenance'
                ? $this->authenticationService->maintenanceResponse()
                : $this->authenticationService->authenticationRequiredResponse();
        }

        $subscriber = $session['subscriber'];

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'state' => 'ready',
                'suppressed' => (bool) $session['suppressed'],
                'quietHours' => $this->quietHoursPayload($subscriber),
                'subscriptions' => $this->subscriptionsPayload(
                    $this->repositories->subscriptions()->findVisibleBySubscriber((int) $subscriber['subscriber_id'])
                ),
                'lastUpdatedAt' => $this->nullableString($subscriber['updated_at'] ?? null),
                'settingsCsrfToken' => $this->authenticationService->settingsCsrfToken(),
            ],
        ];
    }

    public function updateQuietHours(
        string $enabledValue,
        string $startValue,
        string $endValue,
        string $csrfToken,
        string $requestId
    ): array {
        if (!$this->authenticationService->settingsMutationAvailable()) {
            return $this->authenticationService->maintenanceResponse();
        }

        $session = $this->authenticationService->validateSettingsSession();
        if (!$session['valid']) {
            return $this->authenticationService->authenticationRequiredResponse();
        }

        try {
            $this->authenticationService->requireSettingsCsrf($csrfToken);
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

        $parsed = $this->parseQuietHours($enabledValue, $startValue, $endValue);
        if (!$parsed['valid']) {
            return [
                'status' => 400,
                'body' => [
                    'ok' => false,
                    'state' => 'validation_error',
                    'message' => self::VALIDATION_MESSAGE,
                ],
            ];
        }

        if ((bool) $session['suppressed']) {
            return $this->restrictedResponse();
        }

        $subscriberId = (int) $session['subscriber_id'];
        $this->pdo->beginTransaction();
        try {
            $subscriber = $this->repositories->subscribers()->findByIdForSettingsUpdate($subscriberId);
            if ($subscriber === null) {
                $this->pdo->rollBack();
                $this->authenticationService->clearSettingsSession();
                return $this->authenticationService->authenticationRequiredResponse();
            }

            $status = (string) $subscriber['status'];
            if (!in_array($status, ['active', 'suppressed'], true)) {
                $this->pdo->rollBack();
                $this->authenticationService->clearSettingsSession();
                return $this->authenticationService->authenticationRequiredResponse();
            }

            if ($status === 'suppressed') {
                $this->pdo->rollBack();
                return $this->restrictedResponse();
            }

            if ($this->repositories->suppressions()->existsActiveByEmail((string) $subscriber['email'])) {
                $this->pdo->rollBack();
                return $this->restrictedResponse();
            }

            $changed = !$this->sameQuietHours($subscriber, $parsed);
            if ($changed) {
                $this->repositories->subscribers()->updateQuietHours(
                    $subscriberId,
                    (bool) $parsed['enabled'],
                    $parsed['start_minute'],
                    $parsed['end_minute']
                );
                $this->repositories->auditLogs()->insertUserAction([
                    'subscriber_id' => $subscriberId,
                    'action' => 'quiet_hours_updated',
                    'result' => 'success',
                    'request_id' => $requestId,
                    'email_hash' => hash('sha256', (string) $subscriber['email'], true),
                    'metadata' => $this->metadata([
                        'reason_code' => 'quiet_hours_saved',
                        'enabled' => $parsed['enabled'] ? '1' : '0',
                    ]),
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        $nextCsrfToken = $this->authenticationService->settingsCsrfToken();

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'state' => 'updated',
                'changed' => $changed,
                'quietHours' => [
                    'enabled' => (bool) $parsed['enabled'],
                    'startMinute' => $parsed['start_minute'],
                    'endMinute' => $parsed['end_minute'],
                ],
                'settingsCsrfToken' => $nextCsrfToken,
            ],
        ];
    }

    private function restrictedResponse(): array
    {
        return [
            'status' => 403,
            'body' => [
                'ok' => false,
                'state' => 'restricted',
                'message' => self::RESTRICTED_MESSAGE,
            ],
        ];
    }

    private function quietHoursPayload(array $subscriber): array
    {
        return [
            'enabled' => (int) $subscriber['quiet_hours_enabled'] === 1,
            'startMinute' => $subscriber['quiet_start_minute'] === null ? null : (int) $subscriber['quiet_start_minute'],
            'endMinute' => $subscriber['quiet_end_minute'] === null ? null : (int) $subscriber['quiet_end_minute'],
        ];
    }

    private function subscriptionsPayload(array $subscriptions): array
    {
        return array_map(function (array $subscription): array {
            $status = (string) $subscription['status'];
            $pauseReason = $this->nullableString($subscription['pause_reason'] ?? null);

            return [
                'subscriptionId' => (int) $subscription['subscription_id'],
                'shopId' => (string) $subscription['shop_id'],
                'castId' => (int) $subscription['cast_id'],
                'shopName' => $this->displayName($subscription['shop_name_snapshot'], (string) $subscription['shop_id']),
                'castName' => $this->displayName($subscription['cast_name_snapshot'], (string) $subscription['cast_id']),
                'status' => $status,
                'pauseReason' => $pauseReason,
                'statusLabel' => $this->statusLabel($status, $pauseReason),
                'updatedAt' => $this->nullableString($subscription['updated_at'] ?? null),
            ];
        }, $subscriptions);
    }

    private function statusLabel(string $status, ?string $pauseReason): string
    {
        if ($status === 'active') {
            return '通知中';
        }

        if ($status === 'paused' && $pauseReason === 'admin_paused') {
            return '管理者により通知停止中';
        }

        return '通知停止中';
    }

    private function parseQuietHours(string $enabledValue, string $startValue, string $endValue): array
    {
        if (!in_array($enabledValue, ['0', '1'], true)) {
            return ['valid' => false];
        }

        $enabled = $enabledValue === '1';
        if (!$enabled) {
            if ($startValue !== '' || $endValue !== '') {
                return ['valid' => false];
            }

            return [
                'valid' => true,
                'enabled' => false,
                'start_minute' => null,
                'end_minute' => null,
            ];
        }

        $startMinute = $this->parseMinute($startValue);
        $endMinute = $this->parseMinute($endValue);
        if ($startMinute === null || $endMinute === null || $startMinute === $endMinute) {
            return ['valid' => false];
        }

        return [
            'valid' => true,
            'enabled' => true,
            'start_minute' => $startMinute,
            'end_minute' => $endMinute,
        ];
    }

    private function parseMinute(string $value): ?int
    {
        if ($value === '' || preg_match('/^(0|[1-9]\d{0,3})$/', $value) !== 1) {
            return null;
        }

        $minute = (int) $value;

        return $minute >= 0 && $minute <= 1439 ? $minute : null;
    }

    private function sameQuietHours(array $subscriber, array $parsed): bool
    {
        return (int) $subscriber['quiet_hours_enabled'] === ($parsed['enabled'] ? 1 : 0)
            && ($subscriber['quiet_start_minute'] === null ? null : (int) $subscriber['quiet_start_minute']) === $parsed['start_minute']
            && ($subscriber['quiet_end_minute'] === null ? null : (int) $subscriber['quiet_end_minute']) === $parsed['end_minute'];
    }

    private function displayName(mixed $value, string $fallback): string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
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
