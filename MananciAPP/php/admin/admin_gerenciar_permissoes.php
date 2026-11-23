<?php
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));

$mensagem = "";
$erro = "";
$admin_id_logado = isset($_SESSION["Usuario_id"]) ? (int) $_SESSION["Usuario_id"] : 0;
// Grupos de permissões (seção 73): quem tem acesso total cria os grupos e
// escolhe o de cada admin; quem está num grupo só promove para o próprio.
$grupos_disponivel = admin_grupos_disponivel($conn);
$gerencia_grupos = $grupos_disponivel && admin_gerencia_grupos();
$grupos = admin_grupos_lista($conn);
$telas = admin_telas();
// Financeiro, Contribuições e as partes do Financeiro: só superadmin marca.
$eh_superadmin = auth_eh_superadmin();
$telas_restritas = admin_telas_restritas();
$financeiro_partes = financeiro_partes();

// Proteções do super admin (equipe interna, SUPERADMIN_EMAILS): só outro
// super admin mexe na conta dele (promover, remover, trocar o tipo) e só
// super admin põe alguém num tipo com telas de dinheiro (Financeiro,
// Contribuições) — senão um admin da igreja repassaria esse acesso.
$conta_eh_superadmin = function ($email) {
    return in_array(mb_strtolower(trim((string) $email)), superadmin_emails(), true);
};
$grupo_tem_dinheiro = function ($grupo_id) use (&$grupos, $telas_restritas) {
    foreach ($grupos[(int) $grupo_id]['telas'] ?? array() as $chave) {
        if (isset($telas_restritas[$chave]) || strpos($chave, 'financeiro.') === 0) {
            return true;
        }
    }
    return false;
};
// O termo "super admin" e a existência dessas contas são restritos: para
// quem não é super admin, as contas da equipe nem aparecem na lista abaixo,
// os tipos com dinheiro somem dos seletores e as mensagens ficam neutras
// (só chegam aqui por requisição forjada).
$aviso_so_superadmin = "Usuário não encontrado.";
$aviso_tipo_dinheiro = "Você não pode colocar alguém nesse tipo de admin.";
$acao_post = isset($_POST["acao"]) ? site_entrada_texto($_POST["acao"]) : "";

// Grupo de um admin (null = acesso total), para as regras abaixo.
function permissoes_grupo_do_admin($conn, $fiel_id) {
    $res = db_query($conn, "SELECT ida_grupo_id FROM ID_ADMIN WHERE IDA_FIEL_ID = ? LIMIT 1", array((int) $fiel_id));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha && $linha['ida_grupo_id'] !== null ? (int) $linha['ida_grupo_id'] : null;
}

