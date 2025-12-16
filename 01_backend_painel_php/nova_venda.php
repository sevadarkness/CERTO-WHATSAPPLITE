<?php
require_once __DIR__ . '/session_bootstrap.php';
include 'db_config.php';
include 'menu_navegacao.php';

if (!isset($_SESSION['mensagem'])) $_SESSION['mensagem'] = '';
if (!isset($_SESSION['tipo_mensagem'])) $_SESSION['tipo_mensagem'] = '';
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$produtos_grouped = [];
$acesso_restrito = true;

try {
    $usuario_id = $_SESSION['usuario_id'];
    $stmt_usuario = (new \RedeAlabama\Repositories\Screens\NovaVendaRepository($pdo))->prepare_693();
    $stmt_usuario->execute([$usuario_id]);
    $usuario = $stmt_usuario->fetch();
    $acesso_restrito = !in_array($usuario['nivel_acesso'], ['Vendedor', 'Gerente', 'Administrador']);

    $stmt_produtos = (new \RedeAlabama\Repositories\Screens\NovaVendaRepository($pdo))->prepare_1014();
    $stmt_produtos->execute([$usuario_id]);
    $produtos = $stmt_produtos->fetchAll();

    foreach ($produtos as $produto) {
        $produtos_grouped[$produto['id']]['nome'] = $produto['nome'];
        $produtos_grouped[$produto['id']]['preco'] = $produto['preco'];
        $produtos_grouped[$produto['id']]['sabores'][] = [
            'sabor_id' => $produto['sabor_id'],
            'sabor' => $produto['sabor'],
            'quantidade' => $produto['quantidade']
        ];
    }
} catch (PDOException $e) {
    $_SESSION['mensagem'] = "Erro no sistema: " . $e->getMessage();
    $_SESSION['tipo_mensagem'] = "danger";
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Venda - Rede Alabama</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script>

    <style>
        .floating-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            z-index: 1000;
            border-radius: var(--al-radius-md);
        }
        .sabor-item {
            padding: 10px;
            margin: 4px 0;
            border-radius: var(--al-radius-md);
            transition: all 0.2s;
        }
        .sabor-item:hover { transform: translateX(4px); }
        .file-upload {
            border: 2px dashed var(--al-border-light);
            border-radius: var(--al-radius-md);
            padding: 20px;
            text-align: center;
        }
        .status-toggle {
            border: 1px solid var(--al-border-light);
            border-radius: var(--al-radius-md);
            padding: 12px;
            margin-bottom: 8px;
        }
    </style>
</head>

<body class="al-body">
<div class="container mt-4">
    <div class="card p-4">
        <h2 class="mb-4 text-center text-primary">
            <i class="fas fa-cash-register me-2"></i>Nova Venda
        </h2>

        <?php if (!empty($_SESSION['mensagem'])): ?>
            <div class="floating-alert alert alert-<?= $_SESSION['tipo_mensagem'] ?> alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['mensagem'], ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['mensagem'], $_SESSION['tipo_mensagem']); ?>
        <?php endif; ?>

        <?php if (!$acesso_restrito): ?>
        <form method="POST" id="formVenda" enctype="multipart/form-data">
            <?php require_once __DIR__ . '/csrf.php'; echo csrf_field(); ?>

            <div class="mb-4">
                <label class="form-label fw-bold">Produto</label>
                <select class="form-select" name="produto_id" id="produtoSelect" required>
                    <option value="">Selecione…</option>
                    <?php foreach ($produtos_grouped as $id => $produto): ?>
                        <option value="<?= $id ?>" data-preco="<?= $produto['preco'] ?>">
                            <?= $produto['nome'] ?> — R$ <?= number_format($produto['preco'], 2, ',', '.') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Sabores</label>
                <div id="saboresList" class="p-3 bg-light rounded-2">
                    <span class="text-muted small">Selecione um produto</span>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Cliente</label>
                    <input type="text" id="clienteNome" name="cliente_nome" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Telefone</label>
                    <input type="text" id="clienteTelefone" name="cliente_telefone"
                           class="form-control" data-inputmask="'mask':'(99) 99999-9999'" required>
                </div>
            </div>

            <input type="hidden" name="preco_produto" id="precoProduto">

            <div class="d-grid gap-2">
                <button type="submit" name="confirmar_venda" class="btn btn-primary">
                    <i class="fas fa-check-circle me-2"></i>Confirmar Venda
                </button>
            </div>
        </form>
        <?php else: ?>
            <div class="alert alert-danger text-center">
                <i class="fas fa-ban me-2"></i>Acesso não autorizado
            </div>
        <?php endif; ?>
    </div>
</div>

<script <?php echo alabama_csp_nonce_attr(); ?>>
$(function () {
    $('[data-inputmask]').inputmask();

    $('#produtoSelect').on('change', function () {
        const produtos = <?= json_encode($produtos_grouped) ?>;
        const produtoId = this.value;
        const sabores = produtos[produtoId]?.sabores || [];
        let html = '';

        sabores.forEach(s => {
            html += `
              <div class="form-check sabor-item">
                <input class="form-check-input" type="radio" name="sabor_id" value="${s.sabor_id}" required>
                <label class="form-check-label">${s.sabor}
                  <span class="badge bg-primary ms-2">${s.quantidade}</span>
                </label>
              </div>`;
        });

        $('#saboresList').html(html || '<span class="text-danger">Sem estoque</span>');
        $('#precoProduto').val($(this).find(':selected').data('preco'));
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
