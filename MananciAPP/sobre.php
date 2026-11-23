<?php
require __DIR__ . '/php/conexao.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre - Missão Manancial</title>
    <?php echo site_meta_publica('Sobre a Missão Manancial', 'Conheça a Missão Evangélica Manancial da Esperança e a plataforma de cursos, lives e comunidade para o seu crescimento espiritual.', 'sobre.php'); ?>
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
            padding: 4rem 0 2rem;
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

        .section-heading {
            text-align: center;
            margin: 1rem 0 1.6rem;
        }

        .section-heading h2 {
            font-size: 1.6rem;
            color: var(--text-main);
            margin: 0 0 0.5rem;
        }

        .section-heading p {
            color: var(--text-muted);
            max-width: 600px;
            margin: 0 auto;
        }

        .testimonial-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            padding-bottom: 3rem;
        }

        .quote-card {
            padding: 1.2rem;
            border-radius: 18px;
            background: var(--surface-strong);
            border: 1px solid var(--border-color);
        }

        .sobre-grade {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            padding-bottom: 2.5rem;
        }

        .quote-card h3 { font-size: 1.1rem; margin-bottom: 0.6rem; color: var(--text-main); }
        .sobre-texto { color: var(--text-muted); font-size: 1.05rem; line-height: 1.6; max-width: 70ch; margin: 0 auto; }
        .sobre-pastor { margin-top: 1rem; font-weight: 600; color: var(--text-main); }
        .sobre-lista { color: var(--text-muted); padding-left: 1.1rem; margin: 0; line-height: 1.6; }
        .sobre-link { display: flex; align-items: center; min-height: 44px; color: var(--primary); font-weight: 600; text-decoration: none; }

        .quote-card p { color: var(--text-muted); margin-bottom: 0.8rem; }
        .quote-card strong { color: var(--text-main); display: block; }

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
        <?php
        // Dados reais da igreja (Admin → Identidade da igreja). Antes ficavam
        // aqui três "depoimentos" inventados ("— Usuário fiel"), que soavam
        // falsos justo antes de a pessoa dar CPF e fazer Pix.
        $igreja = igreja_info();
        $horarios = igreja_horarios();
        $zap = igreja_whatsapp_link('Olá! Vi o site e gostaria de saber mais sobre a igreja.');
        $redes = array_filter(array(
            'instagram' => array('fab fa-instagram', 'Instagram'),
            'youtube' => array('fab fa-youtube', 'YouTube'),
            'facebook' => array('fab fa-facebook', 'Facebook'),
            'site' => array('fas fa-globe', 'Site da igreja'),
        ), function ($chave) use ($igreja) { return $igreja[$chave] !== ''; }, ARRAY_FILTER_USE_KEY);
        ?>
        <section class="pagina-intro">
            <span class="pill"><i class="fas fa-church" aria-hidden="true"></i> Quem somos</span>
            <h1><?php echo htmlspecialchars($igreja['nome_completo'] !== '' ? $igreja['nome_completo'] : $igreja['nome']); ?></h1>
            <?php if ($igreja['quem_somos'] !== '') { ?>
                <div class="sobre-texto"><?php echo nl2br(htmlspecialchars($igreja['quem_somos'])); ?></div>
            <?php } else { ?>
                <p>Uma igreja que usa esta plataforma para reunir cursos em vídeo, cultos ao vivo, devocionais e a comunidade num só lugar, para você crescer na fé no seu ritmo.</p>
            <?php } ?>
            <?php if ($igreja['pastor'] !== '') { ?>
                <p class="sobre-pastor"><i class="fas fa-user-tie me-2" aria-hidden="true"></i><?php echo htmlspecialchars($igreja['pastor']); ?></p>
            <?php } ?>
        </section>

        <?php if ($horarios || $igreja['endereco'] !== '' || $zap !== '' || $redes || $igreja['telefone'] !== '' || $igreja['email'] !== '') { ?>
        <section>
            <div class="section-heading">
                <h2><i class="fas fa-door-open me-2" aria-hidden="true"></i>Venha nos visitar</h2>
            </div>
            <div class="sobre-grade">
                <?php if ($horarios) { ?>
                    <div class="quote-card">
                        <h3><i class="fas fa-clock me-2" aria-hidden="true"></i>Cultos</h3>
                        <ul class="sobre-lista">
                            <?php foreach ($horarios as $h) { ?><li><?php echo htmlspecialchars($h); ?></li><?php } ?>
                        </ul>
                    </div>
                <?php } ?>
                <?php if ($igreja['endereco'] !== '') { ?>
                    <div class="quote-card">
                        <h3><i class="fas fa-location-dot me-2" aria-hidden="true"></i>Onde estamos</h3>
                        <p><?php echo htmlspecialchars($igreja['endereco']); ?></p>
                        <?php if ($igreja['mapa_url'] !== '') { ?>
                            <a class="sobre-link" href="<?php echo htmlspecialchars($igreja['mapa_url']); ?>" target="_blank" rel="noopener"><i class="fas fa-map me-2" aria-hidden="true"></i>Ver no mapa</a>
                        <?php } ?>
                    </div>
                <?php } ?>
                <?php if ($zap !== '' || $redes || $igreja['telefone'] !== '' || $igreja['email'] !== '') { ?>
                    <div class="quote-card">
                        <h3><i class="fas fa-heart me-2" aria-hidden="true"></i>Fale com a gente</h3>
                        <?php if ($zap !== '') { ?>
                            <a class="sobre-link" href="<?php echo htmlspecialchars($zap); ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp me-2" aria-hidden="true"></i>WhatsApp</a>
                        <?php } ?>
                        <?php if ($igreja['telefone'] !== '') { ?>
                            <p class="mb-1"><i class="fas fa-phone me-2" aria-hidden="true"></i><?php echo htmlspecialchars($igreja['telefone']); ?></p>
                        <?php } ?>
                        <?php if ($igreja['email'] !== '') { ?>
                            <a class="sobre-link" href="mailto:<?php echo htmlspecialchars($igreja['email']); ?>"><i class="fas fa-envelope me-2" aria-hidden="true"></i><?php echo htmlspecialchars($igreja['email']); ?></a>
                        <?php } ?>
                        <?php foreach ($redes as $chave => $rede) { ?>
                            <a class="sobre-link" href="<?php echo htmlspecialchars($igreja[$chave]); ?>" target="_blank" rel="noopener"><i class="<?php echo $rede[0]; ?> me-2" aria-hidden="true"></i><?php echo $rede[1]; ?></a>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </section>
        <?php } ?>

        <section>
            <div class="section-heading">
                <h2><i class="fas fa-mobile-screen-button me-2" aria-hidden="true"></i>O que você encontra no app</h2>
            </div>
            <div class="sobre-grade">
                <div class="quote-card"><h3><i class="fas fa-graduation-cap me-2" aria-hidden="true"></i>Cursos em vídeo</h3><p>Estudos completos para assistir no seu ritmo, com certificado no fim.</p></div>
                <div class="quote-card"><h3><i class="fas fa-tower-broadcast me-2" aria-hidden="true"></i>Cultos ao vivo</h3><p>Assista aos cultos de onde estiver e participe pelo chat.</p></div>
                <div class="quote-card"><h3><i class="fas fa-hands-praying me-2" aria-hidden="true"></i>Comunidade</h3><p>Devocionais, testemunhos e pedidos de oração com a sua igreja.</p></div>
            </div>
        </section>

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