// Processar mudança de permissão
if ($_SERVER["REQUEST_METHOD"] == "POST" && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && in_array($acao_post, array('salvar_grupo', 'excluir_grupo', 'definir_grupo'), true)) {
    if (!$gerencia_grupos) {
        $erro = "Só administradores com acesso total gerenciam os tipos de admin.";
    } elseif ($acao_post === 'salvar_grupo') {
        $grupo_id = (int) ($_POST['idag_id'] ?? 0);
        $nome = trim(site_entrada_texto($_POST['nome'] ?? ''));
        $descricao = trim(site_entrada_texto($_POST['descricao'] ?? ''));
        $telas_escolhidas = array();
        foreach ((array) ($_POST['telas'] ?? array()) as $chave) {
            if (is_string($chave) && isset($telas[$chave])) {
                $telas_escolhidas[] = $chave;
            }
        }
        if ($eh_superadmin) {
            $telas_escolhidas = array_merge($telas_escolhidas, admin_telas_da_lista(implode(',', array_filter((array) ($_POST['telas_restritas'] ?? array()), 'is_string'))));
        } elseif ($grupo_id > 0 && isset($grupos[$grupo_id])) {
            // Quem não é superadmin não vê esses campos: o que o tipo já tinha fica.
            foreach ($grupos[$grupo_id]['telas'] as $chave) {
                if (!isset($telas[$chave])) {
                    $telas_escolhidas[] = $chave;
                }
            }
        }
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 60 || mb_strlen($descricao) > 200) {
            $erro = "Informe o nome do tipo (de 2 a 60 caracteres) e uma descrição de até 200.";
        } elseif ($grupo_id > 0 && !isset($grupos[$grupo_id])) {
            $erro = "Tipo de admin não encontrado.";
        } else {
            $params = array($nome, $descricao !== '' ? $descricao : null, implode(',', array_unique($telas_escolhidas)));
            $ok = $grupo_id > 0
                ? db_query($conn, "UPDATE id_admin_grupo SET idag_nome = ?, idag_descricao = ?, idag_telas = ? WHERE idag_id = ?", array_merge($params, array($grupo_id)))
                : db_query($conn, "INSERT INTO id_admin_grupo (idag_nome, idag_descricao, idag_telas) VALUES (?, ?, ?)", $params);
            if ($ok) {
                admin_log($conn, 'grupo.salvar', 'grupo_admin', $grupo_id ?: null, array('nome' => $nome, 'telas' => $params[2]));
                $_SESSION['permissoes_ok'] = "Tipo de admin salvo.";
                header('Location: admin_gerenciar_permissoes.php#grupos');
                exit;
            }
            $erro = site_erro_banco("Não foi possível salvar o tipo de admin.");
        }
    } elseif ($acao_post === 'excluir_grupo') {
        $grupo_id = (int) ($_POST['idag_id'] ?? 0);
        if (!isset($grupos[$grupo_id])) {
            $erro = "Tipo de admin não encontrado.";
        } elseif ((int) $grupos[$grupo_id]['membros'] > 0) {
            $erro = "Este tipo tem administradores. Mude-os de tipo antes de excluir.";
        } elseif (db_query($conn, "DELETE FROM id_admin_grupo WHERE idag_id = ?", array($grupo_id))) {
            admin_log($conn, 'grupo.excluir', 'grupo_admin', $grupo_id, array('nome' => (string) $grupos[$grupo_id]['idag_nome']));
            $_SESSION['permissoes_ok'] = "Tipo de admin excluído.";
            header('Location: admin_gerenciar_permissoes.php#grupos');
            exit;
        } else {
            $erro = site_erro_banco("Não foi possível excluir o tipo de admin.");
        }
    } else {
        $usuario_id = (int) ($_POST['usuario_id'] ?? 0);
        $grupo_id = (int) ($_POST['grupo_id'] ?? 0);
        $res_admin = db_query($conn, "SELECT f.IDF_NOME, f.IDF_EMAIL FROM ID_ADMIN a JOIN ID_FIEL f ON f.IDF_ID = a.IDA_FIEL_ID WHERE a.IDA_FIEL_ID = ? AND a.IDA_ATIVO = 1 LIMIT 1", array($usuario_id));
        $linha_admin = $res_admin ? mysqli_fetch_assoc($res_admin) : null;
        if (!$linha_admin) {
            $erro = "Administrador não encontrado.";
        } elseif ($usuario_id === $admin_id_logado) {
            // Mudar o próprio grupo podia trancar quem muda fora desta tela.
            $erro = "Você não pode mudar o seu próprio tipo de admin.";
        } elseif ($grupo_id > 0 && !isset($grupos[$grupo_id])) {
            $erro = "Tipo de admin inválido.";
        } elseif (!$eh_superadmin && $conta_eh_superadmin($linha_admin['IDF_EMAIL'])) {
            $erro = $aviso_so_superadmin;
        } elseif (!$eh_superadmin && $grupo_id > 0 && $grupo_tem_dinheiro($grupo_id)) {
            $erro = $aviso_tipo_dinheiro;
        } elseif (db_query($conn, "UPDATE ID_ADMIN SET ida_grupo_id = ? WHERE IDA_FIEL_ID = ?", array($grupo_id > 0 ? $grupo_id : null, $usuario_id))) {
            admin_log($conn, 'admin.tipo', 'fiel', $usuario_id, array('nome' => (string) $linha_admin['IDF_NOME'], 'tipo' => $grupo_id > 0 ? $grupos[$grupo_id]['idag_nome'] : 'acesso total'));
            $_SESSION['permissoes_ok'] = "Tipo de admin de '" . $linha_admin['IDF_NOME'] . "': " . ($grupo_id > 0 ? $grupos[$grupo_id]['idag_nome'] : 'acesso total (admin)') . ".";
            header('Location: admin_gerenciar_permissoes.php');
            exit;
        } else {
            $erro = site_erro_banco("Não foi possível mudar o tipo de admin.");
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario_id = isset($_POST["usuario_id"]) ? (int) $_POST["usuario_id"] : 0;
    $acao = isset($_POST["acao"]) ? site_entrada_texto($_POST["acao"]) : "";

    if ($usuario_id <= 0 || !in_array($acao, array('promover', 'remover'))) {
        $erro = "Requisição inválida.";
    } else {
        // Buscar dados do usuário
        $sql_user = "SELECT IDF_ID, IDF_NOME, IDF_EMAIL, IDF_STATUS, COALESCE(IDF_ATIVO, 1) AS ATIVO FROM ID_FIEL WHERE IDF_ID = $usuario_id LIMIT 1";
        $res_user = mysqli_query($conn, $sql_user);
        
        if ($res_user && mysqli_num_rows($res_user) > 0) {
            $user = mysqli_fetch_assoc($res_user);
            
            if ($acao === 'promover') {
                // Verificar se já é admin. Uma linha com IDA_ATIVO = 0 (admin
                // desativado) é reativada: antes a tela dizia "já é
                // administrador" e não havia como devolver o acesso.
                $sql_check = "SELECT IDA_ID, IDA_ATIVO FROM ID_ADMIN WHERE IDA_FIEL_ID = $usuario_id LIMIT 1";
                $res_check = mysqli_query($conn, $sql_check);
                $linha_admin = $res_check ? mysqli_fetch_assoc($res_check) : null;

                // Tipo em que o novo admin entra: quem tem acesso total escolhe;
                // quem está num tipo só promove para o próprio tipo.
                $grupo_novo = null;
                if ($grupos_disponivel) {
                    $grupo_novo = $gerencia_grupos ? (int) ($_POST['grupo_id'] ?? 0) : (int) permissoes_grupo_do_admin($conn, $admin_id_logado);
                    $grupo_novo = isset($grupos[$grupo_novo]) ? $grupo_novo : null;
                }

                if ($user['IDF_STATUS'] !== 'aprovado' || (int) $user['ATIVO'] !== 1) {
                    // Conta pendente, negada ou desativada não entra no painel
                    // (o login recusa), e ficaria admin "escondida" para quando
                    // fosse reativada.
                    $erro = "Só contas aprovadas e ativas podem virar administrador.";
                } elseif ($linha_admin && (int) $linha_admin['IDA_ATIVO'] === 1) {
                    $erro = "Este usuário já é administrador.";
                } elseif (!$eh_superadmin && $conta_eh_superadmin($user['IDF_EMAIL'])) {
                    // Promover uma conta com e-mail da equipe a faria super admin.
                    $erro = $aviso_so_superadmin;
                } elseif (!$eh_superadmin && $grupo_novo !== null && $grupo_tem_dinheiro($grupo_novo)) {
                    $erro = $aviso_tipo_dinheiro;
                } else {
                    // Promover a admin
                    $sql_promote = $linha_admin
                        ? "UPDATE ID_ADMIN SET IDA_ATIVO = 1 WHERE IDA_ID = " . (int) $linha_admin['IDA_ID']
                        : "INSERT INTO ID_ADMIN (IDA_FIEL_ID, IDA_NIVEL, IDA_ATIVO) VALUES ($usuario_id, 'ADMIN', 1)";
                    if (mysqli_query($conn, $sql_promote)) {
                        $mensagem = "Usuário '" . $user["IDF_NOME"] . "' promovido a ADMIN com sucesso!";
                        admin_log($conn, 'admin.promover', 'fiel', $usuario_id, array('nome' => (string) $user["IDF_NOME"]));
                        if ($grupos_disponivel) {
                            db_query($conn, "UPDATE ID_ADMIN SET ida_grupo_id = ? WHERE IDA_FIEL_ID = ?", array($grupo_novo, $usuario_id));
                            $mensagem .= " Tipo de admin: " . ($grupo_novo ? $grupos[$grupo_novo]['idag_nome'] : 'acesso total (admin)') . ".";
                        }
                        
                        // Criar notificação para o usuário
                        $notif_titulo = "Você foi promovido a Administrador!";
                        $notif_msg = "Parabéns! Você agora tem acesso ao painel administrativo. Faça login novamente para acessar.";
                        $sql_notif = "INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO) VALUES ($usuario_id, '$notif_titulo', '$notif_msg', 'promocao')";
                        @mysqli_query($conn, $sql_notif);
                    } else {
                        $erro = site_erro_banco("Erro ao promover o usuário.");
                    }
                }
            } else if ($acao === 'remover') {
                if ($usuario_id === $admin_id_logado) {
                    $erro = "Você não pode remover sua própria permissão de administrador.";
                } elseif (!$eh_superadmin && $conta_eh_superadmin($user['IDF_EMAIL'])) {
                    // Sem isto, um admin da igreja tirava o acesso da equipe e
                    // depois podia mudar o e-mail e o status da conta.
                    $erro = $aviso_so_superadmin;
                } elseif ($grupos_disponivel && !$gerencia_grupos && permissoes_grupo_do_admin($conn, $usuario_id) === null) {
                    // Admin num grupo restrito não derruba quem tem acesso total.
                    $erro = "Só administradores com acesso total podem remover este administrador.";
                } else {
                // Verificar se é admin
                $sql_check = "SELECT IDA_ID FROM ID_ADMIN WHERE IDA_FIEL_ID = $usuario_id LIMIT 1";
                $res_check = mysqli_query($conn, $sql_check);
                
                if (!$res_check || mysqli_num_rows($res_check) == 0) {
                    $erro = "Este usuário não é administrador.";
                } else {
                    $admin_row = mysqli_fetch_assoc($res_check);
                    $admin_id = (int) $admin_row["IDA_ID"];
                    
                    // Remover permissão de admin
                    $sql_remove = "DELETE FROM ID_ADMIN WHERE IDA_ID = $admin_id LIMIT 1";
                    if (mysqli_query($conn, $sql_remove)) {
                        // O papel fica guardado na sessão desde o login: sem
                        // derrubar as sessões, o ex-admin seguia com o painel
                        // aberto até ela expirar.
                        auth_revogar_sessoes($conn, $usuario_id);
                        $mensagem = "Permissão de administrador removida de '" . $user["IDF_NOME"] . "' com sucesso!";
                        admin_log($conn, 'admin.remover', 'fiel', $usuario_id, array('nome' => (string) $user["IDF_NOME"]));
                        
                        // Criar notificação
                        $notif_titulo = "Permissões alteradas";
                        $notif_msg = "Sua permissão de administrador foi removida. Você continuará tendo acesso como usuário regular.";
                        $sql_notif = "INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO) VALUES ($usuario_id, '$notif_titulo', '$notif_msg', 'permissao_alterada')";
                        @mysqli_query($conn, $sql_notif);
                    } else {
                        $erro = site_erro_banco("Erro ao remover a permissão.");
                    }
                }
                }
            }
        } else {
            $erro = "Usuário não encontrado.";
        }
    }
}

if (!empty($_SESSION['permissoes_ok'])) {
    $mensagem = $_SESSION['permissoes_ok'];
    unset($_SESSION['permissoes_ok']);
}
$grupos = admin_grupos_lista($conn); // de novo: contagem de membros atualizada
$editar_grupo = $grupos[(int) ($_GET['editar_grupo'] ?? 0)] ?? null;
$superadmins = superadmin_emails();

// Buscar usuários e seu status de admin
$usuarios = array();
$coluna_grupo = $grupos_disponivel ? 'a.ida_grupo_id' : 'NULL';
$sql_users = "
    SELECT
        f.IDF_ID, f.IDF_NOME, f.IDF_EMAIL, f.IDF_STATUS, f.IDF_FUNCAO,
        CASE WHEN a.IDA_ID IS NOT NULL THEN 1 ELSE 0 END as eh_admin,
        a.IDA_NIVEL, $coluna_grupo AS grupo_id
    FROM ID_FIEL f
    LEFT JOIN ID_ADMIN a ON f.IDF_ID = a.IDA_FIEL_ID
    WHERE f.IDF_STATUS = 'aprovado'
    ORDER BY f.IDF_NOME
";
$res_users = mysqli_query($conn, $sql_users);
if ($res_users && mysqli_num_rows($res_users) > 0) {
    while ($user = mysqli_fetch_assoc($res_users)) {
        // Contas da equipe interna só aparecem para quem também é dela.
        if (!$eh_superadmin && $conta_eh_superadmin($user['IDF_EMAIL'])) {
            continue;
        }
        $usuarios[] = $user;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tipos de Admin - Admin</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'permissoes', 'Tipos de Admin', 'fa-user-shield'); ?>
        <div class="row">
            <div class="col-12">
                <p class="text-muted mb-4">Escolha quais telas do painel cada tipo de admin vê, promova usuários a administrador ou remova o acesso.</p>

                <?php if ($mensagem !== "") { ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensagem); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php } ?>

                <?php if ($erro !== "") { ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($erro); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php } ?>

                <?php if (!$grupos_disponivel) { ?>
                    <div class="alert alert-info small"><i class="fas fa-circle-info me-2"></i>Para limitar as telas de cada administrador (tipos de admin), rode a seção 73 do <code>database/bancosupabase.md</code> no Supabase. Até lá, todo admin tem acesso total.</div>
                <?php } else { ?>
                <!-- TIPOS DE ADMIN (no banco: id_admin_grupo) -->
                <div class="card mb-4" id="grupos" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Tipos de Admin</h5>
                        <small class="text-muted">Quem está em <strong>Acesso total (admin)</strong> vê todas as telas do painel<?php echo $eh_superadmin ? ', menos Financeiro e Contribuições (essas só o super admin libera)' : ''; ?>. Ao colocar o admin num tipo que não tem alguma tela, ele vira admin comum e vê só as telas marcadas no tipo. O Dashboard e as notificações valem para todos.<?php echo $gerencia_grupos ? '' : ' Só quem tem acesso total cria e muda os tipos.'; ?></small>
                    </div>
                    <div class="card-body">
                        <?php if (!$grupos) { ?>
                            <p class="text-muted">Nenhum tipo ainda: todos os administradores têm acesso total.<?php echo $gerencia_grupos ? ' Crie um tipo abaixo e desmarque as telas que ele não deve ver (ex.: "Mídia" só com Cursos, Vídeos e Ao Vivo).' : ''; ?></p>
                        <?php } else { ?>
                            <div class="row g-3 mb-4">
                                <?php foreach ($grupos as $g) { ?>
                                    <div class="col-md-6 col-xl-4">
                                        <div class="permissoes-grupo h-100">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($g['idag_nome']); ?></strong>
                                                    <small class="d-block text-muted"><?php echo (int) $g['membros']; ?> admin(s)<?php echo !empty($g['idag_descricao']) ? ' · ' . htmlspecialchars($g['idag_descricao']) : ''; ?></small>
                                                </div>
                                                <?php if ($gerencia_grupos) { ?>
                                                    <div class="d-flex gap-1">
                                                        <a href="admin_gerenciar_permissoes.php?editar_grupo=<?php echo (int) $g['idag_id']; ?>#formGrupo" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fas fa-pen"></i></a>
                                                        <?php if ((int) $g['membros'] === 0) { ?>
                                                            <form method="POST" data-confirmar="Excluir o tipo &quot;<?php echo htmlspecialchars($g['idag_nome']); ?>&quot;?">
                                                                <?php echo csrf_campo(); ?>
                                                                <input type="hidden" name="acao" value="excluir_grupo">
                                                                <input type="hidden" name="idag_id" value="<?php echo (int) $g['idag_id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="fas fa-trash"></i></button>
                                                            </form>
                                                        <?php } ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                            <div class="d-flex flex-wrap gap-1 mt-2">
                                                <?php if (!$g['telas']) { ?><span class="text-muted small">Só o Dashboard</span><?php } ?>
                                                <?php foreach ($g['telas'] as $chave) {
                                                    if (isset($telas[$chave])) { ?><span class="badge bg-secondary"><?php echo htmlspecialchars($telas[$chave][0]); ?></span><?php }
                                                    elseif (isset($telas_restritas[$chave]) && $eh_superadmin) {
                                                        // Telas restritas: só o super admin vê que o tipo as tem.
                                                        $qtd_partes = $chave === 'financeiro' ? count(array_filter($g['telas'], function ($t) { return strpos($t, 'financeiro.') === 0; })) : 0;
                                                    ?><span class="badge bg-warning text-dark"><i class="fas fa-sack-dollar me-1"></i><?php echo htmlspecialchars($telas_restritas[$chave][0]); ?><?php echo $chave === 'financeiro' ? ' (' . $qtd_partes . ' de ' . count($financeiro_partes) . ' partes)' : ''; ?></span><?php }
                                                } ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <?php if ($gerencia_grupos) { ?>
                            <form method="POST" id="formGrupo" class="permissoes-form-grupo">
                                <?php echo csrf_campo(); ?>
                                <input type="hidden" name="acao" value="salvar_grupo">
                                <input type="hidden" name="idag_id" value="<?php echo (int) ($editar_grupo['idag_id'] ?? 0); ?>">
                                <h6 class="mb-3"><?php echo $editar_grupo ? 'Editar tipo: ' . htmlspecialchars($editar_grupo['idag_nome']) : '<i class="fas fa-plus me-1"></i>Novo tipo de admin'; ?></h6>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-4"><input type="text" name="nome" class="form-control custom-input" maxlength="60" required placeholder="Nome (ex.: Mídia)" aria-label="Nome do tipo" value="<?php echo htmlspecialchars((string) ($editar_grupo['idag_nome'] ?? '')); ?>"></div>
                                    <div class="col-md-8"><input type="text" name="descricao" class="form-control custom-input" maxlength="200" placeholder="Descrição (opcional)" aria-label="Descrição do tipo" value="<?php echo htmlspecialchars((string) ($editar_grupo['idag_descricao'] ?? '')); ?>"></div>
                                </div>
                                <p class="small text-muted mb-2">Telas que este tipo vê (desmarque as que ele não deve ver):</p>
                                <div class="permissoes-telas mb-3">
                                    <?php foreach ($telas as $chave => $info) { ?>
                                        <div class="form-check">
                                            <?php // Tipo novo começa com tudo marcado: quem cria só tira o que não quer. ?>
                                            <input class="form-check-input" type="checkbox" name="telas[]" value="<?php echo $chave; ?>" id="tela_<?php echo $chave; ?>" <?php echo !$editar_grupo || in_array($chave, $editar_grupo['telas'], true) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="tela_<?php echo $chave; ?>"><?php echo htmlspecialchars($info[0]); ?></label>
                                        </div>
                                    <?php } ?>
                                </div>
                                <?php if ($eh_superadmin) {
                                    // Restritas começam desmarcadas: dinheiro só aparece se o superadmin liberar.
                                    $marcada = function ($chave) use ($editar_grupo) {
                                        return $editar_grupo && in_array($chave, $editar_grupo['telas'], true) ? 'checked' : '';
                                    };
                                ?>
                                    <fieldset class="permissoes-grupo mb-3">
                                        <legend class="small fw-semibold mb-1"><i class="fas fa-sack-dollar me-1 text-warning"></i>Telas com dinheiro <span class="text-muted fw-normal">(só superadmin libera; nem o acesso total vê sem estar marcado aqui)</span></legend>
                                        <?php foreach ($telas_restritas as $chave => $info) { ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="telas_restritas[]" value="<?php echo $chave; ?>" id="tela_<?php echo $chave; ?>" <?php echo $marcada($chave); ?>>
                                                <label class="form-check-label" for="tela_<?php echo $chave; ?>"><?php echo htmlspecialchars($info[0]); ?></label>
                                            </div>
                                            <?php if ($chave === 'financeiro') { ?>
                                                <div class="ms-4 mb-1">
                                                    <small class="text-muted d-block">Partes do Financeiro que este tipo vê:</small>
                                                    <?php foreach ($financeiro_partes as $parte => $rotulo) { ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="telas_restritas[]" value="financeiro.<?php echo $parte; ?>" id="fin_<?php echo $parte; ?>" <?php echo $marcada('financeiro.' . $parte); ?>>
                                                            <label class="form-check-label small" for="fin_<?php echo $parte; ?>"><?php echo htmlspecialchars($rotulo); ?></label>
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                        <?php } ?>
                                    </fieldset>
                                <?php } ?>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-salvar"><i class="fas fa-save me-1"></i><?php echo $editar_grupo ? 'Salvar tipo' : 'Criar tipo'; ?></button>
                                    <?php if ($editar_grupo) { ?><a href="admin_gerenciar_permissoes.php#grupos" class="btn btn-outline-secondary">Cancelar</a><?php } ?>
                                </div>
                                <small class="text-muted d-block mt-2">Mudanças valem na próxima página que o admin abrir. "Tipos de Admin" marcado num tipo deixa promover e remover admins, mas só para o próprio tipo.</small>
                            </form>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>

                <!-- LISTA DE USUÁRIOS -->
                <div class="card" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Usuários (<?php echo count($usuarios); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($usuarios) > 0) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm mb-0">
                                    <thead style="border-bottom: 2px solid var(--border-color);">
                                        <tr>
                                            <th>Nome</th>
                                            <th>E-mail</th>
                                            <th>Função</th>
                                            <th>Status</th>
                                            <th>Tipo</th>
                                            <th>Ação</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usuarios as $user) { ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($user["IDF_NOME"]); ?></strong></td>
                                                <td><small><?php echo htmlspecialchars($user["IDF_EMAIL"]); ?></small></td>
                                                <td><?php echo htmlspecialchars($user["IDF_FUNCAO"]); ?></td>
                                                <td>
                                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Aprovado</span>
                                                </td>
                                                <td>
                                                    <?php if ($user["eh_admin"] == 1) {
                                                        $eh_superadmin_linha = in_array(mb_strtolower((string) $user["IDF_EMAIL"]), $superadmins, true);
                                                        $grupo_linha = $user['grupo_id'] !== null ? (int) $user['grupo_id'] : 0;
                                                    ?>
                                                        <?php // "Super admin" só para quem está em SUPERADMIN_EMAILS (o IDA_NIVEL do banco é só um rótulo antigo). ?>
                                                        <?php if ($eh_superadmin_linha) { ?>
                                                            <span class="badge" style="background:#7c3aed;" title="E-mail em SUPERADMIN_EMAILS (equipe interna)">
                                                                <i class="fas fa-user-shield me-1"></i>Super admin
                                                            </span>
                                                            <small class="d-block text-muted mt-1">Equipe interna · tudo, inclusive Financeiro</small>
                                                        <?php } else { ?>
                                                            <span class="badge bg-warning" style="color: #000;">
                                                                <i class="fas fa-shield-alt me-1"></i>Admin
                                                            </span>
                                                        <?php } ?>
                                                        <?php if ($eh_superadmin_linha) {
                                                            // Sem seletor: a equipe interna sempre tem tudo.
                                                        } elseif ($grupos_disponivel && $gerencia_grupos && (int) $user["IDF_ID"] !== $admin_id_logado && ($eh_superadmin || !$grupo_tem_dinheiro($grupo_linha))) { ?>
                                                            <form method="POST" class="mt-1">
                                                                <?php echo csrf_campo(); ?>
                                                                <input type="hidden" name="acao" value="definir_grupo">
                                                                <input type="hidden" name="usuario_id" value="<?php echo (int) $user["IDF_ID"]; ?>">
                                                                <select name="grupo_id" class="form-select form-select-sm custom-input" data-enviar-ao-mudar aria-label="Tipo de admin de <?php echo htmlspecialchars($user["IDF_NOME"]); ?>">
                                                                    <option value="0">Acesso total (admin)</option>
                                                                    <?php foreach ($grupos as $g) {
                                                                        // Tipo com dinheiro: só o super admin vê e coloca alguém nele.
                                                                        if (!$eh_superadmin && $grupo_tem_dinheiro($g['idag_id'])) {
                                                                            continue;
                                                                        }
                                                                    ?><option value="<?php echo (int) $g['idag_id']; ?>" <?php echo $grupo_linha === (int) $g['idag_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['idag_nome']); ?></option><?php } ?>
                                                                </select>
                                                            </form>
                                                        <?php } elseif ($grupos_disponivel) { ?>
                                                            <small class="d-block text-muted mt-1"><?php echo $grupo_linha && isset($grupos[$grupo_linha]) ? htmlspecialchars($grupos[$grupo_linha]['idag_nome']) : 'Acesso total (admin)'; ?></small>
                                                        <?php } ?>
                                                    <?php } else { ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-user me-1"></i>Usuário
                                                        </span>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <?php if ($user["eh_admin"] == 1 && (int) $user["IDF_ID"] === $admin_id_logado) { ?>
                                                        <span class="badge bg-info text-dark">
                                                            <i class="fas fa-shield-alt me-1"></i>Seu Acesso
                                                        </span>
                                                    <?php } elseif ($user["eh_admin"] == 1) { ?>
                                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#removeModal<?php echo $user["IDF_ID"]; ?>">
                                                            <i class="fas fa-times me-1"></i>Remover Admin
                                                        </button>
                                                    <?php } else { ?>
                                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#promoteModal<?php echo $user["IDF_ID"]; ?>">
                                                            <i class="fas fa-arrow-up me-1"></i>Promover
                                                        </button>
                                                    <?php } ?>
                                                </td>
                                            </tr>

                                            <!-- Modal Promover -->
                                            <div class="modal fade" id="promoteModal<?php echo $user["IDF_ID"]; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                                                        <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                                                            <h5 class="modal-title">Promover a Administrador</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                        <?php echo csrf_campo(); ?>
                                                            <div class="modal-body">
                                                                <p>Deseja promover <strong><?php echo htmlspecialchars($user["IDF_NOME"]); ?></strong> a administrador?</p>
                                                                    <p class="text-muted small">O usuário terá acesso ao painel administrativo.</p>
                                                                <?php if ($grupos_disponivel && $gerencia_grupos) { ?>
                                                                    <label class="form-label small mb-1" for="grupo_promover_<?php echo (int) $user["IDF_ID"]; ?>">Tipo de admin</label>
                                                                    <select name="grupo_id" id="grupo_promover_<?php echo (int) $user["IDF_ID"]; ?>" class="form-select custom-input">
                                                                        <?php foreach ($grupos as $g) {
                                                                            if (!$eh_superadmin && $grupo_tem_dinheiro($g['idag_id'])) {
                                                                                continue; // tipo com dinheiro: só o super admin vê
                                                                            }
                                                                        ?><option value="<?php echo (int) $g['idag_id']; ?>"><?php echo htmlspecialchars($g['idag_nome']); ?></option><?php } ?>
                                                                        <option value="0" <?php echo $grupos ? '' : 'selected'; ?>>Acesso total (admin)</option>
                                                                    </select>
                                                                <?php } elseif ($grupos_disponivel) { ?>
                                                                    <p class="text-muted small mb-0">A pessoa entra no mesmo tipo de admin que o seu.</p>
                                                                <?php } ?>
                                                            </div>
                                                            <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                <button type="submit" class="btn btn-success" name="acao" value="promover">
                                                                    <input type="hidden" name="usuario_id" value="<?php echo $user["IDF_ID"]; ?>">
                                                                    Confirmar Promoção
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php if ($user["eh_admin"] == 1 && (int) $user["IDF_ID"] !== $admin_id_logado) { ?>
                                            <!-- Modal Remover -->
                                            <div class="modal fade" id="removeModal<?php echo $user["IDF_ID"]; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                                                        <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                                                            <h5 class="modal-title">Remover Permissão de Admin</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                        <?php echo csrf_campo(); ?>
                                                            <div class="modal-body">
                                                                <p>Deseja remover as permissões de administrador de <strong><?php echo htmlspecialchars($user["IDF_NOME"]); ?></strong>?</p>
                                                                <p class="text-muted small">O usuário continuará tendo acesso à plataforma como usuário regular, mas perderá acesso ao painel administrativo.</p>
                                                            </div>
                                                            <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                <button type="submit" class="btn btn-danger" name="acao" value="remover">
                                                                    <input type="hidden" name="usuario_id" value="<?php echo $user["IDF_ID"]; ?>">
                                                                    Remover Permissão
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php } ?>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.5;"></i>
                                <p class="mt-3 text-muted">Nenhum usuário aprovado disponível</p>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <!-- INFORMAÇÕES -->
                <div class="alert alert-info mt-4" style="background: rgba(96,165,250,0.1); border: 1px solid rgba(96,165,250,0.2);">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Níveis de acesso:</strong>
                    <ul class="mb-0 mt-2">
                        <li><strong>Fiel:</strong> Acesso à plataforma de conteúdo (vídeos, cursos). O que pode mudar no perfil fica em Tipos de fiel.</li>
                        <li><strong>Acesso total (admin):</strong> todas as telas do painel<?php echo $eh_superadmin ? ' (menos Financeiro e Contribuições)' : ''; ?> e cria e muda os tipos de admin</li>
                        <li><strong>Admin:</strong> só as telas marcadas no tipo de admin dele</li>
                        <?php if ($eh_superadmin) { ?>
                            <li><strong>Super admin (equipe interna):</strong> admins da equipe (e-mail em <code>SUPERADMIN_EMAILS</code> no Render): sempre com acesso total, inclusive ao Financeiro. Só quem é super admin vê esta linha e essas contas.</li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php admin_layout_fechar(); ?>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
