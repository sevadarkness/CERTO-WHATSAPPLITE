<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mfa_lib.php';
require_once __DIR__ . '/../../session_bootstrap.php';
require_once __DIR__ . '/../../csrf.php';

$redirect = $_GET['redirect'] ?? 'painel_admin.php';
$redirect = (string)$redirect;
// Mitiga open-redirect: permite apenas caminhos relativos (sem scheme/host) e sem quebras de linha.
$redirect = str_replace(["\r", "\n"], '', $redirect);
$parsed = @parse_url($redirect);
if (is_array($parsed) && (isset($parsed['scheme']) || isset($parsed['host']))) {
    $redirect = 'painel_admin.php';
}
if (str_starts_with($redirect, '//')) {
    $redirect = 'painel_admin.php';
}
if ($redirect === '') {
    $redirect = 'painel_admin.php';
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!csrf_validate()) {
        $erro = 'Sessão expirada. Recarregue a página.';
    } else {
        $code = $_POST['code'] ?? '';
        if (alabama_mfa_verify_code($code)) {
            $_SESSION['mfa_ok'] = true;
            header('Location: ' . $redirect);
            exit;
        } else {
            $erro = 'Código inválido.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Verificação MFA - Rede Alabama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container my-5">
    <h1 class="mb-4">Verificação de segundo fator (MFA)</h1>

    <?php if ($erro !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <p class="mb-3">
        Digite o código de 6 dígitos gerado pelo seu aplicativo autenticador
        configurado com o segredo definido em <code>ALABAMA_MFA_SECRET_ADMIN</code>.
    </p>

    <form method="post" class="card card-body">
        <?= csrf_field(); ?>
        <div class="mb-3">
            <label class="form-label">Código MFA</label>
            <input type="text" name="code" class="form-control" maxlength="6" autocomplete="one-time-code" required>
        </div>
        <button type="submit" class="btn btn-primary">Confirmar</button>
    </form>
</div>
</body>
</html>
