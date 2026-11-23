<?php
// Tipos de fiel: cada tipo diz o que o fiel pode mudar no próprio perfil
// (nome, e-mail, CPF, dados pessoais, foto, vínculo de membro). Aqui o
// admin cria e edita os tipos e escolhe o tipo de cada fiel; quem não tem
// tipo fica no padrão. Funções em "TIPOS DE FIEL" de php/conexao.php;
// tabela na seção 74 do bancosupabase.md.
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));
// Ações que deram certo vão para o Registro de atividade (admin_log_ao_redirecionar()).
admin_log_ao_redirecionar($conn, 'tipos_fiel');

$mensagem = "";
$erro = "";
$disponivel = fiel_tipos_disponivel($conn);
$catalogo = fiel_permissoes_catalogo();
$tipos = fiel_tipos_lista($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $disponivel) {
    $acao = site_entrada_texto($_POST['acao'] ?? '');
    $tipo_id = (int) ($_POST['idft_id'] ?? 0);

    if ($acao === 'salvar_tipo') {
        $nome = trim(site_entrada_texto($_POST['nome'] ?? ''));
        $descricao = trim(site_entrada_texto($_POST['descricao'] ?? ''));
        $permissoes = array();
        foreach ((array) ($_POST['permissoes'] ?? array()) as $chave) {
            if (is_string($chave) && isset($catalogo[$chave])) {
                $permissoes[] = $chave;
            }
        }
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 60 || mb_strlen($descricao) > 200) {
            $erro = "Informe o nome do tipo (de 2 a 60 caracteres) e uma descrição de até 200.";
        } elseif ($tipo_id > 0 && !isset($tipos[$tipo_id])) {
            $erro = "Tipo não encontrado.";
        } else {
            $params = array($nome, $descricao !== '' ? $descricao : null, implode(',', array_unique($permissoes)));
            $ok = $tipo_id > 0
                ? db_query($conn, "UPDATE id_fiel_tipo SET idft_nome = ?, idft_descricao = ?, idft_permissoes = ? WHERE idft_id = ?", array_merge($params, array($tipo_id)))
                : db_query($conn, "INSERT INTO id_fiel_tipo (idft_nome, idft_descricao, idft_permissoes) VALUES (?, ?, ?)", $params);
            if ($ok) {
                $_SESSION['tipos_fiel_ok'] = "Tipo salvo.";
                header('Location: admin_tipos_fiel.php#tipos');
                exit;
            }
            $erro = site_erro_banco("Não foi possível salvar o tipo.");
        }
    } elseif ($acao === 'tornar_padrao') {
        if (!isset($tipos[$tipo_id])) {
            $erro = "Tipo não encontrado.";
        } else {
            // Em dois passos: o índice único só aceita um padrão por vez.
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $conn->beginTransaction();
            $ok = db_query($conn, "UPDATE id_fiel_tipo SET idft_padrao = 0 WHERE idft_padrao = 1")
                && db_query($conn, "UPDATE id_fiel_tipo SET idft_padrao = 1 WHERE idft_id = ?", array($tipo_id));
            if ($ok && $conn->commit()) {
                $_SESSION['tipos_fiel_ok'] = "\"" . $tipos[$tipo_id]['idft_nome'] . "\" agora é o tipo padrão.";
                header('Location: admin_tipos_fiel.php#tipos');
                exit;
            }
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $erro = site_erro_banco("Não foi possível mudar o tipo padrão.");
        }
    } elseif ($acao === 'excluir_tipo') {
        if (!isset($tipos[$tipo_id])) {
            $erro = "Tipo não encontrado.";
        } elseif ((int) $tipos[$tipo_id]['idft_padrao'] === 1) {
            $erro = "O tipo padrão não pode ser excluído. Escolha outro como padrão antes.";
        } elseif (db_query($conn, "DELETE FROM id_fiel_tipo WHERE idft_id = ?", array($tipo_id))) {
            // ON DELETE SET NULL: os fiéis dele voltam para o padrão.
            $_SESSION['tipos_fiel_ok'] = "Tipo excluído. Os fiéis dele voltaram para o tipo padrão.";
            header('Location: admin_tipos_fiel.php#tipos');
            exit;
        } else {
            $erro = site_erro_banco("Não foi possível excluir o tipo.");
        }
    } elseif ($acao === 'definir_tipo') {
        $fiel_id = (int) ($_POST['fiel_id'] ?? 0);
        if ($tipo_id > 0 && !isset($tipos[$tipo_id])) {
            $erro = "Tipo inválido.";
        } elseif (db_query($conn, "UPDATE ID_FIEL SET idf_tipo_id = ? WHERE IDF_ID = ? AND NOT EXISTS (SELECT 1 FROM ID_ADMIN a WHERE a.IDA_FIEL_ID = ID_FIEL.IDF_ID AND a.IDA_ATIVO = 1)", array($tipo_id > 0 ? $tipo_id : null, $fiel_id)) && mysqli_affected_rows($conn) > 0) {
            $_SESSION['tipos_fiel_ok'] = "Tipo atualizado.";
            $voltar_query = preg_replace('/[^a-zA-Z0-9=&_%.@-]/', '', site_entrada_texto($_POST['voltar'] ?? ''));
            header('Location: admin_tipos_fiel.php' . ($voltar_query !== '' ? '?' . $voltar_query : '') . '#fieis');
            exit;
        } else {
            $erro = "Fiel não encontrado.";
        }
    }
}
if (!empty($_SESSION['tipos_fiel_ok'])) {
    $mensagem = $_SESSION['tipos_fiel_ok'];
    unset($_SESSION['tipos_fiel_ok']);
}

