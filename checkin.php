<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();
$pdo = db();

$u = usuario();
$ehAdm = ehAdmin();
$meuColabId = (int)($u['colaborador_id'] ?? 0);
$hoje = date('Y-m-d');

// janela de check-in: abre 30 min antes do horário de chegada
// e fecha 4 horas depois (cobre o evento e eventuais atrasos)
define('CHECKIN_ANTES_MIN', 30);
define('CHECKIN_DEPOIS_MIN', 90);

// ---------- ações ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $op = $_POST['op'] ?? '';
    $escalaId = (int)($_POST['escala_id'] ?? 0);

    // valida se o evento é de hoje
    $ev = null;
    if ($escalaId) {
        $st = $pdo->prepare("SELECT * FROM escalas WHERE id=? AND data_evento=?");
        $st->execute([$escalaId, $hoje]);
        $ev = $st->fetch();
    }

    if (!$ev) {
        flash('Evento inválido.', 'erro');
        redirect('checkin.php');
    }

    // função local: o colaborador está escalado neste evento?
    $estaEscalado = function($cid) use ($pdo, $escalaId) {
        $st = $pdo->prepare("SELECT 1 FROM escala_colaboradores WHERE escala_id=? AND colaborador_id=?");
        $st->execute([$escalaId, $cid]);
        return (bool)$st->fetchColumn();
    };
    // função local: quem é o colaborador principal (primeiro da ordem)
    $principalDe = function() use ($pdo, $escalaId) {
        $st = $pdo->prepare(
          "SELECT ec.colaborador_id
           FROM escala_colaboradores ec
           JOIN colaboradores c ON c.id = ec.colaborador_id
           WHERE ec.escala_id = ?
           ORDER BY ec.posicao IS NULL, ec.posicao,
                    c.ordem_padrao IS NULL, c.ordem_padrao,
                    FIELD(c.nivel,'lider','pleno','junior'), c.nome
           LIMIT 1"
        );
        $st->execute([$escalaId]);
        return (int)$st->fetchColumn();
    };

    // ---- confirmar chegada ----
    if ($op === 'checkin' && $meuColabId) {
        if (!$estaEscalado($meuColabId)) {
            flash('Você não está escalado neste evento.', 'erro');
            redirect('checkin.php');
        }
        // valida a janela de horário
        $inicio = strtotime($ev['data_evento'] . ' ' . $ev['horario_chegada']);
        $agora  = time();
        if ($agora < $inicio - CHECKIN_ANTES_MIN * 60) {
            flash('O check-in abre 30 minutos antes do evento.', 'erro');
        } elseif ($agora > $inicio + CHECKIN_DEPOIS_MIN * 60) {
            flash('O período de check-in deste evento já encerrou.', 'erro');
        } else {
            // INSERT IGNORE: se já fez check-in, não duplica nem sobrescreve o horário
            $pdo->prepare(
              "INSERT IGNORE INTO evento_checkins (escala_id, colaborador_id) VALUES (?, ?)"
            )->execute([$escalaId, $meuColabId]);
            flash('Chegada confirmada. Bom trabalho!');
        }
        redirect('checkin.php');
    }

    // ---- enviar foto (só o colaborador principal) ----
    if ($op === 'foto' && $meuColabId) {
        if ($principalDe() !== $meuColabId) {
            flash('Apenas o colaborador principal do evento pode enviar fotos.', 'erro');
            redirect('checkin.php');
        }
        // limite de 3 fotos por evento
        $st = $pdo->prepare("SELECT COUNT(*) FROM evento_fotos WHERE escala_id=?");
        $st->execute([$escalaId]);
        if ((int)$st->fetchColumn() >= 3) {
            flash('Este evento já tem 3 fotos. Exclua uma para enviar outra.', 'erro');
            redirect('checkin.php');
        }
        if (empty($_FILES['foto']['name']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            flash('Selecione uma foto.', 'erro');
            redirect('checkin.php');
        }
        // validação da imagem (mesmo padrão do resto do sistema)
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $ehImagem = @getimagesize($_FILES['foto']['tmp_name']) !== false;
        if (!in_array($ext, ['jpg','jpeg','png','webp']) || !$ehImagem
            || $_FILES['foto']['size'] > 6*1024*1024) {
            flash('Envie uma imagem JPG, PNG ou WEBP de até 6 MB.', 'erro');
            redirect('checkin.php');
        }
        if (!is_dir(__DIR__ . '/uploads/eventos')) {
            mkdir(__DIR__ . '/uploads/eventos', 0755, true);
        }
        $nome = 'ev' . $escalaId . '_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/uploads/eventos/' . $nome);
        $pdo->prepare(
          "INSERT INTO evento_fotos (escala_id, arquivo, enviado_por) VALUES (?, ?, ?)"
        )->execute([$escalaId, 'uploads/eventos/' . $nome, $meuColabId]);
        flash('Foto enviada.');
        redirect('checkin.php');
    }

    // ---- excluir foto (principal ou admin) ----
    if ($op === 'excluir_foto') {
        $fotoId = (int)($_POST['foto_id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM evento_fotos WHERE id=? AND escala_id=?");
        $st->execute([$fotoId, $escalaId]);
        $foto = $st->fetch();
        $podeExcluir = $foto && ($ehAdm || ($meuColabId && $principalDe() === $meuColabId));
        if ($podeExcluir) {
            @unlink(__DIR__ . '/' . $foto['arquivo']);
            $pdo->prepare("DELETE FROM evento_fotos WHERE id=?")->execute([$fotoId]);
            flash('Foto excluída.');
        } else {
            flash('Sem permissão para excluir esta foto.', 'erro');
        }
        redirect('checkin.php');
    }

    // ---- encerrar evento (só o líder/colaborador principal) ----
    if ($op === 'encerrar' && $meuColabId) {
        if ($principalDe() !== $meuColabId) {
            flash('Apenas o líder do evento (primeiro da escala) pode encerrá-lo.', 'erro');
            redirect('checkin.php');
        }
        // já encerrado? (imutável)
        $st = $pdo->prepare("SELECT 1 FROM evento_encerramentos WHERE escala_id=?");
        $st->execute([$escalaId]);
        if ($st->fetchColumn()) {
            flash('Este evento já foi encerrado e não pode ser alterado.', 'erro');
            redirect('checkin.php');
        }

        $observacao = trim($_POST['observacao'] ?? '');
        if (mb_strlen($observacao) > 1000) $observacao = mb_substr($observacao, 0, 1000);
        $marcados = array_map('intval', (array)($_POST['itens'] ?? []));

        // grava o encerramento + snapshot do checklist (imutável dali em diante)
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
              "INSERT INTO evento_encerramentos (escala_id, encerrado_por, observacao)
               VALUES (?, ?, ?)"
            )->execute([$escalaId, $meuColabId, $observacao !== '' ? $observacao : null]);
            $encId = (int)$pdo->lastInsertId();

            $itensAtivos = $pdo->query(
              "SELECT id, descricao FROM checklist_itens WHERE ativo=1 ORDER BY ordem, id"
            )->fetchAll();
            $ins = $pdo->prepare(
              "INSERT INTO evento_checklist (encerramento_id, item_descricao, marcado) VALUES (?, ?, ?)"
            );
            foreach ($itensAtivos as $it) {
                $ins->execute([$encId, $it['descricao'], in_array((int)$it['id'], $marcados) ? 1 : 0]);
            }
            $pdo->commit();
            flash('Evento encerrado com sucesso.');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('Erro ao encerrar evento: ' . $ex->getMessage());
            flash('Erro ao encerrar o evento. Tente novamente.', 'erro');
        }
        redirect('checkin.php');
    }

    redirect('checkin.php');
}

