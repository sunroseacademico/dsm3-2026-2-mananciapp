// Liga/desliga o "pisca" do ponto "Ao Vivo" (.live-dot) no menu, em
// qualquer página que carregue esse script — landing pública, área do
// fiel e admin. A cor do ponto já é fixa via CSS; aqui só adicionamos/
// removemos a classe "is-ao-vivo", que é o que ativa a animação (ver
// regra ".live-dot.is-ao-vivo::before" em cada página/css).
//
// A URL do endpoint muda de acordo com a profundidade da página que
// inclui esse script (raiz, php/fiel/ ou php/admin/), então quem inclui
// passa o caminho certo via atributo, ex.:
//   <script src="js/live-indicator.js" data-status-url="ajax_transmissao_status.php"></script>
// Sem o atributo, assume que está na raiz do site.
(function () {
    var script = document.currentScript;
    var statusUrl = (script && script.dataset.statusUrl) || 'ajax_transmissao_status.php';

    function aplicar(aoVivo) {
        document.querySelectorAll('.live-dot').forEach(function (el) {
            el.classList.toggle('is-ao-vivo', aoVivo);
        });
    }

    function verificar() {
        // Aba em segundo plano: ninguém está vendo o menu. Ao voltar, o
        // visibilitychange abaixo confere na hora.
        if (document.visibilityState === 'hidden') return;
        fetch(statusUrl)
            .then(function (resp) { return resp.json(); })
            .then(function (dados) { aplicar(!!(dados && dados.ao_vivo)); })
            .catch(function () {
                // Falha de rede pontual: não desliga o indicador à toa, só
                // tenta de novo rapidinho (em vez de esperar o próximo
                // ciclo de 1 minuto inteiro) e segue o padrão normal depois.
                setTimeout(verificar, 5000);
            });
    }

    verificar();
    setInterval(verificar, 60000);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') verificar();
    });
})();
