<?php
require_once __DIR__ . '/includes/escala_engine.php';
exigirAdmin();
$pdo = db();

// mês/ano selecionados (padrão: mês seguinte)
$prox = (new DateTime('today'))->modify('first day of next month');
$mes = (int)($_GET['mes'] ?? $prox->format('n'));
$ano = (int)($_GET['ano'] ?? $prox->format('Y'));
$colabId = (int)($_GET['colaborador'] ?? ($_POST['colaborador_id'] ?? 0));

// ---- salvar indisponibilidade ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'salvar') {
    validarCSRF();
    $colabId = (int)$_POST['colaborador_id'];
    $mes = (int)$_POST['mes'];
    $ano = (int)$_POST['ano'];
    $marcados = array_map('intval', $_POST['indisponivel'] ?? []);

    if ($colabId) {
        // universo: eventos do mês selecionado
        $st = $pdo->prepare("SELECT id FROM escalas WHERE mes=? AND ano=?");
        $st->execute([$mes, $ano]);
        $idsMes = array_map('intval', array_column($st->fetchAll(), 'id'));

        $pdo->beginTransaction();
        try {
            if ($idsMes) {
                $in = implode(',', array_fill(0, count($idsMes), '?'));
                $del = $pdo->prepare("DELETE FROM indisponibilidades WHERE colaborador_id=? AND escala_id IN ($in)");
                $del->execute(array_merge([$colabId], $idsMes));
            }
            $ins = $pdo->prepare("INSERT IGNORE INTO indisponibilidades (colaborador_id, escala_id) VALUES (?, ?)");
            foreach ($marcados as $eid) {
                if (in_array($eid, $idsMes, true)) $ins->execute([$colabId, $eid]);
            }
            $pdo->commit();
            flash('Indisponibilidade do colaborador registrada.');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('Erro ao salvar.', 'erro');
        }
    }
    redirect("admin_indisponibilidade.php?colaborador=$colabId&mes=$mes&ano=$ano");
}

// ---- regerar escala do mês ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'regerar') {
    validarCSRF();
    $mes = (int)$_POST['mes']; $ano = (int)$_POST['ano'];
    $colabId = (int)$_POST['colaborador_id'];
    $res = regerarEscalaDoMes($pdo, $mes, $ano);
    $msg = "Escala regerada: {$res['completas']} completo(s)";
    if ($res['parciais'] > 0) $msg .= ", {$res['parciais']} parcial(is)";
    flash($msg . '.', $res['parciais'] ? 'erro' : 'sucesso');
    redirect("admin_indisponibilidade.php?colaborador=$colabId&mes=$mes&ano=$ano");
}

$colaboradores = $pdo->query("SELECT id,nome,nivel FROM colaboradores WHERE ativo=1 ORDER BY FIELD(nivel,'lider','pleno','junior'), nome")->fetchAll();

// eventos do mês + indisponibilidades do colaborador
$eventosPorDia = [];
$indispon = [];
$temEscalaGerada = false;
if ($colabId) {
    $st = $pdo->prepare("SELECT * FROM escalas WHERE mes=? AND ano=? ORDER BY data_evento, horario_chegada");
    $st->execute([$mes, $ano]);
    foreach ($st->fetchAll() as $ev) {
        $eventosPorDia[(int)date('j', strtotime($ev['data_evento']))][] = $ev;
    }
    $st = $pdo->prepare(
        "SELECT i.escala_id FROM indisponibilidades i
         JOIN escalas e ON e.id=i.escala_id
         WHERE i.colaborador_id=? AND e.mes=? AND e.ano=?"
    );
    $st->execute([$colabId, $mes, $ano]);
    $indispon = array_map('intval', array_column($st->fetchAll(), 'escala_id'));

    $temEscalaGerada = escalaMensalJaGerada($pdo, $mes, $ano);
}

// estrutura do calendário
$primeiro   = mktime(0,0,0,$mes,1,$ano);
$diasNoMes  = (int)date('t', $primeiro);
$diaSemana1 = (int)date('w', $primeiro);
$semanas    = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
$meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
          7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];

$titulo = 'Indisponibilidade (Admin)';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Indisponibilidade por colaborador</h1>
<p class="page-sub">Selecione o colaborador e clique nos eventos em que ele estará indisponível.</p>

