<?php
// Navbar compartilhada da area do fiel (estilo streaming).
// Include a partir de qualquer php/fiel/*.php DEPOIS de conexao.php/auth_require().
// Variaveis opcionais que a pagina pode definir ANTES do include:
//   $navbar_active = 'inicio' | 'ao_vivo' | 'cursos' | 'categorias' | 'perfil' | 'progresso' | 'ofensiva'
//                     | 'certificados' | 'historico' | 'favoritos' | 'configuracoes' | 'assinatura'
//                     | 'sobre' | 'contato'
//   $navbar_base    = prefixo relativo pra paginas que NAO estao direto em
//                     php/fiel/ (ex.: '../' pra algo em php/fiel/Certificados/*.php)
//
// Layout:
// - Desktop (>= 992px): barra do topo só com ações (recolher menu, logo,
//   ofensiva, busca, pessoas online, sino, avatar) + menu lateral fixo à
//   esquerda com a navegação, que recolhe pra só ícones (estado salvo no
//   localStorage). O menu do avatar fica só com perfil/assinatura/
//   configurações, tema e sair.
// - Mobile: barra com logo, pessoas online, sino e o botão que abre o painel
//   lateral (offcanvas, à direita) com todo o resto.

if (!isset($navbar_active)) {
    $navbar_active = '';
}
if (!isset($navbar_base)) {
    $navbar_base = '';
}

$navbar_usuario_nome = $_SESSION['Usuario_nome'] ?? 'Usuário';
$navbar_usuario_iniciais = '';
foreach (preg_split('/\s+/', trim((string) $navbar_usuario_nome)) as $parte) {
    if ($parte !== '') {
        $navbar_usuario_iniciais .= mb_strtoupper(mb_substr($parte, 0, 1));
    }
    if (mb_strlen($navbar_usuario_iniciais) >= 2) {
        break;
    }
}
if ($navbar_usuario_iniciais === '') {
    $navbar_usuario_iniciais = 'U';
}

// Foto de perfil (T11), se o fiel tiver uma.
$navbar_foto_url = isset($conn) ? fiel_foto_url($conn, auth_resolve_fiel_id(), $navbar_base) : null;
$navbar_avatar_html = $navbar_foto_url
    ? '<img src="' . htmlspecialchars($navbar_foto_url) . '" alt="">'
    : htmlspecialchars($navbar_usuario_iniciais);

$navbar_total_unread = 0;
$navbar_user_id = isset($_SESSION['Usuario_id']) ? (int) $_SESSION['Usuario_id'] : 0;
if ($navbar_user_id > 0 && isset($conn)) {
    $sql_navbar_unread = "SELECT COUNT(*) as cnt FROM ID_NOTIFICACAO WHERE IDF_ID = $navbar_user_id AND (IDN_LIDA = 0 OR IDN_LIDA IS NULL)";
    $res_navbar_unread = mysqli_query($conn, $sql_navbar_unread);
    if ($res_navbar_unread && $row_navbar_unread = mysqli_fetch_assoc($res_navbar_unread)) {
        $navbar_total_unread = (int) $row_navbar_unread['cnt'];
    }
}

$navbar_ofensiva = ($navbar_user_id > 0 && isset($conn)) ? ofensiva_atual_cache($conn, $navbar_user_id) : 0;

// Primeiro acesso do dia (ver ofensiva_registrar_acesso() em conexao.php):
// mostra o aviso uma vez só.
$navbar_comemorar_ofensiva = !empty($_SESSION['ofensiva_comemorar']);
unset($_SESSION['ofensiva_comemorar']);
// Protetor de ofensiva (seção 85): salvou dias que faltaram ou foi ganho hoje.
$navbar_protegida = (int) ($_SESSION['ofensiva_protegida'] ?? 0);
$navbar_protetor_ganho = !empty($_SESSION['protetor_ganho']);
unset($_SESSION['ofensiva_protegida'], $_SESSION['protetor_ganho']);
// Medalhas conquistadas agora (T14): o aviso delas tem prioridade.
$navbar_medalhas_novas = $_SESSION['medalhas_novas'] ?? array();
unset($_SESSION['medalhas_novas']);

