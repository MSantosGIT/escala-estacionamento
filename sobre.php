<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();

$titulo = 'Sobre o sistema';
require __DIR__ . '/includes/header.php';
?>
<div class="card" style="max-width:680px">
  <div class="sobre-topo">
    <img src="assets/icons/icon-192.png" alt="Apoio Externo">
    <div>
      <h1>Apoio Externo</h1>
      <div class="sub">Sistema de Gestão de Escala</div>
    </div>
  </div>

  <div class="sobre-sec">
    <h2>Sobre o desenvolvedor</h2>
    <p>Este sistema foi desenvolvido por <strong>Marielton Miranda dos Santos</strong>,
    formado em Sistemas de Informação pela PUC-MG em 2009. Atua com análise e
    desenvolvimento de sistemas desde 2002. Especialista em programação Cobol,
    Rexx, PHP, Delphi, entre outras.</p>
  </div>

  <div class="contato-box">
    <div class="contato-titulo">📨 Contato para novos projetos</div>
    <div class="linha">✉️ <a href="mailto:marieltonsantos@hotmail.com">marieltonsantos@hotmail.com</a></div>
    <div class="linha">💬 <a href="https://wa.me/5561999116077" target="_blank" rel="noopener">WhatsApp (61) 99911-6077</a></div>
  </div>
</div>

<style>
.sobre-topo{display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem}
.sobre-topo img{width:70px;height:70px;border-radius:18px;box-shadow:0 6px 16px rgba(232,132,63,.35)}
.sobre-topo h1{margin:0;color:var(--laranja-6);font-size:1.5rem;line-height:1.15}
.sobre-topo .sub{color:var(--texto-suave);font-size:.95rem}
.sobre-sec h2{color:var(--laranja-6);font-size:1.15rem;margin:0 0 .6rem}
.sobre-sec p{line-height:1.6;color:#444;margin:0 0 1rem}
.contato-box{background:var(--laranja-1);border:1px solid var(--laranja-3);border-radius:12px;padding:1rem 1.2rem}
.contato-box .linha{display:flex;align-items:center;gap:.6rem;padding:.4rem 0;font-size:1rem}
.contato-box .linha a{color:var(--laranja-6);text-decoration:none;font-weight:600;word-break:break-word}
.contato-box .linha a:hover{text-decoration:underline}
.contato-titulo{font-weight:700;color:#444;margin-bottom:.3rem}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
