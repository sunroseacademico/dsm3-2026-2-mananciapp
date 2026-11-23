// Dados da igreja na página inicial pública (index.html), vindos de
// ajax_igreja_publica.php (Admin → Identidade da igreja). Sem resposta ou
// sem dados, a página fica como está (os blocos de visita começam escondidos).
//   <span data-igreja-texto="nome">Missão Manancial</span>   troca o texto
//   <p data-igreja-texto="frase" hidden></p>                 aparece se houver
//   <section data-igreja-visita hidden> … </section>         horários, endereço, mapa e redes
// Tudo montado com textContent (nada de HTML vindo de fora) e só links https.
(function () {
    if (!window.fetch) return;
    var alvo = document.querySelector('[data-igreja-url]');
    var url = alvo ? alvo.getAttribute('data-igreja-url') : 'ajax_igreja_publica.php';

    function link(href) {
        return typeof href === 'string' && /^https:\/\//i.test(href) ? href : '';
    }

    function preencher(igreja) {
        document.querySelectorAll('[data-igreja-texto]').forEach(function (el) {
            var valor = igreja[el.getAttribute('data-igreja-texto')];
            if (typeof valor === 'string' && valor !== '') {
                el.textContent = valor;
                el.hidden = false;
            }
        });

        var visita = document.querySelector('[data-igreja-visita]');
        if (!visita) return;
        var temAlgo = false;

        var lista = visita.querySelector('[data-igreja-horarios]');
        if (lista && igreja.horarios && igreja.horarios.length) {
            lista.innerHTML = '';
            igreja.horarios.forEach(function (h) {
                var li = document.createElement('li');
                li.textContent = h;
                lista.appendChild(li);
            });
            lista.closest('[data-igreja-bloco]').hidden = false;
            temAlgo = true;
        }

        var endereco = visita.querySelector('[data-igreja-endereco]');
        if (endereco && igreja.endereco) {
            endereco.textContent = igreja.endereco;
            var mapa = visita.querySelector('[data-igreja-mapa]');
            if (mapa && link(igreja.mapa_url)) {
                mapa.href = link(igreja.mapa_url);
                mapa.hidden = false;
            }
            endereco.closest('[data-igreja-bloco]').hidden = false;
            temAlgo = true;
        }

        var redes = visita.querySelector('[data-igreja-redes]');
        if (redes) {
            var itens = [
                ['whatsapp', 'fab fa-whatsapp', 'WhatsApp'],
                ['instagram', 'fab fa-instagram', 'Instagram'],
                ['youtube', 'fab fa-youtube', 'YouTube'],
                ['facebook', 'fab fa-facebook', 'Facebook'],
                ['site', 'fas fa-globe', 'Site da igreja']
            ];
            itens.forEach(function (item) {
                var href = link(igreja[item[0]]);
                if (!href) return;
                var a = document.createElement('a');
                a.href = href;
                a.target = '_blank';
                a.rel = 'noopener';
                a.className = 'visita-rede';
                var i = document.createElement('i');
                i.className = item[1];
                i.setAttribute('aria-hidden', 'true');
                a.appendChild(i);
                a.appendChild(document.createTextNode(' ' + item[2]));
                redes.appendChild(a);
            });
            if (redes.children.length) {
                redes.closest('[data-igreja-bloco]').hidden = false;
                temAlgo = true;
            }
        }

        if (temAlgo) visita.hidden = false;
    }

    fetch(url || 'ajax_igreja_publica.php')
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (dados) { if (dados && dados.ok && dados.igreja) preencher(dados.igreja); })
        .catch(function () {});
})();
