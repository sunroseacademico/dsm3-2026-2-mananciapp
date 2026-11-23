<?php
include("../conexao.php");
require_once __DIR__ . '/includes/cursos_helpers.php';
auth_require();

// Só aceita o `id` do conteúdo — URL, título e descrição vêm sempre do
// banco. Antes a página também aceitava ?url=/?titulo=/?descricao= direto
// na querystring: com ?url=<caminho no bucket>, qualquer fiel logado
// recebia uma URL assinada de QUALQUER arquivo do bucket privado (inclusive
// vídeo de curso em rascunho), pulando a checagem de publicação abaixo, e
// ainda podia exibir um título qualquer na página.
$url = "";
$titulo = "Video";
$descricao = "";

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$curso = null;
$aulas = array();
$indice_atual = -1;
$mapa_progresso = array();
$percentual_curso = null;
$posicao_retomar = 0;
$conteudo_encontrado = false;

if ($id > 0) {
    // Confere publicacao (IDCT_PUBLICADO) e, se o video pertence a um curso,
    // que o curso esteja com status PUBLICADO — sem isso, qualquer fiel
    // logado conseguia assistir a QUALQUER video so adivinhando o id
    // (inteiros sequenciais pequenos), inclusive rascunhos que o admin
    // ainda esta preparando. Admin fica isento (ja tem outro caminho de
    // preview no painel, mas mantemos aqui tambem pra nao quebrar testes).
    $eh_admin = ($_SESSION['Usuario_tipo'] ?? '') === 'admin';
    $filtro_publicacao = $eh_admin ? '' : "AND ct.IDCT_PUBLICADO = 1 AND (c.IDC_ID IS NULL OR c.IDC_STATUS = 'PUBLICADO')";
    // Mesma regra de video_conteudo_acessivel() (conexao.php).
    $coluna_gratuito = assinatura_bloqueio_ativo() ? 'COALESCE(c.IDC_GRATUITO, 0)' : '1';
    $sql = "
        SELECT ct.IDCT_URL, ct.IDCT_TITULO, ct.IDCT_DESCRICAO, ct.IDC_ID, $coluna_gratuito AS curso_gratuito
        FROM ID_CONTENT ct
        LEFT JOIN ID_CURSO c ON c.IDC_ID = ct.IDC_ID
        WHERE ct.IDCT_ID = $id
        $filtro_publicacao
        LIMIT 1
    ";
    $res = mysqli_query($conn, $sql);
    if ($res && mysqli_num_rows($res) == 1) {
        $row = mysqli_fetch_assoc($res);

        // Conteúdo pago sem assinatura: nem chega a gerar a URL assinada do vídeo.
        if (!assinatura_curso_liberado($conn, $row['curso_gratuito'])) {
            header('Location: assinar.php?bloqueado=1');
            exit;
        }

        $conteudo_encontrado = true;
        $url = $row['IDCT_URL'] ?? '';
        $titulo = $row['IDCT_TITULO'] ?? $titulo;
        $descricao = $row['IDCT_DESCRICAO'] ?? $descricao;

        $curso_id = (int) ($row['IDC_ID'] ?? 0);
        if ($curso_id > 0) {
            $curso = curso_obter_por_id($conn, $curso_id);
            $aulas = curso_lista_aulas($conn, $curso_id);
            foreach ($aulas as $i => $aula) {
                if ((int) $aula['IDCT_ID'] === $id) {
                    $indice_atual = $i;
                    break;
                }
            }

            $fiel_id = auth_resolve_fiel_id();
            if ($fiel_id) {
                $matricula = curso_obter_matricula($conn, $fiel_id, $curso_id);
                if ($matricula) {
                    $mapa_progresso = curso_mapa_progresso($conn, (int) $matricula['IDMATR_ID']);
                    $percentual_curso = (float) $matricula['IDMATR_PERCENTUAL'];

                    // Onde parou nesta aula (seção 46 do bancosupabase.md).
                    // Sem a coluna, a consulta só falha e o vídeo começa do zero.
                    $matricula_id_posicao = (int) $matricula['IDMATR_ID'];
                    $res_posicao = mysqli_query($conn, "SELECT IDPR_POSICAO_SEGUNDOS, IDPR_TEMPO_ASSISTIDO_SEGUNDOS FROM ID_PROGRESSO WHERE IDMATR_ID = $matricula_id_posicao AND IDCT_ID = $id LIMIT 1");
                    $linha_posicao = $res_posicao ? mysqli_fetch_assoc($res_posicao) : null;
                    if ($linha_posicao) {
                        $posicao_retomar = max(0, (int) $linha_posicao['IDPR_POSICAO_SEGUNDOS']);
                        $tempo_ja_assistido = max(0, (int) $linha_posicao['IDPR_TEMPO_ASSISTIDO_SEGUNDOS']);
                    }
                }
            }
        }

        // Marca quando a aula foi aberta e até onde já tinha sido vista:
        // ajax_progresso.php não aceita avanço maior que o tempo real
        // passado desde então (ver aula_progresso_limite()).
        aula_registrar_abertura($id, $tempo_ja_assistido ?? 0);
    }
}

$aula_anterior = ($indice_atual > 0) ? $aulas[$indice_atual - 1] : null;
$aula_proxima = ($indice_atual >= 0 && $indice_atual < count($aulas) - 1) ? $aulas[$indice_atual + 1] : null;

// Videos novos ficam num bucket PRIVADO do Supabase Storage: o que esta salvo no
// banco e so o caminho dentro do bucket, entao pedimos uma URL assinada de leitura
// (valida por um tempo) toda vez que a pagina carrega - so chega ate aqui porque
// auth_require() ja confirmou que o usuario esta logado.
// Videos antigos, de antes da migracao para o Render, podem ainda apontar para a
// pasta local videos/ (mantido so por compatibilidade).
$url = site_resolver_url_video($url);

// Primeira página dos comentários na aba padrão (Mais relevantes; sem a
// tabela de votos, Mais recentes). O resto vem pelo "Ver mais" e as outras
// abas por ajax_video_comentarios.php. Busca um a mais só para saber se há mais.
$comentarios_ordem = video_comentarios_ordem_valida($conn, 'relevantes');
$comentarios_votos = video_votos_disponivel($conn);
$comentarios_citacoes = video_citacoes_disponivel($conn);
$comentarios = $conteudo_encontrado ? video_listar_comentarios($conn, $id, VIDEO_COMENTARIOS_POR_PAGINA + 1, 0, $comentarios_ordem) : array();
$comentarios_tem_mais = count($comentarios) > VIDEO_COMENTARIOS_POR_PAGINA;
$comentarios = array_slice($comentarios, 0, VIDEO_COMENTARIOS_POR_PAGINA);
$comentarios_total = $comentarios_tem_mais ? video_contar_comentarios($conn, $id) : count($comentarios);
$comentario_destaque = $conteudo_encontrado ? video_comentario_destaque($conn, $id, $comentarios_ordem) : null;
// Nota do pastor fixada (T19): fora da lista, no topo; conta no título.
$nota_fixada = $conteudo_encontrado ? video_nota_fixada($conn, $id) : null;
$comentarios_total += $nota_fixada ? 1 : 0;
// Link de comentário compartilhado (T20): ?comentario=ID abre a aula já no
// comentário (ou na resposta, com a conversa aberta).
$comentario_link_id = isset($_GET['comentario']) ? (int) $_GET['comentario'] : 0;
$comentario_link = $conteudo_encontrado ? video_comentario_do_link($conn, $id, $comentario_link_id) : null;

// Minhas notas (T27): anotações do próprio fiel nesta aula. Sem a tabela
// (seção 58 do bancosupabase.md), a aba mostra "ainda não disponível".
$notas_disponivel = $conteudo_encontrado && notas_aula_disponivel($conn);
$fiel_id_notas = $notas_disponivel ? auth_resolve_fiel_id() : 0;
$notas = $fiel_id_notas > 0 ? notas_aula_listar($conn, $fiel_id_notas, $id) : array();

// Resumos da aula (T29): o do próprio fiel vai no topo, à parte da lista.
// Sem as tabelas (seção 60 do bancosupabase.md), a aba mostra "ainda não
// disponível". ?resumo=ID (link das notificações) abre já no resumo.
$resumos_disponivel = $conteudo_encontrado && resumos_disponivel($conn);
$resumos = $resumos_disponivel ? resumos_listar($conn, $id, 'votados', RESUMOS_POR_PAGINA + 1) : array();
$resumos_tem_mais = count($resumos) > RESUMOS_POR_PAGINA;
$resumos = array_slice($resumos, 0, RESUMOS_POR_PAGINA);
$meu_resumo = $resumos_disponivel ? resumo_obter($conn, 0, auth_resolve_fiel_id(), $id) : null;
$resumos_total = $resumos_disponivel ? resumos_contar($conn, $id) : 0;
$resumo_link = ($resumos_disponivel && isset($_GET['resumo'])) ? resumo_obter($conn, (int) $_GET['resumo']) : null;
if ($resumo_link && $resumo_link['idct_id'] !== $id) {
    $resumo_link = null; // resumo de outra aula
}

// Orações (T28): mural único de pedidos da igreja (não só desta aula). Sem
// as tabelas (seção 59 do bancosupabase.md), a aba mostra "ainda não
// disponível". ?oracao=ID (link das notificações) abre já no pedido.
$oracoes_disponivel = $conteudo_encontrado && oracoes_disponivel($conn);
$oracoes = $oracoes_disponivel ? oracoes_listar($conn, 'abertos', ORACOES_POR_PAGINA + 1) : array();
$oracoes_tem_mais = count($oracoes) > ORACOES_POR_PAGINA;
$oracoes = array_slice($oracoes, 0, ORACOES_POR_PAGINA);
$oracoes_abertas = ($oracoes_disponivel && $oracoes_tem_mais) ? oracoes_contar($conn, 'abertos') : count($oracoes);
$oracao_link = ($oracoes_disponivel && isset($_GET['oracao'])) ? oracao_obter($conn, (int) $_GET['oracao']) : null;

// Só aulas de curso gravam progresso; vídeo avulso fica sem contador.
$visualizacoes = ($conteudo_encontrado && $curso) ? conteudo_total_visualizacoes($conn, $id) : 0;

// Velocidade preferida do fiel (seção 45 do bancosupabase.md). Se a coluna
// ainda não existir no banco, a consulta só falha e o vídeo fica em 1x.
$velocidade_preferida = 1.0;
$fiel_id_velocidade = $url !== '' ? auth_resolve_fiel_id() : 0;
if ($fiel_id_velocidade > 0) {
    $res_vel = mysqli_query($conn, "SELECT IDPREF_VELOCIDADE_VIDEO FROM ID_PREFERENCIA WHERE IDF_ID = $fiel_id_velocidade LIMIT 1");
    $linha_vel = $res_vel ? mysqli_fetch_assoc($res_vel) : null;
    if ($linha_vel && in_array((float) $linha_vel['IDPREF_VELOCIDADE_VIDEO'], VIDEO_VELOCIDADES, true)) {
        $velocidade_preferida = (float) $linha_vel['IDPREF_VELOCIDADE_VIDEO'];
    }
}

