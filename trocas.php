<?php
require_once __DIR__ . '/includes/trocas.php';
exigirLogin();
$pdo = db();
$u = usuario();
$meuColabId = (int)($u['colaborador_id'] ?? 0);

// ao abrir a tela de trocas, marca as notificações do usuário como lidas
if ($meuColabId) {
    $pdo->prepare("UPDATE notificacoes SET lida=1 WHERE colaborador_id=?")->execute([$meuColabId]);
}
if (ehAdmin()) {
    $pdo->prepare("UPDATE notificacoes SET lida=1 WHERE para_admin=1")->execute();
}

// ------------------------------------------------------------
//  AÇÕES
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $op = $_POST['op'] ?? '';

    // 1) colaborador solicita troca
    if ($op === 'solicitar' && $meuColabId) {
        $escalaOrigem = (int)$_POST['escala_origem_id'];
        $alvoId       = (int)$_POST['alvo_id'];
        $escalaAlvo   = $_POST['escala_alvo_id'] !== '' ? (int)$_POST['escala_alvo_id'] : null;

        // valida: solicitante realmente está na escala de origem
        $chk = $pdo->prepare("SELECT 1 FROM escala_colaboradores WHERE escala_id=? AND colaborador_id=?");
        $chk->execute([$escalaOrigem, $meuColabId]);
        if (!$chk->fetchColumn()) {
            flash('Você não está escalado nesse evento.', 'erro');
            redirect('trocas.php');
        }

        // valida compatibilidade de nível
        $meuNivel = $pdo->prepare("SELECT nivel FROM colaboradores WHERE id=?");
        $meuNivel->execute([$meuColabId]); $meuNivel = $meuNivel->fetchColumn();
        $nivelAlvo = $pdo->prepare("SELECT nivel FROM colaboradores WHERE id=?");
        $nivelAlvo->execute([$alvoId]); $nivelAlvo = $nivelAlvo->fetchColumn();

        if (!in_array($nivelAlvo, niveisCompativeis($meuNivel), true)) {
            flash('Troca não permitida entre esses níveis.', 'erro');
            redirect('trocas.php');
        }

        $pdo->prepare(
            "INSERT INTO trocas_escala (solicitante_id, escala_origem_id, alvo_id, escala_alvo_id, status)
             VALUES (?,?,?,?, 'pendente_colaborador')"
        )->execute([$meuColabId, $escalaOrigem, $alvoId, $escalaAlvo]);
        $trocaId = (int)$pdo->lastInsertId();

        $nomeSol = $pdo->prepare("SELECT nome FROM colaboradores WHERE id=?");
        $nomeSol->execute([$meuColabId]); $nomeSol = $nomeSol->fetchColumn();
        notificarColaborador($pdo, $alvoId, "$nomeSol solicitou uma troca de escala com você.", $trocaId);

        flash('Solicitação de troca enviada. Aguardando resposta do colega.');
        redirect('trocas.php');
    }

    // 2) alvo aceita ou recusa
    if (($op === 'aceitar' || $op === 'recusar_colab') && $meuColabId) {
        $trocaId = (int)$_POST['troca_id'];
        $st = $pdo->prepare("SELECT * FROM trocas_escala WHERE id=? AND alvo_id=? AND status='pendente_colaborador'");
        $st->execute([$trocaId, $meuColabId]);
        $tr = $st->fetch();
        if (!$tr) { flash('Solicitação não encontrada.', 'erro'); redirect('trocas.php'); }

        $nomeAlvo = $pdo->prepare("SELECT nome FROM colaboradores WHERE id=?");
        $nomeAlvo->execute([$meuColabId]); $nomeAlvo = $nomeAlvo->fetchColumn();

        if ($op === 'aceitar') {
            $pdo->prepare("UPDATE trocas_escala SET status='pendente_admin', respondido_em=NOW() WHERE id=?")
                ->execute([$trocaId]);
            notificarColaborador($pdo, (int)$tr['solicitante_id'], "$nomeAlvo aceitou sua troca. Aguardando confirmação do administrador.", $trocaId);
            notificarAdmin($pdo, "Troca aceita por $nomeAlvo aguardando sua confirmação.", $trocaId);
            flash('Troca aceita. Aguardando confirmação do administrador.');
        } else {
            $pdo->prepare("UPDATE trocas_escala SET status='recusada_colaborador', respondido_em=NOW() WHERE id=?")
                ->execute([$trocaId]);
            notificarColaborador($pdo, (int)$tr['solicitante_id'], "$nomeAlvo recusou sua solicitação de troca.", $trocaId);
            flash('Você recusou a troca.');
        }
        redirect('trocas.php');
    }

    // 3) admin confirma ou recusa
    if (($op === 'confirmar' || $op === 'recusar_admin') && ehAdmin()) {
        $trocaId = (int)$_POST['troca_id'];
        $st = $pdo->prepare("SELECT * FROM trocas_escala WHERE id=? AND status='pendente_admin'");
        $st->execute([$trocaId]);
        $tr = $st->fetch();
        if (!$tr) { flash('Troca não encontrada.', 'erro'); redirect('trocas.php'); }

        if ($op === 'confirmar') {
            [$ok, $msg] = efetivarTroca($pdo, $tr);
            if (!$ok) { flash($msg, 'erro'); redirect('trocas.php'); }
            $pdo->prepare("UPDATE trocas_escala SET status='confirmada', decidido_em=NOW() WHERE id=?")
                ->execute([$trocaId]);
            notificarColaborador($pdo, (int)$tr['solicitante_id'], "Sua troca foi confirmada pelo administrador e já está efetivada.", $trocaId);
            notificarColaborador($pdo, (int)$tr['alvo_id'],        "A troca foi confirmada pelo administrador e já está efetivada.", $trocaId);
            flash('Troca confirmada e efetivada nas escalas.');
        } else {
            $pdo->prepare("UPDATE trocas_escala SET status='recusada_admin', decidido_em=NOW() WHERE id=?")
                ->execute([$trocaId]);
            notificarColaborador($pdo, (int)$tr['solicitante_id'], "Sua troca foi recusada pelo administrador.", $trocaId);
            notificarColaborador($pdo, (int)$tr['alvo_id'],        "A troca que você aceitou foi recusada pelo administrador.", $trocaId);
            flash('Troca recusada.');
        }
        redirect('trocas.php');
    }

    // 4) solicitante cancela uma troca ainda pendente
    if ($op === 'cancelar' && $meuColabId) {
        $trocaId = (int)$_POST['troca_id'];
        $pdo->prepare("UPDATE trocas_escala SET status='cancelada' WHERE id=? AND solicitante_id=? AND status IN ('pendente_colaborador','pendente_admin')")
            ->execute([$trocaId, $meuColabId]);
        flash('Solicitação cancelada.');
        redirect('trocas.php');
    }
}

