<?php
/**
 * Wrapper/atalho gerado automaticamente na reorganização do pacote.
 *
 * IMPORTANTE (web):
 *   Estas telas/scripts dependem do contexto do backend (RBAC, links e assets relativos).
 *   Por isso, em requisições HTTP este wrapper REDIRECIONA para o arquivo real no backend,
 *   garantindo que rotas, redirects e assets funcionem corretamente.
 *
 * IMPORTANTE (CLI):
 *   Em CLI, apenas carrega o arquivo do backend (útil para debug/execução local).
 */

$targetFs  = __DIR__ . '/../../01_backend_painel_php/export_llm_dataset.php';
$targetUrl = '../../01_backend_painel_php/export_llm_dataset.php';

if (!is_file($targetFs)) {
    http_response_code(500);
    echo "Arquivo alvo não encontrado: " . $targetFs;
    exit;
}

// Em ambiente web, redireciona (preservando querystring e POST quando possível)
if (PHP_SAPI !== 'cli') {
    // Ajusta URL alvo de acordo com o DocumentRoot do servidor:
    // - Se o backend é o próprio DocumentRoot -> /arquivo.php
    // - Se o backend é subdiretório do DocumentRoot -> /01_backend_painel_php/arquivo.php
    $docRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
    $backendReal = realpath(dirname($targetFs)) ?: '';
    if ($docRoot !== '' && $backendReal !== '' && str_starts_with($backendReal, $docRoot)) {
        $rel = trim((string)substr($backendReal, strlen($docRoot)), '/');
        $targetUrl = '/' . ($rel !== '' ? ($rel . '/') : '') . basename($targetFs);
    }

    $qs = (string)($_SERVER['QUERY_STRING'] ?? '');
    $loc = $targetUrl . ($qs !== '' ? ('?' . $qs) : '');

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $code = ($method === 'GET' || $method === 'HEAD') ? 302 : 307;

    header('Location: ' . $loc, true, $code);
    exit;
}

// CLI: tenta executar a partir do diretório do backend (evita problemas com cwd)
$backendDir = realpath(__DIR__ . '/../../01_backend_painel_php');
if ($backendDir) {
    @chdir($backendDir);
}

require_once $targetFs;
