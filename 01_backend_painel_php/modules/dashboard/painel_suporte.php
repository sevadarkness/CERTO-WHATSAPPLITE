<?php
declare(strict_types=1);

require_once __DIR__ . '/../../session_bootstrap.php';
require_once __DIR__ . '/../../rbac.php';
require_role(['Administrador', 'Gerente', 'Vendedor']);
require_once __DIR__ . '/../../db_config.php';

// Este painel assume que existe uma tabela tickets_suporte opcional.
// Se não existir, apenas mostrará uma mensagem amigável.

$tickets = [];
$erro = null;

try {
    $stmt = $pdo->query("SELECT id, assunto, status, prioridade, criado_em, atualizado_em, cliente_nome 
                         FROM tickets_suporte 
                         ORDER BY status = 'aberto' DESC, prioridade DESC, criado_em DESC
                         LIMIT 50");
    if ($stmt !== false) {
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $erro = 'Tabela tickets_suporte não encontrada ou erro ao carregar dados.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Painel Suporte</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4">🛠️ Painel de Suporte</h2>

    <?php if ($erro !== null): ?>
        <div class="alert alert-warning">
            <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?><br>
            <small>Caso deseje usar esse módulo, crie uma tabela <code>tickets_suporte</code> com campos básicos
                (id, assunto, status, prioridade, criado_em, atualizado_em, cliente_nome).</small>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Assunto</th>
                        <th>Cliente</th>
                        <th>Status</th>
                        <th>Prioridade</th>
                        <th>Criado em</th>
                        <th>Atualizado em</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$tickets): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">Nenhum ticket registrado ou módulo ainda não habilitado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $t): ?>
                            <tr>
                                <td><?php echo (int)$t['id']; ?></td>
                                <td><?php echo htmlspecialchars((string)$t['assunto'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)($t['cliente_nome'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)$t['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)$t['prioridade'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)$t['criado_em'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)($t['atualizado_em'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
