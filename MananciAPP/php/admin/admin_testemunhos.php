<?php
// Moderação do mural de testemunhos (T26): aprovar, recusar (com motivo,
// que o autor vê) ou tirar do ar. O autor é avisado ao aprovar e ao
// recusar. Ver "MURAL DE TESTEMUNHOS" em php/conexao.php e a seção 57 do
// bancosupabase.md.
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));
// Ações que deram certo vão para o Registro de atividade (admin_log_ao_redirecionar()).
admin_log_ao_redirecionar($conn, 'testemunhos');

$disponivel = testemunhos_disponivel($conn);
$erro = '';
$mensagem = '';
$abas = array(
    'pendente' => array('Pendentes', 'fa-hourglass-half'),
    'aprovado' => array('Publicados', 'fa-circle-check'),
    'recusado' => array('Recusados', 'fa-circle-xmark'),
    'removido' => array('Removidos', 'fa-trash-alt'),
);
$aba = isset($abas[site_entrada_texto($_GET['status'] ?? '')]) ? site_entrada_texto($_GET['status']) : 'pendente';

if ($disponivel && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validar()) {
        $erro = 'Sessão expirada ou formulário inválido. Atualize a página e tente de novo.';
    } else {
        $acao = site_entrada_texto($_POST['acao'] ?? '', false);
        $erro = (string) testemunho_moderar($conn, (int) ($_POST['idts_id'] ?? 0), auth_resolve_fiel_id(), $acao, site_entrada_texto($_POST['motivo'] ?? '', false));
        if ($erro === '') {
            $textos = array('aprovar' => 'Testemunho publicado e autor avisado.', 'recusar' => 'Testemunho recusado e autor avisado.', 'remover' => 'Testemunho tirado do mural.');
            $_SESSION['admin_testemunhos_ok'] = $textos[$acao] ?? 'Feito.';
            header('Location: admin_testemunhos.php?status=' . $aba);
            exit;
        }
    }
}
if (!empty($_SESSION['admin_testemunhos_ok'])) {
    $mensagem = $_SESSION['admin_testemunhos_ok'];
    unset($_SESSION['admin_testemunhos_ok']);
}

$contagem = $disponivel ? testemunhos_admin_contar($conn) : array();
$lista = $disponivel ? testemunhos_admin_listar($conn, $aba) : array();

function admin_testemunhos_data($ts) {
    return (new DateTime('@' . (int) $ts))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i');
}
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
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'testemunhos', 'Testemunhos', 'fa-hands-praying'); ?>

    <p class="text-muted mb-4">Os testemunhos enviados pelos fiéis só aparecem no mural depois de aprovados aqui. O autor recebe um aviso quando o testemunho é publicado ou recusado (com o motivo).</p>

    <?php if ($mensagem !== '') { ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensagem); ?></div>
    <?php } ?>
    <?php if ($erro !== '') { ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($erro); ?></div>
    <?php } ?>

    <?php if (!$disponivel) { ?>
        <div class="alert alert-warning">
            O mural de testemunhos ainda não existe no banco. Rode a seção 57 do
            <code>database/bancosupabase.md</code> no Supabase para liberar esta tela.
        </div>
    <?php } else { ?>

    <ul class="nav nav-pills admin-testemunhos-abas mb-3">
        <?php foreach ($abas as $chave => $info) { ?>
            <li class="nav-item">
                <a class="nav-link<?php echo $chave === $aba ? ' active' : ''; ?>" href="admin_testemunhos.php?status=<?php echo $chave; ?>"<?php echo $chave === $aba ? ' aria-current="page"' : ''; ?>>
                    <i class="fas <?php echo $info[1]; ?> me-1"></i><?php echo $info[0]; ?>
                    <span class="badge <?php echo $chave === 'pendente' && $contagem[$chave] > 0 ? 'bg-danger' : 'bg-secondary'; ?> ms-1"><?php echo (int) $contagem[$chave]; ?></span>
                </a>
            </li>
        <?php } ?>
    </ul>

    <?php if (!$lista) { ?>
        <div class="admin-card text-center text-muted py-5">
            <?php echo $aba === 'pendente' ? 'Nenhum testemunho esperando aprovação.' : 'Nada por aqui.'; ?>
        </div>
    <?php } ?>

    <?php foreach ($lista as $t) { ?>
        <div class="admin-card admin-testemunho">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($t['titulo']); ?></h5>
                    <small class="text-muted">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($t['nome']); ?>
                        <?php if ($t['anonimo']) { ?><span class="badge bg-secondary ms-1" title="No mural aparece como Anônimo">Pede anonimato</span><?php } ?>
                        · enviado em <?php echo admin_testemunhos_data($t['criado_ts']); ?>
                        <?php if ($aba === 'aprovado') { ?> · <i class="fas fa-hands-praying ms-1 me-1"></i><?php echo (int) $t['votos']; ?> voto(s)<?php } ?>
                    </small>
                </div>
            </div>
            <p class="admin-testemunho-texto mb-3"><?php echo htmlspecialchars($t['texto']); ?></p>
            <?php if ($aba === 'recusado' && $t['motivo'] !== '') { ?>
                <p class="small mb-3" style="color:#f59e0b;"><i class="fas fa-circle-info me-1"></i>Motivo da recusa: <?php echo htmlspecialchars($t['motivo']); ?></p>
            <?php } ?>

            <div class="d-flex flex-wrap gap-2 align-items-start">
                <?php if ($aba === 'pendente' || $aba === 'recusado') { ?>
                    <form method="POST" action="admin_testemunhos.php?status=<?php echo $aba; ?>">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="aprovar">
                        <input type="hidden" name="idts_id" value="<?php echo (int) $t['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i><?php echo $aba === 'recusado' ? 'Publicar mesmo assim' : 'Aprovar e publicar'; ?></button>
                    </form>
                <?php } ?>
                <?php if ($aba === 'pendente') { ?>
                    <form method="POST" action="admin_testemunhos.php?status=pendente" class="admin-testemunho-recusar d-flex flex-wrap gap-2">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="recusar">
                        <input type="hidden" name="idts_id" value="<?php echo (int) $t['id']; ?>">
                        <input type="text" name="motivo" class="form-control form-control-sm custom-input" maxlength="255" required placeholder="Motivo da recusa (o autor vai ver)" aria-label="Motivo da recusa">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times me-1"></i>Recusar</button>
                    </form>
                <?php } ?>
                <?php if ($aba === 'aprovado') { ?>
                    <form method="POST" action="admin_testemunhos.php?status=aprovado" data-confirmar="Tirar este testemunho do mural?">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="remover">
                        <input type="hidden" name="idts_id" value="<?php echo (int) $t['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-eye-slash me-1"></i>Tirar do mural</button>
                    </form>
                    <a href="../fiel/testemunhos.php#t-<?php echo (int) $t['id']; ?>" class="btn btn-sm btn-outline-light" target="_blank" rel="noopener"><i class="fas fa-external-link-alt me-1"></i>Ver no mural</a>
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
