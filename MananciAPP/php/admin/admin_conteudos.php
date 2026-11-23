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

// O vídeo já chega aqui hospedado no Cloudflare R2: o navegador faz o upload
// direto pro bucket (ver JS mais abaixo) e só o caminho ("r2:aulas/...") é
// enviado pro PHP. Isso evita que arquivos grandes passem pelo servidor do Render.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] === "salvar_video" && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] === "salvar_video") {
    $titulo = site_entrada_texto($_POST["IDCT_TITULO"] ?? "");
    $descricao = site_entrada_texto($_POST["IDCT_DESCRICAO"] ?? "");
    $ordem = (int) ($_POST["IDCT_ORDEM"] ?? 1);
    $curso_id = (int) ($_POST["IDC_ID"] ?? 0);
    $modulo_id = isset($_POST["IDM_ID"]) && $_POST["IDM_ID"] !== "" ? (int) $_POST["IDM_ID"] : null;
    $video_url = site_entrada_texto($_POST["video_url"] ?? "");
    $video_nome = site_entrada_texto($_POST["video_nome"] ?? "");
    $video_mime = site_entrada_texto($_POST["video_mime"] ?? "");
    $video_tamanho = isset($_POST["video_tamanho"]) ? (int) $_POST["video_tamanho"] : 0;

    if ($titulo === "") {
        $erro = "Informe o titulo do video.";
    } elseif ($video_url === "") {
        $erro = "Nenhum video foi enviado para o armazenamento. Selecione um arquivo e aguarde o upload terminar antes de salvar.";
    } elseif (!admin_video_url_valida($video_url)) {
        $erro = "Caminho do vídeo inválido. Envie o arquivo de novo.";
    } elseif ($curso_id <= 0) {
        // Antes isso caia num fallback silencioso pro curso de ID 1, que pode
        // nem existir (ex.: nenhum curso foi criado ainda) — o INSERT falhava
        // por violar a foreign key com ID_CURSO, e o vídeo (já enviado ao
        // bucket nesse ponto) ficava orfão: no bucket, mas sem
        // nenhuma linha em ID_CONTENT apontando pra ele.
        $erro = "Selecione um curso antes de enviar o video. Se nenhum curso aparecer na lista, crie um curso primeiro em Cursos.";
    } else {
        // Confere se o curso escolhido realmente existe, em vez de descobrir
        // isso só quando o INSERT falhar por violação de foreign key.
        $curso_existe = mysqli_query($conn, "SELECT IDC_ID FROM ID_CURSO WHERE IDC_ID = $curso_id LIMIT 1");
        if (!$curso_existe || mysqli_num_rows($curso_existe) === 0) {
            $erro = "O curso selecionado não existe mais. Atualize a página e selecione novamente.";
        } elseif (!admin_modulo_do_curso($conn, $modulo_id, $curso_id)) {
            $erro = "O módulo escolhido não é desse curso. Selecione o módulo de novo.";
        } else {
            $duracao_sql = admin_video_duracao_sql($_POST["video_duracao"] ?? 0);

            $sql = "INSERT INTO ID_CONTENT (IDC_ID, IDM_ID, IDCT_TIPO, IDCT_TITULO, IDCT_DESCRICAO, IDCT_URL, IDCT_ORDEM, IDCT_ARQUIVO_NOME, IDCT_MIME_TYPE, IDCT_TAMANHO_BYTES, IDCT_DURACAO_SEGUNDOS)
                    VALUES (?, ?, 'VIDEO', ?, ?, ?, ?, ?, ?, ?, $duracao_sql)";

            if (db_query($conn, $sql, array($curso_id, $modulo_id, $titulo, $descricao, $video_url, $ordem, $video_nome, $video_mime, $video_tamanho))) {
                $mensagem = "Video publicado com sucesso.";
            } else {
                // O erro real do Postgres vai para o log do Render (ver
                // site_erro_banco()); na tela, só o motivo legível.
                $erro = site_erro_banco("Erro ao salvar a aula no banco de dados.");
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] === "excluir" && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] === "excluir") {
    $id = (int) $_POST["IDCT_ID"];
    $sql_busca = "SELECT IDCT_URL, IDCT_TITULO, IDC_ID FROM ID_CONTENT WHERE IDCT_ID = $id LIMIT 1";
    $res_busca = mysqli_query($conn, $sql_busca);

    if ($res_busca && mysqli_num_rows($res_busca) == 1) {
        // Vídeo no Cloudflare R2 ("r2:..."): o arquivo é apagado do bucket
        // junto, depois que o registro saiu do banco.
        $linha_busca = mysqli_fetch_assoc($res_busca);
        $url_excluida = $linha_busca["IDCT_URL"] ?? "";
        if (mysqli_query($conn, "DELETE FROM ID_CONTENT WHERE IDCT_ID = $id LIMIT 1")) {
            site_excluir_arquivo_r2_orfao($url_excluida);
            admin_log($conn, 'aula.excluir', 'aula', $id, array('titulo' => (string) ($linha_busca['IDCT_TITULO'] ?? ''), 'curso' => (int) ($linha_busca['IDC_ID'] ?? 0)));
        }
        $mensagem = "Vídeo excluído com sucesso.";
    } else {
        $erro = "Vídeo não encontrado.";
    }
}

