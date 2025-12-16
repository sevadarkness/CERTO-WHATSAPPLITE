<?php
declare(strict_types=1);

namespace RedeAlabama\Support;

/**
 * Config central do painel.
 *
 * Prioridade:
 *   1) Variáveis de ambiente (.env / servidor)
 *   2) config.json legado
 */
final class Config
{
    private static array $cache = [];
    private static bool $loaded = false;

    private static function bootstrap(): void
    {
        if (self::$loaded) {
            return;
        }

        Env::load();

        $configPath = dirname(__DIR__, 2) . '/config.json';
        $jsonConfig = [];

        if (is_file($configPath)) {
            $raw = @file_get_contents($configPath);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $jsonConfig = $decoded;
                }
            }
        }

        self::$cache  = $jsonConfig;
        self::$loaded = true;
    }

    /**
     * Busca uma chave em notação "dot", ex.: "database.host".
     * Variáveis de ambiente (DB_HOST, APP_ENV etc.) têm prioridade.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::bootstrap();

        $envKey = strtoupper(str_replace('.', '_', $key));
        $envVal = getenv($envKey);
        if ($envVal !== false) {
            return $envVal;
        }

        $segments = explode('.', $key);
        $value    = self::$cache;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value ?? $default;
    }

    public static function env(): string
    {
        $env = getenv('APP_ENV');
        if (is_string($env) && $env !== '') {
            return $env;
        }
        return 'production';
    }

    public static function debug(): bool
    {
        $val = getenv('APP_DEBUG');
        if ($val === false) {
            return in_array(self::env(), ['local', 'development'], true);
        }

        $normalized = strtolower((string)$val);
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    public static function dbDsn(): string
    {
        $host   = getenv('DB_HOST') ?: (string) self::get('database.host', '127.0.0.1');
        $port   = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_DATABASE') ?: (string) self::get('database.dbname', 'vapealab_painel');

        return sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
    }

    public static function dbUsername(): string
    {
        $fromEnv = getenv('DB_USERNAME');
        if ($fromEnv !== false) {
            return (string)$fromEnv;
        }
        return (string) self::get('database.username', 'root');
    }

    public static function dbPassword(): string
    {
        $fromEnv = getenv('DB_PASSWORD');
        if ($fromEnv !== false) {
            return (string)$fromEnv;
        }

        $fromConfig = self::get('database.password', '');
        return is_string($fromConfig) ? $fromConfig : '';
    }
}
