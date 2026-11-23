<?php
// Filiais (seção 76): o admin mantém a lista que o fiel escolhe no cadastro
// e no perfil, e vê ou corrige a filial de cada fiel. Hoje a lista são os
// estados, até o cliente mandar as filiais de verdade. Ver "FILIAIS" em
// php/conexao.php.
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));
// Ações que deram certo vão para o Registro de atividade (admin_log_ao_redirecionar()).
admin_log_ao_redirecionar($conn, 'filiais');

$disponivel = filiais_disponivel($conn);
$erro = '';
$mensagem = '';

// Filtros da lista de fiéis: todos | sem (não informada) | nenhuma | id.
$filtro = (string) ($_GET['filtro'] ?? 'todos');
if (!in_array($filtro, array('todos', 'sem', FILIAL_NENHUMA), true) && !ctype_digit($filtro)) {
    $filtro = 'todos';
}
$busca = trim(site_entrada_texto($_GET['busca'] ?? '', false));
$query_volta = http_build_query(array_filter(array('filtro' => $filtro !== 'todos' ? $filtro : null, 'busca' => $busca !== '' ? $busca : null)));
$url_volta = 'admin_filiais.php' . ($query_volta !== '' ? '?' . $query_volta : '');

function admin_filiais_redirecionar($url, $mensagem, $ancora = '') {
    $_SESSION['admin_filiais_ok'] = $mensagem;
    header('Location: ' . $url . $ancora);
    exit;
}

