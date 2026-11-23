<?php
include("../conexao.php");

auth_require();

// Teto na página: com ?pagina= enorme a conta do OFFSET estourava o inteiro.
$pagina = isset($_GET["pagina"]) ? min(10000, max(1, (int) $_GET["pagina"])) : 1;
$itens_por_pagina = 12;
$offset = ($pagina - 1) * $itens_por_pagina;

// Buscar total de vídeos
// Só aulas publicadas de curso publicado (ou avulsas) — antes apareciam
// aulas de cursos em rascunho, como na busca antes da correção dela.
$filtro_publicado = "LOWER(ct.IDCT_TIPO) = 'video' AND ct.IDCT_PUBLICADO = 1 AND (c.IDC_ID IS NULL OR c.IDC_STATUS = 'PUBLICADO')";
$sql_total = "SELECT COUNT(*) as total FROM ID_CONTENT ct LEFT JOIN ID_CURSO c ON c.IDC_ID = ct.IDC_ID WHERE $filtro_publicado";
$res_total = mysqli_query($conn, $sql_total);
$total_videos = 0;
if ($res_total && mysqli_num_rows($res_total) > 0) {
    $row = mysqli_fetch_assoc($res_total);
    $total_videos = (int) $row["total"];
}
$total_paginas = ceil($total_videos / $itens_por_pagina);

// Buscar vídeos com paginação
$videos = array();
$sql_videos = "
    SELECT ct.IDCT_ID, ct.IDCT_TITULO, ct.IDCT_DESCRICAO, ct.IDC_ID, ct.IDCT_URL, ct.IDCT_CRIADO_EM, ct.IDCT_ORDEM
    FROM ID_CONTENT ct
    LEFT JOIN ID_CURSO c ON c.IDC_ID = ct.IDC_ID
    WHERE $filtro_publicado
    ORDER BY ct.IDCT_CRIADO_EM DESC
    LIMIT $offset, $itens_por_pagina
";
$res_videos = mysqli_query($conn, $sql_videos);
if ($res_videos && mysqli_num_rows($res_videos) > 0) {
    while ($video = mysqli_fetch_assoc($res_videos)) {
        $videos[] = $video;
    }
}

