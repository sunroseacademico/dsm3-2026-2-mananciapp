<?php
// Devocional do dia (T33): o devocional publicado hoje (enviado por um fiel,
// avaliado pelos líderes e agendado sozinho), o envio de devocionais, a
// avaliação dos líderes e os anteriores. Ver "DEVOCIONAIS ENVIADOS PELOS
// FIÉIS" em php/conexao.php e a seção 63 do bancosupabase.md.
include("../conexao.php");
auth_require();

$fiel_id = auth_resolve_fiel_id();
$disponivel = devocionais_disponivel($conn);
$eh_lider = $disponivel && devocional_eh_lider($conn, $fiel_id);
$erro = '';
$mensagem = '';
$form = array('titulo' => '', 'referencia' => '', 'versiculo' => '', 'tema' => '', 'texto' => '');

if ($disponivel && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = site_entrada_texto($_POST['acao'] ?? '', false);
    $ancora = '';
    if (!csrf_validar()) {
        $erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } elseif ($acao === 'enviar') {
        $form = array_intersect_key($_POST, $form) + $form;
        $erro = (string) devocional_enviar($conn, $fiel_id, $form);
        if ($erro === '') {
            $_SESSION['devocional_ok'] = 'Devocional enviado! Os líderes vão avaliar e, se aprovado, ele entra na agenda — você recebe um aviso com a data.';
            $ancora = '#meus';
        }
    } elseif ($acao === 'avaliar') {
        $resultado = devocional_avaliar($conn, $fiel_id, (int) ($_POST['iddv_id'] ?? 0), (int) ($_POST['nota'] ?? 0));
        $erro = (string) $resultado['erro'];
        if ($erro === '') {
            $_SESSION['devocional_ok'] = $resultado['aprovado_em']
                ? 'Nota salva. Com ela, o devocional foi aprovado e entrou na agenda (' . date('d/m', strtotime($resultado['aprovado_em'])) . ').'
                : 'Nota salva. Obrigado por avaliar!';
            $ancora = '#avaliar';
        }
    } elseif ($acao === 'remover') {
        if (devocional_remover_proprio($conn, $fiel_id, (int) ($_POST['iddv_id'] ?? 0))) {
            $_SESSION['devocional_ok'] = 'Devocional removido.';
        }
        $ancora = '#meus';
    }
    if ($erro === '') {
        header('Location: devocional.php' . $ancora);
        exit;
    }
}
if (!empty($_SESSION['devocional_ok'])) {
    $mensagem = $_SESSION['devocional_ok'];
    unset($_SESSION['devocional_ok']);
}

$hoje = site_hoje();
// ?id=N abre um devocional já publicado (dos anteriores).
$principal = null;
if ($disponivel && isset($_GET['id'])) {
    $principal = devocional_obter($conn, (int) $_GET['id']);
    if (!$principal || $principal['status'] !== 'aprovado' || $principal['data'] > $hoje) {
        $principal = null;
    }
}
if ($disponivel && !$principal) {
    $principal = devocional_do_dia($conn);
}
$anteriores = $disponivel ? array_values(array_filter(devocionais_publicados($conn, 15), function ($d) use ($principal) { return !$principal || $d['id'] !== $principal['id']; })) : array();
$anteriores = array_slice($anteriores, 0, 14);
$meus = $disponivel ? devocionais_do_fiel($conn, $fiel_id) : array();
$para_avaliar = $eh_lider ? devocionais_para_avaliar($conn, $fiel_id) : array();
$form_aberto = $erro !== '' && ($_POST['acao'] ?? '') === 'enviar' || isset($_GET['enviar']);

