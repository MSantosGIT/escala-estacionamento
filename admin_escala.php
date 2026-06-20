<?php
require_once __DIR__ . '/includes/escala_engine.php';
require_once __DIR__ . '/includes/trocas.php';
exigirAdmin();
$pdo = db();

$mes = (int)($_GET['mes'] ?? date('n'));
$ano = (int)($_GET['ano'] ?? date('Y'));

// helpers
function colabNivel(PDO $pdo, int $id): string {
    $s = $pdo->prepare("SELECT nivel FROM colaboradores WHERE id=?");
    $s->execute([$id]); return (string)($s->fetchColumn() ?: 'junior');
}
function colabNome(PDO $pdo, int $id): string {
    $s = $pdo->prepare("SELECT nome FROM colaboradores WHERE id=?");
    $s->execute([$id]); return (string)($s->fetchColumn() ?: '—');
}
function estaIndisponivel(PDO $pdo, int $colabId, int $escalaId): bool {
    $s = $pdo->prepare("SELECT 1 FROM indisponibilidades WHERE colaborador_id=? AND escala_id=?");
    $s->execute([$colabId, $escalaId]); return (bool)$s->fetchColumn();
}
function nomeEvento(PDO $pdo, int $escalaId): string {
    $s = $pdo->prepare("SELECT CONCAT(evento,' (',DATE_FORMAT(data_evento,'%d/%m/%Y'),')') FROM escalas WHERE id=?");
    $s->execute([$escalaId]); return (string)($s->fetchColumn() ?: 'evento');
}

// ------------------------------------------------------------
//  AÇÕES
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $op = $_POST['op'] ?? '';
    $escalaId = (int)($_POST['escala_id'] ?? 0);
    $mes = (int)$_POST['mes']; $ano = (int)$_POST['ano'];

    // remover colaborador do evento
    if ($op === 'remover') {
        $colabId = (int)$_POST['colaborador_id'];
        $pdo->prepare("DELETE FROM escala_colaboradores WHERE escala_id=? AND colaborador_id=?")
            ->execute([$escalaId, $colabId]);
        notificarColaborador($pdo, $colabId, "Você foi removido da escala de " . nomeEvento($pdo,$escalaId) . " pelo administrador.");
        flash('Colaborador removido do evento.');
        redirect("admin_escala.php?mes=$mes&ano=$ano");
    }

    // adicionar colaborador ao evento
    if ($op === 'adicionar') {
        $novoId = (int)$_POST['novo_id'];
        if ($novoId) {
            $nivel = colabNivel($pdo, $novoId);
            $pdo->prepare(
                "INSERT INTO escala_colaboradores (escala_id, colaborador_id, nivel_na_escala)
                 VALUES (?,?,?) ON DUPLICATE KEY UPDATE nivel_na_escala=VALUES(nivel_na_escala)"
            )->execute([$escalaId, $novoId, $nivel]);
            notificarColaborador($pdo, $novoId, "Você foi escalado para " . nomeEvento($pdo,$escalaId) . " pelo administrador.");
            flash('Colaborador adicionado ao evento.');
        }
        redirect("admin_escala.php?mes=$mes&ano=$ano");
    }

    // substituir: sai 'colaborador_id', entra 'novo_id'
    if ($op === 'substituir') {
        $saiId   = (int)$_POST['colaborador_id'];
        $entraId = (int)$_POST['novo_id'];
        if ($saiId && $entraId && $saiId !== $entraId) {
            $nivel = colabNivel($pdo, $entraId);
            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM escala_colaboradores WHERE escala_id=? AND colaborador_id=?")
                    ->execute([$escalaId, $saiId]);
                $pdo->prepare(
                    "INSERT INTO escala_colaboradores (escala_id, colaborador_id, nivel_na_escala)
                     VALUES (?,?,?) ON DUPLICATE KEY UPDATE nivel_na_escala=VALUES(nivel_na_escala)"
                )->execute([$escalaId, $entraId, $nivel]);
                $pdo->commit();
                $ev = nomeEvento($pdo,$escalaId);
                notificarColaborador($pdo, $saiId,   "Você saiu da escala de $ev (troca feita pelo administrador).");
                notificarColaborador($pdo, $entraId, "Você foi escalado para $ev (troca feita pelo administrador).");
                flash('Troca realizada e colaboradores avisados.');
            } catch (Throwable $ex) {
                $pdo->rollBack();
                flash('Erro ao substituir.', 'erro');
            }
        }
        redirect("admin_escala.php?mes=$mes&ano=$ano");
    }
}

