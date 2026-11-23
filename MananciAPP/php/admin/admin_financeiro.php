<?php
// Financeiro da equipe: o que entrou pelo Asaas (assinaturas, missões e
// contribuições da oferta geral), custos e outras receitas lançados à mão,
// lucro do período, parte da igreja e repasses. Superadmin vê tudo; um
// tipo de admin com a tela Financeiro vê só as partes marcadas nele
// (financeiro_pode_ver()). Regras do cálculo em docs/interno/financeiro.md;
// funções em "FINANCEIRO DA EQUIPE" de php/conexao.php; tabelas na seção 71
// do bancosupabase.md.
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

// Barra quem não é superadmin nem tem a tela no tipo (admin_pode_abrir_script).
auth_require(array('admin'));
// Ações que deram certo vão para o Registro de atividade (admin_log_ao_redirecionar()).
admin_log_ao_redirecionar($conn, 'financeiro');

$mensagem = "";
$erro = "";
$hoje = site_hoje();
$admin_id = auth_resolve_fiel_id();
$disponivel = financeiro_disponivel($conn);
$pode = array();
foreach (financeiro_partes() as $parte => $rotulo) {
    $pode[$parte] = financeiro_pode_ver($parte);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $disponivel) {
    $acao = site_entrada_texto($_POST['acao'] ?? '', false);
    $voltar = 'admin_financeiro.php' . (($_POST['voltar'] ?? '') !== '' ? '?' . preg_replace('/[^a-zA-Z0-9=&_-]/', '', (string) $_POST['voltar']) : '');
    $acao_parte = array('configuracao' => 'regras', 'lancar' => 'lancamentos', 'excluir_lancamento' => 'lancamentos', 'sincronizar_taxas' => 'origens');

    if (isset($acao_parte[$acao]) && !$pode[$acao_parte[$acao]] && !($acao === 'sincronizar_taxas' && $pode['resumo'])) {
        $erro = "Seu tipo de admin não permite essa ação no Financeiro.";
    } elseif ($acao === 'configuracao') {
        $percentual = (string) min(100, max(0, (int) ($_POST['percentual_igreja'] ?? 50)));
        $no_lucro = ($_POST['missoes_no_lucro'] ?? '1') === '0' ? '0' : '1';
        if (site_config_salvar($conn, 'financeiro_percentual_igreja', $percentual)
            && site_config_salvar($conn, 'financeiro_missoes_no_lucro', $no_lucro)) {
            $_SESSION['financeiro_ok'] = 'Divisão salva.';
            header('Location: ' . $voltar);
            exit;
        }
        $erro = "Não foi possível salvar. Confira se a seção 43 do bancosupabase.md já rodou.";
    } elseif ($acao === 'lancar') {
        $erro = financeiro_lancamento_salvar($conn, array(
            'tipo' => site_entrada_texto($_POST['tipo'] ?? ''),
            'destino' => site_entrada_texto($_POST['destino'] ?? ''),
            'categoria' => site_entrada_texto($_POST['categoria'] ?? ''),
            'descricao' => site_entrada_texto($_POST['descricao'] ?? ''),
            'valor' => site_entrada_texto($_POST['valor'] ?? ''),
            'data' => site_entrada_texto($_POST['data'] ?? ''),
            'projeto_id' => (int) ($_POST['projeto_id'] ?? 0),
        ), $admin_id);
        if ($erro === '') {
            $_SESSION['financeiro_ok'] = 'Lançamento registrado.';
            header('Location: ' . $voltar . '#lancamentos');
            exit;
        }
    } elseif ($acao === 'excluir_lancamento') {
        // Cancela (fica riscado, com o motivo, e sai dos totais) em vez de
        // apagar: ver financeiro_lancamento_cancelar().
        $erro = financeiro_lancamento_cancelar($conn, (int) ($_POST['idfl_id'] ?? 0), auth_resolve_fiel_id(), site_entrada_texto($_POST['motivo'] ?? '', false));
        if ($erro === '') {
            $_SESSION['financeiro_ok'] = financeiro_cancelamento_disponivel($conn) ? 'Lançamento cancelado. Ele continua na lista, riscado, e saiu dos totais.' : 'Lançamento excluído.';
            header('Location: ' . $voltar . '#lancamentos');
            exit;
        }
    } elseif ($acao === 'sincronizar_taxas') {
        $atualizadas = financeiro_sincronizar_taxas($conn);
        $_SESSION['financeiro_ok'] = $atualizadas > 0 ? "Taxas atualizadas em $atualizadas cobrança(s)." : 'Nenhuma cobrança precisou de atualização (ou o Asaas não respondeu).';
        header('Location: ' . $voltar);
        exit;
    }
}
if (!empty($_SESSION['financeiro_ok'])) {
    $mensagem = $_SESSION['financeiro_ok'];
    unset($_SESSION['financeiro_ok']);
}

