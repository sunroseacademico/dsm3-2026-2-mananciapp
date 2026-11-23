<?php
// Página de um projeto de missão (T32): história, progresso, quadro de
// apoiadores e o formulário de contribuição (valor livre, Pix ou cartão).
// O pagamento abre numa aba nova, na página do Asaas; esta fica esperando
// a confirmação (como em assinar.php). Ver "CONTRIBUIÇÃO EM MISSÕES" em
// php/conexao.php e a seção 72 do bancosupabase.md.
include("../conexao.php");
auth_require();

$fiel_id = auth_resolve_fiel_id();
$projeto_id = (int) ($_GET['id'] ?? ($_POST['idmp_id'] ?? 0));
$disponivel = missoes_disponivel($conn);
$erro = '';

if ($disponivel && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'contribuir') {
    if (!csrf_validar()) {
        $resultado = array('ok' => false, 'url' => '', 'erro' => 'Sessão expirada. Atualize a página e tente de novo.');
    } else {
        $resultado = missao_contribuir(
            $conn,
            $fiel_id,
            $projeto_id,
            site_valor_para_centavos(site_entrada_texto($_POST['valor'] ?? '')),
            site_entrada_texto($_POST['forma'] ?? ''),
            site_entrada_texto($_POST['documento'] ?? ''),
            isset($_POST['anonimo']),
            site_entrada_texto($_POST['mensagem'] ?? '')
        );
    }
    // Pedido do JS da página (pagamento em outra aba): só a URL.
    if (($_POST['formato'] ?? '') === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resultado);
        exit;
    }
    if ($resultado['ok']) {
        header('Location: ' . $resultado['url']);
        exit;
    }
    $erro = $resultado['erro'];
}

$aguardando = isset($_GET['aguardando']);
// No máximo 1 consulta ao Asaas a cada 12 s por fiel: a página recarrega
// sozinha a cada 15 s esperando o Pix, e F5 ou abas em paralelo ocupavam os
// processos do Apache e gastavam o limite da chave do Asaas.
if ($disponivel && $aguardando && site_intervalo_livre('missao_sync_' . (int) $fiel_id, 12)) {
    missao_sincronizar_pendentes($conn, $fiel_id);
}
$projeto = $disponivel ? missao_projeto($conn, $projeto_id) : null;
if (!$projeto) {
    header('Location: missoes.php');
    exit;
}

$hoje = site_hoje();
$capa = missao_capa_url($projeto['idmp_capa_url'] ?? '');
$apoiadores = missao_apoiadores($conn, $projeto['idmp_id']);
$pode_contribuir = $projeto['situacao'] === 'aberto' && asaas_configurado();
$precisa_cpf = $pode_contribuir && missao_customer_do_fiel($conn, $fiel_id) === '';
// Pix direto (seção 77): QR Code fixo, sem valor — a pessoa digita quanto
// quer no app do banco. null sem a seção 77 ou se o Asaas falhar.
$pix_qr = $pode_contribuir ? missao_pix_qr($conn, $projeto) : null;
// O que esta página mostra (Admin → Missões / Contribuições, seção 78).
$exibe = missao_exibicao($projeto);
$mostrar_valores = $exibe['valores'];
// Oferta geral (é o que a aba Contribuir abre): mesma página, com textos de
// oferta em vez de missão e a aba Contribuir marcada no menu.
$eh_geral = (int) $projeto['idmp_id'] === missoes_oferta_geral_id();

// Contribuição deste fiel para este projeto ainda aguardando pagamento.
// "Confirmada" (no modo aguardando) olha só a contribuição MAIS RECENTE do
// fiel neste projeto: olhar o quadro de apoiadores dava "confirmada" na
// hora para quem já tinha apoiado antes, com o pagamento novo pendente.
$pendente = null;
$ultima = null;
// Contribuições deste fiel para este projeto ("Suas contribuições").
$minhas_projeto = array();
foreach (missao_contribuicoes_do_fiel($conn, $fiel_id, 30) as $c) {
    if ((int) $c['idmp_id'] !== (int) $projeto['idmp_id']) {
        continue;
    }
    $minhas_projeto[] = $c;
    if ($ultima === null) {
        $ultima = $c;
    }
    if ($pendente === null && in_array(strtoupper((string) $c['idmc_status']), array('PENDING', 'AWAITING_RISK_ANALYSIS'), true) && !empty($c['idmc_invoice_url'])) {
        $pendente = $c;
    }
}
$minha_confirmada = $ultima !== null && in_array(strtoupper((string) $ultima['idmc_status']), financeiro_status_pagos(), true);