// ---------- consulta dos eventos de hoje ----------
if ($ehAdm) {
    // admin vê todos os eventos de hoje
    $st = $pdo->prepare("SELECT * FROM escalas WHERE data_evento=? ORDER BY horario_chegada");
    $st->execute([$hoje]);
} else {
    // colaborador vê só os eventos em que está escalado
    $st = $pdo->prepare(
      "SELECT e.* FROM escalas e
       JOIN escala_colaboradores ec ON ec.escala_id = e.id
       WHERE e.data_evento=? AND ec.colaborador_id=?
       ORDER BY e.horario_chegada"
    );
    $st->execute([$hoje, $meuColabId]);
}
$eventosHoje = $st->fetchAll();

// dados de apoio por evento: escalados (na ordem), check-ins e fotos
$dadosEv = [];
foreach ($eventosHoje as $ev) {
    $eid = (int)$ev['id'];

    $st = $pdo->prepare(
      "SELECT c.id, c.nome, c.nivel, ec.nivel_na_escala
       FROM escala_colaboradores ec
       JOIN colaboradores c ON c.id = ec.colaborador_id
       WHERE ec.escala_id = ?
       ORDER BY ec.posicao IS NULL, ec.posicao,
                c.ordem_padrao IS NULL, c.ordem_padrao,
                FIELD(c.nivel,'lider','pleno','junior'), c.nome"
    );
    $st->execute([$eid]);
    $escalados = $st->fetchAll();

    $st = $pdo->prepare("SELECT colaborador_id, checkin_em FROM evento_checkins WHERE escala_id=?");
    $st->execute([$eid]);
    $checks = [];
    foreach ($st->fetchAll() as $ck) $checks[(int)$ck['colaborador_id']] = $ck['checkin_em'];

    $st = $pdo->prepare("SELECT * FROM evento_fotos WHERE escala_id=? ORDER BY criado_em");
    $st->execute([$eid]);
    $fotos = $st->fetchAll();

    // encerramento (se houver) + checklist preenchido
    $st = $pdo->prepare(
      "SELECT en.*, c.nome AS encerrado_por_nome
       FROM evento_encerramentos en
       LEFT JOIN colaboradores c ON c.id = en.encerrado_por
       WHERE en.escala_id = ?"
    );
    $st->execute([$eid]);
    $encerramento = $st->fetch();
    $checklistFeito = [];
    if ($encerramento) {
        $st = $pdo->prepare("SELECT * FROM evento_checklist WHERE encerramento_id=? ORDER BY id");
        $st->execute([(int)$encerramento['id']]);
        $checklistFeito = $st->fetchAll();
    }

    $dadosEv[$eid] = [
        'escalados' => $escalados,
        'checks'    => $checks,
        'fotos'     => $fotos,
        'principal' => $escalados ? (int)$escalados[0]['id'] : 0,
        'encerramento'  => $encerramento,
        'checklistFeito'=> $checklistFeito,
    ];
}

