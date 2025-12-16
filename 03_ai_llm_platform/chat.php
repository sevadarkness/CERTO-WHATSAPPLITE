<?php
declare(strict_types=1);

// /ai/chat.php
// Endpoint leve para a extensão (MV3 service worker) chamar o backend e:
//  - injetar contexto do painel (CRM/estoque/promos/bot settings)
//  - chamar OpenAI server-side sem expor API key

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// CORS seguro com whitelist de origens permitidas
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin = '';

if ($origin !== '') {
    // Extensões Chrome sempre permitidas (prefixo chrome-extension://)
    if (str_starts_with($origin, 'chrome-extension://')) {
        $allowedOrigin = $origin;
    }
    // Extensões Firefox (moz-extension://)
    elseif (str_starts_with($origin, 'moz-extension://')) {
        $allowedOrigin = $origin;
    }
    // Extensões Edge (edge-extension://)
    elseif (str_starts_with($origin, 'edge-extension://')) {
        $allowedOrigin = $origin;
    }
    // Localhost para desenvolvimento
    elseif (preg_match('/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/', $origin)) {
        $allowedOrigin = $origin;
    }
    // Domínios adicionais configurados via env (lista separada por vírgula)
    else {
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

// Só envia header CORS se a origem for permitida
if ($allowedOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Vary: Origin');
} else {
    // Para requisições sem Origin (same-origin, curl, etc), não precisa de CORS
    // Para origens não permitidas, o browser bloqueará a resposta
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Alabama-Proxy-Key');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    // Preflight: retorna 204 apenas se origem permitida
    if ($allowedOrigin !== '') {
        http_response_code(204);
    } else {
        http_response_code(403);
    }
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

// Optional shared secret (reuse OPENAI_PROXY_SECRET so you keep one secret for extension + proxy)
$requiredSecret = trim((string)(getenv('OPENAI_PROXY_SECRET') ?: (getenv('ALABAMA_EXTENSION_SECRET') ?: '')));
if ($requiredSecret !== '') {
    $given = trim((string)($_SERVER['HTTP_X_ALABAMA_PROXY_KEY'] ?? ''));
    if ($given === '' || !hash_equals($requiredSecret, $given)) {
        respond(['ok' => false, 'error' => 'Unauthorized'], 401);
    }
}

// --- Rate Limiting ---
$rateLimitEnabled = getenv('ALABAMA_RATE_LIMIT_ENABLED') !== '0';
if ($rateLimitEnabled) {
    $rateLimitKey = 'ai_chat_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $rateLimitFile = sys_get_temp_dir() . '/alabama_rate_' . md5($rateLimitKey) . '.json';
    $rateLimitMax = (int)(getenv('ALABAMA_RATE_LIMIT_MAX') ?: 30); // 30 requests
    $rateLimitWindow = (int)(getenv('ALABAMA_RATE_LIMIT_WINDOW') ?: 60); // per 60 seconds
    
    $rateData = [];
    if (is_file($rateLimitFile)) {
        $raw = @file_get_contents($rateLimitFile);
        $rateData = json_decode($raw ?: '{}', true) ?: [];
    }
    
    $now = time();
    $windowStart = $now - $rateLimitWindow;
    
    // Limpar requests antigas
    $rateData['requests'] = array_filter(
        $rateData['requests'] ?? [],
        fn($ts) => $ts > $windowStart
    );
    
    // Verificar limite
    if (count($rateData['requests']) >= $rateLimitMax) {
        $retryAfter = ($rateData['requests'][0] ?? $now) + $rateLimitWindow - $now;
        header('Retry-After: ' . max(1, $retryAfter));
        respond([
            'ok' => false, 
            'error' => 'Rate limit excedido. Aguarde ' . $retryAfter . ' segundos.',
            'retry_after' => $retryAfter
        ], 429);
    }
    
    // Registrar request
    $rateData['requests'][] = $now;
    @file_put_contents($rateLimitFile, json_encode($rateData), LOCK_EX);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    respond(['ok' => false, 'error' => 'Invalid JSON body'], 400);
}

$messages = $data['messages'] ?? null;
if (!is_array($messages) || count($messages) < 1) {
    respond(['ok' => false, 'error' => 'Campo "messages" ausente ou inválido'], 400);
}

$model = trim((string)($data['model'] ?? 'gpt-4o-mini'));
if ($model === '') {
    $model = 'gpt-4o-mini';
}

$temperature = $data['temperature'] ?? 0.7;
if (!is_numeric($temperature)) {
    $temperature = 0.7;
}
$temperature = max(0.0, min(2.0, (float)$temperature));

$maxTokens = $data['max_tokens'] ?? ($data['maxTokens'] ?? 450);
if (!is_numeric($maxTokens)) {
    $maxTokens = 450;
}
$maxTokens = max(16, min(2000, (int)$maxTokens));

$meta = $data['meta'] ?? [];
if (!is_array($meta)) {
    $meta = [];
}
$chatTitle = trim((string)($meta['chatTitle'] ?? ''));
$contactPhone = trim((string)($meta['contactPhone'] ?? ''));
$mode = trim((string)($meta['mode'] ?? ''));
$transcript = (string)($data['transcript'] ?? '');

// Best-effort: se o telefone não veio, tenta extrair do título.
if ($contactPhone === '' && $chatTitle !== '') {
    if (preg_match('/(\+?\d[\d\s().-]{6,}\d)/', $chatTitle, $m)) {
        $contactPhone = preg_replace('/[^\d+]/', '', (string)$m[1]);
        if ($contactPhone !== '' && $contactPhone[0] !== '+') {
            $contactPhone = '+' . $contactPhone;
        }
    }
}

// --- Sanitização contra Prompt Injection ---
function sanitize_for_prompt(string $text, int $maxLen = 500): string {
    // Remove caracteres de controle
    $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
    // Remove padrões que parecem instruções
    $clean = preg_replace('/\b(ignore|disregard|forget|override|system|instruction|prompt)\b/i', '[FILTERED]', $clean);
    // Trunca
    if (mb_strlen($clean) > $maxLen) {
        $clean = mb_substr($clean, 0, $maxLen) . '...';
    }
    return $clean;
}

// --- Contexto do painel (CRM/catalogo/config) ---
$panelContext = '';
$botSystemPrompt = '';

try {
    require_once __DIR__ . '/../01_backend_painel_php/db_config.php';
    $pdo = get_db_connection();

    // Pega o System Prompt configurado no painel (whatsapp_bot_settings), se existir.
    try {
        $stmt = $pdo->query("SELECT llm_system_prompt FROM whatsapp_bot_settings ORDER BY id DESC LIMIT 1");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if (is_array($row) && !empty($row['llm_system_prompt'])) {
            $botSystemPrompt = trim((string)$row['llm_system_prompt']);
        }
    } catch (Throwable $e) {
        // tabela pode não existir em alguns ambientes
        $botSystemPrompt = '';
    }

    // Contexto CRM por telefone
    if ($contactPhone !== '') {
        $digits = preg_replace('/\D+/', '', $contactPhone);
        $like = '%' . $digits . '%';

        $ctxParts = [];
        $ctxParts[] = 'DADOS DO PAINEL (use somente como referência; não invente):';
        $ctxParts[] = 'Contato (raw): ' . $contactPhone;

        // Lead
        try {
            $stmtLead = $pdo->prepare("SELECT * FROM leads WHERE telefone_cliente LIKE :tel ORDER BY id DESC LIMIT 1");
            $stmtLead->execute([':tel' => $like]);
            $lead = $stmtLead->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($lead) {
                $ctxParts[] = 'LEAD:';
                if (!empty($lead['nome_cliente'])) $ctxParts[] = '- Nome: ' . sanitize_for_prompt((string)$lead['nome_cliente'], 100);
                if (!empty($lead['status'])) $ctxParts[] = '- Status: ' . (string)$lead['status'];
                if (!empty($lead['vendedor_responsavel_id'])) $ctxParts[] = '- Vendedor ID: ' . (string)$lead['vendedor_responsavel_id'];
                if (!empty($lead['observacao'])) $ctxParts[] = '- Obs: ' . sanitize_for_prompt((string)$lead['observacao'], 300);
            }
        } catch (Throwable $e) {
            // ignore
        }

        // Últimas compras
        try {
            $stmtSales = $pdo->prepare("\
                SELECT v.data_venda, v.valor_total, p.nome AS produto, s.sabor AS sabor\
                FROM vendas v\
                LEFT JOIN produtos p ON v.produto_id = p.id\
                LEFT JOIN sabores s ON v.sabor_id = s.id\
                WHERE v.telefone_cliente LIKE :tel\
                ORDER BY v.data_venda DESC\
                LIMIT 5\
            ");
            $stmtSales->execute([':tel' => $like]);
            $sales = $stmtSales->fetchAll(PDO::FETCH_ASSOC);
            if ($sales) {
                $ctxParts[] = 'ÚLTIMAS COMPRAS:';
                foreach ($sales as $s) {
                    $dt = $s['data_venda'] ?? null;
                    $dtStr = '';
                    if ($dt) {
                        try {
                            $dtStr = (new DateTime((string)$dt))->format('d/m/Y');
                        } catch (Throwable $e) {
                            $dtStr = (string)$dt;
                        }
                    }
                    $prod = trim((string)($s['produto'] ?? ''));
                    $sab = trim((string)($s['sabor'] ?? ''));
                    $val = $s['valor_total'] ?? null;
                    $line = '- ' . ($dtStr !== '' ? $dtStr . ' — ' : '') . ($prod !== '' ? $prod : 'Produto');
                    if ($sab !== '') $line .= ' (' . $sab . ')';
                    if ($val !== null && $val !== '') $line .= ' — R$ ' . (string)$val;
                    $ctxParts[] = $line;
                }
            }

            $stmtAgg = $pdo->prepare("SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS total FROM vendas WHERE telefone_cliente LIKE :tel");
            $stmtAgg->execute([':tel' => $like]);
            $agg = $stmtAgg->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($agg) {
                $ctxParts[] = 'HISTÓRICO:';
                $ctxParts[] = '- Compras: ' . (string)($agg['qtd'] ?? '0') . ' | Total gasto: R$ ' . (string)($agg['total'] ?? '0');
            }
        } catch (Throwable $e) {
            // ignore
        }

        // Promoções
        try {
            $stmtPromo = $pdo->query("SELECT nome, preco FROM produtos WHERE promocao = 1 ORDER BY id DESC LIMIT 10");
            $promos = $stmtPromo ? $stmtPromo->fetchAll(PDO::FETCH_ASSOC) : [];
            if ($promos) {
                $ctxParts[] = 'PROMOÇÕES ATIVAS (catálogo):';
                foreach ($promos as $p) {
                    $n = trim((string)($p['nome'] ?? ''));
                    if ($n === '') continue;
                    $price = $p['preco'] ?? null;
                    $ctxParts[] = '- ' . $n . ($price !== null && $price !== '' ? (' — R$ ' . (string)$price) : '');
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        $panelContext = implode("\n", $ctxParts);
    }

} catch (Throwable $e) {
    // Se o painel/DB não estiver disponível, seguimos sem contexto.
    $panelContext = '';
}

$contextBlocks = [];
if ($botSystemPrompt !== '') {
    $contextBlocks[] = "SYSTEM PROMPT (Painel):\n" . $botSystemPrompt;
}
if ($panelContext !== '') {
    $contextBlocks[] = $panelContext;
}

if ($mode !== '') {
    $contextBlocks[] = 'MODO: ' . $mode;
}

if ($contextBlocks) {
    $extraContext = "\n\n" . implode("\n\n", $contextBlocks);

    // Injeta/concatena no primeiro system message para evitar estourar a ordem da conversa.
    if (isset($messages[0]) && is_array($messages[0]) && ($messages[0]['role'] ?? '') === 'system') {
        $messages[0]['content'] = (string)($messages[0]['content'] ?? '') . $extraContext;
    } else {
        array_unshift($messages, [
            'role' => 'system',
            'content' => trim($extraContext),
        ]);
    }
}

// --- Chamada OpenAI ---
$apiKey = trim((string)(getenv('OPENAI_API_KEY') ?: ''));
if ($apiKey === '') {
    $apiKey = trim((string)(getenv('ALABAMA_OPENAI_API_KEY') ?: ''));
}
if ($apiKey === '') {
    $apiKey = trim((string)(getenv('LLM_OPENAI_API_KEY') ?: ''));
}
if ($apiKey === '') {
    respond(['ok' => false, 'error' => 'OPENAI_API_KEY não configurada no servidor.'], 500);
}

$payload = [
    'model' => $model,
    'messages' => array_values($messages),
    'temperature' => $temperature,
    'max_tokens' => $maxTokens,
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => (int)(getenv('LLM_TIMEOUT_SECONDS') ?: 20),
]);

$respBody = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($respBody === false) {
    respond(['ok' => false, 'error' => 'Erro cURL: ' . $curlErr], 502);
}

$decoded = json_decode((string)$respBody, true);
if (!is_array($decoded)) {
    respond(['ok' => false, 'error' => 'Resposta inválida da OpenAI', 'raw' => mb_substr((string)$respBody, 0, 3000)], 502);
}

if ($httpCode < 200 || $httpCode >= 300) {
    $msg = $decoded['error']['message'] ?? ('Erro OpenAI (HTTP ' . $httpCode . ')');
    respond(['ok' => false, 'error' => $msg, 'status' => $httpCode], 502);
}

$text = (string)($decoded['choices'][0]['message']['content'] ?? '');

respond([
    'ok' => true,
    'text' => $text,
    'usage' => $decoded['usage'] ?? null,
]);
