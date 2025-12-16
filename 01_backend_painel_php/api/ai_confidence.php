<?php
declare(strict_types=1);

/**
 * AI Confidence API Endpoint
 * 
 * Manages AI confidence scoring system for copilot mode.
 * Tracks user feedback, suggestion usage, and enables autonomous responses
 * when confidence threshold is reached.
 * 
 * Endpoints:
 * - GET: Returns current score, statistics, and configuration
 * - POST action=feedback: Registers feedback (good/bad/correction)
 * - POST action=suggestion_used: Registers suggestion usage (with/without edit)
 * - POST action=auto_sent: Registers automatic send
 * - POST action=toggle_copilot: Activates/deactivates copilot mode
 * - POST action=set_threshold: Sets confidence threshold
 */

header('Content-Type: application/json; charset=utf-8');

// CORS headers
$allowedOrigins = getenv('ALABAMA_CORS_ORIGINS') ?: 'https://web.whatsapp.com';
$origins = array_map('trim', explode(',', $allowedOrigins));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Extension-Secret');
    header('Access-Control-Max-Age: 3600');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
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

// Helper functions
function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Calculate confidence score (0-100) based on metrics
 */
function calculateConfidenceScore(array $metrics): float
{
    // Base score from feedback ratio
    $totalFeedback = $metrics['total_good'] + $metrics['total_bad'];
    $feedbackScore = 0;
    if ($totalFeedback > 0) {
        $feedbackScore = ($metrics['total_good'] / $totalFeedback) * 40; // max 40 points
    }
    
    // Knowledge base score
    $knowledgeScore = min(20, 
        ($metrics['total_faq'] * 0.5) + 
        ($metrics['total_products'] * 0.3) + 
        ($metrics['total_examples'] * 1.0)
    ); // max 20 points
    
    // Usage score
    $totalSuggestions = $metrics['total_suggestions_used'] + $metrics['total_suggestions_edited'];
    $usageScore = 0;
    if ($totalSuggestions > 0) {
        $usageScore = ($metrics['total_suggestions_used'] / $totalSuggestions) * 25; // max 25 points
    }
    
    // Auto-send success score
    $autoScore = min(15, $metrics['total_auto_sent'] * 0.5); // max 15 points
    
    // Total (max 100)
    return min(100, $feedbackScore + $knowledgeScore + $usageScore + $autoScore);
}

/**
 * Get confidence level based on score
 */
function getConfidenceLevel(float $score): array
{
    if ($score >= 90) {
        return [
            'level' => 'autonomous',
            'label' => 'Autônomo',
            'color' => '#3b82f6',
            'emoji' => '🔵',
            'description' => 'IA responde automaticamente'
        ];
    }
    if ($score >= 70) {
        return [
            'level' => 'copilot',
            'label' => 'Copiloto',
            'color' => '#22c55e',
            'emoji' => '🟢',
            'description' => 'IA pode responder casos simples'
        ];
    }
    if ($score >= 50) {
        return [
            'level' => 'assisted',
            'label' => 'Assistido',
            'color' => '#eab308',
            'emoji' => '🟡',
            'description' => 'IA sugere, você decide'
        ];
    }
    if ($score >= 30) {
        return [
            'level' => 'learning',
            'label' => 'Aprendendo',
            'color' => '#f97316',
            'emoji' => '🟠',
            'description' => 'IA em treinamento'
        ];
    }
    return [
        'level' => 'beginner',
        'label' => 'Iniciante',
        'color' => '#ef4444',
        'emoji' => '🔴',
        'description' => 'IA apenas sugere respostas'
    ];
}

/**
 * Points configuration for different actions
 */
function getPointsConfig(): array
{
    return [
        'feedback_good' => 2.0,
        'feedback_bad' => -3.0,
        'feedback_correction' => -2.0,
        'suggestion_used' => 1.0,
        'suggestion_edited' => -0.5,
        'auto_sent_success' => 1.5,
        'faq_added' => 0.5,
        'product_added' => 0.5,
        'example_added' => 1.0,
    ];
}

/**
 * Get or create metrics for user
 */
