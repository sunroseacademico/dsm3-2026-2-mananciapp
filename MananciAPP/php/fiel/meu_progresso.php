<?php
include("../conexao.php");
require_once __DIR__ . '/includes/cursos_helpers.php';
auth_require();

$fiel_id = auth_resolve_fiel_id();
$cursos = array();

if ($fiel_id) {
    $sql = "
        SELECT m.IDMATR_ID, m.IDMATR_PERCENTUAL, c.*, cat.IDCAT_NOME AS categoria_nome
        FROM ID_MATRICULA m
        JOIN ID_CURSO c ON c.IDC_ID = m.IDC_ID
        LEFT JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = c.IDC_CATEGORIA_ID
        WHERE m.IDF_ID = $fiel_id
        ORDER BY m.IDMATR_INICIO_EM DESC
    ";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $card = curso_montar_card($conn, $row, null, (float) $row['IDMATR_PERCENTUAL']);
            $card['aulas_concluidas'] = curso_contar_aulas_concluidas($conn, (int) $row['IDMATR_ID']);
            $cursos[] = $card;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Meu Progresso</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'progresso'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 1500px; margin: 0 auto;">
        <div class="streaming-page-header">
            <h1><i class="fas fa-chart-line me-2" style="color:#60a5fa;"></i>Meu Progresso</h1>
            <p>Acompanhe os cursos que você já iniciou</p>
        </div>

        <?php if (empty($cursos)) { ?>
            <div class="streaming-empty-state">
                <i class="fas fa-chart-line"></i>
                <p class="mt-3 mb-0">Você ainda não iniciou nenhum curso. <a href="cursos.php">Explore o catálogo</a>.</p>
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