<div class="card">
  <form method="get" style="display:flex;gap:.8rem;align-items:flex-end;flex-wrap:wrap">
    <div style="min-width:240px">
      <label>Colaborador</label>
      <select name="colaborador" onchange="this.form.submit()">
        <option value="">Selecione…</option>
        <?php foreach ($colaboradores as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $c['id']==$colabId?'selected':'' ?>><?= e($c['nome']) ?> · <?= e(nivelLabel($c['nivel'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Mês</label>
      <select name="mes" onchange="this.form.submit()">
        <?php foreach ($meses as $k=>$v): ?><option value="<?= $k ?>" <?= $k===$mes?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
      </select>
    </div>
    <div><label>Ano</label><input type="number" name="ano" value="<?= $ano ?>" style="max-width:110px" onchange="this.form.submit()"></div>
  </form>
</div>

<?php if (!$colabId): ?>
  <div class="card"><p class="muted">Escolha um colaborador acima para ver o calendário.</p></div>
<?php else: ?>

  <form method="post" id="formInd">
    <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
    <input type="hidden" name="op" value="salvar">
    <input type="hidden" name="colaborador_id" value="<?= $colabId ?>">
    <input type="hidden" name="mes" value="<?= $mes ?>">
    <input type="hidden" name="ano" value="<?= $ano ?>">

    <div class="card">
      <div class="flex-between">
        <h2><?= $meses[$mes] ?> / <?= $ano ?></h2>
        <span class="muted">Clique nos dias com evento para marcar indisponibilidade</span>
      </div>

      <table class="cal-adm">
        <thead><tr><?php foreach ($semanas as $s): ?><th><?= $s ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php
        $dia = 1; $totalCelulas = $diaSemana1 + $diasNoMes;
        $linhas = (int)ceil($totalCelulas / 7);
        for ($l=0;$l<$linhas;$l++): ?>
          <tr>
          <?php for ($c=0;$c<7;$c++):
            $idx=$l*7+$c;
            if ($idx<$diaSemana1 || $dia>$diasNoMes): ?>
              <td class="vazio"></td>
            <?php else:
              $evs = $eventosPorDia[$dia] ?? []; ?>
              <td class="<?= $evs?'tem-ev':'' ?>">
                <div class="dnum"><?= $dia ?></div>
                <?php foreach ($evs as $ev):
                  $marc = in_array((int)$ev['id'], $indispon, true); ?>
                  <div class="ev-chip <?= $marc?'ind':'' ?>">
                    <input type="checkbox" name="indisponivel[]" value="<?= $ev['id'] ?>" <?= $marc?'checked':'' ?> class="ev-check">
                    <span class="ev-nome"><?= e($ev['evento']) ?></span>
                    <span class="ev-hora"><?= substr($ev['horario_chegada'],0,5) ?></span>
                    <span class="ev-tag"><?= $marc?'indisponível':'disponível' ?></span>
                  </div>
                <?php endforeach; ?>
              </td>
            <?php $dia++; endif; ?>
          <?php endfor; ?>
          </tr>
        <?php endfor; ?>
        </tbody>
      </table>

      <?php if (!array_filter($eventosPorDia)): ?>
        <p class="muted" style="margin-top:1rem">Nenhum evento cadastrado neste mês. Crie os eventos em Escalas (ou use “Gerar mês seguinte”).</p>
      <?php else: ?>
        <button class="btn" style="margin-top:1rem">Salvar indisponibilidade</button>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($temEscalaGerada): ?>
  <div class="card" style="border-color:var(--laranja-4)">
    <div class="flex-between">
      <div>
        <h2>Escala deste mês já está gerada</h2>
        <p class="muted">Se você alterou a indisponibilidade, regere para aplicar as mudanças. Isso sobrescreve a escala do mês.</p>
      </div>
      <form method="post" onsubmit="return confirm('Regerar a escala de <?= $meses[$mes] ?>/<?= $ano ?>? A escala atual do mês será substituída.')">
        <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
        <input type="hidden" name="op" value="regerar">
        <input type="hidden" name="colaborador_id" value="<?= $colabId ?>">
        <input type="hidden" name="mes" value="<?= $mes ?>">
        <input type="hidden" name="ano" value="<?= $ano ?>">
        <button class="btn">♻️ Regerar escala do mês</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

<?php endif; ?>

<style>
.cal-adm{width:100%;border-collapse:collapse;table-layout:fixed;margin-top:1rem}
.cal-adm th{background:var(--laranja-2);color:var(--laranja-6);text-align:center;padding:.5rem;font-size:.8rem;border:1px solid var(--borda)}
.cal-adm td{border:1px solid var(--borda);vertical-align:top;height:92px;padding:.3rem;width:14.28%;background:#fff}
.cal-adm td.vazio{background:var(--laranja-1)}
.cal-adm td.tem-ev{background:#fffaf4}
.dnum{font-weight:700;color:var(--texto-suave);font-size:.8rem;margin-bottom:.25rem}
.ev-chip{display:block;cursor:pointer;border:1px solid var(--laranja-3);background:var(--laranja-1);
  border-radius:8px;padding:.3rem .4rem;margin-bottom:.3rem;transition:.12s;user-select:none}
.ev-check{display:none}
.ev-chip:hover{border-color:var(--laranja-4)}
.ev-chip .ev-nome{display:block;font-weight:700;color:var(--laranja-6);font-size:.72rem;line-height:1.1}
.ev-chip .ev-hora{display:block;font-size:.68rem;color:var(--texto-suave)}
.ev-chip .ev-tag{display:inline-block;margin-top:.15rem;font-size:.64rem;font-weight:600;
  color:#3c7b4c;background:#dff3e3;border-radius:10px;padding:.05rem .4rem}
.ev-chip.ind{background:#fbe1e1;border-color:#e7a3a3}
.ev-chip.ind .ev-nome{color:#a83b3b}
.ev-chip.ind .ev-tag{color:#a83b3b;background:#f6cccc}
@media (max-width:760px){
  .cal-adm td{height:auto;min-height:60px}
  .ev-chip .ev-nome{font-size:.68rem}
}
</style>
<script>
// clicar em qualquer parte do chip alterna o checkbox (uma vez só)
document.querySelectorAll('.ev-chip').forEach(chip=>{
  const inp = chip.querySelector('input');
  function sync(){
    chip.classList.toggle('ind', inp.checked);
    const tag = chip.querySelector('.ev-tag');
    if (tag) tag.textContent = inp.checked ? 'indisponível' : 'disponível';
  }
  chip.addEventListener('click', e=>{
    if (e.target.tagName !== 'INPUT') { inp.checked = !inp.checked; }
    sync();
  });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
