<?php
declare(strict_types=1);

use RedeAlabama\Support\ApiResponse;
use RedeAlabama\Repositories\LeadRepository;
use RedeAlabama\Services\Lead\LeadsService;
use RedeAlabama\Http\Requests\LeadRequest;
use RedeAlabama\Http\Controllers\Api\V2\LeadsController;



require_once __DIR__ . '/../../rbac.php';
require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../logger.php';

/**
 * Autoload opcional via Composer (se disponível).
 * Em ambientes onde vendor/autoload.php não existe, é simplesmente ignorado.
 */
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

// Fallback explícito para projetos sem autoloader configurado
require_once __DIR__ . '/../../app/Repositories/LeadRepository.php';
require_once __DIR__ . '/../../app/Services/Lead/LeadService.php';
require_once __DIR__ . '/../../app/Services/Lead/LeadsService.php';
require_once __DIR__ . '/../../app/Support/ApiResponse.php';
require_once __DIR__ . '/../../app/Http/Requests/LeadRequest.php';
require_once __DIR__ . '/../../app/Http/Controllers/Api/V2/LeadsController.php';

require_role(['Administrador', 'Gerente', 'Vendedor']);

$user    = current_user();
$usuario = is_array($user) ? $user : [];

// $pdo é definido em db_config.php
if (!isset($pdo)) {
    ApiResponse::jsonError('db_not_initialized', 'Conexão com banco de dados não inicializada.', 500);
    return;
}

$repository = new LeadRepository($pdo);
$service    = new LeadsService($repository);
$request    = LeadRequest::fromGlobals();

$controller = new LeadsController($service, $usuario);
$controller->handle($request);
return;

