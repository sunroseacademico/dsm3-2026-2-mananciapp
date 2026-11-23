<?php
include("../conexao.php");
require_once __DIR__ . '/includes/cursos_helpers.php';
auth_require();

// Configurações do fiel: tudo o que se edita (dados da conta, dados
// pessoais, vínculo e filial, preferências de conteúdo, notificações,
// aparência e senha). O perfil (perfil.php) é só para ver, como uma página
// de perfil de rede social; a foto continua sendo trocada lá.

$fiel_id = (int) auth_resolve_fiel_id();
$acao_post = $_SERVER['REQUEST_METHOD'] === 'POST' ? site_entrada_texto($_POST['acao'] ?? '', false) : '';
$mensagem = "";
$erro = "";

// O que este fiel pode mudar: depende do tipo dele (seção 74, Admin →
// Tipos de fiel; ver fiel_permissoes() em conexao.php).
$pode_nome = fiel_pode('nome');
$pode_email = fiel_pode('email');
$pode_cpf = fiel_pode('cpf');
$pode_dados = fiel_pode('dados');
$pode_membro = fiel_pode('membro');
$pode_identificacao = $pode_nome || $pode_email || $pode_cpf;

// Mensagem de sucesso de cada seção: gravada na sessão antes do
// redirecionamento (para um F5 não reenviar o formulário).
$config_ok = function ($chave) {
    $texto = (string) ($_SESSION[$chave] ?? '');
    unset($_SESSION[$chave]);
    return $texto;
};
$config_redirecionar = function ($chave, $texto, $ancora) {
    $_SESSION[$chave] = $texto;
    header('Location: configuracoes.php#' . $ancora);
    exit;
};

// ---- Dados da conta: nome, e-mail de login e CPF ----
$identificacao_erro = '';
if ($pode_identificacao && $acao_post === 'identificacao') {
    if (!csrf_validar()) {
        $identificacao_erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } else {
        $identificacao_dados = array('senha_atual' => site_entrada_texto($_POST['senha_atual'] ?? '', false));
        foreach (array('nome' => $pode_nome, 'email' => $pode_email, 'cpf' => $pode_cpf) as $campo => $pode) {
            if ($pode) {
                $identificacao_dados[$campo] = site_entrada_texto($_POST[$campo] ?? '');
            }
        }
        $identificacao_erro = fiel_identificacao_salvar($conn, $fiel_id, $identificacao_dados);
        if ($identificacao_erro === '') {
            $config_redirecionar('config_conta_ok', 'Dados da conta atualizados.', 'conta');
        }
    }
}
$identificacao_mensagem = $config_ok('config_conta_ok');

// ---- Membro da igreja (seção 47 do bancosupabase.md) ----
$tem_membro = fiel_membro_disponivel($conn);
$membro_erro = '';
$membro_form = null;
if ($tem_membro && $pode_membro && $acao_post === 'membro') {
    $membro_form = array(
        'membro' => site_entrada_texto($_POST['IDF_MEMBRO'] ?? '', false),
        'mes' => (int) ($_POST['IDF_MEMBRO_MES'] ?? 0),
        'ano' => (int) ($_POST['IDF_MEMBRO_ANO'] ?? 0),
    );
    $membro_validado = fiel_membro_validar($membro_form['membro'], $membro_form['mes'], $membro_form['ano']);
    if (!csrf_validar()) {
        $membro_erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } elseif ($membro_validado['erro']) {
        $membro_erro = $membro_validado['erro'];
    } elseif ($fiel_id > 0) {
        if (db_query($conn, "UPDATE ID_FIEL SET IDF_MEMBRO = ?, IDF_MEMBRO_DESDE = ? WHERE IDF_ID = ?", array((int) $membro_validado['membro'], $membro_validado['desde'] ? $membro_validado['desde'] : null, $fiel_id))) {
            $config_redirecionar('config_membro_ok', 'Vínculo com a igreja atualizado.', 'igreja');
        }
        $membro_erro = 'Não foi possível salvar. Tente novamente.';
    }
}
$membro_mensagem = $config_ok('config_membro_ok');

// ---- Filial (seção 76): mesma permissão do vínculo de membro ----
$tem_filiais = filiais_disponivel($conn);
$filial_erro = '';
$filial_enviada = null;
if ($tem_filiais && $pode_membro && $acao_post === 'filial') {
    $filial_enviada = site_entrada_texto($_POST['IDF_FILIAL_ESCOLHA'] ?? '');
    if (!csrf_validar()) {
        $filial_erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } elseif (($filial_erro = (string) fiel_filial_definir($conn, $fiel_id, $filial_enviada)) === '') {
        $config_redirecionar('config_filial_ok', 'Filial atualizada.', 'filial');
    }
}
$filial_mensagem = $config_ok('config_filial_ok');

// ---- Preferências de conteúdo (T23, seção 56): temas, tempo e lembrete ----
$tem_preferencias = preferencias_conteudo_disponivel($conn);
$preferencias_erro = '';
$preferencias_form = null;
$temas_opcoes = $tem_preferencias ? preferencias_temas_opcoes($conn) : array();
if ($tem_preferencias && $acao_post === 'preferencias') {
    $preferencias_form = preferencias_conteudo_validar($_POST['temas'] ?? array(), $_POST['tempo'] ?? '', $_POST['lembrete'] ?? '', $temas_opcoes);
    if (!csrf_validar()) {
        $preferencias_erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } elseif ($preferencias_form['erro']) {
        $preferencias_erro = $preferencias_form['erro'];
    } elseif ($fiel_id > 0 && preferencias_conteudo_salvar($conn, $fiel_id, $preferencias_form)) {
        $config_redirecionar('config_preferencias_ok', 'Preferências salvas.', 'preferencias');
    } else {
        $preferencias_erro = 'Não foi possível salvar. Tente novamente.';
    }
}
$preferencias_mensagem = $config_ok('config_preferencias_ok');

