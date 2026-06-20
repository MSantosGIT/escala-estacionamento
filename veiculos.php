<?php
require_once __DIR__ . '/includes/functions.php';
exigirAdmin();
$pdo = db();
$acao = $_GET['acao'] ?? 'listar';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $id    = (int)($_POST['id'] ?? 0);
    $marca = trim($_POST['marca']);
    $modelo= trim($_POST['modelo']);
    $cor   = trim($_POST['cor']);
    $placa = strtoupper(trim($_POST['placa']));
    $prop  = trim($_POST['proprietario']);
    $cel   = trim($_POST['celular']);
    $cel2  = trim($_POST['celular2'] ?? '');

    // upload foto
    $foto = $_POST['foto_atual'] ?? null;
    if (!empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $ehImagem = @getimagesize($_FILES['foto']['tmp_name']) !== false;
        if (in_array($ext, ['jpg','jpeg','png','webp']) && $ehImagem && $_FILES['foto']['size'] <= 4*1024*1024) {
            $nome = 'veic_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/uploads/' . $nome);
            $foto = 'uploads/' . $nome;
        }
    }

    if ($id) {
        $pdo->prepare("UPDATE veiculos SET marca=?,modelo=?,cor=?,placa=?,proprietario=?,celular=?,celular2=?,foto=? WHERE id=?")
            ->execute([$marca,$modelo,$cor,$placa,$prop,$cel,$cel2,$foto,$id]);
        flash('Veículo atualizado.');
    } else {
        $pdo->prepare("INSERT INTO veiculos (marca,modelo,cor,placa,proprietario,celular,celular2,foto) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$marca,$modelo,$cor,$placa,$prop,$cel,$cel2,$foto]);
        flash('Veículo cadastrado.');
    }
    redirect('veiculos.php');
}

if ($acao === 'excluir' && ($id = (int)($_GET['id'] ?? 0))) {
    $pdo->prepare("DELETE FROM veiculos WHERE id=?")->execute([$id]);
    flash('Veículo excluído.');
    redirect('veiculos.php');
}

if ($acao === 'aprovar' && ($id = (int)($_GET['id'] ?? 0))) {
    $pdo->prepare("UPDATE veiculos SET aprovado=1 WHERE id=?")->execute([$id]);
    flash('Veículo aprovado.');
    redirect('veiculos.php');
}

if ($acao === 'rejeitar' && ($id = (int)($_GET['id'] ?? 0))) {
    $pdo->prepare("DELETE FROM veiculos WHERE id=?")->execute([$id]);
    flash('Cadastro rejeitado e removido.');
    redirect('veiculos.php');
}

$editar = null;
if ($acao === 'editar' && ($id = (int)($_GET['id'] ?? 0))) {
    $st = $pdo->prepare("SELECT * FROM veiculos WHERE id=?"); $st->execute([$id]); $editar = $st->fetch();
}

$pendentes = $pdo->query("SELECT * FROM veiculos WHERE aprovado=0 ORDER BY criado_em DESC")->fetchAll();
$lista     = $pdo->query("SELECT * FROM veiculos WHERE aprovado=1 ORDER BY marca,modelo")->fetchAll();

// URL do formulário público
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off' ? 'https' : 'http')
      . '://' . $_SERVER['HTTP_HOST']
      . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$linkPublico = $base . '/cadastro_publico.php';

$titulo = 'Veículos';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Veículos</h1>
<p class="page-sub">Cadastro dos veículos e proprietários.</p>

<div class="flex-between" style="margin-bottom:1.2rem">
  <span class="muted">Cadastre manualmente abaixo, importe uma planilha ou compartilhe o link de autocadastro.</span>
  <a href="importar_veiculos.php" class="btn">📥 Importar CSV</a>
</div>

<div class="card">
  <h2>Link de autocadastro</h2>
  <p class="muted" style="margin-bottom:.7rem">Compartilhe este link para que qualquer pessoa cadastre o próprio veículo. Os envios aparecem abaixo para aprovação.</p>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <input id="linkPub" type="text" readonly value="<?= e($linkPublico) ?>" style="flex:1;min-width:240px">
    <button type="button" class="btn sec" onclick="navigator.clipboard.writeText(document.getElementById('linkPub').value);this.textContent='✓ Copiado'">Copiar link</button>
    <a class="btn" href="<?= e($linkPublico) ?>" target="_blank">Abrir</a>
  </div>
