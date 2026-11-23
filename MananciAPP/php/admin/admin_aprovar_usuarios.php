<?php
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));

$mensagem = "";
$erro = "";
$admin_logado_id = isset($_SESSION["Usuario_id"]) ? (int) $_SESSION["Usuario_id"] : 0;

// Processar aprovação/rejeição: um cadastro (botões de cada cartão) ou
// vários de uma vez ("Aprovar selecionados", no dia de abertura chegam
// dezenas). Cada um passa pela mesma função: status, sessões, notificação,
// e-mail e registro de atividade.
if ($_SERVER["REQUEST_METHOD"] == "POST" && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && ($_POST["acao"] ?? '') === 'aprovar_lote') {
    $ids = array_slice(array_unique(array_filter(array_map('intval', (array) ($_POST["usuario_ids"] ?? array())))), 0, 100);
    // Só quem está pendente de verdade: um POST montado à mão com ids de
    // contas negadas ou excluídas não pode aprová-las por aqui.
    if ($ids) {
        $res_pend = db_query($conn, "SELECT IDF_ID FROM ID_FIEL WHERE IDF_STATUS = 'pendente' AND IDF_REMOVIDO_EM IS NULL AND IDF_ID IN (" . implode(', ', array_fill(0, count($ids), '?')) . ")", $ids);
        $ids = array();
        while ($res_pend && ($linha_pend = mysqli_fetch_assoc($res_pend))) {
            $ids[] = (int) $linha_pend['IDF_ID'];
        }
    }
    // Um e-mail por cadastro (Brevo, ~0,3 s cada): 60 s do .htaccess não bastam.
    set_time_limit(300);
    $aprovados = 0;
    $sem_email = 0;
    foreach ($ids as $id_lote) {
        list($ok_lote, $erro_lote) = aprovar_processar($conn, $id_lote, 'aprovar', '', $admin_logado_id);
        if ($erro_lote === '') {
            $aprovados++;
            $sem_email += strpos($ok_lote, 'Aviso') !== false ? 1 : 0;
        }
    }
    if ($aprovados > 0) {
        $mensagem = $aprovados . ($aprovados === 1 ? ' cadastro aprovado.' : ' cadastros aprovados.')
            . ($sem_email > 0 ? " Aviso: $sem_email e-mail(s) não puderam ser enviados." : '');
    } else {
        $erro = "Selecione pelo menos um cadastro pendente.";
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    list($mensagem, $erro) = aprovar_processar(
        $conn,
        isset($_POST["usuario_id"]) ? (int) $_POST["usuario_id"] : 0,
        isset($_POST["acao"]) ? site_entrada_texto($_POST["acao"]) : "",
        isset($_POST["motivo"]) ? site_entrada_texto($_POST["motivo"]) : "",
        $admin_logado_id
    );
}

// Devolve array(mensagem, erro).
function aprovar_processar($conn, $usuario_id, $acao, $motivo, $admin_logado_id) {
    $mensagem = "";
    $erro = "";
    if ($usuario_id <= 0 || !in_array($acao, array('aprovar', 'rejeitar', 'remover_permissao', 'dar_permissao'))) {
        $erro = "Requisição inválida.";
    } elseif ($usuario_id === $admin_logado_id && in_array($acao, array('rejeitar', 'remover_permissao', 'dar_permissao'), true)) {
        $erro = "Não é permitido alterar as permissões do próprio administrador logado.";
    } elseif (in_array($acao, array('rejeitar', 'remover_permissao'), true) && usuario_e_outro_admin($conn, $usuario_id, $admin_logado_id)) {
        $erro = "Esta conta é de um administrador. Remova o acesso de admin em Tipos de Admin antes de negar o acesso.";
    } else {
        // Buscar dados do usuário
        $sql_user = "
            SELECT 
                f.IDF_ID, f.IDF_NOME, f.IDF_EMAIL, f.IDF_TELEFONE, f.IDF_CPF,
                f.IDF_FUNCAO, f.IDF_ENDERECO, f.IDF_STATUS, f.IDF_ATIVO,
                f.IDF_CRIADO_EM, COALESCE(fl.IDL_NOME, f.IDF_FILIAL, 'N/A') AS IDF_FILIAL_EXIBICAO
            FROM ID_FIEL f
            LEFT JOIN ID_FILIAL fl ON fl.IDL_ID = f.IDF_FILIAL_ID
            WHERE f.IDF_ID = $usuario_id
              -- Conta excluída (\"Conta excluída\", LGPD) não volta a ser aprovada.
              AND f.IDF_REMOVIDO_EM IS NULL
            LIMIT 1
        ";
        $res_user = mysqli_query($conn, $sql_user);
        
        if ($res_user && mysqli_num_rows($res_user) > 0) {
            $user = mysqli_fetch_assoc($res_user);
            
            // Mapear ação para novo status
            $novo_status = '';
            if ($acao === 'aprovar' || $acao === 'dar_permissao') {
                $novo_status = 'aprovado';
            } elseif ($acao === 'rejeitar' || $acao === 'remover_permissao') {
                $novo_status = 'negado';
            }
            
            // Atualizar status do usuário
            $sql_update = "UPDATE ID_FIEL SET IDF_STATUS = '$novo_status' WHERE IDF_ID = $usuario_id LIMIT 1";
            if (mysqli_query($conn, $sql_update)) {
                // Acesso negado/removido vale na hora: derruba as sessões
                // abertas (auth_require() também confere o status).
                if ($novo_status !== 'aprovado') {
                    auth_revogar_sessoes($conn, $usuario_id);
                }
                // Atualizar solicitação de acesso. IDSA_ADMIN_ID aponta para
                // ID_ADMIN.IDA_ID (chave estrangeira): antes ia o id do fiel,
                // a gravação falhava e o histórico mostrava sempre "Sistema".
                // Upsert: o cadastro nem sempre criou a linha.
                $res_ida = db_query($conn, "SELECT IDA_ID FROM ID_ADMIN WHERE IDA_FIEL_ID = ? LIMIT 1", array($admin_logado_id));
                $ida_id = $res_ida ? (int) (mysqli_fetch_assoc($res_ida)['IDA_ID'] ?? 0) : 0;
                $sql_sol = "
                    INSERT INTO ID_SOLICITACAO_ACESSO (IDF_ID, IDSA_STATUS, IDSA_ADMIN_ID, IDSA_MOTIVO_NEGACAO, IDSA_RESPONDIDO_EM)
                    VALUES (?, ?, ?, ?, NOW())
                    ON CONFLICT (IDF_ID) DO UPDATE SET
                        IDSA_STATUS = EXCLUDED.IDSA_STATUS, IDSA_ADMIN_ID = EXCLUDED.IDSA_ADMIN_ID,
                        IDSA_MOTIVO_NEGACAO = EXCLUDED.IDSA_MOTIVO_NEGACAO, IDSA_RESPONDIDO_EM = NOW(), IDSA_ATUALIZADO_EM = NOW()
                ";
                // Sem "@": a falha vai para o log e o Sentry (foi escondido assim
                // que o histórico mostrou "Sistema" por meses).
                if (!db_query($conn, $sql_sol, array($usuario_id, $novo_status, $ida_id ?: null, $motivo !== '' ? $motivo : null))) {
                    site_erro_banco('Aprovação: o histórico da solicitação não foi gravado.');
                }

                // Criar notificação para o usuário
                $notif_link = null;
                if ($novo_status === 'aprovado') {
                    $notif_titulo = "Acesso Aprovado!";
                    $notif_msg = "Sua solicitação de acesso foi aprovada! Acesse a plataforma com suas credenciais.";
                    $notif_link = 'dashboard.php';
                    // Concede o certificado de boas-vindas — a notificação sobre
                    // ele só é criada no próximo login (ver login.php), não aqui.
                    certificado_boas_vindas_conceder($conn, $usuario_id);
                } else {
                    $notif_titulo = "Acesso Negado";
                    $notif_msg = "Sua solicitação de acesso foi negada." . (!empty($motivo) ? " Motivo: $motivo" : "");
                }
                $sql_notif = "INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK) VALUES (?, ?, ?, 'resposta_acesso', ?)";
                if (!db_query($conn, $sql_notif, array($usuario_id, $notif_titulo, $notif_msg, $notif_link))) {
                    site_erro_banco('Aprovação: a notificação para a pessoa não foi gravada.');
                }

                // URL fixa do site (SITE_URL_PUBLICA): link de e-mail não pode
                // vir do cabeçalho Host da requisição.
                $login_link = site_url_publica('login.php');
                $assunto_email = $novo_status === 'aprovado' ? 'Seu acesso foi aprovado' : 'Seu cadastro foi recusado';
                $nome_email = htmlspecialchars($user['IDF_NOME']);
                if ($novo_status === 'aprovado') {
                    $texto_email = "Olá, {$user['IDF_NOME']}!\n\nSeu cadastro foi aprovado. Acesse a plataforma no link abaixo:\n{$login_link}\n\nAtenciosamente,\nEquipe Missão Manancial";
                    $html_email = '<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">'
                        . '<h2 style="margin: 0 0 16px; color: #1d4ed8;">Cadastro aprovado</h2>'
                        . '<p>Olá, <strong>' . $nome_email . '</strong>.</p>'
                        . '<p>Seu cadastro foi aprovado. Você já pode acessar a plataforma pelo link abaixo:</p>'
                        . '<p><a href="' . htmlspecialchars($login_link) . '" style="color: #1d4ed8; font-weight: bold;">' . htmlspecialchars($login_link) . '</a></p>'
                        . '<p>Atenciosamente,<br>Equipe Missão Manancial</p>'
                        . '</div>';
                } else {
                    $texto_email = "Olá, {$user['IDF_NOME']}!\n\nSeu cadastro foi recusado." . (!empty($motivo) ? "\nMotivo: $motivo" : "") . "\n\nAtenciosamente,\nEquipe Missão Manancial";
                    $html_email = '<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">'
                        . '<h2 style="margin: 0 0 16px; color: #b91c1c;">Cadastro recusado</h2>'
                        . '<p>Olá, <strong>' . $nome_email . '</strong>.</p>'
                        . '<p>Seu cadastro foi recusado.' . (!empty($motivo) ? ' Motivo: ' . htmlspecialchars($motivo) : '') . '</p>'
                        . '<p>Se necessário, entre em contato com a administração.</p>'
                        . '<p>Atenciosamente,<br>Equipe Missão Manancial</p>'
                        . '</div>';
                }
                $email_enviado = false;
                // Só tenta enviar se a config de e-mail (EMAIL_API_KEY/EMAIL_SENDER_EMAIL,
                // definidas em conexao.php) estiver realmente preenchida.
                if (defined('EMAIL_API_KEY') && trim((string) EMAIL_API_KEY) !== '' && defined('EMAIL_SENDER_EMAIL') && trim((string) EMAIL_SENDER_EMAIL) !== '') {
                    try {
                        $email_enviado = enviar_email_sistema($user['IDF_EMAIL'], $assunto_email, $html_email, $texto_email);
                    } catch (\Throwable $ex) {
                        // swallow errors from the mailer and mark as not sent
                        $email_enviado = false;
                    }
                } else {
                    // Mail configuration missing — do not attempt to call the mailer (avoids fatal errors)
                    $email_enviado = false;
                }
                
                admin_log($conn, $novo_status === 'aprovado' ? 'usuario.aprovar' : 'usuario.negar', 'fiel', $usuario_id, array_filter(array(
                    'nome' => (string) $user['IDF_NOME'],
                    'acao' => $acao,
                    'motivo' => $motivo !== '' ? $motivo : null,
                )));

                // Mensagem de sucesso
                if ($acao === 'aprovar' || $acao === 'dar_permissao') {
                    $mensagem = "Usuário aprovado com sucesso!" . ($email_enviado ? " E-mail enviado." : " Aviso: o e-mail não pôde ser enviado automaticamente.");
                } else {
                    $mensagem = "Usuário rejeitado com sucesso!" . ($email_enviado ? " E-mail enviado." : " Aviso: o e-mail não pôde ser enviado automaticamente.");
                }
            } else {
                $erro = "Erro ao processar solicitação.";
            }
        } else {
            $erro = "Usuário não encontrado.";
        }
    }
    return array($mensagem, $erro);
}

// Buscar usuários pendentes (60 por página, com busca por nome ou e-mail):
// sem limite, um dia de abertura com centenas de cadastros travava a tela.
$por_pagina = 60;
$busca = site_entrada_texto($_GET["busca"] ?? "");
$pagina = max(1, (int) ($_GET["pagina"] ?? 1));
$filtro_busca = '';
$params_busca = array();
if ($busca !== '') {
    $filtro_busca = "AND (LOWER(f.IDF_NOME) LIKE ? OR LOWER(f.IDF_EMAIL) LIKE ?)";
    $termo = '%' . site_like_escapar(mb_strtolower($busca)) . '%';
    $params_busca = array($termo, $termo);
}
$res_total_pend = db_query($conn, "SELECT COUNT(*) AS total FROM ID_FIEL f WHERE f.IDF_STATUS = 'pendente' $filtro_busca", $params_busca);
$total_pendentes = $res_total_pend ? (int) (mysqli_fetch_assoc($res_total_pend)['total'] ?? 0) : 0;
$paginas = max(1, (int) ceil($total_pendentes / $por_pagina));
$pagina = min($pagina, $paginas);
$limite_sql = "LIMIT $por_pagina OFFSET " . (($pagina - 1) * $por_pagina);
$usa_banco_robusto = tabela_tem_coluna($conn, 'ID_FIEL', 'IDF_FILIAL_ID') && tabela_tem_coluna($conn, 'ID_FIEL', 'IDF_CRIADO_EM');

// Idade e responsável na aprovação: o admin aprovava um menor sem saber.
$col_idade = (tabela_tem_coluna($conn, 'id_fiel', 'idf_nascimento') ? 'f.IDF_NASCIMENTO' : 'NULL AS IDF_NASCIMENTO')
    . ', ' . (tabela_tem_coluna($conn, 'id_fiel', 'idf_responsavel_nome') ? 'f.IDF_RESPONSAVEL_NOME' : 'NULL AS IDF_RESPONSAVEL_NOME');
if ($usa_banco_robusto) {
    $sql_pendentes = "
        SELECT 
            f.IDF_ID, f.IDF_NOME, f.IDF_EMAIL, f.IDF_TELEFONE, f.IDF_CPF, 
            f.IDF_FILIAL_ID, f.IDF_FUNCAO, f.IDF_ENDERECO, 
            $col_idade,
            f.IDF_CRIADO_EM, f.IDF_STATUS,
            -- Filial digitada no cadastro quando não bate com uma cadastrada.
            COALESCE(fil.IDL_NOME, NULLIF(f.IDF_FILIAL, '')) AS IDL_NOME
        FROM ID_FIEL f
        LEFT JOIN ID_FILIAL fil ON f.IDF_FILIAL_ID = fil.IDL_ID
        WHERE f.IDF_STATUS = 'pendente' $filtro_busca
        ORDER BY f.IDF_CRIADO_EM DESC
        $limite_sql
    ";
} else {
    $sql_pendentes = "
        SELECT
            f.IDF_ID, f.IDF_NOME, f.IDF_EMAIL, f.IDF_TELEFONE, f.IDF_CPF,
            f.IDF_FILIAL, f.IDF_FUNCAO, f.IDF_ENDERECO,
            $col_idade,
            NULL AS IDF_CRIADO_EM, f.IDF_STATUS,
            f.IDF_FILIAL AS IDL_NOME
        FROM ID_FIEL f
        WHERE f.IDF_STATUS = 'pendente' $filtro_busca
        ORDER BY f.IDF_ID DESC
        $limite_sql
    ";
}
$res_pendentes = db_query($conn, $sql_pendentes, $params_busca);
$usuarios_pendentes = array();
if ($res_pendentes && mysqli_num_rows($res_pendentes) > 0) {
    while ($user = mysqli_fetch_assoc($res_pendentes)) {
        $usuarios_pendentes[] = $user;
    }
}

// Buscar histórico de aprovações/rejeições
$sql_historico = "
    SELECT 
        f.IDF_ID, f.IDF_NOME, f.IDF_EMAIL, f.IDF_STATUS,
        sa.IDSA_RESPONDIDO_EM, sa.IDSA_MOTIVO_NEGACAO,
        a.IDF_NOME as ADMIN_NOME
    FROM ID_FIEL f
    LEFT JOIN ID_SOLICITACAO_ACESSO sa ON f.IDF_ID = sa.IDF_ID
    LEFT JOIN ID_ADMIN ad ON sa.IDSA_ADMIN_ID = ad.IDA_ID
    LEFT JOIN ID_FIEL a ON ad.IDA_FIEL_ID = a.IDF_ID
    WHERE f.IDF_STATUS IN ('aprovado', 'negado') AND f.IDF_REMOVIDO_EM IS NULL
    ORDER BY sa.IDSA_RESPONDIDO_EM DESC NULLS LAST
    LIMIT 50
";
$res_historico = mysqli_query($conn, $sql_historico);
$usuarios_historico = array();
if ($res_historico && mysqli_num_rows($res_historico) > 0) {
    while ($user = mysqli_fetch_assoc($res_historico)) {
        $usuarios_historico[] = $user;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprovar Usuários - Admin</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'aprovar', 'Aprovação de Usuários', 'fa-user-check'); ?>
        <div class="row">
            <div class="col-12">

                <?php if ($mensagem !== "") { ?>
                    <div class="alert alert-success alert-dismissible fade show approval-status-alert" role="alert">
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

                <style>
                    :root[data-theme="dark"] .approval-status-alert {
                        background: #14532d !important;
                        color: #000 !important;
                        border: 1px solid #166534 !important;
                    }
                </style>

                <!-- USUARIOS PENDENTES -->
                <div class="card mb-5" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-hourglass-half me-2 text-warning"></i>Solicitações Pendentes (<?php echo (int) $total_pendentes; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="d-flex gap-2 mb-3 flex-wrap">
                            <input type="search" name="busca" class="form-control" style="max-width: 320px;" placeholder="Buscar por nome ou e-mail" value="<?php echo htmlspecialchars($busca); ?>" aria-label="Buscar cadastro pendente">
                            <button type="submit" class="btn btn-outline-primary" aria-label="Buscar"><i class="fas fa-search" aria-hidden="true"></i></button>
                            <?php if ($busca !== '') { ?><a href="admin_aprovar_usuarios.php" class="btn btn-link">Limpar</a><?php } ?>
                        </form>
                        <?php if (count($usuarios_pendentes) > 0) { ?>
                            <!-- Aprovar em lote: as caixinhas de cada cartão apontam para
                                 este formulário (atributo form=), porque os cartões já têm
                                 os formulários dos modais dentro. -->
                            <form method="POST" id="formLote" class="d-flex align-items-center gap-3 flex-wrap mb-3 aprovar-lote" data-confirmar="Aprovar todos os cadastros selecionados?">
                                <?php echo csrf_campo(); ?>
                                <input type="hidden" name="acao" value="aprovar_lote">
                                <label class="d-flex align-items-center gap-2 mb-0">
                                    <input type="checkbox" class="form-check-input" id="loteTodos"> Selecionar todos desta página
                                </label>
                                <button type="submit" class="btn btn-success btn-sm" id="loteBotao" disabled>
                                    <i class="fas fa-check-double me-1"></i>Aprovar selecionados (<span id="loteContagem">0</span>)
                                </button>
                            </form>
                            <div class="row g-3">
                                <?php foreach ($usuarios_pendentes as $user) { ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="border rounded p-3" style="border-color: var(--border-color);">
                                            <div class="mb-3 d-flex gap-2 align-items-start">
                                                <input type="checkbox" class="form-check-input mt-1 lote-item" name="usuario_ids[]" value="<?php echo (int) $user["IDF_ID"]; ?>" form="formLote" aria-label="Selecionar <?php echo htmlspecialchars($user["IDF_NOME"]); ?>">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($user["IDF_NOME"]); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($user["IDF_EMAIL"]); ?></small>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <table class="table table-sm mb-0" style="font-size: 0.85rem;">
                                                    <tr>
                                                        <td><strong>CPF:</strong></td>
                                                        <td><?php echo htmlspecialchars($user["IDF_CPF"]); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Telefone:</strong></td>
                                                        <td><?php echo htmlspecialchars(formatar_telefone_exibicao($user["IDF_TELEFONE"])); ?></td>
                                                    </tr>
                                                    <?php $idade_pend = site_idade($user["IDF_NASCIMENTO"] ?? null); ?>
                                                    <tr>
                                                        <td><strong>Idade:</strong></td>
                                                        <td>
                                                            <?php if ($idade_pend === null) { ?>
                                                                <span class="text-muted">não informada</span>
                                                            <?php } else { ?>
                                                                <?php echo (int) $idade_pend; ?> anos
                                                                <?php if (lgpd_eh_menor($user["IDF_NASCIMENTO"] ?? null)) { ?>
                                                                    <span class="badge bg-warning text-dark ms-1">Menor</span>
                                                                    <div class="small mt-1">Responsável: <?php echo htmlspecialchars((string) ($user["IDF_RESPONSAVEL_NOME"] ?? "") !== "" ? (string) $user["IDF_RESPONSAVEL_NOME"] : "não informado"); ?></div>
                                                                <?php } ?>
                                                            <?php } ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Função:</strong></td>
                                                        <td><?php echo htmlspecialchars($user["IDF_FUNCAO"]); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Filial:</strong></td>
                                                        <td><?php echo htmlspecialchars($user["IDL_NOME"] ?? "N/A"); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Solicitado em:</strong></td>
                                                        <td><?php echo !empty($user["IDF_CRIADO_EM"]) ? date('d/m/Y H:i', strtotime($user["IDF_CRIADO_EM"])) : 'N/A'; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Endereço:</strong></td>
                                                        <td><?php echo htmlspecialchars($user["IDF_ENDERECO"] ?? "N/A"); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Status:</strong></td>
                                                        <td><?php echo htmlspecialchars($user["IDF_STATUS"] ?? "N/A"); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Ativo:</strong></td>
                                                        <td><?php echo !empty($user["IDF_ATIVO"]) ? "Sim" : "Não"; ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="d-flex gap-2 mt-3">
                                                <button type="button" class="btn btn-sm btn-success flex-grow-1" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $user["IDF_ID"]; ?>">
                                                    <i class="fas fa-check me-1"></i>Aprovar
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger flex-grow-1" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $user["IDF_ID"]; ?>">
                                                    <i class="fas fa-times me-1"></i>Rejeitar
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal Aprovar -->
                                    <div class="modal fade" id="approveModal<?php echo $user["IDF_ID"]; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                                                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                                                    <h5 class="modal-title">Aprovar Usuário</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                <?php echo csrf_campo(); ?>
                                                    <div class="modal-body">
                                                        <p>Deseja aprovar o acesso de <strong><?php echo htmlspecialchars($user["IDF_NOME"]); ?></strong>?</p>
                                                        <p class="text-muted small">O usuário receberá uma notificação e poderá acessar a plataforma.</p>
                                                    </div>
                                                    <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-success" name="acao" value="aprovar">
                                                            <input type="hidden" name="usuario_id" value="<?php echo $user["IDF_ID"]; ?>">
                                                            Aprovar
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal Rejeitar -->
                                    <div class="modal fade" id="rejectModal<?php echo $user["IDF_ID"]; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                                                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                                                    <h5 class="modal-title">Rejeitar Acesso</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                <?php echo csrf_campo(); ?>
                                                    <div class="modal-body">
                                                        <p>Rejeitar o acesso de <strong><?php echo htmlspecialchars($user["IDF_NOME"]); ?></strong>?</p>
                                                        <div class="mb-3">
                                                            <label class="form-label">Motivo (opcional):</label>
                                                            <textarea class="form-control custom-input" name="motivo" rows="3" placeholder="Informe o motivo da rejeição..."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-danger" name="acao" value="rejeitar">
                                                            <input type="hidden" name="usuario_id" value="<?php echo $user["IDF_ID"]; ?>">
                                                            Rejeitar
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                            <?php if ($paginas > 1) { ?>
                                <nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Páginas">
                                    <small class="text-muted">Página <?php echo (int) $pagina; ?> de <?php echo (int) $paginas; ?></small>
                                    <div class="btn-group">
                                        <a class="btn btn-outline-secondary btn-sm<?php echo $pagina <= 1 ? ' disabled' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query(array('busca' => $busca, 'pagina' => $pagina - 1))); ?>">Anterior</a>
                                        <a class="btn btn-outline-secondary btn-sm<?php echo $pagina >= $paginas ? ' disabled' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query(array('busca' => $busca, 'pagina' => $pagina + 1))); ?>">Próxima</a>
                                    </div>
                                </nav>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle text-success" style="font-size: 3rem; opacity: 0.5;"></i>
                                <p class="mt-3 text-muted"><?php echo $busca !== '' ? 'Nenhum cadastro pendente com essa busca.' : 'Nenhuma solicitação pendente!'; ?></p>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <!-- HISTÓRICO -->
                <div class="card" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Histórico de Decisões</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($usuarios_historico) > 0) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead>
                                        <tr style="border-bottom: 1px solid var(--border-color);">
                                            <th>Usuário</th>
                                            <th>E-mail</th>
                                            <th>Status</th>
                                            <th>Respondido em</th>
                                            <th>Por</th>
                                            <th>Motivo</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usuarios_historico as $user) { ?>
                                            <?php $eh_proprio_admin = $admin_logado_id > 0 && (int) $user["IDF_ID"] === $admin_logado_id; ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user["IDF_NOME"]); ?></td>
                                                <td><small><?php echo htmlspecialchars($user["IDF_EMAIL"]); ?></small></td>
                                                <td>
                                                    <?php if ($user["IDF_STATUS"] === "aprovado") { ?>
                                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i>Aprovado</span>
                                                    <?php } else { ?>
                                                        <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Rejeitado</span>
                                                    <?php } ?>
                                                </td>
                                                <td><small><?php echo $user["IDSA_RESPONDIDO_EM"] ? date('d/m/Y H:i', strtotime($user["IDSA_RESPONDIDO_EM"])) : "N/A"; ?></small></td>
                                                <td><small><?php echo htmlspecialchars($user["ADMIN_NOME"] ?? "Sistema"); ?></small></td>
                                                <td><small><?php echo htmlspecialchars($user["IDSA_MOTIVO_NEGACAO"] ?? "-"); ?></small></td>
                                                <td>
                                                    <?php if ($eh_proprio_admin) { ?>
                                                        <span class="badge bg-info text-dark">
                                                            <i class="fas fa-shield-alt"></i>
                                                        </span>
                                                    <?php } elseif ($user["IDF_STATUS"] === "aprovado") { ?>
                                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#removerPermissaoModal<?php echo $user["IDF_ID"]; ?>" title="Remover Permissão">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    <?php } else { ?>
                                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#darPermissaoModal<?php echo $user["IDF_ID"]; ?>" title="Dar Permissão">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- MODAIS DE AÇÃO DO HISTÓRICO -->
                            <?php foreach ($usuarios_historico as $user) { ?>
                                <?php if ($admin_logado_id > 0 && (int) $user["IDF_ID"] === $admin_logado_id) { continue; } ?>
                                <!-- Modal Remover Permissão -->
                                <div class="modal fade" id="removerPermissaoModal<?php echo $user["IDF_ID"]; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                                            <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                                                <h5 class="modal-title">Remover Permissão</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                            <?php echo csrf_campo(); ?>
                                                <div class="modal-body">
                                                    <p>Deseja remover a permissão de <strong><?php echo htmlspecialchars($user["IDF_NOME"]); ?></strong>?</p>
                                                    <div class="mb-3">
                                                        <label class="form-label">Motivo (opcional):</label>
                                                        <textarea class="form-control custom-input" name="motivo" rows="3" placeholder="Informe o motivo da revogação..."></textarea>
                                                    </div>
                                                    <div class="alert alert-warning" role="alert">
                                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                                        <small>O usuário será notificado sobre a remoção de permissão.</small>
                                                    </div>
                                                </div>
                                                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-danger" name="acao" value="remover_permissao">
                                                        <input type="hidden" name="usuario_id" value="<?php echo $user["IDF_ID"]; ?>">
                                                        <i class="fas fa-ban me-1"></i>Remover
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal Dar Permissão -->
                                <div class="modal fade" id="darPermissaoModal<?php echo $user["IDF_ID"]; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                                            <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                                                <h5 class="modal-title">Dar Permissão</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                            <?php echo csrf_campo(); ?>
                                                <div class="modal-body">
                                                    <p>Deseja dar permissão para <strong><?php echo htmlspecialchars($user["IDF_NOME"]); ?></strong> acessar a plataforma?</p>
                                                    <div class="alert alert-info" role="alert">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        <small>O usuário receberá uma notificação sobre a aprovação.</small>
                                                    </div>
                                                </div>
                                                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-success" name="acao" value="dar_permissao">
                                                        <input type="hidden" name="usuario_id" value="<?php echo $user["IDF_ID"]; ?>">
                                                        <i class="fas fa-check me-1"></i>Aprovar
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="text-center py-5">
                                <p class="text-muted">Nenhum histórico disponível</p>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    <?php admin_layout_fechar(); ?>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
<script nonce="<?php echo site_csp_nonce(); ?>">
    // Aprovar em lote: conta os marcados e liga o botão.
    (function () {
        var todos = document.getElementById('loteTodos');
        var botao = document.getElementById('loteBotao');
        var contagem = document.getElementById('loteContagem');
        var itens = document.querySelectorAll('.lote-item');
        if (!todos || !botao) return;
        function atualizar() {
            var marcados = Array.prototype.filter.call(itens, function (c) { return c.checked; }).length;
            contagem.textContent = marcados;
            botao.disabled = marcados === 0;
            todos.checked = marcados > 0 && marcados === itens.length;
        }
        todos.addEventListener('change', function () {
            itens.forEach(function (c) { c.checked = todos.checked; });
            atualizar();
        });
        itens.forEach(function (c) { c.addEventListener('change', atualizar); });
        atualizar();
    })();
</script>
</body>
</html>