// ---- Dados pessoais (T11, seção 49): WhatsApp, nascimento, família, cidade ----
// O WhatsApp fica em IDF_TELEFONE, que sempre existiu; o resto só aparece
// com as colunas novas.
$tem_perfil = fiel_perfil_disponivel($conn);
$dados_erro = '';
$dados_form = null;
if ($acao_post === 'dados' && $pode_dados) {
    $dados_form = array(
        'whatsapp' => trim(site_entrada_texto($_POST['IDF_WHATSAPP'] ?? '', false)),
        'nascimento' => trim(site_entrada_texto($_POST['IDF_NASCIMENTO'] ?? '', false)),
        'estado_civil' => site_entrada_texto($_POST['IDF_ESTADO_CIVIL'] ?? '', false),
        'tem_filhos' => site_entrada_texto($_POST['IDF_TEM_FILHOS'] ?? '', false),
        'cidade' => trim(site_entrada_texto($_POST['IDF_CIDADE'] ?? '', false)),
        'uf' => site_entrada_texto($_POST['IDF_UF'] ?? '', false),
    );
    $whatsapp_novo = fiel_whatsapp_normalizar($dados_form['whatsapp']);
    $nascimento_novo = $tem_perfil ? fiel_nascimento_validar($dados_form['nascimento']) : array('erro' => null, 'data' => null);
    $contexto_novo = $tem_perfil ? fiel_contexto_validar($dados_form['estado_civil'], $dados_form['tem_filhos'], $dados_form['cidade'], $dados_form['uf']) : array('erro' => null);

    if (!csrf_validar()) {
        $dados_erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } elseif ($whatsapp_novo === '') {
        $dados_erro = 'Informe seu WhatsApp com DDD.';
    } elseif ($whatsapp_novo === null) {
        $dados_erro = 'WhatsApp inválido. Use o celular com DDD, ex.: (11) 9 9999-9999.';
    } elseif ($nascimento_novo['erro']) {
        $dados_erro = $nascimento_novo['erro'];
    } elseif ($tem_perfil && ($erro_menor = configuracoes_nascimento_bloqueio($conn, $fiel_id, $nascimento_novo['data'])) !== '') {
        $dados_erro = $erro_menor;
    } elseif ($contexto_novo['erro']) {
        $dados_erro = $contexto_novo['erro'];
    } elseif ($fiel_id > 0) {
        // Consulta preparada: cada "?" de $sets tem seu valor em $params, na mesma ordem.
        $sets = array('IDF_TELEFONE = ?');
        $params = array($whatsapp_novo);
        if ($tem_perfil) {
            $sets[] = 'IDF_NASCIMENTO = ?';
            $params[] = $nascimento_novo['data'];
            $sets[] = 'IDF_ESTADO_CIVIL = ?';
            $params[] = $contexto_novo['estado_civil'];
            $sets[] = 'IDF_TEM_FILHOS = ?';
            $params[] = $contexto_novo['tem_filhos'] === null ? null : (int) $contexto_novo['tem_filhos'];
            $sets[] = 'IDF_CIDADE = ?';
            $params[] = $contexto_novo['cidade'];
            $sets[] = 'IDF_UF = ?';
            $params[] = $contexto_novo['uf'];
        }
        $sets[] = 'IDF_ATUALIZADO_EM = NOW()';
        $params[] = $fiel_id;
        if (db_query($conn, 'UPDATE ID_FIEL SET ' . implode(', ', $sets) . ' WHERE IDF_ID = ?', $params)) {
            $config_redirecionar('config_dados_ok', 'Dados pessoais atualizados.', 'dados-pessoais');
        }
        $dados_erro = 'Não foi possível salvar. Tente novamente.';
    }
}
$dados_mensagem = $config_ok('config_dados_ok');

// ---- Capa do perfil (recompensa dos Primeiros passos, seção 80) ----
$capa_erro = '';
if ($acao_post === 'capa') {
    if (!csrf_validar()) {
        $capa_erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } elseif (($capa_erro = perfil_capa_salvar($conn, $fiel_id, site_entrada_texto($_POST['capa'] ?? ''))) === '') {
        $config_redirecionar('config_capa_ok', 'Capa do perfil atualizada.', 'aparencia');
    }
}
$capa_mensagem = $config_ok('config_capa_ok');
$capa_atual = perfil_capa_do_fiel($conn, $fiel_id);
$pp_resumo = primeiros_passos_resumo(primeiros_passos($conn, $fiel_id));

