<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();
$pdo = db();

$u = usuario();
$ehAdm = ehAdmin();
$meuColabId = (int)($u['colaborador_id'] ?? 0);

// aba ativa (evento | colaborador | mes)
$aba = $_GET['aba'] ?? 'mes';
if (!in_array($aba, ['evento','colaborador','mes'], true)) $aba = 'mes';
// colaborador comum não tem visão "por colaborador" (seria só ele mesmo)
if (!$ehAdm && $aba === 'colaborador') $aba = 'mes';

$meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
          7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
$mesAtualChave = date('Y-m');

if ($ehAdm) {
    // ================================================================
    //  ADMIN: mostra quem está DISPONÍVEL para cada evento (não quem
    //  marcou indisponibilidade) — alinhado ao título "Disponibilidade".
    // ================================================================

    $colabsAtivos = $pdo->query(
      "SELECT id, nome, nivel FROM colaboradores WHERE ativo=1
       ORDER BY FIELD(nivel,'lider','pleno','junior'), nome"
    )->fetchAll();

    $todosEventos = $pdo->query(
      "SELECT id, evento, data_evento, horario_chegada, mes, ano
       FROM escalas ORDER BY data_evento DESC, horario_chegada"
    )->fetchAll();

    // mapa: escala_id => [colaborador_id => true] de quem marcou indisponibilidade
    $indisp = [];
    if ($todosEventos) {
        $ids = implode(',', array_column($todosEventos, 'id'));
        $rows = $pdo->query("SELECT escala_id, colaborador_id FROM indisponibilidades WHERE escala_id IN ($ids)")->fetchAll();
        foreach ($rows as $r) $indisp[(int)$r['escala_id']][(int)$r['colaborador_id']] = true;
    }

    // meses com eventos (para o seletor), do mais recente ao mais antigo
    $mesesDisponiveis = [];
    foreach ($todosEventos as $ev) {
        $chave = sprintf('%04d-%02d', $ev['ano'], $ev['mes']);
        if (!isset($mesesDisponiveis[$chave])) {
            $mesesDisponiveis[$chave] = $meses[(int)$ev['mes']] . ' de ' . $ev['ano'];
        }
    }
    krsort($mesesDisponiveis);

    if (isset($_GET['mes'])) {
        $filtroMes = $_GET['mes'];
    } else {
        $filtroMes = isset($mesesDisponiveis[$mesAtualChave])
            ? $mesAtualChave
            : (array_key_first($mesesDisponiveis) ?: 'todos');
    }

    $eventosFiltrados = $todosEventos;
    if ($filtroMes !== 'todos') {
        $eventosFiltrados = array_values(array_filter(
            $todosEventos,
            fn($e) => sprintf('%04d-%02d', $e['ano'], $e['mes']) === $filtroMes
        ));
    }

    // por evento, monta a lista de quem está disponível (ativos - indisponíveis)
    $eventosComDisponiveis = [];
    foreach ($eventosFiltrados as $ev) {
        $eid = (int)$ev['id'];
        $indispDoEvento = $indisp[$eid] ?? [];
        $disponiveis = [];
        foreach ($colabsAtivos as $c) {
            if (!isset($indispDoEvento[(int)$c['id']])) $disponiveis[] = $c;
        }
        $eventosComDisponiveis[$eid] = [
            'evento' => $ev['evento'], 'data_evento' => $ev['data_evento'],
            'horario' => $ev['horario_chegada'], 'mes' => (int)$ev['mes'], 'ano' => (int)$ev['ano'],
            'disponiveis' => $disponiveis, 'total_ativos' => count($colabsAtivos),
        ];
    }

    // por evento: já é 1 evento = 1 grupo (mais recente primeiro)
    $porEvento = $eventosComDisponiveis;
    uasort($porEvento, fn($a,$b) => strcmp($b['data_evento'], $a['data_evento']));

    // por mês: agrupa os eventos dentro de cada mês
    $porMes = [];
    foreach ($eventosComDisponiveis as $eid => $ev) {
        $chaveMes = sprintf('%04d-%02d', $ev['ano'], $ev['mes']);
        if (!isset($porMes[$chaveMes])) {
            $porMes[$chaveMes] = ['mes' => $ev['mes'], 'ano' => $ev['ano'], 'eventos' => []];
        }
        $porMes[$chaveMes]['eventos'][$eid] = $ev;
    }
    krsort($porMes);
    foreach ($porMes as &$pm) {
        uasort($pm['eventos'], fn($a,$b) => strcmp($b['data_evento'], $a['data_evento']));
    }
    unset($pm);

    // "por colaborador": para cada pessoa, os eventos em que ELA está disponível
    // (mesma lógica das outras abas — consistência em toda a tela)
    $porColaborador = [];
    foreach ($colabsAtivos as $c) {
        $porColaborador[(int)$c['id']] = ['nome' => $c['nome'], 'nivel' => $c['nivel'], 'itens' => []];
    }
    foreach ($eventosFiltrados as $ev) {
        $eid = (int)$ev['id'];
        $indispDoEvento = $indisp[$eid] ?? [];
        foreach ($colabsAtivos as $c) {
            $cid = (int)$c['id'];
            if (!isset($indispDoEvento[$cid])) {
                $porColaborador[$cid]['itens'][] = [
                    'evento' => $ev['evento'], 'data_evento' => $ev['data_evento'],
                    'horario' => $ev['horario_chegada'],
                ];
            }
        }
    }
    uasort($porColaborador, fn($a,$b) => strcmp($a['nome'], $b['nome']));
    foreach ($porColaborador as &$pc) {
        usort($pc['itens'], fn($a,$b) => strcmp($b['data_evento'], $a['data_evento']));
    }
    unset($pc);

    $totalEventos = count($eventosComDisponiveis);

} else {
    // ================================================================
    //  COLABORADOR: mantém a visão pessoal — meus próprios registros
    //  de indisponibilidade (quando marquei que não poderia ir).
    // ================================================================
    $sql = "
      SELECT i.id, i.criado_em,
             c.id AS colaborador_id, c.nome AS colaborador_nome, c.nivel,
             e.id AS escala_id, e.data_evento, e.evento, e.horario_chegada, e.mes, e.ano
      FROM indisponibilidades i
      JOIN colaboradores c ON c.id = i.colaborador_id
      JOIN escalas e ON e.id = i.escala_id
      WHERE i.colaborador_id = ?
      ORDER BY e.data_evento DESC, c.nome
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$meuColabId]);
    $todosRegistros = $st->fetchAll();

    $mesesDisponiveis = [];
    foreach ($todosRegistros as $r) {
        $chave = sprintf('%04d-%02d', $r['ano'], $r['mes']);
        if (!isset($mesesDisponiveis[$chave])) {
            $mesesDisponiveis[$chave] = $meses[(int)$r['mes']] . ' de ' . $r['ano'];
        }
    }
    krsort($mesesDisponiveis);

    if (isset($_GET['mes'])) {
        $filtroMes = $_GET['mes'];
    } else {
        $filtroMes = isset($mesesDisponiveis[$mesAtualChave])
            ? $mesAtualChave
            : (array_key_first($mesesDisponiveis) ?: 'todos');
    }

    $registros = $todosRegistros;
    if ($filtroMes !== 'todos') {
        $registros = array_values(array_filter(
            $todosRegistros,
            fn($r) => sprintf('%04d-%02d', $r['ano'], $r['mes']) === $filtroMes
        ));
    }

    $porEvento = [];
    foreach ($registros as $r) {
        $eid = (int)$r['escala_id'];
        if (!isset($porEvento[$eid])) {
            $porEvento[$eid] = [
                'evento' => $r['evento'], 'data_evento' => $r['data_evento'],
                'horario' => $r['horario_chegada'], 'itens' => [],
            ];
        }
        $porEvento[$eid]['itens'][] = $r;
    }
    uasort($porEvento, fn($a,$b) => strcmp($b['data_evento'], $a['data_evento']));

    $porMes = [];
    foreach ($registros as $r) {
        $chaveMes = sprintf('%04d-%02d', $r['ano'], $r['mes']);
        $eid = (int)$r['escala_id'];
        if (!isset($porMes[$chaveMes])) {
            $porMes[$chaveMes] = ['mes' => (int)$r['mes'], 'ano' => (int)$r['ano'],
                                  'total' => 0, 'eventos' => []];
        }
        if (!isset($porMes[$chaveMes]['eventos'][$eid])) {
            $porMes[$chaveMes]['eventos'][$eid] = [
                'evento' => $r['evento'], 'data_evento' => $r['data_evento'],
                'horario' => $r['horario_chegada'], 'itens' => [],
            ];
        }
        $porMes[$chaveMes]['eventos'][$eid]['itens'][] = $r;
        $porMes[$chaveMes]['total']++;
    }
    krsort($porMes);
    foreach ($porMes as &$pm) {
        uasort($pm['eventos'], fn($a,$b) => strcmp($b['data_evento'], $a['data_evento']));
    }
    unset($pm);

    $totalRegistros = count($registros);
}

