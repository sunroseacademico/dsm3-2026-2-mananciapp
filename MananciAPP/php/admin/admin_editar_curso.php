<?php
session_start();
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';
require_once __DIR__ . '/../fiel/includes/cursos_helpers.php';

// auth_require confere também inatividade, revogação (admin removido ou
// "desconectado" cai na hora) e o Termo — a checagem manual que havia aqui
// só olhava a sessão.
auth_require(array('admin'));

$mensagem = "";
$erro = "";

$tem_professor = tabela_tem_coluna($conn, 'ID_CURSO', 'IDC_PROFESSOR_NOME');
$tem_nivel = tabela_tem_coluna($conn, 'ID_CURSO', 'IDC_NIVEL');
$tem_destaque = tabela_tem_coluna($conn, 'ID_CURSO', 'IDC_DESTAQUE');
$tem_gratuito = tabela_tem_coluna($conn, 'ID_CURSO', 'IDC_GRATUITO');

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
if ($id <= 0) {
    header("location:admin_cursos.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
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

        // Capa atual, pra apagar do R2 depois de trocar.
        $res_capa = mysqli_query($conn, "SELECT IDC_CAPA_URL FROM ID_CURSO WHERE IDC_ID = $id LIMIT 1");
        $linha_capa = $res_capa ? mysqli_fetch_assoc($res_capa) : null;
        $capa_antiga = $linha_capa["IDC_CAPA_URL"] ?? "";

        if ($erro === "") {
            // Cada "SET coluna = ?" tem o seu valor em $params, na mesma ordem.
            $sets = array(
                "IDC_TITULO = ?",
                "IDC_DESCRICAO = ?",
                "IDC_CATEGORIA_ID = ?",
                "IDC_CARGA_HORARIA_MIN = ?",
                "IDC_STATUS = ?",
            );
            $params = array($titulo, $descricao, $categoria_id ?: null, $carga_horaria, $status);

            if ($capa_nova) {
                $sets[] = "IDC_CAPA_URL = ?";
                $params[] = $capa_nova;
            }
            if ($tem_professor) {
                $sets[] = "IDC_PROFESSOR_NOME = ?";
                $params[] = $professor;
            }
            if ($tem_nivel) {
                $sets[] = "IDC_NIVEL = ?";
                $params[] = $nivel !== "" ? $nivel : null;
            }
            if ($tem_destaque) {
                $sets[] = "IDC_DESTAQUE = ?";
                $params[] = $destaque;
            }
            if ($tem_gratuito) {
                $sets[] = "IDC_GRATUITO = ?";
                $params[] = $gratuito;
            }
            $params[] = $id;

            $sql_update = "UPDATE ID_CURSO SET " . implode(", ", $sets) . " WHERE IDC_ID = ? LIMIT 1";

            if (db_query($conn, $sql_update, $params)) {
                if ($tem_destaque && $destaque) {
                    mysqli_query($conn, "UPDATE ID_CURSO SET IDC_DESTAQUE = 0 WHERE IDC_ID <> $id");
                }
                // Trocou a capa: se a antiga estava no R2, apaga do bucket.
                if ($capa_nova && $capa_antiga !== $capa_nova) {
                    site_excluir_arquivo_r2_orfao($capa_antiga);
                }
                $mensagem = "Curso atualizado com sucesso.";
            } else {
                // Não salvou: a capa nova que já subiu pro R2 ficaria órfã.
                if ($capa_nova) {
                    site_excluir_arquivo_r2_orfao($capa_nova);
                }
                $erro = "Erro ao atualizar o curso.";
            }
        }
    }
}

$curso = curso_obter_por_id($conn, $id);
if (!$curso) {
    header("location:admin_cursos.php");
    exit;
}

$categorias = array();
$res_categorias = mysqli_query($conn, "SELECT IDCAT_ID, IDCAT_NOME FROM ID_CATEGORIA WHERE IDCAT_ATIVO = 1 ORDER BY IDCAT_NOME");
if ($res_categorias) {
    while ($linha = mysqli_fetch_assoc($res_categorias)) {
        $categorias[] = $linha;
    }
}

