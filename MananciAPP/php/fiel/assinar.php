<?php
// Assinatura do fiel via Asaas (ver docs/interno/assinaturas/assinaturas_asaas.md, seção
// 4.2). Cria a assinatura e manda o fiel pra página de pagamento do Asaas;
// a liberação vem do webhook_asaas.php. Ao voltar pra cá com a assinatura
// ainda pendente/atrasada, a página consulta o Asaas direto (rede de
// segurança caso o webhook tenha atrasado).
//
// Também: teste grátis, cancelar (mantém o acesso até o fim do período
// pago) e, nos planos Família/Igreja, liberar outras contas.
//
// No modo "só assinantes" (Admin → Assinaturas), quem não tem acesso é
// mandado pra cá por auth_require() em qualquer página — aí a tela abre
// em modo "tela cheia" (sem o menu do app), no estilo "escolha seu plano".
include("../conexao.php");
auth_require();

$fiel_id = auth_resolve_fiel_id();
$erro = "";
$mensagem = "";
$disponivel = assinatura_modulo_ativo() && $conn;
$acao = site_entrada_texto($_POST['acao'] ?? '', false);

// Assinaturas fechadas (Admin → Assinaturas): link antigo ou digitado
// volta para a home, em vez de mostrar planos que ainda não valem.
if (asaas_configurado() && !assinaturas_abertas()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $disponivel && $fiel_id) {
    if (!csrf_validar()) {
        $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
        if (($_POST['formato'] ?? '') === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('ok' => false, 'url' => '', 'erro' => $erro));
            exit;
        }
    } elseif ($acao === 'assinar') {
        $resultado = assinatura_iniciar($conn, $fiel_id, (int) ($_POST['plano_id'] ?? 0), site_entrada_texto($_POST['documento'] ?? '', false));
        // Pedido do JS da própria página (abre o pagamento em outra aba):
        // devolve só a URL da fatura, sem redirecionar esta aba.
        if (($_POST['formato'] ?? '') === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('ok' => $resultado['ok'], 'url' => $resultado['url'], 'erro' => $resultado['erro']));
            exit;
        }
        if ($resultado['ok']) {
            header('Location: ' . $resultado['url']);
            exit;
        }
        $erro = $resultado['erro'];
    } elseif ($acao === 'teste') {
        $erro = assinatura_iniciar_teste($conn, $fiel_id);
        if ($erro === "") {
            header('Location: dashboard.php');
            exit;
        }
    } elseif ($acao === 'cancelar') {
        $antes_de_cancelar = assinatura_do_fiel($conn, $fiel_id);
        $erro = assinatura_cancelar($conn, $fiel_id);
        if ($erro === "") {
            $acesso_ate_antes = (string) ($antes_de_cancelar['idas_acesso_ate'] ?? '');
            if ($acesso_ate_antes === '') {
                $mensagem = "A cobrança foi cancelada. Você pode escolher um plano quando quiser.";
            } elseif (strtotime($acesso_ate_antes) > time()) {
                $mensagem = "Assinatura cancelada. Nenhuma nova cobrança será gerada, e você continua com acesso até " . assinar_formatar_data($acesso_ate_antes) . ".";
            } else {
                $mensagem = "Assinatura cancelada. Nenhuma nova cobrança será gerada.";
            }
        }
    } elseif ($acao === 'adicionar_beneficiario') {
        $erro = assinatura_adicionar_beneficiario($conn, $fiel_id, site_entrada_texto($_POST['email'] ?? '', false));
        $mensagem = $erro === "" ? "Pessoa adicionada ao seu plano." : "";
    } elseif ($acao === 'remover_beneficiario') {
        if (assinatura_remover_beneficiario($conn, $fiel_id, (int) ($_POST['beneficiario_id'] ?? 0))) {
            $mensagem = "Pessoa removida do seu plano.";
        } else {
            $erro = "Não foi possível remover. Tente de novo.";
        }
    }
}

