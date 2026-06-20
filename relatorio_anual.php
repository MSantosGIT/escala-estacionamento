<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();
$pdo = db();

$ano = (int)($_GET['ano'] ?? date('Y'));

// participações: colaborador x mês (apenas do ano selecionado)
$st = $pdo->prepare(
  "SELECT ec.colaborador_id, es.mes, COUNT(*) AS qtd
   FROM escala_colaboradores ec
   JOIN escalas es ON es.id = ec.escala_id
   WHERE es.ano = ?
   GROUP BY ec.colaborador_id, es.mes"
);
$st->execute([$ano]);

// mapa[colab_id][mes] = qtd
$mapa = [];
foreach ($st as $r) {
    $mapa[(int)$r['colaborador_id']][(int)$r['mes']] = (int)$r['qtd'];
}

// colaboradores (ativos primeiro, mas inclui inativos que tenham participação)
$colabs = $pdo->query(
  "SELECT id, nome, nivel, ativo FROM colaboradores ORDER BY ativo DESC, FIELD(nivel,'lider','pleno','junior'), nome"
)->fetchAll();

$mesesAbrev = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',
               7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
$mesAtual = (int)date('n');
$anoAtual = (int)date('Y');

// totais por mês (rodapé) e geral
$totMes = array_fill(1, 12, 0);
$totGeral = 0;

$titulo = 'Relatório Anual';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Participações por colaborador</h1>
<p class="page-sub">Total de vezes que cada colaborador foi escalado, mês a mês.</p>

<div class="card">
  <form method="get" style="display:flex;gap:.6rem;align-items:flex-end">
    <div><label>Ano</label>
      <div style="display:flex;gap:.4rem;align-items:center">
        <a class="btn sm sec" href="?ano=<?= $ano-1 ?>">←</a>
        <input type="number" name="ano" value="<?= $ano ?>" style="max-width:110px" onchange="this.form.submit()">
        <a class="btn sm sec" href="?ano=<?= $ano+1 ?>">→</a>
      </div>
    </div>
    <button class="btn sm" onclick="window.print();return false;">🖨️ Imprimir</button>
  </form>
</div>

<div class="card folha">
  <div class="cab-rel">
    <div class="rel-titulo">Efetivo · <?= $ano ?></div>
  </div>

  <div style="overflow-x:auto">
  <table class="matriz">
    <thead>
      <tr>
        <th class="col-n">#</th>
        <th class="col-nome">Colaborador</th>
        <th class="col-niv">Nível</th>
        <?php foreach ($mesesAbrev as $m=>$lbl): ?>
          <th class="<?= ($m===$mesAtual && $ano===$anoAtual)?'mes-atual':'' ?>"><?= $lbl ?></th>
        <?php endforeach; ?>
        <th class="col-tot">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php $i=0; foreach ($colabs as $c): $i++;
        $linhaTotal = 0; ?>
      <tr class="<?= $c['ativo']?'':'inativo' ?>">
        <td class="col-n"><?= $i ?></td>
        <td class="col-nome"><?= e($c['nome']) ?><?php if(!$c['ativo']):?> <span class="badge junior">inativo</span><?php endif;?></td>
        <td class="col-niv"><span class="badge <?= $c['nivel'] ?>"><?= nivelLabel($c['nivel']) ?></span></td>
        <?php foreach ($mesesAbrev as $m=>$lbl):
          $q = $mapa[(int)$c['id']][$m] ?? 0;
          $linhaTotal += $q; $totMes[$m] += $q;
          // destaque: passado sem participação = vermelho; mês atual = leve
          $passado = ($ano < $anoAtual) || ($ano===$anoAtual && $m < $mesAtual);
          $cls = '';
          if ($q === 0 && $passado) $cls = 'zero';
          elseif ($m===$mesAtual && $ano===$anoAtual) $cls = 'mes-atual';
        ?>
          <td class="num <?= $cls ?>"><?= $q ?: ($passado?'0':'·') ?></td>
        <?php endforeach; ?>
        <td class="num col-tot"><b><?= $linhaTotal ?></b></td>
      </tr>
      <?php $totGeral += $linhaTotal; endforeach; ?>
      <?php if (!$colabs): ?><tr><td colspan="16" class="muted">Nenhum colaborador cadastrado.</td></tr><?php endif; ?>
    </tbody>
    <?php if ($colabs): ?>
    <tfoot>
      <tr class="rodape">
        <td></td><td>Total por mês</td><td></td>
        <?php foreach ($mesesAbrev as $m=>$lbl): ?>
          <td class="num"><b><?= $totMes[$m] ?></b></td>
        <?php endforeach; ?>
        <td class="num col-tot"><b><?= $totGeral ?></b></td>
      </tr>
    </tfoot>
    <?php endif; ?>
  </table>
  </div>

  <p class="muted" style="margin-top:1rem">
    Legenda: <span class="leg zero">0</span> mês passado sem nenhuma participação ·
    <span class="leg mes-atual">&nbsp;</span> mês atual · <b>·</b> mês futuro.
  </p>
</div>

<style>
.folha .cab-rel{text-align:center;border-bottom:2px solid var(--laranja-3);padding-bottom:.6rem;margin-bottom:1rem}
.rel-titulo{font-size:1.4rem;color:var(--laranja-6);font-weight:700;letter-spacing:1px}

table.matriz{width:100%;border-collapse:collapse;font-size:.9rem}
table.matriz th,table.matriz td{border:1px solid var(--borda);padding:.5rem .45rem;text-align:center}
table.matriz thead th{background:var(--laranja-2);color:var(--laranja-6);font-size:.78rem;text-transform:uppercase;letter-spacing:.3px}
table.matriz th.mes-atual{background:var(--laranja-3)}
.col-n{width:34px;color:var(--texto-suave)}
.col-nome{text-align:left!important;white-space:nowrap;font-weight:600;color:var(--texto)}
.col-niv{width:64px}
.col-tot{background:var(--laranja-1);font-size:1rem}
td.num{font-variant-numeric:tabular-nums}
td.num.zero{background:#fae0e0;color:#c0392b;font-weight:700}
td.num.mes-atual{background:#eef4f8}
tr:hover td{background:var(--laranja-1)}
tr.inativo{opacity:.6}
tr.rodape td{background:var(--laranja-2);color:var(--laranja-6)}
.leg{display:inline-block;padding:0 .5rem;border-radius:6px;font-weight:700}
.leg.zero{background:#fae0e0;color:#c0392b}
.leg.mes-atual{background:#dfe9f0;width:24px}

@media print{
  @page{size:A4 landscape;margin:8mm}
  .sidebar,.mobtop,.shade,.no-print,.flash{display:none!important}
  .conteudo{margin:0!important;padding:0;max-width:100%}
  .card{box-shadow:none;border:none}
  .page-title,.page-sub,form{display:none!important}
  table.matriz{font-size:.72rem}
  table.matriz th,table.matriz td{padding:.3rem .25rem}
}
</style>
<?php require __DIR__ . '/includes/footer.php'; ?>
