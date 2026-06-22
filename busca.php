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
    // Carrega a imagem original
    const imgURL = URL.createObjectURL(file);
    const imgEl = new Image();
    imgEl.src = imgURL;
    await new Promise(r => imgEl.onload = r);

    // 1) PADRÕES DE PLACA BR + função de validação com correções automáticas
    //    O OCR confunde: O↔0, I↔1, S↔5, B↔8, Z↔2, G↔6, D↔0, Q↔0
    function corrigirPlaca(s){
      if (s.length !== 7) return null;
      const L_para_N = {'O':'0','I':'1','Q':'0','Z':'2','S':'5','B':'8','D':'0','G':'6'};
      const N_para_L = {'0':'O','1':'I','5':'S','8':'B','2':'Z','6':'G'};
      const tipos = [
        ['L','L','L','N','N','N','N'],   // antigo:   AAA9999
        ['L','L','L','N','L','N','N'],   // mercosul: AAA9A99
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
          } else {
            if (ehNumero) candidata += c;
            else if (ehLetra && L_para_N[c]) candidata += L_para_N[c];
            else { ok = false; break; }
          }
        }
        if (ok) return { texto: candidata, valida: true, padrao: t.join('') };
      }
      return null;
    }

    function buscarPlacaNoTexto(texto){
      const bruto = (texto || '').toUpperCase().replace(/[^A-Z0-9]/g,'');
      // janela deslizante de 7 caracteres
      for (let i = 0; i + 7 <= bruto.length; i++) {
        const t = bruto.substr(i, 7);
        const c = corrigirPlaca(t);
        if (c) return { ...c, bruto };
      }
      return { texto: bruto.slice(0,7), valida: false, bruto };
    }

    // 2) Gera 3 variações da imagem para tentar o OCR em cada uma
    function prepararCanvas(modo){
      const maxW = 1400;
      const scale = imgEl.width > maxW ? maxW / imgEl.width : 1;
      const w = Math.round(imgEl.width * scale);
      const h = Math.round(imgEl.height * scale);
      const cv = document.createElement('canvas');
      cv.width = w; cv.height = h;
      const ctx = cv.getContext('2d');
      ctx.drawImage(imgEl, 0, 0, w, h);

      if (modo === 'original') return cv;

      const px = ctx.getImageData(0, 0, w, h);
      const d = px.data;

      // tons de cinza primeiro
      for (let i = 0; i < d.length; i += 4) {
        const g = 0.299*d[i] + 0.587*d[i+1] + 0.114*d[i+2];
        d[i] = d[i+1] = d[i+2] = g;
      }

      if (modo === 'global') {
        // limiar pela média (rápido, bom para iluminação uniforme)
        let s = 0;
        for (let i = 0; i < d.length; i += 4) s += d[i];
        const lim = (s / (d.length/4)) * 0.85;
        for (let i = 0; i < d.length; i += 4) {
          const v = d[i] < lim ? 0 : 255;
          d[i] = d[i+1] = d[i+2] = v;
        }
      } else if (modo === 'adaptativo') {
        // limiar local: cada pixel comparado à média de uma janela 25x25
        // (melhor para fotos com sombras parciais — comum em placas)
        const win = 12; // raio = janela 25x25
        const copia = new Uint8ClampedArray(d.length);
        copia.set(d);
        for (let y = 0; y < h; y++) {
          for (let x = 0; x < w; x++) {
            let soma = 0, cnt = 0;
            for (let dy = -win; dy <= win; dy += 4) {
              const yy = y + dy; if (yy < 0 || yy >= h) continue;
              for (let dx = -win; dx <= win; dx += 4) {
                const xx = x + dx; if (xx < 0 || xx >= w) continue;
                soma += copia[(yy*w + xx)*4];
                cnt++;
              }
            }
            const media = soma / cnt;
            const idx = (y*w + x)*4;
            const v = copia[idx] < (media - 10) ? 0 : 255;
            d[idx] = d[idx+1] = d[idx+2] = v;
          }
        }
      }
      ctx.putImageData(px, 0, 0);
      return cv;
    }

    status.textContent = 'Lendo a placa (1/3)…';
    const tentativas = [];

    // 3) Roda 3 versões com modo "linha única"
    const variantes = ['adaptativo', 'global', 'original'];
    for (let i = 0; i < variantes.length; i++) {
      status.textContent = `Lendo a placa (${i+1}/3)…`;
      const cv = prepararCanvas(variantes[i]);
      const { data } = await Tesseract.recognize(cv, 'eng', {
        logger: m => {
          if (m.status === 'recognizing text') {
            const pct = (i + m.progress) / variantes.length;
            bar.style.width = Math.round(pct*100) + '%';
          }
        },
        tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        tessedit_pageseg_mode: '7',
      });
      const r = buscarPlacaNoTexto(data.text);
      tentativas.push({ ...r, modo: variantes[i] });
      if (r.valida) break;     // se já achou uma válida, para
    }

    // 4) Escolhe melhor resultado: prioriza placa válida, senão o que tem mais texto reconhecido
    let melhor = tentativas.find(t => t.valida) || tentativas[0];
    const placa = melhor.texto;

    bar.style.width = '100%';
    if(placa && placa.length >= 5){
      const aviso = melhor.valida
        ? '<span class="muted" style="font-size:.8rem">(confira antes de salvar)</span>'
        : '<span class="muted" style="font-size:.8rem;color:#a83b3b">⚠ Não consegui validar o padrão, confira com atenção</span>';
      status.innerHTML = 'Placa detectada: <b>'+esc(placa)+'</b> '+aviso;
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
