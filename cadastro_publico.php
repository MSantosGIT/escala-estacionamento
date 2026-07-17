<?php
// ============================================================
//  CADASTRO PÚBLICO DE VEÍCULOS
//  Página standalone (NÃO exige login).
//  Compartilhe o link: http://SEU_DOMINIO/cadastro_publico.php
//  Os veículos entram com origem='publico' e aprovado=0,
//  para o administrador revisar dentro do sistema.
// ============================================================
require_once __DIR__ . '/config/db.php';
session_start();

function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// token CSRF próprio desta página
if (empty($_SESSION['pub_csrf'])) {
    $_SESSION['pub_csrf'] = bin2hex(random_bytes(32));
}

$erros   = [];
$sucesso = false;
$old     = ['marca'=>'','modelo'=>'','cor'=>'','placa'=>'','proprietario'=>'','celular'=>'','celular2'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) CSRF
    if (!hash_equals($_SESSION['pub_csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $erros[] = 'Sessão expirada. Recarregue a página e tente novamente.';
    }
    // 2) honeypot (campo oculto que humanos não preenchem)
    if (!empty($_POST['website'])) {
        // provável bot: finge sucesso e descarta
        $sucesso = true;
    }
    // 3) rate-limit simples por sessão (máx. 3 envios / 10 min)
    $agora = time();
    $_SESSION['pub_envios'] = array_filter($_SESSION['pub_envios'] ?? [], fn($t) => $t > $agora - 600);
    if (count($_SESSION['pub_envios']) >= 3) {
        $erros[] = 'Muitos envios em sequência. Aguarde alguns minutos.';
    }

    if (!$sucesso && !$erros) {
        $old = [
            'marca'        => trim($_POST['marca'] ?? ''),
            'modelo'       => trim($_POST['modelo'] ?? ''),
            'cor'          => trim($_POST['cor'] ?? ''),
            'placa'        => strtoupper(trim($_POST['placa'] ?? '')),
            'proprietario' => trim($_POST['proprietario'] ?? ''),
            'celular'      => trim($_POST['celular'] ?? ''),
            'celular2'     => trim($_POST['celular2'] ?? ''),
        ];

        // 4) validações
        if ($old['marca']==='')        $erros[] = 'Informe a marca.';
        if ($old['modelo']==='')       $erros[] = 'Informe o modelo.';
        if ($old['cor']==='')          $erros[] = 'Informe a cor.';
        if ($old['proprietario']==='') $erros[] = 'Informe o nome do proprietário.';
        if ($old['celular']==='')      $erros[] = 'Informe o celular.';

        // placa BR (ABC1234 ou ABC1D23 – Mercosul)
        $placa = preg_replace('/[^A-Z0-9]/', '', $old['placa']);
        if (!preg_match('/^[A-Z]{3}[0-9][0-9A-Z][0-9]{2}$/', $placa)) {
            $erros[] = 'Placa inválida. Use o formato ABC1234 ou ABC1D23.';
        }

        // duplicidade
        if (!$erros) {
            $st = db()->prepare("SELECT 1 FROM veiculos WHERE placa = ?");
            $st->execute([$placa]);
            if ($st->fetch()) $erros[] = 'Já existe um veículo cadastrado com esta placa.';
        }

        // upload foto (opcional)
        $foto = null;
        if (!$erros && !empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['foto']['size'] > 4 * 1024 * 1024) {
                $erros[] = 'A foto deve ter no máximo 4 MB.';
            } else {
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $okExt = ['jpg','jpeg','png','webp'];
                $tipo  = @getimagesize($_FILES['foto']['tmp_name']);  // confirma que é imagem real
                if (!in_array($ext, $okExt) || $tipo === false) {
                    $erros[] = 'Envie uma imagem JPG, PNG ou WEBP.';
                } else {
                    $nome = 'pub_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/uploads/' . $nome);
                    $foto = 'uploads/' . $nome;
                }
            }
        }

        // 5) grava (aguardando aprovação)
        if (!$erros) {
            db()->prepare(
              "INSERT INTO veiculos (marca,modelo,cor,placa,proprietario,celular,celular2,foto,origem,aprovado)
               VALUES (?,?,?,?,?,?,?,?, 'publico', 0)"
            )->execute([$old['marca'],$old['modelo'],$old['cor'],$placa,$old['proprietario'],$old['celular'],$old['celular2'],$foto]);

            $_SESSION['pub_envios'][] = $agora;
            // renova o token para evitar reenvio
            $_SESSION['pub_csrf'] = bin2hex(random_bytes(32));
            $sucesso = true;
            $old = array_map(fn()=>'', $old);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cadastro de Veículo · Apoio Externo</title>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#e8843f">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<link rel="icon" href="assets/icons/favicon.png" type="image/png">
<link rel="stylesheet" href="assets/css/style.css?v=14">
</head>
<body>
<div class="login-wrap" style="align-items:flex-start;padding-top:3rem">
  <div class="login-box" style="max-width:560px">
    <div class="logo">
      <div class="cab-igreja" style="font-size:.9rem;font-weight:700;color:var(--laranja-6);letter-spacing:.5px;margin-bottom:.4rem;text-align:center;text-transform:uppercase">
        Apoio Externo · Igreja Primazia
      </div>
      <div class="ic">🚗</div>
      <h1>Cadastre seu veículo</h1>
      <p>Preencha os dados abaixo para registrar seu veículo no estacionamento.</p>
    </div>

    <?php if ($sucesso): ?>
      <div class="flash sucesso">
        ✅ Cadastro enviado com sucesso! Seu veículo será conferido pela equipe.
      </div>
      <a href="cadastro_publico.php" class="btn" style="width:100%;justify-content:center">Cadastrar outro veículo</a>
    <?php else: ?>

      <?php if ($erros): ?>
        <div class="flash erro">
          <?php foreach ($erros as $e): ?>• <?= esc($e) ?><br><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= esc($_SESSION['pub_csrf']) ?>">
        <!-- honeypot: invisível para humanos -->
        <div style="position:absolute;left:-9999px" aria-hidden="true">
          <label>Não preencha</label>
          <input type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div class="form-row">
          <div><label>Marca</label><input name="marca" required value="<?= esc($old['marca']) ?>"></div>
          <div><label>Modelo</label><input name="modelo" required value="<?= esc($old['modelo']) ?>"></div>
        </div>
        <div class="form-row">
          <div><label>Cor</label><input name="cor" required value="<?= esc($old['cor']) ?>"></div>
          <div><label>Placa</label><input name="placa" required placeholder="ABC1D23" value="<?= esc($old['placa']) ?>"></div>
        </div>
        <div class="form-row">
          <div><label>Seu nome (proprietário)</label><input name="proprietario" required value="<?= esc($old['proprietario']) ?>"></div>
          <div><label>Celular</label><input class="mask-tel" name="celular" required placeholder="(61) 99999-9999" value="<?= esc($old['celular']) ?>" inputmode="tel"></div>
        </div>
        <div class="form-row">
          <div><label>2º telefone (opcional)</label><input class="mask-tel" name="celular2" placeholder="(61) 99999-9999" value="<?= esc($old['celular2']) ?>" inputmode="tel"></div>
        </div>
        <div style="margin-bottom:1.2rem">
          <label>Foto do veículo (opcional)</label>
          <input type="file" name="foto" accept="image/*">
        </div>
        <button class="btn" style="width:100%;justify-content:center">Enviar cadastro</button>
      </form>
      <p class="muted" style="text-align:center;margin-top:1rem">
        Seus dados serão usados apenas para a gestão do estacionamento.
      </p>
    <?php endif; ?>
  </div>
</div>
<footer class="rodape-dev centro">
  <span class="rd-desktop">Desenvolvedor <strong>Marielton M Santos</strong> · WhatsApp </span>
  <span class="rd-mobile">Desenvolvedor: <strong>M Santos</strong> </span>
  <a href="https://wa.me/5561999116077" target="_blank" rel="noopener">(61) 99911-6077</a>
</footer>
<script>
function formatarTelefone(s){
  s = (s || '').replace(/\D/g,'').slice(0,11);
  if (s.length === 0) return '';
  if (s.length <= 2)  return '(' + s;
  if (s.length <= 6)  return '(' + s.slice(0,2) + ') ' + s.slice(2);
  if (s.length <= 10) return '(' + s.slice(0,2) + ') ' + s.slice(2,6) + '-' + s.slice(6);
  return '(' + s.slice(0,2) + ') ' + s.slice(2,7) + '-' + s.slice(7);
}
document.addEventListener('input', (ev) => {
  const el = ev.target;
  if (el && el.matches && el.matches('input.mask-tel')) {
    const pos = el.selectionStart;
    const antes = el.value.length;
    el.value = formatarTelefone(el.value);
    const depois = el.value.length;
    el.setSelectionRange(pos + (depois - antes), pos + (depois - antes));
  }
});
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input.mask-tel').forEach(i => i.value = formatarTelefone(i.value));
});
</script>
</body>
</html>
