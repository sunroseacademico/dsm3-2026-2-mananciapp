<?php
include("../conexao.php");
require_once __DIR__ . '/includes/cursos_helpers.php';
auth_require();

$fiel_id = auth_resolve_fiel_id();

$categoria_filtro = isset($_GET['categoria']) ? (int) $_GET['categoria'] : 0;
$nivel_filtro = is_string($_GET['nivel'] ?? null) ? trim($_GET['nivel']) : ''; // ?nivel[]=x virava erro 500

$where = array("c.IDC_STATUS = 'PUBLICADO'");
$params = array(); // valores dos "?" de $where, na mesma ordem
if ($categoria_filtro > 0) {
    $where[] = "c.IDC_CATEGORIA_ID = ?";
    $params[] = $categoria_filtro;
}
if ($nivel_filtro !== '' && tabela_tem_coluna($conn, 'ID_CURSO', 'IDC_NIVEL')) {
    $where[] = "c.IDC_NIVEL = ?";
    $params[] = $nivel_filtro;
}

$sql = "
    SELECT c.*, cat.IDCAT_NOME AS categoria_nome
    FROM ID_CURSO c
    LEFT JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = c.IDC_CATEGORIA_ID
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.IDC_CRIADO_EM DESC
";
$res = db_query($conn, $sql, $params);
$cursos = array();
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $cursos[] = curso_montar_card($conn, $row, $fiel_id);
    }
}

$categorias = categorias_com_contagem($conn);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Cursos</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'cursos'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 1500px; margin: 0 auto;">
        <div class="streaming-page-header">
            <h1><i class="fas fa-play-circle me-2" style="color:#60a5fa;"></i>Cursos</h1>
            <p>Explore todo o catálogo de cursos disponíveis</p>
        </div>

        <form method="GET" class="filter-bar">
            <select name="categoria" data-enviar-ao-mudar aria-label="Categoria">
                <option value="0">Todas as categorias</option>
                <?php foreach ($categorias as $cat) { ?>
                    <option value="<?php echo (int) $cat['IDCAT_ID']; ?>" <?php echo ($categoria_filtro === (int) $cat['IDCAT_ID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['IDCAT_NOME']); ?></option>
                <?php } ?>
            </select>
            <select name="nivel" data-enviar-ao-mudar aria-label="Nível">
                <option value="">Todos os níveis</option>
                <?php foreach (array('INICIANTE' => 'Iniciante', 'INTERMEDIARIO' => 'Intermediário', 'AVANCADO' => 'Avançado') as $valor => $rotulo) { ?>
                    <option value="<?php echo $valor; ?>" <?php echo ($nivel_filtro === $valor) ? 'selected' : ''; ?>><?php echo $rotulo; ?></option>
                <?php } ?>
            </select>
        </form>

        <?php if (empty($cursos)) { ?>
            <div class="streaming-empty-state">
                <i class="fas fa-inbox"></i>
                <p class="mt-3 mb-0">Nenhum curso encontrado com esses filtros.</p>
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
