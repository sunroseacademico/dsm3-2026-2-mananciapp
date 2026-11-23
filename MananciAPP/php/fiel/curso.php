<?php
include("../conexao.php");
require_once __DIR__ . '/includes/cursos_helpers.php';

auth_require();

$curso_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($curso_id <= 0) {
    header('Location: cursos.php');
    exit;
}

$eh_admin = ($_SESSION['Usuario_tipo'] ?? '') === 'admin';
$curso = curso_obter_por_id($conn, $curso_id, !$eh_admin);
if (!$curso) {
    header('Location: cursos.php');
    exit;
}

$fiel_id = auth_resolve_fiel_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'favoritar' && $fiel_id && csrf_validar()) {
    curso_toggle_favorito($conn, $fiel_id, $curso_id);
    header('Location: curso.php?id=' . $curso_id);
    exit;
}

$aulas = curso_lista_aulas($conn, $curso_id);
$matricula = $fiel_id ? curso_obter_matricula($conn, $fiel_id, $curso_id) : null;
$mapa_progresso = $matricula ? curso_mapa_progresso($conn, (int) $matricula['IDMATR_ID']) : array();
$percentual_curso = $matricula ? (float) $matricula['IDMATR_PERCENTUAL'] : 0.0;
$favoritado = $fiel_id ? curso_esta_favoritado($conn, $fiel_id, $curso_id) : false;

// Curso concluído: caminho direto para o certificado (antes não havia
// nenhum, e o botão voltava para a aula 1).
$certificado_id = 0;
if ($fiel_id && $percentual_curso >= 100) {
    $res_cert = db_query($conn, "SELECT idcert_id FROM id_certificado WHERE idf_id = ? AND idc_id = ? ORDER BY idcert_id DESC LIMIT 1", array($fiel_id, $curso_id));
    $linha_cert = $res_cert ? mysqli_fetch_assoc($res_cert) : null;
    $certificado_id = (int) ($linha_cert['idcert_id'] ?? 0);
}

$proxima_aula_id = null;
foreach ($aulas as $aula) {
    $status = $mapa_progresso[(int) $aula['IDCT_ID']]['IDPR_STATUS'] ?? 'NAO_INICIADO';
    if ($status !== 'CONCLUIDO') {
        $proxima_aula_id = (int) $aula['IDCT_ID'];
        break;
    }
}
if ($proxima_aula_id === null && !empty($aulas)) {
    $proxima_aula_id = (int) $aulas[0]['IDCT_ID']; // curso 100% concluido: reassistir do inicio
}

$total_aulas = count($aulas);
$aulas_concluidas = 0;
foreach ($mapa_progresso as $p) {
    if (($p['IDPR_STATUS'] ?? '') === 'CONCLUIDO') {
        $aulas_concluidas++;
    }
}

// Página do curso continua aberta pra todos (serve de vitrine); quem
// bloqueia o vídeo em si é assistir_video.php.
$curso_gratuito = (int) ($curso['IDC_GRATUITO'] ?? 0) === 1;
$curso_liberado = assinatura_curso_liberado($conn, $curso_gratuito ? 1 : 0);

