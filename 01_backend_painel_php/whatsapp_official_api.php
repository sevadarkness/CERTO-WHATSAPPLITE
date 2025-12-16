<?php
/**
 * whatsapp_official_api.php
 *
 * Configuração e helper para envio de mensagens pela
 * API oficial do WhatsApp (WhatsApp Cloud API - Meta).
 *
 * IMPORTANTE:
 * - NÃO deixe tokens sensíveis em código-fonte versionado.
 * - Prefira variáveis de ambiente ou um cofre de segredos.
 */

declare(strict_types=1);

$WHATSAPP_API = [
    // Endpoint base da Graph API (ajuste a versão se necessário).
    'base_url'        => getenv('WHATSAPP_API_BASE_URL') ?: 'https://graph.facebook.com/v21.0',
    // ID do número de telefone configurado no WhatsApp Cloud API.
    'phone_number_id' => getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '',
    // Token de acesso da API (long-lived ou obtido via processo seguro).
    'access_token'    => getenv('WHATSAPP_API_TOKEN') ?: '',
    // Timeout em segundos para chamadas HTTP.
    'timeout'         => 15,
];

/**
 * Envia uma mensagem de texto simples via WhatsApp Cloud API.
 *
 * @param string $toPhoneE164  Telefone no formato E.164 (ex.: +5511999999999).
 * @param string $text         Corpo da mensagem.
 * @param array  $meta         Meta-informações opcionais para log (não usadas aqui diretamente).
 *
 * @return array [
 *   'ok'        => bool,
 *   'status'    => int|null,
 *   'error'     => string|null,
 *   'response'  => array|string|null,
 * ]
 */
function whatsapp_api_send_text(string $toPhoneE164, string $text, array $meta = []): array
{
    global $WHATSAPP_API;

    $phoneId = $WHATSAPP_API['phone_number_id'] ?? '';
    $token   = $WHATSAPP_API['access_token'] ?? '';
    $baseUrl = rtrim((string)($WHATSAPP_API['base_url'] ?? ''), '/');

    if ($phoneId === '' || $token === '' || $baseUrl === '') {
        return [
            'ok'       => false,
            'status'   => null,
            'error'    => 'Configuração incompleta da WhatsApp API (ver variáveis de ambiente).',
            'response' => null,
        ];
    }

    $url = $baseUrl . '/' . rawurlencode($phoneId) . '/messages';

    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $toPhoneE164,
        'type'              => 'text',
        'text'              => [
            'preview_url' => false,
            'body'        => $text,
        ],
    ];

    $maxRetries = (int)(getenv('WHATSAPP_API_MAX_RETRIES') ?: 3);
    $retryDelayMs = (int)(getenv('WHATSAPP_API_RETRY_DELAY_MS') ?: 1000);
    
    $lastError = null;
    $lastStatus = null;
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => (function() use ($token) {
                $sec = __DIR__ . '/app/Support/Security.php';
                if (file_exists($sec)) {
                    require_once $sec;
                }
                $auth = class_exists('Security') ? \Security::build_bearer_header($token) : ('Authorization: Bearer ' . $token);
                return [
                    'Content-Type: application/json',
                    $auth,
                ];
            })(),
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => (int)($WHATSAPP_API['timeout'] ?? 15),
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            $lastError = 'Erro cURL: ' . $curlErr;
            $lastStatus = $httpCode ?: null;
            
            // Retry em caso de erro de conexão
            if ($attempt < $maxRetries) {
                usleep($retryDelayMs * 1000);
                continue;
            }
            
            return [
                'ok'       => false,
                'status'   => $lastStatus,
                'error'    => $lastError,
                'response' => null,
                'attempts' => $attempt,
            ];
        }

        $decoded = json_decode($body, true);
        
        // Sucesso
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'ok'       => true,
                'status'   => $httpCode,
                'error'    => null,
                'response' => is_array($decoded) ? $decoded : $body,
                'attempts' => $attempt,
            ];
        }
        
        // Erros que não devem ser retried (4xx exceto 429)
        if ($httpCode >= 400 && $httpCode < 500 && $httpCode !== 429) {
            return [
                'ok'       => false,
                'status'   => $httpCode,
                'error'    => is_array($decoded) ? ($decoded['error']['message'] ?? 'Erro da API WhatsApp.') : 'Resposta não-JSON da API.',
                'response' => is_array($decoded) ? $decoded : $body,
                'attempts' => $attempt,
            ];
        }
        
        // Erros 5xx ou 429 - retry
        $lastError = is_array($decoded) ? ($decoded['error']['message'] ?? 'Erro HTTP ' . $httpCode) : 'Erro HTTP ' . $httpCode;
        $lastStatus = $httpCode;
        
        if ($attempt < $maxRetries) {
            // Backoff exponencial para 429
            $delay = $httpCode === 429 ? $retryDelayMs * $attempt : $retryDelayMs;
            usleep($delay * 1000);
        }
    }
    
    return [
        'ok'       => false,
        'status'   => $lastStatus,
        'error'    => $lastError ?? 'Falha após ' . $maxRetries . ' tentativas.',
        'response' => null,
        'attempts' => $maxRetries,
    ];
}

/**
 * Normaliza um telefone para o formato E.164 de forma simples.
 * Esta função é propositalmente conservadora; ajuste conforme sua realidade.
 *
 * @param string $raw            Telefone cru vindo do banco.
 * @param string $defaultCountry DDI padrão (ex.: '55' para Brasil).
 * @return string                Telefone em E.164 (ex.: +5511999999999) ou string vazia em caso de falha.
 */
function whatsapp_normalize_phone_e164(string $raw, string $defaultCountry = '55'): string
{
    $digits = preg_replace('/\D+/', '', $raw ?? '');
    if ($digits === '') {
        return '';
    }

    // Remove zeros à esquerda
    $digits = ltrim($digits, '0');

    // Se não começar com DDI e for um número com tamanho típico nacional, prefixa DDI.
    if (strpos($digits, $defaultCountry) !== 0 && strlen($digits) <= 11) {
        $digits = $defaultCountry . $digits;
    }

    return '+' . $digits;
}
?>
