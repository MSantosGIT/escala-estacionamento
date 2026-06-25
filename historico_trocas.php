<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();
$pdo = db();

$u = usuario();
$ehAdm = ehAdmin();
$meuColabId = (int)($u['colaborador_id'] ?? 0);

// filtro opcional por status
$filtro = $_GET['status'] ?? 'todos';

$where = [];
$params = [];

// colaborador comum só vê trocas em que participou
if (!$ehAdm && $meuColabId) {
    $where[] = "(t.solicitante_id = ? OR t.alvo_id = ?)";
    $params[] = $meuColabId;
    $params[] = $meuColabId;
}

if ($filtro === 'confirmadas')       { $where[] = "t.status = 'confirmada'"; }
elseif ($filtro === 'recusadas')     { $where[] = "t.status IN ('recusada_colaborador','recusada_admin')"; }
elseif ($filtro === 'pendentes')     { $where[] = "t.status IN ('pendente_colaborador','pendente_admin')"; }
elseif ($filtro === 'canceladas')    { $where[] = "t.status = 'cancelada'"; }

$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
  SELECT t.*,
         cs.nome AS solicitante_nome,
         ca.nome AS alvo_nome,
         eo.evento AS evento_origem, eo.data_evento AS data_origem, eo.horario_chegada AS hora_origem,
         ea.evento AS evento_alvo,   ea.data_evento AS data_alvo,   ea.horario_chegada AS hora_alvo
  FROM trocas_escala t
  JOIN colaboradores cs ON cs.id = t.solicitante_id
  JOIN colaboradores ca ON ca.id = t.alvo_id
  JOIN escalas eo ON eo.id = t.escala_origem_id
  LEFT JOIN escalas ea ON ea.id = t.escala_alvo_id
  $sqlWhere
  ORDER BY t.criado_em DESC
";
$st = $pdo->prepare($sql);
$st->execute($params);
$trocas = $st->fetchAll();

// rótulos e cores de status
function statusInfo($s) {
    switch ($s) {
        case 'confirmada':            return ['Confirmada', 'st-ok', '✅'];
        case 'pendente_colaborador':  return ['Aguardando colaborador', 'st-wait', '⏳'];
        case 'pendente_admin':        return ['Aguardando admin', 'st-wait', '⏳'];
        case 'recusada_colaborador':  return ['Recusada pelo colaborador', 'st-no', '❌'];
        case 'recusada_admin':        return ['Recusada pelo admin', 'st-no', '❌'];
        case 'cancelada':             return ['Cancelada', 'st-cancel', '🚫'];
        default:                      return [$s, 'st-wait', '•'];
    }
}
function dataBR($dt) {
    return $dt ? date('d/m/Y H:i', strtotime($dt)) : null;
}
function dataEvento($dt) {
    return $dt ? date('d/m/Y', strtotime($dt)) : '—';
}

$titulo = 'Histórico de trocas';
require __DIR__ . '/includes/header.php';
?>
<div class="flex-between" style="margin-bottom:1rem;flex-wrap:wrap;gap:.6rem">
  <div>
    <h1 class="page-title" style="margin-bottom:.2rem">Histórico de trocas</h1>
    <p class="page-sub" style="margin:0">Solicitações de troca de escala e seu andamento.</p>
  </div>
  <form method="get">
    <select name="status" onchange="this.form.submit()">
      <option value="todos"       <?= $filtro==='todos'?'selected':'' ?>>Todas</option>
      <option value="confirmadas" <?= $filtro==='confirmadas'?'selected':'' ?>>Confirmadas</option>
      <option value="pendentes"   <?= $filtro==='pendentes'?'selected':'' ?>>Pendentes</option>
      <option value="recusadas"   <?= $filtro==='recusadas'?'selected':'' ?>>Recusadas</option>
      <option value="canceladas"  <?= $filtro==='canceladas'?'selected':'' ?>>Canceladas</option>
    </select>
  </form>
</div>

<?php if (!$trocas): ?>
  <div class="card"><p class="muted">Nenhuma troca encontrada<?= $filtro!=='todos' ? ' com esse filtro' : '' ?>.</p></div>
<?php else: ?>

