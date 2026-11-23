<?php
// Partial: card de vídeo/aula individual (usado em "Recomendados para Você"),
// indicando de qual curso a aula faz parte.
// Uso:
//   $card_video_dados = video_montar_card($row);
//   include __DIR__ . '/includes/card_video.php';
// Espera um array com: id, titulo, curso_id, curso_titulo, categoria,
// capa_url, duracao_segundos, em_alta (bool), link.

if (!isset($card_video_dados) || !is_array($card_video_dados)) {
    return;
}

$cv = $card_video_dados;
$cv_capa = function_exists('curso_resolver_capa') ? curso_resolver_capa($cv['capa_url'] ?? null) : ($cv['capa_url'] ?? null);
// Com capa, a capa basta: a prévia em vídeo baixava um pedaço de cada vídeo
// do R2 ao abrir a página (pesado no 4G). Sem capa, a prévia só carrega
// quando o card aparece na tela (data-previa-src, ver js/acoes.js).
$cv_preview = (!$cv_capa && function_exists('video_resolver_preview')) ? video_resolver_preview($cv['video_url'] ?? null) : '';
$cv_segundos = (int) ($cv['duracao_segundos'] ?? 0);
$cv_duracao = $cv_segundos > 0 ? gmdate($cv_segundos >= 3600 ? 'H:i:s' : 'i:s', $cv_segundos) : '';
$cv_link = htmlspecialchars($cv['link'] ?? '#');
$cv_progresso = isset($cv['progresso']) && $cv['progresso'] !== null ? max(0, min(100, (float) $cv['progresso'])) : null;
?>
<a href="<?php echo $cv_link; ?>" class="course-card">
    <div class="course-card-thumb" <?php echo (!$cv_preview && $cv_capa) ? 'style="background-image:url(\'' . htmlspecialchars($cv_capa) . '\')"' : ''; ?>>
        <?php if ($cv_preview !== '') { ?>
            <video muted playsinline preload="none" data-previa-inicio data-previa-src="<?php echo htmlspecialchars($cv_preview); ?>"></video>
        <?php } elseif (!$cv_capa) { ?>
            <i class="fas fa-play-circle course-card-thumb-icon"></i>
        <?php } ?>
        <?php if (!empty($cv['categoria'])) { ?>
            <span class="course-card-category-badge"><?php echo htmlspecialchars($cv['categoria']); ?></span>
        <?php } ?>
        <?php if (!empty($cv['em_alta'])) { ?>
            <span class="course-card-em-alta" title="Muito assistida nos últimos 7 dias"><i class="fas fa-fire"></i>Em alta</span>
        <?php } ?>
        <div class="course-card-overlay">
            <span class="course-card-watch-btn"><i class="fas fa-play"></i> <?php echo $cv_progresso !== null && $cv_progresso < 100 ? 'Continuar' : 'Assistir'; ?></span>
        </div>
        <?php if ($cv_progresso !== null) { ?>
            <div class="course-card-progress-thumb" title="<?php echo (int) round($cv_progresso); ?>% assistido">
                <div class="course-card-progress-thumb-fill" style="width: <?php echo $cv_progresso; ?>%;"></div>
            </div>
        <?php } ?>
    </div>
    <div class="course-card-body">
        <div class="course-card-title"><?php echo htmlspecialchars($cv['titulo'] ?? 'Vídeo'); ?></div>
        <div class="course-card-meta">
            <?php if (!empty($cv['curso_titulo'])) { ?>
                <span><i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($cv['curso_titulo']); ?></span>
            <?php } ?>
            <?php if ($cv_duracao !== '') { ?>
                <span><i class="fas fa-clock me-1"></i><?php echo htmlspecialchars($cv_duracao); ?></span>
            <?php } ?>
        </div>
    </div>
</a>