$capa = curso_resolver_capa($curso['IDC_CAPA_URL'] ?? null);
$duracao_min = curso_duracao_total_min($conn, $curso_id);
$nivel_label = curso_formatar_nivel($curso['IDC_NIVEL'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | <?php echo htmlspecialchars($curso['IDC_TITULO']); ?></title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'cursos'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 1200px; margin: 0 auto;">
        <div class="mb-2">
            <a href="cursos.php" class="text-decoration-none" style="color: var(--primary-light); font-size: 0.9rem;">
                <i class="fas fa-chevron-left me-1"></i>Cursos
            </a>
        </div>

        <div class="course-hero" <?php echo $capa ? 'style="background-image:url(\'' . htmlspecialchars($capa) . '\')"' : ''; ?>>
            <div class="course-hero-content">
                <div class="course-hero-badges">
                    <?php if (!empty($curso['categoria_nome'])) { ?><span class="badge badge-curso-azul"><?php echo htmlspecialchars($curso['categoria_nome']); ?></span><?php } ?>
                    <?php if ($nivel_label !== '') { ?><span class="badge badge-curso-verde"><?php echo htmlspecialchars($nivel_label); ?></span><?php } ?>
                    <?php if (assinatura_bloqueio_ativo() && $curso_gratuito) { ?><span class="badge badge-curso-verde"><i class="fas fa-gift me-1"></i>Gratuito</span><?php } ?>
                    <?php if (!$curso_liberado) { ?><span class="badge badge-curso-ambar"><i class="fas fa-crown me-1"></i>Assinantes</span><?php } ?>
                </div>
                <h1 class="mb-2" style="color:#fff;font-weight:800;"><?php echo htmlspecialchars($curso['IDC_TITULO']); ?></h1>
                <p class="mb-3 course-hero-desc"><?php echo nl2br(htmlspecialchars($curso['IDC_DESCRICAO'] ?? '')); ?></p>
                <p class="mb-3 course-hero-meta">
                    <?php if (!empty($curso['IDC_PROFESSOR_NOME'])) { ?><i class="fas fa-chalkboard-teacher me-1"></i><?php echo htmlspecialchars($curso['IDC_PROFESSOR_NOME']); ?> &nbsp; <?php } ?>
                    <i class="fas fa-film me-1"></i><?php echo $total_aulas; ?> <?php echo $total_aulas === 1 ? 'aula' : 'aulas'; ?> &nbsp;
                    <?php if ($duracao_min > 0) { ?><i class="fas fa-clock me-1"></i><?php echo htmlspecialchars(curso_formatar_duracao_min($duracao_min)); ?><?php } ?>
                </p>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if (!$curso_liberado) { ?>
                        <a href="assinar.php" class="btn btn-play">
                            <i class="fas fa-crown me-2"></i>Assine para assistir
                        </a>
                    <?php } elseif ($certificado_id) { ?>
                        <a href="Certificados/certificado_view.php?id=<?php echo $certificado_id; ?>" class="btn btn-play">
                            <i class="fas fa-award me-2"></i>Ver certificado
                        </a>
                    <?php } elseif ($proxima_aula_id) { ?>
                        <a href="assistir_video.php?id=<?php echo $proxima_aula_id; ?>" class="btn btn-play">
                            <i class="fas fa-play me-2"></i><?php echo $percentual_curso > 0 ? 'Continuar' : 'Assistir Agora'; ?>
                        </a>
                    <?php } ?>
                    <?php if ($fiel_id) { ?>
                        <form method="POST" class="d-inline">
<?php echo csrf_campo(); ?>
                            <input type="hidden" name="acao" value="favoritar">
                            <button type="submit" class="btn btn-info">
                                <i class="fa<?php echo $favoritado ? 's' : 'r'; ?> fa-heart me-2"></i><?php echo $favoritado ? 'Favoritado' : 'Favoritar'; ?>
                            </button>
                        </form>
                    <?php } ?>
                </div>
                <?php if ($matricula) { ?>
                    <div class="mt-3" style="max-width:360px;">
                        <div class="progress-bar-custom"><div class="progress-bar-custom-fill" style="width:<?php echo $percentual_curso; ?>%;"></div></div>
                        <small class="course-hero-progresso"><?php echo $aulas_concluidas; ?>/<?php echo $total_aulas; ?> aulas concluídas · <?php echo (int) round($percentual_curso); ?>%</small>
                    </div>
                <?php } ?>
            </div>
        </div>

        <h4 class="mb-3" style="color:var(--text-main);">Aulas do curso</h4>
        <?php if (empty($aulas)) { ?>
            <div class="streaming-empty-state">
                <i class="fas fa-video-slash"></i>
                <p class="mt-3 mb-0">Nenhuma aula publicada neste curso ainda.</p>
            </div>
        <?php } else { ?>
            <div class="mb-5">
                <?php foreach ($aulas as $index => $aula) { ?>
                    <?php
                        $status_aula = $mapa_progresso[(int) $aula['IDCT_ID']]['IDPR_STATUS'] ?? 'NAO_INICIADO';
                        $concluida = $status_aula === 'CONCLUIDO';
                        $duracao_aula = (int) ($aula['IDCT_DURACAO_SEGUNDOS'] ?? 0);
                        $percentual_aula = curso_percentual_aula_em_andamento($mapa_progresso, (int) $aula['IDCT_ID']);
                    ?>
                    <div class="lesson-row <?php echo $concluida ? 'is-done' : ''; ?> <?php echo ((int) $aula['IDCT_ID'] === $proxima_aula_id) ? 'is-current' : ''; ?>">
                        <div class="lesson-row-number">
                            <?php echo $concluida ? '<i class="fas fa-check"></i>' : ($index + 1); ?>
                        </div>
                        <div class="lesson-row-body">
                            <div class="lesson-row-title"><?php echo htmlspecialchars($aula['IDCT_TITULO']); ?></div>
                            <?php if ($duracao_aula > 0 || $percentual_aula !== null) { ?>
                                <div class="lesson-row-duration">
                                    <?php if ($duracao_aula > 0) { ?>
                                        <?php echo gmdate($duracao_aula >= 3600 ? 'G:i:s' : 'i:s', $duracao_aula); ?>
                                    <?php } ?>
                                    <?php if ($percentual_aula !== null) { ?>
                                        <?php echo $duracao_aula > 0 ? ' · ' : ''; ?><?php echo (int) round($percentual_aula); ?>% assistido
                                    <?php } ?>
                                </div>
                            <?php } ?>
                            <?php if ($percentual_aula !== null) { ?>
                                <div class="lesson-row-progress"><div style="width: <?php echo $percentual_aula; ?>%;"></div></div>
                            <?php } ?>
                        </div>
                        <?php if ($curso_liberado) { ?>
                            <a href="assistir_video.php?id=<?php echo (int) $aula['IDCT_ID']; ?>" class="btn btn-sm btn-outline-light">
                                <i class="fas fa-play me-1"></i>Assistir
                            </a>
                        <?php } else { ?>
                            <a href="assinar.php" class="btn btn-sm btn-outline-light" title="Exclusivo para assinantes">
                                <i class="fas fa-lock me-1"></i>Assinar
                            </a>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </main>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
