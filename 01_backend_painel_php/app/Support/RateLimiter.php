<?php
declare(strict_types=1);

namespace RedeAlabama\Support;

/**
 * RateLimiter híbrido (Redis + disco).
 *
 * - Usa Redis quando disponível (classe Redis + ALABAMA_REDIS_HOST/REDIS_HOST)
 * - Faz fallback automático para storage em arquivos em disco
 */
final class RateLimiter
{
    public static function hit(string $bucket, int $maxAttempts, int $decaySeconds): bool
    {
        if (self::redisEnabled()) {
            return self::hitRedis($bucket, $maxAttempts, $decaySeconds);
        }

        return self::hitFile($bucket, $maxAttempts, $decaySeconds);
    }

    private static function hitFile(string $bucket, int $maxAttempts, int $decaySeconds): bool
    {
        $now   = time();
        $store = self::storageDir();

        if (!is_dir($store)) {
            @mkdir($store, 0775, true);
        }

        $file = $store . '/' . preg_replace('/[^a-zA-Z0-9_\-:]/', '_', $bucket) . '.json';

        $data = [
            'count'    => 0,
            'reset_at' => $now + $decaySeconds,
        ];

        if (is_file($file)) {
            $json = @file_get_contents($file);
            if (is_string($json) && $json !== '') {
                $decoded = json_decode($json, true);
                if (is_array($decoded) && isset($decoded['count'], $decoded['reset_at'])) {
                    $data = $decoded;
                }
            }
        }

        if ($data['reset_at'] <= $now) {
            $data['count']    = 0;
            $data['reset_at'] = $now + $decaySeconds;
        }

        $data['count']++;

        @file_put_contents($file, json_encode($data));

        return $data['count'] <= $maxAttempts;
    }

    private static function storageDir(): string
    {
        if (defined('ALABAMA_LOG_DIR')) {
            return ALABAMA_LOG_DIR . '/ratelimit';
        }
        return sys_get_temp_dir() . '/alabama_ratelimit';
    }

    private static function redisEnabled(): bool
    {
        if (!class_exists('Redis')) {
            return false;
        }
        $host = getenv('ALABAMA_REDIS_HOST') ?: getenv('REDIS_HOST') ?: '';
        if ($host === '') {
            return false;
        }
        return true;
    }

    private static function hitRedis(string $bucket, int $maxAttempts, int $decaySeconds): bool
    {
        try {
            $redis = new \Redis();
            $host  = getenv('ALABAMA_REDIS_HOST') ?: getenv('REDIS_HOST') ?: 'redis';
            $port  = (int) (getenv('ALABAMA_REDIS_PORT') ?: 6379);
            $redis->connect($host, $port, 0.1);

            $key   = 'rl:' . preg_replace('/[^a-zA-Z0-9_\-:]/', '_', $bucket);
            $count = (int) $redis->incr($key);
            if ($count === 1) {
                $redis->expire($key, $decaySeconds);
            }

            return $count <= $maxAttempts;
        } catch (\Throwable $e) {
            // Fallback silencioso para disco se Redis falhar (evita recursão infinita)
            return self::hitFile($bucket, $maxAttempts, $decaySeconds);
        }
    }
}
