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

    // ---- resolve os USUÁRIOS que vão receber o alerta ----
    // (cada um terá sua própria linha, marcando como visto individualmente)
    $usuariosAlvo = [];

    // colaboradores selecionados → busca o usuario_id vinculado a cada colaborador
    $colabIds = [];
    if ($destino === 'selecionados') {
        $colabIds = array_filter(array_map('intval', (array)$selecionados));
    } else {
        $colabIds = $pdo->query("SELECT id FROM colaboradores WHERE ativo=1")->fetchAll(PDO::FETCH_COLUMN);
    }
    $qtdColab = 0;
    if ($colabIds) {
        $in = implode(',', $colabIds);
        // pega os usuários ligados a esses colaboradores
        $us = $pdo->query("SELECT id FROM usuarios WHERE colaborador_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($us as $uid) $usuariosAlvo[(int)$uid] = true;
        $qtdColab = count($us);
    }

    // administradores → todos os usuários do tipo administrador
    $qtdAdmin = 0;
    if ($incluirAdmin) {
        $ad = $pdo->query("SELECT id FROM usuarios WHERE tipo='administrador'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ad as $uid) $usuariosAlvo[(int)$uid] = true;
        $qtdAdmin = count($ad);
    }

    if (!$usuariosAlvo) {
        flash('Nenhum destinatário encontrado. Verifique se os colaboradores têm usuário vinculado.', 'erro');
        redirect('enviar_alerta.php');
    }

    // cria o alerta e as linhas de destinatário (uma por usuário)
    $pdo->prepare("INSERT INTO alertas (mensagem, criado_por) VALUES (?, ?)")
        ->execute([$mensagem, (int)(usuario()['id'] ?? 0)]);
    $alertaId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare(
      "INSERT INTO alertas_destinatarios (alerta_id, usuario_id) VALUES (?, ?)"
    );
    foreach (array_keys($usuariosAlvo) as $uid) {
        $stmt->execute([$alertaId, $uid]);
    }

    $partes = [];
    if ($qtdColab) $partes[] = $qtdColab . ' colaborador(es)';
    if ($qtdAdmin) $partes[] = $qtdAdmin . ' administrador(es)';
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
