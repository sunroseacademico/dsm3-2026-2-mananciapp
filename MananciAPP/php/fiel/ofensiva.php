<?php
include("../conexao.php");
auth_require();

$fiel_id = auth_resolve_fiel_id();
$resumo = ofensiva_resumo($conn, $fiel_id);
$ranking = ofensiva_ranking($conn, 50, true);
// Protetor de ofensiva (seção 85).
$tem_protetor = ofensiva_protetor_disponivel($conn);
$protetores = $tem_protetor ? ofensiva_protetores($conn, $fiel_id) : 0;

// Medalhas (T14): concede as que o recorde já alcança antes do menu ser
// montado, para o aviso "Nova medalha!" aparecer já nesta página.
$tem_medalhas = medalhas_disponivel($conn);
$medalhas_conquistadas = array();
$proxima_medalha = null;
if ($tem_medalhas) {
    medalhas_avisar_novas(medalhas_conceder_ofensiva($conn, $fiel_id, $resumo['recorde']));
    $medalhas_conquistadas = medalhas_do_fiel($conn, $fiel_id);
    $proxima_medalha = medalhas_proxima_ofensiva($conn, $medalhas_conquistadas);
}

$minha_posicao = 0;
foreach ($ranking as $i => $linha) {
    if ((int) $linha['idf_id'] === $fiel_id) {
        $minha_posicao = $i + 1;
        break;
    }
}

$proximo_marco = null;
foreach (ofensiva_marcos() as $marco) {
    if ($marco > $resumo['atual']) {
        $proximo_marco = $marco;
        break;
    }
}
$marco_anterior = 0;
foreach (ofensiva_marcos() as $marco) {
    if ($marco <= $resumo['atual']) {
        $marco_anterior = $marco;
    }
}
$progresso_marco = $proximo_marco
    ? (int) round((($resumo['atual'] - $marco_anterior) / ($proximo_marco - $marco_anterior)) * 100)
    : 100;

$dias_semana = array('Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb');
$hoje = site_hoje();

