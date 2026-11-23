<?php
// Missões (T32): cadastro dos projetos missionários (qualquer admin) e,
// só para a equipe interna (superadmin), a lista das contribuições com os
// nomes reais e o lançamento de ofertas recebidas fora do app. A oferta
// geral (a oferta livre para manter o app) não aparece aqui: fica em
// Admin → Contribuições (admin_contribuicoes.php), só da equipe. Funções
// em "CONTRIBUIÇÃO EM MISSÕES" de php/conexao.php; tabelas nas seções 72,
// 77 e 78 do bancosupabase.md.
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));
// Ações que deram certo vão para o Registro de atividade (admin_log_ao_redirecionar()).
admin_log_ao_redirecionar($conn, 'missoes');

$mensagem = "";
$erro = "";
$hoje = site_hoje();
$admin_id = auth_resolve_fiel_id();
$superadmin = auth_eh_superadmin();
$disponivel = missoes_disponivel($conn);
$oferta_geral_id = missoes_oferta_geral_id();
$situacoes = array(
    'rascunho' => array('Rascunho', 'bg-secondary'),
    'aberto' => array('Recebendo', 'bg-success'),
    'encerrado' => array('Encerrado', 'bg-dark'),
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $disponivel) {
    $acao = site_entrada_texto($_POST['acao'] ?? '', false);
    $projeto_post = (int) ($_POST['idmp_id'] ?? 0);

    if ($projeto_post > 0 && $projeto_post === $oferta_geral_id) {
        // A oferta geral só muda em Admin → Contribuições (equipe interna).
        $erro = "Este projeto é a oferta geral: ele fica em Contribuições.";
    } elseif ($acao === 'salvar_projeto') {
        $salvo = missao_projeto_salvar($conn, array(
            'idmp_id' => $projeto_post,
            'titulo' => site_entrada_texto($_POST['titulo'] ?? ''),
            'local' => site_entrada_texto($_POST['local'] ?? ''),
            'descricao' => site_entrada_texto($_POST['descricao'] ?? ''),
            'meta' => site_entrada_texto($_POST['meta'] ?? ''),
            'data_fim' => site_entrada_texto($_POST['data_fim'] ?? ''),
            'status' => site_entrada_texto($_POST['status'] ?? ''),
            'mostrar_total' => isset($_POST['mostrar_total']),
            'mostrar_apoiadores' => isset($_POST['mostrar_apoiadores']),
            'mostrar_quadro' => isset($_POST['mostrar_quadro']),
            'mostrar_valores' => isset($_POST['mostrar_valores']),
        ), $admin_id);
        $erro = $salvo['erro'];
        if ($erro === '') {
            $_SESSION['missoes_ok'] = 'Projeto salvo.';
            header('Location: admin_missoes.php');
            exit;
        }
    } elseif ($acao === 'excluir_projeto') {
        $projeto = missao_projeto($conn, $projeto_post, true);
        $res = $projeto ? db_query($conn, "SELECT 1 FROM id_missao_contribuicao WHERE idmp_id = ? LIMIT 1", array((int) $projeto['idmp_id'])) : false;
        if (!$projeto) {
            $erro = "Projeto não encontrado.";
        } elseif (!$res || mysqli_num_rows($res) > 0) {
            $erro = "Este projeto já tem contribuições e não pode ser excluído. Mude a situação para Encerrado.";
        } elseif (db_query($conn, "DELETE FROM id_missao_projeto WHERE idmp_id = ?", array((int) $projeto['idmp_id']))) {
            if (!empty($projeto['idmp_capa_url'])) {
                site_excluir_arquivo_r2_orfao($projeto['idmp_capa_url']);
            }
            $_SESSION['missoes_ok'] = 'Projeto excluído.';
            header('Location: admin_missoes.php');
            exit;
        } else {
            $erro = site_erro_banco('Não foi possível excluir o projeto.');
        }
    } elseif ($acao === 'lancar_oferta' && $superadmin) {
        $erro = missao_lancar_oferta(
            $conn,
            $projeto_post,
            site_entrada_texto($_POST['valor'] ?? ''),
            site_entrada_texto($_POST['data'] ?? ''),
            site_entrada_texto($_POST['nome'] ?? ''),
            site_entrada_texto($_POST['recebido_por'] ?? ''),
            $admin_id
        );
        if ($erro === '') {
            $_SESSION['missoes_ok'] = 'Oferta lançada.';
            header('Location: admin_missoes.php#contribuicoes');
            exit;
        }
    } elseif ($acao === 'excluir_oferta' && $superadmin) {
        // Só as lançadas à mão, e fora da oferta geral: as do Asaas mudam
        // pelo webhook.
        // Cancela em vez de apagar (missao_oferta_manual_cancelar()).
        $erro_oferta = missao_oferta_manual_cancelar($conn, (int) ($_POST['idmc_id'] ?? 0), auth_resolve_fiel_id(), site_entrada_texto($_POST['motivo'] ?? '', false), null, $oferta_geral_id);
        if ($erro_oferta === '') {
            $_SESSION['missoes_ok'] = 'Oferta cancelada. Ela continua na lista e saiu dos totais.';
            header('Location: admin_missoes.php#contribuicoes');
            exit;
        }
        $erro = $erro_oferta;
    }
}
if (!empty($_SESSION['missoes_ok'])) {
    $mensagem = $_SESSION['missoes_ok'];
    unset($_SESSION['missoes_ok']);
}

