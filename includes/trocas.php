<?php
// ============================================================
//  Lógica de trocas de escala e notificações
// ============================================================
require_once __DIR__ . '/functions.php';

/**
 * Define quais níveis podem trocar entre si.
 * Regra: A1 (lider) só troca com A1. A2 (pleno) e A3 (junior) trocam entre si.
 */
function niveisCompativeis(string $nivel): array {
    if ($nivel === 'lider') return ['lider'];
    return ['pleno', 'junior']; // A2 e A3 trocam entre si
}

/** Cria uma notificação para um colaborador. */
function notificarColaborador(PDO $pdo, int $colaboradorId, string $msg, ?int $trocaId = null): void {
    $pdo->prepare(
        "INSERT INTO notificacoes (colaborador_id, para_admin, mensagem, troca_id)
         VALUES (?, 0, ?, ?)"
    )->execute([$colaboradorId, $msg, $trocaId]);
}

/** Cria uma notificação para o(s) administrador(es). */
function notificarAdmin(PDO $pdo, string $msg, ?int $trocaId = null): void {
    $pdo->prepare(
        "INSERT INTO notificacoes (colaborador_id, para_admin, mensagem, troca_id)
         VALUES (NULL, 1, ?, ?)"
    )->execute([$msg, $trocaId]);
}

/** Notificações não lidas de um colaborador. */
function notificacoesColaborador(PDO $pdo, int $colaboradorId): array {
    $st = $pdo->prepare(
        "SELECT * FROM notificacoes
         WHERE colaborador_id = ? AND lida = 0
         ORDER BY criado_em DESC"
    );
    $st->execute([$colaboradorId]);
    return $st->fetchAll();
}

/** Notificações não lidas do admin. */
function notificacoesAdmin(PDO $pdo): array {
    return $pdo->query(
        "SELECT * FROM notificacoes
         WHERE para_admin = 1 AND lida = 0
         ORDER BY criado_em DESC"
    )->fetchAll();
}

function marcarNotificacaoLida(PDO $pdo, int $id): void {
    $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id = ?")->execute([$id]);
}

/** Descrição curta de um evento para exibir nas telas de troca. */
function descreveEscala(PDO $pdo, ?int $escalaId): ?array {
    if (!$escalaId) return null;
    $st = $pdo->prepare("SELECT * FROM escalas WHERE id = ?");
    $st->execute([$escalaId]);
    return $st->fetch() ?: null;
}

/**
 * Efetiva a troca nas escalas (chamado quando o admin confirma).
 *  - Move o solicitante para o evento alvo (se houver) e o alvo para o evento de origem.
 *  - Se o alvo não estava escalado, ele apenas assume a vaga do solicitante.
 * Retorna [ok, msg].
 */
function efetivarTroca(PDO $pdo, array $tr): array {
    $solId   = (int)$tr['solicitante_id'];
    $alvoId  = (int)$tr['alvo_id'];
    $eOrigem = (int)$tr['escala_origem_id'];
    $eAlvo   = $tr['escala_alvo_id'] !== null ? (int)$tr['escala_alvo_id'] : null;

    // níveis para gravar em nivel_na_escala
    $nivel = function($cid) use ($pdo) {
        $s = $pdo->prepare("SELECT nivel FROM colaboradores WHERE id=?");
        $s->execute([$cid]); return $s->fetchColumn() ?: 'junior';
    };

    $pdo->beginTransaction();
    try {
        // remove o solicitante do evento de origem
        $pdo->prepare("DELETE FROM escala_colaboradores WHERE escala_id=? AND colaborador_id=?")
            ->execute([$eOrigem, $solId]);

        // alvo assume a vaga no evento de origem
        $pdo->prepare(
            "INSERT INTO escala_colaboradores (escala_id, colaborador_id, nivel_na_escala)
             VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE nivel_na_escala = VALUES(nivel_na_escala)"
        )->execute([$eOrigem, $alvoId, $nivel($alvoId)]);

        // se o alvo também estava escalado em outro evento, o solicitante assume o lugar dele
        if ($eAlvo) {
            $pdo->prepare("DELETE FROM escala_colaboradores WHERE escala_id=? AND colaborador_id=?")
                ->execute([$eAlvo, $alvoId]);
            $pdo->prepare(
                "INSERT INTO escala_colaboradores (escala_id, colaborador_id, nivel_na_escala)
                 VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE nivel_na_escala = VALUES(nivel_na_escala)"
            )->execute([$eAlvo, $solId, $nivel($solId)]);
        }

        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        return [false, 'Erro ao efetivar a troca: ' . $ex->getMessage()];
    }
    return [true, 'Troca efetivada.'];
}
