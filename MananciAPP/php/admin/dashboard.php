<?php
session_start();
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

// auth_require confere também inatividade, revogação (admin removido ou
// "desconectado" cai na hora) e o Termo — a checagem manual que havia aqui
// só olhava a sessão.
auth_require(array('admin'));

// Buscar estatísticas
$stats = array(
    'total_usuarios' => 0,
    'usuarios_pendentes' => 0,
    'usuarios_aprovados' => 0,
    'total_videos' => 0,
    'total_cursos' => 0,
    'total_matriculas' => 0,
    'taxa_conclusao' => 0
);

// Total de usuários
$sql = "SELECT COUNT(*) as cnt FROM ID_FIEL";
$res = mysqli_query($conn, $sql);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $stats['total_usuarios'] = $row['cnt'];
}

// Usuários pendentes
$sql = "SELECT COUNT(*) as cnt FROM ID_FIEL WHERE IDF_STATUS = 'pendente'";
$res = mysqli_query($conn, $sql);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $stats['usuarios_pendentes'] = $row['cnt'];
}

// Usuários aprovados
$sql = "SELECT COUNT(*) as cnt FROM ID_FIEL WHERE IDF_STATUS = 'aprovado'";
$res = mysqli_query($conn, $sql);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $stats['usuarios_aprovados'] = $row['cnt'];
}

// Total de vídeos
$sql = "SELECT COUNT(*) as cnt FROM ID_CONTENT WHERE LOWER(IDCT_TIPO) = 'video'";
$res = mysqli_query($conn, $sql);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $stats['total_videos'] = $row['cnt'];
}

// Total de cursos
$sql = "SELECT COUNT(*) as cnt FROM ID_CURSO";
$res = mysqli_query($conn, $sql);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $stats['total_cursos'] = $row['cnt'];
}

// Total de matrículas
$sql = "SELECT COUNT(*) as cnt FROM ID_MATRICULA";
$res = mysqli_query($conn, $sql);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $stats['total_matriculas'] = $row['cnt'];
}

// Taxa de conclusão: de todos os cursos começados, quantos foram até o fim.
// Antes era a média do progresso só de quem tinha passado de 0% (quem se
// matriculou e nunca assistiu ficava de fora, e o número saía inflado).
$stats['progresso_medio'] = 0;
$sql = "SELECT COUNT(*) AS total,
               COUNT(*) FILTER (WHERE IDMATR_CONCLUSAO_EM IS NOT NULL OR IDMATR_PERCENTUAL >= 100) AS concluidas,
               AVG(COALESCE(IDMATR_PERCENTUAL, 0)) AS media
        FROM ID_MATRICULA";
$res = mysqli_query($conn, $sql);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $total_matr = (int) ($row['total'] ?? 0);
    $stats['taxa_conclusao'] = $total_matr > 0 ? round(100 * (int) $row['concluidas'] / $total_matr, 1) : 0;
    $stats['progresso_medio'] = round((float) ($row['media'] ?? 0), 1);
}

// As duas listas abaixo têm e-mail, CPF, telefone e endereço: só para quem
// tem "Aprovar Usuários" ou "Gerenciar Usuários" (antes qualquer tipo de
// admin, até um só de Cursos, lia esses dados aqui). A equipe interna
// (SUPERADMIN_EMAILS) fica fora, como nas outras listas.
$ve_pessoas = admin_pode_acessar('aprovar') || admin_pode_acessar('usuarios');
$sem_equipe = '';
$params_equipe = array();
if (superadmin_emails() && !auth_eh_superadmin()) {
    $sem_equipe = ' AND LOWER(IDF_EMAIL) NOT IN (' . implode(', ', array_fill(0, count(superadmin_emails()), '?')) . ')';
    $params_equipe = superadmin_emails();
}

