<?php
declare(strict_types=1);

namespace RedeAlabama\Support;

final class TenantResolver
{
    /** @var int|null */
    private static ?int $forcedTenantId = null;

    public static function forceTenantId(?int $tenantId): void
    {
        self::$forcedTenantId = $tenantId;
    }

    public static function currentTenantId(): int
    {
        if (self::$forcedTenantId !== null) {
            return self::$forcedTenantId;
        }

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['tenant_id'])) {
            $id = (int) $_SESSION['tenant_id'];
            if ($id > 0) {
                return $id;
            }
        }

        $byHost = self::resolveFromHost();
        if ($byHost !== null) {
            return $byHost;
        }

        return 1;
    }

    private static function resolveFromHost(): ?int
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return null;
        }

        try {
            $map = self::loadTenantMap();
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($map) || empty($map['hosts']) || !is_array($map['hosts'])) {
            return null;
        }

        foreach ($map['hosts'] as $pattern => $tenantId) {
            if (!is_string($pattern)) {
                continue;
            }
            if (!is_int($tenantId) && !ctype_digit((string) $tenantId)) {
                continue;
            }
            if (strcasecmp($host, $pattern) === 0) {
                return (int) $tenantId;
            }
        }

        return null;
    }

    private static function loadTenantMap(): array
    {
        $baseDir = dirname(__DIR__, 2);
        $path    = $baseDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'tenants.json';

        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
