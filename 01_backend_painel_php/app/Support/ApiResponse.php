<?php
declare(strict_types=1);

namespace RedeAlabama\Support;

/**
 * Helper para padronizar respostas JSON da API.
 */
final class ApiResponse
{
    public static function jsonSuccess($data = null, array $meta = [], int $httpCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($httpCode);
        }
        self::send([
            'ok'    => true,
            'data'  => $data,
            'error' => null,
            'meta'  => $meta,
        ]);
    }

    public static function jsonError(string $error, string $message = '', int $httpCode = 400, array $meta = []): void
    {
        if (!headers_sent()) {
            http_response_code($httpCode);
        }
        self::send([
            'ok'    => false,
            'data'  => null,
            'error' => [
                'code'    => $error,
                'message' => $message,
            ],
            'meta'  => $meta,
        ]);
    }

    private static function send(array $payload): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

