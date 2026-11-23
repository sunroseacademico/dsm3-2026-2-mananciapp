<?php
session_start();
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

// auth_require confere também inatividade, revogação (admin removido ou
// "desconectado" cai na hora) e o Termo — a checagem manual que havia aqui
// só olhava a sessão.
auth_require(array('admin'));

// Aulas mudam o número de aulas e a duração dos cards (cache "catalogo_publico").
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    site_cache_apagar("catalogo_publico");
}

$mensagem = "";
$erro = "";
$conteudo = null;
$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id <= 0) {
    header("location:admin_conteudos.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = trim(site_entrada_texto($_POST["IDCT_TITULO"] ?? '', false));
    $descricao = trim(site_entrada_texto($_POST["IDCT_DESCRICAO"] ?? '', false));
    $ordem = (int) ($_POST["IDCT_ORDEM"] ?? 1);
    $curso_id = (int) ($_POST["IDC_ID"] ?? 0);
    $modulo_id = isset($_POST["IDM_ID"]) && $_POST["IDM_ID"] !== "" ? (int) $_POST["IDM_ID"] : null;
    $video_url_novo = trim(site_entrada_texto($_POST["video_url_novo"] ?? '', false));
    $curso_existe = $curso_id > 0 ? db_query($conn, "SELECT 1 FROM ID_CURSO WHERE IDC_ID = ? LIMIT 1", array($curso_id)) : false;

    if ($titulo === "") {
        $erro = "Informe o titulo do video.";
    } elseif (!$curso_existe || mysqli_num_rows($curso_existe) === 0) {
        // Antes caía no curso de ID 1 (que pode nem existir) — o mesmo
        // fallback que admin_conteudos.php já tinha removido.
        $erro = "Selecione um curso válido para a aula.";
    } elseif (!admin_modulo_do_curso($conn, $modulo_id, $curso_id)) {
        $erro = "O módulo escolhido não é desse curso. Selecione o módulo de novo.";
    } elseif ($video_url_novo !== "" && !admin_video_url_valida($video_url_novo)) {
        $erro = "Caminho do vídeo inválido. Envie o arquivo de novo.";
    } else {
        $sql_busca = "SELECT IDCT_URL FROM ID_CONTENT WHERE IDCT_ID = $id LIMIT 1";
        $res_busca = mysqli_query($conn, $sql_busca);
        $conteudo_atual = $res_busca ? mysqli_fetch_assoc($res_busca) : null;
        $url_final = $conteudo_atual && isset($conteudo_atual["IDCT_URL"]) ? $conteudo_atual["IDCT_URL"] : "";

        // Se um novo video foi enviado, o JS ja subiu o arquivo pro Cloudflare R2
        // e preencheu o campo escondido "video_url_novo" com o caminho ("r2:aulas/...").
        if ($video_url_novo !== "") {
            $url_final = $video_url_novo;
        }

        if ($erro === "") {
            // Vídeo novo: grava a duração lida do arquivo pelo navegador; sem
            // ela, zera para o player medir de novo (ver ajax_progresso.php).
            $duracao_sql = $video_url_novo !== "" ? ", IDCT_DURACAO_SEGUNDOS = " . admin_video_duracao_sql($_POST["video_duracao"] ?? 0) : "";

            $sql_update = "UPDATE ID_CONTENT SET IDC_ID = ?, IDM_ID = ?, IDCT_TITULO = ?, IDCT_DESCRICAO = ?, IDCT_URL = ?, IDCT_ORDEM = ?$duracao_sql WHERE IDCT_ID = ? LIMIT 1";

            if (db_query($conn, $sql_update, array($curso_id, $modulo_id, $titulo, $descricao, $url_final, $ordem, $id))) {
                // Trocou o vídeo: se o antigo estava no R2, apaga do bucket.
                $url_antiga = $conteudo_atual["IDCT_URL"] ?? "";
                if ($video_url_novo !== "" && $url_antiga !== $url_final) {
                    site_excluir_arquivo_r2_orfao($url_antiga);
                }
                $mensagem = "Video atualizado com sucesso.";
            } else {
                $erro = "Erro ao atualizar no banco.";
            }
        }
    }
}

$sql = "SELECT * FROM ID_CONTENT WHERE IDCT_ID = $id LIMIT 1";
$resultado = mysqli_query($conn, $sql);
if ($resultado && mysqli_num_rows($resultado) == 1) {
    $conteudo = mysqli_fetch_assoc($resultado);
} else {
    header("location:admin_conteudos.php");
    exit;
}

$cursos = array();
$res_cursos = mysqli_query($conn, "SELECT IDC_ID, IDC_TITULO, IDC_STATUS FROM ID_CURSO ORDER BY IDC_TITULO");
if ($res_cursos && mysqli_num_rows($res_cursos) > 0) {
    while ($linha = mysqli_fetch_assoc($res_cursos)) {
        $cursos[] = $linha;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Editar Conteúdo</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <style>
        :root[data-theme="dark"] .video-edit-success {
            background: #14532d !important;
            color: #ffffff !important;
            border: 1px solid #166534 !important;
        }
    </style>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'conteudos', 'Editar Vídeo', 'fa-pen'); ?>

        <p class="text-muted mb-4">Ajuste titulo, descricao, ordem e arquivo do conteudo</p>

        <div class="admin-card">

            <?php if ($mensagem !== "") { ?>
                <div class="alert alert-success video-edit-success"><?php echo htmlspecialchars($mensagem); ?></div>
            <?php } ?>

            <?php if ($erro !== "") { ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
            <?php } ?>

            <form method="POST" id="formEditarVideo">
            <?php echo csrf_campo(); ?>
                <input type="hidden" name="video_url_novo" id="campoVideoUrlNovo">
                <input type="hidden" name="video_duracao" id="campoVideoDuracao">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Titulo</label>
                        <input type="text" name="IDCT_TITULO" class="form-control custom-input" value="<?php echo htmlspecialchars($conteudo["IDCT_TITULO"]); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Curso</label>
                        <select name="IDC_ID" class="form-control custom-input" required>
                            <option value="">Selecione</option>
                            <?php foreach ($cursos as $curso) { ?>
                                <option value="<?php echo (int) $curso["IDC_ID"]; ?>" <?php echo ((int) $conteudo["IDC_ID"] === (int) $curso["IDC_ID"]) ? "selected" : ""; ?>><?php echo htmlspecialchars($curso["IDC_TITULO"]); ?><?php echo $curso["IDC_STATUS"] !== 'PUBLICADO' ? ' — ' . htmlspecialchars($curso["IDC_STATUS"]) . ' (não aparece p/ o fiel)' : ''; ?></option>
                            <?php } ?>
                        </select>
                        <small class="text-muted">Cursos marcados como Rascunho/Arquivado não aparecem para o fiel até serem publicados em <a href="admin_cursos.php">Cursos</a>.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Modulo (opcional)</label>
                        <input type="text" name="IDM_ID" class="form-control custom-input" value="<?php echo htmlspecialchars($conteudo["IDM_ID"]); ?>" placeholder="ID do modulo" inputmode="numeric" pattern="[0-9]*" title="Use apenas numeros" data-so-digitos>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ordem</label>
                        <input type="number" name="IDCT_ORDEM" class="form-control custom-input" value="<?php echo (int) $conteudo["IDCT_ORDEM"]; ?>" min="1" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descricao</label>
                        <textarea name="IDCT_DESCRICAO" class="form-control custom-input" rows="3"><?php echo htmlspecialchars($conteudo["IDCT_DESCRICAO"]); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Trocar video (opcional)</label>
                        <input type="file" id="campoArquivoVideoNovo" class="form-control custom-input" accept="video/mp4,video/webm,video/ogg,video/quicktime">
                        <small class="text-muted d-block mt-1"><i class="fas fa-circle-info me-1"></i>Prefira <strong>MP4</strong>: toca em todos os aparelhos e na TV (Chromecast e AirPlay). OGG não pode ser transmitido para a TV.</small>
                        <small class="text-muted d-block mt-1">Atual: <?php echo htmlspecialchars($conteudo["IDCT_URL"]); ?></small>
                        <small class="text-muted d-block" id="uploadStatusEdit">Se nenhum arquivo for selecionado, o vídeo atual será mantido. Ao escolher um novo, ele sobe direto pro Cloudflare R2 antes de salvar.</small>
                        <?php $url_preview_atual = site_resolver_url_video($conteudo["IDCT_URL"] ?? ''); ?>
                        <?php if ($url_preview_atual !== '') { ?>
                            <div class="mt-3">
                                <video controls style="max-width: 100%; border-radius: 10px; border: 1px solid var(--border-color);">
                                    <source src="<?php echo htmlspecialchars($url_preview_atual); ?>">
                                    Seu navegador não suporta a reprodução de vídeo.
                                </video>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-light fw-bold" id="btnSalvarEdicao">Salvar alteracoes</button>
                        <a href="dashboard.php" class="btn btn-outline-light">Voltar ao Painel</a>
                    </div>
                </div>
            </form>

            <script nonce="<?php echo site_csp_nonce(); ?>">
                (function () {
                    var form = document.getElementById('formEditarVideo');
                    var inputArquivo = document.getElementById('campoArquivoVideoNovo');
                    var status = document.getElementById('uploadStatusEdit');
                    var btn = document.getElementById('btnSalvarEdicao');
                    var jaEnviado = false;

                    // Duração do arquivo, lida pelo próprio navegador (0 se o
                    // formato não abrir aqui, ex.: .mov no Chrome).
                    function lerDuracaoVideo(file) {
                        return new Promise(function (resolve) {
                            var v = document.createElement('video');
                            var url = URL.createObjectURL(file);
                            var fim = function (segundos) { URL.revokeObjectURL(url); resolve(segundos); };
                            var espera = setTimeout(function () { fim(0); }, 8000);
                            v.preload = 'metadata';
                            v.onloadedmetadata = function () { clearTimeout(espera); fim(isFinite(v.duration) ? Math.round(v.duration) : 0); };
                            v.onerror = function () { clearTimeout(espera); fim(0); };
                            v.src = url;
                        });
                    }

                    form.addEventListener('submit', async function (e) {
                        var file = inputArquivo.files[0];

                        // Sem arquivo novo selecionado: mantem o video atual, envia normal.
                        if (!file || jaEnviado) {
                            return;
                        }

                        e.preventDefault();
                        btn.disabled = true;
                        status.classList.remove('text-danger');
                        status.textContent = 'Preparando upload...';

                        try {
                            var respostaAssinatura = await fetch('r2_upload_url.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'nome=' + encodeURIComponent(file.name) + '&tamanho=' + file.size + '&csrf_token=' + encodeURIComponent((document.querySelector('input[name="csrf_token"]') || {}).value || ''),
                            });
                            var assinatura = await respostaAssinatura.json();

                            if (!assinatura.ok) {
                                throw new Error(assinatura.erro || 'Não foi possível preparar o upload.');
                            }

                            // PUT direto na URL pre-assinada; o texto mostra o progresso real.
                            await new Promise(function (resolve, reject) {
                                var xhr = new XMLHttpRequest();
                                xhr.open('PUT', assinatura.uploadUrl);
                                // Content-Type do servidor: faz parte da assinatura.
                                xhr.setRequestHeader('Content-Type', assinatura.contentType);
                                xhr.upload.onprogress = function (ev) {
                                    if (ev.lengthComputable) {
                                        status.textContent = 'Enviando novo video para o Cloudflare R2... ' + Math.round((ev.loaded / ev.total) * 100) + '%';
                                    }
                                };
                                xhr.onload = function () {
                                    if (xhr.status >= 200 && xhr.status < 300) {
                                        resolve();
                                    } else {
                                        reject(new Error('Falha no upload (HTTP ' + xhr.status + ').'));
                                    }
                                };
                                xhr.onerror = function () { reject(new Error('Falha de rede no upload (confira o CORS do bucket).')); };
                                status.textContent = 'Enviando novo video para o Cloudflare R2...';
                                xhr.send(file);
                            });

                            // Bucket privado: guardamos o caminho, nao uma URL publica.
                            document.getElementById('campoVideoUrlNovo').value = assinatura.path;
                            document.getElementById('campoVideoDuracao').value = await lerDuracaoVideo(file);

                            status.textContent = 'Upload concluído. Salvando alterações...';
                            jaEnviado = true;
                            form.submit();
                        } catch (err) {
                            btn.disabled = false;
                            status.classList.add('text-danger');
                            status.textContent = 'Erro no upload: ' + (err && err.message ? err.message : 'tente novamente.');
                        }
                    });
                })();
            </script>
        </div>
    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
