<?php
// Segunda etapa do login de quem ligou a verificação em duas etapas (admins,
// Admin → Minha segurança). login.php já conferiu a senha e deixou em
// $_SESSION['login_2fa'] só o id da conta; a sessão de verdade só abre aqui,
// com o código do app autenticador ou um código de reserva.
// Ver "VERIFICAÇÃO EM DUAS ETAPAS" em php/conexao.php.
session_start();
include("php/conexao.php");

// 5 minutos para digitar o código e 5 erros: depois, volta para a senha.
$pendente = $_SESSION['login_2fa'] ?? null;
if (!is_array($pendente) || (time() - (int) ($pendente['em'] ?? 0)) > 300 || (int) ($pendente['erros'] ?? 0) >= 5) {
    unset($_SESSION['login_2fa']);
    $_SESSION['login_aviso'] = is_array($pendente) ? 'Entre de novo com a sua senha para receber outro pedido de código.' : '';
    header('Location: login.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = site_entrada_texto($_POST['codigo'] ?? '');
    if (!csrf_validar()) {
        $erro = 'Sua sessão expirou. Entre de novo.';
    } elseif ($conn && login_tentativas_bloqueado($conn, (string) $pendente['email'])) {
        // Erros de código de outros logins somam no mesmo limite do e-mail:
        // entrar de novo com a senha não dá mais chutes.
        unset($_SESSION['login_2fa']);
        $_SESSION['login_aviso'] = 'Muitas tentativas. Aguarde alguns minutos e entre de novo.';
        header('Location: login.php');
        exit;
    } elseif (!$conn || !doisfa_conferir_login($conn, (int) $pendente['fiel'], $codigo)) {
        $_SESSION['login_2fa']['erros'] = (int) $pendente['erros'] + 1;
        // Conta também no limite de tentativas do e-mail (login_tentativas_bloqueado()).
        login_registrar_falha($conn, (string) $pendente['email']);
        $restantes = 5 - (int) $_SESSION['login_2fa']['erros'];
        $erro = 'Código inválido.' . ($restantes > 0 ? ' Confira a hora do celular e tente de novo.' : '');
        if ($restantes <= 0) {
            unset($_SESSION['login_2fa']);
            $_SESSION['login_aviso'] = 'Muitos códigos errados. Entre de novo com a sua senha.';
            header('Location: login.php');
            exit;
        }
    } else {
        // Confere de novo que a conta continua liberada e admin.
        $res = db_query($conn, "
            SELECT f.IDF_ID, f.IDF_NOME, f.IDF_EMAIL, f.IDF_STATUS, COALESCE(f.IDF_ATIVO, 1) AS ATIVO,
                   CASE WHEN a.IDA_ID IS NULL THEN 0 ELSE 1 END AS EH_ADMIN
            FROM ID_FIEL f LEFT JOIN ID_ADMIN a ON a.IDA_FIEL_ID = f.IDF_ID AND a.IDA_ATIVO = 1
            WHERE f.IDF_ID = ? LIMIT 1
        ", array((int) $pendente['fiel']));
        $conta = $res ? mysqli_fetch_assoc($res) : null;
        $voltar = (string) ($pendente['voltar'] ?? '');
        if (!$conta || $conta['IDF_STATUS'] !== 'aprovado' || (int) $conta['ATIVO'] !== 1) {
            unset($_SESSION['login_2fa']);
            header('Location: login.php');
            exit;
        }
        if (!auth_iniciar_sessao($conn, $conta)) {
            $erro = 'Não foi possível iniciar a sessão agora. Tente novamente em instantes.';
        } else {
            // Só agora (senha + código certos) as tentativas erradas zeram.
            login_limpar_tentativas($conn, (string) $pendente['email']);
            certificado_boas_vindas_notificar_se_necessario($conn, (int) $conta['IDF_ID']);
            header('Location: ' . auth_destino_pos_login((int) $conta['EH_ADMIN'] === 1, $voltar));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Verificação em duas etapas</title>
    <link href="css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <script src="js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/php/includes/pwa_head.php'; ?>
    <style>
        .codigo-2fa { font-size: 1.6rem; letter-spacing: 0.4em; text-align: center; font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="auth-page">
    <section class="auth-panel auth-panel--compact auth-panel--heroless">
        <div class="auth-brand-row">
            <img src="assets/logo-sm.png" alt="Logotipo" class="logo" />
            <div class="auth-brand-copy">
                <h2>Confirme que é você</h2>
                <p>Digite o código de 6 dígitos do seu app autenticador.</p>
            </div>
        </div>

        <div class="auth-compact-divider"></div>

        <?php if ($erro !== '') { ?>
            <div class="error-message mb-3" role="alert"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($erro); ?></div>
        <?php } ?>

        <form method="POST" class="auth-form-grid" autocomplete="off">
            <?php echo csrf_campo(); ?>
            <div>
                <input aria-label="Código de 6 dígitos" type="text" name="codigo" id="codigo2fa" class="form-control custom-input codigo-2fa" inputmode="numeric" autocomplete="one-time-code" maxlength="9" placeholder="000000" required autofocus>
            </div>
            <p class="small mb-0" style="color: var(--text-muted);">
                Perdeu o celular? Use um dos <strong>códigos de reserva</strong> (formato xxxx-xxxx) que você guardou ao ligar a verificação.
            </p>
            <div class="auth-form-actions">
                <button type="submit" class="btn auth-submit w-100 py-2"><i class="fas fa-shield-halved me-2"></i>Confirmar</button>
                <p class="text-center mb-1"><a href="login.php" class="auth-link small">Voltar para o login</a></p>
            </div>
        </form>
    </section>
<script src="js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