$tipos = fiel_tipos_lista($conn);
$editar = $tipos[(int) ($_GET['editar'] ?? 0)] ?? null;
$padrao_id = 0;
foreach ($tipos as $t) {
    if ((int) $t['idft_padrao'] === 1) {
        $padrao_id = (int) $t['idft_id'];
    }
}

// Lista de fiéis aprovados: busca por nome/e-mail e filtro por tipo. Quem é
// admin fica de fora: o acesso dele vem do tipo de admin (Admin → Tipos de
// Admin), não do tipo de fiel.
$busca = trim(site_entrada_texto($_GET['q'] ?? ''));
$filtro_tipo = (int) ($_GET['tipo'] ?? 0); // -1 = padrão (sem tipo)
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$por_pagina = 50;
$fieis = array();
$total_fieis = 0;
if ($disponivel) {
    $where = array("f.IDF_STATUS = 'aprovado'", "NOT EXISTS (SELECT 1 FROM ID_ADMIN a WHERE a.IDA_FIEL_ID = f.IDF_ID AND a.IDA_ATIVO = 1)");
    $params = array();
    if ($busca !== '') {
        $termo = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), mb_strtolower($busca)) . '%';
        $where[] = "(LOWER(f.IDF_NOME) LIKE ? OR LOWER(f.IDF_EMAIL) LIKE ?)";
        $params[] = $termo;
        $params[] = $termo;
    }
    if ($filtro_tipo === -1) {
        $where[] = "f.idf_tipo_id IS NULL";
    } elseif ($filtro_tipo > 0) {
        $where[] = "f.idf_tipo_id = ?";
        $params[] = $filtro_tipo;
    }
    $sql_where = 'WHERE ' . implode(' AND ', $where);
    $res = db_query($conn, "SELECT COUNT(*) AS total FROM ID_FIEL f $sql_where", $params);
    $total_fieis = (int) (($res ? mysqli_fetch_assoc($res) : null)['total'] ?? 0);
    $res = db_query($conn, "
        SELECT f.IDF_ID, f.IDF_NOME, f.IDF_EMAIL, f.idf_tipo_id
        FROM ID_FIEL f
        $sql_where
        ORDER BY LOWER(f.IDF_NOME)
        LIMIT $por_pagina OFFSET " . (($pagina - 1) * $por_pagina), $params);
    $fieis = $res ? $res->todas_as_linhas() : array();
}
$query_lista = http_build_query(array_filter(array('q' => $busca, 'tipo' => $filtro_tipo ?: null, 'pagina' => $pagina > 1 ? $pagina : null)));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Tipos de fiel</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'tipos_fiel', 'Tipos de fiel', 'fa-user-tag'); ?>

    <?php if ($mensagem !== "") { ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
    <?php } ?>
    <?php if ($erro !== "") { ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
    <?php } ?>

    <?php if (!$disponivel) { ?>
        <div class="alert alert-danger">Os tipos de fiel ainda não existem no banco. Rode a seção 74 do <code>database/bancosupabase.md</code> no SQL Editor do Supabase. Até lá, todo fiel edita o que o perfil já permitia (dados pessoais, foto e vínculo de membro).</div>
    <?php } else { ?>

    <div class="card mb-4" id="tipos" style="background: var(--bg-light); border: 1px solid var(--border-color);">
        <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
            <h5 class="mb-0"><i class="fas fa-user-tag me-2" style="color:#60a5fa;"></i>Tipos</h5>
            <small class="text-muted">O que cada tipo de fiel pode mudar no próprio perfil. Quem não tem tipo escolhido fica no <strong>padrão</strong> (inclusive quem se cadastra).</small>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <?php foreach ($tipos as $t) { ?>
                    <div class="col-md-6">
                        <div class="permissoes-grupo h-100">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <strong><?php echo htmlspecialchars($t['idft_nome']); ?></strong>
                                    <?php if ((int) $t['idft_padrao'] === 1) { ?><span class="badge bg-primary ms-1">Padrão</span><?php } ?>
                                    <small class="d-block text-muted"><?php echo (int) $t['idft_padrao'] === 1 ? 'todos sem tipo escolhido' : (int) $t['membros'] . ' fiel(is)'; ?><?php echo !empty($t['idft_descricao']) ? ' · ' . htmlspecialchars($t['idft_descricao']) : ''; ?></small>
                                </div>
                                <div class="d-flex gap-1 flex-shrink-0">
                                    <a href="admin_tipos_fiel.php?editar=<?php echo (int) $t['idft_id']; ?>#formTipo" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fas fa-pen"></i></a>
                                    <?php if ((int) $t['idft_padrao'] !== 1) { ?>
                                        <form method="POST" data-confirmar="Tornar &quot;<?php echo htmlspecialchars($t['idft_nome']); ?>&quot; o tipo padrão? Vale para todos os fiéis sem tipo escolhido.">
                                            <?php echo csrf_campo(); ?>
                                            <input type="hidden" name="acao" value="tornar_padrao">
                                            <input type="hidden" name="idft_id" value="<?php echo (int) $t['idft_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Tornar padrão"><i class="fas fa-star"></i></button>
                                        </form>
                                        <form method="POST" data-confirmar="Excluir o tipo &quot;<?php echo htmlspecialchars($t['idft_nome']); ?>&quot;? Os fiéis dele voltam para o tipo padrão.">
                                            <?php echo csrf_campo(); ?>
                                            <input type="hidden" name="acao" value="excluir_tipo">
                                            <input type="hidden" name="idft_id" value="<?php echo (int) $t['idft_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php } ?>
                                </div>
                            </div>
                            <ul class="tipos-fiel-permissoes">
                                <?php foreach ($catalogo as $chave => $rotulo) {
                                    $tem = in_array($chave, $t['permissoes'], true);
                                ?>
                                    <li class="<?php echo $tem ? 'is-sim' : 'is-nao'; ?>"><i class="fas <?php echo $tem ? 'fa-check' : 'fa-xmark'; ?> me-1" aria-hidden="true"></i><span class="visually-hidden"><?php echo $tem ? 'Pode: ' : 'Não pode: '; ?></span><?php echo htmlspecialchars($rotulo); ?></li>
                                <?php } ?>
                            </ul>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <form method="POST" id="formTipo" class="permissoes-form-grupo">
                <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="salvar_tipo">
                <input type="hidden" name="idft_id" value="<?php echo (int) ($editar['idft_id'] ?? 0); ?>">
                <h6 class="mb-3"><?php echo $editar ? 'Editar tipo: ' . htmlspecialchars($editar['idft_nome']) : '<i class="fas fa-plus me-1"></i>Novo tipo'; ?></h6>
                <div class="row g-2 mb-3">
                    <div class="col-md-4"><input type="text" name="nome" class="form-control custom-input" maxlength="60" required placeholder="Nome (ex.: Líder de célula)" aria-label="Nome do tipo" value="<?php echo htmlspecialchars((string) ($editar['idft_nome'] ?? '')); ?>"></div>
                    <div class="col-md-8"><input type="text" name="descricao" class="form-control custom-input" maxlength="200" placeholder="Descrição (opcional)" aria-label="Descrição do tipo" value="<?php echo htmlspecialchars((string) ($editar['idft_descricao'] ?? '')); ?>"></div>
                </div>
                <div class="permissoes-telas mb-3">
                    <?php foreach ($catalogo as $chave => $rotulo) { ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissoes[]" value="<?php echo $chave; ?>" id="perm_<?php echo $chave; ?>" <?php echo $editar ? (in_array($chave, $editar['permissoes'], true) ? 'checked' : '') : (in_array($chave, array('dados', 'foto', 'membro'), true) ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="perm_<?php echo $chave; ?>"><?php echo htmlspecialchars($rotulo); ?></label>
                        </div>
                    <?php } ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-salvar"><i class="fas fa-save me-1"></i><?php echo $editar ? 'Salvar tipo' : 'Criar tipo'; ?></button>
                    <?php if ($editar) { ?><a href="admin_tipos_fiel.php#tipos" class="btn btn-outline-secondary">Cancelar</a><?php } ?>
                </div>
                <small class="text-muted d-block mt-2">Vale na próxima página que o fiel abrir. Trocar o e-mail sempre pede a senha atual do fiel.</small>
            </form>
        </div>
    </div>

    <div class="card mb-4" id="fieis" style="background: var(--bg-light); border: 1px solid var(--border-color);">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
            <h5 class="mb-0"><i class="fas fa-users me-2" style="color:#60a5fa;"></i>Fiéis (<?php echo $total_fieis; ?>)</h5>
            <form method="GET" class="d-flex flex-wrap gap-2" action="admin_tipos_fiel.php#fieis">
                <input type="search" name="q" class="form-control form-control-sm custom-input" placeholder="Nome ou e-mail" value="<?php echo htmlspecialchars($busca); ?>" aria-label="Buscar fiel">
                <select name="tipo" class="form-select form-select-sm custom-input" data-enviar-ao-mudar aria-label="Filtrar por tipo">
                    <option value="0">Todos os tipos</option>
                    <option value="-1" <?php echo $filtro_tipo === -1 ? 'selected' : ''; ?>>Sem tipo (padrão)</option>
                    <?php foreach ($tipos as $t) { ?><option value="<?php echo (int) $t['idft_id']; ?>" <?php echo $filtro_tipo === (int) $t['idft_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['idft_nome']); ?></option><?php } ?>
                </select>
                <button type="submit" class="btn btn-sm btn-salvar"><i class="fas fa-search"></i><span class="visually-hidden">Buscar</span></button>
            </form>
        </div>
        <div class="card-body p-0">
            <?php if (!$fieis) { ?>
                <p class="text-muted p-3 mb-0">Nenhum fiel encontrado.</p>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th class="ps-3">Fiel</th><th class="pe-3" style="width: 280px;">Tipo</th></tr></thead>
                        <tbody>
                            <?php foreach ($fieis as $f) {
                                $tipo_atual = $f['idf_tipo_id'] !== null ? (int) $f['idf_tipo_id'] : 0;
                            ?>
                                <tr>
                                    <td class="ps-3"><?php echo htmlspecialchars($f['IDF_NOME']); ?><br><small class="text-muted"><?php echo htmlspecialchars($f['IDF_EMAIL']); ?></small></td>
                                    <td class="pe-3">
                                        <form method="POST">
                                            <?php echo csrf_campo(); ?>
                                            <input type="hidden" name="acao" value="definir_tipo">
                                            <input type="hidden" name="fiel_id" value="<?php echo (int) $f['IDF_ID']; ?>">
                                            <input type="hidden" name="voltar" value="<?php echo htmlspecialchars($query_lista); ?>">
                                            <select name="idft_id" class="form-select form-select-sm custom-input" data-enviar-ao-mudar aria-label="Tipo de <?php echo htmlspecialchars($f['IDF_NOME']); ?>">
                                                <option value="0">Padrão<?php echo $padrao_id ? ' (' . htmlspecialchars($tipos[$padrao_id]['idft_nome']) . ')' : ''; ?></option>
                                                <?php foreach ($tipos as $t) { if ((int) $t['idft_id'] === $padrao_id) { continue; } ?>
                                                    <option value="<?php echo (int) $t['idft_id']; ?>" <?php echo $tipo_atual === (int) $t['idft_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['idft_nome']); ?></option>
                                                <?php } ?>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_fieis > $por_pagina) {
                    $paginas = (int) ceil($total_fieis / $por_pagina);
                ?>
                    <nav class="d-flex justify-content-between align-items-center p-3" aria-label="Páginas">
                        <small class="text-muted">Página <?php echo $pagina; ?> de <?php echo $paginas; ?></small>
                        <div class="d-flex gap-2">
                            <?php if ($pagina > 1) { ?><a class="btn btn-sm btn-outline-secondary" href="admin_tipos_fiel.php?<?php echo htmlspecialchars(http_build_query(array_filter(array('q' => $busca, 'tipo' => $filtro_tipo ?: null, 'pagina' => $pagina - 1)))); ?>#fieis">Anterior</a><?php } ?>
                            <?php if ($pagina < $paginas) { ?><a class="btn btn-sm btn-outline-secondary" href="admin_tipos_fiel.php?<?php echo htmlspecialchars(http_build_query(array_filter(array('q' => $busca, 'tipo' => $filtro_tipo ?: null, 'pagina' => $pagina + 1)))); ?>#fieis">Próxima</a><?php } ?>
                        </div>
                    </nav>
                <?php } ?>
            <?php } ?>
        </div>
    </div>

    <?php } ?>

    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