// Usuários recentes
$usuarios_recentes = array();
$sql = "SELECT IDF_ID, IDF_NOME, IDF_EMAIL, IDF_STATUS, IDF_CRIADO_EM FROM ID_FIEL WHERE 1 = 1$sem_equipe ORDER BY IDF_CRIADO_EM DESC LIMIT 5";
$res = $ve_pessoas ? db_query($conn, $sql, $params_equipe) : false;
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $usuarios_recentes[] = $row;
    }
}

// Usuários pendentes
$usuarios_pendentes = array();
$sql = tabela_tem_coluna($conn, 'ID_FIEL', 'IDF_FILIAL_ID') && tabela_tem_coluna($conn, 'ID_FIEL', 'IDF_CRIADO_EM')
    ? "SELECT IDF_ID, IDF_NOME, IDF_EMAIL, IDF_TELEFONE, IDF_CPF, IDF_FUNCAO, IDF_ENDERECO, IDF_STATUS, IDF_ATIVO, IDF_FILIAL_ID, IDF_CRIADO_EM, IDF_FILIAL, (SELECT fl.IDL_NOME FROM ID_FILIAL fl WHERE fl.IDL_ID = ID_FIEL.IDF_FILIAL_ID) AS IDL_NOME FROM ID_FIEL WHERE IDF_STATUS = 'pendente'$sem_equipe ORDER BY IDF_CRIADO_EM DESC LIMIT 10"
    : "SELECT IDF_ID, IDF_NOME, IDF_EMAIL, IDF_TELEFONE, IDF_CPF, IDF_FUNCAO, IDF_ENDERECO, IDF_STATUS, IDF_ATIVO, IDF_FILIAL AS IDL_NOME, NULL AS IDF_CRIADO_EM FROM ID_FIEL WHERE IDF_STATUS = 'pendente'$sem_equipe ORDER BY IDF_ID DESC LIMIT 10";
$res = $ve_pessoas ? db_query($conn, $sql, $params_equipe) : false;
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $usuarios_pendentes[] = $row;
    }
}

