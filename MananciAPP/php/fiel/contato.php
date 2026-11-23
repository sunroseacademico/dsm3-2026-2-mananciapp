<?php
$painel_link = "dashboard.php";
session_start();
include("../conexao.php");
auth_require();

if(!isset($_SESSION["Usuario_logado"])) {
    header("location:../../login.php");
    exit;
}
$titulo_nav = "Missão Evangélica Manancial da Esperança";
if(isset($_SESSION["Usuario_tipo"]) && $_SESSION["Usuario_tipo"] === "admin") {
    $painel_link = "../admin/admin_conteudos.php";
    $titulo_nav = "Painel Administrativo";
}

// Dados de contato e endereço do formulário vêm do ambiente (painel do
// Render / .env): antes a página mostrava dados de exemplo ("Rua Exemplo",
// "(11) 99999-9999") e o formulário ia para uma conta pessoal de teste do
// Formspree (ver docs/interno/pontos_gerais.md, seção 1.4). Linha sem valor não aparece.
// Contatos de Admin → Identidade da igreja (sem preencher, as variáveis
// CONTATO_* do Render, como antes).
$igreja_contato = igreja_info();
$contato_email = $igreja_contato['email'];
$contato_telefone = $igreja_contato['telefone'];
$contato_endereco = $igreja_contato['endereco'];
$contato_whatsapp = igreja_whatsapp_link('Olá! Vim pelo app da igreja.');
$formspreeEndpoint = trim((string) site_env('CONTATO_FORMSPREE_URL'));
if ($formspreeEndpoint === '' || !preg_match('#^https://formspree\.io/f/[A-Za-z0-9]+$#', $formspreeEndpoint)) {
    // Sem a variável, o formulário some (ficam só os contatos acima) e o
    // log avisa. Antes caía numa conta de teste do Formspree: numa igreja
    // nova, nome, e-mail e mensagem dos fiéis iam para um terceiro.
    site_avisar_config_faltando('CONTATO_FORMSPREE_URL', 'formulário de contato escondido');
    $formspreeEndpoint = '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Contato</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body>
    <?php $navbar_active = 'contato'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container py-5">
        <div class="auth-container" style="max-width: 900px; margin: 0 auto;">
            <h1 class="mb-3">Fale conosco</h1>
            <?php if ($contato_whatsapp !== '') { ?>
                <p class="mb-3"><a href="<?php echo htmlspecialchars($contato_whatsapp); ?>" class="btn btn-play" target="_blank" rel="noopener"><i class="fab fa-whatsapp me-2" aria-hidden="true"></i>Falar no WhatsApp</a></p>
            <?php } ?>
            <?php if ($contato_email !== '') { ?>
                <p class="mb-1"><strong>E-mail:</strong> <a href="mailto:<?php echo htmlspecialchars($contato_email); ?>"><?php echo htmlspecialchars($contato_email); ?></a></p>
            <?php } ?>
            <?php if ($contato_telefone !== '') { ?>
                <p class="mb-1"><strong>Telefone:</strong> <?php echo htmlspecialchars($contato_telefone); ?></p>
            <?php } ?>
            <?php if ($contato_endereco !== '') { ?>
                <p class="mb-3"><strong>Endereço:</strong> <?php echo htmlspecialchars($contato_endereco); ?></p>
            <?php } ?>
            <?php if ($formspreeEndpoint === '' && $contato_whatsapp === '' && $contato_email === '' && $contato_telefone === '' && $contato_endereco === '') { ?>
                <p class="mb-3">Os contatos da igreja ainda não foram configurados. Fale com a liderança pessoalmente ou pelo grupo da igreja.</p>
            <?php } elseif ($contato_email === '' && $contato_telefone === '' && $contato_endereco === '') { ?>
                <p class="mb-3">Mande sua mensagem pelo formulário abaixo.</p>
            <?php } ?>

            <?php if ($formspreeEndpoint !== '') { ?>
                        <h5 class="mt-4">Formulário</h5>
                        <form id="contactForm" method="POST" action="<?php echo htmlspecialchars($formspreeEndpoint); ?>" class="mt-3" autocomplete="off">
                <div class="mb-3">
                                        <input aria-label="Seu nome" type="text" id="nome" name="nome" class="form-control custom-input" placeholder="Seu nome" required maxlength="80">
                </div>
                <div class="mb-3">
                                        <input aria-label="Seu e-mail" type="email" id="email" name="email" class="form-control custom-input" placeholder="Seu e-mail" required maxlength="120">
                </div>
                <div class="mb-3">
                                        <textarea aria-label="Sua mensagem" id="mensagem" name="mensagem" class="form-control custom-input" rows="4" placeholder="Sua mensagem" required maxlength="500"></textarea>
                </div>
                <button type="submit" class="btn btn-light">Enviar</button>
            </form>
            <?php } ?>
            <!-- back button removed (now in navbar) -->
        </div>
    </main>

    <script nonce="<?php echo site_csp_nonce(); ?>">
        (function(){
            const form = document.getElementById("contactForm");
            if (!form) return;
            const emailInput = document.getElementById("email");
            const messageInput = document.getElementById("mensagem");

            const emailRegex = /^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/;
            const fakeEmails = ["test@test.com", "a@a.com", "email@email.com"];
            const forbiddenWords = [
                "idiota", "burro", "lixo", "merda", "porra",
                "script", "<script", "select *", "drop table",
                "http://", "https://", "spam", "hack"
            ];

            form.addEventListener("submit", function (event) {
                const email = (emailInput.value || '').trim().toLowerCase();
                const message = (messageInput.value || '').toLowerCase();
                const action = form.getAttribute("action") || '';

                if (action.indexOf("SEU_ID_AQUI") !== -1) {
                    (window.siteAvisar || window.alert)("Configure seu endpoint do Formspree antes de enviar.", 'erro');
                    event.preventDefault();
                    return;
                }

                if (!emailRegex.test(email)) {
                    (window.siteAvisar || window.alert)("Por favor, insira um e-mail válido.", 'erro');
                    event.preventDefault();
                    return;
                }

                if (fakeEmails.includes(email)) {
                    (window.siteAvisar || window.alert)("E-mail inválido ou genérico.", 'erro');
                    event.preventDefault();
                    return;
                }

                for (const word of forbiddenWords) {
                    if (message.includes(word)) {
                        (window.siteAvisar || window.alert)("Sua mensagem contém termos inadequados ou suspeitos.", 'erro');
                        event.preventDefault();
                        return;
                    }
                }

                // Mantem envio na mesma aba e marca retorno para ir ao inicio
                sessionStorage.setItem("formspreeRetorno", "1");
            });

            // Ao voltar da tela do Formspree, redireciona para a tela inicial
            window.addEventListener("pageshow", function(event) {
                const veioDoFormspree = (document.referrer || '').indexOf("formspree.io") !== -1;
                const deveIrParaInicio = sessionStorage.getItem("formspreeRetorno") === "1";

                if (veioDoFormspree && deveIrParaInicio) {
                    sessionStorage.removeItem("formspreeRetorno");
                    form.reset();
                    window.location.replace("<?php echo $painel_link; ?>");
                    return;
                }

                if (event.persisted) {
                    form.reset();
                }
            });
        })();
    </script>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
