<?php
/**
 * api_remarketing_stats.php (legacy)
 *
 * Mantém compatibilidade com o bridge usado por RemarketingStatsService (API v2).
 * Retorna uma série temporal simplificada.
 *
 * Parâmetros (GET):
 *   - dias: quantidade de dias (1-180, padrão 30)
 *
 * Contrato mínimo:
 *   { "success": true, "series": [{dia,disparos,entregues,respostas}, ...] }
 */

declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json; charset=utf-8');

$dias = isset($_GET['dias']) ? (int)$_GET['dias'] : 30;
if ($dias < 1) $dias = 1;
if ($dias > 180) $dias = 180;

// Inicializa série com zeros para o range solicitado
$series = [];
$today = new DateTimeImmutable('today');
for ($i = $dias - 1; $i >= 0; $i--) {
    $d = $today->sub(new DateInterval('P' . $i . 'D'));
    $series[$d->format('Y-m-d')] = [
        'dia' => $d->format('Y-m-d'),
        'disparos' => 0,
        'entregues' => 0,
        'respostas' => 0,
    ];
}

// Tentativa best-effort: aproximar "disparos" e "respostas" com base em whatsapp_mensagens (se existir)
try {
    $pdo->query('SELECT 1 FROM whatsapp_mensagens LIMIT 1');

    $sql = "
        SELECT DATE(created_at) AS dia,
               SUM(CASE WHEN LOWER(COALESCE(direcao,'')) IN ('saida','out','outgoing','enviada','enviado','sent') THEN 1 ELSE 0 END) AS disparos,
               SUM(CASE WHEN LOWER(COALESCE(direcao,'')) IN ('entrada','in','incoming','recebida','recebido','received') THEN 1 ELSE 0 END) AS respostas
        FROM whatsapp_mensagens
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)
        GROUP BY DATE(created_at)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as $row) {
        $key = (string)($row['dia'] ?? '');
        if ($key !== '' && isset($series[$key])) {
            $d = (int)($row['disparos'] ?? 0);
            $r = (int)($row['respostas'] ?? 0);
            $series[$key]['disparos'] = $d;
            // Sem confirmação real de entrega, usamos disparos como proxy.
            $series[$key]['entregues'] = $d;
            $series[$key]['respostas'] = $r;
        }
    }
} catch (Throwable $e) {
    // Ignora e mantém série zerada
}

echo json_encode([
    'success' => true,
    'range_dias' => $dias,
    'series' => array_values($series),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
