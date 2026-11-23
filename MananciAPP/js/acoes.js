// Ações simples que antes ficavam em atributos de evento no HTML
// (onsubmit="return confirm(...)", onchange="this.form.submit()"...). A CSP
// das páginas PHP só roda script com nonce (ver site_csp_politica() em
// php/conexao.php), e atributo de evento não aceita nonce — então elas vêm
// para cá, por delegação no document. Incluído em toda página pelo
// php/includes/pwa_head.php.
//
//   <form data-confirmar="Excluir?">      pede confirmação antes de enviar
//   <select data-enviar-ao-mudar>         envia o formulário ao trocar a opção
//   <input data-so-digitos>               apaga o que não for dígito
//   <button data-imprimir>                abre a impressão
//   <video data-previa-inicio>            prévia do card: para em 0,15 s
//   <video data-previa-src="url">         só carrega a prévia perto da tela
//   <form data-avisar-ao-sair>            avisa ao sair da página com algo digitado e não salvo
//
// Também deixa para o resto do app (no lugar do alert()/confirm() do
// navegador, que aparecem como "fulano.onrender.com diz:" — assusta quem é
// mais velho e parece golpe — e travam a tela):
//   window.siteAvisar('Não deu certo, tente de novo.', 'erro'|'ok'|'info')
//   window.siteConfirmar('Excluir?', { botao: 'Excluir', perigo: true }).then(function (ok) { ... })
(function () {
    var estiloPronto = false;
    function estilo() {
        if (estiloPronto) return;
        estiloPronto = true;
        var css = document.createElement('style');
        css.textContent =
            '.site-avisos{position:fixed;left:50%;bottom:calc(84px + env(safe-area-inset-bottom));transform:translateX(-50%);z-index:3000;display:flex;flex-direction:column;gap:8px;width:min(92vw,440px);pointer-events:none}' +
            '.site-aviso{pointer-events:auto;display:flex;align-items:flex-start;gap:10px;padding:14px 16px;border-radius:14px;background:#1e293b;color:#f8fafc;box-shadow:0 12px 30px rgba(0,0,0,.35);font-size:1rem;line-height:1.4;border-left:5px solid #60a5fa}' +
            '.site-aviso.erro{border-left-color:#f87171}.site-aviso.ok{border-left-color:#34d399}' +
            '.site-aviso span{flex:1}.site-aviso button{background:none;border:0;color:inherit;font-size:1.3rem;line-height:1;min-width:44px;min-height:44px;margin:-12px -12px -12px 0;cursor:pointer}' +
            '.site-confirmar-fundo{position:fixed;inset:0;z-index:3001;background:rgba(2,6,23,.6);display:flex;align-items:center;justify-content:center;padding:16px}' +
            '.site-confirmar{background:#1e293b;color:#f8fafc;border-radius:18px;padding:24px;width:min(100%,420px);box-shadow:0 20px 50px rgba(0,0,0,.45)}' +
            '.site-confirmar p{margin:0 0 20px;font-size:1.08rem;line-height:1.5}' +
            '.site-confirmar-botoes{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}' +
            '.site-confirmar-botoes button{min-height:48px;padding:0 20px;border-radius:12px;font-size:1rem;font-weight:600;cursor:pointer;border:1px solid rgba(148,163,184,.5);background:transparent;color:inherit}' +
            '.site-confirmar-botoes .sim{background:#3b82f6;border-color:#3b82f6;color:#fff}.site-confirmar-botoes .sim.perigo{background:#dc2626;border-color:#dc2626}' +
            ':root[data-theme="light"] .site-aviso,:root[data-theme="light"] .site-confirmar{background:#fff;color:#0f172a}' +
            '@media (prefers-reduced-motion:no-preference){.site-aviso,.site-confirmar{animation:site-surgir .18s ease-out}}' +
            '@keyframes site-surgir{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}';
        document.head.appendChild(css);
    }

    // Aviso discreto embaixo da tela. Erro fica 10 s (quem lê devagar
    // termina de ler); os outros 6 s. Sempre com "x" para fechar.
    window.siteAvisar = function (texto, tipo) {
        estilo();
        tipo = tipo || 'info';
        var area = document.querySelector('.site-avisos');
        if (!area) {
            area = document.createElement('div');
            area.className = 'site-avisos';
            document.body.appendChild(area);
        }
        var aviso = document.createElement('div');
        aviso.className = 'site-aviso ' + tipo;
        aviso.setAttribute('role', tipo === 'erro' ? 'alert' : 'status');
        var msg = document.createElement('span');
        msg.textContent = String(texto || '');
        var fechar = document.createElement('button');
        fechar.type = 'button';
        fechar.setAttribute('aria-label', 'Fechar aviso');
        fechar.textContent = '×';
        fechar.addEventListener('click', function () { aviso.remove(); });
        aviso.appendChild(msg);
        aviso.appendChild(fechar);
        area.appendChild(aviso);
        setTimeout(function () { aviso.remove(); }, tipo === 'erro' ? 10000 : 6000);
    };

    // Confirmação no visual do app. Esc ou tocar fora = cancelar.
    window.siteConfirmar = function (texto, opcoes) {
        estilo();
        opcoes = opcoes || {};
        return new Promise(function (resolver) {
            var anterior = document.activeElement;
            var fundo = document.createElement('div');
            fundo.className = 'site-confirmar-fundo';
            var caixa = document.createElement('div');
            caixa.className = 'site-confirmar';
            caixa.setAttribute('role', 'alertdialog');
            caixa.setAttribute('aria-modal', 'true');
            var p = document.createElement('p');
            p.id = 'site-confirmar-texto';
            p.textContent = String(texto || 'Tem certeza?');
            caixa.setAttribute('aria-labelledby', p.id);
            var botoes = document.createElement('div');
            botoes.className = 'site-confirmar-botoes';
            var nao = document.createElement('button');
            nao.type = 'button';
            nao.textContent = opcoes.cancelar || 'Cancelar';
            var sim = document.createElement('button');
            sim.type = 'button';
            sim.className = 'sim' + (opcoes.perigo ? ' perigo' : '');
            sim.textContent = opcoes.botao || 'Confirmar';
            botoes.appendChild(nao);
            botoes.appendChild(sim);
            caixa.appendChild(p);
            caixa.appendChild(botoes);
            fundo.appendChild(caixa);
            document.body.appendChild(fundo);
            function fim(resposta) {
                document.removeEventListener('keydown', teclas, true);
                fundo.remove();
                if (anterior && anterior.focus) anterior.focus();
                resolver(resposta);
            }
            function teclas(e) {
                if (e.key === 'Escape') { e.preventDefault(); fim(false); }
                // Tab fica entre os dois botões.
                if (e.key === 'Tab') { e.preventDefault(); (document.activeElement === sim ? nao : sim).focus(); }
            }
            document.addEventListener('keydown', teclas, true);
            nao.addEventListener('click', function () { fim(false); });
            sim.addEventListener('click', function () { fim(true); });
            fundo.addEventListener('click', function (e) { if (e.target === fundo) fim(false); });
            sim.focus();
        });
    };

    // Texto que parece de exclusão ganha o botão vermelho.
    function ehPerigo(texto) {
        return /exclu|apag|remov|desconect|cancel|recus|sem volta/i.test(texto);
    }

    // Captura: roda antes dos outros "submit" da página. O envio é parado,
    // a confirmação aparece e, com "sim", o formulário é enviado de novo
    // (com o mesmo botão que foi clicado, para valer o name/value dele).
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.hasAttribute || !form.hasAttribute('data-confirmar')) {
            return;
        }
        if (form.getAttribute('data-confirmado') === '1') {
            form.removeAttribute('data-confirmado');
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        var texto = form.getAttribute('data-confirmar');
        var botao = e.submitter || null;
        window.siteConfirmar(texto, { botao: ehPerigo(texto) ? 'Sim, continuar' : 'Confirmar', perigo: ehPerigo(texto) }).then(function (ok) {
            if (!ok) return;
            form.setAttribute('data-confirmado', '1');
            if (form.requestSubmit) {
                form.requestSubmit(botao && botao.form === form ? botao : undefined);
            } else {
                form.submit();
            }
        });
    }, true);

    // Formulário com algo digitado e não salvo: o navegador pergunta antes
    // de sair da página (antes, um toque errado no menu perdia tudo).
    var sujos = [];
    document.addEventListener('input', function (e) {
        var form = e.target && e.target.form;
        if (form && form.hasAttribute('data-avisar-ao-sair') && sujos.indexOf(form) === -1) {
            sujos.push(form);
        }
    });
    document.addEventListener('submit', function (e) {
        if (!e.defaultPrevented) {
            sujos = sujos.filter(function (f) { return f !== e.target; });
        }
    });
    window.addEventListener('beforeunload', function (e) {
        if (sujos.length) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    document.addEventListener('change', function (e) {
        var campo = e.target;
        if (campo && campo.hasAttribute && campo.hasAttribute('data-enviar-ao-mudar') && campo.form) {
            campo.form.submit();
        }
    });

    document.addEventListener('input', function (e) {
        var campo = e.target;
        if (campo && campo.hasAttribute && campo.hasAttribute('data-so-digitos')) {
            campo.value = campo.value.replace(/\D/g, '');
        }
    });

    document.addEventListener('click', function (e) {
        var alvo = e.target && e.target.closest ? e.target.closest('[data-imprimir]') : null;
        if (alvo) {
            window.print();
        }
    });

    // Prévia em vídeo dos cards: mostra um quadro logo depois do início
    // (o primeiro costuma ser preto) e fica parada. "loadedmetadata" não
    // sobe pelo DOM, por isso a captura; os vídeos que já carregaram antes
    // deste script rodar são tratados logo abaixo.
    function pararPrevia(video) {
        if (video.currentTime < 0.15) {
            try { video.currentTime = 0.15; } catch (erro) {}
        }
        video.pause();
    }
    document.addEventListener('loadedmetadata', function (e) {
        var video = e.target;
        if (video && video.hasAttribute && video.hasAttribute('data-previa-inicio')) {
            pararPrevia(video);
        }
    }, true);
    // <video data-previa-src="...">: o endereço só entra quando o card chega
    // perto da tela. Antes, cada card baixava um pedaço do vídeo ao abrir a
    // página, mesmo os que a pessoa nunca rolava até ver.
    function carregarPrevia(video) {
        var src = video.getAttribute('data-previa-src');
        if (!src) return;
        video.removeAttribute('data-previa-src');
        video.preload = 'metadata';
        video.src = src;
    }
    var observadorPrevias = 'IntersectionObserver' in window
        ? new IntersectionObserver(function (entradas) {
            entradas.forEach(function (e) {
                if (e.isIntersecting) {
                    observadorPrevias.unobserve(e.target);
                    carregarPrevia(e.target);
                }
            });
        }, { rootMargin: '200px' })
        : null;
    function previasJaCarregadas() {
        document.querySelectorAll('video[data-previa-src]').forEach(function (video) {
            if (observadorPrevias) {
                observadorPrevias.observe(video);
            } else {
                carregarPrevia(video);
            }
        });
        document.querySelectorAll('video[data-previa-inicio]').forEach(function (video) {
            if (video.readyState >= 1) {
                pararPrevia(video);
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', previasJaCarregadas);
    } else {
        previasJaCarregadas();
    }
})();
