<?php
// ============================================================
//  CRON (opcional) — preenche automaticamente escalas abertas.
//  Agende ex.: 0 6 * * * php /caminho/cron/gerar_escalas.php
// ============================================================
require_once __DIR__ . '/../includes/escala_engine.php';
$pdo = db();
$st = $pdo->query("SELECT * FROM escalas WHERE status='aberta' AND data_evento>=CURDATE() ORDER BY data_evento");
$ok=0;
foreach ($st as $esc) {
    $r = gerarEscalaEvento($pdo, $esc);
    echo ($r['ok']?'[OK] ':'[--] ').$r['msg'].PHP_EOL;
    if ($r['ok']) $ok++;
}
echo "Total geradas: $ok".PHP_EOL;
