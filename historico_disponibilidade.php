<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();
$pdo = db();

$u = usuario();
$ehAdm = ehAdmin();
$meuColabId = (int)($u['colaborador_id'] ?? 0);

// aba ativa (evento | colaborador | mes)
$aba = $_GET['aba'] ?? 'mes';
if (!in_array($aba, ['evento','colaborador','mes'], true)) $aba = 'mes';
// colaborador comum não tem visão "por colaborador" (seria só ele mesmo)
if (!$ehAdm && $aba === 'colaborador') $aba = 'mes';

// ---- busca todos os registros (admin vê todos; colaborador só os seus) ----
$sql = "
  SELECT i.id, i.criado_em,
         c.id AS colaborador_id, c.nome AS colaborador_nome, c.nivel,
         e.id AS escala_id, e.data_evento, e.evento, e.horario_chegada, e.mes, e.ano
  FROM indisponibilidades i
  JOIN colaboradores c ON c.id = i.colaborador_id
  JOIN escalas e ON e.id = i.escala_id
";
$params = [];
if (!$ehAdm) {
    $sql .= " WHERE i.colaborador_id = ?";
    $params[] = $meuColabId;
}
$sql .= " ORDER BY e.data_evento DESC, c.nome";
$st = $pdo->prepare($sql);
$st->execute($params);
$registros = $st->fetchAll();

$meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
          7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];

// ---- agrupamento: por evento ----
$porEvento = [];
foreach ($registros as $r) {
    $eid = (int)$r['escala_id'];
    if (!isset($porEvento[$eid])) {
        $porEvento[$eid] = [
            'evento' => $r['evento'], 'data_evento' => $r['data_evento'],
            'horario' => $r['horario_chegada'], 'itens' => [],
        ];
    }
    $porEvento[$eid]['itens'][] = $r;
}
uasort($porEvento, fn($a,$b) => strcmp($b['data_evento'], $a['data_evento']));

// ---- agrupamento: por colaborador (só admin usa) ----
$porColaborador = [];
foreach ($registros as $r) {
    $cid = (int)$r['colaborador_id'];
    if (!isset($porColaborador[$cid])) {
        $porColaborador[$cid] = ['nome' => $r['colaborador_nome'], 'nivel' => $r['nivel'], 'itens' => []];
    }
    $porColaborador[$cid]['itens'][] = $r;
}
uasort($porColaborador, fn($a,$b) => strcmp($a['nome'], $b['nome']));
foreach ($porColaborador as &$pc) {
    usort($pc['itens'], fn($a,$b) => strcmp($b['data_evento'], $a['data_evento']));
}
unset($pc);

// ---- agrupamento: por mês ----
$porMes = [];
foreach ($registros as $r) {
    $chave = sprintf('%04d-%02d', $r['ano'], $r['mes']);
    if (!isset($porMes[$chave])) {
        $porMes[$chave] = ['mes' => (int)$r['mes'], 'ano' => (int)$r['ano'], 'itens' => []];
    }
    $porMes[$chave]['itens'][] = $r;
}
krsort($porMes);
foreach ($porMes as &$pm) {
    usort($pm['itens'], fn($a,$b) => strcmp($b['data_evento'], $a['data_evento']) ?: strcmp($a['colaborador_nome'], $b['colaborador_nome']));
}
unset($pm);

$totalRegistros = count($registros);

$titulo = 'Histórico de disponibilidade';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Histórico de disponibilidade</h1>
<p class="page-sub">
  <?= $ehAdm ? 'Registros de indisponibilidade de toda a equipe' : 'Seus registros de indisponibilidade' ?>
  — <span class="badge ok"><?= $totalRegistros ?></span>
</p>

<div class="abas-hist">
  <a href="?aba=mes" class="aba-btn <?= $aba==='mes'?'ativa':'' ?>">🗓️ Por mês</a>
  <a href="?aba=evento" class="aba-btn <?= $aba==='evento'?'ativa':'' ?>">🎫 Por evento</a>
  <?php if ($ehAdm): ?>
  <a href="?aba=colaborador" class="aba-btn <?= $aba==='colaborador'?'ativa':'' ?>">👤 Por colaborador</a>
  <?php endif; ?>
