<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();
$pdo = db();

$u = usuario();
$ehAdm = ehAdmin();
$meuColabId = (int)($u['colaborador_id'] ?? 0);

// filtro de período
$periodo = $_GET['periodo'] ?? 'todos';
$condData = '';
if ($periodo === 'mes') {
    $condData = "AND e.data_evento >= '" . date('Y-m-01') . "' AND e.data_evento <= '" . date('Y-m-t') . "'";
} elseif ($periodo === 'ano') {
    $condData = "AND YEAR(e.data_evento) = " . (int)date('Y');
}

// eventos encerrados (admin vê todos; colaborador só os que participou)
$sql = "
  SELECT en.id AS encerramento_id, en.observacao, en.encerrado_em,
         cEnc.nome AS encerrado_por_nome,
         e.id AS escala_id, e.evento, e.data_evento, e.horario_chegada
  FROM evento_encerramentos en
  JOIN escalas e ON e.id = en.escala_id
  LEFT JOIN colaboradores cEnc ON cEnc.id = en.encerrado_por
";
$params = [];
if (!$ehAdm) {
    $sql .= " JOIN escala_colaboradores ec ON ec.escala_id = e.id AND ec.colaborador_id = ? ";
    $params[] = $meuColabId;
}
$sql .= " WHERE 1=1 $condData ORDER BY e.data_evento DESC, e.horario_chegada DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$eventos = $st->fetchAll();

// dados de apoio de cada evento
$dados = [];
foreach ($eventos as $ev) {
    $eid = (int)$ev['escala_id'];
    $encId = (int)$ev['encerramento_id'];

    $st = $pdo->prepare("SELECT item_descricao, marcado FROM evento_checklist WHERE encerramento_id=? ORDER BY id");
    $st->execute([$encId]);
    $checklist = $st->fetchAll();

    $st = $pdo->prepare(
      "SELECT c.nome, c.nivel, ck.checkin_em
       FROM evento_checkins ck JOIN colaboradores c ON c.id = ck.colaborador_id
       WHERE ck.escala_id = ? ORDER BY ck.checkin_em"
    );
    $st->execute([$eid]);
    $checkins = $st->fetchAll();

    $st = $pdo->prepare("SELECT arquivo FROM evento_fotos WHERE escala_id=? ORDER BY criado_em");
    $st->execute([$eid]);
    $fotos = $st->fetchAll();

    $carros = null;
    try {
        $st = $pdo->prepare("SELECT * FROM carros_evento WHERE escala_id=?");
        $st->execute([$eid]);
        $carros = $st->fetch() ?: null;
    } catch (Throwable $ex) { /* tabela pode não existir neste ambiente; ignora silenciosamente */ }

    $dados[$encId] = ['checklist'=>$checklist, 'checkins'=>$checkins, 'fotos'=>$fotos, 'carros'=>$carros];
}

$titulo = 'Eventos finalizados';
require __DIR__ . '/includes/header.php';
?>
<div class="flex-between" style="margin-bottom:.4rem;flex-wrap:wrap;gap:.6rem">
  <div>
    <h1 class="page-title" style="margin-bottom:.2rem">Eventos finalizados</h1>
    <p class="page-sub" style="margin:0">Consulta dos eventos já encerrados — check-in, checklist e fotos. <?= $ehAdm ? '' : '(seus eventos)' ?></p>
  </div>
  <form method="get">
    <select name="periodo" onchange="this.form.submit()">
      <option value="todos" <?= $periodo==='todos'?'selected':'' ?>>Todo o período</option>
      <option value="mes"   <?= $periodo==='mes'?'selected':'' ?>>Este mês</option>
      <option value="ano"   <?= $periodo==='ano'?'selected':'' ?>>Este ano</option>
    </select>
  </form>
</div>

<?php if (!$eventos): ?>
  <div class="card"><p class="muted">Nenhum evento finalizado encontrado<?= $periodo!=='todos' ? ' nesse período' : '' ?>.</p></div>
<?php endif; ?>