// Links do menu: [chave do $navbar_active, arquivo, ícone (null = ponto do Ao Vivo), texto].
// As mesmas listas montam o menu lateral do desktop, o menu do avatar e o
// painel do mobile.
$navbar_links_navegar = array(
    array('inicio', 'dashboard.php', 'fas fa-home', 'Início'),
    array('ao_vivo', 'ao_vivo.php', null, 'Ao Vivo'),
    array('cursos', 'cursos.php', 'fas fa-play-circle', 'Cursos'),
    array('devocional', 'devocional.php', 'fas fa-book-bible', 'Devocional'),
    array('testemunhos', 'testemunhos.php', 'fas fa-hands-praying', 'Testemunhos'),
    array('desafios', 'desafios.php', 'fas fa-flag-checkered', 'Desafios'),
);
// Missões e Contribuir (a oferta geral da equipe), T32: abas separadas,
// só depois da seção 72 do bancosupabase.md.
if (missoes_disponivel($conn)) {
    $navbar_links_navegar[] = array('missoes', 'missoes.php', 'fas fa-earth-americas', 'Missões');
    $navbar_links_navegar[] = array('contribuir', 'contribuir.php', 'fas fa-hand-holding-heart', 'Contribuir');
}
$navbar_links_jornada = array(
    array('progresso', 'meu_progresso.php', 'fas fa-chart-line', 'Meu Progresso'),
    array('ofensiva', 'ofensiva.php', 'fas fa-fire', 'Ofensiva e Ranking'),
    array('certificados', 'Certificados/certificados.php', 'fas fa-certificate', 'Certificados'),
    array('historico', 'historico.php', 'fas fa-history', 'Histórico'),
    array('favoritos', 'favoritos.php', 'fas fa-heart', 'Favoritos'),
);
$navbar_links_institucionais = array(
    array('categorias', 'categorias.php', 'fas fa-th-large', 'Categorias'),
    array('sobre', 'sobre.php', 'fas fa-info-circle', 'Sobre'),
    array('contato', 'contato.php', 'fas fa-envelope', 'Contato'),
);
// Conta (menu do avatar no desktop).
$navbar_links_conta = array(
    array('perfil', 'perfil.php', 'fas fa-user-circle', 'Meu Perfil'),
);
if (assinatura_modulo_ativo()) {
    $navbar_links_conta[] = array('assinatura', 'assinar.php', 'fas fa-crown', 'Assinatura');
}
$navbar_links_conta[] = array('configuracoes', 'configuracoes.php', 'fas fa-cog', 'Configurações');

function navbar_fiel_ativo($chave, $navbar_active) {
    return $chave === $navbar_active ? ' active' : '';
}

// <a> de um link da lista acima, com a classe informada. O texto vai num
// <span> próprio pra o menu lateral conseguir esconder quando recolhido;
// $com_titulo põe o texto também no title (dica ao passar o mouse nos ícones).
function navbar_fiel_link($l, $classe, $navbar_active, $navbar_base, $com_titulo = false) {
    $icone = $l[2]
        ? '<i class="' . $l[2] . ' me-2"></i>'
        : '<span class="navbar-fiel-live-col me-2"><span class="live-dot"></span></span>';
    $estilo = $l[0] === 'ao_vivo' ? ' style="color:#fb7185;"' : '';
    $titulo = $com_titulo ? ' title="' . htmlspecialchars($l[3]) . '"' : '';
    $atual = $l[0] === $navbar_active ? ' aria-current="page"' : '';
    return '<a class="' . $classe . navbar_fiel_ativo($l[0], $navbar_active) . '" href="' . $navbar_base . $l[1] . '"' . $estilo . $titulo . $atual . '>'
        . $icone . '<span class="navbar-fiel-rotulo">' . htmlspecialchars($l[3]) . '</span></a>';
}

function navbar_fiel_itens($links, $classe, $navbar_active, $navbar_base, $com_titulo = false) {
    foreach ($links as $l) {
        echo '<li>' . navbar_fiel_link($l, $classe, $navbar_active, $navbar_base, $com_titulo) . '</li>';
    }
}
?>
<script nonce="<?php echo site_csp_nonce(); ?>">
    // Aplica o estado salvo do menu lateral antes do primeiro render (sem "piscar").
    document.body.classList.add('fiel-sidebar-sem-anim');
    try {
        if (localStorage.getItem('mananciapp-fiel-sidebar') === 'recolhida') {
            document.body.classList.add('fiel-sidebar-recolhida');
        }
    } catch (e) {}