$titulo = 'Histórico de disponibilidade';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Histórico de disponibilidade</h1>
<p class="page-sub">
  <?php if ($ehAdm): ?>
    Colaboradores disponíveis para cada evento — <span class="badge ok"><?= $totalEventos ?></span> evento(s)
  <?php else: ?>
    Seus registros de indisponibilidade — <span class="badge ok"><?= $totalRegistros ?></span>
  <?php endif; ?>
</p>

<div class="abas-hist">
  <a href="?aba=mes&mes=<?= e($filtroMes) ?>" class="aba-btn <?= $aba==='mes'?'ativa':'' ?>">🗓️ Por mês</a>
  <a href="?aba=evento&mes=<?= e($filtroMes) ?>" class="aba-btn <?= $aba==='evento'?'ativa':'' ?>">🎫 Por evento</a>
  <?php if ($ehAdm): ?>
  <a href="?aba=colaborador&mes=<?= e($filtroMes) ?>" class="aba-btn <?= $aba==='colaborador'?'ativa':'' ?>">👤 Por colaborador</a>
  <?php endif; ?>
</div>

<form method="get" class="filtro-mes">
  <input type="hidden" name="aba" value="<?= e($aba) ?>">
  <label for="selMes">Mês:</label>
  <select name="mes" id="selMes" onchange="this.form.submit()">
    <option value="todos" <?= $filtroMes==='todos'?'selected':'' ?>>Todos os meses</option>
    <?php foreach ($mesesDisponiveis as $chave => $rotulo): ?>
      <option value="<?= e($chave) ?>" <?= $filtroMes===$chave?'selected':'' ?>>
        <?= e($rotulo) ?><?= $chave === $mesAtualChave ? ' (atual)' : '' ?>
      </option>
    <?php endforeach; ?>
  </select>