// Período: mês (padrão), ano, desde o começo ou intervalo escolhido.
$periodo = in_array($_GET['periodo'] ?? '', array('mes', 'ano', 'tudo', 'intervalo'), true) ? $_GET['periodo'] : 'mes';
$mes = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', site_entrada_texto($_GET['mes'] ?? '', false)) ? $_GET['mes'] : substr($hoje, 0, 7);
$ano = preg_match('/^\d{4}$/', site_entrada_texto($_GET['ano'] ?? '', false)) ? (int) $_GET['ano'] : (int) substr($hoje, 0, 4);
$data_valida = function ($valor) {
    $d = DateTime::createFromFormat('Y-m-d', (string) $valor);
    return $d && $d->format('Y-m-d') === $valor;
};
if ($periodo === 'ano') {
    $inicio = $ano . '-01-01';
    $fim = $ano . '-12-31';
    $periodo_texto = 'Ano de ' . $ano;
} elseif ($periodo === 'tudo') {
    $inicio = $disponivel ? financeiro_primeiro_dia($conn) : $hoje;
    $fim = $hoje;
    $periodo_texto = 'Desde o começo';
} elseif ($periodo === 'intervalo') {
    // Recém-escolhido (ainda sem datas) ou datas inválidas: do dia 1º até hoje.
    $tem_datas = $data_valida($_GET['de'] ?? '') && $data_valida($_GET['ate'] ?? '') && $_GET['de'] <= $_GET['ate'];
    $inicio = $tem_datas ? $_GET['de'] : substr($hoje, 0, 8) . '01';
    $fim = $tem_datas ? $_GET['ate'] : $hoje;
    $periodo_texto = date('d/m/Y', strtotime($inicio)) . ' a ' . date('d/m/Y', strtotime($fim));
} else {
    $periodo = 'mes';
    $inicio = $mes . '-01';
    $fim = date('Y-m-t', strtotime($inicio));
    $nomes_meses = array('', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro');
    $periodo_texto = $nomes_meses[(int) substr($mes, 5, 2)] . ' de ' . substr($mes, 0, 4);
}
$query_periodo = http_build_query(array_filter(array('periodo' => $periodo, 'mes' => $periodo === 'mes' ? $mes : null, 'ano' => $periodo === 'ano' ? $ano : null, 'de' => $periodo === 'intervalo' ? $inicio : null, 'ate' => $periodo === 'intervalo' ? $fim : null)));

// Exportação: todas as entradas e saídas do período, para planilha
// (";" e vírgula decimal, como o Excel em português espera).
if ($disponivel && $pode['exportar'] && isset($_GET['csv'])) {
    $linhas = financeiro_extrato($conn, $inicio, $fim);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="financeiro_' . $inicio . '_a_' . $fim . '.csv"');
    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, array('Data', 'Tipo', 'Origem', 'Descrição', 'Forma', 'Bruto', 'Taxa', 'Líquido'), ';');
    $centavos = function ($c) {
        return number_format(((int) $c) / 100, 2, ',', '');
    };
    foreach ($linhas as $l) {
        // Texto começando com = + - @ (ou tab/CR) viraria fórmula na planilha.
        $descricao = preg_match('/^[=+\-@\t\r]/', $l['descricao']) ? "'" . $l['descricao'] : $l['descricao'];
        fputcsv($saida, array(date('d/m/Y', strtotime($l['data'])), $l['tipo'], $l['origem'], $descricao, $l['forma'], $centavos($l['bruto']), $centavos($l['bruto'] - $l['liquido']), $centavos($l['liquido'])), ';');
    }
    fclose($saida);
    exit;
}

