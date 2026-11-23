<?php
session_start();
include("../conexao.php");
auth_require();

$busca = "";
$resultado = false;
$cursos_achados = array();
$busca_curta = false;
// GET (busca.php?q=...): voltar não pede mais "Confirmar reenvio do
// formulário" e o link da busca pode ser compartilhado. ?busca= (antigo) vale.
$buscou = isset($_GET['q']) || isset($_GET['busca']);

// Até 50 aulas e 10 cursos; termo com pelo menos 2 letras.
const BUSCA_MAX_RESULTADOS = 50;

if ($buscou) {
    $busca = mb_substr(trim(site_entrada_texto($_GET['q'] ?? ($_GET['busca'] ?? ''), false)), 0, 100);
    if (mb_strlen($busca) < 2) {
        $busca_curta = true;
    } else {
        // Sem diferenciar maiúsculas nem acentos ("fe" acha "Fé", "oracao"
        // acha "Oração"): no Postgres o LIKE diferencia os dois — no MySQL de
        // antes não. O termo vai sem acento e minúsculo; a coluna passa pelo
        // mesmo tratamento no SQL (translate). % e _ digitados são escapados:
        // antes, buscar "%" listava todo o conteúdo. Consulta preparada:
        // o termo não entra no texto do SQL.
        $termo = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), video_texto_sem_acento($busca)) . '%';
        $sem_acento = function ($coluna) {
            return "translate(lower(COALESCE($coluna, '')), 'áàâãäéèêëíìîïóòôõöúùûüç', 'aaaaaeeeeiiiiooooouuuuc')";
        };
        // Cursos publicados pelo nome ou descrição (antes a busca só achava aulas).
        $res_cursos = db_query($conn, "
            SELECT c.IDC_ID, c.IDC_TITULO, c.IDC_DESCRICAO FROM ID_CURSO c
            WHERE c.IDC_STATUS = 'PUBLICADO'
              AND (" . $sem_acento('c.IDC_TITULO') . " LIKE ? OR " . $sem_acento('c.IDC_DESCRICAO') . " LIKE ?)
            ORDER BY c.IDC_CRIADO_EM DESC
            LIMIT 10
        ", array($termo, $termo));
        while ($res_cursos && ($linha_curso = mysqli_fetch_assoc($res_cursos))) {
            $cursos_achados[] = $linha_curso;
        }
        // So retorna conteudo ja publicado, de curso ja publicado (ou sem curso
        // vinculado) — sem isso, titulo/descricao de rascunho do admin aparecia
        // na busca pra qualquer fiel, mesmo o video em si sendo bloqueado depois
        // em assistir_video.php. O nome do curso vem junto (e também acha a
        // aula pelo curso).
        $sql = "
            SELECT ct.IDCT_ID, ct.IDCT_TITULO, ct.IDCT_DESCRICAO, ct.IDCT_TIPO, c.IDC_TITULO AS curso_titulo FROM ID_CONTENT ct
            LEFT JOIN ID_CURSO c ON c.IDC_ID = ct.IDC_ID
            WHERE (" . $sem_acento('ct.IDCT_TITULO') . " LIKE ? OR " . $sem_acento('ct.IDCT_DESCRICAO') . " LIKE ?)
              AND ct.IDCT_PUBLICADO = 1 AND (c.IDC_ID IS NULL OR c.IDC_STATUS = 'PUBLICADO')
            ORDER BY ct.IDCT_ID DESC
            LIMIT " . BUSCA_MAX_RESULTADOS . "
        ";
        $resultado = db_query($conn, $sql, array($termo, $termo));
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Buscar Conteúdo</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <style>
        .search-result-item {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-main);
        }

        .btn-ver-video {
            background: #0b2f6b;
            border: 1px solid #1e4d9b;
            color: #fff;
            font-weight: 600;
        }

        .btn-ver-video:hover,
        .btn-ver-video:focus {
            background: #123f8f;
            border-color: #2f63b8;
            color: #fff;
        }
    </style>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body>
    <?php $navbar_active = 'busca'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container py-5">
        <div class="auth-container" style="max-width: 900px; margin: 0 auto;">
            <h1 class="mb-4">Buscar conteúdo</h1>
            
            <form method="GET" action="busca.php" class="mb-4" role="search">
                <label for="busca" class="visually-hidden">Buscar conteúdo</label>
                <div class="input-group">
                    <input type="search" name="q" id="busca" minlength="2" maxlength="100" class="form-control custom-input" placeholder="Nome do curso, da aula ou um assunto" enterkeyhint="search" value="<?php echo htmlspecialchars($busca); ?>">
                    <button class="btn btn-light" type="submit">Buscar</button>
                </div>
            </form>

            <?php 
            if ($buscou && $busca_curta) {
            ?>
                <div class="alert alert-warning">Digite pelo menos 2 letras para buscar.</div>
            <?php
            } elseif ($buscou) {
                if ($cursos_achados) { ?>
                <h5>Cursos</h5>
                <div class="list-group mt-2 mb-4">
                    <?php foreach ($cursos_achados as $curso_achado) { ?>
                        <a href="curso.php?id=<?php echo (int) $curso_achado['IDC_ID']; ?>" class="list-group-item list-group-item-action search-result-item">
                            <h6 class="mb-1"><i class="fas fa-graduation-cap me-2" aria-hidden="true"></i><?php echo htmlspecialchars((string) $curso_achado['IDC_TITULO']); ?></h6>
                            <p class="mb-0 small"><?php echo htmlspecialchars(mb_strimwidth((string) ($curso_achado['IDC_DESCRICAO'] ?? ''), 0, 160, '…')); ?></p>
                        </a>
                    <?php } ?>
                </div>
            <?php }
                if($resultado && mysqli_num_rows($resultado) > 0) {
            ?>
                <div>
                    <h5>Aulas</h5>
                    <div class="list-group mt-3">
                        <?php 
                        while($conteudo = mysqli_fetch_assoc($resultado)) {
                            $tipo = strtoupper((string) ($conteudo['IDCT_TIPO'] ?? ''));
                            $eh_video = (strtolower((string) ($conteudo['IDCT_TIPO'] ?? '')) === 'video');
                            $link_video = "assistir_video.php?id=" . (int) ($conteudo['IDCT_ID'] ?? 0);
                        ?>
                            <div class="list-group-item list-group-item-action search-result-item">
                                <h6 class="mb-1"><?php echo htmlspecialchars((string) ($conteudo['IDCT_TITULO'] ?? 'Sem título')); ?></h6>
                                <p class="mb-2"><?php echo htmlspecialchars((string) ($conteudo['IDCT_DESCRICAO'] ?? '')); ?></p>
                                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                    <small><?php echo !empty($conteudo['curso_titulo']) ? '<i class="fas fa-graduation-cap me-1" aria-hidden="true"></i>' . htmlspecialchars((string) $conteudo['curso_titulo']) : 'Vídeo avulso'; ?></small>
                                    <?php if ($eh_video) { ?>
                                        <a href="<?php echo htmlspecialchars($link_video); ?>" class="btn btn-sm btn-ver-video">
                                            <i class="fas fa-play me-1"></i>Ver Vídeo
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php 
                        }
                        ?>
                    </div>
                </div>
            <?php
                } elseif (!$cursos_achados) {
            ?>
                <div class="streaming-empty-state">
                    <i class="fas fa-magnifying-glass"></i>
                    <p class="mt-3 mb-3">Nada encontrado para "<?php echo htmlspecialchars($busca); ?>". Tente uma palavra mais curta ou o nome do pregador.</p>
                    <a href="cursos.php" class="btn btn-play"><i class="fas fa-book-open me-2" aria-hidden="true"></i>Ver todos os cursos</a>
                </div>
            <?php 
                }
            }
            ?>

                    <!-- back button removed (now in navbar) -->
        </div>
    </main>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
