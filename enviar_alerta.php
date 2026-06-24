<?php
require_once __DIR__ . '/includes/functions.php';
exigirAdmin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $mensagem = trim($_POST['mensagem'] ?? '');
    $destino  = $_POST['destino'] ?? 'todos';
    $selecionados = $_POST['colaboradores'] ?? [];

    // valida mensagem (máx 50 caracteres)
    if ($mensagem === '') {
        flash('Digite a mensagem do alerta.', 'erro');
        redirect('enviar_alerta.php');
    }
    if (mb_strlen($mensagem) > 50) {
        flash('A mensagem deve ter no máximo 50 caracteres.', 'erro');
        redirect('enviar_alerta.php');
    }

    // monta a lista de colaboradores que vão receber
    if ($destino === 'selecionados') {
        $ids = array_filter(array_map('intval', (array)$selecionados));
        if (!$ids) {
            flash('Selecione ao menos um colaborador.', 'erro');
            redirect('enviar_alerta.php');
        }
        $in = implode(',', $ids);
        $alvos = $pdo->query("SELECT id FROM colaboradores WHERE ativo=1 AND id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $alvos = $pdo->query("SELECT id FROM colaboradores WHERE ativo=1")->fetchAll(PDO::FETCH_COLUMN);
    }

    if (!$alvos) {
        flash('Nenhum colaborador ativo para receber o alerta.', 'erro');
        redirect('enviar_alerta.php');
    }

    // insere a notificação para cada colaborador (troca_id NULL = alerta geral)
    $stmt = $pdo->prepare(
      "INSERT INTO notificacoes (colaborador_id, para_admin, mensagem, troca_id)
       VALUES (?, 0, ?, NULL)"
    );
    foreach ($alvos as $cid) {
        $stmt->execute([$cid, $mensagem]);
    }

    flash('Alerta enviado para ' . count($alvos) . ' colaborador(es).');
    redirect('enviar_alerta.php');
}

$colabs = $pdo->query(
  "SELECT id, nome, nivel FROM colaboradores WHERE ativo=1
   ORDER BY FIELD(nivel,'lider','pleno','junior'), nome"
)->fetchAll();

$titulo = 'Enviar alerta';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Enviar alerta</h1>
<p class="page-sub">Envie um aviso que aparece na tela inicial dos colaboradores.</p>

<div class="card" style="max-width:620px">
  <form method="post" id="formAlerta">
    <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">

    <label>Mensagem do alerta</label>
    <input type="text" name="mensagem" id="msgAlerta" maxlength="50" required
           placeholder="Ex.: Reunião geral domingo às 16h" autocomplete="off">
    <p class="muted" style="margin:.3rem 0 1rem">
      <span id="contador">0</span>/50 caracteres
    </p>

    <label>Enviar para</label>
    <div style="display:flex;gap:1.2rem;margin:.4rem 0 1rem;flex-wrap:wrap">
      <label class="radio-inline"><input type="radio" name="destino" value="todos" checked onchange="toggleLista()"> Todos os colaboradores</label>
      <label class="radio-inline"><input type="radio" name="destino" value="selecionados" onchange="toggleLista()"> Selecionar colaboradores</label>
    </div>

    <div id="listaColabs" style="display:none;border:1px solid var(--borda);border-radius:10px;padding:.8rem;margin-bottom:1rem;max-height:300px;overflow-y:auto">
      <div style="margin-bottom:.6rem">
        <button type="button" class="btn sm sec" onclick="marcarTodos(true)">Marcar todos</button>
        <button type="button" class="btn sm sec" onclick="marcarTodos(false)">Desmarcar todos</button>
      </div>
      <?php foreach ($colabs as $c): ?>
        <label class="check-colab">
          <input type="checkbox" name="colaboradores[]" value="<?= $c['id'] ?>">
          <?= e($c['nome']) ?> <span class="badge <?= $c['nivel'] ?>"><?= nivelLabel($c['nivel']) ?></span>
        </label>
      <?php endforeach; ?>
      <?php if (!$colabs): ?><p class="muted">Nenhum colaborador ativo.</p><?php endif; ?>
    </div>

    <button class="btn">📢 Enviar alerta</button>
  </form>
</div>

<style>
.radio-inline{display:flex;align-items:center;gap:.4rem;cursor:pointer;font-weight:500}
.check-colab{display:flex;align-items:center;gap:.5rem;padding:.35rem 0;cursor:pointer}
.check-colab input{width:auto}
</style>

<script>
const msg = document.getElementById('msgAlerta');
const contador = document.getElementById('contador');
msg.addEventListener('input', () => { contador.textContent = msg.value.length; });

function toggleLista(){
  const sel = document.querySelector('input[name=destino]:checked').value;
  document.getElementById('listaColabs').style.display = sel === 'selecionados' ? 'block' : 'none';
}
function marcarTodos(valor){
  document.querySelectorAll('#listaColabs input[type=checkbox]').forEach(c => c.checked = valor);
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
