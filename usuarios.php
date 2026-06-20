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
    if (($_POST['op'] ?? '')==='excluir') {
        $id=(int)$_POST['id'];
        if ($id != usuario()['id'])
            $pdo->prepare("DELETE FROM usuarios WHERE id=?")->execute([$id]);
        flash('Usuário removido.');
    }
    redirect('usuarios.php');
}

$colabs = $pdo->query("SELECT id,nome FROM colaboradores WHERE ativo=1 ORDER BY nome")->fetchAll();
$lista  = $pdo->query("SELECT u.*, c.nome AS colab_nome FROM usuarios u LEFT JOIN colaboradores c ON c.id=u.colaborador_id ORDER BY u.tipo,u.nome")->fetchAll();
$titulo = 'Usuários';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Usuários do sistema</h1>
<p class="page-sub">Administradores e colaboradores com acesso.</p>

<div class="card">
  <h2>Novo usuário</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
    <input type="hidden" name="op" value="criar">
    <div class="form-row">
      <div><label>Nome</label><input name="nome" required></div>
      <div><label>Login</label><input name="login" required></div>
      <div><label>Senha</label><input type="password" name="senha" required></div>
    </div>
    <div class="form-row">
      <div><label>Tipo</label>
        <select name="tipo">
          <option value="colaborador">Colaborador</option>
          <option value="administrador">Administrador</option>
        </select>
      </div>
      <div><label>Vincular a colaborador (opcional)</label>
        <select name="colaborador_id">
          <option value="">—</option>
          <?php foreach ($colabs as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['nome']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <button class="btn">Criar usuário</button>
  </form>
</div>

<div class="card">
  <h2>Usuários cadastrados <span class="badge ok"><?= count($lista) ?></span></h2>
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
<?php require __DIR__ . '/includes/footer.php'; ?>
