<?php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';

// Apenas administradores podem remover produtos
require_role(['Administrador']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Método não permitido. Use POST.';
    exit;
}

csrf_require();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if ($id === null || $id === false || $id <= 0) {
    header('Location: catalogo.php');
    exit;
}

$query = (new \RedeAlabama\Repositories\Screens\RemoverProdutoRepository($pdo))->prepare_60();
$query->execute([$id]);

if (function_exists('log_app_event')) {
    log_app_event('estoque', 'produto_removido', [
        'produto_id' => (int) $id,
        'usuario_id' => $_SESSION['usuario_id'] ?? null,
    ]);
}

header('Location: catalogo.php?msg=' . urlencode('Produto removido.'));
exit;
