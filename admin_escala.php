<?php
require_once __DIR__ . '/includes/escala_engine.php';
require_once __DIR__ . '/includes/trocas.php';
exigirAdmin();
$pdo = db();

$mes = (int)($_GET['mes'] ?? date('n'));
$ano = (int)($_GET['ano'] ?? date('Y'));

function colabNivel(PDO $pdo, int $id): string {
    $s = $pdo->prepare("SELECT nivel FROM colaboradores WHERE id=?");
    $s->execute([$id]); return (string)($s->fetchColumn() ?: 'junior');
}
function nomeEvento(PDO $pdo, int $escalaId): string {
    $s = $pdo->prepare("SELECT CONCAT(evento,' (',DATE_FORMAT(data_evento,'%d/%m/%Y'),')') FROM escalas WHERE id=?");
    $s->execute([$escalaId]); return (string)($s->fetchColumn() ?: 'evento');
}

// ------------------------------------------------------------
//  SALVAR EM LOTE — recebe JSON com a lista de operações
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'salvar_ordem') {
    validarCSRF();
    $mes = (int)$_POST['mes']; $ano = (int)$_POST['ano'];
    $escalaId = (int)$_POST['escala_id'];
    $aplicarTodos = ($_POST['escopo'] ?? 'evento') === 'todos';
    $ordem = json_decode($_POST['ordem'] ?? '[]', true);
    if (!is_array($ordem)) $ordem = [];

    $pdo->beginTransaction();
    try {
        if ($aplicarTodos) {
            // grava ordem global no colaborador (vale para todos os eventos futuros)
            $pos = 1;
            foreach ($ordem as $cid) {
                $cid = (int)$cid;
                if ($cid) {
                    $pdo->prepare("UPDATE colaboradores SET ordem_padrao=? WHERE id=?")
                        ->execute([$pos, $cid]);
                    $pos++;
                }
            }
            // limpa posições específicas do evento (para usar a ordem global)
            $pdo->prepare("UPDATE escala_colaboradores SET posicao=NULL WHERE escala_id=?")->execute([$escalaId]);
            flash('Ordem aplicada como padrão para todos os eventos.');
        } else {
            // ordem específica deste evento
            $pos = 1;
            foreach ($ordem as $cid) {
                $cid = (int)$cid;
                if ($cid) {
                    $pdo->prepare("UPDATE escala_colaboradores SET posicao=? WHERE escala_id=? AND colaborador_id=?")
                        ->execute([$pos, $escalaId, $cid]);
                    $pos++;
                }
            }
            flash('Ordem salva para este evento.');
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        flash('Erro ao salvar a ordem: ' . $ex->getMessage(), 'erro');
    }
    redirect("admin_escala.php?mes=$mes&ano=$ano");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'salvar_lote') {
    validarCSRF();
    $mes = (int)$_POST['mes']; $ano = (int)$_POST['ano'];
    $ops = json_decode($_POST['ops'] ?? '[]', true);
    if (!is_array($ops)) $ops = [];

    $aplicadas = 0; $erros = [];
    $pdo->beginTransaction();
    try {
        foreach ($ops as $o) {
            $tipo = $o['tipo'] ?? '';
            $eid  = (int)($o['escala_id'] ?? 0);
            if (!$eid) continue;

            if ($tipo === 'remover') {
                $cid = (int)($o['colaborador_id'] ?? 0);
                if (!$cid) continue;
                $pdo->prepare("DELETE FROM escala_colaboradores WHERE escala_id=? AND colaborador_id=?")
                    ->execute([$eid, $cid]);
                notificarColaborador($pdo, $cid, "Você foi removido da escala de " . nomeEvento($pdo,$eid) . " pelo administrador.");
                $aplicadas++;
            }
            elseif ($tipo === 'adicionar') {
                $cid = (int)($o['colaborador_id'] ?? 0);
                if (!$cid) continue;
                $nivel = colabNivel($pdo, $cid);
                $pdo->prepare(
                    "INSERT INTO escala_colaboradores (escala_id, colaborador_id, nivel_na_escala)
                     VALUES (?,?,?) ON DUPLICATE KEY UPDATE nivel_na_escala=VALUES(nivel_na_escala)"
                )->execute([$eid, $cid, $nivel]);
                notificarColaborador($pdo, $cid, "Você foi escalado para " . nomeEvento($pdo,$eid) . " pelo administrador.");
                $aplicadas++;
            }
            elseif ($tipo === 'substituir') {
                $sai   = (int)($o['sai_id'] ?? 0);
                $entra = (int)($o['entra_id'] ?? 0);
                if (!$sai || !$entra || $sai === $entra) continue;
                $nivel = colabNivel($pdo, $entra);
                $pdo->prepare("DELETE FROM escala_colaboradores WHERE escala_id=? AND colaborador_id=?")
                    ->execute([$eid, $sai]);
                $pdo->prepare(
                    "INSERT INTO escala_colaboradores (escala_id, colaborador_id, nivel_na_escala)
                     VALUES (?,?,?) ON DUPLICATE KEY UPDATE nivel_na_escala=VALUES(nivel_na_escala)"
                )->execute([$eid, $entra, $nivel]);
                $ev = nomeEvento($pdo,$eid);
                notificarColaborador($pdo, $sai,   "Você saiu da escala de $ev (troca feita pelo administrador).");
                notificarColaborador($pdo, $entra, "Você foi escalado para $ev (troca feita pelo administrador).");
                $aplicadas++;
            }
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        $erros[] = $ex->getMessage();
    }

    if ($erros) flash('Erro ao salvar: ' . implode('; ', $erros), 'erro');
    else {
        flash($aplicadas . ' alteração(ões) aplicada(s) e colaboradores notificados.');
        // notifica os admins sobre as alterações feitas (aparece no painel inicial)
        if ($aplicadas > 0) {
            $u = usuario();
            $nomeAdmin = $u['nome'] ?? 'Administrador';
            notificarAdmin($pdo, "{$aplicadas} alteração(ões) na escala feitas por {$nomeAdmin}.");
        }
    }
    redirect("admin_escala.php?mes=$mes&ano=$ano");
}

// ------------------------------------------------------------
//  DADOS
// ------------------------------------------------------------
$st = $pdo->prepare("SELECT * FROM escalas WHERE mes=? AND ano=? ORDER BY data_evento, horario_chegada");
$st->execute([$mes, $ano]);
$eventos = $st->fetchAll();

$escalados = [];
if ($eventos) {
    $ids = implode(',', array_column($eventos,'id'));
    $q = $pdo->query(
      "SELECT ec.escala_id, ec.colaborador_id, c.nome, ec.nivel_na_escala, ec.posicao
       FROM escala_colaboradores ec JOIN colaboradores c ON c.id=ec.colaborador_id
       WHERE ec.escala_id IN ($ids)
       ORDER BY
         ec.posicao IS NULL,                          -- posição específica primeiro
         ec.posicao,
         c.ordem_padrao IS NULL,                      -- depois ordem global do colaborador
         c.ordem_padrao,
         FIELD(ec.nivel_na_escala,'lider','pleno','junior'),
         c.nome"
    );
    foreach ($q as $r) $escalados[$r['escala_id']][] = $r;
}

$todos = $pdo->query(
  "SELECT id, nome, nivel FROM colaboradores WHERE ativo=1 ORDER BY FIELD(nivel,'lider','pleno','junior'), nome"
)->fetchAll();

$indisponMes = [];
if ($eventos) {
    $q = $pdo->query(
      "SELECT i.escala_id, i.colaborador_id FROM indisponibilidades i
       JOIN escalas e ON e.id=i.escala_id WHERE e.mes=$mes AND e.ano=$ano"
    );
    foreach ($q as $r) $indisponMes[(int)$r['escala_id']][] = (int)$r['colaborador_id'];
}

$meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
          7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];

// estrutura para o JS
$colabInfo = [];
foreach ($todos as $c) $colabInfo[(int)$c['id']] = ['nome'=>$c['nome'], 'nivel'=>$c['nivel']];

$titulo = 'Ajustar Escala (Admin)';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Ajustar escala</h1>
<p class="page-sub">Marque várias alterações e clique em <b>Salvar todos</b>. A tela não recarrega a cada ação.</p>

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

  <!-- BARRA DE AÇÃO (topo) -->
  <div id="barraTopo" class="barra-acao" style="display:none">
    <span><b id="qtdTopo">0</b> alteração(ões) pendentes</span>
    <span>
      <button type="button" class="btn sm sec" onclick="descartarTudo()">Descartar</button>
      <button type="button" class="btn sm" onclick="salvarTudo()">💾 Salvar todos</button>
    </span>
  </div>

  <?php foreach ($eventos as $ev):
    $eid = (int)$ev['id'];
    $time = strtotime($ev['data_evento']);
    $equipe = $escalados[$eid] ?? [];
  ?>
  <div class="card evento" data-eid="<?= $eid ?>">
    <div class="flex-between">
      <h2><?= e($ev['evento']) ?> · <?= date('d/m/Y', $time) ?> <span class="muted" style="font-weight:400">(<?= ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][date('w',$time)] ?> · <?= substr($ev['horario_chegada'],0,5) ?>)</span></h2>
    </div>

    <table class="tab-equipe">
      <thead><tr><th></th><th>Colaborador</th><th>Nível</th><th class="right">Ações</th></tr></thead>
      <tbody class="ordenavel" data-eid="<?= $eid ?>">
      <?php foreach ($equipe as $p):
        $cid = (int)$p['colaborador_id'];
        $indispEvt = in_array($cid, $indisponMes[$eid] ?? [], true); ?>
        <tr class="linha-escalado" data-cid="<?= $cid ?>" data-nivel="<?= $p['nivel_na_escala'] ?>">
          <td class="alca" title="Arrastar para reordenar">⋮⋮</td>
          <td>
            <span class="nome-original"><?= e($p['nome']) ?></span>
            <span class="nome-pendente" style="display:none"></span>
            <?php if($indispEvt):?><span class="badge warn">marcou indisponível</span><?php endif;?>
            <span class="tag-status"></span>
          </td>
          <td><span class="badge <?= $p['nivel_na_escala'] ?>"><?= nivelLabel($p['nivel_na_escala']) ?></span></td>
          <td class="right" style="white-space:nowrap">
            <select class="sel-trocar" style="max-width:170px">
              <option value="">Trocar por…</option>
              <?php foreach ($todos as $c): if ($c['id']==$cid) continue; ?>
                <option value="<?= $c['id'] ?>"><?= e($c['nome']) ?> · <?= e(nivelLabel($c['nivel'])) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn sm danger" onclick="marcarRemover(this)" title="Remover">×</button>
            <button type="button" class="btn sm sec btn-desfazer" style="display:none" onclick="desfazerLinha(this)">↶ desfazer</button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$equipe): ?><tr><td colspan="4" class="muted">Ninguém escalado.</td></tr><?php endif; ?>
      </tbody>
    </table>

    <?php if ($equipe): ?>
    <div class="barra-ordem" id="barra-ordem-<?= $eid ?>" style="display:none">
      <span>Ordem alterada — aplicar:</span>
      <button type="button" class="btn sm" onclick="salvarOrdem(<?= $eid ?>, 'evento')">Só neste evento</button>
      <button type="button" class="btn sm sec" onclick="salvarOrdem(<?= $eid ?>, 'todos')">Como padrão pra todos</button>
      <button type="button" class="btn sm sec" onclick="cancelarOrdem(<?= $eid ?>)">↶ Desfazer</button>
    </div>
    <?php endif; ?>

    <form id="form-ordem-<?= $eid ?>" method="post" style="display:none">
      <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
      <input type="hidden" name="op" value="salvar_ordem">
      <input type="hidden" name="escala_id" value="<?= $eid ?>">
      <input type="hidden" name="mes" value="<?= $mes ?>">
      <input type="hidden" name="ano" value="<?= $ano ?>">
      <input type="hidden" name="escopo" value="evento">
      <input type="hidden" name="ordem" value="[]">
    </form>

    <div class="add-area" style="display:flex;gap:.4rem;margin-top:.8rem;flex-wrap:wrap;align-items:center">
      <select class="sel-adicionar" style="max-width:220px">
        <option value="">Adicionar colaborador…</option>
        <?php foreach ($todos as $c):
          $jaTem = false; foreach ($equipe as $p) if ($p['colaborador_id']==$c['id']) $jaTem=true;
          if ($jaTem) continue; ?>
          <option value="<?= $c['id'] ?>"><?= e($c['nome']) ?> · <?= e(nivelLabel($c['nivel'])) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="button" class="btn sm sec" onclick="marcarAdicionar(this)">+ Adicionar</button>
      <ul class="add-pendentes" style="list-style:none;padding:0;margin:0;display:flex;gap:.3rem;flex-wrap:wrap"></ul>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- BARRA DE AÇÃO (rodapé) -->
  <div id="barraBaixo" class="barra-acao" style="display:none">
    <span><b id="qtdBaixo">0</b> alteração(ões) pendentes</span>
    <span>
      <button type="button" class="btn sm sec" onclick="descartarTudo()">Descartar</button>
      <button type="button" class="btn" onclick="salvarTudo()">💾 Salvar todos</button>
    </span>
  </div>

  <!-- Formulário oculto que envia o lote -->
  <form id="formLote" method="post" style="display:none">
    <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
    <input type="hidden" name="op" value="salvar_lote">
    <input type="hidden" name="mes" value="<?= $mes ?>">
    <input type="hidden" name="ano" value="<?= $ano ?>">
    <input type="hidden" name="ops" id="opsField" value="[]">
  </form>

