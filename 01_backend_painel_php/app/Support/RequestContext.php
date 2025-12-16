<?php
declare(strict_types=1);

namespace RedeAlabama\Support;

/**
 * Contexto leve de requisição.
 * - Gera um request_id único por requisição web
 * - Mede duração aproximada
 */
final class RequestContext
{
    private static ?string $requestId = null;
    private static float $startedAt = 0.0;

    public static function init(): void
    {
        if (self::$requestId !== null) {
            return;
        }

        self::$startedAt = microtime(true);
        try {
            self::$requestId = bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            self::$requestId = bin2hex((string)mt_rand());
        }

        if (!defined('ALABAMA_REQUEST_ID')) {
            define('ALABAMA_REQUEST_ID', self::$requestId);
        }
    }

    public static function id(): ?string
    {
        if (self::$requestId === null) {
            self::init();
        }
        return self::$requestId;
    }

    public static function durationMs(): int
    {
        if (self::$startedAt <= 0) {
            return 0;
        }
        return (int) ((microtime(true) - self::$startedAt) * 1000);
    }
}
