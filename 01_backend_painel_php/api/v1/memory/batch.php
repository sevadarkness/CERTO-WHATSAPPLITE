<?php
declare(strict_types=1);

/**
 * Memory Server - Batch Events Endpoint
 * 
 * Recebe eventos em lote da extensão Chrome para sincronizar memória.
 * 
 * POST /api/v1/memory/batch.php
 * {
 *   "events": [
 *     {"type": "page_view", "url": "...", "timestamp": "..."},
 *     {"type": "interaction", "data": {...}}
 *   ]
 * }
 */

header('Content-Type: application/json; charset=utf-8');

// CORS headers
$allowedOrigins = getenv('ALABAMA_CORS_ORIGINS') ?: 'https://web.whatsapp.com';
$origins = array_map('trim', explode(',', $allowedOrigins));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Workspace-Key');
    header('Access-Control-Max-Age: 3600');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Authentication via workspace key
$workspaceKey = $_SERVER['HTTP_X_WORKSPACE_KEY'] ?? '';
$expectedKey = getenv('ALABAMA_MEMORY_WORKSPACE_KEY') ?: '';

if ($expectedKey !== '' && $workspaceKey !== $expectedKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!is_array($data) || !isset($data['events']) || !is_array($data['events'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

// Store events in database
try {
    require_once __DIR__ . '/../../../db_config.php';
    
    // Create table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS memory_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(64) NOT NULL,
            event_data JSON NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type_created (event_type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $stmt = $pdo->prepare("
        INSERT INTO memory_events (event_type, event_data, created_at)
        VALUES (:type, :data, NOW())
    ");
    
    $processed = 0;
    foreach ($data['events'] as $event) {
        if (!isset($event['type'])) {
            continue;
        }
        
        $stmt->execute([
            ':type' => $event['type'],
            ':data' => json_encode($event, JSON_UNESCAPED_UNICODE),
        ]);
        $processed++;
    }
    
    echo json_encode([
        'success' => true,
        'processed' => $processed,
        'total' => count($data['events']),
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => getenv('APP_DEBUG') === 'true' ? $e->getMessage() : null,
    ]);
}
