<?php
include("../conexao.php");
require_once __DIR__ . '/includes/cursos_helpers.php';
auth_require();

$fiel_id = auth_resolve_fiel_id();
$cursos = array();

if ($fiel_id && site_tabela_existe($conn, 'ID_FAVORITO')) {
    $sql = "
        SELECT c.*, cat.IDCAT_NOME AS categoria_nome
        FROM ID_FAVORITO f
        JOIN ID_CURSO c ON c.IDC_ID = f.IDC_ID
        LEFT JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = c.IDC_CATEGORIA_ID
        WHERE f.IDF_ID = $fiel_id
        ORDER BY f.IDFAV_CRIADO_EM DESC
    ";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $cursos[] = curso_montar_card($conn, $row, $fiel_id);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Favoritos</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'favoritos'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 1500px; margin: 0 auto;">
        <div class="streaming-page-header">
            <h1><i class="fas fa-heart me-2" style="color:#60a5fa;"></i>Favoritos</h1>
            <p>Cursos que você marcou para assistir depois</p>
        </div>

        <?php if (!site_tabela_existe($conn, 'ID_FAVORITO')) { ?>
            <div class="alert alert-info">Os favoritos estarão disponíveis em breve.</div>
        <?php } elseif (empty($cursos)) { ?>
            <div class="streaming-empty-state">
                <i class="fas fa-heart"></i>
                <p class="mt-3 mb-3">Você ainda não favoritou nenhum curso. Toque no coração de um curso para guardar aqui.</p>
                <a href="cursos.php" class="btn btn-play"><i class="fas fa-book-open me-2" aria-hidden="true"></i>Ver os cursos</a>
            </div>
        <?php } else { ?>
            <div class="courses-grid">
                <?php foreach ($cursos as $card_curso_dados) { ?>
                    <?php include __DIR__ . '/includes/card_curso.php'; ?>
                <?php } ?>
            </div>
        <?php } ?>
    </main>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
