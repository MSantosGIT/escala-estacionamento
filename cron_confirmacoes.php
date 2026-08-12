<?php
// ============================================================
//  CRON — Avisos automáticos de confirmação de escala
//  Rodar 1x por dia. Exemplo de crontab (todo dia às 08:00):
//    0 8 * * * /usr/bin/php /var/www/instituto/public_html/escala/cron_confirmacoes.php
//
//  O que faz:
//   1) 7 dias antes do evento  -> alerta a cada escalado: "confirme sua escala"
//   2) 48h antes do evento     -> alerta aos admins com a lista de quem não confirmou
//
//  A tabela avisos_enviados impede que o mesmo aviso saia duas vezes.
// ============================================================

// segurança: este script só roda pela linha de comando (não pelo navegador)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script deve ser executado apenas pela linha de comando.\n");
}

require_once __DIR__ . '/config/db.php';
$pdo = db();

$hoje = new DateTime('today');
$log = [];

// ------------------------------------------------------------
// 1) AVISO DE 7 DIAS: pedir confirmação aos escalados
// ------------------------------------------------------------
$alvo7 = (clone $hoje)->modify('+7 days')->format('Y-m-d');

$st = $pdo->prepare(
  "SELECT e.id, e.evento, e.data_evento, e.horario_chegada
   FROM escalas e
   LEFT JOIN avisos_enviados a ON a.escala_id = e.id AND a.tipo = 'confirmar_7d'
   WHERE e.data_evento = ? AND a.id IS NULL"
);
$st->execute([$alvo7]);
$eventos7 = $st->fetchAll();

foreach ($eventos7 as $ev) {
    // usuários dos colaboradores escalados que ainda não confirmaram
    $st = $pdo->prepare(
      "SELECT u.id AS usuario_id
       FROM escala_colaboradores ec
       JOIN usuarios u ON u.colaborador_id = ec.colaborador_id
       LEFT JOIN escala_confirmacoes cf
              ON cf.escala_id = ec.escala_id AND cf.colaborador_id = ec.colaborador_id
       WHERE ec.escala_id = ? AND cf.id IS NULL"
    );
    $st->execute([(int)$ev['id']]);
    $usuarios = $st->fetchAll(PDO::FETCH_COLUMN);

    if ($usuarios) {
        $msg = 'Confirme sua escala: ' . $ev['evento'] . ' em '
             . date('d/m', strtotime($ev['data_evento']));
        if (mb_strlen($msg) > 100) $msg = mb_substr($msg, 0, 97) . '...';

        $pdo->prepare("INSERT INTO alertas (mensagem, link, criado_por) VALUES (?, ?, NULL)")
            ->execute([$msg, 'confirmar_escala.php']);
        $alertaId = (int)$pdo->lastInsertId();

        $ins = $pdo->prepare("INSERT INTO alertas_destinatarios (alerta_id, usuario_id) VALUES (?, ?)");
        foreach ($usuarios as $uid) $ins->execute([$alertaId, (int)$uid]);

        $log[] = "[7d] Evento '{$ev['evento']}' ({$ev['data_evento']}): alerta para " . count($usuarios) . " colaborador(es).";
    } else {
        $log[] = "[7d] Evento '{$ev['evento']}': todos já confirmaram, nenhum alerta enviado.";
    }

    // marca como processado mesmo sem destinatários (evita reprocessar todo dia)
    $pdo->prepare("INSERT IGNORE INTO avisos_enviados (escala_id, tipo) VALUES (?, 'confirmar_7d')")
        ->execute([(int)$ev['id']]);
}

// ------------------------------------------------------------
// 2) AVISO DE 48H: listar aos admins quem não confirmou
// ------------------------------------------------------------
$alvo48 = (clone $hoje)->modify('+2 days')->format('Y-m-d');

$st = $pdo->prepare(
  "SELECT e.id, e.evento, e.data_evento
   FROM escalas e
   LEFT JOIN avisos_enviados a ON a.escala_id = e.id AND a.tipo = 'pendentes_48h'
   WHERE e.data_evento = ? AND a.id IS NULL"
);
$st->execute([$alvo48]);
$eventos48 = $st->fetchAll();

foreach ($eventos48 as $ev) {
    // colaboradores escalados que NÃO confirmaram
    $st = $pdo->prepare(
      "SELECT c.nome
       FROM escala_colaboradores ec
       JOIN colaboradores c ON c.id = ec.colaborador_id
       LEFT JOIN escala_confirmacoes cf
              ON cf.escala_id = ec.escala_id AND cf.colaborador_id = ec.colaborador_id
       WHERE ec.escala_id = ? AND cf.id IS NULL
       ORDER BY c.nome"
    );
    $st->execute([(int)$ev['id']]);
    $naoConfirmaram = $st->fetchAll(PDO::FETCH_COLUMN);

    if ($naoConfirmaram) {
        $qtd = count($naoConfirmaram);
        $nomes = implode(', ', $naoConfirmaram);
        $msg = "{$qtd} sem ciente em " . date('d/m', strtotime($ev['data_evento'])) . ": {$nomes}";
        if (mb_strlen($msg) > 100) $msg = mb_substr($msg, 0, 97) . '...';

        // envia a todos os administradores
        $admins = $pdo->query("SELECT id FROM usuarios WHERE tipo='administrador'")->fetchAll(PDO::FETCH_COLUMN);
        if ($admins) {
            $pdo->prepare("INSERT INTO alertas (mensagem, link, criado_por) VALUES (?, ?, NULL)")
                ->execute([$msg, 'confirmar_escala.php']);
            $alertaId = (int)$pdo->lastInsertId();

            $ins = $pdo->prepare("INSERT INTO alertas_destinatarios (alerta_id, usuario_id) VALUES (?, ?)");
            foreach ($admins as $uid) $ins->execute([$alertaId, (int)$uid]);

            $log[] = "[48h] Evento '{$ev['evento']}': {$qtd} pendente(s), alerta para " . count($admins) . " admin(s).";
        }
    } else {
        $log[] = "[48h] Evento '{$ev['evento']}': todos confirmaram.";
    }

    $pdo->prepare("INSERT IGNORE INTO avisos_enviados (escala_id, tipo) VALUES (?, 'pendentes_48h')")
        ->execute([(int)$ev['id']]);
}

// ------------------------------------------------------------
echo '[' . date('Y-m-d H:i:s') . "] Cron de confirmações executado.\n";
if ($log) {
    foreach ($log as $l) echo "  - $l\n";
} else {
    echo "  - Nenhum evento a processar hoje.\n";
}
