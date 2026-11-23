<?php
// Minha segurança (todo admin): verificação em duas etapas (2FA) da própria
// conta. Ligar: escaneia o QR Code no app autenticador, digita o código e a
// senha; recebe 8 códigos de reserva (mostrados uma vez só). Desligar e
// gerar novos códigos de reserva pedem a senha. Ver "VERIFICAÇÃO EM DUAS
// ETAPAS" em php/conexao.php e a seção 84 do bancosupabase.md.
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));

$fiel_id = (int) auth_resolve_fiel_id();
$tem_2fa = doisfa_disponivel($conn);
$mensagem = (string) ($_SESSION['seguranca_ok'] ?? '');
$codigos_reserva = $_SESSION['seguranca_reserva'] ?? null;
unset($_SESSION['seguranca_ok'], $_SESSION['seguranca_reserva']);
$erro = '';

$conta = $tem_2fa ? doisfa_da_conta($conn, $fiel_id) : null;
$ligado = $conta && !empty($conta['idf_2fa_ativo_em']);

// Chave em preparação: fica na sessão até o primeiro código certo.
if ($tem_2fa && !$ligado && empty($_SESSION['seguranca_2fa_novo'])) {
    $_SESSION['seguranca_2fa_novo'] = totp_novo_segredo();
}
$segredo_novo = (string) ($_SESSION['seguranca_2fa_novo'] ?? '');

$voltar = function ($texto, $reserva = null) {
    $_SESSION['seguranca_ok'] = $texto;
    if ($reserva) {
        $_SESSION['seguranca_reserva'] = $reserva;
    }
    header('Location: admin_seguranca_conta.php');
    exit;
};

$acao = $_SERVER['REQUEST_METHOD'] === 'POST' ? site_entrada_texto($_POST['acao'] ?? '') : '';
if ($acao !== '' && !$tem_2fa) {
    $erro = 'A verificação em duas etapas ainda não foi ativada no banco (seção 84).';
} elseif ($acao !== '' && !csrf_validar()) {
    $erro = 'Sessão expirada. Atualize a página e tente de novo.';
} elseif ($acao !== '' && ($erro = senha_conferir_atual($conn, $fiel_id, site_entrada_texto($_POST['senha'] ?? '', false))) !== '') {
    // Toda ação desta tela pede a senha (erro já em $erro).
} elseif ($acao === 'ligar' && !$ligado) {
    $passo = totp_conferir($segredo_novo, site_entrada_texto($_POST['codigo'] ?? ''));
    if ($passo === false) {
        $erro = 'Código inválido. Confira se escaneou o QR Code desta página e se a hora do celular está certa.';
    } else {
        $reserva = doisfa_gerar_reserva();
        if (db_query($conn, "UPDATE id_fiel SET idf_2fa_segredo = ?, idf_2fa_ativo_em = NOW(), idf_2fa_ultimo_passo = ?, idf_2fa_reserva = ? WHERE idf_id = ?", array(doisfa_cifrar_segredo($segredo_novo, $fiel_id), $passo, $reserva['json'], $fiel_id))) {
            unset($_SESSION['seguranca_2fa_novo']);
            admin_log($conn, 'conta.2fa', 'fiel', $fiel_id, array('ligada' => true));
            $voltar('Verificação em duas etapas ligada. Guarde os códigos de reserva abaixo.', $reserva['codigos']);
        }
        $erro = site_erro_banco('Não foi possível ligar a verificação agora.');
    }
} elseif ($acao === 'desligar' && $ligado) {
    if (!doisfa_conferir_login($conn, $fiel_id, site_entrada_texto($_POST['codigo'] ?? ''))) {
        $erro = 'Código inválido.';
    } elseif (db_query($conn, "UPDATE id_fiel SET idf_2fa_segredo = NULL, idf_2fa_ativo_em = NULL, idf_2fa_ultimo_passo = NULL, idf_2fa_reserva = NULL WHERE idf_id = ?", array($fiel_id))) {
        admin_log($conn, 'conta.2fa', 'fiel', $fiel_id, array('ligada' => false));
        $voltar('Verificação em duas etapas desligada.');
    } else {
        $erro = site_erro_banco('Não foi possível desligar agora.');
    }
} elseif ($acao === 'nova_reserva' && $ligado) {
    $reserva = doisfa_gerar_reserva();
    if (db_query($conn, "UPDATE id_fiel SET idf_2fa_reserva = ? WHERE idf_id = ?", array($reserva['json'], $fiel_id))) {
        $voltar('Códigos de reserva novos gerados. Os antigos deixaram de valer.', $reserva['codigos']);
    }
    $erro = site_erro_banco('Não foi possível gerar os códigos agora.');
}

