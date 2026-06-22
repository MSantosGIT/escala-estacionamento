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
  <p class="muted" style="margin-top:.6rem">Tire a foto, ajuste o quadro sobre a placa e clique em <b>Ler placa</b>.</p>
  <!-- input de câmera: capture=environment abre a traseira no celular -->
  <input type="file" id="cam" accept="image/*" capture="environment" style="display:none">

  <!-- ÁREA DE RECORTE -->
  <div id="recorteBox" style="display:none;margin-top:1rem">
    <div id="cropArea" style="position:relative;display:inline-block;max-width:100%;background:#000;border-radius:10px;overflow:hidden;touch-action:none">
      <img id="cropImg" style="display:block;max-width:100%;max-height:60vh;user-select:none">
      <div id="cropOverlay" style="position:absolute;border:2px dashed #fff;box-shadow:0 0 0 9999px rgba(0,0,0,.55);cursor:move;touch-action:none">
        <!-- alças dos 4 cantos -->
        <div class="crop-handle" data-corner="nw" style="position:absolute;left:-10px;top:-10px;width:20px;height:20px;background:#e8843f;border:2px solid #fff;border-radius:50%;cursor:nwse-resize"></div>
        <div class="crop-handle" data-corner="ne" style="position:absolute;right:-10px;top:-10px;width:20px;height:20px;background:#e8843f;border:2px solid #fff;border-radius:50%;cursor:nesw-resize"></div>
        <div class="crop-handle" data-corner="sw" style="position:absolute;left:-10px;bottom:-10px;width:20px;height:20px;background:#e8843f;border:2px solid #fff;border-radius:50%;cursor:nesw-resize"></div>
        <div class="crop-handle" data-corner="se" style="position:absolute;right:-10px;bottom:-10px;width:20px;height:20px;background:#e8843f;border:2px solid #fff;border-radius:50%;cursor:nwse-resize"></div>
      </div>
    </div>
    <div style="margin-top:.7rem;display:flex;gap:.5rem;flex-wrap:wrap">
      <button type="button" class="btn" id="btnLerPlaca">🔍 Ler placa</button>
      <button type="button" class="btn sec" id="btnRefoto">↻ Refazer foto</button>
      <button type="button" class="btn sec" id="btnCancelarFoto">Cancelar</button>
    </div>
    <div id="ocrStatusBox" style="display:none;margin-top:1rem">
      <div class="muted" id="ocrStatus">Processando…</div>
      <div style="height:8px;background:var(--laranja-2);border-radius:6px;overflow:hidden;margin-top:.4rem">
        <div id="ocrBar" style="height:100%;width:0;background:var(--laranja-5);transition:width .2s"></div>
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

// Estado global do recorte
let imgOriginal = null;          // <img> com a foto carregada
let cropRect = {x:0, y:0, w:0, h:0};  // em pixels da imagem renderizada na tela

cam.addEventListener('change', () => {
  if (!cam.files || !cam.files[0]) return;
  const file = cam.files[0];
  abrirRecorte(file);
  cam.value = '';
});

document.getElementById('btnRefoto').addEventListener('click', () => cam.click());
document.getElementById('btnCancelarFoto').addEventListener('click', fecharRecorte);
document.getElementById('btnLerPlaca').addEventListener('click', processarRecorte);

function abrirRecorte(file) {
  const url = URL.createObjectURL(file);
  imgOriginal = new Image();
  imgOriginal.onload = () => {
    const img = document.getElementById('cropImg');
    img.src = url;
    img.onload = () => {
      document.getElementById('recorteBox').style.display = 'block';
      document.getElementById('ocrStatusBox').style.display = 'none';
      // posiciona o retângulo inicial ocupando o centro horizontalmente,
      // e ~25% da altura no meio da imagem
      const rect = img.getBoundingClientRect();
      cropRect.w = Math.round(rect.width * 0.7);
      cropRect.h = Math.round(rect.height * 0.18);
      cropRect.x = Math.round((rect.width - cropRect.w) / 2);
      cropRect.y = Math.round((rect.height - cropRect.h) / 2);
      atualizarOverlay();
    };
  };
  imgOriginal.src = url;
}
function fecharRecorte() {
  document.getElementById('recorteBox').style.display = 'none';
  imgOriginal = null;
}
function atualizarOverlay() {
  const ov = document.getElementById('cropOverlay');
  ov.style.left = cropRect.x + 'px';
  ov.style.top  = cropRect.y + 'px';
  ov.style.width  = cropRect.w + 'px';
  ov.style.height = cropRect.h + 'px';
}

