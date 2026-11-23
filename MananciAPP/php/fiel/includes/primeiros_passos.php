<?php
// Partial: Primeiros passos (seção 80) — as missões de boas-vindas, no topo
// de php/fiel/desafios.php (no fim, depois de completas). Ver "PRIMEIROS
// PASSOS" em php/conexao.php. Uso:
//   $pp_passos = primeiros_passos($conn, $fiel_id);
//   include __DIR__ . '/includes/primeiros_passos.php';
if (empty($pp_passos)) {
    return;
}
$pp_resumo = primeiros_passos_resumo($pp_passos);
$pp_grupos = primeiros_passos_grupos();
$pp_pct = (int) floor($pp_resumo['feitos'] * 100 / max(1, $pp_resumo['total']));

// Nome da medalha de cada grupo (do catálogo, se a seção 80 já rodou).
$pp_medalhas = array();
if (medalhas_disponivel($conn)) {
    foreach (medalhas_listar($conn) as $pp_m) {
        $pp_medalhas[$pp_m['regra']] = $pp_m['nome'];
    }
}
?>
<section class="passos mb-4<?php echo $pp_resumo['completo'] ? ' is-completo' : ''; ?>" id="primeiros-passos" aria-labelledby="passosTitulo">
    <div class="passos-topo">
        <div class="passos-anel" style="--p: <?php echo $pp_pct; ?>;" role="img" aria-label="<?php echo $pp_resumo['feitos']; ?> de <?php echo $pp_resumo['total']; ?> missões feitas">
            <span><?php echo $pp_resumo['feitos']; ?><small>/<?php echo $pp_resumo['total']; ?></small></span>
        </div>
        <div class="passos-topo-texto">
            <h3 class="stream-section-title mb-1" id="passosTitulo"><i class="fas fa-shoe-prints me-2" style="color:#fb923c;"></i>Primeiros passos</h3>
            <?php if ($pp_resumo['completo']) { ?>
                <p class="mb-0">Tudo pronto! 🎉 Você liberou a <a href="configuracoes.php#aparencia">capa do perfil personalizável</a>.</p>
            <?php } else { ?>
                <p class="mb-0 text-muted">Deixe o app com a sua cara e conheça cada canto. Cada grupo completo vale uma medalha, e tudo completo libera a capa do perfil personalizável.</p>
            <?php } ?>
        </div>
    </div>

    <?php if ($pp_resumo['completo']) { ?><details class="passos-feitos"><summary>Ver missões</summary><?php } ?>
    <div class="passos-grupos">
        <?php foreach ($pp_grupos as $pp_grupo => $pp_info) {
            $pp_g = $pp_resumo['grupos'][$pp_grupo] ?? null;
            if (!$pp_g) {
                continue;
            }
            $pp_grupo_ok = $pp_g['feitos'] === $pp_g['total'];
        ?>
            <div class="passos-grupo<?php echo $pp_grupo_ok ? ' is-completo' : ''; ?>">
                <div class="passos-grupo-topo">
                    <h3><i class="fas <?php echo $pp_info['icone']; ?> me-2" aria-hidden="true"></i><?php echo htmlspecialchars($pp_info['titulo']); ?></h3>
                    <span class="passos-grupo-conta"><?php echo $pp_g['feitos']; ?> de <?php echo $pp_g['total']; ?></span>
                </div>
                <div class="progress-bar-custom mb-2"><div class="progress-bar-custom-fill" style="width: <?php echo (int) floor($pp_g['feitos'] * 100 / max(1, $pp_g['total'])); ?>%;"></div></div>
                <?php if (isset($pp_medalhas[$pp_info['regra']])) { ?>
                    <p class="passos-recompensa"><i class="fas fa-medal" aria-hidden="true"></i><?php echo $pp_grupo_ok ? 'Medalha conquistada' : 'Vale a medalha'; ?> <strong><?php echo htmlspecialchars($pp_medalhas[$pp_info['regra']]); ?></strong></p>
                <?php } ?>
                <ul class="passos-lista">
                    <?php foreach ($pp_passos as $pp_chave => $pp) {
                        if ($pp['grupo'] !== $pp_grupo) {
                            continue;
                        }
                    ?>
                        <li class="passo<?php echo $pp['feito'] ? ' is-feito' : ''; ?>">
                            <span class="passo-icone" aria-hidden="true"><i class="fas <?php echo $pp['feito'] ? 'fa-check' : $pp['icone']; ?>"></i></span>
                            <span class="passo-texto">
                                <strong><?php echo htmlspecialchars($pp['titulo']); ?></strong>
                                <small><?php echo htmlspecialchars($pp['descricao']); ?></small>
                            </span>
                            <?php if ($pp['feito']) { ?>
                                <span class="visually-hidden">Feito</span>
                            <?php } elseif ($pp['link'] !== null) { ?>
                                <a href="<?php echo htmlspecialchars($pp['link']); ?>" class="btn btn-play btn-sm passo-acao">Fazer</a>
                            <?php } else { ?>
                                <span class="passo-desafio" title="Sem atalho: descubra como"><i class="fas fa-puzzle-piece me-1" aria-hidden="true"></i>Desafio</span>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        <?php } ?>
    </div>
    <?php if ($pp_resumo['completo']) { ?></details><?php } ?>
</section>
