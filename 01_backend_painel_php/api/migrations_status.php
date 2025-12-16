<?php
/**
 * migrations_status.php
 * 
 * Endpoint protegido para verificar o status das migrations.
 * Retorna informações sobre quais tabelas existem e o estado do sistema de auto-migration.
 */

declare(strict_types=1);

require_once __DIR__ . '/../rbac.php';
require_role(['Administrador']); // Apenas administradores podem ver status de migrations

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../database/auto_migrate.php';

header('Content-Type: application/json; charset=utf-8');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $pdo = get_db_connection();
    
    // Verifica o status de cada tabela gerenciada pelo auto-migrate
    $tables = [
        'remarketing_campanhas'
    ];
    
    $status = [];
    foreach ($tables as $table) {
        $exists = auto_migrate_table_exists($pdo, $table);
        $status[] = [
            'table' => $table,
            'exists' => $exists,
            'status' => $exists ? 'ok' : 'missing'
        ];
    }
    
    // Informações gerais
    $response = [
        'success' => true,
        'auto_migrate_enabled' => getenv('ALABAMA_AUTO_MIGRATE') !== '0',
        'tables' => $status,
        'timestamp' => date('c')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to check migration status',
        'message' => $e->getMessage()
    ]);
}