if ($disponivel && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = site_entrada_texto($_POST['acao'] ?? '', false);
    if (!csrf_validar()) {
        $erro = 'Sessão expirada ou formulário inválido. Atualize a página e tente de novo.';
    } elseif ($acao === 'filial_salvar') {
        $id = (int) ($_POST['idl_id'] ?? 0);
        $nome = trim(preg_replace('/\s+/', ' ', site_entrada_texto($_POST['idl_nome'] ?? '', false)));
        $codigo = strtoupper(trim(site_entrada_texto($_POST['idl_codigo'] ?? '', false)));
        $estado = strtoupper(trim(site_entrada_texto($_POST['idl_estado'] ?? '', false)));
        $cidade = trim(preg_replace('/\s+/', ' ', site_entrada_texto($_POST['idl_cidade'] ?? '', false)));
        $ordem = max(0, min(9999, (int) ($_POST['idl_ordem'] ?? 0)));
        $ativa = $id === 0 || isset($_POST['idl_ativa']) ? 1 : 0;

        $res_dup = db_query($conn, "SELECT idl_id FROM id_filial WHERE LOWER(idl_nome) = LOWER(CAST(? AS TEXT)) AND idl_id <> ? LIMIT 1", array($nome, $id));
        $res_dup_codigo = $codigo !== '' ? db_query($conn, "SELECT idl_id FROM id_filial WHERE UPPER(idl_codigo) = CAST(? AS TEXT) AND idl_id <> ? LIMIT 1", array($codigo, $id)) : false;
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > FILIAL_NOME_MAX) {
            $erro = 'Nome da filial: de 2 a ' . FILIAL_NOME_MAX . ' caracteres.';
        } elseif (mb_strtolower($nome) === mb_strtolower(FILIAL_NENHUMA_ROTULO)) {
            $erro = '"' . FILIAL_NENHUMA_ROTULO . '" já é uma opção fixa da lista.';
        } elseif ($res_dup && mysqli_num_rows($res_dup) > 0) {
            $erro = 'Já existe uma filial chamada "' . $nome . '".';
        } elseif ($codigo !== '' && !preg_match('/^[A-Z0-9-]{2,20}$/', $codigo)) {
            $erro = 'Código: de 2 a 20 letras, números ou hífen (ex.: MAN-CPS).';
        } elseif ($res_dup_codigo && mysqli_num_rows($res_dup_codigo) > 0) {
            $erro = 'Já existe uma filial com o código ' . $codigo . '.';
        } elseif ($estado !== '' && !preg_match('/^[A-Z]{2}$/', $estado)) {
            $erro = 'Estado: a sigla com 2 letras (ex.: SP).';
        } elseif (mb_strlen($cidade) > 100) {
            $erro = 'Cidade: até 100 caracteres.';
        } elseif ($id > 0) {
            $ok = db_query($conn, "UPDATE id_filial SET idl_nome = ?, idl_codigo = ?, idl_estado = ?, idl_cidade = ?, idl_ordem = ?, idl_ativa = ? WHERE idl_id = ?", array($nome, $codigo !== '' ? $codigo : null, $estado !== '' ? $estado : null, $cidade !== '' ? $cidade : null, $ordem, $ativa, $id));
            // O nome também fica copiado em id_fiel.idf_filial (ver conexao.php).
            $ok = $ok && db_query($conn, "UPDATE id_fiel SET idf_filial = ? WHERE idf_filial_id = ?", array($nome, $id));
            if ($ok) {
                admin_filiais_redirecionar($url_volta, 'Filial "' . $nome . '" atualizada.', '#filiais');
            }
            $erro = 'Não foi possível salvar a filial.';
        } else {
            if (db_query($conn, "INSERT INTO id_filial (idl_nome, idl_codigo, idl_estado, idl_cidade, idl_ordem, idl_ativa) VALUES (?, ?, ?, ?, ?, 1)", array($nome, $codigo !== '' ? $codigo : null, $estado !== '' ? $estado : null, $cidade !== '' ? $cidade : null, $ordem))) {
                admin_filiais_redirecionar($url_volta, 'Filial "' . $nome . '" criada.', '#filiais');
            }
            $erro = 'Não foi possível criar a filial.';
        }
    } elseif ($acao === 'filial_excluir') {
        $id = (int) ($_POST['idl_id'] ?? 0);
        $res_uso = db_query($conn, "SELECT COUNT(*) AS cnt FROM id_fiel WHERE idf_filial_id = ?", array($id));
        $uso = $res_uso ? (int) (mysqli_fetch_assoc($res_uso)['cnt'] ?? 0) : 1;
        if ($uso > 0) {
            $erro = 'Essa filial tem fiéis. Desative em vez de excluir (ou mude a filial deles antes).';
        } elseif (db_query($conn, "DELETE FROM id_filial WHERE idl_id = ?", array($id))) {
            admin_filiais_redirecionar($url_volta, 'Filial excluída.', '#filiais');
        } else {
            $erro = 'Não foi possível excluir a filial.';
        }
    } elseif ($acao === 'fiel_filial') {
        $fiel_id = (int) ($_POST['fiel_id'] ?? 0);
        $escolha = site_entrada_texto($_POST['IDF_FILIAL_ESCOLHA'] ?? '', false);
        if ($escolha === '') {
            // Admin pode voltar o fiel para "não informada".
            $ok = db_query($conn, "UPDATE id_fiel SET idf_filial_id = NULL, idf_sem_filial = 0 WHERE idf_id = ?", array($fiel_id));
            $erro_definir = $ok ? null : 'Não foi possível salvar a filial.';
        } else {
            $erro_definir = fiel_filial_definir($conn, $fiel_id, $escolha);
        }
        if ($erro_definir === null) {
            admin_filiais_redirecionar($url_volta, 'Filial do fiel atualizada.', '#fiel-' . $fiel_id);
        }
        $erro = $erro_definir;
    }
}
if (!empty($_SESSION['admin_filiais_ok'])) {
    $mensagem = $_SESSION['admin_filiais_ok'];
    unset($_SESSION['admin_filiais_ok']);
}

