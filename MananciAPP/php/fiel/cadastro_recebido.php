<?php
// Tela de espera depois do cadastro: diz o que acontece agora (um líder
// confere e libera o acesso; chega um e-mail) e o que dá para fazer
// enquanto isso. Antes, o cadastro jogava a pessoa no login com uma frase,
// e ela tentava entrar várias vezes achando que tinha dado erro.
// Mostra só o primeiro nome que a própria pessoa digitou (vem da sessão):
// o mesmo vale para quem já tinha conta, então nada revela quem é membro.
define('SITE_SESSAO_OPCIONAL', true);
include("../conexao.php");

$nome = '';
if (isset($_SESSION['cadastro_recebido'])) {
    $nome = (string) $_SESSION['cadastro_recebido'];
}
$primeiro_nome = $nome !== '' ? explode(' ', trim($nome))[0] : '';
$igreja = igreja_info();
$whatsapp = igreja_whatsapp_link('Olá! Acabei de me cadastrar no app da igreja' . ($nome !== '' ? ' (' . $nome . ')' : '') . '.');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Missão Manancial | Cadastro recebido</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
    <style>
        .espera { max-width: 560px; margin: 0 auto; }
        .espera-icone { width: 72px; height: 72px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; background: rgba(52, 211, 153, 0.15); color: #34d399; }
        .espera h1 { font-size: 1.6rem; margin: 1rem 0 0.5rem; }
        .espera-passos { list-style: none; padding: 0; margin: 1.5rem 0; text-align: left; }
        .espera-passos li { display: flex; gap: 0.85rem; align-items: flex-start; padding: 0.85rem 0; border-bottom: 1px solid var(--border-color); font-size: 1.02rem; }
        .espera-passos li:last-child { border-bottom: 0; }
        .espera-passos .num { flex: 0 0 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; background: var(--primary); color: #fff; }
        .espera-acoes { display: grid; gap: 0.6rem; margin-top: 1.25rem; }
        .espera-acoes .btn { min-height: 48px; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; font-size: 1rem; }
    </style>
</head>
<body class="auth-page">
    <section class="auth-panel auth-panel--heroless espera text-center">
        <span class="espera-icone" aria-hidden="true"><i class="fas fa-check"></i></span>
        <h1>Cadastro recebido<?php echo $primeiro_nome !== '' ? ', ' . htmlspecialchars($primeiro_nome) : ''; ?>!</h1>
        <p class="text-muted mb-0">Que bom ter você com a gente na <?php echo htmlspecialchars($igreja['nome']); ?>.</p>

        <ol class="espera-passos">
            <li><span class="num">1</span><span>Um líder da igreja confere o seu cadastro. Isso é para proteger a comunidade, e costuma levar até um dia.</span></li>
            <li><span class="num">2</span><span>Quando o acesso for liberado, você recebe um <strong>e-mail</strong> (confira também a caixa de spam).</span></li>
            <li><span class="num">3</span><span>Depois é só entrar com o seu e-mail e a senha que você criou.</span></li>
        </ol>

        <p class="mb-2"><strong>Enquanto isso, você já pode:</strong></p>
        <div class="espera-acoes">
            <a href="../../ao-vivo.php" class="btn btn-play"><i class="fas fa-tower-broadcast" aria-hidden="true"></i>Assistir ao vivo</a>
            <a href="../../cursos.php" class="btn btn-outline-light"><i class="fas fa-book-open" aria-hidden="true"></i>Ver os cursos</a>
            <a href="../../como-instalar.php" class="btn btn-outline-light"><i class="fas fa-mobile-screen-button" aria-hidden="true"></i>Instalar o app no celular</a>
            <?php if ($whatsapp !== '') { ?>
                <a href="<?php echo htmlspecialchars($whatsapp); ?>" class="btn btn-outline-light" target="_blank" rel="noopener"><i class="fab fa-whatsapp" aria-hidden="true"></i>Avisar a igreja pelo WhatsApp</a>
            <?php } ?>
        </div>

        <p class="mt-4 mb-0 small text-muted">Já foi aprovado? <a href="../../login.php" class="auth-link">Entrar</a></p>
    </section>
</body>
</html>
