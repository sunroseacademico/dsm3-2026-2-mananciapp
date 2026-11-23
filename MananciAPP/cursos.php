<?php
require __DIR__ . '/php/conexao.php';

// Catálogo público: os cursos publicados de verdade (antes eram 4 cartões
// fixos de exemplo). As linhas do banco ficam 5 min no cache compartilhado
// (página aberta por quem ainda nem tem conta); a URL da capa no R2 é
// assinada a cada visita, porque vence.
$catalogo = $conn ? site_cache_compartilhado('catalogo_publico', 300, function () use ($conn) {
    $res = mysqli_query($conn, "
        SELECT c.IDC_ID, c.IDC_TITULO, c.IDC_DESCRICAO, c.IDC_CAPA_URL, cat.IDCAT_NOME,
               (SELECT COUNT(*) FROM ID_CONTENT ct WHERE ct.IDC_ID = c.IDC_ID) AS AULAS
        FROM ID_CURSO c
        LEFT JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = c.IDC_CATEGORIA_ID
        WHERE c.IDC_STATUS = 'PUBLICADO'
        ORDER BY c.IDC_DESTAQUE DESC, c.IDC_CRIADO_EM DESC
        LIMIT 24
    ");
    if (!$res) {
        return false;
    }
    $lista = array();
    while ($linha = mysqli_fetch_assoc($res)) {
        $lista[] = $linha;
    }
    return $lista;
}) : array();
$catalogo = is_array($catalogo) ? $catalogo : array();

function catalogo_capa($capa) {
    $capa = trim((string) $capa);
    if (strpos($capa, 'r2:') === 0) {
        return r2_criar_url_assinada('GET', substr($capa, 3), 14400) ?: '';
    }
    return (preg_match('#^https://#i', $capa) || strpos($capa, 'assets/') === 0) ? $capa : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursos - Missão Manancial</title>
    <?php echo site_meta_publica('Cursos - Missão Manancial', 'Cursos em vídeo da Missão Manancial: fundamentos da fé, estudo bíblico, discipulado e vida em comunidade. Crie sua conta grátis.', 'cursos.php'); ?>
    <link href="css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="css/inicio.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/php/includes/pwa_head.php'; ?>
    <style>
        :root {
            --primary: #60a5fa;
            --secondary: #a78bfa;
            --accent: #ec4899;
            --bg-dark: #060816;
            --bg-light: #111827;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --card-bg: rgba(17, 24, 39, 0.78);
            --border-color: rgba(255,255,255,0.12);
            --surface: rgba(17, 24, 39, 0.9);
            --surface-strong: rgba(255,255,255,0.06);
            --gutter: clamp(1.25rem, 5vw, 6rem);
            /* Sem largura máxima: header e rodapé ocupam a tela toda, igual à
               landing page (index.html), só com a margem lateral (gutter). */
            --container: none;
        }

        :root[data-theme="light"] {
            --bg-dark: #f8fafc;
            --bg-light: #eef2ff;
            --text-main: #0f172a;
            --text-muted: #475569;
            --card-bg: rgba(255, 255, 255, 0.92);
            --border-color: rgba(15, 23, 42, 0.12);
            --surface: rgba(255, 255, 255, 0.9);
            --surface-strong: rgba(15, 23, 42, 0.04);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            background:
                radial-gradient(circle at top left, rgba(96,165,250,0.18), transparent 28%),
                linear-gradient(135deg, var(--bg-dark) 0%, var(--bg-light) 100%);
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: all 0.3s ease;
            line-height: 1.6;
        }

        header {
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(16px);
            background: rgba(6, 8, 22, 0.78);
            border-bottom: 1px solid var(--border-color);
        }

        :root[data-theme="light"] header {
            background: rgba(255, 255, 255, 0.85);
            border-bottom-color: rgba(15, 23, 42, 0.1);
        }

        .header-shell {
            max-width: var(--container);
            margin: 0 auto;
            padding: 0.65rem var(--gutter);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
        }

        .header { display: flex; align-items: center; gap: 0.6rem; }
        .header img { height: 34px; filter: drop-shadow(0 4px 12px rgba(96,165,250,0.35)); }
        .header h2 {
            font-size: 0.95rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--text-main);
            white-space: nowrap;
        }

        /* inicio.css estica o nav (flex: 1) e centraliza os links — aqui
           eles ficam alinhados à direita, igual à landing page. */
        nav { display: flex; align-items: center; gap: 0.6rem; flex-wrap: nowrap; flex: 0 1 auto; justify-content: flex-end; }
        nav a {
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.88rem;
            white-space: nowrap;
            transition: all 0.25s ease;
        }
        nav a:hover { color: var(--text-main); transform: translateY(-1px); }

        .nav-login, .nav-register {
            padding: 0.5rem 0.85rem;
            border-radius: 999px;
            border: 1px solid transparent;
        }

        .nav-login {
            color: var(--primary);
            border-color: rgba(96,165,250,0.35);
        }

        .nav-register {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #ffffff;
            box-shadow: 0 10px 24px rgba(96,165,250,0.24);
        }

        .nav-live { color: #fb7185 !important; }
        .nav-download { color: #4ade80 !important; }
        .nav-download:hover { color: #22c55e !important; }

        .live-dot {
            position: relative;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #fb7185;
            flex-shrink: 0;
        }

        .live-dot::before {
            /* content: none (em vez de '') não gera o pseudo-elemento
               nenhum — o ponto fica com cor fixa, sem anel pulsando, até
               js/live-indicator.js confirmar que tem transmissão AO_VIVO
               agora e adicionar a classe abaixo. */
            content: none;
            position: absolute;
            inset: -5px;
            border-radius: 50%;
            background: rgba(251,113,133,0.35);
            animation: live-pulse 1.6s ease-out infinite;
        }

        .live-dot.is-ao-vivo::before {
            content: '';
        }

        @keyframes live-pulse {
            0% { transform: scale(0.6); opacity: 0.9; }
            100% { transform: scale(2.4); opacity: 0; }
        }

        .theme-toggle-container {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.25rem 0.45rem;
            border-radius: 999px;
            background: var(--surface-strong);
            border: 1px solid var(--border-color);
        }

        .theme-toggle {
            appearance: none;
            -webkit-appearance: none;
            position: relative;
            width: 54px;
            height: 30px;
            border-radius: 999px;
            border: 1px solid var(--border-color);
            background: var(--surface-strong);
            cursor: pointer;
            transition: all 0.25s ease;
            flex-shrink: 0;
            padding: 0;
        }

        .theme-toggle::before {
            content: '';
            position: absolute;
            top: 4px;
            left: 4px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--knob, #ffffff);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
            transition: transform 0.25s ease;
        }

        .theme-toggle:checked { background: linear-gradient(90deg, var(--accent), var(--primary)); }
        .theme-toggle:checked::before { transform: translateX(24px); }
        .theme-toggle:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
        .theme-icon { color: var(--text-main); font-size: 0.95rem; }

        main { max-width: 1400px; margin: 0 auto; padding: 0 var(--gutter); }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.9rem;
            border-radius: 999px;
            font-size: 0.85rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-muted);
            background: rgba(96,165,250,0.14);
            border: 1px solid rgba(96,165,250,0.24);
            margin-bottom: 1.2rem;
        }

        .pagina-intro {
            text-align: center;
            padding: 4rem 0 3rem;
        }

        .pagina-intro h1 {
            font-size: clamp(2rem, 4vw, 2.8rem);
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--text-main) 0%, var(--accent) 46%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .pagina-intro p {
            font-size: 1.05rem;
            color: var(--text-muted);
            max-width: 640px;
            margin: 0 auto;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.2rem;
            padding-bottom: 3rem;
        }

        .content-card {
            padding: 1.3rem;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            transition: transform 0.25s ease, border-color 0.25s ease;
        }

        :root[data-theme="light"] .content-card {
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
        }

        /* Cartão de um curso real (capa + texto). */
        a.content-card { display: flex; flex-direction: column; padding: 0; overflow: hidden; text-decoration: none; }
        .content-card-capa { aspect-ratio: 16 / 9; background: linear-gradient(135deg, rgba(59,130,246,0.35), rgba(167,139,250,0.35)); display: grid; place-items: center; }
        .content-card-capa img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .content-card-capa i { margin: 0; font-size: 2.2rem; opacity: 0.8; }
        .content-card-corpo { padding: 1rem 1.2rem 1.2rem; display: flex; flex-direction: column; gap: 0.4rem; flex: 1; }
        .content-card-meta { font-size: 0.8rem; color: var(--text-muted); display: flex; gap: 0.8rem; flex-wrap: wrap; margin-top: auto; }
        .content-card-meta i { font-size: 0.8rem; margin: 0 0.25rem 0 0; }

        .content-card:hover {
            transform: translateY(-4px);
            border-color: rgba(96,165,250,0.36);
        }

        .content-card i {
            font-size: 1.6rem;
            color: var(--primary);
            margin-bottom: 0.9rem;
        }

        .content-card h3 {
            font-size: 1.08rem;
            color: var(--text-main);
            margin-bottom: 0.45rem;
        }

        .content-card p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        .btn-primary-pill, .btn-secondary-pill {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            padding: 0.95rem 1.5rem;
            border-radius: 999px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.25s ease;
            font-size: 1rem;
        }

        .btn-primary-pill {
            color: #ffffff;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 14px 26px rgba(96,165,250,0.24);
        }

        .btn-primary-pill:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 30px rgba(96,165,250,0.32);
            color: #ffffff;
        }

        .btn-secondary-pill {
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            background: var(--surface);
        }

        .btn-secondary-pill:hover {
            color: var(--text-main);
            background: rgba(255,255,255,0.06);
            transform: translateY(-2px);
        }

        .cta-final {
            text-align: center;
            padding: 1rem 0 4rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        footer {
            border-top: 1px solid var(--border-color);
            padding: 2.2rem var(--gutter) 1.5rem;
            margin-top: 2rem;
            background: var(--footer-bg);
            backdrop-filter: blur(6px);
        }

        .footer-content {
            max-width: var(--container);
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
        }

        .footer-section h4 { color: var(--text-main); margin-bottom: 0.7rem; }
        .footer-section p, .footer-section a {
            color: var(--text-muted);
            text-decoration: none;
            display: block;
            margin-bottom: 0.45rem;
        }
        .footer-section a:hover { color: var(--text-main); }

        .footer-bottom {
            max-width: var(--container);
            margin: 1.2rem auto 0;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        @media (max-width: 900px) {
            .header-shell { flex-direction: column; padding: 0.75rem 1rem; }
            nav { justify-content: center; flex-wrap: wrap; }
        }

        @media (max-width: 576px) {
            main { padding: 0 1.2rem; }
            .header-shell { padding: 1rem; }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-shell">
            <div class="header">
                <img src="assets/logo-sm.png" alt="Logo Missão Manancial">
                <h2>Missão Manancial</h2>
            </div>
            <nav>
                <a href="index.html"><i class="fas fa-home"></i> Home</a>
                <a href="cursos.php"><i class="fas fa-book"></i> Cursos</a>
                <a href="sobre.php"><i class="fas fa-heart"></i> Sobre</a>
                <a href="ao-vivo.php" class="nav-live"><span class="live-dot"></span> Ao Vivo</a>
                <a href="como-instalar.php" class="nav-download"><i class="fas fa-download"></i> Baixar App</a>
                <a href="login.php" class="nav-login"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="php/fiel/register.php" class="nav-register"><i class="fas fa-user-plus"></i> Criar Conta</a>
                <div class="theme-toggle-container">
                    <i class="fas fa-moon theme-icon"></i>
                    <input type="checkbox" id="theme-toggle" class="theme-toggle" aria-label="Alternar tema">
                    <i class="fas fa-sun theme-icon"></i>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <section class="pagina-intro">
            <span class="pill"><i class="fas fa-book-open"></i> Conteúdos + comunidade</span>
            <h1>Conteúdos em destaque</h1>
            <p>Uma seleção pensada para inspirar, ensinar e aproximar cada pessoa de uma experiência mais profunda de fé.</p>
        </section>

        <?php if ($catalogo) { ?>
        <section class="cards-grid">
            <?php foreach ($catalogo as $curso) { $capa = catalogo_capa($curso['IDC_CAPA_URL']); $aulas = (int) $curso['AULAS']; ?>
                <a class="content-card" href="php/fiel/curso.php?id=<?php echo (int) $curso['IDC_ID']; ?>">
                    <div class="content-card-capa">
                        <?php if ($capa !== '') { ?>
                            <img src="<?php echo htmlspecialchars($capa); ?>" alt="" loading="lazy" decoding="async" width="480" height="270">
                        <?php } else { ?>
                            <i class="fas fa-play-circle" aria-hidden="true"></i>
                        <?php } ?>
                    </div>
                    <div class="content-card-corpo">
                        <h3><?php echo htmlspecialchars($curso['IDC_TITULO']); ?></h3>
                        <?php if (trim((string) $curso['IDC_DESCRICAO']) !== '') { ?>
                            <p><?php echo htmlspecialchars($curso['IDC_DESCRICAO']); ?></p>
                        <?php } ?>
                        <div class="content-card-meta">
                            <?php if ($aulas > 0) { ?><span><i class="fas fa-video" aria-hidden="true"></i><?php echo $aulas; ?> <?php echo $aulas === 1 ? 'aula' : 'aulas'; ?></span><?php } ?>
                            <?php if (!empty($curso['IDCAT_NOME'])) { ?><span><i class="fas fa-tag" aria-hidden="true"></i><?php echo htmlspecialchars($curso['IDCAT_NOME']); ?></span><?php } ?>
                        </div>
                    </div>
                </a>
            <?php } ?>
        </section>
        <p class="text-center mb-4" style="color: var(--text-muted);">Para assistir, entre ou crie sua conta grátis — o cadastro é aprovado pela igreja.</p>
        <?php } else { ?>
        <!-- Sem curso publicado ainda (ou banco fora do ar): os temas de antes. -->
        <section class="cards-grid">
            <div class="content-card">
                <i class="fas fa-hands-praying"></i>
                <h3>Fundamentos da Fé</h3>
                <p>Aprenda os princípios essenciais da Bíblia com clareza e aplicabilidade.</p>
            </div>
            <div class="content-card">
                <i class="fas fa-users"></i>
                <h3>Vida em Comunidade</h3>
                <p>Descubra como viver com propósito, amor e crescimento conjunto.</p>
            </div>
            <div class="content-card">
                <i class="fas fa-book-bible"></i>
                <h3>Estudo Bíblico</h3>
                <p>Explore materiais aprofundados para fortalecer sua caminhada espiritual.</p>
            </div>
            <div class="content-card">
                <i class="fas fa-heart"></i>
                <h3>Discipulado</h3>
                <p>Fortaleça sua vida espiritual com conteúdos transformadores e práticos.</p>
            </div>
        </section>
        <?php } ?>

        <section class="cta-final">
            <a href="index.html" class="btn-secondary-pill"><i class="fas fa-arrow-left"></i> Voltar para o site</a>
            <a href="php/fiel/register.php" class="btn-primary-pill"><i class="fas fa-user-plus"></i> Criar conta</a>
        </section>
    </main>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h4><i class="fas fa-church me-2"></i>Missão Manancial</h4>
                <p>Uma plataforma dedicada ao crescimento espiritual, à comunidade e à transmissão de conteúdos de valor.</p>
            </div>
            <div class="footer-section">
                <h4><i class="fas fa-link me-2"></i>Links rápidos</h4>
                <a href="index.html">Home</a>
                <a href="cursos.php">Conteúdos</a>
                <a href="sobre.php">Sobre</a>
                <a href="login.php">Login</a>
                <a href="php/fiel/register.php">Criar conta</a>
            </div>
            <div class="footer-section">
                <h4><i class="fas fa-hands-praying me-2"></i>Contato</h4>
                <?php echo site_rodape_contato_html(); ?>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024-2026 <?php echo htmlspecialchars(igreja_info()['nome_completo']); ?>. Todos os direitos reservados.</p>
        </div>
    </footer>

    <script src="js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <script src="js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
    <script src="js/live-indicator.js?v=<?php echo site_versao_estatico(); ?>" data-status-url="ajax_transmissao_status.php"></script>
    <script src="js/menu-publico.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
