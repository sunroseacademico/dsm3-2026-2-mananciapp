// "Pessoas online": manda um "estou aqui" pro servidor a cada minuto
// enquanto a aba está visível e atualiza o contador/lista de quem está
// online (navbar do fiel e header do admin). Ver ajax_presenca.php.
//
// Quem inclui passa a URL do endpoint via atributo, igual ao
// js/live-indicator.js:
//   <script src=".../js/presenca.js" data-presenca-url=".../ajax_presenca.php"></script>
//
// Elementos que o script preenche (opcionais, em qualquer quantidade):
//   [data-presenca-contador]  recebe o número de pessoas online
//   [data-presenca-lista]     recebe a lista (<li> por pessoa)
// Telas que queiram montar algo próprio (ex.: tabela do admin) ouvem o
// evento "presenca:atualizada" no document — detail = resposta do servidor.
(function () {
    var script = document.currentScript;
    var url = (script && script.dataset.presencaUrl) || 'ajax_presenca.php';
    var INTERVALO = 60000;
    var timer = null;
    var parado = false;

    function paginaAtual() {
        return (document.title || '').replace(/^Missão Manancial\s*\|\s*/i, '').trim();
    }

    function iniciais(nome) {
        var partes = String(nome || '').trim().split(/\s+/);
        var r = (partes[0] || '').charAt(0) + (partes.length > 1 ? partes[partes.length - 1].charAt(0) : '');
        return (r || '?').toUpperCase();
    }

    function tempoRelativo(segundos) {
        if (segundos < 60) return 'agora';
        return 'há ' + Math.floor(segundos / 60) + ' min';
    }

    function montarItem(p) {
        var li = document.createElement('li');
        li.className = 'presenca-item';

        var avatar = document.createElement('span');
        avatar.className = 'presenca-avatar';
        avatar.textContent = iniciais(p.nome);
        if (p.foto) {
            var foto = document.createElement('img');
            foto.src = p.foto;
            foto.alt = '';
            foto.loading = 'lazy';
            // Foto não carregou: fica com as iniciais.
            foto.onerror = function () { foto.remove(); };
            avatar.appendChild(foto);
        }
        li.appendChild(avatar);

        var corpo = document.createElement('div');
        corpo.className = 'presenca-corpo';

        var nome = document.createElement('div');
        nome.className = 'presenca-nome';
        nome.textContent = p.nome + (p.eu ? ' (você)' : '');
        if (p.equipe) {
            var tag = document.createElement('span');
            tag.className = 'presenca-tag';
            tag.textContent = 'Equipe';
            nome.appendChild(tag);
        }
        corpo.appendChild(nome);

        if (typeof p.segundos === 'number') {
            var det = document.createElement('div');
            det.className = 'presenca-detalhe';
            det.textContent = (p.pagina ? p.pagina + ' · ' : '') + tempoRelativo(p.segundos);
            corpo.appendChild(det);
        }

        li.appendChild(corpo);
        return li;
    }

    function aplicar(dados) {
        document.querySelectorAll('[data-presenca-contador]').forEach(function (el) {
            el.textContent = dados.total;
        });

        document.querySelectorAll('[data-presenca-lista]').forEach(function (lista) {
            lista.innerHTML = '';
            if (!dados.pessoas.length) {
                var vazio = document.createElement('li');
                vazio.className = 'presenca-vazio';
                vazio.textContent = 'Ninguém online agora.';
                lista.appendChild(vazio);
                return;
            }
            dados.pessoas.forEach(function (p) { lista.appendChild(montarItem(p)); });
        });

        document.dispatchEvent(new CustomEvent('presenca:atualizada', { detail: dados }));
    }

    function bater() {
        if (parado || document.visibilityState === 'hidden') return;

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pagina: paginaAtual() }),
            credentials: 'same-origin'
        })
            .then(function (resp) {
                // Sessão expirou/foi encerrada: para de bater (a próxima
                // navegação já leva a pessoa pro login).
                if (resp.status === 401 || resp.status === 403) { parado = true; return null; }
                return resp.json();
            })
            .then(function (dados) { if (dados && dados.ok) aplicar(dados); })
            .catch(function () {});
    }

    function agendar() {
        clearInterval(timer);
        timer = setInterval(bater, INTERVALO);
    }

    // Voltou pra aba: atualiza na hora (a lista pode estar bem velha).
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            bater();
            agendar();
        }
    });

    bater();
    agendar();

    window.Presenca = { atualizar: bater, tempoRelativo: tempoRelativo, iniciais: iniciais };
})();
