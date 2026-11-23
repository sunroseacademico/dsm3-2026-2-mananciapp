<?php
include("../conexao.php");
require_once __DIR__ . '/includes/cursos_helpers.php';
auth_require();

$categorias = categorias_com_contagem($conn);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Categorias</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'categorias'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 1500px; margin: 0 auto;">
        <div class="streaming-page-header">
            <h1><i class="fas fa-th-large me-2" style="color:#60a5fa;"></i>Categorias</h1>
            <p>Encontre cursos organizados por tema</p>
        </div>

        <?php if (empty($categorias)) { ?>
            <div class="streaming-empty-state">
                <i class="fas fa-th-large"></i>
                <p class="mt-3 mb-0">Nenhuma categoria cadastrada ainda.</p>
            </div>
        <?php } else { ?>
            <div class="courses-grid">
                <?php foreach ($categorias as $cat) { ?>
                    <a href="cursos.php?categoria=<?php echo (int) $cat['IDCAT_ID']; ?>" class="category-tile">
                        <i class="fas <?php echo categoria_icone($cat['IDCAT_NOME']); ?>"></i>
                        <div class="fw-bold"><?php echo htmlspecialchars($cat['IDCAT_NOME']); ?></div>
                        <div class="category-tile-count"><?php echo (int) $cat['total_cursos']; ?> curso(s)</div>
                    </a>
                <?php } ?>
            </div>
        <?php } ?>
    </main>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
