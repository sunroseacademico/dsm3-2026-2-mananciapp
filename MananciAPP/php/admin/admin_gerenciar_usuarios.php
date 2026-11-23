<?php
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));

$mensagem = "";
$erro = "";
$modo = site_entrada_texto($_GET["modo"] ?? "listar");
$usuario_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$usuario_edicao = null;
$admin_id = isset($_SESSION["Usuario_id"]) ? (int) $_SESSION["Usuario_id"] : 0;

function formatar_cpf_exibicao($cpf) {
    $cpf_limpo = preg_replace('/\D/', '', (string) $cpf);
    if (strlen($cpf_limpo) !== 11) {
        return $cpf_limpo === '' ? 'N/A' : $cpf;
    }

    return sprintf('%s.%s.%s-%s', substr($cpf_limpo, 0, 3), substr($cpf_limpo, 3, 3), substr($cpf_limpo, 6, 3), substr($cpf_limpo, 9, 2));
}

// Lista de funções na igreja (T12, seção 50); sem ela, a função continua
// sendo texto livre.
$funcoes_lista_ativa = funcoes_igreja_disponivel($conn);
$funcoes_lista = $funcoes_lista_ativa ? funcoes_igreja_listar($conn) : array();

// Processar atualização de usuário
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"])) {
    $acao = site_entrada_texto($_POST["acao"]);
    $alvo_id = (int) ($_POST["usuario_id"] ?? 0);

    if (in_array($acao, array('excluir_usuario', 'editar_usuario', 'enviar_link_senha', 'desconectar_usuario'), true)
        && $alvo_id !== $admin_id && !auth_eh_superadmin() && usuario_e_superadmin($conn, $alvo_id)) {
        // Conta da equipe interna (SUPERADMIN_EMAILS): só outro super admin
        // edita, desconecta ou manda link de senha.
        $erro = "Usuário não encontrado."; // para os outros admins, a conta não existe
    } elseif (in_array($acao, array('excluir_usuario', 'editar_usuario', 'enviar_link_senha', 'desconectar_usuario'), true)
        && ($res_removida = db_query($conn, "SELECT 1 FROM ID_FIEL WHERE IDF_ID = ? AND IDF_REMOVIDO_EM IS NOT NULL", array($alvo_id)))
        && mysqli_num_rows($res_removida) > 0) {
        // Conta já excluída (LGPD): não se edita nem se reativa.
        $erro = "Esta conta foi excluída e não pode ser alterada.";
    } elseif (in_array($acao, array('excluir_usuario', 'editar_usuario', 'enviar_link_senha', 'desconectar_usuario'), true)
        && admin_alvo_protegido($conn, $alvo_id)) {
        // Admin num tipo restrito não mexe em quem tem acesso total.
        $erro = "Só administradores com acesso total podem alterar este administrador.";
    } elseif ($acao === "desligar_2fa_outro") {
        // Admin que perdeu o celular e os códigos de reserva: só um super
        // admin (equipe interna) desliga o 2FA dele, confirmando a PRÓPRIA
        // senha. Antes só por SQL. As sessões dele caem, e ele liga de novo
        // em Minha segurança.
        $alvo_2fa = (int) ($_POST['usuario_id'] ?? 0);
        $erro_senha = auth_eh_superadmin() ? senha_conferir_atual($conn, $admin_id, site_entrada_texto($_POST['senha_confirmacao'] ?? '', false)) : 'Só a equipe interna pode fazer isso.';
        if ($erro_senha !== '') {
            $erro = $erro_senha;
        } elseif ($alvo_2fa <= 0 || $alvo_2fa === $admin_id) {
            $erro = "Para a sua própria conta, use Minha segurança.";
        } elseif (db_query($conn, "UPDATE id_fiel SET idf_2fa_segredo = NULL, idf_2fa_ativo_em = NULL, idf_2fa_ultimo_passo = NULL, idf_2fa_reserva = NULL WHERE idf_id = ?", array($alvo_2fa))) {
            auth_revogar_sessoes($conn, $alvo_2fa);
            admin_log($conn, 'conta.2fa_outro', 'fiel', $alvo_2fa, array('ligada' => false));
            $mensagem = "Verificação em duas etapas desligada para essa conta. Peça para a pessoa ligar de novo em Minha segurança.";
        } else {
            $erro = site_erro_banco("Não foi possível desligar agora.");
        }
    } elseif ($acao === "criar_pessoa") {
        // Cadastro feito pelo admin (balcão, irmão idoso): conta já aprovada
        // e convite por e-mail para criar a senha (admin_criar_pessoa()).
        $form_criar = array(
            'nome' => site_entrada_texto($_POST['nova_nome'] ?? ''),
            'email' => site_entrada_texto($_POST['nova_email'] ?? ''),
            'whatsapp' => site_entrada_texto($_POST['nova_whatsapp'] ?? ''),
            'nascimento' => site_entrada_texto($_POST['nova_nascimento'] ?? ''),
            'responsavel' => site_entrada_texto($_POST['nova_responsavel'] ?? ''),
            'autorizou' => !empty($_POST['nova_autorizou']),
        );
        $criada = admin_criar_pessoa($conn, $form_criar);
        if ($criada['ok']) {
            admin_log($conn, 'usuario.criar', 'fiel', $criada['id'], array('convite' => $criada['aviso'] === ''));
            $mensagem = $criada['aviso'] === ''
                ? 'Pessoa cadastrada. Ela recebe um e-mail para criar a senha (o link vale 24 horas).'
                : $criada['aviso'];
            $form_criar = null;
        } else {
            $erro = $criada['erro'];
        }
    } elseif ($acao === "excluir_usuario") {
        $id = (int) $_POST["usuario_id"];

        if ($id <= 0) {
            $erro = "Usuário inválido.";
        } else {
            // Excluir o fiel apaga a assinatura dele em cascata no banco, mas a
            // assinatura no Asaas continuaria cobrando — e os pagamentos não
            // achariam mais a quem liberar acesso. Primeiro cancela em
            // Assinaturas, depois exclui.
            $res_assinatura = db_query($conn, "SELECT 1 FROM id_assinatura WHERE idf_id = ? AND idas_asaas_subscription_id IS NOT NULL AND idas_status <> 'CANCELADA' LIMIT 1", array($id));
            if ($id === $admin_id) {
                $erro = "Você não pode excluir sua própria conta.";
            } elseif (usuario_e_outro_admin($conn, $id, $admin_id)) {
                $erro = "Esta conta é de um administrador. Remova o acesso de admin em Tipos de Admin antes de excluir.";
            } elseif ($res_assinatura && mysqli_num_rows($res_assinatura) > 0) {
                $erro = "Este usuário tem uma assinatura ativa no Asaas. Cancele a assinatura em Assinaturas antes de excluir a conta.";
            } else {
                // Mesma rotina do "Excluir minha conta" (LGPD): com histórico
                // de pagamento, a conta é anonimizada em vez de apagada — o
                // DELETE direto levava junto os pagamentos de assinaturas
                // canceladas e o Financeiro perdia as entradas.
                $exclusao = lgpd_excluir_conta($conn, $id, 'admin');
                if ($exclusao['ok']) {
                    admin_log($conn, 'usuario.excluir', 'fiel', $id, array('modo' => $exclusao['modo']));
                    header("location:admin_gerenciar_usuarios.php");
                    exit;
                } else {
                    $erro = $exclusao['erro'];
                }
            }
        }
    
    } elseif ($acao === "editar_usuario") {
        $id = (int) $_POST["usuario_id"];
        $nome = site_entrada_texto($_POST["nome"]);
        $email = site_entrada_texto($_POST["email"]);
        $telefone = site_entrada_texto($_POST["telefone"]);
        $cpf = site_entrada_texto($_POST["cpf"] ?? '');
        // Função: da lista (T12) quando ela existe; senão, texto livre
        // opcional como antes.
        $funcao = site_entrada_texto($_POST["funcao"] ?? '');
        $funcao_id = (int) ($_POST["funcao_id"] ?? 0);
        // Só mexe na função se ela mudou: salvar outro campo de um fiel antigo
        // (sem função da lista) não pode apagar o texto que ele informou.
        $funcao_mudou = $funcao_id !== (int) ($_POST["funcao_id_atual"] ?? 0);
        $status = site_entrada_texto($_POST["status"]);
        $telefone_limpo = preg_replace('/\D/', '', $telefone);
        $cpf_limpo = preg_replace('/\D/', '', $cpf);
        
        if (preg_match('/\d/', $nome)) {
            $erro = "O nome não pode conter números.";
        } elseif (mb_strlen($nome) < 3 || mb_strlen($nome) > 50) {
            $erro = "Nome inválido. Use entre 3 e 50 caracteres.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = "Informe um e-mail válido.";
        } elseif (strlen($email) > 100) {
            // Mesmo limite do cadastro (register.php) e da coluna IDF_EMAIL;
            // com 60, o admin não conseguia salvar um fiel de e-mail longo.
            $erro = "E-mail inválido. Máximo de 100 caracteres.";
        } elseif ($telefone_limpo !== '' && strlen($telefone_limpo) !== 11) {
            $erro = "Telefone inválido. Use o formato (11) 9 9999-9999.";
        } elseif ($cpf_limpo !== '' && !assinatura_cpf_valido($cpf_limpo)) {
            // CPF é opcional: o cadastro nunca pediu, e exigir aqui impedia o
            // admin de salvar qualquer fiel comum. Quando vem, confere os
            // dígitos (antes bastava ter 11 números).
            $erro = "CPF inválido. Confira os números (formato 000.000.000-00).";
        } elseif ($cpf_limpo !== '' && ($res_cpf = db_query($conn, "SELECT 1 FROM ID_FIEL WHERE regexp_replace(COALESCE(IDF_CPF, ''), '[^0-9]', '', 'g') = ? AND IDF_ID <> ? LIMIT 1", array($cpf_limpo, $id))) && mysqli_num_rows($res_cpf) > 0) {
            // IDF_CPF é UNIQUE: antes o repetido caía em "Erro ao atualizar".
            $erro = "Este CPF já pertence a outra conta.";
        } elseif (!$funcoes_lista_ativa && $funcao !== '' && (mb_strlen($funcao) < 2 || mb_strlen($funcao) > 50)) {
            $erro = "Função inválida. Use entre 2 e 50 caracteres.";
        } elseif ($funcoes_lista_ativa && $funcao_id !== 0 && !isset($funcoes_lista[$funcao_id])) {
            $erro = "Função inválida.";
        } elseif ($funcoes_lista_ativa && $funcao_mudou && $funcao_id !== 0 && !$funcoes_lista[$funcao_id]['ativo']) {
            $erro = "Essa função está desativada.";
        } elseif (!in_array($status, array('pendente', 'aprovado', 'negado'), true)) {
            $erro = "Status inválido.";
        } elseif ($id === $admin_id && $status !== 'aprovado') {
            // Tirar a aprovação da própria conta derrubaria a sessão e
            // trancaria o admin fora do painel.
            $erro = "Você não pode mudar o status da sua própria conta.";
        } else {
            $email_digitado = $email;
            $nome_digitado = $nome;
            // Vazio vira NULL: IDF_CPF é UNIQUE, e '' em dois fiéis colidiria.
            $telefone_param = $telefone_limpo === '' ? null : $telefone_limpo;
            $cpf_param = $cpf_limpo === '' ? null : $cpf_limpo;
            // Com a lista, a função é gravada por fiel_funcao_definir() logo abaixo.
            $funcao_set = $funcoes_lista_ativa ? "" : ", IDF_FUNCAO = ?";
            $params_update = array($nome, $email, $telefone_param, $cpf_param);
            if (!$funcoes_lista_ativa) {
                $params_update[] = $funcao === '' ? null : $funcao;
            }
            $params_update[] = $status;
            $params_update[] = $id;

            // O UNIQUE de IDF_EMAIL diferencia maiúsculas, mas o login e o
            // "Esqueci minha senha" procuram ignorando — "Fulano@x.com" numa
            // conta e "fulano@x.com" em outra virariam duas contas para o
            // mesmo e-mail (ver seção 66 do bancosupabase.md).
            $res_duplicado = db_query($conn, "SELECT 1 FROM ID_FIEL WHERE LOWER(IDF_EMAIL) = LOWER(CAST(? AS TEXT)) AND IDF_ID <> ? LIMIT 1", array($email, $id));
            $res_email_atual = mysqli_query($conn, "SELECT IDF_EMAIL, IDF_STATUS, IDF_NOME, IDF_TELEFONE, IDF_CPF FROM ID_FIEL WHERE IDF_ID = $id LIMIT 1");
            $linha_email_atual = $res_email_atual ? mysqli_fetch_assoc($res_email_atual) : null;
            $email_mudou = $linha_email_atual && mb_strtolower((string) $linha_email_atual['IDF_EMAIL']) !== mb_strtolower($email_digitado);
            $status_mudou = $linha_email_atual && $linha_email_atual['IDF_STATUS'] !== $status;

            if ($res_duplicado && mysqli_num_rows($res_duplicado) > 0) {
                $erro = "Já existe outra conta com esse e-mail.";
            } elseif ($email_mudou && in_array(mb_strtolower($email_digitado), superadmin_emails(), true) && !auth_eh_superadmin()) {
                // Superadmin é quem tem um e-mail de SUPERADMIN_EMAILS: pôr
                // um deles numa conta seria se promover à equipe interna.
                $erro = "Esse e-mail não pode ser usado.";
            } elseif (($email_mudou || $status_mudou) && usuario_e_outro_admin($conn, $id, $admin_id)) {
                $erro = "Esta conta é de um administrador: o e-mail e o status só mudam depois de remover o acesso de admin em Tipos de Admin.";
            } else {
                $sql = "UPDATE ID_FIEL SET IDF_NOME = ?, IDF_EMAIL = ?, IDF_TELEFONE = ?, IDF_CPF = ?$funcao_set, IDF_STATUS = ? WHERE IDF_ID = ? LIMIT 1";
                $erro_funcao = null;
                $atualizou = db_query($conn, $sql, $params_update);
                // E-mail trocado: um link de "Esqueci minha senha" mandado ao
                // e-mail antigo (que pode ser justamente o invadido) deixa de valer.
                if ($atualizou && $email_mudou) {
                    recuperacao_invalidar_links($conn, $id);
                    conta_email_alterado_avisar($nome_digitado, (string) $linha_email_atual['IDF_EMAIL'], $email_digitado);
                }
                // Conta que deixou de estar aprovada sai na hora (a sessão guardou
                // o acesso no login; auth_require() também confere o status).
                if ($atualizou && $status !== 'aprovado' && $linha_email_atual && $linha_email_atual['IDF_STATUS'] === 'aprovado') {
                    auth_revogar_sessoes($conn, $id);
                }
                if ($atualizou && (!$funcoes_lista_ativa || !$funcao_mudou || ($erro_funcao = fiel_funcao_definir($conn, $id, $funcao_id)) === null)) {
                    $mensagem = "Usuário atualizado com sucesso!";
                    $modo = "listar";
                    // Antes e depois do que mudou (antes só o status e "e-mail
                    // trocado"). CPF e telefone só como "mudou", sem os números.
                    admin_log($conn, 'usuario.editar', 'fiel', $id, array_filter(array(
                        'nome' => $linha_email_atual && (string) $linha_email_atual['IDF_NOME'] !== $nome_digitado ? $linha_email_atual['IDF_NOME'] . ' → ' . $nome_digitado : null,
                        'email' => $email_mudou ? site_mascarar_email((string) $linha_email_atual['IDF_EMAIL']) . ' → ' . site_mascarar_email($email_digitado) : null,
                        'telefone_mudou' => $linha_email_atual && (string) ($linha_email_atual['IDF_TELEFONE'] ?? '') !== (string) $telefone_param ? true : null,
                        'cpf_mudou' => $linha_email_atual && (string) ($linha_email_atual['IDF_CPF'] ?? '') !== (string) $cpf_param ? true : null,
                        'funcao_mudou' => $funcao_mudou ? true : null,
                        'status' => $status_mudou ? ($linha_email_atual['IDF_STATUS'] ?? '') . ' → ' . $status : null,
                    )));
                } else {
                    $erro = $erro_funcao ?: site_erro_banco("Erro ao atualizar o usuário.");
                }
            }
        }
    } elseif ($acao === "enviar_link_senha") {
        // Fiel que pediu ajuda para entrar: manda o link de nova senha para o
        // e-mail dele (ver recuperacao_enviar_pelo_admin(), php/conexao.php).
        $id = (int) ($_POST["usuario_id"] ?? 0);
        $erro_link = recuperacao_enviar_pelo_admin($conn, $id);
        if ($erro_link === '') {
            admin_log($conn, 'usuario.link_senha', 'fiel', $id);
            $mensagem = "Link de nova senha enviado para o e-mail do usuário. Ele vale por " . RECUPERACAO_VALIDADE_MINUTOS . " minutos (" . RECUPERACAO_VALIDADE_ADMIN_MINUTOS . " se a conta for de administrador).";
        } else {
            $erro = $erro_link;
        }
    } elseif ($acao === "desconectar_usuario") {
        // Encerra de verdade toda sessão ativa desse usuário (ver a checagem
        // de IDS_REVOGADA em auth_require(), php/conexao.php) — útil pra
        // desligamento de líder, celular perdido/roubado com o app aberto,
        // etc. Antes, IDS_REVOGADA existia na tabela mas nunca era
        // reconsultado — "desconectar" não tinha efeito nenhum de verdade.
        $id = (int) ($_POST["usuario_id"] ?? 0);
        if ($id <= 0) {
            $erro = "Usuário inválido.";
        } elseif ($id === $admin_id) {
            $erro = "Você não pode desconectar sua própria sessão por aqui — use Sair.";
        } else {
            auth_revogar_sessoes($conn, $id);
            admin_log($conn, 'usuario.desconectar', 'fiel', $id);
            $mensagem = "Sessões ativas desse usuário foram encerradas. No próximo carregamento de página, ele será desconectado.";
        }
    } elseif ($acao === "editar_perfil") {
        // "Meu perfil" do admin logado: sempre a própria conta (o usuario_id
        // do formulário era aceito, e editar outro fiel por aqui trocava o
        // nome do admin na sessão).
        $id = $admin_id;
        $nome_raw = trim(site_entrada_texto($_POST["nome"] ?? '', false));
        $telefone_limpo = preg_replace('/\D/', '', site_entrada_texto($_POST["telefone"] ?? '', false));

        if (preg_match('/\d/', $nome_raw)) {
            $erro = "O nome não pode conter números.";
        } elseif (mb_strlen($nome_raw) < 3 || mb_strlen($nome_raw) > 50) {
            $erro = "Nome inválido. Use entre 3 e 50 caracteres.";
        } elseif ($telefone_limpo !== '' && strlen($telefone_limpo) !== 11) {
            $erro = "Telefone inválido. Use o formato (11) 9 9999-9999.";
        } else {
            // Telefone só com dígitos (ou NULL), como na edição de usuários.
            if (db_query($conn, "UPDATE ID_FIEL SET IDF_NOME = ?, IDF_TELEFONE = ? WHERE IDF_ID = ?", array($nome_raw, $telefone_limpo === '' ? null : $telefone_limpo, $id))) {
                $_SESSION["Usuario_nome"] = $nome_raw;
                $mensagem = "Perfil atualizado com sucesso!";
                $modo = "listar";
            } else {
                $erro = site_erro_banco("Erro ao atualizar o perfil.");
            }
        }
    }
}

