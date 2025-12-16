<?php
declare(strict_types=1);

/**
 * Alabama Favorites API
 * Gerencia favoritos do usuário
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../csrf.php';

// Verifica autenticação
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized'], JSON_THROW_ON_ERROR);
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    // GET: Lista favoritos do usuário
    if ($method === 'GET') {
        $stmt = $pdo->prepare("
            SELECT id, page_url, page_label, page_icon, sort_order, created_at
            FROM user_favorites
            WHERE user_id = :usuario_id
            ORDER BY sort_order ASC, created_at DESC
        ");
        $stmt->execute([':usuario_id' => $usuario_id]);
        $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'favorites' => $favorites
        ], JSON_THROW_ON_ERROR);
        exit;
    }
    
    // POST: Adiciona, remove ou reordena favoritos
    if ($method === 'POST') {
        try {
            $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON'], JSON_THROW_ON_ERROR);
            exit;
        }
        
        // Valida CSRF token
        if (!isset($input['_csrf_token']) || !validate_csrf_token($input['_csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token'], JSON_THROW_ON_ERROR);
            exit;
        }
        
        $action = $input['action'] ?? '';
        
        // Adicionar favorito
        if ($action === 'add') {
            $page_url = trim($input['page_url'] ?? '');
            $page_label = trim($input['page_label'] ?? '');
            $page_icon = trim($input['page_icon'] ?? 'fa-star');
            
            if (empty($page_url) || empty($page_label)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields'], JSON_THROW_ON_ERROR);
                exit;
            }
            
            // Verifica se já existe
            $stmt = $pdo->prepare("
                SELECT id FROM user_favorites 
                WHERE user_id = :usuario_id AND page_url = :page_url
            ");
            $stmt->execute([
                ':usuario_id' => $usuario_id,
                ':page_url' => $page_url
            ]);
            
            if ($stmt->fetch()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Page already in favorites'
                ], JSON_THROW_ON_ERROR);
                exit;
            }
            
            // Pega o próximo sort_order
            $stmt = $pdo->prepare("
                SELECT MAX(sort_order) as max_order 
                FROM user_favorites 
                WHERE user_id = :usuario_id
            ");
            $stmt->execute([':usuario_id' => $usuario_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $next_order = ((int)($result['max_order'] ?? 0)) + 1;
            
            // Insere novo favorito
            $stmt = $pdo->prepare("
                INSERT INTO user_favorites (user_id, page_url, page_label, page_icon, sort_order)
                VALUES (:usuario_id, :page_url, :page_label, :page_icon, :sort_order)
            ");
            $stmt->execute([
                ':usuario_id' => $usuario_id,
                ':page_url' => $page_url,
                ':page_label' => $page_label,
                ':page_icon' => $page_icon,
                ':sort_order' => $next_order
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Favorite added',
                'id' => (int)$pdo->lastInsertId()
            ], JSON_THROW_ON_ERROR);
            exit;
        }
        
        // Remover favorito
        if ($action === 'remove') {
            $page_url = trim($input['page_url'] ?? '');
            
            if (empty($page_url)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing page_url'], JSON_THROW_ON_ERROR);
                exit;
            }
            
            $stmt = $pdo->prepare("
                DELETE FROM user_favorites 
                WHERE user_id = :usuario_id AND page_url = :page_url
            ");
            $stmt->execute([
                ':usuario_id' => $usuario_id,
                ':page_url' => $page_url
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Favorite removed'
            ], JSON_THROW_ON_ERROR);
            exit;
        }
        
        // Reordenar favoritos
        if ($action === 'reorder') {
            $order = $input['order'] ?? [];
            
            if (!is_array($order) || empty($order)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid order array'], JSON_THROW_ON_ERROR);
                exit;
            }
            
            $pdo->beginTransaction();
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE user_favorites 
                    SET sort_order = :sort_order 
                    WHERE user_id = :usuario_id AND page_url = :page_url
                ");
                
                foreach ($order as $index => $page_url) {
                    $stmt->execute([
                        ':usuario_id' => $usuario_id,
                        ':page_url' => $page_url,
                        ':sort_order' => $index
                    ]);
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Favorites reordered'
                ], JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            
            exit;
        }
        
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action'], JSON_THROW_ON_ERROR);
        exit;
    }
    
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed'], JSON_THROW_ON_ERROR);
    
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ], JSON_THROW_ON_ERROR);
}