$assinatura = ($disponivel && $fiel_id) ? assinatura_do_fiel($conn, $fiel_id) : null;
// Consulta ao Asaas (rede de segurança para webhook atrasado) no máximo a
// cada 12 s por sessão — a página recarrega sozinha a cada 15 s enquanto há
// cobrança aberta (ver o script no fim), e cada recarga ou F5 repetido era
// uma chamada à API de até 15 s. Se a chamada falhar, espera 1 min antes de
// tentar de novo, em vez de travar toda recarga no tempo limite.
if ($assinatura && in_array($assinatura['idas_status'], array('PENDENTE', 'ATRASADA'), true)
    && time() >= (int) ($_SESSION['asaas_sync_proxima'] ?? 0)) {
    $sincronizou = asaas_sincronizar_assinatura($conn, $assinatura);
    $_SESSION['asaas_sync_proxima'] = time() + ($sincronizou ? 12 : 60);
    if ($sincronizou) {
        $assinatura = assinatura_do_fiel($conn, $fiel_id);
    }
}
$tem_subscription = $assinatura && !empty($assinatura['idas_asaas_subscription_id']);
$pode_assinar = $disponivel && (!$tem_subscription || $assinatura['idas_status'] === 'CANCELADA');
$planos = $pode_assinar ? assinatura_planos_ativos($conn) : array();
$beneficio = ($disponivel && $fiel_id) ? assinatura_beneficio_do_fiel($conn, $fiel_id) : null;
$teste_disponivel = $disponivel && assinatura_teste_disponivel($conn, $fiel_id);
$teste_dias = assinatura_teste_dias();

$max_beneficiarios = (int) ($assinatura['idp_max_beneficiarios'] ?? 0);
$mostra_beneficiarios = $tem_subscription && $max_beneficiarios > 0 && $assinatura['idas_status'] !== 'CANCELADA';
$beneficiarios = $mostra_beneficiarios ? assinatura_beneficiarios($conn, $assinatura['idas_id']) : array();

// Tela cheia = o fiel foi barrado no login (modo "só assinantes") e ainda
// não tem acesso: some o menu do app, fica só a escolha do plano.
$tela_cheia = $disponivel && assinatura_exige_no_login() && !assinatura_fiel_tem_acesso($conn, $fiel_id);

// Documento já usado antes (ou o CPF do cadastro) vem preenchido.
$documento_padrao = (string) ($assinatura['idas_documento'] ?? '');
if ($documento_padrao === '' && $fiel_id) {
    $res_cpf = mysqli_query($conn, "SELECT idf_cpf FROM id_fiel WHERE idf_id = $fiel_id LIMIT 1");
    $documento_padrao = trim((string) (($res_cpf ? mysqli_fetch_assoc($res_cpf) : null)['idf_cpf'] ?? ''));
}
$algum_aceita_cnpj = false;
foreach ($planos as $plano) {
    $algum_aceita_cnpj = $algum_aceita_cnpj || (int) $plano['idp_aceita_cnpj'] === 1;
}

$status_info = array(
    'PENDENTE' => array('Aguardando pagamento', 'bg-warning text-dark'),
    'ATIVA' => array('Ativa', 'bg-success'),
    'ATRASADA' => array('Pagamento atrasado', 'bg-danger'),
    'CANCELADA' => array('Cancelada', 'bg-secondary'),
    'CORTESIA' => array('Cortesia', 'bg-info text-dark'),
    'TESTE' => array('Teste grátis', 'bg-primary'),
);

function assinar_formatar_data($valor) {
    if (empty($valor)) {
        return '';
    }
    $data = new DateTime($valor);
    $data->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    return $data->format('d/m/Y');
}

$acesso_ate_texto = assinar_formatar_data($assinatura['idas_acesso_ate'] ?? null);
$acesso_futuro = $assinatura && !empty($assinatura['idas_acesso_ate']) && strtotime($assinatura['idas_acesso_ate']) > time();
$status_atual = (string) ($assinatura['idas_status'] ?? '');

