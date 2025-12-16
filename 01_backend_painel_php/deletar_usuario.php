<?php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';

// Apenas Administrador pode excluir usuários
require_role(['Administrador']);

// Este endpoint era legado (aceitava GET). Para produção, restringimos a POST + CSRF.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Método não permitido. Use POST.';
    exit;
}

csrf_require();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    echo 'ID de usuário inválido.';
    exit;
}

$current = current_user();
$byUserId = $current['id'] ?? null;

// Evita que o usuário logado se auto-exclua
if ($byUserId !== null && (int)$byUserId === (int)$id) {
    http_response_code(400);
    echo 'Você não pode excluir o próprio usuário logado.';
    exit;
}

try {
    $stmt = (new \RedeAlabama\Repositories\Screens\DeletarUsuarioRepository($pdo))->prepare_981();
    $stmt->execute([$id]);

    log_app_event('usuarios', 'delete_via_deletar_usuario_post', [
        'id'         => $id,
        'by_user'    => $byUserId,
        'by_ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'uri'        => $_SERVER['REQUEST_URI'] ?? null,
    ]);
} catch (Throwable $e) {
    log_app_event('usuarios', 'delete_legacy_error', [
        'id'      => $id,
        'by_user' => $byUserId,
        'error'   => $e->getMessage(),
    ]);

    http_response_code(500);
    echo 'Erro ao excluir usuário.';
    exit;
}

header('Location: painel_admin.php?user_deleted=1');
exit;
