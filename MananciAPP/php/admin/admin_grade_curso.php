<?php
// Grade do curso: todas as aulas de um curso numa tela só.
// - Reordenar arrastando (computador) ou com ↑ ↓ (celular/teclado); salva sozinho.
// - Publicar ou esconder cada aula (IDCT_PUBLICADO; escondida, o fiel não vê).
// - Enviar vários vídeos de uma vez: cada arquivo sobe direto para o R2 (como
//   em Gerenciar Vídeos) e vira uma aula em RASCUNHO no fim da lista, com o
//   nome do arquivo como título — o admin confere, renomeia e publica.
// Mesma permissão de Gerenciar Vídeos (chave "conteudos" em admin_telas()).
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));

$curso_id = (int) ($_GET['id'] ?? $_POST['curso_id'] ?? 0);
$res_curso = db_query($conn, "SELECT IDC_ID, IDC_TITULO, IDC_STATUS FROM ID_CURSO WHERE IDC_ID = ? LIMIT 1", array($curso_id));
$curso = $res_curso ? mysqli_fetch_assoc($res_curso) : null;

// ---- Ações (fetch, resposta em JSON) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $responder = function ($dados, $codigo = 200) {
        http_response_code($codigo);
        echo json_encode($dados);
        exit;
    };
    if (!csrf_validar()) {
        $responder(array('ok' => false, 'erro' => 'Sessão expirada. Atualize a página.'), 403);
    }
    if (!$curso) {
        $responder(array('ok' => false, 'erro' => 'Curso não encontrado.'), 404);
    }
    $acao = site_entrada_texto($_POST['acao'] ?? '');

    if ($acao === 'ordem') {
        // Só as aulas deste curso; a posição na lista vira a ordem (1, 2, 3…).
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? array())))));
        if (!$ids || count($ids) > 500) {
            $responder(array('ok' => false, 'erro' => 'Lista de aulas inválida.'), 400);
        }
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $conn->beginTransaction();
        $ok = true;
        foreach ($ids as $posicao => $id) {
            if (db_query($conn, "UPDATE ID_CONTENT SET IDCT_ORDEM = ? WHERE IDCT_ID = ? AND IDC_ID = ?", array($posicao + 1, $id, $curso_id)) === false) {
                $ok = false;
                break;
            }
        }
        if (!$ok) {
            $conn->rollBack();
            $responder(array('ok' => false, 'erro' => 'Não foi possível salvar a ordem.'), 500);
        }
        if (!db_commit($conn)) {
            $responder(array('ok' => false, 'erro' => 'Não foi possível salvar a ordem.'), 500);
        }
        admin_log($conn, 'aula.ordem', 'curso', $curso_id, array('aulas' => count($ids)));
        $responder(array('ok' => true));
    }

    if ($acao === 'publicar') {
        $id = (int) ($_POST['aula_id'] ?? 0);
        $publicado = !empty($_POST['publicado']) ? 1 : 0;
        $res = db_query($conn, "UPDATE ID_CONTENT SET IDCT_PUBLICADO = ? WHERE IDCT_ID = ? AND IDC_ID = ?", array($publicado, $id, $curso_id));
        if ($res === false || mysqli_affected_rows($conn) < 1) {
            $responder(array('ok' => false, 'erro' => 'Aula não encontrada neste curso.'), 404);
        }
        site_cache_apagar('catalogo_publico');
        admin_log($conn, 'aula.publicar', 'aula', $id, array('publicada' => (bool) $publicado));
        $responder(array('ok' => true, 'publicado' => $publicado));
    }

    if ($acao === 'criar_aula') {
        // Chamado pelo envio em lote depois que o arquivo já está no R2.
        $video_url = site_entrada_texto($_POST['video_url'] ?? '');
        $titulo = mb_substr(site_entrada_texto($_POST['titulo'] ?? ''), 0, 150);
        if (!admin_video_url_valida($video_url)) {
            $responder(array('ok' => false, 'erro' => 'Caminho do vídeo inválido. Envie o arquivo de novo.'), 400);
        }
        if ($titulo === '') {
            $titulo = 'Aula nova';
        }
        $res_max = db_query($conn, "SELECT COALESCE(MAX(IDCT_ORDEM), 0) AS maior FROM ID_CONTENT WHERE IDC_ID = ?", array($curso_id));
        $ordem = 1 + (int) (($res_max ? mysqli_fetch_assoc($res_max) : array())['maior'] ?? 0);
        $duracao_sql = admin_video_duracao_sql($_POST['video_duracao'] ?? 0);
        $res = db_query($conn, "
            INSERT INTO ID_CONTENT (IDC_ID, IDCT_TIPO, IDCT_TITULO, IDCT_URL, IDCT_ORDEM, IDCT_PUBLICADO, IDCT_ARQUIVO_NOME, IDCT_MIME_TYPE, IDCT_TAMANHO_BYTES, IDCT_DURACAO_SEGUNDOS)
            VALUES (?, 'VIDEO', ?, ?, ?, 0, ?, ?, ?, $duracao_sql)
            RETURNING IDCT_ID, IDCT_DURACAO_SEGUNDOS
        ", array(
            $curso_id, $titulo, $video_url, $ordem,
            mb_substr(site_entrada_texto($_POST['video_nome'] ?? ''), 0, 255),
            mb_substr(site_entrada_texto($_POST['video_mime'] ?? ''), 0, 100),
            max(0, (int) ($_POST['video_tamanho'] ?? 0)),
        ));
        $nova = $res ? mysqli_fetch_assoc($res) : null;
        if (!$nova) {
            // A aula não foi salva: o vídeo que já subiu ficaria órfão no R2.
            site_excluir_arquivo_r2_orfao($video_url);
            $responder(array('ok' => false, 'erro' => site_erro_banco('Não foi possível salvar a aula.')), 500);
        }
        admin_log($conn, 'aula.lote', 'aula', (int) $nova['IDCT_ID'], array('titulo' => $titulo, 'curso' => $curso_id));
        $responder(array('ok' => true, 'aula' => array(
            'id' => (int) $nova['IDCT_ID'],
            'titulo' => $titulo,
            'duracao' => (int) ($nova['IDCT_DURACAO_SEGUNDOS'] ?? 0),
            'ordem' => $ordem,
        )));
    }

    $responder(array('ok' => false, 'erro' => 'Ação inválida.'), 400);
}

