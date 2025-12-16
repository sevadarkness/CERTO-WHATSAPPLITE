<?php
include '../auth/middleware.php';
if (!temPermissao('usuarios')) die("Acesso negado.");
include '../auth/db.php';
include '../auth/logger.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = $_POST['email'];
  $justificativa = $_POST['justificativa'];
  $acao = $_POST['acao']; // suspender ou deletar
  if ($acao === 'suspender') {
    $pdo->prepare("UPDATE usuarios SET status = 'inativo' WHERE email = ?")->execute([$email]);
    registrarLog("Suspendeu usuário $email | $justificativa");
  } elseif ($acao === 'deletar') {
    $pdo->prepare("DELETE FROM usuarios WHERE email = ?")->execute([$email]);
    registrarLog("Deletou usuário $email | $justificativa");
  }
  echo "Ação realizada.";
} else {
?>
<form method="post">
  Email do usuário: <input name="email" required><br>
  Justificativa: <input name="justificativa" required><br>
  <button name="acao" value="suspender">❌ Suspender</button>
  <button name="acao" value="deletar">🗑️ Excluir</button>
</form>
<?php } ?>
