<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();
$pdo = db();

$mes = (int)($_GET['mes'] ?? date('n'));
$ano = (int)($_GET['ano'] ?? date('Y'));

$st = $pdo->prepare(
  "SELECT * FROM escalas WHERE mes=? AND ano=? ORDER BY data_evento, horario_chegada"
);
$st->execute([$mes, $ano]);
$eventos = $st->fetchAll();

// busca escalados de todos os eventos, na ordem combinada (posicao -> ordem_padrao -> nivel/nome)
$escalados = [];
if ($eventos) {
    $ids = implode(',', array_column($eventos, 'id'));
    $q = $pdo->query(
      "SELECT ec.escala_id, c.nome, ec.nivel_na_escala
       FROM escala_colaboradores ec JOIN colaboradores c ON c.id=ec.colaborador_id
       WHERE ec.escala_id IN ($ids)
       ORDER BY
         ec.posicao IS NULL,
         ec.posicao,
         c.ordem_padrao IS NULL,
         c.ordem_padrao,
         FIELD(ec.nivel_na_escala,'lider','pleno','junior'),
         c.nome"
    );
    foreach ($q as $r) $escalados[$r['escala_id']][] = $r;
}

$meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
          7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
$mesesAbrev = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',
               7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
$diaSemAbrev = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