// ------------------------------------------------------------
//  DADOS
// ------------------------------------------------------------
$st = $pdo->prepare("SELECT * FROM escalas WHERE mes=? AND ano=? ORDER BY data_evento, horario_chegada");
$st->execute([$mes, $ano]);
$eventos = $st->fetchAll();

// escalados por evento
$escalados = [];
if ($eventos) {
    $ids = implode(',', array_column($eventos,'id'));
    $q = $pdo->query(
      "SELECT ec.escala_id, ec.colaborador_id, c.nome, ec.nivel_na_escala
       FROM escala_colaboradores ec JOIN colaboradores c ON c.id=ec.colaborador_id
       WHERE ec.escala_id IN ($ids)
       ORDER BY FIELD(ec.nivel_na_escala,'lider','pleno','junior'), c.nome"
    );
    foreach ($q as $r) $escalados[$r['escala_id']][] = $r;
}

// todos os colaboradores ativos (para o select de adicionar/substituir)
$todos = $pdo->query("SELECT id, nome, nivel FROM colaboradores WHERE ativo=1 ORDER BY FIELD(nivel,'lider','pleno','junior'), nome")->fetchAll();

// indisponibilidades do mês (para sinalizar)
$indisponMes = [];
if ($eventos) {
    $q = $pdo->query(
      "SELECT i.escala_id, i.colaborador_id FROM indisponibilidades i
       JOIN escalas e ON e.id=i.escala_id WHERE e.mes=$mes AND e.ano=$ano"
    );
    foreach ($q as $r) $indisponMes[$r['escala_id']][] = (int)$r['colaborador_id'];
}

$meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
          7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];

// dados p/ JS validar regra de nível e indisponibilidade no cliente
$colabInfo = [];
foreach ($todos as $c) $colabInfo[(int)$c['id']] = ['nome'=>$c['nome'], 'nivel'=>$c['nivel']];

$titulo = 'Ajustar Escala (Admin)';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Ajustar escala</h1>
<p class="page-sub">Revise os escalados e faça trocas, inclusões ou remoções em cada evento.</p>

<div class="card">
  <form method="get" style="display:flex;gap:.6rem;align-items:flex-end">
    <div><label>Mês</label>
      <select name="mes" onchange="this.form.submit()">
        <?php foreach ($meses as $k=>$v): ?><option value="<?= $k ?>" <?= $k===$mes?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
      </select>
    </div>
    <div><label>Ano</label><input type="number" name="ano" value="<?= $ano ?>" style="max-width:110px" onchange="this.form.submit()"></div>
  </form>
</div>

<?php if (!$eventos): ?>
  <div class="card"><p class="muted">Nenhum evento neste mês.</p></div>
