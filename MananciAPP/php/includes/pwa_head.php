<?php
// Tags de PWA (manifest, ícones, meta iOS) — incluído no <head> de toda página.
// Usa SITE_BASE_URL (definida em php/conexao.php) para os caminhos sempre
// apontarem pra raiz do projeto, não importa a profundidade da página atual
// (raiz, php/admin/*, php/fiel/*).
$pwa_base = defined('SITE_BASE_URL') ? SITE_BASE_URL : '/';

// Scope do Service Worker: só o caminho (sem protocolo/host), pra bater com
// o que navigator.serviceWorker.register() recebe em js/pwa.js. Em produção
// (Render, na raiz do domínio) isso é "/"; localmente (dentro de uma
// subpasta do XAMPP) seria "/nome-da-pasta/".
$pwa_scope = parse_url($pwa_base, PHP_URL_PATH) ?: '/';

// Amarra o dispositivo/navegador ao IDF_ID do fiel logado (OneSignal chama
// isso de "external_id") — sem isso, o backend não tem como mandar push pra
// UM usuário específico (só broadcast pra todo mundo). Só funciona se
// session_start() já rodou antes deste include (todo lugar que inclui este
// arquivo já chama isso via conexao.php ou no topo da própria página).
$pwa_onesignal_external_id = isset($_SESSION['Usuario_id']) ? (int) $_SESSION['Usuario_id'] : 0;
// sair.php redireciona pra cá com esse parametro pra desfazer o vinculo
// (ver php/fiel/sair.php) — sem isso, um navegador compartilhado continuaria
// "logado" no OneSignal pro usuário anterior mesmo depois do logout.
$pwa_onesignal_logout = isset($_GET['onesignal_logout']);
?>
<!-- PWA: manifest, cor do tema e ícones (Android/Chrome/Edge/Samsung Internet + iOS/Safari) -->
<link rel="manifest" href="<?php echo htmlspecialchars($pwa_base . 'manifest.json'); ?>">
<meta name="theme-color" content="#60a5fa">

<!-- Android / Chrome / Edge / Samsung Internet -->
<meta name="mobile-web-app-capable" content="yes">

<!-- iOS / Safari (não lê manifest.json para o modo "standalone", precisa das tags abaixo) -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Manancial">
<link rel="apple-touch-icon" href="<?php echo htmlspecialchars($pwa_base . 'assets/img/apple-touch-icon.png'); ?>">

<!-- Ícones / favicon -->
<link rel="icon" type="image/png" sizes="192x192" href="<?php echo htmlspecialchars($pwa_base . 'assets/img/icon-192.png'); ?>">
<link rel="icon" type="image/png" sizes="512x512" href="<?php echo htmlspecialchars($pwa_base . 'assets/img/icon-512.png'); ?>">

<?php if (site_alerta_dsn()) { ?>
<!-- Erros de JavaScript → Sentry, pelo servidor (ajax_erro_js.php). Sem
     defer: precisa estar escutando antes dos scripts da página rodarem. -->
<script src="<?php echo htmlspecialchars($pwa_base . 'js/erros.js?v=' . site_versao_estatico()); ?>" data-url="<?php echo htmlspecialchars($pwa_base . 'ajax_erro_js.php'); ?>"></script>
<?php } ?>
<script nonce="<?php echo site_csp_nonce(); ?>">window.PWA_BASE_URL = <?php echo json_encode($pwa_base); ?>;</script>
<!-- data-confirmar, data-enviar-ao-mudar etc.: substituem onsubmit=/onchange=,
     que a CSP com nonce não deixa rodar (ver site_csp_politica()). -->
<script src="<?php echo htmlspecialchars($pwa_base . 'js/acoes.js?v=' . site_versao_estatico()); ?>" defer></script>

<?php
// Uso pelo app instalado (seção 79, medalha "No bolso"): aberto pelo ícone
// (modo standalone), avisa o servidor uma vez. Depois de registrado hoje,
// este trecho nem sai na página. Quem usa pelo navegador não envia nada.
$pwa_fiel_app = $pwa_onesignal_external_id > 0 && isset($conn) && function_exists('acesso_app_disponivel') && acesso_app_disponivel($conn)
    ? (int) auth_resolve_fiel_id() : 0;
if ($pwa_fiel_app > 0 && !acesso_app_registrado_hoje($pwa_fiel_app)) { ?>
<script nonce="<?php echo site_csp_nonce(); ?>">
    (function () {
        // Só "standalone" (o modo do manifest.json): "fullscreen" também vale
        // para o F11 do navegador comum e daria a medalha sem o app.
        var app = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
            || window.navigator.standalone === true; // iPhone: aberto pela tela inicial
        if (!app) { return; }
        fetch(<?php echo json_encode($pwa_base . 'php/fiel/ajax_acesso_app.php'); ?>, {
            method: 'POST',
            keepalive: true,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: <?php echo json_encode(function_exists('csrf_token') ? csrf_token() : ''); ?> }),
        }).catch(function () {}); // falhou: a próxima página tenta de novo
    })();
</script>
<?php } ?>