$titulo = 'Eventos do mês';
require __DIR__ . '/includes/header.php';
?>
<div class="barra-lista no-print">
  <div>
    <h1 class="page-title" style="margin-bottom:.2rem">Eventos do mês</h1>
    <p class="page-sub" style="margin:0"><?= e($meses[$mes]) ?> / <?= $ano ?> · <?= count($eventos) ?> evento(s)</p>
  </div>
  <form method="get" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
    <select name="mes" onchange="this.form.submit()">
      <?php foreach ($meses as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $k===$mes?'selected':'' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <input type="number" name="ano" value="<?= $ano ?>" style="max-width:90px" onchange="this.form.submit()">
    <button type="button" class="btn" onclick="window.print()">🖨️ Imprimir</button>
  </form>
</div>

<div class="cabecalho-impressao print-only">
  <h2>Apoio Externo · Igreja Primazia</h2>
  <p>Eventos de <?= e($meses[$mes]) ?> / <?= $ano ?></p>
</div>

<?php if (!$eventos): ?>
  <div class="card"><p class="muted">Nenhum evento neste mês.</p></div>
<?php else: ?>

<div class="eventos-grid">
  <?php foreach ($eventos as $ev):
    $t = strtotime($ev['data_evento']);
    $dia = (int)date('d', $t);
    $mesEv = (int)date('n', $t);
    $diaSem = (int)date('w', $t);
    $equipe = $escalados[$ev['id']] ?? [];
  ?>
  <div class="ev-card">
    <div class="ev-cab">
      <div class="ev-data-box">
        <div class="ev-data-dia-sem"><?= $diaSemAbrev[$diaSem] ?></div>
        <div class="ev-data-dia"><?= sprintf('%02d', $dia) ?></div>
        <div class="ev-data-mes"><?= $mesesAbrev[$mesEv] ?></div>
      </div>
      <div class="ev-corpo">
        <div class="ev-nome"><?= e($ev['evento']) ?></div>
        <div class="ev-horario">⏰ <?= substr($ev['horario_chegada'],0,5) ?></div>
      </div>
    </div>
    <?php if ($equipe): ?>
    <div class="ev-corpo-baixo">
      <ul class="ev-equipe">
        <?php foreach ($equipe as $p): ?>
          <li class="nivel-<?= e($p['nivel_na_escala']) ?>"><?= e($p['nome']) ?></li>
        <?php endforeach; ?>
      </ul>
      <span class="ev-colete" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
          <path d="M20 10 L32 16 L44 10 L52 16 L48 56 L16 56 L12 16 Z" fill="#f47b20" stroke="#d9641a" stroke-width="1.5"/>
          <path d="M32 16 L30 56 M32 16 L34 56" stroke="#d9641a" stroke-width="1.2" fill="none"/>
          <path d="M20 10 L26 22 M44 10 L38 22" stroke="#d9641a" stroke-width="1.2" fill="none"/>
          <rect x="14" y="30" width="36" height="4" fill="#ffe000" stroke="#e6c200" stroke-width="0.5"/>
          <rect x="14" y="42" width="36" height="4" fill="#ffe000" stroke="#e6c200" stroke-width="0.5"/>
          <rect x="22" y="22" width="3.5" height="32" fill="#ffe000" stroke="#e6c200" stroke-width="0.4"/>
          <rect x="38.5" y="22" width="3.5" height="32" fill="#ffe000" stroke="#e6c200" stroke-width="0.4"/>
        </svg>
      </span>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<?php endif; ?>

<style>
.barra-lista{display:flex;justify-content:space-between;align-items:flex-end;
  gap:1rem;flex-wrap:wrap;margin-bottom:1.2rem}
.eventos-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
@media(max-width:680px){.eventos-grid{grid-template-columns:1fr}}

.ev-card{background:var(--laranja-1);border:1.5px solid var(--laranja-3);
  border-radius:12px;padding:1rem 1.1rem;transition:.15s}
.ev-card:hover{border-color:var(--laranja-5);transform:translateY(-2px);
  box-shadow:0 6px 18px rgba(232,132,63,.15)}

.ev-cab{display:flex;align-items:flex-start;gap:.6rem;margin-bottom:.5rem}
.ev-data-box{text-align:center;background:#fff;border:1px solid var(--laranja-3);
  border-radius:8px;padding:.4rem .55rem;min-width:54px}
.ev-data-dia-sem{font-size:.6rem;font-weight:700;color:var(--laranja-6);
  text-transform:uppercase;letter-spacing:.5px}
.ev-data-dia{font-size:1.4rem;font-weight:700;color:var(--laranja-6);line-height:1}
.ev-data-mes{font-size:.65rem;color:var(--texto-suave);text-transform:uppercase}

.ev-corpo{flex:1}
.ev-nome{font-weight:700;color:var(--laranja-6);font-size:1.05rem;margin-bottom:.2rem}
.ev-horario{font-size:.85rem;color:var(--texto-suave)}

.ev-equipe{list-style:none;padding:0;margin:.6rem 0 0;
  border-top:1px solid var(--laranja-3);padding-top:.55rem;flex:1}
.ev-equipe li{font-size:.92rem;padding:.18rem 0;font-weight:600}
.nivel-lider{color:#9a4f12}
.nivel-pleno{color:#1f6b86}
.nivel-junior{color:#2f7d49}

/* colete de apoio ao lado da lista */
.ev-corpo-baixo{display:flex;justify-content:space-between;align-items:center;gap:.5rem}
.ev-colete{width:55px;height:55px;flex:0 0 auto;opacity:.95;align-self:center}
.ev-colete svg{width:100%;height:100%;display:block}

/* impressão A4 */
.cabecalho-impressao{display:none;text-align:center;margin-bottom:1rem}
.cabecalho-impressao h2{color:var(--laranja-6);margin:0 0 .2rem}
.cabecalho-impressao p{color:var(--texto-suave);margin:0}
.print-only{display:none}

@media print{
  body{background:#fff}
  .conteudo{padding:0;background:#fff}
  .sidebar,.mobtop,.shade,#pwa-instalar,#pwa-fab,.rodape-dev,
  .barra-lista,.flash,.no-print{display:none !important}
  .cabecalho-impressao,.print-only{display:block !important}
  .eventos-grid{grid-template-columns:1fr 1fr;gap:.6rem}
  .ev-card{break-inside:avoid;page-break-inside:avoid;
    box-shadow:none;border:1px solid #ccc;background:#fff;padding:.6rem .7rem}
  .ev-card:hover{transform:none;box-shadow:none}
  .ev-equipe{border-top-color:#ccc}
  .ev-data-box{border-color:#ccc}
  @page{size:A4;margin:1.2cm}
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