// ------------------------------------------------------------
//  DADOS PARA EXIBIÇÃO
// ------------------------------------------------------------
$hoje = date('Y-m-d');

// eventos futuros em que EU estou escalado (origem da troca)
$meusEventos = [];
$meuNivel = null;
if ($meuColabId) {
    $st = $pdo->prepare("SELECT nivel FROM colaboradores WHERE id=?");
    $st->execute([$meuColabId]); $meuNivel = $st->fetchColumn();

    $st = $pdo->prepare(
        "SELECT es.* FROM escala_colaboradores ec
         JOIN escalas es ON es.id = ec.escala_id
         WHERE ec.colaborador_id = ? AND es.data_evento >= ?
         ORDER BY es.data_evento"
    );
    $st->execute([$meuColabId, $hoje]);
    $meusEventos = $st->fetchAll();
}

// colaboradores compatíveis por nível (para escolher parceiro)
$compativeis = [];
if ($meuNivel) {
    $in = implode(',', array_fill(0, count(niveisCompativeis($meuNivel)), '?'));
    $st = $pdo->prepare("SELECT id, nome, nivel FROM colaboradores WHERE ativo=1 AND id<>? AND nivel IN ($in) ORDER BY FIELD(nivel,'lider','pleno','junior'), nome");
    $st->execute(array_merge([$meuColabId], niveisCompativeis($meuNivel)));
    $compativeis = $st->fetchAll();
}

// trocas que preciso responder (sou o alvo)
$pendentesParaMim = [];
if ($meuColabId) {
    $st = $pdo->prepare("SELECT * FROM trocas_escala WHERE alvo_id=? AND status='pendente_colaborador' ORDER BY criado_em DESC");
    $st->execute([$meuColabId]);
    $pendentesParaMim = $st->fetchAll();
}

// minhas solicitações enviadas
$minhasSolicitacoes = [];
if ($meuColabId) {
    $st = $pdo->prepare("SELECT * FROM trocas_escala WHERE solicitante_id=? ORDER BY criado_em DESC LIMIT 20");
    $st->execute([$meuColabId]);
    $minhasSolicitacoes = $st->fetchAll();
}