</div>

<?php if (!$totalRegistros): ?>
  <div class="card"><p class="muted">Nenhum registro de indisponibilidade encontrado.</p></div>
<?php endif; ?>

<?php if ($aba === 'mes' && $totalRegistros): ?>
  <?php foreach ($porMes as $grupo): ?>
  <div class="card hist-grupo">
    <h2><?= e($meses[$grupo['mes']]) ?> de <?= $grupo['ano'] ?> <span class="badge ok"><?= count($grupo['itens']) ?></span></h2>
    <ul class="hist-lista">
      <?php foreach ($grupo['itens'] as $it): ?>
      <li>
        <span class="hist-nivel nivel-<?= e($it['nivel']) ?>"><?= e($it['colaborador_nome']) ?></span>
        <span class="hist-evt"><?= e($it['evento']) ?> · <?= date('d/m/Y', strtotime($it['data_evento'])) ?></span>
        <span class="hist-quando">Registrado em <?= date('d/m/Y H:i', strtotime($it['criado_em'])) ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($aba === 'evento' && $totalRegistros): ?>
  <?php foreach ($porEvento as $grupo): ?>
  <div class="card hist-grupo">
    <h2><?= e($grupo['evento']) ?> <span class="muted" style="font-weight:400;font-size:.85rem">— <?= date('d/m/Y', strtotime($grupo['data_evento'])) ?> ⏰ <?= substr($grupo['horario'],0,5) ?></span>
      <span class="badge ok"><?= count($grupo['itens']) ?></span></h2>
    <ul class="hist-lista">
      <?php foreach ($grupo['itens'] as $it): ?>
      <li>
        <span class="hist-nivel nivel-<?= e($it['nivel']) ?>"><?= e($it['colaborador_nome']) ?></span>
        <span class="hist-quando">Registrado em <?= date('d/m/Y H:i', strtotime($it['criado_em'])) ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($aba === 'colaborador' && $ehAdm && $totalRegistros): ?>
  <?php foreach ($porColaborador as $grupo): ?>
  <div class="card hist-grupo">
    <h2 class="nivel-<?= e($grupo['nivel']) ?>"><?= e($grupo['nome']) ?> <span class="badge ok"><?= count($grupo['itens']) ?></span></h2>
    <ul class="hist-lista">
      <?php foreach ($grupo['itens'] as $it): ?>
      <li>
        <span class="hist-evt"><?= e($it['evento']) ?> · <?= date('d/m/Y', strtotime($it['data_evento'])) ?></span>
        <span class="hist-quando">Registrado em <?= date('d/m/Y H:i', strtotime($it['criado_em'])) ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<style>
.abas-hist{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.2rem}
.aba-btn{flex:1;min-width:140px;text-align:center;background:#fff;color:var(--laranja-6);
  border:1px solid var(--borda);border-radius:10px;padding:.6rem .8rem;font-weight:700;
  font-size:.9rem;text-decoration:none}
.aba-btn.ativa{background:linear-gradient(120deg,var(--laranja-5),var(--laranja-4));color:#fff;border-color:transparent}
.hist-grupo{margin-bottom:1.1rem}
.hist-grupo h2{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
.hist-lista{list-style:none;padding:0;margin:0}
.hist-lista li{display:flex;align-items:center;gap:.7rem;flex-wrap:wrap;padding:.4rem 0;
  font-size:.9rem;border-bottom:1px dashed var(--borda)}
.hist-lista li:last-child{border-bottom:none}
.hist-nivel{font-weight:700;min-width:150px}
.nivel-lider{color:#9a4f12}.nivel-pleno{color:#1f6b86}.nivel-junior{color:#2f7d49}
.hist-evt{color:#444;flex:1}
.hist-quando{color:var(--texto-suave);font-size:.8rem;white-space:nowrap}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
