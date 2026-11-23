<?php
// Devocionais (T33): regra da agenda automática (nota mínima e número de
// avaliações), devocionais em avaliação, agenda, publicados e recusados.
// O admin aprova direto, recusa (com motivo), troca a data ou tira do ar.
// Ver "DEVOCIONAIS ENVIADOS PELOS FIÉIS" em php/conexao.php e a seção 63 do
// bancosupabase.md.
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));
// Ações que deram certo vão para o Registro de atividade (admin_log_ao_redirecionar()).
admin_log_ao_redirecionar($conn, 'devocionais');

$disponivel = devocionais_disponivel($conn);
$erro = '';
$mensagem = '';
$abas = array(
    'avaliacao' => array('Em avaliação', 'fa-hourglass-half'),
    'agendado' => array('Agenda', 'fa-calendar-days'),
    'publicado' => array('Publicados', 'fa-circle-check'),
    'recusado' => array('Recusados', 'fa-circle-xmark'),
    'removido' => array('Removidos', 'fa-trash-alt'),
);
$aba = isset($abas[site_entrada_texto($_GET['aba'] ?? '')]) ? site_entrada_texto($_GET['aba']) : 'avaliacao';

if ($disponivel && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = site_entrada_texto($_POST['acao'] ?? '', false);
    if (!csrf_validar()) {
        $erro = 'Sessão expirada ou formulário inválido. Atualize a página e tente de novo.';
    } elseif ($acao === 'regra') {
        $nota = (float) str_replace(',', '.', site_entrada_texto($_POST['nota_minima'] ?? '', false));
        $avaliacoes = (int) ($_POST['avaliacoes_minimas'] ?? 0);
        if ($nota < 1 || $nota > 5) {
            $erro = 'A nota mínima precisa ser de 1 a 5.';
        } elseif ($avaliacoes < 1 || $avaliacoes > 50) {
            $erro = 'O número de avaliações precisa ser de 1 a 50.';
        } else {
            site_config_salvar($conn, 'devocional_nota_minima', (string) round($nota, 1));
            site_config_salvar($conn, 'devocional_avaliacoes_minimas', (string) $avaliacoes);
            $_SESSION['admin_devocionais_ok'] = 'Regra da agenda atualizada (vale para as próximas avaliações).';
        }
    } else {
        $erro = (string) devocional_moderar($conn, (int) ($_POST['iddv_id'] ?? 0), $acao, site_entrada_texto($_POST['motivo'] ?? '', false), site_entrada_texto($_POST['data'] ?? '', false));
        if ($erro === '') {
            $textos = array('aprovar' => 'Devocional aprovado e agendado; o autor foi avisado.', 'recusar' => 'Devocional recusado e autor avisado.', 'remover' => 'Devocional tirado do ar.', 'reavaliar' => 'Devocional voltou para a avaliação.', 'data' => 'Data alterada.');
            $_SESSION['admin_devocionais_ok'] = $textos[$acao] ?? 'Feito.';
        }
    }
    if ($erro === '') {
        header('Location: admin_devocionais.php?aba=' . $aba);
        exit;
    }
}
if (!empty($_SESSION['admin_devocionais_ok'])) {
    $mensagem = $_SESSION['admin_devocionais_ok'];
    unset($_SESSION['admin_devocionais_ok']);
}

