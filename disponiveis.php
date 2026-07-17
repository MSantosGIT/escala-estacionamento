<?php
require_once __DIR__ . '/includes/functions.php';
exigirAdmin();
$pdo = db();

// mês/ano selecionado (padrão: mês atual)
$mes = (int)($_GET['mes'] ?? date('n'));
$ano = (int)($_GET['ano'] ?? date('Y'));

// navegação de mês anterior/próximo
$dtAtual = DateTime::createFromFormat('!n-Y', "$mes-$ano");
$dtAnt = (clone $dtAtual)->modify('-1 month');
$dtProx = (clone $dtAtual)->modify('+1 month');

// colaboradores ativos
$colabs = $pdo->query(
  "SELECT id, nome, nivel FROM colaboradores WHERE ativo=1
   ORDER BY FIELD(nivel,'lider','pleno','junior'), nome"
)->fetchAll();

// contagem total de ativos por nível
$totalPorNivel = ['lider'=>0,'pleno'=>0,'junior'=>0];
foreach ($colabs as $c) {
    if (isset($totalPorNivel[$c['nivel']])) $totalPorNivel[$c['nivel']]++;
}

// eventos do mês/ano
$st = $pdo->prepare("SELECT * FROM escalas WHERE mes=? AND ano=? ORDER BY data_evento, horario_chegada");
$st->execute([$mes, $ano]);
$eventos = $st->fetchAll();

// indisponibilidades desses eventos (mapa: escala_id => [colaborador_id => true])
$indisp = [];
if ($eventos) {
    $ids = implode(',', array_column($eventos, 'id'));
    $rows = $pdo->query("SELECT escala_id, colaborador_id FROM indisponibilidades WHERE escala_id IN ($ids)")->fetchAll();
    foreach ($rows as $r) {
        $indisp[(int)$r['escala_id']][(int)$r['colaborador_id']] = true;
    }
}

// monta disponíveis por evento
$diaSemAbrev = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
$mesesAbrev  = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',
                7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
$mesesNome   = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
                7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];

$ticketsEventos = [];
foreach ($eventos as $ev) {
    $eid = (int)$ev['id'];
    $indisponiveisDoEvento = $indisp[$eid] ?? [];
    $disponiveis = [];
    $porNivel = ['lider'=>0,'pleno'=>0,'junior'=>0];
    foreach ($colabs as $c) {
        if (!isset($indisponiveisDoEvento[(int)$c['id']])) {
            $disponiveis[] = $c;
            if (isset($porNivel[$c['nivel']])) $porNivel[$c['nivel']]++;
        }
    }
    $ticketsEventos[] = ['evento' => $ev, 'disponiveis' => $disponiveis, 'porNivel' => $porNivel];
}

$titulo = 'Disponíveis para escalar';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Disponíveis para escalar</h1>
<p class="page-sub">Colaboradores livres para cada evento — quem não marcou indisponibilidade.</p>

<div class="disp-resumo">
  <div class="disp-resumo-item"><span class="badge lider">A1</span> <?= $totalPorNivel['lider'] ?> ativo(s)</div>
  <div class="disp-resumo-item"><span class="badge pleno">A2</span> <?= $totalPorNivel['pleno'] ?> ativo(s)</div>
  <div class="disp-resumo-item"><span class="badge junior">A3</span> <?= $totalPorNivel['junior'] ?> ativo(s)</div>
  <div class="disp-resumo-item total">Total: <?= count($colabs) ?> colaborador(es) ativo(s)</div>
</div>

<div class="barra-lista">
  <div style="display:flex;align-items:center;gap:.6rem">
    <a class="btn sm sec" href="?mes=<?= $dtAnt->format('n') ?>&ano=<?= $dtAnt->format('Y') ?>">‹</a>
    <b><?= $mesesNome[$mes] ?> / <?= $ano ?></b>
    <a class="btn sm sec" href="?mes=<?= $dtProx->format('n') ?>&ano=<?= $dtProx->format('Y') ?>">›</a>
  </div>
</div>

<?php if (!$ticketsEventos): ?>
  <div class="card"><p class="muted">Nenhum evento cadastrado para <?= $mesesNome[$mes] ?>/<?= $ano ?>.</p></div>
<?php endif; ?>

