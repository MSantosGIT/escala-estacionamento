<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();
$pdo = db();
$ehAdm = ehAdmin();

// ---- salvar/atualizar registro ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $escalaId = (int)($_POST['escala_id'] ?? 0);
    $qtd = (int)($_POST['quantidade'] ?? -1);

    if (!$escalaId) {
        flash('Selecione um evento.', 'erro');
    } elseif ($qtd < 0) {
        flash('Informe uma quantidade válida (0 ou mais).', 'erro');
    } else {
        // insere ou atualiza (UNIQUE em escala_id)
        $st = $pdo->prepare(
          "INSERT INTO carros_evento (escala_id, quantidade, registrado_por)
           VALUES (?, ?, ?)
           ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade), atualizado_em = NOW()"
        );
        $st->execute([$escalaId, $qtd, (int)(usuario()['id'] ?? 0)]);
        flash('Registro salvo com sucesso.');
    }
    redirect('carros_evento.php');
}

// ---- filtro de período ----
$periodo = $_GET['periodo'] ?? 'todos';
$hoje = date('Y-m-d');
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

// ---- registros existentes (com filtro de período) ----
$registros = $pdo->query(
  "SELECT ce.id, ce.quantidade, ce.escala_id,
          e.data_evento, e.evento, e.horario_chegada
   FROM carros_evento ce
   JOIN escalas e ON e.id = ce.escala_id
   WHERE 1=1 $condData
   ORDER BY e.data_evento DESC, e.horario_chegada DESC"
)->fetchAll();

// ---- estatísticas ----
$total = 0; $maior = 0; $qtdEv = count($registros);
foreach ($registros as $r) {
    $total += (int)$r['quantidade'];
    if ((int)$r['quantidade'] > $maior) $maior = (int)$r['quantidade'];
}
$media = $qtdEv ? round($total / $qtdEv) : 0;

// dados do gráfico (ordem cronológica crescente, até 12 últimos)
$grafico = array_reverse(array_slice($registros, 0, 12));
$maxGraf = 1;
foreach ($grafico as $g) if ((int)$g['quantidade'] > $maxGraf) $maxGraf = (int)$g['quantidade'];

$meses = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];

$titulo = 'Carros por evento';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Carros por evento</h1>
<p class="page-sub">Registre a movimentação de veículos em cada evento.</p>

<div class="card">
  <h2>Registrar movimento</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
    <div class="form-row">
      <div style="flex:2">
        <label>Evento</label>
        <select name="escala_id" required>
          <option value="">— selecione —</option>
          <?php foreach ($eventos as $ev): ?>
            <option value="<?= $ev['id'] ?>">
              <?= date('d/m/Y', strtotime($ev['data_evento'])) ?> — <?= e($ev['evento']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:1">
        <label>Qtd. de carros</label>
        <input type="number" name="quantidade" min="0" placeholder="Ex.: 145" required>
      </div>
    </div>
    <button class="btn" style="margin-top:.8rem">Salvar registro</button>
  </form>
  <p class="muted" style="margin-top:.6rem;font-size:.85rem">
    Se o evento já tiver um número registrado, ele será atualizado.
  </p>
</div>

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
  <div class="stat"><div class="n"><?= number_format($total,0,',','.') ?></div><div class="l">Total de carros</div></div>
  <div class="stat"><div class="n"><?= number_format($media,0,',','.') ?></div><div class="l">Média por evento</div></div>
  <div class="stat"><div class="n"><?= $qtdEv ?></div><div class="l">Eventos registrados</div></div>
  <div class="stat"><div class="n"><?= number_format($maior,0,',','.') ?></div><div class="l">Maior movimento</div></div>
</div>

<?php if ($grafico): ?>
<div class="card" style="margin-top:1.2rem">
  <h2>Comparativo de eventos</h2>
  <div class="barra-bars">
    <?php foreach ($grafico as $g):
      $h = max(8, round(($g['quantidade'] / $maxGraf) * 165));
      $t = strtotime($g['data_evento']);
    ?>
    <div class="barra-item">
      <div class="barra-vis" style="height:<?= $h ?>px">
        <span class="barra-num"><?= (int)$g['quantidade'] ?></span>
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
    <thead><tr><th>Data</th><th>Evento</th><th class="right">Carros</th></tr></thead>
    <tbody>
      <?php foreach ($registros as $r): ?>
      <tr>
        <td><?= date('d/m/Y', strtotime($r['data_evento'])) ?></td>
        <td><?= e($r['evento']) ?></td>
        <td class="right car-badge"><?= number_format((int)$r['quantidade'],0,',','.') ?></td>
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
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
