<?php
// ============================================================
//  MOTOR DE GERAÇÃO DE ESCALA
//  Regras:
//   1. Eventos com 3+ colaboradores exigem: 1 A1, 1+ A2, 1+ A3
//   2. Eventos com 1 ou 2 colaboradores NÃO escalam A1 (só A2 e A3),
//      exceto quando o evento marca "exige A1".
//   3. Preferência de 1 evento por colaborador no mês
//   4. Prioriza quem está há mais tempo sem ser escalado
//   5. Respeita disponibilidade por tipo de dia (dias de semana / sábado / domingo)
//   6. Grava histórico em escala_colaboradores
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
 * Filtra por disponibilidade conforme o tipo de dia:
 *   'semana' (seg–sex), 'sabado' ou 'domingo'.
 */
function candidatos(PDO $pdo, string $nivel, string $tipoDia, array $jaEscalados, array $indisponiveis = []): array {
    $campos = [
        'semana'  => 'trabalha_semana',
        'sabado'  => 'trabalha_sabado',
        'domingo' => 'trabalha_domingo',
    ];
    $campo = $campos[$tipoDia] ?? 'trabalha_semana';
    $st = $pdo->prepare(
        "SELECT id FROM colaboradores
         WHERE ativo = 1 AND nivel = ? AND $campo = 1"
    );
    $st->execute([$nivel]);
    $ids = array_column($st->fetchAll(), 'id');
    // remove quem já está nesta escala e quem marcou indisponibilidade
    return array_values(array_diff($ids, $jaEscalados, $indisponiveis));
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
function gerarEscalaEvento(PDO $pdo, array $escala, bool $parcial = false): array {
    $escalaId = (int)$escala['id'];
    $num      = (int)$escala['num_colaboradores'];
    $exigeL   = (int)$escala['exige_lider'] === 1;
    $diaSem   = (int)date('w', strtotime($escala['data_evento'])); // 0=Dom ... 6=Sáb
    $tipoDia  = $diaSem === 0 ? 'domingo' : ($diaSem === 6 ? 'sabado' : 'semana');

    $ultima = ultimaEscalaPorColaborador($pdo);
    $noMes  = escalasNoMes($pdo, (int)$escala['mes'], (int)$escala['ano']);

    // colaboradores que marcaram indisponibilidade neste evento
    $stInd = $pdo->prepare("SELECT colaborador_id FROM indisponibilidades WHERE escala_id = ?");
    $stInd->execute([$escalaId]);
    $indispon = array_map('intval', array_column($stInd->fetchAll(), 'colaborador_id'));

    $selecionados = [];   // colaborador_id => nivel
    $jaUsados     = [];
    $faltas       = [];

    $precisaComposicao = ($num >= 3);

    // ---- 1) Garantir composição mínima para eventos de 3+ ----
    if ($precisaComposicao || $exigeL) {
        $lid = ordenarPorPrioridade(candidatos($pdo, 'lider', $tipoDia, $jaUsados, $indispon), $ultima, $noMes);
        if (empty($lid)) {
            if (!$parcial) return ['ok' => false, 'msg' => 'Sem A1 disponível para ' . $escala['evento'] . ' em ' . $escala['data_evento']];
            $faltas[] = 'A1';
        } else {
            $selecionados[$lid[0]] = 'lider';
            $jaUsados[] = $lid[0];
        }
    }

    if ($precisaComposicao) {
        $ple = ordenarPorPrioridade(candidatos($pdo, 'pleno', $tipoDia, $jaUsados, $indispon), $ultima, $noMes);
        if (empty($ple)) {
            if (!$parcial) return ['ok' => false, 'msg' => 'Sem A2 disponível para ' . $escala['evento'] . ' em ' . $escala['data_evento']];
            $faltas[] = 'A2';
        } else {
            $selecionados[$ple[0]] = 'pleno';
            $jaUsados[] = $ple[0];
        }

        $jun = ordenarPorPrioridade(candidatos($pdo, 'junior', $tipoDia, $jaUsados, $indispon), $ultima, $noMes);
        if (empty($jun)) {
            if (!$parcial) return ['ok' => false, 'msg' => 'Sem A3 disponível para ' . $escala['evento'] . ' em ' . $escala['data_evento']];
            $faltas[] = 'A3';
        } else {
            $selecionados[$jun[0]] = 'junior';
            $jaUsados[] = $jun[0];
        }
    }

    // ---- 2) Completar vagas restantes pela prioridade geral ----
    // Regra: em eventos de 1 ou 2 colaboradores que NÃO exigem A1,
    // o nível A1 (lider) não deve ser escalado — só A2 e A3.
    $a1Permitido = $precisaComposicao || $exigeL;
    $niveisPool = $a1Permitido ? ['junior', 'pleno', 'lider'] : ['junior', 'pleno'];

    while (count($selecionados) < $num) {
        $pool = [];
        foreach ($niveisPool as $nv) {
            foreach (candidatos($pdo, $nv, $tipoDia, $jaUsados, $indispon) as $cid) {
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
        if (!$parcial) return ['ok' => false, 'msg' => 'Colaboradores insuficientes para ' . $escala['evento'] . ' em ' . $escala['data_evento']];
        $faltas[] = 'faltam ' . ($num - count($selecionados)) . ' vaga(s)';
    }

    // sem ninguém selecionado: não grava
    if (!$selecionados) {
        return ['ok' => false, 'msg' => 'Nenhum colaborador disponível para ' . $escala['evento'] . ' em ' . $escala['data_evento']];
    }

    // status final: preenchida se completou, aberta se ficou parcial
    $completa = ($faltas === [] && count($selecionados) >= $num);

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
        $pdo->prepare("UPDATE escalas SET status = ? WHERE id = ?")
            ->execute([$completa ? 'preenchida' : 'aberta', $escalaId]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        return ['ok' => false, 'msg' => 'Erro ao gravar: ' . $ex->getMessage()];
    }

    $msg = $completa
        ? 'Escala gerada.'
        : 'Escala parcial para ' . $escala['evento'] . ' em ' . date('d/m', strtotime($escala['data_evento'])) . ' (' . implode(', ', $faltas) . ')';
    return ['ok' => true, 'completa' => $completa, 'msg' => $msg, 'selecionados' => $selecionados];
}

/**
 * Cria as escalas de todos os domingos do ano (Culto de Celebração 17:45).
 */
function criarDomingosDoAno(PDO $pdo, int $ano): int {
    $inicio = new DateTime("$ano-01-01");
    $fim    = new DateTime("$ano-12-31");
    $criadas = 0;

    $st = $pdo->prepare(
        "INSERT IGNORE INTO escalas
         (data_evento, dia, mes, ano, evento, horario_chegada, num_colaboradores, exige_lider, status)
         VALUES (?, ?, ?, ?, 'Culto de Celebração', '17:45:00', 3, 1, 'aberta')"
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

/**
 * Garante que os domingos (Culto de Celebração 17:45) de um mês/ano existam.
 * Retorna o nº de eventos criados.
 */
function criarDomingosDoMes(PDO $pdo, int $mes, int $ano): int {
    $inicio = new DateTime("$ano-$mes-01");
    $fim    = (clone $inicio)->modify('last day of this month');
    $criadas = 0;
    $st = $pdo->prepare(
        "INSERT IGNORE INTO escalas
         (data_evento, dia, mes, ano, evento, horario_chegada, num_colaboradores, exige_lider, status)
         VALUES (?, ?, ?, ?, 'Culto de Celebração', '17:45:00', 3, 1, 'aberta')"
    );
    for ($d = clone $inicio; $d <= $fim; $d->modify('+1 day')) {
        if ((int)$d->format('w') === 0) {
            $st->execute([$d->format('Y-m-d'), (int)$d->format('d'), $mes, $ano]);
            $criadas += $st->rowCount();
        }
    }
    return $criadas;
}

/**
 * Verifica se a escala de um mês/ano já foi gerada automaticamente.
 */
function escalaMensalJaGerada(PDO $pdo, int $mes, int $ano): bool {
    $st = $pdo->prepare("SELECT 1 FROM geracao_mensal WHERE mes=? AND ano=?");
    $st->execute([$mes, $ano]);
    return (bool)$st->fetchColumn();
}

/**
 * Gera automaticamente a escala de um mês inteiro, respeitando indisponibilidades.
 * Marca o mês como gerado (geracao_mensal) e retorna um resumo.
 *
 * $incluirPreenchidas = true reprocessa TODOS os eventos do mês (usado no regerar),
 * inclusive os que já estavam preenchidos. false processa só os abertos (1ª geração).
 */
function gerarEscalaDoMes(PDO $pdo, int $mes, int $ano, bool $incluirPreenchidas = false): array {
    criarDomingosDoMes($pdo, $mes, $ano);

    $filtroStatus = $incluirPreenchidas ? '' : " AND status='aberta'";
    $st = $pdo->prepare(
        "SELECT * FROM escalas
         WHERE mes=? AND ano=?" . $filtroStatus . "
         ORDER BY data_evento, horario_chegada"
    );
    $st->execute([$mes, $ano]);
    $eventos = $st->fetchAll();

    $completas = 0; $parciais = 0; $avisos = [];
    foreach ($eventos as $ev) {
        $r = gerarEscalaEvento($pdo, $ev, true); // modo parcial
        if ($r['ok'] && !empty($r['completa'])) $completas++;
        elseif ($r['ok']) { $parciais++; $avisos[] = $r['msg']; }
        else $avisos[] = $r['msg'];
    }

    // registra que o mês foi gerado (1x)
    $pdo->prepare("INSERT IGNORE INTO geracao_mensal (mes, ano) VALUES (?, ?)")->execute([$mes, $ano]);

    // notifica todos os colaboradores ativos e o admin de que a agenda está montada
    $nomes = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',
              7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
    $msg = 'A agenda de ' . ($nomes[$mes] ?? $mes) . '/' . $ano . ' já está montada. Confira sua escala.';
    try {
        // remove notificações antigas de geração/agenda (evita acúmulo a cada regeração)
        $pdo->exec("DELETE FROM notificacoes WHERE mensagem LIKE 'A agenda de %já está montada%' OR mensagem LIKE 'Escala de % gerada:%'");
        $pdo->prepare(
            "INSERT INTO notificacoes (colaborador_id, para_admin, mensagem)
             SELECT id, 0, ? FROM colaboradores WHERE ativo = 1"
        )->execute([$msg]);
        $resumo = 'Escala de ' . ($nomes[$mes] ?? $mes) . " gerada: $completas completo(s)" . ($parciais ? ", $parciais parcial(is) para ajuste" : '') . '.';
        $pdo->prepare("INSERT INTO notificacoes (colaborador_id, para_admin, mensagem) VALUES (NULL, 1, ?)")
            ->execute([$resumo]);
    } catch (Throwable $ex) { /* tabela de notificações pode não existir em instalações antigas */ }

    return [
        'total'     => count($eventos),
        'completas' => $completas,
        'parciais'  => $parciais,
        'avisos'    => $avisos,
    ];
}

/**
 * Dispara a geração do mês seguinte no 1º acesso do admin a partir do dia 20.
 * Idempotente: só roda uma vez por mês (controle em geracao_mensal).
 * Retorna o resumo se gerou agora, ou null se não era o caso.
 */
function dispararGeracaoAutomatica(PDO $pdo): ?array {
    $hoje = new DateTime('today');
    if ((int)$hoje->format('j') < 20) return null; // só a partir do dia 20

    // mês seguinte
    $prox = (clone $hoje)->modify('first day of next month');
    $mes = (int)$prox->format('n');
    $ano = (int)$prox->format('Y');

    if (escalaMensalJaGerada($pdo, $mes, $ano)) return null;

    return gerarEscalaDoMes($pdo, $mes, $ano);
}

/**
 * Geração manual do mês seguinte (botão do admin), sem a trava do dia 20.
 * Idempotente: se o mês já foi gerado, não gera de novo.
 * Retorna o resumo, ou null se já estava gerado.
 */
function gerarMesSeguinteManual(PDO $pdo): ?array {
    $prox = (new DateTime('today'))->modify('first day of next month');
    $mes = (int)$prox->format('n');
    $ano = (int)$prox->format('Y');

    if (escalaMensalJaGerada($pdo, $mes, $ano)) return null;

    return gerarEscalaDoMes($pdo, $mes, $ano);
}

/**
 * Regera a escala de um mês/ano específico, mesmo que já tenha sido gerado.
 * Usado pelo admin após ajustar indisponibilidades (sobrescreve a escala do mês).
 */
function regerarEscalaDoMes(PDO $pdo, int $mes, int $ano): array {
    $pdo->prepare("DELETE FROM geracao_mensal WHERE mes=? AND ano=?")->execute([$mes, $ano]);
    return gerarEscalaDoMes($pdo, $mes, $ano, true); // reprocessa inclusive os já preenchidos
}
