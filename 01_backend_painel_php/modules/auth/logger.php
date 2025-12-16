<?php
include 'db.php';
function registrarLog($acao) {
  $email = $_SESSION['email'] ?? 'anônimo';
  $pdo = db();
  $stmt = $pdo->prepare("INSERT INTO logs (usuario_email, acao) VALUES (?, ?)");
  $stmt->execute([$email, $acao]);
}
?>
