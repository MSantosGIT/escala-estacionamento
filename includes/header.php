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
<link rel="stylesheet" href="assets/css/style.css?v=9">
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('sw.js')
      .then(reg => console.log('[PWA] SW registrado:', reg.scope))
      .catch(err => console.warn('[PWA] Falha ao registrar SW:', err));
  });
}
</script>
</head>
<body>
<div class="mobtop no-print">
  <button class="burger" onclick="document.body.classList.toggle('nav-aberta')" aria-label="Menu">☰</button>
  <span class="mob-marca"><img class="marca-img" src="assets/icons/icon-192.png" alt=""> Apoio Externo</span>
</div>

<div class="shade no-print" onclick="document.body.classList.remove('nav-aberta')"></div>

<aside class="sidebar no-print">
  <div class="sb-marca"><img class="marca-img" src="assets/icons/icon-192.png" alt=""> Apoio Externo</div>
  <nav class="sb-nav">
    <?php
      navItem('dashboard.php', '🏠', 'Início', $pg);
      navItem('busca.php', '🔍', 'Buscar veículo', $pg);
      navItem('escalas.php', '📋', 'Escalas', $pg);
      navItem('calendario.php', '📅', 'Calendário', $pg);
      navItem('lista_eventos.php', '🎫', 'Eventos', $pg);
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
        navItem('acessos.php', '🕒', 'Acessos', $pg);
      ?>
    <?php endif; ?>
  </nav>
  <div class="sb-user">
    <div class="sb-user-nome"><?= e($u['nome']) ?></div>
    <div class="sb-user-tipo"><?= e(ucfirst($u['tipo'])) ?></div>
    <a class="sb-sair" href="trocar_senha.php" style="display:block;margin-bottom:.3rem">🔑 Trocar senha</a>
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

<!-- Botão flutuante de instalar (sempre visível em mobile, para quem o banner não apareceu) -->
<button id="pwa-fab" class="no-print" style="display:none;position:fixed;bottom:14px;right:14px;z-index:60;
  background:linear-gradient(120deg,var(--laranja-5),var(--laranja-4));color:#fff;border:none;
  border-radius:50px;padding:.7rem 1rem;font-weight:700;box-shadow:0 6px 18px rgba(0,0,0,.25);cursor:pointer">
  📲 Instalar app
</button>

<script>
let pwaPrompt = null;
const elBanner = () => document.getElementById('pwa-instalar');
const elFab    = () => document.getElementById('pwa-fab');

// detecta se o app já está instalado (rodando em modo standalone)
const jaInstalado = window.matchMedia('(display-mode: standalone)').matches
                 || window.navigator.standalone === true;

function mostrarBanner() {
  if (!sessionStorage.getItem('pwa-dispensado') && elBanner()) elBanner().style.display = 'flex';
}
function mostrarFab() {
  if (!jaInstalado && elFab()) elFab().style.display = 'block';
}
function esconderTudo() {
  if (elBanner()) elBanner().style.display = 'none';
  if (elFab())    elFab().style.display = 'none';
}

// 1) Quando o Chrome considerar o site instalável, guarda o evento e mostra o banner.
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  pwaPrompt = e;
  mostrarBanner();
  mostrarFab();
});

// 2) Se já estiver instalado, esconde tudo.
window.addEventListener('appinstalled', () => { esconderTudo(); pwaPrompt = null; });

// 3) Em mobile, mesmo sem o evento, mostra o FAB com instruções (alguns Androids
//    demoram a disparar beforeinstallprompt). Esconde se for desktop.
window.addEventListener('load', () => {
  if (jaInstalado) return;
  const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
  if (isMobile && !sessionStorage.getItem('pwa-fab-dispensado')) mostrarFab();
});

document.addEventListener('click', (ev) => {
  if (ev.target && (ev.target.id === 'pwa-btn' || ev.target.id === 'pwa-fab')) {
    if (pwaPrompt) {
      // caminho fácil: o Chrome considerou elegível
      pwaPrompt.prompt();
      pwaPrompt.userChoice.finally(() => { esconderTudo(); pwaPrompt = null; });
    } else {
      // caminho manual: instrui como adicionar à tela inicial
      const isiOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
      alert(isiOS
        ? 'Para instalar no iPhone/iPad:\n\n1. Toque no botão Compartilhar (□↑) na barra inferior do Safari\n2. Escolha "Adicionar à Tela de Início"'
        : 'Para instalar no Android:\n\n1. Toque no menu ⋮ (3 pontinhos) no canto superior direito do Chrome\n2. Escolha "Instalar aplicativo" ou "Adicionar à tela inicial"\n\nSe não aparecer essa opção, navegue um pouco pelo sistema e tente de novo.');
    }
  }
  if (ev.target && ev.target.id === 'pwa-x') {
    sessionStorage.setItem('pwa-dispensado', '1');
    if (elBanner()) elBanner().style.display = 'none';
  }
});
</script>
<?php if ($f = flash()): ?>
  <div class="flash <?= e($f['tipo']) ?>"><?= e($f['msg']) ?></div>
<?php endif; ?>

<script>
// Toggle global dos cards recolhíveis (usado nas telas de cadastro)
document.addEventListener('click', (ev) => {
  const btn = ev.target.closest('.btn-toggle');
  if (!btn) return;
  const alvo = document.getElementById(btn.dataset.alvo);
  if (!alvo) return;
  const jaAberto = alvo.classList.contains('aberto');
  // fecha todos os cards e desativa todos os botões do mesmo grupo
  const grupo = btn.closest('.acoes-topo');
  if (grupo) {
    grupo.querySelectorAll('.btn-toggle').forEach(b => b.classList.remove('ativo'));
    grupo.parentElement.querySelectorAll('.card-recolhivel').forEach(c => c.classList.remove('aberto'));
  }
  if (!jaAberto) {
    alvo.classList.add('aberto');
    btn.classList.add('ativo');
  }
});

// Máscara global de telefone — aplica em todo input com class="mask-tel"
// Formatos: (DD) 9999-9999 (8 dígitos) ou (DD) 99999-9999 (9 dígitos)
function formatarTelefone(s){
  s = (s || '').replace(/\D/g,'').slice(0,11);
  if (s.length === 0) return '';
  if (s.length <= 2)  return '(' + s;
  if (s.length <= 6)  return '(' + s.slice(0,2) + ') ' + s.slice(2);
  if (s.length <= 10) return '(' + s.slice(0,2) + ') ' + s.slice(2,6) + '-' + s.slice(6);
  return '(' + s.slice(0,2) + ') ' + s.slice(2,7) + '-' + s.slice(7);
}
document.addEventListener('input', (ev) => {
  const el = ev.target;
  if (el && el.matches && el.matches('input.mask-tel')) {
    const pos = el.selectionStart;
    const antes = el.value.length;
    el.value = formatarTelefone(el.value);
    const depois = el.value.length;
    // tenta manter o cursor onde estava (compensando os caracteres da máscara)
    el.setSelectionRange(pos + (depois - antes), pos + (depois - antes));
  }
});
// aplica também no carregamento (para valores já preenchidos pelo PHP)
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input.mask-tel').forEach(i => i.value = formatarTelefone(i.value));
});
</script>