// Vídeos recentes
$videos_recentes = array();
$sql = "SELECT IDCT_ID, IDCT_TITULO, IDCT_CRIADO_EM FROM ID_CONTENT WHERE LOWER(IDCT_TIPO) = 'video' ORDER BY IDCT_CRIADO_EM DESC LIMIT 5";
$res = mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $videos_recentes[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Dashboard Admin</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <style>
        :root {
            --admin-primary: #1e40af;
            --admin-primary-light: #3b82f6;
            --admin-accent: #f59e0b;
            --admin-success: #10b981;
            --admin-danger: #ef4444;
            --admin-warning: #f59e0b;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(96, 165, 250, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out backwards;
        }

        .stat-card:nth-child(1) { animation-delay: 0.3s; }
        .stat-card:nth-child(2) { animation-delay: 0.35s; }
        .stat-card:nth-child(3) { animation-delay: 0.4s; }
        .stat-card:nth-child(4) { animation-delay: 0.45s; }
        .stat-card:nth-child(5) { animation-delay: 0.5s; }
        .stat-card:nth-child(6) { animation-delay: 0.55s; }
        .stat-card:nth-child(7) { animation-delay: 0.6s; }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(96, 165, 250, 0.1), transparent);
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: rgba(96, 165, 250, 0.5);
            background: rgba(30, 41, 59, 0.8);
            box-shadow: 0 15px 35px rgba(59, 130, 246, 0.15);
        }

        .stat-card:hover::before {
            right: -20%;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            color: var(--text-main);
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1;
        }

        .stat-change {
            color: var(--accent);
            font-size: 0.85rem;
            margin-top: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Action Cards */
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.7), rgba(21, 34, 56, 0.5));
            border: 1px solid rgba(96, 165, 250, 0.3);
            border-radius: 12px;
            padding: 1.75rem;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            animation: fadeInUp 0.6s ease-out backwards;
        }

        .action-card:nth-child(1) { animation-delay: 0.65s; }
        .action-card:nth-child(2) { animation-delay: 0.7s; }
        .action-card:nth-child(3) { animation-delay: 0.75s; }
        .action-card:nth-child(4) { animation-delay: 0.8s; }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, transparent, rgba(96, 165, 250, 0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-6px);
            border-color: rgba(59, 130, 246, 0.6);
            box-shadow: 0 20px 40px rgba(59, 130, 246, 0.2);
        }

        .action-card:hover::before {
            opacity: 1;
        }

        .action-card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            z-index: 1;
        }

        .action-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-light));
            transition: transform 0.3s ease;
        }

        .action-card:hover .action-icon {
            transform: scale(1.1);
        }

        .action-title {
            font-weight: 700;
            color: var(--text-main);
            font-size: 1.1rem;
        }

        .action-desc {
            color: var(--text-muted);
            font-size: 0.9rem;
            position: relative;
            z-index: 1;
        }

        .action-cta {
            align-self: flex-start;
            color: var(--primary-light);
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            z-index: 1;
            transition: gap 0.3s ease;
        }

        .action-card:hover .action-cta {
            gap: 0.75rem;
        }

        /* Data Tables */
        .data-table {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(96, 165, 250, 0.2);
            border-radius: 12px;
            /* Rola de lado no celular: com overflow hidden a coluna "Ação" sumia. */
            overflow-x: auto;
            margin-top: 1rem;
            animation: fadeInUp 0.6s ease-out 0.4s both;
        }

        .data-table-header {
            background: rgba(15, 27, 53, 0.8);
            border-bottom: 1px solid rgba(96, 165, 250, 0.3);
            padding: 1rem 1.5rem;
        }

        .data-table-title {
            font-weight: 700;
            font-size: 1.1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .data-table table {
            width: 100%;
            margin: 0;
        }

        .data-table th {
            background: rgba(30, 41, 59, 0.8);
            border-bottom: 1px solid rgba(96, 165, 250, 0.2);
            padding: 1rem 1.5rem;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .data-table td {
            border-bottom: 1px solid rgba(96, 165, 250, 0.1);
            padding: 1rem 1.5rem;
            color: var(--text-main);
        }

        .data-table tbody tr {
            transition: background 0.3s ease;
        }

        .data-table tbody tr:hover {
            background: rgba(96, 165, 250, 0.05);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-approved {
            background: rgba(16, 185, 129, 0.2);
            color: var(--accent);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.2);
            color: var(--admin-warning);
        }

        .status-rejected {
            background: rgba(239, 68, 68, 0.2);
            color: var(--admin-danger);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
            }

            .stat-value {
                font-size: 2rem;
            }

            .action-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Theme Specific Colors */
        .stat-card.primary { --icon-bg: linear-gradient(135deg, #3b82f6, #60a5fa); }
        .stat-card.success { --icon-bg: linear-gradient(135deg, #10b981, #34d399); }
        .stat-card.warning { --icon-bg: linear-gradient(135deg, #f59e0b, #fbbf24); }
        .stat-card.danger { --icon-bg: linear-gradient(135deg, #ef4444, #f87171); }
        /* Cartão "Saúde do sistema" (sistema_saude()). */
        .saude-sistema { display: flex; flex-direction: column; gap: 0.3rem; padding: 0.9rem 1.1rem; border-radius: 12px; margin-bottom: 1.25rem; border: 1px solid; }
        .saude-sistema.tudo-certo { border-color: rgba(52, 211, 153, 0.35); background: rgba(52, 211, 153, 0.08); }
        .saude-sistema.tem-falha { border-color: rgba(251, 191, 36, 0.5); background: rgba(251, 191, 36, 0.1); }
        .saude-sistema ul { margin: 0.2rem 0 0; padding-left: 1.2rem; }
        .saude-sistema small { color: var(--text-muted); }
    </style>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'dashboard', 'Dashboard', 'fa-chart-pie'); ?>

            <?php
            // Só a equipe interna: o pastor não tem o que fazer com isso.
            // Seções do banco, variáveis do Render na produção e o agendador
            // do lembrete parado (site_avisos_equipe()).
            foreach (auth_eh_superadmin() ? site_avisos_equipe($conn) : array() as $aviso_equipe) { ?>
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-triangle-exclamation me-2" aria-hidden="true"></i><?php echo htmlspecialchars($aviso_equipe); ?>
                </div>
            <?php } ?>

            <?php
            // Saúde do sistema (seção 89): o que falhou nas últimas 24 h sem
            // ninguém ver (e-mail, push, aviso de pagamento, rotina) e quando
            // chegou o último aviso de pagamento. Para quem tem acesso total
            // e para a equipe interna.
            $saude = (auth_eh_superadmin() || admin_telas_do_logado() === null) ? sistema_saude($conn) : null;
            if ($saude !== null) {
                $tipos_falha = sistema_falha_tipos();
            ?>
                <div class="saude-sistema <?php echo $saude['falhas'] ? 'tem-falha' : 'tudo-certo'; ?>" role="status">
                    <strong><i class="fas <?php echo $saude['falhas'] ? 'fa-triangle-exclamation' : 'fa-circle-check'; ?> me-2" aria-hidden="true"></i>Saúde do sistema</strong>
                    <?php if (!$saude['falhas']) { ?>
                        <span>Tudo certo nas últimas 24 horas.</span>
                    <?php } else { ?>
                        <ul>
                            <?php foreach ($saude['falhas'] as $tipo_falha => $info_falha) { ?>
                                <li><?php echo htmlspecialchars($tipos_falha[$tipo_falha] ?? $tipo_falha); ?>: <strong><?php echo (int) $info_falha['total']; ?></strong> (a última às <?php echo date('H:i', strtotime($info_falha['ultima'])); ?>)</li>
                            <?php } ?>
                        </ul>
                        <small>Se continuar, avise a equipe técnica.</small>
                    <?php } ?>
                    <?php if (!empty($saude['ultimo_webhook'])) { ?>
                        <small class="d-block">Último aviso de pagamento recebido: <?php echo date('d/m/Y H:i', strtotime($saude['ultimo_webhook'])); ?></small>
                    <?php } ?>
                </div>
            <?php } ?>

            <!-- STATISTICS GRID -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-label">Total de Usuários</div>
                    <div class="stat-value"><?php echo $stats['total_usuarios']; ?></div>
                    <div class="stat-change">
                        <i class="fas fa-arrow-up"></i>
                        <?php echo $stats['usuarios_aprovados']; ?> aprovados
                    </div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-label">Pendentes de Aprovação</div>
                    <div class="stat-value"><?php echo $stats['usuarios_pendentes']; ?></div>
                    <div class="stat-change">
                        <i class="fas fa-exclamation-circle"></i>
                        Requer ação
                    </div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                        <i class="fas fa-video"></i>
                    </div>
                    <div class="stat-label">Total de Vídeos</div>
                    <div class="stat-value"><?php echo $stats['total_videos']; ?></div>
                    <div class="stat-change">
                        <i class="fas fa-check-circle"></i>
                        Disponíveis
                    </div>
                </div>

                <div class="stat-card primary">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-label">Total de Cursos</div>
                    <div class="stat-value"><?php echo $stats['total_cursos']; ?></div>
                    <div class="stat-change">
                        <i class="fas fa-check"></i>
                        Cadastrados
                    </div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-label">Matrículas Ativas</div>
                    <div class="stat-value"><?php echo $stats['total_matriculas']; ?></div>
                    <div class="stat-change">
                        <i class="fas fa-users-cog"></i>
                        Em andamento
                    </div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ec4899, #f472b6);">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="stat-label">Taxa de Conclusão</div>
                    <div class="stat-value"><?php echo $stats['taxa_conclusao']; ?>%</div>
                    <div class="stat-change">
                        <i class="fas fa-trending-up"></i>
                        Cursos começados que chegaram ao fim · progresso médio <?php echo $stats['progresso_medio']; ?>%
                    </div>
                </div>
            </div>

            <!-- ACTION CARDS -->
            <h3 style="color: var(--text-main); margin-bottom: 1.5rem; font-weight: 700; animation: fadeInUp 0.6s ease-out 0.5s both;">
                <i class="fas fa-bolt me-2"></i>Ações Rápidas
            </h3>
            <div class="action-grid">
                <a href="admin_conteudos.php" class="action-card">
                    <div class="action-card-header">
                        <div class="action-icon">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div>
                            <div class="action-title">Novo Vídeo</div>
                        </div>
                    </div>
                    <div class="action-desc">Adicione um novo conteúdo em vídeo para os cursos</div>
                    <div class="action-cta">
                        Ir para Vídeos
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>

                <a href="admin_aprovar_usuarios.php" class="action-card">
                    <div class="action-card-header">
                        <div class="action-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div>
                            <div class="action-title">Aprovar Usuários</div>
                        </div>
                    </div>
                    <div class="action-desc">Revise e aprove <?php echo $stats['usuarios_pendentes']; ?> solicitação(ões) pendente(s)</div>
                    <div class="action-cta">
                        Gerenciar Aprovações
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>

                <a href="admin_visao_usuario.php" class="action-card">
                    <div class="action-card-header">
                        <div class="action-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <div class="action-title">Ver Progresso</div>
                        </div>
                    </div>
                    <div class="action-desc">Acompanhe o progresso dos usuários nos cursos</div>
                    <div class="action-cta">
                        Visualizar Progresso
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>

                <a href="admin_gerenciar_permissoes.php" class="action-card">
                    <div class="action-card-header">
                        <div class="action-icon" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div>
                            <div class="action-title">Tipos de Admin</div>
                        </div>
                    </div>
                    <div class="action-desc">Escolha as telas que cada tipo de admin vê</div>
                    <div class="action-cta">
                        Gerenciar Tipos de Admin
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- PENDING NOTIFICATIONS -->
            <?php if (count($usuarios_pendentes) > 0): ?>
                <div class="data-table" style="margin-top: 2rem; border-left: 4px solid var(--admin-warning);">
                    <div class="data-table-header">
                        <p class="data-table-title">
                            <i class="fas fa-bell"></i>
                            Solicitações Pendentes (<?php echo count($usuarios_pendentes); ?>)
                        </p>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Função</th>
                                <th>Data</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios_pendentes as $user): ?>
                                <tr style="background: rgba(245, 158, 11, 0.05);">
                                    <td><strong><?php echo htmlspecialchars($user['IDF_NOME']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['IDF_EMAIL']); ?></td>
                                    <td><?php echo htmlspecialchars($user['IDF_FUNCAO'] ?? 'N/A'); ?></td>
                                    <td><?php echo !empty($user['IDF_CRIADO_EM']) ? date('d/m/Y H:i', strtotime($user['IDF_CRIADO_EM'])) : 'N/A'; ?></td>
                                    <td>
                                        <a href="admin_aprovar_usuarios.php" class="btn btn-sm btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.85rem;">
                                            </i>Ver Dados
                                        </a>
                                    </td>
                                </tr>

                                <!-- Modal Ver Dados -->
                                <div class="modal fade" id="verDadosModal<?php echo $user['IDF_ID']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content" style="background: var(--bg-light); border: 1px solid var(--border-color);">
                                            <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                                                <h5 class="modal-title"><i class="fas fa-user me-2"></i>Dados do Usuário - <?php echo htmlspecialchars($user['IDF_NOME']); ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="d-flex flex-wrap gap-2 justify-content-end mb-3">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                        <i class="fas fa-times me-2"></i>Fechar
                                                    </button>
                                                    <a href="admin_aprovar_usuarios.php" class="btn btn-primary">
                                                        <i class="fas fa-check me-2"></i>Ir para Aprovações
                                                    </a>
                                                </div>

                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Nome:</strong><br>
                                                        <span style="color: var(--text-muted);"><?php echo htmlspecialchars($user['IDF_NOME']); ?></span>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <strong>E-mail:</strong><br>
                                                        <span style="color: var(--text-muted);"><?php echo htmlspecialchars($user['IDF_EMAIL']); ?></span>
                                                    </div>
                                                </div>

                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Telefone:</strong><br>
                                                        <span style="color: var(--text-muted);"><?php echo htmlspecialchars(formatar_telefone_exibicao($user['IDF_TELEFONE'] ?? '')); ?></span>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <strong>CPF:</strong><br>
                                                        <span style="color: var(--text-muted);"><?php echo htmlspecialchars($user['IDF_CPF'] ?? 'N/A'); ?></span>
                                                    </div>
                                                </div>

                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Função:</strong><br>
                                                        <span style="color: var(--text-muted);"><?php echo htmlspecialchars($user['IDF_FUNCAO'] ?? 'N/A'); ?></span>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <strong>Data de Solicitação:</strong><br>
                                                        <span style="color: var(--text-muted);"><?php echo !empty($user['IDF_CRIADO_EM']) ? date('d/m/Y H:i', strtotime($user['IDF_CRIADO_EM'])) : 'N/A'; ?></span>
                                                    </div>
                                                </div>

                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Endereço:</strong><br>
                                                        <span style="color: var(--text-muted);"><?php echo htmlspecialchars($user['IDF_ENDERECO'] ?? 'N/A'); ?></span>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <strong>Status:</strong><br>
                                                        <span style="color: var(--text-muted);"><?php echo htmlspecialchars($user['IDF_STATUS'] ?? 'N/A'); ?></span>
                                                    </div>
                                                </div>

                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Ativo:</strong><br>
                                                        <span style="color: var(--text-muted);"><?php echo !empty($user['IDF_ATIVO']) ? 'Sim' : 'Não'; ?></span>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <strong>Filial:</strong><br>
                                                        <span style="color: var(--text-muted);"><?php echo htmlspecialchars($user['IDL_NOME'] ?? $user['IDF_FILIAL'] ?? 'N/A'); ?></span>
                                                    </div>
                                                </div>

                                                <hr style="border-color: var(--border-color);">

                                                <div class="alert alert-info" role="alert">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    Verifique os dados acima com os registros da filial para confirmar a identidade do usuário.
                                                </div>
                                            </div>
                                            <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                                <a href="admin_aprovar_usuarios.php" class="btn btn-primary">
                                                    <i class="fas fa-check me-2"></i>Ir para Aprovações
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- RECENT USERS -->
            <?php if (count($usuarios_recentes) > 0): ?>
                <div class="data-table" style="margin-top: 2rem;">
                    <div class="data-table-header">
                        <p class="data-table-title">
                            <i class="fas fa-user-plus"></i>
                            Usuários Recentes
                        </p>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Status</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios_recentes as $user): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($user['IDF_NOME']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['IDF_EMAIL']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['IDF_STATUS']; ?>">
                                            <i class="fas fa-<?php echo $user['IDF_STATUS'] === 'aprovado' ? 'check-circle' : 'hourglass-half'; ?>"></i>
                                            <?php echo ucfirst($user['IDF_STATUS']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($user['IDF_CRIADO_EM'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- RECENT VIDEOS -->
            <?php if (count($videos_recentes) > 0): ?>
                <div class="data-table">
                    <div class="data-table-header">
                        <p class="data-table-title">
                            <i class="fas fa-film"></i>
                            Vídeos Recentes
                        </p>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Data de Criação</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($videos_recentes as $video): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($video['IDCT_TITULO']); ?></strong></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($video['IDCT_CRIADO_EM'])); ?></td>
                                    <td>
                                        <a href="admin_editar_conteudo.php?id=<?php echo $video['IDCT_ID']; ?>" class="btn btn-sm btn-outline-light" style="padding: 0.35rem 0.75rem; font-size: 0.85rem;">
                                            <i class="fas fa-edit me-1"></i>Editar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

    <?php admin_layout_fechar(); ?>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
