<?php
declare(strict_types=1);

namespace RedeAlabama\Api;

use RedeAlabama\Support\ApiResponse;
use RedeAlabama\Support\PrometheusMetrics;
use RedeAlabama\Http\Requests\MatchingRequest;
use RedeAlabama\Http\Requests\EntregadoresRequest;
use RedeAlabama\Http\Requests\RemarketingDisparosRequest;
use RedeAlabama\Http\Requests\RemarketingSegmentosRequest;
use RedeAlabama\Http\Requests\RemarketingLogsRequest;
use RedeAlabama\Http\Controllers\Api\V2\MatchingController;
use RedeAlabama\Http\Controllers\Api\V2\EntregadoresController;
use RedeAlabama\Http\Controllers\Api\V2\RemarketingStatsController;
use RedeAlabama\Http\Controllers\Api\V2\RemarketingDisparosController;
use RedeAlabama\Http\Controllers\Api\V2\RemarketingSegmentosController;
use RedeAlabama\Http\Controllers\Api\V2\RemarketingLogsController;
use RedeAlabama\Services\Matching\MatchingService;
use RedeAlabama\Services\Entregadores\EntregadoresService;
use RedeAlabama\Services\Remarketing\RemarketingStatsService;
use RedeAlabama\Services\Remarketing\RemarketingDisparosService;
use RedeAlabama\Services\Remarketing\RemarketingSegmentosService;
use RedeAlabama\Services\Remarketing\RemarketingLogsService;

/**
 * Router REST centralizado para a API v2.
 *
 * Lê o arquivo de rotas (routes/api_v2.php) e resolve qual Controller
 * deve ser invocado com base em método + URI.
 */
