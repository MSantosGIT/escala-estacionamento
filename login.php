<?php
require_once __DIR__ . '/includes/functions.php';
if (logado()) redirect('dashboard.php');

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // proteção CSRF
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    }

    // proteção contra força bruta: máx. 5 tentativas a cada 5 min por sessão
    $agora = time();
    $_SESSION['login_tries'] = array_filter($_SESSION['login_tries'] ?? [], fn($t) => $t > $agora - 300);
    if (!$erro && count($_SESSION['login_tries']) >= 5) {
        $erro = 'Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.';
    }

    if (!$erro) {
        $login = trim($_POST['login'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $st = db()->prepare("SELECT * FROM usuarios WHERE login = ?");
        $st->execute([$login]);
        $user = $st->fetch();
        if ($user && password_verify($senha, $user['senha'])) {
            session_regenerate_id(true); // evita fixação de sessão
            $_SESSION['usuario'] = [
                'id'   => $user['id'],
                'nome' => $user['nome'],
                'tipo' => $user['tipo'],
                'colaborador_id' => $user['colaborador_id'],
            ];
            unset($_SESSION['login_tries']);
            redirect('dashboard.php');
        }
        $_SESSION['login_tries'][] = $agora;
        $erro = 'Login ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar · Apoio Externo</title>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#e8843f">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Apoio Externo">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<link rel="icon" href="assets/icons/favicon.png" type="image/png">
<link rel="stylesheet" href="assets/css/style.css?v=4">
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('sw.js').catch(()=>{}));
}
</script>
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <div class="logo">
      <div class="ic">🅿️</div>
      <h1>Apoio Externo</h1>
      <p>Gestão de Escala</p>
    </div>
    <?php if ($erro): ?><div class="flash erro"><?= e($erro) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
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
  </div>
</div>
<footer class="rodape-dev" style="margin-top:1.5rem">
  Desenvolvedor <strong>Marielton M Santos</strong>
  · WhatsApp
  <a href="https://wa.me/5561999116077" target="_blank" rel="noopener">(61) 99911-6077</a>
</footer>
</body>
</html>
