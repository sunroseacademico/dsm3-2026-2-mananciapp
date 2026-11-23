<?php
// Registro de atividade: quem fez o quê no painel (admin_log() em
// php/conexao.php, seção 83 do bancosupabase.md). Filtros por pessoa, ação e
// período; 50 por página. Ações da equipe interna só aparecem para ela
// (mesma regra das listas de usuários).
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));

$disponivel = admin_log_disponivel($conn);
$acoes = admin_log_acoes();
$por_pagina = 50;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$filtro_acao = site_entrada_texto($_GET['acao'] ?? '');
$filtro_admin = (int) ($_GET['admin'] ?? 0);
$filtro_dias = (int) ($_GET['dias'] ?? 30);
if (!in_array($filtro_dias, array(1, 7, 30, 90, 365), true)) {
    $filtro_dias = 30;
}

$linhas = array();
$total = 0;
$admins = array();
if ($disponivel) {
    $where = array("l.idal_criado_em > NOW() - (? * INTERVAL '1 day')");
    $params = array($filtro_dias);
    if ($filtro_acao !== '' && isset($acoes[$filtro_acao])) {
        $where[] = 'l.idal_acao = ?';
        $params[] = $filtro_acao;
    }
    if ($filtro_admin > 0) {
        $where[] = 'l.idal_admin_id = ?';
        $params[] = $filtro_admin;
    }
    $superadmins = superadmin_emails();
    if ($superadmins && !auth_eh_superadmin()) {
        $where[] = '(f.IDF_EMAIL IS NULL OR LOWER(f.IDF_EMAIL) NOT IN (' . implode(', ', array_fill(0, count($superadmins), '?')) . '))';
        $params = array_merge($params, $superadmins);
    }
    $sql_base = "FROM id_admin_log l LEFT JOIN ID_FIEL f ON f.IDF_ID = l.idal_admin_id WHERE " . implode(' AND ', $where);

    $res_total = db_query($conn, "SELECT COUNT(*) AS total $sql_base", $params);
    $total = $res_total ? (int) (mysqli_fetch_assoc($res_total)['total'] ?? 0) : 0;
    $paginas = max(1, (int) ceil($total / $por_pagina));
    $pagina = min($pagina, $paginas);

    $res = db_query($conn, "
        SELECT l.*, COALESCE(f.IDF_NOME, l.idal_admin_nome) AS nome_atual
        $sql_base
        ORDER BY l.idal_criado_em DESC
        LIMIT $por_pagina OFFSET " . (($pagina - 1) * $por_pagina), $params);
    while ($res && ($linha = mysqli_fetch_assoc($res))) {
        $linhas[] = $linha;
    }

    // Quem aparece no filtro: quem já tem algo registrado.
    $params_admins = array();
    $sem_super = '';
    if ($superadmins && !auth_eh_superadmin()) {
        $sem_super = 'WHERE (f.IDF_EMAIL IS NULL OR LOWER(f.IDF_EMAIL) NOT IN (' . implode(', ', array_fill(0, count($superadmins), '?')) . '))';
        $params_admins = $superadmins;
    }
    $res_admins = db_query($conn, "
        SELECT l.idal_admin_id, MAX(COALESCE(f.IDF_NOME, l.idal_admin_nome)) AS nome
        FROM id_admin_log l LEFT JOIN ID_FIEL f ON f.IDF_ID = l.idal_admin_id
        $sem_super
        GROUP BY l.idal_admin_id ORDER BY 2
    ", $params_admins);
    while ($res_admins && ($linha = mysqli_fetch_assoc($res_admins))) {
        $admins[(int) $linha['idal_admin_id']] = (string) $linha['nome'];
    }
}
$paginas = isset($paginas) ? $paginas : 1;

// Detalhes em texto curto ("titulo: Fundamentos · status: publicado").
$detalhes_texto = function ($json) {
    $dados = json_decode((string) $json, true);
    if (!is_array($dados)) {
        return '';
    }
    $partes = array();
    foreach ($dados as $chave => $valor) {
        if (is_bool($valor)) {
            $valor = $valor ? 'sim' : 'não';
        } elseif (is_array($valor)) {
            $valor = implode(', ', array_map('strval', $valor));
        }
        $partes[] = $chave . ': ' . $valor;
    }
    return implode(' · ', $partes);
};
$link = function ($pagina_nova) use ($filtro_acao, $filtro_admin, $filtro_dias) {
    return 'admin_atividade.php?' . http_build_query(array_filter(array(
        'acao' => $filtro_acao, 'admin' => $filtro_admin ?: null, 'dias' => $filtro_dias, 'pagina' => $pagina_nova,
    )));
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Registro de atividade</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell">
    <?php admin_layout_abrir($conn, 'atividade', 'Registro de atividade', 'fa-clock-rotate-left'); ?>

    <?php if (!$disponivel) { ?>
        <div class="alert alert-warning">Falta rodar a seção 83 do <code>bancosupabase.md</code> no Supabase.</div>
    <?php } else { ?>
        <p class="text-muted">Quem fez o quê no painel. Os registros ficam guardados por 2 anos.</p>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-sm-4">
                <label class="form-label small" for="filtroAdmin">Pessoa</label>
                <select class="form-select" id="filtroAdmin" name="admin" data-enviar-ao-mudar>
                    <option value="">Todas</option>
                    <?php foreach ($admins as $id => $nome) { ?>
                        <option value="<?php echo (int) $id; ?>"<?php echo $filtro_admin === $id ? ' selected' : ''; ?>><?php echo htmlspecialchars($nome); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-sm-4">
                <label class="form-label small" for="filtroAcao">Ação</label>
                <select class="form-select" id="filtroAcao" name="acao" data-enviar-ao-mudar>
                    <option value="">Todas</option>
                    <?php foreach ($acoes as $chave => $rotulo) { ?>
                        <option value="<?php echo htmlspecialchars($chave); ?>"<?php echo $filtro_acao === $chave ? ' selected' : ''; ?>><?php echo htmlspecialchars($rotulo); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-sm-4">
                <label class="form-label small" for="filtroDias">Período</label>
                <select class="form-select" id="filtroDias" name="dias" data-enviar-ao-mudar>
                    <?php foreach (array(1 => 'Hoje e ontem', 7 => '7 dias', 30 => '30 dias', 90 => '90 dias', 365 => '1 ano') as $dias => $rotulo) { ?>
                        <option value="<?php echo $dias; ?>"<?php echo $filtro_dias === $dias ? ' selected' : ''; ?>><?php echo $rotulo; ?></option>
                    <?php } ?>
                </select>
            </div>
            <noscript><div class="col-12"><button class="btn btn-primary btn-sm">Filtrar</button></div></noscript>
        </form>

        <?php if (!$linhas) { ?>
            <div class="card admin-card"><div class="card-body text-muted">Nada registrado nesse período.</div></div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table admin-tabela align-middle">
                    <thead><tr><th>Quando</th><th>Quem</th><th>O quê</th><th>Detalhes</th></tr></thead>
                    <tbody>
                        <?php foreach ($linhas as $linha) { ?>
                            <tr>
                                <td class="text-nowrap small"><?php echo htmlspecialchars(site_data_hora($linha['idal_criado_em'], 'd/m/Y H:i')); ?></td>
                                <td><?php echo htmlspecialchars((string) $linha['nome_atual']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($acoes[$linha['idal_acao']] ?? $linha['idal_acao']); ?>
                                    <?php if (!empty($linha['idal_alvo_id'])) { ?>
                                        <span class="text-muted small">(<?php echo htmlspecialchars((string) $linha['idal_alvo_tipo']); ?> #<?php echo (int) $linha['idal_alvo_id']; ?>)</span>
                                    <?php } ?>
                                </td>
                                <td class="small text-muted"><?php echo htmlspecialchars($detalhes_texto($linha['idal_detalhes'])); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php if ($paginas > 1) { ?>
                <nav aria-label="Páginas" class="d-flex justify-content-between align-items-center">
                    <span class="small text-muted"><?php echo (int) $total; ?> registros · página <?php echo (int) $pagina; ?> de <?php echo (int) $paginas; ?></span>
                    <div class="btn-group">
                        <a class="btn btn-outline-secondary btn-sm<?php echo $pagina <= 1 ? ' disabled' : ''; ?>" href="<?php echo htmlspecialchars($link($pagina - 1)); ?>">Anterior</a>
                        <a class="btn btn-outline-secondary btn-sm<?php echo $pagina >= $paginas ? ' disabled' : ''; ?>" href="<?php echo htmlspecialchars($link($pagina + 1)); ?>">Próxima</a>
                    </div>
                </nav>
            <?php } ?>
        <?php } ?>
    <?php } ?>

    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
</body>
</html>