// Buscar cursos para filtro
$cursos = array();
$sql_cursos = "SELECT IDC_ID, IDC_TITULO FROM ID_CURSO WHERE IDC_STATUS = 'PUBLICADO' ORDER BY IDC_TITULO";
$res_cursos = mysqli_query($conn, $sql_cursos);
if ($res_cursos && mysqli_num_rows($res_cursos) > 0) {
    while ($curso = mysqli_fetch_assoc($res_cursos)) {
        $cursos[] = $curso;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Vídeos</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <style>
        .video-library-header h1 {
            color: var(--text-main);
            font-weight: 800;
        }

        .video-library-subtitle {
            color: #ffffff;
            margin-bottom: 0;
        }

        :root[data-theme="light"] .video-library-subtitle {
            color: #0f172a !important;
        }

        .video-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .video-card:hover {
            background: rgba(96,165,250,0.1);
            border-color: var(--primary-light);
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(96,165,250,0.2);
        }

        .video-thumbnail {
            position: relative;
            height: 180px;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-light);
            overflow: hidden;
        }

        .video-thumbnail video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            background: #0b1220;
        }

        .video-preview-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(7,16,29,0.05), rgba(7,16,29,0.28));
            z-index: 1;
            pointer-events: none;
        }

        .video-play-btn {
            position: absolute;
            font-size: 3rem;
            opacity: 0.9;
            transition: all 0.3s ease;
            z-index: 2;
        }

        .video-card:hover .video-play-btn {
            transform: scale(1.1);
            opacity: 1;
        }

        .video-play-link {
            position: absolute;
            inset: 0;
            z-index: 3;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .video-play-link:hover {
            text-decoration: none;
        }

        .video-play-link:focus-visible {
            outline: 2px solid var(--primary-light);
            outline-offset: -4px;
        }

        .video-info {
            padding: 1rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .video-title {
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.3;
        }

        .video-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: auto;
            min-height: 40px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .video-meta {
            display: flex;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 1rem;
        }

        .video-actions {
            padding: 0.8rem;
            border-top: 1px solid var(--border-color);
        }

        .pagination {
            flex-wrap: wrap;
            row-gap: 0.5rem;
        }
    </style>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'cursos'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid py-5 px-4">
        <div class="row">
            <div class="col-12">
                <div class="mb-4 video-library-header">
                    <h1 class="mb-2"><i class="fas fa-film me-2" style="color: #60a5fa;"></i>Biblioteca de Vídeos</h1>
                    <p class="video-library-subtitle">Total de <?php echo $total_videos; ?> vídeos disponíveis</p>
                </div>

                <!-- GRID DE VÍDEOS -->
                <?php if (count($videos) > 0) { ?>
                    <div class="row g-4 mb-5">
                        <?php foreach ($videos as $video) { ?>
                            <?php $preview_url = assinatura_previews_liberados($conn) ? site_resolver_url_video($video["IDCT_URL"] ?? '', '../../', 3600) : ''; ?>
                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <div class="video-card">
                                    <div class="video-thumbnail">
                                        <a href="assistir_video.php?id=<?php echo (int) $video["IDCT_ID"]; ?>" class="video-play-link" aria-label="Assistir vídeo <?php echo htmlspecialchars($video["IDCT_TITULO"]); ?>">
                                        <?php if ($preview_url !== "") { ?>
                                            <video muted playsinline preload="none" data-previa-inicio data-previa-src="<?php echo htmlspecialchars($preview_url); ?>"></video>
                                            <div class="video-preview-overlay"></div>
                                        <?php } else { ?>
                                            <div style="position:absolute; inset:0; background: radial-gradient(circle, rgba(96,165,250,0.2) 0%, transparent 70%);"></div>
                                        <?php } ?>
                                        <i class="fas fa-play video-play-btn"></i>
                                        </a>
                                    </div>

                                    <div class="video-info">
                                        <div class="video-title">
                                            <?php echo htmlspecialchars(mb_substr($video["IDCT_TITULO"], 0, 60)) . (mb_strlen($video["IDCT_TITULO"]) > 60 ? "..." : ""); ?>
                                        </div>

                                        <div class="video-desc">
                                            <?php echo htmlspecialchars(substr($video["IDCT_DESCRICAO"], 0, 80)) . (strlen($video["IDCT_DESCRICAO"]) > 80 ? "..." : ""); ?>
                                        </div>

                                        <div class="video-meta">
                                            <span><i class="fas fa-calendar-alt me-1"></i><?php echo date('d/m/Y', strtotime($video["IDCT_CRIADO_EM"])); ?></span>
                                        </div>
                                    </div>

                                    <div class="video-actions">
                                        <a href="assistir_video.php?id=<?php echo (int) $video["IDCT_ID"]; ?>" class="btn btn-sm btn-light w-100" style="background: linear-gradient(135deg, #60a5fa, #a78bfa); border: none; color: var(--text-on-primary, #ffffff);">
                                            <i class="fas fa-play me-1"></i>Assistir Agora
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- PAGINAÇÃO -->
                    <?php if ($total_paginas > 1) { ?>
                        <nav aria-label="Paginação" class="mb-5">
                            <ul class="pagination justify-content-center">
                                <?php if ($pagina > 1) { ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?>" style="background: var(--bg-light); border: 1px solid var(--border-color); color: var(--text-main);">
                                            <i class="fas fa-chevron-left me-1"></i>Anterior
                                        </a>
                                    </li>
                                <?php } ?>

                                <?php 
                                $inicio = max(1, $pagina - 2);
                                $fim = min($total_paginas, $pagina + 2);
                                
                                if ($inicio > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?pagina=1" style="background: var(--bg-light); border: 1px solid var(--border-color); color: var(--text-main);">1</a></li>';
                                    if ($inicio > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }

                                for ($i = $inicio; $i <= $fim; $i++) {
                                    $ativo = ($i == $pagina) ? ' active' : '';
                                    $bg = ($i == $pagina) ? 'background: linear-gradient(135deg, #60a5fa, #a78bfa); border-color: transparent;' : 'background: var(--bg-light); border: 1px solid var(--border-color);';
                                    echo "<li class=\"page-item$ativo\"><a class=\"page-link\" href=\"?pagina=$i\" style=\"$bg color: " . ($i == $pagina ? "white" : "var(--text-main)") . ";\">$i</a></li>";
                                }

                                if ($fim < $total_paginas) {
                                    if ($fim < $total_paginas - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    echo "<li class=\"page-item\"><a class=\"page-link\" href=\"?pagina=$total_paginas\" style=\"background: var(--bg-light); border: 1px solid var(--border-color); color: var(--text-main);\">$total_paginas</a></li>";
                                }
                                ?>

                                <?php if ($pagina < $total_paginas) { ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?>" style="background: var(--bg-light); border: 1px solid var(--border-color); color: var(--text-main);">
                                            Próxima<i class="fas fa-chevron-right ms-1"></i>
                                        </a>
                                    </li>
                                <?php } ?>
                            </ul>
                        </nav>
                    <?php } ?>
                <?php } else { ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox" style="font-size: 3rem; color: #60a5fa; opacity: 0.3;"></i>
                        <p class="mt-3 text-muted">Nenhum vídeo disponível no momento</p>
                        <!-- back button removed (now in navbar) -->
                    </div>
                <?php } ?>
            </div>
        </div>
    </main>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
