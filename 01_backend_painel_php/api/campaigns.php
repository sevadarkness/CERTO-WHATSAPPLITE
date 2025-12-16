<?php
declare(strict_types=1);

// /api/campaigns.php
// Endpoint simples para a extensão criar campanhas (bulk) no backend.
// Este endpoint NÃO dispara via DOM/WhatsApp Web.
// Ele apenas cria um job em whatsapp_bulk_jobs + itens em whatsapp_bulk_job_items,
// que serão processados pelo worker CLI existente (whatsapp_contacts_bulk_worker.php).

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin = '';

if ($origin !== '') {
    if (str_starts_with($origin, 'chrome-extension://')) {
        $allowedOrigin = $origin;
    } elseif (str_starts_with($origin, 'moz-extension://')) {
        $allowedOrigin = $origin;
    } elseif (str_starts_with($origin, 'edge-extension://')) {
        $allowedOrigin = $origin;
    } elseif (preg_match('/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/', $origin)) {
        $allowedOrigin = $origin;
    } else {
        $extraOrigins = trim((string)(getenv('ALABAMA_CORS_ALLOWED_ORIGINS') ?: ''));
        if ($extraOrigins !== '') {
            $allowedList = array_map('trim', explode(',', $extraOrigins));
            foreach ($allowedList as $allowed) {
                if ($allowed !== '' && $origin === $allowed) {
                    $allowedOrigin = $origin;
                    break;
                }
            }
        }
    }
}

