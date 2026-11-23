<?php
// Partial: um testemunho publicado (T26), no mural (php/fiel/testemunhos.php)
// e no bloco da home (php/fiel/dashboard.php).
// Uso:
//   $card_testemunho = testemunho_formatar(...);  // ver conexao.php
//   $card_testemunho_resumo = true;  // home: corta o texto e linka ao mural
//   include __DIR__ . '/includes/card_testemunho.php';
// O voto e o "Ler mais" são tratados por js/testemunhos.js.

$ct = $card_testemunho;
$ct_resumo = !empty($card_testemunho_resumo);
$ct_data = (new DateTime('@' . (int) $ct['publicado_ts']))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y');
$ct_texto = $ct['texto'];
$ct_cortado = false;
if ($ct_resumo && mb_strlen($ct_texto) > 220) {
    $ct_texto = rtrim(mb_substr($ct_texto, 0, 210)) . '…';
    $ct_cortado = true;
}
$ct_inicial = $ct['anonimo'] && !$ct['meu'] ? '?' : mb_strtoupper(mb_substr($ct['nome'], 0, 1));
?>
<article class="testemunho-card<?php echo $ct_resumo ? ' is-resumo' : ''; ?>"<?php echo $ct_resumo ? '' : ' id="t-' . (int) $ct['id'] . '"'; ?>>
    <i class="fas fa-quote-left testemunho-aspas" aria-hidden="true"></i>
    <h3 class="testemunho-titulo"><?php echo htmlspecialchars($ct['titulo']); ?></h3>
    <p class="testemunho-texto<?php echo !$ct_resumo ? ' is-recolhivel' : ''; ?>"><?php echo htmlspecialchars($ct_texto); ?></p>
    <?php if ($ct_cortado) { ?>
        <a href="testemunhos.php#t-<?php echo (int) $ct['id']; ?>" class="testemunho-ler-mais">Ler o testemunho completo</a>
    <?php } elseif (!$ct_resumo) { ?>
        <button type="button" class="testemunho-ler-mais" data-ler-mais hidden>Ler mais</button>
    <?php } ?>
    <footer class="testemunho-rodape">
        <span class="presenca-avatar testemunho-avatar"><?php echo htmlspecialchars($ct_inicial); ?></span>
        <span class="testemunho-autor">
            <strong><?php echo htmlspecialchars($ct['nome']); ?><?php echo $ct['meu'] ? ' (você)' : ''; ?></strong>
            <small class="text-muted"><?php echo $ct_data; ?><?php echo $ct['anonimo'] && $ct['meu'] ? ' · publicado como anônimo' : ''; ?></small>
        </span>
        <button type="button" class="testemunho-voto<?php echo $ct['meu_voto'] ? ' is-ativo' : ''; ?>" data-testemunho-voto="<?php echo (int) $ct['id']; ?>" aria-pressed="<?php echo $ct['meu_voto'] ? 'true' : 'false'; ?>" <?php echo $ct['meu'] ? 'disabled title="Você não pode votar no próprio testemunho"' : 'title="Edificante"'; ?>>
            <i class="fas fa-hands-praying" aria-hidden="true"></i><span class="testemunho-voto-rotulo">Edificante</span><span class="testemunho-voto-num"><?php echo (int) $ct['votos']; ?></span>
        </button>
    </footer>
</article>
