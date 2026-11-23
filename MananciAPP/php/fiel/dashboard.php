<?php
include("../conexao.php");
require_once __DIR__ . '/includes/cursos_helpers.php';

auth_require();

// Redirecionar admin para seu painel
if (isset($_SESSION["Usuario_tipo"]) && $_SESSION["Usuario_tipo"] === "admin") {
    header("location: ../admin/dashboard.php");
    exit;
}

$usuario_nome = $_SESSION["Usuario_nome"] ?? "Usuário";
$fiel_id = auth_resolve_fiel_id();

$curso_hero = curso_destaque($conn);
$continuar_assistindo = $fiel_id ? cursos_continuar_assistindo($conn, $fiel_id, 12) : array();
$novos_cursos = cursos_novos($conn, 12, $fiel_id);
$em_alta_semana = cursos_em_alta_semana($conn, 12, $fiel_id);
$mais_assistidos = cursos_mais_assistidos($conn, 12, $fiel_id);
$videos_recomendados = videos_recomendados($conn, $fiel_id, 12);
$filas_categoria = cursos_por_categoria($conn, $fiel_id, 12);
// Devocional do dia (T33): bloco no topo quando há um publicado hoje.
$devocional_home = devocionais_disponivel($conn) ? devocional_do_dia($conn) : null;
if ($devocional_home && $devocional_home['data'] !== site_hoje()) {
    $devocional_home = null;
}

// Desafios (T31): os em andamento (com o progresso) ou o convite para os
// abertos. Também fecha os que chegaram à meta.
$tem_desafios = desafios_disponivel($conn);
$desafios_home = array();
$desafios_abertos_home = 0;
if ($tem_desafios) {
    $desafios_home = array_slice(array_values(array_filter(
        desafios_do_fiel($conn, auth_resolve_fiel_id(), true),
        function ($m) { return $m['progresso']['situacao'] === 'andamento'; }
    )), 0, 3);
    if (!$desafios_home) {
        $desafios_abertos_home = count(array_filter(desafios_abertos($conn, auth_resolve_fiel_id()), function ($d) { return !$d['fechado']; }));
    }
}

// Mural de testemunhos (T26): os mais votados para o bloco da home.
$tem_testemunhos = testemunhos_disponivel($conn);
$testemunhos_home = $tem_testemunhos ? testemunhos_destaque_home($conn, 3) : array();
// Faixa "Primeiros passos" (seção 80) logo abaixo do banner, até o fiel
// completar as missões de boas-vindas. Substitui o antigo aviso "Complete
// seu perfil" (T13): o perfil entra nas missões. Grupo que acabou de
// completar dá a medalha (aviso antes do menu ser montado).
// Conquistas da comunidade (seção 81): as últimas medalhas ganhas pelos irmãos.
$conquistas_home = medalhas_conquistas_recentes($conn, 6);

// Meta diária (seção 85): minutos assistidos hoje contra o "tempo por dia"
// escolhido em Configurações (sem escolha, 10 min).
$meta_hoje = ($fiel_id && meta_diaria_disponivel($conn)) ? meta_diaria_hoje($conn, $fiel_id) : null;

$pp_passos = $fiel_id ? primeiros_passos($conn, $fiel_id) : array();
$pp_resumo = primeiros_passos_resumo($pp_passos);
if ($pp_passos && medalhas_disponivel($conn)) {
    medalhas_avisar_novas(medalhas_conceder_atividade($conn, $fiel_id, array('passos_espaco', 'passos_app'), true));
}

$hero_capa = $curso_hero ? curso_resolver_capa($curso_hero['IDC_CAPA_URL'] ?? null) : null;
$hero_categoria = $curso_hero['categoria_nome'] ?? null;

