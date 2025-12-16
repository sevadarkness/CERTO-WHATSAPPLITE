<?php
declare(strict_types=1);

namespace RedeAlabama\Themes;

final class ThemeManager
{
    public static function currentTheme(): string
    {
        $configPath = __DIR__ . '/../../config/themes.json';
        if (!is_file($configPath)) {
            return 'default';
        }
        $raw = file_get_contents($configPath);
        if ($raw === false) {
            return 'default';
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['current'])) {
            return 'default';
        }
        return (string) $decoded['current'];
    }

    public static function cssPath(): string
    {
        $theme = self::currentTheme();
        return "/themes/{$theme}/theme.css";
    }
}
