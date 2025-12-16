<?php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/logger.php';

require_role(['Administrador']);

/**
 * Exportação do Audit Log em formato HTML pronto para PDF.
 *
 * Opcionalmente aceita filtros via GET:
 *  - from=YYYY-MM-DD
 *  - to=YYYY-MM-DD
 */
$from = isset($_GET['from']) && $_GET['from'] !== '' ? (string) $_GET['from'] : null;
$to   = isset($_GET['to']) && $_GET['to'] !== '' ? (string) $_GET['to'] : null;

$filters = [
    'from' => $from,
    'to'   => $to,
];

$logFile = ALABAMA_LOG_DIR . '/audit.log';
$rows = [];

if (is_file($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $lines = array_reverse($lines);

    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            continue;
        }

        $ts = $decoded['ts'] ?? null;
        if ($ts !== null && ($from !== null || $to !== null)) {
            // Considera 'ts' como string ISO ou similar. Filtro simples por prefixo de data.
            $datePart = substr((string) $ts, 0, 10); // YYYY-MM-DD
            if ($from !== null && $datePart < $from) {
                continue;
            }
            if ($to !== null && $datePart > $to) {
                continue;
            }
        }

        $rows[] = $decoded;

        // Limite de segurança para PDF: evita arquivos gigantescos
        if (count($rows) >= 500) {
            break;
        }
    }
}

$generatedAt = date('Y-m-d H:i:s');

// Cabeçalho amigável para impressão / exportação como PDF
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    $filename = 'audit_log_' . date('Ymd_His') . '.html';
    header('Content-Disposition: inline; filename="' . $filename . '"');
}

$template = __DIR__ . '/templates/pdf/audit_log_template.php';
if (is_file($template)) {
    /** @var array<int,array<string,mixed>> $rows */
    /** @var string $generatedAt */
    /** @var array<string,?string> $filters */
    include $template;
} else {
    echo '<p>Template de PDF não encontrado.</p>';
}