// Carrossel do topo: o curso em destaque (se ligado) e as artes no ar de
// Admin → Destaques da home. Vazio = boas-vindas.
$home_slides = array();
if ($curso_hero && home_destaque_curso_ligado()) {
    $home_slides[] = array('tipo' => 'curso');
}
foreach (home_destaques($conn) as $destaque) {
    $slide = home_destaque_slide($destaque);
    if ($slide) {
        $home_slides[] = array('tipo' => 'arte') + $slide;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Início</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <style>
        .footer-faith-panel {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(96,165,250,0.15);
            border-radius: 18px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .footer-faith-panel::before,
        .footer-faith-panel::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .footer-faith-panel::before {
            width: 180px;
            height: 180px;
            background: rgba(96,165,250,0.12);
            top: -70px;
            right: -40px;
            animation: footerFloat 8s ease-in-out infinite;
        }

        .footer-faith-panel::after {
            width: 120px;
            height: 120px;
            background: rgba(16,185,129,0.12);
            bottom: -40px;
            left: -30px;
            animation: footerFloat 10s ease-in-out infinite reverse;
        }

        .footer-faith-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 1.5rem;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .footer-faith-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .footer-faith-brand img {
            width: 58px;
            height: 58px;
            object-fit: contain;
            filter: drop-shadow(0 0 18px rgba(96,165,250,0.35));
        }

        .footer-faith-title {
            margin: 0;
            color: #60a5fa;
            font-weight: 700;
        }

        .footer-faith-icons {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .footer-faith-icon {
            width: 62px;
            height: 62px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(96,165,250,0.18);
            color: #93c5fd;
            font-size: 1.5rem;
            box-shadow: 0 0 0 rgba(96,165,250,0.0);
            animation: footerPulse 2.8s ease-in-out 3; /* 3 vezes e para (antes, para sempre) */
        }

        .footer-faith-icon:nth-child(2) { animation-delay: 0.35s; }
        .footer-faith-icon:nth-child(3) { animation-delay: 0.7s; }
        .footer-faith-icon:nth-child(4) { animation-delay: 1.05s; }

        .footer-faith-verse {
            margin-top: 1rem;
            padding: 1rem 1.2rem;
            border-left: 3px solid #60a5fa;
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            color: var(--text-muted);
        }

        .footer-faith-bible {
            font-size: 0.92rem;
            color: #93c5fd;
            margin-top: 0.4rem;
        }

        /* Tema claro: os azuis claros e o branco translúcido somem no fundo claro. */
        :root[data-theme="light"] .footer-faith-panel {
            background: #ffffff;
            border-color: rgba(37,99,235,0.18);
            box-shadow: 0 8px 24px rgba(15,23,42,0.06);
        }
        :root[data-theme="light"] .footer-faith-icon {
            background: rgba(37,99,235,0.08);
            border-color: rgba(37,99,235,0.25);
            color: #2563eb;
        }
        :root[data-theme="light"] .footer-faith-verse {
            background: rgba(37,99,235,0.05);
            border-left-color: #2563eb;
            color: #1e293b;
        }
        :root[data-theme="light"] .footer-faith-bible { color: #2563eb; }

        @keyframes footerPulse {
            0%, 100% { transform: translateY(0) scale(1); box-shadow: 0 0 0 0 rgba(96,165,250,0.0); }
            50% { transform: translateY(-4px) scale(1.04); box-shadow: 0 10px 30px rgba(96,165,250,0.18); }
        }

        @keyframes footerFloat {
            0%, 100% { transform: translateY(0) translateX(0) scale(1); }
            50% { transform: translateY(-10px) translateX(6px) scale(1.04); }
        }

        @media (max-width: 768px) {
            .footer-faith-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .footer-faith-brand {
                justify-content: center;
            }
        }
    </style>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'inicio'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container-fluid px-4 py-4" style="max-width: 1500px; margin: 0 auto;">
        <?php
        // Saudação pelo horário (Brasília) e pelo primeiro nome: deixa o app
        // pessoal. Antes o nome só aparecia quando não havia carrossel.
        $hora_agora = (int) date('G');
        $saudacao = $hora_agora < 12 ? 'Bom dia' : ($hora_agora < 18 ? 'Boa tarde' : 'Boa noite');
        $primeiro_nome = explode(' ', trim((string) $usuario_nome))[0];
        $novato = $pp_passos && !$pp_resumo['completo'] && (int) $pp_resumo['feitos'] <= 1;
        ?>
        <div class="home-saudacao">
            <span><?php echo htmlspecialchars($saudacao . ', ' . $primeiro_nome); ?>!</span>
            <?php if ($novato) { ?>
                <!-- Quem acabou de chegar vai direto aos Primeiros passos. -->
                <a href="#passosFaixa" class="home-saudacao-comece">Comece por aqui <i class="fas fa-arrow-down" aria-hidden="true"></i></a>
            <?php } ?>
        </div>

        <?php if ($home_slides) { ?>
            <!-- Carrossel do topo: curso em destaque + artes (Admin → Destaques da home) -->
            <?php include __DIR__ . '/includes/home_carrossel.php'; ?>
        <?php } else { ?>
            <div class="hero-banner">
                <div class="hero-banner-content">
                    <h1 class="hero-banner-title">Bem-vindo, <?php echo htmlspecialchars($usuario_nome); ?>!</h1>
                    <p class="hero-banner-desc">Explore nossos cursos, conteúdos exclusivos e fortaleça seu crescimento espiritual na comunidade.</p>
                </div>
            </div>
        <?php } ?>

        <?php if ($devocional_home) { ?>
            <!-- Devocional do dia (T33) -->
            <a href="devocional.php" class="devocional-home">
                <span class="devocional-home-icone" aria-hidden="true"><i class="fas fa-book-bible"></i></span>
                <span class="devocional-home-corpo">
                    <small>Devocional do dia · <?php echo htmlspecialchars($devocional_home['tema']); ?></small>
                    <strong><?php echo htmlspecialchars($devocional_home['titulo']); ?></strong>
                    <span class="text-muted"><?php echo htmlspecialchars($devocional_home['referencia']); ?> · por <?php echo htmlspecialchars($devocional_home['autor']); ?></span>
                </span>
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </a>
        <?php } ?>

        <?php if ($meta_hoje) {
            $meta_pct = (int) min(100, floor($meta_hoje['segundos'] * 100 / max(60, $meta_hoje['alvo'] * 60)));
            $meta_ok = $meta_hoje['feitos'] >= $meta_hoje['alvo'];
            $meta_falta = max(1, $meta_hoje['alvo'] - $meta_hoje['feitos']);
        ?>
            <!-- Meta diária (seção 85), como o anel do Apple Fitness/Duolingo. -->
            <section class="meta-home<?php echo $meta_ok ? ' is-cumprida' : ''; ?>" aria-label="Meta de hoje">
                <div class="passos-anel passos-anel-sm" style="--p: <?php echo $meta_pct; ?>;" role="img" aria-label="<?php echo (int) $meta_hoje['feitos']; ?> de <?php echo (int) $meta_hoje['alvo']; ?> minutos hoje">
                    <span><?php echo $meta_ok ? '<i class="fas fa-check" aria-hidden="true"></i>' : $meta_pct . '%'; ?></span>
                </div>
                <div class="meta-home-texto">
                    <strong>Meta de hoje: <?php echo (int) $meta_hoje['feitos']; ?> de <?php echo (int) $meta_hoje['alvo']; ?> min</strong>
                    <small>
                        <?php if ($meta_ok) { ?>
                            Cumprida! 🙌 Volte amanhã para manter o ritmo.
                        <?php } elseif ($meta_hoje['feitos'] === 0) { ?>
                            Que tal começar com uma aula?
                        <?php } else { ?>
                            Faltam <?php echo $meta_falta; ?> min — dá para fechar hoje.
                        <?php } ?>
                        <a href="configuracoes.php#preferencias" class="meta-home-mudar">Mudar meta</a>
                    </small>
                </div>
            </section>
        <?php } ?>

        <?php if ($pp_passos && !$pp_resumo['completo']) {
            $pp_prox = $pp_passos[$pp_resumo['proxima']];
            $pp_pct = (int) floor($pp_resumo['feitos'] * 100 / max(1, $pp_resumo['total']));
        ?>
            <!-- Primeiros passos (seção 80): a próxima missão e a trilha com todas.
                 Passar o mouse (ou tocar) num ícone mostra aquela missão. -->
            <section class="passos-faixa" id="passosFaixa" aria-label="Primeiros passos">
                <div class="passos-anel passos-anel-sm" style="--p: <?php echo $pp_pct; ?>;" role="img" aria-label="<?php echo $pp_resumo['feitos']; ?> de <?php echo $pp_resumo['total']; ?> missões feitas">
                    <span><?php echo $pp_resumo['feitos']; ?><small>/<?php echo $pp_resumo['total']; ?></small></span>
                </div>
                <div class="passos-faixa-corpo">
                    <small class="passos-faixa-rotulo">Primeiros passos · <span data-passo-status>próxima missão</span></small>
                    <strong data-passo-titulo><?php echo htmlspecialchars($pp_prox['titulo']); ?></strong>
                    <span class="passos-faixa-desc" data-passo-desc><?php echo htmlspecialchars($pp_prox['descricao']); ?></span>
                    <div class="passos-trilha" role="list">
                        <?php foreach ($pp_passos as $pp_chave => $pp) {
                            $pp_tag = $pp['link'] !== null && !$pp['feito'] ? 'a' : 'span';
                        ?>
                            <<?php echo $pp_tag; ?> role="listitem" class="passos-trilha-item<?php echo $pp['feito'] ? ' is-feito' : ''; ?><?php echo $pp_chave === $pp_resumo['proxima'] ? ' is-atual' : ''; ?>"
                                <?php echo $pp_tag === 'a' ? 'href="' . htmlspecialchars($pp['link']) . '"' : 'tabindex="0"'; ?>
                                data-passo-titulo="<?php echo htmlspecialchars($pp['titulo']); ?>"
                                data-passo-desc="<?php echo htmlspecialchars($pp['descricao']); ?>"
                                data-passo-link="<?php echo htmlspecialchars((string) $pp['link']); ?>"
                                data-passo-feito="<?php echo $pp['feito'] ? '1' : '0'; ?>"
                                aria-label="<?php echo htmlspecialchars($pp['titulo']) . ($pp['feito'] ? ' (feito)' : ''); ?>"
                                title="<?php echo htmlspecialchars($pp['titulo']); ?>">
                                <i class="fas <?php echo $pp['feito'] ? 'fa-check' : $pp['icone']; ?>" aria-hidden="true"></i>
                            </<?php echo $pp_tag; ?>>
                        <?php } ?>
                    </div>
                </div>
                <div class="passos-faixa-acoes">
                    <a href="<?php echo htmlspecialchars((string) $pp_prox['link']); ?>" class="btn btn-play btn-sm" data-passo-acao<?php echo $pp_prox['link'] === null ? ' hidden' : ''; ?>>Fazer agora</a>
                    <a href="desafios.php#primeiros-passos" class="passos-faixa-todas">Ver todas</a>
                </div>
                <button type="button" class="passos-faixa-fechar" id="passosFaixaFechar" aria-label="Esconder até amanhã" title="Esconder até amanhã"><i class="fas fa-xmark"></i></button>
            </section>
            <script nonce="<?php echo site_csp_nonce(); ?>">
                (function () {
                    var faixa = document.getElementById('passosFaixa');
                    // "x": some até amanhã neste aparelho. Roda antes de pintar
                    // a página para a faixa não "piscar".
                    var CHAVE = 'manancial-passos-ate';
                    try {
                        if (Date.now() < Number(localStorage.getItem(CHAVE) || 0)) faixa.hidden = true;
                    } catch (e) {}
                    document.getElementById('passosFaixaFechar').addEventListener('click', function () {
                        faixa.hidden = true;
                        try { localStorage.setItem(CHAVE, String(Date.now() + 24 * 60 * 60 * 1000)); } catch (e) {}
                    });

                    // Trilha: mouse em cima (ou foco) num ícone mostra a missão
                    // e o "Fazer agora" passa a levar até ela; ao sair, volta
                    // para a próxima.
                    var titulo = faixa.querySelector('strong[data-passo-titulo]');
                    var desc = faixa.querySelector('[data-passo-desc]');
                    var status = faixa.querySelector('[data-passo-status]');
                    var acao = faixa.querySelector('[data-passo-acao]');
                    var itens = faixa.querySelectorAll('.passos-trilha-item');
                    var atual = faixa.querySelector('.passos-trilha-item.is-atual');
                    function mostrar(item, previa) {
                        if (!item) return;
                        var feito = item.getAttribute('data-passo-feito') === '1';
                        var link = item.getAttribute('data-passo-link');
                        titulo.textContent = item.getAttribute('data-passo-titulo');
                        desc.textContent = item.getAttribute('data-passo-desc');
                        status.textContent = feito ? 'feito ✓' : (previa ? 'missão' : 'próxima missão');
                        acao.hidden = feito || !link;
                        if (link) acao.setAttribute('href', link);
                    }
                    Array.prototype.forEach.call(itens, function (item) {
                        item.addEventListener('mouseenter', function () { mostrar(item, true); });
                        item.addEventListener('focus', function () { mostrar(item, true); });
                    });
                    faixa.querySelector('.passos-trilha').addEventListener('mouseleave', function () { mostrar(atual, false); });
                })();
            </script>
        <?php } ?>

        <?php
        $secoes = array(
            array('titulo' => 'Continuar Assistindo', 'icone' => 'fa-play-circle', 'itens' => $continuar_assistindo, 'tipo' => 'curso'),
            array('titulo' => 'Em alta esta semana', 'icone' => 'fa-fire', 'itens' => $em_alta_semana, 'tipo' => 'curso'),
            array('titulo' => 'Novos Cursos', 'icone' => 'fa-layer-group', 'itens' => $novos_cursos, 'tipo' => 'curso'),
            array('titulo' => 'Mais Assistidos', 'icone' => 'fa-trophy', 'itens' => $mais_assistidos, 'tipo' => 'curso'),
            array('titulo' => 'Recomendados para Você', 'icone' => 'fa-heart', 'itens' => $videos_recomendados, 'tipo' => 'video'),
        );
        // Uma fila por tema (categoria), com cards maiores.
        foreach ($filas_categoria as $fila) {
            $secoes[] = array(
                'titulo' => $fila['nome'],
                'icone' => categoria_icone($fila['nome']),
                'itens' => $fila['cursos'],
                'tipo' => 'curso',
                'link' => 'cursos.php?categoria=' . (int) $fila['id'],
                'classe' => 'stream-carousel-tema',
            );
        }
        foreach ($secoes as $secao) {
            if (empty($secao['itens'])) {
                continue;
            }
        ?>
            <section class="stream-section">
                <div class="stream-section-header">
                    <h2 class="stream-section-title"><i class="fas <?php echo $secao['icone']; ?> me-2" style="color:#60a5fa;"></i><?php echo htmlspecialchars($secao['titulo']); ?></h2>
                    <a href="<?php echo htmlspecialchars($secao['link'] ?? 'cursos.php'); ?>" class="stream-section-link">Ver todos <i class="fas fa-chevron-right ms-1"></i></a>
                </div>
                <div class="stream-carousel-wrap">
                    <button type="button" class="carousel-arrow prev" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button>
                    <div class="stream-carousel<?php echo !empty($secao['classe']) ? ' ' . $secao['classe'] : ''; ?>">
                        <?php if ($secao['tipo'] === 'video') { ?>
                            <?php foreach ($secao['itens'] as $card_video_dados) { ?>
                                <?php include __DIR__ . '/includes/card_video.php'; ?>
                            <?php } ?>
                        <?php } else { ?>
                            <?php foreach ($secao['itens'] as $card_curso_dados) { ?>
                                <?php include __DIR__ . '/includes/card_curso.php'; ?>
                            <?php } ?>
                        <?php } ?>
                    </div>
                    <button type="button" class="carousel-arrow next" aria-label="Próximo"><i class="fas fa-chevron-right"></i></button>
                </div>
            </section>
        <?php } ?>

        <?php if ($desafios_home || $desafios_abertos_home) { ?>
            <!-- Desafios (T31): em andamento ou convite -->
            <section class="stream-section">
                <div class="stream-section-header">
                    <h2 class="stream-section-title"><i class="fas fa-flag-checkered me-2" style="color:#34d399;"></i>Seus desafios</h2>
                    <a href="desafios.php" class="stream-section-link">Ver desafios <i class="fas fa-chevron-right ms-1"></i></a>
                </div>
                <?php if ($desafios_home) { ?>
                    <div class="desafios-home">
                        <?php foreach ($desafios_home as $item) { $g = $item['progresso']; ?>
                            <a href="desafios.php#d-<?php echo (int) $item['desafio']['id']; ?>" class="desafio-home">
                                <strong><?php echo htmlspecialchars($item['desafio']['titulo']); ?></strong>
                                <small class="text-muted"><?php echo htmlspecialchars($item['desafio']['meta_texto']); ?></small>
                                <div class="progress-bar-custom"><div class="progress-bar-custom-fill" style="width: <?php echo $g['percentual']; ?>%;"></div></div>
                                <small><?php echo $g['atual']; ?> de <?php echo $g['meta']; ?></small>
                            </a>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="testemunhos-convite">
                        <i class="fas fa-flag-checkered" aria-hidden="true" style="color:#34d399;"></i>
                        <div>
                            <strong><?php echo $desafios_abertos_home === 1 ? '1 desafio aberto' : $desafios_abertos_home . ' desafios abertos'; ?></strong>
                            <p class="text-muted mb-0">Desafie-se a crescer junto com a igreja.</p>
                        </div>
                        <a href="desafios.php#abertos" class="btn btn-play btn-sm">Ver desafios</a>
                    </div>
                <?php } ?>
            </section>
        <?php } ?>

        <?php if ($conquistas_home) { ?>
            <!-- Conquistas da comunidade (seção 81): as últimas medalhas ganhas.
                 As secretas saem sem nome ("uma medalha secreta"). -->
            <section class="stream-section">
                <div class="stream-section-header">
                    <h2 class="stream-section-title"><i class="fas fa-medal me-2" style="color:#facc15;"></i>Conquistas da comunidade</h2>
                    <a href="desafios.php#medalhas" class="stream-section-link">Conquistar também <i class="fas fa-chevron-right ms-1"></i></a>
                </div>
                <ul class="conquistas-home">
                    <?php foreach ($conquistas_home as $cq) { ?>
                        <li class="conquista<?php echo (int) $cq['fiel_id'] === (int) $fiel_id ? ' is-minha' : ''; ?>">
                            <span class="conquista-avatar" aria-hidden="true">
                                <?php if ($cq['foto']) { ?><img src="<?php echo htmlspecialchars($cq['foto']); ?>" alt="" loading="lazy"><?php } else { echo htmlspecialchars(mb_strtoupper(mb_substr($cq['nome'], 0, 1))); } ?>
                                <span class="medalha-mini nivel-<?php echo $cq['nivel']; ?>"><i class="fas <?php echo $cq['icone']; ?>"></i></span>
                            </span>
                            <span class="conquista-texto">
                                <strong><?php echo (int) $cq['fiel_id'] === (int) $fiel_id ? 'Você' : htmlspecialchars($cq['nome']); ?></strong>
                                <small>ganhou <?php echo htmlspecialchars($cq['medalha']); ?> · <?php echo htmlspecialchars(site_tempo_relativo($cq['quando'])); ?></small>
                            </span>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

        <?php if ($tem_testemunhos) { ?>
            <!-- Mural de testemunhos (T26): os mais votados -->
            <section class="stream-section" data-testemunhos data-csrf="<?php echo htmlspecialchars(csrf_token()); ?>">
                <div class="stream-section-header">
                    <h2 class="stream-section-title"><i class="fas fa-hands-praying me-2" style="color:#fbbf24;"></i>Testemunhos</h2>
                    <a href="testemunhos.php" class="stream-section-link">Ver mural <i class="fas fa-chevron-right ms-1"></i></a>
                </div>
                <?php if ($testemunhos_home) { ?>
                    <div class="testemunhos-grade is-home">
                        <?php foreach ($testemunhos_home as $card_testemunho) { $card_testemunho_resumo = true; include __DIR__ . '/includes/card_testemunho.php'; } ?>
                    </div>
                <?php } else { ?>
                    <div class="testemunhos-convite">
                        <i class="fas fa-hands-praying" aria-hidden="true"></i>
                        <div>
                            <strong>O que Deus tem feito na sua vida?</strong>
                            <p class="text-muted mb-0">Conte seu testemunho e edifique a comunidade.</p>
                        </div>
                        <a href="testemunhos.php?enviar=1#enviar" class="btn btn-play btn-sm">Contar meu testemunho</a>
                    </div>
                <?php } ?>
            </section>
        <?php } ?>

        <?php if (empty($continuar_assistindo) && empty($novos_cursos) && empty($mais_assistidos) && empty($videos_recomendados)) { ?>
            <div class="streaming-empty-state">
                <i class="fas fa-book-open"></i>
                <p class="mt-3 mb-3">Os cursos estão sendo preparados. Enquanto isso, acompanhe os cultos ao vivo e o devocional.</p>
                <a href="ao_vivo.php" class="btn btn-play me-2"><i class="fas fa-tower-broadcast me-2" aria-hidden="true"></i>Ao vivo</a>
                <a href="devocional.php" class="btn btn-outline-light"><i class="fas fa-book-bible me-2" aria-hidden="true"></i>Devocional</a>
            </div>
        <?php } ?>
    </main>

    <!-- FOOTER -->
    <footer class="site-footer">
        <div class="container-fluid px-4">
            <div class="footer-faith-panel">
                <div class="footer-faith-grid">
                    <div>
                        <div class="footer-faith-brand">
                            <img src="../../assets/logo-sm.png" alt="">
                            <div>
                                <h6 class="footer-faith-title"><?php echo htmlspecialchars(igreja_info()['nome']); ?></h6>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars(igreja_info()['nome_completo']); ?></p>
                            </div>
                        </div>
                        <div class="footer-faith-verse">
                            <div><i class="fas fa-quote-left me-2" style="color:#60a5fa;"></i>“Lâmpada para os meus pés é a tua palavra e luz para o meu caminho.”</div>
                            <div class="footer-faith-bible">Salmo 119:105</div>
                        </div>
                    </div>
                    <div>
                        <div class="footer-faith-icons">
                            <span class="footer-faith-icon" title="Fé"><i class="fas fa-cross"></i></span>
                            <span class="footer-faith-icon" title="Luz"><i class="fas fa-sun"></i></span>
                            <span class="footer-faith-icon" title="Paz"><i class="fas fa-dove"></i></span>
                            <span class="footer-faith-icon" title="Esperança"><i class="fas fa-hands-praying"></i></span>
                        </div>
                        <p class="text-muted small mt-3 mb-0 text-center text-md-start">Que cada acesso à plataforma seja um momento de edificação, esperança e comunhão.</p>
                        <!-- Quem administra, contato e as regras: antes a área logada não tinha caminho para nenhum. -->
                        <nav class="footer-faith-links" aria-label="Institucional">
                            <a href="sobre.php">Sobre a igreja</a>
                            <a href="contato.php">Fale conosco</a>
                            <a href="../../privacidade.php">Privacidade</a>
                            <a href="termos.php">Termos de Uso</a>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <script src="../../js/script.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <script src="../../js/testemunhos.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        // Só o aviso de sucesso some sozinho, e em 8 s (antes todos, até os de
        // erro, sumiam em 3,5 s: quem lê devagar não terminava de ler).
        setTimeout(() => {
            document.querySelectorAll('.alert.alert-success').forEach(alert => {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            });
        }, 8000);
    </script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
