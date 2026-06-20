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
<link rel="stylesheet" href="assets/css/style.css?v=2">
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
<?php if ($f = flash()): ?>
  <div class="flash <?= e($f['tipo']) ?>"><?= e($f['msg']) ?></div>
<?php endif; ?>