<div class="historico-lista">
  <?php foreach ($trocas as $t):
    list($stLabel, $stClass, $stIcon) = statusInfo($t['status']);
  ?>
  <div class="troca-card">
    <div class="troca-topo">
      <span class="troca-status <?= $stClass ?>"><?= $stIcon ?> <?= e($stLabel) ?></span>
      <span class="troca-quando">Solicitada em <?= dataBR($t['criado_em']) ?></span>
    </div>

    <div class="troca-pessoas">
      <b><?= e($t['solicitante_nome']) ?></b>
      <span class="seta">⇄</span>
      <b><?= e($t['alvo_nome']) ?></b>
    </div>

    <div class="troca-evento">
      <span class="muted">Evento:</span>
      <?= e($t['evento_origem']) ?> · <?= dataEvento($t['data_origem']) ?>
      <?php if ($t['data_origem']): ?>às <?= substr($t['hora_origem'],0,5) ?><?php endif; ?>
    </div>

    <ul class="troca-timeline">
      <li>
        <span class="tl-dot done"></span>
        <span class="tl-txt"><b><?= e($t['solicitante_nome']) ?></b> solicitou a troca</span>
        <span class="tl-data"><?= dataBR($t['criado_em']) ?></span>
      </li>
      <?php if ($t['respondido_em']): ?>
      <li>
        <span class="tl-dot <?= in_array($t['status'],['recusada_colaborador']) ? 'no':'done' ?>"></span>
        <span class="tl-txt">
          <?php if ($t['status']==='recusada_colaborador'): ?>
            <b><?= e($t['alvo_nome']) ?></b> recusou
          <?php else: ?>
            <b><?= e($t['alvo_nome']) ?></b> aceitou
          <?php endif; ?>
        </span>
        <span class="tl-data"><?= dataBR($t['respondido_em']) ?></span>
      </li>
      <?php endif; ?>
      <?php if ($t['decidido_em']): ?>
      <li>
        <span class="tl-dot <?= $t['status']==='confirmada' ? 'done':'no' ?>"></span>
        <span class="tl-txt">
          <?php if ($t['status']==='confirmada'): ?>
            Administrador <b>aprovou</b> a troca
          <?php else: ?>
            Administrador <b>recusou</b> a troca
          <?php endif; ?>
        </span>
        <span class="tl-data"><?= dataBR($t['decidido_em']) ?></span>
      </li>
      <?php endif; ?>
      <?php if ($t['status']==='cancelada'): ?>
      <li>
        <span class="tl-dot cancel"></span>
        <span class="tl-txt"><b><?= e($t['solicitante_nome']) ?></b> cancelou a solicitação</span>
        <span class="tl-data"></span>
      </li>
      <?php endif; ?>
    </ul>
  </div>
  <?php endforeach; ?>
</div>

<?php endif; ?>

<style>
.historico-lista{display:flex;flex-direction:column;gap:1rem}
.troca-card{background:#fff;border:1px solid var(--borda);border-radius:12px;padding:1rem 1.1rem;
  box-shadow:0 2px 8px rgba(0,0,0,.04)}
.troca-topo{display:flex;justify-content:space-between;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.7rem}
.troca-status{font-weight:700;font-size:.85rem;padding:.2rem .6rem;border-radius:20px}
.st-ok{background:#dff3e3;color:#2f7d49}
.st-wait{background:#fff3e0;color:#9a5a12}
.st-no{background:#fbe1e1;color:#a83b3b}
.st-cancel{background:#eee;color:#666}
.troca-quando{font-size:.8rem;color:var(--texto-suave)}
.troca-pessoas{font-size:1.05rem;color:var(--laranja-6);margin-bottom:.4rem}
.troca-pessoas .seta{margin:0 .5rem;color:var(--texto-suave)}
.troca-evento{font-size:.9rem;color:#444;margin-bottom:.7rem;
  padding-bottom:.7rem;border-bottom:1px dashed var(--borda)}
.troca-timeline{list-style:none;padding:0;margin:0}
.troca-timeline li{display:flex;align-items:center;gap:.6rem;padding:.3rem 0;font-size:.9rem}
.tl-dot{width:11px;height:11px;border-radius:50%;flex:0 0 auto;background:#ccc}
.tl-dot.done{background:#3c9d5c}
.tl-dot.no{background:#d35454}
.tl-dot.cancel{background:#999}
.tl-txt{flex:1;color:#444}
.tl-data{font-size:.78rem;color:var(--texto-suave);white-space:nowrap}
@media (max-width:560px){
  .tl-data{display:none}
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
