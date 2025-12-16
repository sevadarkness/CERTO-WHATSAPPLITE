<?php
declare(strict_types=1);


/**
 * Atualiza campos de SLA em leads (primeiro_contato_em, ultimo_contato_em)
 * com base no telefone recebido via WhatsApp.
 * É defensivo: se a tabela/colunas não existirem, falha em silêncio.
 */
function whatsapp_atualizar_sla_para_telefone(PDO $pdo, string $telefone): void
{
    if ($telefone === '') {
        return;
    }
    if (!function_exists('wa_only_digits')) {
        return;
    }

    $telDigits = wa_only_digits($telefone);
    if ($telDigits === '') {
        return;
    }

    try {
        // Tenta localizar lead mais recente com telefone compatível
        $sql = "SELECT id, primeiro_contato_em, ultimo_contato_em
                FROM leads
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(telefone_cliente,' ',''),'-',''), '(', ''), ')','') LIKE :tel
                ORDER BY id DESC
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':tel' => '%' . $telDigits . '%']);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lead) {
            return;
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $primeiro = $lead['primeiro_contato_em'] ?? null;
        $ultimo   = $lead['ultimo_contato_em'] ?? null;

        if ($primeiro === null || $primeiro === '0000-00-00 00:00:00') {
            $primeiro = $now;
        }
        $ultimo = $now;

        $stmtUp = (new \RedeAlabama\Repositories\Screens\WhatsappWebhookRepository($pdo))->prepare_1387();
        $stmtUp->execute([
            ':primeiro' => $primeiro,
            ':ultimo'   => $ultimo,
            ':id'       => (int)$lead['id'],
        ]);
    } catch (Throwable $e) {
        // Se a tabela/colunas não existirem ou houver erro, não quebra o fluxo do webhook
        if (function_exists('log_app_event')) {
            log_app_event('whatsapp_sla', 'erro_atualizar_sla', [
                'telefone' => $telefone,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}


/**
 * Endpoint de webhook para WhatsApp Business (Cloud API).
 */


require_once __DIR__ . '/bootstrap_autoload.php';

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/whatsapp_contacts_utils.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/whatsapp_llm_helper.php';

ini_set('display_errors', '0');

/**
 * Valida a assinatura HMAC SHA-256 do webhook do WhatsApp Meta.
 * 
 * @param string $payload Corpo da requisição (raw)
 * @param string $signature Header X-Hub-Signature-256 recebido
 * @param string $appSecret App Secret do Facebook/Meta
 * @return bool
 */
function whatsapp_validate_signature(string $payload, string $signature, string $appSecret): bool
{
    if ($appSecret === '' || $signature === '') {
        return false;
    }
    // Verifica formato 'sha256=' para prevenir bypass com assinaturas malformadas
    if (strpos($signature, 'sha256=') !== 0) {
        return false;
    }
    $expected = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);
    return hash_equals($expected, $signature);
}

function whatsapp_webhook_json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function whatsapp_send_text(string $phoneNumberId, string $accessToken, string $to, string $body): bool
{
    $url = 'https://graph.facebook.com/v17.0/' . rawurlencode($phoneNumberId) . '/messages';

    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'text',
        'text'              => ['body' => $body],
    ];

    $ch = curl_init($url);
    $secFile = __DIR__ . '/app/Support/Security.php';
    if (file_exists($secFile)) {
        require_once $secFile;
    }
    $authHeader = class_exists('Security') ? \Security::build_bearer_header($accessToken) : ('Authorization: Bearer ' . $accessToken);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            $authHeader,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($response === false || $err) {
        log_app_event('whatsapp_bot', 'erro_enviar_whatsapp', [
            'erro'         => $err ?: 'body false',
            'http_code'    => $httpCode,
            'destinatario' => $to,
        ]);
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        log_app_event('whatsapp_bot', 'erro_http_whatsapp', [
            'http_code' => $httpCode,
            'body'      => $response,
        ]);
        return false;
    }

    return true;
}

function whatsapp_find_or_create_conversa(PDO $pdo, string $telefone): int
{
    $sql = "SELECT id FROM whatsapp_conversas WHERE telefone_cliente = :tel ORDER BY id DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':tel' => $telefone]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $id = (int)$row['id'];
        $stmt = (new \RedeAlabama\Repositories\Screens\WhatsappWebhookRepository($pdo))->prepare_4435();
        $stmt->execute([':id' => $id]);
        return $id;
    }

    $stmt = (new \RedeAlabama\Repositories\Screens\WhatsappWebhookRepository($pdo))->prepare_4624();
    $stmt->execute([':tel' => $telefone]);
    return (int)$pdo->lastInsertId();
}

