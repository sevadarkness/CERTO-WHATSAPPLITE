<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}



/**
 * Envio manual de mensagem WhatsApp via painel,
 * usando a API oficial (WhatsApp Cloud API).
 *
 * Este endpoint é pensado para ser chamado via AJAX (JSON)
 * a partir da tela de conversas do Bot WhatsApp (whatsapp_bot_console.php).
 */

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente', 'Vendedor']);

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/whatsapp_official_api.php';

header('Content-Type: application/json; charset=utf-8');

if (php_sapi_name() === 'cli') {
    echo json_encode(['ok' => false, 'error' => 'Endpoint HTTP apenas.']);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido. Use POST.']);
    exit;
}

// Valida CSRF (suporta JSON e form-data)
csrf_require();

// Lê dados JSON ou POST
$raw = file_get_contents('php://input') ?: '';
$data = [];
if ($raw !== '') {
    $tmp = json_decode($raw, true);
    if (is_array($tmp)) {
        $data = $tmp;
    }
}
if (!$data) {
    $data = $_POST;
}

$telefone   = trim((string)($data['telefone'] ?? ''));
$conversaId = isset($data['conversa_id']) ? (int)$data['conversa_id'] : null;
$mensagem   = trim((string)($data['mensagem'] ?? ''));

if ($mensagem === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Mensagem vazia.']);
    exit;
}

try {
    // Se veio conversa_id mas não veio telefone, busca no banco
    if ($conversaId && $telefone === '') {
        $stmt = (new \RedeAlabama\Repositories\Screens\WhatsappManualSendRepository($pdo))->prepare_1674();
        $stmt->execute([':id' => $conversaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Conversa não encontrada para envio manual.']);
            exit;
        }
        $telefone = (string)$row['telefone_cliente'];
    }

    if ($telefone === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Telefone não informado.']);
        exit;
    }

    // Normaliza telefone para E.164 (+55...)
    $toE164 = whatsapp_normalize_phone_e164($telefone, '55');
    if ($toE164 === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Telefone inválido para envio WhatsApp.']);
        exit;
    }

    $usuarioId   = $_SESSION['usuario_id'] ?? null;
    // Padronização: o login do painel seta "nome_usuario".
    // Mantemos compatibilidade com chaves antigas ("usuario_nome") caso existam.
    $usuarioNome = $_SESSION['nome_usuario'] ?? ($_SESSION['usuario_nome'] ?? null);

    // Envia via API oficial
    $result = whatsapp_api_send_text($toE164, $mensagem, [
        'mode'        => 'manual_panel_send',
        'user_id'     => $usuarioId,
        'user_name'   => $usuarioNome,
        'conversa_id' => $conversaId,
    ]);

    if (!$result['ok']) {
        log_app_event('whatsapp_manual_send', 'erro_envio', [
            'telefone' => $telefone,
            'to_e164'  => $toE164,
            'error'    => $result['error'] ?? null,
            'status'   => $result['status'] ?? null,
        ]);

        http_response_code(500);
        echo json_encode([
            'ok'    => false,
            'error' => 'Falha ao enviar pela API WhatsApp: ' . ($result['error'] ?? 'erro desconhecido'),
        ]);
        exit;
    }

    // Garante que exista uma conversa se ainda não tinha
    if (!$conversaId) {
        $stmt = (new \RedeAlabama\Repositories\Screens\WhatsappManualSendRepository($pdo))->prepare_3525();
        $stmt->execute([':tel' => $telefone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $conversaId = (int)$row['id'];
        } else {
            $stmt = (new \RedeAlabama\Repositories\Screens\WhatsappManualSendRepository($pdo))->prepare_3827();
            $stmt->execute([
                ':tel'    => $telefone,
                ':status' => 'ativa',
            ]);
            $conversaId = (int)$pdo->lastInsertId();
        }
    }

    // Registra mensagem em whatsapp_mensagens
    $rawPayload = $result['response'] ?? null;

    $sql = 'INSERT INTO whatsapp_mensagens (conversa_id, direction, author, conteudo, raw_payload, llm_model, llm_tokens_total)
            VALUES (:conversa_id, :direction, :author, :conteudo, :raw_payload, :llm_model, :llm_tokens_total)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':conversa_id'      => $conversaId,
        ':direction'        => 'out',
        ':author'           => $usuarioNome ? ('painel:' . $usuarioNome) : 'painel',
        ':conteudo'         => $mensagem,
        ':raw_payload'      => $rawPayload ? json_encode($rawPayload, JSON_UNESCAPED_UNICODE) : null,
        ':llm_model'        => null,
        ':llm_tokens_total' => null,
    ]);

    // Atualiza timestamps da conversa
    $stmt = (new \RedeAlabama\Repositories\Screens\WhatsappManualSendRepository($pdo))->prepare_5054();
    $stmt->execute([':id' => $conversaId]);

    log_app_event('whatsapp_manual_send', 'sucesso', [
        'telefone'    => $telefone,
        'to_e164'     => $toE164,
        'conversa_id' => $conversaId,
    ]);

    echo json_encode([
        'ok'          => true,
        'conversa_id' => $conversaId,
        'to'          => $toE164,
    ]);
    exit;

} catch (Throwable $e) {
    log_app_event('whatsapp_manual_send', 'excecao', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erro interno ao enviar mensagem.']);
    exit;
}
