<?php
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));

$usuario_id = isset($_SESSION['Usuario_id']) ? (int) $_SESSION['Usuario_id'] : 0;
$notificacoes = array();
$total_notificacoes = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && csrf_validar()) {
    $acao = site_entrada_texto($_POST['acao']);

    if ($acao === 'excluir_notificacao') {
        $notificacao_id = isset($_POST['notificacao_id']) ? (int) $_POST['notificacao_id'] : 0;
        if ($notificacao_id > 0 && $usuario_id > 0) {
            $sql_delete = "DELETE FROM ID_NOTIFICACAO WHERE IDN_ID = $notificacao_id AND IDF_ID = $usuario_id LIMIT 1";
            if (mysqli_query($conn, $sql_delete)) {
                header('Location: notificacoes.php');
                exit;
            }
        }
    } elseif ($acao === 'excluir_todas_notificacoes') {
        if ($usuario_id > 0) {
            $sql_delete_all = "DELETE FROM ID_NOTIFICACAO WHERE IDF_ID = $usuario_id";
            if (mysqli_query($conn, $sql_delete_all)) {
                header('Location: notificacoes.php');
                exit;
            }
        }
    }
}

if ($usuario_id > 0) {
    $sql_notificacoes = "SELECT IDN_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LIDA, IDN_CRIADO_EM, IDN_LINK FROM ID_NOTIFICACAO WHERE IDF_ID = $usuario_id ORDER BY IDN_CRIADO_EM DESC LIMIT 100";
    $res_notificacoes = mysqli_query($conn, $sql_notificacoes);
    if ($res_notificacoes && mysqli_num_rows($res_notificacoes) > 0) {
        while ($row = mysqli_fetch_assoc($res_notificacoes)) {
            $notificacoes[] = $row;
        }
    }
    $total_notificacoes = count($notificacoes);
}

$total_nao_lidas = 0;
foreach ($notificacoes as $notificacao) {
    if ((int) ($notificacao['IDN_LIDA'] ?? 0) === 0) {
        $total_nao_lidas++;
    }
}

$tipos_info = array(
    'info' => array('icone' => 'fa-circle-info', 'cor' => '#3b82f6', 'label' => 'Informação'),
    'sucesso' => array('icone' => 'fa-circle-check', 'cor' => '#10b981', 'label' => 'Sucesso'),
    'aviso' => array('icone' => 'fa-triangle-exclamation', 'cor' => '#f59e0b', 'label' => 'Aviso'),
    'erro' => array('icone' => 'fa-circle-exclamation', 'cor' => '#ef4444', 'label' => 'Erro'),
    'resposta_acesso' => array('icone' => 'fa-user-check', 'cor' => '#3b82f6', 'label' => 'Acesso'),
    'promocao' => array('icone' => 'fa-star', 'cor' => '#a855f7', 'label' => 'Promoção'),
    'permissao_alterada' => array('icone' => 'fa-user-shield', 'cor' => '#6366f1', 'label' => 'Permissão'),
    'certificado_boas_vindas' => array('icone' => 'fa-award', 'cor' => '#f59e0b', 'label' => 'Certificado'),
);