function whatsapp_insert_message(PDO $pdo, int $conversaId, string $direction, string $author, string $conteudo, array $raw = [], ?string $llmModel = null, ?int $tokensTotal = null, ?string $metaMessageId = null): int
{
    // Tenta inserir com meta_message_id; se a coluna não existe, usa query sem ela
    try {
        $sql = "INSERT INTO whatsapp_mensagens (conversa_id, direction, author, conteudo, raw_payload, llm_model, llm_tokens_total, meta_message_id)
                VALUES (:conversa_id, :direction, :author, :conteudo, :raw_payload, :llm_model, :llm_tokens_total, :meta_message_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':conversa_id'      => $conversaId,
            ':direction'        => $direction,
            ':author'           => $author,
            ':conteudo'         => $conteudo,
            ':raw_payload'      => $raw ? json_encode($raw, JSON_UNESCAPED_UNICODE) : null,
            ':llm_model'        => $llmModel,
            ':llm_tokens_total' => $tokensTotal,
            ':meta_message_id'  => $metaMessageId,
        ]);
    } catch (PDOException $e) {
        // Se falhar (ex: coluna meta_message_id não existe), tenta sem ela
        $sql = "INSERT INTO whatsapp_mensagens (conversa_id, direction, author, conteudo, raw_payload, llm_model, llm_tokens_total)
                VALUES (:conversa_id, :direction, :author, :conteudo, :raw_payload, :llm_model, :llm_tokens_total)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':conversa_id'      => $conversaId,
            ':direction'        => $direction,
            ':author'           => $author,
            ':conteudo'         => $conteudo,
            ':raw_payload'      => $raw ? json_encode($raw, JSON_UNESCAPED_UNICODE) : null,
            ':llm_model'        => $llmModel,
            ':llm_tokens_total' => $tokensTotal,
        ]);
    }
    return (int)$pdo->lastInsertId();
}

function whatsapp_build_history_for_llm(PDO $pdo, int $conversaId, int $limit = 10, int $maxChars = 8000): array
{
    $sql = "SELECT direction, author, conteudo 
            FROM whatsapp_mensagens 
            WHERE conversa_id = :id 
            ORDER BY id DESC 
            LIMIT :limite";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $conversaId, PDO::PARAM_INT);
    $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rows = array_reverse($rows);

    $history = [];
    foreach ($rows as $r) {
        $role = 'user';
        if ($r['direction'] === 'out' && $r['author'] === 'bot') {
            $role = 'assistant';
        }
        $history[] = [
            'role'    => $role,
            'content' => $r['conteudo'],
        ];
    }

    // Truncar se exceder limite de caracteres para prevenir crescimento descontrolado
    // Otimizado: evita O(n²) de array_unshift usando construção reversa
    $totalChars = 0;
    $truncated = [];
    $reversed = array_reverse($history);
    
    foreach ($reversed as $msg) {
        $msgLen = strlen($msg['content']);
        if ($totalChars + $msgLen > $maxChars) {
            break;
        }
        $totalChars += $msgLen;
        $truncated[] = $msg;
    }

    return array_reverse($truncated);
}


/**
 * Verifica se uma mensagem já foi processada (idempotência).
 * Previne processamento duplicado de mensagens do WhatsApp.
 * 
 * @param PDO $pdo
 * @param string $messageId ID da mensagem do WhatsApp
 * @return bool true se já foi processada
 */
function whatsapp_message_already_processed(PDO $pdo, string $messageId): bool
{
    if ($messageId === '') {
        return false;
    }
    
    try {
        $sql = "SELECT 1 FROM whatsapp_mensagens WHERE meta_message_id = :mid LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':mid' => $messageId]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        // Se a coluna não existe, ignora a verificação
        if (function_exists('log_app_event')) {
            log_app_event('whatsapp_bot', 'erro_verificar_idempotencia', ['erro' => $e->getMessage()]);
        }
        return false;
    }
}

/**
 * Retorna 'humano' ou 'bot' para uma conversa, com base em atendimentos abertos.
 */