if ($resumo['atual'] === 0) {
    $frase = 'Comece hoje sua ofensiva! Entre pelo menos uma vez por dia pra manter a chama acesa.';
} elseif ($resumo['atual'] === 1) {
    $frase = 'Bom começo! Volte amanhã pra sua ofensiva crescer.';
} elseif ($resumo['atual'] >= $resumo['recorde'] && $resumo['atual'] >= 3) {
    $frase = 'Você está no seu melhor momento — esse é o seu recorde!';
} else {
    $frase = 'Continue firme! Cada dia conta.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Ofensiva</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'ofensiva'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 1100px; margin: 0 auto;">
        <div class="streaming-page-header">
            <h1><i class="fas fa-fire me-2" style="color:#f97316;"></i>Ofensiva</h1>
            <p>Entre todo dia e mantenha sua sequência</p>
        </div>

        <section class="ofensiva-hero<?php echo $resumo['atual'] > 0 ? '' : ' is-apagada'; ?>">
            <div class="ofensiva-hero-chama">
                <i class="fas fa-fire"></i>
                <span class="ofensiva-hero-numero"><?php echo (int) $resumo['atual']; ?></span>
            </div>
            <div class="ofensiva-hero-texto">
                <h2><?php echo (int) $resumo['atual']; ?> <?php echo $resumo['atual'] === 1 ? 'dia seguido' : 'dias seguidos'; ?></h2>
                <p><?php echo htmlspecialchars($frase); ?></p>

                <div class="ofensiva-semana">
                    <?php foreach ($resumo['semana'] as $data => $acessou) { ?>
                        <?php $dt = new DateTime($data); ?>
                        <?php $protegido = $acessou === 'protegido'; ?>
                        <div class="ofensiva-dia<?php echo $acessou ? ' is-feito' : ''; ?><?php echo $protegido ? ' is-protegido' : ''; ?><?php echo $data === $hoje ? ' is-hoje' : ''; ?>"<?php echo $protegido ? ' title="Dia salvo pelo protetor"' : ''; ?>>
                            <span class="ofensiva-dia-bola"><?php echo $protegido ? '<i class="fas fa-shield-heart"></i>' : ($acessou ? '<i class="fas fa-fire"></i>' : ''); ?></span>
                            <small><?php echo $dias_semana[(int) $dt->format('w')]; ?></small>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </section>

        <div class="ofensiva-stats">
            <div class="ofensiva-stat">
                <i class="fas fa-trophy" style="color:#facc15;"></i>
                <strong><?php echo (int) $resumo['recorde']; ?></strong>
                <small>Recorde de dias seguidos</small>
            </div>
            <div class="ofensiva-stat">
                <i class="fas fa-calendar-check" style="color:#34d399;"></i>
                <strong><?php echo (int) $resumo['total']; ?></strong>
                <small>Dias acessados no total</small>
            </div>
            <div class="ofensiva-stat">
                <i class="fas fa-ranking-star" style="color:#60a5fa;"></i>
                <strong><?php echo $minha_posicao > 0 ? $minha_posicao . 'º' : '—'; ?></strong>
                <small>Sua posição no ranking</small>
            </div>
            <?php if ($tem_protetor) { ?>
                <div class="ofensiva-stat" title="A cada <?php echo OFENSIVA_PROTETOR_A_CADA; ?> dias seguidos você ganha um protetor (guarda até <?php echo OFENSIVA_PROTETORES_MAX; ?>). Se faltar um dia, ele é usado sozinho e a sequência continua.">
                    <i class="fas fa-shield-heart" style="color:#93c5fd;"></i>
                    <strong><?php echo (int) $protetores; ?>/<?php echo OFENSIVA_PROTETORES_MAX; ?></strong>
                    <small>Protetores de ofensiva</small>
                </div>
            <?php } ?>
        </div>
        <?php if ($tem_protetor) { ?>
            <p class="ofensiva-protetor-ajuda">
                <i class="fas fa-shield-heart me-1" aria-hidden="true"></i>
                <?php if ($protetores > 0) { ?>
                    Você tem <?php echo (int) $protetores; ?> protetor(es): se um dia não der para entrar, ele guarda sua ofensiva sozinho.
                <?php } else { ?>
                    A cada <?php echo OFENSIVA_PROTETOR_A_CADA; ?> dias seguidos você ganha um protetor, que guarda a ofensiva num dia em que não der para entrar (até <?php echo OFENSIVA_PROTETORES_MAX; ?>).
                <?php } ?>
            </p>
        <?php } ?>

        <?php if ($tem_medalhas) { ?>
            <?php if ($proxima_medalha) { ?>
                <div class="ofensiva-meta">
                    <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                        <span><i class="fas fa-medal me-2"></i>Próxima medalha: <strong><?php echo htmlspecialchars($proxima_medalha['nome']); ?></strong> · <?php echo $proxima_medalha['valor']; ?> dias seguidos</span>
                        <small class="text-muted">
                            <?php $faltam = max(0, $proxima_medalha['valor'] - $resumo['atual']); ?>
                            <?php echo $faltam === 1 ? 'Falta 1 dia' : 'Faltam ' . $faltam . ' dias'; ?>
                        </small>
                    </div>
                    <div class="progress-bar-custom"><div class="progress-bar-custom-fill" style="width:<?php echo min(100, (int) floor($resumo['atual'] * 100 / max(1, $proxima_medalha['valor']))); ?>%;"></div></div>
                </div>
            <?php } ?>

            <section class="mt-4" id="medalhas">
                <h2 class="stream-section-title mb-1"><i class="fas fa-medal me-2" style="color:#facc15;"></i>Medalhas</h2>
                <?php
                    // Aqui só as de ofensiva; as da comunidade (T30) ficam no perfil.
                    $medalhas_ofensiva = array_filter(medalhas_listar($conn), function ($m) { return $m['regra'] === 'ofensiva_dias'; });
                ?>
                <p class="text-muted small mb-3">Conquiste pelo seu recorde de dias seguidos. Medalha conquistada é sua para sempre. <?php echo count(array_intersect_key($medalhas_conquistadas, $medalhas_ofensiva)); ?> de <?php echo count($medalhas_ofensiva); ?> conquistadas · <a href="perfil.php#medalhas">todas as minhas medalhas</a></p>
                <?php
                    $medalhas_grade = array('catalogo' => $medalhas_ofensiva, 'conquistadas' => $medalhas_conquistadas, 'ofensiva_atual' => $resumo['atual']);
                    include __DIR__ . '/includes/medalhas_grade.php';
                ?>
            </section>
        <?php } elseif ($proximo_marco) { ?>
            <div class="ofensiva-meta">
                <div class="d-flex justify-content-between mb-2">
                    <span><i class="fas fa-flag-checkered me-2"></i>Próxima meta: <strong><?php echo $proximo_marco; ?> dias</strong></span>
                    <small class="text-muted">Faltam <?php echo $proximo_marco - $resumo['atual']; ?></small>
                </div>
                <div class="progress-bar-custom"><div class="progress-bar-custom-fill" style="width:<?php echo max(0, min(100, $progresso_marco)); ?>%;"></div></div>
            </div>
        <?php } ?>

        <section class="mt-4">
            <h2 class="stream-section-title mb-3"><i class="fas fa-ranking-star me-2" style="color:#60a5fa;"></i>Ranking de ofensiva</h2>
            <?php if (empty($ranking)) { ?>
                <div class="streaming-empty-state">
                    <i class="fas fa-fire"></i>
                    <p class="mt-3 mb-0">O ranking ainda está vazio. Seja o primeiro!</p>
                </div>
            <?php } else { ?>
                <ol class="ofensiva-ranking">
                    <?php foreach ($ranking as $i => $linha) { ?>
                        <?php $eu = (int) $linha['idf_id'] === $fiel_id; ?>
                        <li class="ofensiva-ranking-item<?php echo $eu ? ' is-eu' : ''; ?>">
                            <span class="ofensiva-ranking-pos pos-<?php echo $i + 1; ?>"><?php echo $i + 1; ?></span>
                            <span class="ofensiva-ranking-nome">
                                <?php echo htmlspecialchars(site_nome_curto($linha['IDF_NOME'])); ?><?php echo $eu ? ' <small>(você)</small>' : ''; ?>
                                <?php if (!empty($linha['eh_admin'])) { ?><span class="presenca-tag">Equipe</span><?php } ?>
                            </span>
                            <span class="ofensiva-ranking-dias<?php echo (int) $linha['atual'] > 0 ? '' : ' is-apagada'; ?>" title="Ofensiva atual">
                                <i class="fas fa-fire"></i><?php echo (int) $linha['atual']; ?>
                            </span>
                            <span class="ofensiva-ranking-extra d-none d-sm-inline" title="Recorde / total de dias">
                                <i class="fas fa-trophy"></i><?php echo (int) $linha['recorde']; ?>
                                <i class="fas fa-calendar-check ms-2"></i><?php echo (int) $linha['total']; ?>
                            </span>
                        </li>
                    <?php } ?>
                </ol>
            <?php } ?>
        </section>
    </main>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
