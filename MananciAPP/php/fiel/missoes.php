<?php
// Aba Missões (T32): os projetos de missão abertos, as contribuições do
// próprio fiel para eles e os projetos encerrados. A contribuição é feita
// na página do projeto (missao.php). A oferta geral (a oferta livre para
// manter o app, da equipe interna) tem a sua própria aba, contribuir.php.
// Ver "CONTRIBUIÇÃO EM MISSÕES" em php/conexao.php e a seção 72 do
// bancosupabase.md.
include("../conexao.php");
auth_require();

$fiel_id = auth_resolve_fiel_id();
$disponivel = missoes_disponivel($conn);

// Atualiza as contribuições que ainda aguardam pagamento (caso o webhook do
// Asaas tenha atrasado), no máximo uma vez por minuto por sessão: cada uma
// é uma chamada à API do Asaas.
if ($disponivel && time() - (int) ($_SESSION['missoes_sincronizado_em'] ?? 0) >= 60) {
    $_SESSION['missoes_sincronizado_em'] = time();
    missao_sincronizar_pendentes($conn, $fiel_id);
}
$projetos = $disponivel ? missoes_projetos($conn) : array();
// A oferta geral fica na aba Contribuir.
$geral_id = missoes_oferta_geral_id();
$missoes = array_values(array_filter($projetos, function ($p) use ($geral_id) {
    return (int) $p['idmp_id'] !== $geral_id;
}));
$abertos = array_values(array_filter($missoes, function ($p) { return $p['situacao'] === 'aberto'; }));
$encerrados = array_values(array_filter($missoes, function ($p) { return $p['situacao'] === 'encerrado'; }));
$minhas = array_values(array_filter($disponivel ? missao_contribuicoes_do_fiel($conn, $fiel_id, 30) : array(), function ($c) use ($geral_id) {
    return (int) $c['idmp_id'] !== $geral_id;
}));
$hoje = site_hoje();

// Resumo: soma só o que cada projeto mostra ao fiel (Admin → Missões,
// seção 78). Se nenhum mostra o total, o valor some do resumo.
$total_arrecadado = 0;
$total_apoiadores = 0;
$algum_total = false;
$algum_apoiadores = false;
foreach ($missoes as $p) {
    $e = missao_exibicao($p);
    if ($e['total']) {
        $total_arrecadado += $p['arrecadado'];
        $algum_total = true;
    }
    if ($e['apoiadores']) {
        $total_apoiadores += (int) $p['apoiadores'];
        $algum_apoiadores = true;
    }
}

// "Faltam 12 dias", "Último dia", "" (sem data de fim).
function missoes_prazo_texto($data_fim, $hoje) {
    if (empty($data_fim)) {
        return '';
    }
    $dias = (int) round((strtotime($data_fim) - strtotime($hoje)) / 86400);
    if ($dias <= 0) {
        return 'Último dia';
    }
    return $dias === 1 ? 'Falta 1 dia' : 'Faltam ' . $dias . ' dias';
}

