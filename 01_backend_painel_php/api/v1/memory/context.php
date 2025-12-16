<?php
declare(strict_types=1);

/**
 * Memory Server - Context Update Endpoint
 * 
 * Atualiza contexto de trabalho atual da extensão Chrome.
 * 
 * POST /api/v1/memory/context.php
 * {
 *   "context": {
 *     "current_chat": "...",
 *     "user_intent": "...",
 *     "active_flow": "..."
 *   }
 * }
 */

header('Content-Type: application/json; charset=utf-8');

// CORS headers
$allowedOrigins = getenv('ALABAMA_CORS_ORIGINS') ?: 'https://web.whatsapp.com';
$origins = array_map('trim', explode(',', $allowedOrigins));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Workspace-Key');
    header('Access-Control-Max-Age: 3600');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update context
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (!is_array($data) || !isset($data['context'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    try {
        require_once __DIR__ . '/../../../db_config.php';
        
        // Create table if not exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS memory_context (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                workspace_key VARCHAR(64) NOT NULL,
                context_data JSON NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_workspace (workspace_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $contextJson = json_encode($data['context'], JSON_UNESCAPED_UNICODE);
        
        $stmt = $pdo->prepare("
            INSERT INTO memory_context (workspace_key, context_data, updated_at)
            VALUES (:key, :data, NOW())
            ON DUPLICATE KEY UPDATE context_data = VALUES(context_data), updated_at = NOW()
        ");
        
        $stmt->execute([
            ':key' => $workspaceKey,
            ':data' => $contextJson,
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Context updated',
        ]);
        
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Internal server error',
            'message' => getenv('APP_DEBUG') === 'true' ? $e->getMessage() : null,
        ]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get current context
    try {
        require_once __DIR__ . '/../../../db_config.php';
        
        $stmt = $pdo->prepare("
            SELECT context_data, updated_at 
            FROM memory_context 
            WHERE workspace_key = :key
            LIMIT 1
        ");
        
        $stmt->execute([':key' => $workspaceKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            echo json_encode([
                'success' => true,
                'context' => json_decode($row['context_data'], true),
                'updated_at' => $row['updated_at'],
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'context' => null,
                'message' => 'No context found',
            ]);
        }
        
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Internal server error',
            'message' => getenv('APP_DEBUG') === 'true' ? $e->getMessage() : null,
        ]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
