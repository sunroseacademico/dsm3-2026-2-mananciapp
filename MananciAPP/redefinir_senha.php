<?php
session_start();
include("php/conexao.php");

// Tela aberta pelo link do e-mail de recuperação (ver esqueci_senha.php e
// docs/interno/reset_de_senha.md). A lógica fica em recuperacao_*()
// (php/conexao.php).

// no-referrer: o token chega na URL; sem isso, os CSS/JS de CDN carregados
// pela página receberiam a URL com o token no cabeçalho Referer.
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$base_url = SITE_BASE_URL;

// O token vem na URL só na primeira abertura: guarda na sessão e recarrega a
// página sem ele, para não ficar no histórico do navegador nem em log de
// acesso. Abrir o link (GET) NÃO gasta o token — leitores de e-mail
// (Outlook, Gmail, antivírus) abrem os links sozinhos para checar, e o link
// chegaria "já usado" para a pessoa.
if (isset($_GET['token'])) {
    $token_url = site_entrada_texto($_GET['token']);
    if (recuperacao_token_formato_ok($token_url)) {
        // Só o hash: a sessão pode ficar no banco (SESSOES_NO_BANCO=1).
        $_SESSION['recuperacao_token_hash'] = recuperacao_hash_token($token_url);
        $_SESSION['recuperacao_token_em'] = time();
    } else {
        unset($_SESSION['recuperacao_token'], $_SESSION['recuperacao_token_hash'], $_SESSION['recuperacao_token_em']);
    }
    header('Location: ' . site_url('redefinir_senha.php'), true, 303);
    exit;
}

// Link aberto e deixado de lado (computador compartilhado): depois de
// RECUPERACAO_SESSAO_MINUTOS, quem usar o navegador em seguida não conclui a
// troca — é preciso abrir o link do e-mail de novo.
$token_hash = (string) ($_SESSION['recuperacao_token_hash'] ?? '');
if ($token_hash !== '' && time() - (int) ($_SESSION['recuperacao_token_em'] ?? 0) > RECUPERACAO_SESSAO_MINUTOS * 60) {
    $token_hash = '';
}
$fiel = recuperacao_token_valido_por_hash($conn, $token_hash);
$erro = "";

if ($fiel && $_SERVER["REQUEST_METHOD"] === "POST") {
    $senha = site_entrada_texto($_POST["IDF_SENHA"] ?? '', false);
    $senha_confirma = site_entrada_texto($_POST["IDF_SENHA_CONFIRMA"] ?? '', false);

    if (!csrf_validar()) {
        $erro = "Sua sessão expirou. Atualize a página e tente novamente.";
    } elseif (($erro = senha_validar_politica($senha, $senha_confirma)) !== '') {
        // $erro já tem a mensagem.
        // Sem checagem de "igual à senha atual" de propósito: o aviso servia
        // para quem tem o link testar chutes da senha atual sem limite, sem
        // gastar o token e sem o dono receber aviso nenhum.
    } else {
        $resultado = recuperacao_redefinir_por_hash($conn, $token_hash, $senha);
        if (!$resultado) {
            // Token gasto/vencido entre abrir a página e enviar (ou outro
            // envio simultâneo chegou antes): cai na tela de link inválido.
            $fiel = null;
        } else {
            // Encerra a sessão deste navegador (pode até ser de outra conta)
            // e manda para o login. As sessões da conta em outros aparelhos
            // já foram revogadas em recuperacao_redefinir().
            session_unset();
            session_destroy();
            session_start();
            session_regenerate_id(true);
            $_SESSION['login_aviso'] = "Senha alterada com sucesso! Entre com a nova senha.";

            recuperacao_email_confirmacao($resultado['nome'], $resultado['email']);
            if ($resultado['eh_admin']) {
                recuperacao_avisar_admins($conn, $resultado['id'], $resultado['nome']);
            }

            header('Location: ' . site_url('login.php'), true, 303);
            exit;
        }
    }
}

