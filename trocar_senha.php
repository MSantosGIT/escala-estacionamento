<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();
$pdo = db();
$u = usuario();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $nova    = $_POST['nova'] ?? '';
    $confirm = $_POST['confirmacao'] ?? '';

    if (strlen($nova) < 6) {
        flash('A nova senha precisa ter pelo menos 6 caracteres.', 'erro');
    } elseif ($nova !== $confirm) {
        flash('A confirmação não confere com a nova senha.', 'erro');
    } else {
        $hash = password_hash($nova, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE usuarios SET senha=? WHERE id=?")->execute([$hash, $u['id']]);
        flash('Senha trocada com sucesso.');
        redirect('dashboard.php');
    }
    redirect('trocar_senha.php');
}

$titulo = 'Trocar Senha';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Trocar minha senha</h1>
<p class="page-sub">Defina uma nova senha para sua conta (<?= e($u['nome']) ?>).</p>

<div class="card" style="max-width:480px">
  <form method="post">
    <input type="hidden" name="csrf" value="<?= tokenCSRF() ?>">
    <div style="margin-bottom:.8rem">
      <label>Nova senha</label>
      <input type="password" name="nova" required minlength="6" autocomplete="new-password">
    </div>
    <div style="margin-bottom:.8rem">
      <label>Confirmar nova senha</label>
      <input type="password" name="confirmacao" required minlength="6" autocomplete="new-password">
    </div>
    <button class="btn">🔑 Salvar nova senha</button>
    <a class="btn sec" href="dashboard.php" style="margin-left:.4rem">Cancelar</a>
  </form>
  <p class="muted" style="margin-top:1rem;font-size:.85rem">Mínimo de 6 caracteres. Use uma senha forte e que você lembre.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
