<?php
// Desafios (T31): o admin cria e edita os desafios, pausa/reativa e vê
// quantos entraram e concluíram. O fiel participa em php/fiel/desafios.php.
// Ver "DESAFIOS" em php/conexao.php e a seção 62 do bancosupabase.md.
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));
// Ações que deram certo vão para o Registro de atividade (admin_log_ao_redirecionar()).
admin_log_ao_redirecionar($conn, 'desafios');

$disponivel = desafios_disponivel($conn);
$erro = '';
$mensagem = '';
$editar_id = (int) ($_GET['editar'] ?? 0);
$form = array(
    'titulo' => '', 'descricao' => '', 'metrica' => 'aulas', 'modo' => 'total', 'meta' => 5,
    'periodo' => 'ao_entrar', 'duracao' => 7, 'inicio' => '', 'fim' => '', 'avisar' => true,
);

if ($disponivel && $editar_id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $atual = desafio_obter($conn, $editar_id);
    if ($atual) {
        $form = array(
            'titulo' => $atual['titulo'], 'descricao' => $atual['descricao'], 'metrica' => $atual['metrica'],
            'modo' => $atual['modo'], 'meta' => $atual['meta'], 'periodo' => $atual['periodo'],
            'duracao' => $atual['duracao'], 'inicio' => (string) $atual['inicio'], 'fim' => (string) $atual['fim'], 'avisar' => false,
        );
    } else {
        $editar_id = 0;
    }
}

if ($disponivel && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = site_entrada_texto($_POST['acao'] ?? '', false);
    $desafio_id = (int) ($_POST['idds_id'] ?? 0);
    if (!csrf_validar()) {
        $erro = 'Sessão expirada ou formulário inválido. Atualize a página e tente de novo.';
    } elseif ($acao === 'salvar') {
        $form = array(
            'titulo' => site_entrada_texto($_POST['titulo'] ?? '', false), 'descricao' => site_entrada_texto($_POST['descricao'] ?? '', false),
            'metrica' => site_entrada_texto($_POST['metrica'] ?? '', false), 'modo' => site_entrada_texto($_POST['modo'] ?? '', false),
            'meta' => (int) ($_POST['meta'] ?? 0), 'periodo' => site_entrada_texto($_POST['periodo'] ?? '', false),
            'duracao' => (int) ($_POST['duracao'] ?? 0), 'inicio' => site_entrada_texto($_POST['inicio'] ?? '', false),
            'fim' => site_entrada_texto($_POST['fim'] ?? '', false), 'avisar' => isset($_POST['avisar']),
        );
        $editar_id = $desafio_id;
        $resultado = desafio_salvar($conn, $form, auth_resolve_fiel_id(), $desafio_id);
        $erro = (string) $resultado['erro'];
        if ($erro === '') {
            if ($desafio_id === 0 && $form['avisar']) {
                $novo = desafio_obter($conn, $resultado['id']);
                if ($novo) {
                    desafio_avisar_todos($conn, $novo);
                }
            }
            $_SESSION['admin_desafios_ok'] = $desafio_id > 0 ? 'Desafio atualizado.' : ($form['avisar'] ? 'Desafio criado e fiéis avisados.' : 'Desafio criado.');
            header('Location: admin_desafios.php');
            exit;
        }
    } elseif ($acao === 'pausar' || $acao === 'reativar') {
        $ativo = $acao === 'reativar' ? 1 : 0;
        mysqli_query($conn, "UPDATE id_desafio SET idds_ativo = $ativo WHERE idds_id = $desafio_id");
        $_SESSION['admin_desafios_ok'] = $ativo ? 'Desafio reativado.' : 'Desafio pausado: ninguém novo entra, e quem já está continua.';
        header('Location: admin_desafios.php');
        exit;
    } elseif ($acao === 'excluir') {
        // Só sem participantes; com participantes, o caminho é pausar.
        mysqli_query($conn, "DELETE FROM id_desafio WHERE idds_id = $desafio_id AND NOT EXISTS (SELECT 1 FROM id_desafio_participante p WHERE p.idds_id = $desafio_id)");
        $_SESSION['admin_desafios_ok'] = mysqli_affected_rows($conn) > 0 ? 'Desafio excluído.' : 'Este desafio já tem participantes: pause em vez de excluir.';
        header('Location: admin_desafios.php');
        exit;
    }
}
if (!empty($_SESSION['admin_desafios_ok'])) {
    $mensagem = $_SESSION['admin_desafios_ok'];
    unset($_SESSION['admin_desafios_ok']);
}

