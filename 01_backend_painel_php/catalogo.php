<?php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';

// Menu já valida sessão e carrega $usuario (com nivel_acesso)
include __DIR__ . '/menu_navegacao.php';

$nivelAcesso = (string)($usuario['nivel_acesso'] ?? '');
$isAdmin = $nivelAcesso === 'Administrador';
$isGerente = $nivelAcesso === 'Gerente';

// Por padrão, esta tela é do time de gestão (evita exposição para vendedores)
if (!$isAdmin && !$isGerente) {
    header('Location: painel_vendedor.php');
    exit;
}

$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

$repo = new \RedeAlabama\Repositories\Screens\CatalogoRepository($pdo);
$stmtProdutos = $repo->query_4765();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo - Rede Alabama</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
</head>
<body class="al-body">

<main class="container my-4">
    <h2 class="mb-4">Catálogo</h2>

    <?php if ($msg !== ''): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <!-- Formulário para adicionar novo produto (ADMIN) -->
        <div class="card p-4 mb-4">
            <h3 class="h5">Adicionar Novo Produto</h3>
            <form action="adicionar_produto.php" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>

                <div class="mb-3">
                    <input type="text" name="nome" class="form-control" placeholder="Nome do Produto" required>
                </div>
                <div class="mb-3">
                    <textarea name="descricao" class="form-control" placeholder="Descrição do Produto" required style="height: 100px;"></textarea>
                </div>
                <div class="mb-3">
                    <input type="number" name="preco" class="form-control" placeholder="Preço" step="0.01" min="0" required>
                </div>
                <div class="mb-3">
                    <input type="file" name="imagem" class="form-control" accept="image/*" required>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" name="promocao" class="form-check-input" value="1" id="promocao">
                    <label class="form-check-label" for="promocao">Produto em Promoção</label>
                </div>

                <!-- Campos para adicionar sabores -->
                <div class="form-group">
                    <label>Sabores (Mínimo 1, Máximo 10)</label>
                    <div id="sabores-container">
                        <input type="text" class="form-control mb-2" name="sabores[]" placeholder="Sabor 1" required>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="adicionarSabor()">Adicionar Sabor</button>
                </div>

                <button type="submit" class="btn btn-primary">Adicionar Produto</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Lista de Produtos -->
    <h3 class="h5">Lista de Produtos</h3>
    <div class="row">
        <?php while ($produto = $stmtProdutos->fetch()): ?>
            <?php
                $id = (int)($produto['id'] ?? 0);
                $nome = htmlspecialchars((string)($produto['nome'] ?? ''), ENT_QUOTES, 'UTF-8');
                $descricao = htmlspecialchars((string)($produto['descricao'] ?? ''), ENT_QUOTES, 'UTF-8');
                $imagemFile = basename((string)($produto['imagem'] ?? ''));
                $imagem = htmlspecialchars($imagemFile, ENT_QUOTES, 'UTF-8');
                $precoNum = (float)($produto['preco'] ?? 0);
                $promocao = (int)($produto['promocao'] ?? 0) === 1;

                // Sabores do produto
                $stmtSabores = $repo->prepare_5662();
                $stmtSabores->execute([$id]);
                $sabores = $stmtSabores->fetchAll(PDO::FETCH_COLUMN);
                $saboresTxt = '';
                if (is_array($sabores) && $sabores) {
                    $saboresSafe = array_map(static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'), $sabores);
                    $saboresTxt = implode(', ', $saboresSafe);
                }
            ?>

            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <?php if ($imagem !== ''): ?>
                        <img src="uploads/<?php echo $imagem; ?>" class="card-img-top" alt="<?php echo $nome; ?>" style="max-height: 200px; object-fit: cover;">
                    <?php endif; ?>

                    <div class="card-body">
                        <h4 class="card-title h6"><?php echo $nome; ?></h4>
                        <p class="card-text"><?php echo $descricao; ?></p>
                        <p class="card-text text-success">R$ <?php echo number_format($precoNum, 2, ',', '.'); ?></p>

                        <?php if ($promocao): ?>
                            <span class="badge badge-danger">Em Oferta!</span>
                        <?php endif; ?>

                        <?php if ($saboresTxt !== ''): ?>
                            <p class="card-text">Sabores: <?php echo $saboresTxt; ?></p>
                        <?php endif; ?>

                        <?php if ($isAdmin): ?>
                            <div class="d-flex justify-content-between mt-3">
                                <a href="editar_produto.php?id=<?php echo $id; ?>" class="btn btn-outline-primary btn-sm">Editar</a>
                                <form action="remover_produto.php" method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja remover este produto?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Remover</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

<?php if ($isAdmin): ?>
<script <?php echo alabama_csp_nonce_attr(); ?>>
    // Função para adicionar campos de sabor dinamicamente (máximo 10)
    let saborCount = 1;
    function adicionarSabor() {
        if (saborCount < 10) {
            saborCount++;
            const saboresContainer = document.getElementById('sabores-container');
            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'sabores[]';
            input.className = 'form-control mb-2';
            input.placeholder = 'Sabor ' + saborCount;
            saboresContainer.appendChild(input);
        } else {
            alert('Você pode adicionar no máximo 10 sabores.');
        }
    }
</script>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