final class Router
{
    public static function dispatch(?string $pathOverride = null, ?string $methodOverride = null): void
    {
        // Garante autoload PSR-4 mesmo quando /vendor não está presente.
        // Sem isso, referências a classes (ex.: PrometheusMetrics) podem causar fatal error
        // antes do bootstrap (db_config.php) ser carregado.
        require_once __DIR__ . '/../bootstrap_autoload.php';

        // Carrega .env cedo (melhor esforço), útil para métricas/flags.
        try {
            if (class_exists(\RedeAlabama\Support\Env::class)) {
                \RedeAlabama\Support\Env::load();
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $method = $methodOverride ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $method = strtoupper($method);

        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = $pathOverride ?? parse_url($requestUri, PHP_URL_PATH);

        $metricsRegistry = PrometheusMetrics::instance();
        $requestTimer = $metricsRegistry->startTimer('http_request_duration_seconds', [
            'route'  => $path,
            'method' => $method,
        ]);

        // Bootstrap básico da aplicação
        require_once __DIR__ . '/../rbac.php';
        require_once __DIR__ . '/../db_config.php';
        require_once __DIR__ . '/../logger.php';

        // Autoload opcional
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }

        // Fallback manual para classes usadas aqui
        require_once __DIR__ . '/../app/Support/ApiResponse.php';
        require_once __DIR__ . '/../app/Http/Requests/MatchingRequest.php';
        require_once __DIR__ . '/../app/Http/Requests/EntregadoresRequest.php';
        require_once __DIR__ . '/../app/Http/Requests/RemarketingDisparosRequest.php';
        require_once __DIR__ . '/../app/Http/Requests/RemarketingSegmentosRequest.php';
        require_once __DIR__ . '/../app/Http/Requests/RemarketingLogsRequest.php';
        require_once __DIR__ . '/../app/Http/Controllers/Api/V2/MatchingController.php';
        require_once __DIR__ . '/../app/Http/Controllers/Api/V2/EntregadoresController.php';
        require_once __DIR__ . '/../app/Http/Controllers/Api/V2/RemarketingStatsController.php';
        require_once __DIR__ . '/../app/Http/Controllers/Api/V2/RemarketingDisparosController.php';
        require_once __DIR__ . '/../app/Http/Controllers/Api/V2/RemarketingSegmentosController.php';
        require_once __DIR__ . '/../app/Http/Controllers/Api/V2/RemarketingLogsController.php';
        require_once __DIR__ . '/../app/Services/Matching/MatchingService.php';
        require_once __DIR__ . '/../app/Services/Entregadores/EntregadoresService.php';
        require_once __DIR__ . '/../app/Services/Remarketing/RemarketingStatsService.php';
        require_once __DIR__ . '/../app/Services/Remarketing/RemarketingDisparosService.php';
        require_once __DIR__ . '/../app/Services/Remarketing/RemarketingSegmentosService.php';
        require_once __DIR__ . '/../app/Services/Remarketing/RemarketingLogsService.php';

        if (!isset($pdo)) {
            ApiResponse::jsonError('db_not_initialized', 'Conexão com banco de dados não inicializada.', 500);
            return;
        }

        $routes = require __DIR__ . '/../routes/api_v2.php';

        $user    = function_exists('current_user') ? current_user() : null;
        $usuario = is_array($user) ? $user : [];

        // Matching
        if ($path === '/api/v2/matching' && $method === 'POST') {
            require_role(['Administrador', 'Gerente']);

            $service    = new MatchingService($pdo);
            $request    = MatchingRequest::fromGlobals();
            $controller = new MatchingController($service, $usuario);
            $controller->handle($request);
            
            // Métrica Prometheus: requisição atendida com sucesso
            $metricsRegistry->incCounter('http_requests_total', [
                'route'  => $path,
                'method' => $method,
                'status' => 200,
            ]);
            $metricsRegistry->endTimer($requestTimer);
return;
        }

        // Entregadores
        if ($path === '/api/v2/entregadores' && $method === 'GET') {
            require_role(['Administrador', 'Gerente']);

            $service    = new EntregadoresService($pdo);
            $request    = EntregadoresRequest::fromGlobals();
            $controller = new EntregadoresController($service, $usuario);
            $controller->handle($request);
            
            // Métrica Prometheus: requisição atendida com sucesso
            $metricsRegistry->incCounter('http_requests_total', [
                'route'  => $path,
                'method' => $method,
                'status' => 200,
            ]);
            $metricsRegistry->endTimer($requestTimer);
return;
        }

        // Remarketing stats
        if ($path === '/api/v2/remarketing/stats' && $method === 'GET') {
            require_role(['Administrador', 'Gerente']);

            $service    = new RemarketingStatsService($pdo);
            $controller = new RemarketingStatsController($service, $usuario);
            $controller->handle();
            
            // Métrica Prometheus: requisição atendida com sucesso
            $metricsRegistry->incCounter('http_requests_total', [
                'route'  => $path,
                'method' => $method,
                'status' => 200,
            ]);
            $metricsRegistry->endTimer($requestTimer);
return;
        }

        // Remarketing disparos
        if ($path === '/api/v2/remarketing/disparos' && $method === 'POST') {
            require_role(['Administrador', 'Gerente']);

            $service    = new RemarketingDisparosService();
            $request    = RemarketingDisparosRequest::fromGlobals();
            $controller = new RemarketingDisparosController($service, $usuario);
            $controller->handle($request);
            
            // Métrica Prometheus: requisição atendida com sucesso
            $metricsRegistry->incCounter('http_requests_total', [
                'route'  => $path,
                'method' => $method,
                'status' => 200,
            ]);
            $metricsRegistry->endTimer($requestTimer);
return;
        }

        // Remarketing segmentos
        if ($path === '/api/v2/remarketing/segmentos' && in_array($method, ['GET','POST'], true)) {
            require_role(['Administrador', 'Gerente']);

            $service    = new RemarketingSegmentosService($pdo);
            $request    = RemarketingSegmentosRequest::fromGlobals();
            $controller = new RemarketingSegmentosController($service, $usuario);
            $controller->handle($request);
            
            // Métrica Prometheus: requisição atendida com sucesso
            $metricsRegistry->incCounter('http_requests_total', [
                'route'  => $path,
                'method' => $method,
                'status' => 200,
            ]);
            $metricsRegistry->endTimer($requestTimer);
return;
        }

        // Remarketing logs
        if ($path === '/api/v2/remarketing/logs' && $method === 'GET') {
            require_role(['Administrador', 'Gerente']);

            $service    = new RemarketingLogsService();
            $request    = RemarketingLogsRequest::fromGlobals();
            $controller = new RemarketingLogsController($service, $usuario);
            $controller->handle($request);
            
            // Métrica Prometheus: requisição atendida com sucesso
            $metricsRegistry->incCounter('http_requests_total', [
                'route'  => $path,
                'method' => $method,
                'status' => 200,
            ]);
            $metricsRegistry->endTimer($requestTimer);
return;
        }


        // Métrica Prometheus: requisição sem rota correspondente
        $metricsRegistry->incCounter('http_requests_total', [
            'route'  => $path,
            'method' => $method,
            'status' => 404,
        ]);
        $metricsRegistry->endTimer($requestTimer);

        ApiResponse::jsonError('route_not_found', 'Rota não encontrada na API v2.', 404, [
            'method' => $method,
            'path'   => $path,
        ]);
    }
}

