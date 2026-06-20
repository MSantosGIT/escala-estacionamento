<?php
require_once __DIR__ . '/includes/escala_engine.php';
exigirLogin();
$pdo = db();
$u = usuario();
$meuColabId = (int)($u['colaborador_id'] ?? 0);

// período de marcação: eventos do mês seguinte, liberados até o dia 20 do mês atual
$hoje = new DateTime('today');
$prazo = (int)$hoje->format('j') <= 20;  // pode marcar até o dia 20 (inclusive)

$prox = (clone $hoje)->modify('first day of next month');
$mesAlvo = (int)$prox->format('n');
$anoAlvo = (int)$prox->format('Y');

// garante que os domingos do mês seguinte existam para marcação
if ($meuColabId && $prazo) {
    criarDomingosDoMes($pdo, $mesAlvo, $anoAlvo);
}

// salvar marcações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $meuColabId && $prazo) {
    validarCSRF();
    $marcados = array_map('intval', $_POST['indisponivel'] ?? []);

    // eventos do mês alvo (universo permitido)
    $st = $pdo->prepare("SELECT id FROM escalas WHERE mes=? AND ano=?");
    $st->execute([$mesAlvo, $anoAlvo]);
    $idsMes = array_map('intval', array_column($st->fetchAll(), 'id'));

    $pdo->beginTransaction();
    try {
        // limpa as marcações anteriores deste colaborador para o mês alvo
        if ($idsMes) {
            $in = implode(',', array_fill(0, count($idsMes), '?'));
            $del = $pdo->prepare("DELETE FROM indisponibilidades WHERE colaborador_id=? AND escala_id IN ($in)");
            $del->execute(array_merge([$meuColabId], $idsMes));
        }
        // grava as novas
        $ins = $pdo->prepare("INSERT IGNORE INTO indisponibilidades (colaborador_id, escala_id) VALUES (?, ?)");
        foreach ($marcados as $eid) {
            if (in_array($eid, $idsMes, true)) $ins->execute([$meuColabId, $eid]);
        }
        $pdo->commit();
        flash('Indisponibilidade registrada. Obrigado!');
    } catch (Throwable $ex) {
        $pdo->rollBack();
        flash('Erro ao salvar: ' . $ex->getMessage(), 'erro');
    }
    redirect('disponibilidade.php');
}

// eventos do mês alvo
$eventos = [];
$jaMarcados = [];
if ($meuColabId) {
    $st = $pdo->prepare("SELECT * FROM escalas WHERE mes=? AND ano=? ORDER BY data_evento, horario_chegada");
    $st->execute([$mesAlvo, $anoAlvo]);
    $eventos = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT i.escala_id FROM indisponibilidades i
         JOIN escalas e ON e.id=i.escala_id
         WHERE i.colaborador_id=? AND e.mes=? AND e.ano=?"
    );
    $st->execute([$meuColabId, $mesAlvo, $anoAlvo]);
    $jaMarcados = array_map('intval', array_column($st->fetchAll(), 'escala_id'));
}

$meses = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',
          7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];

$titulo = 'Minha Disponibilidade';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Minha disponibilidade</h1>
<p class="page-sub">Marque os eventos de <?= $meses[$mesAlvo] ?>/<?= $anoAlvo ?> em que você <b>não</b> poderá servir.</p>

<?php if (!$meuColabId): ?>
  <div class="card"><p class="muted">Este usuário não está vinculado a um colaborador. Use uma conta de colaborador para marcar disponibilidade.</p></div>
<?php else: ?>

  <?php if (!$prazo): ?>
    <div class="flash erro">O prazo para marcar indisponibilidade do próximo mês encerrou no dia 20. A escala já está sendo montada. Procure o administrador se precisar de ajuste.</div>
  <?php else: ?>
    <div class="flash sucesso">Prazo aberto até o dia 20. Marque abaixo os eventos em que estará indisponível e salve.</div>
  <?php endif; ?>

  <div class="card">
    <h2>Eventos de <?= $meses[$mesAlvo] ?>/<?= $anoAlvo ?> <span class="badge ok"><?= count($eventos) ?></span></h2>
    <?php if (!$eventos): ?>
      <p class="muted">Nenhum evento cadastrado para o mês seguinte ainda.</p>
    <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
      <table>
        <thead><tr><th>Data</th><th>Evento</th><th>Chegada</th><th class="right">Não estarei disponível</th></tr></thead>
        <tbody>
        <?php foreach ($eventos as $ev):
          $marc = in_array((int)$ev['id'], $jaMarcados, true); ?>
          <tr>
            <td><?= date('d/m', strtotime($ev['data_evento'])) ?><br><span class="muted"><?= ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][date('w',strtotime($ev['data_evento']))] ?></span></td>
            <td><?= e($ev['evento']) ?></td>
            <td><?= substr($ev['horario_chegada'],0,5) ?></td>
            <td class="right">
              <input type="checkbox" name="indisponivel[]" value="<?= $ev['id'] ?>" <?= $marc?'checked':'' ?> <?= $prazo?'':'disabled' ?> style="width:20px;height:20px">
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($prazo): ?>
        <button class="btn" style="margin-top:1rem">Salvar indisponibilidade</button>
      <?php endif; ?>
    </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <p class="muted">Eventos não marcados significam que você está disponível. A escala é montada automaticamente após o dia 20, respeitando as marcações de todos e as regras de composição (A1, A2 e A3).</p>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
