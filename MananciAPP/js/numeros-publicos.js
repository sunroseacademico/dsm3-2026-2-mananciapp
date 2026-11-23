// Números reais nos cartões da página inicial pública (index.html), vindos de
// ajax_numeros_publicos.php. Sem resposta, os cartões ficam com os textos
// que já estão no HTML (sem número inventado).
//   <div class="hero-stats" data-numeros-publicos data-url="ajax_numeros_publicos.php">
(function () {
    var caixa = document.querySelector('[data-numeros-publicos]');
    if (!caixa || !window.fetch) return;
    fetch(caixa.getAttribute('data-url') || 'ajax_numeros_publicos.php')
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (dados) {
            if (!dados || !dados.ok || !dados.cartoes || !dados.cartoes.length) return;
            var cartoes = caixa.querySelectorAll('.stat-card');
            dados.cartoes.forEach(function (c, i) {
                var cartao = cartoes[i];
                if (!cartao) return;
                cartao.querySelector('strong').textContent = c.numero;
                cartao.querySelector('span').textContent = c.texto;
            });
        })
        .catch(function () {});
})();
