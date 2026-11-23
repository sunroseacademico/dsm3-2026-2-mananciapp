<?php
// Aba Contribuir: só a oferta geral (a oferta livre para manter o app, da
// equipe interna; Admin → Contribuições). Abre direto a página dela
// (missao.php), com o Pix com o valor que quiser e o formulário. As missões
// têm a aba delas (missoes.php). Sem oferta geral recebendo, mostra um
// aviso.
include("../conexao.php");
auth_require();

$geral_id = missoes_oferta_geral_id();
$geral = missoes_disponivel($conn) && $geral_id > 0 ? missao_projeto($conn, $geral_id) : null;
if ($geral && $geral['situacao'] === 'aberto') {
    header('Location: missao.php?id=' . $geral_id);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Contribuir</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'contribuir'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 1100px; margin: 0 auto;">
        <div class="streaming-page-header">
            <h1><i class="fas fa-hand-holding-heart me-2" style="color:#f472b6;"></i>Contribuir</h1>
            <p>Toda contribuição, sendo de coração, é bem-vinda.</p>
        </div>
        <div class="streaming-empty-state">
            <i class="fas fa-hand-holding-heart"></i>
            <p class="mt-3 mb-2">As contribuições não estão abertas no momento.</p>
            <a href="missoes.php" class="btn btn-missao btn-sm">Ver as missões</a>
        </div>
    </main>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
