<?php
declare(strict_types=1);

/**
 * Alabama Menu Badges API
 * Retorna contadores para badges do menu lateral
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../db_config.php';

// Verifica autenticação
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized'], JSON_THROW_ON_ERROR);
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];

try {
    $badges = [];
    
    // new_leads: COUNT leads criados hoje (usando índice otimizado)
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM leads 
            WHERE data_cadastro >= CURDATE() 
            AND data_cadastro < CURDATE() + INTERVAL 1 DAY
            AND status = 'novo'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $badges['new_leads'] = (int)($result['total'] ?? 0);
    } catch (Throwable $e) {
        $badges['new_leads'] = 0;
    }
    
    // unread_messages: COUNT conversas não lidas do WhatsApp
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT telefone) as total 
            FROM whatsapp_messages 
            WHERE is_from_me = 0 
            AND read_at IS NULL
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $badges['unread_messages'] = (int)($result['total'] ?? 0);
    } catch (Throwable $e) {
        $badges['unread_messages'] = 0;
    }
    
    // active_campaigns: COUNT campanhas ativas de remarketing
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM remarketing_campanhas 
            WHERE ativo = 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $badges['active_campaigns'] = (int)($result['total'] ?? 0);
    } catch (Throwable $e) {
        $badges['active_campaigns'] = 0;
    }
    
    // pending_tasks: COUNT tarefas pendentes (se houver tabela de tarefas)
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM tasks 
            WHERE status = 'pending'
            AND assigned_to = :usuario_id
        ");
        $stmt->execute([':usuario_id' => $usuario_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $badges['pending_tasks'] = (int)($result['total'] ?? 0);
    } catch (Throwable $e) {
        // Tabela tasks pode não existir, não é erro crítico
        $badges['pending_tasks'] = 0;
    }
    
    echo json_encode([
        'success' => true,
        'badges' => $badges,
        'timestamp' => time()
    ], JSON_THROW_ON_ERROR);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ], JSON_THROW_ON_ERROR);
}
