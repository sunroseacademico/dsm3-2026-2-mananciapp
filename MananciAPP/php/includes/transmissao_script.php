<?php
// JS do widget de transmissão (polling do chat + envio de mensagem) —
// compartilhado entre ao-vivo.php e php/fiel/ao_vivo.php. Espera as mesmas
// variáveis de php/includes/transmissao_widget.php ($transmissao,
// $usuario_logado) já em escopo.
//
// site_url() é usado pras URLs dos endpoints (em vez de caminho relativo)
// porque esse script roda incluído de profundidades diferentes (raiz e
// php/fiel/) — um caminho relativo fixo (ex.: "ajax_transmissao_comentarios.php")
// funcionaria numa e quebraria na outra.
if ($pode_assistir && $transmissao && $transmissao['idtr_status'] === 'AO_VIVO') {
?>
<script nonce="<?php echo site_csp_nonce(); ?>">
    (function () {
        const TRANSMISSAO_ID = <?php echo (int) $transmissao['idtr_id']; ?>;
        const CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;
        const URL_COMENTARIOS = <?php echo json_encode(site_url('ajax_transmissao_comentarios.php')); ?>;
        const URL_ENVIAR = <?php echo json_encode(site_url('php/fiel/ajax_transmissao_comentar.php')); ?>;
        const lista = document.getElementById('lista-comentarios');

        // Essa página já sabe, direto do servidor, que está AO_VIVO agora —
        // acende o pontinho do menu na hora, sem depender de
        // js/live-indicator.js completar o fetch dele primeiro (numa falha
        // de rede pontual, o indicador não pode ficar apagado bem na tela
        // de quem está vendo a live tocando).
        document.querySelectorAll('.live-dot').forEach(el => el.classList.add('is-ao-vivo'));

        function formatarComentario(c) {
            const div = document.createElement('div');
            div.className = 'comentario-item';
            div.dataset.id = String(c.id);

            const nome = document.createElement('strong');
            nome.textContent = c.nome;

            const hora = document.createElement('span');
            hora.className = 'comentario-hora';
            hora.textContent = c.criado_em;

            const texto = document.createElement('div');
            texto.className = 'comentario-texto';
            texto.textContent = c.mensagem;

            div.appendChild(nome);
            div.appendChild(hora);
            div.appendChild(texto);
            return div;
        }

        // Trava/destrava a caixa do chat do próprio fiel conforme o bloqueio
        // da moderação (estado inicial já vem do servidor no widget).
        function aplicarBloqueioChat(bloqueado, texto) {
            const form = document.getElementById('form-comentario');
            const aviso = document.getElementById('aviso-bloqueio-chat');
            if (!form || !aviso) return;

            const campo = document.getElementById('campo-comentario');
            const botao = document.getElementById('btn-comentar');
            const erroEl = document.getElementById('erro-comentario');

            form.classList.toggle('is-bloqueado', bloqueado);
            campo.disabled = bloqueado;
            botao.disabled = bloqueado;
            campo.placeholder = bloqueado ? 'Chat bloqueado para você' : 'Escreva no chat...';
            aviso.hidden = !bloqueado;
            if (bloqueado) {
                campo.value = '';
                if (texto) document.getElementById('texto-bloqueio-chat').textContent = texto;
                if (erroEl) erroEl.textContent = '';
            }
        }

        // Polling econômico (300 pessoas na mesma live): manda a versão da
        // última lista recebida e, sem mudança, a resposta vem quase vazia;
        // o intervalo cai para 3 s quando chegam mensagens e sobe até 10 s
        // com o chat parado; a sessão (bloqueio do chat) só é conferida a
        // cada ~30 s, com ?b=1.
        const LOGADO = <?php echo !empty($usuario_logado) ? 'true' : 'false'; ?>;
        const INTERVALO_MIN = 3000;
        const INTERVALO_MAX = 10000;
        let versaoChat = '';
        let intervalo = 5000;
        let ultimaConsultaBloqueio = 0;

        async function atualizarComentarios() {
            let algoNovo = false;
            try {
                let url = URL_COMENTARIOS + '?idtr_id=' + TRANSMISSAO_ID;
                if (versaoChat) url += '&v=' + encodeURIComponent(versaoChat);
                if (LOGADO && Date.now() - ultimaConsultaBloqueio > 30000) {
                    url += '&b=1';
                    ultimaConsultaBloqueio = Date.now();
                }
                const resp = await fetch(url);
                const dados = await resp.json();
                if (!dados.ok) return false;

                if (dados.bloqueio) {
                    aplicarBloqueioChat(dados.bloqueio.bloqueado, dados.bloqueio.texto);
                }
                if (dados.igual) return false;
                versaoChat = dados.versao || '';

                const idsAtuais = new Set(dados.comentarios.map(c => String(c.id)));

                // Remove da tela quem foi ocultado pela moderação nesse meio-tempo.
                // A resposta vem de um cache de até 2 s: mensagem mais nova
                // que a última da resposta (a que o próprio fiel acabou de
                // mandar) ainda não está nela e fica na tela.
                const ultimoId = dados.comentarios.reduce((maior, c) => Math.max(maior, c.id), 0);
                lista.querySelectorAll('[data-id]').forEach(el => {
                    if (!idsAtuais.has(el.dataset.id) && Number(el.dataset.id) <= ultimoId) el.remove();
                });

                const idsRenderizados = new Set(Array.from(lista.querySelectorAll('[data-id]')).map(el => el.dataset.id));
                dados.comentarios.forEach(c => {
                    if (!idsRenderizados.has(String(c.id))) {
                        lista.appendChild(formatarComentario(c));
                        algoNovo = true;
                    }
                });

                const semComentarios = document.getElementById('sem-comentarios');
                if (dados.comentarios.length > 0 && semComentarios) {
                    semComentarios.remove();
                }

                if (algoNovo) {
                    lista.scrollTop = lista.scrollHeight;
                }
            } catch (e) {
                // Falha de rede pontual não deve travar a página — só tenta de novo no próximo ciclo.
            }
            return algoNovo;
        }

        // Pausa o polling com a aba em segundo plano (economiza fetch/consulta
        // ao banco sem necessidade) e já atualiza uma vez assim que ela volta
        // a ficar visível, em vez de esperar o próximo ciclo.
        let temporizador = null;
        function agendar(espera) {
            clearTimeout(temporizador);
            if (document.visibilityState === 'hidden') return;
            temporizador = setTimeout(async function () {
                const novo = await atualizarComentarios();
                intervalo = novo ? INTERVALO_MIN : Math.min(INTERVALO_MAX, Math.round(intervalo * 1.5));
                agendar(intervalo);
            }, espera);
        }
        agendar(intervalo);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                clearTimeout(temporizador);
            } else {
                intervalo = INTERVALO_MIN;
                agendar(0);
            }
        });

        const form = document.getElementById('form-comentario');
        if (form) {
            const campo = document.getElementById('campo-comentario');
            const botao = document.getElementById('btn-comentar');
            const erroEl = document.getElementById('erro-comentario');

            form.addEventListener('submit', async function (ev) {
                ev.preventDefault();
                const mensagem = campo.value.trim();
                if (mensagem === '') return;

                botao.disabled = true;
                erroEl.textContent = '';

                try {
                    const resp = await fetch(URL_ENVIAR, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            idtr_id: TRANSMISSAO_ID,
                            mensagem: mensagem,
                            csrf_token: CSRF_TOKEN,
                        }),
                    });
                    const dados = await resp.json();

                    if (dados.ok) {
                        campo.value = '';
                        const semComentarios = document.getElementById('sem-comentarios');
                        if (semComentarios) semComentarios.remove();
                        if (!lista.querySelector('[data-id="' + dados.comentario.id + '"]')) {
                            lista.appendChild(formatarComentario(dados.comentario));
                            lista.scrollTop = lista.scrollHeight;
                        }
                        // Conversa andando: volta a buscar mais rápido.
                        intervalo = INTERVALO_MIN;
                        agendar(INTERVALO_MIN);
                    } else if (dados.bloqueado) {
                        aplicarBloqueioChat(true, dados.erro);
                    } else {
                        erroEl.textContent = dados.erro || 'Não foi possível enviar a mensagem.';
                    }
                } catch (e) {
                    erroEl.textContent = 'Falha de conexão. Tente de novo.';
                } finally {
                    // Não reabilita se o envio acabou de revelar um bloqueio.
                    botao.disabled = campo.disabled;
                }
            });
        }
    })();
</script>
<?php
} elseif ($pode_assistir && $transmissao && $transmissao['idtr_status'] === 'AGENDADA') {
    // Live agendada com a página aberta: confere a cada 30 s e recarrega quando
    // ela ficar ao vivo (a tela promete isso). Usa o mesmo status em cache do
    // indicador do menu (ajax_transmissao_status.php), barato para todos.
?>
<script nonce="<?php echo site_csp_nonce(); ?>">
    (function () {
        var url = <?php echo json_encode(site_url('ajax_transmissao_status.php')); ?>;
        setInterval(function () {
            if (document.visibilityState === 'hidden') return;
            fetch(url)
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) { if (d && d.ao_vivo) location.reload(); })
                .catch(function () {});
        }, 30000);
    })();
</script>
<?php
}
?>