</form>

<?php if ($ehAdm): ?>

  <?php if (!$totalEventos): ?>
    <div class="card"><p class="muted">Nenhum evento encontrado nesse período.</p></div>
  <?php endif; ?>

  <?php if ($aba === 'mes' && $totalEventos): ?>
    <?php foreach ($porMes as $grupo): ?>
    <div class="card hist-grupo">
      <h2><?= e($meses[$grupo['mes']]) ?> de <?= $grupo['ano'] ?> <span class="badge ok"><?= count($grupo['eventos']) ?></span></h2>
      <?php foreach ($grupo['eventos'] as $ev): ?>
        <div class="hist-evento-bloco">
          <div class="hist-evento-tit">
            🎫 <?= e($ev['evento']) ?>
            <span class="hist-evento-data"><?= date('d/m/Y', strtotime($ev['data_evento'])) ?><?php if ($ev['horario']): ?> · ⏰ <?= substr($ev['horario'],0,5) ?><?php endif; ?></span>
            <span class="badge ok"><?= count($ev['disponiveis']) ?>/<?= $ev['total_ativos'] ?> disponíveis</span>
          </div>
          <?php if ($ev['disponiveis']): ?>
          <ul class="hist-lista">
            <?php foreach ($ev['disponiveis'] as $c): ?>
            <li><span class="hist-nivel nivel-<?= e($c['nivel']) ?>"><?= e($c['nome']) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?>
            <p class="muted" style="margin:.3rem 0 0;font-size:.85rem">Ninguém disponível para este evento.</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($aba === 'evento' && $totalEventos): ?>
    <?php foreach ($porEvento as $grupo): ?>
    <div class="card hist-grupo">
      <h2><?= e($grupo['evento']) ?> <span class="muted" style="font-weight:400;font-size:.85rem">— <?= date('d/m/Y', strtotime($grupo['data_evento'])) ?> ⏰ <?= substr($grupo['horario'],0,5) ?></span>
        <span class="badge ok"><?= count($grupo['disponiveis']) ?>/<?= $grupo['total_ativos'] ?> disponíveis</span></h2>
      <?php if ($grupo['disponiveis']): ?>
      <ul class="hist-lista">
        <?php foreach ($grupo['disponiveis'] as $c): ?>
        <li><span class="hist-nivel nivel-<?= e($c['nivel']) ?>"><?= e($c['nome']) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
        <p class="muted" style="margin:.3rem 0 0;font-size:.85rem">Ninguém disponível para este evento.</p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($aba === 'colaborador'): ?>
    <?php if (!$porColaborador): ?>
      <div class="card"><p class="muted">Nenhum evento encontrado nesse período.</p></div>
    <?php endif; ?>
    <?php foreach ($porColaborador as $grupo): ?>
    <div class="card hist-grupo">
      <h2 class="nivel-<?= e($grupo['nivel']) ?>"><?= e($grupo['nome']) ?> <span class="badge ok"><?= count($grupo['itens']) ?> disponível(is)</span></h2>
      <?php if ($grupo['itens']): ?>
      <ul class="hist-lista">
        <?php foreach ($grupo['itens'] as $it): ?>
        <li>
          <span class="hist-evt"><?= e($it['evento']) ?> · <?= date('d/m/Y', strtotime($it['data_evento'])) ?><?php if ($it['horario']): ?> · ⏰ <?= substr($it['horario'],0,5) ?><?php endif; ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
        <p class="muted" style="margin:.3rem 0 0;font-size:.85rem">Não está disponível para nenhum evento nesse período.</p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