<div class="eventos-grid">
  <?php foreach ($ticketsEventos as $t):
    $ev = $t['evento'];
    $dt = strtotime($ev['data_evento']);
    $diaSem = (int)date('w', $dt);
  ?>
  <div class="ev-card">
    <div class="ev-cab">
      <div class="ev-data-box">
        <div class="ev-data-dia-sem"><?= $diaSemAbrev[$diaSem] ?></div>
        <div class="ev-data-dia"><?= date('d', $dt) ?></div>
        <div class="ev-data-mes"><?= $mesesAbrev[(int)date('n', $dt)] ?></div>
      </div>
      <div class="ev-corpo">
        <div class="ev-nome"><?= e($ev['evento']) ?></div>
        <div class="ev-horario">⏰ <?= substr($ev['horario_chegada'],0,5) ?></div>
      </div>
    </div>

    <div class="disp-contagem">
      <span class="badge ok"><?= count($t['disponiveis']) ?> disponível(is)</span>
      <span class="disp-mini"><span class="badge lider">A1</span> <?= $t['porNivel']['lider'] ?></span>
      <span class="disp-mini"><span class="badge pleno">A2</span> <?= $t['porNivel']['pleno'] ?></span>
      <span class="disp-mini"><span class="badge junior">A3</span> <?= $t['porNivel']['junior'] ?></span>
    </div>

    <?php if ($t['disponiveis']):
      $porNivelNomes = ['lider'=>[], 'pleno'=>[], 'junior'=>[]];
      foreach ($t['disponiveis'] as $c) {
          if (isset($porNivelNomes[$c['nivel']])) $porNivelNomes[$c['nivel']][] = $c['nome'];
      }
    ?>
    <div class="ev-equipe-grupos">
      <?php foreach (['lider'=>'A1','pleno'=>'A2','junior'=>'A3'] as $niv => $sigla): ?>
        <?php if ($porNivelNomes[$niv]): ?>
        <div class="eq-grupo">
          <div class="eq-grupo-tit"><span class="badge <?= $niv ?>"><?= $sigla ?></span> <?= count($porNivelNomes[$niv]) ?></div>
          <ul class="ev-equipe">
            <?php foreach ($porNivelNomes[$niv] as $nome): ?>
              <li class="nivel-<?= $niv ?>"><?= e($nome) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <p class="muted" style="margin-top:.6rem">Ninguém disponível para este evento.</p>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<style>
.disp-resumo{display:flex;gap:.7rem;flex-wrap:wrap;margin:.8rem 0 1.2rem}
.disp-resumo-item{background:#fff;border:1px solid var(--borda);border-radius:10px;
  padding:.5rem .9rem;font-size:.88rem;display:flex;align-items:center;gap:.4rem;font-weight:600}
.disp-resumo-item.total{background:var(--laranja-1);border-color:var(--laranja-3);color:var(--laranja-6)}
.eventos-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
@media(max-width:680px){.eventos-grid{grid-template-columns:1fr}}
.ev-card{background:var(--laranja-1);border:1.5px solid var(--laranja-3);
  border-radius:12px;padding:1rem 1.1rem}
.ev-cab{display:flex;align-items:flex-start;gap:.6rem;margin-bottom:.6rem}
.ev-data-box{text-align:center;background:#fff;border:1px solid var(--laranja-3);
  border-radius:8px;padding:.4rem .55rem;min-width:54px}
.ev-data-dia-sem{font-size:.6rem;font-weight:700;color:var(--laranja-6);text-transform:uppercase}
.ev-data-dia{font-size:1.4rem;font-weight:700;color:var(--laranja-6);line-height:1}
.ev-data-mes{font-size:.65rem;color:var(--texto-suave);text-transform:uppercase}
.ev-corpo{flex:1}
.ev-nome{font-weight:700;color:var(--laranja-6);font-size:1.05rem;margin-bottom:.2rem}
.ev-horario{font-size:.85rem;color:var(--texto-suave)}
.disp-contagem{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;
  border-top:1px solid var(--laranja-3);border-bottom:1px solid var(--laranja-3);
  padding:.5rem 0;margin-bottom:.5rem}
.disp-mini{font-size:.82rem;font-weight:600;display:flex;align-items:center;gap:.3rem}
.ev-equipe{list-style:none;padding:0;margin:0}
.ev-equipe li{font-size:.92rem;padding:.18rem 0;font-weight:600}
.ev-equipe-grupos{display:flex;flex-direction:column;gap:.55rem}
.eq-grupo-tit{font-size:.78rem;font-weight:700;color:var(--texto-suave);
  display:flex;align-items:center;gap:.4rem;margin-bottom:.15rem}
.nivel-lider{color:#9a4f12}
.nivel-pleno{color:#1f6b86}
.nivel-junior{color:#2f7d49}
.badge.lider{background:#ffe3c2;color:#9a4f12}
.badge.pleno{background:#cfe8f2;color:#1f6b86}
.badge.junior{background:#d3ecdc;color:#2f7d49}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
