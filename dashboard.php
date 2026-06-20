<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();
$pdo = db();

$totColab  = $pdo->query("SELECT COUNT(*) FROM colaboradores WHERE ativo=1")->fetchColumn();
$totVeic   = $pdo->query("SELECT COUNT(*) FROM veiculos")->fetchColumn();
$totEsc    = $pdo->query("SELECT COUNT(*) FROM escalas")->fetchColumn();
$abertas   = $pdo->query("SELECT COUNT(*) FROM escalas WHERE status='aberta'")->fetchColumn();
$pendVeic  = $pdo->query("SELECT COUNT(*) FROM veiculos WHERE aprovado=0")->fetchColumn();

$proximas = $pdo->query(
  "SELECT * FROM escalas WHERE data_evento >= CURDATE() ORDER BY data_evento ASC LIMIT 6"
)->fetchAll();

$titulo = 'Início';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Painel</h1>
<p class="page-sub">Visão geral do sistema de apoio ao estacionamento.</p>

<?php if (ehAdmin() && $pendVeic > 0): ?>
<div class="flash erro" style="display:flex;justify-content:space-between;align-items:center;gap:1rem">
  <span>🚗 <?= $pendVeic ?> veículo(s) de autocadastro aguardando aprovação.</span>
  <a href="veiculos.php" class="btn sm">Revisar agora</a>
</div>
<?php endif; ?>

<div class="grid cols-4">
  <div class="stat"><div class="n"><?= $totColab ?></div><div class="l">Colaboradores ativos</div></div>
  <div class="stat"><div class="n"><?= $totVeic ?></div><div class="l">Veículos cadastrados</div></div>
  <div class="stat"><div class="n"><?= $totEsc ?></div><div class="l">Escalas no sistema</div></div>
  <div class="stat"><div class="n"><?= $abertas ?></div><div class="l">Escalas em aberto</div></div>
</div>

<div class="card" style="margin-top:1.4rem">
  <div class="flex-between">
    <h2>Próximos eventos</h2>
    <a href="escalas.php" class="btn sm">Ver todas as escalas</a>
  </div>
  <table>
    <thead><tr><th>Data</th><th>Evento</th><th>Chegada</th><th>Vagas</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($proximas as $p): ?>
      <tr>
        <td><?= date('d/m/Y', strtotime($p['data_evento'])) ?></td>
        <td><?= e($p['evento']) ?></td>
        <td><?= substr($p['horario_chegada'],0,5) ?></td>
        <td><?= $p['num_colaboradores'] ?><?= $p['exige_lider']?' · exige líder':'' ?></td>
        <td><span class="badge <?= $p['status']==='preenchida'?'ok':'warn' ?>"><?= ucfirst($p['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$proximas): ?><tr><td colspan="5" class="muted">Nenhum evento futuro.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
