<?php
declare(strict_types=1);

namespace RedeAlabama\Routes;

use RedeAlabama\Http\Controllers\Api\V2\LeadsController;
use RedeAlabama\Http\Controllers\Api\V2\MatchingController;
use RedeAlabama\Http\Controllers\Api\V2\EntregadoresController;
use RedeAlabama\Http\Controllers\Api\V2\RemarketingStatsController;
use RedeAlabama\Http\Controllers\Api\V2\RemarketingDisparosController;
use RedeAlabama\Http\Controllers\Api\V2\RemarketingSegmentosController;
use RedeAlabama\Http\Controllers\Api\V2\RemarketingLogsController;

/**
 * Mapa de rotas da API v2.
 */
return [
    'leads.store' => [
        'method'      => 'POST',
        'uri'         => '/api/v2/leads',
        'controller'  => LeadsController::class,
        'description' => 'Criação de leads via API v2',
    ],
    'leads.show' => [
        'method'      => 'GET',
        'uri'         => '/api/v2/leads',
        'controller'  => LeadsController::class,
        'description' => 'Consulta de lead por telefone ou ID',
    ],
    'matching.store' => [
        'method'      => 'POST',
        'uri'         => '/api/v2/matching',
        'controller'  => MatchingController::class,
        'description' => 'Registro de matching (cliente x vendedor)',
    ],
    'entregadores.index' => [
        'method'      => 'GET',
        'uri'         => '/api/v2/entregadores',
        'controller'  => EntregadoresController::class,
        'description' => 'Listagem de entregadores',
    ],
    'remarketing.stats' => [
        'method'      => 'GET',
        'uri'         => '/api/v2/remarketing/stats',
        'controller'  => RemarketingStatsController::class,
        'description' => 'Estatísticas de remarketing (clientes 7/30 dias)',
    ],
    'remarketing.disparos' => [
        'method'      => 'POST',
        'uri'         => '/api/v2/remarketing/disparos',
        'controller'  => RemarketingDisparosController::class,
        'description' => 'Disparos de campanhas de remarketing',
    ],
    'remarketing.segmentos' => [
        'method'      => 'POST',
        'uri'         => '/api/v2/remarketing/segmentos',
        'controller'  => RemarketingSegmentosController::class,
        'description' => 'Geração de segmentos de clientes para remarketing',
    ],
    'remarketing.logs' => [
        'method'      => 'GET',
        'uri'         => '/api/v2/remarketing/logs',
        'controller'  => RemarketingLogsController::class,
        'description' => 'Consulta de logs de campanhas de remarketing',
    ],
];