$meses = $disponivel ? financeiro_movimentos_por_mes($conn, $inicio, $fim) : array();
$total = financeiro_somar($meses);
$percentual = financeiro_percentual_igreja();
$no_lucro = financeiro_missoes_no_lucro();
$divisao = financeiro_divisao($total, $percentual, $no_lucro);
$divisao_outro_modo = financeiro_divisao($total, $percentual, !$no_lucro);
$saldo = $disponivel ? financeiro_saldo_igreja($conn) : array('devido' => 0, 'repassado' => 0, 'saldo' => 0, 'caixa_missoes' => null);
$lancamentos = $disponivel ? financeiro_lancamentos($conn, $inicio, $fim) : array();
$projetos = missoes_disponivel($conn) ? missoes_projetos($conn, true) : array();
$categorias = financeiro_categorias();
$sem_taxa = $total['assinaturas_sem_taxa'] + $total['missoes_sem_taxa'] + $total['contribuicoes_sem_taxa'];

// "R$ 1.234,56" / "-R$ 10,00".
function financeiro_moeda($centavos) {
    $centavos = (int) $centavos;
    return ($centavos < 0 ? '-' : '') . assinatura_formatar_valor(abs($centavos));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Financeiro</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'financeiro', 'Financeiro', 'fa-sack-dollar'); ?>

    <?php if ($mensagem !== "") { ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
    <?php } ?>
    <?php if ($erro !== "") { ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
    <?php } ?>

    <?php if (!$disponivel) { ?>
        <div class="alert alert-danger">As tabelas do Financeiro ainda não existem no banco. Rode a seção 71 do <code>database/bancosupabase.md</code> no SQL Editor do Supabase (e a 72 para as missões).</div>
    <?php } else { ?>

    <?php if (auth_eh_superadmin()) { ?>
        <p class="text-muted small mb-3"><i class="fas fa-lock me-1"></i>A equipe interna (e-mails em <code>SUPERADMIN_EMAILS</code> no Render) vê tudo. Para liberar partes desta tela a outro admin, marque Financeiro no tipo dele em <a href="admin_gerenciar_permissoes.php#grupos">Tipos de Admin</a>.</p>
    <?php } elseif (!in_array(true, $pode, true)) { ?>
        <div class="alert alert-info"><i class="fas fa-lock me-2"></i>Seu tipo de admin tem a tela Financeiro, mas nenhuma parte dela liberada. Peça a quem cuida das permissões para liberar as partes.</div>
    <?php } ?>

    <form method="GET" class="financeiro-periodo mb-4">
        <div class="btn-group flex-wrap" role="group" aria-label="Período">
            <?php foreach (array('mes' => 'Mês', 'ano' => 'Ano', 'tudo' => 'Desde o começo', 'intervalo' => 'Intervalo') as $valor => $rotulo) { ?>
                <input type="radio" class="btn-check" name="periodo" id="periodo_<?php echo $valor; ?>" value="<?php echo $valor; ?>" <?php echo $periodo === $valor ? 'checked' : ''; ?> data-enviar-ao-mudar>
                <label class="btn btn-sm btn-outline-primary" for="periodo_<?php echo $valor; ?>"><?php echo $rotulo; ?></label>
            <?php } ?>
        </div>
        <?php if ($periodo === 'mes') { ?>
            <input type="month" name="mes" class="form-control form-control-sm custom-input" value="<?php echo htmlspecialchars($mes); ?>" data-enviar-ao-mudar aria-label="Mês">
        <?php } elseif ($periodo === 'ano') { ?>
            <input type="number" name="ano" min="2020" max="2100" class="form-control form-control-sm custom-input" value="<?php echo $ano; ?>" data-enviar-ao-mudar aria-label="Ano">
        <?php } elseif ($periodo === 'intervalo') { ?>
            <input type="date" name="de" class="form-control form-control-sm custom-input" value="<?php echo htmlspecialchars($inicio); ?>" aria-label="De">
            <input type="date" name="ate" class="form-control form-control-sm custom-input" value="<?php echo htmlspecialchars($fim); ?>" aria-label="Até">
            <button type="submit" class="btn btn-sm btn-salvar">Ver</button>
        <?php } ?>
        <span class="financeiro-periodo-texto"><?php echo htmlspecialchars($periodo_texto); ?></span>
        <?php if ($pode['exportar']) { ?>
            <a href="admin_financeiro.php?<?php echo htmlspecialchars($query_periodo); ?>&amp;csv=1" class="btn btn-sm btn-outline-secondary ms-auto"><i class="fas fa-file-csv me-1"></i>Exportar planilha</a>
        <?php } ?>
    </form>

    <?php if ($pode['resumo']) { ?>
    <div class="ofensiva-stats mb-4">
        <div class="ofensiva-stat">
            <i class="fas fa-arrow-down" style="color:#22c55e;"></i>
            <strong><?php echo financeiro_moeda($divisao['bruto']); ?></strong>
            <small>Entrou (bruto)</small>
        </div>
        <div class="ofensiva-stat">
            <i class="fas fa-percent" style="color:#f59e0b;"></i>
            <strong><?php echo financeiro_moeda($divisao['taxas']); ?></strong>
            <small>Taxas do Asaas</small>
        </div>
        <div class="ofensiva-stat">
            <i class="fas fa-arrow-up" style="color:#ef4444;"></i>
            <strong><?php echo financeiro_moeda($divisao['despesas']); ?></strong>
            <small><?php echo $no_lucro ? 'Custos e repasses a missões' : 'Custos'; ?></small>
        </div>
        <div class="ofensiva-stat">
            <i class="fas fa-scale-balanced" style="color:<?php echo $divisao['lucro'] >= 0 ? '#60a5fa' : '#ef4444'; ?>;"></i>
            <strong><?php echo financeiro_moeda($divisao['lucro']); ?></strong>
            <small><?php echo $divisao['lucro'] >= 0 ? 'Lucro' : 'Prejuízo'; ?></small>
        </div>
    </div>

    <?php } ?>

    <?php if ($sem_taxa > 0 && ($pode['resumo'] || $pode['origens'])) { ?>
        <div class="alert alert-warning small d-flex flex-wrap align-items-center gap-2">
            <span><i class="fas fa-circle-info me-1"></i><?php echo (int) $sem_taxa; ?> cobrança(s) do período sem a taxa do Asaas gravada (anteriores à seção 71): estão contadas pelo valor bruto.</span>
            <form method="POST" class="ms-auto">
                <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="sincronizar_taxas">
                <input type="hidden" name="voltar" value="<?php echo htmlspecialchars($query_periodo); ?>">
                <button type="submit" class="btn btn-sm btn-outline-dark"><i class="fas fa-rotate me-1"></i>Buscar taxas no Asaas</button>
            </form>
        </div>
    <?php } ?>

    <?php if ($pode['resumo']) { ?>
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card h-100" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
                    <h5 class="mb-0"><i class="fas fa-handshake me-2" style="color:#60a5fa;"></i>Divisão do lucro</h5>
                    <small class="text-muted"><?php echo htmlspecialchars($periodo_texto); ?> · igreja <?php echo $percentual; ?>% · equipe <?php echo 100 - $percentual; ?>%</small>
                </div>
                <div class="card-body">
                    <div class="financeiro-divisao">
                        <div>
                            <small>Equipe (<?php echo 100 - $percentual; ?>%)</small>
                            <strong><?php echo financeiro_moeda($divisao['parte_equipe']); ?></strong>
                        </div>
                        <div>
                            <small>Igreja (<?php echo $percentual; ?>%)</small>
                            <strong><?php echo financeiro_moeda($divisao['parte_igreja']); ?></strong>
                        </div>
                    </div>
                    <?php if ($divisao['lucro'] > 0) { ?>
                        <div class="financeiro-barra" role="img" aria-label="Equipe <?php echo 100 - $percentual; ?>%, igreja <?php echo $percentual; ?>%">
                            <span class="is-equipe" style="width: <?php echo 100 - $percentual; ?>%;"></span>
                            <span class="is-igreja" style="width: <?php echo $percentual; ?>%;"></span>
                        </div>
                    <?php } elseif ($divisao['lucro'] < 0) { ?>
                        <p class="small text-muted mb-2">Período com prejuízo: a igreja não recebe e a equipe absorve o resultado.</p>
                    <?php } ?>
                    <p class="small mb-1">
                        <strong>Modo:</strong>
                        <?php echo $no_lucro ? 'missões entram no lucro dividido.' : 'missões ficam num caixa separado.'; ?>
                    </p>
                    <?php if (!$no_lucro) { ?>
                        <p class="small mb-1"><strong>Caixa de missões no período:</strong> entrou <?php echo financeiro_moeda($divisao['missoes_entrada']); ?>, repassado <?php echo financeiro_moeda($total['repasses_missao']); ?>, sobra <?php echo financeiro_moeda($divisao['caixa_missoes']); ?>.</p>
                    <?php } ?>
                    <p class="small text-muted mb-0">
                        No outro modo (<?php echo $no_lucro ? 'missões separadas' : 'missões no lucro'; ?>), o lucro do período seria
                        <?php echo financeiro_moeda($divisao_outro_modo['lucro']); ?>
                        (igreja <?php echo financeiro_moeda($divisao_outro_modo['parte_igreja']); ?>, equipe <?php echo financeiro_moeda($divisao_outro_modo['parte_equipe']); ?>)<?php echo !$no_lucro ? '' : ', e o caixa de missões ' . financeiro_moeda($divisao_outro_modo['caixa_missoes']); ?>.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
                    <h5 class="mb-0"><i class="fas fa-church me-2" style="color:#60a5fa;"></i>Repasses à igreja (desde o começo)</h5>
                    <small class="text-muted">A parte de cada mês é calculada sobre o lucro daquele mês; mês com prejuízo não desconta dos outros.</small>
                </div>
                <div class="card-body">
                    <dl class="financeiro-saldo mb-3">
                        <div><dt>Parte da igreja acumulada</dt><dd><?php echo financeiro_moeda($saldo['devido']); ?></dd></div>
                        <div><dt>Já repassado</dt><dd><?php echo financeiro_moeda($saldo['repassado']); ?></dd></div>
                        <div class="is-destaque"><dt><?php echo $saldo['saldo'] >= 0 ? 'Falta repassar' : 'Repassado a mais'; ?></dt><dd><?php echo financeiro_moeda(abs($saldo['saldo'])); ?></dd></div>
                        <?php if ($saldo['caixa_missoes'] !== null) { ?>
                            <div><dt>Caixa de missões (a repassar aos projetos)</dt><dd><?php echo financeiro_moeda($saldo['caixa_missoes']); ?></dd></div>
                        <?php } ?>
                    </dl>
                    <p class="small text-muted mb-0">Ao transferir para a igreja, lance abaixo como <strong>Repasse → Igreja</strong>. O cálculo usa a porcentagem e o modo atuais para todos os meses.</p>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>

    <?php if ($pode['origens']) { ?>
    <div class="card mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color);">
        <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
            <h5 class="mb-0"><i class="fas fa-list me-2" style="color:#60a5fa;"></i>De onde veio e para onde foi</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th class="ps-3">Origem</th><th class="text-end">Qtd.</th><th class="text-end">Bruto</th><th class="text-end">Taxa</th><th class="text-end pe-3">Líquido</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="ps-3"><i class="fas fa-crown me-2 text-warning"></i>Assinaturas</td>
                            <td class="text-end"><?php echo (int) $total['assinaturas_qtd']; ?></td>
                            <td class="text-end"><?php echo financeiro_moeda($total['assinaturas_bruto']); ?></td>
                            <td class="text-end"><?php echo financeiro_moeda($total['assinaturas_bruto'] - $total['assinaturas_liquido']); ?></td>
                            <td class="text-end pe-3"><?php echo financeiro_moeda($total['assinaturas_liquido']); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-3"><i class="fas fa-earth-americas me-2" style="color:#f472b6;"></i>Missões pelo app</td>
                            <td class="text-end"><?php echo (int) $total['missoes_qtd']; ?></td>
                            <td class="text-end"><?php echo financeiro_moeda($total['missoes_bruto']); ?></td>
                            <td class="text-end"><?php echo financeiro_moeda($total['missoes_bruto'] - $total['missoes_liquido']); ?></td>
                            <td class="text-end pe-3"><?php echo financeiro_moeda($total['missoes_liquido']); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-3"><i class="fas fa-hand-holding-heart me-2" style="color:#f472b6;"></i>Ofertas de missão recebidas pela equipe (fora do app)</td>
                            <td class="text-end">—</td>
                            <td class="text-end"><?php echo financeiro_moeda($total['missoes_manual_equipe']); ?></td>
                            <td class="text-end">—</td>
                            <td class="text-end pe-3"><?php echo financeiro_moeda($total['missoes_manual_equipe']); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-3"><i class="fas fa-hand-holding-dollar me-2" style="color:#34d399;"></i>Contribuições pelo app <small class="text-muted">(oferta geral, aba Contribuir)</small></td>
                            <td class="text-end"><?php echo (int) $total['contribuicoes_qtd']; ?></td>
                            <td class="text-end"><?php echo financeiro_moeda($total['contribuicoes_bruto']); ?></td>
                            <td class="text-end"><?php echo financeiro_moeda($total['contribuicoes_bruto'] - $total['contribuicoes_liquido']); ?></td>
                            <td class="text-end pe-3"><?php echo financeiro_moeda($total['contribuicoes_liquido']); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-3"><i class="fas fa-hand-holding-dollar me-2" style="color:#34d399;"></i>Contribuições recebidas pela equipe (fora do app)</td>
                            <td class="text-end">—</td>
                            <td class="text-end"><?php echo financeiro_moeda($total['contribuicoes_manual_equipe']); ?></td>
                            <td class="text-end">—</td>
                            <td class="text-end pe-3"><?php echo financeiro_moeda($total['contribuicoes_manual_equipe']); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-3"><i class="fas fa-circle-plus me-2 text-success"></i>Outras receitas</td>
                            <td class="text-end">—</td>
                            <td class="text-end"><?php echo financeiro_moeda($total['receitas']); ?></td>
                            <td class="text-end">—</td>
                            <td class="text-end pe-3"><?php echo financeiro_moeda($total['receitas']); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-3"><i class="fas fa-circle-minus me-2 text-danger"></i>Custos</td>
                            <td class="text-end">—</td><td class="text-end">—</td><td class="text-end">—</td>
                            <td class="text-end pe-3">-<?php echo financeiro_moeda($total['custos']); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-3"><i class="fas fa-share me-2 text-danger"></i>Repasses a projetos de missão</td>
                            <td class="text-end">—</td><td class="text-end">—</td><td class="text-end">—</td>
                            <td class="text-end pe-3">-<?php echo financeiro_moeda($total['repasses_missao']); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-3"><i class="fas fa-church me-2 text-muted"></i>Repasses à igreja (parte dela, não reduz o lucro)</td>
                            <td class="text-end">—</td><td class="text-end">—</td><td class="text-end">—</td>
                            <td class="text-end pe-3"><?php echo financeiro_moeda($total['repasses_igreja']); ?></td>
                        </tr>
                        <?php if ($total['missoes_manual_igreja'] > 0) { ?>
                            <tr class="text-muted">
                                <td class="ps-3"><i class="fas fa-circle-info me-2"></i>Ofertas de missão que a igreja recebeu direto (só na barra do projeto, fora do caixa da equipe)</td>
                                <td class="text-end">—</td>
                                <td class="text-end"><?php echo financeiro_moeda($total['missoes_manual_igreja']); ?></td>
                                <td class="text-end">—</td><td class="text-end pe-3">—</td>
                            </tr>
                        <?php } ?>
                        <?php if ($total['contribuicoes_manual_igreja'] > 0) { ?>
                            <tr class="text-muted">
                                <td class="ps-3"><i class="fas fa-circle-info me-2"></i>Contribuições que a igreja recebeu direto (fora do caixa da equipe)</td>
                                <td class="text-end">—</td>
                                <td class="text-end"><?php echo financeiro_moeda($total['contribuicoes_manual_igreja']); ?></td>
                                <td class="text-end">—</td><td class="text-end pe-3">—</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php } ?>

    <?php if ($pode['resumo'] && count($meses) > 1) { ?>
        <div class="card mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
                <h5 class="mb-0"><i class="fas fa-calendar-days me-2" style="color:#60a5fa;"></i>Mês a mês</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr><th class="ps-3">Mês</th><th class="text-end">Receita líquida</th><th class="text-end">Despesas</th><th class="text-end">Lucro</th><th class="text-end">Igreja</th><th class="text-end">Equipe</th><th class="text-end pe-3">Repassado à igreja</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($meses, true) as $chave_mes => $linha) {
                                $d = financeiro_divisao($linha, $percentual, $no_lucro);
                            ?>
                                <tr>
                                    <td class="ps-3"><a href="admin_financeiro.php?periodo=mes&amp;mes=<?php echo $chave_mes; ?>"><?php echo date('m/Y', strtotime($chave_mes . '-01')); ?></a></td>
                                    <td class="text-end"><?php echo financeiro_moeda($d['receita']); ?></td>
                                    <td class="text-end"><?php echo financeiro_moeda($d['despesas']); ?></td>
                                    <td class="text-end <?php echo $d['lucro'] < 0 ? 'text-danger' : ''; ?>"><?php echo financeiro_moeda($d['lucro']); ?></td>
                                    <td class="text-end"><?php echo financeiro_moeda($d['parte_igreja']); ?></td>
                                    <td class="text-end"><?php echo financeiro_moeda($d['parte_equipe']); ?></td>
                                    <td class="text-end pe-3"><?php echo financeiro_moeda($linha['repasses_igreja']); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php } ?>

    <?php if ($pode['lancamentos']) { ?>
    <div class="card mb-4" id="lancamentos" style="background: var(--bg-light); border: 1px solid var(--border-color);">
        <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
            <h5 class="mb-0"><i class="fas fa-pen-to-square me-2" style="color:#60a5fa;"></i>Lançamentos manuais</h5>
            <small class="text-muted">O que não passa pelo Asaas: custos (Render, Supabase, domínio…), outras receitas (ajudas, doações) e os repasses feitos à igreja ou aos projetos de missão.</small>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-2 mb-4" id="formLancamento">
                <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="lancar">
                <input type="hidden" name="voltar" value="<?php echo htmlspecialchars($query_periodo); ?>">
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label small mb-0" for="lanc_tipo">Tipo</label>
                    <select name="tipo" id="lanc_tipo" class="form-select form-select-sm custom-input" required>
                        <option value="custo">Custo</option>
                        <option value="receita">Outra receita</option>
                        <option value="repasse">Repasse</option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2" data-so-repasse hidden>
                    <label class="form-label small mb-0" for="lanc_destino">Para</label>
                    <select name="destino" id="lanc_destino" class="form-select form-select-sm custom-input">
                        <option value="igreja">Igreja (parte do lucro)</option>
                        <option value="missao">Projeto de missão</option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3" data-so-missao hidden>
                    <label class="form-label small mb-0" for="lanc_projeto">Projeto</label>
                    <select name="projeto_id" id="lanc_projeto" class="form-select form-select-sm custom-input">
                        <option value="0">Nenhum em especial</option>
                        <?php foreach ($projetos as $p) { ?>
                            <option value="<?php echo (int) $p['idmp_id']; ?>"><?php echo htmlspecialchars($p['idmp_titulo']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label small mb-0" for="lanc_categoria">Categoria</label>
                    <input type="text" name="categoria" id="lanc_categoria" class="form-control form-control-sm custom-input" maxlength="40" list="lanc_categorias" required placeholder="Ex.: Hospedagem (Render)">
                    <datalist id="lanc_categorias">
                        <?php foreach ($categorias as $tipo => $lista) { foreach ($lista as $c) { ?>
                            <option value="<?php echo htmlspecialchars($c); ?>" data-tipo="<?php echo $tipo; ?>"></option>
                        <?php } } ?>
                    </datalist>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label small mb-0" for="lanc_valor">Valor (R$)</label>
                    <input type="text" name="valor" id="lanc_valor" class="form-control form-control-sm custom-input" inputmode="decimal" required placeholder="0,00">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label small mb-0" for="lanc_data">Data</label>
                    <input type="date" name="data" id="lanc_data" class="form-control form-control-sm custom-input" required value="<?php echo $hoje; ?>">
                </div>
                <div class="col-lg">
                    <label class="form-label small mb-0" for="lanc_descricao">Descrição (opcional)</label>
                    <input type="text" name="descricao" id="lanc_descricao" class="form-control form-control-sm custom-input" maxlength="200" placeholder="Ex.: plano Starter de outubro">
                </div>
                <div class="col-12 col-lg-auto d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-salvar w-100"><i class="fas fa-plus me-1"></i>Lançar</button>
                </div>
            </form>
            <script nonce="<?php echo site_csp_nonce(); ?>">
                (function () {
                    var tipo = document.getElementById('lanc_tipo');
                    var destino = document.getElementById('lanc_destino');
                    var categoria = document.getElementById('lanc_categoria');
                    var opcoes = Array.prototype.slice.call(document.querySelectorAll('#lanc_categorias option'));
                    function atualizar() {
                        var repasse = tipo.value === 'repasse';
                        document.querySelector('[data-so-repasse]').hidden = !repasse;
                        document.querySelector('[data-so-missao]').hidden = !(repasse && destino.value === 'missao');
                        // Sugestões da categoria só do tipo escolhido.
                        opcoes.forEach(function (o) { o.disabled = o.getAttribute('data-tipo') !== tipo.value; });
                        categoria.placeholder = tipo.value === 'custo' ? 'Ex.: Hospedagem (Render)' : (repasse ? 'Ex.: Divisão do lucro' : 'Ex.: Ajuda');
                    }
                    tipo.addEventListener('change', atualizar);
                    destino.addEventListener('change', atualizar);
                    atualizar();
                })();
            </script>

            <?php if (!$lancamentos) { ?>
                <p class="text-muted mb-0">Nenhum lançamento neste período.</p>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr><th>Data</th><th>Tipo</th><th>Categoria</th><th>Descrição</th><th class="text-end">Valor</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lancamentos as $l) {
                                $tipo_texto = $l['idfl_tipo'] === 'custo' ? 'Custo' : ($l['idfl_tipo'] === 'receita' ? 'Receita' : ($l['idfl_destino'] === 'missao' ? 'Repasse → missão' : 'Repasse → igreja'));
                                $classe = $l['idfl_tipo'] === 'receita' ? 'bg-success' : ($l['idfl_tipo'] === 'custo' ? 'bg-danger' : 'bg-primary');
                            ?>
                                <?php $cancelado = !empty($l['idfl_cancelado_em']); ?>
                                <tr<?php echo $cancelado ? ' class="text-muted"' : ''; ?>>
                                    <td><?php echo date('d/m/Y', strtotime($l['idfl_data'])); ?></td>
                                    <td><span class="badge <?php echo $cancelado ? 'bg-secondary' : $classe; ?>"><?php echo $cancelado ? 'Cancelado' : $tipo_texto; ?></span></td>
                                    <td><?php echo htmlspecialchars($l['idfl_categoria']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars((string) ($l['idfl_descricao'] ?? '')); ?><?php if (!empty($l['projeto_titulo'])) { ?> <small class="text-muted">(<?php echo htmlspecialchars($l['projeto_titulo']); ?>)</small><?php } ?>
                                        <?php if ($cancelado) { ?>
                                            <div class="small">Cancelado em <?php echo date('d/m/Y H:i', strtotime($l['idfl_cancelado_em'])); ?>: <?php echo htmlspecialchars((string) ($l['idfl_cancelado_motivo'] ?? '')); ?></div>
                                        <?php } ?>
                                    </td>
                                    <td class="text-end"><?php echo $cancelado ? '<s>' . financeiro_moeda($l['idfl_valor_centavos']) . '</s>' : financeiro_moeda($l['idfl_valor_centavos']); ?></td>
                                    <td class="text-end">
                                        <?php if (!$cancelado) { ?>
                                        <form method="POST" class="d-flex gap-1 justify-content-end" data-confirmar="Cancelar este lançamento? Ele continua na lista, riscado, e sai dos totais.">
                                            <?php echo csrf_campo(); ?>
                                            <input type="hidden" name="acao" value="excluir_lancamento">
                                            <input type="hidden" name="idfl_id" value="<?php echo (int) $l['idfl_id']; ?>">
                                            <input type="hidden" name="voltar" value="<?php echo htmlspecialchars($query_periodo); ?>">
                                            <?php if (financeiro_cancelamento_disponivel($conn)) { ?>
                                                <input type="text" name="motivo" class="form-control form-control-sm" style="max-width: 180px;" placeholder="Motivo" maxlength="200" required aria-label="Motivo do cancelamento">
                                            <?php } ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar lançamento" aria-label="Cancelar lançamento"><i class="fas fa-ban" aria-hidden="true"></i></button>
                                        </form>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

    <?php if ($pode['regras']) { ?>
    <div class="card mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color);">
        <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
            <h5 class="mb-0"><i class="fas fa-sliders me-2" style="color:#60a5fa;"></i>Regras da divisão</h5>
            <small class="text-muted">Vale na hora para todos os períodos (inclusive o "falta repassar").</small>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3 align-items-end">
                <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="configuracao">
                <input type="hidden" name="voltar" value="<?php echo htmlspecialchars($query_periodo); ?>">
                <div class="col-sm-4 col-lg-2">
                    <label class="form-label" for="percentual_igreja"><strong>Parte da igreja (%)</strong></label>
                    <input type="number" min="0" max="100" name="percentual_igreja" id="percentual_igreja" class="form-control custom-input" value="<?php echo $percentual; ?>">
                </div>
                <div class="col-sm-8 col-lg-7">
                    <span class="form-label d-block"><strong>Dinheiro de missões</strong></span>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="missoes_no_lucro" id="missoes_no_lucro_1" value="1" <?php echo $no_lucro ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="missoes_no_lucro_1">Entra no lucro dividido (repasses a projetos contam como despesa)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="missoes_no_lucro" id="missoes_no_lucro_0" value="0" <?php echo !$no_lucro ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="missoes_no_lucro_0">Fica separado (caixa de missões, repassado aos projetos)</label>
                    </div>
                </div>
                <div class="col-lg-3">
                    <button type="submit" class="btn btn-salvar w-100"><i class="fas fa-save me-1"></i>Salvar regras</button>
                </div>
            </form>
            <small class="text-muted d-block mt-2">As contribuições (oferta geral, para manter o app) entram sempre no lucro, nos dois modos.</small>
        </div>
    </div>
    <?php } ?>

    <?php } ?>

    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
