<?php
// Partial: grade de medalhas (T14), como no exemplo do cliente — conquistadas
// coloridas, bloqueadas em cinza com cadeado. Uma seção por categoria; as
// bloqueadas que têm contagem mostram o progresso ("12 de 50 aulas").
// Secretas (seção 81) aparecem como "???" até serem conquistadas.
// Uso:
//   $medalhas_grade = array(
//       'catalogo' => medalhas_listar($conn),
//       'conquistadas' => medalhas_do_fiel($conn, $fiel_id),
//       'ofensiva_atual' => int,   // progresso das medalhas de ofensiva
//       'progresso' => medalhas_contagens($conn, $fiel_id),
//       'raridade' => medalhas_raridade($conn),   // opcional: "% dos fiéis têm"
//       'modo' => 'resumo' | 'bloqueadas',        // opcional
//       'vitrine' => fiel_vitrine($conn, $fiel_id), // opcional, só no 'resumo'
//   );
//   include __DIR__ . '/includes/medalhas_grade.php';
// Modos:
//   (nenhum)     todas, por categoria (ofensiva).
//   'resumo'     só as CONQUISTADAS, numa linha (mais recentes primeiro),
//                com "Ver todas" e a estrela da vitrine (perfil).
//   'bloqueadas' as que faltam, sem bagunça: a próxima de cada categoria,
//                as secretas ("???") e o resto em "Ver todas" (Desafios).

$mg = isset($medalhas_grade) && is_array($medalhas_grade) ? $medalhas_grade : array();
$mg_catalogo = $mg['catalogo'] ?? array();
$mg_conquistadas = $mg['conquistadas'] ?? array();
$mg_atual = (int) ($mg['ofensiva_atual'] ?? 0);
$mg_progresso = $mg['progresso'] ?? array();
$mg_raridade = $mg['raridade'] ?? null;
$mg_modo = (string) ($mg['modo'] ?? '');
$mg_vitrine = isset($mg['vitrine']) ? (array) $mg['vitrine'] : null;
$mg_niveis = medalhas_niveis();
$mg_categorias = medalhas_categorias();
$mg_unidades = medalhas_unidades();
$mg_sem_barra = medalhas_regras_sem_barra();

// Agrupa por categoria, na ordem de medalhas_categorias() (as desconhecidas
// no fim), mantendo a ordem do catálogo dentro de cada uma.
$mg_grupos = array_fill_keys(array_keys($mg_categorias), array());
foreach ($mg_catalogo as $mg_medalha) {
    $mg_grupos[$mg_medalha['categoria']][] = $mg_medalha;
}
$mg_grupos = array_filter($mg_grupos);

