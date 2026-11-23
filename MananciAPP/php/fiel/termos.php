<?php
include("../conexao.php");

// Sem login, o termo abre só para leitura (link do cadastro, antes de ter
// conta); logado, é a tela de aceite de sempre.
$termos_so_leitura = !isset($_SESSION['Usuario_logado']);
if (!$termos_so_leitura) {
    auth_require();
}

$fiel_id = $termos_so_leitura ? 0 : auth_resolve_fiel_id();
$erro = "";

if (!$termos_so_leitura && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "aceitar") {
    if (!csrf_validar()) {
        $erro = "Sessão expirada. Atualize a página e tente de novo.";
    } elseif (empty($_POST["aceite"])) {
        $erro = "Você precisa marcar a caixa confirmando que leu e concorda com o termo pra continuar.";
    } elseif (!db_query($conn, "UPDATE ID_FIEL SET IDF_TERMOS_VERSAO_ACEITA = ?, IDF_TERMOS_ACEITOS_EM = NOW() WHERE IDF_ID = ?", array(TERMOS_VERSAO_ATUAL, $fiel_id))) {
        // Sem conferir, o aceite não gravado mandava para a Home, que mandava
        // de volta para o termo: um laço sem mensagem nenhuma.
        $erro = site_erro_banco("Não foi possível registrar o seu aceite.");
    } else {
        // O aceite deste termo inclui a Política de Privacidade: quem se
        // cadastrou antes dela (seção 82) passa a ter a data gravada.
        if (lgpd_disponivel($conn)) {
            db_query($conn, "UPDATE ID_FIEL SET IDF_PRIVACIDADE_ACEITA_EM = COALESCE(IDF_PRIVACIDADE_ACEITA_EM, NOW()) WHERE IDF_ID = ?", array($fiel_id));
        }

        // Admin e fiel comum caem em dashboards diferentes — manda cada um
        // pro seu, igual auth_require() já faz em outras situações.
        if (($_SESSION['Usuario_tipo'] ?? '') === 'admin') {
            header('Location: ' . site_url('php/admin/dashboard.php'));
        } else {
            header('Location: ' . site_url('php/fiel/dashboard.php'));
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termo de Uso - Missão Manancial</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .termos-shell {
            max-width: 760px;
            width: 100%;
            background: var(--auth-bg, #1e293b);
            border: 1px solid var(--border-color, rgba(96,165,250,0.2));
            border-radius: 20px;
            padding: 2rem;
        }
        .termos-cabecalho { text-align: center; margin-bottom: 1.5rem; }
        .termos-cabecalho img { height: 48px; margin-bottom: 0.8rem; }
        .termos-cabecalho h1 { font-size: 1.4rem; margin-bottom: 0.3rem; }
        .termos-cabecalho p { color: var(--text-muted); font-size: 0.9rem; margin: 0; }
        .termos-texto {
            max-height: 360px;
            overflow-y: auto;
            padding: 1.2rem 1.4rem;
            border-radius: 14px;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border-color, rgba(96,165,250,0.2));
            margin-bottom: 1.3rem;
        }
        .termos-texto h2 { font-size: 1.02rem; margin: 1.3rem 0 0.5rem; color: var(--text-main); }
        .termos-texto h2:first-child { margin-top: 0; }
        .termos-texto p, .termos-texto li { color: var(--text-muted); font-size: 0.92rem; line-height: 1.6; }
        .termos-texto ul { padding-left: 1.2rem; margin-bottom: 0.8rem; }
        .termos-versao { text-align: center; color: var(--text-muted); font-size: 0.78rem; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="termos-shell">
        <div class="termos-cabecalho">
            <img src="../../assets/logo-sm.png" alt="Logo Missão Manancial">
            <h1>Termo de Uso e Consentimento</h1>
            <p><?php echo $termos_so_leitura ? 'Leia com calma: você aceita este termo ao criar sua conta.' : 'Precisamos que você leia e aceite antes de continuar — é rápido.'; ?></p>
        </div>

        <?php if ($erro !== "") { ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($erro); ?>
            </div>
        <?php } ?>

        <div class="termos-texto">
            <h2>1. Sobre este termo</h2>
            <p>Este documento explica como a plataforma MananciAPP da Missão Evangélica Manancial da Esperança ("a Igreja", "nós") funciona, quais dados seus são usados e, principalmente, como funcionam as transmissões ao vivo e o chat. Ao marcar a caixa no final e clicar em "Aceitar e continuar", você concorda com tudo abaixo.</p>

            <h2>2. Seu cadastro</h2>
            <p>Pra usar o app, guardamos os dados que você informou no cadastro (nome, e-mail, telefone, CPF, endereço, filial/função) e o que você gera usando a plataforma (progresso em cursos, certificados, comentários no chat, preferências de notificação). Esses dados são usados só pra operar o app e a comunicação da Igreja com você — não são vendidos nem repassados a terceiros fora do que for necessário pro funcionamento do serviço (ex.: envio de e-mail, notificações push). Os detalhes — quais dados, com quem, por quanto tempo e como baixar ou excluir os seus — estão na <a href="../../privacidade.php" class="auth-link">Política de Privacidade</a>.</p>

            <h2>3. Cursos e conteúdos</h2>
            <p>Os vídeos, materiais e certificados disponíveis são de uso pessoal, para seu crescimento espiritual — não podem ser copiados, revendidos ou redistribuídos fora da plataforma sem autorização da Igreja.</p>

            <h2>4. Transmissões ao vivo (lives)</h2>
            <p>A Igreja transmite cultos, estudos e eventos ao vivo pela plataforma. Sobre isso:</p>
            <ul>
                <li><strong>Imagem e voz:</strong> se você estiver presente fisicamente em um culto ou evento da Igreja que esteja sendo transmitido ao vivo ou gravado, sua imagem e voz podem aparecer na transmissão e na gravação, que ficam disponíveis para os participantes autorizados a assistir (conforme o público definido pela Igreja para cada transmissão: todos, só fiéis cadastrados, ou só administradores). Ao participar presencialmente de um evento divulgado como "transmitido ao vivo", você autoriza esse uso da sua imagem e voz, sem direito a remuneração, exclusivamente para essa finalidade religiosa/institucional.</li>
                <li><strong>Chat ao vivo:</strong> se você usar o chat durante uma transmissão, seu nome de cadastro aparece publicamente ao lado de cada mensagem, para quem estiver assistindo àquela transmissão (inclusive visitantes sem login, quando a transmissão for aberta a todos).</li>
                <li><strong>Moderação:</strong> mensagens que violem boa conduta, contenham ofensas, spam ou conteúdo impróprio podem ser ocultadas ou excluídas por um administrador a qualquer momento, e quem enviar esse tipo de mensagem pode ser impedido de comentar em qualquer transmissão futura, temporária ou permanentemente, a critério da Igreja.</li>
                <li><strong>Gravações:</strong> depois de encerrada, a transmissão costuma continuar disponível como gravação (replay), junto com o histórico do chat daquela transmissão.</li>
            </ul>

            <h2>5. Conduta esperada</h2>
            <p>Você é responsável pelo conteúdo que publica no chat e pelo uso da sua conta. Não é permitido usar o chat pra ofender, assediar, divulgar spam/propaganda ou compartilhar conteúdo que não tenha relação com o culto/evento em transmissão.</p>

            <h2>6. Notificações</h2>
            <p>Com sua permissão (concedida pelo navegador/app), enviamos notificações sobre novidades, aprovação de cadastro, início de transmissões ao vivo e outros avisos da Igreja. Você pode desativar isso nas configurações do seu dispositivo a qualquer momento.</p>

            <h2>7. Alterações deste termo</h2>
            <p>Se este termo mudar de forma relevante, vamos pedir um novo aceite no seu próximo acesso — você sempre pode consultar a versão vigente aqui mesmo, nesta tela.</p>

            <h2>8. Dúvidas</h2>
            <p>Qualquer dúvida sobre este termo ou sobre seus dados, fale com a secretaria/liderança da Igreja pelos canais de contato já disponíveis na plataforma.</p>
        </div>

        <?php if ($termos_so_leitura) { ?>
        <a href="register.php" class="btn btn-primary w-100"><i class="fas fa-arrow-left me-2"></i>Voltar ao cadastro</a>
        <?php } else { ?>
        <form method="POST">
            <?php echo csrf_campo(); ?>
            <input type="hidden" name="acao" value="aceitar">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="aceite" id="aceite" value="1" required>
                <label class="form-check-label" for="aceite">
                    Li e concordo com o Termo de Uso e Consentimento acima, inclusive sobre imagem/voz em transmissões e sobre o chat ao vivo, e com a <a href="../../privacidade.php" target="_blank" rel="noopener" class="auth-link">Política de Privacidade</a>, inclusive o uso dos meus dados de fé para participar da plataforma.
                </label>
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-check me-2"></i>Aceitar e continuar</button>
        </form>
        <?php } ?>

        <p class="termos-versao">Versão do termo: <?php echo htmlspecialchars(TERMOS_VERSAO_ATUAL); ?></p>
    </div>
</body>
</html>
