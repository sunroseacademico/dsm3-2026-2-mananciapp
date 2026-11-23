<?php
session_start();
include("php/conexao.php");

$erro = "";
$erro_com_contato = false;
// Avisos de outras telas: cadastro concluído (register.php) e senha
// redefinida (redefinir_senha.php).
$sucesso = $_SESSION['cadastro_sucesso'] ?? ($_SESSION['login_aviso'] ?? "");
unset($_SESSION['cadastro_sucesso'], $_SESSION['login_aviso']);
$base_url = SITE_BASE_URL;
$db_error = $GLOBALS['site_db_error'] ?? '';

if ($db_error !== '') {
    $erro = $db_error;
}

// Anti força-bruta: sem isso, login.php aceitava tentativa de senha
// ilimitada, sem nenhum delay/bloqueio — um script conseguia testar
// milhares de senhas por minuto contra qualquer e-mail conhecido. Os
// limites (e-mail + IP, e-mail, IP) estão em login_tentativas_bloqueado(),
// php/conexao.php; tabela "id_login_tentativa" (database/bancosupabase.md).

if ($conn && $_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim(site_entrada_texto($_POST["IDF_EMAIL"] ?? '', false));
    $senha = site_entrada_texto($_POST["IDF_SENHA"] ?? '', false);
    // Consultas preparadas (db_query, php/conexao.php): o e-mail digitado
    // nunca entra no texto do SQL. Cortado em 100, o tamanho de IDLT_EMAIL.
    $email_lower = mb_substr(mb_strtolower($email), 0, 100);

    if (!csrf_validar()) {
        // Sem o token, um site de fora conseguia logar o visitante numa
        // conta do atacante (login CSRF) e ver o que ele fizesse depois.
        $erro = "Sua sessão expirou. Tente entrar de novo.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Só e-mail de verdade chega na tabela de tentativas: ela guarda
        // também "cadastro:<ip>" e "beneficiario:<id>", e um "e-mail"
        // desses no login apagava as linhas desses outros limites.
        password_verify($senha, '$2y$10$C38Fmu0krpgVRsmoLld5YeX8R5/OG9p1Khiqx2qoWcRI2Bs8TbUKK');
        $erro = "E-mail ou senha inválido(s).";
    } elseif (login_tentativas_bloqueado($conn, $email_lower)) {
        $erro = "Muitas tentativas de login. Aguarde alguns minutos e tente novamente.";
    }

    // Procurar usuário no banco para resolver nome e flag de admin (se existir)
    $sql_lookup = "SELECT f.IDF_ID, f.IDF_NOME, f.IDF_EMAIL, f.IDF_STATUS, COALESCE(f.IDF_ATIVO, 1) AS ATIVO, f.IDF_SENHA_HASH, CASE WHEN a.IDA_ID IS NULL THEN 0 ELSE 1 END AS EH_ADMIN FROM ID_FIEL f LEFT JOIN ID_ADMIN a ON a.IDA_FIEL_ID = f.IDF_ID AND a.IDA_ATIVO = 1 WHERE LOWER(f.IDF_EMAIL) = LOWER(?) LIMIT 1";
    $res_lookup = ($erro === "") ? db_query($conn, $sql_lookup, array($email)) : false;
    $usuario_db = null;
    $eh_admin = false;
    $id_fiel = 1;

    if ($res_lookup && mysqli_num_rows($res_lookup) > 0) {
        $usuario_db = mysqli_fetch_assoc($res_lookup);
        $status_usuario = $usuario_db['IDF_STATUS'] ?? 'pendente';
        $senha_db = (string) ($usuario_db['IDF_SENHA_HASH'] ?? '');

        // Só hash. A senha antiga em texto puro (IDF_SENHA) deixou de valer
        // em 26/10/2026 (S11 em docs/interno/seguranca.md): nenhuma conta a
        // usava mais; uma que aparecesse entra por "Esqueci minha senha".
        $senha_valida = $senha_db !== '' && password_verify($senha, $senha_db);

        if (!$senha_valida) {
            $erro = "E-mail ou senha inválido(s).";
            login_registrar_falha($conn, $email_lower);
        } elseif (password_needs_rehash($senha_db, PASSWORD_DEFAULT)) {
            // Senha certa: atualiza um hash antigo (como o "$2a$" gerado pelo
            // SQL da seção 67 do bancosupabase.md) JÁ AQUI, antes de olhar o
            // status, e garante IDF_SENHA vazia.
            $id_rehash = (int) ($usuario_db['IDF_ID'] ?? 0);
            db_query($conn, "UPDATE ID_FIEL SET IDF_SENHA_HASH = ?, IDF_SENHA = NULL WHERE IDF_ID = ?", array(password_hash($senha, PASSWORD_DEFAULT), $id_rehash));
        }

        // Verificar status do usuário
        if ($erro === "" && $status_usuario === 'pendente') {
            $erro = "Sua conta ainda está aguardando a aprovação de um líder. Você recebe um e-mail assim que o acesso for liberado.";
            $erro_com_contato = true;
        } elseif ($erro === "" && (int) ($usuario_db['ATIVO'] ?? 1) !== 1) {
            // Conta desativada (IDF_ATIVO = 0): a recuperação de senha e o
            // resto do app já a tratavam como fora; o login deixava entrar.
            $erro = "Sua conta está desativada. Fale com a igreja para entender o que aconteceu.";
            $erro_com_contato = true;
        } elseif ($erro === "" && $status_usuario === 'negado') {
            $erro = "Seu cadastro não foi aprovado. Se acha que foi um engano, fale com a igreja: a liderança pode rever.";
            $erro_com_contato = true;
        } elseif ($erro === "") {
            // Status é 'aprovado', continuar com o login
            $id_fiel = (int) ($usuario_db['IDF_ID'] ?? 1);
            $nome_usuario = $usuario_db['IDF_NOME'] ?? $email;
            $eh_admin = ((int) ($usuario_db['EH_ADMIN'] ?? 0) === 1);

            // Admin com verificação em duas etapas: a senha certa ainda não
            // abre a sessão; login_2fa.php pede o código do app autenticador.
            // Sem conseguir ler o 2FA (erro de banco), recusa: deixar passar
            // só com a senha anularia o 2FA num soluço do Supabase.
            $doisfa = $eh_admin ? doisfa_estado_login($conn, $id_fiel) : 'sem_2fa';
            if ($doisfa === 'erro') {
                $erro = "Não foi possível conferir o acesso agora. Tente novamente em instantes.";
            } elseif ($doisfa === 'ligado') {
                // As tentativas erradas NÃO são zeradas aqui, só depois do
                // código certo (login_2fa.php): senão cada senha certa daria
                // mais 5 chutes de código a quem já tem a senha.
                session_regenerate_id(true);
                $_SESSION['login_2fa'] = array(
                    'fiel' => $id_fiel,
                    'email' => $email_lower,
                    'em' => time(),
                    'erros' => 0,
                    'voltar' => auth_voltar_valido($_GET['voltar'] ?? ''),
                );
                header('Location: login_2fa.php');
                exit;
            } else {
                // Login certo: some com o histórico de tentativas erradas desse e-mail.
                login_limpar_tentativas($conn, $email_lower);

                // Senha certa e conta liberada: abre a sessão (auth_iniciar_sessao,
                // php/conexao.php). Sem conseguir gravar em ID_SESSAO, recusa o
                // login agora, com mensagem.
                if (!auth_iniciar_sessao($conn, $usuario_db)) {
                    $erro = "Não foi possível iniciar a sessão agora. Tente novamente em instantes.";
                }
            }
        }

        if ($erro === "") {
            // Se o fiel já tem o certificado de boas-vindas e ainda não foi
            // notificado sobre ele, cria a notificação agora (uma única vez).
            certificado_boas_vindas_notificar_se_necessario($conn, $id_fiel);

            // Veio de um link (ex.: comentário compartilhado) antes de logar:
            // volta para ele (o form posta na mesma URL, então ?voltar=
            // segue); senão, o painel ou a área do fiel.
            header('Location: ' . auth_destino_pos_login($eh_admin, $_GET['voltar'] ?? ''));
            exit;
        }
    } elseif ($erro === "") {
        // E-mail não cadastrado: mesma mensagem (e mesma contagem de
        // tentativas) de senha errada. Antes a página só recarregava sem
        // mensagem nenhuma — confundia quem digitou o e-mail errado e, de
        // quebra, revelava quais e-mails existem (com cadastro = "senha
        // inválida", sem cadastro = silêncio). O password_verify contra um
        // hash qualquer é só pra o tempo de resposta também não denunciar a
        // diferença (bcrypt leva dezenas de ms, que dá pra medir de fora).
        password_verify($senha, '$2y$10$C38Fmu0krpgVRsmoLld5YeX8R5/OG9p1Khiqx2qoWcRI2Bs8TbUKK');
        $erro = "E-mail ou senha inválido(s).";
        if ($email_lower !== '') {
            login_registrar_falha($conn, $email_lower);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Login</title>
    <link href="css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <script src="js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/php/includes/pwa_head.php'; ?>
</head>
<body class="auth-page">

    <a href="<?php echo $base_url; ?>index.html" class="auth-back-link">
        <i class="fas fa-arrow-left"></i>
        Voltar para a página inicial
    </a>

    <div class="position-absolute top-0 end-0 p-3" style="z-index: 50;">
        <div class="theme-toggle-container">
            <i class="fas fa-moon theme-icon"></i>
            <input type="checkbox" id="theme-toggle" class="theme-toggle" aria-label="Alternar tema">
            <i class="fas fa-sun theme-icon"></i>
        </div>
    </div>

    <section class="auth-panel auth-panel--compact auth-panel--heroless">
        <div class="auth-brand-row">
            <img src="assets/logo-sm.png" alt="Logotipo" class="logo" />
            <div class="auth-brand-copy">
                <h2>Bem-vindo</h2>
                <p>Acesse sua conta para continuar.</p>
            </div>
        </div>

        <div class="auth-compact-divider"></div>

        <?php if ($sucesso !== "") { ?>
            <div class="alert alert-info mb-3" role="alert">
                <i class="fas fa-info-circle me-2"></i><?php echo htmlspecialchars($sucesso); ?>
            </div>
        <?php } ?>

        <?php if ($erro != "") { 
            echo "<div class='error-message mb-3'><i class='fas fa-exclamation-circle'></i> " . htmlspecialchars($erro) . "</div>";
            // Conta pendente, recusada ou desativada: um caminho para falar
            // com a igreja (antes só "entre em contato", sem contato nenhum).
            if ($erro_com_contato) {
                $zap_login = igreja_whatsapp_link('Olá! Preciso de ajuda com o meu acesso ao app da igreja.');
                $email_igreja = igreja_info()['email'];
                if ($zap_login !== '' || $email_igreja !== '') {
                    echo "<p class='small mb-3'>Fale com a igreja: ";
                    if ($zap_login !== '') {
                        echo "<a class='auth-link' href='" . htmlspecialchars($zap_login) . "' target='_blank' rel='noopener'><i class='fab fa-whatsapp me-1'></i>WhatsApp</a>";
                    }
                    if ($zap_login !== '' && $email_igreja !== '') {
                        echo " · ";
                    }
                    if ($email_igreja !== '') {
                        echo "<a class='auth-link' href='mailto:" . htmlspecialchars($email_igreja) . "'>" . htmlspecialchars($email_igreja) . "</a>";
                    }
                    echo "</p>";
                }
            }
        } ?>

        <form action="" method="POST" class="auth-form-grid">
            <?php echo csrf_campo(); ?>
            <div>
                <label class="campo-perfil-rotulo" for="loginEmailInput">E-mail</label>
                <input aria-label="Seu e-mail" type="email" id="loginEmailInput" name="IDF_EMAIL" class="form-control custom-input" placeholder="Seu e-mail" autocomplete="email" maxlength="100" value="<?php echo htmlspecialchars(isset($email) ? (string) $email : ''); ?>" required>
            </div>

            <div>
                <label class="campo-perfil-rotulo" for="loginPasswordInput">Senha</label>
                <div class="input-group">
                    <input aria-label="Sua senha" type="password" name="IDF_SENHA" id="loginPasswordInput" class="form-control custom-input" placeholder="Sua senha" autocomplete="current-password" maxlength="100" required>
                    <button class="btn btn-outline-light" type="button" id="toggleLoginPassword" aria-label="Mostrar senha" style="border: 2px solid var(--border-color); color: var(--text-main);">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="text-end">
                <a href="<?php echo $base_url; ?>esqueci_senha.php" class="auth-link small">Esqueci minha senha</a>
            </div>

            <div class="auth-form-actions">
                <button type="submit" class="btn auth-submit w-100 py-2">
                    <i class="fas fa-sign-in-alt me-2"></i> Entrar
                </button>

                <p class="text-center mb-1">
                    Novo aqui? <a href="<?php echo $base_url; ?>php/fiel/register.php" class="auth-link">Criar uma conta</a>
                </p>
                <p class="text-center mb-0 small">
                    <a href="<?php echo $base_url; ?>privacidade.php" class="auth-link">Política de Privacidade</a>
                </p>
            </div>
        </form>
    </section>

</body>
<script nonce="<?php echo site_csp_nonce(); ?>">
    (function () {
        const passwordInput = document.getElementById('loginPasswordInput');
        const toggleButton = document.getElementById('toggleLoginPassword');
        if (!passwordInput || !toggleButton) return;

        toggleButton.addEventListener('click', function () {
            const isHidden = passwordInput.type === 'password';
            passwordInput.type = isHidden ? 'text' : 'password';
            toggleButton.querySelector('i').className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
    })();
</script>
<script src="js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</html>