// Buscar dados do usuário para edição
if ($modo === "editar" && $usuario_id > 0 && $usuario_id !== $admin_id && !auth_eh_superadmin() && usuario_e_superadmin($conn, $usuario_id)) {
    // Conta da equipe interna: para os outros admins, ela não existe.
    $modo = "listar";
    $erro = "Usuário não encontrado.";
} elseif ($modo === "editar" && admin_alvo_protegido($conn, $usuario_id)) {
    $modo = "listar";
    $erro = "Só administradores com acesso total podem alterar este administrador.";
} elseif ($modo === "editar" && $usuario_id > 0) {
    $sql = "SELECT IDF_ID, IDF_NOME, IDF_EMAIL, IDF_TELEFONE, IDF_CPF, IDF_FUNCAO, IDF_STATUS" . ($funcoes_lista_ativa ? ", IDF_FUNCAO_ID" : "") . " FROM ID_FIEL WHERE IDF_ID = $usuario_id LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if ($res && mysqli_num_rows($res) > 0) {
        $usuario_edicao = mysqli_fetch_assoc($res);
    } else {
        $modo = "listar";
        $erro = "Usuário não encontrado.";
    }
}

// Buscar informações do admin logado
$sql_admin = "SELECT IDF_ID, IDF_NOME, IDF_EMAIL, IDF_TELEFONE, IDF_CPF, IDF_FUNCAO, IDF_STATUS FROM ID_FIEL WHERE IDF_ID = $admin_id LIMIT 1";
$res_admin = mysqli_query($conn, $sql_admin);
$admin_info = $res_admin && mysqli_num_rows($res_admin) > 0 ? mysqli_fetch_assoc($res_admin) : null;

