/**
 * PWA — registro do Service Worker + botão fixo "Instalar Aplicativo".
 *
 * O botão fica sempre visível nas páginas onde é permitido (enquanto o app
 * não estiver instalado — ver função podeMostrarBotaoInstalar), porque o
 * disparo automático do navegador (beforeinstallprompt) depende de
 * heurísticas de engajamento que podem demorar a acontecer. Assim o usuário
 * sempre tem uma forma de descobrir como instalar, mesmo antes do navegador
 * "liberar" o prompt nativo.
 *
 * Ao clicar: se o navegador já liberou o prompt nativo, usa ele direto.
 * Senão (o que pode acontecer com frequência, já que a intermitência do
 * InfinityFree às vezes impede o service worker/manifest de serem buscados
 * a tempo do navegador liberar o prompt), leva para a tela "como-instalar.php",
 * com o passo a passo manual de cada dispositivo.
 *
 * window.PWA_BASE_URL é definido inline por php/includes/pwa_head.php (nas
 * páginas PHP) com a URL absoluta da raiz do projeto — necessário porque o
 * projeto pode rodar dentro de uma subpasta (ex.: XAMPP local) e não em
 * "/". Em index.html (sempre na raiz), calculamos o mesmo valor aqui via
 * window.location, já que ali não há PHP disponível.
 *
 * O botão só é criado na landing page e nas telas de antes de logar (login
 * e criar conta): quem já fez login (fiel ou admin) presumivelmente já tem
 * o app instalado ou optou por usar pelo navegador mesmo, então o botão some
 * de todas as telas internas — o Service Worker continua registrado em
 * todas as páginas normalmente, só o botão que fica restrito.
 *
 * JavaScript puro (sem bibliotecas externas), carregado em todas as
 * páginas do sistema.
 */
(function () {
    'use strict';

    var base = window.PWA_BASE_URL || window.location.pathname.replace(/index\.html(?:[?#].*)?$/, '');

    // ---------------------------------------------------------------
    // 1) Registro do Service Worker
    // ---------------------------------------------------------------
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(base + 'sw.js', { scope: base })
                .catch(function (erro) {
                    console.warn('Falha ao registrar o Service Worker:', erro);
                });
        });
    }

    // ---------------------------------------------------------------
    // 2) Estado de instalação
    // ---------------------------------------------------------------
    function jaEstaInstalado() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
    }

    function jaEstaNaTelaDeAjuda() {
        return /\/como-instalar\.php$/.test(window.location.pathname);
    }

    // Landing page ("/" ou "/index.html"), login e criar conta — as únicas
    // telas de antes de logar onde o botão de instalar deve aparecer.
    function podeMostrarBotaoInstalar() {
        var caminho = window.location.pathname;
        return /\/(index\.html)?$/.test(caminho)
            || /\/login\.php$/.test(caminho)
            || /\/php\/fiel\/register\.php$/.test(caminho);
    }

    // ---------------------------------------------------------------
    // 3) Botão fixo "Instalar Aplicativo"
    // ---------------------------------------------------------------
    var eventoInstalacao = null;
    var botaoInstalar = null;

    function criarBotao() {
        if (botaoInstalar) {
            return botaoInstalar;
        }

        botaoInstalar = document.createElement('button');
        botaoInstalar.type = 'button';
        botaoInstalar.id = 'pwaInstallButton';
        botaoInstalar.className = 'pwa-install-btn';
        botaoInstalar.setAttribute('aria-label', 'Instalar aplicativo');
        botaoInstalar.innerHTML = '<i class="fas fa-download"></i><span>Instalar Aplicativo</span>';

        botaoInstalar.addEventListener('click', function () {
            // Se o navegador já liberou o prompt nativo, usa ele direto.
            if (eventoInstalacao) {
                eventoInstalacao.prompt();
                eventoInstalacao.userChoice.finally(function () {
                    eventoInstalacao = null;
                });
                return;
            }

            // Sem o prompt nativo disponível, manda para a tela com o passo a
            // passo de cada dispositivo (evita redirecionar se já estiver nela).
            if (!jaEstaNaTelaDeAjuda()) {
                window.location.href = base + 'como-instalar.php';
            }
        });

        document.body.appendChild(botaoInstalar);
        return botaoInstalar;
    }

    function removerBotao() {
        if (botaoInstalar) {
            botaoInstalar.remove();
            botaoInstalar = null;
        }
    }

    // Captura o prompt nativo quando o navegador liberar (Chrome/Edge/
    // Samsung Internet no Android e Chromium no desktop).
    window.addEventListener('beforeinstallprompt', function (evento) {
        evento.preventDefault();
        eventoInstalacao = evento;
    });

    // Assim que o app é efetivamente instalado, o botão não faz mais sentido.
    window.addEventListener('appinstalled', function () {
        eventoInstalacao = null;
        removerBotao();
    });

    document.addEventListener('DOMContentLoaded', function () {
        if (jaEstaInstalado() || !podeMostrarBotaoInstalar()) {
            return;
        }

        criarBotao();
    });
})();