</script>
<nav class="navbar navbar-expand-lg navbar-dark w-100 navbar-fiel">
    <div class="container-fluid navbar-fiel-container">
        <button type="button" class="navbar-fiel-icon navbar-fiel-recolher d-none d-lg-inline-flex" id="fielSidebarToggle" aria-controls="fielSidebar" aria-expanded="true" aria-label="Recolher menu" title="Recolher menu">
            <i class="fas fa-bars"></i>
        </button>

        <a class="navbar-brand fw-bold d-flex align-items-center" href="<?php echo $navbar_base; ?>dashboard.php">
            <img src="<?php echo $navbar_base; ?>../../assets/logo-sm.png" alt="Logo" class="navbar-fiel-logo">
            <span class="navbar-fiel-brand-text">Missão Manancial</span>
        </a>

        <div class="d-flex align-items-center flex-nowrap navbar-fiel-actions">
            <a href="<?php echo $navbar_base; ?>ofensiva.php" class="ofensiva-chip d-none d-lg-inline-flex<?php echo $navbar_ofensiva > 0 ? '' : ' is-apagada'; ?>" title="Sua ofensiva: <?php echo (int) $navbar_ofensiva; ?> dia(s) seguido(s)">
                <i class="fas fa-fire"></i><span><?php echo (int) $navbar_ofensiva; ?></span>
            </a>

            <a href="<?php echo $navbar_base; ?>busca.php" class="text-decoration-none navbar-fiel-icon d-none d-lg-inline-flex" title="Pesquisar" aria-label="Pesquisar">
                <i class="fas fa-search"></i>
            </a>

            <button type="button" class="navbar-fiel-icon" data-biblia-abrir aria-label="Abrir Bíblia" title="Bíblia Sagrada">
                <i class="fas fa-book-bible"></i>
            </button>

            <!-- Pessoas online: ícone com contador, igual ao sino. No mobile
                 a lista abre ancorada na barra inteira (ver styles.css). -->
            <div class="dropdown navbar-fiel-online">
                <button type="button" class="navbar-fiel-icon position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Pessoas online" aria-label="Pessoas online">
                    <i class="fas fa-users"></i>
                    <span class="navbar-fiel-badge is-online" data-presenca-contador>0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end presenca-menu">
                    <div class="presenca-menu-titulo"><span class="presenca-ponto"></span>Pessoas online</div>
                    <ul class="presenca-lista" data-presenca-lista>
                        <li class="presenca-vazio">Carregando…</li>
                    </ul>
                </div>
            </div>

            <a href="<?php echo $navbar_base; ?>notificacoes.php" class="text-decoration-none position-relative navbar-fiel-icon notif-bell-link" title="Notificações" aria-label="Notificações">
                <i class="fas fa-bell"></i>
                <?php if ($navbar_total_unread > 0) { ?>
                    <span class="notif-badge navbar-fiel-badge"><?php echo $navbar_total_unread; ?></span>
                <?php } ?>
            </a>

            <!-- Desktop: menu do avatar (conta, tema, sair). auto-close="outside"
                 pra trocar o tema sem o menu fechar. -->
            <div class="dropdown d-none d-lg-block">
                <button class="btn navbar-fiel-user dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <span class="navbar-fiel-avatar"><?php echo $navbar_avatar_html; ?></span>
                    <span class="navbar-fiel-username d-none d-xl-inline"><?php echo htmlspecialchars(mb_substr($navbar_usuario_nome, 0, 16)); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end navbar-fiel-menu navbar-fiel-menu-usuario">
                    <li>
                        <a class="navbar-fiel-menu-cabecalho" href="<?php echo $navbar_base; ?>perfil.php">
                            <span class="navbar-fiel-avatar"><?php echo $navbar_avatar_html; ?></span>
                            <span>
                                <strong><?php echo htmlspecialchars($navbar_usuario_nome); ?></strong>
                                <small>Ver meu perfil</small>
                            </span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <?php navbar_fiel_itens($navbar_links_conta, 'dropdown-item', $navbar_active, $navbar_base); ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <label class="dropdown-item navbar-fiel-linha">
                            <span><i class="fas fa-sun me-2"></i>Tema claro</span>
                            <input type="checkbox" class="theme-toggle" aria-label="Alternar tema">
                        </label>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo $navbar_base; ?>sair.php?t=<?php echo logout_token(); ?>"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
                </ul>
            </div>

            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#navbarFielPainel" aria-controls="navbarFielPainel" aria-label="Abrir menu">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </div>