if (!$fiel) {
    unset($_SESSION['recuperacao_token'], $_SESSION['recuperacao_token_hash'], $_SESSION['recuperacao_token_em']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Nova senha</title>
    <link href="css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <script src="js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php $pwa_sem_onesignal = true; require __DIR__ . '/php/includes/pwa_head.php'; ?>
    <style>
        .password-toggle-btn { border: 2px solid var(--border-color); color: var(--text-main); }
    </style>
</head>
<body class="auth-page">

    <a href="<?php echo $base_url; ?>login.php" class="auth-back-link">
        <i class="fas fa-arrow-left"></i>
        Voltar para o login
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
                <h2>Criar nova senha</h2>
                <?php if ($fiel) { ?>
                    <p>Olá, <?php echo htmlspecialchars(site_nome_curto($fiel['IDF_NOME'] ?? '')); ?>. Escolha sua nova senha.</p>
                <?php } else { ?>
                    <p>Não foi possível usar este link.</p>
                <?php } ?>
            </div>
        </div>

        <div class="auth-compact-divider"></div>

        <?php if (!$fiel) { ?>
            <div class="error-message mb-3">
                <i class="fas fa-exclamation-circle"></i>
                Este link é inválido ou expirou. Os links valem por até <?php echo (int) RECUPERACAO_VALIDADE_MINUTOS; ?> minutos, só podem ser usados uma vez e, se você pediu mais de um, só o último funciona.
            </div>
            <div class="auth-form-actions">
                <a href="<?php echo $base_url; ?>esqueci_senha.php" class="btn auth-submit w-100 py-2">
                    <i class="fas fa-paper-plane me-2"></i> Pedir um novo link
                </a>
            </div>
        <?php } else { ?>
            <?php if ($erro !== "") {
                echo "<div class='error-message mb-3'><i class='fas fa-exclamation-circle'></i> " . htmlspecialchars($erro) . "</div>";
            } ?>

            <form action="" method="POST" class="auth-form-grid">
                <?php echo csrf_campo(); ?>
                <div class="password-help auth-note">
                    <i class="fas fa-info-circle"></i>
                    De <?php echo SENHA_MIN; ?> a <?php echo SENHA_MAX; ?> caracteres, com pelo menos 1 letra maiúscula e 1 caractere especial.
                </div>

                <div class="input-group">
                    <input aria-label="Nova senha" id="IDF_SENHA" type="password" name="IDF_SENHA" class="form-control custom-input" placeholder="Nova senha" autocomplete="new-password" minlength="<?php echo SENHA_MIN; ?>" maxlength="<?php echo SENHA_MAX; ?>" pattern="(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{<?php echo SENHA_MIN; ?>,<?php echo SENHA_MAX; ?>}" title="<?php echo SENHA_MIN; ?>-<?php echo SENHA_MAX; ?> caracteres, pelo menos 1 letra maiúscula e 1 caractere especial" required>
                    <button class="btn btn-outline-light password-toggle-btn" type="button" data-alvo="IDF_SENHA" aria-label="Mostrar senha"><i class="fas fa-eye"></i></button>
                </div>

                <div class="input-group">
                    <input aria-label="Confirmar nova senha" id="IDF_SENHA_CONFIRMA" type="password" name="IDF_SENHA_CONFIRMA" class="form-control custom-input" placeholder="Confirmar nova senha" autocomplete="new-password" minlength="<?php echo SENHA_MIN; ?>" maxlength="<?php echo SENHA_MAX; ?>" required>
                    <button class="btn btn-outline-light password-toggle-btn" type="button" data-alvo="IDF_SENHA_CONFIRMA" aria-label="Mostrar senha"><i class="fas fa-eye"></i></button>
                </div>

                <div class="auth-form-actions">
                    <button type="submit" class="btn auth-submit w-100 py-2">
                        <i class="fas fa-key me-2"></i> Salvar nova senha
                    </button>
                </div>
            </form>
        <?php } ?>
    </section>

</body>
<script nonce="<?php echo site_csp_nonce(); ?>">
    (function () {
        var senha = document.getElementById('IDF_SENHA');
        var confirma = document.getElementById('IDF_SENHA_CONFIRMA');

        document.querySelectorAll('.password-toggle-btn').forEach(function (botao) {
            botao.addEventListener('click', function () {
                var campo = document.getElementById(botao.getAttribute('data-alvo'));
                if (!campo) return;
                var oculta = campo.type === 'password';
                campo.type = oculta ? 'text' : 'password';
                botao.querySelector('i').className = oculta ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
        });

        if (senha && confirma) {
            var conferir = function () {
                confirma.setCustomValidity(confirma.value !== '' && confirma.value !== senha.value ? 'As senhas não conferem.' : '');
            };
            senha.addEventListener('input', conferir);
            confirma.addEventListener('input', conferir);
        }
    })();
</script>
<script src="js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</html>