if ($allowedOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Vary: Origin');
} else {
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Alabama-Proxy-Key');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '', true);
if (!is_array($body)) {
    respond(['ok' => false, 'error' => 'Invalid JSON body'], 400);
}

// Optional shared secret (reuse OPENAI_PROXY_SECRET)
$requiredSecret = trim((string)(getenv('OPENAI_PROXY_SECRET') ?: (getenv('ALABAMA_EXTENSION_SECRET') ?: '')));
$given = trim((string)($_SERVER['HTTP_X_ALABAMA_PROXY_KEY'] ?? ''));

// Determine user ID based on authentication method
$userId = 0;
if ($requiredSecret !== '' && $given !== '' && hash_equals($requiredSecret, $given)) {
    // Valid secret provided - use user_id from body or default
    $userId = (int)($body['user_id'] ?? ($body['userId'] ?? 0));
    if ($userId <= 0) {
        $userId = (int)(getenv('EXTENSION_USER_ID') ?: 1);
    }
} else {
    // No valid secret - require authenticated session
    require_once __DIR__ . '/../session_bootstrap.php';
    require_once __DIR__ . '/../rbac.php';
    $user = current_user();
    if (!$user) {
        respond(['ok' => false, 'error' => 'Não autenticado. Forneça X-Alabama-Proxy-Key ou faça login.'], 401);
    }
    $userId = (int)$user['id'];
}

// Suporta 2 formatos:
// 1) Extensão nova: { message: string, recipients: string[], batchSize?: int, intervalSeconds?: int, name?: string, dryRun?: bool }
// 2) Compat (extensão antiga): { messageTemplate: {content:string}, recipients?:[], dryRun?:bool, ... }
$message = '';
if (isset($body['message']) && is_string($body['message'])) {
    $message = trim($body['message']);
} elseif (isset($body['messageTemplate']['content']) && is_string($body['messageTemplate']['content'])) {
    $message = trim((string)$body['messageTemplate']['content']);
}

if ($message === '') {
    respond(['ok' => false, 'error' => 'Campo "message" (ou messageTemplate.content) é obrigatório.'], 400);
}

$recipients = $body['recipients'] ?? ($body['to'] ?? null);
if (!is_array($recipients)) {
    respond(['ok' => false, 'error' => 'Campo "recipients" deve ser um array de telefones.'], 400);
}

$dryRun = (bool)($body['dryRun'] ?? false);

$batchSize = (int)($body['batchSize'] ?? 25);
$batchSize = max(1, min(200, $batchSize));

$intervalSeconds = (int)($body['intervalSeconds'] ?? 8);
$intervalSeconds = max(1, min(600, $intervalSeconds));

$minDelayMs = $intervalSeconds * 1000;
$maxDelayMs = $intervalSeconds * 1000;

$name = trim((string)($body['name'] ?? ''));
if ($name === '') {
    $name = 'Extensão - ' . date('Y-m-d H:i');
}

// Handle scheduling
$agendadoPara = null;
if (!empty($body['scheduled_at'])) {
    $timestamp = strtotime($body['scheduled_at']);
    if ($timestamp === false) {
        respond(['ok' => false, 'error' => 'Formato de data inválido em scheduled_at.'], 400);
    }
    $agendadoPara = date('Y-m-d H:i:s', $timestamp);
} elseif (!empty($body['agendado_para'])) {
    $timestamp = strtotime($body['agendado_para']);
    if ($timestamp === false) {
        respond(['ok' => false, 'error' => 'Formato de data inválido em agendado_para.'], 400);
    }
    $agendadoPara = date('Y-m-d H:i:s', $timestamp);
}

// DB
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../whatsapp_official_api.php';

$pdo = get_db_connection();

// Normaliza números
$normalized = [];
foreach ($recipients as $r) {
    if (!is_string($r)) continue;
    $e164 = whatsapp_normalize_phone_e164($r);
    if ($e164 === '') continue;
    
    // Validar formato E.164: + seguido de 10-15 dígitos
    $digits = preg_replace('/[^\d]/', '', $e164);
    if (strlen($digits) < 10 || strlen($digits) > 15) continue;
    
    $normalized[] = $e164;
}
$normalized = array_values(array_unique($normalized));

if (!$normalized) {
    respond(['ok' => false, 'error' => 'Nenhum telefone válido encontrado em recipients.'], 400);
}

$maxRecipients = (int)(getenv('ALABAMA_MAX_CAMPAIGN_RECIPIENTS') ?: 1000);
if (count($normalized) > $maxRecipients) {
    respond([
        'ok' => false, 
        'error' => "Número máximo de destinatários excedido. Limite: {$maxRecipients}",
        'max_allowed' => $maxRecipients,
        'received' => count($normalized)
    ], 400);
}

try {
    $pdo->beginTransaction();

    $stmtJob = $pdo->prepare("
        INSERT INTO whatsapp_bulk_jobs (user_id, nome_campanha, mensagem, total_destinatarios, min_delay_ms, max_delay_ms, is_simulation, status, agendado_para)
        VALUES (:user_id, :nome, :mensagem, :total, :min_delay, :max_delay, :sim, 'queued', :agendado)
    ");
    $stmtJob->execute([
        ':user_id' => $userId,
        ':nome' => $name,
        ':mensagem' => $message,
        ':total' => count($normalized),
        ':min_delay' => $minDelayMs,
        ':max_delay' => $maxDelayMs,
        ':sim' => $dryRun ? 1 : 0,
        ':agendado' => $agendadoPara,
    ]);

    $jobId = (int)$pdo->lastInsertId();

    $stmtItem = $pdo->prepare("
        INSERT INTO whatsapp_bulk_job_items (bulk_job_id, to_phone_e164, payload_json, status)
        VALUES (:job_id, :to, :payload, 'pending')
    ");

    foreach ($normalized as $to) {
        // payload_json é opcional; deixamos armazenado para debug.
        $payloadJson = json_encode([
            'to' => $to,
            'text' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmtItem->execute([
            ':job_id' => $jobId,
            ':to' => $to,
            ':payload' => $payloadJson,
        ]);
    }

    $pdo->commit();

    respond([
        'ok' => true,
        'jobId' => $jobId,
        'name' => $name,
        'totalRecipients' => count($normalized),
        'batchSize' => $batchSize,
        'intervalSeconds' => $intervalSeconds,
        'dryRun' => $dryRun,
        'status' => 'queued',
        'note' => 'Campanha criada. Rode o worker (whatsapp_contacts_bulk_worker.php) para processar a fila.',
    ]);
} catch (Throwable $e) {
    try { $pdo->rollBack(); } catch (Throwable $e2) {}
    respond(['ok' => false, 'error' => 'Falha ao criar campanha: ' . $e->getMessage()], 500);
}
