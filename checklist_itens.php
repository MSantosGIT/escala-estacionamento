<?php
require_once __DIR__ . '/includes/functions.php';
exigirAdmin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $op = $_POST['op'] ?? '';

    if ($op === 'criar') {
        $desc  = trim($_POST['descricao'] ?? '');
        $ordem = (int)($_POST['ordem'] ?? 0);
        if ($desc === '' || mb_strlen($desc) > 200) {
            flash('Informe a descrição do item (até 200 caracteres).', 'erro');
        } else {
            $pdo->prepare("INSERT INTO checklist_itens (descricao, ordem) VALUES (?, ?)")
                ->execute([$desc, $ordem]);
            flash('Item adicionado ao checklist.');
        }
    }

    if ($op === 'editar') {
        $id    = (int)($_POST['id'] ?? 0);
        $desc  = trim($_POST['descricao'] ?? '');
        $ordem = (int)($_POST['ordem'] ?? 0);
        if ($id && $desc !== '' && mb_strlen($desc) <= 200) {
            $pdo->prepare("UPDATE checklist_itens SET descricao=?, ordem=? WHERE id=?")
                ->execute([$desc, $ordem, $id]);
            flash('Item atualizado.');
        } else {
            flash('Dados inválidos.', 'erro');
        }
    }

    if ($op === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            // exclusão não afeta encerramentos passados (eles guardam cópia do texto)
            $pdo->prepare("DELETE FROM checklist_itens WHERE id=?")->execute([$id]);
            flash('Item removido do checklist.');
        }
    }

    redirect('checklist_itens.php');
}

$editar = null;
if (isset($_GET['editar'])) {
    $st = $pdo->prepare("SELECT * FROM checklist_itens WHERE id=?");
    $st->execute([(int)$_GET['editar']]);
    $editar = $st->fetch();
}

$itens = $pdo->query("SELECT * FROM checklist_itens ORDER BY ordem, id")->fetchAll();

$titulo = 'Itens do checklist';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Checklist de encerramento</h1>
<p class="page-sub">Itens que o líder confirma ao encerrar cada evento. Alterações valem para os próximos encerramentos — os já realizados não mudam.</p>

<div class="acoes-topo">
  <button type="button" class="btn-toggle <?= $editar?'ativo':'' ?>" data-alvo="form-item">
    <?= $editar ? '✏️ Editar item' : '➕ Novo item' ?>
  </button>
</div>

<div class="card card-recolhivel <?= $editar?'aberto':'' ?>" id="form-item">
  <h2><?= $editar ? 'Editar item' : 'Novo item' ?></h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
    <input type="hidden" name="op" value="<?= $editar ? 'editar' : 'criar' ?>">
    <?php if ($editar): ?><input type="hidden" name="id" value="<?= (int)$editar['id'] ?>"><?php endif; ?>
    <div class="form-row">
      <div style="flex:3">
        <label>Descrição do item</label>
        <input type="text" name="descricao" maxlength="200" required
               value="<?= e($editar['descricao'] ?? '') ?>" placeholder="Ex.: Portões trancados">
      </div>
      <div style="flex:1">
        <label>Ordem</label>
        <input type="number" name="ordem" value="<?= (int)($editar['ordem'] ?? (count($itens)+1)) ?>">
      </div>
    </div>
    <button class="btn" style="margin-top:.8rem"><?= $editar ? 'Salvar alterações' : 'Adicionar item' ?></button>
    <?php if ($editar): ?><a class="btn sec" style="margin-top:.8rem" href="checklist_itens.php">Cancelar</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <h2>Itens atuais <span class="badge ok"><?= count($itens) ?></span></h2>
  <?php if (!$itens): ?>
    <p class="muted">Nenhum item cadastrado. Adicione o primeiro acima.</p>
  <?php else: ?>
  <table>
    <thead><tr><th style="width:60px">Ordem</th><th>Descrição</th><th class="right"></th></tr></thead>
    <tbody>
      <?php foreach ($itens as $it): ?>
      <tr>
        <td><?= (int)$it['ordem'] ?></td>
        <td><?= e($it['descricao']) ?></td>
        <td class="right" style="white-space:nowrap">
          <a class="btn sm sec" href="checklist_itens.php?editar=<?= (int)$it['id'] ?>#form-item" title="Editar">✏️</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Remover este item do checklist?')">
            <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
            <input type="hidden" name="op" value="excluir">
            <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <button class="btn sm sec" title="Excluir">🗑️</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