</nav>

<div class="biblia-modal" id="bibliaModal" role="dialog" aria-modal="true" aria-labelledby="bibliaModalTitulo" hidden>
    <div class="biblia-modal-caixa">
        <div class="biblia-modal-cabecalho">
            <div>
                <span class="biblia-modal-sobretitulo"><i class="fas fa-book-bible me-1"></i>Leitura e consulta</span>
                <h2 id="bibliaModalTitulo">Bíblia Sagrada</h2>
            </div>
            <button type="button" class="biblia-modal-fechar" data-biblia-fechar aria-label="Fechar Bíblia"><i class="fas fa-xmark"></i></button>
        </div>
        <form class="biblia-controles" data-biblia-form>
            <label>
                <span>Livro</span>
                <select data-biblia-livro aria-label="Livro bíblico"></select>
            </label>
            <label>
                <span>Capítulo</span>
                <select data-biblia-capitulo aria-label="Capítulo"></select>
            </label>
            <label>
                <span>Versão</span>
                <select data-biblia-versao aria-label="Versão da Bíblia"></select>
            </label>
            <button type="submit" class="dashboard-button dashboard-button-primary"><i class="fas fa-book-open"></i><span>Ler</span></button>
            <button type="button" class="biblia-comparar" data-biblia-comparar><i class="fas fa-code-compare"></i><span>Comparar versículo</span></button>
        </form>
        <form class="biblia-busca" data-biblia-busca-form>
            <label class="visually-hidden" for="bibliaReferencia">Buscar referência</label>
            <input id="bibliaReferencia" type="search" data-biblia-referencia placeholder="Ex.: João 3:16 ou Salmos 23" autocomplete="off">
            <button type="submit" aria-label="Buscar referência"><i class="fas fa-search"></i></button>
        </form>
        <div class="biblia-status" data-biblia-status role="status" aria-live="polite">Escolha um livro e capítulo para começar.</div>
        <div class="biblia-navegacao" aria-label="Navegação entre capítulos">
            <button type="button" class="biblia-navegacao-botao" data-biblia-anterior disabled><i class="fas fa-chevron-left"></i><span>Anterior</span></button>
            <span class="biblia-navegacao-indicador" data-biblia-indicador>Capítulo 1</span>
            <button type="button" class="biblia-navegacao-botao" data-biblia-proximo><span>Próximo</span><i class="fas fa-chevron-right"></i></button>
        </div>
        <article class="biblia-texto" data-biblia-texto>
            <p class="biblia-vazio"><i class="fas fa-book-open"></i><span>O texto bíblico aparecerá aqui.</span></p>
        </article>
        <section class="biblia-comparacao" data-biblia-comparacao hidden>
            <div class="biblia-comparacao-titulo"><i class="fas fa-code-compare"></i><strong>Comparando versões</strong></div>
            <div class="biblia-comparacao-grade" data-biblia-comparacao-grade></div>
        </section>
        <section class="biblia-favoritos" data-biblia-favoritos hidden>
            <h3><i class="fas fa-bookmark me-2"></i>Versículos marcados</h3>
            <ul data-biblia-favoritos-lista></ul>
        </section>
    </div>
</div>

<!-- Menu lateral do desktop (como o do admin). Recolhe pra só ícones pelo
     botão ☰ da barra; no mobile quem faz esse papel é o painel abaixo. -->
