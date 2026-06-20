<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();
$pdo = db();

$mes = (int)($_GET['mes'] ?? date('n'));
$ano = (int)($_GET['ano'] ?? date('Y'));

// escalas do mês
$st = $pdo->prepare("SELECT * FROM escalas WHERE mes=? AND ano=? ORDER BY data_evento, horario_chegada");
$st->execute([$mes, $ano]);
$escalas = $st->fetchAll();

// agrupa por dia + carrega escalados
$porDia = [];
$escalados = [];
if ($escalas) {
    $ids = implode(',', array_column($escalas, 'id'));
    $q = $pdo->query(
      "SELECT ec.escala_id, c.nome, c.celular, ec.nivel_na_escala
       FROM escala_colaboradores ec JOIN colaboradores c ON c.id=ec.colaborador_id
       WHERE ec.escala_id IN ($ids)
       ORDER BY FIELD(ec.nivel_na_escala,'lider','pleno','junior'), c.nome"
    );
    foreach ($q as $r) $escalados[$r['escala_id']][] = $r;
    foreach ($escalas as $es) $porDia[(int)date('j', strtotime($es['data_evento']))][] = $es;
}

$meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
          7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];

// estrutura do calendário
$primeiro   = mktime(0,0,0,$mes,1,$ano);
$diasNoMes  = (int)date('t', $primeiro);
$diaSemana1 = (int)date('w', $primeiro);   // 0=Dom
$semanas    = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

$titulo = 'Calendário de Escalas';
require __DIR__ . '/includes/header.php';
?>
<div class="no-print">
  <div class="flex-between">
    <div>
      <h1 class="page-title">Calendário de Escalas</h1>
      <p class="page-sub">Visualização mensal pronta para impressão.</p>
    </div>
    <form method="get" style="display:flex;gap:.5rem;align-items:flex-end">
      <div><label>Mês</label>
        <select name="mes" onchange="this.form.submit()">
          <?php foreach ($meses as $k=>$v): ?><option value="<?= $k ?>" <?= $k===$mes?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label>Ano</label><input type="number" name="ano" value="<?= $ano ?>" style="max-width:110px" onchange="this.form.submit()"></div>
      <button type="button" class="btn" onclick="window.print()">🖨️ Imprimir</button>
    </form>
  </div>
</div>

<div class="cal-folha">
  <div class="cal-cabecalho">
    <div class="cal-marca">🅿️ Apoio Externo · Gestão de Escala</div>
    <h2 class="cal-titulo"><?= $meses[$mes] ?> de <?= $ano ?></h2>
    <div class="cal-legenda">
      <span class="badge lider">A1</span>
      <span class="badge pleno">A2</span>
      <span class="badge junior">A3</span>
    </div>
  </div>

  <table class="cal">
    <thead>
      <tr><?php foreach ($semanas as $s): ?><th><?= $s ?></th><?php endforeach; ?></tr>
    </thead>
    <tbody>
      <?php
      $dia = 1;
      $totalCelulas = $diaSemana1 + $diasNoMes;
      $linhas = (int)ceil($totalCelulas / 7);
      for ($l = 0; $l < $linhas; $l++): ?>
      <tr>
        <?php for ($c = 0; $c < 7; $c++):
          $idx = $l*7 + $c;
          if ($idx < $diaSemana1 || $dia > $diasNoMes): ?>
            <td class="vazio"></td>
        <?php else:
            $fds = in_array($c, [0,6], true);
            $eventosDoDia = $porDia[$dia] ?? []; ?>
            <td class="<?= $fds?'fds':'' ?> <?= $eventosDoDia?'com-evento':'' ?>">
              <div class="num-dia"><?= $dia ?></div>
              <?php
                $diasSemNome = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
                foreach ($eventosDoDia as $ev): ?>
                <div class="evento">
                  <div class="ev-dia"><?= $diasSemNome[$c] ?></div>
                  <div class="ev-nome"><?= e($ev['evento']) ?></div>
                  <div class="ev-info">⏰ <?= substr($ev['horario_chegada'],0,5) ?></div>
                  <?php if (!empty($escalados[$ev['id']])): ?>
                    <ul class="ev-equipe">
                      <?php foreach ($escalados[$ev['id']] as $p): ?>
                        <li><span class="dot-<?= $p['nivel_na_escala'] ?>"></span><?= e($p['nome']) ?></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php else: ?>
                    <div class="ev-vazio">— não preenchida —</div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </td>
        <?php $dia++; endif; ?>
        <?php endfor; ?>
      </tr>
      <?php endfor; ?>
    </tbody>
  </table>

  <div class="cal-rodape">
    Gerado em <?= date('d/m/Y H:i') ?> · <?= count($escalas) ?> evento(s) no mês
  </div>
</div>