$lista = $disponivel ? desafios_admin_listar($conn) : array();
$metricas = desafios_metricas();
$hoje = site_hoje();
$form_aberto = $erro !== '' || $editar_id > 0 || isset($_GET['novo']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Desafios</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'desafios', 'Desafios', 'fa-flag-checkered'); ?>

    <p class="text-muted mb-4">Crie desafios para os fiéis (ex.: "assistir 5 aulas em 7 dias"). O progresso é contado sozinho com o que eles fazem no app; para o que o app não mede (ex.: "ler 1 capítulo por dia"), use "Marcar como feito" — a própria pessoa confirma cada dia.</p>

    <?php if ($mensagem !== '') { ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensagem); ?></div>
    <?php } ?>
    <?php if ($erro !== '') { ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($erro); ?></div>
    <?php } ?>

    <?php if (!$disponivel) { ?>
        <div class="alert alert-warning">
            Os desafios ainda não existem no banco. Rode a seção 62 do
            <code>database/bancosupabase.md</code> no Supabase para liberar esta tela.
        </div>
    <?php } else { ?>

    <?php if (!$form_aberto) { ?>
        <a href="admin_desafios.php?novo=1#form" class="btn btn-success mb-4"><i class="fas fa-plus me-2"></i>Novo desafio</a>
    <?php } else { ?>
        <div class="admin-card mb-4" id="form">
            <h5 class="mb-3"><i class="fas <?php echo $editar_id ? 'fa-pen' : 'fa-plus'; ?> me-2"></i><?php echo $editar_id ? 'Editar desafio' : 'Novo desafio'; ?></h5>
            <form method="POST" action="admin_desafios.php<?php echo $editar_id ? '?editar=' . $editar_id : ''; ?>#form" id="desafioForm">
                <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="idds_id" value="<?php echo $editar_id; ?>">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="dTitulo">Título</label>
                        <input type="text" class="form-control custom-input" id="dTitulo" name="titulo" required minlength="3" maxlength="<?php echo DESAFIO_TITULO_MAX; ?>" placeholder="Ex.: Semana de imersão" value="<?php echo htmlspecialchars($form['titulo']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="dMetrica">O que conta</label>
                        <select class="form-select custom-input" id="dMetrica" name="metrica">
                            <?php foreach ($metricas as $chave => $m) { $liberada = desafio_metrica_disponivel($conn, $chave); ?>
                                <option value="<?php echo $chave; ?>"<?php echo $form['metrica'] === $chave ? ' selected' : ''; ?><?php echo $liberada ? '' : ' disabled'; ?>><?php echo htmlspecialchars($m['rotulo']) . ($liberada ? '' : ' (indisponível)'); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="dDescricao">Descrição <span class="text-muted">(opcional)</span></label>
                        <textarea class="form-control custom-input" id="dDescricao" name="descricao" rows="2" maxlength="<?php echo DESAFIO_DESCRICAO_MAX; ?>" placeholder="Explique o desafio. Ex.: Leia 1 capítulo do Evangelho de João por dia e marque aqui."><?php echo htmlspecialchars($form['descricao']); ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <span class="form-label d-block">Meta</span>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="modo" id="dModoTotal" value="total"<?php echo $form['modo'] !== 'diario' ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="dModoTotal">Total no período <small class="text-muted">(ex.: 5 aulas em 7 dias)</small></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="modo" id="dModoDiario" value="diario"<?php echo $form['modo'] === 'diario' ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="dModoDiario">Todo dia <small class="text-muted">(ex.: 1 aula por dia)</small></label>
                        </div>
                    </div>
                    <div class="col-md-2" id="dMetaBox">
                        <label class="form-label" for="dMeta" id="dMetaRotulo">Quantidade</label>
                        <input type="number" class="form-control custom-input" id="dMeta" name="meta" min="1" max="1000" value="<?php echo (int) $form['meta']; ?>">
                    </div>
                    <div class="col-md-6">
                        <span class="form-label d-block">Período</span>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="periodo" id="dPeriodoEntrar" value="ao_entrar"<?php echo $form['periodo'] !== 'fixo' ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="dPeriodoEntrar">Começa quando cada pessoa entrar</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="periodo" id="dPeriodoFixo" value="fixo"<?php echo $form['periodo'] === 'fixo' ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="dPeriodoFixo">Datas fixas para todos</label>
                        </div>
                    </div>
                    <div class="col-md-3" id="dDuracaoBox">
                        <label class="form-label" for="dDuracao">Duração (dias)</label>
                        <input type="number" class="form-control custom-input" id="dDuracao" name="duracao" min="1" max="366" value="<?php echo (int) $form['duracao']; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="dInicio" id="dInicioRotulo">Abre em <span class="text-muted">(opcional)</span></label>
                        <input type="date" class="form-control custom-input" id="dInicio" name="inicio" value="<?php echo htmlspecialchars($form['inicio']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="dFim" id="dFimRotulo">Inscrições até <span class="text-muted">(opcional)</span></label>
                        <input type="date" class="form-control custom-input" id="dFim" name="fim" value="<?php echo htmlspecialchars($form['fim']); ?>">
                    </div>
                    <?php if (!$editar_id) { ?>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="dAvisar" name="avisar"<?php echo $form['avisar'] ? ' checked' : ''; ?>>
                                <label class="form-check-label" for="dAvisar">Avisar todos os fiéis (notificação no app)</label>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="col-12">
                        <p class="admin-desafio-previa mb-0"><i class="fas fa-eye me-2"></i>Como o fiel vai ler: <strong id="dPrevia"></strong></p>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i><?php echo $editar_id ? 'Salvar alterações' : 'Criar desafio'; ?></button>
                        <a href="admin_desafios.php" class="btn btn-outline-light">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    <?php } ?>

    <?php if (!$lista) { ?>
        <div class="admin-card text-center text-muted py-5">Nenhum desafio criado ainda.</div>
    <?php } ?>

    <?php foreach ($lista as $d) {
        $encerrado = $d['fim'] && $hoje > $d['fim'];
        if (!$d['ativo']) {
            $selo = array('Pausado', 'bg-secondary');
        } elseif ($encerrado) {
            $selo = array('Encerrado', 'bg-secondary');
        } elseif ($d['inicio'] && $hoje < $d['inicio']) {
            $selo = array('Agendado', 'bg-info');
        } else {
            $selo = array('Aberto', 'bg-success');
        }
    ?>
        <div class="admin-card admin-desafio">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($d['titulo']); ?> <span class="badge <?php echo $selo[1]; ?> ms-1"><?php echo $selo[0]; ?></span></h5>
                    <small class="text-muted">
                        <?php echo htmlspecialchars($d['meta_texto']); ?>
                        <?php if ($d['periodo'] === 'ao_entrar' && ($d['inicio'] || $d['fim'])) { ?>
                            · inscrições <?php echo $d['inicio'] ? 'de ' . desafio_data_br($d['inicio']) . ' ' : ''; ?><?php echo $d['fim'] ? 'até ' . desafio_data_br($d['fim']) : ''; ?>
                        <?php } ?>
                    </small>
                </div>
                <div class="text-end small">
                    <div><i class="fas fa-users me-1"></i><?php echo $d['participantes']; ?> participante(s)</div>
                    <div><i class="fas fa-trophy me-1" style="color:#facc15;"></i><?php echo $d['concluiram']; ?> concluíram</div>
                </div>
            </div>
            <?php if ($d['descricao'] !== '') { ?>
                <p class="mb-3 small" style="white-space: pre-wrap;"><?php echo htmlspecialchars($d['descricao']); ?></p>
            <?php } ?>
            <div class="d-flex flex-wrap gap-2">
                <a href="admin_desafios.php?editar=<?php echo $d['id']; ?>#form" class="btn btn-sm btn-outline-light"><i class="fas fa-pen me-1"></i>Editar</a>
                <form method="POST" action="admin_desafios.php">
                    <?php echo csrf_campo(); ?>
                    <input type="hidden" name="acao" value="<?php echo $d['ativo'] ? 'pausar' : 'reativar'; ?>">
                    <input type="hidden" name="idds_id" value="<?php echo $d['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas <?php echo $d['ativo'] ? 'fa-pause' : 'fa-play'; ?> me-1"></i><?php echo $d['ativo'] ? 'Pausar' : 'Reativar'; ?></button>
                </form>
                <?php if ($d['participantes'] === 0) { ?>
                    <form method="POST" action="admin_desafios.php" data-confirmar="Excluir este desafio?">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="idds_id" value="<?php echo $d['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt me-1"></i>Excluir</button>
                    </form>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
    <?php } ?>

    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        // Formulário: mostra só os campos do período escolhido, trava a meta
        // em 1 quando ela só conta 1 por dia e monta a prévia do texto.
        (function () {
            var form = document.getElementById('desafioForm');
            if (!form) return;
            var metricas = <?php echo json_encode($metricas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            var campo = function (id) { return document.getElementById(id); };

            function dataCurta(valor) {
                var p = (valor || '').split('-');
                return p.length === 3 ? p[2] + '/' + p[1] : '?';
            }

            function atualizar() {
                var metrica = campo('dMetrica').value;
                var diario = campo('dModoDiario').checked;
                var fixo = campo('dPeriodoFixo').checked;
                var unica = metrica === 'dias_app' || metrica === 'checkin';
                var m = metricas[metrica];

                campo('dDuracaoBox').hidden = fixo;
                campo('dInicioRotulo').innerHTML = fixo ? 'Início' : 'Abre em <span class="text-muted">(opcional)</span>';
                campo('dFimRotulo').innerHTML = fixo ? 'Fim' : 'Inscrições até <span class="text-muted">(opcional)</span>';
                campo('dInicio').required = fixo;
                campo('dFim').required = fixo;
                campo('dMetaBox').hidden = diario && unica;
                campo('dMetaRotulo').textContent = diario ? 'Por dia' : 'Quantidade';
                if (diario && unica) campo('dMeta').value = 1;

                var meta = parseInt(campo('dMeta').value, 10) || 1;
                var dias = parseInt(campo('dDuracao').value, 10) || 1;
                var periodo = fixo ? 'de ' + dataCurta(campo('dInicio').value) + ' a ' + dataCurta(campo('dFim').value) : 'em ' + dias + (dias === 1 ? ' dia' : ' dias');
                var texto;
                if (diario) {
                    var duracao = fixo ? periodo : 'durante ' + dias + (dias === 1 ? ' dia' : ' dias');
                    texto = unica
                        ? (metrica === 'dias_app' ? 'Acessar o app' : 'Marcar como feito') + ' todos os dias ' + duracao
                        : m.verbo + ' ' + meta + ' ' + (meta === 1 ? m.um : m.varios) + ' por dia ' + duracao;
                } else if (unica) {
                    var acao = metrica === 'dias_app' ? 'Acessar o app' : 'Marcar como feito';
                    texto = fixo
                        ? acao + ' em ' + meta + (meta === 1 ? ' dia, ' : ' dias, ') + periodo
                        : acao + ' em ' + meta + ' dos ' + dias + ' dias';
                } else {
                    texto = m.verbo + ' ' + meta + ' ' + (meta === 1 ? m.um : m.varios) + ' ' + periodo;
                }
                campo('dPrevia').textContent = texto;
            }

            form.addEventListener('input', atualizar);
            form.addEventListener('change', atualizar);
            atualizar();
        })();
    </script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
