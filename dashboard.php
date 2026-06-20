<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/trocas.php';
require_once __DIR__ . '/includes/escala_engine.php';
exigirLogin();
$pdo = db();
$u = usuario();
$meuColabId = (int)($u['colaborador_id'] ?? 0);

// gatilho: no 1º acesso do ADMIN a partir do dia 20, gera a escala do mês seguinte
$geracaoAgora = null;
if (ehAdmin()) {
    $geracaoAgora = dispararGeracaoAutomatica($pdo);
}

// alerta "agenda do mês seguinte montada" para todos (enquanto for o mês corrente >= dia 20
// ou já no mês alvo): considera montada se o mês seguinte consta em geracao_mensal
$prox = (new DateTime('today'))->modify('first day of next month');
$mesProx = (int)$prox->format('n'); $anoProx = (int)$prox->format('Y');
$agendaMontada = escalaMensalJaGerada($pdo, $mesProx, $anoProx);
$nomeMesProx = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'][$mesProx];

// notificações do usuário atual
$minhasNotif = $meuColabId ? notificacoesColaborador($pdo, $meuColabId) : [];
$notifAdmin  = ehAdmin() ? notificacoesAdmin($pdo) : [];

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

<?php /* Resultado da geração automática (aparece ao admin que disparou) */ ?>
<?php if ($geracaoAgora): ?>
<div class="flash sucesso">
  ⚙️ Escala de <?= e($nomeMesProx) ?> gerada automaticamente:
  <?= (int)$geracaoAgora['completas'] ?> evento(s) completos<?php if ($geracaoAgora['parciais']>0): ?>,
  <?= (int)$geracaoAgora['parciais'] ?> parcial(is) para ajuste manual<?php endif; ?>.
  <?php if (!empty($geracaoAgora['avisos'])): ?>
    <br><span class="muted" style="font-size:.85rem"><?= e(implode(' · ', array_slice($geracaoAgora['avisos'],0,4))) ?><?= count($geracaoAgora['avisos'])>4?'…':'' ?></span>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php /* Alerta a TODOS: agenda do mês seguinte montada */ ?>
<?php if ($agendaMontada): ?>
<div class="flash sucesso" style="display:flex;justify-content:space-between;align-items:center;gap:1rem">
  <span>📅 A agenda de <?= e($nomeMesProx) ?> já está montada! Confira sua escala.</span>
  <a href="calendario.php?mes=<?= $mesProx ?>&ano=<?= $anoProx ?>" class="btn sm">Ver calendário</a>
</div>
<?php endif; ?>

<?php /* Lembrete para colaborador marcar disponibilidade (antes do dia 20) */ ?>
<?php if ($meuColabId && (int)date('j') <= 20 && !$agendaMontada): ?>
<div class="flash erro" style="display:flex;justify-content:space-between;align-items:center;gap:1rem">
  <span>📝 Marque sua indisponibilidade de <?= e($nomeMesProx) ?> até o dia 20.</span>
  <a href="disponibilidade.php" class="btn sm">Marcar agora</a>
</div>
<?php endif; ?>

<?php /* Notificações pessoais do colaborador */ ?>
<?php foreach ($minhasNotif as $n): ?>
<div class="flash <?= strpos($n['mensagem'],'recusada')!==false||strpos($n['mensagem'],'recusou')!==false ? 'erro':'sucesso' ?>" style="display:flex;justify-content:space-between;align-items:center;gap:1rem">
  <span>🔔 <?= e($n['mensagem']) ?></span>
  <a href="trocas.php" class="btn sm">Ver trocas</a>
</div>
<?php endforeach; ?>

<?php /* Notificações para o admin */ ?>
<?php if (ehAdmin()): foreach ($notifAdmin as $n): ?>
<div class="flash sucesso" style="display:flex;justify-content:space-between;align-items:center;gap:1rem">
  <span>🔁 <?= e($n['mensagem']) ?></span>
  <a href="trocas.php" class="btn sm">Confirmar trocas</a>
</div>
<?php endforeach; endif; ?>

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
        <td><?= $p['num_colaboradores'] ?><?= $p['exige_lider']?' · exige A1':'' ?></td>
        <td><span class="badge <?= $p['status']==='preenchida'?'ok':'warn' ?>"><?= ucfirst($p['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$proximas): ?><tr><td colspan="5" class="muted">Nenhum evento futuro.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
