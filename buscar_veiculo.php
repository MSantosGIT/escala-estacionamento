<?php
// ============================================================
//  ENDPOINT DE BUSCA DE VEÍCULOS (retorna JSON)
//  Usado pela tela busca.php (texto e OCR de placa).
// ============================================================
require_once __DIR__ . '/includes/functions.php';
exigirLogin();

header('Content-Type: application/json; charset=utf-8');

$termo = trim($_GET['q'] ?? '');
$termo = strtoupper($termo);

if (mb_strlen($termo) < 2) {
    echo json_encode(['ok' => true, 'total' => 0, 'veiculos' => []]);
    exit;
}

// normaliza para comparação de placa (sem espaços/hífen)
$placaLimpa = preg_replace('/[^A-Z0-9]/', '', $termo);

$st = db()->prepare(
  "SELECT id, marca, modelo, cor, placa, proprietario, celular, celular2, foto, origem
   FROM veiculos
   WHERE aprovado = 1
     AND ( REPLACE(REPLACE(placa,' ',''),'-','') LIKE :p
        OR UPPER(proprietario) LIKE :n
        OR UPPER(marca) LIKE :m
        OR UPPER(modelo) LIKE :mo )
   ORDER BY
     CASE WHEN REPLACE(REPLACE(placa,' ',''),'-','') = :exata THEN 0 ELSE 1 END,
     marca, modelo
   LIMIT 20"
);
$like = '%' . $placaLimpa . '%';
$nlike = '%' . $termo . '%';
$st->execute([
    ':p'     => $like,
    ':n'     => $nlike,
    ':m'     => $nlike,
    ':mo'    => $nlike,
    ':exata' => $placaLimpa,
]);

$veiculos = $st->fetchAll();
echo json_encode(['ok' => true, 'total' => count($veiculos), 'veiculos' => $veiculos], JSON_UNESCAPED_UNICODE);