if (!$conteudo_encontrado) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | <?php echo htmlspecialchars($titulo); ?></title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <style>
        /* Offset dinâmico para evitar corte quando a navbar fixa quebra linhas no desktop */
        body.video-player-page {
            --video-nav-offset: calc(var(--navbar-height) + 24px);
            padding-top: var(--video-nav-offset) !important;
        }

        body.video-player-page .video-main {
            padding-top: clamp(0.6rem, 1.6vh, 1rem);
        }

        body.video-player-page .video-title {
            margin-top: 0;
            line-height: 1.25;
        }

        /* Ajustes para desktop intermediário, reduzindo o "zoom" percebido da barra */
        @media (max-width: 1300px) {
            body.video-player-page .navbar .container-fluid {
                padding-left: 0.9rem !important;
                padding-right: 0.9rem !important;
            }

            body.video-player-page .navbar-brand span {
                font-size: 1.05rem;
            }

            body.video-player-page .navbar .d-flex.align-items-center.gap-3 {
                gap: 0.6rem !important;
            }

            body.video-player-page .navbar .nav-link {
                font-size: 0.95rem;
                padding-left: 0.45rem;
                padding-right: 0.45rem;
            }

            body.video-player-page .navbar .btn.btn-sm {
                font-size: 0.9rem;
                padding: 0.28rem 0.55rem;
            }
        }

        @media (max-width: 768px) {
            body.video-player-page {
                --video-nav-offset: calc(var(--navbar-height) + 14px);
            }
        }
    </style>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="video-player-page">
    <?php $navbar_active = 'cursos'; include __DIR__ . '/includes/navbar_fiel.php'; ?>
    <main class="container-fluid py-4 video-main" style="max-width: 1400px; margin: 0 auto;">
        <?php if ($curso) { ?>
            <div class="mb-2">
                <a href="curso.php?id=<?php echo (int) $curso['IDC_ID']; ?>" class="text-decoration-none" style="color: var(--primary-light); font-size: 0.9rem;">
                    <i class="fas fa-chevron-left me-1"></i><?php echo htmlspecialchars($curso['IDC_TITULO']); ?>
                </a>
            </div>
        <?php } ?>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h4 class="mb-0 video-title"><?php echo htmlspecialchars($titulo); ?></h4>
            <?php if ($visualizacoes > 0) { ?>
                <span class="video-visualizacoes">
                    <i class="far fa-eye"></i><?php echo number_format($visualizacoes, 0, ',', '.'); ?> <?php echo $visualizacoes === 1 ? 'visualização' : 'visualizações'; ?>
                </span>
            <?php } ?>
        </div>

        <div class="<?php echo $curso ? 'player-layout' : ''; ?>">
            <div>
                <?php if($url != ""): ?>
                    <div class="player-video-wrap" style="background:#000; border-radius: 10px; overflow: hidden;">
                        <video id="playerVideo" controls autoplay x-webkit-airplay="allow" style="width:100%; max-height:70vh;" src="<?php echo htmlspecialchars($url); ?>">
                            Seu navegador não suporta vídeo HTML5.
                        </video>
                        <?php if ($curso) { ?>
                            <!-- Fim da aula: contagem para a próxima ou aviso de última aula -->
                            <div class="player-fim" id="playerFim" hidden>
                                <div class="player-fim-card" role="dialog" aria-labelledby="playerFimTitulo">
                                    <?php if ($aula_proxima) { ?>
                                        <small class="player-fim-rotulo">Próxima aula</small>
                                        <div class="player-fim-titulo" id="playerFimTitulo"><?php echo htmlspecialchars($aula_proxima['IDCT_TITULO']); ?></div>
                                        <div class="player-fim-contagem" aria-live="polite">Começa em <strong id="playerFimSegundos">5</strong>s</div>
                                        <div class="player-fim-barra"><div id="playerFimBarra"></div></div>
                                        <div class="player-fim-acoes">
                                            <a href="assistir_video.php?id=<?php echo (int) $aula_proxima['IDCT_ID']; ?>" class="player-fim-btn player-fim-btn-principal" id="playerFimIr">
                                                <i class="fas fa-play me-2"></i>Assistir agora
                                            </a>
                                            <button type="button" class="player-fim-btn player-fim-btn-secundario" id="playerFimFechar">Cancelar</button>
                                        </div>
                                    <?php } else { ?>
                                        <i class="fas fa-flag-checkered player-fim-icone"></i>
                                        <div class="player-fim-titulo" id="playerFimTitulo">Esta foi a última aula do curso</div>
                                        <div class="player-fim-acoes">
                                            <a href="curso.php?id=<?php echo (int) $curso['IDC_ID']; ?>" class="player-fim-btn player-fim-btn-principal" id="playerFimIr">Ver o curso</a>
                                            <button type="button" class="player-fim-btn player-fim-btn-secundario" id="playerFimFechar">Fechar</button>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="player-toolbar">
                        <div class="player-retomar" id="retomarAviso" hidden>
                            <i class="fas fa-clock-rotate-left"></i>
                            <span>Continuando de <strong id="retomarTempo"></strong></span>
                            <button type="button" class="player-retomar-inicio" id="retomarInicio">Começar do início</button>
                        </div>
                        <!-- T25: só aparece quando há uma TV (AirPlay/Chromecast) disponível -->
                        <button type="button" class="btn btn-sm btn-outline-light player-transmitir-btn" id="transmitirBtn" hidden aria-label="Transmitir para a TV">
                            <i class="fas fa-tv me-2" aria-hidden="true"></i><span id="transmitirRotulo">Transmitir</span>
                        </button>
                        <!-- Janela flutuante: só aparece onde o navegador permite -->
                        <button type="button" class="btn btn-sm btn-outline-light" id="pipBtn" hidden aria-label="Assistir em janela flutuante" title="Janela flutuante: continua tocando enquanto você usa outro app ou aba">
                            <i class="fas fa-up-right-from-square me-2" aria-hidden="true"></i><span id="pipRotulo">Janela flutuante</span>
                        </button>
                        <div class="dropdown">
                            <button type="button" class="btn btn-sm btn-outline-light player-velocidade-btn" id="velocidadeBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Velocidade do vídeo">
                                <i class="fas fa-gauge-high me-2"></i>Velocidade <strong id="velocidadeAtual">1x</strong>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end player-velocidade-menu" aria-labelledby="velocidadeBtn" id="velocidadeMenu">
                                <?php foreach (VIDEO_VELOCIDADES as $vel) { ?>
                                    <li>
                                        <button type="button" class="dropdown-item" data-velocidade="<?php echo $vel; ?>">
                                            <?php echo str_replace('.', ',', (string) $vel); ?>x<?php echo $vel == 1 ? ' <span class="text-muted">(normal)</span>' : ''; ?>
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </li>
                                <?php } ?>
                            </ul>
                        </div>
                    </div>
                    <p class="mt-3 text-info"><?php echo htmlspecialchars($descricao); ?></p>
                <?php else: ?>
                    <div class="alert alert-warning">Vídeo não encontrado ou link inválido.</div>
                <?php endif; ?>

                <?php if ($curso) { ?>
                    <div class="player-nav-buttons">
                        <?php if ($aula_anterior) { ?>
                            <a href="assistir_video.php?id=<?php echo (int) $aula_anterior['IDCT_ID']; ?>" class="btn btn-outline-light">
                                <i class="fas fa-chevron-left me-2"></i>Aula anterior
                            </a>
                        <?php } else { ?><span></span><?php } ?>
                        <?php if ($aula_proxima) { ?>
                            <a href="assistir_video.php?id=<?php echo (int) $aula_proxima['IDCT_ID']; ?>" class="btn btn-play">
                                Próxima aula<i class="fas fa-chevron-right ms-2"></i>
                            </a>
                        <?php } ?>
                    </div>
                <?php } ?>

                <?php if ($conteudo_encontrado) { ?>
                    <section class="video-comentarios" id="videoComentarios">
                        <!-- Abas abaixo do vídeo: Comunidade (comentários), Resumos (T29), Orações (T28) e Minhas notas (T27) -->
                        <div class="aula-abas" id="aulaAbas" role="tablist" aria-label="Comunidade, resumos, orações e minhas notas">
                            <button type="button" role="tab" class="aula-aba is-ativa" id="abaComunidade" data-aba="comunidade" aria-controls="painelComunidade" aria-selected="true">
                                <i class="far fa-comments" aria-hidden="true"></i>Comunidade <span class="aula-aba-num" id="comentariosTotal">(<?php echo $comentarios_total; ?>)</span>
                            </button>
                            <button type="button" role="tab" class="aula-aba" id="abaResumos" data-aba="resumos" aria-controls="painelResumos" aria-selected="false" tabindex="-1">
                                <i class="fas fa-feather-pointed" aria-hidden="true"></i>Resumos <span class="aula-aba-num" id="resumosTotal"><?php echo $resumos_total ? '(' . $resumos_total . ')' : ''; ?></span>
                            </button>
                            <button type="button" role="tab" class="aula-aba" id="abaOracoes" data-aba="oracoes" aria-controls="painelOracoes" aria-selected="false" tabindex="-1">
                                <i class="fas fa-hands-praying" aria-hidden="true"></i>Orações <span class="aula-aba-num" id="oracoesTotal"><?php echo $oracoes_abertas ? '(' . $oracoes_abertas . ')' : ''; ?></span>
                            </button>
                            <button type="button" role="tab" class="aula-aba" id="abaNotas" data-aba="notas" aria-controls="painelNotas" aria-selected="false" tabindex="-1">
                                <i class="far fa-note-sticky" aria-hidden="true"></i>Minhas notas <span class="aula-aba-num" id="notasTotal"><?php echo $notas ? '(' . count($notas) . ')' : ''; ?></span>
                            </button>
                        </div>

                        <div class="aula-painel" id="painelComunidade" role="tabpanel" aria-labelledby="abaComunidade">
                        <form class="video-comentario-form" id="comentarioForm">
                            <span class="presenca-avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($_SESSION['Usuario_nome'] ?? 'U', 0, 1))); ?></span>
                            <div class="flex-grow-1">
                                <textarea id="comentarioTexto" class="form-control" rows="1" maxlength="1000" placeholder="Escreva o que você achou desse vídeo…" required></textarea>
                                <?php if ($comentarios_citacoes) { ?>
                                    <!-- T21: momento da aula e versículo citados no comentário -->
                                    <div class="video-comentario-anexos" id="comentarioAnexos">
                                        <span class="video-comentario-anexo" id="anexoMomento" hidden>
                                            <i class="far fa-clock" aria-hidden="true"></i><span id="anexoMomentoTexto"></span>
                                            <button type="button" id="anexoMomentoRemover" aria-label="Tirar o momento da aula"><i class="fas fa-times"></i></button>
                                        </span>
                                        <span class="video-comentario-anexo is-versiculo" id="anexoVersiculo" hidden>
                                            <i class="fas fa-book-bible" aria-hidden="true"></i>
                                            <input type="text" id="anexoVersiculoTexto" maxlength="<?php echo (int) VIDEO_VERSICULO_MAX; ?>" placeholder="Ex.: Jo 3:16" aria-label="Versículo (livro capítulo:versículo)" autocomplete="off">
                                            <button type="button" id="anexoVersiculoRemover" aria-label="Tirar o versículo"><i class="fas fa-times"></i></button>
                                        </span>
                                    </div>
                                <?php } ?>
                                <div class="video-comentario-acoes" id="comentarioAcoes">
                                    <?php if ($comentarios_citacoes) { ?>
                                        <?php if ($url !== '') { ?>
                                            <button type="button" class="video-comentario-citar" id="citarMomento" title="Marca o minuto em que o vídeo está agora"><i class="far fa-clock" aria-hidden="true"></i>Momento da aula</button>
                                        <?php } ?>
                                        <button type="button" class="video-comentario-citar" id="citarVersiculo" title="Cita um versículo (ex.: Jo 3:16)"><i class="fas fa-book-bible" aria-hidden="true"></i>Versículo</button>
                                    <?php } ?>
                                    <small class="text-danger me-auto" id="comentarioErro"></small>
                                    <button type="button" class="btn btn-sm btn-outline-light" id="comentarioCancelar">Cancelar</button>
                                    <button type="submit" class="btn btn-sm btn-play" id="comentarioEnviar">Comentar</button>
                                </div>
                            </div>
                        </form>

                        <div class="video-comentarios-ordens" id="comentariosOrdens" role="tablist" aria-label="Ordenar comentários">
                            <?php foreach (video_comentarios_ordens() as $valor => $rotulo) {
                                if ($valor === 'relevantes' && !$comentarios_votos) continue; ?>
                                <button type="button" role="tab" class="video-comentarios-ordem<?php echo $valor === $comentarios_ordem ? ' is-ativa' : ''; ?>" data-ordem="<?php echo $valor; ?>" aria-selected="<?php echo $valor === $comentarios_ordem ? 'true' : 'false'; ?>"><?php echo htmlspecialchars($rotulo); ?></button>
                            <?php } ?>
                        </div>

                        <ul class="video-comentarios-lista" id="comentariosLista"></ul>
                        <div class="video-comentarios-mais" id="comentariosMaisBox" hidden>
                            <button type="button" class="btn btn-sm btn-outline-light" id="comentariosMais"></button>
                            <small class="text-danger d-block mt-2" id="comentariosMaisErro"></small>
                        </div>
                        </div>

                        <div class="aula-painel" id="painelResumos" role="tabpanel" aria-labelledby="abaResumos" hidden>
                            <p class="aula-notas-aviso"><i class="fas fa-feather-pointed" aria-hidden="true"></i>Resumos desta aula escritos pela comunidade. Escreva o seu com suas palavras — ajuda quem vai assistir depois.</p>
                            <?php if (!$resumos_disponivel) { ?>
                                <p class="video-comentarios-vazio">Os resumos ainda não estão disponíveis.</p>
                            <?php } else { ?>
                                <div class="resumo-novo" id="resumoNovo"<?php echo $meu_resumo ? ' hidden' : ''; ?>>
                                    <button type="button" class="btn btn-sm btn-outline-light resumo-abrir" id="resumoAbrir"><i class="fas fa-pen me-2" aria-hidden="true"></i>Escrever meu resumo</button>
                                    <form class="aula-nota-form" id="resumoForm" hidden>
                                        <textarea id="resumoTexto" class="form-control" rows="8" maxlength="<?php echo (int) RESUMO_TEXTO_MAX; ?>" placeholder="Com suas palavras: qual foi a mensagem principal da aula? Quais versículos e ideias marcaram? O que você leva para a sua vida?" aria-label="Meu resumo da aula"></textarea>
                                        <div class="aula-nota-form-rodape">
                                            <button type="button" class="video-comentario-citar" id="resumoTrazerNotas" hidden title="Coloca suas notas desta aula no texto, para você reescrever"><i class="far fa-note-sticky" aria-hidden="true"></i><span>Trazer minhas notas</span></button>
                                            <small class="text-danger me-auto" id="resumoErro" role="alert"></small>
                                            <small class="aula-nota-contador" id="resumoContador"></small>
                                            <button type="button" class="btn btn-sm btn-outline-light" id="resumoCancelar">Cancelar</button>
                                            <button type="submit" class="btn btn-sm btn-play" id="resumoPublicar">Publicar resumo</button>
                                        </div>
                                    </form>
                                </div>
                                <ul class="video-comentarios-lista resumo-meu" id="resumoMeu"></ul>

                                <div class="video-comentarios-ordens" id="resumosOrdens" role="group" aria-label="Ordenar resumos">
                                    <?php foreach (resumos_ordens() as $valor => $rotulo) { ?>
                                        <button type="button" class="video-comentarios-ordem<?php echo $valor === 'votados' ? ' is-ativa' : ''; ?>" data-ordem="<?php echo $valor; ?>" aria-pressed="<?php echo $valor === 'votados' ? 'true' : 'false'; ?>"><?php echo htmlspecialchars($rotulo); ?></button>
                                    <?php } ?>
                                </div>

                                <ul class="video-comentarios-lista" id="resumosLista"></ul>
                                <div class="video-comentarios-mais" id="resumosMaisBox" hidden>
                                    <button type="button" class="btn btn-sm btn-outline-light" id="resumosMais">Ver mais resumos</button>
                                    <small class="text-danger d-block mt-2" id="resumosMaisErro"></small>
                                </div>
                            <?php } ?>
                        </div>

                        <div class="aula-painel" id="painelOracoes" role="tabpanel" aria-labelledby="abaOracoes" hidden>
                            <p class="aula-notas-aviso"><i class="fas fa-hands-praying" aria-hidden="true"></i>Pedidos de oração de toda a igreja. Orou por alguém? Toque em "Amém" — a pessoa fica sabendo que tem gente orando por ela.</p>
                            <?php if (!$oracoes_disponivel) { ?>
                                <p class="video-comentarios-vazio">Os pedidos de oração ainda não estão disponíveis.</p>
                            <?php } else { ?>
                                <form class="video-comentario-form" id="oracaoForm">
                                    <span class="presenca-avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($_SESSION['Usuario_nome'] ?? 'U', 0, 1))); ?></span>
                                    <div class="flex-grow-1">
                                        <textarea id="oracaoTexto" class="form-control" rows="1" maxlength="<?php echo (int) ORACAO_TEXTO_MAX; ?>" placeholder="Pelo que podemos orar por você?" aria-label="Pedido de oração"></textarea>
                                        <div class="video-comentario-acoes" id="oracaoAcoes">
                                            <div class="form-check mb-0 me-auto">
                                                <input class="form-check-input" type="checkbox" id="oracaoAnonimo">
                                                <label class="form-check-label small" for="oracaoAnonimo">Pedir sem o meu nome</label>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-light" id="oracaoCancelar">Cancelar</button>
                                            <button type="submit" class="btn btn-sm btn-play" id="oracaoEnviar">Pedir oração</button>
                                            <small class="text-danger w-100" id="oracaoErro" role="alert"></small>
                                        </div>
                                    </div>
                                </form>

                                <div class="video-comentarios-ordens" id="oracoesFiltros" role="group" aria-label="Filtrar pedidos">
                                    <?php foreach (oracoes_filtros() as $valor => $rotulo) { ?>
                                        <button type="button" class="video-comentarios-ordem<?php echo $valor === 'abertos' ? ' is-ativa' : ''; ?>" data-filtro="<?php echo $valor; ?>" aria-pressed="<?php echo $valor === 'abertos' ? 'true' : 'false'; ?>"><?php echo htmlspecialchars($rotulo); ?></button>
                                    <?php } ?>
                                </div>

                                <ul class="video-comentarios-lista" id="oracoesLista"></ul>
                                <div class="video-comentarios-mais" id="oracoesMaisBox" hidden>
                                    <button type="button" class="btn btn-sm btn-outline-light" id="oracoesMais">Ver mais pedidos</button>
                                    <small class="text-danger d-block mt-2" id="oracoesMaisErro"></small>
                                </div>
                            <?php } ?>
                        </div>

                        <div class="aula-painel" id="painelNotas" role="tabpanel" aria-labelledby="abaNotas" hidden>
                            <p class="aula-notas-aviso"><i class="fas fa-lock" aria-hidden="true"></i>Só você vê suas notas.<?php if ($url !== '') { ?> Elas guardam o minuto da aula — toque nele para voltar ao trecho.<?php } ?></p>
                            <?php if (!$notas_disponivel) { ?>
                                <p class="video-comentarios-vazio">As notas ainda não estão disponíveis.</p>
                            <?php } else { ?>
                                <form class="aula-nota-form" id="notaForm">
                                    <textarea id="notaTexto" class="form-control" rows="3" maxlength="<?php echo (int) NOTA_AULA_TEXTO_MAX; ?>" placeholder="Anote o que chamou sua atenção nesta aula…" aria-label="Nova nota"></textarea>
                                    <div class="aula-nota-form-rodape">
                                        <?php if ($url !== '') { ?>
                                            <span class="video-comentario-anexo" id="notaMomento" hidden>
                                                <i class="far fa-clock" aria-hidden="true"></i><span id="notaMomentoTexto"></span>
                                                <button type="button" id="notaMomentoRemover" aria-label="Salvar sem o minuto da aula"><i class="fas fa-times"></i></button>
                                            </span>
                                            <button type="button" class="video-comentario-citar" id="notaMarcarMomento" title="Usa o minuto em que o vídeo está agora"><i class="far fa-clock" aria-hidden="true"></i><span>Marcar o minuto atual</span></button>
                                        <?php } ?>
                                        <small class="text-danger me-auto" id="notaErro" role="alert"></small>
                                        <small class="aula-nota-contador" id="notaContador" hidden></small>
                                        <button type="submit" class="btn btn-sm btn-play" id="notaSalvar">Salvar nota</button>
                                    </div>
                                </form>
                                <ul class="aula-notas-lista" id="notasLista"></ul>
                            <?php } ?>
                        </div>
                    </section>
                <?php } ?>
            </div>

            <?php if ($curso) { ?>
                <aside class="player-sidebar">
                    <?php if ($percentual_curso !== null) { ?>
                        <div class="mb-3">
                            <div class="progress-bar-custom"><div class="progress-bar-custom-fill" style="width:<?php echo $percentual_curso; ?>%;"></div></div>
                            <small class="text-muted"><?php echo (int) round($percentual_curso); ?>% do curso concluído</small>
                        </div>
                    <?php } ?>
                    <?php foreach ($aulas as $i => $aula) { ?>
                        <?php
                            $status_aula = $mapa_progresso[(int) $aula['IDCT_ID']]['IDPR_STATUS'] ?? 'NAO_INICIADO';
                            $concluida = $status_aula === 'CONCLUIDO';
                            $atual = ((int) $aula['IDCT_ID'] === $id);
                            $percentual_aula = curso_percentual_aula_em_andamento($mapa_progresso, (int) $aula['IDCT_ID']);
                        ?>
                        <a href="assistir_video.php?id=<?php echo (int) $aula['IDCT_ID']; ?>" class="lesson-row <?php echo $concluida ? 'is-done' : ''; ?> <?php echo $atual ? 'is-current' : ''; ?>" style="text-decoration:none; color: var(--text-main);">
                            <div class="lesson-row-number"><?php echo $concluida ? '<i class="fas fa-check"></i>' : ($i + 1); ?></div>
                            <div class="lesson-row-body">
                                <div class="lesson-row-title"><?php echo htmlspecialchars($aula['IDCT_TITULO']); ?></div>
                                <?php if ($percentual_aula !== null) { ?>
                                    <div class="lesson-row-progress" title="<?php echo (int) round($percentual_aula); ?>% assistido"><div style="width: <?php echo $percentual_aula; ?>%;"></div></div>
                                <?php } ?>
                            </div>
                        </a>
                    <?php } ?>
                </aside>
            <?php } ?>
        </div>
    </main>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        // Esqueleto enquanto comentários, orações e resumos carregam: blocos
        // cinza no formato do que vai chegar, no lugar do girador (como no
        // YouTube). O "Carregando…" fica para leitores de tela.
        var ESQUELETO_LISTA = '<li class="esqueleto-lista" role="status"><span class="visually-hidden">Carregando…</span>'
            + new Array(4).join('<span class="esqueleto-item" aria-hidden="true"><span class="esqueleto-avatar"></span><span class="esqueleto-textos"><span class="esqueleto-linha"></span><span class="esqueleto-linha is-curta"></span></span></span>')
            + '</li>';
        (function () {
            var navbar = document.querySelector('.navbar-fiel');
            if (!navbar) return;

            function updateVideoPageOffset() {
                var navHeight = Math.ceil(navbar.getBoundingClientRect().height || 0);
                var extra = window.innerWidth <= 768 ? 14 : 18;
                document.body.style.setProperty('--video-nav-offset', (navHeight + extra) + 'px');
            }

            window.addEventListener('load', updateVideoPageOffset);
            window.addEventListener('resize', updateVideoPageOffset);
            updateVideoPageOffset();
        })();
    </script>
    <?php if ($id > 0 && $url !== "") { ?>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        (function () {
            var video = document.getElementById('playerVideo');
            var conteudoId = <?php echo (int) $id; ?>;
            // Última aula de um curso (comemorarCurso() no fim dela).
            var ULTIMA_AULA_DO_CURSO = <?php echo ($curso && !$aula_proxima) ? 'true' : 'false'; ?>;
            if (!video || !conteudoId) return;

            var maxTime = 0;
            video.addEventListener('timeupdate', function () {
                if (video.currentTime > maxTime) maxTime = video.currentTime;
            });

            // Voltar do ponto onde parou. Não retoma se parou nos primeiros
            // segundos ou já no finzinho (aí começa do início).
            var posicaoSalva = <?php echo (int) $posicao_retomar; ?>;
            var aviso = document.getElementById('retomarAviso');

            // Link do vídeo renovado (ver "video.addEventListener('error'"
            // mais abaixo): continua do ponto exato em que estava, sem esperar
            // o progresso gravado no servidor.
            var chaveRenovar = 'video_renovado_' + conteudoId;
            try {
                var renovado = JSON.parse(sessionStorage.getItem(chaveRenovar) || 'null');
                if (renovado && Date.now() - renovado.em < 120000 && renovado.posicao > 0) {
                    posicaoSalva = renovado.posicao;
                }
            } catch (e) {}

            function formatarTempo(segundos) {
                var h = Math.floor(segundos / 3600);
                var m = Math.floor((segundos % 3600) / 60);
                var s = segundos % 60;
                var mmss = (h > 0 && m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                return h > 0 ? h + ':' + mmss : mmss;
            }

            function retomar() {
                var duracao = video.duration;
                if (posicaoSalva < 5) return;
                if (isFinite(duracao) && duracao > 0 && posicaoSalva > duracao - 10) return;
                video.currentTime = posicaoSalva;

                if (!aviso) return;
                document.getElementById('retomarTempo').textContent = formatarTempo(posicaoSalva);
                aviso.hidden = false;
                var esconder = setTimeout(function () { aviso.hidden = true; }, 10000);
                document.getElementById('retomarInicio').addEventListener('click', function () {
                    clearTimeout(esconder);
                    video.currentTime = 0;
                    aviso.hidden = true;
                });
            }

            // readyState >= 1: os dados do vídeo (duração) já chegaram antes
            // deste script rodar — antes disso, mudar currentTime não funciona.
            if (video.readyState >= 1) {
                retomar();
            } else {
                video.addEventListener('loadedmetadata', retomar, { once: true });
            }

            function enviarProgresso(concluido) {
                var payload = JSON.stringify({
                    conteudo_id: conteudoId,
                    segundos_assistidos: Math.round(maxTime),
                    // Aula terminada volta para o início na próxima vez.
                    posicao_segundos: concluido ? 0 : Math.floor(video.currentTime || 0),
                    // Grava a duração da aula se ainda não estiver no banco.
                    duracao_segundos: isFinite(video.duration) ? Math.round(video.duration) : 0,
                    concluido: !!concluido,
                    csrf_token: <?php echo json_encode(csrf_token()); ?>
                });
                // Na última aula, fetch (com keepalive, que também sobrevive ao
                // fechar a página) em vez do beacon: a resposta diz se o curso
                // chegou a 100%, para comemorar e levar ao certificado.
                if (concluido && navigator.sendBeacon && !ULTIMA_AULA_DO_CURSO) {
                    navigator.sendBeacon('ajax_progresso.php', new Blob([payload], { type: 'application/json' }));
                } else {
                    fetch('ajax_progresso.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: payload,
                        keepalive: true
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (d && d.meta && d.meta.cumpriu_agora) avisarMetaCumprida(d.meta);
                            if (concluido && ULTIMA_AULA_DO_CURSO && d && d.percentual >= 100) comemorarCurso();
                        })
                        .catch(function () {});
                }
            }

            // Curso concluído na última aula: o momento mais importante passava
            // em branco ("Esta foi a última aula"). Agora dá os parabéns e
            // leva ao certificado (curso.php mostra "Ver certificado").
            function comemorarCurso() {
                var titulo = document.getElementById('playerFimTitulo');
                var botao = document.getElementById('playerFimIr');
                var icone = document.querySelector('.player-fim-icone');
                if (titulo) titulo.textContent = 'Parabéns! Você concluiu o curso 🎉';
                if (icone) icone.className = 'fas fa-trophy player-fim-icone is-concluido';
                if (botao) botao.textContent = 'Ver meu certificado';
                if (window.siteAvisar) siteAvisar('Curso concluído! Seu certificado já está pronto.', 'ok');
            }

            // Meta diária (seção 85): aviso discreto por cima do vídeo quando
            // os minutos de hoje alcançam a meta escolhida em Configurações.
            function avisarMetaCumprida(meta) {
                var aviso = document.createElement('div');
                aviso.className = 'ofensiva-toast meta-toast';
                aviso.setAttribute('role', 'status');
                aviso.innerHTML = '<i class="fas fa-bullseye" aria-hidden="true"></i><div><strong></strong><small></small></div>';
                aviso.querySelector('strong').textContent = 'Meta de hoje cumprida!';
                aviso.querySelector('small').textContent = meta.alvo + ' min de estudo hoje. Até amanhã 🙌';
                document.body.appendChild(aviso);
                setTimeout(function () { aviso.classList.add('is-saindo'); }, 6000);
                setTimeout(function () { aviso.remove(); }, 6600);
            }

            // Fim da aula: mostra "Próxima aula em 5s… Cancelar" (ou o aviso
            // de última aula) por cima do vídeo.
            var fim = document.getElementById('playerFim');
            var fimIr = document.getElementById('playerFimIr');
            var fimSegundos = document.getElementById('playerFimSegundos');
            var fimBarra = document.getElementById('playerFimBarra');
            var contagem = null;
            var SEGUNDOS_PROXIMA = 5;

            // O aviso fica dentro da página: em tela cheia ele não apareceria.
            function sairTelaCheia() {
                try {
                    if (document.fullscreenElement && document.exitFullscreen) {
                        var p = document.exitFullscreen();
                        if (p && p.catch) p.catch(function () {});
                    } else if (document.webkitFullscreenElement && document.webkitExitFullscreen) {
                        document.webkitExitFullscreen();
                    } else if (video.webkitDisplayingFullscreen && video.webkitExitFullscreen) {
                        video.webkitExitFullscreen(); // iPhone
                    }
                } catch (e) {}
            }

            function fecharFim() {
                if (contagem) { clearInterval(contagem); contagem = null; }
                if (fim) fim.hidden = true;
            }

            function mostrarFim() {
                if (!fim || !fim.hidden) return;
                sairTelaCheia();
                fim.hidden = false;
                if (fimIr) fimIr.focus({ preventScroll: true });
                if (!fimSegundos) return; // última aula: sem contagem

                var inicio = Date.now();
                fimSegundos.textContent = SEGUNDOS_PROXIMA;
                fimBarra.style.transition = 'none';
                fimBarra.style.width = '100%';
                void fimBarra.offsetWidth; // aplica os 100% antes de animar
                fimBarra.style.transition = 'width ' + SEGUNDOS_PROXIMA + 's linear';
                fimBarra.style.width = '0%';

                contagem = setInterval(function () {
                    var restante = Math.max(0, SEGUNDOS_PROXIMA - Math.floor((Date.now() - inicio) / 1000));
                    fimSegundos.textContent = restante;
                    if (restante === 0) {
                        clearInterval(contagem);
                        contagem = null;
                        window.location.href = fimIr.href;
                    }
                }, 250);
            }

            if (fim) {
                document.getElementById('playerFimFechar').addEventListener('click', fecharFim);
                // Voltar a tocar (replay ou arrastar a barra) cancela.
                video.addEventListener('play', fecharFim);
                video.addEventListener('seeking', fecharFim);
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && !fim.hidden) fecharFim();
                });
            }

            function aoTerminar() {
                enviarProgresso(true);
                mostrarFim();
            }

            video.addEventListener('ended', aoTerminar);

            // Vídeo com "autoplay" começa a tocar assim que a tag <video> é
            // parseada (linha bem acima nesta página) — antes até do <script>
            // do Bootstrap (sem defer) terminar de carregar, que é o que
            // atrasa a execução deste bloco. Para um vídeo bem curto (poucos
            // segundos, ex.: material de teste), o evento "ended" pode disparar
            // ANTES desse listener existir — e como o evento não fica
            // "guardado" para quem se inscrever depois, o progresso nunca era
            // enviado. `video.ended` é uma propriedade (não um evento), então
            // reflete o estado real do vídeo mesmo que o "ended" já tenha
            // passado — se já tiver terminado, marca como concluído agora.
            if (video.ended) {
                aoTerminar();
            }

            var heartbeat = setInterval(function () {
                if (!video.paused && maxTime > 0) enviarProgresso(false);
            }, 30000);

            // O link do vídeo é assinado e vale de 2 a 3 h
            // (site_resolver_url_video). Quem pausa por mais tempo e volta a
            // dar play recebe erro do R2: recarrega a página (link novo) e
            // continua do mesmo ponto. Só depois de 90 min com a página
            // aberta, e no máximo uma vez a cada 2 min — um erro de outro tipo
            // não vira recarga sem fim.
            var paginaAbertaEm = Date.now();
            video.addEventListener('error', function () {
                if (Date.now() - paginaAbertaEm < 90 * 60000) return;
                try {
                    var anterior = JSON.parse(sessionStorage.getItem(chaveRenovar) || 'null');
                    if (anterior && Date.now() - anterior.em < 120000) return;
                    sessionStorage.setItem(chaveRenovar, JSON.stringify({ em: Date.now(), posicao: Math.floor(video.currentTime || 0) }));
                } catch (e) {
                    return; // sem sessionStorage, não há como evitar o laço
                }
                if (maxTime > 3) enviarProgresso(false);
                window.location.reload();
            });

            window.addEventListener('beforeunload', function () {
                clearInterval(heartbeat);
                if (maxTime > 3) enviarProgresso(false);
            });
        })();

        // Transmitir para a TV (T25). Usa o recurso padrão do navegador
        // (Remote Playback): no Safari abre o AirPlay; no Chrome do Android,
        // o Chromecast. O <video> da página continua "dono" da reprodução —
        // tempo, pausa e fim seguem chegando aqui —, então o progresso
        // (T03/T04) e a próxima aula (T05) funcionam sem mudança. O botão só
        // aparece quando há uma TV disponível. No computador, o Chrome
        // transmite pelo menu ⋮ > Transmitir; botão próprio lá exigiria o
        // Google Cast SDK (docs/para_o_cliente/pontos_para_alinhar.md, seção 5).
        (function () {
            var video = document.getElementById('playerVideo');
            var botao = document.getElementById('transmitirBtn');
            var rotulo = document.getElementById('transmitirRotulo');
            if (!video || !botao) return;

            // OGG não toca no Chromecast nem na Apple TV: nem oferece.
            var caminho = '';
            try { caminho = new URL(video.currentSrc || video.src, location.href).pathname.toLowerCase(); } catch (e) {}
            if (/\.(ogg|ogv)$/.test(caminho)) return;

            function mostrarEstado(conectado, conectando) {
                botao.classList.toggle('is-ativo', conectado || conectando);
                rotulo.textContent = conectado ? 'Transmitindo' : (conectando ? 'Conectando…' : 'Transmitir');
                botao.setAttribute('aria-label', conectado ? 'Transmitindo para a TV — tocar para parar ou trocar' : 'Transmitir para a TV');
                botao.title = conectado ? 'Transmitindo para a TV. Toque para parar ou trocar de TV.' : 'Transmitir para a TV';
            }

            if (video.remote && typeof video.remote.watchAvailability === 'function') {
                video.remote.watchAvailability(function (disponivel) {
                    // Transmitindo, o botão fica (para poder parar).
                    botao.hidden = !disponivel && video.remote.state === 'disconnected';
                }).catch(function () {
                    // Sem como saber se há TV (ex.: computador, economia de
                    // dados): sem botão — o navegador ainda tem o menu dele.
                    botao.hidden = true;
                });
                video.remote.addEventListener('connecting', function () { botao.hidden = false; mostrarEstado(false, true); });
                video.remote.addEventListener('connect', function () { botao.hidden = false; mostrarEstado(true, false); });
                video.remote.addEventListener('disconnect', function () { mostrarEstado(false, false); });
                botao.addEventListener('click', function () {
                    // Conectado, a mesma janela do navegador oferece parar.
                    video.remote.prompt().catch(function () {});
                });
                mostrarEstado(video.remote.state === 'connected', video.remote.state === 'connecting');
                return;
            }

            // Safari mais antigo (sem Remote Playback): API própria do AirPlay.
            if (window.WebKitPlaybackTargetAvailabilityEvent && typeof video.webkitShowPlaybackTargetPicker === 'function') {
                video.addEventListener('webkitplaybacktargetavailabilitychanged', function (e) {
                    botao.hidden = e.availability !== 'available' && !video.webkitCurrentPlaybackTargetIsWireless;
                });
                video.addEventListener('webkitcurrentplaybacktargetiswirelesschanged', function () {
                    mostrarEstado(!!video.webkitCurrentPlaybackTargetIsWireless, false);
                });
                botao.addEventListener('click', function () { video.webkitShowPlaybackTargetPicker(); });
            }
        })();

        // Picture-in-picture (janela flutuante): o vídeo segue tocando num
        // cantinho enquanto a pessoa usa outro app ou aba. Chrome/Edge/Android
        // usam a API padrão; o Safari (iPhone/Mac), a dele. No Chrome, sair da
        // aba com o vídeo tocando já abre a janela sozinho (autopictureinpicture).
        (function () {
            var video = document.getElementById('playerVideo');
            var botao = document.getElementById('pipBtn');
            var rotulo = document.getElementById('pipRotulo');
            if (!video || !botao) return;
            var padrao = document.pictureInPictureEnabled && typeof video.requestPictureInPicture === 'function' && !video.disablePictureInPicture;
            var safari = typeof video.webkitSupportsPresentationMode === 'function' && video.webkitSupportsPresentationMode('picture-in-picture');
            if (!padrao && !safari) return;
            botao.hidden = false;
            video.setAttribute('autopictureinpicture', '');
            function aberto() {
                return padrao ? document.pictureInPictureElement === video : video.webkitPresentationMode === 'picture-in-picture';
            }
            function atualizar() { rotulo.textContent = aberto() ? 'Voltar para a página' : 'Janela flutuante'; }
            botao.addEventListener('click', function () {
                if (padrao) {
                    (aberto() ? document.exitPictureInPicture() : video.requestPictureInPicture()).catch(function () {});
                } else {
                    video.webkitSetPresentationMode(aberto() ? 'inline' : 'picture-in-picture');
                }
            });
            video.addEventListener('enterpictureinpicture', atualizar);
            video.addEventListener('leavepictureinpicture', atualizar);
            video.addEventListener('webkitpresentationmodechanged', atualizar);
        })();

        // Celular: tela de bloqueio e fone de ouvido. MediaSession mostra o
        // título, o curso e o logo na notificação do sistema, com voltar e
        // avançar 10 s (pregação vira "podcast" com a tela apagada). E toque
        // duplo nas laterais do vídeo volta/avança 10 s, como no YouTube.
        (function () {
            var video = document.getElementById('playerVideo');
            if (!video) return;
            function pular(segundos) {
                var alvo = Math.max(0, Math.min((video.duration || Infinity) - 0.5, video.currentTime + segundos));
                video.currentTime = alvo;
            }
            if ('mediaSession' in navigator) {
                try {
                    navigator.mediaSession.metadata = new MediaMetadata({
                        title: <?php echo json_encode((string) $titulo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                        artist: <?php echo json_encode($curso ? (string) $curso['IDC_TITULO'] : 'Missão Manancial', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                        artwork: [{ src: <?php echo json_encode(site_url('assets/img/icon-512.png')); ?>, sizes: '512x512', type: 'image/png' }]
                    });
                    navigator.mediaSession.setActionHandler('seekbackward', function () { pular(-10); });
                    navigator.mediaSession.setActionHandler('seekforward', function () { pular(10); });
                    navigator.mediaSession.setActionHandler('play', function () { video.play(); });
                    navigator.mediaSession.setActionHandler('pause', function () { video.pause(); });
                } catch (e) {}
            }
            // Toque duplo: só em tela de toque, para não brigar com o duplo
            // clique de tela cheia do computador.
            if (!window.matchMedia || !window.matchMedia('(pointer: coarse)').matches) return;
            var ultimoToque = 0;
            video.addEventListener('touchend', function (e) {
                var agora = Date.now();
                if (agora - ultimoToque < 300 && e.changedTouches && e.changedTouches[0]) {
                    var caixa = video.getBoundingClientRect();
                    var x = e.changedTouches[0].clientX - caixa.left;
                    if (x < caixa.width * 0.35) {
                        e.preventDefault();
                        pular(-10);
                    } else if (x > caixa.width * 0.65) {
                        e.preventDefault();
                        pular(10);
                    }
                    ultimoToque = 0;
                    return;
                }
                ultimoToque = agora;
            });
        })();

        // Seletor de velocidade próprio: o controle nativo do navegador não
        // aparece em todos os celulares (ex.: Safari no iPhone). A escolha
        // fica salva em ID_PREFERENCIA e vale para todas as aulas.
        (function () {
            var video = document.getElementById('playerVideo');
            var menu = document.getElementById('velocidadeMenu');
            var rotulo = document.getElementById('velocidadeAtual');
            if (!video || !menu || !rotulo) return;

            var itens = menu.querySelectorAll('[data-velocidade]');
            var preferida = <?php echo json_encode($velocidade_preferida); ?>;
            var podeSalvar = <?php echo $fiel_id_velocidade > 0 ? 'true' : 'false'; ?>;
            var csrfToken = <?php echo json_encode(csrf_token()); ?>;

            function aplicar(velocidade) {
                // defaultPlaybackRate junto: alguns navegadores voltam
                // para ele quando o vídeo recarrega (ex.: troca de rede).
                video.defaultPlaybackRate = velocidade;
                video.playbackRate = velocidade;
            }

            function salvar(velocidade) {
                if (!podeSalvar || velocidade === preferida) return;
                preferida = velocidade;
                fetch('ajax_preferencia_velocidade.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ velocidade: velocidade, csrf_token: csrfToken }),
                    keepalive: true
                }).catch(function () {});
            }

            // Atualiza o botão e o item marcado. Também é chamado no
            // "ratechange", então fica certo mesmo se a velocidade for trocada
            // pelo menu nativo do navegador.
            function marcar(velocidade) {
                rotulo.textContent = String(velocidade).replace('.', ',') + 'x';
                itens.forEach(function (item) {
                    var ativo = parseFloat(item.dataset.velocidade) === velocidade;
                    item.classList.toggle('active', ativo);
                    item.setAttribute('aria-current', ativo ? 'true' : 'false');
                });
            }

            itens.forEach(function (item) {
                item.addEventListener('click', function () {
                    var velocidade = parseFloat(item.dataset.velocidade);
                    aplicar(velocidade);
                    // Só salva o que veio deste menu: o "ratechange" também
                    // dispara quando o navegador reseta a velocidade sozinho.
                    salvar(velocidade);
                });
            });

            video.addEventListener('ratechange', function () { marcar(video.playbackRate); });

            // O vídeo começa a carregar antes deste script; aplica agora e de
            // novo quando os dados chegarem, porque alguns navegadores voltam
            // a velocidade para o padrão ao carregar o vídeo.
            aplicar(preferida);
            video.addEventListener('loadedmetadata', function () {
                if (video.playbackRate !== video.defaultPlaybackRate) aplicar(video.defaultPlaybackRate);
            });
            marcar(video.playbackRate);
        })();
    </script>
    <?php } ?>
    <?php if ($conteudo_encontrado) { ?>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        (function () {
            var conteudoId = <?php echo (int) $id; ?>;
            var csrfToken = <?php echo json_encode(csrf_token()); ?>;
            var comentarios = <?php echo json_encode($comentarios, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            // Mais votado da aba atual: vai no topo com o selo "Destaque".
            var destaque = <?php echo json_encode($comentario_destaque, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            // Nota do pastor fixada (T19): acima de tudo, em todas as abas.
            var nota = <?php echo json_encode($nota_fixada, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            // Aba atual: relevantes, recentes, lideres ou versiculo.
            var ordem = <?php echo json_encode($comentarios_ordem); ?>;

            // Total real de comentários (título), total da aba atual e se
            // há mais para buscar nela.
            var totalComentarios = <?php echo (int) $comentarios_total; ?>;
            var totalFiltro = totalComentarios - (nota ? 1 : 0);
            var temMais = <?php echo $comentarios_tem_mais ? 'true' : 'false'; ?>;
            // Próxima página: nas abas por data, "antes" do menor id já
            // carregado (cursor); em "relevantes", pulando os já recebidos.
            var cursor = 0;
            var pulados = 0;
            var carregando = false;
            // Descarta respostas de uma aba que já foi trocada.
            var pedido = 0;
            registrarPagina(comentarios);

            var tituloAula = <?php echo json_encode($titulo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            // Link compartilhado (T20): o comentário principal da conversa e
            // o id a destacar (ele mesmo ou a resposta).
            var alvoId = <?php echo $comentario_link ? (int) $comentario_link_id : 0; ?>;
            (function (doLink) {
                if (!doLink) return;
                var principal = (nota && nota.id === doLink.id) ? nota
                    : (destaque && destaque.id === doLink.id) ? destaque
                    : comentarios.filter(function (c) { return c.id === doLink.id; })[0];
                if (!principal) {
                    // Fora da primeira página: entra no topo da lista
                    // (o "Ver mais" não repete quando a página dele chegar).
                    principal = doLink;
                    comentarios.unshift(principal);
                }
                if (alvoId !== principal.id) principal.aberta = true;
            })(<?php echo json_encode($comentario_link, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);

            var lista = document.getElementById('comentariosLista');
            var ordens = document.getElementById('comentariosOrdens');
            var total = document.getElementById('comentariosTotal');
            var maisBox = document.getElementById('comentariosMaisBox');
            var maisBotao = document.getElementById('comentariosMais');
            var maisErro = document.getElementById('comentariosMaisErro');
            var form = document.getElementById('comentarioForm');
            var texto = document.getElementById('comentarioTexto');
            var acoes = document.getElementById('comentarioAcoes');
            var erro = document.getElementById('comentarioErro');
            var botao = document.getElementById('comentarioEnviar');

            function tempoRelativo(ts) {
                var s = Math.max(0, Math.floor(Date.now() / 1000) - ts);
                if (s < 60) return 'agora';
                if (s < 3600) return 'há ' + Math.floor(s / 60) + ' min';
                if (s < 86400) return 'há ' + Math.floor(s / 3600) + ' h';
                if (s < 86400 * 30) { var d = Math.floor(s / 86400); return 'há ' + d + (d === 1 ? ' dia' : ' dias'); }
                return new Date(ts * 1000).toLocaleDateString('pt-BR');
            }

            // pai: o comentário principal quando c é uma resposta (T18).
            function montar(c, pai) {
                var li = document.createElement('li');
                li.className = 'video-comentario' + (pai ? ' is-resposta' : '');
                li.dataset.id = c.id;
                li.innerHTML =
                    '<span class="presenca-avatar"></span>' +
                    '<div class="video-comentario-corpo">' +
                        '<div class="video-comentario-topo"><strong class="video-comentario-nome"></strong><small class="text-muted"></small></div>' +
                        '<p class="video-comentario-texto"></p>' +
                    '</div>';
                li.querySelector('.presenca-avatar').textContent = (c.nome || '?').charAt(0).toUpperCase();
                var nome = li.querySelector('.video-comentario-nome');
                nome.textContent = c.nome + (c.meu ? ' (você)' : '');
                // Selos (T24): a função na igreja ("Pastor(a)", "Diácono(isa)"…);
                // sem função, "Equipe" para admin e "Líder" para liderança.
                var rotuloTag = c.funcao || (c.equipe ? 'Equipe' : (c.lider ? 'Líder' : ''));
                if (rotuloTag) {
                    var tag = document.createElement('span');
                    tag.className = 'presenca-tag' + (c.lider || c.equipe ? '' : ' is-neutra');
                    tag.textContent = rotuloTag;
                    nome.appendChild(tag);
                }
                if (c.medalha) {
                    var medalha = document.createElement('i');
                    medalha.className = 'fas ' + c.medalha.icone + ' comentario-selo-medalha nivel-' + c.medalha.nivel;
                    medalha.title = 'Medalha: ' + c.medalha.nome + ' (' + c.medalha.nivel_nome + ')';
                    medalha.setAttribute('aria-label', medalha.title);
                    medalha.setAttribute('role', 'img');
                    nome.appendChild(medalha);
                }
                if (!pai && c.fixado) {
                    li.classList.add('is-fixado');
                    var seloNota = document.createElement('span');
                    seloNota.className = 'video-comentario-nota';
                    seloNota.innerHTML = '<i class="fas fa-thumbtack" aria-hidden="true"></i>Nota do pastor · Fixado';
                    li.querySelector('.video-comentario-corpo').insertBefore(seloNota, li.querySelector('.video-comentario-topo'));
                } else if (!pai && destaque && c.id === destaque.id) {
                    li.classList.add('is-destaque');
                    var selo = document.createElement('span');
                    selo.className = 'video-comentario-destaque';
                    selo.innerHTML = '<i class="fas fa-star" aria-hidden="true"></i>Destaque';
                    li.querySelector('.video-comentario-corpo').insertBefore(selo, li.querySelector('.video-comentario-topo'));
                }
                li.querySelector('small').textContent = tempoRelativo(c.criado_ts);
                li.querySelector('.video-comentario-texto').textContent = c.mensagem;
                var citacoes = montarCitacoes(c);
                if (citacoes) li.querySelector('.video-comentario-texto').after(citacoes);

                if (!pai && c.pode_fixar) {
                    var fixar = document.createElement('button');
                    fixar.type = 'button';
                    fixar.className = 'video-comentario-fixar' + (c.fixado ? ' is-ativo' : '');
                    fixar.title = c.fixado ? 'Desafixar nota' : 'Fixar como nota do pastor no topo';
                    fixar.setAttribute('aria-label', fixar.title);
                    fixar.innerHTML = '<i class="fas fa-thumbtack" aria-hidden="true"></i>';
                    fixar.addEventListener('click', function () { fixarComentario(c); });
                    li.querySelector('.video-comentario-topo').appendChild(fixar);
                }

                if (c.pode_excluir) {
                    var excluir = document.createElement('button');
                    excluir.type = 'button';
                    excluir.className = 'video-comentario-excluir';
                    excluir.title = c.meu ? 'Excluir meu comentário' : 'Excluir comentário (moderação)';
                    excluir.innerHTML = '<i class="fas fa-trash-alt"></i>';
                    excluir.addEventListener('click', function () {
                        if (pai) excluirResposta(c, pai); else excluirComentario(c.id, li);
                    });
                    li.querySelector('.video-comentario-topo').appendChild(excluir);
                }

                // Sem a coluna de respostas no banco, c.respostas não vem.
                var podeResponder = c.respostas !== undefined;
                var rodape = c.votos ? montarVotos(c) : document.createElement('div');
                rodape.classList.add('video-comentario-votos');
                if (podeResponder) rodape.appendChild(botaoResponder(c, pai));
                rodape.appendChild(botaoCompartilhar(c));
                li.querySelector('.video-comentario-corpo').appendChild(rodape);
                if (podeResponder && !pai) {
                    var conversa = document.createElement('div');
                    conversa.className = 'video-comentario-conversa';
                    li.querySelector('.video-comentario-corpo').appendChild(conversa);
                    c.conversaEl = conversa;
                    if (c.aberta) renderizarConversa(c);
                }
                return li;
            }

            // ---------------------------------------------------------------
            // Compartilhar (T20): link direto para o comentário na aula. No
            // celular abre o compartilhar do aparelho (WhatsApp etc.); sem
            // ele, copia o texto com o link.
            // ---------------------------------------------------------------
            function linkDoComentario(c) {
                return location.origin + location.pathname + '?id=' + conteudoId + '&comentario=' + c.id;
            }

            function botaoCompartilhar(c) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'video-comentario-responder video-comentario-compartilhar';
                b.setAttribute('aria-label', 'Compartilhar comentário');
                b.innerHTML = '<i class="fas fa-share-nodes" aria-hidden="true"></i><span>Compartilhar</span>';
                b.addEventListener('click', function () { compartilhar(c, b); });
                return b;
            }

            function compartilhar(c, b) {
                var url = linkDoComentario(c);
                var trecho = c.mensagem.length > 160 ? c.mensagem.slice(0, 157).trim() + '…' : c.mensagem;
                var texto = c.nome + ' comentou na aula "' + tituloAula + '":\n"' + trecho + '"';

                if (navigator.share) {
                    navigator.share({ title: tituloAula, text: texto, url: url }).catch(function (e) {
                        // Fechou a janela de compartilhar: nada a fazer.
                        if (e && e.name === 'AbortError') return;
                        copiarLink(texto + '\n' + url, url, b);
                    });
                    return;
                }
                copiarLink(texto + '\n' + url, url, b);
            }

            function copiarLink(conteudo, url, b) {
                var rotulo = b.querySelector('span');
                function avisar(msg) {
                    rotulo.textContent = msg;
                    b.classList.add('is-ativo');
                    setTimeout(function () {
                        rotulo.textContent = 'Compartilhar';
                        b.classList.remove('is-ativo');
                    }, 2500);
                }
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(conteudo)
                        .then(function () { avisar('Link copiado!'); })
                        .catch(function () { window.prompt('Copie o link do comentário:', url); });
                } else {
                    window.prompt('Copie o link do comentário:', url);
                }
            }

            // Abriu por um link compartilhado: rola até o comentário (ou a
            // resposta, quando a conversa dela terminar de carregar) e
            // destaca por alguns segundos. Uma vez só.
            function irParaAlvo() {
                if (!alvoId) return;
                var li = lista.querySelector('li.video-comentario[data-id="' + alvoId + '"]');
                if (!li) return;
                alvoId = 0;
                li.classList.add('is-alvo');
                setTimeout(function () { li.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 150);
                setTimeout(function () { li.classList.remove('is-alvo'); }, 4500);
            }

            // ---------------------------------------------------------------
            // Respostas (T18). No comentário principal, "Responder (X)" abre
            // e fecha a conversa (respostas + campo para responder). Numa
            // resposta, "Responder" abre o campo do principal já com
            // "@Nome". Estado guardado no próprio objeto do comentário:
            // aberta, lista (respostas carregadas), rascunho.
            // ---------------------------------------------------------------
            function botaoResponder(c, pai) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'video-comentario-responder';
                if (pai) {
                    b.innerHTML = '<i class="fas fa-reply" aria-hidden="true"></i>Responder';
                    b.addEventListener('click', function () {
                        pai.rascunho = '@' + c.nome + ' ';
                        renderizarConversa(pai, true);
                    });
                    return b;
                }
                c.botaoResponderEl = b;
                atualizarBotaoResponder(c);
                b.addEventListener('click', function () {
                    c.aberta = !c.aberta;
                    atualizarBotaoResponder(c);
                    if (c.aberta) renderizarConversa(c, !c.respostas);
                    else c.conversaEl.innerHTML = '';
                });
                return b;
            }

            function atualizarBotaoResponder(c) {
                var b = c.botaoResponderEl;
                if (!b) return;
                b.innerHTML = '<i class="fas fa-reply" aria-hidden="true"></i>';
                b.appendChild(document.createTextNode('Responder (' + c.respostas + ')'));
                b.classList.toggle('is-ativo', !!c.aberta);
                b.setAttribute('aria-expanded', c.aberta ? 'true' : 'false');
            }

            function renderizarConversa(c, focar) {
                if (!c.conversaEl) return;
                if (!c.aberta) {
                    c.aberta = true;
                    atualizarBotaoResponder(c);
                }
                var box = c.conversaEl;
                box.innerHTML =
                    '<ul class="video-comentarios-lista video-respostas-lista"></ul>' +
                    '<form class="video-resposta-form">' +
                        '<textarea class="form-control" rows="2" maxlength="1000" placeholder="Escreva sua resposta…" aria-label="Sua resposta" required></textarea>' +
                        '<div class="video-comentario-acoes is-aberto">' +
                            '<small class="text-danger me-auto"></small>' +
                            '<button type="button" class="btn btn-sm btn-outline-light">Cancelar</button>' +
                            '<button type="submit" class="btn btn-sm btn-play">Responder</button>' +
                        '</div>' +
                    '</form>';
                var listaRespostas = box.querySelector('.video-respostas-lista');
                var formResposta = box.querySelector('form');
                var campo = formResposta.querySelector('textarea');
                var erroResposta = formResposta.querySelector('small');
                var enviarResposta = formResposta.querySelector('button[type="submit"]');

                if (c.lista) {
                    c.lista.forEach(function (r) { listaRespostas.appendChild(montar(r, c)); });
                } else if (c.respostas > 0) {
                    listaRespostas.innerHTML = '<li class="video-comentarios-vazio"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Carregando respostas…</li>';
                    carregarRespostas(c);
                }

                campo.value = c.rascunho || '';
                campo.addEventListener('input', function () { c.rascunho = campo.value; });
                formResposta.querySelector('.btn-outline-light').addEventListener('click', function () {
                    c.rascunho = '';
                    c.aberta = false;
                    atualizarBotaoResponder(c);
                    box.innerHTML = '';
                });
                formResposta.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var mensagem = campo.value.trim();
                    if (!mensagem) return;
                    enviarResposta.disabled = true;
                    erroResposta.textContent = '';
                    enviar({ acao: 'comentar', idct_id: conteudoId, idcc_pai_id: c.id, mensagem: mensagem })
                        .then(function (resp) {
                            if (!resp.ok) { erroResposta.textContent = resp.erro || 'Não foi possível responder.'; return; }
                            if (resp.comentario) {
                                c.lista = (c.lista || []).concat([resp.comentario]);
                                c.respostas++;
                            }
                            c.rascunho = '';
                            atualizarBotaoResponder(c);
                            renderizarConversa(c);
                        })
                        .catch(function () { erroResposta.textContent = 'Falha de conexão. Tente de novo.'; })
                        .then(function () { enviarResposta.disabled = false; });
                });

                irParaAlvo();
                if (focar) {
                    campo.focus();
                    campo.setSelectionRange(campo.value.length, campo.value.length);
                }
            }

            function carregarRespostas(c) {
                if (c.carregandoRespostas) return;
                c.carregandoRespostas = true;
                fetch('ajax_video_comentarios.php?respostas_de=' + c.id)
                    .then(function (r) { return r.json(); })
                    .then(function (resp) {
                        if (!resp.ok) throw new Error(resp.erro || '');
                        c.lista = resp.respostas;
                        c.respostas = resp.respostas.length;
                        atualizarBotaoResponder(c);
                        if (c.aberta) renderizarConversa(c);
                    })
                    .catch(function () {
                        var lista = c.conversaEl && c.conversaEl.querySelector('.video-respostas-lista');
                        if (lista) lista.innerHTML = '<li class="video-comentarios-vazio">Não foi possível carregar as respostas. Feche e abra de novo.</li>';
                    })
                    .then(function () { c.carregandoRespostas = false; });
            }

            function excluirResposta(r, pai) {
                siteConfirmar('Excluir essa resposta?', { botao: 'Excluir', perigo: true }).then(function (ok) {
                    if (!ok) return;
                    enviar({ acao: 'excluir', idcc_id: r.id }).then(function (resp) {
                        if (!resp.ok) { siteAvisar(resp.erro || 'Não foi possível excluir.', 'erro'); return; }
                        pai.lista = (pai.lista || []).filter(function (x) { return x.id !== r.id; });
                        pai.respostas = Math.max(0, pai.respostas - 1);
                        atualizarBotaoResponder(pai);
                        renderizarConversa(pai);
                    }).catch(function () { siteAvisar('Falha de conexão. Tente de novo.', 'erro'); });
                });
            }

            var tiposVoto = [
                { tipo: 'edificante', icone: 'fa-arrow-up', rotulo: 'Edificante' },
                { tipo: 'fora', icone: 'fa-arrow-down', rotulo: 'Fora de contexto' }
            ];

            function montarVotos(c) {
                var box = document.createElement('div');
                box.className = 'video-comentario-votos';
                tiposVoto.forEach(function (t) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'video-comentario-voto is-' + t.tipo;
                    b.innerHTML = '<i class="fas ' + t.icone + '" aria-hidden="true"></i><span class="video-comentario-voto-rotulo"></span><span class="video-comentario-voto-num"></span>';
                    b.querySelector('.video-comentario-voto-rotulo').textContent = t.rotulo;
                    if (c.meu) {
                        b.disabled = true;
                        b.title = 'Você não pode votar no próprio comentário';
                    } else {
                        b.addEventListener('click', function () { votar(c, box, t.tipo); });
                    }
                    box.appendChild(b);
                });
                atualizarVotos(c, box);
                return box;
            }

            function atualizarVotos(c, box) {
                box.querySelectorAll('.video-comentario-voto').forEach(function (b, i) {
                    var tipo = tiposVoto[i].tipo;
                    var ativo = c.votos.meu === tipo;
                    b.classList.toggle('is-ativo', ativo);
                    b.setAttribute('aria-pressed', ativo ? 'true' : 'false');
                    b.querySelector('.video-comentario-voto-num').textContent = c.votos[tipo];
                    if (!c.meu) b.title = ativo ? 'Desfazer voto' : tiposVoto[i].rotulo;
                });
            }

            // Clicar no voto que já é seu desfaz; no outro, troca.
            function votar(c, box, tipo) {
                if (box.dataset.enviando) return;
                box.dataset.enviando = '1';
                box.classList.add('is-enviando');
                enviar({ acao: 'votar', idcc_id: c.id, tipo: c.votos.meu === tipo ? '' : tipo })
                    .then(function (resp) {
                        if (!resp.ok) { siteAvisar(resp.erro || 'Não foi possível votar.', 'erro'); return; }
                        c.votos = resp.votos;
                        atualizarVotos(c, box);
                    })
                    .catch(function () { siteAvisar('Falha de conexão. Tente de novo.', 'erro'); })
                    .then(function () {
                        delete box.dataset.enviando;
                        box.classList.remove('is-enviando');
                    });
            }

            function atualizarBotaoMais() {
                maisBox.hidden = !temMais;
                if (!temMais || carregando) return;
                var restante = totalFiltro - carregados();
                maisBotao.textContent = restante > 1 ? 'Ver mais ' + restante + ' comentários'
                    : (restante === 1 ? 'Ver mais 1 comentário' : 'Ver mais comentários');
            }

            function temNaLista(id) {
                return comentarios.some(function (c) { return c.id === id; });
            }

            // Quantos comentários da aba já estão na tela (o destaque pode
            // ter vindo antes de chegar a página dele).
            function carregados() {
                return comentarios.length + (destaque && !temNaLista(destaque.id) ? 1 : 0);
            }

            // Guarda o ponto de onde a próxima página continua.
            function registrarPagina(pagina) {
                pagina.forEach(function (c) {
                    if (c.id < cursor || !cursor) cursor = c.id;
                });
                pulados += pagina.length;
            }

            var vazios = {
                relevantes: 'Seja o primeiro a comentar sobre esse vídeo.',
                recentes: 'Seja o primeiro a comentar sobre esse vídeo.',
                lideres: 'Nenhum comentário de líderes neste vídeo ainda.',
                versiculo: 'Nenhum comentário com versículo (ex.: Jo 3:16) neste vídeo ainda.'
            };

            function renderizar() {
                lista.innerHTML = '';
                if (nota) lista.appendChild(montar(nota));
                if (!carregados() && !temMais && !nota) {
                    var vazio = document.createElement('li');
                    vazio.className = 'video-comentarios-vazio';
                    vazio.textContent = vazios[ordem];
                    lista.appendChild(vazio);
                }
                if (destaque) lista.appendChild(montar(destaque));
                comentarios.forEach(function (c) {
                    if (!destaque || c.id !== destaque.id) lista.appendChild(montar(c));
                });
                total.textContent = '(' + totalComentarios + ')';
                atualizarBotaoMais();
                irParaAlvo();
            }

            // Busca a próxima página da aba atual; com recomecar, a primeira
            // (troca de aba), que traz também o destaque.
            function carregar(recomecar) {
                if (carregando && !recomecar) return;
                var meuPedido = ++pedido;
                carregando = true;
                maisBotao.disabled = true;
                maisBotao.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Carregando…';
                maisErro.textContent = '';

                var url = 'ajax_video_comentarios.php?idct_id=' + conteudoId + '&ordem=' + ordem;
                if (!recomecar) url += ordem === 'relevantes' ? '&pular=' + pulados : '&antes_de=' + cursor;

                fetch(url)
                    .then(function (r) { return r.json(); })
                    .then(function (resp) {
                        if (meuPedido !== pedido) return;
                        if (!resp.ok) throw new Error(resp.erro || '');
                        if (recomecar) {
                            comentarios = [];
                            destaque = resp.destaque || null;
                            nota = resp.nota || null;
                        }
                        resp.comentarios.forEach(function (c) {
                            if (!temNaLista(c.id)) comentarios.push(c);
                        });
                        registrarPagina(resp.comentarios);
                        totalComentarios = resp.total;
                        totalFiltro = resp.total_filtro;
                        temMais = resp.tem_mais;
                    })
                    .catch(function () {
                        if (meuPedido !== pedido) return;
                        maisErro.textContent = 'Não foi possível carregar. Tente de novo.';
                        // Falhou ao trocar de aba: deixa o "Ver mais" tentar a primeira página de novo.
                        if (recomecar) temMais = true;
                    })
                    .then(function () {
                        if (meuPedido !== pedido) return;
                        carregando = false;
                        maisBotao.disabled = false;
                        renderizar();
                    });
            }

            maisBotao.addEventListener('click', function () {
                if (!temMais) return;
                carregar(!cursor && !pulados);
            });

            ordens.addEventListener('click', function (e) {
                var aba = e.target.closest('.video-comentarios-ordem');
                if (!aba || aba.dataset.ordem === ordem) return;
                ordem = aba.dataset.ordem;
                ordens.querySelectorAll('.video-comentarios-ordem').forEach(function (b) {
                    var ativa = b === aba;
                    b.classList.toggle('is-ativa', ativa);
                    b.setAttribute('aria-selected', ativa ? 'true' : 'false');
                });
                recarregarAba();
            });

            // Busca de novo a primeira página da aba atual (troca de aba ou
            // depois de fixar/desfixar a nota).
            function recarregarAba() {
                comentarios = [];
                destaque = null;
                nota = null;
                totalFiltro = 0;
                cursor = 0;
                pulados = 0;
                maisBox.hidden = true;
                lista.innerHTML = ESQUELETO_LISTA;
                carregar(true);
            }

            function fixarComentario(c) {
                var fixar = !c.fixado;
                var pergunta = fixar && nota
                    ? siteConfirmar('Já existe uma nota fixada nesta aula. Fixar esta no lugar dela?', { botao: 'Fixar esta' })
                    : Promise.resolve(true);
                pergunta.then(function (ok) {
                    if (!ok) return;
                    enviar({ acao: 'fixar', idcc_id: c.id, fixar: fixar }).then(function (resp) {
                        if (!resp.ok) { siteAvisar(resp.erro || 'Não foi possível salvar.', 'erro'); return; }
                        recarregarAba();
                    }).catch(function () { siteAvisar('Falha de conexão. Tente de novo.', 'erro'); });
                });
            }

            // Comentário novo entra na aba atual se passar no filtro dela.
            function entraNaAba(c) {
                if (ordem === 'lideres') return c.lider || c.equipe;
                if (ordem === 'versiculo') return c.versiculo;
                return true;
            }

            function enviar(payload) {
                payload.csrf_token = csrfToken;
                return fetch('ajax_video_comentarios.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(function (r) { return r.json(); });
            }

            function excluirComentario(id, li) {
                siteConfirmar('Excluir esse comentário?', { botao: 'Excluir', perigo: true }).then(function (ok) {
                    if (!ok) return;
                    enviar({ acao: 'excluir', idcc_id: id }).then(function (resp) {
                        if (!resp.ok) { siteAvisar(resp.erro || 'Não foi possível excluir.', 'erro'); return; }
                        totalComentarios = Math.max(0, totalComentarios - 1);
                        if (nota && nota.id === id) {
                            // A nota não conta no total da aba.
                            nota = null;
                        } else {
                            comentarios = comentarios.filter(function (c) { return c.id !== id; });
                            if (destaque && destaque.id === id) destaque = null;
                            totalFiltro = Math.max(0, totalFiltro - 1);
                        }
                        renderizar();
                    }).catch(function () { siteAvisar('Falha de conexão. Tente de novo.', 'erro'); });
                });
            }

            function fecharForm() {
                texto.value = '';
                texto.rows = 1;
                erro.textContent = '';
                acoes.classList.remove('is-aberto');
                limparAnexos();
            }

            // ---------------------------------------------------------------
            // Momento da aula e versículo (T21)
            // ---------------------------------------------------------------
            var player = document.getElementById('playerVideo');
            var momentoMarcado = null;
            var anexoMomento = document.getElementById('anexoMomento');
            var anexoVersiculo = document.getElementById('anexoVersiculo');
            var campoVersiculo = document.getElementById('anexoVersiculoTexto');

            function formatarMomento(segundos) {
                var h = Math.floor(segundos / 3600);
                var m = Math.floor((segundos % 3600) / 60);
                var s = segundos % 60;
                var mmss = (h > 0 && m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                return h > 0 ? h + ':' + mmss : mmss;
            }

            // Clicar no minuto citado leva o vídeo até ele (e rola até o player).
            function irParaMomento(segundos) {
                if (!player) return;
                player.currentTime = segundos;
                var tocando = player.play();
                if (tocando && tocando.catch) tocando.catch(function () {});
                player.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            function montarCitacoes(c) {
                if (c.momento === null && !c.versiculo_ref) return null;
                var box = document.createElement('div');
                box.className = 'video-comentario-citacoes';
                if (c.momento !== null && c.momento !== undefined) {
                    var m = document.createElement(player ? 'button' : 'span');
                    m.className = 'video-comentario-citacao is-momento';
                    m.innerHTML = '<i class="far fa-clock" aria-hidden="true"></i>';
                    m.appendChild(document.createTextNode('no minuto ' + formatarMomento(c.momento)));
                    if (player) {
                        m.type = 'button';
                        m.title = 'Ir para esse momento do vídeo';
                        m.addEventListener('click', function () { irParaMomento(c.momento); });
                    }
                    box.appendChild(m);
                }
                if (c.versiculo_ref) {
                    var v = document.createElement('span');
                    v.className = 'video-comentario-citacao is-versiculo';
                    v.innerHTML = '<i class="fas fa-book-bible" aria-hidden="true"></i>';
                    v.appendChild(document.createTextNode(c.versiculo_ref));
                    box.appendChild(v);
                }
                return box;
            }

            function limparAnexos() {
                momentoMarcado = null;
                if (anexoMomento) anexoMomento.hidden = true;
                if (anexoVersiculo) {
                    anexoVersiculo.hidden = true;
                    campoVersiculo.value = '';
                }
            }

            var botaoMomento = document.getElementById('citarMomento');
            if (botaoMomento && player) {
                // Clicar de novo atualiza para o minuto atual.
                botaoMomento.addEventListener('click', function () {
                    momentoMarcado = Math.floor(player.currentTime || 0);
                    document.getElementById('anexoMomentoTexto').textContent = 'no minuto ' + formatarMomento(momentoMarcado);
                    anexoMomento.hidden = false;
                });
                document.getElementById('anexoMomentoRemover').addEventListener('click', function () {
                    momentoMarcado = null;
                    anexoMomento.hidden = true;
                });
            }

            var botaoVersiculo = document.getElementById('citarVersiculo');
            if (botaoVersiculo) {
                botaoVersiculo.addEventListener('click', function () {
                    anexoVersiculo.hidden = false;
                    campoVersiculo.focus();
                });
                document.getElementById('anexoVersiculoRemover').addEventListener('click', function () {
                    anexoVersiculo.hidden = true;
                    campoVersiculo.value = '';
                });
                // Enter no campo do versículo não envia o comentário sem querer.
                campoVersiculo.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); texto.focus(); }
                });
            }

            texto.addEventListener('focus', function () { acoes.classList.add('is-aberto'); texto.rows = 3; });
            document.getElementById('comentarioCancelar').addEventListener('click', fecharForm);

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var mensagem = texto.value.trim();
                if (!mensagem) return;

                botao.disabled = true;
                erro.textContent = '';
                var payload = { acao: 'comentar', idct_id: conteudoId, mensagem: mensagem };
                if (momentoMarcado !== null) payload.momento = momentoMarcado;
                if (anexoVersiculo && !anexoVersiculo.hidden && campoVersiculo.value.trim()) {
                    payload.versiculo = campoVersiculo.value.trim();
                }
                enviar(payload)
                    .then(function (resp) {
                        if (!resp.ok) { erro.textContent = resp.erro || 'Não foi possível comentar.'; return; }
                        if (resp.comentario) {
                            totalComentarios++;
                            if (entraNaAba(resp.comentario)) {
                                comentarios.unshift(resp.comentario);
                                totalFiltro++;
                            }
                        }
                        renderizar();
                        fecharForm();
                    })
                    .catch(function () { erro.textContent = 'Falha de conexão. Tente de novo.'; })
                    .then(function () { botao.disabled = false; });
            });

            renderizar();

            // ---------------------------------------------------------------
            // Abas Comunidade / Orações (T28) / Minhas notas (T27). A última
            // aba escolhida fica lembrada neste aparelho (quem anota segue
            // anotando nas próximas aulas). Link de comentário (T20) abre
            // sempre a Comunidade e o de pedido de oração (notificação), as
            // Orações; #notas ou #oracoes na URL abrem essas abas.
            // ---------------------------------------------------------------
            var abas = document.getElementById('aulaAbas');
            var CHAVE_ABA = 'aula_aba';

            function mostrarAba(nome, focar) {
                abas.querySelectorAll('.aula-aba').forEach(function (b) {
                    var ativa = b.dataset.aba === nome;
                    b.classList.toggle('is-ativa', ativa);
                    b.setAttribute('aria-selected', ativa ? 'true' : 'false');
                    b.tabIndex = ativa ? 0 : -1;
                    document.getElementById(b.getAttribute('aria-controls')).hidden = !ativa;
                    if (ativa && focar) b.focus();
                });
                try { localStorage.setItem(CHAVE_ABA, nome); } catch (e) {}
            }

            abas.addEventListener('click', function (e) {
                var aba = e.target.closest('.aula-aba');
                if (aba) mostrarAba(aba.dataset.aba);
            });
            // Setas esquerda/direita trocam de aba (padrão de abas acessíveis).
            abas.addEventListener('keydown', function (e) {
                if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
                var lista = Array.prototype.slice.call(abas.querySelectorAll('.aula-aba'));
                var atual = lista.indexOf(document.activeElement);
                if (atual < 0) return;
                e.preventDefault();
                var proxima = lista[(atual + (e.key === 'ArrowRight' ? 1 : lista.length - 1)) % lista.length];
                mostrarAba(proxima.dataset.aba, true);
            });

            (function () {
                if (<?php echo $comentario_link ? 'true' : 'false'; ?>) return;
                if (<?php echo $oracao_link ? 'true' : 'false'; ?>) { mostrarAba('oracoes'); return; }
                if (<?php echo $resumo_link ? 'true' : 'false'; ?>) { mostrarAba('resumos'); return; }
                var inicial = { '#notas': 'notas', '#oracoes': 'oracoes', '#resumos': 'resumos' }[location.hash] || '';
                if (!inicial) {
                    try { inicial = localStorage.getItem(CHAVE_ABA) || ''; } catch (e) {}
                }
                if (inicial === 'notas' || inicial === 'oracoes' || inicial === 'resumos') mostrarAba(inicial);
            })();

            // ---------------------------------------------------------------
            // Orações (T28): mural único de pedidos da igreja. "Amém" = orei
            // por você (um por pessoa, liga/desliga, não no próprio). O
            // autor marca como atendida (com o relato opcional) ou remove;
            // o admin também remove. Filtros: Pedidos (abertos), Atendidas e
            // Meus pedidos, 10 por vez.
            // ---------------------------------------------------------------
            (function () {
                var oracaoForm = document.getElementById('oracaoForm');
                if (!oracaoForm) return;

                var oracoes = <?php echo json_encode($oracoes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                var oracoesTemMais = <?php echo $oracoes_tem_mais ? 'true' : 'false'; ?>;
                var totalAbertas = <?php echo (int) $oracoes_abertas; ?>;
                var filtro = 'abertos';
                var pedidoOracoes = 0;
                var carregandoOracoes = false;
                // Pedido aberto pelo link da notificação: no topo e destacado.
                var oracaoAlvo = <?php echo json_encode($oracao_link, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                // extra: o pedido do link entrou além da página do servidor e não
                // conta para o "pular" do "Ver mais".
                var extraId = 0;
                if (oracaoAlvo) {
                    var antes = oracoes.length;
                    oracoes = oracoes.filter(function (o) { return o.id !== oracaoAlvo.id; });
                    if (oracoes.length === antes) extraId = oracaoAlvo.id;
                    oracoes.unshift(oracaoAlvo);
                }
                var oracaoAlvoId = oracaoAlvo ? oracaoAlvo.id : 0;
                var oracaoTotalBadge = document.getElementById('oracoesTotal');
                var listaOracoes = document.getElementById('oracoesLista');
                var filtros = document.getElementById('oracoesFiltros');
                var maisBoxOracoes = document.getElementById('oracoesMaisBox');
                var maisOracoes = document.getElementById('oracoesMais');
                var maisErroOracoes = document.getElementById('oracoesMaisErro');
                var oracaoTexto = document.getElementById('oracaoTexto');
                var oracaoAcoes = document.getElementById('oracaoAcoes');
                var oracaoErro = document.getElementById('oracaoErro');
                var oracaoEnviar = document.getElementById('oracaoEnviar');
                var oracaoAnonimo = document.getElementById('oracaoAnonimo');
                var RESPOSTA_MAX = <?php echo (int) ORACAO_RESPOSTA_MAX; ?>;

                var vaziosOracoes = {
                    abertos: 'Nenhum pedido de oração no momento. Precisa de oração? Escreva acima.',
                    atendidos: 'Nenhuma oração marcada como atendida ainda.',
                    meus: 'Você ainda não fez nenhum pedido de oração.'
                };

                function enviarOracao(payload) {
                    payload.csrf_token = csrfToken;
                    return fetch('ajax_oracoes.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    }).then(function (r) { return r.json(); });
                }

                function textoIntercessoes(o) {
                    if (o.meu) {
                        if (!o.amens) return o.status === 'atendido' ? '' : 'Ainda ninguém disse amém';
                        return o.amens === 1 ? '1 pessoa orou por você' : o.amens + ' pessoas oraram por você';
                    }
                    if (!o.amens) return o.status === 'atendido' ? '' : 'Seja o primeiro a orar';
                    return o.amens === 1 ? '1 pessoa orou' : o.amens + ' pessoas oraram';
                }

                function atualizarAmem(o, botao, contador) {
                    contador.textContent = textoIntercessoes(o);
                    if (!botao) return;
                    botao.classList.toggle('is-ativo', o.meu_amem);
                    botao.setAttribute('aria-pressed', o.meu_amem ? 'true' : 'false');
                    botao.querySelector('span').textContent = o.meu_amem ? 'Orei' : 'Amém';
                    botao.title = o.meu_amem ? 'Você orou por este pedido. Toque para desfazer.' : 'Toque quando orar por este pedido';
                }

                function montarOracao(o) {
                    var li = document.createElement('li');
                    li.className = 'video-comentario oracao' + (o.status === 'atendido' ? ' is-atendida' : '');
                    li.dataset.id = o.id;
                    li.innerHTML =
                        '<span class="presenca-avatar"></span>' +
                        '<div class="video-comentario-corpo">' +
                            '<div class="video-comentario-topo"><strong class="video-comentario-nome"></strong><small class="text-muted"></small></div>' +
                            '<p class="video-comentario-texto"></p>' +
                            '<div class="oracao-rodape"><span class="oracao-intercessoes"></span></div>' +
                        '</div>';
                    var corpo = li.querySelector('.video-comentario-corpo');
                    li.querySelector('.presenca-avatar').textContent = o.anonimo && !o.meu && o.nome === 'Anônimo' ? '?' : (o.nome || '?').charAt(0).toUpperCase();
                    li.querySelector('.video-comentario-nome').textContent = o.nome + (o.meu ? ' (você)' : '');
                    if (o.meu && o.anonimo) {
                        var tagAnonimo = document.createElement('span');
                        tagAnonimo.className = 'presenca-tag is-neutra';
                        tagAnonimo.textContent = 'Anônimo';
                        tagAnonimo.title = 'Os outros veem este pedido como "Anônimo"';
                        li.querySelector('.video-comentario-nome').appendChild(tagAnonimo);
                    }
                    li.querySelector('small').textContent = tempoRelativo(o.criado_ts);
                    li.querySelector('.video-comentario-texto').textContent = o.texto;

                    if (o.status === 'atendido') {
                        var selo = document.createElement('span');
                        selo.className = 'oracao-selo-atendida';
                        selo.innerHTML = '<i class="fas fa-hands-clapping" aria-hidden="true"></i>';
                        selo.appendChild(document.createTextNode('Oração atendida' + (o.atendido_ts ? ' · ' + tempoRelativo(o.atendido_ts) : '')));
                        corpo.insertBefore(selo, li.querySelector('.video-comentario-topo'));
                        if (o.resposta) {
                            var resposta = document.createElement('div');
                            resposta.className = 'oracao-resposta';
                            resposta.innerHTML = '<strong>Como Deus respondeu</strong><p></p>';
                            resposta.querySelector('p').textContent = o.resposta;
                            li.querySelector('.video-comentario-texto').after(resposta);
                        }
                    }

                    if (o.pode_remover) {
                        var remover = document.createElement('button');
                        remover.type = 'button';
                        remover.className = 'video-comentario-excluir';
                        remover.title = o.meu ? 'Remover meu pedido' : 'Remover pedido (moderação)';
                        remover.setAttribute('aria-label', remover.title);
                        remover.innerHTML = '<i class="fas fa-trash-alt"></i>';
                        remover.addEventListener('click', function () { removerOracao(o); });
                        li.querySelector('.video-comentario-topo').appendChild(remover);
                    }

                    var rodape = li.querySelector('.oracao-rodape');
                    var contador = rodape.querySelector('.oracao-intercessoes');
                    var botaoAmem = null;
                    if (!o.meu && o.status === 'aberto') {
                        botaoAmem = document.createElement('button');
                        botaoAmem.type = 'button';
                        botaoAmem.className = 'oracao-amem';
                        botaoAmem.innerHTML = '<i class="fas fa-hands-praying" aria-hidden="true"></i><span></span>';
                        botaoAmem.addEventListener('click', function () { dizerAmem(o, botaoAmem, contador); });
                        rodape.insertBefore(botaoAmem, contador);
                    }
                    atualizarAmem(o, botaoAmem, contador);

                    if (o.meu && o.status === 'aberto') {
                        var atendida = document.createElement('button');
                        atendida.type = 'button';
                        atendida.className = 'video-comentario-responder';
                        atendida.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>Marcar como atendida';
                        atendida.addEventListener('click', function () { abrirAtendida(o, li, atendida); });
                        rodape.appendChild(atendida);
                    } else if (o.meu && o.status === 'atendido') {
                        var testemunho = document.createElement('a');
                        testemunho.className = 'video-comentario-responder';
                        testemunho.href = 'testemunhos.php?enviar=1#enviar';
                        testemunho.innerHTML = '<i class="fas fa-bullhorn" aria-hidden="true"></i>Contar como testemunho';
                        rodape.appendChild(testemunho);
                    }
                    return li;
                }

                function dizerAmem(o, botao, contador) {
                    if (botao.disabled) return;
                    botao.disabled = true;
                    var amem = !o.meu_amem;
                    enviarOracao({ acao: 'amem', idor_id: o.id, amem: amem })
                        .then(function (resp) {
                            if (!resp.ok) { siteAvisar(resp.erro || 'Não foi possível registrar.', 'erro'); return; }
                            o.amens = resp.amens;
                            o.meu_amem = resp.meu_amem;
                            atualizarAmem(o, botao, contador);
                            if (amem) {
                                botao.classList.add('is-pulso');
                                setTimeout(function () { botao.classList.remove('is-pulso'); }, 600);
                            }
                        })
                        .catch(function () { siteAvisar('Falha de conexão. Tente de novo.', 'erro'); })
                        .then(function () { botao.disabled = false; });
                }

                // "Marcar como atendida": campo opcional para contar como
                // Deus respondeu, dentro do próprio pedido.
                function abrirAtendida(o, li, botao) {
                    if (li.querySelector('.oracao-atendida-form')) return;
                    botao.hidden = true;
                    var form = document.createElement('form');
                    form.className = 'video-resposta-form oracao-atendida-form';
                    form.innerHTML =
                        '<label class="small fw-semibold mb-1">Que bênção! Quer contar como Deus respondeu? <span class="text-muted fw-normal">(opcional)</span></label>' +
                        '<textarea class="form-control" rows="2" placeholder="Ex.: Fui aprovado na entrevista na sexta-feira." aria-label="Como a oração foi respondida (opcional)"></textarea>' +
                        '<div class="video-comentario-acoes is-aberto">' +
                            '<small class="text-danger me-auto" role="alert"></small>' +
                            '<button type="button" class="btn btn-sm btn-outline-light">Cancelar</button>' +
                            '<button type="submit" class="btn btn-sm btn-play">Marcar como atendida</button>' +
                        '</div>';
                    var campo = form.querySelector('textarea');
                    var erroForm = form.querySelector('small');
                    var confirmar = form.querySelector('button[type="submit"]');
                    campo.maxLength = RESPOSTA_MAX;
                    form.querySelector('.btn-outline-light').addEventListener('click', function () {
                        form.remove();
                        botao.hidden = false;
                    });
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        confirmar.disabled = true;
                        erroForm.textContent = '';
                        enviarOracao({ acao: 'atendida', idor_id: o.id, resposta: campo.value })
                            .then(function (resp) {
                                if (!resp.ok) { erroForm.textContent = resp.erro || 'Não foi possível salvar.'; return; }
                                if (resp.oracao) {
                                    oracoes = oracoes.map(function (x) { return x.id === o.id ? resp.oracao : x; });
                                }
                                // Sai dos pedidos abertos.
                                if (filtro === 'abertos') {
                                    oracoes = oracoes.filter(function (x) { return x.id !== o.id; });
                                }
                                totalAbertas = Math.max(0, totalAbertas - 1);
                                renderizarOracoes(filtro === 'abertos' ? 0 : o.id);
                            })
                            .catch(function () { erroForm.textContent = 'Falha de conexão. Tente de novo.'; })
                            .then(function () { confirmar.disabled = false; });
                    });
                    li.querySelector('.oracao-rodape').after(form);
                    campo.focus();
                }

                function removerOracao(o) {
                    siteConfirmar(o.meu ? 'Remover seu pedido de oração? Ele sai do mural.' : 'Remover este pedido do mural (moderação)?', { botao: 'Remover', perigo: true }).then(function (ok) {
                        if (!ok) return;
                        enviarOracao({ acao: 'remover', idor_id: o.id }).then(function (resp) {
                            // Já removido (outra aba ou outra pessoa da equipe): some da lista também.
                            if (!resp.ok && resp.erro !== 'Pedido não encontrado.') { siteAvisar(resp.erro || 'Não foi possível remover.', 'erro'); return; }
                            oracoes = oracoes.filter(function (x) { return x.id !== o.id; });
                            if (o.status === 'aberto') totalAbertas = Math.max(0, totalAbertas - 1);
                            renderizarOracoes();
                        }).catch(function () { siteAvisar('Falha de conexão. Tente de novo.', 'erro'); });
                    });
                }

                function renderizarOracoes(destacarId) {
                    listaOracoes.innerHTML = '';
                    if (!oracoes.length && !oracoesTemMais) {
                        var vazio = document.createElement('li');
                        vazio.className = 'video-comentarios-vazio';
                        vazio.textContent = vaziosOracoes[filtro];
                        listaOracoes.appendChild(vazio);
                    }
                    oracoes.forEach(function (o) {
                        var li = montarOracao(o);
                        if (o.id === destacarId || o.id === oracaoAlvoId) {
                            li.classList.add('is-alvo');
                            setTimeout(function () { li.classList.remove('is-alvo'); }, 4500);
                            if (o.id === oracaoAlvoId) {
                                setTimeout(function () { li.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 250);
                            }
                        }
                        listaOracoes.appendChild(li);
                    });
                    oracaoAlvoId = 0;
                    oracaoTotalBadge.textContent = totalAbertas ? '(' + totalAbertas + ')' : '';
                    maisBoxOracoes.hidden = !oracoesTemMais;
                }

                function carregarOracoes(recomecar) {
                    if (carregandoOracoes && !recomecar) return;
                    var meuPedido = ++pedidoOracoes;
                    carregandoOracoes = true;
                    maisOracoes.disabled = true;
                    maisErroOracoes.textContent = '';
                    if (recomecar) {
                        oracoes = [];
                        extraId = 0;
                        oracoesTemMais = false;
                        maisBoxOracoes.hidden = true;
                        listaOracoes.innerHTML = ESQUELETO_LISTA;
                    }
                    fetch('ajax_oracoes.php?filtro=' + filtro + '&pular=' + (oracoes.length - (extraId && oracoes.some(function (x) { return x.id === extraId; }) ? 1 : 0)))
                        .then(function (r) { return r.json(); })
                        .then(function (resp) {
                            if (meuPedido !== pedidoOracoes) return;
                            if (!resp.ok) throw new Error(resp.erro || '');
                            resp.oracoes.forEach(function (o) {
                                if (!oracoes.some(function (x) { return x.id === o.id; })) oracoes.push(o);
                            });
                            oracoesTemMais = resp.tem_mais;
                            totalAbertas = resp.total_abertas;
                        })
                        .catch(function () {
                            if (meuPedido !== pedidoOracoes) return;
                            maisErroOracoes.textContent = 'Não foi possível carregar. Tente de novo.';
                            oracoesTemMais = true;
                        })
                        .then(function () {
                            if (meuPedido !== pedidoOracoes) return;
                            carregandoOracoes = false;
                            maisOracoes.disabled = false;
                            renderizarOracoes();
                        });
                }

                maisOracoes.addEventListener('click', function () { carregarOracoes(false); });

                filtros.addEventListener('click', function (e) {
                    var botaoFiltro = e.target.closest('.video-comentarios-ordem');
                    if (!botaoFiltro || botaoFiltro.dataset.filtro === filtro) return;
                    filtro = botaoFiltro.dataset.filtro;
                    filtros.querySelectorAll('.video-comentarios-ordem').forEach(function (b) {
                        var ativo = b === botaoFiltro;
                        b.classList.toggle('is-ativa', ativo);
                        b.setAttribute('aria-pressed', ativo ? 'true' : 'false');
                    });
                    carregarOracoes(true);
                });

                function fecharOracaoForm() {
                    oracaoTexto.value = '';
                    oracaoTexto.rows = 1;
                    oracaoAnonimo.checked = false;
                    oracaoErro.textContent = '';
                    oracaoAcoes.classList.remove('is-aberto');
                }

                oracaoTexto.addEventListener('focus', function () { oracaoAcoes.classList.add('is-aberto'); oracaoTexto.rows = 3; });
                document.getElementById('oracaoCancelar').addEventListener('click', fecharOracaoForm);

                oracaoForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (!oracaoTexto.value.trim()) { oracaoErro.textContent = 'Escreva o seu pedido.'; oracaoTexto.focus(); return; }
                    oracaoEnviar.disabled = true;
                    oracaoErro.textContent = '';
                    enviarOracao({ acao: 'pedir', idct_id: conteudoId, texto: oracaoTexto.value, anonimo: oracaoAnonimo.checked })
                        .then(function (resp) {
                            if (!resp.ok) { oracaoErro.textContent = resp.erro || 'Não foi possível enviar o pedido.'; return; }
                            totalAbertas++;
                            if (filtro !== 'atendidos') oracoes.unshift(resp.oracao);
                            fecharOracaoForm();
                            renderizarOracoes(resp.oracao.id);
                        })
                        .catch(function () { oracaoErro.textContent = 'Falha de conexão. Tente de novo.'; })
                        .then(function () { oracaoEnviar.disabled = false; });
                });

                // Pedido escrito e não enviado: o navegador pergunta antes de sair.
                window.addEventListener('beforeunload', function (e) {
                    if (oracaoTexto.value.trim()) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                });

                renderizarOracoes();
            })();

            // ---------------------------------------------------------------
            // Resumos (T29): um por pessoa por aula, publicado na hora, com
            // o voto "Edificante" (T16; um por pessoa, não no próprio). O
            // resumo de quem vê fica no topo, à parte da lista (e fora do
            // "pular" do "Ver mais"). "Trazer minhas notas" (T27) puxa as
            // notas da aula para o texto, para reescrever com calma.
            // ---------------------------------------------------------------
            (function () {
                var resumoForm = document.getElementById('resumoForm');
                if (!resumoForm) return;

                var resumos = <?php echo json_encode($resumos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                var meuResumo = <?php echo json_encode($meu_resumo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                var resumosTemMais = <?php echo $resumos_tem_mais ? 'true' : 'false'; ?>;
                var resumosTotal = <?php echo (int) $resumos_total; ?>;
                var RESUMO_MIN = <?php echo (int) RESUMO_TEXTO_MIN; ?>;
                var RESUMO_MAX = <?php echo (int) RESUMO_TEXTO_MAX; ?>;
                var ordemResumos = 'votados';
                var pedidoResumos = 0;
                var carregandoResumos = false;
                // Resumo aberto pelo link da notificação: no topo e destacado.
                // extraResumoId: entrou além da página do servidor (não conta no "pular").
                var resumoAlvo = <?php echo json_encode($resumo_link, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                var resumoAlvoId = resumoAlvo ? resumoAlvo.id : 0;
                var extraResumoId = 0;
                if (resumoAlvo && !resumoAlvo.meu) {
                    var antesAlvo = resumos.length;
                    resumos = resumos.filter(function (r) { return r.id !== resumoAlvo.id; });
                    if (resumos.length === antesAlvo) extraResumoId = resumoAlvo.id;
                    resumos.unshift(resumoAlvo);
                }

                var badgeResumos = document.getElementById('resumosTotal');
                var listaResumos = document.getElementById('resumosLista');
                var meuBox = document.getElementById('resumoMeu');
                var novoBox = document.getElementById('resumoNovo');
                var abrir = document.getElementById('resumoAbrir');
                var ordensResumos = document.getElementById('resumosOrdens');
                var maisBoxResumos = document.getElementById('resumosMaisBox');
                var maisResumos = document.getElementById('resumosMais');
                var maisErroResumos = document.getElementById('resumosMaisErro');
                var resumoTexto = document.getElementById('resumoTexto');
                var resumoErro = document.getElementById('resumoErro');
                var resumoContador = document.getElementById('resumoContador');
                var resumoPublicar = document.getElementById('resumoPublicar');
                var trazerNotas = document.getElementById('resumoTrazerNotas');

                function enviarResumo(payload) {
                    payload.csrf_token = csrfToken;
                    return fetch('ajax_resumos.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    }).then(function (r) { return r.json(); });
                }

                // "120 / 3000" e, abaixo do mínimo, quanto falta.
                function contarResumo(campo, contador) {
                    var tamanho = campo.value.trim().length;
                    contador.textContent = tamanho < RESUMO_MIN
                        ? 'faltam ' + (RESUMO_MIN - tamanho) + ' caracteres'
                        : campo.value.length + ' / ' + RESUMO_MAX;
                    contador.classList.toggle('is-limite', RESUMO_MAX - campo.value.length <= 100);
                }

                // Notas da aula (T27), na ordem do vídeo, com o minuto.
                function notasDaAula() {
                    return (typeof notas !== 'undefined' && notas) ? notas : [];
                }

                function atualizarTrazerNotas() {
                    if (trazerNotas) trazerNotas.hidden = !notasDaAula().length;
                }

                function montarVotoResumo(r) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'video-comentario-voto is-edificante';
                    b.innerHTML = '<i class="fas fa-arrow-up" aria-hidden="true"></i><span>Edificante</span><span class="video-comentario-voto-num"></span>';
                    function atualizar() {
                        b.classList.toggle('is-ativo', r.meu_voto);
                        b.setAttribute('aria-pressed', r.meu_voto ? 'true' : 'false');
                        b.querySelector('.video-comentario-voto-num').textContent = r.votos;
                        if (!r.meu) b.title = r.meu_voto ? 'Desfazer voto' : 'Este resumo me edificou';
                    }
                    if (r.meu) {
                        b.disabled = true;
                        b.title = 'Você não pode votar no próprio resumo';
                    } else {
                        b.addEventListener('click', function () {
                            if (b.dataset.enviando) return;
                            b.dataset.enviando = '1';
                            enviarResumo({ acao: 'votar', idrs_id: r.id, votar: !r.meu_voto })
                                .then(function (resp) {
                                    if (!resp.ok) { siteAvisar(resp.erro || 'Não foi possível votar.', 'erro'); return; }
                                    r.votos = resp.votos;
                                    r.meu_voto = resp.meu_voto;
                                    atualizar();
                                })
                                .catch(function () { siteAvisar('Falha de conexão. Tente de novo.', 'erro'); })
                                .then(function () { delete b.dataset.enviando; });
                        });
                    }
                    atualizar();
                    return b;
                }

                function montarResumo(r) {
                    var li = document.createElement('li');
                    li.className = 'video-comentario resumo' + (r.meu ? ' is-meu' : '');
                    li.dataset.id = r.id;
                    li.innerHTML =
                        '<span class="presenca-avatar"></span>' +
                        '<div class="video-comentario-corpo">' +
                            '<div class="video-comentario-topo"><strong class="video-comentario-nome"></strong><small class="text-muted"></small></div>' +
                            '<p class="video-comentario-texto resumo-texto"></p>' +
                            '<button type="button" class="testemunho-ler-mais" hidden>Ler mais</button>' +
                            '<div class="oracao-rodape"></div>' +
                        '</div>';
                    var corpo = li.querySelector('.video-comentario-corpo');
                    var topo = li.querySelector('.video-comentario-topo');
                    li.querySelector('.presenca-avatar').textContent = (r.nome || '?').charAt(0).toUpperCase();
                    li.querySelector('.video-comentario-nome').textContent = r.nome + (r.meu ? ' (você)' : '');
                    li.querySelector('small').textContent = tempoRelativo(r.criado_ts) + (r.editado ? ' · editado' : '');
                    if (r.meu) {
                        var selo = document.createElement('span');
                        selo.className = 'resumo-selo-meu';
                        selo.innerHTML = '<i class="fas fa-feather-pointed" aria-hidden="true"></i>Seu resumo';
                        corpo.insertBefore(selo, topo);
                    }

                    var texto = li.querySelector('.resumo-texto');
                    var lerMais = li.querySelector('.testemunho-ler-mais');
                    texto.textContent = r.texto;
                    // Resumo longo começa recolhido. Decide pelo tamanho do
                    // texto (a aba pode estar oculta agora, sem como medir).
                    if (r.texto.length > 600 || r.texto.split('\n').length > 8) {
                        texto.classList.add('is-recolhido');
                        lerMais.hidden = false;
                    }
                    lerMais.addEventListener('click', function () {
                        var aberto = texto.classList.toggle('is-recolhido') === false;
                        lerMais.textContent = aberto ? 'Ler menos' : 'Ler mais';
                    });

                    if (r.meu) {
                        var editar = document.createElement('button');
                        editar.type = 'button';
                        editar.className = 'aula-nota-acao resumo-editar';
                        editar.title = 'Editar meu resumo';
                        editar.setAttribute('aria-label', editar.title);
                        editar.innerHTML = '<i class="fas fa-pen"></i>';
                        editar.addEventListener('click', function () { editarResumo(r, li); });
                        topo.appendChild(editar);
                    }
                    if (r.pode_remover) {
                        var remover = document.createElement('button');
                        remover.type = 'button';
                        remover.className = 'video-comentario-excluir';
                        remover.title = r.meu ? 'Remover meu resumo' : 'Remover resumo (moderação)';
                        remover.setAttribute('aria-label', remover.title);
                        remover.innerHTML = '<i class="fas fa-trash-alt"></i>';
                        if (r.meu) remover.style.marginLeft = '0';
                        remover.addEventListener('click', function () { removerResumo(r); });
                        topo.appendChild(remover);
                    }
                    li.querySelector('.oracao-rodape').appendChild(montarVotoResumo(r));
                    return li;
                }

                function editarResumo(r, li) {
                    if (li.querySelector('.aula-nota-form')) return;
                    var texto = li.querySelector('.resumo-texto');
                    var lerMais = li.querySelector('.testemunho-ler-mais');
                    var form = document.createElement('form');
                    form.className = 'aula-nota-form is-edicao';
                    form.innerHTML =
                        '<textarea class="form-control" rows="8" aria-label="Editar meu resumo"></textarea>' +
                        '<div class="aula-nota-form-rodape">' +
                            '<small class="text-danger me-auto" role="alert"></small>' +
                            '<small class="aula-nota-contador"></small>' +
                            '<button type="button" class="btn btn-sm btn-outline-light">Cancelar</button>' +
                            '<button type="submit" class="btn btn-sm btn-play">Salvar</button>' +
                        '</div>';
                    var campo = form.querySelector('textarea');
                    var erroEdicao = form.querySelector('small.text-danger');
                    var contador = form.querySelector('.aula-nota-contador');
                    var salvar = form.querySelector('button[type="submit"]');
                    campo.maxLength = RESUMO_MAX;
                    campo.value = r.texto;
                    campo.dataset.original = r.texto;
                    contarResumo(campo, contador);
                    campo.addEventListener('input', function () { contarResumo(campo, contador); });
                    form.querySelector('.btn-outline-light').addEventListener('click', renderizarResumos);
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        salvar.disabled = true;
                        erroEdicao.textContent = '';
                        enviarResumo({ acao: 'editar', idrs_id: r.id, texto: campo.value })
                            .then(function (resp) {
                                if (!resp.ok) { erroEdicao.textContent = resp.erro || 'Não foi possível salvar.'; return; }
                                meuResumo = resp.resumo;
                                renderizarResumos(meuResumo.id);
                            })
                            .catch(function () { erroEdicao.textContent = 'Falha de conexão. Tente de novo.'; })
                            .then(function () { salvar.disabled = false; });
                    });
                    texto.hidden = true;
                    lerMais.hidden = true;
                    texto.after(form);
                    campo.focus();
                }

                function removerResumo(r) {
                    siteConfirmar(r.meu ? 'Remover seu resumo? Os votos dele se perdem.' : 'Remover este resumo (moderação)?', { botao: 'Remover', perigo: true }).then(function (ok) {
                        if (!ok) return;
                        enviarResumo({ acao: 'remover', idrs_id: r.id }).then(function (resp) {
                            // Já removido (outra aba ou outra pessoa da equipe): some também.
                            if (!resp.ok && resp.erro !== 'Resumo não encontrado.') { siteAvisar(resp.erro || 'Não foi possível remover.', 'erro'); return; }
                            if (meuResumo && meuResumo.id === r.id) {
                                meuResumo = null;
                            } else {
                                resumos = resumos.filter(function (x) { return x.id !== r.id; });
                            }
                            resumosTotal = Math.max(0, resumosTotal - 1);
                            renderizarResumos();
                        }).catch(function () { siteAvisar('Falha de conexão. Tente de novo.', 'erro'); });
                    });
                }

                function destacar(li, rolar) {
                    li.classList.add('is-alvo');
                    setTimeout(function () { li.classList.remove('is-alvo'); }, 4500);
                    if (rolar) setTimeout(function () { li.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 250);
                }

                function renderizarResumos(destacarId) {
                    meuBox.innerHTML = '';
                    if (meuResumo) {
                        var meuLi = montarResumo(meuResumo);
                        meuBox.appendChild(meuLi);
                        if (meuResumo.id === destacarId || meuResumo.id === resumoAlvoId) destacar(meuLi, meuResumo.id === resumoAlvoId);
                    }
                    meuBox.hidden = !meuResumo;
                    novoBox.hidden = !!meuResumo;

                    listaResumos.innerHTML = '';
                    if (!resumos.length && !resumosTemMais) {
                        var vazio = document.createElement('li');
                        vazio.className = 'video-comentarios-vazio';
                        vazio.textContent = meuResumo
                            ? 'Ainda não há resumos de outras pessoas nesta aula.'
                            : 'Ninguém resumiu esta aula ainda. Que tal ser o primeiro?';
                        listaResumos.appendChild(vazio);
                    }
                    resumos.forEach(function (r) {
                        var li = montarResumo(r);
                        if (r.id === destacarId || r.id === resumoAlvoId) destacar(li, r.id === resumoAlvoId);
                        listaResumos.appendChild(li);
                    });
                    resumoAlvoId = 0;
                    badgeResumos.textContent = resumosTotal ? '(' + resumosTotal + ')' : '';
                    maisBoxResumos.hidden = !resumosTemMais;
                }

                function carregarResumos(recomecar) {
                    if (carregandoResumos && !recomecar) return;
                    var meuPedido = ++pedidoResumos;
                    carregandoResumos = true;
                    maisResumos.disabled = true;
                    maisErroResumos.textContent = '';
                    if (recomecar) {
                        resumos = [];
                        extraResumoId = 0;
                        resumosTemMais = false;
                        maisBoxResumos.hidden = true;
                        listaResumos.innerHTML = ESQUELETO_LISTA;
                    }
                    var pular = resumos.length - (extraResumoId && resumos.some(function (x) { return x.id === extraResumoId; }) ? 1 : 0);
                    fetch('ajax_resumos.php?idct_id=' + conteudoId + '&ordem=' + ordemResumos + '&pular=' + pular)
                        .then(function (r) { return r.json(); })
                        .then(function (resp) {
                            if (meuPedido !== pedidoResumos) return;
                            if (!resp.ok) throw new Error(resp.erro || '');
                            resp.resumos.forEach(function (r) {
                                if (!resumos.some(function (x) { return x.id === r.id; })) resumos.push(r);
                            });
                            resumosTemMais = resp.tem_mais;
                            resumosTotal = resp.total;
                        })
                        .catch(function () {
                            if (meuPedido !== pedidoResumos) return;
                            maisErroResumos.textContent = 'Não foi possível carregar. Tente de novo.';
                            resumosTemMais = true;
                        })
                        .then(function () {
                            if (meuPedido !== pedidoResumos) return;
                            carregandoResumos = false;
                            maisResumos.disabled = false;
                            renderizarResumos();
                        });
                }

                maisResumos.addEventListener('click', function () { carregarResumos(false); });

                ordensResumos.addEventListener('click', function (e) {
                    var botaoOrdem = e.target.closest('.video-comentarios-ordem');
                    if (!botaoOrdem || botaoOrdem.dataset.ordem === ordemResumos) return;
                    ordemResumos = botaoOrdem.dataset.ordem;
                    ordensResumos.querySelectorAll('.video-comentarios-ordem').forEach(function (b) {
                        var ativo = b === botaoOrdem;
                        b.classList.toggle('is-ativa', ativo);
                        b.setAttribute('aria-pressed', ativo ? 'true' : 'false');
                    });
                    carregarResumos(true);
                });

                function fecharResumoForm() {
                    resumoForm.hidden = true;
                    abrir.hidden = false;
                    resumoErro.textContent = '';
                }

                abrir.addEventListener('click', function () {
                    abrir.hidden = true;
                    resumoForm.hidden = false;
                    atualizarTrazerNotas();
                    contarResumo(resumoTexto, resumoContador);
                    resumoTexto.focus();
                });
                document.getElementById('resumoCancelar').addEventListener('click', function () {
                    var pergunta = resumoTexto.value.trim()
                        ? siteConfirmar('Descartar o que você escreveu?', { botao: 'Descartar', perigo: true })
                        : Promise.resolve(true);
                    pergunta.then(function (ok) {
                        if (!ok) return;
                        resumoTexto.value = '';
                        fecharResumoForm();
                    });
                });
                resumoTexto.addEventListener('input', function () { contarResumo(resumoTexto, resumoContador); });

                if (trazerNotas) {
                    trazerNotas.addEventListener('click', function () {
                        var linhas = notasDaAula().map(function (n) {
                            return '• ' + (n.momento !== null ? '[' + formatarMomento(n.momento) + '] ' : '') + n.texto;
                        });
                        if (!linhas.length) return;
                        var atual = resumoTexto.value.replace(/\s+$/, '');
                        resumoTexto.value = (atual ? atual + '\n\n' : '') + linhas.join('\n');
                        resumoTexto.value = resumoTexto.value.slice(0, RESUMO_MAX);
                        contarResumo(resumoTexto, resumoContador);
                        resumoTexto.focus();
                    });
                }

                resumoForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (resumoTexto.value.trim().length < RESUMO_MIN) {
                        resumoErro.textContent = 'O resumo precisa de pelo menos ' + RESUMO_MIN + ' caracteres.';
                        resumoTexto.focus();
                        return;
                    }
                    resumoPublicar.disabled = true;
                    resumoErro.textContent = '';
                    enviarResumo({ acao: 'publicar', idct_id: conteudoId, texto: resumoTexto.value })
                        .then(function (resp) {
                            if (!resp.ok) { resumoErro.textContent = resp.erro || 'Não foi possível publicar.'; return; }
                            meuResumo = resp.resumo;
                            resumosTotal++;
                            resumoTexto.value = '';
                            fecharResumoForm();
                            renderizarResumos(meuResumo.id);
                        })
                        .catch(function () { resumoErro.textContent = 'Falha de conexão. Tente de novo.'; })
                        .then(function () { resumoPublicar.disabled = false; });
                });

                // Resumo escrito (ou editado) e não salvo: o navegador pergunta antes de sair.
                window.addEventListener('beforeunload', function (e) {
                    var editando = meuBox.querySelector('textarea');
                    if (resumoTexto.value.trim() || (editando && editando.value !== editando.dataset.original)) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                });

                renderizarResumos();
            })();

            // ---------------------------------------------------------------
            // Minhas notas (T27): só do próprio fiel, com o minuto da aula.
            // Ao começar a escrever, o minuto em que o vídeo está entra
            // sozinho (dá para tirar ou trocar pelo minuto atual).
            // ---------------------------------------------------------------
            var notaForm = document.getElementById('notaForm');
            if (!notaForm) return;

            var notas = <?php echo json_encode($notas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            var NOTA_MAX = <?php echo (int) NOTA_AULA_TEXTO_MAX; ?>;
            var notasLista = document.getElementById('notasLista');
            var notasTotal = document.getElementById('notasTotal');
            var notaTexto = document.getElementById('notaTexto');
            var notaErro = document.getElementById('notaErro');
            var notaContador = document.getElementById('notaContador');
            var notaSalvar = document.getElementById('notaSalvar');
            var notaMomento = document.getElementById('notaMomento');
            var notaMomentoTexto = document.getElementById('notaMomentoTexto');
            var notaMarcar = document.getElementById('notaMarcarMomento');
            // null = sem minuto. semMinuto: a pessoa tirou o minuto desta
            // nota — não marca de novo sozinho até salvar.
            var notaMomentoValor = null;
            var semMinuto = false;

            function enviarNota(payload) {
                payload.csrf_token = csrfToken;
                return fetch('ajax_notas_aula.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(function (r) { return r.json(); });
            }

            function minutoAtual() {
                return player ? Math.floor(player.currentTime || 0) : null;
            }

            // Na ordem do vídeo; sem minuto no fim, na ordem em que foram escritas.
            function ordenarNotas() {
                notas.sort(function (a, b) {
                    if (a.momento === null && b.momento === null) return a.id - b.id;
                    if (a.momento === null) return 1;
                    if (b.momento === null) return -1;
                    return a.momento - b.momento || a.id - b.id;
                });
            }

            function marcarMomentoForm(segundos) {
                notaMomentoValor = segundos;
                if (!notaMomento) return;
                notaMomento.hidden = segundos === null;
                if (segundos !== null) notaMomentoTexto.textContent = 'no minuto ' + formatarMomento(segundos);
            }

            function atualizarContador(campo, contador) {
                var restante = NOTA_MAX - campo.value.length;
                // Só aparece perto do limite.
                contador.hidden = restante > 200;
                contador.textContent = restante + ' caracteres restantes';
                contador.classList.toggle('is-limite', restante <= 50);
            }

            function botaoMomentoNota(n) {
                if (n.momento === null) {
                    var sem = document.createElement('span');
                    sem.className = 'aula-nota-sem-momento';
                    sem.textContent = 'Sem minuto';
                    return sem;
                }
                var m = document.createElement(player ? 'button' : 'span');
                m.className = 'video-comentario-citacao is-momento';
                m.innerHTML = '<i class="far fa-clock" aria-hidden="true"></i>';
                m.appendChild(document.createTextNode(formatarMomento(n.momento)));
                if (player) {
                    m.type = 'button';
                    m.title = 'Ir para esse momento do vídeo';
                    m.addEventListener('click', function () { irParaMomento(n.momento); });
                }
                return m;
            }

            function montarNota(n) {
                var li = document.createElement('li');
                li.className = 'aula-nota';
                li.dataset.id = n.id;
                li.innerHTML =
                    '<div class="aula-nota-topo">' +
                        '<small class="text-muted aula-nota-data"></small>' +
                        '<button type="button" class="aula-nota-acao" data-acao="editar" title="Editar nota" aria-label="Editar nota"><i class="fas fa-pen"></i></button>' +
                        '<button type="button" class="aula-nota-acao is-excluir" data-acao="excluir" title="Apagar nota" aria-label="Apagar nota"><i class="fas fa-trash-alt"></i></button>' +
                    '</div>' +
                    '<p class="aula-nota-texto"></p>';
                var topo = li.querySelector('.aula-nota-topo');
                topo.insertBefore(botaoMomentoNota(n), topo.firstChild);
                li.querySelector('.aula-nota-data').textContent = tempoRelativo(n.criado_ts) + (n.editado ? ' · editada' : '');
                li.querySelector('.aula-nota-texto').textContent = n.texto;
                li.querySelector('[data-acao="editar"]').addEventListener('click', function () { editarNota(n, li); });
                li.querySelector('[data-acao="excluir"]').addEventListener('click', function () { excluirNota(n); });
                return li;
            }

            function renderizarNotas(destacarId) {
                ordenarNotas();
                notasLista.innerHTML = '';
                if (!notas.length) {
                    var vazio = document.createElement('li');
                    vazio.className = 'video-comentarios-vazio';
                    vazio.textContent = 'Você ainda não anotou nada nesta aula.';
                    notasLista.appendChild(vazio);
                }
                notas.forEach(function (n) {
                    var li = montarNota(n);
                    if (n.id === destacarId) {
                        li.classList.add('is-nova');
                        setTimeout(function () { li.classList.remove('is-nova'); }, 2000);
                    }
                    notasLista.appendChild(li);
                });
                notasTotal.textContent = notas.length ? '(' + notas.length + ')' : '';
            }

            // Edição no lugar: texto e minuto (tirar ou trocar pelo atual).
            function editarNota(n, li) {
                if (li.classList.contains('is-editando')) return;
                li.classList.add('is-editando');
                var momento = n.momento;
                var form = document.createElement('form');
                form.className = 'aula-nota-form is-edicao';
                form.innerHTML =
                    '<textarea class="form-control" rows="3" aria-label="Editar nota"></textarea>' +
                    '<div class="aula-nota-form-rodape">' +
                        '<span class="video-comentario-anexo" hidden><i class="far fa-clock" aria-hidden="true"></i><span></span>' +
                            '<button type="button" aria-label="Tirar o minuto da aula"><i class="fas fa-times"></i></button></span>' +
                        (player ? '<button type="button" class="video-comentario-citar"><i class="far fa-clock" aria-hidden="true"></i><span>Usar o minuto atual</span></button>' : '') +
                        '<small class="text-danger me-auto" role="alert"></small>' +
                        '<small class="aula-nota-contador" hidden></small>' +
                        '<button type="button" class="btn btn-sm btn-outline-light">Cancelar</button>' +
                        '<button type="submit" class="btn btn-sm btn-play">Salvar</button>' +
                    '</div>';
                var campo = form.querySelector('textarea');
                var chip = form.querySelector('.video-comentario-anexo');
                var erroEdicao = form.querySelector('small.text-danger');
                var contador = form.querySelector('.aula-nota-contador');
                var salvar = form.querySelector('button[type="submit"]');
                campo.maxLength = NOTA_MAX;
                campo.value = n.texto;
                campo.dataset.original = n.texto;

                function mostrarMomento() {
                    chip.hidden = momento === null;
                    if (momento !== null) chip.querySelector('span').textContent = 'no minuto ' + formatarMomento(momento);
                }
                mostrarMomento();
                chip.querySelector('button').addEventListener('click', function () { momento = null; mostrarMomento(); });
                var usarAtual = form.querySelector('.video-comentario-citar');
                if (usarAtual) usarAtual.addEventListener('click', function () { momento = minutoAtual(); mostrarMomento(); });
                campo.addEventListener('input', function () { atualizarContador(campo, contador); });
                atualizarContador(campo, contador);

                function fechar() { renderizarNotas(); }
                form.querySelector('.btn-outline-light').addEventListener('click', fechar);
                campo.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') { e.preventDefault(); fechar(); }
                    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : salvar.click(); }
                });
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (!campo.value.trim()) { erroEdicao.textContent = 'A nota não pode ficar vazia — para tirar, use a lixeira.'; return; }
                    salvar.disabled = true;
                    erroEdicao.textContent = '';
                    enviarNota({ acao: 'editar', idan_id: n.id, texto: campo.value, momento: momento })
                        .then(function (resp) {
                            if (!resp.ok) { erroEdicao.textContent = resp.erro || 'Não foi possível salvar.'; return; }
                            notas = notas.map(function (x) { return x.id === n.id ? resp.nota : x; });
                            renderizarNotas(resp.nota.id);
                        })
                        .catch(function () { erroEdicao.textContent = 'Falha de conexão. Tente de novo.'; })
                        .then(function () { salvar.disabled = false; });
                });

                li.querySelector('.aula-nota-texto').replaceWith(form);
                campo.focus();
                campo.setSelectionRange(campo.value.length, campo.value.length);
            }

            function excluirNota(n) {
                siteConfirmar('Apagar essa nota? Não dá para desfazer.', { botao: 'Apagar', perigo: true }).then(function (ok) {
                    if (!ok) return;
                    enviarNota({ acao: 'excluir', idan_id: n.id }).then(function (resp) {
                        // Já apagada (outra aba do navegador): some da lista também.
                        if (!resp.ok && resp.erro !== 'Nota não encontrada.') { siteAvisar(resp.erro || 'Não foi possível apagar.', 'erro'); return; }
                        notas = notas.filter(function (x) { return x.id !== n.id; });
                        renderizarNotas();
                    }).catch(function () { siteAvisar('Falha de conexão. Tente de novo.', 'erro'); });
                });
            }

            // Primeira letra digitada numa nota nova marca o minuto do vídeo
            // (se ele já começou; antes do play, a nota fica sem minuto).
            notaTexto.addEventListener('input', function () {
                if (notaMomentoValor === null && !semMinuto && player && player.currentTime > 0 && notaTexto.value.trim()) {
                    marcarMomentoForm(minutoAtual());
                }
                atualizarContador(notaTexto, notaContador);
            });
            // Nota escrita e não salva: o navegador pergunta antes de sair
            // (inclusive na contagem para a próxima aula).
            window.addEventListener('beforeunload', function (e) {
                var editando = notasLista.querySelector('.aula-nota.is-editando textarea');
                if (notaTexto.value.trim() || (editando && editando.value !== editando.dataset.original)) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
            if (notaMarcar) {
                notaMarcar.addEventListener('click', function () {
                    semMinuto = false;
                    marcarMomentoForm(minutoAtual());
                    notaTexto.focus();
                });
                document.getElementById('notaMomentoRemover').addEventListener('click', function () {
                    semMinuto = true;
                    marcarMomentoForm(null);
                });
            }
            notaTexto.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    notaForm.requestSubmit ? notaForm.requestSubmit() : notaSalvar.click();
                }
            });

            notaForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!notaTexto.value.trim()) { notaErro.textContent = 'Escreva a nota antes de salvar.'; notaTexto.focus(); return; }
                notaSalvar.disabled = true;
                notaErro.textContent = '';
                enviarNota({ acao: 'criar', idct_id: conteudoId, texto: notaTexto.value, momento: notaMomentoValor })
                    .then(function (resp) {
                        if (!resp.ok) { notaErro.textContent = resp.erro || 'Não foi possível salvar a nota.'; return; }
                        notas.push(resp.nota);
                        notaTexto.value = '';
                        semMinuto = false;
                        marcarMomentoForm(null);
                        atualizarContador(notaTexto, notaContador);
                        renderizarNotas(resp.nota.id);
                    })
                    .catch(function () { notaErro.textContent = 'Falha de conexão. A nota não foi salva — tente de novo.'; })
                    .then(function () { notaSalvar.disabled = false; });
            });

            renderizarNotas();
        })();
    </script>
    <?php } ?>
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
