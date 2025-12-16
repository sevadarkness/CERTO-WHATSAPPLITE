<?php
/**
 * Exporta logs.
 *
 * Correções aplicadas:
 * - Evita fatal error quando Dompdf não está presente
 * - Fallback para CSV quando Dompdf não existir
 * - Usa paths absolutos (__DIR__) para includes
 */

declare(strict_types=1);

require_once __DIR__ . '/../auth/db.php';

$pdo = db();
$stmt = $pdo->query('SELECT * FROM logs ORDER BY momento DESC LIMIT 100');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dompdfAutoload = __DIR__ . '/dompdf/autoload.inc.php';

if (is_file($dompdfAutoload)) {
    require_once $dompdfAutoload;
    
    $html = "<h2>Logs</h2><table border='1' cellpadding='5' cellspacing='0'><tr><th>Email</th><th>Ação</th><th>Momento</th></tr>";
    foreach ($rows as $row) {
        $email = htmlspecialchars((string)($row['usuario_email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $acao  = htmlspecialchars((string)($row['acao'] ?? ''), ENT_QUOTES, 'UTF-8');
        $mom   = htmlspecialchars((string)($row['momento'] ?? ''), ENT_QUOTES, 'UTF-8');
        $html .= "<tr><td>{$email}</td><td>{$acao}</td><td>{$mom}</td></tr>";
    }
    $html .= '</table>';

    $dompdf = new Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->render();
    $dompdf->stream('logs.pdf');
    exit;
}

// Fallback: CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="logs.csv"');

$out = fopen('php://output', 'w');

fputcsv($out, ['usuario_email', 'acao', 'momento']);
foreach ($rows as $row) {
    fputcsv($out, [
        (string)($row['usuario_email'] ?? ''),
        (string)($row['acao'] ?? ''),
        (string)($row['momento'] ?? ''),
    ]);
}

fclose($out);
