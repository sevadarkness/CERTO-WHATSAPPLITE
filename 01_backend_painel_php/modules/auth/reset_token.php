<?php
/**
 * (LEGACY) Gerador manual de token de reset.
 *
 * IMPORTANTE:
 * - Este endpoint NUNCA deve ficar exposto em produção...
 * - Por padrão ele fica DESABILITADO. Para habilitar em ambiente controlado:
 *     ALABAMA_ENABLE_LEGACY_PASSWORD_RESET=1
 *
 * Mesmo habilitado, restringe acesso a Administrador.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_support/env.php';
rede_alabama_load_env();

$enabled = strtolower(trim((string)(getenv('ALABAMA_ENABLE_LEGACY_PASSWORD_RESET') ?: '')));
if (!in_array($enabled, ['1', 'true', 'yes', 'on'], true)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

require_once __DIR__ . '/../../session_bootstrap.php';
require_once __DIR__ . '/../../rbac.php';
require_role(['Administrador']);

require_once __DIR__ . '/db.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    if (!$email) {
        echo 'Email inválido.';
        exit;
    }

    $token = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("UPDATE usuarios SET reset_token = ? WHERE email = ?");
    $stmt->execute([$token, $email]);

    echo 'Token gerado (uso manual): ' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
    exit;
}

?>
<form method="post">
  Email: <input name="email" type="email" required>
  <button type="submit">Gerar Token</button>
</form>
