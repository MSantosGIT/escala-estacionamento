<?php
require_once __DIR__ . '/includes/functions.php';
exigirAdmin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    if (($_POST['op'] ?? '')==='criar') {
        $login = trim($_POST['login']);
        $existe = $pdo->prepare("SELECT 1 FROM usuarios WHERE login=?"); $existe->execute([$login]);
        if ($existe->fetch()) { flash('Login já existe.','erro'); redirect('usuarios.php'); }
        $colab = $_POST['colaborador_id'] ?: null;
        $pdo->prepare("INSERT INTO usuarios (nome,login,senha,tipo,colaborador_id) VALUES (?,?,?,?,?)")
            ->execute([trim($_POST['nome']),$login,password_hash($_POST['senha'],PASSWORD_BCRYPT),$_POST['tipo'],$colab]);
        flash('Usuário criado.');
    }
    if (($_POST['op'] ?? '')==='editar') {
        $id = (int)$_POST['id'];
        $login = trim($_POST['login']);
        // confere se o login já é usado por outro usuário
        $existe = $pdo->prepare("SELECT 1 FROM usuarios WHERE login=? AND id<>?");
        $existe->execute([$login, $id]);
        if ($existe->fetch()) {
            flash('Já existe outro usuário com este login.', 'erro');
        } else {
            $colab = $_POST['colaborador_id'] ?: null;
            $pdo->prepare("UPDATE usuarios SET nome=?, login=?, tipo=?, colaborador_id=? WHERE id=?")
                ->execute([trim($_POST['nome']), $login, $_POST['tipo'], $colab, $id]);
            flash('Dados do usuário atualizados.');
        }
    }
    if (($_POST['op'] ?? '')==='excluir') {
        $id=(int)$_POST['id'];
        if ($id != usuario()['id'])
            $pdo->prepare("DELETE FROM usuarios WHERE id=?")->execute([$id]);
        flash('Usuário removido.');
    }
    if (($_POST['op'] ?? '')==='resetar_senha') {
        $id   = (int)$_POST['id'];
        $nova = $_POST['nova'] ?? '';
        if (strlen($nova) < 6) {
            flash('A nova senha precisa ter pelo menos 6 caracteres.', 'erro');
        } else {
            $hash = password_hash($nova, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE usuarios SET senha=? WHERE id=?")->execute([$hash, $id]);
            flash('Senha redefinida com sucesso.');
        }
    }
    redirect('usuarios.php');
}

// carrega o usuário para edição (se houver)
$editar = null;
if (isset($_GET['editar'])) {
    $st = $pdo->prepare("SELECT * FROM usuarios WHERE id=?");
    $st->execute([(int)$_GET['editar']]);
    $editar = $st->fetch();
}

$colabs = $pdo->query("SELECT id,nome FROM colaboradores WHERE ativo=1 ORDER BY FIELD(nivel,'lider','pleno','junior'), nome")->fetchAll();
$busca = trim($_GET['busca'] ?? '');
if ($busca !== '') {
    $termo = '%' . $busca . '%';
    $st = $pdo->prepare(
      "SELECT u.*, c.nome AS colab_nome FROM usuarios u
       LEFT JOIN colaboradores c ON c.id=u.colaborador_id
       WHERE u.nome LIKE ? OR u.login LIKE ?
       ORDER BY u.tipo,u.nome"
    );
    $st->execute([$termo, $termo]);
    $lista = $st->fetchAll();
} else {
    $lista = $pdo->query("SELECT u.*, c.nome AS colab_nome FROM usuarios u LEFT JOIN colaboradores c ON c.id=u.colaborador_id ORDER BY u.tipo,u.nome")->fetchAll();
}
$titulo = 'Usuários';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Usuários do sistema</h1>
<p class="page-sub">Administradores e colaboradores com acesso.</p>

<div class="acoes-topo">
  <button type="button" class="btn-toggle <?= $editar?'ativo':'' ?>" data-alvo="card-novo-usr">
    ➕ <?= $editar ? 'Editar usuário' : 'Novo usuário' ?>
  </button>
</div>

