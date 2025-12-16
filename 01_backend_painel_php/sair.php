<?php
require_once __DIR__ . '/session_bootstrap.php';

// Destruir todas as variáveis de sessão
session_unset();

// Destruir a sessão
session_destroy();

// Redireciona o usuário para a página inicial (index.php)
header("Location: index.php");
exit;
?>