<?php
// Páginas onde se digita senha (esqueci_senha.php, redefinir_senha.php)
// definem $pwa_sem_onesignal = true antes do include: o SDK é JavaScript de
// terceiros com acesso à página inteira, e lá ele não tem função nenhuma.
if (empty($pwa_sem_onesignal)) { ?>
<!-- OneSignal: notificações push. Usa o sw.js do próprio PWA (fundido via
     importScripts, ver sw.js) para evitar registrar um segundo Service
     Worker no mesmo scope. -->
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script nonce="<?php echo site_csp_nonce(); ?>">
    <?php if ($pwa_onesignal_external_id > 0) { ?>
    // Guarda a escolha do fiel sobre push em ID_PREFERENCIA
    // (IDPREF_NOTIFICACOES_PUSH), que é o switch "Receber notificações push"
    // de configuracoes.php — assim ele vê lá o que escolheu no convite e pode
    // mudar depois. Definida aqui fora (e não dentro do callback da
    // OneSignal) pra já existir antes do init/login terminarem.
    window.salvarPreferenciaPush = function (ativo) {
        return fetch(<?php echo json_encode($pwa_base . 'php/fiel/ajax_preferencia_push.php'); ?>, {
            method: 'POST',
            keepalive: true,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ativo: !!ativo, csrf_token: <?php echo json_encode(function_exists('csrf_token') ? csrf_token() : ''); ?> }),
        }).catch(function () {});
    };

    // "Mais tarde" no convite: a OneSignal não tem evento documentado pra
    // isso, então olhamos o clique no botão de cancelar do próprio convite
    // (id fixo do slidedown da OneSignal v16). Se a OneSignal mudar esse id,
    // só deixa de gravar o "não" — nada quebra.
    document.addEventListener('click', function (evento) {
        if (evento.target && evento.target.closest && evento.target.closest('#onesignal-slidedown-cancel-button')) {
            window.salvarPreferenciaPush(false);
        }
    }, true);
    <?php } ?>

    // Resolve quando init (e, logado, o login) da OneSignal terminam — quem
    // precisa ler o estado da inscrição (ex.: configuracoes.php) espera isso
    // em vez de ler cedo demais e mostrar "não inscrito" por engano.
    var resolverOneSignalPronto;
    window.oneSignalPronto = new Promise(function (resolver) { resolverOneSignalPronto = resolver; });

    window.OneSignalDeferred = window.OneSignalDeferred || [];
    OneSignalDeferred.push(async function (OneSignal) {
        try {
            await OneSignal.init({
                appId: <?php echo json_encode(ONESIGNAL_APP_ID); ?>,
                serviceWorkerParam: { scope: <?php echo json_encode($pwa_scope); ?> },
                serviceWorkerPath: <?php echo json_encode($pwa_base . 'sw.js'); ?>,
            });

            <?php if ($pwa_onesignal_external_id > 0) { ?>
            // "Permitir" no convite (ou ativar/desativar por qualquer
            // caminho): a inscrição deste navegador muda e o novo estado vai
            // pro banco. Registrado antes do login pra não perder um clique
            // rápido no convite.
            OneSignal.User.PushSubscription.addEventListener('change', function (evento) {
                if (evento && evento.current && evento.previous && evento.current.optedIn !== evento.previous.optedIn) {
                    window.salvarPreferenciaPush(evento.current.optedIn);
                }
            });

            // Fiel logado nesta página: amarra este dispositivo a ele, pra
            // onesignal_enviar_push() (php/conexao.php) conseguir mandar push só
            // pra ele (via include_aliases.external_id na API da OneSignal).
            // O valor vem de onesignal_external_id() (php/conexao.php) — tem
            // que ser igual ao usado no envio do push.
            await OneSignal.login(<?php echo json_encode(onesignal_external_id($pwa_onesignal_external_id)); ?>);
            // Tag pra diferenciar, na hora de mandar um push, quem é fiel
            // logado de quem só se inscreveu na Home sem nunca ter feito login
            // (ex.: alguém de fora da igreja querendo saber de transmissão ao
            // vivo). Um aviso de curso/evento novo, por exemplo, deve ir só pra
            // quem tem essa tag — crie um Segment no painel do OneSignal
            // filtrando por Tag "papel" = "fiel" (Audience > Segments), ou use
            // "filters": [{"field":"tag","key":"papel","relation":"=","value":"fiel"}]
            // numa chamada à API. Só é setada aqui (após login bem-sucedido) —
            // nunca é removida no logout de propósito, pra continuar
            // identificando "aparelho de um membro" mesmo se ele ficar um tempo
            // sem estar logado.
            await OneSignal.User.addTag("papel", "fiel");
            // Só AGORA (depois do login acima resolver) o dispositivo está de
            // fato vinculado — avisa o backend pra mandar qualquer push que
            // tenha ficado pendente (ex.: certificado de boas-vindas). Ver
            // notificacoes_push_enviar_pendentes() em php/conexao.php.
            // Uma vez por aba e por pessoa: antes ia em TODA página aberta
            // (~1 pedido e ~6 consultas por navegação). O que é criado depois
            // já sai na hora pelo servidor (resposta, live, mensagem do admin).
            var chaveSync = 'push_sync_' + <?php echo json_encode(onesignal_external_id($pwa_onesignal_external_id)); ?>;
            var jaSincronizou = false;
            try { jaSincronizou = sessionStorage.getItem(chaveSync) === '1'; } catch (e) {}
            if (!jaSincronizou) {
                fetch(<?php echo json_encode($pwa_base . 'php/fiel/ajax_onesignal_sync.php'); ?>, { method: 'POST', keepalive: true })
                    .then(function (r) { if (r.ok) { try { sessionStorage.setItem(chaveSync, '1'); } catch (e) {} } })
                    .catch(function () {});
            }
            <?php } elseif ($pwa_onesignal_logout) { ?>
            // Acabou de sair (ver php/fiel/sair.php) — desfaz o vinculo pra este
            // navegador nao continuar "marcado" como o usuario anterior.
            await OneSignal.logout();
            <?php } ?>
        } finally {
            resolverOneSignalPronto(OneSignal);
        }
    });
</script>
<?php } ?>
