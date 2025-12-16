<?php
include_once 'logger.php';
session_start();
if (!isset($_SESSION['usuario']) || !isset($_SESSION['nivel'])) {
  header("Location: /modules/auth/login.html");
  exit();
}
if (time() - $_SESSION['timeout'] > 1800) {
  session_destroy();
  header("Location: /modules/auth/login.html?expired=1");
  exit();
}
$_SESSION['timeout'] = time();

include_once "db.php";
function temPermissao($modulo) {
  $nivel = $_SESSION['nivel'];
  $permissoes = [
    'admin' => ['dashboard', 'relatorios', 'usuarios'],
    'vendedor' => ['dashboard'],
    'suporte' => ['dashboard', 'relatorios']
  ];
  return in_array($modulo, $permissoes[$nivel] ?? []);
}
?>
