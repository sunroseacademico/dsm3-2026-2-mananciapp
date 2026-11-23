<?php
include("../conexao.php");
require_once __DIR__ . '/includes/cursos_helpers.php';
auth_require();

$fiel_id = auth_resolve_fiel_id();
$grupos = array('Hoje' => array(), 'Ontem' => array(), 'Últimos 7 dias' => array(), 'Mais antigo' => array());
$tabela_disponivel = site_tabela_existe($conn, 'ID_HISTORICO_VISUALIZACAO');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $tabela_disponivel && csrf_validar()) {
    $acao = site_entrada_texto($_POST['acao']);

    if ($acao === 'excluir_item' && $fiel_id) {
        $item_id = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
        if ($item_id > 0) {
            $sql_delete = "DELETE FROM ID_HISTORICO_VISUALIZACAO WHERE IDHV_ID = $item_id AND IDF_ID = $fiel_id LIMIT 1";
            if (mysqli_query($conn, $sql_delete)) {
                header('Location: historico.php');
                exit;
            }
        }
    } elseif ($acao === 'excluir_tudo' && $fiel_id) {
        $sql_delete_all = "DELETE FROM ID_HISTORICO_VISUALIZACAO WHERE IDF_ID = $fiel_id";
        if (mysqli_query($conn, $sql_delete_all)) {
            header('Location: historico.php');
            exit;
        }
    }
}

if ($fiel_id && $tabela_disponivel) {
    $sql = "
        SELECT h.*, c.IDC_TITULO, ct.IDCT_TITULO AS aula_titulo
        FROM ID_HISTORICO_VISUALIZACAO h
        JOIN ID_CURSO c ON c.IDC_ID = h.IDC_ID
        JOIN ID_CONTENT ct ON ct.IDCT_ID = h.IDCT_ID
        WHERE h.IDF_ID = $fiel_id
        ORDER BY h.IDHV_CRIADO_EM DESC
        LIMIT 200
    ";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        $hoje = date('Y-m-d');
        $ontem = date('Y-m-d', strtotime('-1 day'));
        $limite_7dias = date('Y-m-d', strtotime('-7 day'));

        while ($row = mysqli_fetch_assoc($res)) {
            $data_evento = date('Y-m-d', strtotime($row['IDHV_CRIADO_EM']));
            if ($data_evento === $hoje) {
                $grupos['Hoje'][] = $row;
            } elseif ($data_evento === $ontem) {
                $grupos['Ontem'][] = $row;
            } elseif ($data_evento >= $limite_7dias) {
                $grupos['Últimos 7 dias'][] = $row;
            } else {
                $grupos['Mais antigo'][] = $row;
            }
        }
    }
}

$tem_itens = false;
foreach ($grupos as $itens) {
    if (!empty($itens)) {
        $tem_itens = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Histórico</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'historico'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 1100px; margin: 0 auto;">
        <div class="streaming-page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <h1><i class="fas fa-history me-2" style="color:#60a5fa;"></i>Histórico</h1>
                <p>Suas últimas aulas assistidas</p>
            </div>
            <?php if ($tem_itens) { ?>
                <form method="POST" data-confirmar="Deseja apagar todo o histórico?">
                <?php echo csrf_campo(); ?>
                    <input type="hidden" name="acao" value="excluir_tudo">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-trash-alt me-2"></i>Apagar histórico
                    </button>
                </form>
            <?php } ?>
        </div>

        <?php if (!$tabela_disponivel) { ?>
            <div class="alert alert-info">O histórico estará disponível em breve.</div>
        <?php } elseif (!$tem_itens) { ?>
            <div class="streaming-empty-state">
                <i class="fas fa-history"></i>
                <p class="mt-3 mb-3">Você ainda não assistiu a nenhuma aula.</p>
                <a href="cursos.php" class="btn btn-play"><i class="fas fa-play me-2" aria-hidden="true"></i>Escolher um curso</a>
            </div>
        <?php } else { ?>
            <?php foreach ($grupos as $rotulo => $itens) { ?>
                <?php if (empty($itens)) { continue; } ?>
                <h5 class="mb-3 mt-4" style="color: var(--text-main);"><?php echo htmlspecialchars($rotulo); ?></h5>
                <?php foreach ($itens as $item) { ?>
                    <div class="lesson-row">
                        <div class="lesson-row-number"><i class="fas fa-play"></i></div>
                        <div class="lesson-row-body">
                            <div class="lesson-row-title"><?php echo htmlspecialchars($item['aula_titulo']); ?></div>
                            <div class="lesson-row-duration">
                                <?php echo htmlspecialchars($item['IDC_TITULO']); ?> ·
                                <?php echo date('d/m/Y', strtotime($item['IDHV_CRIADO_EM'])); ?> às
                                <?php echo date('H:i', strtotime($item['IDHV_CRIADO_EM'])); ?> ·
                                <?php echo gmdate((int) $item['IDHV_TEMPO_ASSISTIDO_SEGUNDOS'] >= 3600 ? 'G:i:s' : 'i:s', (int) $item['IDHV_TEMPO_ASSISTIDO_SEGUNDOS']); ?> assistidos
                                <?php if (!empty($item['IDHV_CONCLUIDO'])) { ?> · <span style="color: var(--accent);">Concluída</span><?php } ?>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="assistir_video.php?id=<?php echo (int) $item['IDCT_ID']; ?>" class="btn btn-sm btn-outline-light">
                                <i class="fas fa-play me-1"></i>Continuar
                            </a>
                            <form method="POST" data-confirmar="Deseja apagar este item do histórico?">
                            <?php echo csrf_campo(); ?>
                                <input type="hidden" name="acao" value="excluir_item">
                                <input type="hidden" name="item_id" value="<?php echo (int) $item['IDHV_ID']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Remover do histórico">
                                    <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
        <?php } ?>
    </main>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