$capa_atual = curso_resolver_capa($curso['IDC_CAPA_URL'] ?? null);
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
    <title>Editar Curso - Painel Admin</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'cursos', 'Editar Curso', 'fa-pen'); ?>

        <p class="text-muted mb-4">Ajuste as informações exibidas na área do fiel</p>

        <div class="admin-card">

            <?php if ($mensagem !== "") { ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
            <?php } ?>
            <?php if ($erro !== "") { ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
            <?php } ?>

            <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_campo(); ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Título</label>
                        <input type="text" name="IDC_TITULO" class="form-control custom-input" value="<?php echo htmlspecialchars($curso["IDC_TITULO"]); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Categoria</label>
                        <select name="IDC_CATEGORIA_ID" class="form-control custom-input">
                            <option value="">Sem categoria</option>
                            <?php foreach ($categorias as $cat) { ?>
                                <option value="<?php echo (int) $cat["IDCAT_ID"]; ?>" <?php echo ((int) ($curso["IDC_CATEGORIA_ID"] ?? 0) === (int) $cat["IDCAT_ID"]) ? "selected" : ""; ?>><?php echo htmlspecialchars($cat["IDCAT_NOME"]); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descrição</label>
                        <textarea name="IDC_DESCRICAO" class="form-control custom-input" rows="3"><?php echo htmlspecialchars($curso["IDC_DESCRICAO"] ?? ""); ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Professor / Ministrante</label>
                        <input type="text" name="IDC_PROFESSOR_NOME" class="form-control custom-input" value="<?php echo htmlspecialchars($curso["IDC_PROFESSOR_NOME"] ?? ""); ?>" <?php echo $tem_professor ? '' : 'disabled'; ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nível</label>
                        <select name="IDC_NIVEL" class="form-control custom-input" <?php echo $tem_nivel ? '' : 'disabled'; ?>>
                            <option value="">Selecione</option>
                            <?php foreach (array("INICIANTE" => "Iniciante", "INTERMEDIARIO" => "Intermediário", "AVANCADO" => "Avançado") as $valor => $rotulo) { ?>
                                <option value="<?php echo $valor; ?>" <?php echo (($curso["IDC_NIVEL"] ?? "") === $valor) ? "selected" : ""; ?>><?php echo $rotulo; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Carga horária (min)</label>
                        <input type="number" name="IDC_CARGA_HORARIA_MIN" class="form-control custom-input" value="<?php echo (int) ($curso["IDC_CARGA_HORARIA_MIN"] ?? 0); ?>" min="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Trocar capa (opcional)</label>
                        <input type="file" name="capa" class="form-control custom-input" accept="image/*">
                        <?php if ($capa_atual) { ?>
                            <img src="<?php echo htmlspecialchars($capa_atual); ?>" alt="" class="mt-2" style="max-width:220px;border-radius:8px;border:1px solid var(--border-color);">
                        <?php } ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="IDC_STATUS" class="form-control custom-input">
                            <?php foreach (array("RASCUNHO" => "Rascunho", "PUBLICADO" => "Publicado", "ARQUIVADO" => "Arquivado") as $valor => $rotulo) { ?>
                                <option value="<?php echo $valor; ?>" <?php echo ($curso["IDC_STATUS"] === $valor) ? "selected" : ""; ?>><?php echo $rotulo; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="IDC_DESTAQUE" id="IDC_DESTAQUE" <?php echo !empty($curso["IDC_DESTAQUE"]) ? "checked" : ""; ?> <?php echo $tem_destaque ? '' : 'disabled'; ?>>
                            <label class="form-check-label" for="IDC_DESTAQUE">Destacar na Home</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="IDC_GRATUITO" id="IDC_GRATUITO" <?php echo !empty($curso["IDC_GRATUITO"]) ? "checked" : ""; ?> <?php echo $tem_gratuito ? '' : 'disabled'; ?>>
                            <label class="form-check-label" for="IDC_GRATUITO">Curso gratuito (liberado sem assinatura)</label>
                        </div>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-light fw-bold">Salvar alterações</button>
                        <a href="admin_cursos.php" class="btn btn-outline-light">Voltar para Cursos</a>
                    </div>
                </div>
            </form>
        </div>
    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
