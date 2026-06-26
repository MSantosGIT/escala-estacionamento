<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();
$pdo = db();
$ehAdm = ehAdmin();

// ---- excluir registro (só admin) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'excluir') {
    validarCSRF();
    if (ehAdmin()) {
        $eid = (int)($_POST['escala_id'] ?? 0);
        if ($eid) {
            $pdo->prepare("DELETE FROM carros_evento WHERE escala_id = ?")->execute([$eid]);
            flash('Registro excluído.');
        }
    }
    redirect('carros_evento.php');
}

// ---- salvar/atualizar registro ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $escalaId = (int)($_POST['escala_id'] ?? 0);
    $qEst = max(0, (int)($_POST['qtd_estacionamento'] ?? 0));
    $qAnx = max(0, (int)($_POST['qtd_anexo'] ?? 0));
    $qExt = max(0, (int)($_POST['qtd_externo'] ?? 0));

    if (!$escalaId) {
        flash('Selecione um evento.', 'erro');
    } else {
        // insere ou atualiza (UNIQUE em escala_id)
        $st = $pdo->prepare(
          "INSERT INTO carros_evento (escala_id, qtd_estacionamento, qtd_anexo, qtd_externo, registrado_por)
           VALUES (?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE
             qtd_estacionamento = VALUES(qtd_estacionamento),
             qtd_anexo = VALUES(qtd_anexo),
             qtd_externo = VALUES(qtd_externo),
             atualizado_em = NOW()"
        );
        $st->execute([$escalaId, $qEst, $qAnx, $qExt, (int)(usuario()['id'] ?? 0)]);
        flash('Registro salvo com sucesso.');
    }
    redirect('carros_evento.php');
}

// ---- filtro de período ----
$periodo = $_GET['periodo'] ?? 'todos';
$condData = '';
if ($periodo === 'mes') {
    $condData = "AND e.data_evento >= '" . date('Y-m-01') . "' AND e.data_evento <= '" . date('Y-m-t') . "'";
} elseif ($periodo === 'ano') {
    $condData = "AND YEAR(e.data_evento) = " . (int)date('Y');
}

// ---- eventos disponíveis para registro (todos com escala) ----
$eventos = $pdo->query(
  "SELECT id, data_evento, evento, horario_chegada
   FROM escalas ORDER BY data_evento DESC, horario_chegada"
)->fetchAll();

// ---- registro selecionado para edição (admin) ----
$editar = null;
if ($ehAdm && isset($_GET['editar'])) {
    $st = $pdo->prepare(
      "SELECT ce.*, e.evento, e.data_evento
       FROM carros_evento ce JOIN escalas e ON e.id = ce.escala_id
       WHERE ce.escala_id = ?"
    );
    $st->execute([(int)$_GET['editar']]);
    $editar = $st->fetch();
}

// ---- registros existentes (com filtro de período) ----
$registros = $pdo->query(
  "SELECT ce.id, ce.escala_id,
          ce.qtd_estacionamento, ce.qtd_anexo, ce.qtd_externo,
          (ce.qtd_estacionamento + ce.qtd_anexo + ce.qtd_externo) AS total_veic,
          e.data_evento, e.evento, e.horario_chegada
   FROM carros_evento ce
   JOIN escalas e ON e.id = ce.escala_id
   WHERE 1=1 $condData
   ORDER BY e.data_evento DESC, e.horario_chegada DESC"
)->fetchAll();

// ---- estatísticas ----
$total = 0; $maior = 0; $qtdEv = count($registros);
$totEst = 0; $totAnx = 0; $totExt = 0;
foreach ($registros as $r) {
    $tv = (int)$r['total_veic'];
    $total += $tv;
    $totEst += (int)$r['qtd_estacionamento'];
    $totAnx += (int)$r['qtd_anexo'];
    $totExt += (int)$r['qtd_externo'];
    if ($tv > $maior) $maior = $tv;
}
$media = $qtdEv ? round($total / $qtdEv) : 0;

