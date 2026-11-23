<?php
// Layout padrão do painel admin: sidebar fixa (recolhível) + header com o
// título da tela, sino de notificações e tema claro/escuro.
//
// Uso numa tela do admin: carregar css/admin.css no <head>, usar
// <body class="admin-layout admin-shell"> e envolver o conteúdo da tela com
// admin_layout_abrir($conn, 'cursos', 'Cursos', 'fa-graduation-cap') e
// admin_layout_fechar(). A chave ('cursos') marca o item ativo do menu —
// ver admin_layout_menu().

function admin_layout_menu() {
    $menu = array(
        array('chave' => 'dashboard',    'href' => 'dashboard.php',                  'icone' => 'fa-chart-line',        'rotulo' => 'Dashboard'),
        array('chave' => 'metricas',     'href' => 'admin_metricas.php',             'icone' => 'fa-chart-column',      'rotulo' => 'Métricas'),
        array('chave' => 'cursos',       'href' => 'admin_cursos.php',               'icone' => 'fa-graduation-cap',    'rotulo' => 'Cursos'),
        array('chave' => 'conteudos',    'href' => 'admin_conteudos.php',            'icone' => 'fa-video',             'rotulo' => 'Gerenciar Vídeos'),
        array('chave' => 'transmissao',  'href' => 'admin_transmissao.php',          'icone' => 'fa-tower-broadcast',   'rotulo' => 'Ao Vivo'),
        array('chave' => 'aprovar',      'href' => 'admin_aprovar_usuarios.php',     'icone' => 'fa-user-check',        'rotulo' => 'Aprovar Usuários'),
        array('chave' => 'testemunhos',  'href' => 'admin_testemunhos.php',          'icone' => 'fa-hands-praying',     'rotulo' => 'Testemunhos'),
        array('chave' => 'desafios',     'href' => 'admin_desafios.php',             'icone' => 'fa-flag-checkered',    'rotulo' => 'Desafios'),
        array('chave' => 'devocionais',  'href' => 'admin_devocionais.php',          'icone' => 'fa-book-bible',        'rotulo' => 'Devocionais'),
        array('chave' => 'funcoes',      'href' => 'admin_funcoes.php',              'icone' => 'fa-people-group',      'rotulo' => 'Funções na Igreja'),
        array('chave' => 'filiais',      'href' => 'admin_filiais.php',              'icone' => 'fa-map-location-dot', 'rotulo' => 'Filiais'),
        array('chave' => 'progresso',    'href' => 'admin_visao_usuario.php',       'icone' => 'fa-tasks',             'rotulo' => 'Ver Progresso'),
        array('chave' => 'engajamento',  'href' => 'admin_engajamento.php',          'icone' => 'fa-fire',              'rotulo' => 'Online e Ofensiva'),
        array('chave' => 'assinaturas',  'href' => 'admin_assinaturas.php',          'icone' => 'fa-crown',             'rotulo' => 'Assinaturas'),
        array('chave' => 'destaques',    'href' => 'admin_destaques_home.php',       'icone' => 'fa-images',            'rotulo' => 'Destaques da home'),
        array('chave' => 'igreja',       'href' => 'admin_igreja.php',               'icone' => 'fa-church',            'rotulo' => 'Identidade da igreja'),
        array('chave' => 'missoes',      'href' => 'admin_missoes.php',              'icone' => 'fa-earth-americas',    'rotulo' => 'Missões'),
        array('chave' => 'contribuicoes', 'href' => 'admin_contribuicoes.php',       'icone' => 'fa-hand-holding-heart', 'rotulo' => 'Contribuições'),
        array('chave' => 'financeiro',   'href' => 'admin_financeiro.php',           'icone' => 'fa-sack-dollar',       'rotulo' => 'Financeiro'),
        array('chave' => 'permissoes',   'href' => 'admin_gerenciar_permissoes.php', 'icone' => 'fa-user-shield',       'rotulo' => 'Tipos de Admin'),
        array('chave' => 'usuarios',     'href' => 'admin_gerenciar_usuarios.php',   'icone' => 'fa-users-gear',        'rotulo' => 'Gerenciar Usuários'),
        array('chave' => 'tipos_fiel',   'href' => 'admin_tipos_fiel.php',           'icone' => 'fa-user-tag',          'rotulo' => 'Tipos de fiel'),
        array('chave' => 'mensagem',     'href' => 'admin_enviar_mensagem.php',      'icone' => 'fa-comment-dots',      'rotulo' => 'Enviar Mensagem'),
        array('chave' => 'atividade',    'href' => 'admin_atividade.php',            'icone' => 'fa-clock-rotate-left', 'rotulo' => 'Registro de atividade'),
        array('chave' => 'seguranca',    'href' => 'admin_seguranca_conta.php',      'icone' => 'fa-shield-halved',     'rotulo' => 'Minha segurança'),
        array('chave' => 'sair',        'href' => '../fiel/sair.php?t=' . logout_token(),             'icone' => 'fa-sign-out-alt',      'rotulo' => 'Sair'),
    );
    // Só as telas do grupo de permissões do admin (Financeiro: só a equipe
    // interna). Ver admin_pode_acessar() em conexao.php. "Minha segurança" é
    // da própria conta: todo admin vê.
    return array_values(array_filter($menu, function ($item) {
        return in_array($item['chave'], array('dashboard', 'seguranca', 'sair'), true) || admin_pode_acessar($item['chave']);
    }));
}

function admin_layout_contar($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return (int) $row['cnt'];
    }
    return 0;
}