// trocas aguardando o admin
$pendentesAdmin = [];
if (ehAdmin()) {
    $pendentesAdmin = $pdo->query("SELECT * FROM trocas_escala WHERE status='pendente_admin' ORDER BY criado_em DESC")->fetchAll();
}

// helper de nome
function nomeColab(PDO $pdo, int $id): string {
    $s = $pdo->prepare("SELECT nome FROM colaboradores WHERE id=?");
    $s->execute([$id]); return (string)($s->fetchColumn() ?: '—');
}
function rotuloEscala(?array $es): string {
    if (!$es) return 'Sem escala (assume a vaga)';
    return $es['evento'] . ' · ' . date('d/m/Y', strtotime($es['data_evento'])) . ' · ' . substr($es['horario_chegada'],0,5);
}
$statusLabel = [
    'pendente_colaborador' => ['Aguardando colega','warn'],
    'pendente_admin'       => ['Aguardando admin','warn'],
    'confirmada'           => ['Confirmada','ok'],
    'recusada_colaborador' => ['Recusada pelo colega','junior'],
    'recusada_admin'       => ['Recusada pelo admin','junior'],
    'cancelada'            => ['Cancelada','junior'],
];

$titulo = 'Trocas de Escala';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Trocas de escala</h1>
<p class="page-sub">Solicite trocas com colegas do mesmo grupo de nível.</p>

<?php /* ---------- Trocas para EU responder ---------- */ ?>
<?php foreach ($pendentesParaMim as $tr):
    $eo = descreveEscala($pdo, (int)$tr['escala_origem_id']);
    $ea = descreveEscala($pdo, $tr['escala_alvo_id'] !== null ? (int)$tr['escala_alvo_id'] : null);
    $solNome = nomeColab($pdo, (int)$tr['solicitante_id']); ?>
<div class="card" style="border-color:var(--laranja-4)">
  <h2>🔔 <?= e($solNome) ?> quer trocar de escala com você</h2>
  <div class="grid" style="grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
    <div class="stat">
      <div class="l">Você está escalado em</div>
      <div style="font-weight:700;color:var(--laranja-6);margin-top:.3rem"><?= e(rotuloEscala($ea)) ?></div>
    </div>
    <div class="stat">
      <div class="l">Passaria para o evento de <?= e($solNome) ?></div>
      <div style="font-weight:700;color:var(--laranja-6);margin-top:.3rem"><?= e(rotuloEscala($eo)) ?></div>
    </div>
  </div>
  <form method="post" style="display:inline">
    <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
    <input type="hidden" name="op" value="aceitar">
    <input type="hidden" name="troca_id" value="<?= $tr['id'] ?>">
    <button class="btn">Aceitar troca</button>
  </form>
  <form method="post" style="display:inline" onsubmit="return confirm('Recusar esta troca?')">
    <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
    <input type="hidden" name="op" value="recusar_colab">
    <input type="hidden" name="troca_id" value="<?= $tr['id'] ?>">
    <button class="btn danger">Recusar</button>
  </form>
</div>
<?php endforeach; ?>

<?php /* ---------- Admin: confirmar trocas ---------- */ ?>
<?php if (ehAdmin() && $pendentesAdmin): ?>
<div class="card" style="border-color:var(--laranja-4)">
  <h2>Trocas aguardando sua confirmação <span class="badge warn"><?= count($pendentesAdmin) ?></span></h2>
  <table>
    <thead><tr><th>Solicitante</th><th>Colega</th><th>Evento origem</th><th>Evento destino</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pendentesAdmin as $tr):
        $eo = descreveEscala($pdo, (int)$tr['escala_origem_id']);
        $ea = descreveEscala($pdo, $tr['escala_alvo_id'] !== null ? (int)$tr['escala_alvo_id'] : null); ?>
      <tr>
        <td><?= e(nomeColab($pdo,(int)$tr['solicitante_id'])) ?></td>
        <td><?= e(nomeColab($pdo,(int)$tr['alvo_id'])) ?></td>
        <td><?= e(rotuloEscala($eo)) ?></td>
        <td><?= e(rotuloEscala($ea)) ?></td>
        <td class="right" style="white-space:nowrap">
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
            <input type="hidden" name="op" value="confirmar">
            <input type="hidden" name="troca_id" value="<?= $tr['id'] ?>">
            <button class="btn sm">Confirmar</button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('Recusar esta troca?')">
            <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
            <input type="hidden" name="op" value="recusar_admin">
            <input type="hidden" name="troca_id" value="<?= $tr['id'] ?>">
            <button class="btn sm danger">Recusar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php /* ---------- Colaborador: nova solicitação ---------- */ ?>
