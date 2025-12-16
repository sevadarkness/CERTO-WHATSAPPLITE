<?php
declare(strict_types=1);

/**
 * Notifications API Endpoint
 * Handles notification operations for authenticated users.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../rbac.php';
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../app/Services/NotificationService.php';

use RedeAlabama\Services\NotificationService;

function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'ok' => $status >= 200 && $status < 300,
        'data' => $data['data'] ?? $data,
        'error' => $data['error'] ?? null,
        'meta' => $data['meta'] ?? [],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Check authentication
$user = current_user();
if (!$user) {
    respond(['error' => ['code' => 'unauthenticated', 'message' => 'Não autenticado.']], 401);
}

$userId = (int) $user['id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    global $pdo;

    // GET requests - list or count notifications
    if ($method === 'GET') {
        // Count unread notifications
        if (isset($_GET['count']) && $_GET['count'] === '1') {
            $count = NotificationService::getUnreadCount($pdo, $userId);
            respond(['count' => $count]);
        }
        
        // List notifications
        $unreadOnly = isset($_GET['unread']) && $_GET['unread'] === '1';
        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
        $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
        
        if ($unreadOnly) {
            $notifications = NotificationService::getUnread($pdo, $userId, $limit);
        } else {
            $notifications = NotificationService::getAll($pdo, $userId, $limit, $offset);
        }
        
        respond([
            'data' => $notifications,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($notifications),
            ],
        ]);
    }

    // POST requests - mark as read
    if ($method === 'POST') {
        $action = $_GET['action'] ?? $_POST['action'] ?? null;
        
        if (!$action) {
            respond(['error' => ['code' => 'missing_action', 'message' => 'Action parameter required']], 400);
        }
        
        // Mark single notification as read
        if ($action === 'read') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                respond(['error' => ['code' => 'invalid_id', 'message' => 'Valid notification ID required']], 400);
            }
            
            $success = NotificationService::markAsRead($pdo, $id, $userId);
            
            if ($success) {
                respond(['message' => 'Notification marked as read', 'id' => $id]);
            } else {
                respond(['error' => ['code' => 'not_found', 'message' => 'Notification not found or already read']], 404);
            }
        }
        
        // Mark all notifications as read
        if ($action === 'read_all') {
            $count = NotificationService::markAllAsRead($pdo, $userId);
            respond(['message' => 'All notifications marked as read', 'count' => $count]);
        }
        
        respond(['error' => ['code' => 'invalid_action', 'message' => 'Invalid action']], 400);
    }

    // Other methods not allowed
    respond(['error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed']], 405);

} catch (Throwable $e) {
    if (function_exists('log_app_event')) {
        log_app_event('notifications_api', 'error', [
            'user_id' => $userId,
            'method' => $method,
            'error' => $e->getMessage(),
        ]);
    }
    
    respond([
        'error' => [
            'code' => 'internal_error',
            'message' => 'Internal server error',
        ],
    ], 500);
}
