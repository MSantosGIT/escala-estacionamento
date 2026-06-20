<?php
require_once __DIR__ . '/includes/functions.php';
exigirAdmin();
$pdo = db();

$relatorio = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    validarCSRF();

    if ($_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        flash('Falha no upload do arquivo.', 'erro');
        redirect('importar_veiculos.php');
    }

    $modo = $_POST['modo'] ?? 'ignorar'; // 'ignorar' ou 'atualizar' duplicatas

    $fh = fopen($_FILES['csv']['tmp_name'], 'r');
    if (!$fh) {
        flash('Não foi possível ler o arquivo.', 'erro');
        redirect('importar_veiculos.php');
    }

    // detecta separador pela primeira linha
    $primeira = fgets($fh);
    rewind($fh);
    $sep = (substr_count($primeira, ';') > substr_count($primeira, ',')) ? ';' : ',';

    $cabecalho = fgetcsv($fh, 0, $sep);
    // normaliza nomes de coluna (maiúsculas, sem acento/espaço)
    $norm = function($s){
        $s = strtoupper(trim($s ?? ''));
        $s = strtr($s, ['Á'=>'A','À'=>'A','Ã'=>'A','Â'=>'A','É'=>'E','Ê'=>'E','Í'=>'I','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ú'=>'U','Ç'=>'C']);
        return preg_replace('/[^A-Z0-9]/', '', $s);
    };
    $cols = array_map($norm, $cabecalho ?: []);
    $idx = fn($nome) => array_search($nome, $cols, true);

    $iMarca = $idx('MARCA');
    $iModelo= $idx('MODELO');
    $iCor   = $idx('COR');
    $iPlaca = $idx('PLACA');
    $iCond  = $idx('CONDUTOR');
    if ($iCond === false) $iCond = $idx('PROPRIETARIO');
    $iCel   = $idx('CELULAR');
    $iCel2  = $idx('CELULAR2');
    if ($iCel2 === false) $iCel2 = $idx('TELEFONE2');
    if ($iCel2 === false) $iCel2 = $idx('CONTATO2');

    if ($iPlaca === false) {
        fclose($fh);
        flash('O arquivo não tem uma coluna PLACA reconhecível.', 'erro');
        redirect('importar_veiculos.php');
    }

    $limpaPlaca = function($p){
        $p = strtoupper(trim($p ?? ''));
        return preg_replace('/[^A-Z0-9]/', '', $p);
    };
    $validaPlaca = fn($p) => (bool)preg_match('/^[A-Z]{3}[0-9][0-9A-Z][0-9]{2}$/', $p);

    $inseridos = $atualizados = $ignorados = $invalidos = 0;
    $linhasInvalidas = [];
    $linha = 1;

    $selPlaca = $pdo->prepare("SELECT id FROM veiculos WHERE REPLACE(REPLACE(placa,' ',''),'-','') = ?");
    $insVe = $pdo->prepare(
      "INSERT INTO veiculos (marca,modelo,cor,placa,proprietario,celular,celular2,origem,aprovado)
       VALUES (?,?,?,?,?,?,?, 'sistema', 1)"
    );
    $updVe = $pdo->prepare(
      "UPDATE veiculos SET marca=?,modelo=?,cor=?,proprietario=?,celular=?,celular2=? WHERE id=?"
    );

    while (($row = fgetcsv($fh, 0, $sep)) !== false) {
        $linha++;
        if (count(array_filter($row, fn($v)=>trim((string)$v)!=='')) === 0) continue; // linha vazia

        $placaRaw = $row[$iPlaca] ?? '';
        $placa = $limpaPlaca($placaRaw);

        if (!$validaPlaca($placa)) {
            $invalidos++;
            if (count($linhasInvalidas) < 15) {
                $linhasInvalidas[] = ['linha'=>$linha, 'placa'=>trim($placaRaw), 'motivo'=>'placa inválida ou vazia'];
            }
            continue;
        }

        $marca = ucwords(strtolower(trim($row[$iMarca] ?? '')));
        $modelo= ucwords(strtolower(trim($row[$iModelo] ?? '')));
        $cor   = ucwords(strtolower(trim($row[$iCor] ?? '')));
        $cond  = trim($row[$iCond] ?? '', " \t\n\r\0\x0B,\"");
        $cel   = trim($row[$iCel] ?? '');
        $cel2  = ($iCel2 !== false) ? trim($row[$iCel2] ?? '') : '';

        // extrai todos os telefones (10-11 dígitos) do campo celular bagunçado
        if (mb_strlen($cel) > 20 || preg_match('/[^0-9()\-\s+]/', $cel)) {
            $fones = [];
            foreach (preg_split('/[^0-9\-]+/', $cel) as $bloco) {
                $d = preg_replace('/\D+/', '', $bloco);
                if (strlen($d) >= 10 && strlen($d) <= 11) $fones[] = $d;
            }
            if (!$fones && preg_match('/(\d[\d\-\s]{8,}\d)/', $cel, $mm)) {
                $fones[] = substr(preg_replace('/\D+/', '', $mm[1]), 0, 11);
            }
            $cel = $fones[0] ?? trim(mb_substr($cel, 0, 20));
            // se não houver coluna dedicada, usa o 2º telefone encontrado
            if ($cel2 === '' && isset($fones[1])) $cel2 = $fones[1];
        }

        if ($marca==='')  $marca  = '—';
        if ($modelo==='') $modelo = '—';
        if ($cor==='')    $cor    = '—';
        if ($cond==='')   $cond   = 'Não informado';

        // limita ao tamanho de cada coluna (evita "MySQL server has gone away")
        $marca  = mb_substr($marca,  0, 60);
        $modelo = mb_substr($modelo, 0, 60);
        $cor    = mb_substr($cor,    0, 40);
        $cond   = mb_substr($cond,   0, 120);
        $cel    = mb_substr($cel,    0, 20);
        $cel2   = mb_substr($cel2,   0, 20);

        $selPlaca->execute([$placa]);
        $existe = $selPlaca->fetchColumn();

        try {
            if ($existe) {
                if ($modo === 'atualizar') {
                    $updVe->execute([$marca,$modelo,$cor,$cond,$cel,$cel2,$existe]);
                    $atualizados++;
                } else {
                    $ignorados++;
                }
            } else {
                $insVe->execute([$marca,$modelo,$cor,$placa,$cond,$cel,$cel2]);
                $inseridos++;
            }
        } catch (Throwable $ex) {
            $invalidos++;
            if (count($linhasInvalidas) < 15) {
                $linhasInvalidas[] = ['linha'=>$linha, 'placa'=>$placa, 'motivo'=>'erro ao gravar (possível duplicata)'];
            }
        }
    }
    fclose($fh);

    $relatorio = compact('inseridos','atualizados','ignorados','invalidos','linhasInvalidas');
}