function missoes_resumo_texto($descricao, $limite = 150) {
    $texto = trim(preg_replace('/\s+/', ' ', (string) $descricao));
    return mb_strimwidth($texto, 0, $limite, '…');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Missões</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'missoes'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 1100px; margin: 0 auto;">
        <div class="streaming-page-header">
            <h1><i class="fas fa-earth-americas me-2" style="color:#f472b6;"></i>Missões</h1>
            <p>Leve o amor de Cristo mais longe. Escolha um projeto e contribua com o valor que o seu coração mandar.</p>
        </div>

        <?php if (!$disponivel) { ?>
            <div class="streaming-empty-state">
                <i class="fas fa-earth-americas"></i>
                <p class="mt-3 mb-0">As missões ainda não estão disponíveis.</p>
            </div>
        <?php } else { ?>

        <?php if ($missoes && ($algum_total || $algum_apoiadores)) { ?>
            <div class="missoes-resumo mb-4">
                <?php if ($algum_total) { ?><div><strong><?php echo assinatura_formatar_valor($total_arrecadado); ?></strong><small>arrecadados juntos</small></div><?php } ?>
                <div><strong><?php echo count($abertos); ?></strong><small><?php echo count($abertos) === 1 ? 'projeto recebendo' : 'projetos recebendo'; ?></small></div>
                <?php if ($algum_apoiadores) { ?><div><strong><?php echo $total_apoiadores; ?></strong><small><?php echo $total_apoiadores === 1 ? 'apoio' : 'apoios'; ?></small></div><?php } ?>
            </div>
        <?php } ?>

        <section class="mb-5">
            <h2 class="stream-section-title mb-3"><i class="fas fa-seedling me-2"></i>Projetos abertos</h2>
            <?php if (!$abertos) { ?>
                <p class="text-muted">Nenhum projeto de missão recebendo contribuições agora. Fique de olho: avisamos quando surgir um novo.</p>
            <?php } ?>
            <div class="missoes-grade">
                <?php foreach ($abertos as $p) {
                    $capa = missao_capa_url($p['idmp_capa_url'] ?? '');
                    $prazo = missoes_prazo_texto($p['idmp_data_fim'] ?? '', $hoje);
                    $e = missao_exibicao($p);
                    $com_meta = $e['total'] && $p['percentual'] !== null;
                ?>
                    <a class="missao-card" href="missao.php?id=<?php echo (int) $p['idmp_id']; ?>">
                        <div class="missao-card-capa"<?php echo $capa ? ' style="background-image: url(\'' . htmlspecialchars($capa, ENT_QUOTES) . '\');"' : ''; ?>>
                            <?php if (!$capa) { ?><i class="fas fa-earth-americas" aria-hidden="true"></i><?php } ?>
                            <?php if (!empty($p['idmp_local'])) { ?><span class="missao-chip-local"><i class="fas fa-location-dot me-1"></i><?php echo htmlspecialchars($p['idmp_local']); ?></span><?php } ?>
                            <?php if ($com_meta && $p['percentual'] >= 100) { ?><span class="missao-chip-meta">Meta alcançada 🎉</span><?php } ?>
                        </div>
                        <div class="missao-card-corpo">
                            <h3 class="missao-titulo"><?php echo htmlspecialchars($p['idmp_titulo']); ?></h3>
                            <?php if (trim((string) $p['idmp_descricao']) !== '') { ?>
                                <p class="missao-resumo-texto"><?php echo htmlspecialchars(missoes_resumo_texto($p['idmp_descricao'])); ?></p>
                            <?php } ?>
                            <?php if ($com_meta) { ?>
                                <div class="missao-barra" role="progressbar" aria-valuenow="<?php echo (int) $p['percentual']; ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progresso da meta"><span style="width: <?php echo max(2, (int) $p['percentual']); ?>%;"></span></div>
                            <?php } ?>
                            <?php if ($e['total']) { ?>
                            <div class="missao-numeros">
                                <span><strong><?php echo assinatura_formatar_valor($p['arrecadado']); ?></strong><?php echo $com_meta ? ' de ' . assinatura_formatar_valor($p['idmp_meta_centavos']) : ' arrecadados'; ?></span>
                                <?php if ($com_meta) { ?><span class="missao-percentual"><?php echo (int) $p['percentual']; ?>%</span><?php } ?>
                            </div>
                            <?php } ?>
                            <div class="missao-rodape">
                                <span><i class="fas fa-heart me-1"></i><?php echo !$e['apoiadores'] ? 'Apoie esta missão' : ((int) $p['apoiadores'] === 0 ? 'Seja o primeiro a apoiar' : ((int) $p['apoiadores'] === 1 ? '1 apoiador' : (int) $p['apoiadores'] . ' apoiadores')); ?></span>
                                <?php if ($prazo !== '') { ?><span><i class="far fa-clock me-1"></i><?php echo $prazo; ?></span><?php } ?>
                            </div>
                            <span class="btn btn-sm btn-missao mt-2"><i class="fas fa-hand-holding-heart me-1"></i>Quero apoiar</span>
                        </div>
                    </a>
                <?php } ?>
            </div>
        </section>

        <?php if ($minhas) { ?>
            <section class="mb-5" id="minhas">
                <h2 class="stream-section-title mb-3"><i class="fas fa-receipt me-2"></i>Minhas contribuições</h2>
                <ul class="missao-minhas">
                    <?php foreach ($minhas as $c) {
                        $info = missao_status_info($c['idmc_status']);
                        $pendente = in_array(strtoupper((string) $c['idmc_status']), array('PENDING', 'AWAITING_RISK_ANALYSIS'), true);
                    ?>
                        <li>
                            <div class="min-w-0">
                                <a href="missao.php?id=<?php echo (int) $c['idmp_id']; ?>" class="fw-semibold"><?php echo htmlspecialchars($c['idmp_titulo']); ?></a>
                                <small class="d-block text-muted">
                                    <?php echo assinatura_formatar_valor($c['idmc_valor_centavos']); ?>
                                    · <?php echo $c['idmc_forma'] === 'CREDIT_CARD' ? 'cartão' : ($c['idmc_forma'] === 'PIX' ? 'Pix' : htmlspecialchars(strtolower((string) $c['idmc_forma']))); ?>
                                    · <?php echo date('d/m/Y', strtotime($c['idmc_pago_em'] ?: $c['idmc_criado_em'])); ?>
                                    <?php if ((int) $c['idmc_anonimo'] === 1) { ?>· anônima<?php } ?>
                                </small>
                            </div>
                            <span class="testemunho-status <?php echo $info[1]; ?>"><?php echo $info[0]; ?></span>
                            <?php if ($pendente && !empty($c['idmc_invoice_url'])) { ?>
                                <a href="<?php echo htmlspecialchars($c['idmc_invoice_url']); ?>" class="btn btn-sm btn-missao" target="_blank" rel="noopener">Pagar agora</a>
                            <?php } elseif (in_array(strtoupper((string) $c['idmc_status']), financeiro_status_pagos(), true) && !empty($c['idmc_invoice_url'])) { ?>
                                <a href="<?php echo htmlspecialchars($c['idmc_invoice_url']); ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Comprovante</a>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

        <?php if ($encerrados) { ?>
            <section class="mb-4">
                <h2 class="stream-section-title mb-3"><i class="fas fa-flag-checkered me-2"></i>Projetos encerrados</h2>
                <div class="missoes-grade is-compacta">
                    <?php foreach ($encerrados as $p) {
                        $capa = missao_capa_url($p['idmp_capa_url'] ?? '');
                        $e = missao_exibicao($p);
                    ?>
                        <a class="missao-card is-encerrado" href="missao.php?id=<?php echo (int) $p['idmp_id']; ?>">
                            <div class="missao-card-capa"<?php echo $capa ? ' style="background-image: url(\'' . htmlspecialchars($capa, ENT_QUOTES) . '\');"' : ''; ?>>
                                <?php if (!$capa) { ?><i class="fas fa-earth-americas" aria-hidden="true"></i><?php } ?>
                                <span class="missao-chip-meta is-encerrado">Encerrado</span>
                            </div>
                            <div class="missao-card-corpo">
                                <h3 class="missao-titulo"><?php echo htmlspecialchars($p['idmp_titulo']); ?></h3>
                                <div class="missao-rodape">
                                    <?php if ($e['total']) { ?><span><strong><?php echo assinatura_formatar_valor($p['arrecadado']); ?></strong> arrecadados</span><?php } ?>
                                    <?php if ($e['apoiadores']) { ?><span><i class="fas fa-heart me-1"></i><?php echo (int) $p['apoiadores']; ?></span><?php } ?>
                                </div>
                            </div>
                        </a>
                    <?php } ?>
                </div>
            </section>
        <?php } ?>

        <?php } ?>
    </main>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
