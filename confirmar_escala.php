<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();
$pdo = db();

$u = usuario();
$ehAdm = ehAdmin();
$meuColabId = (int)($u['colaborador_id'] ?? 0);

// janela de confirmação: abre 7 dias antes do evento
define('CONFIRMA_DIAS_ANTES', 7);

// ---- confirmar presença (ciente) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'confirmar') {
    validarCSRF();
    $eid = (int)($_POST['escala_id'] ?? 0);

    if ($eid && $meuColabId) {
        // valida que está mesmo escalado nesse evento
        $st = $pdo->prepare("SELECT 1 FROM escala_colaboradores WHERE escala_id=? AND colaborador_id=?");
        $st->execute([$eid, $meuColabId]);
        if (!$st->fetchColumn()) {
            flash('Você não está escalado neste evento.', 'erro');
            redirect('confirmar_escala.php');
        }
        // valida a janela (só a partir de 7 dias antes, e antes do evento passar)
        $st = $pdo->prepare("SELECT data_evento, horario_chegada FROM escalas WHERE id=?");
        $st->execute([$eid]);
        $ev = $st->fetch();
        $inicio = $ev ? strtotime($ev['data_evento'] . ' ' . $ev['horario_chegada']) : 0;
        $abre = $inicio - CONFIRMA_DIAS_ANTES * 86400;
        if (!$ev) {
            flash('Evento inválido.', 'erro');
        } elseif (time() < $abre) {
            flash('A confirmação abre 7 dias antes do evento.', 'erro');
        } else {
            $pdo->prepare(
              "INSERT IGNORE INTO escala_confirmacoes (escala_id, colaborador_id) VALUES (?, ?)"
            )->execute([$eid, $meuColabId]);
            flash('Presença confirmada. Obrigado!');
        }
    }
    redirect('confirmar_escala.php');
}

// ---- eventos do colaborador dentro da janela de confirmação ----
$minhasEscalas = [];
if ($meuColabId) {
    $st = $pdo->prepare(
      "SELECT e.id, e.evento, e.data_evento, e.horario_chegada,
              cf.confirmado_em
       FROM escala_colaboradores ec
       JOIN escalas e ON e.id = ec.escala_id
       LEFT JOIN escala_confirmacoes cf
              ON cf.escala_id = e.id AND cf.colaborador_id = ec.colaborador_id
       WHERE ec.colaborador_id = ?
         AND e.data_evento >= CURDATE()
       ORDER BY e.data_evento, e.horario_chegada"
    );
    $st->execute([$meuColabId]);
    $minhasEscalas = $st->fetchAll();
}

// ---- admin: pendências de confirmação ----
$pendentes = [];
if ($ehAdm) {
    $st = $pdo->query(
      "SELECT e.id AS escala_id, e.evento, e.data_evento, e.horario_chegada,
              c.nome AS colaborador_nome, c.nivel, c.celular
       FROM escala_colaboradores ec
       JOIN escalas e ON e.id = ec.escala_id
       JOIN colaboradores c ON c.id = ec.colaborador_id
       LEFT JOIN escala_confirmacoes cf
              ON cf.escala_id = e.id AND cf.colaborador_id = ec.colaborador_id
       WHERE e.data_evento >= CURDATE()
         AND cf.id IS NULL
       ORDER BY e.data_evento, e.horario_chegada, c.nome"
    );
    foreach ($st->fetchAll() as $p) {
        $pendentes[(int)$p['escala_id']]['evento'] = $p;
        $pendentes[(int)$p['escala_id']]['itens'][] = $p;
    }
}

$titulo = 'Confirmar escala';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Confirmar escala</h1>
<p class="page-sub">Dê o ciente nos eventos em que você está escalado. A confirmação abre 7 dias antes de cada evento.</p>

<?php if ($meuColabId): ?>
  <?php if (!$minhasEscalas): ?>
    <div class="card"><p class="muted">Você não tem eventos futuros na escala.</p></div>
  <?php else: ?>
  <div class="conf-lista">
    <?php foreach ($minhasEscalas as $es):
      $inicio = strtotime($es['data_evento'] . ' ' . $es['horario_chegada']);
      $abre   = $inicio - CONFIRMA_DIAS_ANTES * 86400;
      $agora  = time();
      $diasFaltando = (int)ceil(($inicio - $agora) / 86400);
      $podeConfirmar = ($agora >= $abre);
    ?>
    <div class="conf-card <?= $es['confirmado_em'] ? 'ok' : ($podeConfirmar ? 'pendente' : '') ?>">
      <div class="conf-cab">
        <div>
          <div class="conf-evento"><?= e($es['evento']) ?></div>
          <div class="conf-data">
            <?= date('d/m/Y', $inicio) ?> · ⏰ <?= substr($es['horario_chegada'],0,5) ?>
            <?php if ($diasFaltando > 0): ?>
              <span class="conf-faltam">em <?= $diasFaltando ?> dia<?= $diasFaltando > 1 ? 's' : '' ?></span>
            <?php elseif ($diasFaltando === 0): ?>
              <span class="conf-faltam hoje">é hoje!</span>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($es['confirmado_em']): ?>
          <span class="conf-selo ok">✓ Confirmado</span>
        <?php elseif ($podeConfirmar): ?>
          <span class="conf-selo pend">⏳ Aguardando ciente</span>
        <?php else: ?>
          <span class="conf-selo espera">Abre <?= date('d/m', $abre) ?></span>
        <?php endif; ?>
      </div>

      <?php if ($es['confirmado_em']): ?>
        <div class="conf-msg-ok">Você confirmou em <?= date('d/m/Y \à\s H:i', strtotime($es['confirmado_em'])) ?>.</div>
      <?php elseif ($podeConfirmar): ?>
        <form method="post" style="margin-top:.7rem">
          <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
          <input type="hidden" name="op" value="confirmar">
          <input type="hidden" name="escala_id" value="<?= (int)$es['id'] ?>">
          <button class="btn">✓ Confirmar minha presença</button>
        </form>
      <?php else: ?>
        <p class="muted" style="margin-top:.5rem;font-size:.87rem">
          A confirmação estará disponível a partir de <?= date('d/m/Y', $abre) ?>.
        </p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php if ($ehAdm): ?>