// Drag para mover/redimensionar
(function setupCrop(){
  const ov = document.getElementById('cropOverlay');
  const area = document.getElementById('cropArea');
  let dragging = null;  // 'move' ou 'nw'/'ne'/'sw'/'se'
  let startX, startY, startRect;

  function onStart(ev) {
    ev.preventDefault();
    const t = ev.touches ? ev.touches[0] : ev;
    const handle = ev.target.closest('.crop-handle');
    dragging = handle ? handle.dataset.corner : 'move';
    startX = t.clientX; startY = t.clientY;
    startRect = {...cropRect};
  }
  function onMove(ev) {
    if (!dragging) return;
    ev.preventDefault();
    const t = ev.touches ? ev.touches[0] : ev;
    const dx = t.clientX - startX;
    const dy = t.clientY - startY;
    const img = document.getElementById('cropImg');
    const rect = img.getBoundingClientRect();
    if (dragging === 'move') {
      cropRect.x = Math.max(0, Math.min(rect.width  - cropRect.w, startRect.x + dx));
      cropRect.y = Math.max(0, Math.min(rect.height - cropRect.h, startRect.y + dy));
    } else {
      const min = 40;
      if (dragging.includes('e')) cropRect.w = Math.max(min, Math.min(rect.width  - startRect.x, startRect.w + dx));
      if (dragging.includes('w')) {
        const nx = Math.max(0, startRect.x + dx);
        cropRect.w = Math.max(min, startRect.w + (startRect.x - nx));
        cropRect.x = nx;
      }
      if (dragging.includes('s')) cropRect.h = Math.max(min, Math.min(rect.height - startRect.y, startRect.h + dy));
      if (dragging.includes('n')) {
        const ny = Math.max(0, startRect.y + dy);
        cropRect.h = Math.max(min, startRect.h + (startRect.y - ny));
        cropRect.y = ny;
      }
    }
    atualizarOverlay();
  }
  function onEnd() { dragging = null; }

  ov.addEventListener('mousedown', onStart);
  ov.addEventListener('touchstart', onStart, {passive:false});
  window.addEventListener('mousemove', onMove);
  window.addEventListener('touchmove', onMove, {passive:false});
  window.addEventListener('mouseup', onEnd);
  window.addEventListener('touchend', onEnd);
})();

