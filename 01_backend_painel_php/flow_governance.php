<?php
declare(strict_types=1);

use RedeAlabama\Repositories\FlowRepository;
use RedeAlabama\Services\Flow\FlowGovernanceService;


/**
 * Painel visual de governança de fluxos.
 */

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/security_helpers.php';

require_role(['Administrador']);

require_once __DIR__ . '/app/Repositories/FlowRepository.php';
require_once __DIR__ . '/app/Services/Flow/FlowGovernanceService.php';


$repo = new FlowRepository($pdo);
$gov  = new FlowGovernanceService($pdo, $repo);

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'snapshot') {
        $reason = trim((string)($_POST['reason'] ?? 'snapshot_manual'));
        $uid    = $_SESSION['usuario_id'] ?? null;
        try {
            $versionId = $gov->snapshot($reason !== '' ? $reason : 'snapshot_manual', $uid);
            $sucesso = 'Snapshot criado: versão ' . $versionId;
        } catch (Throwable $e) {
            $erro = 'Falha ao criar snapshot: ' . $e->getMessage();
        }
    } elseif ($action === 'rollback') {
        $versionId = isset($_POST['version_id']) ? (int) $_POST['version_id'] : 0;
        if ($versionId <= 0) {
            $erro = 'Versão inválida.';
        } else {
            if ($gov->rollback($versionId)) {
                $sucesso = 'Rollback aplicado para a versão ' . $versionId;
            } else {
                $erro = 'Falha ao aplicar rollback.';
            }
        }
    }
}

$versions = $gov->listVersions();
$flows    = $repo->allActive();

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
    <title>Governança de Fluxos - Rede Alabama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="al-body">
<div class="container my-4">
    <h1 class="mb-4">Governança de Fluxos</h1>

    <?php if ($erro !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($sucesso !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">Snapshot atual</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="snapshot">
                        <div class="mb-3">
                            <label class="form-label">Motivo / Observação</label>
                            <input type="text" name="reason" class="form-control" placeholder="Ex.: antes de grande campanha">
                        </div>
                        <button type="submit" class="btn btn-primary">Criar Snapshot</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">Versões disponíveis</div>
                <div class="card-body">
                    <?php if (empty($versions)): ?>
                        <p class="text-muted">Nenhum snapshot encontrado.</p>
                    <?php else: ?>
                        <form method="post">
                            <input type="hidden" name="action" value="rollback">
                            <div class="mb-3">
                                <label class="form-label">Escolha uma versão para rollback</label>
                                <select name="version_id" class="form-select" required>
                                    <?php foreach ($versions as $v): ?>
                                        <option value="<?php echo (int)$v['version_id']; ?>">
                                            <?php echo (int)$v['version_id']; ?> -
                                            <?php echo htmlspecialchars((string)$v['created_at'], ENT_QUOTES, 'UTF-8'); ?> -
                                            <?php echo htmlspecialchars((string)($v['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-danger"
                                    onclick="return confirm('Tem certeza que deseja aplicar rollback para esta versão?');">
                                Aplicar Rollback
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <h2 class="mt-4 mb-3">Fluxos Ativos</h2>
    <div class="card mb-4">
        <div class="card-body">
            <?php if (empty($flows)): ?>
                <p class="text-muted">Nenhum fluxo ativo encontrado.</p>
            <?php else: ?>
                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Ativo</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($flows as $f): ?>
                        <tr>
                            <td><?php echo (int)$f->id; ?></td>
                            <td><?php echo htmlspecialchars($f->nome, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $f->ativo ? 'Sim' : 'Não'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <a href="painel_admin.php" class="btn btn-secondary">Voltar ao painel</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
