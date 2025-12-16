<?php
/**
 * Logger estruturado em JSON (V27+).
 *
 * - Escreve cada evento em logs/app.log
 * - Também escreve em logs/{canal}.log para facilitar auditoria por módulo
 * - Inclui automaticamente o request_id (quando disponível) no contexto
 *
 * Uso:
 *      log_app_event('auth', 'login_sucesso', ['usuario_id' => 123]);
 */

declare(strict_types=1);

if (!defined('ALABAMA_LOG_DIR')) {
    define('ALABAMA_LOG_DIR', __DIR__ . '/logs');
}

/**
 * Rotaciona arquivo de log se exceder tamanho máximo.
 * 
 * @param string $file Caminho do arquivo de log
 * @param int $maxSizeBytes Tamanho máximo em bytes (default: 10MB)
 * @param int $maxFiles Número máximo de arquivos rotacionados (default: 5)
 */
function alabama_rotate_log_if_needed(string $file, int $maxSizeBytes = 10485760, int $maxFiles = 5): void
{
    if (!is_file($file)) {
        return;
    }
    
    $size = @filesize($file);
    if ($size === false || $size < $maxSizeBytes) {
        return;
    }
    
    // Rotacionar: app.log -> app.log.1 -> app.log.2 -> ...
    for ($i = $maxFiles - 1; $i >= 1; $i--) {
        $old = $file . '.' . $i;
        $new = $file . '.' . ($i + 1);
        if (is_file($old)) {
            if ($i + 1 >= $maxFiles) {
                @unlink($old); // Remove o mais antigo
            } else {
                @rename($old, $new);
            }
        }
    }
    
    // Rotacionar o arquivo atual
    @rename($file, $file . '.1');
}

// Tenta carregar o contexto de requisição (para obter request_id), se existir
$requestContextFile = __DIR__ . '/app/Support/RequestContext.php';
if (is_file($requestContextFile)) {
    require_once $requestContextFile;
}

/**
 * Registra um evento de aplicação em formato estruturado (JSON).
 *
 * @param string $channel Canal/módulo (ex.: "auth", "ia", "automation")
 * @param string $event   Evento (ex.: "login_sucesso", "ia_sugestao_gerada")
 * @param array  $context Dados adicionais (IDs, payloads resumidos, etc.)
 */
function log_app_event(string $channel, string $event, array $context = []): void
{
    // Enriquecimento padrão de contexto
    $enriched = $context;

    // Anexa request_id automaticamente, caso exista
    if (!array_key_exists('request_id', $enriched)) {
        if (defined('ALABAMA_REQUEST_ID')) {
            $enriched['request_id'] = ALABAMA_REQUEST_ID;
        } elseif (class_exists('\RedeAlabama\Support\RequestContext')) {
            $enriched['request_id'] = \RedeAlabama\Support\RequestContext::id();
        }
    }

    // Informações de ambiente úteis em troubleshooting
    if (!array_key_exists('ip', $enriched) && isset($_SERVER['REMOTE_ADDR'])) {
        $enriched['ip'] = $_SERVER['REMOTE_ADDR'];
    }

    if (!array_key_exists('user_agent', $enriched) && isset($_SERVER['HTTP_USER_AGENT'])) {
        $enriched['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    }

    if (!array_key_exists('usuario_id', $enriched) && isset($_SESSION['usuario_id'])) {
        $enriched['usuario_id'] = $_SESSION['usuario_id'];
    }

    // Campos estilo OpenTelemetry (trace/span)
    $traceId = $enriched['otel.trace_id'] ?? null;
    if ($traceId === null) {
        if (defined('ALABAMA_REQUEST_ID')) {
            $traceId = ALABAMA_REQUEST_ID;
        } elseif (class_exists('\RedeAlabama\Support\RequestContext')) {
            $traceId = \RedeAlabama\Support\RequestContext::id();
        }
    }
    if ($traceId !== null) {
        $enriched['otel.trace_id'] = $traceId;
    }
    if (!isset($enriched['otel.span_id'])) {
        $enriched['otel.span_id'] = bin2hex(random_bytes(8));
    }
    if (!isset($enriched['otel.service_name'])) {
        $enriched['otel.service_name'] = 'redealabama-panel';
    }

    $record = [
        'ts'      => (new DateTimeImmutable('now'))->format(DATE_ATOM),
        'channel' => $channel,
        'event'   => $event,
        'context' => $enriched,
    ];

    // Audit log empresarial com trilha criptográfica (apenas canal "audit")
    if ($channel === 'audit') {
        $secret = getenv('ALABAMA_AUDIT_SECRET') ?: '';
        $auditFile = ALABAMA_LOG_DIR . '/audit.log';
        $prevHash = null;

        if (is_file($auditFile)) {
            $lines = @file($auditFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines) && !empty($lines)) {
                $last = trim($lines[count($lines) - 1]);
                $lastDecoded = json_decode($last, true);
                if (is_array($lastDecoded) && isset($lastDecoded['chain_hash'])) {
                    $prevHash = (string) $lastDecoded['chain_hash'];
                }
            }
        }

        $record['chain_prev'] = $prevHash;
        if ($secret !== '') {
            $record['chain_hash'] = hash_hmac(
                'sha256',
                ($prevHash ?? '') . '|' . json_encode($record, JSON_UNESCAPED_UNICODE),
                $secret
            );
        } else {
            $record['chain_hash'] = hash(
                'sha256',
                ($prevHash ?? '') . '|' . json_encode($record, JSON_UNESCAPED_UNICODE)
            );
        }
    }

    $line = json_encode($record, JSON_UNESCAPED_UNICODE) . PHP_EOL;

    if (!is_dir(ALABAMA_LOG_DIR)) {
        @mkdir(ALABAMA_LOG_DIR, 0775, true);
    }

    // Arquivo geral de app
    $files = [ALABAMA_LOG_DIR . '/app.log'];

    // Arquivo específico do módulo (ex.: logs/auth.log)
    $safeChannel = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $channel);
    if ($safeChannel !== '') {
        $files[] = ALABAMA_LOG_DIR . '/' . $safeChannel . '.log';
    }

    foreach ($files as $file) {
        alabama_rotate_log_if_needed($file);
        @file_put_contents($file, $line, FILE_APPEND);
    }

}


/**
 * Helper de alto nível para auditoria empresarial.
 *
 * Exemplo:
 *   log_audit_event('usuario_create', 'usuario', 123, ['perfil' => 'Administrador']);
 */
function log_audit_event(string $action, string $entityType, $entityId = null, array $details = []): void
{
    if (!isset($details['entity_type'])) {
        $details['entity_type'] = $entityType;
    }
    if (!isset($details['entity_id'])) {
        $details['entity_id'] = $entityId;
    }
    if (!isset($details['usuario_id']) && isset($_SESSION['usuario_id'])) {
        $details['usuario_id'] = $_SESSION['usuario_id'];
    }

    log_app_event('audit', $action, $details);
}



/**
 * Exporta evento para um collector OpenTelemetry (quando configurado).
 * Usa ALABAMA_OTEL_EXPORTER_URL, formato JSON genérico.
 */
function alabama_otel_export(array $record): void
{
    $url = getenv('ALABAMA_OTEL_EXPORTER_URL') ?: '';
    if ($url === '') {
        return;
    }

    $payload = json_encode($record, JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return;
    }

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 0.2,
        ],
    ];

    try {
        @file_get_contents($url, false, stream_context_create($opts));
    } catch (\Throwable $e) {
        // Melhor esforço: falha silenciosa para não impactar o fluxo principal
    }
}

