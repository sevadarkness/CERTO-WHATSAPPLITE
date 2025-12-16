<?php
session_start();

// Ensure the session is valid and sanitized
if (!isset($_SESSION['nivel_acesso'])) {
    header('Location: login.php');
    exit();
}

include 'menu_navegacao.php';

// Sanitize session data
$niveis_acesso_validos = ['Administrador', 'Gerente', 'Vendedor'];
$nivel_acesso_usuario = in_array($_SESSION['nivel_acesso'], $niveis_acesso_validos) ? $_SESSION['nivel_acesso'] : 'Vendedor';
?>

<div class="container mt-4">
    <div class="alert alert-warning" role="alert">
        <h4 class="alert-heading">Acesso Restrito</h4>
        <p>Você não tem permissão para acessar esta página.</p>
        <hr>
        <a href="<?php echo ($nivel_acesso_usuario === 'Administrador') ? 'painel_admin.php' : (($nivel_acesso_usuario === 'Gerente') ? 'painel_gerente.php' : 'painel_vendedor_hoje.php'); ?>" class="btn btn-primary">Voltar ao Painel</a>
    </div>
</div>