<aside class="fiel-sidebar d-none d-lg-flex" id="fielSidebar" aria-label="Menu principal">
    <div class="fiel-sidebar-titulo">Navegar</div>
    <ul class="fiel-sidebar-lista">
        <?php navbar_fiel_itens($navbar_links_navegar, 'fiel-sidebar-link', $navbar_active, $navbar_base, true); ?>
    </ul>

    <div class="fiel-sidebar-titulo">Sua jornada</div>
    <ul class="fiel-sidebar-lista">
        <?php navbar_fiel_itens($navbar_links_jornada, 'fiel-sidebar-link', $navbar_active, $navbar_base, true); ?>
    </ul>

    <div class="fiel-sidebar-titulo">Igreja</div>
    <ul class="fiel-sidebar-lista">
        <?php navbar_fiel_itens($navbar_links_institucionais, 'fiel-sidebar-link', $navbar_active, $navbar_base, true); ?>
    </ul>
</aside>

<!-- Painel lateral do mobile. Fica FORA da <nav> fixa de propósito, pra não
     herdar o contexto/empilhamento dela. -->
<div class="offcanvas offcanvas-end navbar-fiel-painel d-lg-none" tabindex="-1" id="navbarFielPainel" aria-label="Menu">
    <div class="offcanvas-header">
        <a href="<?php echo $navbar_base; ?>perfil.php" class="navbar-fiel-painel-usuario">
            <span class="navbar-fiel-avatar"><?php echo $navbar_avatar_html; ?></span>
            <span>
                <strong><?php echo htmlspecialchars($navbar_usuario_nome); ?></strong>
                <small>Ver meu perfil</small>
            </span>
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body">
        <div class="navbar-fiel-painel-chips">
            <a href="<?php echo $navbar_base; ?>ofensiva.php" class="ofensiva-chip<?php echo $navbar_ofensiva > 0 ? '' : ' is-apagada'; ?>" title="Sua ofensiva">
                <i class="fas fa-fire"></i><span><?php echo (int) $navbar_ofensiva; ?> <?php echo (int) $navbar_ofensiva === 1 ? 'dia' : 'dias'; ?></span>
            </a>
            <a href="<?php echo $navbar_base; ?>busca.php" class="navbar-fiel-icon ms-auto" title="Pesquisar" aria-label="Pesquisar"><i class="fas fa-search"></i></a>
            <button type="button" class="navbar-fiel-icon" data-biblia-abrir aria-label="Abrir Bíblia" title="Bíblia Sagrada"><i class="fas fa-book-bible"></i></button>
        </div>

        <div class="navbar-fiel-painel-titulo">Navegar</div>
        <ul class="navbar-fiel-painel-lista">
            <?php navbar_fiel_itens($navbar_links_navegar, 'navbar-fiel-painel-link', $navbar_active, $navbar_base); ?>
        </ul>

        <div class="navbar-fiel-painel-titulo">Minha conta</div>
        <ul class="navbar-fiel-painel-lista">
            <?php navbar_fiel_itens(array_merge($navbar_links_conta, $navbar_links_jornada), 'navbar-fiel-painel-link', $navbar_active, $navbar_base); ?>
        </ul>

        <div class="navbar-fiel-painel-titulo">Igreja</div>
        <ul class="navbar-fiel-painel-lista">
            <?php navbar_fiel_itens($navbar_links_institucionais, 'navbar-fiel-painel-link', $navbar_active, $navbar_base); ?>
        </ul>

        <div class="navbar-fiel-painel-rodape">
            <label class="navbar-fiel-painel-link navbar-fiel-linha">
                <span><i class="fas fa-sun me-2"></i>Tema claro</span>
                <input type="checkbox" class="theme-toggle" aria-label="Alternar tema">
            </label>
            <a class="navbar-fiel-painel-link text-danger" href="<?php echo $navbar_base; ?>sair.php?t=<?php echo logout_token(); ?>"><i class="fas fa-sign-out-alt me-2"></i>Sair</a>
        </div>
    </div>
</div>

<!-- Barra de navegação inferior (só no celular), como em YouTube, Instagram e
     Netflix: as telas mais usadas a um toque do polegar. "Menu" abre o
     painel lateral acima com todo o resto. -->
