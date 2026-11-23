<?php
// Mural de testemunhos (T26): o fiel envia o testemunho (vai para a
// moderação do admin), acompanha os seus e vota nos publicados. Ver
// "MURAL DE TESTEMUNHOS" em php/conexao.php e a seção 57 do bancosupabase.md.
include("../conexao.php");
auth_require();

$fiel_id = auth_resolve_fiel_id();
$disponivel = testemunhos_disponivel($conn);
$erro = '';
$mensagem = '';
$form = array('titulo' => '', 'texto' => '', 'anonimo' => false);

if ($disponivel && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = site_entrada_texto($_POST['acao'] ?? '', false);
    if (!csrf_validar()) {
        $erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } elseif ($acao === 'enviar') {
        $form = array(
            'titulo' => site_entrada_texto($_POST['titulo'] ?? '', false),
            'texto' => site_entrada_texto($_POST['texto'] ?? '', false),
            'anonimo' => isset($_POST['anonimo']),
        );
        $erro = (string) testemunho_enviar($conn, $fiel_id, $form['titulo'], $form['texto'], $form['anonimo']);
        if ($erro === '') {
            $_SESSION['testemunhos_ok'] = 'Testemunho enviado! A equipe vai revisar e, quando for publicado, você recebe um aviso.';
            header('Location: testemunhos.php#meus');
            exit;
        }
    } elseif ($acao === 'remover') {
        if (testemunho_remover_proprio($conn, (int) ($_POST['idts_id'] ?? 0), $fiel_id)) {
            $_SESSION['testemunhos_ok'] = 'Testemunho removido.';
        }
        header('Location: testemunhos.php#meus');
        exit;
    }
}
if (!empty($_SESSION['testemunhos_ok'])) {
    $mensagem = $_SESSION['testemunhos_ok'];
    unset($_SESSION['testemunhos_ok']);
}

$ordem = ($_GET['ordem'] ?? '') === 'recentes' ? 'recentes' : 'votados';
// "Ver mais" aumenta o tanto mostrado, de página em página.
$mostrar = max(TESTEMUNHOS_POR_PAGINA, min(240, (int) ($_GET['mostrar'] ?? TESTEMUNHOS_POR_PAGINA)));
$publicados = $disponivel ? testemunhos_listar($conn, $ordem, $mostrar + 1) : array();
$tem_mais = count($publicados) > $mostrar;
$publicados = array_slice($publicados, 0, $mostrar);
$total_publicados = $disponivel ? testemunhos_contar_publicados($conn) : 0;
$meus = $disponivel ? testemunhos_do_fiel($conn, $fiel_id) : array();
$form_aberto = $erro !== '' || isset($_GET['enviar']);

