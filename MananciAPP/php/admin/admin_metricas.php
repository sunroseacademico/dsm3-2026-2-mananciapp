<?php
// Métricas de aprendizado e uso (Admin → Métricas, chave "metricas"):
// - funil: cadastro → aprovado → começou a assistir → concluiu uma aula →
//   concluiu um curso;
// - pessoas que abriram o app por dia (30 dias);
// - por curso: quantos começaram e concluíram cada aula;
// - por aula: onde quem não terminou parou (em décimos do vídeo).
// Tudo vem do que o app já grava (id_fiel, id_fiel_acesso_dia, id_matricula,
// id_progresso). Os números ficam 2 min no cache compartilhado.
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));

$curso_id = (int) ($_GET['curso'] ?? 0);
$aula_id = (int) ($_GET['aula'] ?? 0);
$hoje = site_hoje();

// Consultas em php/conexao.php ("MÉTRICAS DO PAINEL"), testadas no CI.
$geral = site_cache_compartilhado('metricas_geral_' . $hoje, 120, function () use ($conn, $hoje) {
    return metricas_geral($conn, $hoje);
});
$geral = is_array($geral) ? $geral : array('funil' => array(), 'dias' => array(), 'hoje' => 0, 'semana' => 0, 'mes' => 0);

// Os 30 dias em ordem, com zero onde ninguém entrou.
$serie_dias = array();
for ($i = 29; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime($hoje . " -$i days"));
    $serie_dias[$dia] = (int) ($geral['dias'][$dia] ?? 0);
}
$maior_dia = max(1, max($serie_dias));

$cursos = array();
$res = mysqli_query($conn, "SELECT IDC_ID, IDC_TITULO FROM ID_CURSO ORDER BY IDC_TITULO");
while ($res && ($l = mysqli_fetch_assoc($res))) {
    $cursos[] = $l;
}

$aulas = array();
if ($curso_id > 0) {
    $aulas = site_cache_compartilhado('metricas_curso_' . $curso_id, 120, function () use ($conn, $curso_id) {
        return metricas_aulas_curso($conn, $curso_id);
    });
    $aulas = is_array($aulas) ? $aulas : array();
}

