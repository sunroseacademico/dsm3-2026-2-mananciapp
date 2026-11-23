<?php
// Desafios (T31): o fiel entra nos desafios criados pelo admin, acompanha
// o progresso (contado sozinho com o que ele faz no app) e, nos de "marcar
// como feito", confirma o dia. Ver "DESAFIOS" em php/conexao.php e a seção
// 62 do bancosupabase.md.
include("../conexao.php");
auth_require();

$fiel_id = auth_resolve_fiel_id();
$disponivel = desafios_disponivel($conn);
$erro = '';
$mensagem = '';

if ($disponivel && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = site_entrada_texto($_POST['acao'] ?? '', false);
    $desafio_id = (int) ($_POST['idds_id'] ?? 0);
    if (!csrf_validar()) {
        $erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } elseif ($acao === 'entrar') {
        $erro = (string) desafio_entrar($conn, $fiel_id, $desafio_id);
        if ($erro === '') {
            desafios_verificar($conn, $fiel_id); // o que já fez hoje conta
            $_SESSION['desafios_ok'] = 'Você entrou no desafio. Vamos juntos! 💪';
        }
    } elseif ($acao === 'checkin') {
        $erro = (string) desafio_checkin($conn, $fiel_id, $desafio_id);
        if ($erro === '') {
            desafios_verificar($conn, $fiel_id);
            $_SESSION['desafios_ok'] = 'Feito! Hoje está marcado. ✅';
        }
    } elseif ($acao === 'desistir') {
        if (desafio_desistir($conn, $fiel_id, $desafio_id)) {
            $_SESSION['desafios_ok'] = 'Você saiu do desafio.';
        }
    }
    if ($erro === '') {
        header('Location: desafios.php#d-' . $desafio_id);
        exit;
    }
}
if (!empty($_SESSION['desafios_ok'])) {
    $mensagem = $_SESSION['desafios_ok'];
    unset($_SESSION['desafios_ok']);
}

// Também fecha os que chegaram à meta (e avisa) antes do menu ser montado.
$meus = $disponivel ? desafios_do_fiel($conn, $fiel_id) : array();
$abertos = $disponivel ? desafios_abertos($conn, $fiel_id) : array();
$hoje = site_hoje();
$metricas = desafios_metricas();

// Embaixo, os "desafios que valem medalha": os Primeiros passos (seção 80,
// missões fixas de boas-vindas) e as medalhas que ainda faltam, com o
// progresso. Concede antes do menu ser montado (aviso de medalha nova).
$pp_passos = primeiros_passos($conn, $fiel_id);
$pp_completo = primeiros_passos_resumo($pp_passos)['completo'];
$tem_medalhas = medalhas_disponivel($conn) && medalhas_listar($conn);
$medalhas_conquistadas = array();
$medalhas_progresso = array();
$ofensiva_atual = 0;
if ($tem_medalhas) {
    medalhas_avisar_novas(medalhas_conceder_atividade($conn, $fiel_id));
    $medalhas_conquistadas = medalhas_do_fiel($conn, $fiel_id);
    $medalhas_progresso = medalhas_contagens($conn, $fiel_id);
    $ofensiva_atual = ofensiva_resumo($conn, $fiel_id)['atual'];
}
$medalhas_faltando = $tem_medalhas ? count(medalhas_listar($conn)) - count($medalhas_conquistadas) : 0;

$meus_ativos = array_filter($meus, function ($m) { return in_array($m['progresso']['situacao'], array('andamento', 'aguardando'), true); });
$meus_historico = array_filter($meus, function ($m) { return !in_array($m['progresso']['situacao'], array('andamento', 'aguardando'), true); });

// "Termina hoje", "Termina amanhã", "Faltam 5 dias (até 12/10)".
function desafios_prazo_texto($fim, $hoje) {
    $dias = (int) round((strtotime($fim) - strtotime($hoje)) / 86400);
    if ($dias <= 0) {
        return 'Termina hoje';
    }
    if ($dias === 1) {
        return 'Termina amanhã';
    }
    return 'Faltam ' . ($dias + 1) . ' dias (até ' . date('d/m', strtotime($fim)) . ')';
}

