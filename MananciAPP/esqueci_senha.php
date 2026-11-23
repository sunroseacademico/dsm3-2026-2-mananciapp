<?php
session_start();
include("php/conexao.php");

// Pedido de recuperação de senha. Regras e motivos em
// docs/interno/reset_de_senha.md; a lógica fica em recuperacao_pedir()
// (php/conexao.php).

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$base_url = SITE_BASE_URL;
$erro = "";
$aviso = $_SESSION['recuperacao_aviso'] ?? "";
if ($aviso !== "") {
    unset($_SESSION['recuperacao_aviso']);
}

// Mesma mensagem para tudo — e-mail com conta, sem conta, conta pendente,
// limite atingido. Se mudasse, a tela diria quais e-mails têm cadastro.
$mensagem_generica = "Se existir uma conta aprovada com esse e-mail, enviamos um link para criar uma nova senha. Ele vale por até " . RECUPERACAO_VALIDADE_MINUTOS . " minutos. Confira também a caixa de spam e a aba Promoções.";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim(site_entrada_texto($_POST["IDF_EMAIL"] ?? '', false));

    if (!csrf_validar()) {
        $erro = "Sua sessão expirou. Atualize a página e tente novamente.";
    } elseif (!turnstile_validar()) {
        // CAPTCHA invisível (Turnstile), quando ligado no Render.
        $erro = "Confirme que você não é um robô no quadrinho acima do botão e tente de novo.";
    } elseif ($email === "" || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Informe um e-mail válido.";
    } elseif (!$conn) {
        $erro = "Serviço indisponível no momento. Tente novamente em alguns minutos.";
    } else {
        $_SESSION['recuperacao_aviso'] = $mensagem_generica;

        // Responde (redireciona) ANTES de consultar o banco e chamar a Brevo:
        // assim o tempo de resposta é o mesmo exista ou não a conta — senão a
        // demora do envio do e-mail denunciaria quem tem cadastro.
        ignore_user_abort(true);
        set_time_limit(60);
        session_write_close();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . site_url('esqueci_senha.php'), true, 303);
        header('Content-Length: 0');
        header('Connection: close');
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        $envio = recuperacao_pedir($conn, $email);
        if ($envio) {
            try {
                enviar_email_sistema($envio['destinatario'], $envio['assunto'], $envio['html'], $envio['texto']);
            } catch (\Throwable $e) {
                error_log('Recuperação de senha: falha ao enviar o e-mail (' . $e->getMessage() . ')');
            }
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
    <title>Missão Manancial | Esqueci minha senha</title>
    <link href="css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <script src="js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php $pwa_sem_onesignal = true; require __DIR__ . '/php/includes/pwa_head.php'; ?>
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
                <h2>Esqueci minha senha</h2>
                <p>Informe o e-mail da sua conta e enviaremos um link para criar uma nova senha.</p>
            </div>
        </div>

        <div class="auth-compact-divider"></div>

        <?php if ($aviso !== "") { ?>
            <div class="alert alert-info mb-3" role="alert">
                <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($aviso); ?>
            </div>
        <?php } ?>

        <?php if ($erro !== "") {
            echo "<div class='error-message mb-3'><i class='fas fa-exclamation-circle'></i> " . htmlspecialchars($erro) . "</div>";
        } ?>

        <form action="" method="POST" class="auth-form-grid">
            <?php echo csrf_campo(); ?>
            <div>
                <input aria-label="Seu e-mail" type="email" name="IDF_EMAIL" class="form-control custom-input" placeholder="Seu e-mail" autocomplete="email" maxlength="100" required>
            </div>

            <?php echo turnstile_campo_html(); ?>

            <div class="auth-form-actions">
                <button type="submit" class="btn auth-submit w-100 py-2">
                    <i class="fas fa-paper-plane me-2"></i> Enviar link
                </button>

                <p class="text-center mb-1">
                    Lembrou a senha? <a href="<?php echo $base_url; ?>login.php" class="auth-link">Entrar</a>
                </p>
            </div>
        </form>
    </section>

</body>
<script src="js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</html>
