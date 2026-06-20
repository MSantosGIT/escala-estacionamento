<?php
// ============================================================
//  MOTOR DE GERAÇÃO DE ESCALA
//  Regras:
//   1. Eventos com 3+ colaboradores exigem: 1 líder, 1+ pleno, 1+ júnior(sênior)
//   2. Preferência de 1 evento por colaborador no mês
//   3. Prioriza quem está há mais tempo sem ser escalado
//   4. Respeita disponibilidade (semana / fim de semana)
//   5. Grava histórico em escala_colaboradores
// ============================================================
require_once __DIR__ . '/functions.php';

/**
 * Retorna a "última data de escala" de cada colaborador.
 * Quem nunca foi escalado recebe data muito antiga (prioridade máxima).
 */
function ultimaEscalaPorColaborador(PDO $pdo): array {
    $sql = "SELECT c.id,
                   COALESCE(MAX(es.data_evento), '1900-01-01') AS ultima
            FROM colaboradores c
            LEFT JOIN escala_colaboradores ec ON ec.colaborador_id = c.id
            LEFT JOIN escalas es ON es.id = ec.escala_id
            WHERE c.ativo = 1
            GROUP BY c.id";
    $out = [];
    foreach ($pdo->query($sql) as $r) {
        $out[(int)$r['id']] = $r['ultima'];
    }
    return $out;
}

/**
 * Quantos eventos cada colaborador já tem no mês/ano informados.
 */
function escalasNoMes(PDO $pdo, int $mes, int $ano): array {
    $st = $pdo->prepare(
        "SELECT ec.colaborador_id, COUNT(*) AS qtd
         FROM escala_colaboradores ec
         JOIN escalas es ON es.id = ec.escala_id
         WHERE es.mes = ? AND es.ano = ?
         GROUP BY ec.colaborador_id"
    );
    $st->execute([$mes, $ano]);
    $out = [];
    foreach ($st as $r) {
        $out[(int)$r['colaborador_id']] = (int)$r['qtd'];
    }
    return $out;
}

/**
 * Seleciona colaboradores de um nível, ordenando por:
 *   1) menos eventos no mês (preferência de 1/mês)
 *   2) mais tempo sem ser escalado
 * Filtra por disponibilidade (fim de semana ou dia útil).
 */
function candidatos(PDO $pdo, string $nivel, bool $ehFds, array $jaEscalados): array {
    $campo = $ehFds ? 'trabalha_fds' : 'trabalha_semana';
    $st = $pdo->prepare(
        "SELECT id FROM colaboradores
         WHERE ativo = 1 AND nivel = ? AND $campo = 1"
    );
    $st->execute([$nivel]);
    $ids = array_column($st->fetchAll(), 'id');
    // remove quem já está nesta escala
    return array_values(array_diff($ids, $jaEscalados));
}

/**
 * Ordena uma lista de ids de colaboradores pela política de prioridade.
 */
function ordenarPorPrioridade(array $ids, array $ultima, array $noMes): array {
    usort($ids, function ($a, $b) use ($ultima, $noMes) {
        $ma = $noMes[$a] ?? 0;
        $mb = $noMes[$b] ?? 0;
        if ($ma !== $mb) return $ma <=> $mb;          // menos eventos no mês primeiro
        $ua = $ultima[$a] ?? '1900-01-01';
        $ub = $ultima[$b] ?? '1900-01-01';
        return strcmp($ua, $ub);                        // mais antigo primeiro
    });
    return $ids;
}

/**
 * Gera a escala de UM evento.
 * Retorna ['ok'=>bool, 'msg'=>string, 'selecionados'=>[id=>nivel]]
 */