function desafios_participantes_texto($d) {
    if ($d['participantes'] <= 0) {
        return 'Seja o primeiro a participar';
    }
    $texto = $d['participantes'] === 1 ? '1 participante' : $d['participantes'] . ' participantes';
    if ($d['concluiram'] > 0) {
        $texto .= ' · ' . ($d['concluiram'] === 1 ? '1 concluiu' : $d['concluiram'] . ' concluíram');
    }
    return $texto;
}

$icones = array('aulas' => 'fa-play-circle', 'dias_app' => 'fa-calendar-check', 'comentarios' => 'fa-comments', 'resumos' => 'fa-feather-pointed', 'amens' => 'fa-hands-praying', 'checkin' => 'fa-square-check');
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
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'desafios'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 1100px; margin: 0 auto;">
        <div class="streaming-page-header">
            <h1><i class="fas fa-flag-checkered me-2" style="color:#34d399;"></i>Desafios</h1>
            <p>Desafie-se a crescer junto com a igreja. O progresso é contado sozinho.</p>
        </div>

        <?php if ($mensagem !== '') { ?>
            <div class="alert alert-success" role="status"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensagem); ?></div>
        <?php } ?>
        <?php if ($erro !== '') { ?>
            <div class="alert alert-danger" role="alert"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($erro); ?></div>
        <?php } ?>

        <?php // Em cima, os desafios da igreja (criados pelo admin); embaixo, os que valem medalha. ?>
        <?php if (!$disponivel) { ?>
            <div class="streaming-empty-state">
                <i class="fas fa-flag-checkered"></i>
                <p class="mt-3 mb-0">Os desafios ainda não estão disponíveis.</p>
            </div>
        <?php } else { ?>

        <section class="mb-4" id="meus">
            <h2 class="stream-section-title mb-3"><i class="fas fa-person-running me-2"></i>Meus desafios</h2>
            <?php if (!$meus_ativos) { ?>
                <p class="text-muted"><?php echo $abertos ? 'Você não está em nenhum desafio agora. Escolha um abaixo!' : 'Você não está em nenhum desafio agora.'; ?></p>
            <?php } ?>
            <div class="desafios-grade">
                <?php foreach ($meus_ativos as $item) {
                    $d = $item['desafio'];
                    $p = $item['participacao'];
                    $g = $item['progresso'];
                    $m = $metricas[$d['metrica']];
                    $checkin_hoje = $d['metrica'] === 'checkin' && $g['situacao'] === 'andamento' && $g['hoje'] > 0;
                ?>
                    <article class="desafio-card is-meu" id="d-<?php echo $d['id']; ?>">
                        <div class="desafio-topo">
                            <span class="desafio-icone"><i class="fas <?php echo $icones[$d['metrica']] ?? 'fa-flag'; ?>" aria-hidden="true"></i></span>
                            <div>
                                <h3 class="desafio-titulo"><?php echo htmlspecialchars($d['titulo']); ?></h3>
                                <p class="desafio-meta"><?php echo htmlspecialchars($d['meta_texto']); ?></p>
                            </div>
                        </div>
                        <?php if ($d['descricao'] !== '') { ?>
                            <p class="desafio-descricao"><?php echo htmlspecialchars($d['descricao']); ?></p>
                        <?php } ?>

                        <?php if ($g['situacao'] === 'aguardando') { ?>
                            <p class="desafio-prazo"><i class="far fa-clock me-1"></i>Começa em <?php echo desafio_data_br($p['inicio']); ?></p>
                        <?php } else { ?>
                            <div class="desafio-progresso">
                                <div class="progress-bar-custom"><div class="progress-bar-custom-fill" style="width: <?php echo $g['percentual']; ?>%;"></div></div>
                                <small>
                                    <strong><?php echo $g['atual']; ?> de <?php echo $g['meta']; ?></strong>
                                    <?php echo $d['modo'] === 'diario' ? ($g['meta'] === 1 ? 'dia' : 'dias') : ($g['meta'] === 1 ? $m['um'] : $m['varios']); ?>
                                    <?php if ($d['modo'] === 'diario' && !desafio_metrica_diaria_unica($d['metrica'])) { ?>
                                        · hoje: <?php echo min($g['hoje'], $g['meta_dia']); ?> de <?php echo $g['meta_dia']; ?>
                                    <?php } ?>
                                </small>
                            </div>
                            <?php if ($g['dias'] && count($g['dias']) <= 31) { ?>
                                <ol class="desafio-dias" aria-label="Dias do desafio">
                                    <?php foreach ($g['dias'] as $data => $ok) {
                                        $classe = $ok ? 'is-feito' : ($data === $hoje ? 'is-hoje' : ($data < $hoje ? 'is-perdido' : ''));
                                        $rotulo = date('d/m', strtotime($data)) . ($ok ? ': feito' : ($data === $hoje ? ': hoje' : ($data < $hoje ? ': não cumprido' : '')));
                                    ?>
                                        <li class="<?php echo $classe; ?>" title="<?php echo $rotulo; ?>"><span class="visually-hidden"><?php echo $rotulo; ?></span><?php echo $ok ? '<i class="fas fa-check" aria-hidden="true"></i>' : ''; ?></li>
                                    <?php } ?>
                                </ol>
                            <?php } ?>
                            <p class="desafio-prazo">
                                <i class="far fa-clock me-1"></i><?php echo desafios_prazo_texto($p['fim'], $hoje); ?>
                                <?php if (!$g['possivel']) { ?>
                                    <span class="desafio-aviso"> · já não dá para completar todos os dias, mas continue firme!</span>
                                <?php } ?>
                            </p>
                        <?php } ?>

                        <div class="desafio-acoes">
                            <?php if ($d['metrica'] === 'checkin' && $g['situacao'] === 'andamento') { ?>
                                <?php if ($checkin_hoje) { ?>
                                    <span class="desafio-feito-hoje"><i class="fas fa-check-circle me-1"></i>Hoje já está marcado</span>
                                <?php } else { ?>
                                    <form method="POST" action="desafios.php">
                                        <?php echo csrf_campo(); ?>
                                        <input type="hidden" name="acao" value="checkin">
                                        <input type="hidden" name="idds_id" value="<?php echo $d['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-play"><i class="fas fa-check me-1"></i>Fiz hoje</button>
                                    </form>
                                <?php } ?>
                            <?php } ?>
                            <small class="text-muted me-auto"><?php echo htmlspecialchars(desafios_participantes_texto($d)); ?></small>
                            <form method="POST" action="desafios.php" data-confirmar="Sair deste desafio? O progresso dele se perde.">
                                <?php echo csrf_campo(); ?>
                                <input type="hidden" name="acao" value="desistir">
                                <input type="hidden" name="idds_id" value="<?php echo $d['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-link desafio-sair">Sair do desafio</button>
                            </form>
                        </div>
                    </article>
                <?php } ?>
            </div>
        </section>

        <section class="mb-4" id="abertos">
            <h2 class="stream-section-title mb-3"><i class="fas fa-church me-2"></i>Desafios da igreja</h2>
            <?php if (!$abertos) { ?>
                <p class="text-muted">Nenhum desafio novo no momento. Fique de olho: a igreja avisa quando sair um.</p>
            <?php } ?>
            <div class="desafios-grade">
                <?php foreach ($abertos as $d) { ?>
                    <article class="desafio-card" id="d-<?php echo $d['id']; ?>">
                        <div class="desafio-topo">
                            <span class="desafio-icone"><i class="fas <?php echo $icones[$d['metrica']] ?? 'fa-flag'; ?>" aria-hidden="true"></i></span>
                            <div>
                                <h3 class="desafio-titulo"><?php echo htmlspecialchars($d['titulo']); ?></h3>
                                <p class="desafio-meta"><?php echo htmlspecialchars($d['meta_texto']); ?></p>
                            </div>
                        </div>
                        <?php if ($d['descricao'] !== '') { ?>
                            <p class="desafio-descricao"><?php echo htmlspecialchars($d['descricao']); ?></p>
                        <?php } ?>
                        <p class="desafio-prazo">
                            <i class="far fa-calendar me-1"></i>
                            <?php if ($d['periodo'] === 'fixo') { ?>
                                <?php echo $hoje < $d['inicio'] ? 'Começa em ' . desafio_data_br($d['inicio']) : 'Em andamento até ' . desafio_data_br($d['fim']); ?>
                            <?php } else { ?>
                                Começa quando você entrar<?php echo $d['fim'] ? ' · inscrições até ' . desafio_data_br($d['fim']) : ''; ?>
                            <?php } ?>
                        </p>
                        <div class="desafio-acoes">
                            <small class="text-muted me-auto"><?php echo htmlspecialchars(desafios_participantes_texto($d)); ?></small>
                            <?php if ($d['fechado']) { ?>
                                <small class="text-muted"><?php echo htmlspecialchars($d['fechado']); ?></small>
                            <?php } else { ?>
                                <form method="POST" action="desafios.php">
                                    <?php echo csrf_campo(); ?>
                                    <input type="hidden" name="acao" value="entrar">
                                    <input type="hidden" name="idds_id" value="<?php echo $d['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-play"><i class="fas fa-flag-checkered me-1"></i>Participar</button>
                                </form>
                            <?php } ?>
                        </div>
                    </article>
                <?php } ?>
            </div>
        </section>

        <?php if ($meus_historico) { ?>
            <section class="mb-4" id="historico">
                <h2 class="stream-section-title mb-3"><i class="fas fa-trophy me-2" style="color:#facc15;"></i>Concluídos e encerrados</h2>
                <ul class="desafios-historico">
                    <?php foreach ($meus_historico as $item) {
                        $d = $item['desafio'];
                        $p = $item['participacao'];
                        $g = $item['progresso'];
                        $concluido = $g['situacao'] === 'concluido';
                    ?>
                        <li class="<?php echo $concluido ? 'is-concluido' : 'is-encerrado'; ?>" id="d-<?php echo $d['id']; ?>">
                            <i class="fas <?php echo $concluido ? 'fa-trophy' : 'fa-hourglass-end'; ?>" aria-hidden="true"></i>
                            <div>
                                <strong><?php echo htmlspecialchars($d['titulo']); ?></strong>
                                <small class="d-block text-muted">
                                    <?php echo htmlspecialchars($d['meta_texto']); ?> ·
                                    <?php echo $concluido
                                        ? 'concluído em ' . desafio_data_br($p['concluido_em'] ?: $hoje)
                                        : 'encerrado em ' . desafio_data_br($p['fim']) . ' com ' . $g['atual'] . ' de ' . $g['meta']; ?>
                                </small>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>
        <?php } ?>

        <?php if ($pp_passos || $tem_medalhas) { ?>
            <section class="mt-5" id="medalhas">
                <h2 class="stream-section-title mb-1"><i class="fas fa-medal me-2" style="color:#facc15;"></i>Desafios que valem medalha</h2>
                <p class="text-muted mb-3">Conquistas para fazer no seu ritmo. Cada uma vira medalha no seu <a href="perfil.php#medalhas">perfil</a>.</p>

                <?php if (!$pp_completo) { include __DIR__ . '/includes/primeiros_passos.php'; } ?>

                <?php if ($tem_medalhas) { ?>
                    <?php if ($medalhas_faltando > 0) {
                        $medalhas_grade = array('catalogo' => medalhas_listar($conn), 'conquistadas' => $medalhas_conquistadas, 'ofensiva_atual' => $ofensiva_atual, 'progresso' => $medalhas_progresso, 'modo' => 'bloqueadas', 'raridade' => medalhas_raridade($conn));
                        include __DIR__ . '/includes/medalhas_grade.php';
                    } else { ?>
                        <p class="mb-0"><i class="fas fa-trophy me-2" style="color:#facc15;"></i>Você conquistou todas as medalhas! 🎉</p>
                    <?php } ?>
                <?php } ?>

                <?php // Completos: os Primeiros passos descem para o fim (só o "Tudo pronto"). ?>
                <?php if ($pp_completo) { ?><div class="mt-4"><?php include __DIR__ . '/includes/primeiros_passos.php'; ?></div><?php } ?>
            </section>
        <?php } ?>
    </main>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
