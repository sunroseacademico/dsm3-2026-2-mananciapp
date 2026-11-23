<?php
include("../conexao.php");
auth_require();

// Mesma regra/consulta da página pública (ao-vivo.php) — só muda a
// moldura (aqui é a navbar/layout da área logada, em vez do site
// institucional), o vídeo e o chat são exatamente os mesmos.
// Com cache (3 s) e a lista do chat do cache (2 s): 300 pessoas abrindo a
// página no início do culto não vão cada uma ao banco.
$transmissao = transmissao_para_quem_ve(transmissao_obter_atual_cache($conn));
$pode_assistir = transmissao_pode_assistir($transmissao);

$comentarios_iniciais = array();
if ($pode_assistir && $transmissao && in_array($transmissao['idtr_status'], array('AO_VIVO', 'ENCERRADA'), true)) {
    $comentarios_iniciais = transmissao_chat_lista($conn, $transmissao['idtr_id'])['lista'];
}

// Sempre true aqui (auth_require() já garante sessão) — mantido como
// variável só porque o widget compartilhado espera essa variável em
// escopo, igual na página pública.
$usuario_logado = true;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Ao Vivo</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/transmissao.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'ao_vivo'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 1100px; margin: 0 auto;">
        <div class="streaming-page-header">
            <h1><i class="fas fa-tower-broadcast me-2" style="color:#fb7185;"></i>Ao Vivo</h1>
            <p>Acompanhe a transmissão e participe do chat.</p>
        </div>

        <?php require __DIR__ . '/../includes/transmissao_widget.php'; ?>
    </main>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
    <?php require __DIR__ . '/../includes/transmissao_script.php'; ?>
</body>
</html>
