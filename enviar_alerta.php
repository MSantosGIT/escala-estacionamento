<?php
require_once __DIR__ . '/includes/functions.php';
exigirAdmin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $mensagem = trim($_POST['mensagem'] ?? '');
    $destino  = $_POST['destino'] ?? 'todos';
    $incluirAdmin = isset($_POST['incluir_admin']);
    $selecionados = $_POST['colaboradores'] ?? [];

    // valida mensagem (máx 100 caracteres)
    if ($mensagem === '') {
        flash('Digite a mensagem do alerta.', 'erro');
        redirect('enviar_alerta.php');
    }
    if (mb_strlen($mensagem) > 100) {
        flash('A mensagem deve ter no máximo 100 caracteres.', 'erro');
        redirect('enviar_alerta.php');
    }

    // monta a lista de colaboradores que vão receber
    if ($destino === 'selecionados') {
        $ids = array_filter(array_map('intval', (array)$selecionados));
        if (!$ids && !$incluirAdmin) {
            flash('Selecione ao menos um colaborador.', 'erro');
            redirect('enviar_alerta.php');
        }
        $alvos = [];
        if ($ids) {
            $in = implode(',', $ids);
            $alvos = $pdo->query("SELECT id FROM colaboradores WHERE ativo=1 AND id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
        }
    } else {
        $alvos = $pdo->query("SELECT id FROM colaboradores WHERE ativo=1")->fetchAll(PDO::FETCH_COLUMN);
    }

    // insere a notificação para cada colaborador (troca_id NULL = alerta geral)
    $stmt = $pdo->prepare(
      "INSERT INTO notificacoes (colaborador_id, para_admin, mensagem, troca_id)
       VALUES (?, 0, ?, NULL)"
    );
    foreach ($alvos as $cid) {
        $stmt->execute([$cid, $mensagem]);
    }

    // se marcado, envia também para os administradores (notificação para_admin)
    $totalAdmin = 0;
    if ($incluirAdmin) {
        $pdo->prepare(
          "INSERT INTO notificacoes (colaborador_id, para_admin, mensagem, troca_id)
           VALUES (NULL, 1, ?, NULL)"
        )->execute([$mensagem]);
        $totalAdmin = 1;
    }

    if (!$alvos && !$totalAdmin) {
        flash('Nenhum destinatário para receber o alerta.', 'erro');
        redirect('enviar_alerta.php');
    }

    $partes = [];
    if ($alvos) $partes[] = count($alvos) . ' colaborador(es)';
    if ($totalAdmin) $partes[] = 'os administradores';
    flash('Alerta enviado para ' . implode(' e ', $partes) . '.');
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
    <input type="text" name="mensagem" id="msgAlerta" maxlength="100" required
           placeholder="Ex.: Reunião geral domingo às 16h" autocomplete="off">
    <p class="muted" style="margin:.3rem 0 1rem">
      <span id="contador">0</span>/100 caracteres
    </p>

    <label style="margin-bottom:.5rem">Enviar para</label>
    <div class="opcoes-destino">
      <label class="opt"><input type="radio" name="destino" value="todos" checked onchange="toggleLista()"> <span>Todos os colaboradores</span></label>
      <label class="opt"><input type="radio" name="destino" value="selecionados" onchange="toggleLista()"> <span>Selecionar colaboradores</span></label>
    </div>

    <label class="opt opt-admin"><input type="checkbox" name="incluir_admin" value="1"> <span>Enviar também para os administradores</span></label>

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
.opcoes-destino{display:flex;flex-direction:column;gap:.6rem;margin:.4rem 0 .9rem}
.opt{display:flex;align-items:center;gap:.6rem;cursor:pointer;font-weight:500;line-height:1.2}
.opt input{width:18px;height:18px;flex:0 0 auto;margin:0}
.opt span{flex:1}
.opt-admin{padding:.7rem .9rem;background:var(--laranja-1);border:1px solid var(--borda);
  border-radius:10px;margin-bottom:1.2rem}
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