// Vídeos esquecidos no R2 (enviados sem salvar a aula): primeiro a prévia,
// depois o admin confirma. Ver r2_limpar_videos_orfaos() em conexao.php.
$orfaos_previa = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && in_array($_POST["acao"] ?? "", array("orfaos_previa", "orfaos_apagar"), true)) {
    if (!csrf_validar()) {
        $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
    } else {
        // Listar e apagar centenas de arquivos pode passar do limite padrão.
        set_time_limit(300);
        $apagar = $_POST["acao"] === "orfaos_apagar";
        $orfaos = r2_limpar_videos_orfaos($conn, $apagar);
        if ($orfaos === null) {
            $erro = "Não foi possível consultar o armazenamento agora. Tente de novo em alguns minutos.";
        } elseif ($apagar) {
            $mensagem = $orfaos['apagados'] . " arquivo(s) esquecido(s) apagado(s) do armazenamento.";
        } elseif ($orfaos['total'] === 0) {
            $mensagem = "Nenhum vídeo esquecido no armazenamento.";
        } else {
            $orfaos_previa = $orfaos;
        }
    }
}

// "Ver" de uma aula: a URL assinada do R2 é gerada só no clique. Antes a
// tela assinava uma URL para cada aula do banco a cada abertura.
if (isset($_GET['ver'])) {
    $res_ver = db_query($conn, "SELECT IDCT_URL FROM ID_CONTENT WHERE IDCT_ID = ? LIMIT 1", array((int) $_GET['ver']));
    $linha_ver = $res_ver ? mysqli_fetch_assoc($res_ver) : null;
    $url_ver = $linha_ver ? site_resolver_url_video($linha_ver['IDCT_URL']) : '';
    if ($url_ver !== '') {
        header('Location: ' . $url_ver);
        exit;
    }
    $erro = "Vídeo indisponível.";
}

