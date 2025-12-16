<?php
/**
 * (LEGACY) Fluxo antigo de reset de senha baseado em e-mail.
 *
 * Este arquivo não está integrado ao login atual do painel (que usa telefone + senha).
 * Mantido apenas por compatibilidade/consulta, MAS DESABILITADO por padrão.
 *
 * Para habilitar conscientemente...
 *
 * OBS: Recomenda-se migrar para um fluxo de reset moderno (token curto + expiração + rate limiting + ...)
 */

declare(strict_types=1);

require_once __DIR__ . '/../_support/env.php';
rede_alabama_load_env();

$enabled = strtolower(trim((string)(getenv('ALABAMA_ENABLE_LEGACY_PASSWORD_RESET') ?: getenv('ENABLE_LEGACY_PASSWORD_RESET') ?: '')));
if (!in_array($enabled, ['1', 'true', 'yes', 'on'], true)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

require_once __DIR__ . '/../../session_bootstrap.php';
require_once __DIR__ . '/db.php';

$pdo = db();

function alabama_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($row['c'] ?? 0) > 0;
}

$hasTokenExpira = false;
try {
    $hasTokenExpira = alabama_has_column($pdo, 'usuarios', 'token_expira');
} catch (Throwable $e) {
    // Falha silenciosa
    $hasTokenExpira = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string)($_POST['email'] ?? '');
    $email = trim($email);

    // Gera token
    $token = bin2hex(random_bytes(16));
    $expira = time() + 3600; // 1 hora

    if ($hasTokenExpira) {
        $pdo->prepare('UPDATE usuarios SET reset_token=?, token_expira=? WHERE email=?')->execute([$token, $expira, $email]);
    } else {
        $pdo->prepare('UPDATE usuarios SET reset_token=? WHERE email=?')->execute([$token, $email]);
    }

    // Monta link (usa APP_URL/ALABAMA_BASE_URL quando possível)
    $base = getenv('APP_URL') ?: (getenv('ALABAMA_BASE_URL') ?: '');
    $base = rtrim((string)$base, '/');
    $link = ($base !== '')
        ? ($base . '/modules/auth/verify_token.php?token=' . urlencode($token))
        : ('verify_token.php?token=' . urlencode($token));

    // Envio simples (seu ambiente pode trocar para SMTP/PHPMailer)
    @mail($email, 'Reset de Senha', "Clique aqui para resetar: {$link}");

    echo 'Se o e-mail existir, um link foi enviado.';
    exit;
}

// Form simples
echo '<form method="post">Email: <input name="email" type="email" required><button>Gerar Link</button></form>';
