<?php
declare(strict_types=1);

namespace RedeAlabama\Plugins\ExampleHelloPlugin;

use RedeAlabama\Http\Router;

final class Plugin
{
    public function boot(array $context = []): void
    {
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/v6/plugin/example-hello', function (int $tenantId) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'        => true,
                'plugin'    => 'ExampleHelloPlugin',
                'tenant_id' => $tenantId,
                'message'   => 'Hello from plugin!',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        });
    }

    public function registerMenu(array &$menu): void
    {
        $menu[] = [
            'label' => 'Plugin Hello',
            'url'   => '/v6/plugin/example-hello',
        ];
    }
}