// Lista das aulas: 50 por página, com filtro por curso e busca no título
// (antes vinham todas de uma vez).
$por_pagina = 50;
$filtro_curso = (int) ($_GET['curso'] ?? 0);
$busca = site_entrada_texto($_GET['q'] ?? '');
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$where = array("LOWER(c.IDCT_TIPO) = 'video'");
$params_lista = array();
if ($filtro_curso > 0) {
    $where[] = 'c.IDC_ID = ?';
    $params_lista[] = $filtro_curso;
}
if ($busca !== '') {
    $where[] = 'LOWER(c.IDCT_TITULO) LIKE ?';
    $params_lista[] = '%' . site_like_escapar(mb_strtolower($busca)) . '%';
}
$where_sql = implode(' AND ', $where);
$res_total = db_query($conn, "SELECT COUNT(*) AS total FROM ID_CONTENT c WHERE $where_sql", $params_lista);
$total_conteudos = $res_total ? (int) (mysqli_fetch_assoc($res_total)['total'] ?? 0) : 0;
$paginas = max(1, (int) ceil($total_conteudos / $por_pagina));
$pagina = min($pagina, $paginas);
$conteudos = array();
$resultado = db_query($conn, "
    SELECT c.IDCT_ID, c.IDC_ID, c.IDM_ID, c.IDCT_TIPO, c.IDCT_TITULO, c.IDCT_URL, c.IDCT_ORDEM, cu.IDC_TITULO
    FROM ID_CONTENT c
    LEFT JOIN ID_CURSO cu ON cu.IDC_ID = c.IDC_ID
    WHERE $where_sql
    ORDER BY " . ($filtro_curso > 0 ? 'c.IDCT_ORDEM ASC, c.IDCT_ID ASC' : 'c.IDCT_ID DESC') . "
    LIMIT $por_pagina OFFSET " . (($pagina - 1) * $por_pagina), $params_lista);
while ($resultado && ($linha = mysqli_fetch_assoc($resultado))) {
    $conteudos[] = $linha;
}
$link_lista = function ($pagina_nova) use ($filtro_curso, $busca) {
    return 'admin_conteudos.php?' . http_build_query(array_filter(array('curso' => $filtro_curso ?: null, 'q' => $busca, 'pagina' => $pagina_nova))) . '#lista-videos';
};

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
    <title>Missão Manancial | Admin Conteúdos</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <style>
        :root[data-theme="dark"] .video-upload-success {
            background: #14532d !important;
            color: #ffffff !important;
            border: 1px solid #166534 !important;
        }

        :root[data-theme="dark"] .video-upload-error {
            background: #7f1d1d !important;
            color: #ffffff !important;
            border: 1px solid #991b1b !important;
        }

        :root[data-theme="dark"] .video-upload-success .btn-close {
            filter: invert(1) grayscale(100%);
        }

        .videos-table {
            color: var(--text-main) !important;
            width: 100%;
            table-layout: fixed;
        }

        .videos-table thead th {
            color: var(--text-main) !important;
            border-bottom-color: var(--border-color) !important;
        }

        .videos-table th,
        .videos-table td {
            white-space: normal;
            word-break: break-word;
        }

        :root[data-theme="light"] .videos-table.table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: #dbeafe !important;
            color: #0f172a !important;
        }

        :root[data-theme="light"] .videos-table.table-striped > tbody > tr:nth-of-type(even) > * {
            background-color: #1e3a8a !important;
            color: #ffffff !important;
        }

        :root[data-theme="light"] .videos-table thead th {
            background: #cbd5e1 !important;
            color: #0f172a !important;
        }

        :root[data-theme="light"] .videos-table tbody td {
            border-color: rgba(148, 163, 184, 0.45) !important;
        }

        .videos-table .video-column-blue {
            color: var(--primary-light) !important;
            font-weight: 600;
        }

        .videos-table th:nth-child(1),
        .videos-table td:nth-child(1) {
            width: 70px;
        }

        .videos-table th:nth-child(4),
        .videos-table td:nth-child(4) {
            width: 90px;
        }

        .videos-table th:nth-child(5),
        .videos-table td:nth-child(5) {
            width: 240px;
        }

        .videos-table-wrapper {
            max-height: 410px;
            overflow-y: auto;
            overflow-x: auto;
            padding-right: 12px;
            box-sizing: border-box;
        }

        .video-actions-cell {
            white-space: nowrap;
            width: 1%;
            text-align: right;
        }

        .video-actions-group {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        @media (max-width: 576px) {
            .video-actions-group {
                flex-direction: column;
            }

            .videos-table th:nth-child(5),
            .videos-table td:nth-child(5) {
                width: 100px;
            }
        }
    </style>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'conteudos', 'Gerenciar Vídeos', 'fa-video'); ?>

        <p class="text-muted mb-4">Envio, edicao e exclusao simples de conteudos</p>

        <div class="admin-card">

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);">
                        <small class="text-muted d-block">Vídeos cadastrados</small>
                        <strong class="fs-4"><?php echo (int) $total_conteudos; ?></strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);">
                        <small class="text-muted d-block">Modo do banco</small>
                        <strong class="fs-4"><?php echo banco_eh_robusto() ? 'Robusto' : 'Simples'; ?></strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);">
                        <small class="text-muted d-block">Arquivos enviados</small>
                        <strong class="fs-4">Cloudflare R2</strong>
                    </div>
                </div>
            </div>

            <!-- Diagnostic removed from default view; shown only after successful upload -->

            <?php if ($mensagem !== "") { ?>
                <div class="alert alert-success video-upload-success"><?php echo htmlspecialchars($mensagem); ?></div>
            <?php } ?>

            <?php if ($erro !== "") { ?>
                <div class="alert alert-danger video-upload-error"><?php echo htmlspecialchars($erro); ?></div>
            <?php } ?>

            <form method="POST" id="formUploadVideo" class="mb-5">
            <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="salvar_video">
                <input type="hidden" name="video_url" id="campoVideoUrl">
                <input type="hidden" name="video_nome" id="campoVideoNome">
                <input type="hidden" name="video_mime" id="campoVideoMime">
                <input type="hidden" name="video_tamanho" id="campoVideoTamanho">
                <input type="hidden" name="video_duracao" id="campoVideoDuracao">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Titulo</label>
                        <input type="text" name="IDCT_TITULO" class="form-control custom-input" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Curso</label>
                        <select name="IDC_ID" class="form-control custom-input" required>
                            <option value="">Selecione</option>
                            <?php foreach ($cursos as $curso) { ?>
                                <option value="<?php echo (int) $curso["IDC_ID"]; ?>"><?php echo htmlspecialchars($curso["IDC_TITULO"]); ?><?php echo $curso["IDC_STATUS"] !== 'PUBLICADO' ? ' — ' . htmlspecialchars($curso["IDC_STATUS"]) . ' (não aparece p/ o fiel)' : ''; ?></option>
                            <?php } ?>
                        </select>
                        <small class="text-muted">Cursos marcados como Rascunho/Arquivado não aparecem para o fiel até serem publicados em <a href="admin_cursos.php">Cursos</a>.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Modulo (opcional)</label>
                        <input type="text" name="IDM_ID" class="form-control custom-input" placeholder="ID do modulo" inputmode="numeric" pattern="[0-9]*" title="Use apenas numeros" data-so-digitos>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ordem</label>
                        <input type="number" name="IDCT_ORDEM" class="form-control custom-input" value="1" min="1" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descricao</label>
                        <textarea name="IDCT_DESCRICAO" class="form-control custom-input" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Arquivo de video</label>
                        <input type="file" id="campoArquivoVideo" class="form-control custom-input" accept="video/mp4,video/webm,video/ogg,video/quicktime" required>
                        <small class="text-muted d-block mt-1"><i class="fas fa-circle-info me-1"></i>Prefira <strong>MP4</strong>: toca em todos os aparelhos e na TV (Chromecast e AirPlay). OGG não pode ser transmitido para a TV.</small>
                        <small class="text-muted d-block mt-1" id="uploadStatus">O video vai direto do seu navegador para o Cloudflare R2, sem passar pelo servidor.</small>
                        <div class="progress mt-2" style="height: 6px; display:none;" id="uploadProgressWrap">
                            <div class="progress-bar" id="uploadProgressBar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-light fw-bold" id="btnEnviarVideo" style="color:#000000 !important; background:#ffffff !important;">Enviar video</button>
                    </div>
                </div>
            </form>

            <script nonce="<?php echo site_csp_nonce(); ?>">
                (function () {
                    var form = document.getElementById('formUploadVideo');
                    var inputArquivo = document.getElementById('campoArquivoVideo');
                    var status = document.getElementById('uploadStatus');
                    var btn = document.getElementById('btnEnviarVideo');
                    var progressWrap = document.getElementById('uploadProgressWrap');
                    var progressBar = document.getElementById('uploadProgressBar');

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

                    // PUT direto na URL pre-assinada do R2. Com XMLHttpRequest (e nao
                    // fetch) a barra mostra o progresso real do envio.
                    // O Content-Type vem do servidor (r2_upload_url.php): ele faz
                    // parte da assinatura, e o R2 recusa (403) se for outro.
                    function enviarParaR2(uploadUrl, file, contentType) {
                        return new Promise(function (resolve, reject) {
                            var xhr = new XMLHttpRequest();
                            xhr.open('PUT', uploadUrl);
                            xhr.setRequestHeader('Content-Type', contentType);
                            xhr.upload.onprogress = function (ev) {
                                if (ev.lengthComputable) {
                                    progressBar.style.width = Math.max(5, Math.round((ev.loaded / ev.total) * 95)) + '%';
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
                            xhr.send(file);
                        });
                    }

                    form.addEventListener('submit', async function (e) {
                        var urlJaPreenchida = document.getElementById('campoVideoUrl').value;

                        // Se o upload ja terminou (usuario clicou de novo apos erro), deixa o form seguir.
                        if (jaEnviado && urlJaPreenchida) {
                            return;
                        }

                        e.preventDefault();

                        var file = inputArquivo.files[0];
                        if (!file) {
                            (window.siteAvisar || window.alert)('Selecione um arquivo de video.', 'erro');
                            return;
                        }

                        var campoCurso = form.querySelector('[name="IDC_ID"]');
                        if (campoCurso && campoCurso.value === '') {
                            // form.submit() (mais abaixo) ignora a validacao nativa do
                            // "required" do select, entao sem essa checagem o upload
                            // pro R2 rodava inteiro pra so depois o PHP
                            // rejeitar por falta de curso — desperdicando banda e tempo.
                            (window.siteAvisar || window.alert)('Selecione um curso antes de enviar o video. Se nenhum curso aparecer na lista, crie um curso primeiro em Cursos.', 'erro');
                            return;
                        }

                        btn.disabled = true;
                        status.classList.remove('text-danger');
                        status.textContent = 'Preparando upload...';
                        progressWrap.style.display = 'block';
                        progressBar.style.width = '5%';

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

                            status.textContent = 'Enviando video para o Cloudflare R2...';
                            await enviarParaR2(assinatura.uploadUrl, file, assinatura.contentType);

                            // Bucket é privado: o que guardamos é o caminho dentro do bucket,
                            // não uma URL pública. O PHP gera uma URL assinada na hora de exibir.
                            document.getElementById('campoVideoUrl').value = assinatura.path;
                            document.getElementById('campoVideoNome').value = file.name;
                            document.getElementById('campoVideoMime').value = assinatura.contentType;
                            document.getElementById('campoVideoTamanho').value = file.size;
                            document.getElementById('campoVideoDuracao').value = await lerDuracaoVideo(file);

                            progressBar.style.width = '100%';
                            status.textContent = 'Upload concluído. Salvando a aula...';
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

            <h2 class="h4 mb-3" id="lista-videos">Vídeos enviados <small class="text-muted fs-6">(<?php echo (int) $total_conteudos; ?>)</small></h2>
            <p class="small text-muted">
                Para enviar vários vídeos de uma vez, mudar a ordem arrastando ou publicar aulas em rascunho, use a
                <a href="admin_grade_curso.php<?php echo $filtro_curso > 0 ? '?id=' . $filtro_curso : ''; ?>"><i class="fas fa-list-ol me-1"></i>grade do curso</a>.
            </p>
            <form method="GET" action="admin_conteudos.php#lista-videos" class="row g-2 mb-3">
                <div class="col-sm-5">
                    <select name="curso" class="form-select" aria-label="Filtrar por curso" data-enviar-ao-mudar>
                        <option value="">Todos os cursos</option>
                        <?php foreach ($cursos as $curso_filtro) { ?>
                            <option value="<?php echo (int) $curso_filtro['IDC_ID']; ?>"<?php echo $filtro_curso === (int) $curso_filtro['IDC_ID'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($curso_filtro['IDC_TITULO']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-sm-5">
                    <input type="search" name="q" class="form-control" placeholder="Buscar pelo título" value="<?php echo htmlspecialchars($busca); ?>" aria-label="Buscar aula pelo título">
                </div>
                <div class="col-sm-2 d-grid">
                    <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search me-1"></i>Filtrar</button>
                </div>
            </form>
            <div class="table-responsive videos-table-wrapper">
                <table class="table table-striped align-middle videos-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Curso</th>
                            <th>Ordem</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($conteudos) > 0) { ?>
                            <?php foreach ($conteudos as $conteudo) { ?>
                                <tr>
                                    <td class="video-column-blue"><?php echo (int) $conteudo["IDCT_ID"]; ?></td>
                                    <td class="video-column-blue" title="<?php echo htmlspecialchars($conteudo["IDCT_TITULO"]); ?>"><?php echo htmlspecialchars($conteudo["IDCT_TITULO"]); ?></td>
                                    <td class="small"><?php echo htmlspecialchars((string) ($conteudo["IDC_TITULO"] ?? '—')); ?></td>
                                    <td><?php echo (int) $conteudo["IDCT_ORDEM"]; ?></td>
                                    <td class="video-actions-cell">
                                        <div class="video-actions-group">
                                            <?php if (trim((string) $conteudo['IDCT_URL']) !== '') { ?>
                                                <a href="admin_conteudos.php?ver=<?php echo (int) $conteudo["IDCT_ID"]; ?>" class="btn btn-sm btn-info" target="_blank" rel="noopener">Ver</a>
                                            <?php } else { ?>
                                                <span class="btn btn-sm btn-secondary disabled">Indisponível</span>
                                            <?php } ?>
                                            <a href="admin_editar_conteudo.php?id=<?php echo (int) $conteudo["IDCT_ID"]; ?>" class="btn btn-sm btn-warning">Editar</a>
                                            <form method="POST" style="display:inline;" data-confirmar="Deseja excluir este vídeo?">
                                            <?php echo csrf_campo(); ?>
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="IDCT_ID" value="<?php echo (int) $conteudo["IDCT_ID"]; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="5"><?php echo ($filtro_curso || $busca !== '') ? 'Nenhum vídeo com esse filtro.' : 'Nenhum vídeo cadastrado.'; ?></td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php if ($paginas > 1) { ?>
                <nav class="d-flex justify-content-between align-items-center mt-2" aria-label="Páginas">
                    <small class="text-muted">Página <?php echo (int) $pagina; ?> de <?php echo (int) $paginas; ?></small>
                    <div class="btn-group">
                        <a class="btn btn-outline-secondary btn-sm<?php echo $pagina <= 1 ? ' disabled' : ''; ?>" href="<?php echo htmlspecialchars($link_lista($pagina - 1)); ?>">Anterior</a>
                        <a class="btn btn-outline-secondary btn-sm<?php echo $pagina >= $paginas ? ' disabled' : ''; ?>" href="<?php echo htmlspecialchars($link_lista($pagina + 1)); ?>">Próxima</a>
                    </div>
                </nav>
            <?php } ?>

            <h2 class="h5 mt-4 mb-2">Arquivos esquecidos</h2>
            <p class="text-muted small mb-2">Vídeos enviados para o armazenamento sem que a aula tenha sido salva continuam ocupando espaço (e custando). Só entram os que nenhuma aula usa e que têm mais de 24 horas.</p>
            <?php if ($orfaos_previa !== null) { ?>
                <div class="alert alert-warning" role="status">
                    <?php echo (int) $orfaos_previa['total']; ?> arquivo(s) esquecido(s), <?php echo htmlspecialchars(number_format($orfaos_previa['bytes'] / 1048576, 1, ',', '.')); ?> MB no total.
                    <form method="POST" class="d-inline ms-2" data-confirmar="Apagar esses arquivos do armazenamento? Não dá para desfazer.">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="orfaos_apagar">
                        <button type="submit" class="btn btn-sm btn-danger">Apagar agora</button>
                    </form>
                </div>
            <?php } ?>
            <form method="POST">
                <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="orfaos_previa">
                <button type="submit" class="btn btn-sm btn-outline-light"><i class="fas fa-broom me-1"></i>Verificar arquivos esquecidos</button>
            </form>
        </div>
    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
