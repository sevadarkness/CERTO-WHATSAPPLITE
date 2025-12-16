<?php
declare(strict_types=1);

/**
 * Memory Server - Query Endpoint
 * 
 * Consulta memória armazenada e exemplos relevantes.
 * 
 * GET /api/v1/memory/query.php?q=search_term&limit=10&type=interaction
 */

header('Content-Type: application/json; charset=utf-8');

// CORS headers
$allowedOrigins = getenv('ALABAMA_CORS_ORIGINS') ?: 'https://web.whatsapp.com';
$origins = array_map('trim', explode(',', $allowedOrigins));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Workspace-Key');
    header('Access-Control-Max-Age: 3600');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

$query = $_GET['q'] ?? '';
$limit = min((int)($_GET['limit'] ?? 10), 100);
$type = $_GET['type'] ?? '';

try {
    require_once __DIR__ . '/../../../db_config.php';
    
    $sql = "SELECT id, event_type, event_data, created_at 
            FROM memory_events 
            WHERE 1=1";
    
    $params = [];
    
    if ($type !== '') {
        $sql .= " AND event_type = :type";
        $params[':type'] = $type;
    }
    
    if ($query !== '') {
        // Escape special characters for LIKE pattern
        $escapedQuery = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $sql .= " AND event_data LIKE :query";
        $params[':query'] = '%' . $escapedQuery . '%';
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id' => (int)$row['id'],
            'type' => $row['event_type'],
            'data' => json_decode($row['event_data'], true),
            'created_at' => $row['created_at'],
        ];
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($results),
        'results' => $results,
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => getenv('APP_DEBUG') === 'true' ? $e->getMessage() : null,
    ]);
}