<nav class="fiel-barra-inferior d-lg-none" aria-label="Navegação principal">
    <?php
    $navbar_inferior = array(
        array('inicio', 'dashboard.php', 'fa-house', 'Início'),
        array('cursos', 'cursos.php', 'fa-circle-play', 'Cursos'),
        array('ao_vivo', 'ao_vivo.php', null, 'Ao vivo'),
        array('busca', 'busca.php', 'fa-magnifying-glass', 'Buscar'),
    );
    foreach ($navbar_inferior as $item) {
        $ativo = $item[0] === $navbar_active;
    ?>
        <a href="<?php echo $navbar_base . $item[1]; ?>" class="fiel-barra-inferior-item<?php echo $ativo ? ' active' : ''; ?>"<?php echo $ativo ? ' aria-current="page"' : ''; ?>>
            <?php if ($item[2]) { ?>
                <i class="fas <?php echo $item[2]; ?>" aria-hidden="true"></i>
            <?php } else { ?>
                <span class="fiel-barra-inferior-live" aria-hidden="true"><span class="live-dot"></span></span>
            <?php } ?>
            <span><?php echo $item[3]; ?></span>
        </a>
    <?php } ?>
    <button type="button" class="fiel-barra-inferior-item" data-bs-toggle="offcanvas" data-bs-target="#navbarFielPainel" aria-controls="navbarFielPainel">
        <span class="navbar-fiel-avatar fiel-barra-inferior-avatar" aria-hidden="true"><?php echo $navbar_avatar_html; ?></span>
        <span>Menu</span>
    </button>
</nav>

<!-- Barrinha de carregamento no topo ao trocar de página (como no YouTube):
     no 4G a próxima página pode levar um tempo, e sem retorno visual a
     pessoa toca de novo. -->
<div class="fiel-carregando" id="fielCarregando" aria-hidden="true"></div>
<script nonce="<?php echo site_csp_nonce(); ?>">
    (function () {
        var barra = document.getElementById('fielCarregando');
        if (!barra) return;
        var limite = null;
        function ligar() {
            barra.classList.add('is-ativa');
            // Download (ex.: "Baixar meus dados") não troca de página: some sozinha.
            clearTimeout(limite);
            limite = setTimeout(function () { barra.classList.remove('is-ativa'); }, 8000);
        }
        document.addEventListener('click', function (e) {
            var link = e.target.closest ? e.target.closest('a[href]') : null;
            if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || link.target === '_blank' || link.hasAttribute('download')) return;
            var href = link.getAttribute('href');
            if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || link.origin !== location.origin) return;
            ligar();
        });
        document.addEventListener('submit', function (e) {
            if (!e.defaultPrevented && !(e.target.getAttribute('target'))) ligar();
        });
        // Voltar pelo navegador traz a página do cache já com a barra ligada.
        window.addEventListener('pageshow', function () { barra.classList.remove('is-ativa'); });
    })();
</script>
<script nonce="<?php echo site_csp_nonce(); ?>">
    (function () {
        var btn = document.getElementById('fielSidebarToggle');
        function sincronizar() {
            var recolhida = document.body.classList.contains('fiel-sidebar-recolhida');
            btn.setAttribute('aria-expanded', recolhida ? 'false' : 'true');
            btn.setAttribute('aria-label', recolhida ? 'Expandir menu' : 'Recolher menu');
            btn.title = recolhida ? 'Expandir menu' : 'Recolher menu';
        }
        btn.addEventListener('click', function () {
            var recolhida = document.body.classList.toggle('fiel-sidebar-recolhida');
            try { localStorage.setItem('mananciapp-fiel-sidebar', recolhida ? 'recolhida' : 'aberta'); } catch (e) {}
            sincronizar();
        });
        sincronizar();
        // Libera a animação só depois do primeiro render.
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { document.body.classList.remove('fiel-sidebar-sem-anim'); });
        });
    })();
