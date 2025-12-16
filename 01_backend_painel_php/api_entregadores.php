<?php
/**
 * api_entregadores.php (legacy)
 *
 * Este arquivo existe para manter compatibilidade com a ponte (bridge) usada
 * por EntregadoresService (API v2). No histórico do projeto, essa "API" era
 * implementada via script que lia superglobais e imprimia JSON.
 *
 * Contrato esperado (mínimo):
 *   { "success": true, "entregadores": [...] }
 */

declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json; charset=utf-8');

// Filtros opcionais (não obrigatórios no contrato legacy)
$produto = isset($_GET['produto']) ? trim((string)$_GET['produto']) : null;
$quantidade = isset($_GET['quantidade']) ? (int)$_GET['quantidade'] : null;

// A base atual do projeto não possui, de forma garantida, uma tabela de "entregadores".
// Para evitar endpoint quebrado, retornamos uma lista padrão estável.
// Se futuramente for criada uma tabela, basta substituir esta lista por uma consulta.
$default = [
    [
        'id' => 1,
        'name' => 'João Silva - Zona Sul',
        'phone' => '(11) 98888-0001',
        'lat' => -23.6501,
        'lng' => -46.6345,
        'status' => 'available',
        'zone' => 'Zona Sul',
        'rating' => 4.8,
        'active_orders' => 2,
        'capacity' => 8,
        'sla_minutes' => 35,
        'tags' => ['default'],
    ],
    [
        'id' => 2,
        'name' => 'Maria Santos - Centro',
        'phone' => '(11) 97777-0002',
        'lat' => -23.5505,
        'lng' => -46.6333,
        'status' => 'available',
        'zone' => 'Centro',
        'rating' => 4.6,
        'active_orders' => 1,
        'capacity' => 10,
        'sla_minutes' => 40,
        'tags' => ['default'],
    ],
    [
        'id' => 3,
        'name' => 'Carlos Oliveira - Zona Leste',
        'phone' => '(11) 96666-0003',
        'lat' => -23.5460,
        'lng' => -46.5100,
        'status' => 'busy',
        'zone' => 'Zona Leste',
        'rating' => 4.4,
        'active_orders' => 4,
        'capacity' => 6,
        'sla_minutes' => 55,
        'tags' => ['default'],
    ],
];

echo json_encode([
    'success' => true,
    'filters' => [
        'produto' => $produto,
        'quantidade' => $quantidade,
    ],
    'entregadores' => $default,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
