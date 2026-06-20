<?php
require_once __DIR__ . '/includes/escala_engine.php';
exigirLogin();
$pdo = db();

// ---- ações de admin ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ehAdmin()) {
    validarCSRF();
    $op = $_POST['op'] ?? '';

    if ($op === 'criar') {
        $data = $_POST['data_evento'];
        $dt = new DateTime($data);
        $pdo->prepare(
          "INSERT INTO escalas (data_evento,dia,mes,ano,evento,horario_chegada,num_colaboradores,exige_lider)
           VALUES (?,?,?,?,?,?,?,?)"
        )->execute([
            $data,(int)$dt->format('d'),(int)$dt->format('m'),(int)$dt->format('Y'),
            trim($_POST['evento']), $_POST['horario_chegada'],
            (int)$_POST['num_colaboradores'], isset($_POST['exige_lider'])?1:0
        ]);
        flash('Evento criado.');
    }

    if ($op === 'domingos') {
        $ano = (int)$_POST['ano'];
        $n = criarDomingosDoAno($pdo, $ano);
        flash("$n domingos (Culto de Colaboração 17:45) criados para $ano.");
    }

    if ($op === 'gerar') {
        $id = (int)$_POST['escala_id'];
        $st = $pdo->prepare("SELECT * FROM escalas WHERE id=?"); $st->execute([$id]);
        $res = gerarEscalaEvento($pdo, $st->fetch());
        flash($res['msg'], $res['ok']?'sucesso':'erro');
    }

    if ($op === 'gerar_lote') {
        $st = $pdo->query("SELECT * FROM escalas WHERE status='aberta' AND data_evento>=CURDATE() ORDER BY data_evento");
        $ok=0;$falhas=[];
        foreach ($st as $esc) {
            $r = gerarEscalaEvento($pdo, $esc);
            $r['ok'] ? $ok++ : $falhas[]=$r['msg'];
        }
        $msg = "$ok escala(s) gerada(s).";
        if ($falhas) $msg .= ' Pendências: '.implode(' | ', array_slice($falhas,0,3)).(count($falhas)>3?'…':'');
        flash($msg, $falhas?'erro':'sucesso');
    }

    if ($op === 'excluir') {
        $pdo->prepare("DELETE FROM escalas WHERE id=?")->execute([(int)$_POST['escala_id']]);
        flash('Escala excluída.');
    }
    redirect('escalas.php');
}

// filtros
$mes = (int)($_GET['mes'] ?? date('n'));
$ano = (int)($_GET['ano'] ?? date('Y'));

$st = $pdo->prepare("SELECT * FROM escalas WHERE mes=? AND ano=? ORDER BY data_evento, horario_chegada");
$st->execute([$mes,$ano]);
$escalas = $st->fetchAll();

// carrega escalados
$escalados = [];
if ($escalas) {
    $ids = implode(',', array_column($escalas,'id'));
    $q = $pdo->query(
      "SELECT ec.escala_id, c.nome, ec.nivel_na_escala
       FROM escala_colaboradores ec JOIN colaboradores c ON c.id=ec.colaborador_id
       WHERE ec.escala_id IN ($ids) ORDER BY FIELD(ec.nivel_na_escala,'lider','pleno','junior')"
    );
    foreach ($q as $r) $escalados[$r['escala_id']][] = $r;
}

$meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
          7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
$titulo = 'Escalas';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Escalas</h1>
<p class="page-sub">Geração e acompanhamento das escalas de apoio.</p>