<style>
/* ---------- Calendário (tela) ---------- */
.cal-folha{background:#fff;border:1px solid var(--borda);border-radius:var(--raio);
  box-shadow:var(--sombra);padding:1.6rem;margin-top:1rem}
.cal-cabecalho{text-align:center;border-bottom:2px solid var(--laranja-3);padding-bottom:1rem;margin-bottom:1.2rem}
.cal-marca{color:var(--laranja-6);font-weight:700;font-size:1rem}
.cal-titulo{font-size:1.7rem;color:var(--laranja-6);margin:.3rem 0}
.cal-legenda{display:flex;gap:.5rem;justify-content:center;margin-top:.4rem}

table.cal{width:100%;border-collapse:collapse;table-layout:fixed}
table.cal th{background:var(--laranja-2);color:var(--laranja-6);text-align:center;
  padding:.5rem;font-size:.8rem;border:1px solid var(--borda);text-transform:uppercase}
table.cal td{border:1px solid var(--borda);vertical-align:top;height:84px;padding:.3rem;
  width:14.28%;background:#fff}
table.cal td.vazio{background:var(--laranja-1)}
table.cal td.fds{background:#fffaf4}
table.cal td.com-evento{background:linear-gradient(180deg,#fff,#fff7f0)}
.num-dia{font-weight:700;color:var(--texto-suave);font-size:.85rem;margin-bottom:.2rem}
.evento{background:var(--laranja-1);border:1px solid var(--laranja-3);border-radius:8px;
  padding:.35rem .4rem;margin-bottom:.3rem}
.ev-nome{font-weight:700;color:var(--laranja-6);font-size:.74rem;line-height:1.15}
.ev-info{font-size:.68rem;color:var(--texto-suave);margin:.15rem 0}
.ev-dia{font-size:.62rem;font-weight:700;color:var(--laranja-6);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.1rem}
.ev-equipe{list-style:none;margin:.2rem 0 0;padding:0}
.ev-equipe li{font-size:.7rem;color:var(--texto);display:flex;align-items:center;gap:.3rem;line-height:1.45}
.ev-vazio{font-size:.68rem;color:var(--vermelho)}
.dot-lider,.dot-pleno,.dot-junior{width:7px;height:7px;border-radius:50%;flex:0 0 auto}
.dot-lider{background:#e8843f}.dot-pleno{background:#2e8aa8}.dot-junior{background:#3c9a5a}
.cal-rodape{text-align:center;color:var(--texto-suave);font-size:.78rem;margin-top:1rem;
  border-top:1px solid var(--borda);padding-top:.7rem}

/* ---------- Impressão (1 folha A4 paisagem) ---------- */
@media print{
  @page{size:A4 landscape;margin:8mm}
  html,body{background:#fff!important;height:auto}
  .sidebar,.mobtop,.shade,.no-print,.flash{display:none!important}
  .conteudo{margin:0!important;max-width:100%;padding:0}
  .cal-folha{box-shadow:none;border:none;padding:0;margin:0;
    height:192mm;display:flex;flex-direction:column}

  /* cabeçalho enxuto */
  .cal-cabecalho{padding-bottom:.3rem;margin-bottom:.4rem;border-bottom-width:1px}
  .cal-marca{font-size:.72rem}
  .cal-titulo{font-size:1.05rem;margin:.1rem 0}
  .cal-legenda{margin-top:.15rem}
  .cal-legenda .badge{font-size:.6rem;padding:.1rem .4rem}

  /* a tabela ocupa toda a altura restante e divide as linhas igualmente */
  table.cal{flex:1 1 auto;height:100%;page-break-inside:avoid}
  table.cal th{padding:.15rem;font-size:.6rem;border-width:1px}
  table.cal td{height:auto;padding:.15rem .2rem;border-width:1px;overflow:hidden}
  table.cal td.com-evento{background:#fff7f0}

  .num-dia{font-size:.62rem;margin-bottom:.08rem}
  .evento{padding:.12rem .2rem;margin-bottom:.12rem;border-radius:4px;break-inside:avoid}
  .ev-nome{font-size:.58rem;line-height:1.05}
  .ev-info{font-size:.52rem;margin:.04rem 0}
  .ev-dia{font-size:.5rem;margin-bottom:.04rem}
  .ev-equipe li{font-size:.54rem;line-height:1.2;gap:.18rem}
  .ev-equipe .dot-lider,.ev-equipe .dot-pleno,.ev-equipe .dot-junior{width:5px;height:5px}
  .ev-vazio{font-size:.52rem}

  .cal-rodape{font-size:.55rem;margin-top:.3rem;padding-top:.25rem}

  /* nada deve forçar nova página */
  tr,td,.evento{page-break-inside:avoid}
}
</style>
<?php require __DIR__ . '/includes/footer.php'; ?>