<?php if ($meuColabId): ?>
<div class="card">
  <h2>Solicitar nova troca</h2>
  <?php if (!$meusEventos): ?>
    <p class="muted">Você não está escalado em nenhum evento futuro.</p>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
    <input type="hidden" name="op" value="solicitar">
    <div class="form-row">
      <div>
        <label>Seu evento (que você quer trocar)</label>
        <select name="escala_origem_id" required>
          <?php foreach ($meusEventos as $ev): ?>
            <option value="<?= $ev['id'] ?>"><?= e(rotuloEscala($ev)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Trocar com (mesmo grupo de nível)</label>
        <select name="alvo_id" id="alvo" required onchange="carregarEventosAlvo()">
          <option value="">Selecione…</option>
          <?php foreach ($compativeis as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['nome']) ?> · <?= e(nivelLabel($c['nivel'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div>
        <label>Evento do colega (opcional)</label>
        <select name="escala_alvo_id" id="escalaAlvo">
          <option value="">— ele(a) apenas assume sua vaga —</option>
        </select>
        <p class="muted" style="margin-top:.4rem">Se o colega também está escalado e vocês vão trocar de lugar, escolha o evento dele.</p>
      </div>
    </div>
    <button class="btn">Enviar solicitação</button>
  </form>
  <?php endif; ?>
</div>

<?php /* ---------- Minhas solicitações ---------- */ ?>
<div class="card">
  <h2>Minhas solicitações</h2>
  <table>
    <thead><tr><th>Colega</th><th>Status</th><th>Data</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($minhasSolicitacoes as $tr):
        [$lbl,$cor] = $statusLabel[$tr['status']] ?? [$tr['status'],'junior']; ?>
      <tr>
        <td><?= e(nomeColab($pdo,(int)$tr['alvo_id'])) ?></td>
        <td><span class="badge <?= $cor ?>"><?= e($lbl) ?></span></td>
        <td><?= date('d/m/Y H:i', strtotime($tr['criado_em'])) ?></td>
        <td class="right">
          <?php if (in_array($tr['status'], ['pendente_colaborador','pendente_admin'], true)): ?>
          <form method="post" onsubmit="return confirm('Cancelar esta solicitação?')">
            <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
            <input type="hidden" name="op" value="cancelar">
            <input type="hidden" name="troca_id" value="<?= $tr['id'] ?>">
            <button class="btn sm sec">Cancelar</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$minhasSolicitacoes):?><tr><td colspan="4" class="muted">Nenhuma solicitação ainda.</td></tr><?php endif;?>
    </tbody>
  </table>
</div>
<?php else: ?>
  <div class="card"><p class="muted">Este usuário não está vinculado a um colaborador, então não pode solicitar trocas. Use uma conta de colaborador.</p></div>
<?php endif; ?>

<script>
// eventos futuros de cada colaborador, para preencher o select do evento do colega
const eventosPorColab = <?php
    // monta mapa colaborador_id => [{id, label}]
    $mapa = [];
    if ($compativeis) {
        $ids = implode(',', array_column($compativeis,'id'));
        $q = $pdo->query(
          "SELECT ec.colaborador_id, es.id, es.evento, es.data_evento, es.horario_chegada
           FROM escala_colaboradores ec JOIN escalas es ON es.id=ec.escala_id
           WHERE ec.colaborador_id IN ($ids) AND es.data_evento >= '".$hoje."'
           ORDER BY es.data_evento"
        );
        foreach ($q as $r) {
            $lbl = $r['evento'].' · '.date('d/m/Y', strtotime($r['data_evento'])).' · '.substr($r['horario_chegada'],0,5);
            $mapa[$r['colaborador_id']][] = ['id'=>(int)$r['id'], 'label'=>$lbl];
        }
    }
    echo json_encode($mapa, JSON_UNESCAPED_UNICODE);
?>;
function carregarEventosAlvo(){
  const alvo = document.getElementById('alvo').value;
  const sel = document.getElementById('escalaAlvo');
  sel.innerHTML = '<option value="">— ele(a) apenas assume sua vaga —</option>';
  (eventosPorColab[alvo]||[]).forEach(ev=>{
    const o = document.createElement('option');
    o.value = ev.id; o.textContent = ev.label;
    sel.appendChild(o);
  });
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
