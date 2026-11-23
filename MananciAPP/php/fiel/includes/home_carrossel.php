<?php
// Carrossel do topo da home (ver "DESTAQUES DA HOME" em php/conexao.php e
// Admin → Destaques da home). Uso em php/fiel/dashboard.php:
//   $home_slides = array(array('tipo' => 'curso'), array('tipo' => 'arte') + home_destaque_slide($d), ...);
//   include __DIR__ . '/includes/home_carrossel.php';
// O slide 'curso' usa $curso_hero e $hero_capa da página.
//
// Sem JS continua funcionando: o trilho rola de lado com encaixe (arrastar
// no celular, barra de rolagem no computador). O JS só acrescenta setas,
// pontos e a passagem automática.
$hc_total = count($home_slides);
?>
<section class="home-carrossel" data-home-carrossel aria-roledescription="carrossel" aria-label="Destaques">
    <div class="home-carrossel-janela">
        <div class="home-carrossel-trilho" data-home-carrossel-trilho>
            <?php foreach ($home_slides as $i => $s) {
                $hc_rotulo = ($i + 1) . ' de ' . $hc_total;
                if ($s['tipo'] === 'curso') {
            ?>
                <div class="home-carrossel-slide hero-banner" role="group" aria-roledescription="slide" aria-label="<?php echo $hc_rotulo; ?>"<?php echo $hero_capa ? ' style="background-image:url(\'' . htmlspecialchars($hero_capa, ENT_QUOTES) . '\')"' : ''; ?>>
                    <div class="hero-banner-content">
                        <span class="hero-banner-badge"><i class="fas fa-star me-1"></i>Em destaque</span>
                        <h1 class="hero-banner-title"><?php echo htmlspecialchars($curso_hero['IDC_TITULO']); ?></h1>
                        <p class="hero-banner-desc"><?php echo nl2br(htmlspecialchars($curso_hero['IDC_DESCRICAO'] ?? '')); ?></p>
                        <div class="hero-banner-actions">
                            <?php
                            // "Assistir agora" abre a aula (a próxima do fiel); antes os
                            // dois botões levavam à mesma página do curso.
                            $hc_aula = curso_proxima_aula_id($conn, (int) $curso_hero['IDC_ID'], auth_resolve_fiel_id());
                            ?>
                            <a href="<?php echo $hc_aula ? 'assistir_video.php?id=' . (int) $hc_aula : 'curso.php?id=' . (int) $curso_hero['IDC_ID']; ?>" class="btn btn-play">
                                <i class="fas fa-play me-2"></i>Assistir agora
                            </a>
                            <a href="curso.php?id=<?php echo (int) $curso_hero['IDC_ID']; ?>" class="btn btn-outline-light">
                                <i class="fas fa-info-circle me-2"></i>Saiba mais
                            </a>
                        </div>
                    </div>
                </div>
            <?php } else {
                    // O link fica dentro do slide: role="group" no próprio <a>
                    // esconderia do leitor de tela que ele é um link.
                    $hc_tag = $s['link'] !== '' ? 'a' : 'div';
                    $hc_fontes = $s['imagem_celular'] ? '<source media="(max-width: 768px)" srcset="' . htmlspecialchars($s['imagem_celular']) . '">' : '';
            ?>
                <div class="home-carrossel-slide is-arte" role="group" aria-roledescription="slide" aria-label="<?php echo $hc_rotulo; ?>">
                    <<?php echo $hc_tag; ?> class="home-carrossel-link"<?php
                        if ($s['link'] !== '') {
                            echo ' href="' . htmlspecialchars($s['link']) . '"' . ($s['externo'] ? ' target="_blank" rel="noopener"' : '');
                        }
                    ?>>
                        <!-- Fundo: a mesma arte desfocada preenche as sobras quando a proporção da tela não bate com a da arte. -->
                        <picture class="home-carrossel-fundo" aria-hidden="true"><?php echo $hc_fontes; ?><img src="<?php echo htmlspecialchars($s['imagem']); ?>" alt="" decoding="async"></picture>
                        <picture class="home-carrossel-arte"><?php echo $hc_fontes; ?><img src="<?php echo htmlspecialchars($s['imagem']); ?>" alt="<?php echo htmlspecialchars($s['titulo']); ?>" decoding="async"<?php echo $i === 0 ? ' fetchpriority="high"' : ''; ?>></picture>
                    </<?php echo $hc_tag; ?>>
                </div>
            <?php }
            } ?>
        </div>
        <?php if ($hc_total > 1) { ?>
            <button type="button" class="home-carrossel-seta is-anterior" data-home-carrossel-anterior aria-label="Destaque anterior"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
            <button type="button" class="home-carrossel-seta is-proxima" data-home-carrossel-proxima aria-label="Próximo destaque"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
        <?php } ?>
    </div>
    <?php if ($hc_total > 1) { ?>
        <div class="home-carrossel-pontos" role="group" aria-label="Escolher destaque">
            <?php for ($i = 0; $i < $hc_total; $i++) { ?>
                <button type="button" data-home-carrossel-ponto="<?php echo $i; ?>" aria-label="Ir para o destaque <?php echo $i + 1; ?>"<?php echo $i === 0 ? ' aria-current="true"' : ''; ?>></button>
            <?php } ?>
        </div>
        <script nonce="<?php echo site_csp_nonce(); ?>">
            (function () {
                var raiz = document.querySelector('[data-home-carrossel]');
                var trilho = raiz.querySelector('[data-home-carrossel-trilho]');
                var slides = trilho.children;
                var pontos = raiz.querySelectorAll('[data-home-carrossel-ponto]');
                var reduzir = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                var INTERVALO = 7000;
                var atual = 0;
                var mouseEmCima = false;
                var focoDentro = false;
                // Tempo já passado no slide atual: enche a barrinha do ponto
                // ativo (como na Crunchyroll) e, ao completar, passa para o
                // próximo. Mexer (arrastar, setas, pontos) recomeça a contagem.
                var decorrido = 0;

                function ir(i) {
                    atual = (i + slides.length) % slides.length;
                    decorrido = 0;
                    trilho.scrollTo({ left: slides[atual].offsetLeft, behavior: reduzir ? 'auto' : 'smooth' });
                }
                function marcar() {
                    var i = Math.round(trilho.scrollLeft / Math.max(1, trilho.clientWidth));
                    var novo = Math.max(0, Math.min(slides.length - 1, i));
                    if (novo !== atual) { decorrido = 0; }
                    atual = novo;
                    Array.prototype.forEach.call(pontos, function (p, j) {
                        if (j === atual) { p.setAttribute('aria-current', 'true'); } else { p.removeAttribute('aria-current'); p.style.removeProperty('--progresso'); }
                    });
                    // Slides fora da tela saem do Tab e do leitor de tela.
                    Array.prototype.forEach.call(slides, function (s, j) { s.inert = j !== atual; });
                }
                function pausar() { decorrido = 0; }

                var espera = null;
                trilho.addEventListener('scroll', function () {
                    clearTimeout(espera);
                    espera = setTimeout(marcar, 80);
                }, { passive: true });
                trilho.addEventListener('pointerdown', pausar, { passive: true });
                trilho.addEventListener('wheel', pausar, { passive: true });
                raiz.querySelector('[data-home-carrossel-anterior]').addEventListener('click', function () { pausar(); ir(atual - 1); });
                raiz.querySelector('[data-home-carrossel-proxima]').addEventListener('click', function () { pausar(); ir(atual + 1); });
                Array.prototype.forEach.call(pontos, function (p) {
                    p.addEventListener('click', function () { pausar(); ir(parseInt(p.getAttribute('data-home-carrossel-ponto'), 10)); });
                });
                raiz.addEventListener('mouseenter', function () { mouseEmCima = true; });
                raiz.addEventListener('mouseleave', function () { mouseEmCima = false; });
                raiz.addEventListener('focusin', function () { focoDentro = true; });
                raiz.addEventListener('focusout', function () { focoDentro = false; });
                // Tela girada ou janela redimensionada: volta a encaixar no slide atual.
                window.addEventListener('resize', function () { trilho.scrollLeft = slides[atual].offsetLeft; });

                marcar();
                // Com "reduzir movimento" não há passagem automática: o ponto
                // ativo fica cheio (o padrão do CSS) e o fiel troca na mão.
                if (!reduzir) {
                    var ultimo = performance.now();
                    var quadro = function (agora) {
                        // Mouse em cima, foco dentro ou aba escondida: a barrinha para.
                        if (!mouseEmCima && !focoDentro && document.visibilityState === 'visible') {
                            decorrido += Math.min(agora - ultimo, 250);
                        }
                        ultimo = agora;
                        if (decorrido >= INTERVALO) {
                            ir(atual + 1);
                        }
                        if (pontos[atual]) { pontos[atual].style.setProperty('--progresso', Math.min(1, decorrido / INTERVALO).toFixed(3)); }
                        requestAnimationFrame(quadro);
                    };
                    requestAnimationFrame(quadro);
                }
            })();
        </script>
    <?php } ?>
</section>
