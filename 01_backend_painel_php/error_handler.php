<?php
/**
 * Handler global de erros/exceções.
 * - Loga tudo via log_app_event()
 * - Opcionalmente envia para Sentry se SENTRY_DSN estiver configurado.
 */

declare(strict_types=1);

require_once __DIR__ . '/logger.php';

/**
 * Envia exceção para Sentry, se SENTRY_DSN estiver configurado.
 * Implementação mínima, não bloqueante.
 */
function alabama_send_to_sentry(Throwable $e): void
{
    $dsn = getenv('SENTRY_DSN');
    if (!$dsn) {
        return;
    }

    // Este código é propositalmente simples; em produção recomenda-se usar o SDK oficial.
    // Aqui apenas montamos um payload básico de evento.
    $data = [
        'message' => $e->getMessage(),
        'level'   => 'error',
        'extra'   => [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ],
        'timestamp' => time(),
    ];

    $json = json_encode($data, JSON_UNESCAPED_UNICODE);

    // Tentativa best-effort usando cURL, sem quebrar a aplicação em caso de falha.
    if (function_exists('curl_init')) {
        $ch = curl_init($dsn);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        @curl_exec($ch);
        @curl_close($ch);
    }
}

/**
 * Tratador de exceções não capturadas.
 */
function alabama_handle_exception(Throwable $e): void
{
    log_app_event('error', 'uncaught_exception', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ]);

    alabama_send_to_sentry($e);

    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo 'Erro interno. Nossa equipe já foi notificada.';
    }
}

/**
 * Tratador de erros PHP, converte em exceção.
 */
function alabama_handle_error(int $errno, string $errstr, string $errfile, int $errline): bool
{
    // Erros suprimidos com @ não devem ser tratados
    if (error_reporting() === 0) {
        return false;
    }

    $e = new ErrorException($errstr, 0, $errno, $errfile, $errline);
    alabama_handle_exception($e);
    return true;
}

set_error_handler('alabama_handle_error');
set_exception_handler('alabama_handle_exception');