async function processarRecorte() {
  if (!imgOriginal) return;
  const img = document.getElementById('cropImg');
  const rect = img.getBoundingClientRect();
  // proporção entre tamanho exibido e tamanho original
  const escalaX = imgOriginal.naturalWidth  / rect.width;
  const escalaY = imgOriginal.naturalHeight / rect.height;
  // recorta na resolução original
  const sx = Math.round(cropRect.x * escalaX);
  const sy = Math.round(cropRect.y * escalaY);
  const sw = Math.round(cropRect.w * escalaX);
  const sh = Math.round(cropRect.h * escalaY);

  // canvas com o recorte ampliado a 800px de largura (bom pra OCR)
  const alvoW = 800;
  const escala = alvoW / sw;
  const cv = document.createElement('canvas');
  cv.width  = Math.round(sw * escala);
  cv.height = Math.round(sh * escala);
  const ctx = cv.getContext('2d');
  ctx.imageSmoothingEnabled = true;
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(imgOriginal, sx, sy, sw, sh, 0, 0, cv.width, cv.height);

  const statusBox = document.getElementById('ocrStatusBox');
  const status = document.getElementById('ocrStatus');
  const bar = document.getElementById('ocrBar');
  statusBox.style.display = 'block';
  bar.style.width = '0';

  try {
    function corrigirPlaca(s){
      if (s.length !== 7) return null;
      // Quando esperado é número e vem letra: mapeia letra → número
      const L_para_N = {'O':'0','I':'1','Q':'0','Z':'2','S':'5','B':'8','D':'0','G':'6'};
      // Quando esperado é letra e vem número: mapeia número → letras possíveis
      // (cada número pode confundir com mais de uma letra, ex: 0 ↔ O ou D)
      const N_para_L = {'0':['D','O'],'1':['I'],'5':['S'],'8':['B'],'2':['Z'],'6':['G']};
      // Quando vem letra mas pode ser outra letra (D↔O é o caso clássico das placas)
      const L_para_L = {'O':['D','O'], 'D':['D','O']};

      const tipos = [
        ['L','L','L','N','N','N','N'],   // antigo:   AAA9999
        ['L','L','L','N','L','N','N'],   // mercosul: AAA9A99
      ];

      const candidatos = [];
      for (const t of tipos) {
        // expande: para cada posição, lista de caracteres possíveis
        let opcoes = [[]];
        let ok = true;
        for (let i = 0; i < 7; i++) {
          const c = s[i], esperado = t[i];
          const ehLetra = /[A-Z]/.test(c), ehNumero = /[0-9]/.test(c);
          let possiveis = [];
          if (esperado === 'L') {
            if (ehLetra) {
              possiveis = L_para_L[c] || [c];
            } else if (ehNumero && N_para_L[c]) {
              possiveis = N_para_L[c];
            }
          } else { // N
            if (ehNumero) possiveis = [c];
            else if (ehLetra && L_para_N[c]) possiveis = [L_para_N[c]];
          }
          if (possiveis.length === 0) { ok = false; break; }
          // produto cartesiano: combina opções acumuladas com possíveis
          const novas = [];
          for (const acc of opcoes) for (const p of possiveis) novas.push([...acc, p]);
          opcoes = novas;
        }
        if (ok) {
          for (const arr of opcoes) candidatos.push({ texto: arr.join(''), padrao: t.join('') });
        }
      }
      if (!candidatos.length) return null;

      // pontuação: priorizar candidatos com 'D' sobre 'O' nas posições de letra
      // (D é mais comum em placas reais; placas evitam O por causa da confusão com zero)
      function score(p) {
        let pts = 0;
        for (const ch of p.texto) {
          if (ch === 'D') pts += 2;
          if (ch === 'O') pts -= 1;
        }
        return pts;
      }
      candidatos.sort((a,b) => score(b) - score(a));
      return { texto: candidatos[0].texto, valida: true };
    }
    function buscarPlacaNoTexto(texto){
      const bruto = (texto || '').toUpperCase().replace(/[^A-Z0-9]/g,'');
      for (let i = 0; i + 7 <= bruto.length; i++) {
        const t = bruto.substr(i, 7);
        const c = corrigirPlaca(t);
        if (c) return { ...c, bruto };
      }
      return { texto: bruto.slice(0,7), valida: false, bruto };
    }

    // pré-processa o recorte (binarização) — opcionalmente
    function aplicarBinarizacao(canvas){
      const ctx = canvas.getContext('2d');
      const px = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const d = px.data; let soma = 0;
      for (let i = 0; i < d.length; i += 4) {
        const g = 0.299*d[i] + 0.587*d[i+1] + 0.114*d[i+2];
        d[i] = d[i+1] = d[i+2] = g; soma += g;
      }
      const lim = (soma / (d.length/4)) * 0.85;
      for (let i = 0; i < d.length; i += 4) {
        const v = d[i] < lim ? 0 : 255;
        d[i] = d[i+1] = d[i+2] = v;
      }
      ctx.putImageData(px, 0, 0);
    }

    // 2 tentativas: original ampliado e binarizado
    const tentativas = [];
    for (let i = 0; i < 2; i++) {
      status.textContent = `Lendo a placa (${i+1}/2)…`;
      const cvAtual = document.createElement('canvas');
      cvAtual.width = cv.width; cvAtual.height = cv.height;
      cvAtual.getContext('2d').drawImage(cv, 0, 0);
      if (i === 1) aplicarBinarizacao(cvAtual);
      const { data } = await Tesseract.recognize(cvAtual, 'eng', {
        logger: m => {
          if (m.status === 'recognizing text') {
            const pct = (i + m.progress) / 2;
            bar.style.width = Math.round(pct*100) + '%';
          }
        },
        tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        tessedit_pageseg_mode: '7',
      });
      const r = buscarPlacaNoTexto(data.text);
      tentativas.push(r);
      if (r.valida) break;
    }

    const melhor = tentativas.find(t => t.valida) || tentativas[0];
    const placa = melhor.texto;

    bar.style.width = '100%';
    if (placa && placa.length >= 5) {
      const aviso = melhor.valida
        ? '<span class="muted" style="font-size:.8rem">(confira antes de salvar)</span>'
        : '<span class="muted" style="font-size:.8rem;color:#a83b3b">⚠ Padrão não validou, confira com atenção</span>';
      status.innerHTML = 'Placa detectada: <b>' + esc(placa) + '</b> ' + aviso;
      q.value = placa;
      buscar(placa);
    } else {
      status.textContent = 'Não consegui ler. Ajuste melhor o quadro e tente de novo.';
    }
  } catch (err) {
    status.textContent = 'Falha ao processar. Tente refazer a foto.';
  }
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
