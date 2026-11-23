<?php
session_start();
include("../conexao.php");
require_once __DIR__ . '/../fiel/includes/cursos_helpers.php';
require_once __DIR__ . '/admin_layout.php';

// auth_require confere também inatividade, revogação (admin removido ou
// "desconectado" cai na hora) e o Termo — a checagem manual que havia aqui
// só olhava a sessão.
auth_require(array('admin'));

$mensagem = "";
$erro = "";
$curso_com_historico = null;

$tem_professor = tabela_tem_coluna($conn, 'ID_CURSO', 'IDC_PROFESSOR_NOME');
$tem_nivel = tabela_tem_coluna($conn, 'ID_CURSO', 'IDC_NIVEL');
$tem_destaque = tabela_tem_coluna($conn, 'ID_CURSO', 'IDC_DESTAQUE');
$tem_gratuito = tabela_tem_coluna($conn, 'ID_CURSO', 'IDC_GRATUITO');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "salvar" && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "salvar") {
    $titulo = site_entrada_texto($_POST["IDC_TITULO"] ?? "");
    $descricao = site_entrada_texto($_POST["IDC_DESCRICAO"] ?? "");
    $categoria_id = isset($_POST["IDC_CATEGORIA_ID"]) && $_POST["IDC_CATEGORIA_ID"] !== "" ? (int) $_POST["IDC_CATEGORIA_ID"] : null;
    $professor = site_entrada_texto($_POST["IDC_PROFESSOR_NOME"] ?? "");
    $nivel = site_entrada_texto($_POST["IDC_NIVEL"] ?? "");
    $carga_horaria = isset($_POST["IDC_CARGA_HORARIA_MIN"]) && $_POST["IDC_CARGA_HORARIA_MIN"] !== "" ? (int) $_POST["IDC_CARGA_HORARIA_MIN"] : null;
    $status = in_array($_POST["IDC_STATUS"] ?? "", array("RASCUNHO", "PUBLICADO", "ARQUIVADO"), true) ? $_POST["IDC_STATUS"] : "RASCUNHO";
    $destaque = isset($_POST["IDC_DESTAQUE"]) ? 1 : 0;
    $gratuito = isset($_POST["IDC_GRATUITO"]) ? 1 : 0;

    if ($titulo === "") {
        $erro = "Informe o título do curso.";
    } else {
        // A capa vai pro Cloudflare R2 (nunca pro disco do Render, que é
        // apagado a cada deploy). Ver site_salvar_imagem_upload() em conexao.php.
        $capa_nova = site_salvar_imagem_upload("capa", "capas", $erro);

        if ($erro === "") {
            // Um "?" por coluna; os valores vão em $params, na mesma ordem.
            $colunas = array("IDC_TITULO", "IDC_DESCRICAO", "IDC_CATEGORIA_ID", "IDC_CARGA_HORARIA_MIN", "IDC_STATUS");
            $params = array($titulo, $descricao, $categoria_id ?: null, $carga_horaria, $status);

            if ($capa_nova) {
                $colunas[] = "IDC_CAPA_URL";
                $params[] = $capa_nova;
            }
            if ($tem_professor) {
                $colunas[] = "IDC_PROFESSOR_NOME";
                $params[] = $professor;
            }
            if ($tem_nivel) {
                $colunas[] = "IDC_NIVEL";
                $params[] = $nivel !== "" ? $nivel : null;
            }
            if ($tem_destaque) {
                $colunas[] = "IDC_DESTAQUE";
                $params[] = $destaque;
            }
            if ($tem_gratuito) {
                $colunas[] = "IDC_GRATUITO";
                $params[] = $gratuito;
            }

            $sql = "INSERT INTO ID_CURSO (" . implode(", ", $colunas) . ") VALUES (" . implode(", ", array_fill(0, count($colunas), "?")) . ")";

            if (db_query($conn, $sql, $params)) {
                $novo_id = mysqli_insert_id($conn);
                if ($tem_destaque && $destaque) {
                    mysqli_query($conn, "UPDATE ID_CURSO SET IDC_DESTAQUE = 0 WHERE IDC_ID <> $novo_id");
                }
                $mensagem = "Curso criado com sucesso.";
            } else {
                // O curso não foi salvo: a capa que já subiu pro R2 ficaria órfã.
                if ($capa_nova) {
                    site_excluir_arquivo_r2_orfao($capa_nova);
                }
                $erro = "Erro ao salvar o curso no banco.";
            }
        }
    }
}