function gerarEscalaEvento(PDO $pdo, array $escala): array {
    $escalaId = (int)$escala['id'];
    $num      = (int)$escala['num_colaboradores'];
    $exigeL   = (int)$escala['exige_lider'] === 1;
    $ehFds    = in_array((int)date('w', strtotime($escala['data_evento'])), [0, 6], true);

    $ultima = ultimaEscalaPorColaborador($pdo);
    $noMes  = escalasNoMes($pdo, (int)$escala['mes'], (int)$escala['ano']);

    $selecionados = [];   // colaborador_id => nivel
    $jaUsados     = [];

    $precisaComposicao = ($num >= 3);

    // ---- 1) Garantir composição mínima para eventos de 3+ ----
    if ($precisaComposicao || $exigeL) {
        // 1 líder
        $lid = ordenarPorPrioridade(candidatos($pdo, 'lider', $ehFds, $jaUsados), $ultima, $noMes);
        if (empty($lid)) {
            return ['ok' => false, 'msg' => 'Sem líder disponível para ' . $escala['evento'] . ' em ' . $escala['data_evento']];
        }
        $selecionados[$lid[0]] = 'lider';
        $jaUsados[] = $lid[0];
    }

    if ($precisaComposicao) {
        // 1 pleno
        $ple = ordenarPorPrioridade(candidatos($pdo, 'pleno', $ehFds, $jaUsados), $ultima, $noMes);
        if (empty($ple)) {
            return ['ok' => false, 'msg' => 'Sem pleno disponível para ' . $escala['evento'] . ' em ' . $escala['data_evento']];
        }
        $selecionados[$ple[0]] = 'pleno';
        $jaUsados[] = $ple[0];

        // 1 júnior (sênior de base)
        $jun = ordenarPorPrioridade(candidatos($pdo, 'junior', $ehFds, $jaUsados), $ultima, $noMes);
        if (empty($jun)) {
            return ['ok' => false, 'msg' => 'Sem júnior disponível para ' . $escala['evento'] . ' em ' . $escala['data_evento']];
        }
        $selecionados[$jun[0]] = 'junior';
        $jaUsados[] = $jun[0];
    }

    // ---- 2) Completar vagas restantes pela prioridade geral ----
    while (count($selecionados) < $num) {
        $pool = [];
        foreach (['junior', 'pleno', 'lider'] as $nv) {
            foreach (candidatos($pdo, $nv, $ehFds, $jaUsados) as $cid) {
                $pool[$cid] = $nv;
            }
        }
        if (empty($pool)) break;
        $ord = ordenarPorPrioridade(array_keys($pool), $ultima, $noMes);
        $escolhido = $ord[0];
        $selecionados[$escolhido] = $pool[$escolhido];
        $jaUsados[] = $escolhido;
    }

    if (count($selecionados) < $num) {
        return ['ok' => false, 'msg' => 'Colaboradores insuficientes para ' . $escala['evento'] . ' em ' . $escala['data_evento']];
    }

    // ---- 3) Persistir (histórico) ----
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM escala_colaboradores WHERE escala_id = ?")->execute([$escalaId]);
        $ins = $pdo->prepare(
            "INSERT INTO escala_colaboradores (escala_id, colaborador_id, nivel_na_escala)
             VALUES (?, ?, ?)"
        );
        foreach ($selecionados as $cid => $nv) {
            $ins->execute([$escalaId, $cid, $nv]);
        }
        $pdo->prepare("UPDATE escalas SET status = 'preenchida' WHERE id = ?")->execute([$escalaId]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        return ['ok' => false, 'msg' => 'Erro ao gravar: ' . $ex->getMessage()];
    }

    return ['ok' => true, 'msg' => 'Escala gerada.', 'selecionados' => $selecionados];
}

/**
 * Cria as escalas de todos os domingos do ano (Culto de Colaboração 17:45).
 */
function criarDomingosDoAno(PDO $pdo, int $ano): int {
    $inicio = new DateTime("$ano-01-01");
    $fim    = new DateTime("$ano-12-31");
    $criadas = 0;

    $st = $pdo->prepare(
        "INSERT IGNORE INTO escalas
         (data_evento, dia, mes, ano, evento, horario_chegada, num_colaboradores, exige_lider, status)
         VALUES (?, ?, ?, ?, 'Culto de Colaboração', '17:45:00', 3, 1, 'aberta')"
    );

    for ($d = clone $inicio; $d <= $fim; $d->modify('+1 day')) {
        if ((int)$d->format('w') === 0) { // domingo
            $st->execute([
                $d->format('Y-m-d'),
                (int)$d->format('d'),
                (int)$d->format('m'),
                (int)$d->format('Y'),
            ]);
            $criadas += $st->rowCount();
        }
    }
    return $criadas;
}
