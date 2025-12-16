<?php
declare(strict_types=1);

use RedeAlabama\Http\Router;
use RedeAlabama\Http\Controllers\DashboardController;



$router = new Router();

$router->get('/v6/dashboard', function (int $tenantId) {
    $controller = new DashboardController();
    $controller->index($tenantId);
});

return $router;
