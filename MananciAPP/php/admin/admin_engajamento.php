<?php
// Painel de engajamento do admin: quem está online agora (atualiza sozinho
// via js/presenca.js, ver evento "presenca:atualizada" no fim da página) e
// o ranking de ofensiva com nome completo.
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));

$hoje = site_hoje();

function engajamento_contar($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? (int) $linha['cnt'] : 0;
}

$acessaram_hoje = engajamento_contar($conn, "SELECT COUNT(*) AS cnt FROM id_fiel_acesso_dia WHERE idad_data = DATE '$hoje'");
$acessaram_7_dias = engajamento_contar($conn, "SELECT COUNT(DISTINCT idf_id) AS cnt FROM id_fiel_acesso_dia WHERE idad_data > DATE '$hoje' - 7");
$total_aprovados = engajamento_contar($conn, "SELECT COUNT(*) AS cnt FROM ID_FIEL WHERE IDF_STATUS = 'aprovado' AND IDF_ATIVO = 1");

$ranking = ofensiva_ranking($conn, 100);
$online_inicial = presenca_listar_online($conn, 100);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Online e Ofensiva</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'engajamento', 'Online e Ofensiva', 'fa-fire'); ?>

    <div class="ofensiva-stats mb-4">
        <div class="ofensiva-stat">
            <i class="fas fa-circle" style="color:#22c55e;"></i>
            <strong data-presenca-contador><?php echo count($online_inicial); ?></strong>
            <small>Online agora</small>
        </div>
        <div class="ofensiva-stat">
            <i class="fas fa-calendar-day" style="color:#f97316;"></i>
            <strong><?php echo $acessaram_hoje; ?></strong>
            <small>Acessaram hoje</small>
        </div>
        <div class="ofensiva-stat">
            <i class="fas fa-calendar-week" style="color:#60a5fa;"></i>
            <strong><?php echo $acessaram_7_dias; ?><span class="text-muted" style="font-size:1rem;"> / <?php echo $total_aprovados; ?></span></strong>
            <small>Acessaram nos últimos 7 dias</small>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card h-100" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                <div class="card-header d-flex justify-content-between align-items-center" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
                    <h5 class="mb-0"><span class="presenca-ponto me-2"></span>Pessoas online</h5>
                    <small class="text-muted">Atualiza a cada minuto</small>
                </div>
                <div class="card-body p-0">
                    <ul class="presenca-lista presenca-lista-painel" id="engajamentoOnline">
                        <?php if (empty($online_inicial)) { ?>
                            <li class="presenca-vazio">Ninguém online agora.</li>
                        <?php } ?>
                        <?php foreach ($online_inicial as $p) { ?>
                            <li class="presenca-item">
                                <span class="presenca-avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($p['IDF_NOME'], 0, 1))); ?></span>
                                <div class="presenca-corpo">
                                    <div class="presenca-nome"><?php echo htmlspecialchars($p['IDF_NOME']); ?><?php if (!empty($p['eh_admin'])) { ?><span class="presenca-tag">Equipe</span><?php } ?></div>
                                    <div class="presenca-detalhe"><?php echo htmlspecialchars((string) ($p['idf_ultima_pagina'] ?? '')); ?></div>
                                </div>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
                    <h5 class="mb-0"><i class="fas fa-ranking-star me-2" style="color:#60a5fa;"></i>Ranking de ofensiva</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($ranking)) { ?>
                        <p class="text-muted p-3 mb-0">Ninguém registrou acesso ainda.</p>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-3">#</th>
                                        <th>Nome</th>
                                        <th class="text-center" title="Dias seguidos até hoje"><i class="fas fa-fire" style="color:#f97316;"></i> Atual</th>
                                        <th class="text-center"><i class="fas fa-trophy" style="color:#facc15;"></i> Recorde</th>
                                        <th class="text-center pe-3"><i class="fas fa-calendar-check" style="color:#34d399;"></i> Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ranking as $i => $linha) { ?>
                                        <tr>
                                            <td class="ps-3"><?php echo $i + 1; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($linha['IDF_NOME']); ?>
                                                <?php if (!empty($linha['eh_admin'])) { ?><span class="presenca-tag">Equipe</span><?php } ?>
                                            </td>
                                            <td class="text-center fw-bold"><?php echo (int) $linha['atual']; ?></td>
                                            <td class="text-center"><?php echo (int) $linha['recorde']; ?></td>
                                            <td class="text-center pe-3"><?php echo (int) $linha['total']; ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <?php admin_layout_fechar(); ?>

    <script nonce="<?php echo site_csp_nonce(); ?>">
        // A lista desse painel tem id próprio (não data-presenca-lista) pra
        // não ser sobrescrita pelo render genérico do dropdown; usa o mesmo
        // dado, mas sempre mostrando a página atual de cada um.
        document.addEventListener('presenca:atualizada', function (e) {
            var lista = document.getElementById('engajamentoOnline');
            var pessoas = e.detail.pessoas || [];
            lista.innerHTML = '';
            if (!pessoas.length) {
                lista.innerHTML = '<li class="presenca-vazio">Ninguém online agora.</li>';
                return;
            }
            pessoas.forEach(function (p) {
                var li = document.createElement('li');
                li.className = 'presenca-item';
                li.innerHTML = '<span class="presenca-avatar"></span><div class="presenca-corpo"><div class="presenca-nome"></div><div class="presenca-detalhe"></div></div>';
                li.querySelector('.presenca-avatar').textContent = window.Presenca.iniciais(p.nome);
                var nome = li.querySelector('.presenca-nome');
                nome.textContent = p.nome + (p.eu ? ' (você)' : '');
                if (p.equipe) {
                    var tag = document.createElement('span');
                    tag.className = 'presenca-tag';
                    tag.textContent = 'Equipe';
                    nome.appendChild(tag);
                }
                li.querySelector('.presenca-detalhe').textContent =
                    (p.pagina ? p.pagina + ' · ' : '') + window.Presenca.tempoRelativo(p.segundos || 0);
                lista.appendChild(li);
            });
        });
    </script>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
