<?php
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));

// Ver Progresso: busca o fiel por nome ou e-mail e mostra o perfil, os
// cursos com o percentual e as últimas aulas que ele assistiu. Antes era um
// <select> com os 100 primeiros em ordem alfabética (do 101º em diante
// ninguém abria) e uma lista de 10 vídeos do banco inteiro, sem relação
// com o fiel.
$usuario_id = isset($_GET["user_id"]) ? (int) $_GET["user_id"] : 0;
$busca = site_entrada_texto($_GET["q"] ?? "");
$usuario = null;
$cursos = array();
$aulas = array();
$erro = "";

// Contas da equipe interna só aparecem para quem também é dela.
$superadmins = superadmin_emails();
$sem_super = '';
$params_super = array();
if ($superadmins && !auth_eh_superadmin()) {
    $sem_super = 'AND LOWER(IDF_EMAIL) NOT IN (' . implode(', ', array_fill(0, count($superadmins), '?')) . ')';
    $params_super = $superadmins;
}

$resultados = array();
if ($busca !== '' && mb_strlen($busca) >= 2) {
    $like = '%' . site_like_escapar(mb_strtolower($busca)) . '%';
    $res = db_query($conn, "
        SELECT IDF_ID, IDF_NOME, IDF_EMAIL, IDF_STATUS FROM ID_FIEL
        WHERE IDF_REMOVIDO_EM IS NULL AND (LOWER(IDF_NOME) LIKE ? OR LOWER(IDF_EMAIL) LIKE ?) $sem_super
        ORDER BY IDF_NOME LIMIT 30
    ", array_merge(array($like, $like), $params_super));
    while ($res && ($linha = mysqli_fetch_assoc($res))) {
        $resultados[] = $linha;
    }
}

if ($usuario_id > 0) {
    $res_user = db_query($conn, "
        SELECT IDF_ID, IDF_NOME, IDF_EMAIL, IDF_FUNCAO, IDF_TELEFONE, IDF_STATUS, IDF_CRIADO_EM, IDF_LAST_ACTIVITY
        FROM ID_FIEL WHERE IDF_ID = ? $sem_super LIMIT 1
    ", array_merge(array($usuario_id), $params_super));
    $usuario = $res_user ? mysqli_fetch_assoc($res_user) : null;
    if ($usuario) {
        $res_matriculas = db_query($conn, "
            SELECT m.IDMATR_ID, c.IDC_ID, c.IDC_TITULO, m.IDMATR_STATUS, m.IDMATR_PERCENTUAL, m.IDMATR_INICIO_EM
            FROM ID_MATRICULA m
            LEFT JOIN ID_CURSO c ON m.IDC_ID = c.IDC_ID
            WHERE m.IDF_ID = ?
            ORDER BY m.IDMATR_INICIO_EM DESC
        ", array($usuario_id));
        while ($res_matriculas && ($curso = mysqli_fetch_assoc($res_matriculas))) {
            $cursos[] = $curso;
        }

        // Últimas aulas que ele abriu: onde parou e quanto assistiu.
        $res_aulas = db_query($conn, "
            SELECT ct.IDCT_TITULO, cu.IDC_TITULO, p.IDPR_STATUS, p.IDPR_PERCENTUAL, p.IDPR_TEMPO_ASSISTIDO_SEGUNDOS, p.IDPR_ULTIMO_ACESSO_EM
            FROM ID_PROGRESSO p
            JOIN ID_MATRICULA m ON m.IDMATR_ID = p.IDMATR_ID
            JOIN ID_CONTENT ct ON ct.IDCT_ID = p.IDCT_ID
            LEFT JOIN ID_CURSO cu ON cu.IDC_ID = ct.IDC_ID
            WHERE m.IDF_ID = ?
            ORDER BY p.IDPR_ULTIMO_ACESSO_EM DESC NULLS LAST
            LIMIT 15
        ", array($usuario_id));
        while ($res_aulas && ($aula = mysqli_fetch_assoc($res_aulas))) {
            $aulas[] = $aula;
        }
    } else {
        $erro = "Usuário não encontrado.";
    }
}

$minutos = function ($segundos) {
    $segundos = (int) $segundos;
    return $segundos >= 3600 ? floor($segundos / 3600) . ' h ' . floor(($segundos % 3600) / 60) . ' min' : max(1, (int) round($segundos / 60)) . ' min';
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Progresso - Admin</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell">
    <?php admin_layout_abrir($conn, 'progresso', 'Ver Progresso', 'fa-tasks'); ?>
        <div class="row">
            <div class="col-12">
                <p class="text-muted mb-4">Encontre um fiel para ver os cursos, o progresso e as últimas aulas que ele assistiu — útil para ajudar quem diz que "travou".</p>

                <?php if ($erro !== "") { ?>
                    <div class="alert alert-danger" role="alert"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($erro); ?></div>
                <?php } ?>

                <div class="card mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                    <div class="card-body">
                        <form method="GET" class="d-flex gap-2 flex-wrap">
                            <input type="search" name="q" class="form-control custom-input" style="max-width: 360px;" placeholder="Nome ou e-mail (pelo menos 2 letras)" value="<?php echo htmlspecialchars($busca); ?>" aria-label="Buscar fiel" autofocus>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Buscar</button>
                        </form>
                        <?php if ($busca !== '') { ?>
                            <div class="list-group list-group-flush mt-3">
                                <?php foreach ($resultados as $r) { ?>
                                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center<?php echo (int) $r['IDF_ID'] === $usuario_id ? ' active' : ''; ?>" style="background: transparent;" href="?<?php echo htmlspecialchars(http_build_query(array('q' => $busca, 'user_id' => (int) $r['IDF_ID']))); ?>">
                                        <span><?php echo htmlspecialchars($r['IDF_NOME']); ?> <small class="text-muted"><?php echo htmlspecialchars($r['IDF_EMAIL']); ?></small></span>
                                        <?php if ($r['IDF_STATUS'] !== 'aprovado') { ?><span class="badge bg-secondary"><?php echo htmlspecialchars($r['IDF_STATUS']); ?></span><?php } ?>
                                    </a>
                                <?php } ?>
                                <?php if (!$resultados) { ?><p class="text-muted mb-0">Ninguém encontrado.</p><?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <?php if ($usuario !== null) { ?>
                    <div class="row g-3 mb-4">
                        <div class="col-md-5">
                            <div class="card h-100" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                                <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
                                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Perfil</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm mb-0">
                                        <tr><td><strong>Nome:</strong></td><td><?php echo htmlspecialchars($usuario["IDF_NOME"]); ?></td></tr>
                                        <tr><td><strong>E-mail:</strong></td><td><?php echo htmlspecialchars($usuario["IDF_EMAIL"]); ?></td></tr>
                                        <tr><td><strong>Telefone:</strong></td><td><?php echo htmlspecialchars(formatar_telefone_exibicao($usuario["IDF_TELEFONE"])); ?></td></tr>
                                        <tr><td><strong>Função:</strong></td><td><?php echo htmlspecialchars((string) $usuario["IDF_FUNCAO"]); ?></td></tr>
                                        <tr><td><strong>Situação:</strong></td><td><?php echo htmlspecialchars((string) $usuario["IDF_STATUS"]); ?></td></tr>
                                        <tr><td><strong>Cadastro:</strong></td><td><?php echo htmlspecialchars(site_data_hora($usuario["IDF_CRIADO_EM"], 'd/m/Y')); ?></td></tr>
                                        <tr><td><strong>Último acesso:</strong></td><td><?php echo $usuario["IDF_LAST_ACTIVITY"] ? htmlspecialchars(site_tempo_relativo($usuario["IDF_LAST_ACTIVITY"]) ?: site_data_hora($usuario["IDF_LAST_ACTIVITY"], 'd/m/Y H:i')) : 'nunca'; ?></td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div class="card h-100" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                                <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
                                    <h5 class="mb-0"><i class="fas fa-book me-2"></i>Cursos (<?php echo count($cursos); ?>)</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (count($cursos) > 0) { ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($cursos as $curso) { $pct = max(0, min(100, (float) $curso["IDMATR_PERCENTUAL"])); ?>
                                                <div class="list-group-item" style="background: transparent; border-color: rgba(96,165,250,0.1);">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <h6 class="mb-1"><?php echo htmlspecialchars((string) $curso["IDC_TITULO"]); ?></h6>
                                                            <small class="text-muted">Começou em <?php echo htmlspecialchars(site_data_hora($curso["IDMATR_INICIO_EM"], 'd/m/Y')); ?></small>
                                                        </div>
                                                        <span class="badge bg-info"><?php echo (int) round($pct); ?>%</span>
                                                    </div>
                                                    <div class="progress mt-2" style="height: 4px;" role="progressbar" aria-valuenow="<?php echo (int) round($pct); ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <div class="progress-bar" style="width: <?php echo $pct; ?>%; background: linear-gradient(90deg, #60a5fa, #a78bfa);"></div>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    <?php } else { ?>
                                        <p class="text-muted mb-0">Ainda não começou nenhum curso.</p>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                        <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
                            <h5 class="mb-0"><i class="fas fa-clock-rotate-left me-2"></i>Últimas aulas assistidas</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($aulas) { ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead><tr><th>Aula</th><th>Curso</th><th>Assistido</th><th>Tempo</th><th>Quando</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($aulas as $aula) { ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars((string) $aula['IDCT_TITULO']); ?></td>
                                                    <td class="small text-muted"><?php echo htmlspecialchars((string) $aula['IDC_TITULO']); ?></td>
                                                    <td><?php echo strtoupper((string) $aula['IDPR_STATUS']) === 'CONCLUIDO' ? '<span class="badge bg-success">Concluída</span>' : (int) round((float) $aula['IDPR_PERCENTUAL']) . '%'; ?></td>
                                                    <td class="small"><?php echo htmlspecialchars($minutos($aula['IDPR_TEMPO_ASSISTIDO_SEGUNDOS'])); ?></td>
                                                    <td class="small"><?php echo $aula['IDPR_ULTIMO_ACESSO_EM'] ? htmlspecialchars(site_data_hora($aula['IDPR_ULTIMO_ACESSO_EM'], 'd/m H:i')) : '—'; ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } else { ?>
                                <p class="text-muted mb-0">Ainda não assistiu nenhuma aula.</p>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    <?php admin_layout_fechar(); ?>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