// ---- Senha ----
// Erro/sucesso da troca de senha aparecem no próprio cartão "Segurança".
$senha_erro = "";
$senha_ok = "";
if (in_array($acao_post, array('trocar_senha', 'notificacoes'), true) && $fiel_id && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($acao_post === 'trocar_senha' && $fiel_id) {
    // Regras em senha_trocar() (php/conexao.php): exige a senha atual, conta
    // os erros no limite do login e derruba as outras sessões da conta.
    $senha_erro = senha_trocar($conn, $fiel_id, site_entrada_texto($_POST['senha_atual'] ?? '', false), site_entrada_texto($_POST['senha_nova'] ?? '', false), site_entrada_texto($_POST['senha_confirma'] ?? '', false));
    if ($senha_erro === '') {
        $senha_ok = "Senha alterada. Os outros aparelhos conectados à sua conta foram desconectados, e enviamos um aviso para o seu e-mail.";
    }
} elseif ($acao_post === 'notificacoes' && $fiel_id) {
    $notif_email = isset($_POST['notif_email']) ? 1 : 0;
    $notif_push = isset($_POST['notif_push']) ? 1 : 0;

    // ID_PREFERENCIA.IDF_ID tem UNIQUE de verdade, então isso aqui dispara
    // "de verdade" — INSERT ... ON DUPLICATE KEY UPDATE (MySQL) vira
    // INSERT ... ON CONFLICT (coluna) DO UPDATE (Postgres).
    $sql = "
        INSERT INTO ID_PREFERENCIA (IDF_ID, IDPREF_NOTIFICACOES_EMAIL, IDPREF_NOTIFICACOES_PUSH)
        VALUES ($fiel_id, $notif_email, $notif_push)
        ON CONFLICT (IDF_ID) DO UPDATE SET
            IDPREF_NOTIFICACOES_EMAIL = EXCLUDED.IDPREF_NOTIFICACOES_EMAIL,
            IDPREF_NOTIFICACOES_PUSH = EXCLUDED.IDPREF_NOTIFICACOES_PUSH
    ";
    if (mysqli_query($conn, $sql)) {
        $mensagem = "Preferências de notificação atualizadas.";
    }
}

// ---- Privacidade (LGPD): baixar os dados e excluir a conta ----
$privacidade_erro = '';
if (in_array($acao_post, array('exportar_dados', 'excluir_conta'), true) && $fiel_id) {
    if (!csrf_validar()) {
        $privacidade_erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } elseif ($acao_post === 'exportar_dados'
        && ($privacidade_erro = senha_conferir_atual($conn, $fiel_id, site_entrada_texto($_POST['senha_exportacao'] ?? '', false))) !== '') {
        // Senha, como em "Excluir conta": com a sessão de outra pessoa (aparelho
        // compartilhado) dava para levar CPF, telefone e pagamentos num clique.
    } elseif ($acao_post === 'exportar_dados' && !site_intervalo_livre('lgpd_exportar_' . (int) $fiel_id, 600)) {
        // Consulta pesada (todas as tabelas da pessoa): 1 a cada 10 min.
        $privacidade_erro = 'Você baixou seus dados há pouco. Tente de novo em alguns minutos.';
    } elseif ($acao_post === 'exportar_dados') {
        // Arquivo JSON com tudo o que o app guarda da pessoa (art. 18, II e V).
        lgpd_registrar($conn, $fiel_id, 'exportacao');
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="meus-dados-' . date('Y-m-d') . '.json"');
        header('Cache-Control: no-store');
        echo json_encode(lgpd_exportar_dados($conn, $fiel_id), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } elseif (mb_strtoupper(site_entrada_texto($_POST['confirmacao'] ?? '')) !== 'EXCLUIR') {
        $privacidade_erro = 'Para confirmar, digite EXCLUIR no campo indicado.';
    } elseif (($privacidade_erro = lgpd_exclusao_bloqueio($conn, $fiel_id)) === ''
        && ($privacidade_erro = senha_conferir_atual($conn, $fiel_id, site_entrada_texto($_POST['senha_exclusao'] ?? '', false))) === '') {
        // O e-mail de confirmação sai antes: depois da exclusão o endereço
        // não fica mais guardado.
        $res_dono = db_query($conn, "SELECT IDF_NOME, IDF_EMAIL FROM ID_FIEL WHERE IDF_ID = ? LIMIT 1", array($fiel_id));
        $dono = $res_dono ? mysqli_fetch_assoc($res_dono) : null;
        $exclusao = lgpd_excluir_conta($conn, $fiel_id, 'proprio');
        if (!$exclusao['ok']) {
            $privacidade_erro = $exclusao['erro'];
        } else {
            if ($dono && defined('EMAIL_API_KEY') && trim((string) EMAIL_API_KEY) !== '') {
                $nome_dono = (string) $dono['IDF_NOME'];
                try {
                    enviar_email_sistema(
                        (string) $dono['IDF_EMAIL'],
                        'Sua conta foi excluída',
                        '<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;"><h2 style="margin: 0 0 16px; color: #1d4ed8;">Conta excluída</h2>'
                            . '<p>Olá, <strong>' . htmlspecialchars($nome_dono) . '</strong>.</p>'
                            . '<p>Sua conta foi excluída a seu pedido e seus dados pessoais foram apagados. Registros de pagamento, se houver, ficam guardados só pelo tempo que a lei exige, sem ligação com seus dados.</p>'
                            . '<p>Se não foi você, responda este e-mail ou fale conosco pela página de contato.</p></div>',
                        "Olá, {$nome_dono}!\n\nSua conta foi excluída a seu pedido e seus dados pessoais foram apagados. Registros de pagamento, se houver, ficam guardados só pelo tempo que a lei exige, sem ligação com seus dados.\n\nSe não foi você, fale conosco pela página de contato."
                    );
                } catch (\Throwable $ex) {
                    // A conta já foi excluída; o aviso é só cortesia.
                }
            }
            session_unset();
            session_destroy();
            session_start();
            session_regenerate_id(true);
            $_SESSION['cadastro_sucesso'] = 'Sua conta foi excluída. Obrigado por ter caminhado com a gente.';
            header('Location: ' . site_url('login.php?onesignal_logout=1'));
            exit;
        }
    }
}

$preferencias = array('IDPREF_NOTIFICACOES_EMAIL' => 1, 'IDPREF_NOTIFICACOES_PUSH' => 1);
if ($fiel_id) {
    $res = mysqli_query($conn, "SELECT * FROM ID_PREFERENCIA WHERE IDF_ID = $fiel_id LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $preferencias = $row;
    }
}

// ---- Valores salvos (pelo id da sessão, não pelo e-mail) ----
$res_usuario = $fiel_id > 0 ? mysqli_query($conn, "SELECT IDF_NOME, IDF_EMAIL, IDF_CPF, IDF_FILIAL, IDF_FILIAL_ID, " . ($tem_filiais ? 'IDF_SEM_FILIAL' : '0 AS IDF_SEM_FILIAL') . " FROM ID_FIEL WHERE IDF_ID = $fiel_id LIMIT 1") : false;
$usuario = ($res_usuario ? mysqli_fetch_assoc($res_usuario) : null) ?: array();

$perfil_dados = array('whatsapp' => '', 'nascimento' => null, 'estado_civil' => null, 'tem_filhos' => null, 'cidade' => null, 'uf' => null);
$colunas_perfil = 'IDF_TELEFONE' . ($tem_perfil ? ', IDF_NASCIMENTO, IDF_ESTADO_CIVIL, IDF_TEM_FILHOS, IDF_CIDADE, IDF_UF' : '');
$res_perfil = $fiel_id > 0 ? mysqli_query($conn, "SELECT $colunas_perfil FROM ID_FIEL WHERE IDF_ID = $fiel_id LIMIT 1") : false;
$linha_perfil = $res_perfil ? mysqli_fetch_assoc($res_perfil) : null;
if ($linha_perfil) {
    $perfil_dados['whatsapp'] = (string) ($linha_perfil['IDF_TELEFONE'] ?? '');
    if ($tem_perfil) {
        $perfil_dados['nascimento'] = $linha_perfil['IDF_NASCIMENTO'] ? substr((string) $linha_perfil['IDF_NASCIMENTO'], 0, 10) : null;
        $perfil_dados['estado_civil'] = $linha_perfil['IDF_ESTADO_CIVIL'];
        $perfil_dados['tem_filhos'] = $linha_perfil['IDF_TEM_FILHOS'] === null ? null : (int) $linha_perfil['IDF_TEM_FILHOS'];
        $perfil_dados['cidade'] = $linha_perfil['IDF_CIDADE'];
        $perfil_dados['uf'] = $linha_perfil['IDF_UF'] !== null ? trim((string) $linha_perfil['IDF_UF']) : null;
    }
}
$whatsapp_salvo_ok = (bool) fiel_whatsapp_normalizar($perfil_dados['whatsapp']);
// O formulário volta com o que foi enviado (se deu erro) ou com o salvo.
if ($dados_form === null) {
    $dados_form = array(
        'whatsapp' => $perfil_dados['whatsapp'],
        'nascimento' => (string) $perfil_dados['nascimento'],
        'estado_civil' => (string) $perfil_dados['estado_civil'],
        'tem_filhos' => $perfil_dados['tem_filhos'] === null ? '' : (string) $perfil_dados['tem_filhos'],
        'cidade' => (string) $perfil_dados['cidade'],
        'uf' => (string) $perfil_dados['uf'],
    );
}

if ($tem_membro && $membro_form === null) {
    $res_membro = mysqli_query($conn, "SELECT IDF_MEMBRO, IDF_MEMBRO_DESDE FROM ID_FIEL WHERE IDF_ID = $fiel_id LIMIT 1");
    $linha_membro = $res_membro ? mysqli_fetch_assoc($res_membro) : null;
    $membro_atual = $linha_membro && $linha_membro['IDF_MEMBRO'] !== null ? (int) $linha_membro['IDF_MEMBRO'] : null;
    $data_desde = $membro_atual === 1 && !empty($linha_membro['IDF_MEMBRO_DESDE']) ? date_create($linha_membro['IDF_MEMBRO_DESDE']) : null;
    $membro_form = array(
        'membro' => $membro_atual === null ? '' : (string) $membro_atual,
        'mes' => $data_desde ? (int) $data_desde->format('n') : 0,
        'ano' => $data_desde ? (int) $data_desde->format('Y') : 0,
    );
}

$prefs_form = $tem_preferencias ? ($preferencias_form ?? preferencias_conteudo_ler($conn, $fiel_id)) : null;

$perfil_assinatura = assinatura_modulo_ativo() ? assinatura_do_fiel($conn, $fiel_id) : null;
$perfil_tem_acesso = assinatura_modulo_ativo() && assinatura_fiel_tem_acesso($conn, $fiel_id);

// Menu lateral (âncoras das seções abaixo).
$config_secoes = array_filter(array(
    'conta' => $pode_identificacao ? array('fa-user-gear', 'Dados da conta') : null,
    'dados-pessoais' => array('fa-id-card', 'Dados pessoais'),
    'igreja' => ($tem_membro || $tem_filiais) ? array('fa-church', 'Igreja e filial') : null,
    'preferencias' => $tem_preferencias ? array('fa-sliders', 'Conteúdo e lembrete') : null,
    'notificacoes' => array('fa-bell', 'Notificações'),
    'aparencia' => array('fa-palette', 'Aparência'),
    'seguranca' => array('fa-lock', 'Senha e segurança'),
    'assinatura' => assinatura_modulo_ativo() ? array('fa-crown', 'Assinatura') : null,
    'privacidade' => array('fa-user-shield', 'Privacidade e dados'),
));

// Aviso de sucesso/erro de uma seção.
$config_aviso = function ($ok, $falha) {
    if ($ok !== '') {
        echo '<div class="perfil-membro-ok mb-3" role="status"><i class="fas fa-check-circle me-1"></i>' . htmlspecialchars($ok) . '</div>';
    }
    if ($falha !== '') {
        echo '<div class="auth-erro mb-3" role="alert">' . htmlspecialchars($falha) . '</div>';
    }
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Configurações</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'configuracoes'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container py-5">
        <div class="config-pagina">
            <div class="streaming-page-header d-flex justify-content-between align-items-end flex-wrap gap-2">
                <div>
                    <h1><i class="fas fa-cog me-2" style="color:#60a5fa;"></i>Configurações</h1>
                    <p class="mb-0">Seus dados, preferências e segurança da conta</p>
                </div>
                <a href="perfil.php" class="btn btn-outline-light btn-sm"><i class="fas fa-user me-1"></i>Ver meu perfil</a>
            </div>

            <?php if ($mensagem !== "") { ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
            <?php } ?>
            <?php if ($erro !== "") { ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
            <?php } ?>

            <div class="config-layout">
                <nav class="config-menu" aria-label="Seções das configurações">
                    <?php foreach ($config_secoes as $ancora => $secao) { ?>
                        <a href="#<?php echo $ancora; ?>"><i class="fas <?php echo $secao[0]; ?>" aria-hidden="true"></i><?php echo $secao[1]; ?></a>
                    <?php } ?>
                </nav>

                <div class="config-conteudo">
                    <?php if ($pode_identificacao) { ?>
                        <section class="streaming-info-card text-start mb-4" id="conta">
                            <h2 class="config-titulo"><i class="fas fa-user-gear me-2" aria-hidden="true"></i>Dados da conta</h2>
                            <p class="text-muted small">Você pode atualizar <?php echo htmlspecialchars(implode(', ', array_keys(array_filter(array('o nome' => $pode_nome, 'o e-mail de login' => $pode_email, 'o CPF' => $pode_cpf))))); ?>.</p>
                            <?php $config_aviso($identificacao_mensagem, $identificacao_erro); ?>
                            <form method="POST" action="configuracoes.php#conta" class="perfil-dados-form">
                                <?php echo csrf_campo(); ?>
                                <input type="hidden" name="acao" value="identificacao">
                                <div class="row g-2">
                                    <?php if ($pode_nome) { ?>
                                        <div class="col-12">
                                            <label class="form-label small mb-1" for="conta_nome">Nome completo</label>
                                            <input type="text" name="nome" id="conta_nome" class="form-control custom-input" maxlength="100" required value="<?php echo htmlspecialchars(isset($_POST['nome']) ? site_entrada_texto($_POST['nome']) : (string) ($usuario['IDF_NOME'] ?? '')); ?>">
                                        </div>
                                    <?php } ?>
                                    <?php if ($pode_cpf) { ?>
                                        <div class="col-sm-6">
                                            <label class="form-label small mb-1" for="conta_cpf">CPF <span class="text-muted">(só números; pode deixar em branco)</span></label>
                                            <input type="text" name="cpf" id="conta_cpf" class="form-control custom-input" inputmode="numeric" maxlength="11" data-so-digitos value="<?php echo htmlspecialchars(isset($_POST['cpf']) ? site_entrada_texto($_POST['cpf']) : (string) ($usuario['IDF_CPF'] ?? '')); ?>">
                                        </div>
                                    <?php } ?>
                                    <?php if ($pode_email) { ?>
                                        <div class="col-sm-6">
                                            <label class="form-label small mb-1" for="conta_email">E-mail de login</label>
                                            <input type="email" name="email" id="conta_email" class="form-control custom-input" maxlength="100" required autocomplete="email" value="<?php echo htmlspecialchars(isset($_POST['email']) ? site_entrada_texto($_POST['email']) : (string) ($usuario['IDF_EMAIL'] ?? '')); ?>">
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label small mb-1" for="conta_senha">Senha atual <span class="text-muted">(só para trocar o e-mail)</span></label>
                                            <input type="password" name="senha_atual" id="conta_senha" class="form-control custom-input" autocomplete="current-password">
                                        </div>
                                    <?php } ?>
                                </div>
                                <button type="submit" class="btn btn-play btn-sm mt-3">Salvar</button>
                            </form>
                        </section>
                    <?php } ?>

                    <section class="streaming-info-card text-start mb-4" id="dados-pessoais">
                        <h2 class="config-titulo"><i class="fas fa-id-card me-2" aria-hidden="true"></i>Dados pessoais</h2>
                        <?php $config_aviso($dados_mensagem, $dados_erro); ?>
                        <?php if ($pode_dados) { ?>
                            <?php if (!$whatsapp_salvo_ok && !$dados_erro) { ?>
                                <div class="perfil-aviso-campo mb-3"><i class="fab fa-whatsapp me-1"></i>Informe seu WhatsApp para completar o cadastro.</div>
                            <?php } ?>
                            <form method="POST" action="configuracoes.php#dados-pessoais" class="perfil-dados-form">
                                <?php echo csrf_campo(); ?>
                                <input type="hidden" name="acao" value="dados">
                                <?php
                                    $campo_perfil = $dados_form + array('com_nascimento' => $tem_perfil, 'com_contexto' => $tem_perfil);
                                    include __DIR__ . '/includes/campo_perfil.php';
                                ?>
                                <button type="submit" class="btn btn-play btn-sm mt-3">Salvar</button>
                            </form>
                        <?php } else { ?>
                            <p class="text-muted mb-0">Seus dados pessoais são atualizados pela equipe da igreja.</p>
                        <?php } ?>
                    </section>

                    <?php if ($tem_membro || $tem_filiais) { ?>
                        <section class="streaming-info-card text-start mb-4" id="igreja">
                            <h2 class="config-titulo"><i class="fas fa-church me-2" aria-hidden="true"></i>Igreja e filial</h2>
                            <?php if (!$pode_membro) { ?>
                                <p class="text-muted mb-0">O vínculo com a igreja e a filial são atualizados pela equipe da igreja.</p>
                            <?php } else { ?>
                                <?php if ($tem_membro) { ?>
                                    <?php $config_aviso($membro_mensagem, $membro_erro); ?>
                                    <form method="POST" action="configuracoes.php#igreja" class="perfil-membro-form mb-4">
                                        <?php echo csrf_campo(); ?>
                                        <input type="hidden" name="acao" value="membro">
                                        <?php $campo_membro = $membro_form; include __DIR__ . '/includes/campo_membro.php'; ?>
                                        <button type="submit" class="btn btn-play btn-sm mt-3">Salvar vínculo</button>
                                    </form>
                                <?php } ?>
                                <?php if ($tem_filiais) {
                                    $filial_atual = fiel_filial_escolha_atual($usuario['IDF_FILIAL_ID'] ?? 0, $usuario['IDF_SEM_FILIAL'] ?? 0);
                                ?>
                                    <div id="filial">
                                        <?php $config_aviso($filial_mensagem, $filial_erro); ?>
                                        <?php if ($filial_atual === '' && trim((string) ($usuario['IDF_FILIAL'] ?? '')) !== '') { ?>
                                            <p class="text-muted small">No cadastro você escreveu "<?php echo htmlspecialchars($usuario['IDF_FILIAL']); ?>". Escolha a sua filial na lista.</p>
                                        <?php } ?>
                                        <form method="POST" action="configuracoes.php#filial" class="perfil-membro-form">
                                            <?php echo csrf_campo(); ?>
                                            <input type="hidden" name="acao" value="filial">
                                            <?php
                                                $campo_filial = array('filiais' => filiais_listar($conn), 'escolha' => $filial_enviada ?? $filial_atual, 'atual' => $filial_atual);
                                                include __DIR__ . '/includes/campo_filial.php';
                                            ?>
                                            <button type="submit" class="btn btn-play btn-sm mt-3">Salvar filial</button>
                                        </form>
                                    </div>
                                <?php } ?>
                            <?php } ?>
                        </section>
                    <?php } ?>

                    <?php if ($tem_preferencias) { ?>
                        <section class="streaming-info-card text-start mb-4" id="preferencias">
                            <h2 class="config-titulo"><i class="fas fa-sliders me-2" aria-hidden="true"></i>Conteúdo e lembrete</h2>
                            <p class="text-muted small">Usados em "Recomendados para você", na ordem das filas da página inicial e no lembrete diário.</p>
                            <?php $config_aviso($preferencias_mensagem, $preferencias_erro); ?>
                            <form method="POST" action="configuracoes.php#preferencias" class="perfil-membro-form perfil-preferencias-form">
                                <?php echo csrf_campo(); ?>
                                <input type="hidden" name="acao" value="preferencias">

                                <fieldset class="mb-3">
                                    <legend class="form-label">Temas de interesse <small class="text-muted">(até <?php echo (int) PREFERENCIAS_TEMAS_MAX; ?>)</small></legend>
                                    <?php if ($temas_opcoes) { ?>
                                        <div class="perfil-opcoes">
                                            <?php foreach ($temas_opcoes as $tema_id => $tema_nome) { ?>
                                                <label class="perfil-opcao">
                                                    <input type="checkbox" name="temas[]" value="<?php echo (int) $tema_id; ?>" <?php echo in_array($tema_id, $prefs_form['temas'], true) ? 'checked' : ''; ?>>
                                                    <span><i class="fas <?php echo htmlspecialchars(categoria_icone($tema_nome)); ?>" aria-hidden="true"></i><?php echo htmlspecialchars($tema_nome); ?></span>
                                                </label>
                                            <?php } ?>
                                        </div>
                                    <?php } else { ?>
                                        <small class="text-muted">Ainda não há temas cadastrados.</small>
                                    <?php } ?>
                                </fieldset>

                                <fieldset class="mb-3">
                                    <legend class="form-label">Quanto tempo você tem por dia?</legend>
                                    <div class="perfil-opcoes">
                                        <?php foreach (preferencias_tempos() as $minutos => $rotulo_tempo) { ?>
                                            <label class="perfil-opcao">
                                                <input type="radio" name="tempo" value="<?php echo (int) $minutos; ?>" <?php echo $prefs_form['tempo'] === $minutos ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($rotulo_tempo); ?></span>
                                            </label>
                                        <?php } ?>
                                    </div>
                                    <small class="text-muted d-block mt-1">As aulas que cabem nesse tempo aparecem primeiro nas sugestões.</small>
                                </fieldset>

                                <div class="mb-1">
                                    <label class="form-label" for="prefLembrete">Lembrete diário</label>
                                    <select class="form-select custom-input perfil-lembrete-select" id="prefLembrete" name="lembrete">
                                        <option value="">Não quero lembrete</option>
                                        <?php foreach (preferencias_horarios() as $horario) { ?>
                                            <option value="<?php echo $horario; ?>" <?php echo $prefs_form['lembrete'] === $horario ? 'selected' : ''; ?>>Todo dia às <?php echo str_replace(':', 'h', $horario); ?></option>
                                        <?php } ?>
                                    </select>
                                    <small class="text-muted d-block mt-1">Uma notificação no celular para o seu momento com Deus, com a aula de onde você parou. Precisa das <a href="#notificacoes">notificações push</a> ligadas.</small>
                                </div>

                                <button type="submit" class="btn btn-play btn-sm mt-3">Salvar</button>
                            </form>
                        </section>
                    <?php } ?>

                    <form method="POST" action="configuracoes.php#notificacoes" id="notificacoes" class="mb-4">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="notificacoes">
                        <section class="streaming-info-card text-start">
                            <h2 class="config-titulo"><i class="fas fa-bell me-2" aria-hidden="true"></i>Notificações</h2>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" name="notif_email" id="notif_email" <?php echo !empty($preferencias['IDPREF_NOTIFICACOES_EMAIL']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="notif_email">Receber notificações por e-mail</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="notif_push" id="notif_push" <?php echo !empty($preferencias['IDPREF_NOTIFICACOES_PUSH']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="notif_push">Receber notificações push</label>
                            </div>
                            <small id="notif_push_status" class="form-text text-muted d-block mt-1">Verificando o status neste navegador...</small>
                            <button type="submit" class="btn btn-play btn-sm mt-3">Salvar notificações</button>
                        </section>
                    </form>

                    <section class="streaming-info-card text-start mb-4" id="aparencia">
                        <h2 class="config-titulo"><i class="fas fa-palette me-2" aria-hidden="true"></i>Aparência</h2>
                        <p class="text-muted mb-2">Escolha entre o tema claro e o escuro.</p>
                        <div class="theme-toggle-container">
                            <i class="fas fa-moon theme-icon"></i>
                            <input type="checkbox" id="theme-toggle" class="theme-toggle" aria-label="Alternar tema">
                            <i class="fas fa-sun theme-icon"></i>
                        </div>

                        <?php if (perfil_capa_disponivel($conn)) {
                            $capa_liberada = $pp_resumo['completo'];
                        ?>
                            <hr class="my-4">
                            <h3 class="config-subtitulo">Capa do perfil</h3>
                            <?php if ($capa_liberada) { ?>
                                <p class="text-muted small">A recompensa dos Primeiros passos: escolha a capa que aparece no seu perfil.</p>
                            <?php } else { ?>
                                <p class="text-muted small"><i class="fas fa-lock me-1" aria-hidden="true"></i>Complete os <a href="desafios.php#primeiros-passos">Primeiros passos</a> para liberar (<?php echo $pp_resumo['feitos']; ?> de <?php echo $pp_resumo['total']; ?> feitos).</p>
                            <?php } ?>
                            <?php $config_aviso($capa_mensagem, $capa_erro); ?>
                            <form method="POST" action="configuracoes.php#aparencia">
                                <?php echo csrf_campo(); ?>
                                <input type="hidden" name="acao" value="capa">
                                <fieldset class="capas-opcoes" <?php echo $capa_liberada ? '' : 'disabled'; ?>>
                                    <legend class="visually-hidden">Capa do perfil</legend>
                                    <?php foreach (perfil_capas() as $capa_chave => $capa) { ?>
                                        <label class="capa-opcao">
                                            <input type="radio" name="capa" value="<?php echo $capa_chave; ?>" <?php echo $capa_atual === $capa_chave ? 'checked' : ''; ?>>
                                            <span class="capa-amostra perfil-capa <?php echo $capa[1]; ?>" aria-hidden="true"></span>
                                            <span class="capa-nome"><?php echo htmlspecialchars($capa[0]); ?></span>
                                        </label>
                                    <?php } ?>
                                </fieldset>
                                <?php if ($capa_liberada) { ?>
                                    <button type="submit" class="btn btn-play btn-sm mt-3">Salvar capa</button>
                                <?php } ?>
                            </form>
                        <?php } ?>
                    </section>

                    <form method="POST" action="configuracoes.php#seguranca" id="seguranca" class="mb-4" autocomplete="on">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="trocar_senha">
                        <section class="streaming-info-card text-start">
                            <h2 class="config-titulo"><i class="fas fa-lock me-2" aria-hidden="true"></i>Senha e segurança</h2>
                            <p class="text-muted mb-3">Trocar a senha desconecta os outros aparelhos em que sua conta está aberta.</p>
                            <?php if ($senha_ok !== "") { ?>
                                <div class="alert alert-success" role="status"><?php echo htmlspecialchars($senha_ok); ?></div>
                            <?php } ?>
                            <?php if ($senha_erro !== "") { ?>
                                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($senha_erro); ?></div>
                            <?php } ?>
                            <!-- Campo de usuário oculto: ajuda o gerenciador de senhas a
                                 saber de qual conta é a senha nova. -->
                            <input type="email" name="usuario" value="<?php echo htmlspecialchars((string) ($_SESSION['Usuario_logado'] ?? '')); ?>" autocomplete="username" hidden readonly>
                            <div class="mb-3">
                                <label for="senha_atual" class="form-label">Senha atual</label>
                                <input type="password" class="form-control custom-input" id="senha_atual" name="senha_atual" autocomplete="current-password" maxlength="100" required>
                            </div>
                            <div class="mb-3">
                                <label for="senha_nova" class="form-label">Nova senha</label>
                                <input type="password" class="form-control custom-input" id="senha_nova" name="senha_nova" autocomplete="new-password" minlength="<?php echo SENHA_MIN; ?>" maxlength="<?php echo SENHA_MAX; ?>" pattern="(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{<?php echo SENHA_MIN; ?>,<?php echo SENHA_MAX; ?>}" title="<?php echo SENHA_MIN; ?>-<?php echo SENHA_MAX; ?> caracteres, pelo menos 1 letra maiúscula e 1 caractere especial" aria-describedby="senha_regras" required>
                                <small id="senha_regras" class="form-text text-muted"><?php echo SENHA_MIN; ?> a <?php echo SENHA_MAX; ?> caracteres, com pelo menos 1 letra maiúscula e 1 caractere especial.</small>
                            </div>
                            <div class="mb-3">
                                <label for="senha_confirma" class="form-label">Confirmar nova senha</label>
                                <input type="password" class="form-control custom-input" id="senha_confirma" name="senha_confirma" autocomplete="new-password" minlength="<?php echo SENHA_MIN; ?>" maxlength="<?php echo SENHA_MAX; ?>" required>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="senha_mostrar">
                                <label class="form-check-label" for="senha_mostrar">Mostrar senhas</label>
                            </div>
                            <button type="submit" class="btn btn-play btn-sm">Trocar senha</button>
                        </section>
                    </form>

                    <?php if (assinatura_modulo_ativo()) { ?>
                        <section class="streaming-info-card text-start mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2" id="assinatura">
                            <div>
                                <h2 class="config-titulo mb-1"><i class="fas fa-crown me-2" style="color:#fbbf24;" aria-hidden="true"></i>Assinatura</h2>
                                <span class="text-muted">
                                    <?php if ($perfil_tem_acesso) { ?>
                                        Acesso completo<?php echo !empty($perfil_assinatura['idp_nome']) ? ' — plano ' . htmlspecialchars($perfil_assinatura['idp_nome']) : ''; ?>
                                    <?php } elseif ($perfil_assinatura && $perfil_assinatura['idas_status'] === 'PENDENTE') { ?>
                                        Aguardando pagamento
                                    <?php } else { ?>
                                        Você ainda não é assinante
                                    <?php } ?>
                                </span>
                            </div>
                            <a href="assinar.php" class="btn btn-outline-light btn-sm"><?php echo $perfil_tem_acesso ? 'Gerenciar' : 'Ver planos'; ?></a>
                        </section>
                    <?php } ?>

                    <section class="streaming-info-card text-start mb-4" id="privacidade">
                        <h2 class="config-titulo"><i class="fas fa-user-shield me-2" aria-hidden="true"></i>Privacidade e dados</h2>
                        <p class="text-muted mb-3">
                            Veja na <a href="../../privacidade.php" class="auth-link">Política de Privacidade</a> quais dados guardamos, para quê e com quem compartilhamos.
                        </p>
                        <?php $config_aviso('', $privacidade_erro); ?>

                        <h3 class="config-subtitulo">Baixar meus dados</h3>
                        <p class="text-muted small mb-2">Um arquivo com tudo o que o app guarda sobre você: cadastro, progresso, certificados, comentários, orações, contribuições e acessos.</p>
                        <form method="POST" action="configuracoes.php#privacidade" class="mb-4">
                            <?php echo csrf_campo(); ?>
                            <input type="hidden" name="acao" value="exportar_dados">
                            <div class="mb-2" style="max-width: 320px;">
                                <label for="senha_exportacao" class="form-label small">Sua senha</label>
                                <input type="password" class="form-control custom-input" id="senha_exportacao" name="senha_exportacao" autocomplete="current-password" maxlength="100" required>
                            </div>
                            <button type="submit" class="btn btn-outline-light btn-sm"><i class="fas fa-download me-1" aria-hidden="true"></i>Baixar meus dados</button>
                        </form>

                        <h3 class="config-subtitulo">Excluir minha conta</h3>
                        <p class="text-muted small mb-2">
                            Apaga seus dados pessoais, progresso, certificados, anotações, orações e testemunhos. <strong>Não dá para desfazer.</strong>
                            Comentários que têm respostas de outras pessoas ficam ocultos, sem o texto; registros de pagamento ficam guardados só pelo prazo da lei, sem seu nome.
                        </p>
                        <details class="config-excluir">
                            <summary class="btn btn-outline-danger btn-sm">Quero excluir minha conta</summary>
                            <form method="POST" action="configuracoes.php#privacidade" class="mt-3" data-confirmar="Excluir sua conta de vez? Isso não pode ser desfeito.">
                                <?php echo csrf_campo(); ?>
                                <input type="hidden" name="acao" value="excluir_conta">
                                <div class="mb-3">
                                    <label for="senha_exclusao" class="form-label">Sua senha</label>
                                    <input type="password" class="form-control custom-input" id="senha_exclusao" name="senha_exclusao" autocomplete="current-password" maxlength="100" required>
                                </div>
                                <div class="mb-3">
                                    <label for="confirmacao_exclusao" class="form-label">Digite <strong>EXCLUIR</strong> para confirmar</label>
                                    <input type="text" class="form-control custom-input" id="confirmacao_exclusao" name="confirmacao" autocomplete="off" maxlength="10" pattern="[Ee][Xx][Cc][Ll][Uu][Ii][Rr]" required>
                                </div>
                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash me-1" aria-hidden="true"></i>Excluir minha conta</button>
                            </form>
                        </details>
                    </section>
                </div>
            </div>
        </div>
    </main>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
<script nonce="<?php echo site_csp_nonce(); ?>">
    // Limite de temas: ao chegar no máximo, os outros ficam desabilitados.
    (function () {
        var caixas = document.querySelectorAll('input[name="temas[]"]');
        var max = <?php echo (int) PREFERENCIAS_TEMAS_MAX; ?>;
        function atualizar() {
            var marcados = Array.prototype.filter.call(caixas, function (c) { return c.checked; }).length;
            caixas.forEach(function (c) { c.disabled = !c.checked && marcados >= max; });
        }
        caixas.forEach(function (c) { c.addEventListener('change', atualizar); });
        atualizar();
    })();
    // Menu lateral: marca a seção que está na tela.
    (function () {
        var links = document.querySelectorAll('.config-menu a');
        if (!links.length || !('IntersectionObserver' in window)) return;
        var observador = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (e) {
                if (!e.isIntersecting) return;
                links.forEach(function (l) {
                    if (l.getAttribute('href') === '#' + e.target.id) { l.setAttribute('aria-current', 'true'); } else { l.removeAttribute('aria-current'); }
                });
            });
        }, { rootMargin: '-30% 0px -60% 0px' });
        links.forEach(function (l) {
            var alvo = document.getElementById(l.getAttribute('href').slice(1));
            if (alvo) observador.observe(alvo);
        });
    })();
