<?php
declare(strict_types=1);

// DirectoryIndex aponta para index.php; para evitar duas telas de login diferentes
// (e inconsistência de sessão/cookies), este index apenas redireciona.

require_once __DIR__ . '/session_bootstrap.php';

// Se já estiver autenticado, envia para a home correta
if (!empty($_SESSION['usuario_id'])) {
    $nivel = (string)($_SESSION['nivel_acesso'] ?? '');

    if ($nivel === 'Administrador') {
        header('Location: painel_admin.php');
    } elseif ($nivel === 'Gerente') {
        header('Location: painel_gerente.php');
    } else {
        // Vendedor / outros perfis
        header('Location: painel_vendedor_hoje.php');
    }
    exit;
}

// Não autenticado -> tela de login principal
header('Location: login.php');
exit;
