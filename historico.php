<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();
$pdo = db();

// colaborador vê só o próprio histórico; admin escolhe
$colabId = (int)($_GET['colaborador'] ?? 0);
if (!ehAdmin()) {
    $colabId = (int)(usuario()['colaborador_id'] ?? 0);
}

$colabs = ehAdmin()
  ? $pdo->query("SELECT id,nome FROM colaboradores WHERE ativo=1 ORDER BY nome")->fetchAll()
  : [];

$hist = [];
$resumo = null;
if ($colabId) {
    $st = $pdo->prepare(
      "SELECT es.data_evento, es.evento, es.horario_chegada, ec.nivel_na_escala
       FROM escala_colaboradores ec
       JOIN escalas es ON es.id = ec.escala_id
       WHERE ec.colaborador_id = ?
       ORDER BY es.data_evento DESC"
    );
    $st->execute([$colabId]);
    $hist = $st->fetchAll();

    $r = $pdo->prepare("SELECT nome,nivel FROM colaboradores WHERE id=?");
    $r->execute([$colabId]); $resumo = $r->fetch();
}

$titulo = 'Histórico';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Histórico de escalas</h1>
<p class="page-sub">Registro de participações de cada colaborador.</p>

<?php if (ehAdmin()): ?>
<div class="card">
  <form method="get" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap">
    <div style="min-width:260px"><label>Colaborador</label>
      <select name="colaborador" onchange="this.form.submit()">
        <option value="">Selecione…</option>
        <?php foreach ($colabs as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $c['id']==$colabId?'selected':'' ?>><?= e($c['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($resumo): ?>
<div class="card">
  <div class="flex-between">
    <h2><?= e($resumo['nome']) ?> <span class="badge <?= $resumo['nivel'] ?>"><?= nivelLabel($resumo['nivel']) ?></span></h2>
    <span class="muted"><?= count($hist) ?> participação(ões)</span>
  </div>
  <table>
    <thead><tr><th>Data</th><th>Evento</th><th>Chegada</th><th>Função</th></tr></thead>
    <tbody>
    <?php foreach ($hist as $h): ?>
      <tr>
        <td><?= date('d/m/Y', strtotime($h['data_evento'])) ?></td>
        <td><?= e($h['evento']) ?></td>
        <td><?= substr($h['horario_chegada'],0,5) ?></td>
        <td><span class="badge <?= $h['nivel_na_escala'] ?>"><?= nivelLabel($h['nivel_na_escala']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$hist):?><tr><td colspan="4" class="muted">Sem registros.</td></tr><?php endif;?>
    </tbody>
  </table>
</div>
<?php elseif (!ehAdmin()): ?>
  <div class="card"><p class="muted">Nenhum vínculo de colaborador associado a este usuário.</p></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