$titulo = 'Importar Veículos';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Importar veículos (CSV)</h1>
<p class="page-sub">Carregue uma planilha para cadastrar vários veículos de uma vez.</p>

<?php if ($relatorio): ?>
<div class="card">
  <h2>Resultado da importação</h2>
  <div class="grid cols-4" style="margin-bottom:1rem">
    <div class="stat"><div class="n"><?= $relatorio['inseridos'] ?></div><div class="l">Inseridos</div></div>
    <div class="stat"><div class="n"><?= $relatorio['atualizados'] ?></div><div class="l">Atualizados</div></div>
    <div class="stat"><div class="n"><?= $relatorio['ignorados'] ?></div><div class="l">Ignorados (já existiam)</div></div>
    <div class="stat"><div class="n"><?= $relatorio['invalidos'] ?></div><div class="l">Sem placa válida</div></div>
  </div>
  <?php if ($relatorio['linhasInvalidas']): ?>
    <p class="muted">Linhas não importadas (placa inválida ou ausente):</p>
    <table>
      <thead><tr><th>Linha</th><th>Placa lida</th><th>Motivo</th></tr></thead>
      <tbody>
      <?php foreach ($relatorio['linhasInvalidas'] as $li): ?>
        <tr><td><?= (int)$li['linha'] ?></td><td><?= e($li['placa']) ?: '<span class="muted">vazia</span>' ?></td><td><?= e($li['motivo']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <a href="veiculos.php" class="btn" style="margin-top:1rem">Ver veículos cadastrados</a>
</div>
<?php endif; ?>

<div class="card">
  <h2>Enviar arquivo</h2>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
    <div class="form-row">
      <div><label>Arquivo CSV</label><input type="file" name="csv" accept=".csv,text/csv" required></div>
      <div>
        <label>Se a placa já existir</label>
        <select name="modo">
          <option value="ignorar">Ignorar (manter o cadastro atual)</option>
          <option value="atualizar">Atualizar com os dados do arquivo</option>
        </select>
      </div>
    </div>
    <button class="btn">Importar</button>
  </form>
</div>

<div class="card">
  <h2>Formato esperado</h2>
  <p class="muted">A primeira linha deve conter os nomes das colunas. São reconhecidas (em qualquer ordem):</p>
  <p><b>MARCA</b>, <b>MODELO</b>, <b>COR</b>, <b>PLACA</b>, <b>CONDUTOR</b> (ou PROPRIETARIO), <b>CELULAR</b>.</p>
  <p class="muted">Colunas extras (como índice ou data de cadastro) são ignoradas. Placas são normalizadas
  automaticamente (espaços e hífens removidos) e validadas no padrão brasileiro (ABC1234 / ABC1D23).
  Linhas sem placa válida não são importadas e aparecem no relatório.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