function whatsapp_obter_modo_atendimento(PDO $pdo, int $conversaId): string
{
    try {
        $sql = "SELECT modo FROM whatsapp_atendimentos 
                WHERE conversa_id = :id AND status = 'aberto'
                ORDER BY id DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $conversaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['modo']) && $row['modo'] === 'humano') {
            return 'humano';
        }
    } catch (Throwable $e) {
        log_app_event('whatsapp_bot', 'erro_obter_modo_atendimento', ['erro' => $e->getMessage()]);
    }
    return 'bot';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode        = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? null;
    $verifyToken = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? null;
    $challenge   = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? null;

    $settings    = whatsapp_bot_load_settings();
    $tokenConfig = $settings['verify_token'] ?? null;

    if ($mode === 'subscribe' && $verifyToken && $tokenConfig && hash_equals($tokenConfig, $verifyToken)) {
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo $challenge ?: '';
        exit;
    }

    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (!is_array($data)) {
        log_app_event('whatsapp_bot', 'payload_invalido', ['body' => $body]);
        whatsapp_webhook_json_response(['success' => false, 'error' => 'invalid_payload'], 400);
    }

    $settings = whatsapp_bot_load_settings();
    if (!$settings) {
        log_app_event('whatsapp_bot', 'settings_nao_configurados', []);
        whatsapp_webhook_json_response(['success' => true, 'ignored' => true, 'reason' => 'no_settings'], 200);
    }

    // Valida assinatura HMAC do Meta se app_secret estiver configurado
    $appSecret = $settings['meta_app_secret'] ?? getenv('WHATSAPP_APP_SECRET') ?: getenv('META_APP_SECRET') ?: '';
    if ($appSecret !== '') {
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        if (!whatsapp_validate_signature($body, $signature, $appSecret)) {
            log_app_event('whatsapp_bot', 'assinatura_invalida', ['signature' => $signature]);
            whatsapp_webhook_json_response(['success' => false, 'error' => 'invalid_signature'], 403);
        }
    }

    $phoneNumberId = $settings['phone_number_id'] ?? null;
    $accessToken   = $settings['meta_access_token'] ?? null;

    if (!$phoneNumberId || !$accessToken) {
        log_app_event('whatsapp_bot', 'config_incompleta_whatsapp', []);
        whatsapp_webhook_json_response(['success' => true, 'ignored' => true, 'reason' => 'config_incomplete'], 200);
    }

    $entries = $data['entry'] ?? [];
    foreach ($entries as $entry) {
        $changes = $entry['changes'] ?? [];
        foreach ($changes as $change) {
            $value    = $change['value'] ?? [];
            $messages = $value['messages'] ?? [];

            foreach ($messages as $msg) {
                if (($msg['type'] ?? '') !== 'text') {
                    continue;
                }

                $messageId = $msg['id'] ?? '';
                $from  = $msg['from'] ?? null;
                $texto = $msg['text']['body'] ?? '';

                // Verifica idempotência para prevenir processamento duplicado
                if ($messageId !== '' && whatsapp_message_already_processed($pdo, $messageId)) {
                    continue;
                }
                
                // Atualiza SLA de lead com base no telefone de origem
                whatsapp_atualizar_sla_para_telefone($pdo, $from);

                if (!$from || $texto === '') {
                    continue;
                }

                try {
                    $pdo->beginTransaction();

                    $conversaId = whatsapp_find_or_create_conversa($pdo, $from);

                    $modoAtendimento = whatsapp_obter_modo_atendimento($pdo, $conversaId);
                    if ($modoAtendimento === 'humano') {
                        // Apenas registra mensagem; atendimento é do humano, não do bot.
                        whatsapp_insert_message($pdo, $conversaId, 'in', 'cliente', $texto, $msg, null, null, $messageId);
                        $pdo->commit();
                        continue; // Continua para próxima mensagem
                    }

                    whatsapp_insert_message($pdo, $conversaId, 'in', 'cliente', $texto, $msg, null, null, $messageId);

                    $history   = whatsapp_build_history_for_llm($pdo, $conversaId, 10);
                    $llmResult = whatsapp_bot_chamar_llm($texto, $history, $settings);
                    $resposta  = $llmResult['ok'] ? ($llmResult['resposta'] ?? '') : '';

                    if ($resposta !== '') {
                        whatsapp_insert_message($pdo, $conversaId, 'out', 'bot', $resposta, [], $settings['llm_model'] ?? null, null, null);
                        whatsapp_send_text($phoneNumberId, $accessToken, $from, $resposta);
                    }

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    log_app_event('whatsapp_bot', 'erro_processar_mensagem', ['erro' => $e->getMessage()]);
                }
            }
        }
    }

    whatsapp_webhook_json_response(['success' => true], 200);
}

http_response_code(405);
echo 'Method Not Allowed';