function admin_layout_abrir($conn, $pagina_ativa, $titulo, $icone) {
    $pendentes = admin_layout_contar($conn, "SELECT COUNT(*) as cnt FROM ID_FIEL WHERE IDF_STATUS = 'pendente'");
    // Testemunhos esperando moderação (T26), se o mural já existe no banco.
    $testemunhos_pendentes = testemunhos_disponivel($conn)
        ? admin_layout_contar($conn, "SELECT COUNT(*) as cnt FROM id_testemunho WHERE idts_status = 'pendente'")
        : 0;

    $user_id = isset($_SESSION['Usuario_id']) ? (int) $_SESSION['Usuario_id'] : 0;
    $total_unread = $user_id > 0
        ? admin_layout_contar($conn, "SELECT COUNT(*) as cnt FROM ID_NOTIFICACAO WHERE IDF_ID = $user_id AND (IDN_LIDA = 0 OR IDN_LIDA IS NULL)")
        : 0;
    ?>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        // Aplica o estado salvo da sidebar antes do primeiro render (sem "piscar")
        document.body.classList.add('sidebar-no-anim');
        try {
            if (localStorage.getItem('mananciapp-admin-sidebar') === 'recolhida') {
                document.body.classList.add('sidebar-collapsed');
            }
        } catch (e) {}
    </script>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-logo">
                <img src="../../assets/logo-sm.png" alt="Logo" />
                <div>
                    <h5>Painel Admin</h5>
                    <small>Missão Evangélica</small>
                </div>
            </a>
        </div>

        <nav>
            <ul class="sidebar-menu px-2">
                <?php foreach (admin_layout_menu() as $item) { ?>
                    <li class="sidebar-menu-item">
                        <a href="<?php echo $item['href']; ?>" class="sidebar-link<?php echo $item['chave'] === $pagina_ativa ? ' active' : ''; ?>"<?php echo $item['chave'] === $pagina_ativa ? ' aria-current="page"' : ''; ?><?php echo $item['chave'] === 'transmissao' ? ' style="color:#fb7185;"' : ''; ?>>
                            <?php if ($item['chave'] === 'transmissao') { ?>
                                <span class="live-dot-col"><span class="live-dot"></span></span>
                            <?php } else { ?>
                                <i class="fas <?php echo $item['icone']; ?>"></i>
                            <?php } ?>
                            <span><?php echo $item['rotulo']; ?></span>
                            <?php if ($item['chave'] === 'testemunhos' && $testemunhos_pendentes > 0) { ?>
                                <span class="badge bg-danger"><?php echo $testemunhos_pendentes; ?></span>
                            <?php } ?>
                            <?php if ($item['chave'] === 'aprovar' && $pendentes > 0) { ?>
                                <span class="badge bg-danger"><?php echo $pendentes; ?></span>
                            <?php } ?>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        </nav>
    </aside>

    <main class="admin-main">
        <header class="admin-header">
            <div class="admin-header-content">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Ocultar menu" aria-expanded="true" aria-controls="adminSidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>
                        <i class="fas <?php echo $icone; ?>"></i>
                        <?php echo htmlspecialchars($titulo); ?>
                    </h1>
                </div>
                <div class="admin-header-actions">
                    <div class="dropdown">
                        <button type="button" class="btn presenca-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Pessoas online">
                            <i class="fas fa-users"></i>
                            <span class="presenca-contador" data-presenca-contador>0</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end presenca-menu">
                            <div class="presenca-menu-titulo"><span class="presenca-ponto"></span>Pessoas online</div>
                            <ul class="presenca-lista" data-presenca-lista>
                                <li class="presenca-vazio">Carregando…</li>
                            </ul>
                            <a href="admin_engajamento.php" class="presenca-menu-rodape">Ver painel completo <i class="fas fa-chevron-right ms-1"></i></a>
                        </div>
                    </div>
                    <a href="notificacoes.php" class="text-decoration-none position-relative notif-bell-link" style="font-size:1.2rem; color: var(--primary-light);" aria-label="Notificações">
                        <i class="fas fa-bell"></i>
                        <?php if ($total_unread > 0) { ?>
                            <span class="notif-badge position-absolute d-inline-flex align-items-center justify-content-center rounded-circle" style="background:#dc2626;color:#fff;font-size:0.7rem;width:20px;height:20px;right:-6px;top:-6px;border:2px solid rgba(0,0,0,0.15);"><?php echo $total_unread; ?></span>
                        <?php } ?>
                    </a>
                    <div class="theme-toggle-container">
                        <i class="fas fa-moon theme-icon" style="font-size: 1rem;"></i>
                        <input type="checkbox" id="theme-toggle" class="theme-toggle" aria-label="Alternar tema" style="width: 40px; height: 22px;">
                        <i class="fas fa-sun theme-icon" style="font-size: 1rem;"></i>
                    </div>
                </div>
            </div>
        </header>

        <div class="admin-content">
            <?php if (isset($_GET['sem_permissao'])) { ?>
                <div class="alert alert-warning"><i class="fas fa-lock me-2"></i>Você não tem acesso a essa tela. Se precisar, peça a quem cuida das permissões para incluí-la no seu grupo.</div>
            <?php } ?>
    <?php
}

function admin_layout_fechar() {
    ?>
        </div>
    </main>
    <script src="../../js/admin.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <script src="<?php echo htmlspecialchars(site_url('js/live-indicator.js') . '?v=' . site_versao_estatico()); ?>" data-status-url="<?php echo htmlspecialchars(site_url('ajax_transmissao_status.php')); ?>"></script>
    <script src="<?php echo htmlspecialchars(site_url('js/presenca.js') . '?v=' . site_versao_estatico()); ?>" data-presenca-url="<?php echo htmlspecialchars(site_url('ajax_presenca.php')); ?>"></script>
    <?php
}
