// Mural de testemunhos (T26): voto "Edificante" e "Ler mais" nos cards
// (php/fiel/includes/card_testemunho.php). A página informa o token CSRF e
// o endereço do endpoint no elemento com [data-testemunhos].
(function () {
    var raiz = document.querySelector('[data-testemunhos]');
    if (!raiz) return;
    var csrfToken = raiz.getAttribute('data-csrf');
    var endpoint = raiz.getAttribute('data-endpoint') || 'ajax_testemunhos.php';

    // Voto: liga/desliga; o número volta do servidor.
    document.addEventListener('click', function (e) {
        var botao = e.target.closest('[data-testemunho-voto]');
        if (!botao || botao.disabled || botao.dataset.enviando) return;
        var votar = !botao.classList.contains('is-ativo');
        botao.dataset.enviando = '1';
        botao.classList.add('is-enviando');
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'votar', idts_id: parseInt(botao.dataset.testemunhoVoto, 10), votar: votar, csrf_token: csrfToken })
        })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (!resp.ok) { (window.siteAvisar || window.alert)(resp.erro || 'Não foi possível votar.', 'erro'); return; }
                // O mesmo testemunho pode aparecer mais de uma vez na página.
                document.querySelectorAll('[data-testemunho-voto="' + botao.dataset.testemunhoVoto + '"]').forEach(function (b) {
                    b.classList.toggle('is-ativo', resp.meu_voto);
                    b.setAttribute('aria-pressed', resp.meu_voto ? 'true' : 'false');
                    b.querySelector('.testemunho-voto-num').textContent = resp.votos;
                });
            })
            .catch(function () { (window.siteAvisar || window.alert)('Falha de conexão. Tente de novo.', 'erro'); })
            .then(function () {
                delete botao.dataset.enviando;
                botao.classList.remove('is-enviando');
            });
    });

    // "Ler mais": só aparece quando o texto passa do limite de linhas.
    function prepararLerMais() {
        document.querySelectorAll('.testemunho-texto.is-recolhivel').forEach(function (p) {
            var botao = p.parentNode.querySelector('[data-ler-mais]');
            if (!botao || p.classList.contains('is-aberto')) return;
            botao.hidden = p.scrollHeight <= p.clientHeight + 2;
        });
    }
    document.addEventListener('click', function (e) {
        var botao = e.target.closest('[data-ler-mais]');
        if (!botao) return;
        var p = botao.parentNode.querySelector('.testemunho-texto');
        var aberto = p.classList.toggle('is-aberto');
        botao.textContent = aberto ? 'Ler menos' : 'Ler mais';
    });
    prepararLerMais();
    window.addEventListener('resize', prepararLerMais);

    // Veio de um link para um testemunho (#t-ID, ex.: aviso de publicado ou
    // a home): abre e destaca. O "Ver mais" também usa #t-ID só para manter
    // a posição — esse vem com ?mostrar= e não destaca.
    if (/^#t-\d+$/.test(location.hash) && !/[?&]mostrar=/.test(location.search)) {
        var alvo = document.querySelector(location.hash);
        if (alvo) {
            var p = alvo.querySelector('.testemunho-texto');
            var lerMais = alvo.querySelector('[data-ler-mais]');
            if (p) p.classList.add('is-aberto');
            if (lerMais) { lerMais.hidden = false; lerMais.textContent = 'Ler menos'; }
            alvo.classList.add('is-alvo');
            setTimeout(function () { alvo.classList.remove('is-alvo'); }, 4500);
        }
    }
})();
