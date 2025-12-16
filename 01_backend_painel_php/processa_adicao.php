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
        $erro = 'Formato de imagem inválido. Envie JPG, PNG, GIF ou WebP.';
        return null;
    }

    // Valida que é realmente uma imagem
    if (@getimagesize($tmpName) === false) {
        $erro = 'Arquivo enviado não parece ser uma imagem válida.';
        return null;
    }

    $uploadDirFs = __DIR__ . '/uploads';
    if (!is_dir($uploadDirFs)) {
        if (!@mkdir($uploadDirFs, 0755, true) && !is_dir($uploadDirFs)) {
            $erro = 'Não foi possível criar o diretório de uploads.';
            return null;
        }
    }

    try {
        $rand = bin2hex(random_bytes(6));
    } catch (Throwable $e) {
        $rand = substr(hash('sha256', uniqid('', true) . (string)mt_rand()), 0, 12);
    }

    $fileName = 'prod_' . date('Ymd_His') . '_' . $rand . '.' . $ext;
    $destFs   = $uploadDirFs . '/' . $fileName;

    if (!@move_uploaded_file($tmpName, $destFs)) {
        $erro = 'Erro ao salvar o arquivo de imagem.';
        return null;
    }

    @chmod($destFs, 0644);

    return $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $nome      = trim((string)($_POST['nome'] ?? ''));
    $descricao = trim((string)($_POST['descricao'] ?? ''));
    $preco     = (float)($_POST['preco'] ?? 0);
    $promocao  = isset($_POST['promocao']) ? (int)($_POST['promocao'] ?? 0) : 0;
    $promocao  = $promocao === 1 ? 1 : 0;

    if ($nome === '' || $descricao === '' || $preco <= 0) {
        $erro = 'Preencha nome, descrição e preço válido.';
    } else {
        $imagem = null;
        if (isset($_FILES['imagem']) && is_array($_FILES['imagem'])) {
            $imagem = alabama_save_uploaded_product_image($_FILES['imagem'], $erro);
        } else {
            $erro = 'Envie uma imagem do produto.';
        }

        if ($imagem !== null) {
            $query = (new \RedeAlabama\Repositories\Screens\ProcessaAdicaoRepository($pdo))->prepare_887();
            $query->execute([$nome, $descricao, $preco, $imagem, $promocao]);

            if (function_exists('log_app_event')) {
                log_app_event('estoque', 'produto_adicionado', [
                    'nome'       => $nome,
                    'preco'      => $preco,
                    'promocao'   => $promocao,
                    'usuario_id' => $_SESSION['usuario_id'] ?? null,
                ]);
            }

            header('Location: catalogo.php?msg=' . urlencode('Produto adicionado com sucesso!'));
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <link rel="stylesheet" href="alabama-theme.css">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin.css">
    <title>Adicionar Novo Produto</title>
</head>
<body>

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
    <p>&copy; <?php echo date('Y'); ?> Rede Alabama. Todos os direitos reservados.</p>
</footer>

</body>
</html>