function getOrCreateMetrics(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT * FROM ai_confidence_metrics WHERE user_id = ?");
    $stmt->execute([$userId]);
    $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$metrics) {
        // Create new metrics record
        $stmt = $pdo->prepare("
            INSERT INTO ai_confidence_metrics (user_id, score) 
            VALUES (?, 0)
        ");
        $stmt->execute([$userId]);
        
        // Fetch the newly created record
        $stmt = $pdo->prepare("SELECT * FROM ai_confidence_metrics WHERE user_id = ?");
        $stmt->execute([$userId]);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return $metrics;
}

/**
 * Update metrics and recalculate score
 */
function updateMetrics(PDO $pdo, int $userId, array $updates): array
{
    $metrics = getOrCreateMetrics($pdo, $userId);
    
    // Apply updates
    foreach ($updates as $field => $value) {
        if (isset($metrics[$field])) {
            $metrics[$field] += $value;
        }
    }
    
    // Recalculate score
    $newScore = calculateConfidenceScore($metrics);
    
    // Build update query
    $fields = [];
    $values = [];
    foreach ($updates as $field => $value) {
        $fields[] = "$field = $field + ?";
        $values[] = $value;
    }
    $fields[] = "score = ?";
    $values[] = $newScore;
    $values[] = $userId;
    
    $sql = "UPDATE ai_confidence_metrics SET " . implode(', ', $fields) . " WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    
    // Fetch updated metrics
    return getOrCreateMetrics($pdo, $userId);
}

/**
 * Log confidence event
 */
function logConfidenceEvent(PDO $pdo, int $userId, string $action, float $points, ?string $reason = null, ?array $metadata = null): void
{
    $stmt = $pdo->prepare("
        INSERT INTO ai_confidence_log (user_id, action, points, reason, metadata)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $action,
        $points,
        $reason,
        $metadata ? json_encode($metadata) : null
    ]);
}

