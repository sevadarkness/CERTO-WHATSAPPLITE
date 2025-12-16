<?php
/**
 * relatorios_ia_export.php
 *
 * Exporta um resumo agregado do uso de IA (LLM) em formato CSV ou JSON,
 * a partir da tabela llm_logs. É pensado para análise de custo, tokens e latência.
 *
 * Parâmetros GET:
 *   - format: csv (padrão) ou json
 *   - days: período em dias (1–90, padrão 7)
 */

declare(strict_types=1);

require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../rbac.php';
require_role(['Administrador', 'Gerente']);
require_once __DIR__ . '/../db_config.php';

$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
if ($days < 1) {
    $days = 1;
}
if ($days > 90) {
    $days = 90;
}

$format = isset($_GET['format']) ? strtolower((string)$_GET['format']) : 'csv';
if ($format !== 'json') {
    $format = 'csv';
}

// Consulta agregada baseada em llm_analytics_dashboard.php
$sql = "SELECT 
            DATE(created_at) AS dia,
            provider,
            model,
            SUM(COALESCE(total_tokens, 0)) AS tokens_totais,
            AVG(COALESCE(latency_ms, 0)) AS latency_media_ms
        FROM llm_logs
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY DATE(created_at), provider, model
        ORDER BY dia DESC, provider, model";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':days', $days, PDO::PARAM_INT);

try {
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao carregar dados de llm_logs',
        'exception' => $e->getMessage(),
    ]);
    exit;
}

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => false,
        'days' => $days,
        'rows' => $rows,
    ]);
    exit;
}

// CSV
$filename = 'relatorio_ia_' . $days . 'd_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
if ($out === false) {
    // fallback simples
    echo "dia,provider,model,tokens_totais,latency_media_ms\n";
    foreach ($rows as $r) {
        echo $r['dia'] . ',' .
             $r['provider'] . ',' .
             $r['model'] . ',' .
             (int)$r['tokens_totais'] . ',' .
             (int)$r['latency_media_ms'] . "\n";
    }
    exit;
}

// Cabeçalho
fputcsv($out, ['dia', 'provider', 'model', 'tokens_totais', 'latency_media_ms']);

// Linhas
foreach ($rows as $r) {
    fputcsv($out, [
        $r['dia'],
        $r['provider'],
        $r['model'],
        (int)$r['tokens_totais'],
        (int)$r['latency_media_ms'],
    ]);
}

fclose($out);
exit;
