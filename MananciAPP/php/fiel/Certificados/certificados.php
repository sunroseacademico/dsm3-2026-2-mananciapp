<?php
include("../../conexao.php");
require_once __DIR__ . '/../includes/cursos_helpers.php';
auth_require();

$fiel_id = auth_resolve_fiel_id();
$certificados = array();

if ($fiel_id) {
    $sql = "
        SELECT cert.*, c.IDC_TITULO
        FROM ID_CERTIFICADO cert
        JOIN ID_CURSO c ON c.IDC_ID = cert.IDC_ID
        WHERE cert.IDF_ID = $fiel_id
        ORDER BY cert.IDCERT_DATA_CONCLUSAO DESC
    ";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $certificados[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Certificados</title>
    <link href="../../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_base = '../'; $navbar_active = 'certificados'; include __DIR__ . '/../includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 1100px; margin: 0 auto;">
        <div class="streaming-page-header">
            <h1><i class="fas fa-certificate me-2" style="color:#60a5fa;"></i>Certificados</h1>
            <p>Certificados emitidos pelos cursos que você concluiu</p>
        </div>

        <?php if (empty($certificados)) { ?>
            <div class="streaming-empty-state">
                <i class="fas fa-certificate"></i>
                <p class="mt-3 mb-0">Você ainda não possui certificados. Continue estudando para conquistar o seu! <a href="../meu_progresso.php">Ver meu progresso</a>.</p>
            </div>
        <?php } else { ?>
            <?php foreach ($certificados as $cert) { ?>
                <div class="lesson-row">
                    <div class="lesson-row-number"><i class="fas fa-certificate"></i></div>
                    <div class="lesson-row-body">
                        <div class="lesson-row-title"><?php echo htmlspecialchars($cert['IDC_TITULO']); ?></div>
                        <div class="lesson-row-duration">
                            Concluído em <?php echo date('d/m/Y', strtotime($cert['IDCERT_DATA_CONCLUSAO'])); ?> ·
                            Código: <?php echo htmlspecialchars($cert['IDCERT_NUMERO']); ?>
                        </div>
                    </div>
                    <?php
                        // O certificado de boas-vindas é a arte desenhada
                        // (assets/Certificados/BoasVindas.pdf), que certificado_view.php
                        // devolve em PDF já com o nome e a data do fiel — Visualizar e
                        // Baixar mostram o mesmo certificado. Os demais (cursos
                        // concluídos) ainda usam o certificado em HTML/print.
                        $eh_boas_vindas = ($cert['IDC_TITULO'] === 'Boas-vindas');
                    ?>
                    <div class="d-flex gap-2">
                        <a href="certificado_view.php?id=<?php echo (int) $cert['IDCERT_ID']; ?>" class="btn btn-sm btn-outline-light"<?php echo $eh_boas_vindas ? ' target="_blank"' : ''; ?>>
                            <i class="fas fa-eye me-1"></i>Visualizar
                        </a>
                        <?php if ($eh_boas_vindas) { ?>
                            <a href="certificado_view.php?id=<?php echo (int) $cert['IDCERT_ID']; ?>&amp;baixar=1" class="btn btn-sm btn-outline-light">
                                <i class="fas fa-download me-1"></i>Baixar PDF
                            </a>
                        <?php } else { ?>
                            <a href="certificado_view.php?id=<?php echo (int) $cert['IDCERT_ID']; ?>#print" class="btn btn-sm btn-outline-light" target="_blank">
                                <i class="fas fa-download me-1"></i>Baixar PDF
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        <?php } ?>
    </main>

    <script src="../../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
