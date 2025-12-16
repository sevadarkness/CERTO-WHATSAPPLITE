<?php
/**
 * ia_campaigns_dashboard.php
 *
 * Painel unificado de campanhas IA (fluxos WhatsApp + IA).
 * Resume o estado dos fluxos (whatsapp_flows), steps e execuções.
 */

declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);
require_once __DIR__ . '/db_config.php';

$pdo = $pdo ?? null;
if (!$pdo) {
    http_response_code(500);
    echo "PDO não inicializado.";
    exit;
}

// Busca fluxos
$flows = [];
try {
    $sql = "SELECT id, nome, descricao, status, target_segment, created_at, updated_at
            FROM whatsapp_flows
            ORDER BY created_at DESC";
    $flows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $flows = [];
    $erro = $e->getMessage();
}

// Carrega contagens auxiliares
$countsSteps = [];
$countsExecs = [];
$countsQueue = [];

if ($flows) {
    $ids = array_map(fn($f) => (int)$f['id'], $flows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Steps por fluxo
    try {
        $sqlSteps = "SELECT flow_id, COUNT(*) AS total_steps
                     FROM whatsapp_flow_steps
                     WHERE flow_id IN ($placeholders)
                     GROUP BY flow_id";
        $stmt = $pdo->prepare($sqlSteps);
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $countsSteps[(int)$row['flow_id']] = (int)$row['total_steps'];
        }
    } catch (Throwable $e) {
        if (function_exists('log_app_event')) {
            log_app_event('ia_campaigns', 'query_failure', [
                'file' => __FILE__,
                'message' => $e->getMessage(),
            ]);
        } else {
            error_log(sprintf('[%s] %s: %s', date('c'), __FILE__, $e->getMessage()));
        }
    }

    // Execuções por fluxo
    try {
        $sqlExec = "SELECT flow_id, status, COUNT(*) AS total_exec
                     FROM whatsapp_flow_executions
                     WHERE flow_id IN ($placeholders)
                     GROUP BY flow_id, status";
        $stmt = $pdo->prepare($sqlExec);
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $fid = (int)$row['flow_id'];
            $st  = $row['status'];
            $countsExecs[$fid][$st] = (int)$row['total_exec'];
        }
    } catch (Throwable $e) {
        if (function_exists('log_app_event')) {
            log_app_event('ia_campaigns', 'query_failure', [
                'file' => __FILE__,
                'message' => $e->getMessage(),
            ]);
        } else {
            error_log(sprintf('[%s] %s: %s', date('c'), __FILE__, $e->getMessage()));
        }
    }

    // Queue por fluxo
    try {
        $sqlQueue = "SELECT flow_id, status, COUNT(*) AS total_q
                     FROM whatsapp_flow_queue
                     WHERE flow_id IN ($placeholders)
                     GROUP BY flow_id, status";
        $stmt = $pdo->prepare($sqlQueue);
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $fid = (int)$row['flow_id'];
            $st  = $row['status'];
            $countsQueue[$fid][$st] = (int)$row['total_q'];
        }
    } catch (Throwable $e) {
        if (function_exists('log_app_event')) {
            log_app_event('ia_campaigns', 'query_failure', [
                'file' => __FILE__,
                'message' => $e->getMessage(),
            ]);
        } else {
            error_log(sprintf('[%s] %s: %s', date('c'), __FILE__, $e->getMessage()));
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Painel de Campanhas IA - Rede Alabama V18 Ultra</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f4f5f7;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1100px;
            margin: 32px auto;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(15,23,42,0.08);
            padding: 24px 28px 28px 28px;
        }
        h1 {
            margin-top: 0;
            font-size: 1.6rem;
            color: #111827;
        }
        .subtitle {
            margin-top: 4px;
            margin-bottom: 16px;
            color: #6b7280;
            font-size: 0.92rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 0.88rem;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        tr:nth-child(even) td {
            background: #f9fafb;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: #e5e7eb;
            font-size: 0.75rem;
            color: #4b5563;
        }
        .badge-ativo {
            background: #dcfce7;
            color: #166534;
        }
        .badge-pausado {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-rascunho {
            background: #e5e7eb;
            color: #4b5563;
        }
        .badge-segment {
            background: #eff6ff;
            color: #1d4ed8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Painel de Campanhas IA</h1>
        <div class="subtitle">
            V18 Ultra · Visão consolidada dos fluxos WhatsApp + IA (status, steps, execuções e fila).
        </div>

        <?php if (!empty($erro)): ?>
            <div class="subtitle" style="color:#b91c1c;">
                Erro ao ler dados de fluxo: <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Fluxo</th>
                    <th>Status</th>
                    <th>Segmento</th>
                    <th>Steps</th>
                    <th>Execuções</th>
                    <th>Fila</th>
                    <th>Criado / Atualizado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($flows)): ?>
                    <tr>
                        <td colspan="7">Nenhum fluxo cadastrado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($flows as $f): 
                        $fid = (int)$f['id'];
                        $st  = $f['status'];
                        $seg = $f['target_segment'] ?? '';
                        $stepsCount = $countsSteps[$fid] ?? 0;
                        $execStats  = $countsExecs[$fid] ?? [];
                        $queueStats = $countsQueue[$fid] ?? [];
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($f['nome']); ?></strong><br>
                                <span style="color:#6b7280;font-size:0.85rem;"><?php echo nl2br(htmlspecialchars($f['descricao'] ?? '')); ?></span>
                            </td>
                            <td>
                                <?php 
                                    $class = 'badge-rascunho';
                                    if ($st === 'ativo') $class = 'badge-ativo';
                                    elseif ($st === 'pausado') $class = 'badge-pausado';
                                ?>
                                <span class="badge <?php echo $class; ?>"><?php echo htmlspecialchars($st); ?></span>
                            </td>
                            <td>
                                <?php if ($seg): ?>
                                    <span class="badge badge-segment"><?php echo htmlspecialchars($seg); ?></span>
                                <?php else: ?>
                                    <span class="badge">Todos</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int)$stepsCount; ?></td>
                            <td>
                                <?php if (empty($execStats)): ?>
                                    <span style="color:#6b7280;font-size:0.85rem;">Nenhuma execução ainda</span>
                                <?php else: ?>
                                    <?php foreach ($execStats as $status => $qtd): ?>
                                        <div><strong><?php echo htmlspecialchars($status); ?>:</strong> <?php echo (int)$qtd; ?></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($queueStats)): ?>
                                    <span style="color:#6b7280;font-size:0.85rem;">Fila vazia</span>
                                <?php else: ?>
                                    <?php foreach ($queueStats as $status => $qtd): ?>
                                        <div><strong><?php echo htmlspecialchars($status); ?>:</strong> <?php echo (int)$qtd; ?></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($f['created_at']); ?></div>
                                <div style="color:#6b7280;font-size:0.85rem;">Atualizado: <?php echo htmlspecialchars($f['updated_at']); ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
