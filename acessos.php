<?php
require_once __DIR__ . '/includes/functions.php';
exigirAdmin();
$pdo = db();

// busca todos os usuários ordenados pelo acesso mais recente
$lista = $pdo->query(
  "SELECT u.id, u.nome, u.login, u.tipo, u.ultimo_acesso, c.nome AS colab_nome
   FROM usuarios u LEFT JOIN colaboradores c ON c.id=u.colaborador_id
   ORDER BY u.ultimo_acesso IS NULL, u.ultimo_acesso DESC, u.nome"
)->fetchAll();

// quantos acessaram nas últimas 24h, na última semana, total ativo
$qtdHoje    = 0;
$qtdSemana  = 0;
$qtdNunca   = 0;
$agora = time();
foreach ($lista as $u) {
    if (!$u['ultimo_acesso']) { $qtdNunca++; continue; }
    $ts = strtotime($u['ultimo_acesso']);
    if ($agora - $ts <= 86400)        $qtdHoje++;
    if ($agora - $ts <= 7 * 86400)    $qtdSemana++;
}

// função para "há X tempo"
function haQuantoTempo($dt) {
    if (!$dt) return ['—', 'nunca'];
    $diff = time() - strtotime($dt);
    if ($diff < 60)      return [floor($diff).'s atrás', 'recente'];
    if ($diff < 3600)    return [floor($diff/60).' min atrás', 'recente'];
    if ($diff < 86400)   return [floor($diff/3600).' h atrás', 'hoje'];
    if ($diff < 604800)  return [floor($diff/86400).' dias atrás', 'semana'];
    if ($diff < 2592000) return [floor($diff/604800).' sem. atrás', 'mes'];
    return [floor($diff/2592000).' meses atrás', 'antigo'];
}

$titulo = 'Acessos ao sistema';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Acessos ao sistema</h1>
<p class="page-sub">Veja quem entrou, quando, e identifique usuários inativos.</p>

<div class="grid cols-4" style="margin-bottom:1.2rem">
  <div class="stat"><div class="n"><?= count($lista) ?></div><div class="l">Usuários no total</div></div>
  <div class="stat"><div class="n"><?= $qtdHoje ?></div><div class="l">Acessaram nas últimas 24h</div></div>
  <div class="stat"><div class="n"><?= $qtdSemana ?></div><div class="l">Acessaram esta semana</div></div>
  <div class="stat"><div class="n"><?= $qtdNunca ?></div><div class="l">Nunca acessaram</div></div>
</div>

<div class="card">
  <h2>Lista de usuários</h2>
  <table>
    <thead>
      <tr>
        <th>Nome</th>
        <th>Tipo</th>
        <th>Último acesso</th>
        <th>Há quanto tempo</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lista as $u):
        list($texto, $classe) = haQuantoTempo($u['ultimo_acesso']);
      ?>
      <tr>
        <td><?= e($u['nome']) ?></td>
        <td><span class="badge <?= $u['tipo']==='administrador'?'lider':'pleno' ?>"><?= e(ucfirst($u['tipo'])) ?></span></td>
        <td>
          <?php if ($u['ultimo_acesso']): ?>
            <?= date('d/m/Y H:i', strtotime($u['ultimo_acesso'])) ?>
          <?php else: ?>
            <span class="muted">—</span>
          <?php endif; ?>
        </td>
        <td><span class="tempo tempo-<?= $classe ?>"><?= e($texto) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$lista): ?>
        <tr><td colspan="4" class="muted">Nenhum usuário cadastrado.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<style>
.tempo{display:inline-block;padding:.15rem .55rem;border-radius:12px;font-size:.78rem;font-weight:600}
.tempo-recente{background:#dff3e3;color:#2f7d49;border:1px solid #a6d4b0}
.tempo-hoje{background:#dff3e3;color:#2f7d49;border:1px solid #a6d4b0}
.tempo-semana{background:#fff5e8;color:#9a4f12;border:1px solid #fde9b8}
.tempo-mes{background:#fde9b8;color:#8a5a10;border:1px solid #f6cccc}
.tempo-antigo{background:#fbe0e0;color:#a83b3b;border:1px solid #f6cccc}
.tempo-nunca{background:#eee;color:#666;border:1px solid #ccc}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
