<?php
// Partial: card de curso estilo Netflix.
// Uso:
//   $card_curso_dados = curso_montar_card($conn, $curso_row, $fiel_id);
//   include __DIR__ . '/includes/card_curso.php';
// Espera um array com: id, titulo, capa_url, categoria, nivel, professor,
// aulas, duracao_min, progresso (float|null), em_alta (bool), link.

if (!isset($card_curso_dados) || !is_array($card_curso_dados)) {
    return;
}

$cc = $card_curso_dados;
$cc_capa = function_exists('curso_resolver_capa') ? curso_resolver_capa($cc['capa_url'] ?? null) : ($cc['capa_url'] ?? null);
$cc_duracao = function_exists('curso_formatar_duracao_min') ? curso_formatar_duracao_min($cc['duracao_min'] ?? 0) : '';
$cc_progresso = isset($cc['progresso']) && $cc['progresso'] !== null ? max(0, min(100, (float) $cc['progresso'])) : null;
$cc_link = htmlspecialchars($cc['link'] ?? '#');
?>
<a href="<?php echo $cc_link; ?>" class="course-card">
    <div class="course-card-thumb" <?php echo $cc_capa ? 'style="background-image:url(\'' . htmlspecialchars($cc_capa) . '\')"' : ''; ?>>
        <?php if (!$cc_capa) { ?>
            <i class="fas fa-book-open course-card-thumb-icon"></i>
        <?php } ?>
        <?php if (!empty($cc['categoria'])) { ?>
            <span class="course-card-category-badge"><?php echo htmlspecialchars($cc['categoria']); ?></span>
        <?php } ?>
        <?php if (!empty($cc['em_alta'])) { ?>
            <span class="course-card-em-alta" title="Muito assistido nos últimos 7 dias"><i class="fas fa-fire"></i>Em alta</span>
        <?php } ?>
        <div class="course-card-overlay">
            <span class="course-card-watch-btn">
                <i class="fas fa-play"></i> <?php echo $cc_progresso !== null && $cc_progresso > 0 ? 'Continuar' : 'Assistir'; ?>
            </span>
        </div>
        <?php if ($cc_progresso !== null) { ?>
            <div class="course-card-progress-thumb">
                <div class="course-card-progress-thumb-fill" style="width: <?php echo $cc_progresso; ?>%;"></div>
            </div>
        <?php } ?>
    </div>
    <div class="course-card-body">
        <div class="course-card-title"><?php echo htmlspecialchars($cc['titulo'] ?? 'Curso'); ?></div>
        <div class="course-card-meta">
            <span><i class="fas fa-film me-1"></i><?php echo (int) ($cc['aulas'] ?? 0); ?> <?php echo (int) ($cc['aulas'] ?? 0) === 1 ? 'aula' : 'aulas'; ?></span>
            <?php if ($cc_duracao !== '') { ?>
                <span><i class="fas fa-clock me-1"></i><?php echo htmlspecialchars($cc_duracao); ?></span>
            <?php } ?>
            <?php if (!empty($cc['nivel']) && function_exists('curso_formatar_nivel') && curso_formatar_nivel($cc['nivel']) !== '') { ?>
                <span><i class="fas fa-signal me-1"></i><?php echo htmlspecialchars(curso_formatar_nivel($cc['nivel'])); ?></span>
            <?php } ?>
        </div>
        <?php if ($cc_progresso !== null) { ?>
            <div class="course-card-progress-row">
                <div class="progress-bar-custom">
                    <div class="progress-bar-custom-fill" style="width: <?php echo $cc_progresso; ?>%;"></div>
                </div>
                <span class="course-card-progress-percent"><?php echo (int) round($cc_progresso); ?>%</span>
            </div>
            <?php if (isset($cc['aulas_concluidas'])) { ?>
                <div class="course-card-meta mt-1"><?php echo (int) $cc['aulas_concluidas']; ?>/<?php echo (int) ($cc['aulas'] ?? 0); ?> aulas concluídas</div>
            <?php } ?>
        <?php } ?>
    </div>
</a>
