<?php
// Módulo de logout

include("../conexao.php");

// auth_logout() marca a linha da sessão em ID_SESSAO como revogada (antes o
// session_destroy() direto deixava a sessão "viva" no banco até expirar — e
// o fiel continuava na lista de pessoas online), apaga a sessão e volta para
// o login desfazendo o vínculo do OneSignal (ver php/includes/pwa_head.php).
//
// Só sai com o token do link (logout_token()) ou com o botão abaixo (POST +
// CSRF): antes um <img src=".../sair.php"> em qualquer site deslogava o fiel.
// Sem sessão, não há o que proteger: vai direto para o login.
$token_link = site_entrada_texto($_GET['t'] ?? '', false);
if (!isset($_SESSION['Usuario_logado'])
    || ($token_link !== '' && hash_equals(logout_token(), $token_link))
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validar())) {
    auth_logout();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Sair</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
</head>
<body class="auth-page">
    <section class="auth-panel auth-panel--compact auth-panel--heroless">
        <div class="auth-brand-row">
            <img src="../../assets/logo-sm.png" alt="Logotipo" class="logo" />
            <div class="auth-brand-copy">
                <h2>Sair da conta?</h2>
                <p>Confirme para encerrar a sessão neste aparelho.</p>
            </div>
        </div>
        <div class="auth-compact-divider"></div>
        <form method="POST" class="auth-form-grid">
            <?php echo csrf_campo(); ?>
            <div class="auth-form-actions">
                <button type="submit" class="btn auth-submit w-100 py-2">
                    <i class="fas fa-sign-out-alt me-2"></i> Sair
                </button>
                <p class="text-center mb-1">
                    <a href="<?php echo ($_SESSION['Usuario_tipo'] ?? '') === 'admin' ? '../admin/dashboard.php' : 'dashboard.php'; ?>" class="auth-link">Voltar</a>
                </p>
            </div>
        </form>
    </section>
</body>
</html>