<?php else: ?>
  <?php foreach ($eventos as $ev):
    $eid = (int)$ev['id'];
    $time = strtotime($ev['data_evento']);
    $equipe = $escalados[$eid] ?? [];
    $temComp = ((int)$ev['num_colaboradores']) >= 3;
    // contagem por nível
    $cont = ['lider'=>0,'pleno'=>0,'junior'=>0];
    foreach ($equipe as $p) $cont[$p['nivel_na_escala']]++;
  ?>
  <div class="card">
    <div class="flex-between">
      <h2><?= e($ev['evento']) ?> · <?= date('d/m/Y', $time) ?> <span class="muted" style="font-weight:400">(<?= ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][date('w',$time)] ?> · <?= substr($ev['horario_chegada'],0,5) ?>)</span></h2>
      <span class="badge <?= $ev['status']==='preenchida'?'ok':'warn' ?>"><?= ucfirst($ev['status']) ?> · <?= count($equipe) ?>/<?= $ev['num_colaboradores'] ?></span>
    </div>

    <?php if ($temComp): ?>
      <p class="muted" style="margin-bottom:.6rem">Composição atual: A1 <?= $cont['lider'] ?> · A2 <?= $cont['pleno'] ?> · A3 <?= $cont['junior'] ?>
      <?php if ($cont['lider']<1 || $cont['pleno']<1 || $cont['junior']<1): ?><span style="color:var(--vermelho)"> — composição incompleta</span><?php endif; ?></p>
    <?php endif; ?>

    <table>
      <thead><tr><th>Colaborador</th><th>Nível</th><th class="right">Ações</th></tr></thead>
      <tbody>
      <?php foreach ($equipe as $p):
        $indispEvt = in_array((int)$p['colaborador_id'], $indisponMes[$eid] ?? [], true); ?>
        <tr>
          <td><?= e($p['nome']) ?> <?php if($indispEvt):?><span class="badge warn">marcou indisponível</span><?php endif;?></td>
          <td><span class="badge <?= $p['nivel_na_escala'] ?>"><?= nivelLabel($p['nivel_na_escala']) ?></span></td>
          <td class="right" style="white-space:nowrap">
            <!-- substituir -->
            <form method="post" style="display:inline-flex;gap:.3rem" onsubmit="return validarTroca(this, <?= $eid ?>, <?= (int)$p['colaborador_id'] ?>)">
              <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
              <input type="hidden" name="op" value="substituir">
              <input type="hidden" name="escala_id" value="<?= $eid ?>">
              <input type="hidden" name="mes" value="<?= $mes ?>"><input type="hidden" name="ano" value="<?= $ano ?>">
              <input type="hidden" name="colaborador_id" value="<?= $p['colaborador_id'] ?>">
              <select name="novo_id" required style="max-width:160px">
                <option value="">Trocar por…</option>
                <?php foreach ($todos as $c): if ($c['id']==$p['colaborador_id']) continue; ?>
                  <option value="<?= $c['id'] ?>"><?= e($c['nome']) ?> · <?= e(nivelLabel($c['nivel'])) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn sm">Trocar</button>
            </form>
            <!-- remover -->
            <form method="post" style="display:inline" onsubmit="return confirm('Remover <?= e(addslashes($p['nome'])) ?> deste evento?')">
              <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
              <input type="hidden" name="op" value="remover">
              <input type="hidden" name="escala_id" value="<?= $eid ?>">
              <input type="hidden" name="mes" value="<?= $mes ?>"><input type="hidden" name="ano" value="<?= $ano ?>">
              <input type="hidden" name="colaborador_id" value="<?= $p['colaborador_id'] ?>">
              <button class="btn sm danger">×</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$equipe): ?><tr><td colspan="3" class="muted">Ninguém escalado.</td></tr><?php endif; ?>
      </tbody>
    </table>

    <!-- adicionar -->
    <form method="post" style="display:flex;gap:.4rem;margin-top:.8rem;flex-wrap:wrap" onsubmit="return validarAdd(this, <?= $eid ?>)">
      <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
      <input type="hidden" name="op" value="adicionar">
      <input type="hidden" name="escala_id" value="<?= $eid ?>">
      <input type="hidden" name="mes" value="<?= $mes ?>"><input type="hidden" name="ano" value="<?= $ano ?>">
      <select name="novo_id" required style="max-width:220px">
        <option value="">Adicionar colaborador…</option>
        <?php foreach ($todos as $c):
          $jaTem = false; foreach ($equipe as $p) if ($p['colaborador_id']==$c['id']) $jaTem=true;
          if ($jaTem) continue; ?>
          <option value="<?= $c['id'] ?>"><?= e($c['nome']) ?> · <?= e(nivelLabel($c['nivel'])) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn sm sec">+ Adicionar</button>
    </form>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<script>
const COLAB = <?= json_encode($colabInfo, JSON_UNESCAPED_UNICODE) ?>;
const INDISP = <?= json_encode($indisponMes, JSON_UNESCAPED_UNICODE) ?>;
const LABEL = {lider:'A1', pleno:'A2', junior:'A3'};

// regra: A1 só compatível com A1; A2 e A3 entre si
function compativel(nivelSai, nivelEntra){
  if (nivelSai === 'lider') return nivelEntra === 'lider';
  return nivelEntra === 'pleno' || nivelEntra === 'junior';
}
function indisponivel(escalaId, colabId){
  return (INDISP[escalaId]||[]).includes(parseInt(colabId));
}
function validarTroca(form, escalaId, saiId){
  const entra = form.novo_id.value;
  if (!entra) return false;
  const ce = COLAB[entra], cs = COLAB[saiId];
  let avisos = [];
  if (cs && ce && !compativel(cs.nivel, ce.nivel))
    avisos.push(`Níveis diferentes: ${cs.nome} é ${LABEL[cs.nivel]} e ${ce.nome} é ${LABEL[ce.nivel]}.`);
  if (indisponivel(escalaId, entra))
    avisos.push(`${ce.nome} marcou indisponibilidade neste evento.`);
  if (avisos.length)
    return confirm('Atenção:\n- ' + avisos.join('\n- ') + '\n\nDeseja confirmar mesmo assim?');
  return true;
}
function validarAdd(form, escalaId){
  const entra = form.novo_id.value;
  if (!entra) return false;
  const ce = COLAB[entra];
  if (indisponivel(escalaId, entra))
    return confirm(`Atenção: ${ce.nome} marcou indisponibilidade neste evento.\n\nAdicionar mesmo assim?`);
  return true;
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
