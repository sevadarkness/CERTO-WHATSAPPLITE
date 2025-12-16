<?php
session_start();
include 'db.php';
$raw = json_decode(file_get_contents("php://input"), true);
$email = $raw['email'] ?? '';
$nome = $raw['nome'] ?? '';
$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$usuario) {
  $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email) VALUES (?, ?)");
  $stmt->execute([$nome, $email]);
  $nivel = 'vendedor';
} else {
  $nivel = $usuario['nivel'];
  if ($usuario['status'] === 'inativo') {
    echo json_encode(['erro' => 'Usuário inativo']);
    exit;
  }
  $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE email = ?")->execute([$email]);
}
$_SESSION['usuario'] = $nome;
$_SESSION['email'] = $email;
$_SESSION['nivel'] = $nivel;
$_SESSION['timeout'] = time();
echo json_encode(['ok' => true]);
?>