// Onde param: posição de quem começou e não terminou, em 10 faixas do vídeo.
$aula_sel = null;
$faixas = array_fill(0, 10, 0);
foreach ($aulas as $a) {
    if ($a['id'] === $aula_id) {
        $aula_sel = $a;
    }
}
if ($aula_sel && $aula_sel['duracao'] > 0 && tabela_tem_coluna($conn, 'id_progresso', 'idpr_posicao_segundos')) {
    $contagem = site_cache_compartilhado('metricas_aula_' . $aula_id, 120, function () use ($conn, $aula_id, $aula_sel) {
        return metricas_paradas_aula($conn, $aula_id, $aula_sel['duracao']);
    });
    foreach ((array) $contagem as $faixa => $n) {
        if ($faixa >= 0 && $faixa <= 9) {
            $faixas[$faixa] = $n;
        }
    }
}
$maior_faixa = max(1, max($faixas));
$total_paradas = array_sum($faixas);
$minuto = function ($segundos) {
    $segundos = (int) round($segundos);
    return floor($segundos / 60) . ':' . str_pad((string) ($segundos % 60), 2, '0', STR_PAD_LEFT);
};
$funil_topo = max(1, (int) ($geral['funil'][0][1] ?? 1));
$meses = array(1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Métricas</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
    <style>
        /* Uma série por gráfico: uma cor só, validada contra a superfície de
           cada tema (claro #2563eb, escuro #3b82f6). Textos usam as cores de
           texto, nunca a da barra. */
        .metricas { --barra: #3b82f6; --barra-forte: #3b82f6; --grade: rgba(148, 163, 184, 0.18); }
        :root[data-theme="light"] .metricas { --barra: #2563eb; }
        .metricas .cartao { background: var(--bg-light); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem 1.1rem; margin-bottom: 1.2rem; }
        .metricas h2 { font-size: 1rem; font-weight: 700; margin-bottom: 0.2rem; color: var(--text-main); }
        .metricas .sub { font-size: 0.82rem; color: var(--text-muted); margin-bottom: 0.9rem; }
        .numeros { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.8rem; margin-bottom: 1.2rem; }
        .numero { background: var(--bg-light); border: 1px solid var(--border-color); border-radius: 14px; padding: 0.9rem 1rem; }
        .numero strong { display: block; font-size: 1.8rem; line-height: 1.1; color: var(--text-main); font-variant-numeric: tabular-nums; }
        .numero span { font-size: 0.82rem; color: var(--text-muted); }
        /* Funil: barras horizontais com rótulo e número ao lado. */
        .funil-linha { display: grid; grid-template-columns: minmax(120px, 190px) 1fr auto; gap: 0.7rem; align-items: center; margin-bottom: 0.45rem; font-size: 0.88rem; }
        .funil-trilho { height: 14px; background: var(--grade); border-radius: 4px; overflow: hidden; }
        .funil-barra { height: 100%; background: var(--barra); border-radius: 0 4px 4px 0; min-width: 2px; }
        .funil-valor { font-variant-numeric: tabular-nums; color: var(--text-main); white-space: nowrap; }
        .funil-valor small { color: var(--text-muted); }
        /* Colunas: dias e faixas da aula. */
        .colunas { display: flex; align-items: flex-end; gap: 2px; height: 160px; padding-bottom: 2px; border-bottom: 1px solid var(--grade); }
        .coluna { flex: 1; height: 100%; display: flex; align-items: flex-end; position: relative; }
        .coluna span { display: block; width: 100%; background: var(--barra); border-radius: 4px 4px 0 0; min-height: 1px; }
        .coluna:hover span, .coluna:focus span { filter: brightness(1.25); }
        .coluna:focus { outline: 2px solid var(--primary-light); outline-offset: 2px; }
        .coluna .dica { display: none; position: absolute; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%); white-space: nowrap; background: var(--bg-dark); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 6px; padding: 0.2rem 0.45rem; font-size: 0.75rem; z-index: 2; pointer-events: none; }
        .coluna:hover .dica, .coluna:focus .dica { display: block; }
        .colunas-eixo { display: flex; justify-content: space-between; font-size: 0.72rem; color: var(--text-muted); margin-top: 0.3rem; }
        .metricas details summary { cursor: pointer; font-size: 0.82rem; color: var(--text-muted); margin-top: 0.7rem; }
        .aula-taxa { display: flex; gap: 0.5rem; align-items: center; min-width: 140px; }
        .aula-taxa .funil-trilho { flex: 1; height: 8px; }
    </style>
</head>
<body class="admin-layout admin-shell">
    <?php admin_layout_abrir($conn, 'metricas', 'Métricas', 'fa-chart-column'); ?>
    <div class="metricas">

        <div class="numeros">
            <div class="numero"><strong><?php echo (int) $geral['hoje']; ?></strong><span>abriram o app hoje</span></div>
            <div class="numero"><strong><?php echo (int) $geral['semana']; ?></strong><span>nos últimos 7 dias</span></div>
            <div class="numero"><strong><?php echo (int) $geral['mes']; ?></strong><span>nos últimos 30 dias</span></div>
        </div>

        <section class="cartao" aria-labelledby="tituloFunil">
            <h2 id="tituloFunil">Caminho do fiel</h2>
            <p class="sub">De quem se cadastrou, quantos chegaram a cada etapa (desde o início). A maior queda mostra onde agir.</p>
            <?php foreach ($geral['funil'] as $i => $etapa) {
                $pct = 100 * $etapa[1] / $funil_topo;
                $anterior = $i > 0 ? (int) $geral['funil'][$i - 1][1] : 0; ?>
                <div class="funil-linha">
                    <span><?php echo htmlspecialchars($etapa[0]); ?></span>
                    <div class="funil-trilho" role="img" aria-label="<?php echo htmlspecialchars($etapa[0] . ': ' . $etapa[1]); ?>"><div class="funil-barra" style="width: <?php echo round($pct, 1); ?>%"></div></div>
                    <span class="funil-valor"><?php echo (int) $etapa[1]; ?> <small>
                        <?php echo $i === 0 ? '' : ($anterior > 0 ? '(' . round(100 * $etapa[1] / $anterior) . '% da etapa anterior)' : ''); ?>
                    </small></span>
                </div>
            <?php } ?>
        </section>

        <section class="cartao" aria-labelledby="tituloDias">
            <h2 id="tituloDias">Pessoas que abriram o app, por dia</h2>
            <p class="sub">Últimos 30 dias. Passe o mouse (ou toque) numa coluna para ver o número.</p>
            <div class="colunas" role="img" aria-label="Acessos por dia nos últimos 30 dias; tabela abaixo">
                <?php foreach ($serie_dias as $dia => $n) { $d = strtotime($dia); ?>
                    <div class="coluna" tabindex="0">
                        <span style="height: <?php echo $n > 0 ? max(2, round(100 * $n / $maior_dia, 1)) : 0; ?>%"></span>
                        <div class="dica"><?php echo date('d', $d) . ' ' . $meses[(int) date('n', $d)]; ?> · <?php echo $n; ?> pessoa(s)</div>
                    </div>
                <?php } ?>
            </div>
            <div class="colunas-eixo">
                <?php $chaves = array_keys($serie_dias); ?>
                <span><?php echo date('d/m', strtotime($chaves[0])); ?></span>
                <span>maior dia: <?php echo (int) max($serie_dias); ?></span>
                <span>hoje</span>
            </div>
            <details>
                <summary>Ver como tabela</summary>
                <table class="table table-sm mt-2"><thead><tr><th>Dia</th><th>Pessoas</th></tr></thead><tbody>
                    <?php foreach (array_reverse($serie_dias, true) as $dia => $n) { ?><tr><td><?php echo date('d/m', strtotime($dia)); ?></td><td><?php echo $n; ?></td></tr><?php } ?>
                </tbody></table>
            </details>
        </section>

        <section class="cartao" aria-labelledby="tituloAulas">
            <h2 id="tituloAulas">Aulas de um curso</h2>
            <p class="sub">Quantos começaram e quantos terminaram cada aula. Clique numa aula para ver onde quem não terminou parou.</p>
            <form method="GET" class="mb-3">
                <select name="curso" class="form-select" style="max-width: 420px;" data-enviar-ao-mudar aria-label="Escolher curso">
                    <option value="">Escolha um curso…</option>
                    <?php foreach ($cursos as $c) { ?>
                        <option value="<?php echo (int) $c['IDC_ID']; ?>"<?php echo (int) $c['IDC_ID'] === $curso_id ? ' selected' : ''; ?>><?php echo htmlspecialchars($c['IDC_TITULO']); ?></option>
                    <?php } ?>
                </select>
            </form>
            <?php if ($curso_id > 0 && !$aulas) { ?>
                <p class="text-muted mb-0">Este curso ainda não tem aulas.</p>
            <?php } elseif ($aulas) { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>#</th><th>Aula</th><th>Começaram</th><th>Terminaram</th><th>Taxa de conclusão</th></tr></thead>
                        <tbody>
                            <?php foreach ($aulas as $i => $a) { $taxa = $a['comecaram'] > 0 ? 100 * $a['concluiram'] / $a['comecaram'] : 0; ?>
                                <tr<?php echo $a['id'] === $aula_id ? ' class="table-active"' : ''; ?>>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><a href="?<?php echo htmlspecialchars(http_build_query(array('curso' => $curso_id, 'aula' => $a['id']))); ?>#tituloParadas"><?php echo htmlspecialchars($a['titulo']); ?></a></td>
                                    <td><?php echo $a['comecaram']; ?></td>
                                    <td><?php echo $a['concluiram']; ?></td>
                                    <td>
                                        <div class="aula-taxa">
                                            <div class="funil-trilho"><div class="funil-barra" style="width: <?php echo round($taxa, 1); ?>%"></div></div>
                                            <span class="funil-valor"><?php echo $a['comecaram'] > 0 ? round($taxa) . '%' : '—'; ?></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>

        <?php if ($aula_sel) { ?>
            <section class="cartao" aria-labelledby="tituloParadas">
                <h2 id="tituloParadas">Onde param: <?php echo htmlspecialchars($aula_sel['titulo']); ?></h2>
                <?php if ($aula_sel['duracao'] <= 0) { ?>
                    <p class="text-muted mb-0">Esta aula ainda não tem a duração gravada (abra a aula uma vez como admin para gravar).</p>
                <?php } elseif ($total_paradas === 0) { ?>
                    <p class="text-muted mb-0">Ninguém parou no meio desta aula até agora.</p>
                <?php } else { $passo = $aula_sel['duracao'] / 10; ?>
                    <p class="sub"><?php echo $total_paradas; ?> pessoa(s) começaram e não terminaram. Cada coluna é um décimo do vídeo (<?php echo $minuto($passo); ?> cada); a mais alta é onde mais gente sai.</p>
                    <div class="colunas" role="img" aria-label="Onde quem não terminou parou; tabela abaixo">
                        <?php foreach ($faixas as $f => $n) { ?>
                            <div class="coluna" tabindex="0">
                                <span style="height: <?php echo $n > 0 ? max(2, round(100 * $n / $maior_faixa, 1)) : 0; ?>%"></span>
                                <div class="dica"><?php echo $minuto($f * $passo) . '–' . $minuto(($f + 1) * $passo); ?> · <?php echo $n; ?></div>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="colunas-eixo"><span>início</span><span>meio</span><span>fim (<?php echo $minuto($aula_sel['duracao']); ?>)</span></div>
                    <details>
                        <summary>Ver como tabela</summary>
                        <table class="table table-sm mt-2"><thead><tr><th>Trecho</th><th>Pararam aqui</th></tr></thead><tbody>
                            <?php foreach ($faixas as $f => $n) { ?><tr><td><?php echo $minuto($f * $passo) . '–' . $minuto(($f + 1) * $passo); ?></td><td><?php echo $n; ?></td></tr><?php } ?>
                        </tbody></table>
                    </details>
                <?php } ?>
            </section>
        <?php } ?>
    </div>
    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
</body>
</html>
