<?php
declare(strict_types=1);

use RedeAlabama\Repositories\UsuarioRepository;
use RedeAlabama\Services\Auth\LoginService;



require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/../app/Support/Env.php';
require_once __DIR__ . '/../app/Support/Config.php';
require_once __DIR__ . '/../app/Repositories/BaseRepository.php';
require_once __DIR__ . '/../app/Repositories/UsuarioRepository.php';
require_once __DIR__ . '/../app/Services/Auth/LoginService.php';

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telefone = $_POST['telefone'] ?? '';
    $senha    = $_POST['senha'] ?? '';
    $lembrar  = isset($_POST['lembrar']);

    $repo    = new UsuarioRepository($pdo);
    $service = new LoginService($repo);

    $result = $service->authenticate($telefone, $senha, $lembrar);

    if ($result['ok']) {
        $user = $result['user'];

        // Proteção contra fixation: novo ID após login
        session_regenerate_id(true);

        $_SESSION['usuario_id']   = $user['id'] ?? null;
        $_SESSION['nivel_acesso'] = $user['nivel_acesso'] ?? null;
        $_SESSION['nome_usuario'] = $user['nome'] ?? null;

        header('Location: ../painel_vendedor_hoje.php');
        exit;
    }

    $erro = $result['error'];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Login - Rede Alabama V28</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65"
          crossorigin="anonymous">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h1 class="h4 mb-4 text-center">Painel Rede Alabama</h1>

                    <?php if ($erro !== null): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-3">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="telefone" name="telefone"
                                   autocomplete="tel" required>
                        </div>
                        <div class="mb-3">
                            <label for="senha" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="senha" name="senha"
                                   autocomplete="current-password" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="lembrar" name="lembrar">
                            <label class="form-check-label" for="lembrar">Lembrar de mim</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Entrar</button>
                    </form>
                </div>
            </div>
            <p class="text-muted small mt-3 text-center">
                Versão V28 (login via Service + Repository, sem SQL na view)
            </p>
        </div>
    </div>
</div>
</body>
</html>