<div class="fin-lista">
  <?php foreach ($eventos as $ev):
    $d = $dados[(int)$ev['encerramento_id']];
    $totalCheckin = count($d['checkins']);
  ?>
  <div class="fin-card">
    <div class="fin-cab">
      <div>
        <div class="fin-evento"><?= e($ev['evento']) ?></div>
        <div class="fin-meta"><?= date('d/m/Y', strtotime($ev['data_evento'])) ?> · ⏰ <?= substr($ev['horario_chegada'],0,5) ?></div>
      </div>
      <span class="fin-selo">🔒 Finalizado</span>
    </div>

    <div class="fin-encerrado">
      Encerrado por <b><?= e($ev['encerrado_por_nome'] ?: '—') ?></b>
      em <?= date('d/m/Y \à\s H:i', strtotime($ev['encerrado_em'])) ?>
    </div>

    <div class="fin-secoes">
      <!-- checklist -->
      <div class="fin-sec">
        <div class="fin-sec-tit">📋 Checklist de encerramento</div>
        <?php if ($d['checklist']): ?>
        <ul class="fin-check-lista">
          <?php foreach ($d['checklist'] as $ci): ?>
          <li>
            <span class="fin-ck <?= $ci['marcado'] ? 'sim' : 'nao' ?>"><?= $ci['marcado'] ? '✓' : '✗' ?></span>
            <?= e($ci['item_descricao']) ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
          <p class="muted" style="font-size:.85rem">Sem itens registrados.</p>
        <?php endif; ?>
        <?php if (!empty($ev['observacao'])): ?>
          <div class="fin-obs"><b>Observação:</b> <?= nl2br(e($ev['observacao'])) ?></div>
        <?php endif; ?>
      </div>

      <!-- check-ins -->
      <div class="fin-sec">
        <div class="fin-sec-tit">📍 Check-in da equipe <span class="badge ok"><?= $totalCheckin ?></span></div>
        <?php if ($d['checkins']): ?>
        <ul class="fin-check-lista">
          <?php foreach ($d['checkins'] as $ck): ?>
          <li>
            <span class="fin-ck sim">✓</span>
            <?= e($ck['nome']) ?>
            <span class="fin-hora"><?= date('H:i', strtotime($ck['checkin_em'])) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
          <p class="muted" style="font-size:.85rem">Nenhum check-in registrado.</p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($d['carros']):
      $tv = (int)$d['carros']['qtd_estacionamento'] + (int)$d['carros']['qtd_anexo']
          + (int)($d['carros']['qtd_gramado'] ?? 0) + (int)$d['carros']['qtd_externo'];
    ?>
    <div class="fin-sec" style="margin-top:.7rem">
      <div class="fin-sec-tit">🚗 Movimento de veículos <span class="badge ok"><?= $tv ?> total</span></div>
      <div class="fin-carros">
        <span>Principal: <b><?= (int)$d['carros']['qtd_estacionamento'] ?></b></span>
        <span>Anexo: <b><?= (int)$d['carros']['qtd_anexo'] ?></b></span>
        <span>Gramado: <b><?= (int)($d['carros']['qtd_gramado'] ?? 0) ?></b></span>
        <span>Externo: <b><?= (int)$d['carros']['qtd_externo'] ?></b></span>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($d['fotos']): ?>
    <div class="fin-sec" style="margin-top:.7rem">
      <div class="fin-sec-tit">📷 Fotos do evento</div>
      <div class="fin-fotos">
        <?php foreach ($d['fotos'] as $f): ?>
          <a href="<?= e($f['arquivo']) ?>" target="_blank"><img src="<?= e($f['arquivo']) ?>" alt="Foto do evento"></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<style>
.fin-lista{display:flex;flex-direction:column;gap:1.1rem}
.fin-card{background:#fff;border:1px solid var(--borda);border-radius:12px;padding:1.1rem 1.2rem;
  box-shadow:0 2px 8px rgba(0,0,0,.04)}
.fin-cab{display:flex;justify-content:space-between;align-items:flex-start;gap:.6rem;flex-wrap:wrap}
.fin-evento{font-weight:800;color:var(--laranja-6);font-size:1.08rem}
.fin-meta{color:var(--texto-suave);font-size:.85rem;margin-top:.15rem}
.fin-selo{background:#f4f0ec;color:#666;font-weight:700;font-size:.78rem;
  padding:.25rem .65rem;border-radius:20px;white-space:nowrap}
.fin-encerrado{font-size:.85rem;color:#555;margin:.5rem 0 .8rem;padding-bottom:.7rem;
  border-bottom:1px dashed var(--borda)}
.fin-secoes{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem}
@media(max-width:640px){.fin-secoes{grid-template-columns:1fr}}
.fin-sec-tit{font-weight:700;color:var(--laranja-6);font-size:.9rem;margin-bottom:.4rem;
  display:flex;align-items:center;gap:.4rem;flex-wrap:wrap}
.fin-check-lista{list-style:none;padding:0;margin:0}
.fin-check-lista li{display:flex;align-items:center;gap:.55rem;padding:.28rem 0;
  font-size:.88rem;border-bottom:1px dashed var(--borda)}
.fin-check-lista li:last-child{border-bottom:none}
.fin-ck{font-weight:700;width:20px;height:20px;border-radius:50%;flex:0 0 auto;
  display:flex;align-items:center;justify-content:center;font-size:.72rem}
.fin-ck.sim{background:#dff3e3;color:#2f7d49}
.fin-ck.nao{background:#fbe1e1;color:#a83b3b}
.fin-hora{margin-left:auto;color:var(--texto-suave);font-size:.78rem}
.fin-obs{margin-top:.6rem;background:var(--laranja-1);border:1px solid var(--laranja-3);
  border-radius:8px;padding:.5rem .7rem;font-size:.83rem;color:#555}
.fin-carros{display:flex;gap:1rem;flex-wrap:wrap;font-size:.86rem;color:#444}
.fin-fotos{display:flex;gap:.6rem;flex-wrap:wrap}
.fin-fotos img{width:90px;height:90px;object-fit:cover;border-radius:8px;border:1px solid var(--borda)}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