// itens ativos do checklist (para o formulário de encerramento)
$itensChecklist = $pdo->query(
  "SELECT id, descricao FROM checklist_itens WHERE ativo=1 ORDER BY ordem, id"
)->fetchAll();

$titulo = 'Check-in';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Check-in de chegada</h1>
<p class="page-sub">Confirme sua chegada no local a partir de 30 minutos antes do evento.</p>

<?php if (!$eventosHoje): ?>
  <div class="card"><p class="muted">
    <?= $ehAdm ? 'Nenhum evento hoje.' : 'Você não está escalado em nenhum evento hoje.' ?>
  </p></div>
<?php endif; ?>

<?php foreach ($eventosHoje as $ev):
  $eid = (int)$ev['id'];
  $d = $dadosEv[$eid];
  $inicio = strtotime($ev['data_evento'] . ' ' . $ev['horario_chegada']);
  $agora = time();
  $abre  = $inicio - CHECKIN_ANTES_MIN * 60;
  $fecha = $inicio + CHECKIN_DEPOIS_MIN * 60;
  $janela = ($agora >= $abre && $agora <= $fecha) ? 'aberta' : ($agora < $abre ? 'aguardando' : 'encerrada');
  $meuCheck = $meuColabId && isset($d['checks'][$meuColabId]) ? $d['checks'][$meuColabId] : null;
  $souPrincipal = $meuColabId && $d['principal'] === $meuColabId;
  $souEscalado = false;
  foreach ($d['escalados'] as $p) if ((int)$p['id'] === $meuColabId) { $souEscalado = true; break; }
