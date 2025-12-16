<?php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/logger.php';

require_role(['Administrador']);

$logFile = ALABAMA_LOG_DIR . '/audit.log';
$rows = [];

if (is_file($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $lines = array_reverse($lines);
    foreach (array_slice($lines, 0, 200) as $line) {
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            continue;
        }
        $rows[] = $decoded;
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
    <meta charset="UTF-8">
    <title>Audit Log - Rede Alabama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="al-body">
<div class="container my-4">
    <h1 class="mb-4">Audit Log (últimos eventos)</h1>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <small class="text-muted">Exibindo últimos eventos gravados em logs/audit.log</small>
        <a href="audit_log_pdf.php" target="_blank" class="btn btn-sm btn-outline-primary">
            Exportar PDF
        </a>
    </div>

    <?php if (empty($rows)): ?>
        <p class="text-muted">Nenhum evento de auditoria encontrado.</p>
    <?php else: ?>
        <table class="table table-sm table-striped">
            <thead>
            <tr>
                <th>Data/Hora</th>
                <th>Evento</th>
                <th>Entidade</th>
                <th>Usuário</th>
                <th>Hash</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['ts'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($r['event'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['context']['entity_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['context']['usuario_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="small text-monospace"><?php echo htmlspecialchars((string)($r['chain_hash'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <a href="painel_admin.php" class="btn btn-secondary mt-3">Voltar ao painel</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
