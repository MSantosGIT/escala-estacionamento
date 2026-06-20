<?php
require_once __DIR__ . '/includes/functions.php';
exigirAdmin();
$pdo = db();

$acao = $_GET['acao'] ?? 'listar';

// ---- salvar ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $id      = (int)($_POST['id'] ?? 0);
    $nome    = trim($_POST['nome']);
    $cel     = trim($_POST['celular']);
    $nivel   = $_POST['nivel'];
    $semana  = isset($_POST['trabalha_semana']) ? 1 : 0;
    $sabado  = isset($_POST['trabalha_sabado']) ? 1 : 0;
    $domingo = isset($_POST['trabalha_domingo']) ? 1 : 0;
    $crLogin = trim($_POST['login'] ?? '');
    $crSenha = $_POST['senha'] ?? '';

    if ($id) {
        $pdo->prepare("UPDATE colaboradores SET nome=?,celular=?,nivel=?,trabalha_semana=?,trabalha_sabado=?,trabalha_domingo=? WHERE id=?")
            ->execute([$nome,$cel,$nivel,$semana,$sabado,$domingo,$id]);
        flash('Colaborador atualizado.');
    } else {
        $pdo->prepare("INSERT INTO colaboradores (nome,celular,nivel,trabalha_semana,trabalha_sabado,trabalha_domingo) VALUES (?,?,?,?,?,?)")
            ->execute([$nome,$cel,$nivel,$semana,$sabado,$domingo]);
        $cid = $pdo->lastInsertId();
        // cria login de acesso (tipo colaborador) se informado
        if ($crLogin && $crSenha) {
            $pdo->prepare("INSERT INTO usuarios (nome,login,senha,tipo,colaborador_id) VALUES (?,?,?, 'colaborador', ?)")
                ->execute([$nome,$crLogin,password_hash($crSenha,PASSWORD_BCRYPT),$cid]);
        }
        flash('Colaborador cadastrado.');
    }
    redirect('colaboradores.php');
}

// ---- excluir ----
if ($acao === 'excluir' && ($id = (int)($_GET['id'] ?? 0))) {
    $pdo->prepare("UPDATE colaboradores SET ativo=0 WHERE id=?")->execute([$id]);
    flash('Colaborador desativado.');
    redirect('colaboradores.php');
}

$editar = null;
if ($acao === 'editar' && ($id = (int)($_GET['id'] ?? 0))) {
    $st = $pdo->prepare("SELECT * FROM colaboradores WHERE id=?");
    $st->execute([$id]);
    $editar = $st->fetch();
}

$lista = $pdo->query("SELECT * FROM colaboradores WHERE ativo=1 ORDER BY FIELD(nivel,'lider','pleno','junior'), nome")->fetchAll();
$titulo = 'Colaboradores';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Colaboradores</h1>
<p class="page-sub">Cadastro da equipe de apoio ao estacionamento.</p>

<div class="acoes-topo">
  <button type="button" class="btn-toggle <?= $editar?'ativo':'' ?>" data-alvo="card-novo-colab">
    ➕ <?= $editar ? 'Editar colaborador' : 'Novo colaborador' ?>
  </button>
</div>

<div id="card-novo-colab" class="card card-recolhivel <?= $editar?'aberto':'' ?>">
  <h2><?= $editar ? 'Editar colaborador' : 'Novo colaborador' ?></h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
    <input type="hidden" name="id" value="<?= e($editar['id'] ?? '') ?>">
    <div class="form-row">
      <div><label>Nome</label><input name="nome" required value="<?= e($editar['nome'] ?? '') ?>"></div>
      <div><label>Celular</label><input name="celular" required value="<?= e($editar['celular'] ?? '') ?>"></div>
      <div>
        <label>Nível de experiência</label>
        <select name="nivel">
          <?php foreach (['lider','pleno','junior'] as $n): ?>
            <option value="<?= $n ?>" <?= (($editar['nivel'] ?? '')===$n)?'selected':'' ?>><?= nivelLabel($n) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="check"><input type="checkbox" name="trabalha_semana" id="ts" <?= (!$editar||$editar['trabalha_semana'])?'checked':'' ?>><label for="ts" style="margin:0">Dias de Semana</label></div>
      <div class="check"><input type="checkbox" name="trabalha_sabado" id="tsa" <?= (!$editar||$editar['trabalha_sabado'])?'checked':'' ?>><label for="tsa" style="margin:0">Sábado</label></div>
      <div class="check"><input type="checkbox" name="trabalha_domingo" id="td" <?= (!$editar||$editar['trabalha_domingo'])?'checked':'' ?>><label for="td" style="margin:0">Domingo</label></div>
    </div>
    <?php if (!$editar): ?>
    <div class="form-row">
      <div><label>Login de acesso (opcional)</label><input name="login"></div>
      <div><label>Senha (opcional)</label><input type="password" name="senha"></div>
    </div>
    <?php endif; ?>
    <button class="btn"><?= $editar ? 'Salvar alterações' : 'Cadastrar' ?></button>
    <?php if ($editar): ?><a href="colaboradores.php" class="btn sec">Cancelar</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <h2>Equipe cadastrada <span class="badge ok"><?= count($lista) ?></span></h2>
  <table>
    <thead><tr><th>Nome</th><th>Celular</th><th>Nível</th><th>Semana</th><th>Sáb</th><th>Dom</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $c): ?>
      <tr>
        <td><?= e($c['nome']) ?></td>
        <td><?= e($c['celular']) ?></td>
        <td><span class="badge <?= $c['nivel'] ?>"><?= nivelLabel($c['nivel']) ?></span></td>
        <td><?= $c['trabalha_semana']?'✓':'—' ?></td>
        <td><?= $c['trabalha_sabado']?'✓':'—' ?></td>
        <td><?= $c['trabalha_domingo']?'✓':'—' ?></td>
        <td class="right">
          <a class="btn sm sec" href="?acao=editar&id=<?= $c['id'] ?>">Editar</a>
          <a class="btn sm danger" href="?acao=excluir&id=<?= $c['id'] ?>" onclick="return confirm('Desativar este colaborador?')">Remover</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
