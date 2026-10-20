<?php
// ============================================================
//  MOTOR DE GERAÇÃO DE ESCALA
//  Regras:
//   1. Eventos com 3+ colaboradores exigem: 1 A1, 1+ A2, 1+ A3
//   2. Eventos com 1 ou 2 colaboradores NÃO escalam A1 (só A2 e A3),
//      exceto quando o evento marca "exige A1".
//   3. Limite de 1 evento por colaborador no mês (regra rígida — quem já
//      foi escalado no mês some da fila até o próximo mês)
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
 * $excetoEscalaId (opcional): ignora a contagem do próprio evento sendo
 * gerado agora, para não travar a regeneração de um evento que a pessoa
 * já estava escalada nele mesmo.
 */
function escalasNoMes(PDO $pdo, int $mes, int $ano, ?int $excetoEscalaId = null): array {
    $sql = "SELECT ec.colaborador_id, COUNT(*) AS qtd
            FROM escala_colaboradores ec
            JOIN escalas es ON es.id = ec.escala_id
            WHERE es.mes = ? AND es.ano = ?";
    $params = [$mes, $ano];
    if ($excetoEscalaId !== null) {
        $sql .= " AND es.id <> ?";
        $params[] = $excetoEscalaId;
    }
    $sql .= " GROUP BY ec.colaborador_id";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $out = [];
    foreach ($st as $r) {
        $out[(int)$r['colaborador_id']] = (int)$r['qtd'];
    }
    return $out;
}

/**
 * Quantos eventos cada colaborador já tem no SEMESTRE/ano informados.
 * Semestre = 1 (jan–jun) ou 2 (jul–dez)
 */
function escalasNoSemestre(PDO $pdo, int $mes, int $ano, ?int $excetoEscalaId = null): array {
    $sem = ($mes <= 6) ? 1 : 2;
    $iniMes = ($sem === 1) ? 1 : 7;
    $fimMes = ($sem === 1) ? 6 : 12;
    $sql = "SELECT ec.colaborador_id, COUNT(*) AS qtd
            FROM escala_colaboradores ec
            JOIN escalas es ON es.id = ec.escala_id
            WHERE es.ano = ? AND es.mes BETWEEN ? AND ?";
    $params = [$ano, $iniMes, $fimMes];
    if ($excetoEscalaId !== null) {
        $sql .= " AND es.id <> ?";
        $params[] = $excetoEscalaId;
    }
    $sql .= " GROUP BY ec.colaborador_id";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $out = [];
    foreach ($st as $r) {
        $out[(int)$r['colaborador_id']] = (int)$r['qtd'];
    }
    return $out;
}

function escalasNoAno(PDO $pdo, int $ano, ?int $excetoEscalaId = null): array {
    $sql = "SELECT ec.colaborador_id, COUNT(*) AS qtd
            FROM escala_colaboradores ec
            JOIN escalas es ON es.id = ec.escala_id
            WHERE es.ano = ?";
    $params = [$ano];
    if ($excetoEscalaId !== null) {
        $sql .= " AND es.id <> ?";
        $params[] = $excetoEscalaId;
    }
    $sql .= " GROUP BY ec.colaborador_id";
    $st = $pdo->prepare($sql);
    $st->execute($params);
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
 * Critério:
 * 1) menos eventos no mês
 * 2) menos eventos no semestre
 * 3) menos eventos no ano
 * 4) mais tempo sem ser escalado (última escala mais antiga)
 * 5) ordem_padrao ASC, NULLS LAST
 * 6) nome ASC (case-insensitive)
 * 7) id ASC (fallback determinístico)
 */