<?php endif; ?>

<style>
.barra-acao{
  position:sticky;top:.6rem;z-index:10;display:flex!important;justify-content:space-between;align-items:center;gap:1rem;
  background:linear-gradient(120deg,var(--laranja-5),var(--laranja-4));color:#fff;
  padding:.7rem 1rem;border-radius:12px;margin:1rem 0;box-shadow:var(--sombra)
}
#barraBaixo{position:static;margin-top:1rem}
.barra-acao .btn{background:#fff;color:var(--laranja-6)}
.barra-acao .btn.sec{background:rgba(255,255,255,.25);color:#fff}
tr.linha-escalado.pendente-trocar{background:#fff7e3}
tr.linha-escalado.pendente-remover{background:#fbe0e0;text-decoration:line-through;text-decoration-color:#c0392b}
.tag-status{font-size:.72rem;font-weight:700;margin-left:.5rem;padding:.1rem .5rem;border-radius:10px;display:none}
.linha-escalado.pendente-trocar .tag-status{display:inline-block;background:#fde9b8;color:#8a5a10}
.linha-escalado.pendente-remover .tag-status{display:inline-block;background:#f6cccc;color:#a83b3b;text-decoration:none}
.add-pendentes li{display:inline-flex;align-items:center;gap:.3rem;background:#dff3e3;color:#2f7d49;
  border:1px solid #a6d4b0;border-radius:14px;padding:.15rem .45rem;font-size:.78rem;font-weight:600}
.add-pendentes button{background:none;border:none;cursor:pointer;color:#2f7d49;font-size:.95rem;line-height:1;padding:0 2px}

/* Arrastar para reordenar */
.alca{cursor:grab;color:var(--laranja-4);font-weight:700;text-align:center;
  width:32px;font-size:1.1rem;user-select:none}
.alca:active{cursor:grabbing}
tr.linha-escalado{transition:background .15s}
tr.sortable-chosen{background:#fff5e8 !important}
tr.sortable-ghost{opacity:.4}

.barra-ordem{display:flex !important;flex-wrap:wrap;gap:.4rem;align-items:center;
  background:#fff5e8;border:1px solid var(--laranja-3);border-radius:10px;
  padding:.6rem .8rem;margin-top:.7rem;font-size:.9rem;color:var(--laranja-6)}
</style>

<script>
const COLAB = <?= json_encode($colabInfo, JSON_UNESCAPED_UNICODE) ?>;
const INDISP = <?= json_encode($indisponMes, JSON_UNESCAPED_UNICODE) ?>;
const LABEL = {lider:'A1', pleno:'A2', junior:'A3'};

// Estrutura: array de operações pendentes.
// cada op: {uid, tipo:'substituir'|'remover'|'adicionar', escala_id, ...campos}
let pendentes = [];

function compativel(nSai, nEntra){
  if (nSai === 'lider') return nEntra === 'lider';
  return nEntra === 'pleno' || nEntra === 'junior';
}
function indisponivel(eid, cid){
  return (INDISP[eid]||[]).map(Number).includes(parseInt(cid));
}
function uid(){ return 'p'+Math.random().toString(36).slice(2,9); }
function atualizarBarra(){
  const n = pendentes.length;
  document.getElementById('qtdTopo').textContent = n;
  document.getElementById('qtdBaixo').textContent = n;
  document.getElementById('barraTopo').style.display = n ? 'flex' : 'none';
  document.getElementById('barraBaixo').style.display = n ? 'flex' : 'none';
}

// --- TROCAR ---
document.querySelectorAll('.sel-trocar').forEach(sel=>{
  sel.addEventListener('change', () => marcarTrocar(sel));
});
function marcarTrocar(sel){
  const entra = sel.value;
  const tr = sel.closest('tr');
  const eid = parseInt(tr.closest('.evento').dataset.eid);
  const sai = parseInt(tr.dataset.cid);
  if (!entra) return;
  // remove pendentes anteriores desta mesma linha
  pendentes = pendentes.filter(p => !(p.tipo!=='adicionar' && p.escala_id===eid && p._linha===tr.dataset.uidLinha));
  if (!tr.dataset.uidLinha) tr.dataset.uidLinha = uid();
  const ce = COLAB[entra];
  pendentes.push({
    uid: uid(), _linha: tr.dataset.uidLinha,
    tipo:'substituir', escala_id:eid, sai_id:sai, entra_id:parseInt(entra)
  });
  tr.classList.remove('pendente-remover');
  tr.classList.add('pendente-trocar');
  tr.querySelector('.nome-pendente').textContent = ' → ' + ce.nome + ' (' + LABEL[ce.nivel] + ')';
  tr.querySelector('.nome-pendente').style.display = 'inline';
  tr.querySelector('.tag-status').textContent = 'trocar';
  tr.querySelector('.btn-desfazer').style.display = 'inline-block';
  atualizarBarra();
}

// --- REMOVER ---
function marcarRemover(btn){
  const tr = btn.closest('tr');
  const eid = parseInt(tr.closest('.evento').dataset.eid);
  const cid = parseInt(tr.dataset.cid);
  if (!tr.dataset.uidLinha) tr.dataset.uidLinha = uid();
  // limpa qualquer pendência anterior da mesma linha
  pendentes = pendentes.filter(p => !(p._linha === tr.dataset.uidLinha));
  pendentes.push({
    uid: uid(), _linha: tr.dataset.uidLinha,
    tipo:'remover', escala_id:eid, colaborador_id:cid
  });
  tr.classList.remove('pendente-trocar');
  tr.classList.add('pendente-remover');
  tr.querySelector('.sel-trocar').value = '';
  tr.querySelector('.nome-pendente').style.display = 'none';
  tr.querySelector('.tag-status').textContent = 'remover';
  tr.querySelector('.btn-desfazer').style.display = 'inline-block';
  atualizarBarra();
}
function desfazerLinha(btn){
  const tr = btn.closest('tr');
  pendentes = pendentes.filter(p => p._linha !== tr.dataset.uidLinha);
  tr.classList.remove('pendente-trocar','pendente-remover');
  tr.querySelector('.sel-trocar').value = '';
  tr.querySelector('.nome-pendente').style.display = 'none';
  tr.querySelector('.tag-status').textContent = '';
  btn.style.display = 'none';
  atualizarBarra();
}

// --- ADICIONAR ---
function marcarAdicionar(btn){
  const area = btn.closest('.add-area');
  const sel = area.querySelector('.sel-adicionar');
  const cid = sel.value;
  if (!cid) return;
  // evita duplicar a mesma adição
  const eid = parseInt(btn.closest('.evento').dataset.eid);
  if (pendentes.some(p=>p.tipo==='adicionar' && p.escala_id===eid && p.colaborador_id===parseInt(cid))) return;
  const ce = COLAB[cid];
  const u = uid();
  pendentes.push({uid:u, tipo:'adicionar', escala_id:eid, colaborador_id:parseInt(cid)});
  const ul = area.querySelector('.add-pendentes');
  const li = document.createElement('li');
  li.dataset.uid = u;
  li.innerHTML = '+ ' + ce.nome + ' (' + LABEL[ce.nivel] + ') <button type="button" title="desfazer">×</button>';
  li.querySelector('button').addEventListener('click', ()=>{
    pendentes = pendentes.filter(p=>p.uid!==u);
    li.remove();
    atualizarBarra();
  });
  ul.appendChild(li);
  sel.value = '';
  atualizarBarra();
}

// --- SALVAR / DESCARTAR ---
function descartarTudo(){
  if (!pendentes.length) return;
  if (!confirm('Descartar todas as alterações pendentes?')) return;
  pendentes = [];
  document.querySelectorAll('.linha-escalado').forEach(tr=>{
    tr.classList.remove('pendente-trocar','pendente-remover');
    tr.querySelector('.sel-trocar').value = '';
    tr.querySelector('.nome-pendente').style.display = 'none';
    tr.querySelector('.tag-status').textContent = '';
    tr.querySelector('.btn-desfazer').style.display = 'none';
  });
  document.querySelectorAll('.add-pendentes').forEach(ul => ul.innerHTML = '');
  atualizarBarra();
}
function salvarTudo(){
  if (!pendentes.length) return;
  // gera avisos
  const avisos = [];
  pendentes.forEach(p=>{
    if (p.tipo === 'substituir') {
      const cs = COLAB[p.sai_id], ce = COLAB[p.entra_id];
      if (cs && ce && !compativel(cs.nivel, ce.nivel))
        avisos.push(`${cs.nome} (${LABEL[cs.nivel]}) → ${ce.nome} (${LABEL[ce.nivel]}): níveis diferentes.`);
      if (indisponivel(p.escala_id, p.entra_id))
        avisos.push(`${ce.nome} marcou indisponibilidade.`);
    }
    if (p.tipo === 'adicionar') {
      if (indisponivel(p.escala_id, p.colaborador_id))
        avisos.push(`${COLAB[p.colaborador_id].nome} marcou indisponibilidade no evento que recebe.`);
    }
  });
  if (avisos.length) {
    if (!confirm('Atenção:\n- ' + avisos.join('\n- ') + `\n\nDeseja salvar ${pendentes.length} alteração(ões) mesmo assim?`)) return;
  } else {
    if (!confirm(`Salvar ${pendentes.length} alteração(ões)?`)) return;
  }
  document.getElementById('opsField').value = JSON.stringify(pendentes);
  document.getElementById('formLote').submit();
}

// ============================================================
//  Arrastar para reordenar (SortableJS)
// ============================================================
const ordemOriginal = {};
const ordemAtual    = {};

// guarda a ordem inicial de cada tbody
document.querySelectorAll('tbody.ordenavel').forEach(tb => {
  const eid = tb.dataset.eid;
  ordemOriginal[eid] = Array.from(tb.querySelectorAll('tr')).map(tr => tr.dataset.cid);
  ordemAtual[eid]    = [...ordemOriginal[eid]];
});

function carregarSortable(){
  document.querySelectorAll('tbody.ordenavel').forEach(tb => {
    new Sortable(tb, {
      handle: '.alca',
      animation: 150,
      ghostClass: 'sortable-ghost',
      chosenClass: 'sortable-chosen',
      onEnd: () => {
        const eid = tb.dataset.eid;
        ordemAtual[eid] = Array.from(tb.querySelectorAll('tr')).map(tr => tr.dataset.cid);
        const mudou = JSON.stringify(ordemAtual[eid]) !== JSON.stringify(ordemOriginal[eid]);
        const barra = document.getElementById('barra-ordem-' + eid);
        if (barra) barra.style.display = mudou ? 'flex' : 'none';
      }
    });
  });
}

function salvarOrdem(eid, escopo){
  const form = document.getElementById('form-ordem-' + eid);
  form.querySelector('input[name=escopo]').value = escopo;
  form.querySelector('input[name=ordem]').value = JSON.stringify(ordemAtual[eid]);
  if (escopo === 'todos') {
    if (!confirm('Aplicar esta ordem como padrão para TODOS os eventos? Os colaboradores aparecerão nesta ordem em todas as escalas.')) return;
  }
  form.submit();
}

function cancelarOrdem(eid){
  const tb = document.querySelector('tbody.ordenavel[data-eid="'+eid+'"]');
  // reordena DOM para a ordem original
  ordemOriginal[eid].forEach(cid => {
    const tr = tb.querySelector('tr[data-cid="'+cid+'"]');
    if (tr) tb.appendChild(tr);
  });
  ordemAtual[eid] = [...ordemOriginal[eid]];
  document.getElementById('barra-ordem-' + eid).style.display = 'none';
}

// carrega SortableJS sob demanda
if (typeof Sortable === 'undefined') {
  const s = document.createElement('script');
  s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js';
  s.onload = carregarSortable;
  document.head.appendChild(s);
} else {
  carregarSortable();
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
