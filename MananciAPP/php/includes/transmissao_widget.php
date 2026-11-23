<?php
// Widget de transmissão ao vivo (vídeo embutido + chat) — compartilhado
// entre a página pública (ao-vivo.php) e a versão dentro da área do fiel
// (php/fiel/ao_vivo.php), pra não ter duas cópias divergindo com o tempo.
//
// Espera em escopo (definidas por quem faz o include):
//   $transmissao         - array vindo de transmissao_obter_atual_cache($conn), ou null
//   $pode_assistir        - bool vindo de transmissao_pode_assistir($transmissao)
//   $comentarios_iniciais - a 'lista' de transmissao_chat_lista() (id, nome, mensagem, criado_em)
//   $usuario_logado      - bool, isset($_SESSION['Usuario_logado'])
//
// Estilos em css/transmissao.css (linkar no <head> de quem inclui isso).
// Todo link/URL aqui dentro usa site_url() (absoluto) em vez de caminho
// relativo, porque esse mesmo bloco é incluído de profundidades diferentes
// (raiz do site e php/fiel/) — um caminho relativo fixo quebraria em um
// dos dois lugares.
?>
<?php if (!$transmissao) { ?>
<?php $horarios_cultos = igreja_horarios(); ?>
<section class="estado-vazio">
    <div class="icone-vazio">
        <i class="fas fa-satellite-dish"></i>
    </div>
    <h2>Nenhuma transmissão ao vivo agora</h2>
    <?php if ($horarios_cultos) { ?>
        <p>Nossos cultos:</p>
        <ul class="horarios-cultos">
            <?php foreach ($horarios_cultos as $horario_culto) { ?><li><?php echo htmlspecialchars($horario_culto); ?></li><?php } ?>
        </ul>
    <?php } ?>
    <p>Quando começar uma transmissão, esta página mostra o vídeo e o chat. Com as notificações do app ativadas no seu celular, você também recebe um aviso na hora.</p>
    <span class="aviso-notificacao"><i class="fas fa-bell"></i> Ative as notificações em Configurações para ser avisado</span>
</section>
<?php } elseif (!$pode_assistir) { ?>
<section class="estado-vazio">
    <div class="icone-vazio">
        <i class="fas fa-lock"></i>
    </div>
    <h2>Transmissão restrita</h2>
    <?php if ($transmissao['idtr_audiencia'] === 'ADMINS') { ?>
        <p>Essa transmissão é restrita à equipe administrativa.</p>
        <div class="login-para-comentar" style="margin-top:1.2rem; display:inline-flex;">
            <span>Se você é da equipe, faça login com sua conta de admin.</span>
            <a href="<?php echo htmlspecialchars(site_url('login.php?voltar=' . rawurlencode('php/fiel/ao_vivo.php'))); ?>" class="btn-entrar">Entrar</a>
        </div>
    <?php } else { ?>
        <p>Essa transmissão é restrita a fiéis cadastrados.</p>
        <div class="login-para-comentar" style="margin-top:1.2rem; display:inline-flex;">
            <span>Faça login pra assistir.</span>
            <a href="<?php echo htmlspecialchars(site_url('login.php?voltar=' . rawurlencode('php/fiel/ao_vivo.php'))); ?>" class="btn-entrar">Entrar</a>
        </div>
    <?php } ?>
</section>
<?php } elseif ($transmissao['idtr_status'] === 'AGENDADA') { ?>
<section class="estado-vazio">
    <div class="icone-vazio">
        <i class="fas fa-clock"></i>
    </div>
    <h2><?php echo htmlspecialchars($transmissao['idtr_titulo']); ?></h2>
    <p>Essa transmissão ainda não começou. Pode deixar esta página aberta: quando ela ficar ao vivo, a página atualiza sozinha e mostra o vídeo e o chat.</p>
    <span class="aviso-notificacao"><i class="fas fa-bell"></i> Fique de olho nas notificações do app</span>
</section>
<?php } else { ?>
<section class="transmissao-shell">
    <div class="transmissao-topo">
        <h2><?php echo htmlspecialchars($transmissao['idtr_titulo']); ?></h2>
        <?php if ($transmissao['idtr_status'] === 'AO_VIVO') { ?>
            <span class="badge-ao-vivo"><span class="live-dot"></span> Ao vivo agora</span>
        <?php } else { ?>
            <span class="badge-replay"><i class="fas fa-clock-rotate-left"></i> Gravação do culto</span>
        <?php } ?>
    </div>

    <div class="transmissao-corpo">
        <div class="player-wrap">
            <iframe
                src="https://www.youtube-nocookie.com/embed/<?php echo urlencode($transmissao['idtr_youtube_id']); ?>?autoplay=1&modestbranding=1&rel=0&iv_load_policy=3"
                title="<?php echo htmlspecialchars($transmissao['idtr_titulo']); ?>"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen
            ></iframe>
        </div>

        <div class="comentarios-card">
            <h3><i class="fas fa-comments me-2"></i>Chat ao vivo</h3>

            <div id="lista-comentarios">
                <?php if (empty($comentarios_iniciais)) { ?>
                    <p class="sem-comentarios" id="sem-comentarios">Seja o primeiro a comentar.</p>
                <?php } else { ?>
                    <?php foreach ($comentarios_iniciais as $c) { ?>
                        <div class="comentario-item" data-id="<?php echo (int) $c['id']; ?>">
                            <strong><?php echo htmlspecialchars((string) $c['nome']); ?></strong>
                            <span class="comentario-hora"><?php echo htmlspecialchars((string) $c['criado_em']); ?></span>
                            <div class="comentario-texto"><?php echo nl2br(htmlspecialchars((string) $c['mensagem'])); ?></div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>

            <?php if ($transmissao['idtr_status'] !== 'AO_VIVO') { ?>
                <p class="sem-comentarios" style="margin:0;">Essa transmissão já foi encerrada — as mensagens ficam aqui como registro do culto.</p>
            <?php } elseif ($usuario_logado) { ?>
                <?php
                // Fiel bloqueado pela moderação: a caixa já vem travada e com
                // o aviso. Durante a live, o polling do chat
                // (transmissao_script.php) trava/destrava sozinho se o admin
                // bloquear ou desbloquear com a página aberta.
                $bloqueio_chat = transmissao_fiel_bloqueio_chat_info($conn, auth_resolve_fiel_id());
                ?>
                <div class="chat-bloqueado" id="aviso-bloqueio-chat"<?php echo $bloqueio_chat ? '' : ' hidden'; ?>>
                    <i class="fas fa-ban"></i>
                    <span id="texto-bloqueio-chat"><?php echo $bloqueio_chat ? htmlspecialchars(transmissao_texto_bloqueio_chat($bloqueio_chat)) : ''; ?></span>
                </div>
                <form id="form-comentario" class="form-comentario<?php echo $bloqueio_chat ? ' is-bloqueado' : ''; ?>">
                    <textarea id="campo-comentario" maxlength="500" placeholder="<?php echo $bloqueio_chat ? 'Chat bloqueado para você' : 'Escreva no chat...'; ?>" required<?php echo $bloqueio_chat ? ' disabled' : ''; ?>></textarea>
                    <button type="submit" id="btn-comentar"<?php echo $bloqueio_chat ? ' disabled' : ''; ?>><i class="fas fa-paper-plane"></i></button>
                </form>
                <div class="comentario-erro" id="erro-comentario"></div>
            <?php } else { ?>
                <div class="login-para-comentar">
                    <span><i class="fas fa-lock me-2"></i>Faça login para usar o chat. Qualquer pessoa pode assistir e ler.</span>
                    <a href="<?php echo htmlspecialchars(site_url('login.php?voltar=' . rawurlencode('php/fiel/ao_vivo.php'))); ?>" class="btn-entrar">Entrar</a>
                </div>
            <?php } ?>
        </div>
    </div>
</section>
<?php } ?>