</script>
<script nonce="<?php echo site_csp_nonce(); ?>">
    // Troca de senha: mostrar/ocultar e conferir a confirmação antes de enviar
    // (o servidor confere de novo em senha_trocar()).
    (function () {
        var mostrar = document.getElementById('senha_mostrar');
        var campos = ['senha_atual', 'senha_nova', 'senha_confirma'].map(function (id) { return document.getElementById(id); });
        var nova = campos[1];
        var confirma = campos[2];
        if (!mostrar || !nova || !confirma) { return; }
        mostrar.addEventListener('change', function () {
            campos.forEach(function (campo) { campo.type = mostrar.checked ? 'text' : 'password'; });
        });
        function conferir() {
            confirma.setCustomValidity(confirma.value !== '' && confirma.value !== nova.value ? 'As senhas não conferem.' : '');
        }
        nova.addEventListener('input', conferir);
        confirma.addEventListener('input', conferir);
    })();
</script>
<script nonce="<?php echo site_csp_nonce(); ?>">
    // O switch "notif_push" mostra a escolha salva do fiel
    // (IDPREF_NOTIFICACOES_PUSH — gravada também quando ele responde o
    // convite da OneSignal, ver pwa_head.php) e, ao ser alterado, já grava
    // no banco e liga/desliga a inscrição real deste navegador na OneSignal
    // — sem precisar clicar em "Salvar notificações". Assim quem clicou em
    // "Mais tarde" no convite consegue ativar por aqui depois, e vice-versa.
    // window.oneSignalPronto vem de pwa_head.php: só lê o estado da inscrição
    // depois que init/login da OneSignal terminaram.
    window.oneSignalPronto.then(function (OneSignal) {
        var checkbox = document.getElementById('notif_push');
        var status = document.getElementById('notif_push_status');
        if (!checkbox || !status) { return; }

        function bloqueadoNoNavegador() {
            return typeof Notification !== 'undefined' && Notification.permission === 'denied';
        }

        function inscritoNesteNavegador() {
            return !!(OneSignal.User && OneSignal.User.PushSubscription && OneSignal.User.PushSubscription.optedIn);
        }

        function salvar(ativo) {
            if (typeof window.salvarPreferenciaPush === 'function') {
                window.salvarPreferenciaPush(ativo);
            }
        }

        function atualizarStatus() {
            status.classList.remove('text-warning');
            if (!checkbox.checked) {
                status.textContent = 'Desativado. Ligue o botão acima quando quiser receber notificações push.';
                return;
            }
            if (bloqueadoNoNavegador()) {
                status.textContent = 'Você quer receber push, mas as notificações estão bloqueadas neste navegador. Abra as informações do site (ícone ao lado do endereço) e libere "Notificações" manualmente.';
                status.classList.add('text-warning');
                return;
            }
            if (inscritoNesteNavegador()) {
                status.textContent = 'Ativado neste navegador.';
                return;
            }
            // Escolha salva = sim, mas este navegador/aparelho ainda não está
            // inscrito (ex.: primeiro acesso por outro aparelho).
            status.textContent = 'Ativado na sua conta, mas este navegador ainda não está inscrito. ';
            var botaoAtivar = document.createElement('button');
            botaoAtivar.type = 'button';
            botaoAtivar.className = 'btn btn-link btn-sm p-0 align-baseline';
            botaoAtivar.textContent = 'Ativar neste navegador';
            botaoAtivar.addEventListener('click', ativarNesteNavegador);
            status.appendChild(botaoAtivar);
        }

        atualizarStatus();

        // Ex.: respondeu o convite da OneSignal com esta tela aberta.
        if (OneSignal.User && OneSignal.User.PushSubscription) {
            OneSignal.User.PushSubscription.addEventListener('change', function (evento) {
                if (evento && evento.current && evento.previous && evento.current.optedIn !== evento.previous.optedIn) {
                    checkbox.checked = !!evento.current.optedIn;
                    atualizarStatus();
                }
            });
        }

        checkbox.addEventListener('change', function () {
            if (!checkbox.checked) {
                salvar(false);
                OneSignal.User.PushSubscription.optOut().catch(function () {}).finally(atualizarStatus);
                return;
            }
            ativarNesteNavegador();
        });

        function ativarNesteNavegador() {
            checkbox.checked = true;
            if (bloqueadoNoNavegador()) {
                (window.siteAvisar || window.alert)('As notificações deste site estão bloqueadas no seu navegador. Abra as informações do site (ícone ao lado do endereço) e libere "Notificações" manualmente, depois ligue o botão de novo.', 'erro');
                checkbox.checked = false;
                salvar(false);
                atualizarStatus();
                return;
            }

            OneSignal.Notifications.requestPermission()
                .then(function () { return OneSignal.User.PushSubscription.optIn(); })
                .catch(function () {})
                .finally(function () {
                    // Se a pessoa negou a permissão no pop-up do navegador,
                    // volta o botão pra desligado e grava o "não".
                    if (bloqueadoNoNavegador() || !inscritoNesteNavegador()) {
                        checkbox.checked = false;
                    }
                    salvar(checkbox.checked);
                    atualizarStatus();
                });
        }
    });
</script>
</body>
</html>
