// Service Worker — Apoio Externo · Gestão de Escala
// Cache leve apenas de assets estáticos. Páginas PHP sempre vão à rede
// (são dinâmicas e dependem de login/sessão).

const CACHE = 'apoio-externo-v1';
const ASSETS = [
  'assets/css/style.css',
  'assets/icons/icon-192.png',
  'assets/icons/icon-512.png',
  'assets/icons/apple-touch-icon.png'
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)).catch(()=>{}));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  // só lida com GET do mesmo domínio
  if (e.request.method !== 'GET' || url.origin !== location.origin) return;

  const ehAsset = url.pathname.includes('/assets/');
  if (ehAsset) {
    // assets: cache primeiro, rede como reforço
    e.respondWith(
      caches.match(e.request).then((hit) => hit || fetch(e.request).then((res) => {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(e.request, copy)).catch(()=>{});
        return res;
      }).catch(()=>hit))
    );
  } else {
    // páginas: rede primeiro (conteúdo sempre atualizado)
    e.respondWith(fetch(e.request).catch(() => caches.match(e.request)));
  }
});