function ordenarPorPrioridade(
    array $ids,
    array $ultima,      // id => 'Y-m-d'
    array $noMes,       // id => qtd
    array $noSem,       // id => qtd
    array $noAno,       // id => qtd
    array $ordemPadrao, // id => int|null
    array $nomes        // id => string
): array {
    usort($ids, function ($a, $b) use ($ultima, $noMes, $noSem, $noAno, $ordemPadrao, $nomes) {
        $ma = $noMes[$a] ?? 0;
        $mb = $noMes[$b] ?? 0;
        if ($ma !== $mb) {
            return $ma <=> $mb; // 1) menos no mês
        }

        $sa = $noSem[$a] ?? 0;
        $sb = $noSem[$b] ?? 0;
        if ($sa !== $sb) {
            return $sa <=> $sb; // 2) menos no semestre
        }

        $ya = $noAno[$a] ?? 0;
        $yb = $noAno[$b] ?? 0;
        if ($ya !== $yb) {
            return $ya <=> $yb; // 3) menos no ano
        }

        $ua = $ultima[$a] ?? '1900-01-01';
        $ub = $ultima[$b] ?? '1900-01-01';
        if ($ua !== $ub) {
            return strcmp($ua, $ub); // 4) mais tempo sem escalar (mais antigo primeiro)
        }

        // 5) ordem padrão do colaborador (NULLS LAST)
        $opa = $ordemPadrao[$a] ?? null;
        $opb = $ordemPadrao[$b] ?? null;
        $naNull = ($opa === null);
        $nbNull = ($opb === null);
        if ($naNull !== $nbNull) {
            return $naNull <=> $nbNull; // null vai por último
        }
        if ($opa !== null && $opb !== null && $opa !== $opb) {
            return $opa <=> $opb; // menor número = vem antes
        }

        // 6) nome ASC (case-insensitive)
        $na = $nomes[$a] ?? '';
        $nb = $nomes[$b] ?? '';
        $cmp = strcasecmp($na, $nb);
        if ($cmp !== 0) {
            return $cmp;
        }

        // 7) fallback por ID
        return $a <=> $b;
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
    if ($diaSem === 0) {
        $hora = (int)substr($escala['horario_chegada'] ?? '17:00', 0, 2);
        $tipoDia  = $hora < 12 ? 'domingo_manha' : 'domingo_noite';
    } elseif ($diaSem === 6) {
        $tipoDia  = 'sabado';
    } else {
        $tipoDia  = 'semana';
    }

    $ultima = ultimaEscalaPorColaborador($pdo);
    // conta eventos do mês SEM contar o próprio evento sendo gerado agora
    // (evita travar a regeneração de um evento em que a pessoa já estava)
    $noMes  = escalasNoMes($pdo, (int)$escala['mes'], (int)$escala['ano'], $escalaId);
    $noSem  = escalasNoSemestre($pdo, (int)$escala['mes'], (int)$escala['ano'], $escalaId);
    $noAno  = escalasNoAno($pdo, (int)$escala['ano'], $escalaId);

    // mapas para desempate determinístico
    $opMap = [];
    $nmMap = [];
    foreach ($pdo->query("SELECT id, ordem_padrao, nome FROM colaboradores WHERE ativo=1") as $rc) {
        $opMap[(int)$rc['id']] = ($rc['ordem_padrao'] === null ? null : (int)$rc['ordem_padrao']);
        $nmMap[(int)$rc['id']] = (string)$rc['nome'];
    }

    // quem já tem 1+ evento no mês fica de fora desta rodada (vai para o
    // final da fila): impede que a mesma pessoa apareça 2x no mês.
    $jaNoMes = array_keys(array_filter($noMes, fn($qtd) => $qtd >= 1));

    // colaboradores que marcaram indisponibilidade neste evento
    $stInd = $pdo->prepare("SELECT colaborador_id FROM indisponibilidades WHERE escala_id = ?");
    $stInd->execute([$escalaId]);
    $indispon = array_map('intval', array_column($stInd->fetchAll(), 'colaborador_id'));

    // ainda há eventos abertos no mês? Se sim, podemos permitir 2ª escala em caso excepcional
    $stAbertos = $pdo->prepare("SELECT COUNT(*) FROM escalas WHERE mes=? AND ano=? AND status='aberta' AND id!=?");
    $stAbertos->execute([(int)$escala['mes'], (int)$escala['ano'], $escalaId]);
    $eventosAbertosRestantes = (int)$stAbertos->fetchColumn();
    $allowSegunda = ($eventosAbertosRestantes > 0);

    $selecionados = [];   // colaborador_id => nivel
    $jaUsados     = $jaNoMes;   // começa já excluindo quem foi escalado em outro evento do mês
    $faltas       = [];

    $precisaComposicao = ($num >= 3);

    // ---- 1) Garantir composição mínima para eventos de 3+ ----
    if ($precisaComposicao || $exigeL) {
        $lid = ordenarPorPrioridade(candidatos($pdo, 'lider', $tipoDia, $jaUsados, $indispon), $ultima, $noMes, $noSem, $noAno, $opMap, $nmMap);
        if (empty($lid) && $allowSegunda) {
            $jaUsadosRelax = array_keys(array_filter($noMes, fn($qtd) => $qtd >= 2));
            $cand = candidatos($pdo, 'lider', $tipoDia, $jaUsadosRelax, $indispon);
            $cand1 = array_filter($cand, fn($cid) => (($noMes[$cid] ?? 0) === 1));
            if ($cand1) {
                $lid = ordenarPorPrioridade(array_values($cand1), $ultima, $noMes, $noSem, $noAno, $opMap, $nmMap);
            }
        }
        if (empty($lid)) {
            if (!$parcial) return ['ok' => false, 'msg' => 'Sem A1 disponível para ' . $escala['evento'] . ' em ' . $escala['data_evento']];
            $faltas[] = 'A1';
        } else {
            $selecionados[$lid[0]] = 'lider';
            $jaUsados[] = $lid[0];
        }
    }

    if ($precisaComposicao) {
        $ple = ordenarPorPrioridade(candidatos($pdo, 'pleno', $tipoDia, $jaUsados, $indispon), $ultima, $noMes, $noSem, $noAno, $opMap, $nmMap);
        if (empty($ple) && $allowSegunda) {
            $jaUsadosRelax = array_keys(array_filter($noMes, fn($qtd) => $qtd >= 2));
            $cand = candidatos($pdo, 'pleno', $tipoDia, $jaUsadosRelax, $indispon);
            $cand1 = array_filter($cand, fn($cid) => (($noMes[$cid] ?? 0) === 1));
            if ($cand1) {
                $ple = ordenarPorPrioridade(array_values($cand1), $ultima, $noMes, $noSem, $noAno, $opMap, $nmMap);
            }
        }
        if (empty($ple)) {
            if (!$parcial) return ['ok' => false, 'msg' => 'Sem A2 disponível para ' . $escala['evento'] . ' em ' . $escala['data_evento']];
            $faltas[] = 'A2';
        } else {
            $selecionados[$ple[0]] = 'pleno';
            $jaUsados[] = $ple[0];
        }

        $jun = ordenarPorPrioridade(candidatos($pdo, 'junior', $tipoDia, $jaUsados, $indispon), $ultima, $noMes, $noSem, $noAno, $opMap, $nmMap);
        if (empty($jun) && $allowSegunda) {
            $jaUsadosRelax = array_keys(array_filter($noMes, fn($qtd) => $qtd >= 2));
            $cand = candidatos($pdo, 'junior', $tipoDia, $jaUsadosRelax, $indispon);
            $cand1 = array_filter($cand, fn($cid) => (($noMes[$cid] ?? 0) === 1));
            if ($cand1) {
                $jun = ordenarPorPrioridade(array_values($cand1), $ultima, $noMes, $noSem, $noAno, $opMap, $nmMap);
            }
        }
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
        $ord = ordenarPorPrioridade(array_keys($pool), $ultima, $noMes, $noSem, $noAno, $opMap, $nmMap);
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
    $filtroStatus = $incluirPreenchidas ? '' : " AND status='aberta'";
    $st = $pdo->prepare(
        "SELECT * FROM escalas
         WHERE mes=? AND ano=?" . $filtroStatus . "
         ORDER BY data_evento, horario_chegada"
    );
    $st->execute([$mes, $ano]);
    $eventos = $st->fetchAll();

    $completas = 0; $parciais = 0; $avisos = [];
    $chkManual = $pdo->prepare("SELECT COUNT(*) FROM escala_colaboradores WHERE escala_id=? AND posicao IS NOT NULL");
    foreach ($eventos as $ev) {
        $eid = (int)$ev['id'];
        if ($incluirPreenchidas) {
            // Preserva eventos com ordem manual por evento (posicao definida)
            $chkManual->execute([$eid]);
            if ((int)$chkManual->fetchColumn() > 0) {
                continue; // não sobrescreve ajustes manuais
            }
        }
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
 * Dispara a geração do mês seguinte no 1º acesso do admin a partir do dia 22.
 * Idempotente: só roda uma vez por mês (controle em geracao_mensal).
 * Retorna o resumo se gerou agora, ou null se não era o caso.
 */
function dispararGeracaoAutomatica(PDO $pdo): ?array {
    $hoje = new DateTime('today');
    if ((int)$hoje->format('j') < 22) return null; // só a partir do dia 22

    // mês seguinte
    $prox = (clone $hoje)->modify('first day of next month');
    $mes = (int)$prox->format('n');
    $ano = (int)$prox->format('Y');

    if (escalaMensalJaGerada($pdo, $mes, $ano)) return null;

    return gerarEscalaDoMes($pdo, $mes, $ano);
}

/**
 * Geração manual do mês seguinte (botão do admin), sem a trava do dia 22.
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
