<?php
require __DIR__ . '/php/conexao.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Como Instalar o Aplicativo - Missão Manancial</title>
    <?php echo site_meta_publica('Instale o app da Missão Manancial', 'Passo a passo para instalar o aplicativo da Missão Manancial no Android, no iPhone e no computador.', 'como-instalar.php'); ?>
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

        main { max-width: 1100px; margin: 0 auto; padding: 0 var(--gutter); }

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

        .hero-instalar {
            text-align: center;
            padding: 4rem 0 2rem;
        }

        .hero-instalar h1 {
            font-size: clamp(2rem, 4vw, 2.8rem);
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--text-main) 0%, var(--accent) 46%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-instalar p {
            font-size: 1.05rem;
            color: var(--text-muted);
            max-width: 640px;
            margin: 0 auto 2rem;
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

        .estado-vazio {
            text-align: center;
            color: var(--text-muted);
            padding: 3rem 0;
        }

        .estado-vazio i { font-size: 2.5rem; margin-bottom: 1rem; color: var(--primary); }

        .secao-tutorial { padding: 2.5rem 0; }

        .section-title {
            font-weight: 800;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 1.6rem;
        }

        .section-title small { color: var(--text-muted); font-weight: 400; font-size: 1rem; }

        .passos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.25rem;
        }

        .passo-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 1.4rem;
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .passo-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.18);
        }

        .passo-texto {
            display: flex;
            gap: 0.9rem;
            align-items: flex-start;
        }

        .passo-numero {
            flex: 0 0 auto;
            width: 2.1rem;
            height: 2.1rem;
            border-radius: 50%;
            background: rgba(96,165,250,0.16);
            color: var(--primary);
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .passo-card p { color: var(--text-main); margin: 0; font-size: 0.95rem; }
        .passo-card p strong { color: var(--primary); }

        /* Prints do tutorial (placeholders — substituir pelos prints reais do Manancial) */
        .tutorial-print {
            display: block;
            max-height: 380px;
            width: auto;
            max-width: 100%;
            margin: 0 auto;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            cursor: zoom-in;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .tutorial-print:hover {
            transform: scale(1.02);
            box-shadow: 0 12px 28px rgba(0,0,0,0.25);
        }

        /* Botão "!" de ajuda ao lado do título de cada dispositivo */
        .btn-ajuda-tutorial {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
            width: 1.6rem;
            height: 1.6rem;
            margin-left: 0.4rem;
            border: none;
            border-radius: 50%;
            background: rgba(96,165,250,0.16);
            color: var(--primary);
            font-weight: 700;
            font-size: 0.9rem;
            line-height: 1;
            cursor: pointer;
        }

        .btn-ajuda-tutorial:hover,
        .btn-ajuda-tutorial:focus {
            background: rgba(96,165,250,0.3);
        }

        /* Lightbox: amplia o print do tutorial em tela cheia ao clicar */
        .lightbox-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 3000;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: rgba(6, 8, 22, 0.92);
            cursor: zoom-out;
        }

        .lightbox-overlay.aberto { display: flex; }

        .lightbox-overlay .lightbox-imagem {
            max-width: 100%;
            max-height: 100%;
            border-radius: 0.75rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            cursor: default;
        }

        .lightbox-fechar {
            position: fixed;
            top: 1.25rem;
            right: 1.5rem;
            border: none;
            background: transparent;
            color: #fff;
            font-size: 2.5rem;
            line-height: 1;
            cursor: pointer;
            opacity: 0.85;
        }

        .lightbox-fechar:hover { opacity: 1; }

        .voltar-dispositivo { text-align: center; margin-top: 2rem; }

        .cta-final {
            text-align: center;
            padding: 3rem 0 4rem;
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

        .footer-section h4 {
            color: var(--text-main);
            margin-bottom: 0.7rem;
        }

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

        /* Modal — Bootstrap vem claro por padrão; aqui adaptamos pro tema escuro/claro do site */
        .modal-content {
            background: var(--bg-light);
            color: var(--text-main);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
        }

        .modal-header { border-bottom-color: var(--border-color); }
        .btn-close { filter: var(--bs-btn-close-white, none); }
        :root:not([data-theme="light"]) .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }

        .plataforma-opcao {
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            background: var(--surface-strong);
            color: var(--text-main);
            font-weight: 600;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .plataforma-opcao i { color: var(--primary); }

        .plataforma-opcao:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(0,0,0,0.2);
            border-color: rgba(96,165,250,0.4);
            background: var(--surface-strong);
            color: var(--text-main);
        }

        .d-none { display: none !important; }

        @media (max-width: 900px) {
            .header-shell {
                flex-direction: column;
                padding: 0.75rem 1rem;
            }

            nav {
                justify-content: center;
                flex-wrap: wrap;
            }
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
        <section class="hero-instalar">
            <span class="pill"><i class="fas fa-mobile-screen-button"></i> Instalação do App (PWA)</span>
            <h1>Como instalar o aplicativo</h1>
            <p>Escolha o seu dispositivo para ver o passo a passo certo. Não precisa de loja de aplicativos — a instalação é feita direto pelo navegador, em menos de um minuto.</p>
            <button type="button" class="btn-primary-pill" data-bs-toggle="modal" data-bs-target="#modalPlataforma">
                <i class="fas fa-mobile-screen-button"></i> Escolher meu dispositivo
            </button>
        </section>

        <section class="estado-vazio" id="estadoVazio">
            <i class="fas fa-circle-up d-block"></i>
            <p>Toque no botão acima e escolha <strong>Android</strong>, <strong>iPhone/iPad</strong> ou <strong>Desktop</strong> para ver o tutorial.</p>
        </section>

        <!-- ANDROID -->
        <section class="secao-tutorial d-none" id="tutorialAndroid" data-tutorial>
            <h2 class="section-title">Android <small>(Chrome, Edge ou Samsung Internet)</small>
                <button type="button" class="btn-ajuda-tutorial" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top" data-bs-content="Caso não esteja enxergando, clique na imagem para deixar em tela cheia." tabindex="0" aria-label="Ajuda">!</button>
            </h2>
            <div class="passos-grid">
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">1</span>
                        <p>Na tela inicial do Android, toque no ícone do <strong>Chrome</strong>.</p>
                    </div>
                    <img src="assets/img/android-passo-1.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Tela inicial do Android com o ícone do Chrome em destaque">
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">2</span>
                        <p>Acesse o site da <strong>Missão Manancial</strong> e toque no menu <strong>⋮</strong> (três pontinhos), no canto superior direito do navegador.</p>
                    </div>
                    <img src="assets/img/android-passo-2.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Navegador Chrome com o menu de três pontinhos em destaque">
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">3</span>
                        <p>No menu que abrir, toque em <strong>"Instalar e criar atalho"</strong>.</p>
                    </div>
                    <img src="assets/img/android-passo-3.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Menu do Chrome com a opção Instalar e criar atalho em destaque">
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">4</span>
                        <p>Confirme tocando em <strong>"Adicionar"</strong> na caixa de diálogo que aparece.</p>
                    </div>
                    <img src="assets/img/android-passo-4.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Caixa de diálogo Criar atalho com o botão Adicionar em destaque">
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">5</span>
                        <p>Pronto! O ícone da <strong>Missão Manancial</strong> aparece na tela inicial do seu Android.</p>
                    </div>
                    <img src="assets/img/android-passo-5.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Tela inicial do Android com o ícone do app em destaque">
                </div>
            </div>
            <div class="voltar-dispositivo">
                <button type="button" class="btn-secondary-pill" data-bs-toggle="modal" data-bs-target="#modalPlataforma">
                    <i class="fas fa-rotate"></i> Ver tutorial de outro dispositivo
                </button>
            </div>
        </section>

        <!-- IOS -->
        <section class="secao-tutorial d-none" id="tutorialIos" data-tutorial>
            <h2 class="section-title">iPhone / iPad <small>(Safari)</small>
                <button type="button" class="btn-ajuda-tutorial" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top" data-bs-content="Caso não esteja enxergando, clique na imagem para deixar em tela cheia." tabindex="0" aria-label="Ajuda">!</button>
            </h2>
            <!-- Prints ainda são os do projeto de referência (placeholders) — substituir pelos prints reais do Manancial. -->
            <div class="passos-grid">
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">1</span>
                        <p>Na tela inicial do iPhone, toque no ícone do <strong>Safari</strong> (precisa ser o Safari — outros navegadores no iOS não têm esse recurso).</p>
                    </div>
                    <img src="assets/img/ios-passo-1.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Tela inicial do iPhone com o ícone do Safari em destaque">
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">2</span>
                        <p>Acesse o site da <strong>Missão Manancial</strong> pelo navegador.</p>
                    </div>
                    <?php /* Sem print: o ios-passo-2.jpg é de outro projeto ("Registro de Ponto"). Falta um print da tela de login do app (pontos_gerais.md, seção 6). */ ?>
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">3</span>
                        <p>Certifique-se de estar na <strong>tela de Login</strong> antes de continuar — é a partir dela que o aplicativo abrirá depois de instalado.</p>
                    </div>
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">4</span>
                        <p>Toque no botão <strong>"•••"</strong> (mais opções), no canto inferior direito da barra do Safari.</p>
                    </div>
                    <img src="assets/img/ios-passo-2.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Barra inferior do Safari com o botão de mais opções (três pontos) em destaque">
                    <?php /* O print é de outro site, mas o que importa nele é o botão "•••" destacado. Trocar junto com o do passo 2. */ ?>
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">5</span>
                        <p>No menu que abrir, toque em <strong><i class="fas fa-arrow-up-from-bracket"></i> Compartilhar</strong>.</p>
                    </div>
                    <img src="assets/img/ios-passo-7.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Menu do Safari com a opção Compartilhar em destaque">
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">6</span>
                        <p>Toque em <strong>"Ver Mais"</strong> para ver todas as opções disponíveis.</p>
                    </div>
                    <img src="assets/img/ios-passo-3.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Menu de compartilhar com a opção Ver Mais em destaque">
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">7</span>
                        <p>Role a lista e toque em <strong>"Adicionar à Tela de Início"</strong>.</p>
                    </div>
                    <img src="assets/img/ios-passo-4.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Lista de opções com Adicionar à Tela de Início em destaque">
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">8</span>
                        <p>Confira o nome do app e toque em <strong>"Adicionar"</strong>, no canto superior direito, para confirmar.</p>
                    </div>
                    <img src="assets/img/ios-passo-5.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Tela de confirmação com o botão Adicionar em destaque">
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">9</span>
                        <p>Pronto! O ícone da <strong>Missão Manancial</strong> aparece na tela inicial do seu iPhone.</p>
                    </div>
                    <img src="assets/img/ios-passo-6.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Tela inicial do iPhone com o ícone do app em destaque">
                </div>
            </div>
            <div class="voltar-dispositivo">
                <button type="button" class="btn-secondary-pill" data-bs-toggle="modal" data-bs-target="#modalPlataforma">
                    <i class="fas fa-rotate"></i> Ver tutorial de outro dispositivo
                </button>
            </div>
        </section>

        <!-- DESKTOP -->
        <section class="secao-tutorial d-none" id="tutorialDesktop" data-tutorial>
            <h2 class="section-title">Desktop <small>(Chrome ou Edge — Windows/macOS/Linux)</small>
                <button type="button" class="btn-ajuda-tutorial" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top" data-bs-content="Caso não esteja enxergando, clique na imagem para deixar em tela cheia." tabindex="0" aria-label="Ajuda">!</button>
            </h2>
            <!-- Prints ainda são os do projeto de referência (placeholders) — substituir pelos prints reais do Manancial. -->
            <div class="passos-grid">
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">1</span>
                        <p>Abra o <strong>Google Chrome</strong> no seu computador.</p>
                    </div>
                    <img src="assets/img/desktop-passo-1.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Área de trabalho com o ícone do Google Chrome em destaque">
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">2</span>
                        <p>Acesse o site da <strong>Missão Manancial</strong> pelo navegador.</p>
                    </div>
                    <img src="assets/img/desktop-passo-2.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Tela de Login da Missão Manancial">
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">3</span>
                        <p>Certifique-se de estar na <strong>tela de Login</strong> antes de continuar — é a partir dela que o aplicativo abrirá depois de instalado.</p>
                    </div>
                    <img src="assets/img/desktop-passo-2.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Tela de Login da Missão Manancial">
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">4</span>
                        <p>Clique no menu <strong>⋮</strong> (três pontinhos), no canto superior direito do navegador.</p>
                    </div>
                    <img src="assets/img/desktop-passo-2.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Navegador com o menu de três pontinhos em destaque">
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">5</span>
                        <p>No menu que abrir, clique em <strong>"Instalar página como app..."</strong>.</p>
                    </div>
                    <img src="assets/img/desktop-passo-3.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Menu do Chrome com a opção Instalar página como app em destaque">
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">6</span>
                        <p>Confirme clicando em <strong>"Instalar"</strong> na caixa de diálogo que aparece.</p>
                    </div>
                    <img src="assets/img/desktop-passo-4.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Caixa de diálogo com o botão Instalar em destaque">
                </div>
                <div class="passo-card">
                    <div class="passo-texto">
                        <span class="passo-numero">7</span>
                        <p>Pronto! O app abre em uma janela própria e o ícone fica disponível na área de trabalho.</p>
                    </div>
                    <img src="assets/img/desktop-passo-5.jpg" class="tutorial-print" loading="lazy" decoding="async" alt="Área de trabalho com o ícone do app em destaque">
                </div>
            </div>
            <div class="voltar-dispositivo">
                <button type="button" class="btn-secondary-pill" data-bs-toggle="modal" data-bs-target="#modalPlataforma">
                    <i class="fas fa-rotate"></i> Ver tutorial de outro dispositivo
                </button>
            </div>
        </section>

        <section class="cta-final">
            <a href="index.html" class="btn-secondary-pill"><i class="fas fa-arrow-left"></i> Voltar para o site</a>
            <a href="login.php" class="btn-primary-pill"><i class="fas fa-sign-in-alt"></i> Acessar plataforma</a>
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
                <a href="index.html#home">Home</a>
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

    <!-- MODAL: escolha de plataforma -->
    <div class="modal fade" id="modalPlataforma" tabindex="-1" aria-labelledby="modalPlataformaTitulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPlataformaTitulo">Qual é o seu dispositivo?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 text-center">
                        <div class="col-4">
                            <button type="button" class="btn plataforma-opcao w-100 h-100 py-3" data-plataforma="Android">
                                <i class="fab fa-android fs-1 d-block mb-2"></i>Android
                            </button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn plataforma-opcao w-100 h-100 py-3" data-plataforma="Ios">
                                <i class="fab fa-apple fs-1 d-block mb-2"></i>iPhone / iPad
                            </button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn plataforma-opcao w-100 h-100 py-3" data-plataforma="Desktop">
                                <i class="fas fa-desktop fs-1 d-block mb-2"></i>Desktop
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- LIGHTBOX: amplia o print do passo ao clicar -->
    <div id="lightboxImagem" class="lightbox-overlay">
        <button type="button" class="lightbox-fechar" aria-label="Fechar imagem ampliada">&times;</button>
        <img src="" alt="" class="lightbox-imagem">
    </div>

    <script src="js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <script src="js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        /**
         * Popover de ajuda ("!") ao lado do título de cada dispositivo, avisando
         * que dá para clicar nos prints para ampliar. Usa trigger "focus": abre ao
         * clicar/focar no botão e fecha sozinho ao clicar em qualquer outro lugar.
         */
        document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
            new bootstrap.Popover(el);
        });

        /**
         * Mostra o tutorial da plataforma escolhida no modal e esconde os demais.
         * Cada tutorial vive em uma <section data-tutorial id="tutorialX">, oculta
         * por padrão (classe d-none) até o usuário escolher.
         */
        (function () {
            'use strict';

            var estadoVazio = document.getElementById('estadoVazio');
            var modalEl = document.getElementById('modalPlataforma');
            var modalPlataforma = modalEl ? new bootstrap.Modal(modalEl) : null;

            document.querySelectorAll('.plataforma-opcao').forEach(function (botao) {
                botao.addEventListener('click', function () {
                    var plataforma = botao.getAttribute('data-plataforma');

                    document.querySelectorAll('[data-tutorial]').forEach(function (secao) {
                        secao.classList.add('d-none');
                    });

                    var alvo = document.getElementById('tutorial' + plataforma);
                    if (alvo) {
                        alvo.classList.remove('d-none');
                        estadoVazio.classList.add('d-none');
                        alvo.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }

                    if (modalPlataforma) {
                        modalPlataforma.hide();
                    }
                });
            });
        })();

        /**
         * Lightbox simples: clicar num print do tutorial amplia a imagem em tela
         * cheia, para facilitar ver exatamente onde tocar/clicar.
         */
        (function () {
            'use strict';

            var overlay = document.getElementById('lightboxImagem');
            var imagemAmpliada = overlay.querySelector('.lightbox-imagem');
            var botaoFechar = overlay.querySelector('.lightbox-fechar');

            function abrirLightbox(img) {
                imagemAmpliada.src = img.src;
                imagemAmpliada.alt = img.alt;
                overlay.classList.add('aberto');
            }

            function fecharLightbox() {
                overlay.classList.remove('aberto');
                imagemAmpliada.src = '';
            }

            document.querySelectorAll('.tutorial-print').forEach(function (img) {
                img.addEventListener('click', function () {
                    abrirLightbox(img);
                });
            });

            botaoFechar.addEventListener('click', fecharLightbox);

            overlay.addEventListener('click', function (evento) {
                if (evento.target === overlay) {
                    fecharLightbox();
                }
            });

            document.addEventListener('keydown', function (evento) {
                if (evento.key === 'Escape') {
                    fecharLightbox();
                }
            });
        })();
    </script>
    <script src="js/live-indicator.js?v=<?php echo site_versao_estatico(); ?>" data-status-url="ajax_transmissao_status.php"></script>
    <script src="js/menu-publico.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
