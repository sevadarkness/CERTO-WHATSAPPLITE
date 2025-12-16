<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/rbac.php';
include 'db_config.php';

// Use RBAC instead of manual comparison
require_role(['Administrador']);

// Validate and cast ID as positive integer
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if ($id === null || $id <= 0) {
    header('Location: painel_admin.php');
    exit;
}

$stmt = (new \RedeAlabama\Repositories\Screens\EditarUsuarioRepository($pdo))->prepare_231();
$stmt->execute([$id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    echo "Usuário não encontrado!";
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!csrf_validate()) {
        $erro = 'Sessão expirada. Recarregue a página.';
    } else {
        // Sanitize and validate input
        $nome = trim($_POST['nome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $nivel_acesso = $_POST['nivel_acesso'] ?? '';

        // Validate nivel_acesso against whitelist
        $allowed_levels = ['Gerente', 'Vendedor'];
        if (!in_array($nivel_acesso, $allowed_levels, true)) {
            $erro = 'Nível de acesso inválido.';
        } elseif ($nome === '' || $telefone === '') {
            $erro = 'Nome e telefone são obrigatórios.';
        } else {
            $stmt = (new \RedeAlabama\Repositories\Screens\EditarUsuarioRepository($pdo))->prepare_593();
            $stmt->execute([$nome, $telefone, $nivel_acesso, $id]);

            header("Location: painel_admin.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuário</title>
</head>
<body class="al-body">
<div class="container mt-4">
    <h4>Editar Usuário</h4>
    
    <?php if ($erro !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    
    <form action="" method="POST">
        <?= csrf_field(); ?>
        <div class="form-group">
            <label for="nome">Nome</label>
            <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <div class="form-group">
            <label for="telefone">Telefone</label>
            <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo htmlspecialchars($usuario['telefone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <div class="form-group">
            <label for="nivel_acesso">Nível de Acesso</label>
            <select class="form-control" id="nivel_acesso" name="nivel_acesso" required>
                <option value="Gerente" <?php echo ($usuario['nivel_acesso'] == 'Gerente') ? 'selected' : ''; ?>>Gerente</option>
                <option value="Vendedor" <?php echo ($usuario['nivel_acesso'] == 'Vendedor') ? 'selected' : ''; ?>>Vendedor</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