?>
<div class="card" style="margin-bottom:1.2rem">
  <div class="flex-between" style="flex-wrap:wrap;gap:.5rem">
    <h2 style="margin:0"><?= e($ev['evento']) ?> <span class="muted" style="font-weight:400;font-size:.9rem">⏰ <?= substr($ev['horario_chegada'],0,5) ?></span></h2>
    <?php if ($janela === 'aberta'): ?>
      <span class="ck-badge ck-aberta">Check-in aberto</span>
    <?php elseif ($janela === 'aguardando'): ?>
      <span class="ck-badge ck-aguarda">Abre às <?= date('H:i', $abre) ?></span>
    <?php else: ?>
      <span class="ck-badge ck-fim">Encerrado</span>
    <?php endif; ?>
  </div>

  <?php if ($souEscalado): ?>
    <?php if ($meuCheck): ?>
      <div class="flash sucesso" style="margin:.8rem 0 0">✅ Chegada confirmada às <b><?= date('H:i', strtotime($meuCheck)) ?></b></div>
    <?php elseif ($janela === 'aberta'): ?>
      <form method="post" style="margin:.8rem 0 0">
        <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
        <input type="hidden" name="op" value="checkin">
        <input type="hidden" name="escala_id" value="<?= $eid ?>">
        <button class="btn btn-checkin">📍 Confirmar minha chegada</button>
      </form>
    <?php elseif ($janela === 'aguardando'): ?>
      <p class="muted" style="margin:.8rem 0 0">O botão de check-in aparece a partir das <?= date('H:i', $abre) ?>.</p>
    <?php else: ?>
      <p class="muted" style="margin:.8rem 0 0">Você não registrou check-in neste evento.</p>
    <?php endif; ?>
  <?php endif; ?>

  <h3 class="ck-sub">Equipe</h3>
  <ul class="ck-lista">
    <?php foreach ($d['escalados'] as $i => $p):
      $ck = $d['checks'][(int)$p['id']] ?? null; ?>
    <li>
      <?php if ($ck): ?>
        <span class="ck-status ok">✓ <?= date('H:i', strtotime($ck)) ?></span>
      <?php else: ?>
        <span class="ck-status pende">—</span>
      <?php endif; ?>
      <span class="ck-nome nivel-<?= e($p['nivel_na_escala'] ?: $p['nivel']) ?>">
        <?= e($p['nome']) ?><?= $i === 0 ? ' <span class="ck-principal">principal</span>' : '' ?>
      </span>
    </li>
    <?php endforeach; ?>
  </ul>

  <h3 class="ck-sub">Fotos do evento <span class="muted" style="font-weight:400">(<?= count($d['fotos']) ?>/3)</span></h3>
  <?php if ($d['fotos']): ?>
  <div class="ck-fotos">
    <?php foreach ($d['fotos'] as $f): ?>
      <div class="ck-foto">
        <a href="<?= e($f['arquivo']) ?>" target="_blank"><img src="<?= e($f['arquivo']) ?>" alt="Foto do evento"></a>
        <?php if ($ehAdm || $souPrincipal): ?>
        <form method="post" onsubmit="return confirm('Excluir esta foto?')">
          <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
          <input type="hidden" name="op" value="excluir_foto">
          <input type="hidden" name="escala_id" value="<?= $eid ?>">
          <input type="hidden" name="foto_id" value="<?= (int)$f['id'] ?>">
          <button class="ck-foto-x" title="Excluir">✕</button>
        </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <p class="muted" style="margin:.3rem 0">Nenhuma foto ainda.</p>
  <?php endif; ?>

  <?php if ($souPrincipal && count($d['fotos']) < 3): ?>
    <form method="post" enctype="multipart/form-data" style="margin-top:.6rem">
      <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
      <input type="hidden" name="op" value="foto">
      <input type="hidden" name="escala_id" value="<?= $eid ?>">
      <input type="file" name="foto" id="foto-<?= $eid ?>" accept="image/*" capture="environment"
             style="display:none" onchange="this.form.submit()">
      <button type="button" class="btn" onclick="document.getElementById('foto-<?= $eid ?>').click()">
        📷 Tirar foto do evento
      </button>
      <p class="muted" style="margin-top:.4rem;font-size:.82rem">Como colaborador principal, você pode enviar até 3 fotos.</p>
    </form>
  <?php endif; ?>

  <?php $enc = $d['encerramento']; ?>
  <?php if ($enc): ?>
    <?php /* ---- evento já encerrado: exibição imutável ---- */ ?>
    <h3 class="ck-sub">Encerramento</h3>
    <div class="enc-selo">
      🔒 Evento encerrado por <b><?= e($enc['encerrado_por_nome'] ?: '—') ?></b>
      às <b><?= date('H:i', strtotime($enc['encerrado_em'])) ?></b> de <?= date('d/m/Y', strtotime($enc['encerrado_em'])) ?>
    </div>
    <ul class="enc-lista">
      <?php foreach ($d['checklistFeito'] as $ci): ?>
      <li>
        <span class="enc-ck <?= $ci['marcado'] ? 'sim' : 'nao' ?>"><?= $ci['marcado'] ? '✓' : '✗' ?></span>
        <?= e($ci['item_descricao']) ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($enc['observacao']): ?>
      <div class="enc-obs"><b>Observação:</b> <?= nl2br(e($enc['observacao'])) ?></div>
    <?php endif; ?>

  <?php elseif ($souPrincipal): ?>
    <?php /* ---- líder: formulário de encerramento ---- */ ?>
    <h3 class="ck-sub">Encerrar evento</h3>
    <form method="post"
          onsubmit="return confirm('Encerrar o evento agora?\n\nApós o encerramento, o checklist e a observação NÃO poderão mais ser alterados ou excluídos.')">
      <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
      <input type="hidden" name="op" value="encerrar">
      <input type="hidden" name="escala_id" value="<?= $eid ?>">
      <?php if ($itensChecklist): ?>
      <div class="enc-form-itens">
        <?php foreach ($itensChecklist as $it): ?>
        <label class="enc-item">
          <input type="checkbox" name="itens[]" value="<?= (int)$it['id'] ?>">
          <span><?= e($it['descricao']) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <p class="muted">Nenhum item de checklist cadastrado.</p>
      <?php endif; ?>
      <label style="margin-top:.6rem">Observação (opcional)</label>
      <textarea name="observacao" rows="3" maxlength="1000"
                placeholder="Ex.: Faltou 1 cone, rádio 2 com defeito…" style="width:100%"></textarea>
      <button class="btn" style="margin-top:.8rem">🔒 Encerrar evento</button>
      <p class="muted" style="margin-top:.4rem;font-size:.82rem">
        Após encerrado, os dados não poderão mais ser alterados.
      </p>
    </form>
  <?php elseif ($souEscalado): ?>
    <h3 class="ck-sub">Encerramento</h3>
    <p class="muted" style="margin:.3rem 0">O evento ainda não foi encerrado. O encerramento é feito pelo líder da escala.</p>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<style>
