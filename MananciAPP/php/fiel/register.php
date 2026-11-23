<?php
session_start();
include("../conexao.php");

$mensagem = "";

// Limites alinhados com a estrutura da tabela ID_FIEL
$lim_nome = 50;
$lim_email = 100;
$lim_filial = 100;
$lim_endereco = 200;
// Limites da senha: SENHA_MIN/SENHA_MAX e senha_validar_politica() em
// php/conexao.php (a mesma regra vale na redefinição de senha).

// Membro da igreja (seção 47 do bancosupabase.md): só pede se as colunas
// já existem no banco, senão o INSERT quebraria o cadastro.
$pede_membro = fiel_membro_disponivel($conn);
// WhatsApp usa IDF_TELEFONE (sempre existiu); nascimento só com a seção 49.
$pede_nascimento = fiel_perfil_disponivel($conn);
// Filial escolhida numa lista (seção 76); sem ela, o texto livre antigo.
$pede_filial_lista = filiais_disponivel($conn);
$filiais = $pede_filial_lista ? filiais_listar($conn) : array();
$filial_validada = null;
// LGPD (seção 82): aceite da política e responsável do menor gravados.
$tem_lgpd = lgpd_disponivel($conn);

// Valores digitados, para não perder tudo quando o cadastro volta com erro.
$form = array(
    'nome' => site_entrada_texto($_POST["IDF_NOME"] ?? ""),
    'email' => site_entrada_texto($_POST["IDF_EMAIL"] ?? ""),
    'whatsapp' => site_entrada_texto($_POST["IDF_WHATSAPP"] ?? ""),
    'nascimento' => site_entrada_texto($_POST["IDF_NASCIMENTO"] ?? ""),
    'filial' => site_entrada_texto($_POST["IDF_FILIAL"] ?? ""),
    'filial_escolha' => site_entrada_texto($_POST["IDF_FILIAL_ESCOLHA"] ?? ""),
    'membro' => site_entrada_texto($_POST["IDF_MEMBRO"] ?? '', false),
    'membro_mes' => (int) ($_POST["IDF_MEMBRO_MES"] ?? 0),
    'membro_ano' => (int) ($_POST["IDF_MEMBRO_ANO"] ?? 0),
    'aceite' => !empty($_POST["ACEITE_PRIVACIDADE"]),
    'responsavel' => site_entrada_texto($_POST["RESPONSAVEL_NOME"] ?? ""),
    'responsavel_autorizou' => !empty($_POST["RESPONSAVEL_AUTORIZOU"]),
);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $form['nome'];
    $email = $form['email'];
    $filial = $form['filial'];
    $senha = site_entrada_texto($_POST["IDF_SENHA"] ?? "", false);
    $senha_confirma = site_entrada_texto($_POST["IDF_SENHA_CONFIRMA"] ?? "", false);
    $membro = $pede_membro ? fiel_membro_validar($form['membro'], $form['membro_mes'], $form['membro_ano']) : null;
    $whatsapp = fiel_whatsapp_normalizar($form['whatsapp']);
    $nascimento = $pede_nascimento ? fiel_nascimento_validar($form['nascimento']) : array('erro' => null, 'data' => null);

    // Chave do limite de cadastros por IP (ver cadastro_limite_atingido()).
    $chave_limite_cadastro = 'cadastro:' . site_ip_chave_limite(site_ip_cliente());

    if (!csrf_validar()) {
        // Sem token, um site de fora (ou um script) postava cadastros com
        // qualquer e-mail — e cada um dispara um e-mail pela Brevo.
        $mensagem = "Sua sessão expirou. Atualize a página e tente de novo.";
    } elseif (!turnstile_validar()) {
        // CAPTCHA invisível (Turnstile), quando ligado no Render.
        $mensagem = "Confirme que você não é um robô no quadrinho acima do botão e tente de novo.";
    } elseif (cadastro_limite_atingido($conn, $chave_limite_cadastro)) {
        $mensagem = "Muitos cadastros feitos desta conexão na última hora. Tente de novo mais tarde.";
    } elseif (preg_match('/\d/', $nome)) {
        $mensagem = "O nome não pode conter números.";
    } elseif (!preg_match("/^[\p{L}\p{M}' .-]+$/uD", $nome) || preg_match('/\.\S/u', $nome)) {
        // Só letras, espaço, apóstrofo, hífen e ponto de abreviação ("D.
        // Silva"). O nome vai no e-mail de boas-vindas mandado ao endereço
        // digitado: com "visite golpe.com" no nome, qualquer um mandava
        // texto de golpe a terceiros com o remetente da igreja.
        $mensagem = "Nome inválido. Use apenas letras.";
    } elseif (mb_strlen($nome) < 3 || mb_strlen($nome) > $lim_nome) {
        $mensagem = "Nome inválido. Use entre 3 e " . $lim_nome . " caracteres.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Informe um e-mail válido.";
    } elseif (strlen($email) > $lim_email) {
        $mensagem = "E-mail inválido. Máximo de " . $lim_email . " caracteres.";
    } elseif (in_array(mb_strtolower(trim($email)), superadmin_emails(), true)) {
        // E-mail da equipe interna (SUPERADMIN_EMAILS) que ainda não tem conta:
        // quem cadastrasse com ele e fosse promovido a admin viraria super
        // admin. A conta da equipe é criada pela própria equipe.
        $mensagem = "Esse e-mail não pode ser usado. Fale com a equipe pelo Contato.";
    } elseif ($whatsapp === '') {
        $mensagem = "Informe seu WhatsApp com DDD.";
    } elseif ($whatsapp === null) {
        $mensagem = "WhatsApp inválido. Use o celular com DDD, ex.: (11) 9 9999-9999.";
    } elseif ($nascimento['erro']) {
        $mensagem = $nascimento['erro'];
    } elseif ($pede_nascimento && $nascimento['data'] === null) {
        // Obrigatória: é ela que diz se o menor precisa do responsável.
        $mensagem = "Informe sua data de nascimento.";
    } elseif ($pede_filial_lista && ($filial_validada = fiel_filial_validar($form['filial_escolha'], $filiais))['erro']) {
        $mensagem = $filial_validada['erro'];
    } elseif (!$pede_filial_lista && (mb_strlen($filial) < 2 || mb_strlen($filial) > $lim_filial)) {
        $mensagem = "Filial inválida. Use entre 2 e " . $lim_filial . " caracteres.";
    } elseif (($erro_senha = senha_validar_politica($senha, $senha_confirma)) !== '') {
        // Mesma regra da redefinição de senha (ver php/conexao.php).
        $mensagem = $erro_senha;
    } elseif ($membro && $membro['erro']) {
        $mensagem = $membro['erro'];
    } elseif (($erro_lgpd = lgpd_validar_cadastro($form['aceite'], $nascimento['data'], $form['responsavel'], $form['responsavel_autorizou'])) !== '') {
        // Aceite da política e, para menor de 18, o responsável (LGPD art. 14).
        $mensagem = $erro_lgpd;
    } else {
        // Consultas preparadas (db_query, php/conexao.php): nada do que foi
        // digitado entra no texto do SQL. A senha vai só como hash — o ramo
        // antigo do "modo simples", que gravava a senha em texto puro em
        // IDF_SENHA, foi removido (não rodava desde a migração).
        $filial_id = null;
        $resultado_filial = $pede_filial_lista ? false : db_query($conn, "SELECT IDL_ID FROM ID_FILIAL WHERE LOWER(TRIM(IDL_NOME)) = LOWER(TRIM(?)) LIMIT 1", array($filial));
        if ($resultado_filial && mysqli_num_rows($resultado_filial) == 1) {
            $linha_filial = mysqli_fetch_assoc($resultado_filial);
            $filial_id = (int) $linha_filial["IDL_ID"];
        }

        // A filial digitada vai também como texto (IDF_FILIAL): antes, quando não
        // batia exatamente com uma filial cadastrada, ela se perdia e o admin
        // via "N/A" ao aprovar. A busca da filial ignora maiúsculas e espaços.
        $colunas = array('IDF_NOME', 'IDF_EMAIL', 'IDF_FILIAL', 'IDF_FILIAL_ID', 'IDF_ENDERECO', 'IDF_SENHA_HASH', 'IDF_STATUS', 'IDF_ATIVO', 'IDF_TELEFONE');
        $valores = array($nome, $email, $filial, $filial_id, '', password_hash($senha, PASSWORD_DEFAULT), 'pendente', 1, $whatsapp);
        // Filial da lista (seção 76): id, "Nenhuma filial" e o nome copiado em IDF_FILIAL.
        if ($filial_validada) {
            $valores[2] = $filial_validada['nome'];
            $valores[3] = $filial_validada['filial_id'];
            $colunas[] = 'IDF_SEM_FILIAL';
            $valores[] = $filial_validada['sem_filial'];
        }
        // Membro só com a seção 47 e nascimento só com a 49.
        if ($membro) {
            array_push($colunas, 'IDF_MEMBRO', 'IDF_MEMBRO_DESDE');
            array_push($valores, (int) $membro['membro'], $membro['desde'] ?: null);
        }
        if ($pede_nascimento) {
            $colunas[] = 'IDF_NASCIMENTO';
            $valores[] = $nascimento['data'] ?: null;
        }
        if ($tem_lgpd) {
            $colunas[] = 'IDF_PRIVACIDADE_ACEITA_EM';
            $valores[] = date('c');
            if (lgpd_eh_menor($nascimento['data'])) {
                array_push($colunas, 'IDF_RESPONSAVEL_NOME', 'IDF_RESPONSAVEL_CONSENTIU_EM');
                array_push($valores, $form['responsavel'], date('c'));
            }
        }
        $sql = "INSERT INTO ID_FIEL (" . implode(', ', $colunas) . ") VALUES (" . implode(', ', array_fill(0, count($valores), '?')) . ")";
        // Conta a tentativa antes do INSERT: e-mail repetido também gasta o
        // limite (senão dava para testar quais e-mails têm conta à vontade).
        cadastro_registrar_tentativa($conn, $chave_limite_cadastro);

        if (db_query($conn, $sql, $valores)) {
            if (defined('EMAIL_API_KEY') && trim((string) EMAIL_API_KEY) !== '' && defined('EMAIL_SENDER_EMAIL') && trim((string) EMAIL_SENDER_EMAIL) !== '') {
                $nome_email = htmlspecialchars($nome);
                $texto_email = "Olá, {$nome}!\n\nRecebemos sua solicitação de cadastro e ela está aguardando aprovação de um administrador. Você receberá um novo e-mail assim que houver uma resposta.\n\nAtenciosamente,\nEquipe Missão Manancial";
                $html_email = '<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">'
                    . '<h2 style="margin: 0 0 16px; color: #1d4ed8;">Cadastro recebido</h2>'
                    . '<p>Olá, <strong>' . $nome_email . '</strong>.</p>'
                    . '<p>Recebemos sua solicitação de cadastro e ela está aguardando aprovação de um administrador.</p>'
                    . '<p>Você receberá um novo e-mail assim que houver uma resposta.</p>'
                    . '<p>Atenciosamente,<br>Equipe Missão Manancial</p>'
                    . '</div>';
                try {
                    enviar_email_sistema($email, 'Recebemos seu cadastro', $html_email, $texto_email);
                } catch (\Throwable $ex) {
                    // Falha no envio não deve impedir o cadastro.
                }
            }

            // Tela de espera (o que acontece agora e o que fazer enquanto isso).
            $_SESSION['cadastro_recebido'] = $form['nome'];
            header("location:cadastro_recebido.php");
            exit;
        } else {
            // Nao expor mysqli_error() aqui: essa pagina e publica (sem login),
            // e o erro cru do Postgres vazaria detalhes do schema pra qualquer
            // visitante. E-mail já cadastrado (UNIQUE em IDF_EMAIL) responde
            // igual a um cadastro novo, como o login e o "esqueci": a tela
            // dizia "já está cadastrado" e servia para descobrir quem tem
            // conta. Quem é dono do e-mail recebe um aviso com o caminho.
            $res_existente = db_query($conn, "SELECT IDF_ID, IDF_NOME, IDF_STATUS" . (tabela_tem_coluna($conn, 'id_fiel', 'idf_removido_em') ? ", IDF_REMOVIDO_EM" : ", NULL AS IDF_REMOVIDO_EM") . " FROM ID_FIEL WHERE LOWER(IDF_EMAIL) = LOWER(?) LIMIT 1", array($email));
            $existente = $res_existente ? mysqli_fetch_assoc($res_existente) : null;
            // Recusado que se cadastra de novo: vira um pedido de revisão (a
            // conta volta para "Aprovar Usuários"). Antes a tela dizia
            // "aguardando aprovação", ninguém via o pedido e o login seguia
            // "rejeitada": beco sem saída. A tela é a mesma de sempre.
            $revisao = $existente && $existente['IDF_STATUS'] === 'negado' && empty($existente['IDF_REMOVIDO_EM'])
                && ($res_revisao = db_query($conn, "UPDATE ID_FIEL SET IDF_STATUS = 'pendente' WHERE IDF_ID = ? AND IDF_STATUS = 'negado' RETURNING IDF_ID", array((int) $existente['IDF_ID'])))
                && mysqli_num_rows($res_revisao) > 0;
            if ($revisao) {
                @db_query($conn, "UPDATE ID_SOLICITACAO_ACESSO SET IDSA_STATUS = 'pendente', IDSA_MOTIVO_NEGACAO = NULL, IDSA_RESPONDIDO_EM = NULL, IDSA_ATUALIZADO_EM = NOW() WHERE IDF_ID = ?", array((int) $existente['IDF_ID']));
                try {
                    $nome_revisao = (string) $existente['IDF_NOME'];
                    enviar_email_sistema(
                        $email,
                        'Recebemos o seu novo pedido',
                        '<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;"><h2 style="margin: 0 0 16px; color: #1d4ed8;">Novo pedido recebido</h2>'
                            . '<p>Olá, <strong>' . htmlspecialchars($nome_revisao) . '</strong>.</p>'
                            . '<p>Recebemos o seu novo pedido de acesso. A liderança da igreja vai rever o seu cadastro, e você recebe um e-mail com a resposta.</p>'
                            . '<p>A sua senha continua a de antes. Se não lembrar, use "Esqueci minha senha" depois da aprovação.</p>'
                            . '<p>Atenciosamente,<br>Equipe Missão Manancial</p></div>',
                        "Olá, {$nome_revisao}!\n\nRecebemos o seu novo pedido de acesso. A liderança da igreja vai rever o seu cadastro, e você recebe um e-mail com a resposta.\n\nA sua senha continua a de antes. Se não lembrar, use \"Esqueci minha senha\" depois da aprovação.\n\nAtenciosamente,\nEquipe Missão Manancial"
                    );
                } catch (\Throwable $ex) {
                    // Falha no envio não muda a resposta.
                }
                $_SESSION['cadastro_recebido'] = $form['nome'];
                header("location:cadastro_recebido.php");
                exit;
            }
            if ($existente) {
                if (defined('EMAIL_API_KEY') && trim((string) EMAIL_API_KEY) !== '' && defined('EMAIL_SENDER_EMAIL') && trim((string) EMAIL_SENDER_EMAIL) !== '') {
                    $nome_dono = (string) $existente['IDF_NOME'];
                    $link_login = site_url_publica('login.php');
                    $link_senha = site_url_publica('esqueci_senha.php');
                    $texto_email = "Olá, {$nome_dono}!\n\nAlguém tentou criar uma conta com este e-mail, mas você já tem cadastro. Se foi você, entre pelo login ({$link_login}) ou recupere a senha ({$link_senha}).\n\nSe não foi você, pode ignorar este e-mail: nada mudou na sua conta.\n\nAtenciosamente,\nEquipe Missão Manancial";
                    $html_email = '<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">'
                        . '<h2 style="margin: 0 0 16px; color: #1d4ed8;">Você já tem cadastro</h2>'
                        . '<p>Olá, <strong>' . htmlspecialchars($nome_dono) . '</strong>.</p>'
                        . '<p>Alguém tentou criar uma conta com este e-mail, mas você já tem cadastro. Se foi você, <a href="' . htmlspecialchars($link_login) . '">entre pelo login</a> ou <a href="' . htmlspecialchars($link_senha) . '">recupere a senha</a>.</p>'
                        . '<p>Se não foi você, pode ignorar este e-mail: nada mudou na sua conta.</p>'
                        . '<p>Atenciosamente,<br>Equipe Missão Manancial</p>'
                        . '</div>';
                    try {
                        enviar_email_sistema($email, 'Você já tem cadastro', $html_email, $texto_email);
                    } catch (\Throwable $ex) {
                        // Falha no envio não muda a resposta.
                    }
                }
                // Mesma tela de quem é novo: não revela que o e-mail já tem conta.
                $_SESSION['cadastro_recebido'] = $form['nome'];
                header("location:cadastro_recebido.php");
                exit;
            } else {
                $mensagem = "Não foi possível concluir o cadastro. Tente novamente em instantes.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Criar conta</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <style>
        /* Compact layout for register panel */
        .auth-panel.auth-panel--wide {
            width: min(470px, calc(100vw - 0.5rem));
            max-width: 470px;
            margin: 0.25rem auto;
            padding: 0.42rem 0.55rem;
            font-size: 0.86rem;
            box-sizing: border-box;
            overflow-x: hidden;
        }

        .auth-panel.auth-panel--wide .auth-brand-row {
            gap: 0.65rem;
        }

        .auth-panel.auth-panel--wide .logo {
            width: 48px;
            height: auto;
        }

        .auth-panel.auth-panel--wide .auth-brand-copy h2 {
            font-size: 1.45rem;
            margin-bottom: 0;
        }

        .auth-panel.auth-panel--wide .auth-brand-copy p {
            font-size: 0.82rem;
        }

        .auth-panel.auth-panel--wide .auth-compact-divider {
            margin: 0.4rem 0;
        }

        .auth-panel.auth-panel--wide .auth-form-grid {
            gap: 0.4rem;
        }

        .auth-panel.auth-panel--wide .custom-input {
            min-height: 40px;
            padding-top: 0.45rem;
            padding-bottom: 0.45rem;
        }

        .auth-panel.auth-panel--wide .auth-submit {
            padding-top: 0.6rem;
            padding-bottom: 0.6rem;
        }

        @media (max-width: 768px) {
            .auth-panel.auth-panel--wide {
                max-width: 100%;
                margin: 0.2rem;
                padding: 0.35rem;
            }
        }

        @media (max-width: 576px) {
            .auth-panel.auth-panel--wide {
                margin: 0.15rem;
                padding: 0.3rem;
                border-radius: 8px;
            }
        }

        .password-toggle-btn { border: 2px solid var(--border-color); color: var(--text-main); }
        .cadastro-aceite { display: flex; gap: 0.55rem; align-items: flex-start; font-size: 0.82rem; color: var(--text-muted); line-height: 1.4; cursor: pointer; }
        .cadastro-aceite input { margin-top: 0.2rem; width: 18px; height: 18px; flex-shrink: 0; accent-color: var(--primary-light, #60a5fa); }
        .senha-regras { list-style: none; padding: 0; margin: 0 0 0.6rem; display: grid; gap: 0.2rem; font-size: 0.88rem; color: var(--text-muted); }
        .senha-regras li::before { content: "○"; display: inline-block; width: 1.3em; font-weight: 700; }
        .senha-regras li.ok { color: #34d399; }
        .senha-regras li.ok::before { content: "✓"; }
        .cadastro-responsavel { border: 1px dashed var(--border-color); border-radius: 10px; padding: 0.6rem; font-size: 0.82rem; color: var(--text-main); }
        .password-help-trigger {
            color: var(--text-muted);
            font-size: 0.84rem;
            padding: 0;
            border: 0;
            background: transparent;
            line-height: 1;
        }
        .password-help-trigger .fa-circle-info { color: var(--primary-light); }
        .tooltip.password-tooltip {
            --bs-tooltip-bg: rgba(15, 23, 42, 0.96);
            --bs-tooltip-color: #fff;
            --bs-tooltip-padding-x: 0.85rem;
            --bs-tooltip-padding-y: 0.65rem;
            --bs-tooltip-border-radius: 0.75rem;
            --bs-tooltip-max-width: 260px;
        }
        .tooltip.password-tooltip .tooltip-inner {
            font-size: 0.82rem;
            line-height: 1.35;
            text-align: left;
            box-shadow: 0 10px 24px rgba(0,0,0,.28);
        }
    </style>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="auth-page">

    <div class="position-absolute top-0 end-0 p-3" style="z-index: 50; display:flex; gap:8px; align-items:center;">
        <div class="theme-toggle-container">
            <i class="fas fa-moon theme-icon"></i>
            <input type="checkbox" id="theme-toggle" class="theme-toggle" aria-label="Alternar tema">
            <i class="fas fa-sun theme-icon"></i>
        </div>
    </div>

    <a href="../../index.html" class="auth-back-link">
        <i class="fas fa-arrow-left"></i>
        Voltar para a página inicial
    </a>

    <section class="auth-panel auth-panel--wide auth-panel--heroless">
        <div class="auth-brand-row">
            <img src="../../assets/logo-sm.png" alt="Logotipo" class="logo" />
            <div class="auth-brand-copy">
                <h2>Criar conta</h2>
                <p>Preencha seus dados. Um líder da igreja confere o cadastro e libera o seu acesso; você recebe um e-mail quando estiver pronto.</p>
            </div>
        </div>

        <div class="auth-compact-divider"></div>

        <?php if ($mensagem != "") {
            // Toda mensagem aqui é erro (o cadastro certo redireciona para o login).
            echo "<div class='mb-3 auth-erro' role='alert'>" . htmlspecialchars($mensagem) . "</div>";
        } ?>

        <form action="" method="POST" class="auth-form-grid">
            <?php echo csrf_campo(); ?>
            <div class="auth-form-grid auth-form-grid--two">
                <div>
                    <label class="campo-perfil-rotulo" for="IDF_NOME">Nome completo</label>
                    <input aria-label="Nome completo" type="text" id="IDF_NOME" name="IDF_NOME" class="form-control custom-input" placeholder="Ex.: Maria da Silva" value="<?php echo htmlspecialchars($form['nome']); ?>" pattern="^[^0-9]{3,50}$" title="Nome: sem números, entre 3 e 50 caracteres" minlength="3" maxlength="50" autocomplete="name" required>
                </div>

                <div>
                    <label class="campo-perfil-rotulo" for="IDF_EMAIL">E-mail</label>
                    <input aria-label="Endereço de e-mail" type="email" id="IDF_EMAIL" name="IDF_EMAIL" class="form-control custom-input" placeholder="seu@email.com" autocomplete="email" value="<?php echo htmlspecialchars($form['email']); ?>" maxlength="100" required>
                </div>
            </div>

            <?php
                $campo_perfil = array(
                    'whatsapp' => $form['whatsapp'],
                    'nascimento' => $form['nascimento'],
                    'com_nascimento' => $pede_nascimento,
                    'nascimento_obrigatorio' => $pede_nascimento,
                    'com_contexto' => false,
                );
                include __DIR__ . '/includes/campo_perfil.php';
            ?>

            <!-- A função na igreja é definida pela equipe (T12), não no cadastro. -->
            <?php if ($pede_filial_lista) {
                $campo_filial = array('filiais' => $filiais, 'escolha' => $form['filial_escolha']);
                include __DIR__ . '/includes/campo_filial.php';
            } else { ?>
            <div>
                <label class="campo-perfil-rotulo" for="IDF_FILIAL">Filial (a igreja ou congregação que você frequenta)</label>
                <input aria-label="Filial" type="text" id="IDF_FILIAL" name="IDF_FILIAL" class="form-control custom-input" placeholder="Filial" value="<?php echo htmlspecialchars($form['filial']); ?>" minlength="2" maxlength="100" required>
            </div>
            <?php } ?>

            <?php if ($pede_membro) {
                $campo_membro = array('membro' => $form['membro'], 'mes' => $form['membro_mes'], 'ano' => $form['membro_ano']);
                include __DIR__ . '/includes/campo_membro.php';
            } ?>

            <div class="auth-form-grid">
                <label class="campo-perfil-rotulo" for="IDF_SENHA">Crie uma senha</label>
                <div class="input-group mb-2">
                    <input aria-label="Criar senha" id="IDF_SENHA" type="password" name="IDF_SENHA" class="form-control custom-input" placeholder="Criar senha" minlength="8" maxlength="72" pattern="(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,72}" title="8-72 caracteres, pelo menos 1 letra maiúscula e 1 caractere especial" required>
                    <button class="btn btn-outline-light password-toggle-btn" type="button" id="toggleRegisterPassword" aria-label="Mostrar senha"><i class="fas fa-eye"></i></button>
                </div>

                <!-- Regras visíveis e marcadas enquanto digita (antes ficavam num
                     balão escondido e a recusa vinha do balão do navegador). -->
                <ul class="senha-regras" id="regrasSenha" aria-live="polite">
                    <li data-regra="tamanho">Pelo menos 8 letras ou números</li>
                    <li data-regra="maiuscula">Uma letra MAIÚSCULA</li>
                    <li data-regra="simbolo">Um símbolo (ex.: ! @ # $ *)</li>
                    <li data-regra="iguais">As duas senhas iguais</li>
                </ul>

                <label class="campo-perfil-rotulo" for="IDF_SENHA_CONFIRMA">Repita a senha</label>
                <div class="input-group">
                    <input aria-label="Confirmar senha" id="IDF_SENHA_CONFIRMA" type="password" name="IDF_SENHA_CONFIRMA" class="form-control custom-input" placeholder="Confirmar senha" minlength="8" maxlength="72" pattern="(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,72}" title="Repita a senha (8-72 caracteres, pelo menos 1 letra maiúscula e 1 caractere especial)" required>
                    <button class="btn btn-outline-light password-toggle-btn" type="button" id="toggleRegisterPasswordConfirm" aria-label="Mostrar senha"><i class="fas fa-eye"></i></button>
                </div>
            </div>

            <!-- Responsável: aparece quando a data de nascimento indica menos de
                 18 anos (o servidor confere de novo em lgpd_validar_cadastro()). -->
            <div id="blocoResponsavel" class="cadastro-responsavel"<?php echo lgpd_eh_menor($form['nascimento']) ? '' : ' hidden'; ?>>
                <p class="mb-2"><i class="fas fa-child-reaching me-1" aria-hidden="true"></i>Menores de 18 anos precisam da autorização do pai, mãe ou responsável.</p>
                <input aria-label="Nome completo do responsável" type="text" name="RESPONSAVEL_NOME" id="RESPONSAVEL_NOME" class="form-control custom-input mb-2" placeholder="Nome completo do responsável" value="<?php echo htmlspecialchars($form['responsavel']); ?>" maxlength="100">
                <label class="cadastro-aceite">
                    <input type="checkbox" name="RESPONSAVEL_AUTORIZOU" id="RESPONSAVEL_AUTORIZOU" value="1"<?php echo $form['responsavel_autorizou'] ? ' checked' : ''; ?>>
                    <span>Sou o responsável (ou ele está comigo) e autorizo este cadastro.</span>
                </label>
            </div>

            <label class="cadastro-aceite">
                <input type="checkbox" name="ACEITE_PRIVACIDADE" value="1" required<?php echo $form['aceite'] ? ' checked' : ''; ?>>
                <span>Li e aceito os <a href="termos.php" class="auth-link" target="_blank" rel="noopener">Termos de Uso</a> e a <a href="../../privacidade.php" class="auth-link" target="_blank" rel="noopener">Política de Privacidade</a>, inclusive o uso dos meus dados de fé para participar da plataforma.</span>
            </label>

            <?php echo turnstile_campo_html(); ?>

            <div class="auth-form-actions">
                <button type="submit" class="btn auth-submit w-100 py-2">Cadastrar</button>

                <p class="text-center mb-0" style="color: var(--text-muted);">
                    Já tem conta? <a href="../../login.php" class="auth-link">Entrar.</a>
                </p>
            </div>
        </form>
    </section>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
            new bootstrap.Tooltip(element, { container: 'body', trigger: 'hover focus', customClass: 'password-tooltip' });
        });

        document.getElementById('IDF_NOME').addEventListener('input', function() {
            this.value = this.value.replace(/\d+/g, '');
        });

        document.getElementById('IDF_NOME').addEventListener('paste', function(event) {
            var texto = (event.clipboardData || window.clipboardData).getData('text');
            if (/\d/.test(texto)) {
                event.preventDefault();
                this.value = this.value.replace(/\d+/g, '');
            }
        });

        // Menor de 18 pela data de nascimento: mostra e exige o responsável.
        (function () {
            var nascimento = document.querySelector('input[name="IDF_NASCIMENTO"]');
            var bloco = document.getElementById('blocoResponsavel');
            if (!nascimento || !bloco) return;
            var nome = document.getElementById('RESPONSAVEL_NOME');
            var autorizou = document.getElementById('RESPONSAVEL_AUTORIZOU');
            var maioridade = <?php echo (int) LGPD_MAIORIDADE; ?>;
            function atualizar() {
                var menor = false;
                if (nascimento.value) {
                    var d = new Date(nascimento.value + 'T00:00:00');
                    var hoje = new Date();
                    var idade = hoje.getFullYear() - d.getFullYear();
                    if (hoje.getMonth() < d.getMonth() || (hoje.getMonth() === d.getMonth() && hoje.getDate() < d.getDate())) idade--;
                    menor = idade >= 0 && idade < maioridade;
                }
                bloco.hidden = !menor;
                nome.required = menor;
                autorizou.required = menor;
            }
            nascimento.addEventListener('change', atualizar);
            atualizar();
        })();

        // Toggle para mostrar/ocultar senhas
        (function() {
            var pass = document.getElementById('IDF_SENHA');
            var passConfirm = document.getElementById('IDF_SENHA_CONFIRMA');
            var toggle = document.getElementById('toggleRegisterPassword');
            var toggleConfirm = document.getElementById('toggleRegisterPasswordConfirm');

            function bindToggle(btn, input) {
                if (!btn || !input) return;
                btn.addEventListener('click', function () {
                    var isHidden = input.type === 'password';
                    input.type = isHidden ? 'text' : 'password';
                    var icon = btn.querySelector('i');
                    if (icon) icon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
                });
            }

            bindToggle(toggle, pass);
            bindToggle(toggleConfirm, passConfirm);

            // Regras da senha marcadas ao vivo (as mesmas do servidor,
            // senha_validar_politica() em php/conexao.php).
            var regras = document.getElementById('regrasSenha');
            function conferirRegras() {
                if (!regras || !pass) return;
                var v = pass.value;
                var ok = {
                    tamanho: v.length >= 8 && v.length <= 72,
                    maiuscula: /[A-Z]/.test(v),
                    simbolo: /[^A-Za-z0-9]/.test(v),
                    iguais: v !== '' && passConfirm && v === passConfirm.value
                };
                regras.querySelectorAll('[data-regra]').forEach(function (li) {
                    li.classList.toggle('ok', !!ok[li.getAttribute('data-regra')]);
                });
            }
            if (pass) pass.addEventListener('input', conferirRegras);
            if (passConfirm) passConfirm.addEventListener('input', conferirRegras);
            conferirRegras();
        })();
    </script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>

</body>
</html>
