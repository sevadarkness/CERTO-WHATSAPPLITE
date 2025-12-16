<?php
/**
 * (LEGACY) Verificação de token de reset.
 *
 * DESABILITADO por padrão (ver ALABAMA_ENABLE_LEGACY_PASSWORD_RESET).
 *
 * Correções:
 * - Quando habilitado, atualiza tanto a coluna `senha` quanto `senha_hash` (se existir)
 *   para evitar inconsistência com o login atual.
 * - Faz fallback caso a coluna `token_expira` não exista.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../session_bootstrap.php';
require_once __DIR__ . '/../_support/env.php';
rede_alabama_load_env();

$enabled = strtolower(trim((string)(getenv('ALABAMA_ENABLE_LEGACY_PASSWORD_RESET') ?: getenv('ENABLE_LEGACY_PASSWORD_RESET') ?: '')));
if (!in_array($enabled, ['1', 'true', 'yes', 'on'], true)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

require_once __DIR__ . '/db.php';

$pdo = db();

$token = (string)($_GET['token'] ?? '');
$token = trim($token);
if ($token === '') {
    http_response_code(400);
    echo 'Token ausente.';
    exit;
}

// Detecta se a coluna token_expira existe
$hasTokenExpira = false;
try {
    $q = $pdo->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='token_expira'");
    $hasTokenExpira = ((int)($q?->fetch()['c'] ?? 0)) > 0;
} catch (Throwable $e) {
    $hasTokenExpira = false;
}

if ($hasTokenExpira) {
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE reset_token = ? AND token_expira > ?');
    $stmt->execute([$token, time()]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE reset_token = ?');
    $stmt->execute([$token]);
}

$user = $stmt->fetch();
if (!$user) {
    echo 'Token inválido ou expirado.';
    exit;
}

$erro = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha = (string)($_POST['senha'] ?? '');
    if (trim($senha) === '' || strlen($senha) < 6) {
        $erro = 'Informe uma nova senha (mínimo 6 caracteres).';
    } else {
        $nova = password_hash($senha, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);

        // Atualiza `senha` e `senha_hash` se existirem; sempre limpa reset_token e token_expira (se houver)
        try {
            // Primeiro tenta schema novo (senha + senha_hash + token_expira)
            if ($hasTokenExpira) {
                $pdo->prepare('UPDATE usuarios SET senha = ?, senha_hash = ?, reset_token = NULL, token_expira = NULL WHERE id = ?')
                    ->execute([$nova, $nova, $user['id']]);
            } else {
                $pdo->prepare('UPDATE usuarios SET senha = ?, senha_hash = ?, reset_token = NULL WHERE id = ?')
                    ->execute([$nova, $nova, $user['id']]);
            }
        } catch (Throwable $e) {
            // Fallback schema antigo (apenas senha_hash)
            if ($hasTokenExpira) {
                $pdo->prepare('UPDATE usuarios SET senha_hash = ?, reset_token = NULL, token_expira = NULL WHERE id = ?')
                    ->execute([$nova, $user['id']]);
            } else {
                $pdo->prepare('UPDATE usuarios SET senha_hash = ?, reset_token = NULL WHERE id = ?')
                    ->execute([$nova, $user['id']]);
            }
        }

        $ok = 'Senha atualizada com sucesso.';
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset de Senha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container my-5" style="max-width: 540px;">
    <h1 class="h4 mb-3">Definir nova senha</h1>

    <?php if ($erro !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($ok !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($ok, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php else: ?>
        <form method="post" class="card card-body">
            <div class="mb-3">
                <label class="form-label">Nova senha</label>
                <input type="password" name="senha" class="form-control" required minlength="6">
            </div>
            <button class="btn btn-primary" type="submit">Atualizar senha</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