</script>
<script src="<?php echo htmlspecialchars($navbar_base . '../../js/biblia.js?v=' . site_versao_estatico()); ?>" defer></script>
<?php if ($navbar_medalhas_novas) {
    // Comemoração de medalha nova (seção 81): a medalha grande, o nível, a
    // raridade e o que fazer com ela (ver, pôr na vitrine, compartilhar).
    // Sessões antigas guardavam só o nome: vira uma medalha simples.
    $festa = array_map(function ($m) {
        return is_array($m) ? $m : array('id' => 0, 'nome' => (string) $m, 'icone' => 'fa-medal', 'nivel' => 'bronze', 'descricao' => '', 'secreta' => false);
    }, $navbar_medalhas_novas);
    $festa_principal = $festa[0];
    // Mais valiosa primeiro (diamante > ouro > prata > bronze).
    $festa_peso = array('diamante' => 4, 'ouro' => 3, 'prata' => 2, 'bronze' => 1);
    foreach ($festa as $m) {
        if (($festa_peso[$m['nivel']] ?? 1) > ($festa_peso[$festa_principal['nivel']] ?? 1)) {
            $festa_principal = $m;
        }
    }
    $festa_outras = array_values(array_filter($festa, function ($m) use ($festa_principal) { return $m !== $festa_principal; }));
    $festa_raridade = isset($conn) && $festa_principal['id'] > 0 ? (medalhas_raridade($conn)[$festa_principal['id']] ?? null) : null;
    $festa_nivel = medalhas_niveis()[$festa_principal['nivel']] ?? 'Bronze';
    $festa_vitrine = isset($conn) && $festa_principal['id'] > 0 && fiel_vitrine_disponivel($conn);
?>
    <div class="medalha-festa" id="medalhaFesta" role="dialog" aria-modal="true" aria-labelledby="medalhaFestaTitulo">
        <div class="medalha-festa-confete" aria-hidden="true"><?php for ($i = 0; $i < 18; $i++) { echo '<span></span>'; } ?></div>
        <div class="medalha-festa-caixa">
            <button type="button" class="medalha-festa-fechar" data-festa-fechar aria-label="Fechar"><i class="fas fa-xmark"></i></button>
            <small class="medalha-festa-rotulo"><?php echo !empty($festa_principal['secreta']) ? '🤫 Medalha secreta descoberta!' : 'Nova medalha conquistada!'; ?></small>
            <span class="medalha-festa-icone nivel-<?php echo htmlspecialchars($festa_principal['nivel']); ?>" aria-hidden="true"><i class="fas <?php echo htmlspecialchars($festa_principal['icone']); ?>"></i></span>
            <h2 id="medalhaFestaTitulo"><?php echo htmlspecialchars($festa_principal['nome']); ?></h2>
            <p class="medalha-festa-nivel"><?php echo htmlspecialchars($festa_nivel); ?><?php echo $festa_principal['descricao'] !== '' ? ' · ' . htmlspecialchars($festa_principal['descricao']) : ''; ?></p>
            <?php if ($festa_principal['id'] > 0) { ?>
                <p class="medalha-festa-raridade"><?php echo htmlspecialchars(medalhas_raridade_texto($festa_raridade)); ?></p>
            <?php } ?>
            <?php if ($festa_outras) { ?>
                <p class="medalha-festa-outras">E também: <?php echo htmlspecialchars(implode(', ', array_column(array_slice($festa_outras, 0, 4), 'nome')) . (count($festa_outras) > 4 ? '…' : '')); ?></p>
            <?php } ?>
            <div class="medalha-festa-acoes">
                <?php if ($festa_vitrine) { ?>
                    <form method="POST" action="<?php echo $navbar_base; ?>perfil.php#medalhas">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="vitrine">
                        <input type="hidden" name="idbadge_id" value="<?php echo (int) $festa_principal['id']; ?>">
                        <button type="submit" class="btn btn-play btn-sm"><i class="fas fa-star me-1"></i>Pôr na vitrine</button>
                    </form>
                <?php } ?>
                <a href="<?php echo $navbar_base; ?>perfil.php#medalhas" class="btn btn-outline-light btn-sm"><i class="fas fa-medal me-1"></i>Ver no perfil</a>
                <button type="button" class="btn btn-outline-light btn-sm" data-festa-compartilhar
                    data-texto="<?php echo htmlspecialchars('Conquistei a medalha "' . $festa_principal['nome'] . '" (' . $festa_nivel . ') na Missão Manancial! 🏅'); ?>"
                    data-url="<?php echo htmlspecialchars(site_url('')); ?>"><i class="fas fa-share-nodes me-1"></i>Compartilhar</button>
            </div>
        </div>
    </div>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        (function () {
            var festa = document.getElementById('medalhaFesta');
            if (!festa) return;
            var antes = document.activeElement;
            function fechar() {
                festa.classList.add('is-saindo');
                setTimeout(function () { festa.remove(); }, 250);
                document.removeEventListener('keydown', teclado);
                if (antes && antes.focus) antes.focus();
            }
            function teclado(e) { if (e.key === 'Escape') fechar(); }
            document.addEventListener('keydown', teclado);
            festa.querySelectorAll('[data-festa-fechar]').forEach(function (b) { b.addEventListener('click', fechar); });
            // Clique fora da caixa também fecha.
            festa.addEventListener('click', function (e) { if (e.target === festa) fechar(); });
            // Compartilhar: menu do celular (WhatsApp etc.) ou copia o texto.
            var botao = festa.querySelector('[data-festa-compartilhar]');
            botao.addEventListener('click', function () {
                var texto = botao.getAttribute('data-texto');
                var url = botao.getAttribute('data-url');
                if (navigator.share) {
                    navigator.share({ title: 'Missão Manancial', text: texto, url: url }).catch(function () {});
                } else if (navigator.clipboard) {
                    navigator.clipboard.writeText(texto + ' ' + url).then(function () {
                        botao.innerHTML = '<i class="fas fa-check me-1"></i>Texto copiado';
                    }).catch(function () {});
                }
            });
            festa.querySelector('.medalha-festa-fechar').focus();
        })();
    </script>
<?php } elseif ($navbar_comemorar_ofensiva && $navbar_ofensiva > 0) { ?>
    <?php if ($navbar_protegida > 0) { ?>
        <a href="<?php echo $navbar_base; ?>ofensiva.php" class="ofensiva-toast protetor-toast" id="ofensivaToast" role="status">
            <i class="fas fa-shield-heart"></i>
            <div>
                <strong>Seu protetor salvou a ofensiva!</strong>
                <small><?php echo $navbar_protegida === 1 ? 'Você não entrou ontem' : 'Você ficou ' . $navbar_protegida . ' dias sem entrar'; ?>, mas a sequência continua: <?php echo (int) $navbar_ofensiva; ?> dias.</small>
            </div>
        </a>
    <?php } elseif ($navbar_protetor_ganho) { ?>
        <a href="<?php echo $navbar_base; ?>ofensiva.php" class="ofensiva-toast protetor-toast" id="ofensivaToast" role="status">
            <i class="fas fa-shield-halved"></i>
            <div>
                <strong><?php echo (int) $navbar_ofensiva; ?> dias seguidos: ganhou um protetor!</strong>
                <small>Se um dia você não conseguir entrar, ele guarda sua ofensiva.</small>
            </div>
        </a>
    <?php } else { ?>
    <a href="<?php echo $navbar_base; ?>ofensiva.php" class="ofensiva-toast" id="ofensivaToast" role="status">
        <i class="fas fa-fire"></i>
        <div>
            <strong><?php echo $navbar_ofensiva === 1 ? 'Ofensiva iniciada!' : (int) $navbar_ofensiva . ' dias seguidos!'; ?></strong>
            <small><?php echo $navbar_ofensiva === 1 ? 'Volte amanhã pra manter a chama acesa.' : 'Continue entrando todo dia pra não perder sua ofensiva.'; ?></small>
        </div>
    </a>
    <?php } ?>
    <script nonce="<?php echo site_csp_nonce(); ?>">setTimeout(function () { var t = document.getElementById('ofensivaToast'); if (t) t.classList.add('is-saindo'); }, 6000);</script>
<?php } ?>
<script src="<?php echo htmlspecialchars(site_url('js/live-indicator.js') . '?v=' . site_versao_estatico()); ?>" data-status-url="<?php echo htmlspecialchars(site_url('ajax_transmissao_status.php')); ?>"></script>
<script src="<?php echo htmlspecialchars(site_url('js/presenca.js') . '?v=' . site_versao_estatico()); ?>" data-presenca-url="<?php echo htmlspecialchars(site_url('ajax_presenca.php')); ?>"></script>
