<?php
// Contribuições (superadmin, ou tipo de admin com a tela marcada por um
// superadmin; ver admin_telas_restritas()): a oferta geral — a oferta
// livre para manter o app, que a aba Contribuir do fiel abre. Aqui a
// equipe edita o texto, a capa e o que o fiel vê (por padrão, sem o total
// arrecadado), lança ofertas recebidas fora do app e vê as contribuições
// com filtros. Por baixo é um projeto de missão marcado em id_configuracao
// (missoes_oferta_geral); as missões ficam em Admin → Missões. Ver
// "CONTRIBUIÇÃO EM MISSÕES" em php/conexao.php e as seções 72, 77 e 78 do
// bancosupabase.md.
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));
// Ações que deram certo vão para o Registro de atividade (admin_log_ao_redirecionar()).
admin_log_ao_redirecionar($conn, 'contribuicoes');

$mensagem = "";
$erro = "";
$hoje = site_hoje();
$admin_id = auth_resolve_fiel_id();
$disponivel = missoes_disponivel($conn);
$geral_id = missoes_oferta_geral_id();
$geral = $disponivel && $geral_id > 0 ? missao_projeto($conn, $geral_id, true) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $disponivel) {
    $acao = site_entrada_texto($_POST['acao'] ?? '', false);

    if ($acao === 'criar_geral' && !$geral) {
        if (missao_oferta_geral_criar($conn, $admin_id) > 0) {
            $_SESSION['contribuicoes_ok'] = 'Oferta geral criada. Confira o texto e a capa abaixo.';
            header('Location: admin_contribuicoes.php');
            exit;
        }
        $erro = site_erro_banco('Não foi possível criar a oferta geral.');
    } elseif ($acao === 'salvar_projeto' && $geral) {
        $salvo = missao_projeto_salvar($conn, array(
            'idmp_id' => $geral_id,
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
            $_SESSION['contribuicoes_ok'] = 'Oferta geral salva.';
            header('Location: admin_contribuicoes.php');
            exit;
        }
    } elseif ($acao === 'lancar_oferta' && $geral) {
        $erro = missao_lancar_oferta(
            $conn,
            $geral_id,
            site_entrada_texto($_POST['valor'] ?? ''),
            site_entrada_texto($_POST['data'] ?? ''),
            site_entrada_texto($_POST['nome'] ?? ''),
            site_entrada_texto($_POST['recebido_por'] ?? ''),
            $admin_id
        );
        if ($erro === '') {
            $_SESSION['contribuicoes_ok'] = 'Oferta lançada.';
            header('Location: admin_contribuicoes.php#contribuicoes');
            exit;
        }
    } elseif ($acao === 'excluir_oferta' && $geral) {
        // Só as lançadas à mão nesta oferta: as do Asaas mudam pelo webhook.
        // Cancela em vez de apagar (missao_oferta_manual_cancelar()).
        $erro = missao_oferta_manual_cancelar($conn, (int) ($_POST['idmc_id'] ?? 0), auth_resolve_fiel_id(), site_entrada_texto($_POST['motivo'] ?? '', false), $geral_id);
        if ($erro === '') {
            $_SESSION['contribuicoes_ok'] = 'Oferta cancelada. Ela continua na lista e saiu dos totais.';
            header('Location: admin_contribuicoes.php#contribuicoes');
            exit;
        }
    }
}
if (!empty($_SESSION['contribuicoes_ok'])) {
    $mensagem = $_SESSION['contribuicoes_ok'];
    unset($_SESSION['contribuicoes_ok']);
}

$filtros = missao_contribuicoes_filtros($_GET);
$contribuicoes = $geral ? missao_contribuicoes_admin($conn, $filtros, $geral_id) : null;
$capa = $geral ? missao_capa_url($geral['idmp_capa_url'] ?? '') : null;
$situacoes = array('rascunho' => array('Rascunho (o fiel não vê)', 'bg-secondary'), 'aberto' => array('Recebendo', 'bg-success'), 'encerrado' => array('Encerrada', 'bg-dark'));
$eh_sandbox = strpos((string) ASAAS_API_URL, 'sandbox') !== false;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Contribuições</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'contribuicoes', 'Contribuições', 'fa-hand-holding-heart'); ?>

    <p class="text-muted mb-4">
        A <strong>oferta geral</strong>: a oferta livre para manter o app, que a aba <strong>Contribuir</strong> do fiel abre. <?php echo auth_eh_superadmin() ? 'Só super admin vê esta tela, a não ser que ela seja liberada num tipo de admin.' : 'Tela restrita.'; ?>
        As missões, que podem mostrar o quanto já arrecadaram, ficam em <a href="admin_missoes.php">Missões</a>.
    </p>

    <?php if ($mensagem !== "") { ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
    <?php } ?>
    <?php if ($erro !== "") { ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
    <?php } ?>

    <?php if (!$disponivel) { ?>
        <div class="alert alert-danger">As tabelas de contribuições ainda não existem no banco. Rode as seções 71 e 72 do <code>database/bancosupabase.md</code> no SQL Editor do Supabase.</div>
    <?php } elseif (!$geral) { ?>
        <div class="card mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color);">
            <div class="card-body">
                <h5><i class="fas fa-hand-holding-heart me-2" style="color:#f472b6;"></i>Ainda não há oferta geral</h5>
                <p class="text-muted">Crie com o texto e a arte padrão ("Ajude o site da igreja a crescer"), já recebendo e sem mostrar o total arrecadado. Depois é só ajustar aqui.</p>
                <form method="POST">
                    <?php echo csrf_campo(); ?>
                    <input type="hidden" name="acao" value="criar_geral">
                    <button type="submit" class="btn btn-salvar"><i class="fas fa-plus me-1"></i>Criar oferta geral</button>
                </form>
            </div>
        </div>
    <?php } else {
        $s = $situacoes[$geral['situacao']];
    ?>
        <div class="alert <?php echo $eh_sandbox ? 'alert-warning' : 'alert-info'; ?> small">
            <strong>Asaas:</strong>
            <?php if (!asaas_configurado()) { ?>
                não configurado — o fiel vê a oferta, mas não consegue contribuir.
            <?php } else { ?>
                <?php echo $eh_sandbox ? 'ambiente de TESTE (sandbox) — nenhuma contribuição é cobrada de verdade.' : 'PRODUÇÃO — contribuições reais.'; ?>
            <?php } ?>
        </div>

        <div class="card mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color);">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center">
                <?php if ($capa) { ?><img src="<?php echo htmlspecialchars($capa); ?>" alt="" style="width:180px;max-width:100%;border-radius:10px;"><?php } ?>
                <div class="flex-grow-1">
                    <h5 class="mb-1"><?php echo htmlspecialchars($geral['idmp_titulo']); ?> <span class="badge <?php echo $s[1]; ?> align-middle"><?php echo $s[0]; ?></span></h5>
                    <p class="mb-1"><strong><?php echo assinatura_formatar_valor($geral['arrecadado']); ?></strong> arrecadados · <?php echo (int) $geral['apoiadores']; ?> <?php echo (int) $geral['apoiadores'] === 1 ? 'pessoa ajudou' : 'pessoas ajudaram'; ?></p>
                    <small class="text-muted">
                        <?php if (missao_pix_qr_disponivel($conn)) { ?>
                            <i class="fa-brands fa-pix me-1"></i><?php echo !empty($geral['idmp_pix_qr_id']) ? 'QR Code do Pix direto criado.' : 'O QR Code do Pix direto é criado na primeira visita de um fiel à página.'; ?>
                        <?php } else { ?>
                            Pix direto (QR Code): rode a seção 77 do <code>bancosupabase.md</code>.
                        <?php } ?>
                    </small>
                </div>
                <?php if ($geral['situacao'] !== 'rascunho') { ?>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="../fiel/missao.php?id=<?php echo (int) $geral_id; ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Ver como fiel</a>
                        <a href="../fiel/contribuir.php" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Aba Contribuir</a>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="card mb-4" id="formProjeto" style="background: var(--bg-light); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
                <h5 class="mb-0"><i class="fas fa-pen me-2" style="color:#60a5fa;"></i>Editar a oferta geral</h5>
                <small class="text-muted">"Encerrada" fecha a aba Contribuir (o fiel vê "contribuições não estão abertas"). O link dela (usado nos destaques da home) continua o mesmo.</small>
            </div>
            <div class="card-body">
                <?php $mpf = array('projeto' => $geral, 'action' => 'admin_contribuicoes.php', 'geral' => true); include __DIR__ . '/includes/missao_projeto_form.php'; ?>
            </div>
        </div>

        <div class="card mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
                <h5 class="mb-0"><i class="fas fa-hand-holding-heart me-2" style="color:#f472b6;"></i>Lançar oferta recebida fora do app</h5>
                <small class="text-muted">Pix direto na conta, dinheiro em mãos etc. Se a equipe recebeu, entra também no Financeiro.</small>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-2">
                    <?php echo csrf_campo(); ?>
                    <input type="hidden" name="acao" value="lancar_oferta">
                    <div class="col-sm-6 col-md-3"><input type="text" name="valor" class="form-control custom-input" inputmode="decimal" placeholder="Valor (R$)" required aria-label="Valor"></div>
                    <div class="col-sm-6 col-md-3"><input type="date" name="data" class="form-control custom-input" value="<?php echo $hoje; ?>" max="<?php echo $hoje; ?>" required aria-label="Data"></div>
                    <div class="col-sm-6 col-md-3">
                        <select name="recebido_por" class="form-select custom-input" aria-label="Quem recebeu">
                            <option value="equipe">Equipe recebeu</option>
                            <option value="igreja">Igreja recebeu</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-3"><button type="submit" class="btn btn-salvar w-100">Lançar</button></div>
                    <div class="col-12"><input type="text" name="nome" class="form-control custom-input" maxlength="100" placeholder="Nome no quadro de apoiadores (opcional)" aria-label="Nome no quadro"></div>
                </form>
            </div>
        </div>

        <?php $cl = array('pagina' => 'admin_contribuicoes.php', 'filtros' => $filtros, 'dados' => $contribuicoes, 'projetos' => array()); include __DIR__ . '/includes/contribuicoes_lista.php'; ?>
    <?php } ?>

    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
