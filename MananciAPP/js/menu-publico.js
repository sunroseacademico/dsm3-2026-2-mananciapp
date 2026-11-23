// Menu das páginas públicas no celular (index.html, sobre.php, cursos.php,
// ao-vivo.php, como-instalar.php). Abaixo de 900 px, os 7 links quebravam em
// várias linhas num cabeçalho fixo que tomava boa parte da tela, com alvos
// de toque pequenos. Agora ficam o logo, "Entrar" e um botão ☰ que abre o
// resto. CSS em css/inicio.css ("Menu do celular"). Sem JavaScript, o
// cabeçalho continua como antes.
(function () {
    var shell = document.querySelector('header .header-shell');
    var nav = shell && shell.querySelector('nav');
    if (!nav) return;

    document.documentElement.classList.add('menu-publico');
    if (!nav.id) nav.id = 'menuPublico';

    var acoes = document.createElement('div');
    acoes.className = 'menu-publico-acoes';

    var login = nav.querySelector('a.nav-login');
    if (login) {
        var entrar = document.createElement('a');
        entrar.href = login.getAttribute('href');
        entrar.className = 'menu-publico-entrar';
        entrar.textContent = 'Entrar';
        acoes.appendChild(entrar);
    }

    var botao = document.createElement('button');
    botao.type = 'button';
    botao.className = 'menu-publico-botao';
    botao.setAttribute('aria-controls', nav.id);
    botao.setAttribute('aria-expanded', 'false');
    botao.setAttribute('aria-label', 'Abrir o menu');
    var icone = document.createElement('i');
    icone.className = 'fas fa-bars';
    icone.setAttribute('aria-hidden', 'true');
    botao.appendChild(icone);
    acoes.appendChild(botao);

    shell.insertBefore(acoes, nav);

    function abrir(sim) {
        nav.classList.toggle('aberto', sim);
        botao.setAttribute('aria-expanded', sim ? 'true' : 'false');
        botao.setAttribute('aria-label', sim ? 'Fechar o menu' : 'Abrir o menu');
        icone.className = sim ? 'fas fa-xmark' : 'fas fa-bars';
    }

    botao.addEventListener('click', function () {
        abrir(!nav.classList.contains('aberto'));
    });
    nav.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('a')) abrir(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && nav.classList.contains('aberto')) {
            abrir(false);
            botao.focus();
        }
    });
})();