// Buscar todos os usuários
// 50 por página, com busca por nome ou e-mail: antes a tela listava todos os
// fiéis de uma vez (uma consulta e uma tabela que só cresciam).
const USUARIOS_POR_PAGINA = 50;
$busca_usuarios = mb_substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 100);
$pagina_usuarios = min(10000, max(1, (int) ($_GET['pagina'] ?? 1)));
// Contas excluídas e anonimizadas (LGPD, "Conta excluída") não aparecem: só
// ficam no banco pelos registros de pagamento.
$condicoes_usuarios = array('IDF_REMOVIDO_EM IS NULL');
$params_usuarios = array();
if ($busca_usuarios !== '') {
    $termo_usuarios = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), mb_strtolower($busca_usuarios)) . '%';
    $condicoes_usuarios[] = "(LOWER(IDF_NOME) LIKE ? OR LOWER(IDF_EMAIL) LIKE ?)";
    array_push($params_usuarios, $termo_usuarios, $termo_usuarios);
}
// Contas da equipe interna (SUPERADMIN_EMAILS) só aparecem para quem também
// é dela — na lista, no total e na busca.
if (superadmin_emails() && !auth_eh_superadmin()) {
    $condicoes_usuarios[] = "LOWER(IDF_EMAIL) NOT IN (" . implode(', ', array_fill(0, count(superadmin_emails()), '?')) . ")";
    $params_usuarios = array_merge($params_usuarios, superadmin_emails());
}
$filtro_usuarios = $condicoes_usuarios ? 'WHERE ' . implode(' AND ', $condicoes_usuarios) : '';
$res_total_usuarios = db_query($conn, "SELECT COUNT(*) AS total FROM ID_FIEL $filtro_usuarios", $params_usuarios);
$total_usuarios = ($res_total_usuarios && $linha_total = mysqli_fetch_assoc($res_total_usuarios)) ? (int) $linha_total['total'] : 0;
$total_paginas_usuarios = max(1, (int) ceil($total_usuarios / USUARIOS_POR_PAGINA));
$pagina_usuarios = min($pagina_usuarios, $total_paginas_usuarios);
$sql_usuarios = "
    SELECT IDF_ID, IDF_NOME, IDF_EMAIL, IDF_TELEFONE, IDF_CPF, IDF_FUNCAO, IDF_STATUS, IDF_CRIADO_EM
    FROM ID_FIEL
    $filtro_usuarios
    ORDER BY IDF_CRIADO_EM DESC
    LIMIT " . USUARIOS_POR_PAGINA . " OFFSET " . (($pagina_usuarios - 1) * USUARIOS_POR_PAGINA) . "