function devocional_data_extenso($data) {
    $meses = array(1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro');
    $t = strtotime($data);
    return (int) date('j', $t) . ' de ' . $meses[(int) date('n', $t)] . ' de ' . date('Y', $t);
}

$status_meus = array(
    'avaliacao' => array('Em avaliação pelos líderes', 'fa-hourglass-half', 'is-pendente'),
    'aprovado' => array('Aprovado', 'fa-calendar-check', 'is-aprovado'),
    'recusado' => array('Não publicado', 'fa-circle-xmark', 'is-recusado'),
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Devocional do dia</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'devocional'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 900px; margin: 0 auto;">
        <div class="streaming-page-header d-flex justify-content-between align-items-end flex-wrap gap-3">
            <div>
                <h1><i class="fas fa-book-bible me-2" style="color:#fbbf24;"></i>Devocional do dia</h1>
                <p><?php echo ucfirst(devocional_data_extenso($hoje)); ?></p>
            </div>
            <?php if ($disponivel) { ?>
                <button type="button" class="btn btn-play" data-bs-toggle="collapse" data-bs-target="#enviar" aria-expanded="<?php echo $form_aberto ? 'true' : 'false'; ?>" aria-controls="enviar">
                    <i class="fas fa-pen-to-square me-2"></i>Enviar um devocional
                </button>
            <?php } ?>
        </div>

        <?php if ($mensagem !== '') { ?>
            <div class="alert alert-success" role="status"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensagem); ?></div>
        <?php } ?>
        <?php if ($erro !== '' && !$form_aberto) { ?>
            <div class="alert alert-danger" role="alert"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($erro); ?></div>
        <?php } ?>

        <?php if (!$disponivel) { ?>
            <div class="streaming-empty-state">
                <i class="fas fa-book-bible"></i>
                <p class="mt-3 mb-0">O devocional do dia ainda não está disponível.</p>
            </div>
        <?php } else { ?>

        <div class="collapse <?php echo $form_aberto ? 'show' : ''; ?>" id="enviar">
            <div class="streaming-info-card text-start mb-4">
                <h2 class="h6 mb-1"><i class="fas fa-pen-to-square me-2" style="color: var(--primary-light);"></i>Enviar um devocional</h2>
                <p class="text-muted small mb-3">Os líderes da igreja leem e dão uma nota. Aprovado, ele vira o devocional do dia numa data da agenda — você recebe um aviso.</p>
                <?php if ($erro !== '' && $form_aberto) { ?>
                    <div class="auth-erro mb-3" role="alert"><?php echo htmlspecialchars($erro); ?></div>
                <?php } ?>
                <form method="POST" action="devocional.php#enviar" class="row g-3">
                    <?php echo csrf_campo(); ?>
                    <input type="hidden" name="acao" value="enviar">
                    <div class="col-md-8">
                        <label class="form-label" for="dvTitulo">Título</label>
                        <input type="text" class="form-control custom-input" id="dvTitulo" name="titulo" required minlength="5" maxlength="<?php echo DEVOCIONAL_TITULO_MAX; ?>" placeholder="Ex.: O Senhor é o meu pastor" value="<?php echo htmlspecialchars($form['titulo']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="dvTema">Tema</label>
                        <select class="form-select custom-input" id="dvTema" name="tema" required>
                            <option value="">Escolha…</option>
                            <?php foreach (devocional_temas() as $tema) { ?>
                                <option<?php echo $form['tema'] === $tema ? ' selected' : ''; ?>><?php echo htmlspecialchars($tema); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="dvReferencia">Texto base</label>
                        <input type="text" class="form-control custom-input" id="dvReferencia" name="referencia" required maxlength="<?php echo DEVOCIONAL_REFERENCIA_MAX; ?>" placeholder="Ex.: Salmos 23:1-3" value="<?php echo htmlspecialchars($form['referencia']); ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="dvVersiculo">Trecho do versículo <span class="text-muted">(opcional)</span></label>
                        <textarea class="form-control custom-input" id="dvVersiculo" name="versiculo" rows="2" maxlength="<?php echo DEVOCIONAL_VERSICULO_MAX; ?>" placeholder="O Senhor é o meu pastor; nada me faltará…"><?php echo htmlspecialchars($form['versiculo']); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="dvTexto">Devocional</label>
                        <textarea class="form-control custom-input" id="dvTexto" name="texto" rows="9" required minlength="<?php echo DEVOCIONAL_TEXTO_MIN; ?>" maxlength="<?php echo DEVOCIONAL_TEXTO_MAX; ?>" placeholder="Uma reflexão sobre o texto base e como vivê-lo hoje. Termine, se quiser, com uma oração."><?php echo htmlspecialchars($form['texto']); ?></textarea>
                        <small class="text-muted" id="dvContador" data-min="<?php echo DEVOCIONAL_TEXTO_MIN; ?>" data-max="<?php echo DEVOCIONAL_TEXTO_MAX; ?>"></small>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-play"><i class="fas fa-paper-plane me-2"></i>Enviar para avaliação</button>
                        <button type="button" class="btn btn-outline-light" data-bs-toggle="collapse" data-bs-target="#enviar">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($principal) { ?>
            <article class="devocional-card mb-4" id="dv-<?php echo $principal['id']; ?>">
                <?php if ($principal['data'] !== $hoje) { ?>
                    <p class="devocional-aviso"><i class="far fa-calendar me-1"></i>Devocional de <?php echo devocional_data_extenso($principal['data']); ?><?php echo !isset($_GET['id']) ? ' (ainda não há um para hoje)' : ''; ?></p>
                <?php } ?>
                <span class="devocional-tema"><?php echo htmlspecialchars($principal['tema']); ?></span>
                <h2 class="devocional-titulo"><?php echo htmlspecialchars($principal['titulo']); ?></h2>
                <blockquote class="devocional-base">
                    <?php if ($principal['versiculo'] !== '') { ?><p><?php echo nl2br(htmlspecialchars($principal['versiculo'])); ?></p><?php } ?>
                    <cite><i class="fas fa-book-bible me-1" aria-hidden="true"></i><?php echo htmlspecialchars($principal['referencia']); ?></cite>
                </blockquote>
                <div class="devocional-texto"><?php echo nl2br(htmlspecialchars($principal['texto'])); ?></div>
                <p class="devocional-autor">Escrito por <strong><?php echo htmlspecialchars($principal['autor']); ?></strong><?php echo $principal['meu'] ? ' (você)' : ''; ?></p>
            </article>
        <?php } else { ?>
            <div class="streaming-empty-state mb-4">
                <i class="fas fa-book-bible"></i>
                <p class="mt-3 mb-1">Ainda não há devocional publicado.</p>
                <p class="text-muted small mb-0">Que tal escrever o primeiro? Toque em "Enviar um devocional".</p>
            </div>
        <?php } ?>

        <?php if ($eh_lider) { ?>
            <section class="mb-4" id="avaliar">
                <h2 class="stream-section-title mb-1"><i class="fas fa-star me-2" style="color:#fbbf24;"></i>Avaliar devocionais</h2>
                <p class="text-muted small mb-3">Como líder, dê uma nota de 1 a 5. Com <?php echo devocional_avaliacoes_minimas(); ?> avaliações e média a partir de <?php echo str_replace('.', ',', (string) devocional_nota_minima()); ?>, o devocional entra sozinho na agenda (um por dia).</p>
                <?php if (!$para_avaliar) { ?>
                    <p class="text-muted">Nenhum devocional esperando avaliação.</p>
                <?php } ?>
                <?php foreach ($para_avaliar as $d) { ?>
                    <details class="devocional-avaliar">
                        <summary>
                            <span class="me-auto">
                                <strong><?php echo htmlspecialchars($d['titulo']); ?></strong>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($d['tema']); ?> · <?php echo htmlspecialchars($d['referencia']); ?> · por <?php echo htmlspecialchars($d['autor']); ?> · <?php echo $d['avaliacoes'] === 1 ? '1 avaliação' : $d['avaliacoes'] . ' avaliações'; ?></small>
                            </span>
                            <?php if ($d['minha_nota'] !== null) { ?>
                                <span class="devocional-minha-nota" title="Sua nota"><?php echo str_repeat('★', $d['minha_nota']) . str_repeat('☆', 5 - $d['minha_nota']); ?></span>
                            <?php } else { ?>
                                <span class="badge bg-warning text-dark">Avaliar</span>
                            <?php } ?>
                        </summary>
                        <div class="devocional-avaliar-corpo">
                            <blockquote class="devocional-base">
                                <?php if ($d['versiculo'] !== '') { ?><p><?php echo nl2br(htmlspecialchars($d['versiculo'])); ?></p><?php } ?>
                                <cite><?php echo htmlspecialchars($d['referencia']); ?></cite>
                            </blockquote>
                            <div class="devocional-texto"><?php echo nl2br(htmlspecialchars($d['texto'])); ?></div>
                            <form method="POST" action="devocional.php#avaliar" class="devocional-nota-form">
                                <?php echo csrf_campo(); ?>
                                <input type="hidden" name="acao" value="avaliar">
                                <input type="hidden" name="iddv_id" value="<?php echo $d['id']; ?>">
                                <fieldset class="devocional-estrelas">
                                    <legend class="visually-hidden">Nota de 1 a 5</legend>
                                    <?php for ($n = 5; $n >= 1; $n--) { ?>
                                        <input type="radio" id="nota<?php echo $d['id'] . '_' . $n; ?>" name="nota" value="<?php echo $n; ?>"<?php echo $d['minha_nota'] === $n ? ' checked' : ''; ?> required>
                                        <label for="nota<?php echo $d['id'] . '_' . $n; ?>" title="<?php echo $n; ?> de 5">★</label>
                                    <?php } ?>
                                </fieldset>
                                <button type="submit" class="btn btn-sm btn-play">Salvar nota</button>
                                <?php if ($d['minha_nota'] !== null) { ?>
                                    <button type="submit" name="nota" value="0" class="btn btn-sm btn-link text-muted" formnovalidate>Tirar minha nota</button>
                                <?php } ?>
                            </form>
                        </div>
                    </details>
                <?php } ?>
            </section>
        <?php } ?>

        <?php if ($meus) { ?>
            <section class="mb-4" id="meus">
                <h2 class="stream-section-title mb-3"><i class="fas fa-feather-pointed me-2"></i>Meus devocionais</h2>
                <ul class="devocionais-lista">
                    <?php foreach ($meus as $d) {
                        $publicado = $d['status'] === 'aprovado' && $d['data'] <= $hoje;
                        $info = $publicado ? array('Publicado', 'fa-circle-check', 'is-aprovado') : $status_meus[$d['status']];
                    ?>
                        <li>
                            <div class="me-auto">
                                <strong><?php echo htmlspecialchars($d['titulo']); ?></strong>
                                <small class="d-block text-muted">
                                    <?php if ($d['status'] === 'avaliacao') { ?>
                                        <?php echo $d['avaliacoes'] === 1 ? '1 avaliação até agora' : $d['avaliacoes'] . ' avaliações até agora'; ?>
                                    <?php } elseif ($d['status'] === 'aprovado') { ?>
                                        <?php echo $publicado ? 'Publicado em ' : 'Será publicado em '; ?><?php echo date('d/m/Y', strtotime($d['data'])); ?>
                                    <?php } elseif ($d['motivo'] !== '') { ?>
                                        Motivo: <?php echo htmlspecialchars($d['motivo']); ?>
                                    <?php } ?>
                                </small>
                            </div>
                            <span class="devocional-status <?php echo $info[2]; ?>"><i class="fas <?php echo $info[1]; ?> me-1" aria-hidden="true"></i><?php echo $info[0]; ?></span>
                            <?php if ($publicado) { ?>
                                <a href="devocional.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline-light">Ver</a>
                            <?php } elseif (in_array($d['status'], array('avaliacao', 'recusado'), true)) { ?>
                                <form method="POST" action="devocional.php" data-confirmar="Remover este devocional?">
                                    <?php echo csrf_campo(); ?>
                                    <input type="hidden" name="acao" value="remover">
                                    <input type="hidden" name="iddv_id" value="<?php echo $d['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-muted" title="Remover"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

        <?php if ($anteriores) { ?>
            <section class="mb-4" id="anteriores">
                <h2 class="stream-section-title mb-3"><i class="fas fa-clock-rotate-left me-2"></i>Devocionais anteriores</h2>
                <ul class="devocionais-lista">
                    <?php foreach ($anteriores as $d) { ?>
                        <li>
                            <a href="devocional.php?id=<?php echo $d['id']; ?>" class="me-auto devocional-link">
                                <strong><?php echo htmlspecialchars($d['titulo']); ?></strong>
                                <small class="d-block text-muted"><?php echo date('d/m', strtotime($d['data'])); ?> · <?php echo htmlspecialchars($d['tema']); ?> · <?php echo htmlspecialchars($d['referencia']); ?> · <?php echo htmlspecialchars($d['autor']); ?></small>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>
        <?php } ?>
    </main>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        // Contador do texto do devocional (quanto falta para o mínimo).
        (function () {
            var campo = document.getElementById('dvTexto');
            var contador = document.getElementById('dvContador');
            if (!campo || !contador) return;
            var min = parseInt(contador.dataset.min, 10);
            var max = parseInt(contador.dataset.max, 10);
            function atualizar() {
                var n = campo.value.trim().length;
                contador.textContent = n < min ? 'Faltam ' + (min - n) + ' caracteres para o mínimo' : n + ' / ' + max;
            }
            campo.addEventListener('input', atualizar);
            atualizar();
        })();
    </script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
