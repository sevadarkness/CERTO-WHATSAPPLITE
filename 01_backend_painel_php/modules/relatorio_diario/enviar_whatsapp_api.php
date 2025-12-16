<?php
/**
 * Integração WhatsApp Cloud API (módulo relatório diário).
 *
 * Correções aplicadas:
 * - Removido código fora da tag PHP (que quebrava includes/headers)
 * - Removida execução automática de push notification ao incluir este arquivo
 * - Substituído placeholder de token/phone_id por variáveis de ambiente (.env)
 *
 * Variáveis esperadas:
 *  - WA_API_VERSION (padrão: v16.0)
 *  - WA_PHONE_ID
 *  - WA_ACCESS_TOKEN
 *  - WA_DEFAULT_TO (opcional)
 */

declare(strict_types=1);

require_once __DIR__ . '/../_support/env.php';
rede_alabama_load_env();

function enviarParaWhatsApp(string $mensagem, ?string $numero = null): string
{
    $version = getenv('WA_API_VERSION') ?: 'v16.0';
    $phoneId = getenv('WA_PHONE_ID') ?: '';
    $token   = getenv('WA_ACCESS_TOKEN') ?: '';

    $to = $numero ?: (getenv('WA_DEFAULT_TO') ?: '');

    if ($phoneId === '' || $token === '' || $to === '') {
        return json_encode([
            'ok'      => false,
            'error'   => 'whatsapp_not_configured',
            'message' => 'Configure WA_PHONE_ID, WA_ACCESS_TOKEN e WA_DEFAULT_TO (ou informe o número).',
        ], JSON_UNESCAPED_UNICODE);
    }

    $url = sprintf('https://graph.facebook.com/%s/%s/messages', $version, $phoneId);

    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'text',
        'text'              => ['preview_url' => false, 'body' => $mensagem],
    ];

    if (!function_exists('curl_init')) {
        return json_encode([
            'ok'      => false,
            'error'   => 'curl_missing',
            'message' => 'Extensão cURL não encontrada no PHP.',
        ], JSON_UNESCAPED_UNICODE);
    }

    $ch = curl_init($url);
    // Use centralized Security helper for header construction when available
    $secPath = __DIR__ . '/../../app/Support/Security.php';
    if (file_exists($secPath)) {
        require_once $secPath;
    }
    $authHeader = class_exists('Security') ? \Security::build_bearer_header($token) : ('Authorization: Bearer ' . $token);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            $authHeader,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    $errstr   = curl_error($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $errno !== 0) {
        return json_encode([
            'ok'        => false,
            'error'     => 'curl_error',
            'errno'     => $errno,
            'message'   => $errstr,
            'http_code' => $status,
        ], JSON_UNESCAPED_UNICODE);
    }

    // Resposta bruta do Graph API
    return $response;
}

function enviarCartaoFidelidadeCliente(string $numero, string $nome, int $compras, array $historico = []): string
{
    $gerador = __DIR__ . '/../cartao_fidelidade/gerar_fidelidade.php';
    if (is_file($gerador)) {
        include_once $gerador;
    }

    if (!function_exists('gerarMensagemCliente')) {
        $mensagem = "Olá {$nome}! Você tem {$compras} compras registradas.";
        return enviarParaWhatsApp($mensagem, $numero);
    }

    $mensagem = gerarMensagemCliente($nome, $compras, $historico);
    return enviarParaWhatsApp($mensagem, $numero);
}

/**
 * Push notification é opcional.
 * Este arquivo NÃO dispara push automaticamente.
 */
function redealabama_send_push_to_vendedor(string $vendedorId, string $clienteNome, string $clienteTelefone): array
{
    $pushFile = __DIR__ . '/../../notifications/send_push_fcm.php';
    if (!is_file($pushFile)) {
        return ['ok' => false, 'error' => 'push_module_missing'];
    }

    include_once $pushFile;

    if (!function_exists('sendNotificationToVendedor')) {
        return ['ok' => false, 'error' => 'push_function_missing'];
    }

    $result = sendNotificationToVendedor($vendedorId, $clienteNome, $clienteTelefone);
    return ['ok' => true, 'result' => $result];
}
