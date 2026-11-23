// Módulo de JavaScript para interações do cliente
document.addEventListener("DOMContentLoaded", function() {
    // Rolagem horizontal dos carrosséis: só assume o wheel quando o usuário
    // segura Shift (convenção comum p/ scroll horizontal) ou quando o gesto já
    // é horizontal (trackpad). Sem isso, o scroll vertical da página ficava
    // "preso" sempre que o mouse passava sobre uma fileira de cursos.
    const carousels = document.querySelectorAll('.stream-carousel');

    carousels.forEach(carousel => {
        carousel.addEventListener('wheel', (evt) => {
            const gestoHorizontal = Math.abs(evt.deltaX) > Math.abs(evt.deltaY);
            if (!evt.shiftKey && !gestoHorizontal) {
                return; // deixa o scroll vertical normal da página acontecer
            }
            evt.preventDefault();
            carousel.scrollLeft += (gestoHorizontal ? evt.deltaX : evt.deltaY);
        });
    });

    // Setas de navegação dos carrosséis de cursos (Home/página de curso)
    document.querySelectorAll('.carousel-arrow').forEach(botao => {
        botao.addEventListener('click', () => {
            const wrap = botao.closest('.stream-carousel-wrap');
            const carousel = wrap ? wrap.querySelector('.stream-carousel') : null;
            if (!carousel) return;
            const distancia = Math.round(carousel.clientWidth * 0.8) || 320;
            carousel.scrollBy({ left: botao.classList.contains('prev') ? -distancia : distancia, behavior: 'smooth' });
        });
    });

    // Busca ícones de um arquivo JSON local. Caminho relativo (sem
    // PWA_BASE_URL) sempre dava 404 fora da raiz do site (ex.: em
    // php/fiel/dashboard.php ele tentava buscar "php/fiel/icons.json", que
    // nunca existiu — o arquivo real é assets/icons.json). Falhava em
    // silêncio (o .catch abaixo só loga), então os ícones dessas telas nunca
    // eram trocados, mas nada quebrava visivelmente — por isso passou
    // despercebido até aparecer como pedido vermelho no Network.
    fetch((window.PWA_BASE_URL || '../../') + 'assets/icons.json')
        .then(res => {
            if (!res.ok) {
                throw new Error('Network response was not ok');
            }
            return res.json();
        })
        .then(data => {
            // Substitui os ícones se encontrados nos elementos
            if (data.search && document.getElementById('searchIcon')) {
                document.getElementById('searchIcon').textContent = data.search;
            }
            if (data.notification && document.getElementById('notifIcon')) {
                document.getElementById('notifIcon').textContent = data.notification;
            }
            if (data.book && document.getElementById('brandIcon')) {
                document.getElementById('brandIcon').textContent = data.book;
            }
            if (data.profile && document.getElementById('profileIcon')) {
                // profileIcon é um div; insere emoji ou svg dentro
                const prof = document.getElementById('profileIcon');
                prof.textContent = data.profile;
                prof.style.backgroundColor = 'transparent';
                prof.style.fontSize = '1.25rem';
                prof.style.display = 'flex';
                prof.style.justifyContent = 'center';
                prof.style.alignItems = 'center';
            }
        })
        .catch(err => {
            console.error('Erro na API de ícones:', err);
        });
});