$contagem = $disponivel ? devocionais_admin_contar($conn) : array();
$lista = $disponivel ? devocionais_admin_listar($conn, $aba) : array();
$hoje = site_hoje();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Devocionais</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'devocionais', 'Devocionais', 'fa-book-bible'); ?>

    <p class="text-muted mb-4">Os fiéis enviam devocionais e os líderes (equipe e funções de liderança) dão nota de 1 a 5. Quem passa na regra abaixo entra sozinho na agenda — um devocional por dia, na próxima data livre — e o autor é avisado.</p>

    <?php if ($mensagem !== '') { ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensagem); ?></div>
    <?php } ?>
    <?php if ($erro !== '') { ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($erro); ?></div>
    <?php } ?>

    <?php if (!$disponivel) { ?>
        <div class="alert alert-warning">
            Os devocionais ainda não existem no banco. Rode a seção 63 do
            <code>database/bancosupabase.md</code> no Supabase para liberar esta tela.
        </div>
    <?php } else { ?>

    <form method="POST" action="admin_devocionais.php?aba=<?php echo $aba; ?>" class="admin-card mb-4 row g-2 align-items-end">
        <?php echo csrf_campo(); ?>
        <input type="hidden" name="acao" value="regra">
        <div class="col-12"><strong><i class="fas fa-sliders me-2"></i>Regra da agenda automática</strong></div>
        <div class="col-md-3">
            <label class="form-label small" for="rAvaliacoes">Avaliações mínimas</label>
            <input type="number" class="form-control form-control-sm custom-input" id="rAvaliacoes" name="avaliacoes_minimas" min="1" max="50" value="<?php echo devocional_avaliacoes_minimas(); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small" for="rNota">Média mínima (1 a 5)</label>
            <input type="number" class="form-control form-control-sm custom-input" id="rNota" name="nota_minima" min="1" max="5" step="0.1" value="<?php echo devocional_nota_minima(); ?>">
        </div>
        <div class="col-md-2"><button type="submit" class="btn btn-sm btn-success w-100">Salvar regra</button></div>
    </form>

    <ul class="nav nav-pills admin-testemunhos-abas mb-3">
        <?php foreach ($abas as $chave => $info) { ?>
            <li class="nav-item">
                <a class="nav-link<?php echo $chave === $aba ? ' active' : ''; ?>" href="admin_devocionais.php?aba=<?php echo $chave; ?>">
                    <i class="fas <?php echo $info[1]; ?> me-1"></i><?php echo $info[0]; ?>
                    <span class="badge <?php echo $chave === 'avaliacao' && $contagem[$chave] > 0 ? 'bg-warning text-dark' : 'bg-secondary'; ?> ms-1"><?php echo (int) $contagem[$chave]; ?></span>
                </a>
            </li>
        <?php } ?>
    </ul>

    <?php if ($aba === 'agendado' && $contagem['agendado'] === 0) { ?>
        <div class="alert alert-warning"><i class="fas fa-calendar-xmark me-2"></i>A agenda está vazia: depois de hoje, o app repete o último devocional publicado até um novo ser aprovado.</div>
    <?php } ?>

    <?php if (!$lista) { ?>
        <div class="admin-card text-center text-muted py-5">Nada por aqui.</div>
    <?php } ?>

    <?php foreach ($lista as $d) { ?>
        <div class="admin-card admin-testemunho">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($d['titulo']); ?></h5>
                    <small class="text-muted">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($d['autor']); ?> ·
                        <?php echo htmlspecialchars($d['tema']); ?> · <?php echo htmlspecialchars($d['referencia']); ?>
                        <?php if ($d['data']) { ?> · <strong><i class="far fa-calendar ms-1 me-1"></i><?php echo date('d/m/Y', strtotime($d['data'])); ?><?php echo $d['data'] === $hoje ? ' (hoje)' : ''; ?></strong><?php } ?>
                    </small>
                </div>
                <div class="text-end small">
                    <?php if ($d['media'] !== null) { ?>
                        <span style="color:#fbbf24;">★</span> <?php echo str_replace('.', ',', (string) $d['media']); ?> <span class="text-muted">(<?php echo $d['avaliacoes']; ?> avaliação<?php echo $d['avaliacoes'] === 1 ? '' : 'ões'; ?>)</span>
                    <?php } else { ?>
                        <span class="text-muted">Sem avaliações</span>
                    <?php } ?>
                </div>
            </div>
            <details class="mb-3">
                <summary class="small">Ler o devocional</summary>
                <?php if ($d['versiculo'] !== '') { ?><p class="small fst-italic mt-2 mb-1">"<?php echo nl2br(htmlspecialchars($d['versiculo'])); ?>"</p><?php } ?>
                <p class="admin-testemunho-texto mt-2 mb-0"><?php echo htmlspecialchars($d['texto']); ?></p>
            </details>
            <?php if ($aba === 'recusado' && $d['motivo'] !== '') { ?>
                <p class="small mb-3" style="color:#f59e0b;"><i class="fas fa-circle-info me-1"></i>Motivo: <?php echo htmlspecialchars($d['motivo']); ?></p>
            <?php } ?>

            <div class="d-flex flex-wrap gap-2 align-items-start">
                <?php if ($aba === 'avaliacao' || $aba === 'recusado') { ?>
                    <form method="POST" action="admin_devocionais.php?aba=<?php echo $aba; ?>">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="aprovar">
                        <input type="hidden" name="iddv_id" value="<?php echo $d['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-calendar-plus me-1"></i>Aprovar e agendar</button>
                    </form>
                <?php } ?>
                <?php if ($aba === 'avaliacao' || $aba === 'agendado') { ?>
                    <form method="POST" action="admin_devocionais.php?aba=<?php echo $aba; ?>" class="admin-testemunho-recusar d-flex flex-wrap gap-2">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="recusar">
                        <input type="hidden" name="iddv_id" value="<?php echo $d['id']; ?>">
                        <input type="text" name="motivo" class="form-control form-control-sm custom-input" maxlength="255" required placeholder="Motivo da recusa (o autor vai ver)" aria-label="Motivo da recusa">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times me-1"></i>Recusar</button>
                    </form>
                <?php } ?>
                <?php if ($aba === 'agendado') { ?>
                    <form method="POST" action="admin_devocionais.php?aba=agendado" class="d-flex gap-2">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="data">
                        <input type="hidden" name="iddv_id" value="<?php echo $d['id']; ?>">
                        <input type="date" name="data" class="form-control form-control-sm custom-input" min="<?php echo $hoje; ?>" value="<?php echo htmlspecialchars((string) $d['data']); ?>" aria-label="Nova data">
                        <button type="submit" class="btn btn-sm btn-outline-light">Trocar data</button>
                    </form>
                    <form method="POST" action="admin_devocionais.php?aba=agendado">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="reavaliar">
                        <input type="hidden" name="iddv_id" value="<?php echo $d['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas fa-rotate-left me-1"></i>Voltar para avaliação</button>
                    </form>
                <?php } ?>
                <?php if ($aba === 'publicado' || $aba === 'agendado') { ?>
                    <form method="POST" action="admin_devocionais.php?aba=<?php echo $aba; ?>" data-confirmar="Tirar este devocional do ar?">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="remover">
                        <input type="hidden" name="iddv_id" value="<?php echo $d['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-eye-slash me-1"></i>Tirar do ar</button>
                    </form>
                <?php } ?>
                <?php if ($aba === 'publicado') { ?>
                    <a href="../fiel/devocional.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline-light" target="_blank" rel="noopener"><i class="fas fa-external-link-alt me-1"></i>Ver no app</a>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
    <?php } ?>

    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
