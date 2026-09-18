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
        'prepare' => ['action'],
        'request' => ['action', 'email', 'csrf_token'],
    ]);

    $context = require __DIR__ . '/../lib/bootstrap.php';
    if (!$context instanceof ApplicationContext) {
        throw new ConfigException('Notification bootstrap failed.');
    }

    if ($context->featureFlags()->notificationSettingsEnabled()) {
        $guard->requireOriginFromBaseUrl($context->config()->notificationUrlConfig()['settings_base_url']);
    }

    $service = $context->services()->settingsLinkRequests();
    $result = $action === 'prepare'
        ? $service->prepare()
        : $service->request(
            $guard->postString('email'),
            $guard->postString('csrf_token'),
            $requestId
        );

    JsonResponse::send($result['body'], (int) $result['status']);
} catch (ConfigException $exception) {
    if ($context instanceof ApplicationContext) {
        $context->logger()->error('notification settings link request config error', [
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
        $context->logger()->error('notification settings link request unexpected error', [
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
