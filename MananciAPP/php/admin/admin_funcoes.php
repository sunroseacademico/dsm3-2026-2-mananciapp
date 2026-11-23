<?php
// Funções na igreja (T12): o admin mantém a lista (Membro, Líder, Pastor…)
// e define a função de cada fiel. O fiel só vê a função no perfil. Ver
// "FUNÇÃO NA IGREJA" em php/conexao.php e a seção 50 do bancosupabase.md.
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));
// Ações que deram certo vão para o Registro de atividade (admin_log_ao_redirecionar()).
admin_log_ao_redirecionar($conn, 'funcoes');

$disponivel = funcoes_igreja_disponivel($conn);
$erro = '';
$mensagem = '';

// Filtros da lista de fiéis (voltam no redirecionamento depois de salvar).
$filtro = ($_GET['filtro'] ?? '') === 'sem' ? 'sem' : 'todos';
$busca = trim(site_entrada_texto($_GET['busca'] ?? '', false));
$query_volta = http_build_query(array_filter(array('filtro' => $filtro === 'sem' ? 'sem' : null, 'busca' => $busca !== '' ? $busca : null)));
$url_volta = 'admin_funcoes.php' . ($query_volta !== '' ? '?' . $query_volta : '');

function admin_funcoes_redirecionar($url, $mensagem, $ancora = '') {
    $_SESSION['admin_funcoes_ok'] = $mensagem;
    header('Location: ' . $url . $ancora);
    exit;
}

if ($disponivel && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = site_entrada_texto($_POST['acao'] ?? '', false);
    if (!csrf_validar()) {
        $erro = 'Sessão expirada ou formulário inválido. Atualize a página e tente de novo.';
    } elseif ($acao === 'funcao_salvar') {
        $id = (int) ($_POST['idfn_id'] ?? 0);
        $nome = trim(preg_replace('/\s+/', ' ', site_entrada_texto($_POST['idfn_nome'] ?? '', false)));
        $ordem = max(0, min(9999, (int) ($_POST['idfn_ordem'] ?? 0)));
        $lider = isset($_POST['idfn_lider']) ? 1 : 0;
        $ativo = $id === 0 || isset($_POST['idfn_ativo']) ? 1 : 0;

        $res_dup = db_query($conn, "SELECT idfn_id FROM id_funcao_igreja WHERE LOWER(idfn_nome) = LOWER(CAST(? AS TEXT)) AND idfn_id <> ? LIMIT 1", array($nome, $id));
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > FUNCAO_IGREJA_NOME_MAX) {
            $erro = 'Nome da função: de 2 a ' . FUNCAO_IGREJA_NOME_MAX . ' caracteres.';
        } elseif ($res_dup && mysqli_num_rows($res_dup) > 0) {
            $erro = 'Já existe uma função chamada "' . $nome . '".';
        } elseif ($id > 0) {
            $ok = db_query($conn, "UPDATE id_funcao_igreja SET idfn_nome = ?, idfn_ordem = ?, idfn_lider = ?, idfn_ativo = ? WHERE idfn_id = ?", array($nome, $ordem, $lider, $ativo, $id));
            // O nome também fica copiado em id_fiel.idf_funcao (ver conexao.php).
            $ok = $ok && db_query($conn, "UPDATE id_fiel SET idf_funcao = ? WHERE idf_funcao_id = ?", array($nome, $id));
            if ($ok) {
                admin_funcoes_redirecionar($url_volta, 'Função "' . $nome . '" atualizada.', '#funcoes');
            }
            $erro = 'Não foi possível salvar a função.';
        } else {
            if (db_query($conn, "INSERT INTO id_funcao_igreja (idfn_nome, idfn_ordem, idfn_lider, idfn_ativo) VALUES (?, ?, ?, 1)", array($nome, $ordem, $lider))) {
                admin_funcoes_redirecionar($url_volta, 'Função "' . $nome . '" criada.', '#funcoes');
            }
            $erro = 'Não foi possível criar a função.';
        }
    } elseif ($acao === 'funcao_excluir') {
        $id = (int) ($_POST['idfn_id'] ?? 0);
        $res_uso = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM id_fiel WHERE idf_funcao_id = $id");
        $uso = $res_uso ? (int) (mysqli_fetch_assoc($res_uso)['cnt'] ?? 0) : 1;
        if ($uso > 0) {
            $erro = 'Essa função está em uso. Desative em vez de excluir.';
        } elseif (mysqli_query($conn, "DELETE FROM id_funcao_igreja WHERE idfn_id = $id")) {
            admin_funcoes_redirecionar($url_volta, 'Função excluída.', '#funcoes');
        } else {
            $erro = 'Não foi possível excluir a função.';
        }
    } elseif ($acao === 'fiel_funcao') {
        $fiel_id = (int) ($_POST['fiel_id'] ?? 0);
        $erro_definir = fiel_funcao_definir($conn, $fiel_id, (int) ($_POST['funcao_id'] ?? 0));
        if ($erro_definir === null) {
            admin_funcoes_redirecionar($url_volta, 'Função do fiel atualizada.', '#fiel-' . $fiel_id);
        }
        $erro = $erro_definir;
    }
}
if (!empty($_SESSION['admin_funcoes_ok'])) {
    $mensagem = $_SESSION['admin_funcoes_ok'];
    unset($_SESSION['admin_funcoes_ok']);
}