.ck-badge{font-weight:700;font-size:.82rem;padding:.25rem .7rem;border-radius:20px}
.ck-aberta{background:#dff3e3;color:#2f7d49}
.ck-aguarda{background:#fff3e0;color:#9a5a12}
.ck-fim{background:#eee;color:#666}
.btn-checkin{font-size:1.05rem;padding:.8rem 1.6rem}
.ck-sub{color:var(--laranja-6);font-size:1rem;margin:1.1rem 0 .4rem}
.ck-lista{list-style:none;padding:0;margin:0}
.ck-lista li{display:flex;align-items:center;gap:.6rem;padding:.3rem 0;font-size:.92rem;
  border-bottom:1px dashed var(--borda)}
.ck-lista li:last-child{border-bottom:none}
.ck-status{font-weight:700;font-size:.78rem;min-width:64px;text-align:center;
  padding:.12rem .4rem;border-radius:12px;flex:0 0 auto}
.ck-status.ok{background:#dff3e3;color:#2f7d49}
.ck-status.pende{background:#f4f0ec;color:#999}
.ck-nome{font-weight:600}
.ck-principal{background:var(--laranja-2);color:var(--laranja-6);font-size:.68rem;
  font-weight:700;padding:.08rem .45rem;border-radius:10px;vertical-align:middle}
.nivel-lider{color:#9a4f12}.nivel-pleno{color:#1f6b86}.nivel-junior{color:#2f7d49}
.ck-fotos{display:flex;gap:.7rem;flex-wrap:wrap}
.ck-foto{position:relative;width:110px;height:110px;border-radius:10px;overflow:hidden;
  border:1px solid var(--borda)}
.ck-foto img{width:100%;height:100%;object-fit:cover;display:block}
.ck-foto-x{position:absolute;top:4px;right:4px;background:rgba(0,0,0,.55);color:#fff;
  border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:.8rem;line-height:1}

/* encerramento do evento */
.enc-selo{background:#f4f0ec;border:1px solid var(--borda);border-radius:10px;
  padding:.6rem .9rem;font-size:.9rem;color:#555}
.enc-lista{list-style:none;padding:0;margin:.6rem 0 0}
.enc-lista li{display:flex;align-items:center;gap:.6rem;padding:.28rem 0;font-size:.9rem;
  border-bottom:1px dashed var(--borda)}
.enc-lista li:last-child{border-bottom:none}
.enc-ck{font-weight:700;width:24px;height:24px;border-radius:50%;flex:0 0 auto;
  display:flex;align-items:center;justify-content:center;font-size:.8rem}
.enc-ck.sim{background:#dff3e3;color:#2f7d49}
.enc-ck.nao{background:#fbe1e1;color:#a83b3b}
.enc-obs{margin-top:.7rem;background:var(--laranja-1);border:1px solid var(--laranja-3);
  border-radius:10px;padding:.6rem .9rem;font-size:.9rem;color:#555}
.enc-form-itens{display:flex;flex-direction:column;gap:.45rem;margin:.3rem 0 .6rem}
.enc-item{display:flex;align-items:flex-start;gap:.6rem;cursor:pointer;font-weight:500;
  line-height:1.3;padding:.5rem .7rem;background:var(--laranja-1);
  border:1px solid var(--laranja-3);border-radius:10px}
.enc-item input{width:19px;height:19px;flex:0 0 auto;margin-top:.05rem}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