function notificacao_info($tipo, $tipos_info) {
    if (isset($tipos_info[$tipo])) {
        return $tipos_info[$tipo];
    }
    $label = str_replace('_', ' ', $tipo);
    $label = mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
    return array('icone' => 'fa-bell', 'cor' => '#94a3b8', 'label' => $label);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificações - Admin</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
    <style>
        .notif-header {
            background: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.75rem;
        }
        .notif-header .notif-subtitle {
            color: var(--text-muted);
            font-size: 0.92rem;
        }
        .notif-action-btn {
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 0.55rem 1.1rem;
            transition: all 0.2s ease;
        }
        .notif-action-btn:hover {
            transform: translateY(-1px);
        }
        .notif-card {
            background: var(--auth-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            position: relative;
        }
        .notif-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.18);
        }
        .notif-card.is-unread {
            border-color: var(--notif-color, var(--primary-light));
            box-shadow: 0 0 0 1px color-mix(in srgb, var(--notif-color, var(--primary-light)) 25%, transparent);
        }
        .notif-card.is-read {
            opacity: 0.72;
        }
        .notif-icon {
            width: 46px;
            height: 46px;
            min-width: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            background: color-mix(in srgb, var(--notif-color) 16%, transparent);
            color: var(--notif-color);
        }
        .notif-body {
            flex: 1;
            min-width: 0;
        }
        .notif-title-row {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        .notif-title-row h5 {
            margin: 0;
            font-size: 1.02rem;
            font-weight: 700;
        }
        .notif-badge-new {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            background: var(--notif-color);
            color: #fff;
        }
        .notif-type-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.28rem 0.65rem;
            border-radius: 999px;
            background: color-mix(in srgb, var(--notif-color) 14%, transparent);
            color: var(--notif-color);
            white-space: nowrap;
        }
        .notif-message {
            color: var(--text-muted);
            font-size: 0.92rem;
            margin: 0.4rem 0 0.5rem;
            line-height: 1.5;
        }
        .notif-meta {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-muted);
            font-size: 0.78rem;
        }
        .notif-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }
        .notif-icon-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-color);
            background: transparent;
            color: var(--text-muted);
            transition: all 0.2s ease;
        }
        .notif-icon-btn:hover {
            background: var(--card-bg);
            color: var(--text-main);
        }
        .notif-icon-btn.danger:hover {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.4);
        }
        .notif-empty {
            text-align: center;
            padding: 3.5rem 1.5rem;
            background: var(--auth-bg);
            border: 1px dashed var(--border-color);
            border-radius: 16px;
        }
        .notif-empty i {
            font-size: 2.75rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .notif-empty h5 {
            font-weight: 700;
            margin-bottom: 0.4rem;
        }
        .notif-empty p {
            color: var(--text-muted);
            margin: 0;
        }
        @media (max-width: 576px) {
            .notif-card {
                flex-direction: column;
            }
            .notif-actions {
                width: 100%;
                align-items: stretch;
            }
        }
    </style>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'notificacoes', 'Notificações', 'fa-bell'); ?>
        <div>
            <div class="notif-header d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="notif-subtitle">
                    <?php if ($total_nao_lidas > 0) { ?>
                        Você tem <strong><?php echo $total_nao_lidas; ?></strong> notificaç<?php echo $total_nao_lidas === 1 ? 'ão nova' : 'ões novas'; ?>
                    <?php } else { ?>
                        Tudo em dia por aqui
                    <?php } ?>
                </div>
                <?php if ($total_notificacoes > 0) { ?>
                    <form method="POST" data-confirmar="Deseja apagar todas as notificações?">
                    <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="excluir_todas_notificacoes">
                        <button type="submit" class="btn btn-outline-danger notif-action-btn">
                            <i class="fas fa-trash-alt me-2"></i>Apagar todas
                        </button>
                    </form>
                <?php } ?>
            </div>

            <?php if ($total_notificacoes > 0) { ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($notificacoes as $notificacao) { ?>
                        <?php
                            $nao_lida = (int) ($notificacao['IDN_LIDA'] ?? 0) === 0;
                            $info = notificacao_info($notificacao['IDN_TIPO'], $tipos_info);
                        ?>
                        <div class="notif-card <?php echo $nao_lida ? 'is-unread' : 'is-read'; ?>" style="--notif-color: <?php echo $info['cor']; ?>;">
                            <div class="notif-icon"><i class="fas <?php echo $info['icone']; ?>"></i></div>
                            <div class="notif-body">
                                <div class="notif-title-row">
                                    <h5><?php echo htmlspecialchars($notificacao['IDN_TITULO']); ?></h5>
                                    <?php if ($nao_lida) { ?><span class="notif-badge-new">Nova</span><?php } ?>
                                </div>
                                <p class="notif-message"><?php echo nl2br(htmlspecialchars($notificacao['IDN_MENSAGEM'] ?? '')); ?></p>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="notif-type-pill"><i class="fas <?php echo $info['icone']; ?>"></i><?php echo htmlspecialchars($info['label']); ?></span>
                                    <span class="notif-meta"><i class="far fa-clock"></i><?php echo date('d/m/Y H:i', strtotime($notificacao['IDN_CRIADO_EM'])); ?></span>
                                </div>
                            </div>
                            <div class="notif-actions">
                                <?php
                                // O link vem relativo à área do fiel ("assistir_video.php?id=5"):
                                // daqui (php/admin/) ele ganha "../fiel/". Só caminho interno.
                                $link_notif = (string) ($notificacao['IDN_LINK'] ?? '');
                                if ($link_notif !== '' && preg_match('~^[A-Za-z0-9_][A-Za-z0-9_/]*\.php(\?[A-Za-z0-9_=&%.\-]*)?(#[A-Za-z0-9_-]*)?$~D', $link_notif) && strpos($link_notif, '..') === false) { ?>
                                    <a href="<?php echo htmlspecialchars('../fiel/' . $link_notif); ?>" class="btn btn-primary btn-sm notif-action-btn"><i class="fas fa-arrow-right me-1" aria-hidden="true"></i>Ver</a>
                                <?php } ?>
                                <form method="POST" data-confirmar="Deseja apagar esta notificação?">
                                <?php echo csrf_campo(); ?>
                                    <input type="hidden" name="acao" value="excluir_notificacao">
                                    <input type="hidden" name="notificacao_id" value="<?php echo (int) $notificacao['IDN_ID']; ?>">
                                    <button type="submit" class="notif-icon-btn danger" title="Apagar">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="notif-empty">
                    <i class="far fa-bell-slash"></i>
                    <h5>Nenhuma notificação por aqui</h5>
                    <p>Quando algo novo acontecer, você verá tudo neste espaço.</p>
                </div>
            <?php } ?>

        </div>
    <?php admin_layout_fechar(); ?>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