// Main logic
try {
    require_once __DIR__ . '/../db_config.php';
    
    // For this demo, use user_id from query/body or default to 1
    // In production, this should come from session/auth
    $userId = 1;
    if (isset($_GET['user_id'])) {
        $userId = (int) $_GET['user_id'];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        if (isset($data['user_id'])) {
            $userId = (int) $data['user_id'];
        }
    }
    
    // GET request - return current status
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $metrics = getOrCreateMetrics($pdo, $userId);
        $score = (float) $metrics['score'];
        $level = getConfidenceLevel($score);
        
        respond([
            'ok' => true,
            'score' => $score,
            'level' => $level,
            'metrics' => [
                'total_good' => (int) $metrics['total_good'],
                'total_bad' => (int) $metrics['total_bad'],
                'total_corrections' => (int) $metrics['total_corrections'],
                'total_auto_sent' => (int) $metrics['total_auto_sent'],
                'total_suggestions_used' => (int) $metrics['total_suggestions_used'],
                'total_suggestions_edited' => (int) $metrics['total_suggestions_edited'],
                'total_faq' => (int) $metrics['total_faq'],
                'total_products' => (int) $metrics['total_products'],
                'total_examples' => (int) $metrics['total_examples'],
            ],
            'config' => [
                'copilot_enabled' => (bool) $metrics['copilot_enabled'],
                'copilot_threshold' => (float) $metrics['copilot_threshold'],
            ],
            'points_to_threshold' => max(0, (float) $metrics['copilot_threshold'] - $score)
        ]);
    }
    
    // POST request - handle actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        
        if (!is_array($data)) {
            respond(['ok' => false, 'error' => 'Invalid JSON'], 400);
        }
        
        $action = $data['action'] ?? '';
        $pointsConfig = getPointsConfig();
        
        switch ($action) {
            case 'feedback':
                $type = $data['type'] ?? ''; // 'good', 'bad', 'correction'
                $updates = [];
                $points = 0;
                
                if ($type === 'good') {
                    $updates['total_good'] = 1;
                    $points = $pointsConfig['feedback_good'];
                } elseif ($type === 'bad') {
                    $updates['total_bad'] = 1;
                    $points = $pointsConfig['feedback_bad'];
                } elseif ($type === 'correction') {
                    $updates['total_corrections'] = 1;
                    $points = $pointsConfig['feedback_correction'];
                } else {
                    respond(['ok' => false, 'error' => 'Invalid feedback type'], 400);
                }
                
                $metrics = updateMetrics($pdo, $userId, $updates);
                logConfidenceEvent($pdo, $userId, "feedback_$type", $points, $data['reason'] ?? null, $data['metadata'] ?? null);
                
                $score = (float) $metrics['score'];
                respond([
                    'ok' => true,
                    'score' => $score,
                    'level' => getConfidenceLevel($score),
                    'points_awarded' => $points
                ]);
                break;
                
            case 'suggestion_used':
                $edited = $data['edited'] ?? false;
                $updates = $edited ? ['total_suggestions_edited' => 1] : ['total_suggestions_used' => 1];
                $points = $edited ? $pointsConfig['suggestion_edited'] : $pointsConfig['suggestion_used'];
                
                $metrics = updateMetrics($pdo, $userId, $updates);
                logConfidenceEvent($pdo, $userId, $edited ? 'suggestion_edited' : 'suggestion_used', $points, null, $data['metadata'] ?? null);
                
                $score = (float) $metrics['score'];
                respond([
                    'ok' => true,
                    'score' => $score,
                    'level' => getConfidenceLevel($score),
                    'points_awarded' => $points
                ]);
                break;
                
            case 'auto_sent':
                $updates = ['total_auto_sent' => 1];
                $points = $pointsConfig['auto_sent_success'];
                
                $metrics = updateMetrics($pdo, $userId, $updates);
                logConfidenceEvent($pdo, $userId, 'auto_sent_success', $points, null, $data['metadata'] ?? null);
                
                $score = (float) $metrics['score'];
                respond([
                    'ok' => true,
                    'score' => $score,
                    'level' => getConfidenceLevel($score),
                    'points_awarded' => $points
                ]);
                break;
                
            case 'toggle_copilot':
                $enabled = $data['enabled'] ?? false;
                $stmt = $pdo->prepare("UPDATE ai_confidence_metrics SET copilot_enabled = ? WHERE user_id = ?");
                $stmt->execute([$enabled ? 1 : 0, $userId]);
                
                $metrics = getOrCreateMetrics($pdo, $userId);
                respond([
                    'ok' => true,
                    'copilot_enabled' => (bool) $metrics['copilot_enabled']
                ]);
                break;
                
            case 'set_threshold':
                $threshold = $data['threshold'] ?? 70;
                $threshold = max(50, min(95, (float) $threshold));
                
                $stmt = $pdo->prepare("UPDATE ai_confidence_metrics SET copilot_threshold = ? WHERE user_id = ?");
                $stmt->execute([$threshold, $userId]);
                
                $metrics = getOrCreateMetrics($pdo, $userId);
                respond([
                    'ok' => true,
                    'copilot_threshold' => (float) $metrics['copilot_threshold']
                ]);
                break;
                
            case 'knowledge_update':
                // Update knowledge base counters
                $updates = [];
                if (isset($data['faq_count'])) {
                    $updates['total_faq'] = (int) $data['faq_count'];
                }
                if (isset($data['product_count'])) {
                    $updates['total_products'] = (int) $data['product_count'];
                }
                if (isset($data['example_count'])) {
                    $updates['total_examples'] = (int) $data['example_count'];
                }
                
                if (!empty($updates)) {
                    // Set these as absolute values, not increments
                    $fields = [];
                    $values = [];
                    foreach ($updates as $field => $value) {
                        $fields[] = "$field = ?";
                        $values[] = $value;
                    }
                    $values[] = $userId;
                    
                    $sql = "UPDATE ai_confidence_metrics SET " . implode(', ', $fields) . " WHERE user_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);
                    
                    // Recalculate score
                    $metrics = getOrCreateMetrics($pdo, $userId);
                    $newScore = calculateConfidenceScore($metrics);
                    $stmt = $pdo->prepare("UPDATE ai_confidence_metrics SET score = ? WHERE user_id = ?");
                    $stmt->execute([$newScore, $userId]);
                }
                
                $metrics = getOrCreateMetrics($pdo, $userId);
                $score = (float) $metrics['score'];
                respond([
                    'ok' => true,
                    'score' => $score,
                    'level' => getConfidenceLevel($score)
                ]);
                break;
                
            default:
                respond(['ok' => false, 'error' => 'Invalid action'], 400);
        }
    }
    
    respond(['ok' => false, 'error' => 'Method not allowed'], 405);
    
} catch (Throwable $e) {
    respond([
        'ok' => false,
        'error' => $e->getMessage()
    ], 500);
}
