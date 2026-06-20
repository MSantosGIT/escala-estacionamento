<?php
require_once __DIR__ . '/includes/functions.php';
exigirLogin();
$titulo = 'Busca de Veículo';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Busca rápida de veículo</h1>
<p class="page-sub">Pesquise pela placa, proprietário ou modelo — ou fotografe a placa.</p>

<div class="card">
  <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:1;min-width:220px">
      <label>Placa, nome ou modelo</label>
      <input id="q" autocomplete="off" placeholder="Ex.: ABC1D23 ou Ana" autofocus>
    </div>
    <button type="button" class="btn" id="btnFoto">📷 Foto da placa</button>
  </div>
  <p class="muted" style="margin-top:.6rem">Dica: aproxime a câmera e enquadre só a placa, com boa luz, para melhor leitura. A placa lida pode ser ajustada no campo acima antes de buscar.</p>
  <!-- input de câmera: capture=environment abre a traseira no celular -->
  <input type="file" id="cam" accept="image/*" capture="environment" style="display:none">

  <div id="ocrBox" style="display:none;margin-top:1rem">
    <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
      <img id="preview" class="foto-mini" style="width:120px;height:80px">
      <div style="flex:1;min-width:200px">
        <div class="muted" id="ocrStatus">Processando imagem…</div>
        <div style="height:8px;background:var(--laranja-2);border-radius:6px;overflow:hidden;margin-top:.4rem">
          <div id="ocrBar" style="height:100%;width:0;background:var(--laranja-5);transition:width .2s"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="resultados"></div>

<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script>
const BASE = '<?= e(rtrim(dirname($_SERVER['PHP_SELF']),'/\\')) ?>';
const q = document.getElementById('q');
const res = document.getElementById('resultados');
let timer = null;

function nivelFoto(v){
  return v.foto ? `<img class="foto-mini" src="${BASE}/${v.foto}">` : '<span class="muted">—</span>';
}

function render(data){
  if(!data.veiculos.length){
    res.innerHTML = '<div class="card"><p class="muted">Nenhum veículo encontrado.</p></div>';
    return;
  }
  let html = '<div class="card"><h2>Resultados <span class="badge ok">'+data.total+'</span></h2>';
  html += '<table><thead><tr><th>Foto</th><th>Veículo</th><th>Cor</th><th>Placa</th><th>Proprietário</th><th>Celular</th></tr></thead><tbody>';
  for(const v of data.veiculos){
    const tag = v.origem==='publico' ? ' <span class="badge junior">autocadastro</span>' : '';
    const zap = v.celular.replace(/\D/g,'');
    html += `<tr>
      <td>${nivelFoto(v)}</td>
      <td>${esc(v.marca)} ${esc(v.modelo)}${tag}</td>
      <td>${esc(v.cor)}</td>
      <td><b>${esc(v.placa)}</b></td>
      <td>${esc(v.proprietario)}</td>
      <td><a class="btn sm sec" href="https://wa.me/55${zap}" target="_blank">💬 ${esc(v.celular)}</a></td>
    </tr>`;
  }
  html += '</tbody></table></div>';
  res.innerHTML = html;
}

function esc(s){ const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }

function buscar(termo){
  if((termo||'').trim().length < 2){ res.innerHTML=''; return; }
  fetch(`${BASE}/buscar_veiculo.php?q=`+encodeURIComponent(termo))
    .then(r=>r.json()).then(render)
    .catch(()=>{ res.innerHTML='<div class="card"><p class="muted">Erro na busca.</p></div>'; });
}

q.addEventListener('input', ()=>{
  clearTimeout(timer);
  timer = setTimeout(()=>buscar(q.value), 350);
});

// ---------- OCR da placa ----------
const cam = document.getElementById('cam');
document.getElementById('btnFoto').addEventListener('click', ()=>cam.click());

cam.addEventListener('change', async ()=>{
  if(!cam.files || !cam.files[0]) return;
  const file = cam.files[0];
  const box = document.getElementById('ocrBox');
  const bar = document.getElementById('ocrBar');
  const status = document.getElementById('ocrStatus');
  const preview = document.getElementById('preview');

  preview.src = URL.createObjectURL(file);
  box.style.display = 'block';
  bar.style.width = '0';
  status.textContent = 'Lendo a placa…';

  try{
    const { data } = await Tesseract.recognize(file, 'eng', {
      logger: m => {
        if(m.status === 'recognizing text'){
          bar.style.width = Math.round(m.progress*100) + '%';
        }
      },
      tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
    });

    // extrai possível placa do texto reconhecido
    let bruto = (data.text || '').toUpperCase().replace(/[^A-Z0-9]/g,'');
    // tenta o padrão direto (antigo ABC1234 ou Mercosul ABC1D23)
    let m = bruto.match(/[A-Z]{3}[0-9][0-9A-Z][0-9]{2}/);
    // se não bateu, tenta qualquer sequência de 7 caracteres
    if(!m){ m = bruto.match(/[A-Z0-9]{7}/); }
    const placa = m ? m[0] : bruto.slice(0,7);

    bar.style.width = '100%';
    if(placa && placa.length >= 5){
      status.innerHTML = 'Placa detectada: <b>'+esc(placa)+'</b>';
      q.value = placa;
      buscar(placa);
    }else{
      status.textContent = 'Não foi possível ler a placa. Digite manualmente.';
    }
  }catch(err){
    status.textContent = 'Falha ao processar a imagem. Tente novamente ou digite a placa.';
  }
  cam.value = '';
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