$reserva_restante = $ligado ? count((array) json_decode((string) ($conta['idf_2fa_reserva'] ?? '[]'), true)) : 0;
$uri = $segredo_novo !== '' ? totp_uri($segredo_novo, (string) ($_SESSION['Usuario_logado'] ?? ''), 'Manancial') : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Minha segurança</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
    <style>
        .seguranca-qr { background: #fff; padding: 10px; border-radius: 12px; width: 200px; height: 200px; image-rendering: pixelated; }
        .seguranca-chave { font-family: ui-monospace, monospace; letter-spacing: 0.08em; word-break: break-all; }
        .seguranca-reserva { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.4rem 1rem; font-family: ui-monospace, monospace; font-size: 1.05rem; }
        .seguranca-passos li { margin-bottom: 0.35rem; }
    </style>
</head>
<body class="admin-layout admin-shell">
    <?php admin_layout_abrir($conn, 'seguranca', 'Minha segurança', 'fa-shield-halved'); ?>

    <?php if ($mensagem !== '') { ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensagem); ?></div>
    <?php } ?>
    <?php if ($erro !== '') { ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($erro); ?></div>
    <?php } ?>

    <?php if (is_array($codigos_reserva)) { ?>
        <div class="card admin-card mb-4 border-warning">
            <div class="card-body">
                <h2 class="h5"><i class="fas fa-key me-2 text-warning"></i>Seus códigos de reserva</h2>
                <p class="text-muted">Cada um vale <strong>uma vez</strong>, no lugar do código do app, se você perder o celular. <strong>Esta é a única vez que eles aparecem:</strong> anote num papel ou salve num gerenciador de senhas.</p>
                <div class="seguranca-reserva mb-2">
                    <?php foreach ($codigos_reserva as $codigo) { ?><span><?php echo htmlspecialchars($codigo); ?></span><?php } ?>
                </div>
            </div>
        </div>
    <?php } ?>

    <div class="card admin-card mb-4">
        <div class="card-body">
            <h2 class="h5"><i class="fas fa-shield-halved me-2"></i>Verificação em duas etapas</h2>
            <p class="text-muted">Além da senha, o login pede um código de 6 dígitos que muda a cada 30 segundos no seu celular. Mesmo que alguém descubra sua senha, não entra no painel sem o seu celular. <strong>Recomendado para todos os admins</strong>, principalmente quem cuida de usuários, permissões ou dinheiro.</p>

            <?php if ($tem_2fa && auth_eh_superadmin() && doisfa_chave_cifra() === '') { ?>
                <div class="alert alert-warning small">Falta a variável <code>APP_CHAVE</code> (32+ letras) no Render: a chave do autenticador fica no banco sem cifra.</div>
            <?php } ?>
            <?php if (!$tem_2fa) { ?>
                <div class="alert alert-warning mb-0">Falta rodar a seção 84 do <code>bancosupabase.md</code> no Supabase.</div>
            <?php } elseif ($ligado) { ?>
                <p class="mb-3"><span class="badge bg-success"><i class="fas fa-check me-1"></i>Ligada</span>
                    desde <?php echo htmlspecialchars(site_data_hora($conta['idf_2fa_ativo_em'], 'd/m/Y')); ?> ·
                    <?php echo (int) $reserva_restante; ?> código(s) de reserva sobrando</p>

                <div class="row g-4">
                    <form method="POST" class="col-md-6" data-confirmar="Gerar novos códigos de reserva? Os antigos deixam de valer.">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="nova_reserva">
                        <h3 class="h6">Novos códigos de reserva</h3>
                        <label class="form-label" for="senhaReserva">Sua senha</label>
                        <input type="password" class="form-control mb-2" id="senhaReserva" name="senha" autocomplete="current-password" required>
                        <button type="submit" class="btn btn-outline-primary btn-sm">Gerar novos códigos</button>
                    </form>
                    <form method="POST" class="col-md-6" data-confirmar="Desligar a verificação em duas etapas? Sua conta fica protegida só pela senha.">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="desligar">
                        <h3 class="h6">Desligar</h3>
                        <label class="form-label" for="senhaDesligar">Sua senha</label>
                        <input type="password" class="form-control mb-2" id="senhaDesligar" name="senha" autocomplete="current-password" required>
                        <label class="form-label" for="codigoDesligar">Código do app (ou de reserva)</label>
                        <input type="text" class="form-control mb-2" id="codigoDesligar" name="codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="9" required>
                        <button type="submit" class="btn btn-outline-danger btn-sm">Desligar</button>
                    </form>
                </div>
            <?php } else { ?>
                <ol class="seguranca-passos">
                    <li>Instale um app autenticador no celular: <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong> ou <strong>Authy</strong> (gratuitos).</li>
                    <li>No app, toque em <strong>+</strong> e escaneie o QR Code abaixo. No celular, dá para tocar em "Abrir no app autenticador".</li>
                    <li>Digite o código de 6 dígitos que aparecer e a sua senha.</li>
                </ol>
                <div class="d-flex flex-wrap gap-4 align-items-start mb-3">
                    <img id="qr2fa" class="seguranca-qr" alt="QR Code para o app autenticador" data-uri="<?php echo htmlspecialchars($uri); ?>">
                    <div>
                        <p class="mb-1 small text-muted">Não consegue escanear? Digite esta chave no app:</p>
                        <p class="seguranca-chave mb-2"><?php echo htmlspecialchars(trim(chunk_split($segredo_novo, 4, ' '))); ?></p>
                        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($uri); ?>"><i class="fas fa-mobile-screen me-1"></i>Abrir no app autenticador</a>
                    </div>
                </div>
                <form method="POST" class="row g-2" style="max-width: 520px;">
                    <?php echo csrf_campo(); ?>
                    <input type="hidden" name="acao" value="ligar">
                    <div class="col-sm-6">
                        <label class="form-label" for="codigoLigar">Código do app</label>
                        <input type="text" class="form-control" id="codigoLigar" name="codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="000000" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="senhaLigar">Sua senha</label>
                        <input type="password" class="form-control" id="senhaLigar" name="senha" autocomplete="current-password" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-lock me-1"></i>Ligar verificação em duas etapas</button>
                    </div>
                </form>
            <?php } ?>
        </div>
    </div>

    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <script src="../../js/vendor/qrcode-generator-1.4.4.js"></script>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        // QR Code gerado no próprio navegador (a chave não sai para nenhum
        // serviço de fora). qrcode-generator 1.4.4, MIT, em js/vendor/.
        (function () {
            var img = document.getElementById('qr2fa');
            if (!img || typeof qrcode !== 'function') return;
            var qr = qrcode(0, 'M');
            qr.addData(img.getAttribute('data-uri'));
            qr.make();
            img.src = qr.createDataURL(5, 2);
        })();
    </script>
</body>
</html>
