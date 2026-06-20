<?php
require_once __DIR__ . '/functions.php';
exigirLogin();
$pg = basename($_SERVER['PHP_SELF']);
$u  = usuario();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo ?? 'Escala de Estacionamento') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
  <div class="marca"><span class="dot"></span> EstaciÔ · Escala de Apoio</div>
  <nav>
    <a href="dashboard.php" class="<?= $pg==='dashboard.php'?'ativo':'' ?>">Início</a>
    <a href="busca.php" class="<?= $pg==='busca.php'?'ativo':'' ?>">Buscar veículo</a>
    <a href="escalas.php" class="<?= $pg==='escalas.php'?'ativo':'' ?>">Escalas</a>
    <a href="calendario.php" class="<?= $pg==='calendario.php'?'ativo':'' ?>">Calendário</a>
    <?php if (ehAdmin()): ?>
      <a href="colaboradores.php" class="<?= $pg==='colaboradores.php'?'ativo':'' ?>">Colaboradores</a>
      <a href="veiculos.php" class="<?= $pg==='veiculos.php'?'ativo':'' ?>">Veículos</a>
      <a href="usuarios.php" class="<?= $pg==='usuarios.php'?'ativo':'' ?>">Usuários</a>
    <?php endif; ?>
    <a href="historico.php" class="<?= $pg==='historico.php'?'ativo':'' ?>">Histórico</a>
    <span class="user"><?= e($u['nome']) ?> · <?= e(ucfirst($u['tipo'])) ?></span>
    <a href="logout.php">Sair</a>
  </nav>
</header>
<main class="container">
<?php if ($f = flash()): ?>
  <div class="flash <?= e($f['tipo']) ?>"><?= e($f['msg']) ?></div>
<?php endif; ?>