</div>

<?php if ($pendentes): ?>
<div class="card" style="border-color:var(--laranja-4)">
  <h2>Aguardando aprovação <span class="badge warn"><?= count($pendentes) ?></span></h2>
  <table>
    <thead><tr><th>Foto</th><th>Veículo</th><th>Cor</th><th>Placa</th><th>Proprietário</th><th>Celular</th><th>2º Tel.</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pendentes as $v): ?>
      <tr>
        <td><?php if($v['foto']):?><img class="foto-mini" src="<?= e($v['foto']) ?>"><?php else:?><span class="muted">—</span><?php endif;?></td>
        <td><?= e($v['marca'].' '.$v['modelo']) ?></td>
        <td><?= e($v['cor']) ?></td>
        <td><b><?= e($v['placa']) ?></b></td>
        <td><?= e($v['proprietario']) ?></td>
        <td><?= e($v['celular']) ?></td>
        <td><?= e($v['celular2'] ?? '') ?: '<span class="muted">—</span>' ?></td>
        <td class="right" style="white-space:nowrap">
          <a class="btn sm" href="?acao=aprovar&id=<?= $v['id'] ?>">Aprovar</a>
          <a class="btn sm danger" href="?acao=rejeitar&id=<?= $v['id'] ?>" onclick="return confirm('Rejeitar e remover este cadastro?')">Rejeitar</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <h2><?= $editar ? 'Editar veículo' : 'Novo veículo' ?></h2>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
    <input type="hidden" name="id" value="<?= e($editar['id'] ?? '') ?>">
    <input type="hidden" name="foto_atual" value="<?= e($editar['foto'] ?? '') ?>">
    <div class="form-row">
      <div><label>Marca</label><input name="marca" required value="<?= e($editar['marca'] ?? '') ?>"></div>
      <div><label>Modelo</label><input name="modelo" required value="<?= e($editar['modelo'] ?? '') ?>"></div>
      <div><label>Cor</label><input name="cor" required value="<?= e($editar['cor'] ?? '') ?>"></div>
      <div><label>Placa</label><input name="placa" required value="<?= e($editar['placa'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Proprietário</label><input name="proprietario" required value="<?= e($editar['proprietario'] ?? '') ?>"></div>
      <div><label>Celular</label><input name="celular" required value="<?= e($editar['celular'] ?? '') ?>"></div>
      <div><label>2º telefone (opcional)</label><input name="celular2" value="<?= e($editar['celular2'] ?? '') ?>"></div>
      <div><label>Foto</label><input type="file" name="foto" accept="image/*"></div>
    </div>
    <button class="btn"><?= $editar ? 'Salvar alterações' : 'Cadastrar' ?></button>
    <?php if ($editar): ?><a href="veiculos.php" class="btn sec">Cancelar</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <h2>Veículos cadastrados <span class="badge ok"><?= count($lista) ?></span></h2>
  <table>
    <thead><tr><th>Foto</th><th>Veículo</th><th>Cor</th><th>Placa</th><th>Proprietário</th><th>Celular</th><th>2º Tel.</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $v): ?>
      <tr>
        <td><?php if($v['foto']):?><img class="foto-mini" src="<?= e($v['foto']) ?>"><?php else:?><span class="muted">—</span><?php endif;?></td>
        <td><?= e($v['marca'].' '.$v['modelo']) ?><?php if(($v['origem']??'')==='publico'):?> <span class="badge junior">autocadastro</span><?php endif;?></td>
        <td><?= e($v['cor']) ?></td>
        <td><b><?= e($v['placa']) ?></b></td>
        <td><?= e($v['proprietario']) ?></td>
        <td><?= e($v['celular']) ?></td>
        <td><?= e($v['celular2'] ?? '') ?: '<span class="muted">—</span>' ?></td>
        <td class="right" style="white-space:nowrap">
          <a class="btn sm sec" href="?acao=editar&id=<?= $v['id'] ?>">Editar</a>
          <a class="btn sm danger" href="?acao=excluir&id=<?= $v['id'] ?>" onclick="return confirm('Excluir veículo?')">Excluir</a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$lista):?><tr><td colspan="8" class="muted">Nenhum veículo cadastrado.</td></tr><?php endif;?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
