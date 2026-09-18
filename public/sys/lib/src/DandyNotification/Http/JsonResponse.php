<?php
declare(strict_types=1);

namespace DandyNotification\Http;

final class JsonResponse
{
    public static function success(array $data = [], int $status = 200, ?string $requestId = null): void
    {
        self::send([
            'ok' => true,
            'data' => $data,
            'request_id' => $requestId,
        ], $status);
    }

    public static function failure(
        string $code,
        string $message,
        int $status = 400,
        ?string $requestId = null
    ): void {
        self::send([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'request_id' => $requestId,
        ], $status);
    }

    public static function send(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            http_response_code(500);
            echo '{"ok":false,"error":{"code":"json_encode_failed","message":"Internal server error."}}';
            return;
        }

        echo $json;
    }
}
