<?php
declare(strict_types=1);

namespace DandyNotification\Http;

use DandyNotification\Exception\ConfigException;

final class ApiRequestGuard
{
    /**
     * @param array<string, array<int, string>> $allowedKeysByAction
     */
    public function requireFormPost(array $allowedKeysByAction): string
    {
        $this->assertPostRequest();
        $this->assertFormContentType();
        $this->assertBodySize();
        $this->assertScalarPostValues();

        $action = $this->postString('action');
        if (!array_key_exists($action, $allowedKeysByAction)) {
            $this->sendBadRequest();
        }

        $this->assertAllowedPostKeys($allowedKeysByAction[$action]);

        return $action;
    }

    public function requireOriginFromBaseUrl(mixed $baseUrl): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if (!is_string($origin) || $origin === '') {
            $this->sendForbidden();
        }

        if ($origin !== $this->expectedOrigin($baseUrl)) {
            $this->sendForbidden();
        }
    }

    public function postString(string $key): string
    {
        $value = $_POST[$key] ?? null;
        if (!is_string($value)) {
            $this->sendBadRequest();
        }

        return $value;
    }

    private function assertPostRequest(): void
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

    private function assertFormContentType(): void
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

    private function assertBodySize(): void
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

    private function assertScalarPostValues(): void
    {
        foreach ($_POST as $value) {
            if (is_array($value)) {
                $this->sendBadRequest();
            }
        }
    }

    /**
     * @param array<int, string> $allowedKeys
     */
    private function assertAllowedPostKeys(array $allowedKeys): void
    {
        $allowed = array_fill_keys($allowedKeys, true);
        foreach ($_POST as $key => $_value) {
            if (!is_string($key) || !isset($allowed[$key])) {
                $this->sendBadRequest();
            }
        }

        foreach ($allowed as $key => $_unused) {
            if (!array_key_exists($key, $_POST)) {
                $this->sendBadRequest();
            }
        }
    }

    private function expectedOrigin(mixed $baseUrl): string
    {
        $parts = is_string($baseUrl) ? parse_url($baseUrl) : false;
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new ConfigException('notification_url.settings_base_url is invalid.');
        }

        $origin = strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']);
        if (isset($parts['port'])) {
            $origin .= ':' . (int) $parts['port'];
        }

        return $origin;
    }

    private function sendBadRequest(): never
    {
        JsonResponse::send([
            'ok' => false,
            'state' => 'bad_request',
            'message' => 'リクエスト形式が不正です。',
        ], 400);
        exit;
    }

    private function sendForbidden(): never
    {
        JsonResponse::send([
            'ok' => false,
            'state' => 'forbidden',
            'message' => 'リクエスト形式が不正です。',
        ], 403);
        exit;
    }
}