$dias_restantes = !empty($projeto['idmp_data_fim']) ? (int) round((strtotime($projeto['idmp_data_fim']) - strtotime($hoje)) / 86400) : null;

// "hoje", "ontem", "há 5 dias", "em 12/03/2026".
function missao_quando($data, $hoje) {
    if (empty($data)) {
        return '';
    }
    $dias = (int) round((strtotime($hoje) - strtotime($data)) / 86400);
    if ($dias <= 0) {
        return 'hoje';
    }
    if ($dias === 1) {
        return 'ontem';
    }
    return $dias < 30 ? 'há ' . $dias . ' dias' : 'em ' . date('d/m/Y', strtotime($data));
}

// Cor do avatar a partir de um número (sempre a mesma para a mesma pessoa).
function missao_avatar_cor($semente) {
    // Tons escuros o bastante para a inicial branca ficar legível.
    $cores = array('#db2777', '#2563eb', '#047857', '#b45309', '#7c3aed', '#e11d48', '#0e7490', '#c2410c');
    return $cores[abs((int) $semente) % count($cores)];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | <?php echo htmlspecialchars($projeto['idmp_titulo']); ?></title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = $eh_geral ? 'contribuir' : 'missoes'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 1100px; margin: 0 auto;">
        <?php if (!$eh_geral) { ?>
            <a href="missoes.php" class="missao-voltar"><i class="fas fa-arrow-left me-1"></i>Todas as missões</a>
        <?php } ?>

        <?php if ($eh_geral && $capa) { ?>
            <!-- A capa da oferta geral é uma arte com o texto desenhado: vai inteira, sem nada por cima. -->
            <header class="contribuir-geral-arte is-pagina mb-4">
                <img src="<?php echo htmlspecialchars($capa); ?>" alt="" decoding="async">
                <h1 class="visually-hidden"><?php echo htmlspecialchars($projeto['idmp_titulo']); ?></h1>
            </header>
        <?php } else { ?>
        <header class="missao-hero<?php echo $capa ? '' : ' is-sem-capa'; ?>"<?php echo $capa ? ' style="background-image: url(\'' . htmlspecialchars($capa, ENT_QUOTES) . '\');"' : ''; ?>>
            <div class="missao-hero-conteudo">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?php if (!empty($projeto['idmp_local'])) { ?><span class="missao-chip-local is-hero"><i class="fas fa-location-dot me-1"></i><?php echo htmlspecialchars($projeto['idmp_local']); ?></span><?php } ?>
                    <?php if ($projeto['situacao'] === 'encerrado') { ?><span class="missao-chip-meta is-encerrado is-hero">Encerrado</span><?php } ?>
                    <?php if ($projeto['percentual'] !== null && $projeto['percentual'] >= 100) { ?><span class="missao-chip-meta is-hero">Meta alcançada 🎉</span><?php } ?>
                </div>
                <h1><?php echo htmlspecialchars($projeto['idmp_titulo']); ?></h1>
            </div>
        </header>
        <?php } ?>

        <?php if ($aguardando && !$minha_confirmada) { ?>
            <div class="alert alert-info d-flex align-items-center gap-2" role="status">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <span>Assim que o pagamento for confirmado na outra aba, sua contribuição aparece aqui. Pode levar alguns instantes.</span>
            </div>
        <?php } elseif ($aguardando && $minha_confirmada) { ?>
            <div class="alert alert-success" role="status"><i class="fas fa-heart me-2"></i>Contribuição confirmada! <?php echo $eh_geral ? 'Obrigado por ajudar a manter este espaço.' : 'Obrigado por fazer parte desta missão.'; ?> 🙏</div>
        <?php } ?>
        <?php if ($erro !== '') { ?>
            <div class="alert alert-danger" role="alert"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($erro); ?></div>
        <?php } ?>

        <div class="row g-4">
            <div class="col-lg-7 order-2 order-lg-1">
                <?php
                    $mostra_prazo = $dias_restantes !== null && $projeto['situacao'] === 'aberto';
                    $mostra_meta = $exibe['total'] && $projeto['percentual'] !== null;
                    if ($exibe['total'] || $exibe['apoiadores'] || $mostra_prazo) {
                ?>
                <section class="missao-progresso mb-4">
                    <?php if ($exibe['total']) { ?>
                    <div class="missao-progresso-valor">
                        <strong><?php echo assinatura_formatar_valor($projeto['arrecadado']); ?></strong>
                        <span><?php echo $projeto['percentual'] !== null ? 'arrecadados de ' . assinatura_formatar_valor($projeto['idmp_meta_centavos']) : 'arrecadados'; ?></span>
                    </div>
                    <?php } ?>
                    <?php if ($mostra_meta) { ?>
                        <div class="missao-barra is-grande" role="progressbar" aria-valuenow="<?php echo (int) $projeto['percentual']; ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progresso da meta"><span style="width: <?php echo max(2, (int) $projeto['percentual']); ?>%;"></span></div>
                    <?php } ?>
                    <div class="missao-progresso-numeros">
                        <?php if ($mostra_meta) { ?><div><strong><?php echo (int) $projeto['percentual']; ?>%</strong><small>da meta</small></div><?php } ?>
                        <?php if ($exibe['apoiadores']) { ?><div><strong><?php echo (int) $projeto['apoiadores']; ?></strong><small><?php echo (int) $projeto['apoiadores'] === 1 ? 'apoiador' : 'apoiadores'; ?></small></div><?php } ?>
                        <?php if ($mostra_prazo) { ?>
                            <div><strong><?php echo max(0, $dias_restantes); ?></strong><small><?php echo $dias_restantes === 1 ? 'dia restante' : 'dias restantes'; ?></small></div>
                        <?php } ?>
                    </div>
                </section>
                <?php } ?>

                <?php if (trim((string) $projeto['idmp_descricao']) !== '') { ?>
                    <section class="mb-4">
                        <h2 class="stream-section-title mb-3"><i class="fas fa-book-open me-2"></i><?php echo $eh_geral ? 'Para onde vai a sua oferta' : 'Sobre o projeto'; ?></h2>
                        <div class="missao-descricao"><?php echo htmlspecialchars($projeto['idmp_descricao']); ?></div>
                    </section>
                <?php } ?>

                <?php if ($minhas_projeto) { ?>
                    <section class="mb-4" id="minhas">
                        <h2 class="stream-section-title mb-3"><i class="fas fa-receipt me-2"></i>Suas contribuições</h2>
                        <ul class="missao-minhas">
                            <?php foreach (array_slice($minhas_projeto, 0, 10) as $c) {
                                $info = missao_status_info($c['idmc_status']);
                                $aguarda = in_array(strtoupper((string) $c['idmc_status']), array('PENDING', 'AWAITING_RISK_ANALYSIS'), true);
                            ?>
                                <li>
                                    <div class="min-w-0">
                                        <span class="fw-semibold"><?php echo assinatura_formatar_valor($c['idmc_valor_centavos']); ?></span>
                                        <small class="d-block text-muted">
                                            <?php echo $c['idmc_forma'] === 'CREDIT_CARD' ? 'cartão' : ($c['idmc_forma'] === 'PIX' ? 'Pix' : htmlspecialchars(strtolower((string) $c['idmc_forma']))); ?>
                                            · <?php echo date('d/m/Y', strtotime($c['idmc_pago_em'] ?: $c['idmc_criado_em'])); ?>
                                        </small>
                                    </div>
                                    <span class="testemunho-status <?php echo $info[1]; ?>"><?php echo $info[0]; ?></span>
                                    <?php if ($aguarda && !empty($c['idmc_invoice_url'])) { ?>
                                        <a href="<?php echo htmlspecialchars($c['idmc_invoice_url']); ?>" class="btn btn-sm btn-missao" target="_blank" rel="noopener">Pagar agora</a>
                                    <?php } elseif (in_array(strtoupper((string) $c['idmc_status']), financeiro_status_pagos(), true) && !empty($c['idmc_invoice_url'])) { ?>
                                        <a href="<?php echo htmlspecialchars($c['idmc_invoice_url']); ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Comprovante</a>
                                    <?php } ?>
                                </li>
                            <?php } ?>
                        </ul>
                    </section>
                <?php } ?>

                <?php if ($exibe['quadro']) { ?>
                <section class="mb-4" id="apoiadores">
                    <h2 class="stream-section-title mb-3"><i class="fas fa-people-group me-2"></i>Quadro de apoiadores</h2>
                    <?php if (!$apoiadores) { ?>
                        <div class="testemunhos-convite">
                            <i class="fas fa-seedling" aria-hidden="true"></i>
                            <div>
                                <strong>Ainda ninguém por aqui.</strong>
                                <p class="mb-0 small text-muted">Seja a primeira pessoa a <?php echo $eh_geral ? 'contribuir' : 'apoiar esta missão'; ?> e inspire outras a fazerem o mesmo.</p>
                            </div>
                        </div>
                    <?php } else { ?>
                        <ul class="apoiadores-lista">
                            <?php foreach ($apoiadores as $a) {
                                $anonimo = (int) $a['idmc_anonimo'] === 1;
                                $manual = $a['idmc_origem'] === 'manual';
                                $via_pix = $a['idmc_origem'] === 'qrcode';
                                $nome = $via_pix ? 'Oferta via Pix' : ($manual ? ((string) $a['idmc_nome_exibicao'] !== '' ? (string) $a['idmc_nome_exibicao'] : 'Oferta da comunidade') : ($anonimo ? 'Apoiador anônimo' : missao_nome_publico($a['idf_nome'])));
                                $eu = !$manual && !$via_pix && (int) $a['idf_id'] === (int) $fiel_id;
                            ?>
                                <li class="apoiador<?php echo $eu ? ' is-eu' : ''; ?>">
                                    <?php if ($via_pix) { ?>
                                        <span class="apoiador-avatar is-pix" aria-hidden="true"><i class="fa-brands fa-pix"></i></span>
                                    <?php } elseif ($manual) { ?>
                                        <span class="apoiador-avatar is-comunidade" aria-hidden="true"><i class="fas fa-church"></i></span>
                                    <?php } elseif ($anonimo) { ?>
                                        <span class="apoiador-avatar is-anonimo" aria-hidden="true"><i class="fas fa-heart"></i></span>
                                    <?php } else { ?>
                                        <span class="apoiador-avatar" style="background: <?php echo missao_avatar_cor($a['idf_id']); ?>;" aria-hidden="true"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($nome, 0, 1))); ?></span>
                                    <?php } ?>
                                    <div class="apoiador-corpo">
                                        <div class="apoiador-topo">
                                            <strong><?php echo htmlspecialchars($nome); ?><?php echo $eu ? ' <small class="text-muted fw-normal">(você)</small>' : ''; ?></strong>
                                            <?php if ($mostrar_valores) { ?><span class="apoiador-valor"><?php echo assinatura_formatar_valor($a['idmc_valor_centavos']); ?></span><?php } ?>
                                        </div>
                                        <small class="text-muted"><?php echo $manual || $via_pix ? 'oferta' : 'apoiou'; ?> <?php echo missao_quando($a['idmc_pago_em'], $hoje); ?></small>
                                        <?php if (!empty($a['idmc_mensagem'])) { ?>
                                            <p class="apoiador-mensagem"><?php echo htmlspecialchars($a['idmc_mensagem']); ?></p>
                                        <?php } ?>
                                    </div>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </section>
                <?php } ?>
            </div>

            <div class="col-lg-5 order-1 order-lg-2">
                <aside class="missao-apoiar">
                    <?php if ($projeto['situacao'] === 'encerrado') { ?>
                        <h2><i class="fas fa-flag-checkered me-2"></i>Projeto encerrado</h2>
                        <p class="mb-0">Este projeto não recebe mais contribuições. Obrigado a <?php echo !$exibe['apoiadores'] || (int) $projeto['apoiadores'] === 1 ? 'quem apoiou' : 'todos os ' . (int) $projeto['apoiadores'] . ' apoiadores'; ?>! 🙏</p>
                    <?php } elseif (!$pode_contribuir) { ?>
                        <h2><i class="fas fa-hand-holding-heart me-2"></i><?php echo $eh_geral ? 'Contribuir' : 'Apoiar esta missão'; ?></h2>
                        <p class="mb-0 text-muted">As contribuições pelo app estão temporariamente indisponíveis.</p>
                    <?php } else { ?>
                        <h2><i class="fas fa-hand-holding-heart me-2"></i><?php echo $eh_geral ? 'Contribuir' : 'Apoiar esta missão'; ?></h2>
                        <?php if ($pix_qr) { ?>
                            <!-- Pix direto: QR Code fixo, o valor é digitado no app do banco. -->
                            <section class="missao-pix-direto" aria-labelledby="pixDiretoTitulo">
                                <div class="missao-pix-topo">
                                    <i class="fa-brands fa-pix" aria-hidden="true"></i>
                                    <div>
                                        <h3 id="pixDiretoTitulo">Pix com o valor que quiser</h3>
                                        <small>Leia o QR Code no app do banco (ou copie o código Pix) e digite o valor. Sem valor mínimo.</small>
                                    </div>
                                </div>
                                <img class="missao-pix-qr" src="data:image/png;base64,<?php echo htmlspecialchars($pix_qr['imagem']); ?>" alt="QR Code Pix para contribuir com qualquer valor" width="200" height="200">
                                <div class="missao-pix-copiar">
                                    <input type="text" class="form-control custom-input" id="pixCopiaCola" value="<?php echo htmlspecialchars($pix_qr['payload']); ?>" readonly aria-label="Código Pix copia e cola">
                                    <button type="button" class="btn btn-missao" id="pixCopiar"><i class="fas fa-copy me-1"></i><span>Copiar código</span></button>
                                </div>
                                <small class="missao-pix-nota"><?php echo $exibe['quadro'] ? 'Por aqui a oferta entra no quadro como "Oferta via Pix", sem o seu nome, e não aparece em "Suas contribuições".' : 'Por aqui a oferta vai sem o seu nome e não aparece em "Suas contribuições".'; ?></small>
                            </section>
                            <div class="missao-ou"><span><?php echo $exibe['quadro'] ? 'ou contribua pelo app, com o seu nome no quadro' : 'ou contribua pelo app (fica em "Suas contribuições")'; ?></span></div>
                        <?php } ?>
                        <?php if ($pendente) { ?>
                            <div class="missao-pendente">
                                <span>Você tem uma contribuição de <strong><?php echo assinatura_formatar_valor($pendente['idmc_valor_centavos']); ?></strong> aguardando pagamento.</span>
                                <a href="<?php echo htmlspecialchars($pendente['idmc_invoice_url']); ?>" class="btn btn-sm btn-missao" target="_blank" rel="noopener">Pagar agora</a>
                            </div>
                        <?php } ?>
                        <form method="POST" action="missao.php?id=<?php echo (int) $projeto['idmp_id']; ?>" id="formContribuir" novalidate>
                            <?php echo csrf_campo(); ?>
                            <input type="hidden" name="acao" value="contribuir">
                            <input type="hidden" name="idmp_id" value="<?php echo (int) $projeto['idmp_id']; ?>">

                            <label class="form-label fw-semibold" for="valor">Quanto você quer contribuir?</label>
                            <div class="missao-valor">
                                <span>R$</span>
                                <input type="text" name="valor" id="valor" inputmode="decimal" autocomplete="off" placeholder="0,00" required aria-describedby="valorAjuda">
                            </div>
                            <div class="missao-sugestoes" aria-label="Sugestões de valor">
                                <?php foreach (array(5, 10, 20, 50, 100) as $sugestao) { ?>
                                    <button type="button" data-valor="<?php echo $sugestao; ?>,00">R$ <?php echo $sugestao; ?></button>
                                <?php } ?>
                            </div>
                            <small id="valorAjuda" class="text-muted d-block mb-3">Qualquer valor a partir de <?php echo assinatura_formatar_valor(MISSAO_VALOR_MINIMO_CENTAVOS); ?>.</small>

                            <span class="form-label fw-semibold d-block">Como prefere pagar?</span>
                            <div class="missao-formas mb-3" role="radiogroup">
                                <input type="radio" class="btn-check" name="forma" id="forma_pix" value="PIX" checked>
                                <label for="forma_pix"><i class="fa-brands fa-pix"></i><span>Pix</span></label>
                                <input type="radio" class="btn-check" name="forma" id="forma_cartao" value="CREDIT_CARD">
                                <label for="forma_cartao"><i class="fas fa-credit-card"></i><span>Cartão</span></label>
                            </div>

                            <?php if ($precisa_cpf) { ?>
                                <label class="form-label fw-semibold" for="documento">CPF de quem paga</label>
                                <input type="text" name="documento" id="documento" class="form-control custom-input mb-1" inputmode="numeric" maxlength="11" placeholder="Só os números" required data-so-digitos>
                                <small class="text-muted d-block mb-3">O Asaas exige o CPF. Pedimos só na primeira vez.</small>
                            <?php } ?>

                            <label class="form-label fw-semibold" for="mensagem">Deixe uma mensagem <span class="fw-normal text-muted">(opcional)</span></label>
                            <textarea name="mensagem" id="mensagem" class="form-control custom-input" rows="2" maxlength="280" placeholder="Ex.: Orando por vocês! 🙏"></textarea>
                            <small class="text-muted d-block text-end mb-2"><span id="mensagemContador">0</span>/280</small>

                            <?php if ($exibe['quadro']) { ?>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" name="anonimo" id="anonimo">
                                <label class="form-check-label" for="anonimo">Não mostrar meu nome no quadro de apoiadores</label>
                            </div>
                            <?php } ?>

                            <div id="erroContribuir" class="alert alert-danger py-2 small d-none" role="alert"></div>
                            <button type="submit" class="btn btn-missao btn-lg w-100" id="botaoContribuir"><i class="fas fa-heart me-2"></i><span>Contribuir</span></button>
                            <?php
                            // Quem recebe (Admin → Identidade da igreja): perto do
                            // dinheiro, "a equipe que mantém o app" soava vago.
                            $igreja_pag = igreja_info();
                            $recebedor_pag = $igreja_pag['recebedor'] !== ''
                                ? 'Você está contribuindo para ' . $igreja_pag['recebedor'] . ($igreja_pag['cnpj'] !== '' ? ' (CNPJ ' . $igreja_pag['cnpj'] . ')' : '') . '.'
                                : 'Valor recebido pela equipe que mantém o app.';
                            ?>
                            <p class="missao-seguranca"><i class="fas fa-lock me-1" aria-hidden="true"></i>Pagamento processado pelo Asaas, instituição de pagamento autorizada pelo Banco Central. A página dele abre numa nova aba, e o app nunca vê os dados do seu cartão. <?php echo htmlspecialchars($recebedor_pag); ?></p>
                        </form>
                    <?php } ?>
                </aside>
            </div>
        </div>
    </main>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
<script nonce="<?php echo site_csp_nonce(); ?>">
    // Pix direto: copia o código "copia e cola" (no celular, ler o QR na
    // própria tela não dá; colar no app do banco dá).
    (function () {
        var botao = document.getElementById('pixCopiar');
        var campo = document.getElementById('pixCopiaCola');
        if (!botao || !campo) { return; }
        var texto = botao.querySelector('span');
        function avisar(mensagem) {
            texto.textContent = mensagem;
            setTimeout(function () { texto.textContent = 'Copiar código'; }, 2500);
        }
        botao.addEventListener('click', function () {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(campo.value).then(function () { avisar('Copiado!'); }, function () { campo.select(); avisar('Selecione e copie'); });
                return;
            }
            campo.select();
            try { document.execCommand('copy'); avisar('Copiado!'); } catch (e) { avisar('Selecione e copie'); }
        });
        campo.addEventListener('focus', function () { campo.select(); });
    })();

    (function () {
        var form = document.getElementById('formContribuir');
        if (!form) { return; }
        var campoValor = document.getElementById('valor');
        var botao = document.getElementById('botaoContribuir');
        var textoBotao = botao.querySelector('span');
        var caixaErro = document.getElementById('erroContribuir');
        var mensagem = document.getElementById('mensagem');
        var contador = document.getElementById('mensagemContador');
        var minimo = <?php echo (int) MISSAO_VALOR_MINIMO_CENTAVOS; ?>;

        // "1.234,56" / "50" / "50.5" → centavos (0 se não for valor).
        function centavos(texto) {
            texto = String(texto).replace(/[R$\s]/g, '');
            if (texto.indexOf(',') !== -1) { texto = texto.replace(/\./g, '').replace(',', '.'); }
            if (!/^\d+(\.\d{1,2})?$/.test(texto)) { return 0; }
            return Math.round(parseFloat(texto) * 100);
        }
        function moeda(c) {
            return 'R$ ' + (c / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function atualizarBotao() {
            var c = centavos(campoValor.value);
            textoBotao.textContent = c >= minimo ? 'Contribuir com ' + moeda(c) : 'Contribuir';
            Array.prototype.forEach.call(document.querySelectorAll('.missao-sugestoes button'), function (b) {
                b.classList.toggle('is-ativo', centavos(b.getAttribute('data-valor')) === c);
            });
        }
        function mostrarErro(texto) {
            caixaErro.textContent = texto;
            caixaErro.classList.remove('d-none');
        }

        campoValor.addEventListener('input', function () {
            campoValor.value = campoValor.value.replace(/[^\d.,]/g, '');
            atualizarBotao();
        });
        Array.prototype.forEach.call(document.querySelectorAll('.missao-sugestoes button'), function (b) {
            b.addEventListener('click', function () {
                campoValor.value = b.getAttribute('data-valor');
                atualizarBotao();
                campoValor.focus();
            });
        });
        mensagem.addEventListener('input', function () { contador.textContent = mensagem.value.length; });

        // Mesmo esquema de assinar.php: a aba do pagamento abre já no
        // clique (senão o navegador bloqueia), recebe a URL depois, e esta
        // aba passa a esperar a confirmação. Sem pop-up, envia normal.
        form.addEventListener('submit', function (evento) {
            caixaErro.classList.add('d-none');
            if (centavos(campoValor.value) < minimo) {
                evento.preventDefault();
                mostrarErro('Informe um valor a partir de ' + moeda(minimo) + '.');
                campoValor.focus();
                return;
            }
            var documento = document.getElementById('documento');
            if (documento && documento.value.replace(/\D/g, '').length !== 11) {
                evento.preventDefault();
                mostrarErro('Informe o CPF com 11 números.');
                documento.focus();
                return;
            }
            if (!window.fetch || !window.FormData) { return; }
            var aba = window.open('', '_blank');
            if (!aba) { return; }
            evento.preventDefault();

            var conteudoBotao = botao.innerHTML;
            botao.disabled = true;
            botao.textContent = 'Gerando pagamento...';
            try { aba.document.title = 'Abrindo pagamento...'; aba.document.body.innerHTML = '<p style="font-family:sans-serif;padding:2rem">Abrindo a página de pagamento...</p>'; } catch (e) {}

            var dados = new FormData(form);
            dados.append('formato', 'json');
            fetch(form.getAttribute('action'), { method: 'POST', body: dados, credentials: 'same-origin' })
                .then(function (resposta) { return resposta.json(); })
                .then(function (resultado) {
                    if (!resultado.ok) { throw new Error(resultado.erro || 'Não foi possível gerar o pagamento.'); }
                    aba.opener = null;
                    aba.location.href = resultado.url;
                    window.location.href = 'missao.php?id=<?php echo (int) $projeto['idmp_id']; ?>&aguardando=' + Math.floor(Date.now() / 1000);
                })
                .catch(function (erro) {
                    aba.close();
                    mostrarErro((erro && erro.message) ? erro.message : 'Não foi possível gerar o pagamento. Tente de novo.');
                    botao.disabled = false;
                    botao.innerHTML = conteudoBotao;
                    textoBotao = botao.querySelector('span');
                });
        });
    })();

    <?php if ($aguardando && !$minha_confirmada) { ?>
    // Esperando o pagamento na outra aba: recarrega a cada 15 s com a aba
    // visível (o recarregamento consulta o Asaas) por até 10 minutos,
    // contados do clique (o "aguardando" da URL guarda a hora).
    (function () {
        var inicio = <?php echo (int) $_GET['aguardando']; ?> * 1000 || Date.now();
        setInterval(function () {
            if (document.visibilityState === 'visible' && Date.now() - inicio < 600000) { window.location.reload(); }
        }, 15000);
    })();
    <?php } ?>
</script>
</body>
</html>
