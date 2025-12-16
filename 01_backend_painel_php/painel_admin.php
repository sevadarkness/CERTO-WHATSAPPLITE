<?php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/logger.php';

// Garante que apenas Administrador acesse
require_role(['Administrador']);

// Inclui navegação principal
include 'menu_navegacao.php';

$erro_add = $erro_add ?? '';

// Lida com a adição de um novo usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    // Proteção CSRF aplicada apenas ao fluxo de criação de usuário
    csrf_require();

    $nome         = trim($_POST['nome'] ?? '');
    $telefone     = trim($_POST['telefone'] ?? '');
    $senha        = (string)($_POST['senha'] ?? '');
    $nivel_acesso = $_POST['nivel_acesso'] ?? '';

    if ($nome === '' || $telefone === '' || $senha === '' || $nivel_acesso === '') {
        $erro_add = 'Preencha todos os campos para criar o usuário.';
    } else {
        $hash = password_hash(
            $senha,
            defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT
        );

        $stmt = (new \RedeAlabama\Repositories\Screens\PainelAdminRepository($pdo))->prepare_1050();
        $stmt->execute([$nome, $telefone, $hash, $nivel_acesso]);

        log_app_event('usuarios', 'create', [
            'by_user'      => $_SESSION['usuario_id'] ?? null,
            'telefone'     => $telefone,
            'nivel_acesso' => $nivel_acesso,
        ]);

        if (function_exists('log_audit_event')) {
            log_audit_event(
                'usuario_create',
                'usuario',
                (int)$pdo->lastInsertId(),
                [
                    'telefone'     => $telefone,
                    'nivel_acesso' => $nivel_acesso,
                ]
            );
        }

        header('Location: painel_admin.php?user_created=1');
        exit;
    }
}

// Busca todos os usuários
$stmt = (new \RedeAlabama\Repositories\Screens\PainelAdminRepository($pdo))->query_1552();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Painel Administrativo - Rede Alabama</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Vendor CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Alabama Design System -->
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">

    <!-- Admin-specific tweaks (não conflitam com o DS) -->
    <style>
        .action-buttons .btn {
            margin: 0 0.25rem;
            min-width: 70px;
        }
    </style>
</head>

<body class="al-body">

<div class="container mt-4">
    <div class="admin-card al-card al-card-elevated p-4">

        <?php if (isset($_GET['user_created'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> Usuário criado com sucesso!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['user_deleted'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> Usuário excluído com sucesso!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($erro_add !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($erro_add, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <h3 class="text-primary mb-4">
            👋 Bem-vindo,
            <?php
                $bemVindoNome = (string)($_SESSION['nome_usuario'] ?? ($usuario['nome'] ?? 'Usuário'));
                echo htmlspecialchars($bemVindoNome, ENT_QUOTES, 'UTF-8');
            ?>!
        </h3>

        <h4 class="border-bottom pb-2 mb-4">📋 Gerenciamento de Usuários</h4>

        <div class="table-responsive">
            <table class="table table-hover al-table">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>Nível de Acesso</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td class="text-muted"><?= (int)$usuario['id']; ?></td>
                        <td><?= htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($usuario['telefone'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <span class="badge <?= $usuario['nivel_acesso'] === 'Administrador' ? 'al-badge-primary' : 'al-badge-secondary'; ?>">
                                <?= htmlspecialchars($usuario['nivel_acesso'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td class="action-buttons text-center">
                            <a href="editar_usuario.php?id=<?= (int)$usuario['id']; ?>"
                               class="btn btn-outline-warning btn-sm"
                               data-bs-toggle="tooltip"
                               title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>

                            <form action="post_router.php" method="POST" class="d-inline">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="_action" value="usuario.delete">
                                <input type="hidden" name="id" value="<?= (int)$usuario['id']; ?>">
                                <button type="submit"
                                        class="btn btn-outline-danger btn-sm"
                                        data-bs-toggle="tooltip"
                                        title="Excluir"
                                        onclick="return confirm('Tem certeza que deseja excluir este usuário?');">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h4 class="border-bottom pb-2 mt-5 mb-4">➕ Adicionar Novo Usuário</h4>

        <form action="painel_admin.php" method="POST">
            <?= csrf_field(); ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="nome" class="form-label">
                        <i class="fas fa-user me-1"></i> Nome
                    </label>
                    <input type="text" class="form-control" id="nome" name="nome" required>
                </div>

                <div class="col-md-6">
                    <label for="telefone" class="form-label">
                        <i class="fas fa-phone me-1"></i> Telefone
                    </label>
                    <input type="text" class="form-control" id="telefone" name="telefone" required>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="senha" class="form-label">
                        <i class="fas fa-lock me-1"></i> Senha
                    </label>
                    <input type="password" class="form-control" id="senha" name="senha" required>
                </div>

                <div class="col-md-6">
                    <label for="nivel_acesso" class="form-label">
                        <i class="fas fa-shield-alt me-1"></i> Nível de Acesso
                    </label>
                    <select class="form-control" id="nivel_acesso" name="nivel_acesso" required>
                        <option value="Gerente">Gerente</option>
                        <option value="Vendedor">Vendedor</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="add_user" class="btn btn-success w-100">
                <i class="fas fa-user-plus me-2"></i> Adicionar Usuário
            </button>
        </form>

    </div>
</div>

<footer class="footer bg-dark text-center text-white py-3 mt-4">
    <p class="mb-0">
        AlabamaCMS 1.1 &copy; <?= date("Y"); ?> — Todos os direitos reservados
    </p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script <?= alabama_csp_nonce_attr(); ?>>
    document.addEventListener('DOMContentLoaded', function () {
        document
            .querySelectorAll('[data-bs-toggle="tooltip"]')
            .forEach(el => new bootstrap.Tooltip(el));
    });
</script>

</body>
</html>