$status_info = array(
    'pendente' => array('Esperando aprovação', 'fa-hourglass-half', 'is-pendente'),
    'aprovado' => array('Publicado', 'fa-circle-check', 'is-aprovado'),
    'recusado' => array('Não publicado', 'fa-circle-xmark', 'is-recusado'),
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Testemunhos</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'testemunhos'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 1100px; margin: 0 auto;" data-testemunhos data-csrf="<?php echo htmlspecialchars(csrf_token()); ?>">
        <div class="streaming-page-header d-flex justify-content-between align-items-end flex-wrap gap-3">
            <div>
                <h1><i class="fas fa-hands-praying me-2" style="color:#fbbf24;"></i>Testemunhos</h1>
                <p>O que Deus tem feito na vida da nossa comunidade</p>
            </div>
            <?php if ($disponivel) { ?>
                <button type="button" class="btn btn-play" data-bs-toggle="collapse" data-bs-target="#enviar" aria-expanded="<?php echo $form_aberto ? 'true' : 'false'; ?>" aria-controls="enviar">
                    <i class="fas fa-pen-to-square me-2"></i>Contar meu testemunho
                </button>
            <?php } ?>
        </div>

        <?php if ($mensagem !== '') { ?>
            <div class="alert alert-success" role="status"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensagem); ?></div>
        <?php } ?>

        <?php if (!$disponivel) { ?>
            <div class="streaming-empty-state">
                <i class="fas fa-hands-praying"></i>
                <p class="mt-3 mb-0">O mural de testemunhos ainda não está disponível.</p>
            </div>
        <?php } else { ?>

        <div class="collapse <?php echo $form_aberto ? 'show' : ''; ?>" id="enviar">
            <div class="streaming-info-card text-start mb-4">
                <h2 class="h6 mb-1"><i class="fas fa-pen-to-square me-2" style="color: var(--primary-light);"></i>Contar meu testemunho</h2>
                <p class="text-muted small mb-3">Antes de aparecer no mural, a equipe da igreja lê e aprova. Você recebe um aviso quando for publicado.</p>
                <?php if ($erro !== '') { ?>
                    <div class="auth-erro mb-3" role="alert"><?php echo htmlspecialchars($erro); ?></div>
                <?php } ?>
                <form method="POST" action="testemunhos.php#enviar" class="testemunho-form">
                    <?php echo csrf_campo(); ?>
                    <input type="hidden" name="acao" value="enviar">
                    <div class="mb-3">
                        <label class="form-label" for="testemunhoTitulo">Título</label>
                        <input type="text" class="form-control custom-input" id="testemunhoTitulo" name="titulo" required minlength="3" maxlength="<?php echo TESTEMUNHO_TITULO_MAX; ?>" placeholder="Ex.: Deus restaurou minha família" value="<?php echo htmlspecialchars($form['titulo']); ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="testemunhoTexto">Seu testemunho</label>
                        <textarea class="form-control custom-input" id="testemunhoTexto" name="texto" rows="6" required minlength="<?php echo TESTEMUNHO_TEXTO_MIN; ?>" maxlength="<?php echo TESTEMUNHO_TEXTO_MAX; ?>" placeholder="Conte o que aconteceu e como Deus agiu."><?php echo htmlspecialchars($form['texto']); ?></textarea>
                        <small class="text-muted d-block text-end mt-1"><span id="testemunhoContador">0</span>/<?php echo TESTEMUNHO_TEXTO_MAX; ?></small>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="testemunhoAnonimo" name="anonimo" <?php echo $form['anonimo'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="testemunhoAnonimo">Publicar sem o meu nome (aparece como "Anônimo"; a equipe ainda vê quem enviou)</label>
                    </div>
                    <button type="submit" class="btn btn-play"><i class="fas fa-paper-plane me-2"></i>Enviar para aprovação</button>
                </form>
            </div>
        </div>

        <?php if ($meus) { ?>
            <section class="mb-4" id="meus">
                <h2 class="testemunhos-secao-titulo">Meus testemunhos</h2>
                <ul class="testemunhos-meus">
                    <?php foreach ($meus as $t) { $info = $status_info[$t['status']] ?? $status_info['pendente']; ?>
                        <li>
                            <div class="flex-grow-1 min-w-0">
                                <strong class="d-block text-truncate"><?php echo htmlspecialchars($t['titulo']); ?></strong>
                                <span class="testemunho-status <?php echo $info[2]; ?>"><i class="fas <?php echo $info[1]; ?> me-1"></i><?php echo $info[0]; ?></span>
                                <?php if ($t['status'] === 'aprovado') { ?>
                                    <small class="text-muted ms-2"><i class="fas fa-hands-praying me-1"></i><?php echo (int) $t['votos']; ?> · <a href="#t-<?php echo (int) $t['id']; ?>">ver no mural</a></small>
                                <?php } ?>
                                <?php if ($t['status'] === 'recusado' && $t['motivo'] !== '') { ?>
                                    <small class="d-block text-muted mt-1">Motivo: <?php echo htmlspecialchars($t['motivo']); ?></small>
                                <?php } ?>
                            </div>
                            <form method="POST" action="testemunhos.php" data-confirmar="Remover este testemunho? Ele sai do mural e não pode ser recuperado.">
                                <?php echo csrf_campo(); ?>
                                <input type="hidden" name="acao" value="remover">
                                <input type="hidden" name="idts_id" value="<?php echo (int) $t['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-light" title="Remover"><i class="fas fa-trash-alt"></i></button>
                            </form>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

        <section id="mural">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h2 class="testemunhos-secao-titulo mb-0">Mural <span class="text-muted">(<?php echo $total_publicados; ?>)</span></h2>
                <div class="video-comentarios-ordens mb-0" role="tablist" aria-label="Ordenar testemunhos">
                    <a href="testemunhos.php?ordem=votados#mural" role="tab" class="video-comentarios-ordem<?php echo $ordem === 'votados' ? ' is-ativa' : ''; ?>" aria-selected="<?php echo $ordem === 'votados' ? 'true' : 'false'; ?>">Mais votados</a>
                    <a href="testemunhos.php?ordem=recentes#mural" role="tab" class="video-comentarios-ordem<?php echo $ordem === 'recentes' ? ' is-ativa' : ''; ?>" aria-selected="<?php echo $ordem === 'recentes' ? 'true' : 'false'; ?>">Mais recentes</a>
                </div>
            </div>
            <?php if (!$publicados) { ?>
                <div class="streaming-empty-state">
                    <i class="fas fa-hands-praying"></i>
                    <p class="mt-3 mb-0">Ainda não há testemunhos publicados. Que tal ser o primeiro?</p>
                </div>
            <?php } else { ?>
                <div class="testemunhos-grade">
                    <?php foreach ($publicados as $card_testemunho) { $card_testemunho_resumo = false; include __DIR__ . '/includes/card_testemunho.php'; } ?>
                </div>
                <?php if ($tem_mais) { ?>
                    <div class="text-center mt-4">
                        <a href="testemunhos.php?ordem=<?php echo $ordem; ?>&mostrar=<?php echo $mostrar + TESTEMUNHOS_POR_PAGINA; ?>#t-<?php echo (int) end($publicados)['id']; ?>" class="btn btn-outline-light">Ver mais testemunhos</a>
                    </div>
                <?php } ?>
            <?php } ?>
        </section>
        <?php } ?>
    </main>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <script src="../../js/testemunhos.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        // Contador de caracteres do testemunho.
        (function () {
            var campo = document.getElementById('testemunhoTexto');
            var contador = document.getElementById('testemunhoContador');
            if (!campo || !contador) return;
            function atualizar() { contador.textContent = campo.value.length; }
            campo.addEventListener('input', atualizar);
            atualizar();
        })();
    </script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
