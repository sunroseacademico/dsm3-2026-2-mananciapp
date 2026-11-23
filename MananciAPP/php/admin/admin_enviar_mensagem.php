<?php
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));

$mensagem = "";
$erro = "";
$previa = null;
$admin_id_logado = isset($_SESSION["Usuario_id"]) ? (int) $_SESSION["Usuario_id"] : 0;

// Busca de pessoas para "Pessoas específicas" (?buscar=texto, JSON): antes a
// tela carregava todos os fiéis aprovados de uma vez numa lista de
// caixinhas — com milhares de membros, pesada e impossível de usar.
if (isset($_GET['buscar'])) {
    header('Content-Type: application/json; charset=utf-8');
    $termo = mb_strtolower(site_entrada_texto($_GET['buscar']));
    $pessoas = array();
    if (mb_strlen($termo) >= 2) {
        $like = '%' . site_like_escapar($termo) . '%';
        // Contas da equipe interna só aparecem para quem também é dela
        // (mesma regra de Gerenciar Usuários e Ver Progresso).
        $sem_super = '';
        $params_busca = array($like, $like);
        if (superadmin_emails() && !auth_eh_superadmin()) {
            $sem_super = 'AND LOWER(IDF_EMAIL) NOT IN (' . implode(', ', array_fill(0, count(superadmin_emails()), '?')) . ')';
            $params_busca = array_merge($params_busca, superadmin_emails());
        }
        $res = db_query($conn, "
            SELECT IDF_ID, IDF_NOME, IDF_EMAIL FROM ID_FIEL
            WHERE IDF_STATUS = 'aprovado' AND IDF_ATIVO = 1 AND (LOWER(IDF_NOME) LIKE ? OR LOWER(IDF_EMAIL) LIKE ?) $sem_super
            ORDER BY IDF_NOME LIMIT 20
        ", $params_busca);
        while ($res && ($linha = mysqli_fetch_assoc($res))) {
            $pessoas[] = array('id' => (int) $linha['IDF_ID'], 'nome' => (string) $linha['IDF_NOME'], 'email' => (string) $linha['IDF_EMAIL']);
        }
    }
    echo json_encode(array('ok' => true, 'pessoas' => $pessoas));
    exit;
}

// Cursos publicados, pra opção "Ver curso específico" no link de destino —
// só os já publicados, senão o link levaria o fiel pra um curso que ele nem
// deveria conseguir ver ainda. Também servem para o segmento "alunos de um curso".
$cursos_publicados = array();
$sql_cursos_pub = "SELECT IDC_ID, IDC_TITULO FROM ID_CURSO WHERE IDC_STATUS = 'PUBLICADO' ORDER BY IDC_TITULO";
$res_cursos_pub = mysqli_query($conn, $sql_cursos_pub);
if ($res_cursos_pub) {
    while ($row = mysqli_fetch_assoc($res_cursos_pub)) {
        $cursos_publicados[] = $row;
    }
}
$filiais = filiais_listar($conn);
$dias_inativo_opcoes = array(7 => '7 dias', 14 => '14 dias', 30 => '30 dias', 60 => '60 dias');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "enviar_mensagem" && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "enviar_mensagem") {
    $destino_tipo = isset($_POST["destino_tipo"]) ? site_entrada_texto($_POST["destino_tipo"]) : "";
    $destinos = array_slice(array_unique(array_filter(array_map('intval', (array) ($_POST["destinos"] ?? array())))), 0, 500);
    $titulo = site_entrada_texto($_POST["titulo"] ?? "");
    $mensagem_texto = site_entrada_texto($_POST["mensagem"] ?? "");
    $tipo = site_entrada_texto($_POST["tipo"] ?? "info");
    $link_tipo = isset($_POST["link_tipo"]) ? site_entrada_texto($_POST["link_tipo"]) : "";
    $seg_curso_id = (int) ($_POST["seg_curso_id"] ?? 0);
    $seg_dias = (int) ($_POST["seg_dias"] ?? 0);
    $seg_filial_id = (int) ($_POST["seg_filial_id"] ?? 0);

    // Link de destino: só caminhos fixos conhecidos (whitelist) ou um curso
    // publicado de verdade — nunca um valor livre vindo do formulário, pra
    // não virar um jeito de mandar o fiel pra qualquer URL.
    $links_fixos = array(
        'cursos' => 'cursos.php',
        'categorias' => 'categorias.php',
        'progresso' => 'meu_progresso.php',
        'certificados' => 'Certificados/certificados.php',
    );
    $ids_cursos = array_map('intval', array_column($cursos_publicados, 'IDC_ID'));
    $link_valor = null;
    if (isset($links_fixos[$link_tipo])) {
        $link_valor = $links_fixos[$link_tipo];
    } elseif ($link_tipo === 'curso') {
        $link_curso_id = isset($_POST['link_curso_id']) ? (int) $_POST['link_curso_id'] : 0;
        if ($link_curso_id > 0 && in_array($link_curso_id, $ids_cursos, true)) {
            $link_valor = 'curso.php?id=' . $link_curso_id;
        }
    }

    // Quem recebe: cada destino vira um filtro do mesmo INSERT ... SELECT
    // (uma consulta só, inclusive para "pessoas específicas").
    $filtro = '';
    $params_filtro = array();
    $descricao_destino = '';
    if ($destino_tipo === 'todos' || $destino_tipo === 'todos_fieis' || $destino_tipo === 'todos_usuarios') {
        $descricao_destino = 'todos';
    } elseif ($destino_tipo === 'todos_admins') {
        $filtro = "AND EXISTS (SELECT 1 FROM ID_ADMIN a WHERE a.IDA_FIEL_ID = f.IDF_ID AND a.IDA_ATIVO = 1)";
        $descricao_destino = 'admins';
    } elseif ($destino_tipo === 'curso' && in_array($seg_curso_id, $ids_cursos, true)) {
        $filtro = "AND EXISTS (SELECT 1 FROM ID_MATRICULA m WHERE m.IDF_ID = f.IDF_ID AND m.IDC_ID = ?)";
        $params_filtro = array($seg_curso_id);
        $descricao_destino = 'alunos do curso ' . $seg_curso_id;
    } elseif ($destino_tipo === 'inativos' && isset($dias_inativo_opcoes[$seg_dias])) {
        // Reengajamento: quem não abre o app há X dias.
        $filtro = "AND (f.IDF_LAST_ACTIVITY IS NULL OR f.IDF_LAST_ACTIVITY < NOW() - (? * INTERVAL '1 day'))";
        $params_filtro = array($seg_dias);
        $descricao_destino = 'sem entrar há ' . $seg_dias . ' dias';
    } elseif ($destino_tipo === 'filial' && isset($filiais[$seg_filial_id])) {
        $filtro = "AND f.IDF_FILIAL_ID = ?";
        $params_filtro = array($seg_filial_id);
        $descricao_destino = 'filial ' . $filiais[$seg_filial_id]['nome'];
    } elseif ($destino_tipo === 'selecionados' && $destinos) {
        $filtro = "AND f.IDF_ID IN (" . implode(', ', array_fill(0, count($destinos), '?')) . ")";
        $params_filtro = $destinos;
        $descricao_destino = count($destinos) . ' pessoa(s) escolhida(s)';
    }

    if ($titulo === "" || $mensagem_texto === "") {
        $erro = "Preencha o título e a mensagem.";
    } elseif (mb_strlen($titulo) > 150) {
        // A coluna IDN_TITULO tem 150: acima disso o INSERT falhava e a
        // mensagem não ia para ninguém.
        $erro = "O título pode ter até 150 caracteres.";
    } elseif (!in_array($tipo, array('info', 'sucesso', 'aviso', 'erro'), true)) {
        $erro = "Tipo de mensagem inválido.";
    } elseif ($descricao_destino === '') {
        $erro = $destino_tipo === 'selecionados' ? "Escolha pelo menos uma pessoa." : "Escolha para quem enviar.";
    } else {
        // No INSERT ... SELECT o "?" fica sem coluna de contexto: o CAST evita
        // o "could not determine data type of parameter" do Postgres.
        $sql = "INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK)
                SELECT f.IDF_ID, CAST(? AS TEXT), CAST(? AS TEXT), CAST(? AS TEXT), CAST(? AS TEXT)
                FROM ID_FIEL f
                WHERE f.IDF_STATUS = 'aprovado' AND f.IDF_ATIVO = 1 $filtro
                RETURNING IDN_ID";
        // 1ª etapa: só conta e mostra a prévia ("Você vai enviar para N
        // pessoas"). Antes um clique em "Todos" mandava para a igreja inteira
        // sem confirmação nenhuma.
        if (($_POST['confirmar_envio'] ?? '') !== '1') {
            $res_conta = db_query($conn, "SELECT COUNT(*) AS total FROM ID_FIEL f WHERE f.IDF_STATUS = 'aprovado' AND f.IDF_ATIVO = 1 $filtro", $params_filtro);
            $linha_conta = $res_conta ? mysqli_fetch_assoc($res_conta) : null;
            $previa = array(
                'total' => (int) ($linha_conta['total'] ?? 0),
                'para' => $descricao_destino,
                'titulo' => $titulo,
                'texto' => $mensagem_texto,
            );
        } elseif ($res_envio = db_query($conn, $sql, array_merge(array($titulo, $mensagem_texto, $tipo, $link_valor), $params_filtro))) {
            $ids_envio = array();
            while ($linha_envio = mysqli_fetch_assoc($res_envio)) {
                $ids_envio[] = (int) $linha_envio['IDN_ID'];
            }
            $qtde = count($ids_envio);
            // Push na hora (antes só saía quando cada pessoa abria o app).
            $push_saiu = notificacoes_push_lote($conn, $ids_envio, $titulo, mb_substr($mensagem_texto, 0, 240), (string) $link_valor !== '' ? $link_valor : null);
            if ($qtde === 0) {
                $mensagem = "Ninguém se encaixa nesse destino agora; nada foi enviado.";
            } elseif (trim((string) ONESIGNAL_REST_API_KEY) === '') {
                $mensagem = "Mensagem enviada para $qtde pessoa(s). Ela aparece no sininho do app (o aviso no celular não está configurado).";
            } elseif ($push_saiu === 0) {
                // Antes dizia "enviada" mesmo com o push falhando.
                $mensagem = "Mensagem enviada para $qtde pessoa(s) e já está no sininho do app, mas o aviso no celular não saiu agora. Ele sai quando cada pessoa abrir o app.";
            } else {
                $mensagem = "Mensagem enviada para $qtde pessoa(s). O aviso no celular saiu para quem tem o app com as notificações ativadas.";
            }
            admin_log($conn, 'mensagem.enviar', null, null, array('para' => $descricao_destino, 'pessoas' => $qtde, 'titulo' => $titulo));
        } else {
            $erro = site_erro_banco("Erro ao enviar a mensagem.");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Mensagem - Admin</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
    <style>
        .destino-opcoes { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 0.5rem; }
        .destino-opcao { display: flex; gap: 0.5rem; align-items: center; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 10px; cursor: pointer; }
        .destino-opcao:has(input:checked) { border-color: #10b981; background: rgba(16, 185, 129, 0.12); }
        .destino-extra { border: 1px dashed var(--border-color); border-radius: 10px; padding: 0.75rem; }
        .pessoas-resultados { max-height: 240px; overflow: auto; }
        .pessoas-resultados button { display: block; width: 100%; text-align: left; border: 0; background: transparent; color: var(--text-main); padding: 0.45rem 0.6rem; border-radius: 8px; }
        .pessoas-resultados button:hover, .pessoas-resultados button:focus { background: rgba(96, 165, 250, 0.15); }
        .pessoa-chip { display: inline-flex; gap: 0.4rem; align-items: center; padding: 0.25rem 0.6rem; border-radius: 999px; background: rgba(16, 185, 129, 0.18); margin: 0 0.35rem 0.35rem 0; font-size: 0.85rem; }
        .pessoa-chip button { border: 0; background: transparent; color: inherit; padding: 0; line-height: 1; }
        /* Prévia antes de enviar (2 etapas). */
        .mensagem-previa { border: 1px solid rgba(96, 165, 250, 0.4); background: rgba(96, 165, 250, 0.08); border-radius: 14px; padding: 1.1rem 1.25rem; }
        .mensagem-previa-celular { max-width: 380px; display: flex; flex-direction: column; gap: 0.2rem; padding: 0.8rem 1rem; border-radius: 16px; background: var(--bg-light, #1e293b); border: 1px solid var(--border-color); box-shadow: 0 8px 20px rgba(0,0,0,0.25); }
        .mensagem-previa-celular span { color: var(--text-muted); font-size: 0.92rem; }
    </style>
</head>
<body class="admin-layout admin-shell">
    <?php admin_layout_abrir($conn, 'mensagem', 'Enviar Mensagem', 'fa-comment-dots'); ?>

        <p class="text-muted mb-4">Envie uma notificação (no app e, para quem ativou, no celular) para todos, para um grupo ou para pessoas específicas.</p>

        <div class="admin-card">

            <?php if ($previa) { ?>
                <!-- 2ª etapa: confirmar com a contagem e a prévia do aviso. -->
                <div class="mensagem-previa mb-4" role="region" aria-label="Confirmar envio">
                    <h2 class="h5 mb-2"><i class="fas fa-paper-plane me-2" aria-hidden="true"></i>Você vai enviar para <strong><?php echo (int) $previa['total']; ?> pessoa(s)</strong></h2>
                    <p class="text-muted mb-3">Destino: <?php echo htmlspecialchars($previa['para']); ?>. Confira como o aviso vai aparecer:</p>
                    <div class="mensagem-previa-celular" aria-hidden="true">
                        <strong><?php echo htmlspecialchars($previa['titulo']); ?></strong>
                        <span><?php echo htmlspecialchars(mb_strimwidth($previa['texto'], 0, 140, '…')); ?></span>
                    </div>
                    <?php if ($previa['total'] > 0) { ?>
                        <form method="POST" class="d-flex flex-wrap gap-2 mt-3">
                            <?php
                            echo csrf_campo();
                            // Repete o que foi preenchido (inclusive as pessoas escolhidas).
                            foreach ($_POST as $campo_previa => $valor_previa) {
                                if ($campo_previa === 'csrf_token' || $campo_previa === 'confirmar_envio') {
                                    continue;
                                }
                                foreach ((array) $valor_previa as $item_previa) {
                                    if (is_scalar($item_previa)) {
                                        echo '<input type="hidden" name="' . htmlspecialchars($campo_previa . (is_array($valor_previa) ? '[]' : '')) . '" value="' . htmlspecialchars((string) $item_previa) . '">';
                                    }
                                }
                            }
                            ?>
                            <input type="hidden" name="confirmar_envio" value="1">
                            <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-check me-2" aria-hidden="true"></i>Confirmar e enviar para <?php echo (int) $previa['total']; ?></button>
                            <a href="admin_enviar_mensagem.php" class="btn btn-outline-secondary">Cancelar</a>
                        </form>
                    <?php } else { ?>
                        <p class="mb-0">Ninguém se encaixa nesse destino agora. Escolha outro abaixo.</p>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if ($mensagem !== "") { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensagem); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php } ?>

            <?php if ($erro !== "") { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($erro); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php } ?>

            <form method="POST" id="formMensagem">
            <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="enviar_mensagem">
                <div class="row g-3">
                    <div class="col-md-12">
                        <span class="form-label d-block">Para quem</span>
                        <div class="destino-opcoes mb-2">
                            <?php
                            $destinos_opcoes = array(
                                'todos_fieis' => array('fa-users', 'Todos'),
                                'todos_admins' => array('fa-user-shield', 'Só os admins'),
                                'curso' => array('fa-graduation-cap', 'Alunos de um curso'),
                                'inativos' => array('fa-bed', 'Quem está sumido'),
                            );
                            if ($filiais) {
                                $destinos_opcoes['filial'] = array('fa-map-location-dot', 'Uma filial');
                            }
                            $destinos_opcoes['selecionados'] = array('fa-user-check', 'Pessoas específicas');
                            foreach ($destinos_opcoes as $valor => $opcao) { ?>
                                <label class="destino-opcao">
                                    <input class="form-check-input mt-0" type="radio" name="destino_tipo" value="<?php echo $valor; ?>"<?php echo $valor === 'todos_fieis' ? ' checked' : ''; ?>>
                                    <i class="fas <?php echo $opcao[0]; ?>" aria-hidden="true"></i><?php echo $opcao[1]; ?>
                                </label>
                            <?php } ?>
                        </div>

                        <div class="destino-extra" data-destino="curso" hidden>
                            <label class="form-label small" for="segCurso">Curso</label>
                            <select name="seg_curso_id" id="segCurso" class="form-select custom-input">
                                <?php foreach ($cursos_publicados as $curso_opt) { ?>
                                    <option value="<?php echo (int) $curso_opt['IDC_ID']; ?>"><?php echo htmlspecialchars($curso_opt['IDC_TITULO']); ?></option>
                                <?php } ?>
                            </select>
                            <small class="text-muted">Quem começou o curso (matriculado), terminado ou não.</small>
                        </div>
                        <div class="destino-extra" data-destino="inativos" hidden>
                            <label class="form-label small" for="segDias">Sem abrir o app há pelo menos</label>
                            <select name="seg_dias" id="segDias" class="form-select custom-input">
                                <?php foreach ($dias_inativo_opcoes as $dias => $rotulo) { ?>
                                    <option value="<?php echo $dias; ?>"<?php echo $dias === 14 ? ' selected' : ''; ?>><?php echo $rotulo; ?></option>
                                <?php } ?>
                            </select>
                            <small class="text-muted">Bom para um convite carinhoso de volta ("Sentimos sua falta...").</small>
                        </div>
                        <?php if ($filiais) { ?>
                            <div class="destino-extra" data-destino="filial" hidden>
                                <label class="form-label small" for="segFilial">Filial</label>
                                <select name="seg_filial_id" id="segFilial" class="form-select custom-input">
                                    <?php foreach ($filiais as $filial) { if (!$filial['ativa']) { continue; } ?>
                                        <option value="<?php echo (int) $filial['id']; ?>"><?php echo htmlspecialchars($filial['nome']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        <?php } ?>
                        <div class="destino-extra" data-destino="selecionados" hidden>
                            <label class="form-label small" for="buscaPessoa">Buscar por nome ou e-mail</label>
                            <input type="search" id="buscaPessoa" class="form-control custom-input mb-2" placeholder="Digite pelo menos 2 letras" autocomplete="off">
                            <div class="pessoas-resultados mb-2" id="pessoasResultados" role="listbox" aria-label="Resultados"></div>
                            <div id="pessoasEscolhidas" aria-live="polite"></div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label" for="tituloMsg">Título</label>
                        <input type="text" name="titulo" id="tituloMsg" class="form-control custom-input" maxlength="150" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label" for="textoMsg">Mensagem</label>
                        <textarea name="mensagem" id="textoMsg" class="form-control custom-input" rows="5" required maxlength="5000"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="tipoMsg">Tipo</label>
                        <select name="tipo" id="tipoMsg" class="form-select custom-input">
                            <option value="info">Informação</option>
                            <option value="sucesso">Sucesso</option>
                            <option value="aviso">Aviso</option>
                            <option value="erro">Erro</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="link_tipo">Ao tocar na notificação, levar para (opcional)</label>
                        <select name="link_tipo" id="link_tipo" class="form-select custom-input">
                            <option value="">Nenhum lugar específico</option>
                            <option value="curso">Um curso específico...</option>
                            <option value="cursos">Catálogo de cursos</option>
                            <option value="categorias">Categorias</option>
                            <option value="progresso">Meu Progresso</option>
                            <option value="certificados">Certificados</option>
                        </select>
                        <select name="link_curso_id" id="link_curso_id" class="form-select custom-input mt-2" hidden>
                            <option value="">Selecione o curso...</option>
                            <?php foreach ($cursos_publicados as $curso_opt) { ?>
                                <option value="<?php echo (int) $curso_opt['IDC_ID']; ?>"><?php echo htmlspecialchars($curso_opt['IDC_TITULO']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <small class="form-text text-muted">Vira push de verdade só para quem já ativou as notificações e continua com acesso aprovado.</small>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary fw-bold">
                            <i class="fas fa-paper-plane me-2"></i>Enviar Mensagem
                        </button>
                    </div>
                </div>
            </form>

            <script nonce="<?php echo site_csp_nonce(); ?>">
                (function () {
                    var form = document.getElementById('formMensagem');
                    var extras = document.querySelectorAll('.destino-extra');
                    function destinoAtual() {
                        var marcado = form.querySelector('input[name="destino_tipo"]:checked');
                        return marcado ? marcado.value : '';
                    }
                    function atualizarExtras() {
                        var atual = destinoAtual();
                        extras.forEach(function (bloco) { bloco.hidden = bloco.getAttribute('data-destino') !== atual; });
                    }
                    form.querySelectorAll('input[name="destino_tipo"]').forEach(function (r) { r.addEventListener('change', atualizarExtras); });
                    atualizarExtras();

                    // Pessoas específicas: busca no servidor e chips com o
                    // campo escondido destinos[] de cada escolhida.
                    var busca = document.getElementById('buscaPessoa');
                    var resultados = document.getElementById('pessoasResultados');
                    var escolhidas = document.getElementById('pessoasEscolhidas');
                    var espera = null;
                    function escolher(p) {
                        if (escolhidas.querySelector('input[value="' + p.id + '"]')) return;
                        var chip = document.createElement('span');
                        chip.className = 'pessoa-chip';
                        chip.textContent = p.nome;
                        var campo = document.createElement('input');
                        campo.type = 'hidden';
                        campo.name = 'destinos[]';
                        campo.value = String(p.id);
                        var tirar = document.createElement('button');
                        tirar.type = 'button';
                        tirar.setAttribute('aria-label', 'Tirar ' + p.nome);
                        tirar.innerHTML = '<i class="fas fa-xmark" aria-hidden="true"></i>';
                        tirar.addEventListener('click', function () { chip.remove(); });
                        chip.appendChild(campo);
                        chip.appendChild(tirar);
                        escolhidas.appendChild(chip);
                    }
                    busca.addEventListener('input', function () {
                        clearTimeout(espera);
                        var termo = busca.value.trim();
                        if (termo.length < 2) { resultados.textContent = ''; return; }
                        espera = setTimeout(function () {
                            fetch('admin_enviar_mensagem.php?buscar=' + encodeURIComponent(termo), { credentials: 'same-origin' })
                                .then(function (r) { return r.json(); })
                                .then(function (dados) {
                                    resultados.textContent = '';
                                    if (!dados.pessoas || !dados.pessoas.length) {
                                        resultados.textContent = 'Ninguém encontrado.';
                                        return;
                                    }
                                    dados.pessoas.forEach(function (p) {
                                        var b = document.createElement('button');
                                        b.type = 'button';
                                        b.textContent = p.nome + ' · ' + p.email;
                                        b.addEventListener('click', function () { escolher(p); });
                                        resultados.appendChild(b);
                                    });
                                })
                                .catch(function () { resultados.textContent = 'Não foi possível buscar agora.'; });
                        }, 250);
                    });

                    var linkTipo = document.getElementById('link_tipo');
                    var linkCursoId = document.getElementById('link_curso_id');
                    function atualizarLinkCurso() {
                        linkCursoId.hidden = linkTipo.value !== 'curso';
                        if (linkTipo.value !== 'curso') { linkCursoId.value = ''; }
                    }
                    linkTipo.addEventListener('change', atualizarLinkCurso);
                    atualizarLinkCurso();
                })();
            </script>
        </div>
    <?php admin_layout_fechar(); ?>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
