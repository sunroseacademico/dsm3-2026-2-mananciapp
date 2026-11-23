// ==========================================
// LAYOUT DO PAINEL ADMIN — mostrar/ocultar sidebar
// Desktop: recolhe a sidebar e lembra a escolha no navegador.
// Mobile (≤768px): abre/fecha a sidebar como gaveta sobreposta.
// ==========================================

(function () {
    var CHAVE = 'mananciapp-admin-sidebar';
    var mobile = window.matchMedia('(max-width: 768px)');
    var body = document.body;
    var botao = document.getElementById('sidebarToggleBtn');
    var backdrop = document.getElementById('sidebarBackdrop');

    if (!botao) {
        return;
    }

    function atualizarAria() {
        var aberta = mobile.matches
            ? body.classList.contains('sidebar-open')
            : !body.classList.contains('sidebar-collapsed');
        botao.setAttribute('aria-expanded', aberta ? 'true' : 'false');
        botao.setAttribute('aria-label', aberta ? 'Ocultar menu' : 'Mostrar menu');
    }

    function fecharGaveta() {
        body.classList.remove('sidebar-open');
        atualizarAria();
    }

    botao.addEventListener('click', function () {
        if (mobile.matches) {
            body.classList.toggle('sidebar-open');
        } else {
            var recolhida = body.classList.toggle('sidebar-collapsed');
            try {
                localStorage.setItem(CHAVE, recolhida ? 'recolhida' : 'aberta');
            } catch (e) {
                // navegador sem localStorage: só não lembra a escolha
            }
        }
        atualizarAria();
    });

    if (backdrop) {
        backdrop.addEventListener('click', fecharGaveta);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && body.classList.contains('sidebar-open')) {
            fecharGaveta();
        }
    });

    mobile.addEventListener('change', fecharGaveta);

    atualizarAria();

    // Libera as transições depois do primeiro render (ver admin_layout.php)
    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            body.classList.remove('sidebar-no-anim');
        });
    });
})();

// Modais do Bootstrap renderizados dentro do conteúdo da página: move pro
// <body> antes de abrir, pra nenhum ancestral com transform/animation prender
// o modal embaixo do backdrop (a tela "travava" sem deixar clicar em nada).
document.addEventListener('show.bs.modal', function (evento) {
    var modal = evento.target;
    if (modal && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
});
