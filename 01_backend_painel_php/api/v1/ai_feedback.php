<?php
declare(strict_types=1);

/**
 * AI Feedback Loop Endpoint
 * 
 * Persiste feedback (accepted/edited/rejected) das respostas da IA.
 * Usado para melhorar continuamente o modelo.
 * 
 * POST /api/v1/ai_feedback.php
 * {
 *   "message_id": 123,
 *   "original_response": "...",
 *   "feedback_type": "accepted|edited|rejected",
 *   "edited_response": "...",  // only for type=edited
 *   "rejection_reason": "...", // optional for type=rejected
 *   "context": {...}            // optional metadata
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
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Extension-Secret');
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

// Optional authentication via extension secret
$extensionSecret = $_SERVER['HTTP_X_EXTENSION_SECRET'] ?? '';
$expectedSecret = getenv('ALABAMA_EXTENSION_SECRET') ?: '';

if ($expectedSecret !== '' && $extensionSecret !== '' && $extensionSecret !== $expectedSecret) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$messageId = $data['message_id'] ?? null;
$originalResponse = $data['original_response'] ?? '';
$feedbackType = $data['feedback_type'] ?? '';

if (!in_array($feedbackType, ['accepted', 'edited', 'rejected'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid feedback_type. Must be: accepted, edited, or rejected']);
    exit;
}

if ($originalResponse === '') {
    http_response_code(400);
    echo json_encode(['error' => 'original_response is required']);
    exit;
}

$editedResponse = $data['edited_response'] ?? null;
$rejectionReason = $data['rejection_reason'] ?? null;
$context = $data['context'] ?? null;

try {
    require_once __DIR__ . '/../../db_config.php';
    
    // Create table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ai_feedback (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_id BIGINT UNSIGNED NULL,
            original_response TEXT NOT NULL,
            feedback_type ENUM('accepted', 'edited', 'rejected') NOT NULL,
            edited_response TEXT NULL,
            rejection_reason VARCHAR(500) NULL,
            context_json JSON NULL,
            user_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_feedback_type (feedback_type),
            INDEX idx_message_id (message_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Insert feedback
    $stmt = $pdo->prepare("
        INSERT INTO ai_feedback 
        (message_id, original_response, feedback_type, edited_response, rejection_reason, context_json, created_at)
        VALUES 
        (:message_id, :original, :type, :edited, :reason, :context, NOW())
    ");
    
    $stmt->execute([
        ':message_id' => $messageId,
        ':original' => $originalResponse,
        ':type' => $feedbackType,
        ':edited' => $editedResponse,
        ':reason' => $rejectionReason,
        ':context' => $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
    ]);
    
    $feedbackId = (int)$pdo->lastInsertId();
    
    // Log event for analytics
    try {
        require_once __DIR__ . '/../../logger.php';
        if (function_exists('log_app_event')) {
            log_app_event('ai_feedback', 'feedback_received', [
                'feedback_id' => $feedbackId,
                'type' => $feedbackType,
                'message_id' => $messageId,
            ]);
        }
    } catch (Throwable $e) {
        // Logger not available, continue without logging
    }
    
    echo json_encode([
        'success' => true,
        'feedback_id' => $feedbackId,
        'message' => 'Feedback saved successfully',
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => getenv('APP_DEBUG') === 'true' ? $e->getMessage() : null,
    ]);
}
