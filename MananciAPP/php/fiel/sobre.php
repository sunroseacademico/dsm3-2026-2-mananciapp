<?php
session_start();
include("../conexao.php");
auth_require();

if(!isset($_SESSION["Usuario_logado"])) {
    header("location:../../login.php");
    exit;
}

$painel_link = "dashboard.php";
$titulo_nav = "Missão Evangélica Manancial da Esperança";
if(isset($_SESSION["Usuario_tipo"]) && $_SESSION["Usuario_tipo"] === "admin") {
    $painel_link = "../admin/admin_conteudos.php";
    $titulo_nav = "Painel Administrativo";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Sobre</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body>
    <?php $navbar_active = 'sobre'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container py-5">
        <div class="auth-container" style="max-width: 900px; margin: 0 auto;">
            <h1 class="mb-3">Sobre a missão</h1>
            <p>
                Esta plataforma foi criada para disponibilizar vídeos, mensagens e estudos da Missão Evangélica
                Manancial da Esperança de forma simples e organizada.
            </p>
            <p>
                O objetivo é facilitar o acesso ao conteúdo da igreja para membros e visitantes, usando uma
                interface fácil de navegar.
            </p>
            <!-- back button removed (now in navbar) -->
        </div>
    </main>
</body>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</html>