// Título da tela cheia conforme a situação de quem foi barrado.
if ($status_atual === 'PENDENTE') {
    $hero_titulo = 'Falta só o pagamento';
    $hero_texto = 'Sua assinatura foi criada. Assim que o pagamento for confirmado, o acesso é liberado.';
} elseif ($status_atual === 'ATRASADA') {
    $hero_titulo = 'Sua mensalidade está em aberto';
    $hero_texto = 'Pague a cobrança pendente para voltar a assistir.';
} elseif ($status_atual === 'TESTE') {
    $hero_titulo = 'Seu teste grátis terminou';
    $hero_texto = 'Gostou? Escolha um plano para continuar assistindo.';
} elseif ($assinatura && !empty($assinatura['idas_acesso_ate'])) {
    $hero_titulo = 'Seu acesso terminou';
    $hero_texto = 'Escolha um plano para voltar a assistir.';
} else {
    $hero_titulo = 'Você ainda não tem uma assinatura';
    $hero_texto = 'Escolha um plano para assistir a todos os cursos da Missão Manancial.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Assinatura</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="<?php echo $tela_cheia ? 'pagina-planos-cheia' : ''; ?>" style="transition: background 0.3s ease;">
    <?php if ($tela_cheia) {
        $planos_usuario_nome = trim((string) ($_SESSION['Usuario_nome'] ?? ''));
        $planos_iniciais = '';
        foreach (preg_split('/\s+/', $planos_usuario_nome) as $parte) {
            if ($parte !== '' && mb_strlen($planos_iniciais) < 2) {
                $planos_iniciais .= mb_strtoupper(mb_substr($parte, 0, 1));
            }
        }
    ?>
        <header class="planos-topo">
            <div class="planos-marca">
                <img src="../../assets/logo-sm.png" alt="">
                <span>Missão Manancial</span>
            </div>
            <div class="planos-usuario">
                <?php if ($planos_usuario_nome !== '') { ?>
                    <span class="planos-avatar" aria-hidden="true"><?php echo htmlspecialchars($planos_iniciais !== '' ? $planos_iniciais : 'U'); ?></span>
                    <span class="planos-usuario-nome"><?php echo htmlspecialchars($planos_usuario_nome); ?></span>
                <?php } ?>
                <a href="sair.php?t=<?php echo logout_token(); ?>" class="btn-voltar-login" title="Sair e voltar para a tela de login">
                    <i class="fas fa-arrow-left"></i><span>Voltar ao login</span>
                </a>
            </div>
        </header>
    <?php } else { ?>
        <?php $navbar_active = 'assinatura'; include __DIR__ . '/includes/navbar_fiel.php'; ?>
    <?php } ?>

    <main class="container py-5 pagina-assinatura">
        <div style="max-width: <?php echo $tela_cheia ? '980px' : '760px'; ?>; margin: 0 auto;">
            <?php if ($tela_cheia) { ?>
                <div class="planos-hero">
                    <span class="planos-hero-selo"><i class="fas fa-crown"></i>Assinatura</span>
                    <h1><?php echo htmlspecialchars($hero_titulo); ?></h1>
                    <p><?php echo htmlspecialchars($hero_texto); ?></p>
                </div>
            <?php } else { ?>
                <div class="streaming-page-header">
                    <h1><i class="fas fa-crown me-2" style="color:#fbbf24;"></i>Assinatura</h1>
                    <p>Acesso completo aos cursos da Missão Manancial</p>
                </div>
            <?php } ?>

            <?php if (isset($_GET['aguardando']) && $status_atual === 'ATIVA') { ?>
                <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="fas fa-circle-check me-1"></i><strong>Pagamento confirmado!</strong> Seu acesso está liberado.</span>
                    <a href="dashboard.php" class="btn btn-success btn-sm fw-bold">Começar a assistir</a>
                </div>
            <?php } ?>
            <?php if (isset($_GET['aguardando']) && in_array($status_atual, array('PENDENTE', 'ATRASADA'), true)) { ?>
                <div class="alert alert-info">
                    <i class="fas fa-up-right-from-square me-1"></i>Abrimos o pagamento em uma <strong>nova aba</strong>.
                    Depois de pagar, volte para esta aba — ela confere o pagamento sozinha e libera o acesso.
                </div>
            <?php } ?>
            <div class="alert alert-danger d-none" id="erroPagamento" role="alert"></div>
            <?php if (isset($_GET['bloqueado']) && $erro === "" && $mensagem === "" && !$tela_cheia) { ?>
                <div class="alert alert-info">Esse conteúdo é exclusivo para assinantes. Escolha um plano abaixo para continuar assistindo.</div>
            <?php } ?>
            <?php if ($mensagem !== "") { ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
            <?php } ?>
            <?php if ($erro !== "") { ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
            <?php } ?>

            <?php if (!$disponivel) { ?>
                <div class="streaming-info-card text-start mb-4">
                    <p class="mb-0">As assinaturas ainda não estão disponíveis. Volte em breve!</p>
                </div>
            <?php } ?>

            <?php if ($beneficio) { ?>
                <div class="streaming-info-card text-start mb-4">
                    <h6 class="mb-2"><i class="fas fa-users me-2" style="color: var(--primary-light);"></i>Acesso pelo plano de outra pessoa</h6>
                    <p class="mb-0">
                        Você faz parte do plano <strong><?php echo htmlspecialchars($beneficio['idp_nome']); ?></strong>
                        de <?php echo htmlspecialchars($beneficio['titular_nome']); ?><?php $ate_benef = assinar_formatar_data($beneficio['idas_acesso_ate'] ?? null); echo $ate_benef !== '' ? ', com acesso até ' . $ate_benef : ''; ?>.
                    </p>
                </div>
            <?php } ?>

            <?php if ($assinatura && !$tem_subscription && $acesso_futuro) { ?>
                <div class="streaming-info-card text-start mb-4">
                    <?php if ($status_atual === 'TESTE') { ?>
                        <h6 class="mb-2"><i class="fas fa-hourglass-half me-2" style="color: var(--primary-light);"></i>Teste grátis</h6>
                        <p class="mb-0">Seu teste grátis vai até <strong><?php echo $acesso_ate_texto; ?></strong>. Assine abaixo para não perder o acesso depois.</p>
                    <?php } else { ?>
                        <h6 class="mb-2"><i class="fas fa-gift me-2" style="color: var(--primary-light);"></i>Acesso cortesia</h6>
                        <p class="mb-0">Você tem acesso liberado pela igreja até <strong><?php echo $acesso_ate_texto; ?></strong>.</p>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php // Cancelada e com o acesso já vencido não tem mais o que mostrar.
            if ($tem_subscription && ($status_atual !== 'CANCELADA' || $acesso_futuro)) {
                $info = $status_info[$status_atual] ?? array($status_atual, 'bg-secondary');
                $url_aberta = (string) ($assinatura['idas_cobranca_aberta_url'] ?? '');
                $nunca_pagou = empty($assinatura['idas_acesso_ate']);
            ?>
                <div class="streaming-info-card text-start mb-4">
                    <h6 class="mb-3"><i class="fas fa-receipt me-2" style="color: var(--primary-light);"></i>Minha assinatura</h6>
                    <p class="mb-2">
                        <strong>Plano:</strong> <?php echo htmlspecialchars((string) ($assinatura['idp_nome'] ?? '—')); ?>
                        <?php if (!empty($assinatura['idp_valor_centavos'])) { ?>
                            (<?php echo assinatura_formatar_valor($assinatura['idp_valor_centavos']); ?>/<?php echo assinatura_ciclo_texto($assinatura['idp_ciclo'] ?? ''); ?>)
                        <?php } ?>
                    </p>
                    <p class="mb-2"><strong>Status:</strong> <span class="badge <?php echo $info[1]; ?>"><?php echo htmlspecialchars($info[0]); ?></span></p>
                    <?php if ($acesso_ate_texto !== '') { ?>
                        <p class="mb-2"><strong>Acesso garantido até:</strong> <?php echo $acesso_ate_texto; ?></p>
                    <?php } ?>
                    <?php if ($url_aberta !== '' && in_array($status_atual, array('PENDENTE', 'ATRASADA'), true)) { ?>
                        <a href="<?php echo htmlspecialchars($url_aberta); ?>" target="_blank" rel="noopener" class="btn btn-light fw-bold mt-2"><i class="fas fa-qrcode me-2"></i>Pagar agora <i class="fas fa-up-right-from-square ms-1 small"></i></a>
                        <a href="assinar.php" class="btn btn-outline-light mt-2">Já paguei — atualizar</a>
                        <small class="d-block text-muted mt-2">Pix costuma ser confirmado em poucos segundos; boleto leva até 3 dias úteis.</small>
                    <?php } ?>
                    <?php if ($status_atual !== 'CANCELADA') { ?>
                        <form method="POST" class="mt-3" data-confirmar="<?php echo htmlspecialchars($nunca_pagou
                            ? 'Cancelar esta cobrança? Você volta para a escolha de planos.'
                            : 'Cancelar a assinatura? Nenhuma nova cobrança será gerada, e você continua com acesso até ' . $acesso_ate_texto . '.', ENT_QUOTES); ?>">
                            <?php echo csrf_campo(); ?>
                            <input type="hidden" name="acao" value="cancelar">
                            <button type="submit" class="btn btn-link text-danger p-0"><?php echo $nunca_pagou ? 'Cancelar e escolher outro plano' : 'Cancelar assinatura'; ?></button>
                        </form>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if ($mostra_beneficiarios) { ?>
                <div class="streaming-info-card text-start mb-4">
                    <h6 class="mb-2"><i class="fas fa-user-plus me-2" style="color: var(--primary-light);"></i>Pessoas no meu plano (<?php echo count($beneficiarios); ?>/<?php echo $max_beneficiarios; ?>)</h6>
                    <p class="text-muted small mb-3">A pessoa precisa ter conta aprovada no app. Informe o e-mail que ela usa para entrar.</p>
                    <?php if (!empty($beneficiarios)) { ?>
                        <ul class="list-unstyled mb-3">
                            <?php foreach ($beneficiarios as $benef) { ?>
                                <li class="d-flex justify-content-between align-items-center py-1">
                                    <span><?php echo htmlspecialchars($benef['idf_nome']); ?> <small class="text-muted"><?php echo htmlspecialchars($benef['idf_email']); ?></small></span>
                                    <form method="POST" class="d-inline" data-confirmar="Remover essa pessoa do seu plano?">
                                        <?php echo csrf_campo(); ?>
                                        <input type="hidden" name="acao" value="remover_beneficiario">
                                        <input type="hidden" name="beneficiario_id" value="<?php echo (int) $benef['idf_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Remover"><i class="fas fa-times"></i></button>
                                    </form>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                    <?php if (count($beneficiarios) < $max_beneficiarios) { ?>
                        <form method="POST" class="d-flex gap-2">
                            <?php echo csrf_campo(); ?>
                            <input type="hidden" name="acao" value="adicionar_beneficiario">
                            <input type="email" name="email" class="form-control custom-input" placeholder="E-mail da pessoa" aria-label="E-mail da pessoa" required maxlength="100">
                            <button type="submit" class="btn btn-light fw-bold text-nowrap">Adicionar</button>
                        </form>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if ($teste_disponivel) { ?>
                <form method="POST" class="teste-gratis-box d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <?php echo csrf_campo(); ?>
                    <input type="hidden" name="acao" value="teste">
                    <div>
                        <div class="fw-bold fs-5"><i class="fas fa-gift me-2" style="color:#22c55e;"></i>Experimente grátis por <?php echo $teste_dias; ?> dia<?php echo $teste_dias > 1 ? 's' : ''; ?></div>
                        <small class="text-muted">Acesso completo, sem cartão e sem compromisso. Depois é só escolher um plano.</small>
                    </div>
                    <button type="submit" class="btn btn-success fw-bold">Começar teste grátis</button>
                </form>
            <?php } ?>

            <?php if ($pode_assinar) { ?>
                <?php if (empty($planos)) { ?>
                    <div class="streaming-info-card text-start mb-4">
                        <p class="mb-0">Nenhum plano disponível no momento.</p>
                    </div>
                <?php } else { ?>
                    <form method="POST" id="formAssinar">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="assinar">
                        <?php if (!$tela_cheia) { ?><h5 class="mb-3">Escolha seu plano</h5><?php } ?>
                        <div class="row g-3 mb-4">
                            <?php foreach ($planos as $indice => $plano) {
                                $pid = (int) $plano['idp_id'];
                                $max_plano = (int) $plano['idp_max_beneficiarios'];
                            ?>
                                <div class="col-md-6<?php echo count($planos) >= 3 ? ' col-lg-4' : ''; ?>">
                                    <label class="plano-card" for="plano_<?php echo $pid; ?>">
                                        <input type="radio" name="plano_id" id="plano_<?php echo $pid; ?>" value="<?php echo $pid; ?>" <?php echo $indice === 0 ? 'checked' : ''; ?>>
                                        <span class="plano-card-check"><i class="fas fa-check"></i></span>
                                        <span class="plano-card-nome"><?php echo htmlspecialchars($plano['idp_nome']); ?></span>
                                        <span class="plano-card-preco"><?php echo assinatura_formatar_valor($plano['idp_valor_centavos']); ?><small>/<?php echo assinatura_ciclo_texto($plano['idp_ciclo']); ?></small></span>
                                        <?php if (!empty($plano['idp_descricao'])) { ?>
                                            <span class="text-muted"><?php echo htmlspecialchars($plano['idp_descricao']); ?></span>
                                        <?php } ?>
                                        <ul>
                                            <li>Todos os cursos e aulas</li>
                                            <?php if ($max_plano > 0) { ?><li>Você + <?php echo $max_plano; ?> pessoa<?php echo $max_plano > 1 ? 's' : ''; ?></li><?php } ?>
                                            <li><?php echo $plano['idp_forma_pagamento'] === 'CREDIT_CARD' ? 'Cartão de crédito, renova sozinho' : ($plano['idp_forma_pagamento'] === 'UNDEFINED' ? 'Pix, boleto ou cartão' : ($plano['idp_forma_pagamento'] === 'PIX' ? 'Pix' : 'Boleto')); ?></li>
                                            <li>Cancele quando quiser</li>
                                        </ul>
                                    </label>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="streaming-info-card text-start mb-3">
                            <label class="form-label" for="documento"><?php echo $algum_aceita_cnpj ? 'CPF de quem paga (ou CNPJ da igreja, no plano Igreja)' : 'CPF de quem paga'; ?></label>
                            <input type="text" inputmode="numeric" id="documento" name="documento" class="form-control custom-input" placeholder="000.000.000-00" maxlength="18" value="<?php echo htmlspecialchars($documento_padrao); ?>" required>
                            <small class="text-muted d-block mt-1">O Asaas exige o documento para emitir a cobrança.</small>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn-continuar-pagamento"><i class="fas fa-lock"></i>Continuar para o pagamento</button>
                            <small class="d-block text-muted mt-2">Você paga na página segura do Asaas. O app nunca vê os dados do seu cartão.</small>
                        </div>
                    </form>
                <?php } ?>
            <?php } ?>
        </div>
    </main>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
<script nonce="<?php echo site_csp_nonce(); ?>">
    // Evita duplo envio do teste grátis (o servidor também trava).
    document.querySelectorAll('form input[name="acao"][value="teste"]').forEach(function (campo) {
        campo.form.addEventListener('submit', function () {
            var botao = campo.form.querySelector('button[type="submit"]');
            if (botao) { botao.disabled = true; botao.textContent = 'Aguarde...'; }
        });
    });

    // A página de pagamento do Asaas não tem botão de voltar pro app, então
    // ela abre em OUTRA aba e esta fica esperando a confirmação. A aba nova
    // é aberta já no clique (senão o navegador bloqueia como pop-up) e só
    // depois recebe a URL da fatura. Se o navegador bloquear mesmo assim,
    // envia o formulário normal (pagamento na mesma aba, como antes).
    (function () {
        var form = document.getElementById('formAssinar');
        if (!form || !window.fetch || !window.FormData) { return; }
        var caixaErro = document.getElementById('erroPagamento');

        form.addEventListener('submit', function (evento) {
            var aba = window.open('', '_blank');
            if (!aba) { return; }
            evento.preventDefault();

            var botao = form.querySelector('button[type="submit"]');
            var textoBotao = botao.innerHTML;
            botao.disabled = true;
            botao.textContent = 'Gerando cobrança...';
            caixaErro.classList.add('d-none');
            try { aba.document.title = 'Abrindo pagamento...'; aba.document.body.innerHTML = '<p style="font-family:sans-serif;padding:2rem">Abrindo a página de pagamento...</p>'; } catch (e) {}

            var dados = new FormData(form);
            dados.append('formato', 'json');
            fetch('assinar.php', { method: 'POST', body: dados, credentials: 'same-origin' })
                .then(function (resposta) { return resposta.json(); })
                .then(function (resultado) {
                    if (!resultado.ok) { throw new Error(resultado.erro || 'Não foi possível iniciar a assinatura.'); }
                    aba.opener = null;
                    aba.location.href = resultado.url;
                    window.location.href = 'assinar.php?aguardando=1';
                })
                .catch(function (erro) {
                    aba.close();
                    caixaErro.textContent = (erro && erro.message) ? erro.message : 'Não foi possível iniciar a assinatura. Tente de novo.';
                    caixaErro.classList.remove('d-none');
                    caixaErro.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    botao.disabled = false;
                    botao.innerHTML = textoBotao;
                });
        });
    })();

    <?php if (in_array($status_atual, array('PENDENTE', 'ATRASADA'), true) && !empty($assinatura['idas_cobranca_aberta_url'])) { ?>
    // Cobrança em aberto (o fiel está pagando na outra aba): recarrega a
    // cada 15s enquanto esta aba estiver visível — o recarregamento consulta
    // o Asaas (asaas_sincronizar_assinatura) e mostra o acesso liberado
    // assim que o pagamento cair, mesmo sem webhook.
    setInterval(function () {
        if (document.visibilityState === 'visible') { window.location.reload(); }
    }, 15000);
    <?php } ?>
</script>
</body>
</html>