$aulas = array();
if ($curso) {
    $res = db_query($conn, "
        SELECT IDCT_ID, IDCT_TITULO, IDCT_ORDEM, IDCT_PUBLICADO, IDCT_DURACAO_SEGUNDOS, IDCT_URL
        FROM ID_CONTENT
        WHERE IDC_ID = ? AND LOWER(IDCT_TIPO) = 'video'
        ORDER BY IDCT_ORDEM ASC NULLS LAST, IDCT_ID ASC
    ", array($curso_id));
    while ($res && ($linha = mysqli_fetch_assoc($res))) {
        $aulas[] = $linha;
    }
}
$cursos = array();
$res_cursos = mysqli_query($conn, "SELECT IDC_ID, IDC_TITULO FROM ID_CURSO ORDER BY IDC_TITULO");
while ($res_cursos && ($linha = mysqli_fetch_assoc($res_cursos))) {
    $cursos[] = $linha;
}
$duracao_texto = function ($segundos) {
    $segundos = (int) $segundos;
    return $segundos > 0 ? gmdate($segundos >= 3600 ? 'G:i:s' : 'i:s', $segundos) : 'sem duração';
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Grade do curso</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
    <style>
        .grade-lista { list-style: none; padding: 0; margin: 0; }
        .grade-item { display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 0.7rem; border: 1px solid var(--border-color); border-radius: 10px; margin-bottom: 0.45rem; background: var(--bg-light); }
        .grade-item.is-rascunho { border-style: dashed; opacity: 0.85; }
        .grade-item.is-arrastando { opacity: 0.4; }
        .grade-item.is-alvo { box-shadow: 0 -3px 0 var(--primary-light) inset; }
        .grade-alca { cursor: grab; color: var(--text-muted); padding: 0.3rem; touch-action: none; }
        .grade-numero { min-width: 2rem; text-align: right; font-variant-numeric: tabular-nums; color: var(--text-muted); font-weight: 600; }
        .grade-titulo { flex: 1; min-width: 0; overflow-wrap: anywhere; }
        .grade-titulo small { display: block; color: var(--text-muted); }
        .grade-acoes { display: flex; gap: 0.3rem; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        .grade-acoes .btn { min-width: 36px; min-height: 36px; }
        .grade-envio-arquivo { display: flex; gap: 0.6rem; align-items: center; font-size: 0.88rem; padding: 0.35rem 0; }
        .grade-envio-arquivo .progress { flex: 1; height: 6px; min-width: 80px; }
        .grade-salvo { font-size: 0.85rem; color: var(--text-muted); min-height: 1.2em; }
        @media (max-width: 576px) { .grade-alca { display: none; } }
    </style>
</head>
<body class="admin-layout admin-shell">
    <?php admin_layout_abrir($conn, 'conteudos', 'Grade do curso', 'fa-list-ol'); ?>

    <form method="GET" class="d-flex gap-2 flex-wrap align-items-center mb-3">
        <label for="escolherCurso" class="form-label mb-0">Curso</label>
        <select id="escolherCurso" name="id" class="form-select" style="max-width: 420px;" data-enviar-ao-mudar>
            <option value="">Escolha um curso…</option>
            <?php foreach ($cursos as $c) { ?>
                <option value="<?php echo (int) $c['IDC_ID']; ?>"<?php echo (int) $c['IDC_ID'] === $curso_id ? ' selected' : ''; ?>><?php echo htmlspecialchars($c['IDC_TITULO']); ?></option>
            <?php } ?>
        </select>
        <noscript><button class="btn btn-primary btn-sm">Abrir</button></noscript>
    </form>

    <?php if (!$curso) { ?>
        <div class="card admin-card"><div class="card-body text-muted">Escolha um curso para ver e organizar as aulas.</div></div>
    <?php } else { ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1"><?php echo htmlspecialchars($curso['IDC_TITULO']); ?></h2>
                <small class="text-muted">
                    Curso <?php echo $curso['IDC_STATUS'] === 'PUBLICADO' ? 'publicado' : 'em ' . htmlspecialchars(strtolower((string) $curso['IDC_STATUS'])); ?>
                    · <?php echo count($aulas); ?> aula(s)
                </small>
            </div>
            <div class="d-flex gap-2">
                <a href="../fiel/curso.php?id=<?php echo $curso_id; ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye me-1"></i>Ver como fiel</a>
                <a href="admin_editar_curso.php?id=<?php echo $curso_id; ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i>Editar curso</a>
            </div>
        </div>

        <div class="card admin-card mb-4">
            <div class="card-body">
                <h3 class="h6"><i class="fas fa-cloud-arrow-up me-2"></i>Enviar vários vídeos</h3>
                <p class="text-muted small mb-2">Cada vídeo vira uma aula <strong>em rascunho</strong> no fim da lista, com o nome do arquivo como título. Os fiéis só veem depois que você publicar. Não feche a página enquanto envia.</p>
                <input type="file" id="arquivosLote" class="form-control" accept="video/mp4,video/webm,video/ogg,video/quicktime,.mp4,.webm,.ogg,.mov" multiple aria-label="Escolher vídeos">
                <div id="filaEnvio" class="mt-2" aria-live="polite"></div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <h3 class="h6 mb-0">Aulas, na ordem em que o fiel vê</h3>
            <div class="d-flex gap-2 align-items-center">
                <span class="grade-salvo" id="gradeSalvo" role="status"></span>
                <button type="button" class="btn btn-outline-success btn-sm" id="publicarRascunhos"><i class="fas fa-check-double me-1"></i>Publicar todas em rascunho</button>
            </div>
        </div>
        <p class="text-muted small">Arraste pela alça <i class="fas fa-grip-vertical"></i> ou use as setas. A ordem salva sozinha.</p>

        <?php echo csrf_campo(); ?>
        <ol class="grade-lista" id="gradeLista">
            <?php foreach ($aulas as $i => $aula) { $pub = (int) $aula['IDCT_PUBLICADO'] === 1; ?>
                <li class="grade-item<?php echo $pub ? '' : ' is-rascunho'; ?>" draggable="true" data-id="<?php echo (int) $aula['IDCT_ID']; ?>">
                    <span class="grade-alca" aria-hidden="true"><i class="fas fa-grip-vertical"></i></span>
                    <span class="grade-numero"><?php echo $i + 1; ?></span>
                    <span class="grade-titulo">
                        <?php echo htmlspecialchars((string) $aula['IDCT_TITULO']); ?>
                        <small><?php echo htmlspecialchars($duracao_texto($aula['IDCT_DURACAO_SEGUNDOS'])); ?><span class="grade-estado"><?php echo $pub ? '' : ' · rascunho (o fiel não vê)'; ?></span></small>
                        <?php if ((int) $aula['IDCT_DURACAO_SEGUNDOS'] <= 0) { ?>
                            <!-- Sem duração o fiel não conclui a aula (nem ganha o
                                 certificado): o player grava a duração quando
                                 alguém da equipe abre a aula uma vez. -->
                            <a class="badge bg-warning text-dark text-decoration-none mt-1" href="../fiel/assistir_video.php?id=<?php echo (int) $aula['IDCT_ID']; ?>" target="_blank" rel="noopener">
                                <i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>Sem duração: abra a aula uma vez para gravar, senão o fiel não conclui
                            </a>
                        <?php } ?>
                    </span>
                    <span class="grade-acoes">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-mover="-1" aria-label="Subir"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-mover="1" aria-label="Descer"><i class="fas fa-arrow-down"></i></button>
                        <div class="form-check form-switch mb-0 ms-1" title="Publicada para os fiéis">
                            <input class="form-check-input" type="checkbox" role="switch" data-publicar<?php echo $pub ? ' checked' : ''; ?> aria-label="Publicada para os fiéis">
                        </div>
                        <a href="admin_editar_conteudo.php?id=<?php echo (int) $aula['IDCT_ID']; ?>" class="btn btn-outline-warning btn-sm" aria-label="Editar"><i class="fas fa-pen"></i></a>
                    </span>
                </li>
            <?php } ?>
        </ol>
        <?php if (!$aulas) { ?>
            <p class="text-muted" id="gradeVazia">Este curso ainda não tem aulas. Envie os vídeos acima.</p>
        <?php } ?>
    <?php } ?>

    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <?php if ($curso) { ?>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        (function () {
            var CURSO = <?php echo (int) $curso_id; ?>;
            var lista = document.getElementById('gradeLista');
            var salvo = document.getElementById('gradeSalvo');
            var csrf = (document.querySelector('input[name="csrf_token"]') || {}).value || '';

            function post(dados) {
                var corpo = new URLSearchParams();
                corpo.append('csrf_token', csrf);
                corpo.append('curso_id', String(CURSO));
                Object.keys(dados).forEach(function (k) {
                    [].concat(dados[k]).forEach(function (v) { corpo.append(Array.isArray(dados[k]) ? k + '[]' : k, String(v)); });
                });
                return fetch('admin_grade_curso.php', { method: 'POST', body: corpo, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); });
            }
            function aviso(texto, erro) {
                salvo.textContent = texto;
                salvo.classList.toggle('text-danger', !!erro);
            }
            function renumerar() {
                lista.querySelectorAll('.grade-item').forEach(function (li, i) { li.querySelector('.grade-numero').textContent = i + 1; });
            }

            // ---- Ordem: salva um pouco depois da última mudança ----
            var esperaOrdem = null;
            function salvarOrdem() {
                renumerar();
                aviso('Salvando…');
                clearTimeout(esperaOrdem);
                esperaOrdem = setTimeout(function () {
                    var ids = Array.prototype.map.call(lista.querySelectorAll('.grade-item'), function (li) { return li.getAttribute('data-id'); });
                    post({ acao: 'ordem', ids: ids })
                        .then(function (d) { aviso(d.ok ? 'Ordem salva.' : (d.erro || 'Não foi possível salvar.'), !d.ok); })
                        .catch(function () { aviso('Sem conexão: a ordem não foi salva.', true); });
                }, 600);
            }

            lista.addEventListener('click', function (e) {
                var botao = e.target.closest('[data-mover]');
                if (!botao) return;
                var li = botao.closest('.grade-item');
                var passo = parseInt(botao.getAttribute('data-mover'), 10);
                var vizinho = passo < 0 ? li.previousElementSibling : li.nextElementSibling;
                if (!vizinho) return;
                lista.insertBefore(li, passo < 0 ? vizinho : vizinho.nextElementSibling);
                botao.focus();
                salvarOrdem();
            });

            // Arrastar (computador). No celular as setas fazem o mesmo.
            var arrastando = null;
            lista.addEventListener('dragstart', function (e) {
                arrastando = e.target.closest('.grade-item');
                if (!arrastando) return;
                arrastando.classList.add('is-arrastando');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', arrastando.getAttribute('data-id'));
            });
            lista.addEventListener('dragover', function (e) {
                if (!arrastando) return;
                e.preventDefault();
                var alvo = e.target.closest('.grade-item');
                lista.querySelectorAll('.is-alvo').forEach(function (el) { el.classList.remove('is-alvo'); });
                if (alvo && alvo !== arrastando) alvo.classList.add('is-alvo');
            });
            lista.addEventListener('drop', function (e) {
                if (!arrastando) return;
                e.preventDefault();
                var alvo = e.target.closest('.grade-item');
                if (alvo && alvo !== arrastando) {
                    var caixa = alvo.getBoundingClientRect();
                    var depois = e.clientY > caixa.top + caixa.height / 2;
                    lista.insertBefore(arrastando, depois ? alvo.nextElementSibling : alvo);
                    salvarOrdem();
                }
            });
            lista.addEventListener('dragend', function () {
                if (arrastando) arrastando.classList.remove('is-arrastando');
                lista.querySelectorAll('.is-alvo').forEach(function (el) { el.classList.remove('is-alvo'); });
                arrastando = null;
            });

            // ---- Publicar / esconder ----
            function marcarPublicada(li, publicada) {
                li.classList.toggle('is-rascunho', !publicada);
                li.querySelector('.grade-estado').textContent = publicada ? '' : ' · rascunho (o fiel não vê)';
                li.querySelector('[data-publicar]').checked = publicada;
            }
            lista.addEventListener('change', function (e) {
                var chave = e.target.closest('[data-publicar]');
                if (!chave) return;
                var li = chave.closest('.grade-item');
                var quer = chave.checked;
                post({ acao: 'publicar', aula_id: li.getAttribute('data-id'), publicado: quer ? 1 : 0 })
                    .then(function (d) {
                        if (!d.ok) throw new Error(d.erro);
                        marcarPublicada(li, quer);
                        aviso(quer ? 'Aula publicada.' : 'Aula escondida dos fiéis.');
                    })
                    .catch(function (err) { chave.checked = !quer; aviso((err && err.message) || 'Não foi possível mudar.', true); });
            });
            document.getElementById('publicarRascunhos').addEventListener('click', function () {
                var rascunhos = lista.querySelectorAll('.grade-item.is-rascunho');
                if (!rascunhos.length) { aviso('Não há aulas em rascunho.'); return; }
                if (!window.confirm('Publicar ' + rascunhos.length + ' aula(s) em rascunho para os fiéis?')) return;
                var fila = Promise.resolve();
                rascunhos.forEach(function (li) {
                    fila = fila.then(function () {
                        return post({ acao: 'publicar', aula_id: li.getAttribute('data-id'), publicado: 1 })
                            .then(function (d) { if (d.ok) marcarPublicada(li, true); });
                    });
                });
                fila.then(function () { aviso('Aulas publicadas.'); }).catch(function () { aviso('Algumas aulas não foram publicadas. Tente de novo.', true); });
            });

            // ---- Envio em lote ----
            var inputLote = document.getElementById('arquivosLote');
            var filaEl = document.getElementById('filaEnvio');
            var enviando = 0;
            window.addEventListener('beforeunload', function (e) {
                if (enviando > 0) { e.preventDefault(); e.returnValue = ''; }
            });
            function lerDuracao(file) {
                return new Promise(function (resolve) {
                    var v = document.createElement('video');
                    var url = URL.createObjectURL(file);
                    var fim = function (s) { URL.revokeObjectURL(url); resolve(s); };
                    var espera = setTimeout(function () { fim(0); }, 8000);
                    v.preload = 'metadata';
                    v.onloadedmetadata = function () { clearTimeout(espera); fim(isFinite(v.duration) ? Math.round(v.duration) : 0); };
                    v.onerror = function () { clearTimeout(espera); fim(0); };
                    v.src = url;
                });
            }
            function tituloDoArquivo(nome) {
                return nome.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 150);
            }
            function linhaDaFila(file) {
                var div = document.createElement('div');
                div.className = 'grade-envio-arquivo';
                var nome = document.createElement('span');
                nome.textContent = file.name;
                nome.style.maxWidth = '40%';
                nome.style.overflowWrap = 'anywhere';
                var barra = document.createElement('div');
                barra.className = 'progress';
                barra.innerHTML = '<div class="progress-bar" style="width:0%"></div>';
                var estado = document.createElement('small');
                estado.textContent = 'na fila';
                div.appendChild(nome); div.appendChild(barra); div.appendChild(estado);
                filaEl.appendChild(div);
                return { barra: barra.firstChild, estado: estado };
            }
            function putR2(url, file, tipo, barra) {
                return new Promise(function (resolve, reject) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('PUT', url);
                    xhr.setRequestHeader('Content-Type', tipo);
                    xhr.upload.onprogress = function (ev) {
                        if (ev.lengthComputable) barra.style.width = Math.round((ev.loaded / ev.total) * 100) + '%';
                    };
                    xhr.onload = function () { xhr.status >= 200 && xhr.status < 300 ? resolve() : reject(new Error('falha no envio (HTTP ' + xhr.status + ')')); };
                    xhr.onerror = function () { reject(new Error('falha de rede no envio')); };
                    xhr.send(file);
                });
            }
            function adicionarNaLista(aula) {
                var vazia = document.getElementById('gradeVazia');
                if (vazia) vazia.remove();
                var li = document.createElement('li');
                li.className = 'grade-item is-rascunho';
                li.draggable = true;
                li.setAttribute('data-id', String(aula.id));
                li.innerHTML = '<span class="grade-alca" aria-hidden="true"><i class="fas fa-grip-vertical"></i></span>'
                    + '<span class="grade-numero"></span>'
                    + '<span class="grade-titulo"><span class="grade-nome"></span><small><span class="grade-dur"></span><span class="grade-estado"> · rascunho (o fiel não vê)</span></small></span>'
                    + '<span class="grade-acoes">'
                    + '<button type="button" class="btn btn-outline-secondary btn-sm" data-mover="-1" aria-label="Subir"><i class="fas fa-arrow-up"></i></button>'
                    + '<button type="button" class="btn btn-outline-secondary btn-sm" data-mover="1" aria-label="Descer"><i class="fas fa-arrow-down"></i></button>'
                    + '<div class="form-check form-switch mb-0 ms-1" title="Publicada para os fiéis"><input class="form-check-input" type="checkbox" role="switch" data-publicar aria-label="Publicada para os fiéis"></div>'
                    + '<a class="btn btn-outline-warning btn-sm" aria-label="Editar"><i class="fas fa-pen"></i></a>'
                    + '</span>';
                li.querySelector('.grade-nome').textContent = aula.titulo;
                var s = aula.duracao;
                li.querySelector('.grade-dur').textContent = s > 0 ? (Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0')) : 'sem duração';
                li.querySelector('a').href = 'admin_editar_conteudo.php?id=' + aula.id;
                lista.appendChild(li);
                renumerar();
            }
            async function enviarUm(file, linha) {
                linha.estado.textContent = 'preparando…';
                var corpo = new URLSearchParams({ nome: file.name, tamanho: String(file.size), csrf_token: csrf });
                var assinatura = await fetch('r2_upload_url.php', { method: 'POST', body: corpo, credentials: 'same-origin' }).then(function (r) { return r.json(); });
                if (!assinatura.ok) throw new Error(assinatura.erro || 'não foi possível preparar o envio');
                linha.estado.textContent = 'enviando…';
                await putR2(assinatura.uploadUrl, file, assinatura.contentType, linha.barra);
                linha.estado.textContent = 'salvando a aula…';
                var duracao = await lerDuracao(file);
                var d = await post({
                    acao: 'criar_aula', titulo: tituloDoArquivo(file.name), video_url: assinatura.path,
                    video_nome: file.name, video_mime: assinatura.contentType, video_tamanho: file.size, video_duracao: duracao
                });
                if (!d.ok) throw new Error(d.erro || 'não foi possível salvar a aula');
                adicionarNaLista(d.aula);
                linha.estado.textContent = 'pronto ✓';
            }
            inputLote.addEventListener('change', async function () {
                var arquivos = Array.prototype.slice.call(inputLote.files || []);
                inputLote.value = '';
                var linhas = arquivos.map(linhaDaFila);
                enviando += arquivos.length;
                // Um de cada vez: vários envios grandes ao mesmo tempo dividem a
                // internet e todos ficam lentos (e um erro não derruba os outros).
                for (var i = 0; i < arquivos.length; i++) {
                    try {
                        await enviarUm(arquivos[i], linhas[i]);
                    } catch (err) {
                        linhas[i].estado.textContent = 'erro: ' + ((err && err.message) || 'tente de novo');
                        linhas[i].estado.classList.add('text-danger');
                    }
                    enviando--;
                }
            });
        })();
    </script>
    <?php } ?>
</body>
</html>
