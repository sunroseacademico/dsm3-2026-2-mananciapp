/**
 * Service Worker do PWA "Manancial".
 *
 * Responsável apenas por um cache básico de arquivos ESTÁTICOS
 * (CSS, JS, ícones, imagens). Páginas PHP e qualquer requisição que
 * dependa do banco de dados (login, dashboard, vídeos, notificações etc.)
 * NUNCA passam pelo cache — são sempre buscadas na rede, para evitar
 * dados desatualizados ou sessões inconsistentes.
 *
 * Ao adicionar novos arquivos estáticos ao projeto (novo CSS/JS/ícone),
 * atualize a lista PRECACHE_URLS e incremente o CACHE_VERSION para que
 * os usuários recebam a versão nova assim que possível.
 *
 * OneSignal (push notifications): o SDK do OneSignal também precisa de um
 * Service Worker rodando no mesmo scope deste arquivo. Em vez de registrar
 * um segundo SW (o que geraria conflito de scope com este arquivo — só um
 * dos dois ficaria realmente ativo), importamos o script deles aqui dentro
 * e apontamos o OneSignal.init() para usar este mesmo sw.js
 * (serviceWorkerPath). Ver o <script> de inicialização em
 * php/includes/pwa_head.php (incluído em toda página PHP) e em index.html.
 */
// Com o CDN da OneSignal fora (ou bloqueado), o importScripts falhando
// derrubava a instalação do SW inteiro: o PWA e as próprias correções deste
// arquivo deixavam de chegar. Sem ele, só o push fica sem funcionar.
try {
    importScripts('https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js');
} catch (e) {
    // segue sem push
}

const CACHE_VERSION = 'v6';
const CACHE_NAME = `manancial-pwa-estatico-${CACHE_VERSION}`;

// Arquivos estáticos "essenciais", pré-carregados na instalação do SW.
// Caminhos relativos (sem "/" na frente): resolvem sempre a partir da
// localização deste sw.js (a raiz do projeto), funcionando tanto local
// (dentro de uma subpasta do htdocs) quanto em produção.
const PRECACHE_URLS = [
    'manifest.json',
    'offline.html',
    'css/inicio.css',
    'css/styles.css',
    'js/script.js',
    'js/theme.js',
    'js/pwa.js',
    'assets/logo-sm.png',
    'assets/img/icon-192.png',
    'assets/img/icon-512.png',
    'assets/img/apple-touch-icon.png',
];

// Extensões de arquivos considerados "estáticos" e elegíveis para cache.
const EXTENSOES_ESTATICAS = /\.(css|js|png|jpg|jpeg|gif|svg|webp|ico|woff2?|ttf)$/i;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((nomes) => Promise.all(
                nomes
                    .filter((nome) => nome !== CACHE_NAME)
                    .map((nome) => caches.delete(nome))
            ))
            .then(() => self.clients.claim())
    );
});

/**
 * Um recurso só é candidato a cache se for da mesma origem, do método
 * GET e tiver uma extensão de arquivo estático (ou estiver dentro de
 * /assets/). Isso automaticamente exclui .php, chamadas AJAX para
 * endpoints dinâmicos e qualquer requisição de navegação (as páginas
 * em si), garantindo que dados de login/vídeos nunca fiquem em cache.
 */
function ehArquivoEstatico(request, url) {
    if (request.method !== 'GET') {
        return false;
    }

    if (url.origin !== self.location.origin) {
        return false;
    }

    return EXTENSOES_ESTATICAS.test(url.pathname) || url.pathname.includes('/assets/');
}

// Chave do cache sem a query: o "?v=<deploy>" dos CSS/JS mudava a cada
// deploy e cada versão virava uma entrada nova (o cache só crescia). Assim
// fica uma por arquivo, e o precache (sem ?v=) serve de reserva offline.
function chaveSemQuery(url) {
    return url.origin + url.pathname;
}

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Página (navegação) sem internet: mostra offline.html em vez do erro do
    // navegador. A página em si nunca é guardada (só vai à rede).
    if (event.request.mode === 'navigate' && event.request.method === 'GET') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match('offline.html').then((r) => r || Response.error()))
        );
        return;
    }

    if (!ehArquivoEstatico(event.request, url)) {
        // Deixa a requisição seguir normalmente para a rede (páginas PHP,
        // login, dashboard, vídeos, consultas ao banco, etc.).
        return;
    }

    // CSS e JS: rede primeiro, cache só se estiver offline. Com
    // stale-while-revalidate, depois de cada deploy o navegador continuava
    // rodando o CSS/JS antigo (ex.: a correção dos modais do admin não
    // chegava até recarregar de novo), misturado com o PHP novo.
    if (/\.(css|js)$/i.test(url.pathname)) {
        event.respondWith(
            fetch(event.request)
                .then((respostaRede) => {
                    if (respostaRede && respostaRede.status === 200) {
                        const copia = respostaRede.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(chaveSemQuery(url), copia));
                    }
                    return respostaRede;
                })
                .catch(() => caches.match(chaveSemQuery(url)))
        );
        return;
    }

    // Imagens/fontes/ícones: cache-first com atualização em segundo plano
    // (stale-while-revalidate): responde rápido com o que já está em cache
    // e, em paralelo, busca uma versão nova na rede para a próxima visita.
    event.respondWith(
        caches.open(CACHE_NAME).then((cache) =>
            cache.match(event.request).then((respostaCache) => {
                const buscaRede = fetch(event.request)
                    .then((respostaRede) => {
                        if (respostaRede && respostaRede.status === 200) {
                            cache.put(event.request, respostaRede.clone());
                        }
                        return respostaRede;
                    })
                    .catch(() => respostaCache);

                return respostaCache || buscaRede;
            })
        )
    );
});