// Arquivar: tira o curso do ar e guarda alunos, progresso e certificados
// (o caminho sugerido quando o curso tem histórico).
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["acao"] ?? "") === "arquivar") {
    $id = (int) ($_POST["IDC_ID"] ?? 0);
    if (!csrf_validar()) {
        $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
    } elseif ($id > 0 && mysqli_query($conn, "UPDATE ID_CURSO SET IDC_STATUS = 'ARQUIVADO' WHERE IDC_ID = $id")) {
        site_cache_apagar("catalogo_publico");
        admin_log($conn, 'curso.arquivar', 'curso', $id, array());
        $mensagem = "Curso arquivado: saiu do ar, e os alunos continuam com o progresso e os certificados.";
    } else {
        $erro = "Não foi possível arquivar o curso. Tente de novo.";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "excluir" && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "excluir") {
    $id = (int) ($_POST["IDC_ID"] ?? 0);
    if ($id > 0) {
        // As aulas do curso saem junto (ON DELETE CASCADE em ID_CONTENT), então
        // guarda antes os vídeos e a capa do R2 pra apagar os arquivos do
        // bucket depois.
        $arquivos_r2 = array();
        $res_videos = mysqli_query($conn, "SELECT IDCT_URL FROM ID_CONTENT WHERE IDC_ID = $id AND IDCT_URL LIKE 'r2:%'");
        while ($res_videos && ($linha_video = mysqli_fetch_assoc($res_videos))) {
            $arquivos_r2[] = $linha_video["IDCT_URL"] ?? "";
        }
        $res_capa = mysqli_query($conn, "SELECT IDC_TITULO, IDC_CAPA_URL FROM ID_CURSO WHERE IDC_ID = $id LIMIT 1");
        $linha_capa = $res_capa ? mysqli_fetch_assoc($res_capa) : null;
        if ($linha_capa && !empty($linha_capa["IDC_CAPA_URL"])) {
            $arquivos_r2[] = $linha_capa["IDC_CAPA_URL"];
        }

        // Matrículas e certificados também saem em cascata: um curso com alunos
        // apagava o progresso e os certificados de todos sem aviso. Com
        // histórico, só exclui na segunda confirmação (o normal é arquivar).
        $res_hist = mysqli_query($conn, "SELECT (SELECT COUNT(*) FROM ID_MATRICULA WHERE IDC_ID = $id) AS alunos, (SELECT COUNT(*) FROM id_certificado WHERE idc_id = $id) AS certificados");
        $hist = $res_hist ? mysqli_fetch_assoc($res_hist) : null;
        $alunos = (int) ($hist["alunos"] ?? 0);
        $certificados = (int) ($hist["certificados"] ?? 0);
        if (!$hist) {
            $erro = "Não foi possível conferir os alunos do curso. Tente de novo.";
        } elseif (($alunos > 0 || $certificados > 0) && ($_POST["confirmar_historico"] ?? "") !== "1") {
            $curso_com_historico = array("id" => $id, "titulo" => (string) ($linha_capa["IDC_TITULO"] ?? ""), "alunos" => $alunos, "certificados" => $certificados);
        } elseif (mysqli_query($conn, "DELETE FROM ID_CURSO WHERE IDC_ID = $id LIMIT 1")) {
            foreach ($arquivos_r2 as $arquivo) {
                site_excluir_arquivo_r2_orfao($arquivo);
            }
            admin_log($conn, 'curso.excluir', 'curso', $id, array('titulo' => (string) ($linha_capa['IDC_TITULO'] ?? ''), 'aulas_apagadas' => count($arquivos_r2), 'alunos' => $alunos, 'certificados' => $certificados));
            $mensagem = "Curso excluído com sucesso.";
        } else {
            $erro = "Não foi possível excluir o curso. Tente de novo.";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "em_alta" && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "em_alta") {
    $minimo_texto = trim(site_entrada_texto($_POST["em_alta_minimo"] ?? '', false));
    if (!preg_match('/^\d+$/', $minimo_texto) || (int) $minimo_texto > EM_ALTA_MINIMO_MAXIMO) {
        $erro = "Informe um número inteiro de 0 a " . number_format(EM_ALTA_MINIMO_MAXIMO, 0, ',', '.') . " para o selo \"Em alta\".";
    } elseif (site_config_salvar($conn, 'em_alta_minimo', (string) (int) $minimo_texto)) {
        header("Location: admin_cursos.php?em_alta_salvo=1#em-alta");
        exit;
    } else {
        $erro = "Não foi possível salvar o selo \"Em alta\". Rode a seção 43 do database/bancosupabase.md no Supabase.";
    }
}
if (isset($_GET["em_alta_salvo"]) && $erro === "") {
    $mensagem = "Selo \"Em alta\" atualizado.";
}

// Selo "Em alta": cursos mais vistos nos últimos 7 dias, para o admin
// calibrar o mínimo.
$em_alta_minimo = em_alta_minimo();
$em_alta_cursos = em_alta_contagens($conn)['cursos'];
arsort($em_alta_cursos);
$em_alta_ranking = array();
if (!empty($em_alta_cursos)) {
    $ids_ranking = implode(',', array_map('intval', array_slice(array_keys($em_alta_cursos), 0, 8)));
    $res_ranking = mysqli_query($conn, "SELECT IDC_ID, IDC_TITULO, IDC_STATUS FROM ID_CURSO WHERE IDC_ID IN ($ids_ranking)");
    $titulos_ranking = array();
    while ($res_ranking && $linha = mysqli_fetch_assoc($res_ranking)) {
        $titulos_ranking[(int) $linha["IDC_ID"]] = $linha;
    }
    foreach (array_slice($em_alta_cursos, 0, 8, true) as $id_ranking => $pessoas) {
        if (isset($titulos_ranking[$id_ranking])) {
            $em_alta_ranking[] = array("curso" => $titulos_ranking[$id_ranking], "pessoas" => $pessoas);
        }
    }
}

$cursos = array();
$res_cursos = mysqli_query($conn, "
    SELECT c.*, cat.IDCAT_NOME AS categoria_nome
    FROM ID_CURSO c
    LEFT JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = c.IDC_CATEGORIA_ID
    ORDER BY c.IDC_CRIADO_EM DESC
");
if ($res_cursos) {
    while ($linha = mysqli_fetch_assoc($res_cursos)) {
        $cursos[] = $linha;
    }
}

$categorias = array();
$res_categorias = mysqli_query($conn, "SELECT IDCAT_ID, IDCAT_NOME FROM ID_CATEGORIA WHERE IDCAT_ATIVO = 1 ORDER BY IDCAT_NOME");
if ($res_categorias) {
    while ($linha = mysqli_fetch_assoc($res_categorias)) {
        $categorias[] = $linha;
    }
}
// O catálogo público (cursos.php) guarda a lista por 5 min: mudança aqui
// aparece lá na hora.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    site_cache_apagar("catalogo_publico");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursos - Painel Admin</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'cursos', 'Cursos', 'fa-graduation-cap'); ?>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <p class="mb-0 text-muted">Crie e organize os cursos exibidos na área do fiel</p>
                <a href="admin_conteudos.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-film me-2"></i>Gerenciar Vídeos/Aulas
                </a>
            </div>

            <?php if ($mensagem !== "") { ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
            <?php } ?>
            <?php if ($erro !== "") { ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
            <?php } ?>
            <?php if ($curso_com_historico) { ?>
                <div class="alert alert-warning" role="alert">
                    <p class="mb-2"><strong><?php echo htmlspecialchars($curso_com_historico["titulo"]); ?></strong> tem <?php echo (int) $curso_com_historico["alunos"]; ?> aluno(s) e <?php echo (int) $curso_com_historico["certificados"]; ?> certificado(s). Excluir apaga também o progresso e os certificados dessas pessoas, sem volta.</p>
                    <p class="mb-3">Para tirar o curso do ar sem perder o histórico, arquive.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST">
                            <?php echo csrf_campo(); ?>
                            <input type="hidden" name="acao" value="arquivar">
                            <input type="hidden" name="IDC_ID" value="<?php echo (int) $curso_com_historico["id"]; ?>">
                            <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-box-archive me-1" aria-hidden="true"></i>Arquivar (recomendado)</button>
                        </form>
                        <form method="POST" data-confirmar="Excluir mesmo assim? O progresso e os certificados dos alunos serão apagados, sem volta.">
                            <?php echo csrf_campo(); ?>
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="confirmar_historico" value="1">
                            <input type="hidden" name="IDC_ID" value="<?php echo (int) $curso_com_historico["id"]; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Excluir mesmo assim</button>
                        </form>
                    </div>
                </div>
            <?php } ?>
            <?php if (!$tem_professor || !$tem_nivel || !$tem_destaque) { ?>
                <div class="alert alert-warning">
                    Alguns campos novos (professor, nível ou destaque) ainda não existem no banco.
                    Rode <code>database/migracao_streaming_ui.sql</code> para liberá-los.
                </div>
            <?php } ?>

            <div class="admin-card">
            <h5 class="admin-card-title"><i class="fas fa-plus-circle"></i>Novo curso</h5>
            <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="salvar">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Título</label>
                        <input type="text" name="IDC_TITULO" class="form-control custom-input" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Categoria</label>
                        <select name="IDC_CATEGORIA_ID" class="form-control custom-input">
                            <option value="">Sem categoria</option>
                            <?php foreach ($categorias as $cat) { ?>
                                <option value="<?php echo (int) $cat["IDCAT_ID"]; ?>"><?php echo htmlspecialchars($cat["IDCAT_NOME"]); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descrição</label>
                        <textarea name="IDC_DESCRICAO" class="form-control custom-input" rows="3"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Professor / Ministrante</label>
                        <input type="text" name="IDC_PROFESSOR_NOME" class="form-control custom-input" <?php echo $tem_professor ? '' : 'disabled placeholder="Rode a migração para habilitar"'; ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nível</label>
                        <select name="IDC_NIVEL" class="form-control custom-input" <?php echo $tem_nivel ? '' : 'disabled'; ?>>
                            <option value="">Selecione</option>
                            <option value="INICIANTE">Iniciante</option>
                            <option value="INTERMEDIARIO">Intermediário</option>
                            <option value="AVANCADO">Avançado</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Carga horária (min)</label>
                        <input type="number" name="IDC_CARGA_HORARIA_MIN" class="form-control custom-input" min="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Capa (imagem 16:9)</label>
                        <input type="file" name="capa" class="form-control custom-input" accept="image/*">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="IDC_STATUS" class="form-control custom-input">
                            <option value="RASCUNHO">Rascunho (não aparece para o fiel)</option>
                            <option value="PUBLICADO" selected>Publicado</option>
                            <option value="ARQUIVADO">Arquivado</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="IDC_DESTAQUE" id="IDC_DESTAQUE" <?php echo $tem_destaque ? '' : 'disabled'; ?>>
                            <label class="form-check-label" for="IDC_DESTAQUE">Destacar na Home (banner)</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="IDC_GRATUITO" id="IDC_GRATUITO" <?php echo $tem_gratuito ? '' : 'disabled'; ?>>
                            <label class="form-check-label" for="IDC_GRATUITO">Curso gratuito (liberado sem assinatura)</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-light fw-bold">
                            <i class="fas fa-plus me-2"></i>Criar Curso
                        </button>
                    </div>
                </div>
            </form>
            </div>

            <div class="admin-card" id="em-alta">
            <h5 class="admin-card-title"><i class="fas fa-fire"></i>Selo "Em alta"</h5>
            <p class="text-muted small mb-3">
                Aparece no thumbnail dos cursos e aulas que muitas pessoas assistiram nos últimos 7 dias.
                Conta pessoas diferentes (assistir várias vezes conta uma só).
            </p>
            <form method="POST" class="row g-3 align-items-end mb-3">
                <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="em_alta">
                <div class="col-sm-6 col-md-4">
                    <label class="form-label" for="em_alta_minimo">Mínimo de pessoas em 7 dias</label>
                    <input type="number" name="em_alta_minimo" id="em_alta_minimo" class="form-control custom-input" min="0" max="<?php echo EM_ALTA_MINIMO_MAXIMO; ?>" step="1" required value="<?php echo (int) $em_alta_minimo; ?>">
                    <div class="form-text">0 desliga o selo.</div>
                </div>
                <div class="col-sm-6 col-md-3 pb-sm-4">
                    <button type="submit" class="btn btn-light fw-bold"><i class="fas fa-save me-2"></i>Salvar</button>
                </div>
            </form>
            <h6 class="mb-2">Mais vistos nos últimos 7 dias</h6>
            <?php if (empty($em_alta_ranking)) { ?>
                <p class="text-muted small mb-0">Ninguém assistiu a nenhuma aula nos últimos 7 dias.</p>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table data-table align-middle mb-0">
                        <thead>
                            <tr><th>Curso</th><th>Pessoas</th><th>Selo</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($em_alta_ranking as $item) { ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($item["curso"]["IDC_TITULO"]); ?>
                                        <?php if ($item["curso"]["IDC_STATUS"] !== 'PUBLICADO') { ?>
                                            <span class="small text-muted">(não publicado)</span>
                                        <?php } ?>
                                    </td>
                                    <td><?php echo (int) $item["pessoas"]; ?></td>
                                    <td>
                                        <?php if ($em_alta_minimo > 0 && $item["pessoas"] >= $em_alta_minimo) { ?>
                                            <span class="badge bg-danger"><i class="fas fa-fire me-1"></i>Em alta</span>
                                        <?php } else { ?>
                                            <span class="text-muted">—</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
            </div>

            <div class="admin-card">
            <h5 class="admin-card-title"><i class="fas fa-list"></i>Cursos cadastrados (<?php echo count($cursos); ?>)</h5>
            <div class="table-responsive">
                <table class="table data-table align-middle">
                    <thead>
                        <tr>
                            <th>Capa</th>
                            <th>Título</th>
                            <th>Categoria</th>
                            <th>Status</th>
                            <th>Destaque</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cursos as $curso) { ?>
                            <?php
                                $capa = function_exists('curso_resolver_capa') ? curso_resolver_capa($curso['IDC_CAPA_URL'] ?? null) : ($curso['IDC_CAPA_URL'] ?? null);
                            ?>
                            <tr>
                                <td>
                                    <?php if ($capa) { ?>
                                        <img src="<?php echo htmlspecialchars($capa); ?>" alt="" style="width:70px;height:40px;object-fit:cover;border-radius:6px;">
                                    <?php } else { ?>
                                        <span class="text-muted">—</span>
                                    <?php } ?>
                                </td>
                                <td><?php echo htmlspecialchars($curso["IDC_TITULO"]); ?></td>
                                <td><?php echo htmlspecialchars($curso["categoria_nome"] ?? "—"); ?></td>
                                <td>
                                    <?php
                                        $status_curso = $curso["IDC_STATUS"];
                                        $status_cor = $status_curso === 'PUBLICADO' ? 'success' : ($status_curso === 'RASCUNHO' ? 'warning' : 'secondary');
                                    ?>
                                    <span class="badge bg-<?php echo $status_cor; ?>"><?php echo htmlspecialchars($status_curso); ?></span>
                                    <?php if ($status_curso !== 'PUBLICADO') { ?>
                                        <div class="small text-muted mt-1">Não aparece para o fiel</div>
                                    <?php } ?>
                                </td>
                                <td><?php echo (!empty($curso["IDC_DESTAQUE"])) ? '<i class="fas fa-star text-warning"></i>' : '—'; ?></td>
                                <td class="d-flex gap-2">
                                    <a href="admin_editar_curso.php?id=<?php echo (int) $curso["IDC_ID"]; ?>" class="btn btn-sm btn-outline-light" title="Editar curso" aria-label="Editar curso">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <?php if (admin_pode_acessar('conteudos')) { ?>
                                        <a href="admin_grade_curso.php?id=<?php echo (int) $curso["IDC_ID"]; ?>" class="btn btn-sm btn-outline-light" title="Aulas e ordem (grade do curso)" aria-label="Aulas e ordem">
                                            <i class="fas fa-list-ol"></i>
                                        </a>
                                    <?php } ?>
                                    <?php /* O curso sai com as aulas (ON DELETE CASCADE) e os vídeos e a capa são apagados do R2, sem lixeira: o aviso antigo dizia o contrário. */ ?>
                                    <form method="POST" data-confirmar="Excluir este curso? TODAS as aulas e os vídeos dele também serão apagados, sem volta.">

                                    <?php echo csrf_campo(); ?>
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="IDC_ID" value="<?php echo (int) $curso["IDC_ID"]; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Excluir curso"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if (count($cursos) === 0) { ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhum curso cadastrado ainda.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            </div>

    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
