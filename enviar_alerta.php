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

// ---- histórico de alertas enviados (ordem decrescente) ----
$alertasEnviados = $pdo->query(
  "SELECT a.id, a.mensagem, a.criado_em,
          COUNT(d.id) AS total_dest,
          SUM(d.visto_em IS NOT NULL) AS total_vistos
   FROM alertas a
   LEFT JOIN alertas_destinatarios d ON d.alerta_id = a.id
   GROUP BY a.id
   ORDER BY a.criado_em DESC"
)->fetchAll();

// destinatários de cada alerta (nome + status), agrupados
$destPorAlerta = [];
if ($alertasEnviados) {
    $ids = implode(',', array_map(fn($a) => (int)$a['id'], $alertasEnviados));
    $rowsDest = $pdo->query(
      "SELECT d.alerta_id, u.nome, u.tipo, d.visto_em
       FROM alertas_destinatarios d
       JOIN usuarios u ON u.id = d.usuario_id
       WHERE d.alerta_id IN ($ids)
       ORDER BY (d.visto_em IS NULL), u.nome"
    )->fetchAll();
    foreach ($rowsDest as $rd) {
        $destPorAlerta[$rd['alerta_id']][] = $rd;
    }
}

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

<?php if ($alertasEnviados): ?>
<h2 style="color:var(--laranja-6);margin:1.5rem 0 .8rem">Histórico de alertas</h2>
<div class="hist-alertas">
  <?php foreach ($alertasEnviados as $i => $a):
    $dests = $destPorAlerta[$a['id']] ?? [];
    $vistos = (int)$a['total_vistos'];
    $totalD = (int)$a['total_dest'];
    $aberto = ($i === 0); // o mais recente começa expandido
  ?>
  <div class="alerta-hist <?= $aberto?'aberto':'' ?>">
    <button type="button" class="alerta-cab" onclick="toggleAlerta(this)">
      <div class="alerta-cab-txt">
        <span class="alerta-msg">📢 <?= e($a['mensagem']) ?></span>
        <span class="alerta-meta">
          <?= date('d/m/Y H:i', strtotime($a['criado_em'])) ?>
          · <?= $vistos ?>/<?= $totalD ?> leram
        </span>
      </div>
      <span class="alerta-seta">▾</span>
    </button>
    <div class="alerta-corpo">
      <?php if (!$dests): ?>
        <p class="muted" style="padding:.5rem 0">Sem destinatários.</p>
      <?php else: ?>
      <ul class="dest-lista">
        <?php foreach ($dests as $d): ?>
        <li>
          <?php if ($d['visto_em']): ?>
            <span class="dest-status lido">✓ lido</span>
          <?php else: ?>
            <span class="dest-status nao">⏳ não lido</span>
          <?php endif; ?>
          <span class="dest-nome"><?= e($d['nome']) ?><?php if($d['tipo']==='administrador'):?> <span class="badge lider">admin</span><?php endif;?></span>
          <?php if ($d['visto_em']): ?>
            <span class="dest-quando"><?= date('d/m H:i', strtotime($d['visto_em'])) ?></span>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
.opcoes-destino{display:flex;flex-direction:column;gap:.6rem;margin:.4rem 0 .9rem}
.opt{display:flex;align-items:center;gap:.6rem;cursor:pointer;font-weight:500;line-height:1.2}
.opt input{width:18px;height:18px;flex:0 0 auto;margin:0}
.opt span{flex:1}
.opt-admin{padding:.7rem .9rem;background:var(--laranja-1);border:1px solid var(--borda);
  border-radius:10px;margin-bottom:1.2rem}
.check-colab{display:flex;align-items:center;gap:.5rem;padding:.35rem 0;cursor:pointer}
.check-colab input{width:auto}

/* histórico de alertas */
.hist-alertas{display:flex;flex-direction:column;gap:.7rem;max-width:680px}
.alerta-hist{background:#fff;border:1px solid var(--borda);border-radius:12px;overflow:hidden}
.alerta-cab{width:100%;background:none;border:none;cursor:pointer;display:flex;
  align-items:center;justify-content:space-between;gap:.6rem;padding:.9rem 1rem;text-align:left}
.alerta-cab:hover{background:var(--laranja-1)}
.alerta-cab-txt{display:flex;flex-direction:column;gap:.2rem;flex:1}
.alerta-msg{font-weight:700;color:var(--laranja-6);font-size:.98rem}
.alerta-meta{font-size:.8rem;color:var(--texto-suave)}
.alerta-seta{color:var(--laranja-5);font-size:1.1rem;transition:transform .2s;flex:0 0 auto}
.alerta-hist.aberto .alerta-seta{transform:rotate(180deg)}
.alerta-corpo{display:none;padding:0 1rem 1rem;border-top:1px solid var(--borda)}
.alerta-hist.aberto .alerta-corpo{display:block}
.dest-lista{list-style:none;padding:0;margin:.6rem 0 0}
.dest-lista li{display:flex;align-items:center;gap:.6rem;padding:.35rem 0;font-size:.9rem;
  border-bottom:1px dashed var(--borda)}
.dest-lista li:last-child{border-bottom:none}
.dest-status{font-weight:700;font-size:.78rem;padding:.1rem .5rem;border-radius:12px;flex:0 0 auto;min-width:74px;text-align:center}
.dest-status.lido{background:#dff3e3;color:#2f7d49}
.dest-status.nao{background:#fff3e0;color:#9a5a12}
.dest-nome{flex:1;color:#444}
.dest-quando{font-size:.78rem;color:var(--texto-suave);white-space:nowrap}
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
function toggleAlerta(btn){
  btn.closest('.alerta-hist').classList.toggle('aberto');
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