<?php else: /* ---- visão do colaborador (inalterada) ---- */ ?>

  <?php if (!$totalRegistros): ?>
    <div class="card"><p class="muted">Nenhum registro de indisponibilidade encontrado.</p></div>
  <?php endif; ?>

  <?php if ($aba === 'mes' && $totalRegistros): ?>
    <?php foreach ($porMes as $grupo): ?>
    <div class="card hist-grupo">
      <h2><?= e($meses[$grupo['mes']]) ?> de <?= $grupo['ano'] ?> <span class="badge ok"><?= $grupo['total'] ?></span></h2>
      <?php foreach ($grupo['eventos'] as $ev): ?>
        <div class="hist-evento-bloco">
          <div class="hist-evento-tit">
            🎫 <?= e($ev['evento']) ?>
            <span class="hist-evento-data"><?= date('d/m/Y', strtotime($ev['data_evento'])) ?><?php if ($ev['horario']): ?> · ⏰ <?= substr($ev['horario'],0,5) ?><?php endif; ?></span>
            <span class="badge ok"><?= count($ev['itens']) ?></span>
          </div>
          <ul class="hist-lista">
            <?php foreach ($ev['itens'] as $it): ?>
            <li>
              <span class="hist-nivel nivel-<?= e($it['nivel']) ?>"><?= e($it['colaborador_nome']) ?></span>
              <span class="hist-quando">Registrado em <?= date('d/m/Y H:i', strtotime($it['criado_em'])) ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($aba === 'evento' && $totalRegistros): ?>
    <?php foreach ($porEvento as $grupo): ?>
    <div class="card hist-grupo">
      <h2><?= e($grupo['evento']) ?> <span class="muted" style="font-weight:400;font-size:.85rem">— <?= date('d/m/Y', strtotime($grupo['data_evento'])) ?> ⏰ <?= substr($grupo['horario'],0,5) ?></span>
        <span class="badge ok"><?= count($grupo['itens']) ?></span></h2>
      <ul class="hist-lista">
        <?php foreach ($grupo['itens'] as $it): ?>
        <li>
          <span class="hist-nivel nivel-<?= e($it['nivel']) ?>"><?= e($it['colaborador_nome']) ?></span>
          <span class="hist-quando">Registrado em <?= date('d/m/Y H:i', strtotime($it['criado_em'])) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

<?php endif; ?>

<style>
.abas-hist{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.2rem}
.filtro-mes{display:flex;align-items:center;gap:.5rem;margin:-.6rem 0 1.2rem;font-size:.9rem}
.filtro-mes label{color:var(--texto-suave);font-weight:600}
.filtro-mes select{min-width:200px}
.hist-evento-bloco{margin-bottom:.9rem;padding:.8rem .9rem;border-radius:10px;
  background:var(--laranja-1);border:1px solid var(--laranja-3)}
.hist-evento-bloco:last-child{margin-bottom:0}
.hist-evento-tit{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;
  font-weight:700;color:var(--laranja-6);font-size:.95rem;margin-bottom:.5rem;
  padding-bottom:.5rem;border-bottom:1px solid var(--laranja-3)}
.hist-evento-data{font-weight:400;color:var(--texto-suave);font-size:.85rem}
.aba-btn{flex:1;min-width:140px;text-align:center;background:#fff;color:var(--laranja-6);
  border:1px solid var(--borda);border-radius:10px;padding:.6rem .8rem;font-weight:700;
  font-size:.9rem;text-decoration:none}
.aba-btn.ativa{background:linear-gradient(120deg,var(--laranja-5),var(--laranja-4));color:#fff;border-color:transparent}
.hist-grupo{margin-bottom:1.1rem}
.hist-grupo h2{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
.hist-lista{list-style:none;padding:0;margin:0}
.hist-lista li{display:flex;align-items:center;gap:.7rem;flex-wrap:wrap;padding:.4rem 0;
  font-size:.9rem;border-bottom:1px dashed var(--laranja-3)}
.hist-lista li:last-child{border-bottom:none}
.hist-nivel{font-weight:700;min-width:150px}
.nivel-lider{color:#9a4f12}.nivel-pleno{color:#1f6b86}.nivel-junior{color:#2f7d49}
.hist-evt{color:#444;flex:1}
.hist-quando{color:var(--texto-suave);font-size:.8rem;white-space:nowrap}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
