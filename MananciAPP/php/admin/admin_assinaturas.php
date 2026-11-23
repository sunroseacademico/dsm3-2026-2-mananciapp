<?php
// Painel de assinaturas (Asaas): números do mês, planos (valor, ciclo,
// forma de pagamento, quantas pessoas cada plano libera) e a lista de
// assinantes, com cancelar e acesso cortesia. Ver
// docs/interno/assinaturas/assinaturas_asaas.md.
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));
// Ações que deram certo vão para o Registro de atividade (admin_log_ao_redirecionar()).
admin_log_ao_redirecionar($conn, 'assinaturas');

$mensagem = "";
$erro = "";
$hoje = site_hoje();
$migracao_ok = tabela_tem_coluna($conn, 'ID_PLANO', 'IDP_FORMA_PAGAMENTO') && tabela_tem_coluna($conn, 'ID_CURSO', 'IDC_GRATUITO');

$ciclos = array('MONTHLY' => 'Mensal', 'QUARTERLY' => 'Trimestral', 'SEMIANNUALLY' => 'Semestral', 'YEARLY' => 'Anual');
$formas = array('UNDEFINED' => 'Fiel escolhe (Pix, boleto ou cartão)', 'CREDIT_CARD' => 'Só cartão de crédito', 'PIX' => 'Só Pix', 'BOLETO' => 'Só boleto');
$status_info = array(
    'PENDENTE' => array('Aguardando pagamento', 'bg-warning text-dark'),
    'ATIVA' => array('Ativa', 'bg-success'),
    'ATRASADA' => array('Atrasada', 'bg-danger'),
    'CANCELADA' => array('Cancelada', 'bg-secondary'),
    'CORTESIA' => array('Cortesia', 'bg-info text-dark'),
    'TESTE' => array('Teste grátis', 'bg-primary'),
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'abrir_assinaturas') {
    // Abre ou fecha as assinaturas para os fiéis (assinaturas_abertas() em
    // php/conexao.php). Não depende das tabelas de planos.
    if (site_config_salvar($conn, 'assinaturas_abertas', isset($_POST['abertas']) ? '1' : '0')) {
        header('Location: admin_assinaturas.php?salvo=1');
        exit;
    }
    $erro = "Não foi possível salvar. Rode a seção 43 do database/bancosupabase.md no Supabase.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $migracao_ok) {
    $acao = site_entrada_texto($_POST['acao'] ?? '', false);

    if ($acao === 'interruptores') {
        $modo = in_array($_POST['assinaturas_modo'] ?? '', array('livre', 'conteudo', 'login'), true) ? $_POST['assinaturas_modo'] : 'livre';
        $teste_dias = (string) min(365, max(0, (int) ($_POST['assinaturas_teste_dias'] ?? 0)));
        if (site_config_salvar($conn, 'assinaturas_modo', $modo)
            && site_config_salvar($conn, 'assinaturas_teste_dias', $teste_dias)) {
            header('Location: admin_assinaturas.php?salvo=1');
            exit;
        }
        $erro = "Não foi possível salvar. Rode as seções 43 e 44 do database/bancosupabase.md no Supabase.";
    } elseif ($acao === 'salvar_plano') {
        $plano_id = (int) ($_POST['idp_id'] ?? 0);
        $nome = trim(site_entrada_texto($_POST['idp_nome'] ?? '', false));
        $descricao = trim(site_entrada_texto($_POST['idp_descricao'] ?? '', false));
        // Aceita "39,90", "1.290,00" e "39.90".
        $valor_texto = str_replace(array('R$', ' '), '', site_entrada_texto($_POST['idp_valor'] ?? '', false));
        if (strpos($valor_texto, ',') !== false) {
            $valor_texto = str_replace(array('.', ','), array('', '.'), $valor_texto);
        }
        $valor_centavos = (int) round(((float) $valor_texto) * 100);
        $ciclo = site_entrada_texto($_POST['idp_ciclo'] ?? '', false);
        $forma = site_entrada_texto($_POST['idp_forma_pagamento'] ?? '', false);
        $max_benef = max(0, (int) ($_POST['idp_max_beneficiarios'] ?? 0));
        $aceita_cnpj = isset($_POST['idp_aceita_cnpj']) ? 1 : 0;
        $ordem = (int) ($_POST['idp_ordem'] ?? 0);
        $ativo = isset($_POST['idp_ativo']) ? 1 : 0;

        if ($nome === '' || mb_strlen($nome) > 100 || mb_strlen($descricao) > 255) {
            $erro = "Informe o nome do plano (até 100 caracteres) e uma descrição de até 255.";
        } elseif ($valor_centavos < 500) {
            $erro = "Informe um valor válido (o Asaas não cobra assinatura abaixo de R$ 5,00).";
        } elseif (!isset($ciclos[$ciclo]) || !isset($formas[$forma])) {
            $erro = "Ciclo ou forma de pagamento inválidos.";
        } elseif ($plano_id > 0 && plano_tem_assinantes_e_mudou_cobranca($conn, $plano_id, $valor_centavos, $ciclo, $forma)) {
            // Mudar o ciclo de um plano com assinantes mudava o acesso que
            // cada pagamento deles libera (o acesso usa o ciclo do plano), e
            // as assinaturas no Asaas continuavam com o valor/ciclo antigos.
            $erro = "Este plano já tem assinantes: não dá para mudar valor, ciclo ou forma de pagamento. Crie um plano novo com os valores novos e desative este (quem já assina continua como está).";
        } else {
            // Mesma ordem de colunas no UPDATE e no INSERT.
            $params_plano = array($nome, $descricao, $valor_centavos, $ciclo, $forma, $max_benef, $aceita_cnpj, $ordem, $ativo);
            if ($plano_id > 0) {
                $params_plano[] = $plano_id;
                $ok = db_query($conn, "
                    UPDATE id_plano SET
                        idp_nome = ?, idp_descricao = ?, idp_valor_centavos = ?,
                        idp_ciclo = ?, idp_forma_pagamento = ?, idp_max_beneficiarios = ?,
                        idp_aceita_cnpj = ?, idp_ordem = ?, idp_ativo = ?
                    WHERE idp_id = ?
                ", $params_plano);
            } else {
                $ok = db_query($conn, "
                    INSERT INTO id_plano (idp_nome, idp_descricao, idp_valor_centavos, idp_ciclo, idp_forma_pagamento, idp_max_beneficiarios, idp_aceita_cnpj, idp_ordem, idp_ativo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ", $params_plano);
            }
            $mensagem = $ok ? "Plano salvo." : "";
            $erro = $ok ? "" : "Não foi possível salvar o plano.";
        }
    } elseif ($acao === 'cancelar') {
        $erro = assinatura_cancelar($conn, (int) ($_POST['fiel_id'] ?? 0));
        $mensagem = $erro === "" ? "Assinatura cancelada no Asaas. O acesso continua até o fim do período pago." : "";
    } elseif ($acao === 'cortesia') {
        $fiel_id = (int) ($_POST['fiel_id'] ?? 0);
        $email = trim(site_entrada_texto($_POST['email'] ?? '', false));
        if ($fiel_id <= 0 && $email !== '') {
            $res = db_query($conn, "SELECT idf_id FROM id_fiel WHERE LOWER(idf_email) = ? LIMIT 1", array(mb_strtolower($email)));
            $fiel_id = (int) (($res ? mysqli_fetch_assoc($res) : null)['idf_id'] ?? 0);
        }
        if ($fiel_id <= 0) {
            $erro = "Fiel não encontrado.";
        } elseif (assinatura_definir_cortesia($conn, $fiel_id, site_entrada_texto($_POST['data'] ?? '', false))) {
            $mensagem = "Acesso atualizado.";
        } else {
            $erro = "Data inválida.";
        }
    }
}

function assinaturas_contar($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? (int) $linha['cnt'] : 0;
}

$planos = array();
$assinaturas = array();
$contagem = array('ATIVA' => 0, 'ATRASADA' => 0, 'PENDENTE' => 0);
$receita_mes = 0;
$filtro_status = isset($status_info[site_entrada_texto($_GET['status'] ?? '')]) ? site_entrada_texto($_GET['status']) : '';

if ($migracao_ok) {
    $res = mysqli_query($conn, "SELECT * FROM id_plano ORDER BY idp_ordem, idp_valor_centavos");
    $planos = $res ? $res->todas_as_linhas() : array();

    foreach (array_keys($contagem) as $st) {
        $contagem[$st] = assinaturas_contar($conn, "SELECT COUNT(*) AS cnt FROM id_assinatura WHERE idas_status = '$st'");
    }
    $inicio_mes = substr($hoje, 0, 8) . '01';
    $receita_mes = assinaturas_contar($conn, "
        SELECT COALESCE(SUM(idpg_valor_centavos), 0) AS cnt FROM id_pagamento
        WHERE idpg_status IN ('RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH') AND idpg_pago_em >= DATE '$inicio_mes'
    ");

    $where = $filtro_status !== '' ? "WHERE a.idas_status = '$filtro_status'" : '';
    $res = mysqli_query($conn, "
        SELECT a.idf_id, a.idas_status, a.idas_acesso_ate, a.idas_asaas_subscription_id,
               f.idf_nome, f.idf_email, p.idp_nome,
               (SELECT COUNT(*) FROM id_assinatura_beneficiario b WHERE b.idas_id = a.idas_id) AS beneficiarios
        FROM id_assinatura a
        JOIN id_fiel f ON f.idf_id = a.idf_id
        LEFT JOIN id_plano p ON p.idp_id = a.idp_id
        $where
        ORDER BY a.idas_atualizado_em DESC
        LIMIT 300
    ");
    $assinaturas = $res ? $res->todas_as_linhas() : array();
}

function assinaturas_data($valor, $formato = 'd/m/Y') {
    if (empty($valor)) {
        return '';
    }
    $data = new DateTime($valor);
    $data->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    return $data->format($formato);
}

$eh_sandbox = strpos((string) ASAAS_API_URL, 'sandbox') !== false;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Assinaturas</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'assinaturas', 'Assinaturas', 'fa-crown'); ?>

    <?php if ($mensagem !== "" || isset($_GET['salvo'])) { ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($mensagem !== "" ? $mensagem : "Configuração salva — já vale para todos."); ?></div>
    <?php } ?>
    <?php if ($erro !== "") { ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
    <?php } ?>

    <div class="alert <?php echo $eh_sandbox ? 'alert-warning' : 'alert-info'; ?> small">
        <strong>Asaas:</strong>
        <?php if (!asaas_configurado()) { ?>
            não configurado (falta <code>ASAAS_API_KEY</code>) — a tela de assinar fica indisponível para o fiel.
        <?php } else { ?>
            <?php echo $eh_sandbox ? 'ambiente de TESTE (sandbox) — nenhuma cobrança é real.' : 'PRODUÇÃO — cobranças reais.'; ?>
        <?php } ?>
        <?php if (trim((string) ASAAS_WEBHOOK_TOKEN) === '') { ?> Webhook sem <code>ASAAS_WEBHOOK_TOKEN</code>: nenhum pagamento é liberado automaticamente.<?php } ?>
    </div>

    <?php $abertas = assinaturas_abertas(); ?>
    <div class="card mb-4" id="abrir" style="background: var(--bg-light); border: 1px solid <?php echo $abertas ? 'var(--border-color)' : 'rgba(245, 158, 11, 0.45)'; ?>;">
        <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
            <h5 class="mb-0"><i class="fas fa-power-off me-2" style="color:<?php echo $abertas ? '#22c55e' : '#f59e0b'; ?>;"></i>Assinaturas para os fiéis: <?php echo $abertas ? 'abertas' : 'fechadas'; ?></h5>
            <small class="text-muted">Fechadas, o fiel não vê nada de assinatura: some o "Assinatura" do menu e do perfil, a tela de planos volta para a home e ninguém é barrado. As contribuições (aba Contribuir e missões) continuam funcionando, porque usam o mesmo Asaas. Você continua cadastrando e ajustando os planos aqui.</small>
        </div>
        <div class="card-body">
            <form method="POST" class="d-flex flex-wrap align-items-center gap-3" data-confirmar="<?php echo $abertas ? 'Fechar as assinaturas? O fiel deixa de ver os planos e ninguém é barrado.' : 'Abrir as assinaturas? O fiel passa a ver os planos e Quem pode assistir passa a valer.'; ?>">
                <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="abrir_assinaturas">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" name="abertas" id="abertas" <?php echo $abertas ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-semibold" for="abertas">Mostrar as assinaturas para os fiéis</label>
                </div>
                <button type="submit" class="btn btn-sm btn-salvar"><i class="fas fa-save me-1"></i>Salvar</button>
            </form>
            <?php if (!$abertas) { ?>
                <small class="d-block mt-2" style="color:#f59e0b;"><i class="fas fa-circle-info me-1"></i>Lançamento em 01/12/2026 gratuito; assinaturas previstas para fevereiro/março de 2027.</small>
            <?php } ?>
        </div>
    </div>

    <div class="card mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color);">
        <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
            <h5 class="mb-0"><i class="fas fa-user-lock me-2" style="color:#60a5fa;"></i>Quem pode assistir</h5>
            <small class="text-muted">Vale quando as assinaturas estão abertas (acima). Aqui você escolhe quem é barrado — vale na hora, para todos, sem reiniciar o Render.</small>
        </div>
        <div class="card-body">
            <?php
                $modo_salvo = site_config('assinaturas_modo', '');
                if ($modo_salvo === '') {
                    $modo_salvo = site_config('assinaturas_bloqueio', '0') === '1' ? 'conteudo' : 'livre';
                }
                $teste_dias_salvo = (int) site_config('assinaturas_teste_dias', '0');
                $modos = array(
                    'livre' => array('fa-unlock', 'Livre', 'Todos assistem tudo. A tela de assinatura continua disponível para quem quiser assinar.'),
                    'conteudo' => array('fa-film', 'Conteúdo pago (freemium)', 'Todos entram no app; sem assinatura, só abrem os cursos marcados como gratuitos.'),
                    'login' => array('fa-door-closed', 'Só assinantes', 'Sem assinatura, o fiel cai direto na tela de planos ao entrar e não navega pelo app.'),
                );
            ?>
            <form method="POST" id="formInterruptores">
                <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="interruptores">

                <div class="row g-3 mb-4">
                    <?php foreach ($modos as $valor => $info) { ?>
                        <div class="col-md-4">
                            <label class="opcao-modo" for="modo_<?php echo $valor; ?>">
                                <input class="form-check-input me-2" type="radio" name="assinaturas_modo" id="modo_<?php echo $valor; ?>" value="<?php echo $valor; ?>" <?php echo $modo_salvo === $valor ? 'checked' : ''; ?>>
                                <strong><i class="fas <?php echo $info[0]; ?> me-1"></i><?php echo $info[1]; ?></strong>
                                <small><?php echo $info[2]; ?></small>
                            </label>
                        </div>
                    <?php } ?>
                </div>

                <div class="row g-3 align-items-end mb-3">
                    <div class="col-sm-5 col-md-3">
                        <label class="form-label" for="assinaturas_teste_dias"><strong>Teste grátis (dias)</strong></label>
                        <input type="number" min="0" max="365" class="form-control custom-input" name="assinaturas_teste_dias" id="assinaturas_teste_dias" value="<?php echo $teste_dias_salvo; ?>">
                    </div>
                    <div class="col">
                        <small class="text-muted">0 = sem teste. Com 30, por exemplo, quem nunca teve acesso ganha o botão "Experimente grátis por 30 dias" na tela de planos — uma vez por pessoa. Só aparece nos modos "Conteúdo pago" e "Só assinantes".</small>
                    </div>
                </div>

                <p class="small mb-3">
                    Em vigor agora:
                    <?php if (!asaas_configurado()) { ?>
                        <span class="badge bg-secondary">Asaas não configurado</span> todos assistem tudo (ninguém consegue assinar sem a chave do Asaas).
                    <?php } elseif (!assinaturas_abertas()) { ?>
                        <span class="badge bg-secondary">assinaturas fechadas</span> todos assistem tudo; o fiel não vê nada de assinatura.
                    <?php } elseif (assinatura_modo() === 'livre') { ?>
                        <span class="badge bg-info text-dark">livre</span> todos assistem tudo; quem quiser pode assinar.
                    <?php } elseif (assinatura_modo() === 'conteudo') { ?>
                        <span class="badge bg-warning text-dark">conteúdo pago</span> sem acesso, só cursos gratuitos.
                    <?php } else { ?>
                        <span class="badge bg-danger">só assinantes</span> sem acesso, o fiel vê só a tela de planos.
                    <?php } ?>
                    <?php if (assinatura_teste_dias() > 0) { ?>
                        <span class="badge bg-success">teste grátis de <?php echo assinatura_teste_dias(); ?> dia(s)</span>
                    <?php } ?>
                    Tolerância após vencer: <?php echo (int) ASSINATURA_TOLERANCIA_DIAS; ?> dia(s). Admin nunca é bloqueado.
                </p>
                <button type="submit" class="btn btn-salvar"><i class="fas fa-save me-1"></i>Salvar</button>
            </form>
            <script nonce="<?php echo site_csp_nonce(); ?>">
                (function () {
                    var form = document.getElementById('formInterruptores');
                    var modoAntes = <?php echo json_encode($modo_salvo); ?>;
                    var ordem = { livre: 0, conteudo: 1, login: 2 };
                    // Ficar mais restritivo tira o acesso de gente na hora — confirma antes.
                    form.addEventListener('submit', function (evento) {
                        var escolhido = (form.querySelector('input[name="assinaturas_modo"]:checked') || {}).value || 'livre';
                        if (ordem[escolhido] > ordem[modoAntes] && !confirm('Mudar para um modo mais restrito? Quem não tem assinatura, cortesia ou teste perde o acesso agora.')) {
                            evento.preventDefault();
                        }
                    });
                })();
            </script>
        </div>
    </div>

    <?php if (!$migracao_ok) { ?>
        <div class="alert alert-danger">As tabelas de assinatura ainda não existem no banco. Rode as seções 41 a 44 do <code>database/bancosupabase.md</code> no SQL Editor do Supabase.</div>
    <?php } else { ?>

    <div class="ofensiva-stats mb-4">
        <div class="ofensiva-stat">
            <i class="fas fa-circle-check" style="color:#22c55e;"></i>
            <strong><?php echo $contagem['ATIVA']; ?></strong>
            <small>Assinaturas ativas</small>
        </div>
        <div class="ofensiva-stat">
            <i class="fas fa-triangle-exclamation" style="color:#ef4444;"></i>
            <strong><?php echo $contagem['ATRASADA']; ?></strong>
            <small>Atrasadas</small>
        </div>
        <div class="ofensiva-stat">
            <i class="fas fa-hourglass-half" style="color:#f59e0b;"></i>
            <strong><?php echo $contagem['PENDENTE']; ?></strong>
            <small>Aguardando 1º pagamento</small>
        </div>
        <div class="ofensiva-stat">
            <i class="fas fa-sack-dollar" style="color:#60a5fa;"></i>
            <strong><?php echo assinatura_formatar_valor($receita_mes); ?></strong>
            <small>Recebido neste mês</small>
        </div>
    </div>

    <div class="card mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color);">
        <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
            <h5 class="mb-0"><i class="fas fa-tags me-2" style="color:#60a5fa;"></i>Planos</h5>
            <small class="text-muted">Mudar o valor vale para quem assinar daqui em diante — assinaturas já criadas no Asaas mantêm o valor antigo.</small>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach (array_merge($planos, array(null)) as $plano) {
                    $pid = (int) ($plano['idp_id'] ?? 0);
                ?>
                    <div class="col-lg-6">
                        <form method="POST" class="p-3 h-100" style="border: 1px solid var(--border-color); border-radius: 10px;">
                            <?php echo csrf_campo(); ?>
                            <input type="hidden" name="acao" value="salvar_plano">
                            <input type="hidden" name="idp_id" value="<?php echo $pid; ?>">
                            <h6 class="mb-3"><?php echo $plano ? htmlspecialchars($plano['idp_nome']) : '<i class="fas fa-plus me-1"></i>Novo plano'; ?>
                                <?php if ($plano) { ?><span class="badge <?php echo (int) $plano['idp_ativo'] === 1 ? 'bg-success' : 'bg-secondary'; ?> ms-1"><?php echo (int) $plano['idp_ativo'] === 1 ? 'Ativo' : 'Desativado'; ?></span><?php } ?>
                            </h6>
                            <div class="row g-2">
                                <div class="col-8"><input type="text" name="idp_nome" class="form-control form-control-sm custom-input" placeholder="Nome" maxlength="100" required value="<?php echo htmlspecialchars((string) ($plano['idp_nome'] ?? '')); ?>"></div>
                                <div class="col-4"><input type="text" name="idp_valor" class="form-control form-control-sm custom-input" placeholder="Valor (R$)" required value="<?php echo $plano ? number_format($plano['idp_valor_centavos'] / 100, 2, ',', '') : ''; ?>"></div>
                                <div class="col-12"><input type="text" name="idp_descricao" class="form-control form-control-sm custom-input" placeholder="Descrição para o fiel" maxlength="255" value="<?php echo htmlspecialchars((string) ($plano['idp_descricao'] ?? '')); ?>"></div>
                                <div class="col-6">
                                    <select name="idp_ciclo" class="form-select form-select-sm custom-input">
                                        <?php foreach ($ciclos as $valor => $rotulo) { ?><option value="<?php echo $valor; ?>" <?php echo ($plano['idp_ciclo'] ?? 'MONTHLY') === $valor ? 'selected' : ''; ?>><?php echo $rotulo; ?></option><?php } ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select name="idp_forma_pagamento" class="form-select form-select-sm custom-input">
                                        <?php foreach ($formas as $valor => $rotulo) { ?><option value="<?php echo $valor; ?>" <?php echo ($plano['idp_forma_pagamento'] ?? 'UNDEFINED') === $valor ? 'selected' : ''; ?>><?php echo $rotulo; ?></option><?php } ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">Outras pessoas que o titular libera</label>
                                    <input type="number" min="0" name="idp_max_beneficiarios" class="form-control form-control-sm custom-input" value="<?php echo (int) ($plano['idp_max_beneficiarios'] ?? 0); ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">Ordem na tela</label>
                                    <input type="number" name="idp_ordem" class="form-control form-control-sm custom-input" value="<?php echo (int) ($plano['idp_ordem'] ?? 0); ?>">
                                </div>
                                <div class="col-12 d-flex flex-wrap gap-3 align-items-center">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="idp_aceita_cnpj" id="cnpj_<?php echo $pid; ?>" <?php echo !empty($plano['idp_aceita_cnpj']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label small" for="cnpj_<?php echo $pid; ?>">Aceita CNPJ (igreja paga)</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="idp_ativo" id="ativo_<?php echo $pid; ?>" <?php echo !$plano || !empty($plano['idp_ativo']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label small" for="ativo_<?php echo $pid; ?>">Ativo (aparece para o fiel)</label>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-salvar ms-auto"><?php echo $plano ? 'Salvar' : 'Criar plano'; ?></button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="card mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color);">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
            <h5 class="mb-0"><i class="fas fa-users me-2" style="color:#60a5fa;"></i>Assinantes</h5>
            <form method="GET" class="d-flex gap-2">
                <select name="status" class="form-select form-select-sm custom-input" data-enviar-ao-mudar>
                    <option value="">Todos os status</option>
                    <?php foreach ($status_info as $valor => $info) { ?><option value="<?php echo $valor; ?>" <?php echo $filtro_status === $valor ? 'selected' : ''; ?>><?php echo $info[0]; ?></option><?php } ?>
                </select>
            </form>
        </div>
        <div class="card-body p-0">
            <?php if (empty($assinaturas)) { ?>
                <p class="text-muted p-3 mb-0">Nenhuma assinatura ainda.</p>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Fiel</th>
                                <th>Plano</th>
                                <th>Status</th>
                                <th>Acesso até</th>
                                <th class="text-center">Pessoas</th>
                                <th class="pe-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assinaturas as $linha) {
                                $info = $status_info[$linha['idas_status']] ?? array($linha['idas_status'], 'bg-secondary');
                            ?>
                                <tr>
                                    <td class="ps-3"><?php echo htmlspecialchars($linha['idf_nome']); ?><br><small class="text-muted"><?php echo htmlspecialchars($linha['idf_email']); ?></small></td>
                                    <td><?php echo htmlspecialchars((string) ($linha['idp_nome'] ?? '—')); ?></td>
                                    <td><span class="badge <?php echo $info[1]; ?>"><?php echo htmlspecialchars($info[0]); ?></span></td>
                                    <td><?php echo assinaturas_data($linha['idas_acesso_ate']) ?: '—'; ?></td>
                                    <td class="text-center"><?php echo (int) $linha['beneficiarios']; ?></td>
                                    <td class="pe-3">
                                        <div class="d-flex flex-wrap gap-2 align-items-center">
                                            <form method="POST" class="d-flex gap-1">
                                                <?php echo csrf_campo(); ?>
                                                <input type="hidden" name="acao" value="cortesia">
                                                <input type="hidden" name="fiel_id" value="<?php echo (int) $linha['idf_id']; ?>">
                                                <input type="date" name="data" class="form-control form-control-sm custom-input" value="<?php echo assinaturas_data($linha['idas_acesso_ate'], 'Y-m-d'); ?>" title="Acesso até (vazio = remove)">
                                                <button type="submit" class="btn btn-sm btn-salvar" title="Salvar data de acesso"><i class="fas fa-check"></i></button>
                                            </form>
                                            <?php if (!empty($linha['idas_asaas_subscription_id']) && $linha['idas_status'] !== 'CANCELADA') { ?>
                                                <form method="POST" data-confirmar="Cancelar a assinatura no Asaas? Para de gerar cobranças; o acesso continua até o fim do período pago.">
                                                    <?php echo csrf_campo(); ?>
                                                    <input type="hidden" name="acao" value="cancelar">
                                                    <input type="hidden" name="fiel_id" value="<?php echo (int) $linha['idf_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancelar</button>
                                                </form>
                                            <?php } ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>

    <div class="card mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color);">
        <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
            <h5 class="mb-0"><i class="fas fa-gift me-2" style="color:#60a5fa;"></i>Dar acesso cortesia</h5>
            <small class="text-muted">Libera o conteúdo sem cobrança até a data escolhida (ex.: quem já usava o app antes do bloqueio).</small>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-2">
                <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="cortesia">
                <div class="col-md-6"><input type="email" name="email" class="form-control custom-input" placeholder="E-mail do fiel" required></div>
                <div class="col-md-4"><input type="date" name="data" class="form-control custom-input" required></div>
                <div class="col-md-2"><button type="submit" class="btn btn-salvar w-100">Liberar</button></div>
            </form>
        </div>
    </div>

    <?php } ?>

    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
