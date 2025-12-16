<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/menu_navegacao.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: catalogo.php');
    exit;
}

$query = (new \RedeAlabama\Repositories\Screens\EditarProdutoRepository($pdo))->prepare_263();
$query->execute([$id]);
$produto = $query->fetch();

if (!$produto) {
    header('Location: catalogo.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Proteção CSRF
    if (function_exists('csrf_require')) {
        csrf_require();
    }
    $nome      = (string) ($_POST['nome'] ?? '');
    $descricao = (string) ($_POST['descricao'] ?? '');
    $preco     = isset($_POST['preco']) && $_POST['preco'] !== '' ? (float) $_POST['preco'] : 0.0;
    $promocao  = isset($_POST['promocao']) ? 1 : 0;

    // Verifica se uma nova imagem foi enviada
    $imagem = (string) ($produto['imagem'] ?? '');
    if (isset($_FILES['imagem']) && isset($_FILES['imagem']['name']) && $_FILES['imagem']['name'] !== '' && (int)($_FILES['imagem']['error'] ?? 0) === UPLOAD_ERR_OK) {
        $tmpName = (string) $_FILES['imagem']['tmp_name'];
        $orig    = (string) $_FILES['imagem']['name'];
        $ext     = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

        // Extensões permitidas (mínimo para evitar upload perigoso)
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed, true)) {
            $uploadsDir = __DIR__ . '/uploads';
            if (!is_dir($uploadsDir)) {
                @mkdir($uploadsDir, 0775, true);
            }

            $safeName = 'prod_' . $id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destFs   = $uploadsDir . '/' . $safeName;

            if (@move_uploaded_file($tmpName, $destFs)) {
                $imagem = $safeName;
            }
        }
    }

    // Atualiza os dados do produto
    $query = (new \RedeAlabama\Repositories\Screens\EditarProdutoRepository($pdo))->prepare_964();
    $query->execute([$nome, $descricao, $preco, $imagem, $promocao, $id]);

    // Atualiza os sabores (caso modificados ou removidos)
    if (isset($_POST['sabores']) && is_array($_POST['sabores'])) {
        $novos_sabores = array_values(array_filter(array_map('trim', $_POST['sabores']), static fn($v) => $v !== ''));

        // Obtenha os sabores existentes no banco
        $query_sabores_antigos = (new \RedeAlabama\Repositories\Screens\EditarProdutoRepository($pdo))->prepare_1375();
        $query_sabores_antigos->execute([$id]);
        $sabores_antigos = $query_sabores_antigos->fetchAll(PDO::FETCH_ASSOC);

        // Identifique sabores a adicionar e a remover
        $sabores_existentes = array_column($sabores_antigos, 'sabor');
        $sabores_a_remover   = array_diff($sabores_existentes, $novos_sabores);
        $sabores_a_adicionar = array_diff($novos_sabores, $sabores_existentes);

        $repoEditar = new \RedeAlabama\Repositories\Screens\EditarProdutoRepository($pdo);

        // Remove estoque_vendedores (por sabor_id) e depois remove os sabores que saíram da lista
        if (!empty($sabores_a_remover)) {
            $mapIds = [];
            foreach ($sabores_antigos as $row) {
                if (isset($row['sabor'], $row['id'])) {
                    $mapIds[(string)$row['sabor']] = (int)$row['id'];
                }
            }

            // 1) Remove estoque vinculado aos sabores removidos (evita FK/orfãos)
            $stmtDelEstoque = $repoEditar->prepare_2695();
            foreach ($sabores_a_remover as $sabor_removido) {
                $sid = $mapIds[$sabor_removido] ?? null;
                if ($sid !== null) {
                    $stmtDelEstoque->execute([$sid]);
                }
            }

            // 2) Remove os sabores
            $stmtDelSabor = $repoEditar->prepare_1969();
            foreach ($sabores_a_remover as $sabor_removido) {
                $stmtDelSabor->execute([$id, $sabor_removido]);
            }
        }

        // Adiciona os novos sabores
        if (!empty($sabores_a_adicionar)) {
            $stmtAddSabor = $repoEditar->prepare_2320();
            foreach ($sabores_a_adicionar as $sabor_adicionado) {
                $stmtAddSabor->execute([$id, $sabor_adicionado]);
            }
        }
    }

    header('Location: catalogo.php');
    exit;
}

// Carregar sabores do produto
$query_sabores = (new \RedeAlabama\Repositories\Screens\EditarProdutoRepository($pdo))->prepare_3162();
$query_sabores->execute([$id]);
$sabores = $query_sabores->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
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
    <title>Editar Produto</title>
</head>
<body class="al-body">
    <!-- Conteúdo do Painel -->
    <main class="container my-5">
        <div class="card p-4">
            <h2 class="h4 mb-4 text-center">Editar Produto</h2>

            <!-- Exibição da Imagem Atual do Produto -->
            <div class="text-center mb-4">
                <img src="uploads/<?php echo htmlspecialchars((string)($produto['imagem'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" alt="Imagem do Produto" class="img-fluid rounded" style="max-width: 300px;">
            </div>

            <!-- Formulário de Edição -->
            <form action="" method="POST" enctype="multipart/form-data">
                <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="nome">Nome do Produto</label>
                        <input type="text" name="nome" id="nome" class="form-control" placeholder="Nome do Produto" value="<?php echo htmlspecialchars((string)($produto['nome'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="preco">Preço</label>
                        <input type="number" name="preco" id="preco" class="form-control" placeholder="Preço" value="<?php echo htmlspecialchars((string)($produto['preco'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="descricao">Descrição do Produto</label>
                    <textarea name="descricao" id="descricao" class="form-control" placeholder="Descrição do Produto" rows="4" required><?php echo htmlspecialchars((string)($produto['descricao'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="imagem">Imagem do Produto (opcional)</label>
                    <input type="file" name="imagem" id="imagem" class="form-control" accept="image/*">
                </div>

                <div class="form-check">
                    <input type="checkbox" name="promocao" id="promocao" class="form-check-input" value="1" <?php echo !empty($produto['promocao']) ? 'checked' : ''; ?>>
                    <label for="promocao" class="form-check-label">Produto em Promoção</label>
                </div>

                <!-- Exibindo os sabores atuais -->
                <div class="form-group mt-4">
                    <label>Sabores (Editar ou Remover)</label>
                    <div id="sabores-container">
                        <?php foreach ($sabores as $index => $sabor): ?>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" name="sabores[]" value="<?php echo htmlspecialchars((string)($sabor['sabor'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                <div class="input-group-append">
                                    <button class="btn btn-danger" type="button" onclick="removerSabor(this)">Remover</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="adicionarSabor()">Adicionar Sabor</button>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Salvar Alterações</button>
            </form>
        </div>
    </main>

    <script <?php echo alabama_csp_nonce_attr(); ?>>
        // Função para adicionar campos de sabor dinamicamente
        function adicionarSabor() {
            const container = document.getElementById('sabores-container');
            const div = document.createElement('div');
            div.className = 'input-group mb-2';
            div.innerHTML = `
                <input type="text" class="form-control" name="sabores[]" placeholder="Novo Sabor" required>
                <div class="input-group-append">
                    <button class="btn btn-danger" type="button" onclick="removerSabor(this)">Remover</button>
                </div>
            `;
            container.appendChild(div);
        }

        // Função para remover um campo de sabor
        function removerSabor(button) {
            button.closest('.input-group').remove();
        }
    </script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