$mg_card = function (array $mg_medalha, $mg_rotulo_categoria = '') use ($mg_conquistadas, $mg_atual, $mg_progresso, $mg_niveis, $mg_unidades, $mg_sem_barra, $mg_raridade, $mg_vitrine, $mg_categorias) {
    $mg_tem = isset($mg_conquistadas[$mg_medalha['id']]);
    $mg_oculta = !$mg_tem && !empty($mg_medalha['secreta']); // "???"
    $mg_regra = $mg_medalha['regra'] === 'ofensiva_dias'
        ? $mg_medalha['valor'] . ' dias seguidos'
        : $mg_medalha['descricao'];
    // Número atual para a barra: ofensiva atual (T14) ou a contagem da regra.
    $mg_valor_atual = $mg_medalha['regra'] === 'ofensiva_dias' ? $mg_atual : ($mg_progresso[$mg_medalha['regra']] ?? null);
    $mg_na_vitrine = $mg_vitrine !== null && in_array($mg_medalha['id'], $mg_vitrine, true);
?>
    <div class="medalha nivel-<?php echo $mg_oculta ? 'secreta' : $mg_medalha['nivel']; ?><?php echo $mg_tem ? '' : ' is-bloqueada'; ?><?php echo $mg_na_vitrine ? ' is-vitrine' : ''; ?>">
        <?php if ($mg_tem && $mg_vitrine !== null) { ?>
            <form method="POST" action="perfil.php#medalhas" class="medalha-vitrine-form">
                <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="vitrine">
                <input type="hidden" name="idbadge_id" value="<?php echo (int) $mg_medalha['id']; ?>">
                <button type="submit" class="medalha-vitrine-botao" aria-pressed="<?php echo $mg_na_vitrine ? 'true' : 'false'; ?>" title="<?php echo $mg_na_vitrine ? 'Tirar da vitrine' : 'Pôr na vitrine do perfil'; ?>" aria-label="<?php echo ($mg_na_vitrine ? 'Tirar da vitrine: ' : 'Pôr na vitrine: ') . htmlspecialchars($mg_medalha['nome']); ?>"><i class="<?php echo $mg_na_vitrine ? 'fas' : 'far'; ?> fa-star"></i></button>
            </form>
        <?php } ?>
        <?php if ($mg_rotulo_categoria !== '') { ?>
            <small class="medalha-categoria-rotulo"><?php echo htmlspecialchars($mg_rotulo_categoria); ?></small>
        <?php } ?>
        <span class="medalha-icone" aria-hidden="true"><i class="fas <?php echo $mg_tem ? $mg_medalha['icone'] : ($mg_oculta ? 'fa-question' : 'fa-lock'); ?>"></i></span>
        <strong class="medalha-nome"><?php echo $mg_oculta ? '???' : htmlspecialchars($mg_medalha['nome']); ?></strong>
        <span class="medalha-nivel"><?php echo $mg_oculta ? 'Secreta' : $mg_niveis[$mg_medalha['nivel']]; ?></span>
        <small class="medalha-regra"><?php echo $mg_oculta ? 'Continue usando o app para descobrir.' : htmlspecialchars($mg_regra); ?></small>
        <?php if ($mg_tem) { ?>
            <small class="medalha-data"><span class="visually-hidden">Conquistada </span>em <?php echo date('d/m/Y', strtotime($mg_conquistadas[$mg_medalha['id']])); ?><?php echo !empty($mg_medalha['secreta']) ? ' · 🤫 secreta' : ''; ?></small>
        <?php } elseif (!$mg_oculta && $mg_medalha['valor'] > 0 && $mg_valor_atual !== null && !in_array($mg_medalha['regra'], $mg_sem_barra, true)) { ?>
            <div class="medalha-progresso" title="<?php echo $mg_medalha['regra'] === 'ofensiva_dias' ? 'Sua ofensiva atual' : 'Seu progresso'; ?>">
                <div class="progress-bar-custom"><div class="progress-bar-custom-fill" style="width: <?php echo min(100, (int) floor($mg_valor_atual * 100 / $mg_medalha['valor'])); ?>%;"></div></div>
                <small><?php echo min($mg_valor_atual, $mg_medalha['valor']); ?> de <?php echo $mg_medalha['valor']; ?> <?php echo htmlspecialchars($mg_unidades[$mg_medalha['regra']] ?? ''); ?></small>
                <?php if ($mg_medalha['regra'] === 'amens_e_atendidos' && $mg_medalha['valor2'] > 0) { ?>
                    <small><?php echo min((int) ($mg_progresso['oracoes_atendidas'] ?? 0), $mg_medalha['valor2']); ?> de <?php echo $mg_medalha['valor2']; ?> pedidos atendidos</small>
                <?php } ?>
            </div>
        <?php } else { ?>
            <small class="visually-hidden">Bloqueada</small>
        <?php } ?>
        <?php if ($mg_raridade !== null && !$mg_oculta) { ?>
            <small class="medalha-raridade<?php echo ($mg_raridade[$mg_medalha['id']] ?? 0) < 10 ? ' is-rara' : ''; ?>"><?php echo htmlspecialchars(medalhas_raridade_texto($mg_raridade[$mg_medalha['id']] ?? null)); ?></small>
        <?php } ?>
    </div>
<?php
};