";
$res_usuarios = db_query($conn, $sql_usuarios, $params_usuarios);
$usuarios = array();
if ($res_usuarios && mysqli_num_rows($res_usuarios) > 0) {
    while ($user = mysqli_fetch_assoc($res_usuarios)) {
        $usuarios[] = $user;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Admin</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <style>
        .user-card { background: var(--bg-light); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; transition: all 0.3s; }
        .user-card:hover { border-color: var(--primary-light); transform: translateY(-4px); }
        .status-badge { display: inline-block; padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .status-aprovado { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .status-pendente { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .status-negado { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .meu-perfil-card { background: linear-gradient(135deg, rgba(59,130,246,0.15) 0%, rgba(16,185,129,0.1) 100%); border: 2px solid var(--primary-light); border-radius: 12px; padding: 2rem; }
        .table-responsive { background: var(--bg-light); border-radius: 10px; overflow: hidden; }
    </style>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'usuarios', 'Gerenciamento de Usuários', 'fa-users'); ?>
        <div class="row mb-4">
            <div class="col-12">
                <p class="text-muted mb-0">Edite informações de usuários e configure seus perfis</p>
            </div>
        </div>

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

        <!-- MEU PERFIL -->
        <?php if ($admin_info && $modo === "listar") { ?>
        <div class="row mb-5">
            <div class="col-12">
                <div class="meu-perfil-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center mb-3">
                                <div style="width: 80px; height: 80px; background: var(--primary-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--bg-dark); font-size: 2rem; font-weight: bold; margin-right: 1.5rem;">
                                    <?php echo strtoupper(substr($admin_info["IDF_NOME"], 0, 1)); ?>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?php echo htmlspecialchars($admin_info["IDF_NOME"]); ?></h3>
                                    <p class="mb-1" style="color: var(--text-muted);">
                                        <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($admin_info["IDF_EMAIL"]); ?>
                                    </p>
                                    <p style="color: var(--text-muted);">
                                        <i class="fas fa-phone me-2"></i><?php echo htmlspecialchars(formatar_telefone_exibicao($admin_info["IDF_TELEFONE"] ?? "")); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editarPerfilModal">
                                <i class="fas fa-edit me-2"></i>Editar Meu Perfil
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>

        <!-- ADICIONAR PESSOA (admin_criar_pessoa()): o irmão idoso, o membro
             novo no balcão. A conta nasce aprovada e a pessoa recebe o convite. -->
        <?php if ($modo === "listar") { $fc = $form_criar ?? array(); ?>
        <details class="user-card mb-4"<?php echo !empty($fc) ? ' open' : ''; ?>>
            <summary class="h5 mb-0" style="cursor: pointer; min-height: 44px; display: flex; align-items: center;"><i class="fas fa-user-plus me-2" aria-hidden="true"></i>Adicionar pessoa</summary>
            <p class="text-muted mt-3">A conta já fica aprovada. A pessoa recebe um e-mail para criar a senha e, no primeiro acesso, aceita o Termo de Uso e a Política de Privacidade.</p>
            <form method="POST" class="row g-3" data-avisar-ao-sair>
                <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="criar_pessoa">
                <div class="col-md-6">
                    <label class="form-label" for="novaNome">Nome completo</label>
                    <input type="text" class="form-control custom-input" id="novaNome" name="nova_nome" maxlength="100" required value="<?php echo htmlspecialchars($fc['nome'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="novaEmail">E-mail (o convite vai para ele)</label>
                    <input type="email" class="form-control custom-input" id="novaEmail" name="nova_email" maxlength="100" required value="<?php echo htmlspecialchars($fc['email'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="novaWhats">WhatsApp <small class="text-muted">(opcional)</small></label>
                    <input type="tel" class="form-control custom-input" id="novaWhats" name="nova_whatsapp" maxlength="20" placeholder="(11) 9 9999-9999" value="<?php echo htmlspecialchars($fc['whatsapp'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="novaNasc">Data de nascimento <small class="text-muted">(opcional)</small></label>
                    <input type="date" class="form-control custom-input" id="novaNasc" name="nova_nascimento" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($fc['nascimento'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="novaResp">Responsável <small class="text-muted">(obrigatório para menor de 18 anos)</small></label>
                    <input type="text" class="form-control custom-input" id="novaResp" name="nova_responsavel" maxlength="100" placeholder="Nome completo do pai, mãe ou responsável" value="<?php echo htmlspecialchars($fc['responsavel'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <label class="d-flex gap-2 align-items-start">
                        <input type="checkbox" name="nova_autorizou" value="1" class="form-check-input mt-1" required<?php echo !empty($fc['autorizou']) ? ' checked' : ''; ?>>
                        <span>A pessoa (ou o responsável, se for menor) autorizou este cadastro.</span>
                    </label>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2" aria-hidden="true"></i>Cadastrar e enviar convite</button>
                </div>
            </form>
        </details>
        <?php } ?>

        <?php
        // 2FA de outro admin (perdeu o celular): só o super admin vê e desliga.
        $doisfa_alvo = ($modo === "editar" && $usuario_edicao && auth_eh_superadmin() && (int) $usuario_edicao['IDF_ID'] !== $admin_id)
            ? doisfa_da_conta($conn, (int) $usuario_edicao['IDF_ID']) : null;
        if ($doisfa_alvo && !empty($doisfa_alvo['idf_2fa_ativo_em'])) { ?>
            <div class="user-card mb-4 border border-warning">
                <h3 class="h5"><i class="fas fa-shield-halved me-2" aria-hidden="true"></i>Verificação em duas etapas ligada</h3>
                <p class="text-muted">Use só se a pessoa perdeu o celular e os códigos de reserva. As sessões dela são encerradas, e ela liga de novo em Minha segurança.</p>
                <form method="POST" class="d-flex flex-wrap gap-2 align-items-end" data-confirmar="Desligar a verificação em duas etapas desta conta?">
                    <?php echo csrf_campo(); ?>
                    <input type="hidden" name="acao" value="desligar_2fa_outro">
                    <input type="hidden" name="usuario_id" value="<?php echo (int) $usuario_edicao['IDF_ID']; ?>">
                    <div>
                        <label class="form-label small" for="senha2faOutro">Sua senha (confirmação)</label>
                        <input type="password" class="form-control custom-input" id="senha2faOutro" name="senha_confirmacao" autocomplete="current-password" maxlength="100" required>
                    </div>
                    <button type="submit" class="btn btn-outline-danger">Desligar a verificação</button>
                </form>
            </div>
        <?php } ?>

        <!-- MODO EDITAR USUÁRIO -->
        <?php if ($modo === "editar" && $usuario_edicao) { ?>
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="user-card">
                    <h3 class="mb-4"><i class="fas fa-edit me-2"></i>Editar Usuário</h3>
                    <form method="POST">
                    <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="editar_usuario">
                        <input type="hidden" name="usuario_id" value="<?php echo $usuario_edicao['IDF_ID']; ?>">

                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome Completo</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario_edicao['IDF_NOME']); ?>" required maxlength="50" pattern="^[^0-9]{3,50}$" title="Nome: sem números, entre 3 e 50 caracteres">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($usuario_edicao['IDF_EMAIL']); ?>" maxlength="100" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="telefone" class="form-label">Telefone</label>
                                <input type="tel" class="form-control" id="telefone" name="telefone" value="<?php echo htmlspecialchars($usuario_edicao['IDF_TELEFONE'] ? formatar_telefone_exibicao($usuario_edicao['IDF_TELEFONE']) : ''); ?>" placeholder="(11) 9 9999-9999" pattern="\([0-9]{2}\) 9 [0-9]{4}-[0-9]{4}" title="Use o formato (11) 9 9999-9999" inputmode="numeric" minlength="16" maxlength="16">
                            </div>
                            <div class="col-md-6 mb-3">
                                <?php if ($funcoes_lista_ativa) { $funcao_atual = (int) ($usuario_edicao['IDF_FUNCAO_ID'] ?? 0); ?>
                                    <input type="hidden" name="funcao_id_atual" value="<?php echo $funcao_atual; ?>">
                                    <label for="funcao_id" class="form-label">Função na igreja</label>
                                    <select class="form-select" id="funcao_id" name="funcao_id">
                                        <option value="0">— Sem função —</option>
                                        <?php foreach ($funcoes_lista as $fn) { if (!$fn['ativo'] && $fn['id'] !== $funcao_atual) { continue; } ?>
                                            <option value="<?php echo $fn['id']; ?>" <?php echo $fn['id'] === $funcao_atual ? 'selected' : ''; ?>><?php echo htmlspecialchars($fn['nome'] . ($fn['ativo'] ? '' : ' (desativada)')); ?></option>
                                        <?php } ?>
                                    </select>
                                    <?php if ($funcao_atual === 0 && trim((string) $usuario_edicao['IDF_FUNCAO']) !== '') { ?>
                                        <small class="text-muted">Informado no cadastro: "<?php echo htmlspecialchars($usuario_edicao['IDF_FUNCAO']); ?>"</small>
                                    <?php } ?>
                                    <small class="d-block"><a href="admin_funcoes.php">Gerenciar a lista de funções</a></small>
                                <?php } else { ?>
                                    <label for="funcao" class="form-label">Função</label>
                                    <input type="text" class="form-control" id="funcao" name="funcao" value="<?php echo htmlspecialchars((string) $usuario_edicao['IDF_FUNCAO']); ?>" maxlength="50">
                                <?php } ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="pendente" <?php echo $usuario_edicao['IDF_STATUS'] === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                <option value="aprovado" <?php echo $usuario_edicao['IDF_STATUS'] === 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                                <option value="negado" <?php echo $usuario_edicao['IDF_STATUS'] === 'negado' ? 'selected' : ''; ?>>Negado</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cpf" class="form-label">CPF <small class="text-muted">(opcional)</small></label>
                                <input type="text" class="form-control" id="cpf" name="cpf" value="<?php echo htmlspecialchars($usuario_edicao['IDF_CPF'] ? formatar_cpf_exibicao($usuario_edicao['IDF_CPF']) : ''); ?>" placeholder="000.000.000-00" pattern="[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}" title="Use o formato 000.000.000-00" inputmode="numeric" minlength="14" maxlength="14">
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Salvar Alterações
                            </button>
                            <a href="admin_gerenciar_usuarios.php" class="btn btn-outline-light">
                                <i class="fas fa-arrow-left me-2"></i>Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php } ?>

        <!-- MODO LISTAR USUÁRIOS -->
        <?php if ($modo === "listar") { ?>
        <form method="GET" class="d-flex gap-2 mb-3" role="search">
            <label for="busca_usuarios" class="visually-hidden">Buscar por nome ou e-mail</label>
            <input type="search" id="busca_usuarios" name="q" class="form-control" placeholder="Buscar por nome ou e-mail" value="<?php echo htmlspecialchars($busca_usuarios); ?>" maxlength="100">
            <button type="submit" class="btn btn-primary">Buscar</button>
            <?php if ($busca_usuarios !== '') { ?>
                <a href="admin_gerenciar_usuarios.php" class="btn btn-outline-light">Limpar</a>
            <?php } ?>
        </form>
        <p class="text-muted small mb-2"><?php echo (int) $total_usuarios; ?> usuário(s)<?php echo $busca_usuarios !== '' ? ' encontrado(s)' : ''; ?><?php echo $total_paginas_usuarios > 1 ? ' · página ' . (int) $pagina_usuarios . ' de ' . (int) $total_paginas_usuarios : ''; ?></p>
        <div class="row mb-4">
            <div class="col-12">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="color: var(--text-main);">
                        <thead style="background: var(--bg-dark);">
                            <tr>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Telefone</th>
                                <th>Função</th>
                                <th>Status</th>
                                <th>Cadastro</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $user) { 
                                $status_class = "status-" . $user['IDF_STATUS'];
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user["IDF_NOME"]); ?></strong></td>
                                <td><?php echo htmlspecialchars($user["IDF_EMAIL"]); ?></td>
                                <td><?php echo htmlspecialchars(formatar_telefone_exibicao($user["IDF_TELEFONE"] ?? "")); ?></td>
                                <td><?php echo htmlspecialchars($user["IDF_FUNCAO"] ?? "-"); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($user["IDF_STATUS"]); ?>
                                    </span>
                                </td>
                                <td><small><?php echo date('d/m/Y', strtotime($user["IDF_CRIADO_EM"])); ?></small></td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="admin_gerenciar_usuarios.php?modo=editar&id=<?php echo $user['IDF_ID']; ?>" class="btn btn-sm btn-primary" title="Editar" style="min-width: 40px; min-height: 40px; display: inline-flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ((int) $user['IDF_ID'] !== $admin_id) { ?>
                                            <form method="POST" data-confirmar="Encerrar todas as sessões ativas deste usuário? Ele será desconectado no próximo carregamento de página." style="display:inline;">
                                            <?php echo csrf_campo(); ?>
                                                <input type="hidden" name="acao" value="desconectar_usuario">
                                                <input type="hidden" name="usuario_id" value="<?php echo $user['IDF_ID']; ?>">
                                                <button type="submit" class="btn btn-sm btn-warning" title="Desconectar sessões ativas" style="min-width: 40px; min-height: 40px; display: inline-flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-unlink"></i>
                                                </button>
                                            </form>
                                            <?php if ($user['IDF_STATUS'] === 'aprovado') { ?>
                                            <form method="POST" data-confirmar="Enviar para o e-mail deste usuário um link para ele criar uma nova senha?" style="display:inline;">
                                            <?php echo csrf_campo(); ?>
                                                <input type="hidden" name="acao" value="enviar_link_senha">
                                                <input type="hidden" name="usuario_id" value="<?php echo $user['IDF_ID']; ?>">
                                                <button type="submit" class="btn btn-sm btn-info" title="Enviar link de nova senha" style="min-width: 40px; min-height: 40px; display: inline-flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                            </form>
                                            <?php } ?>
                                            <form method="POST" data-confirmar="Tem certeza que deseja excluir este usuário?" style="display:inline;">
                                            <?php echo csrf_campo(); ?>
                                                <input type="hidden" name="acao" value="excluir_usuario">
                                                <input type="hidden" name="usuario_id" value="<?php echo $user['IDF_ID']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Excluir" style="min-width: 40px; min-height: 40px; display: inline-flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_paginas_usuarios > 1) {
                    $link_pagina_usuarios = function ($p) use ($busca_usuarios) {
                        return 'admin_gerenciar_usuarios.php?' . http_build_query(array_filter(array('q' => $busca_usuarios, 'pagina' => $p)));
                    }; ?>
                    <nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Páginas de usuários">
                        <?php if ($pagina_usuarios > 1) { ?>
                            <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars($link_pagina_usuarios($pagina_usuarios - 1)); ?>"><i class="fas fa-chevron-left me-1"></i>Anterior</a>
                        <?php } else { ?><span></span><?php } ?>
                        <?php if ($pagina_usuarios < $total_paginas_usuarios) { ?>
                            <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars($link_pagina_usuarios($pagina_usuarios + 1)); ?>">Próxima<i class="fas fa-chevron-right ms-1"></i></a>
                        <?php } ?>
                    </nav>
                <?php } ?>
            </div>
        </div>
        <?php } ?>
    <?php admin_layout_fechar(); ?>

    <!-- MODAL EDITAR PERFIL -->
    <div class="modal fade" id="editarPerfilModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Editar Meu Perfil</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                <?php echo csrf_campo(); ?>
                    <div class="modal-body">
                        <input type="hidden" name="acao" value="editar_perfil">
                        <input type="hidden" name="usuario_id" value="<?php echo $admin_info['IDF_ID']; ?>">

                        <div class="mb-3">
                            <label for="nome_perfil" class="form-label">Nome Completo</label>
                            <input type="text" class="form-control" id="nome_perfil" name="nome" value="<?php echo htmlspecialchars($admin_info['IDF_NOME']); ?>" required maxlength="50" pattern="^[^0-9]{3,50}$" title="Nome: sem números, entre 3 e 50 caracteres">
                        </div>

                        <div class="mb-3">
                            <label for="email_perfil" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email_perfil" value="<?php echo htmlspecialchars($admin_info['IDF_EMAIL']); ?>" disabled>
                            <small class="text-muted">Email não pode ser alterado</small>
                        </div>

                        <div class="mb-3">
                            <label for="telefone_perfil" class="form-label">Telefone</label>
                            <input type="tel" class="form-control" id="telefone_perfil" name="telefone" value="<?php echo htmlspecialchars($admin_info['IDF_TELEFONE'] ? formatar_telefone_exibicao($admin_info['IDF_TELEFONE']) : ''); ?>" placeholder="(11) 9 9999-9999" pattern="\([0-9]{2}\) 9 [0-9]{4}-[0-9]{4}" title="Use o formato (11) 9 9999-9999" inputmode="numeric" minlength="16" maxlength="16">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">CPF</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars(formatar_cpf_exibicao($admin_info['IDF_CPF'])); ?>" disabled>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script nonce="<?php echo site_csp_nonce(); ?>">
        function mascaraCPF(valor) {
            valor = valor.replace(/\D/g, '').substring(0, 11);
            valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
            valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
            valor = valor.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            return valor;
        }

        function mascaraTelefone(valor) {
            valor = valor.replace(/\D/g, '').substring(0, 11);

            if (valor.length <= 2) {
                return '(' + valor;
            }

            if (valor.length === 3) {
                return '(' + valor.substring(0, 2) + ') ' + valor.substring(2);
            }

            var ddd = valor.substring(0, 2);
            var nove = valor.substring(2, 3);
            var bloco1 = valor.substring(3, 7);
            var bloco2 = valor.substring(7, 11);
            var formatado = '(' + ddd + ') ' + nove;

            if (bloco1.length > 0) {
                formatado += ' ' + bloco1;
            }

            if (bloco2.length > 0) {
                formatado += '-' + bloco2;
            }

            return formatado;
        }

        ['cpf', 'telefone', 'telefone_perfil'].forEach(function (id) {
            var input = document.getElementById(id);
            if (!input) {
                return;
            }

            input.addEventListener('input', function () {
                this.value = id.indexOf('cpf') !== -1 ? mascaraCPF(this.value) : mascaraTelefone(this.value);
            });
        });
    </script>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
