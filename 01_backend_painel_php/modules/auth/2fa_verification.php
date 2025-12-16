<?php
session_start();
include 'db.php';
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_SESSION['2fa_code'])) {
    $code = rand(100000, 999999);
    $_SESSION['2fa_code'] = $code;
    mail($_SESSION['email'], "Seu código 2FA", "Código: $code");
    echo "Código enviado.";
  } else {
    if ($_POST['codigo'] == $_SESSION['2fa_code']) {
      $_SESSION['autenticado'] = true;
      echo "✅ Acesso liberado";
    } else {
      echo "❌ Código incorreto.";
    }
  }
} else {
  echo '<form method="post">Código: <input name="codigo"><button>Verificar</button></form>';
}
?>