<?php if (ehAdmin()): ?>
<div class="grid" style="grid-template-columns:1fr 1fr;gap:1.2rem">
  <div class="card">
    <h2>Novo evento</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
      <input type="hidden" name="op" value="criar">
      <div class="form-row">
        <div><label>Data</label><input type="date" name="data_evento" required></div>
        <div><label>Horário de chegada</label><input type="time" name="horario_chegada" value="17:45" required></div>
      </div>
      <div class="form-row">
        <div><label>Evento</label><input name="evento" required></div>
        <div><label>Nº de colaboradores</label><input type="number" name="num_colaboradores" min="1" value="3" required></div>
      </div>
      <div class="check" style="margin-bottom:1rem"><input type="checkbox" name="exige_lider" id="el" checked><label for="el" style="margin:0">Exige colaborador líder</label></div>
      <button class="btn">Criar evento</button>
    </form>
  </div>

  <div class="card">
    <h2>Ferramentas automáticas</h2>
    <form method="post" style="margin-bottom:1.2rem">
      <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
      <input type="hidden" name="op" value="domingos">
      <label>Gerar domingos do ano (Culto de Colaboração · 17:45)</label>
      <div style="display:flex;gap:.6rem">
        <input type="number" name="ano" value="<?= date('Y') ?>" style="max-width:130px">
        <button class="btn sec">Criar domingos</button>
      </div>
    </form>
    <hr style="border:none;border-top:1px solid var(--borda);margin:1rem 0">
    <form method="post" onsubmit="return confirm('Gerar automaticamente todas as escalas abertas futuras?')">
      <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
      <input type="hidden" name="op" value="gerar_lote">
      <label>Preencher automaticamente todas as escalas abertas</label>
      <button class="btn">⚙️ Gerar escala automática</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="flex-between">
    <h2>Escalas de <?= $meses[$mes] ?> / <?= $ano ?> <span class="badge ok"><?= count($escalas) ?></span></h2>
    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
    <a href="calendario.php?mes=<?= $mes ?>&ano=<?= $ano ?>" class="btn sm sec no-print">🗓️ Ver em calendário</a>
    <form method="get" style="display:flex;gap:.5rem">
      <select name="mes" onchange="this.form.submit()">
        <?php foreach ($meses as $k=>$v): ?><option value="<?= $k ?>" <?= $k===$mes?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
      </select>
      <input type="number" name="ano" value="<?= $ano ?>" style="max-width:110px" onchange="this.form.submit()">
    </form>
    </div>
  </div>

  <table>
    <thead><tr><th>Data</th><th>Evento</th><th>Chegada</th><th>Equipe escalada</th><th>Status</th><?php if(ehAdmin()):?><th></th><?php endif;?></tr></thead>
    <tbody>
    <?php foreach ($escalas as $es): ?>
      <tr>
        <td><?= date('d/m', strtotime($es['data_evento'])) ?><br><span class="muted"><?= ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][date('w',strtotime($es['data_evento']))] ?></span></td>
        <td><?= e($es['evento']) ?><br><span class="muted"><?= $es['num_colaboradores'] ?> vaga(s)<?= $es['exige_lider']?' · líder':'' ?></span></td>
        <td><?= substr($es['horario_chegada'],0,5) ?></td>
        <td>
          <?php if (!empty($escalados[$es['id']])): foreach ($escalados[$es['id']] as $p): ?>
            <span class="badge <?= $p['nivel_na_escala'] ?>"><?= e($p['nome']) ?></span>
          <?php endforeach; else: ?><span class="muted">—</span><?php endif; ?>
        </td>
        <td><span class="badge <?= $es['status']==='preenchida'?'ok':'warn' ?>"><?= ucfirst($es['status']) ?></span></td>
        <?php if (ehAdmin()): ?>
        <td class="right" style="white-space:nowrap">
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
            <input type="hidden" name="op" value="gerar">
            <input type="hidden" name="escala_id" value="<?= $es['id'] ?>">
            <button class="btn sm">Gerar</button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('Excluir escala?')">
            <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
            <input type="hidden" name="op" value="excluir">
            <input type="hidden" name="escala_id" value="<?= $es['id'] ?>">
            <button class="btn sm danger">×</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if(!$escalas):?><tr><td colspan="6" class="muted">Nenhuma escala neste mês.</td></tr><?php endif;?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
