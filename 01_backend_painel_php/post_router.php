<?php
/**
 * Roteador unificado de requisições POST (V14 Ultra).
 *
 * Todos os fluxos sensíveis devem preferencialmente apontar seus formulários para este arquivo,
 * usando o campo hidden "_action" para indicar a operação.
 */

declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Método não permitido. Use POST.';
    exit;
}

// Exige CSRF válido para qualquer ação roteada
csrf_require();

$action = $_POST['_action'] ?? '';

switch ($action) {
    case 'usuario.delete':
        handle_usuario_delete();
        break;

    default:
        http_response_code(400);
        log_app_event('router', 'acao_desconhecida', [
            'action' => $action,
            'uri'    => $_SERVER['REQUEST_URI'] ?? null,
        ]);
        echo 'Ação inválida ou não suportada.';
        exit;
}

/**
 * Exclusão de usuário via POST router.
 * Espera:
 *   - _action = usuario.delete
 *   - id      = ID numérico do usuário
 */
function handle_usuario_delete(): void
{
    require_role(['Administrador']);

    global $pdo;

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(400);
        echo 'ID de usuário inválido.';
        return;
    }

    $current = current_user();
    $byUserId = $current['id'] ?? null;

    // Evita auto-exclusão acidental
    if ($byUserId !== null && (int)$byUserId === (int)$id) {
        http_response_code(400);
        echo 'Você não pode excluir o próprio usuário logado.';
        return;
    }

    try {
        $stmt = (new \RedeAlabama\Repositories\Screens\PostRouterRepository($pdo))->prepare_1798();
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        log_app_event('usuarios', 'delete', [
            'id'        => $id,
            'by_user'   => $byUserId,
            'by_ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'=> $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        header('Location: painel_admin.php?user_deleted=1');
        exit;
    } catch (Throwable $e) {
        log_app_event('usuarios', 'delete_error', [
            'id'      => $id,
            'by_user' => $byUserId,
            'error'   => $e->getMessage(),
        ]);

        http_response_code(500);
        echo 'Erro ao excluir usuário.';
        return;
    }
}
