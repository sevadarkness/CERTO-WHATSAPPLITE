<?php
declare(strict_types=1);

namespace RedeAlabama\Plugins;

final class PluginManager
{
    /** @var array<string, object> */
    private array $plugins = [];

    public function loadFromConfig(string $configPath): void
    {
        if (!is_file($configPath)) {
            return;
        }

        $raw = file_get_contents($configPath);
        if ($raw === false) {
            return;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['plugins']) || !is_array($decoded['plugins'])) {
            return;
        }

        foreach ($decoded['plugins'] as $pluginName => $pluginConfig) {
            if (!isset($pluginConfig['path'])) {
                continue;
            }
            $path = $pluginConfig['path'];
            $file = rtrim($path, '/\\') . '/Plugin.php';
            if (!is_file($file)) {
                continue;
            }

            require_once $file;

            $class = $pluginConfig['class'] ?? null;
            if (!is_string($class) || $class === '') {
                continue;
            }
            if (!class_exists($class)) {
                continue;
            }

            $this->plugins[$pluginName] = new $class();
        }
    }

    public function bootAll(array $context = []): void
    {
        foreach ($this->plugins as $plugin) {
            if (method_exists($plugin, 'boot')) {
                $plugin->boot($context);
            }
        }
    }

    public function registerRoutesAll($router): void
    {
        foreach ($this->plugins as $plugin) {
            if (method_exists($plugin, 'registerRoutes')) {
                $plugin->registerRoutes($router);
            }
        }
    }

    public function registerMenuAll(array &$menu): void
    {
        foreach ($this->plugins as $plugin) {
            if (method_exists($plugin, 'registerMenu')) {
                $plugin->registerMenu($menu);
            }
        }
    }
}