<h2 style="color:var(--laranja-6);margin:1.8rem 0 .3rem">Pendentes de confirmação</h2>
<p class="page-sub" style="margin-bottom:1rem">Colaboradores escalados que ainda não deram o ciente (eventos futuros).</p>

<?php if (!$pendentes): ?>
  <div class="card"><p class="muted">🎉 Todos os escalados já confirmaram os eventos futuros.</p></div>
<?php else: ?>
  <?php foreach ($pendentes as $eid => $grupo):
    $ev = $grupo['evento'];
    $inicio = strtotime($ev['data_evento'] . ' ' . $ev['horario_chegada']);
    $horasFaltando = ($inicio - time()) / 3600;
    $urgente = $horasFaltando <= 24;
  ?>
  <div class="card <?= $urgente ? 'card-urgente' : '' ?>" style="margin-bottom:1rem">
    <div class="flex-between" style="flex-wrap:wrap;gap:.5rem">
      <h3 style="margin:0;color:var(--laranja-6)">
        <?= e($ev['evento']) ?>
        <span class="muted" style="font-weight:400;font-size:.87rem">
          — <?= date('d/m/Y', $inicio) ?> ⏰ <?= substr($ev['horario_chegada'],0,5) ?>
        </span>
      </h3>
      <span class="badge <?= $urgente ? 'warn' : 'ok' ?>">
        <?= count($grupo['itens']) ?> pendente(s)<?= $urgente ? ' · menos de 24h!' : '' ?>
      </span>
    </div>
    <ul class="pend-lista">
      <?php foreach ($grupo['itens'] as $p): ?>
      <li>
        <span class="pend-nome nivel-<?= e($p['nivel']) ?>"><?= e($p['colaborador_nome']) ?></span>
        <?php if (!empty($p['celular'])): ?>
          <span class="pend-cel"><?= e($p['celular']) ?></span>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<style>
.conf-lista{display:flex;flex-direction:column;gap:1rem}
.conf-card{background:#fff;border:1px solid var(--borda);border-radius:12px;padding:1rem 1.1rem;
  box-shadow:0 2px 8px rgba(0,0,0,.04);border-left:4px solid var(--borda)}
.conf-card.ok{border-left-color:#3c9d5c}
.conf-card.pendente{border-left-color:#e8843f;background:#fffaf5}
.conf-cab{display:flex;justify-content:space-between;align-items:flex-start;gap:.6rem;flex-wrap:wrap}
.conf-evento{font-weight:800;color:var(--laranja-6);font-size:1.05rem}
.conf-data{color:var(--texto-suave);font-size:.87rem;margin-top:.15rem}
.conf-faltam{background:var(--laranja-1);border:1px solid var(--laranja-3);border-radius:10px;
  padding:.05rem .45rem;font-size:.78rem;color:var(--laranja-6);font-weight:600;margin-left:.3rem}
.conf-faltam.hoje{background:#fbe1e1;border-color:#f3c6c6;color:#a83b3b}
.conf-selo{font-weight:700;font-size:.8rem;padding:.25rem .65rem;border-radius:20px;white-space:nowrap}
.conf-selo.ok{background:#dff3e3;color:#2f7d49}
.conf-selo.pend{background:#fff3e0;color:#9a5a12}
.conf-selo.espera{background:#f4f0ec;color:#888}
.conf-msg-ok{margin-top:.6rem;font-size:.87rem;color:#2f7d49}
.card-urgente{border-color:#f3c6c6;background:#fffafa}
.pend-lista{list-style:none;padding:0;margin:.7rem 0 0}
.pend-lista li{display:flex;align-items:center;gap:.7rem;padding:.35rem 0;font-size:.9rem;
  border-bottom:1px dashed var(--borda)}
.pend-lista li:last-child{border-bottom:none}
.pend-nome{font-weight:600;flex:1}
.pend-cel{color:var(--texto-suave);font-size:.83rem}
.nivel-lider{color:#9a4f12}.nivel-pleno{color:#1f6b86}.nivel-junior{color:#2f7d49}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