$funcoes = array();
$uso_por_funcao = array();
$fieis = array();
$total_sem_funcao = 0;
if ($disponivel) {
    $funcoes = funcoes_igreja_listar($conn);

    $res = mysqli_query($conn, "SELECT idf_funcao_id, COUNT(*) AS cnt FROM id_fiel WHERE idf_funcao_id IS NOT NULL GROUP BY idf_funcao_id");
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $uso_por_funcao[(int) $linha['idf_funcao_id']] = (int) $linha['cnt'];
    }

    $res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM id_fiel WHERE idf_funcao_id IS NULL AND idf_status <> 'negado'");
    $total_sem_funcao = $res ? (int) (mysqli_fetch_assoc($res)['cnt'] ?? 0) : 0;

    $where = array("idf_status <> 'negado'");
    $params_where = array();
    if ($filtro === 'sem') {
        $where[] = 'idf_funcao_id IS NULL';
    }
    if ($busca !== '') {
        $busca_like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $busca) . '%';
        $where[] = "(idf_nome ILIKE ? OR idf_email ILIKE ?)";
        $params_where[] = $busca_like;
        $params_where[] = $busca_like;
    }
    $res = db_query($conn, "
        SELECT idf_id, idf_nome, idf_email, idf_status, idf_funcao, idf_funcao_id
        FROM id_fiel
        WHERE " . implode(' AND ', $where) . "
        ORDER BY (idf_funcao_id IS NULL) DESC, idf_nome ASC
        LIMIT 300
    ", $params_where);
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $fieis[] = $linha;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Funções na Igreja</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'funcoes', 'Funções na Igreja', 'fa-people-group'); ?>

    <p class="text-muted mb-4">A função de cada fiel (Membro, Líder, Pastor…) é definida só pela equipe. O fiel vê a função no perfil, mas não altera.</p>

    <?php if ($mensagem !== '') { ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensagem); ?></div>
    <?php } ?>
    <?php if ($erro !== '') { ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($erro); ?></div>
    <?php } ?>

    <?php if (!$disponivel) { ?>
        <div class="alert alert-warning">
            A lista de funções ainda não existe no banco. Rode a seção 50 do
            <code>database/bancosupabase.md</code> no Supabase para liberar esta tela.
        </div>
    <?php } else { ?>

    <div class="admin-card" id="funcoes">
        <h5 class="admin-card-title"><i class="fas fa-list-ol"></i>Lista de funções</h5>
        <p class="text-muted small">
            "Liderança" marca quem aparece como líder na comunidade (por exemplo, o selo "Líder" nos comentários).
            Função desativada some da escolha, mas quem já tem continua com ela. Só dá para excluir função que ninguém usa.
        </p>
        <div class="table-responsive campo-perfil">
            <table class="table data-table align-middle">
                <thead>
                    <tr><th>Nome</th><th style="width:110px;">Ordem</th><th>Liderança</th><th>Ativa</th><th>Fiéis</th><th>Ações</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($funcoes as $fn) { $form_id = 'fn' . $fn['id']; $uso = $uso_por_funcao[$fn['id']] ?? 0; ?>
                        <tr>
                            <td><input type="text" name="idfn_nome" form="<?php echo $form_id; ?>" class="form-control custom-input" value="<?php echo htmlspecialchars($fn['nome']); ?>" required minlength="2" maxlength="<?php echo FUNCAO_IGREJA_NOME_MAX; ?>" aria-label="Nome da função"></td>
                            <td><input type="number" name="idfn_ordem" form="<?php echo $form_id; ?>" class="form-control custom-input" value="<?php echo $fn['ordem']; ?>" min="0" max="9999" aria-label="Ordem"></td>
                            <td><input type="checkbox" name="idfn_lider" form="<?php echo $form_id; ?>" class="form-check-input" <?php echo $fn['lider'] ? 'checked' : ''; ?> aria-label="Função de liderança"></td>
                            <td><input type="checkbox" name="idfn_ativo" form="<?php echo $form_id; ?>" class="form-check-input" <?php echo $fn['ativo'] ? 'checked' : ''; ?> aria-label="Função ativa"></td>
                            <td><?php echo $uso; ?></td>
                            <td class="text-nowrap">
                                <form method="POST" action="<?php echo htmlspecialchars($url_volta); ?>" id="<?php echo $form_id; ?>" class="d-inline">
                                    <?php echo csrf_campo(); ?>
                                    <input type="hidden" name="acao" value="funcao_salvar">
                                    <input type="hidden" name="idfn_id" value="<?php echo $fn['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-light" title="Salvar"><i class="fas fa-save"></i></button>
                                </form>
                                <?php if ($uso === 0) { ?>
                                    <form method="POST" action="<?php echo htmlspecialchars($url_volta); ?>" class="d-inline" data-confirmar="<?php echo htmlspecialchars('Excluir a função "' . $fn['nome'] . '"?', ENT_QUOTES); ?>">
                                        <?php echo csrf_campo(); ?>
                                        <input type="hidden" name="acao" value="funcao_excluir">
                                        <input type="hidden" name="idfn_id" value="<?php echo $fn['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <td><input type="text" name="idfn_nome" form="fnNova" class="form-control custom-input" placeholder="Nova função" required minlength="2" maxlength="<?php echo FUNCAO_IGREJA_NOME_MAX; ?>" aria-label="Nome da nova função"></td>
                        <td><input type="number" name="idfn_ordem" form="fnNova" class="form-control custom-input" value="<?php echo $funcoes ? max(array_column($funcoes, 'ordem')) + 10 : 10; ?>" min="0" max="9999" aria-label="Ordem da nova função"></td>
                        <td><input type="checkbox" name="idfn_lider" form="fnNova" class="form-check-input" aria-label="Nova função é de liderança"></td>
                        <td colspan="2"></td>
                        <td>
                            <form method="POST" action="<?php echo htmlspecialchars($url_volta); ?>" id="fnNova" class="d-inline">
                                <?php echo csrf_campo(); ?>
                                <input type="hidden" name="acao" value="funcao_salvar">
                                <input type="hidden" name="idfn_id" value="0">
                                <button type="submit" class="btn btn-sm btn-light fw-bold"><i class="fas fa-plus me-1"></i>Adicionar</button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card" id="fieis">
        <h5 class="admin-card-title"><i class="fas fa-user-tag"></i>Função de cada fiel</h5>
        <form method="GET" action="admin_funcoes.php#fieis" class="row g-2 align-items-end mb-3 campo-perfil">
            <div class="col-sm-5">
                <label class="form-label small" for="busca">Buscar por nome ou e-mail</label>
                <input type="search" name="busca" id="busca" class="form-control custom-input" value="<?php echo htmlspecialchars($busca); ?>">
            </div>
            <div class="col-sm-4">
                <label class="form-label small" for="filtro">Mostrar</label>
                <select name="filtro" id="filtro" class="form-select custom-input">
                    <option value="todos">Todos os fiéis</option>
                    <option value="sem" <?php echo $filtro === 'sem' ? 'selected' : ''; ?>>Sem função definida (<?php echo $total_sem_funcao; ?>)</option>
                </select>
            </div>
            <div class="col-sm-3">
                <button type="submit" class="btn btn-outline-light w-100"><i class="fas fa-search me-1"></i>Filtrar</button>
            </div>
        </form>

        <?php if ($total_sem_funcao > 0 && $filtro !== 'sem') { ?>
            <p class="small mb-3" style="color:#f59e0b;"><i class="fas fa-circle-exclamation me-1"></i><?php echo $total_sem_funcao; ?> fiel(is) ainda sem função definida — eles aparecem primeiro.</p>
        <?php } ?>

        <div class="table-responsive campo-perfil">
            <table class="table data-table align-middle mb-0">
                <thead>
                    <tr><th>Fiel</th><th>Função</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($fieis as $f) { $atual = (int) ($f['idf_funcao_id'] ?? 0); ?>
                        <tr id="fiel-<?php echo (int) $f['idf_id']; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($f['idf_nome']); ?></strong>
                                <?php if ($f['idf_status'] === 'pendente') { ?><span class="badge bg-warning text-dark ms-1">Pendente</span><?php } ?>
                                <div class="small text-muted"><?php echo htmlspecialchars($f['idf_email']); ?></div>
                                <?php if ($atual === 0 && trim((string) $f['idf_funcao']) !== '') { ?>
                                    <div class="small text-muted">Informado no cadastro: "<?php echo htmlspecialchars($f['idf_funcao']); ?>"</div>
                                <?php } ?>
                            </td>
                            <td style="min-width: 260px;">
                                <form method="POST" action="<?php echo htmlspecialchars($url_volta); ?>" class="d-flex gap-2">
                                    <?php echo csrf_campo(); ?>
                                    <input type="hidden" name="acao" value="fiel_funcao">
                                    <input type="hidden" name="fiel_id" value="<?php echo (int) $f['idf_id']; ?>">
                                    <select name="funcao_id" class="form-select custom-input" aria-label="Função de <?php echo htmlspecialchars($f['idf_nome']); ?>">
                                        <option value="0">— Sem função —</option>
                                        <?php foreach ($funcoes as $fn) { if (!$fn['ativo'] && $fn['id'] !== $atual) { continue; } ?>
                                            <option value="<?php echo $fn['id']; ?>" <?php echo $fn['id'] === $atual ? 'selected' : ''; ?>><?php echo htmlspecialchars($fn['nome'] . ($fn['ativo'] ? '' : ' (desativada)')); ?></option>
                                        <?php } ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-light" title="Salvar função"><i class="fas fa-save"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($fieis)) { ?>
                        <tr><td colspan="2" class="text-center text-muted py-4">Nenhum fiel encontrado.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php if (count($fieis) >= 300) { ?>
            <p class="small text-muted mt-2 mb-0">Mostrando os primeiros 300. Use a busca para encontrar outros.</p>
        <?php } ?>
    </div>
    <?php } ?>

    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
