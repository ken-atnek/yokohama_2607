<?php
declare(strict_types=1);

use DandyNotification\ApplicationContext;
use DandyNotification\Exception\ConfigException;
use DandyNotification\Http\ApiRequestGuard;
use DandyNotification\Http\JsonResponse;
use DandyNotification\Support\RequestId;

require_once __DIR__ . '/../lib/src/DandyNotification/Autoloader.php';

\DandyNotification\Autoloader::register(__DIR__ . '/../lib/src/DandyNotification');

$requestId = RequestId::generate();
$context = null;

try {
    $guard = new ApiRequestGuard();
    $action = $guard->requireFormPost([
        'auth_preview' => ['action', 'token'],
        'authenticate' => ['action', 'csrf_token'],
        'load' => ['action'],
        'update_quiet_hours' => ['action', 'csrf_token', 'quiet_hours_enabled', 'quiet_start_minute', 'quiet_end_minute'],
    ]);

    $context = require __DIR__ . '/../lib/bootstrap.php';
    if (!$context instanceof ApplicationContext) {
        throw new ConfigException('Notification bootstrap failed.');
    }

    if ($context->featureFlags()->notificationSettingsEnabled()) {
        $guard->requireOriginFromBaseUrl($context->config()->notificationUrlConfig()['settings_base_url']);
    }

    $result = match ($action) {
        'auth_preview' => $context->services()->settingsAuthentication()->preview(
            $guard->postString('token'),
            $requestId
        ),
        'authenticate' => $context->services()->settingsAuthentication()->authenticate(
            $guard->postString('csrf_token'),
            $requestId
        ),
        'load' => $context->services()->notificationSettings()->load(),
        'update_quiet_hours' => $context->services()->notificationSettings()->updateQuietHours(
            $guard->postString('quiet_hours_enabled'),
            $guard->postString('quiet_start_minute'),
            $guard->postString('quiet_end_minute'),
            $guard->postString('csrf_token'),
            $requestId
        ),
    };

    JsonResponse::send($result['body'], (int) $result['status']);
} catch (ConfigException $exception) {
    if ($context instanceof ApplicationContext) {
        $context->logger()->error('notification settings config error', [
            'request_id' => $requestId,
            'exception' => $exception,
        ]);
    }

    JsonResponse::send([
        'ok' => false,
        'state' => 'maintenance',
        'message' => '現在この機能はご利用いただけません。時間をおいて再度お試しください。',
    ], 503);
} catch (Throwable $exception) {
    if ($context instanceof ApplicationContext) {
        $context->logger()->error('notification settings unexpected error', [
            'request_id' => $requestId,
            'exception' => $exception,
        ]);
    }

    JsonResponse::send([
        'ok' => false,
        'state' => 'temporary_error',
        'message' => '一時的に処理できませんでした。時間をおいて再度お試しください。',
    ], 500);
}
