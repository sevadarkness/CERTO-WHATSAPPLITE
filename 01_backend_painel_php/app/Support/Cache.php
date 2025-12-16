<?php
declare(strict_types=1);

namespace RedeAlabama\Support;

/**
 * Cache simples com suporte a Redis + disco.
 *
 * Uso básico:
 *   $value = Cache::remember('chave', 60, function () {
 *       // calcula valor...
 *   });
 */
final class Cache
{
    public static function remember(string $key, int $ttlSeconds, callable $resolver)
    {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $resolver();
        self::set($key, $value, $ttlSeconds);

        return $value;
    }

    public static function get(string $key)
    {
        if (self::redisEnabled()) {
            return self::getRedis($key);
        }
        return self::getFile($key);
    }

    public static function set(string $key, $value, int $ttlSeconds): void
    {
        if (self::redisEnabled()) {
            self::setRedis($key, $value, $ttlSeconds);
            return;
        }
        self::setFile($key, $value, $ttlSeconds);
    }

    public static function delete(string $key): void
    {
        if (self::redisEnabled()) {
            self::deleteRedis($key);
            return;
        }
        self::deleteFile($key);
    }

    private static function redisEnabled(): bool
    {
        if (!class_exists('Redis')) {
            return false;
        }
        $host = getenv('ALABAMA_REDIS_HOST') ?: getenv('REDIS_HOST') ?: '';
        return $host !== '';
    }

    private static function redis(): \Redis
    {
        $redis = new \Redis();
        $host  = getenv('ALABAMA_REDIS_HOST') ?: getenv('REDIS_HOST') ?: 'redis';
        $port  = (int) (getenv('ALABAMA_REDIS_PORT') ?: 6379);
        $redis->connect($host, $port, 0.1);
        return $redis;
    }

    private static function getRedis(string $key)
    {
        try {
            $redis = self::redis();
            $raw   = $redis->get('cache:' . $key);
            if ($raw === false || $raw === null) {
                return null;
            }
            return json_decode($raw, true);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function setRedis(string $key, $value, int $ttlSeconds): void
    {
        try {
            $redis = self::redis();
            $redis->setex('cache:' . $key, $ttlSeconds, json_encode($value));
        } catch (\Throwable $e) {
            // silencioso
        }
    }

    private static function deleteRedis(string $key): void
    {
        try {
            $redis = self::redis();
            $redis->del('cache:' . $key);
        } catch (\Throwable $e) {
            // silencioso
        }
    }

    private static function fileDir(): string
    {
        if (defined('ALABAMA_LOG_DIR')) {
            return ALABAMA_LOG_DIR . '/cache';
        }
        return sys_get_temp_dir() . '/alabama_cache';
    }

    private static function getFile(string $key)
    {
        $dir = self::fileDir();
        $file = $dir . '/' . md5($key) . '.json';

        if (!is_file($file)) {
            return null;
        }
        $json = @file_get_contents($file);
        if (!is_string($json) || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }
        if (isset($decoded['expires_at']) && $decoded['expires_at'] < time()) {
            @unlink($file);
            return null;
        }
        return $decoded['value'] ?? null;
    }

    private static function setFile(string $key, $value, int $ttlSeconds): void
    {
        $dir = self::fileDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $payload = [
            'value'      => $value,
            'expires_at' => time() + $ttlSeconds,
        ];
        $file = $dir . '/' . md5($key) . '.json';
        @file_put_contents($file, json_encode($payload));
    }

    private static function deleteFile(string $key): void
    {
        $dir = self::fileDir();
        $file = $dir . '/' . md5($key) . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

