<?php
declare(strict_types=1);

use DandyNotification\ApplicationContext;
use DandyNotification\Exception\ConfigException;
use DandyNotification\Http\JsonResponse;
use DandyNotification\Support\RequestId;

require_once __DIR__ . '/../lib/src/DandyNotification/Autoloader.php';

\DandyNotification\Autoloader::register(__DIR__ . '/../lib/src/DandyNotification');

$requestId = RequestId::generate();
$context = null;

try {
    assertPostRequest();
    assertFormContentType();
    assertBodySize();
    assertScalarPostValues();

    $action = postString('action');
    if (!in_array($action, ['preview', 'confirm'], true)) {
        sendBadRequest();
    }

    assertAllowedPostKeys($action);

    $context = require __DIR__ . '/../lib/bootstrap.php';
    if (!$context instanceof ApplicationContext) {
        throw new ConfigException('Notification bootstrap failed.');
    }

    if (confirmationFeatureOn($context)) {
        assertOrigin($context);
    }

    $service = $context->services()->registrationConfirmation();
    $result = $action === 'preview'
        ? $service->preview(postString('token'), $requestId)
        : $service->confirm(postString('csrf_token'), $requestId);

    JsonResponse::send($result['body'], (int) $result['status']);
} catch (ConfigException $exception) {
    if ($context instanceof ApplicationContext) {
        $context->logger()->error('notification confirmation config error', [
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
        $context->logger()->error('notification confirmation unexpected error', [
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

function assertPostRequest(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        JsonResponse::send([
            'ok' => false,
            'state' => 'method_not_allowed',
            'message' => 'リクエスト形式が不正です。',
        ], 405);
        exit;
    }
}

function assertFormContentType(): void
{
    $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
    $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));
    if ($mediaType !== 'application/x-www-form-urlencoded') {
        JsonResponse::send([
            'ok' => false,
            'state' => 'unsupported_media_type',
            'message' => 'リクエスト形式が不正です。',
        ], 415);
        exit;
    }
}

function assertBodySize(): void
{
    $contentLength = (string) ($_SERVER['CONTENT_LENGTH'] ?? '0');
    if (!ctype_digit($contentLength) || (int) $contentLength > 4096) {
        JsonResponse::send([
            'ok' => false,
            'state' => 'payload_too_large',
            'message' => 'リクエスト形式が不正です。',
        ], 413);
        exit;
    }
}

function assertScalarPostValues(): void
{
    foreach ($_POST as $value) {
        if (is_array($value)) {
            sendBadRequest();
        }
    }
}

function assertAllowedPostKeys(string $action): void
{
    $allowed = $action === 'preview'
        ? [
            'action' => true,
            'token' => true,
        ]
        : ['action' => true, 'csrf_token' => true];
    $required = $action === 'preview'
        ? ['action' => true, 'token' => true]
        : ['action' => true, 'csrf_token' => true];

    foreach ($_POST as $key => $_value) {
        if (!is_string($key) || !isset($allowed[$key])) {
            sendBadRequest();
        }
    }

    foreach ($required as $key => $_unused) {
        if (!array_key_exists($key, $_POST)) {
            sendBadRequest();
        }
    }
}

function postString(string $key): string
{
    $value = $_POST[$key] ?? null;
    if (!is_string($value)) {
        sendBadRequest();
    }

    return $value;
}

function confirmationFeatureOn(ApplicationContext $context): bool
{
    return $context->featureFlags()->notificationConfirmationEnabled()
        && $context->featureFlags()->notificationDeliveryCreateEnabled();
}

function assertOrigin(ApplicationContext $context): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    if (!is_string($origin) || $origin === '') {
        sendForbidden();
    }

    if ($origin !== expectedOrigin($context)) {
        sendForbidden();
    }
}

function expectedOrigin(ApplicationContext $context): string
{
    $urlConfig = $context->config()->notificationUrlConfig();
    $parts = parse_url((string) $urlConfig['confirmation_base_url']);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
        throw new ConfigException('notification_url.confirmation_base_url is invalid.');
    }

    $origin = strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']);
    if (isset($parts['port'])) {
        $origin .= ':' . (int) $parts['port'];
    }

    return $origin;
}

function sendBadRequest(): never
{
    JsonResponse::send([
        'ok' => false,
        'state' => 'bad_request',
        'message' => 'リクエスト形式が不正です。',
    ], 400);
    exit;
}

function sendForbidden(): never
{
    JsonResponse::send([
        'ok' => false,
        'state' => 'forbidden',
        'message' => 'リクエスト形式が不正です。',
    ], 403);
    exit;
}
