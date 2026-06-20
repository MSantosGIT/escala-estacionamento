<?php
require_once __DIR__ . '/includes/functions.php';
if (logado()) redirect('dashboard.php');

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $st = db()->prepare("SELECT * FROM usuarios WHERE login = ?");
    $st->execute([$login]);
    $user = $st->fetch();
    if ($user && password_verify($senha, $user['senha'])) {
        $_SESSION['usuario'] = [
            'id'   => $user['id'],
            'nome' => $user['nome'],
            'tipo' => $user['tipo'],
            'colaborador_id' => $user['colaborador_id'],
        ];
        redirect('dashboard.php');
    }
    $erro = 'Login ou senha inválidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar · Escala de Estacionamento</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <div class="logo">
      <div class="ic">🅿️</div>
      <h1>Escala de Apoio</h1>
      <p>Sistema de gestão do estacionamento</p>
    </div>
    <?php if ($erro): ?><div class="flash erro"><?= e($erro) ?></div><?php endif; ?>
    <form method="post">
      <div style="margin-bottom:1rem">
        <label>Login</label>
        <input name="login" autofocus required>
      </div>
      <div style="margin-bottom:1.4rem">
        <label>Senha</label>
        <input type="password" name="senha" required>
      </div>
      <button class="btn" style="width:100%;justify-content:center">Entrar</button>
    </form>
    <p class="muted" style="text-align:center;margin-top:1.2rem">admin / admin123</p>
  </div>
</div>
</body>
</html>
