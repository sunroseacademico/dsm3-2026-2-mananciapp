// Erros de JavaScript das páginas viram alerta no Sentry, pelo servidor
// (ajax_erro_js.php). Incluído pelo php/includes/pwa_head.php só quando
// SENTRY_DSN existe no Render.
//   <script src="js/erros.js" data-url="ajax_erro_js.php" defer></script>
//
// Só erros dos scripts do próprio site: de serviço de fora (OneSignal,
// YouTube) o navegador só diz "Script error." e não há o que corrigir aqui.
// No máximo 3 por página e sem repetir o mesmo erro.
(function () {
    var script = document.currentScript;
    var url = (script && script.getAttribute('data-url')) || 'ajax_erro_js.php';
    var enviados = 0;
    var vistos = {};

    function enviar(mensagem, arquivo, linha) {
        if (!mensagem || enviados >= 3) return;
        var chave = mensagem + '|' + arquivo + '|' + linha;
        if (vistos[chave]) return;
        vistos[chave] = true;
        enviados++;
        var corpo = JSON.stringify({
            mensagem: String(mensagem).slice(0, 500),
            arquivo: String(arquivo || '').slice(0, 300),
            linha: linha || 0,
            pagina: location.pathname
        });
        try {
            if (navigator.sendBeacon) {
                navigator.sendBeacon(url, new Blob([corpo], { type: 'application/json' }));
            } else {
                fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: corpo, keepalive: true, credentials: 'same-origin' });
            }
        } catch (e) {}
    }

    window.addEventListener('error', function (e) {
        // Erro de carregar imagem/vídeo também passa aqui (sem mensagem): ignora.
        if (!e || !e.message || e.message === 'Script error.') return;
        if (e.filename && e.filename.indexOf(location.origin) !== 0) return;
        enviar(e.message, e.filename, e.lineno);
    });
    window.addEventListener('unhandledrejection', function (e) {
        var motivo = e && e.reason;
        var texto = motivo && motivo.message ? motivo.message : (typeof motivo === 'string' ? motivo : '');
        // Falha de rede (fetch) não é bug do código.
        if (!texto || /Failed to fetch|NetworkError|Load failed|AbortError/i.test(texto)) return;
        enviar('Promise rejeitada: ' + texto, motivo && motivo.stack ? String(motivo.stack).split('\n')[1] || '' : '', 0);
    });
})();
