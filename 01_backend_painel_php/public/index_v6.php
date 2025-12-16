<?php
declare(strict_types=1);

$baseDir = __DIR__ . '/../';

$autoload = $baseDir . 'vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

require_once $baseDir . 'session_bootstrap.php';
require_once $baseDir . 'app/Support/TenantResolver.php';
require_once $baseDir . 'app/Http/Router.php';
require_once $baseDir . 'app/Http/Controller.php';
require_once $baseDir . 'app/Http/Controllers/DashboardController.php';

$routesFile = $baseDir . 'routes/web_v6.php';
if (!is_file($routesFile)) {
    http_response_code(500);
    echo 'Arquivo de rotas V6 não encontrado.';
    exit;
}

/** @var \RedeAlabama\Http\Router $router */
$router = require $routesFile;
$router->dispatch();
