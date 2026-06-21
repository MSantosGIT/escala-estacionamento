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
  html += '<table><thead><tr><th>Placa</th><th>Proprietário</th><th>Veículo</th><th>Cor</th><th>Celular</th><th>2º Tel.</th><th>Foto</th></tr></thead><tbody>';
  for(const v of data.veiculos){
    const tag = v.origem==='publico' ? ' <span class="badge junior">autocadastro</span>' : '';
    const zap = (v.celular||'').replace(/\D/g,'');
    const zap2 = (v.celular2||'').replace(/\D/g,'');
    const cel2html = zap2
      ? `<a class="btn sm sec" href="https://wa.me/55${zap2}" target="_blank">💬 ${esc(v.celular2)}</a>`
      : '<span class="muted">—</span>';
    html += `<tr>
      <td><b>${esc(v.placa)}</b></td>
      <td>${esc(v.proprietario)}</td>
      <td>${esc(v.marca)} ${esc(v.modelo)}${tag}</td>
      <td>${esc(v.cor)}</td>
      <td><a class="btn sm sec" href="https://wa.me/55${zap}" target="_blank">💬 ${esc(v.celular)}</a></td>
      <td>${cel2html}</td>
      <td>${nivelFoto(v)}</td>
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
  status.textContent = 'Preparando a imagem…';

  try{
    // 1) Pré-processa a imagem: redimensiona, converte para tons de cinza
    //    e aumenta o contraste antes de jogar no OCR.
    const imgURL = URL.createObjectURL(file);
    const imgEl = new Image();
    imgEl.src = imgURL;
    await new Promise(r => imgEl.onload = r);

    // redimensiona para no máximo 1200px de largura (mais que isso atrapalha o OCR)
    const maxW = 1200;
    const scale = imgEl.width > maxW ? maxW / imgEl.width : 1;
    const w = Math.round(imgEl.width * scale);
    const h = Math.round(imgEl.height * scale);
    const cv = document.createElement('canvas');
    cv.width = w; cv.height = h;
    const ctx = cv.getContext('2d');
    ctx.drawImage(imgEl, 0, 0, w, h);
    const pixels = ctx.getImageData(0, 0, w, h);
    const d = pixels.data;
    // tons de cinza + threshold dinâmico simples (Otsu aproximado)
    let sum = 0;
    for (let i = 0; i < d.length; i += 4) {
      const g = 0.299*d[i] + 0.587*d[i+1] + 0.114*d[i+2];
      d[i] = d[i+1] = d[i+2] = g;
      sum += g;
    }
    const media = sum / (d.length/4);
    const lim = media * 0.85;          // limiar abaixo da média = preto
    for (let i = 0; i < d.length; i += 4) {
      const v = d[i] < lim ? 0 : 255;
      d[i] = d[i+1] = d[i+2] = v;
    }
    ctx.putImageData(pixels, 0, 0);

    status.textContent = 'Lendo a placa…';

    const { data } = await Tesseract.recognize(cv, 'eng', {
      logger: m => {
        if(m.status === 'recognizing text'){
          bar.style.width = Math.round(m.progress*100) + '%';
        }
      },
      tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
      tessedit_pageseg_mode: '7',       // 7 = single line of text (placa é 1 linha)
    });

    // 2) Limpa texto bruto
    let bruto = (data.text || '').toUpperCase().replace(/[^A-Z0-9]/g,'');

    // 3) Função que avalia uma sequência de 7 caracteres como placa, aplicando
    //    correções de OCR comuns (O↔0, I↔1, S↔5, B↔8, Z↔2) só nos lugares onde
    //    o padrão exige letra ou número.
    //    Padrões aceitos:
    //      Antigo:   AAA9999  (3 letras + 4 números)
    //      Mercosul: AAA9A99  (3 letras + 1 número + 1 letra + 2 números)
    function corrigirPlaca(s){
      if (s.length !== 7) return null;
      const L_para_N = {'O':'0','I':'1','Q':'0','Z':'2','S':'5','B':'8','D':'0','G':'6'};
      const N_para_L = {'0':'O','1':'I','5':'S','8':'B','2':'Z','6':'G'};
      const tipos = [
        ['L','L','L','N','N','N','N'],   // antigo
        ['L','L','L','N','L','N','N'],   // mercosul
      ];
      for (const t of tipos) {
        let candidata = '';
        let ok = true;
        for (let i = 0; i < 7; i++) {
          const c = s[i];
          const esperado = t[i];
          const ehLetra = /[A-Z]/.test(c);
          const ehNumero = /[0-9]/.test(c);
          if (esperado === 'L') {
            if (ehLetra) candidata += c;
            else if (ehNumero && N_para_L[c]) candidata += N_para_L[c];
            else { ok = false; break; }
          } else { // N
            if (ehNumero) candidata += c;
            else if (ehLetra && L_para_N[c]) candidata += L_para_N[c];
            else { ok = false; break; }
          }
        }
        if (ok) return candidata;
      }
      return null;
    }

    // 4) Tenta achar 7 caracteres consecutivos que formem uma placa válida.
    //    Faz uma janela deslizante pelo texto bruto.
    let placa = null;
    for (let i = 0; i + 7 <= bruto.length; i++) {
      const trecho = bruto.substr(i, 7);
      const corrigida = corrigirPlaca(trecho);
      if (corrigida) { placa = corrigida; break; }
    }
    // fallback: usa os primeiros 7 caracteres se nada encaixou
    if (!placa && bruto.length >= 5) placa = bruto.slice(0, 7);

    bar.style.width = '100%';
    if(placa && placa.length >= 5){
      status.innerHTML = 'Placa detectada: <b>'+esc(placa)+'</b> <span class="muted" style="font-size:.8rem">(confira antes de salvar)</span>';
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