// dados do gráfico (ordem cronológica crescente, até 12 últimos)
$grafico = array_reverse(array_slice($registros, 0, 12));
$maxGraf = 1;
foreach ($grafico as $g) if ((int)$g['total_veic'] > $maxGraf) $maxGraf = (int)$g['total_veic'];

$meses = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];

$titulo = 'Carros por evento';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Carros por evento</h1>
<p class="page-sub">Registre a movimentação de veículos em cada evento.</p>

<div class="card" id="form-registro">
  <h2><?= $editar ? 'Editar registro' : 'Registrar movimento' ?></h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
    <div style="margin-bottom:1rem">
      <label>Evento</label>
      <select name="escala_id" required>
        <option value="">— selecione —</option>
        <?php foreach ($eventos as $ev): ?>
          <option value="<?= $ev['id'] ?>" <?= ($editar && $editar['escala_id']==$ev['id']) ? 'selected' : '' ?>>
            <?= date('d/m/Y', strtotime($ev['data_evento'])) ?> — <?= e($ev['evento']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <div><label>Qtde no estacionamento</label>
        <input type="number" name="qtd_estacionamento" class="campo-qtd" min="0" value="<?= (int)($editar['qtd_estacionamento'] ?? 0) ?>" required></div>
      <div><label>Qtde em anexo</label>
        <input type="number" name="qtd_anexo" class="campo-qtd" min="0" value="<?= (int)($editar['qtd_anexo'] ?? 0) ?>" required></div>
      <div><label>Qtde externo</label>
        <input type="number" name="qtd_externo" class="campo-qtd" min="0" value="<?= (int)($editar['qtd_externo'] ?? 0) ?>" required></div>
    </div>
    <div class="total-veic">
      Total de veículos: <span id="totalVeic">0</span>
    </div>
    <button class="btn" style="margin-top:.8rem"><?= $editar ? 'Salvar alterações' : 'Salvar registro' ?></button>
    <?php if ($editar): ?>
      <a href="carros_evento.php" class="btn sec" style="margin-top:.8rem">Cancelar</a>
    <?php endif; ?>
  </form>
  <?php if (!$editar): ?>
  <p class="muted" style="margin-top:.6rem;font-size:.85rem">
    Se o evento já tiver um registro, ele será atualizado.
  </p>
  <?php endif; ?>
</div>

<script>
function calcTotal(){
  let t = 0;
  document.querySelectorAll('.campo-qtd').forEach(c => t += parseInt(c.value || 0, 10));
  document.getElementById('totalVeic').textContent = t;
}
document.querySelectorAll('.campo-qtd').forEach(c => c.addEventListener('input', calcTotal));
calcTotal();
</script>

<?php if ($ehAdm): ?>
<div class="flex-between" style="margin:1.2rem 0 .6rem;flex-wrap:wrap;gap:.6rem">
  <h2 style="margin:0;color:var(--laranja-6)">Estatísticas</h2>
  <form method="get">
    <select name="periodo" onchange="this.form.submit()">
      <option value="todos" <?= $periodo==='todos'?'selected':'' ?>>Todo o período</option>
      <option value="mes"   <?= $periodo==='mes'?'selected':'' ?>>Este mês</option>
      <option value="ano"   <?= $periodo==='ano'?'selected':'' ?>>Este ano</option>
    </select>
  </form>
</div>

<div class="grid cols-4">
  <div class="stat"><div class="n"><?= number_format($total,0,',','.') ?></div><div class="l">Total de veículos</div></div>
  <div class="stat"><div class="n"><?= number_format($media,0,',','.') ?></div><div class="l">Média por evento</div></div>
  <div class="stat"><div class="n"><?= $qtdEv ?></div><div class="l">Eventos registrados</div></div>
  <div class="stat"><div class="n"><?= number_format($maior,0,',','.') ?></div><div class="l">Maior movimento</div></div>
</div>

<div class="grid cols-3" style="margin-top:.8rem">
  <div class="stat"><div class="n"><?= number_format($totEst,0,',','.') ?></div><div class="l">No estacionamento</div></div>
  <div class="stat"><div class="n"><?= number_format($totAnx,0,',','.') ?></div><div class="l">Em anexo</div></div>
  <div class="stat"><div class="n"><?= number_format($totExt,0,',','.') ?></div><div class="l">Externo</div></div>
</div>

<?php if ($grafico): ?>
<div class="card" style="margin-top:1.2rem">
  <h2>Comparativo de eventos</h2>
  <div class="barra-bars">
    <?php foreach ($grafico as $g):
      $h = max(8, round(($g['total_veic'] / $maxGraf) * 165));
      $t = strtotime($g['data_evento']);
    ?>
    <div class="barra-item">
      <div class="barra-vis" style="height:<?= $h ?>px">
        <span class="barra-num"><?= (int)$g['total_veic'] ?></span>
      </div>
      <div class="barra-lbl"><?= date('d', $t) ?>/<?= $meses[(int)date('n',$t)] ?><br><?= e(mb_strimwidth($g['evento'],0,10,'…')) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; /* fim só-admin */ ?>

<div class="card" style="margin-top:1.2rem">
  <h2>Histórico de registros <span class="badge ok"><?= $qtdEv ?></span></h2>
  <?php if (!$registros): ?>
    <p class="muted">Nenhum registro ainda. Use o formulário acima para começar.</p>
  <?php else: ?>
  <table class="tbl-carros">
    <thead><tr>
      <th>Data</th><th>Evento</th>
      <th class="right">Estac.</th><th class="right">Anexo</th><th class="right">Externo</th>
      <th class="right">Total</th>
      <?php if ($ehAdm): ?><th></th><?php endif; ?>
    </tr></thead>
    <tbody>
      <?php foreach ($registros as $r): ?>
      <tr>
        <td><?= date('d/m/Y', strtotime($r['data_evento'])) ?></td>
        <td><?= e($r['evento']) ?></td>
        <td class="right"><?= number_format((int)$r['qtd_estacionamento'],0,',','.') ?></td>
        <td class="right"><?= number_format((int)$r['qtd_anexo'],0,',','.') ?></td>
        <td class="right"><?= number_format((int)$r['qtd_externo'],0,',','.') ?></td>
        <td class="right car-badge"><?= number_format((int)$r['total_veic'],0,',','.') ?></td>
        <?php if ($ehAdm): ?>
        <td class="right" style="white-space:nowrap">
          <a class="btn sm sec" href="carros_evento.php?editar=<?= (int)$r['escala_id'] ?>#form-registro" title="Editar">✏️</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Excluir este registro?')">
            <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
            <input type="hidden" name="op" value="excluir">
            <input type="hidden" name="escala_id" value="<?= (int)$r['escala_id'] ?>">
            <button class="btn sm sec" title="Excluir">🗑️</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<style>
.barra-bars{display:flex;align-items:flex-end;gap:.7rem;height:210px;
  padding:1.5rem .5rem 0;overflow-x:auto;box-sizing:border-box}
.barra-item{display:flex;flex-direction:column;align-items:center;gap:.4rem;
  min-width:56px;flex:1;height:100%;justify-content:flex-end}
.barra-vis{width:100%;max-width:48px;background:linear-gradient(180deg,var(--laranja-4),var(--laranja-5));
  border-radius:6px 6px 0 0;position:relative;min-height:8px}
.barra-num{position:absolute;top:-20px;left:0;right:0;text-align:center;
  font-size:.78rem;font-weight:700;color:var(--laranja-6)}
.barra-lbl{font-size:.68rem;color:var(--texto-suave);text-align:center;line-height:1.15}
.tbl-carros td,.tbl-carros th{padding:.55rem .4rem}
.car-badge{font-weight:700;color:var(--laranja-6)}
.total-veic{margin-top:1rem;padding:.7rem 1rem;background:var(--laranja-1);
  border:1px solid var(--laranja-3);border-radius:10px;font-weight:700;
  color:var(--laranja-6);font-size:1.05rem}
.total-veic span{font-size:1.3rem}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
