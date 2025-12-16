<?php
declare(strict_types=1);

namespace RedeAlabama\Http;

use RedeAlabama\Support\TenantResolver;

final class Router
{
    /** @var array<string, callable> */
    private array $getRoutes = [];

    /** @var array<string, callable> */
    private array $postRoutes = [];

    public function get(string $path, callable $handler): void
    {
        $this->getRoutes[$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->postRoutes[$path] = $handler;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = $_SERVER['REQUEST_URI']    ?? '/';

        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $routes = $method === 'POST' ? $this->postRoutes : $this->getRoutes;

        if (!isset($routes[$path])) {
            http_response_code(404);
            echo '404 - Rota não encontrada (V6 Router).';
            return;
        }

        $tenantId = TenantResolver::currentTenantId();

        $handler = $routes[$path];
        $handler($tenantId, $this);
    }
}
