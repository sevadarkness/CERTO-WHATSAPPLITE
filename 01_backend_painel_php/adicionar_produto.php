<?php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';

// Apenas administradores podem adicionar produtos
require_role(['Administrador']);

$erro = null;

/**
 * Salva a imagem no diretório uploads/ com nome seguro.
 *
 * @return string|null Nome do arquivo salvo (relativo) ou null em caso de erro.
 */
function alabama_save_uploaded_product_image(array $file, ?string &$erro): ?string
{
    $erro = null;

    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        $erro = 'Erro no upload da imagem.';
        return null;
    }

    $tmpName  = (string)($file['tmp_name'] ?? '');
    $origName = (string)($file['name'] ?? '');

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $erro = 'Upload inválido.';
        return null;
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if ($ext === '' || !in_array($ext, $allowed, true)) {
        $erro = 'Formato de imagem inválido. Use JPG, PNG, GIF ou WEBP.';
        return null;
    }

    // Verifica se é realmente uma imagem
    if (@getimagesize($tmpName) === false) {
        $erro = 'O arquivo enviado não parece ser uma imagem válida.';
        return null;
    }

    $uploadDirFs = __DIR__ . '/uploads';
    if (!is_dir($uploadDirFs)) {
        if (!@mkdir($uploadDirFs, 0755, true) && !is_dir($uploadDirFs)) {
            $erro = 'Não foi possível criar o diretório de uploads.';
            return null;
        }
    }

    // Nome seguro e não previsível
    try {
        $rand = bin2hex(random_bytes(6));
    } catch (Throwable $e) {
        $rand = substr(hash('sha256', uniqid('', true) . (string)mt_rand()), 0, 12);
    }

    $fileName = 'prod_' . date('Ymd_His') . '_' . $rand . '.' . $ext;
    $destFs   = $uploadDirFs . '/' . $fileName;

    if (!move_uploaded_file($tmpName, $destFs)) {
        $erro = 'Erro ao mover o arquivo para o diretório de uploads.';
        return null;
    }

    // Permissões conservadoras
    @chmod($destFs, 0644);

    return $fileName;
}

/**
 * Extrai sabores do POST (opcional). Quando o campo "sabores" é enviado,
 * valida mínimo 1 e máximo 10.
 */
function alabama_extract_sabores_from_post(array $post, ?string &$erro): array
{
    $erro = null;

    if (!array_key_exists('sabores', $post)) {
        return [];
    }

    $saboresRaw = $post['sabores'];
    if (!is_array($saboresRaw)) {
        $erro = 'Formato inválido de sabores.';
        return [];
    }

    $sabores = [];
    foreach ($saboresRaw as $s) {
        $s = trim((string)$s);
        if ($s === '') {
            continue;
        }
        $sabores[] = $s;
        if (count($sabores) >= 10) {
            break;
        }
    }

    if (count($sabores) === 0) {
        $erro = 'Informe pelo menos 1 sabor.';
        return [];
    }

    return $sabores;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $nome      = trim((string)($_POST['nome'] ?? ''));
    $descricao = trim((string)($_POST['descricao'] ?? ''));
    $precoRaw  = (string)($_POST['preco'] ?? '');
    $promocao  = isset($_POST['promocao']) && (string)($_POST['promocao'] ?? '') === '1' ? 1 : 0;

    if ($nome === '' || $descricao === '' || $precoRaw === '') {
        $erro = 'Preencha nome, descrição e preço.';
    }

    // Normaliza preço
    $preco = (float)str_replace([',', ' '], ['.', ''], $precoRaw);
    if ($erro === null && $preco <= 0) {
        $erro = 'Preço inválido.';
    }

    $sabores = [];
    if ($erro === null) {
        $sabores = alabama_extract_sabores_from_post($_POST, $erro);
    }

    $imagem = null;
    if ($erro === null) {
        $imagem = alabama_save_uploaded_product_image($_FILES['imagem'] ?? [], $erro);
    }

    if ($erro === null && $imagem !== null) {
        $produtoId = 0;

        try {
            $pdo->beginTransaction();

            // 1) Produto
            $stmtProduto = (new \RedeAlabama\Repositories\Screens\AdicionarProdutoRepository($pdo))->prepare_887();
            $stmtProduto->execute([$nome, $descricao, $preco, $imagem, $promocao]);

            $produtoId = (int)$pdo->lastInsertId();

            // 2) Sabores (opcional)
            if ($produtoId > 0 && count($sabores) > 0) {
                $stmtSabor = (new \RedeAlabama\Repositories\Screens\CatalogoRepository($pdo))->prepare_1589();
                foreach ($sabores as $sabor) {
                    $stmtSabor->execute([$produtoId, $sabor]);
                }
            }

            $pdo->commit();

            if (function_exists('log_app_event')) {
                log_app_event('estoque', 'produto_adicionado', [
                    'produto_id' => $produtoId,
                    'nome'       => $nome,
                    'preco'      => $preco,
                    'promocao'   => $promocao,
                    'imagem'     => $imagem,
                    'sabores'    => $sabores,
                    'usuario_id' => $_SESSION['usuario_id'] ?? null,
                ]);
            }

            header('Location: catalogo.php?msg=' . urlencode('Produto adicionado com sucesso!'));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Evita imagem órfã
            @unlink(__DIR__ . '/uploads/' . $imagem);

            $erro = 'Falha ao salvar o produto. Tente novamente.';

            if (function_exists('log_app_event')) {
                log_app_event('estoque', 'produto_adicionar_erro', [
                    'error'      => $e->getMessage(),
                    'produto_id' => $produtoId,
                    'usuario_id' => $_SESSION['usuario_id'] ?? null,
                ]);
            }
        }
    }
}
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
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin.css">
    <title>Adicionar Novo Produto</title>
</head>
<body class="al-body">

<header>
    <h1>Adicionar Novo Produto</h1>
</header>

<section class="adicionar-produto">

    <?php if (is_string($erro) && $erro !== ''): ?>
        <div style="background:#3b0a0a;color:#ffd7d7;padding:10px;border-radius:6px;margin-bottom:12px;">
            <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>

        <label for="nome">Nome:</label>
        <input type="text" id="nome" name="nome" required>

        <label for="descricao">Descrição:</label>
        <textarea id="descricao" name="descricao" required></textarea>

        <label for="preco">Preço:</label>
        <input type="number" id="preco" name="preco" step="0.01" min="0" required>

        <label for="imagem">Imagem:</label>
        <input type="file" id="imagem" name="imagem" accept="image/*" required>

        <label>Sabores (mínimo 1, máximo 10)</label>
        <div id="sabores-container">
            <input type="text" name="sabores[]" class="form-control mb-2" placeholder="Sabor 1" required>
        </div>
        <button type="button" class="btn btn-secondary btn-sm mb-3" onclick="adicionarSabor()">Adicionar Sabor</button>

        <label>Produto em promoção:</label>
        <label>
            <input type="radio" name="promocao" value="1"> Sim
        </label>
        <label>
            <input type="radio" name="promocao" value="0" checked> Não
        </label>

        <button type="submit" class="botao-salvar">Adicionar Produto</button>
    </form>
</section>

<footer>
<?php include 'footer.php'; ?>
</footer>


<script <?php echo alabama_csp_nonce_attr(); ?>>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
