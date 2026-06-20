<?php
require_once __DIR__ . '/functions.php';
exigirLogin();
$pg = basename($_SERVER['PHP_SELF']);
$u  = usuario();
function navItem($href, $icone, $rotulo, $pg) {
    $ativo = $pg === $href ? ' on' : '';
    echo '<a class="sb-link' . $ativo . '" href="' . $href . '"><span class="ic">' . $icone . '</span> ' . e($rotulo) . '</a>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo ?? 'Apoio Externo · Gestão de Escala') ?></title>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#e8843f">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Apoio Externo">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<link rel="icon" href="assets/icons/favicon.png" type="image/png">
<link rel="stylesheet" href="assets/css/style.css?v=3">
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('sw.js').catch(()=>{}));
}
</script>
</head>
<body>
<div class="mobtop no-print">
  <button class="burger" onclick="document.body.classList.toggle('nav-aberta')" aria-label="Menu">☰</button>
  <span class="mob-marca"><span class="dot"></span> Apoio Externo</span>
</div>

<div class="shade no-print" onclick="document.body.classList.remove('nav-aberta')"></div>

<aside class="sidebar no-print">
  <div class="sb-marca"><span class="dot"></span> Apoio Externo</div>
  <nav class="sb-nav">
    <?php
      navItem('dashboard.php', '🏠', 'Início', $pg);
      navItem('busca.php', '🔍', 'Buscar veículo', $pg);
      navItem('escalas.php', '📋', 'Escalas', $pg);
      navItem('calendario.php', '📅', 'Calendário', $pg);
      navItem('trocas.php', '🔁', 'Trocas', $pg);
      navItem('disponibilidade.php', '✅', 'Disponibilidade', $pg);
      navItem('historico.php', '🕘', 'Histórico', $pg);
      navItem('relatorio_anual.php', '📊', 'Relatório anual', $pg);
      if (ehAdmin()):
    ?>
      <div class="sb-sep">Administração</div>
      <?php
        navItem('colaboradores.php', '👥', 'Colaboradores', $pg);
        navItem('admin_escala.php', '🛠️', 'Ajustar escala', $pg);
        navItem('admin_indisponibilidade.php', '🚫', 'Indisp. (Admin)', $pg);
        navItem('veiculos.php', '🚗', 'Veículos', $pg);
        navItem('usuarios.php', '👤', 'Usuários', $pg);
      ?>
    <?php endif; ?>
  </nav>
  <div class="sb-user">
    <div class="sb-user-nome"><?= e($u['nome']) ?></div>
    <div class="sb-user-tipo"><?= e(ucfirst($u['tipo'])) ?></div>
    <a class="sb-sair" href="logout.php">↪ Sair</a>
  </div>
</aside>

<main class="conteudo">
<div id="pwa-instalar" class="no-print" style="display:none;align-items:center;justify-content:space-between;gap:1rem;background:linear-gradient(120deg,var(--laranja-5),var(--laranja-4));color:#fff;padding:.7rem 1rem;border-radius:12px;margin-bottom:1rem">
  <span>📲 Instale o app na tela inicial para acesso rápido.</span>
  <span style="white-space:nowrap">
    <button id="pwa-btn" class="btn sm" style="background:#fff;color:var(--laranja-6)">Instalar</button>
    <button id="pwa-x" class="btn sm sec" style="background:rgba(255,255,255,.25);color:#fff">Agora não</button>
  </span>
</div>
<script>
let pwaPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  pwaPrompt = e;
  if (!sessionStorage.getItem('pwa-dispensado')) {
    const b = document.getElementById('pwa-instalar');
    if (b) b.style.display = 'flex';
  }
});
document.addEventListener('click', (ev) => {
  if (ev.target && ev.target.id === 'pwa-btn' && pwaPrompt) {
    pwaPrompt.prompt();
    pwaPrompt.userChoice.finally(() => {
      document.getElementById('pwa-instalar').style.display = 'none';
      pwaPrompt = null;
    });
  }
  if (ev.target && ev.target.id === 'pwa-x') {
    sessionStorage.setItem('pwa-dispensado', '1');
    document.getElementById('pwa-instalar').style.display = 'none';
  }
});
</script>
<?php if ($f = flash()): ?>
  <div class="flash <?= e($f['tipo']) ?>"><?= e($f['msg']) ?></div>
<?php endif; ?>