$filiais = array();
$uso_por_filial = array();
$total_sem = 0;
$total_nenhuma = 0;
$fieis = array();
if ($disponivel) {
    $filiais = filiais_listar($conn);

    $res = mysqli_query($conn, "SELECT idf_filial_id, COUNT(*) AS cnt FROM id_fiel WHERE idf_filial_id IS NOT NULL AND idf_status <> 'negado' GROUP BY idf_filial_id");
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $uso_por_filial[(int) $linha['idf_filial_id']] = (int) $linha['cnt'];
    }
    $res = mysqli_query($conn, "SELECT COUNT(*) FILTER (WHERE idf_sem_filial = 1) AS nenhuma, COUNT(*) FILTER (WHERE idf_sem_filial = 0) AS sem FROM id_fiel WHERE idf_filial_id IS NULL AND idf_status <> 'negado'");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    $total_nenhuma = (int) ($linha['nenhuma'] ?? 0);
    $total_sem = (int) ($linha['sem'] ?? 0);

    $where = array("f.idf_status <> 'negado'");
    $params_where = array();
    if ($filtro === 'sem') {
        $where[] = 'f.idf_filial_id IS NULL AND f.idf_sem_filial = 0';
    } elseif ($filtro === FILIAL_NENHUMA) {
        $where[] = 'f.idf_filial_id IS NULL AND f.idf_sem_filial = 1';
    } elseif (ctype_digit($filtro)) {
        $where[] = 'f.idf_filial_id = ?';
        $params_where[] = (int) $filtro;
    }
    if ($busca !== '') {
        $busca_like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $busca) . '%';
        $where[] = "(f.idf_nome ILIKE ? OR f.idf_email ILIKE ?)";
        $params_where[] = $busca_like;
        $params_where[] = $busca_like;
    }
    $res = db_query($conn, "
        SELECT f.idf_id, f.idf_nome, f.idf_email, f.idf_status, f.idf_filial, f.idf_filial_id, f.idf_sem_filial
        FROM id_fiel f
        WHERE " . implode(' AND ', $where) . "
        ORDER BY (f.idf_filial_id IS NULL AND f.idf_sem_filial = 0) DESC, f.idf_nome ASC
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
    <title>Missão Manancial | Filiais</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'filiais', 'Filiais', 'fa-map-location-dot'); ?>

    <p class="text-muted mb-4">
        A lista que o fiel escolhe no cadastro e no perfil. Por enquanto são os <strong>estados</strong>, até a igreja mandar as filiais de verdade:
        aí é só cadastrar as filiais, desativar os estados e ajustar a filial de quem já escolheu um estado.
        "<?php echo FILIAL_NENHUMA_ROTULO; ?>" é uma opção fixa, para quem não frequenta a igreja mas gostou da plataforma.
    </p>

    <?php if ($mensagem !== '') { ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensagem); ?></div>
    <?php } ?>
    <?php if ($erro !== '') { ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($erro); ?></div>
    <?php } ?>

    <?php if (!$disponivel) { ?>
        <div class="alert alert-warning">
            A lista de filiais ainda não existe no banco. Rode a seção 76 do
            <code>database/bancosupabase.md</code> no Supabase para liberar esta tela. Até lá, o cadastro continua com a filial em texto livre.
        </div>
    <?php } else { ?>

    <div class="admin-card" id="filiais">
        <h5 class="admin-card-title"><i class="fas fa-list-ol"></i>Lista de filiais</h5>
        <p class="text-muted small">
            Filial desativada some da escolha, mas quem já tem continua com ela. Só dá para excluir filial sem fiéis.
            O código é opcional (ex.: MAN-CPS) e serve para buscas e, se cada filial ganhar um site próprio, para o endereço dele.
        </p>
        <div class="table-responsive campo-perfil">
            <table class="table data-table align-middle">
                <thead>
                    <tr><th>Nome</th><th style="width:130px;">Código</th><th style="width:80px;">UF</th><th>Cidade</th><th style="width:100px;">Ordem</th><th>Ativa</th><th>Fiéis</th><th>Ações</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($filiais as $fl) { $form_id = 'fl' . $fl['id']; $uso = $uso_por_filial[$fl['id']] ?? 0; ?>
                        <tr>
                            <td><input type="text" name="idl_nome" form="<?php echo $form_id; ?>" class="form-control custom-input" value="<?php echo htmlspecialchars($fl['nome']); ?>" required minlength="2" maxlength="<?php echo FILIAL_NOME_MAX; ?>" aria-label="Nome da filial"></td>
                            <td><input type="text" name="idl_codigo" form="<?php echo $form_id; ?>" class="form-control custom-input" value="<?php echo htmlspecialchars($fl['codigo']); ?>" maxlength="20" aria-label="Código"></td>
                            <td><input type="text" name="idl_estado" form="<?php echo $form_id; ?>" class="form-control custom-input" value="<?php echo htmlspecialchars($fl['estado']); ?>" maxlength="2" aria-label="UF"></td>
                            <td><input type="text" name="idl_cidade" form="<?php echo $form_id; ?>" class="form-control custom-input" value="<?php echo htmlspecialchars($fl['cidade']); ?>" maxlength="100" aria-label="Cidade"></td>
                            <td><input type="number" name="idl_ordem" form="<?php echo $form_id; ?>" class="form-control custom-input" value="<?php echo $fl['ordem']; ?>" min="0" max="9999" aria-label="Ordem"></td>
                            <td><input type="checkbox" name="idl_ativa" form="<?php echo $form_id; ?>" class="form-check-input" <?php echo $fl['ativa'] ? 'checked' : ''; ?> aria-label="Filial ativa"></td>
                            <td><a href="admin_filiais.php?filtro=<?php echo $fl['id']; ?>#fieis"><?php echo $uso; ?></a></td>
                            <td class="text-nowrap">
                                <form method="POST" action="<?php echo htmlspecialchars($url_volta); ?>" id="<?php echo $form_id; ?>" class="d-inline">
                                    <?php echo csrf_campo(); ?>
                                    <input type="hidden" name="acao" value="filial_salvar">
                                    <input type="hidden" name="idl_id" value="<?php echo $fl['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-light" title="Salvar"><i class="fas fa-save"></i></button>
                                </form>
                                <?php if ($uso === 0) { ?>
                                    <form method="POST" action="<?php echo htmlspecialchars($url_volta); ?>" class="d-inline" data-confirmar="<?php echo htmlspecialchars('Excluir a filial "' . $fl['nome'] . '"?', ENT_QUOTES); ?>">
                                        <?php echo csrf_campo(); ?>
                                        <input type="hidden" name="acao" value="filial_excluir">
                                        <input type="hidden" name="idl_id" value="<?php echo $fl['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <td><input type="text" name="idl_nome" form="flNova" class="form-control custom-input" placeholder="Nova filial" required minlength="2" maxlength="<?php echo FILIAL_NOME_MAX; ?>" aria-label="Nome da nova filial"></td>
                        <td><input type="text" name="idl_codigo" form="flNova" class="form-control custom-input" placeholder="Opcional" maxlength="20" aria-label="Código da nova filial"></td>
                        <td><input type="text" name="idl_estado" form="flNova" class="form-control custom-input" placeholder="UF" maxlength="2" aria-label="UF da nova filial"></td>
                        <td><input type="text" name="idl_cidade" form="flNova" class="form-control custom-input" placeholder="Cidade" maxlength="100" aria-label="Cidade da nova filial"></td>
                        <td><input type="number" name="idl_ordem" form="flNova" class="form-control custom-input" value="<?php echo $filiais ? max(array_column($filiais, 'ordem')) + 10 : 10; ?>" min="0" max="9999" aria-label="Ordem da nova filial"></td>
                        <td colspan="2"></td>
                        <td>
                            <form method="POST" action="<?php echo htmlspecialchars($url_volta); ?>" id="flNova" class="d-inline">
                                <?php echo csrf_campo(); ?>
                                <input type="hidden" name="acao" value="filial_salvar">
                                <input type="hidden" name="idl_id" value="0">
                                <button type="submit" class="btn btn-sm btn-light fw-bold"><i class="fas fa-plus me-1"></i>Adicionar</button>
                            </form>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="6"><em><?php echo FILIAL_NENHUMA_ROTULO; ?></em> <small class="text-muted">(opção fixa)</small></td>
                        <td><a href="admin_filiais.php?filtro=<?php echo FILIAL_NENHUMA; ?>#fieis"><?php echo $total_nenhuma; ?></a></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card" id="fieis">
        <h5 class="admin-card-title"><i class="fas fa-user-tag"></i>Filial de cada fiel</h5>
        <form method="GET" action="admin_filiais.php#fieis" class="row g-2 align-items-end mb-3 campo-perfil">
            <div class="col-sm-5">
                <label class="form-label small" for="busca">Buscar por nome ou e-mail</label>
                <input type="search" name="busca" id="busca" class="form-control custom-input" value="<?php echo htmlspecialchars($busca); ?>">
            </div>
            <div class="col-sm-4">
                <label class="form-label small" for="filtro">Mostrar</label>
                <select name="filtro" id="filtro" class="form-select custom-input">
                    <option value="todos">Todos os fiéis</option>
                    <option value="sem" <?php echo $filtro === 'sem' ? 'selected' : ''; ?>>Filial não informada (<?php echo $total_sem; ?>)</option>
                    <option value="<?php echo FILIAL_NENHUMA; ?>" <?php echo $filtro === FILIAL_NENHUMA ? 'selected' : ''; ?>><?php echo FILIAL_NENHUMA_ROTULO; ?> (<?php echo $total_nenhuma; ?>)</option>
                    <?php foreach ($filiais as $fl) { ?>
                        <option value="<?php echo $fl['id']; ?>" <?php echo $filtro === (string) $fl['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($fl['nome'] . ($fl['ativa'] ? '' : ' (desativada)')); ?> (<?php echo $uso_por_filial[$fl['id']] ?? 0; ?>)</option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-sm-3">
                <button type="submit" class="btn btn-outline-light w-100"><i class="fas fa-search me-1"></i>Filtrar</button>
            </div>
        </form>

        <?php if ($total_sem > 0 && $filtro !== 'sem') { ?>
            <p class="small mb-3" style="color:#f59e0b;"><i class="fas fa-circle-exclamation me-1"></i><?php echo $total_sem; ?> fiel(is) ainda sem filial escolhida da lista — eles aparecem primeiro. Ao abrir o perfil, eles são convidados a escolher.</p>
        <?php } ?>

        <div class="table-responsive campo-perfil">
            <table class="table data-table align-middle mb-0">
                <thead>
                    <tr><th>Fiel</th><th>Filial</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($fieis as $f) {
                        $atual = fiel_filial_escolha_atual($f['idf_filial_id'], $f['idf_sem_filial']);
                    ?>
                        <tr id="fiel-<?php echo (int) $f['idf_id']; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($f['idf_nome']); ?></strong>
                                <?php if ($f['idf_status'] === 'pendente') { ?><span class="badge bg-warning text-dark ms-1">Pendente</span><?php } ?>
                                <div class="small text-muted"><?php echo htmlspecialchars($f['idf_email']); ?></div>
                                <?php if ($atual === '' && trim((string) $f['idf_filial']) !== '') { ?>
                                    <div class="small text-muted">Escreveu no cadastro: "<?php echo htmlspecialchars($f['idf_filial']); ?>"</div>
                                <?php } ?>
                            </td>
                            <td style="min-width: 280px;">
                                <form method="POST" action="<?php echo htmlspecialchars($url_volta); ?>" class="d-flex gap-2">
                                    <?php echo csrf_campo(); ?>
                                    <input type="hidden" name="acao" value="fiel_filial">
                                    <input type="hidden" name="fiel_id" value="<?php echo (int) $f['idf_id']; ?>">
                                    <select name="IDF_FILIAL_ESCOLHA" class="form-select custom-input" aria-label="Filial de <?php echo htmlspecialchars($f['idf_nome']); ?>">
                                        <option value="">— Não informada —</option>
                                        <?php foreach ($filiais as $fl) { if (!$fl['ativa'] && (string) $fl['id'] !== $atual) { continue; } ?>
                                            <option value="<?php echo $fl['id']; ?>" <?php echo (string) $fl['id'] === $atual ? 'selected' : ''; ?>><?php echo htmlspecialchars($fl['nome'] . ($fl['ativa'] ? '' : ' (desativada)')); ?></option>
                                        <?php } ?>
                                        <option value="<?php echo FILIAL_NENHUMA; ?>" <?php echo $atual === FILIAL_NENHUMA ? 'selected' : ''; ?>><?php echo FILIAL_NENHUMA_ROTULO; ?></option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-light" title="Salvar filial"><i class="fas fa-save"></i></button>
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