$mg_eh_minha = function ($m) use ($mg_conquistadas) {
    return isset($mg_conquistadas[$m['id']]);
};

if ($mg_modo === 'resumo') {
    // Só as conquistadas, mais recentes primeiro. Uma linha (o CSS de
    // .medalhas-grade.is-uma-linha corta no que couber na largura) e, com
    // mais de uma, "Ver todas" abre a lista inteira.
    $mg_minhas = array_values(array_filter($mg_catalogo, $mg_eh_minha));
    usort($mg_minhas, function ($a, $b) use ($mg_conquistadas) {
        return strcmp((string) $mg_conquistadas[$b['id']], (string) $mg_conquistadas[$a['id']]);
    });
?>
    <div class="medalhas-grade is-uma-linha mt-3">
        <?php foreach (array_slice($mg_minhas, 0, 8) as $mg_medalha) { $mg_card($mg_medalha); } ?>
    </div>
    <?php if (count($mg_minhas) > 1) { ?>
        <details class="medalhas-todas">
            <summary>Ver todas as minhas medalhas (<?php echo count($mg_minhas); ?>)</summary>
            <div class="medalhas-grade mt-2">
                <?php foreach ($mg_minhas as $mg_medalha) { $mg_card($mg_medalha); } ?>
            </div>
        </details>
    <?php } ?>
<?php
    return;
}

if ($mg_modo === 'bloqueadas') {
    // Sem bagunça: a próxima de cada categoria, as secretas e o resto
    // guardado em "Ver todas".
    $mg_proximas = array();
    $mg_secretas = array();
    $mg_resto = array();
    foreach ($mg_grupos as $mg_categoria => $mg_lista) {
        foreach ($mg_lista as $mg_medalha) {
            if ($mg_eh_minha($mg_medalha)) {
                continue;
            }
            if (!empty($mg_medalha['secreta'])) {
                $mg_secretas[] = $mg_medalha;
            } elseif (!isset($mg_proximas[$mg_categoria])) {
                $mg_proximas[$mg_categoria] = $mg_medalha;
            } else {
                $mg_resto[$mg_categoria][] = $mg_medalha;
            }
        }
    }
    if ($mg_proximas) { ?>
        <div class="medalhas-categoria">Próximas conquistas</div>
        <div class="medalhas-grade">
            <?php foreach ($mg_proximas as $mg_categoria => $mg_medalha) { $mg_card($mg_medalha, $mg_categorias[$mg_categoria] ?? ucfirst($mg_categoria)); } ?>
        </div>
    <?php }
    if ($mg_secretas) { ?>
        <div class="medalhas-categoria">Secretas <span class="text-muted fw-normal text-lowercase">· <?php echo count($mg_secretas); ?> para descobrir</span></div>
        <div class="medalhas-grade is-secretas">
            <?php foreach ($mg_secretas as $mg_medalha) { $mg_card($mg_medalha); } ?>
        </div>
    <?php }
    if ($mg_resto) { ?>
        <details class="medalhas-todas">
            <summary>Ver todas as que faltam (<?php echo array_sum(array_map('count', $mg_resto)); ?>)</summary>
            <?php foreach ($mg_resto as $mg_categoria => $mg_lista) { ?>
                <div class="medalhas-categoria"><?php echo htmlspecialchars($mg_categorias[$mg_categoria] ?? ucfirst($mg_categoria)); ?></div>
                <div class="medalhas-grade">
                    <?php foreach ($mg_lista as $mg_medalha) { $mg_card($mg_medalha); } ?>
                </div>
            <?php } ?>
        </details>
    <?php }
    return;
}

foreach ($mg_grupos as $mg_categoria => $mg_lista) { ?>
    <div class="medalhas-categoria">
        <?php echo htmlspecialchars($mg_categorias[$mg_categoria] ?? ucfirst($mg_categoria)); ?>
    </div>
    <div class="medalhas-grade">
        <?php foreach ($mg_lista as $mg_medalha) { $mg_card($mg_medalha); } ?>
    </div>
<?php }
