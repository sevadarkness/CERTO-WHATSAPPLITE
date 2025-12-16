<?php
declare(strict_types=1);

namespace RedeAlabama\Support;

/**
 * Helpers genéricos (sanitização, datas, tipos).
 * Centraliza utilidades que estavam espalhadas em telas.
 */
final class Helpers
{
    public static function safeInt(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        return $default;
    }

    public static function safeString(mixed $value, int $maxLen = 255): string
    {
        if (!is_string($value)) {
            $value = (string)$value;
        }
        $value = trim($value);
        if ($maxLen > 0 && mb_strlen($value) > $maxLen) {
            $value = mb_substr($value, 0, $maxLen);
        }
        return $value;
    }

    public static function sanitizePhone(?string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', (string)$phone);
        return $phone ?? '';
    }

    public static function nowUtc(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