<div id="card-novo-usr" class="card card-recolhivel <?= $editar?'aberto':'' ?>">
  <h2><?= $editar ? 'Editar usuário' : 'Novo usuário' ?></h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
    <input type="hidden" name="op" value="<?= $editar?'editar':'criar' ?>">
    <?php if ($editar): ?><input type="hidden" name="id" value="<?= (int)$editar['id'] ?>"><?php endif; ?>
    <div class="form-row">
      <div><label>Nome</label><input name="nome" value="<?= e($editar['nome'] ?? '') ?>" required></div>
      <div><label>Login</label><input name="login" value="<?= e($editar['login'] ?? '') ?>" required></div>
      <?php if (!$editar): ?>
      <div><label>Senha</label><input type="password" name="senha" required minlength="6"></div>
      <?php endif; ?>
    </div>
    <div class="form-row">
      <div><label>Tipo</label>
        <select name="tipo">
          <option value="colaborador" <?= ($editar['tipo'] ?? '')==='colaborador'?'selected':'' ?>>Colaborador</option>
          <option value="administrador" <?= ($editar['tipo'] ?? '')==='administrador'?'selected':'' ?>>Administrador</option>
        </select>
      </div>
      <div><label>Vincular a colaborador (opcional)</label>
        <select name="colaborador_id">
          <option value="">—</option>
          <?php foreach ($colabs as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($editar['colaborador_id'] ?? '')==$c['id']?'selected':'' ?>><?= e($c['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php if ($editar): ?>
      <p class="muted" style="font-size:.85rem;margin-bottom:.8rem">
        A senha não é alterada por aqui. Use o botão <b>🔑 Resetar senha</b> na lista abaixo para definir uma nova.
      </p>
    <?php endif; ?>
    <button class="btn"><?= $editar ? 'Salvar alterações' : 'Criar usuário' ?></button>
    <?php if ($editar): ?>
      <a href="usuarios.php" class="btn sec">Cancelar</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="flex-between" style="margin-bottom:1rem">
    <h2 style="margin:0">Usuários cadastrados <span class="badge ok"><?= count($lista) ?></span></h2>
    <form method="get" style="display:flex;gap:.4rem;align-items:center">
      <input type="text" name="busca" value="<?= e($busca) ?>" placeholder="🔍 Nome ou login" style="min-width:220px">
      <button class="btn sm">Buscar</button>
      <?php if ($busca !== ''): ?><a class="btn sm sec" href="usuarios.php">Limpar</a><?php endif; ?>
    </form>
  </div>
  <?php if ($busca !== ''): ?>
    <p class="muted" style="margin-top:-.5rem;margin-bottom:.8rem">Resultados para "<?= e($busca) ?>" — <?= count($lista) ?> encontrado(s).</p>
  <?php endif; ?>
  <table>
    <thead><tr><th>Nome</th><th>Login</th><th>Tipo</th><th>Colaborador</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $u): ?>
      <tr>
        <td><?= e($u['nome']) ?></td>
        <td><?= e($u['login']) ?></td>
        <td><span class="badge <?= $u['tipo']==='administrador'?'lider':'junior' ?>"><?= ucfirst($u['tipo']) ?></span></td>
        <td><?= e($u['colab_nome'] ?? '—') ?></td>
        <td class="right">
          <a class="btn sm sec" href="usuarios.php?editar=<?= $u['id'] ?>" title="Editar dados">✏️</a>
          <form method="post" style="display:inline" onsubmit="return prepararResetSenha(this)">
            <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
            <input type="hidden" name="op" value="resetar_senha">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <input type="hidden" name="nova" value="">
            <button class="btn sm sec" data-nome="<?= e($u['nome']) ?>">🔑 Resetar senha</button>
          </form>
          <?php if ($u['id'] != usuario()['id']): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Remover usuário?')">
            <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
            <input type="hidden" name="op" value="excluir">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button class="btn sm danger">Remover</button>
          </form>
          <?php else: ?><span class="muted">você</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
function prepararResetSenha(form){
  const nome = form.querySelector('button').dataset.nome || 'este usuário';
  const nova = prompt('Definir nova senha para "' + nome + '"\n\n(mínimo 6 caracteres)', '');
  if (nova === null) return false;           // cancelado
  if (nova.length < 6) {
    alert('A senha precisa ter ao menos 6 caracteres.');
    return false;
  }
  if (!confirm('Confirmar redefinição da senha de "' + nome + '"?')) return false;
  form.querySelector('input[name="nova"]').value = nova;
  return true;
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