// Só as missões: a oferta geral fica em Contribuições.
$projetos = array_values(array_filter($disponivel ? missoes_projetos($conn, true) : array(), function ($p) use ($oferta_geral_id) {
    return (int) $p['idmp_id'] !== $oferta_geral_id;
}));
$editar_id = (int) ($_GET['editar'] ?? 0);
$editando = null;
foreach ($projetos as $p) {
    if ((int) $p['idmp_id'] === $editar_id) {
        $editando = $p;
    }
}

$filtros = missao_contribuicoes_filtros($_GET);
$contribuicoes = $disponivel && $superadmin ? missao_contribuicoes_admin($conn, $filtros, 0, $oferta_geral_id) : null;

$eh_sandbox = strpos((string) ASAAS_API_URL, 'sandbox') !== false;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Missões</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'missoes', 'Missões', 'fa-earth-americas'); ?>

    <?php if ($mensagem !== "") { ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
    <?php } ?>
    <?php if ($erro !== "") { ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
    <?php } ?>

    <?php if (!$disponivel) { ?>
        <div class="alert alert-danger">As tabelas de missões ainda não existem no banco. Rode as seções 71 e 72 do <code>database/bancosupabase.md</code> no SQL Editor do Supabase.</div>
    <?php } else { ?>

    <div class="alert <?php echo $eh_sandbox ? 'alert-warning' : 'alert-info'; ?> small">
        <strong>Asaas:</strong>
        <?php if (!asaas_configurado()) { ?>
            não configurado — o fiel vê os projetos, mas o botão de contribuir fica indisponível.
        <?php } else { ?>
            <?php echo $eh_sandbox ? 'ambiente de TESTE (sandbox) — nenhuma contribuição é cobrada de verdade.' : 'PRODUÇÃO — contribuições reais.'; ?>
        <?php } ?>
        <?php if ($superadmin) { ?><br>A oferta geral (a oferta livre para manter o app) fica em <a href="admin_contribuicoes.php">Contribuições</a>.<?php } ?>
    </div>

    <div class="card mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color);">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
            <h5 class="mb-0"><i class="fas fa-folder-open me-2" style="color:#60a5fa;"></i>Projetos</h5>
            <?php if ($editando) { ?><a href="admin_missoes.php#formProjeto" class="btn btn-sm btn-outline-secondary"><i class="fas fa-plus me-1"></i>Novo projeto</a><?php } ?>
        </div>
        <div class="card-body p-0">
            <?php if (!$projetos) { ?>
                <p class="text-muted p-3 mb-0">Nenhum projeto ainda. Crie o primeiro abaixo.</p>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr><th class="ps-3">Projeto</th><th>Situação</th><th>Arrecadado</th><th class="text-center">Apoiadores</th><th>Encerra em</th><th>O fiel vê</th><th class="pe-3">Ações</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projetos as $p) {
                                $s = $situacoes[$p['situacao']];
                                $capa = missao_capa_url($p['idmp_capa_url'] ?? '');
                                $exibe = missao_exibicao($p);
                            ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($capa) { ?><img src="<?php echo htmlspecialchars($capa); ?>" alt="" style="width:56px;height:36px;object-fit:cover;border-radius:6px;"><?php } ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($p['idmp_titulo']); ?></strong>
                                                <?php if (!empty($p['idmp_local'])) { ?><br><small class="text-muted"><i class="fas fa-location-dot me-1"></i><?php echo htmlspecialchars($p['idmp_local']); ?></small><?php } ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge <?php echo $s[1]; ?>"><?php echo $s[0]; ?></span></td>
                                    <td>
                                        <?php echo assinatura_formatar_valor($p['arrecadado']); ?>
                                        <?php if (!empty($p['idmp_meta_centavos'])) { ?><small class="text-muted">de <?php echo assinatura_formatar_valor($p['idmp_meta_centavos']); ?> (<?php echo (int) $p['percentual']; ?>%)</small><?php } ?>
                                    </td>
                                    <td class="text-center"><?php echo (int) $p['apoiadores']; ?></td>
                                    <td><?php echo !empty($p['idmp_data_fim']) ? date('d/m/Y', strtotime($p['idmp_data_fim'])) : '—'; ?></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars(implode(', ', array_keys(array_filter(array('total' => $exibe['total'], 'apoiadores' => $exibe['apoiadores'], 'quadro' => $exibe['quadro'], 'valores' => $exibe['valores'])))) ?: 'só o convite'); ?></small></td>
                                    <td class="pe-3">
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="admin_missoes.php?editar=<?php echo (int) $p['idmp_id']; ?>#formProjeto" class="btn btn-sm btn-salvar"><i class="fas fa-pen me-1"></i>Editar</a>
                                            <?php if ($p['situacao'] !== 'rascunho') { ?>
                                                <a href="../fiel/missao.php?id=<?php echo (int) $p['idmp_id']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Ver como fiel</a>
                                            <?php } ?>
                                            <?php if ((int) $p['contribuicoes'] === 0) { ?>
                                                <form method="POST" data-confirmar="Excluir este projeto? Não dá para desfazer.">
                                                    <?php echo csrf_campo(); ?>
                                                    <input type="hidden" name="acao" value="excluir_projeto">
                                                    <input type="hidden" name="idmp_id" value="<?php echo (int) $p['idmp_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="fas fa-trash"></i></button>
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

    <div class="card mb-4" id="formProjeto" style="background: var(--bg-light); border: 1px solid var(--border-color);">
        <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
            <h5 class="mb-0"><i class="fas <?php echo $editando ? 'fa-pen' : 'fa-plus'; ?> me-2" style="color:#60a5fa;"></i><?php echo $editando ? 'Editar: ' . htmlspecialchars($editando['idmp_titulo']) : 'Novo projeto'; ?></h5>
            <small class="text-muted">Comece como <strong>Rascunho</strong> (só o admin vê) e mude para <strong>Recebendo</strong> quando estiver pronto. Na data de encerramento o projeto para de receber sozinho.</small>
        </div>
        <div class="card-body">
            <?php $mpf = array('projeto' => $editando, 'action' => 'admin_missoes.php', 'geral' => false); include __DIR__ . '/includes/missao_projeto_form.php'; ?>
        </div>
    </div>

    <?php if ($superadmin) { ?>
        <div class="card mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
                <h5 class="mb-0"><i class="fas fa-hand-holding-heart me-2" style="color:#f472b6;"></i>Lançar oferta recebida fora do app</h5>
                <small class="text-muted">Oferta do culto, Pix direto na conta etc. Soma na barra do projeto. Se a equipe recebeu, entra também no Financeiro. <i class="fas fa-lock ms-1" title="Restrito"></i></small>
            </div>
            <div class="card-body">
                <?php if (!$projetos) { ?>
                    <p class="text-muted mb-0">Crie um projeto antes.</p>
                <?php } else { ?>
                    <form method="POST" class="row g-2">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="lancar_oferta">
                        <div class="col-md-4">
                            <select name="idmp_id" class="form-select custom-input" required aria-label="Projeto">
                                <?php foreach ($projetos as $p) { ?><option value="<?php echo (int) $p['idmp_id']; ?>"><?php echo htmlspecialchars($p['idmp_titulo']); ?></option><?php } ?>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-2"><input type="text" name="valor" class="form-control custom-input" inputmode="decimal" placeholder="Valor (R$)" required aria-label="Valor"></div>
                        <div class="col-sm-6 col-md-2"><input type="date" name="data" class="form-control custom-input" value="<?php echo $hoje; ?>" max="<?php echo $hoje; ?>" required aria-label="Data"></div>
                        <div class="col-sm-6 col-md-2">
                            <select name="recebido_por" class="form-select custom-input" aria-label="Quem recebeu">
                                <option value="equipe">Equipe recebeu</option>
                                <option value="igreja">Igreja recebeu</option>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-2"><button type="submit" class="btn btn-salvar w-100">Lançar</button></div>
                        <div class="col-12"><input type="text" name="nome" class="form-control custom-input" maxlength="100" placeholder="Nome no quadro de apoiadores (opcional, ex.: Oferta do culto de domingo)" aria-label="Nome no quadro"></div>
                    </form>
                <?php } ?>
            </div>
        </div>

        <?php $cl = array('pagina' => 'admin_missoes.php', 'filtros' => $filtros, 'dados' => $contribuicoes, 'projetos' => $projetos); include __DIR__ . '/includes/contribuicoes_lista.php'; ?>
    <?php } ?>

    <?php } ?>

    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
