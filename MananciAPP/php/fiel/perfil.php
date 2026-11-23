<?php
include("../conexao.php");
require_once __DIR__ . '/includes/cursos_helpers.php';
auth_require();

// Perfil do fiel, para ver (como numa rede social): capa, foto, nome e
// selos, "Sobre", números, medalhas e certificados. Tudo o que se edita
// fica em Configurações (configuracoes.php) — aqui só a foto, que se troca
// direto no avatar.

$fiel_id_perfil = (int) auth_resolve_fiel_id();
$pode_foto = fiel_pode('foto');
$tem_foto = fiel_foto_disponivel($conn);
$acao_post = $_SERVER['REQUEST_METHOD'] === 'POST' ? site_entrada_texto($_POST['acao'] ?? '', false) : '';
$foto_erro = '';
$foto_mensagem = '';

if ($tem_foto && $pode_foto && ($acao_post === 'foto' || $acao_post === 'foto_remover')) {
    if (!csrf_validar()) {
        $foto_erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } elseif ($fiel_id_perfil > 0 && $acao_post === 'foto') {
        $foto_nova = fiel_foto_validar($_POST['foto_jpeg'] ?? '');
        if ($foto_nova['erro']) {
            $foto_erro = $foto_nova['erro'];
        } elseif (db_query($conn, "
            INSERT INTO id_fiel_foto (idf_id, idff_dados, idff_atualizado_em)
            VALUES (?, ?, NOW())
            ON CONFLICT (idf_id) DO UPDATE SET idff_dados = EXCLUDED.idff_dados, idff_atualizado_em = NOW()
        ", array($fiel_id_perfil, $foto_nova['base64']))) {
            fiel_foto_versao_esquecer(); // a navbar guarda a versão da foto na sessão
            $_SESSION['perfil_foto_ok'] = 'Foto atualizada.';
            header('Location: perfil.php');
            exit;
        } else {
            $foto_erro = 'Não foi possível salvar a foto. Tente novamente.';
        }
    } elseif ($fiel_id_perfil > 0) {
        mysqli_query($conn, "DELETE FROM id_fiel_foto WHERE idf_id = $fiel_id_perfil");
        fiel_foto_versao_esquecer();
        $_SESSION['perfil_foto_ok'] = 'Foto removida.';
        header('Location: perfil.php');
        exit;
    }
}
// Vitrine (seção 81): a estrela numa medalha conquistada põe ou tira ela
// do destaque no topo do perfil (até 3; a primeira vira o selo nos comentários).
$vitrine_erro = '';
if ($acao_post === 'vitrine') {
    if (!csrf_validar()) {
        $vitrine_erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } elseif (($vitrine_erro = fiel_vitrine_alternar($conn, $fiel_id_perfil, (int) ($_POST['idbadge_id'] ?? 0))) === '') {
        header('Location: perfil.php#medalhas');
        exit;
    }
}

if (!empty($_SESSION['perfil_foto_ok'])) {
    $foto_mensagem = $_SESSION['perfil_foto_ok'];
    unset($_SESSION['perfil_foto_ok']);
}

// Dados do fiel, pelo id da sessão (não pelo e-mail: no Postgres o "="
// diferencia maiúsculas).
$tem_perfil = fiel_perfil_disponivel($conn);
$tem_funcoes = funcoes_igreja_disponivel($conn);
$tem_membro = fiel_membro_disponivel($conn);
$tem_filiais = filiais_disponivel($conn);
$colunas = array('f.IDF_NOME', 'f.IDF_CRIADO_EM', 'f.IDF_FUNCAO', 'f.IDF_FILIAL', "COALESCE(fl.IDL_NOME, '') AS IDF_FILIAL_EXIBICAO",
    $tem_filiais ? 'f.IDF_SEM_FILIAL' : '0 AS IDF_SEM_FILIAL');
if ($tem_perfil) {
    array_push($colunas, 'f.IDF_NASCIMENTO', 'f.IDF_CIDADE', 'f.IDF_UF');
}
if ($tem_funcoes) {
    $colunas[] = 'f.IDF_FUNCAO_ID';
}
if ($tem_membro) {
    array_push($colunas, 'f.IDF_MEMBRO', 'f.IDF_MEMBRO_DESDE');
}
$res_usuario = $fiel_id_perfil > 0 ? mysqli_query($conn, "SELECT " . implode(', ', $colunas) . " FROM ID_FIEL f LEFT JOIN ID_FILIAL fl ON fl.IDL_ID = f.IDF_FILIAL_ID WHERE f.IDF_ID = $fiel_id_perfil LIMIT 1") : false;
$usuario = ($res_usuario ? mysqli_fetch_assoc($res_usuario) : null) ?: array();

$nascimento = !empty($usuario['IDF_NASCIMENTO']) ? substr((string) $usuario['IDF_NASCIMENTO'], 0, 10) : null;
$aniversario_hoje = fiel_aniversario_hoje($nascimento);
$foto_url = fiel_foto_url($conn, $fiel_id_perfil);

// Função na igreja (T12): definida pelo admin. Com a lista, o texto livre
// antigo (sem correspondência na lista) não conta como função.
$funcao_exibir = (string) ($usuario['IDF_FUNCAO'] ?? '');
if ($tem_funcoes && empty($usuario['IDF_FUNCAO_ID'])) {
    $funcao_exibir = '';
}
$membro_atual = $tem_membro && isset($usuario['IDF_MEMBRO']) && $usuario['IDF_MEMBRO'] !== null ? (int) $usuario['IDF_MEMBRO'] : null;
$membro_desde = $membro_atual === 1 ? ($usuario['IDF_MEMBRO_DESDE'] ?? null) : null;
$filial_exibicao = fiel_filial_rotulo($usuario['IDF_FILIAL_EXIBICAO'] ?? '', $usuario['IDF_SEM_FILIAL'] ?? 0, $usuario['IDF_FILIAL'] ?? '');
$cidade_uf = trim(implode(' / ', array_filter(array((string) ($usuario['IDF_CIDADE'] ?? ''), trim((string) ($usuario['IDF_UF'] ?? ''))))));
$data_cadastro = !empty($usuario['IDF_CRIADO_EM']) ? date('d/m/Y', strtotime($usuario['IDF_CRIADO_EM'])) : '—';

$iniciais = '';
foreach (preg_split('/\s+/', trim((string) ($usuario['IDF_NOME'] ?? 'U'))) as $parte) {
    if ($parte !== '') {
        $iniciais .= mb_strtoupper(mb_substr($parte, 0, 1));
    }
    if (mb_strlen($iniciais) >= 2) {
        break;
    }
}
$iniciais = $iniciais !== '' ? $iniciais : 'U';

// "Perfil X% completo" (T13): cada campo que falta leva à seção certa das
// Configurações (a foto se troca aqui mesmo).
$completude = perfil_completude($conn, $fiel_id_perfil);
$completar_destino = array(
    'whatsapp' => 'configuracoes.php#dados-pessoais', 'nascimento' => 'configuracoes.php#dados-pessoais',
    'estado_civil' => 'configuracoes.php#dados-pessoais', 'tem_filhos' => 'configuracoes.php#dados-pessoais',
    'cidade' => 'configuracoes.php#dados-pessoais', 'membro' => 'configuracoes.php#igreja',
    'temas' => 'configuracoes.php#preferencias', 'foto' => 'foto',
);

// Medalhas (T14 e T30): concede antes do menu ser montado (aviso "Nova
// medalha!") — as de ofensiva e as da comunidade (ensino, resumos, oração).
$tem_medalhas = medalhas_disponivel($conn);
$medalhas_conquistadas = array();
$medalhas_progresso = array();
$ofensiva_perfil = array('atual' => 0, 'recorde' => 0);
if ($tem_medalhas) {
    $ofensiva_perfil = ofensiva_resumo($conn, $fiel_id_perfil);
    medalhas_avisar_novas(medalhas_conceder_ofensiva($conn, $fiel_id_perfil, $ofensiva_perfil['recorde']));
    medalhas_avisar_novas(medalhas_conceder_atividade($conn, $fiel_id_perfil));
    $medalhas_conquistadas = medalhas_do_fiel($conn, $fiel_id_perfil);
    $medalhas_progresso = medalhas_contagens($conn, $fiel_id_perfil);
}

$stats = perfil_estatisticas($conn, $fiel_id_perfil);

// Interesses (temas das preferências de conteúdo, T23).
$temas_escolhidos = array();
if (preferencias_conteudo_disponivel($conn)) {
    $prefs = preferencias_conteudo_ler($conn, $fiel_id_perfil);
    $temas_escolhidos = array_values(array_intersect_key(preferencias_temas_opcoes($conn), array_flip($prefs['temas'])));
}

// Últimos certificados (a lista completa fica em Certificados).
$res_certs = $fiel_id_perfil > 0 ? db_query($conn, "
    SELECT cert.IDCERT_ID, cert.IDCERT_DATA_CONCLUSAO, c.IDC_TITULO
    FROM ID_CERTIFICADO cert
    JOIN ID_CURSO c ON c.IDC_ID = cert.IDC_ID
    WHERE cert.IDF_ID = ?
    ORDER BY cert.IDCERT_DATA_CONCLUSAO DESC
    LIMIT 3
", array($fiel_id_perfil)) : false;
$certificados_recentes = $res_certs ? $res_certs->todas_as_linhas() : array();

$perfil_assinatura = assinatura_modulo_ativo() ? assinatura_do_fiel($conn, $fiel_id_perfil) : null;
$perfil_tem_acesso = assinatura_modulo_ativo() && assinatura_fiel_tem_acesso($conn, $fiel_id_perfil);

// Capa escolhida (recompensa dos Primeiros passos, seção 80) e quantas
// missões faltam, para o link do "Perfil X% completo".
$capa_classe = perfil_capas()[perfil_capa_do_fiel($conn, $fiel_id_perfil)][1];

// Vitrine (até 3 medalhas escolhidas) e raridade de cada medalha.
$vitrine_ids = $tem_medalhas ? fiel_vitrine($conn, $fiel_id_perfil) : array();
$vitrine_medalhas = array();
foreach ($vitrine_ids as $vitrine_id) {
    if (isset(medalhas_listar($conn)[$vitrine_id])) {
        $vitrine_medalhas[] = medalhas_listar($conn)[$vitrine_id];
    }
}
$medalhas_raridade = $tem_medalhas ? medalhas_raridade($conn) : array();
$pp_resumo = primeiros_passos_resumo(primeiros_passos($conn, $fiel_id_perfil));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Meu Perfil</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body style="transition: background 0.3s ease;">
    <?php $navbar_active = 'perfil'; include __DIR__ . '/includes/navbar_fiel.php'; ?>

    <main class="container py-4">
        <div class="perfil-pagina">
            <section class="perfil-topo">
                <div class="perfil-capa<?php echo $capa_classe !== '' ? ' ' . $capa_classe : ''; ?>" aria-hidden="true"></div>
                <div class="perfil-topo-corpo">
                    <div class="perfil-foto perfil-foto-grande">
                        <div class="streaming-profile-avatar">
                            <?php if ($foto_url) { ?>
                                <img src="<?php echo htmlspecialchars($foto_url); ?>" alt="Foto de perfil">
                            <?php } else { ?>
                                <?php echo htmlspecialchars($iniciais); ?>
                            <?php } ?>
                        </div>
                        <?php if ($tem_foto && $pode_foto) { ?>
                            <div class="perfil-foto-acoes dropdown">
                                <?php if ($foto_url) { ?>
                                    <button type="button" class="perfil-foto-botao" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Alterar foto de perfil" title="Alterar foto"><i class="fas fa-camera"></i></button>
                                    <ul class="dropdown-menu navbar-fiel-menu perfil-foto-menu">
                                        <li><button type="button" class="dropdown-item" data-foto-escolher><i class="fas fa-image me-2"></i>Trocar foto</button></li>
                                        <li><button type="submit" form="fotoRemoverForm" class="dropdown-item text-danger"><i class="fas fa-trash-alt me-2"></i>Remover foto</button></li>
                                    </ul>
                                <?php } else { ?>
                                    <button type="button" class="perfil-foto-botao" data-foto-escolher aria-label="Adicionar foto de perfil" title="Adicionar foto"><i class="fas fa-camera"></i></button>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="perfil-topo-info">
                        <div class="perfil-nome-linha">
                            <h1 class="perfil-nome"><?php echo htmlspecialchars($usuario['IDF_NOME'] ?? ''); ?></h1>
                            <?php if ($vitrine_medalhas) { ?>
                                <!-- Vitrine (seção 81): as medalhas que o fiel escolheu destacar. -->
                                <ul class="perfil-vitrine" aria-label="Medalhas em destaque">
                                    <?php foreach ($vitrine_medalhas as $vm) { ?>
                                        <li class="medalha-mini nivel-<?php echo $vm['nivel']; ?>" title="<?php echo htmlspecialchars($vm['nome'] . ' · ' . medalhas_niveis()[$vm['nivel']]); ?>">
                                            <i class="fas <?php echo $vm['icone']; ?>" aria-hidden="true"></i><span class="visually-hidden"><?php echo htmlspecialchars($vm['nome']); ?></span>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                        </div>
                        <?php if ($aniversario_hoje) { ?>
                            <p class="mb-2"><span class="perfil-aniversario"><i class="fas fa-cake-candles"></i>Feliz aniversário! Que Deus abençoe seu novo ano de vida.</span></p>
                        <?php } ?>
                        <?php
                            // Selos (T24): função, tempo de membresia e maior medalha.
                            $selos_perfil = fiel_selos($conn, $fiel_id_perfil);
                            if ($vitrine_medalhas) {
                                // A vitrine já mostra as medalhas ao lado do nome.
                                $selos_perfil = array_values(array_filter($selos_perfil, function ($s) { return $s['tipo'] !== 'medalha'; }));
                            }
                            if ($selos_perfil) { ?>
                            <div class="perfil-selos mb-2" aria-label="Selos">
                                <?php foreach ($selos_perfil as $selo) { ?>
                                    <span class="perfil-selo is-<?php echo $selo['tipo']; ?><?php echo isset($selo['nivel']) ? ' nivel-' . $selo['nivel'] : ''; ?>" <?php echo isset($selo['titulo']) ? 'title="' . htmlspecialchars($selo['titulo']) . '"' : ''; ?>>
                                        <i class="fas <?php echo htmlspecialchars($selo['icone']); ?>" aria-hidden="true"></i><?php echo htmlspecialchars($selo['texto']); ?>
                                    </span>
                                <?php } ?>
                            </div>
                        <?php } ?>
                        <p class="perfil-topo-meta mb-0">
                            <span><i class="fas fa-calendar-alt me-1"></i>Na plataforma desde <?php echo $data_cadastro; ?></span>
                            <?php if ($tem_medalhas) { ?>
                                <a href="ofensiva.php"><i class="fas fa-fire me-1" style="color:#fb923c;"></i><?php echo (int) $ofensiva_perfil['atual']; ?> dia<?php echo (int) $ofensiva_perfil['atual'] === 1 ? '' : 's'; ?> de ofensiva</a>
                            <?php } ?>
                        </p>
                        <?php if ($tem_foto) { ?>
                            <p class="perfil-foto-status mt-2 mb-0 <?php echo $foto_erro ? 'erro' : ($foto_mensagem ? 'ok' : ''); ?>" id="fotoStatus" role="status" <?php echo ($foto_erro || $foto_mensagem) ? '' : 'hidden'; ?>><?php echo htmlspecialchars($foto_erro ?: $foto_mensagem); ?></p>
                        <?php } ?>
                    </div>
                    <div class="perfil-topo-acoes">
                        <a href="configuracoes.php" class="btn btn-play btn-sm"><i class="fas fa-pen me-2"></i>Editar perfil</a>
                    </div>
                </div>

                <?php if ($completude['percentual'] < 100) { ?>
                    <div class="perfil-completo" id="perfil-completo">
                        <div class="perfil-completo-topo">
                            <span>Perfil <strong><?php echo (int) $completude['percentual']; ?>%</strong> completo</span>
                            <?php if ($pp_resumo['total'] > 0 && !$pp_resumo['completo']) { ?>
                                <a href="desafios.php#primeiros-passos" class="small">Primeiros passos: <?php echo $pp_resumo['feitos']; ?> de <?php echo $pp_resumo['total']; ?> <i class="fas fa-chevron-right ms-1" aria-hidden="true"></i></a>
                            <?php } ?>
                        </div>
                        <div class="progress-bar-custom" role="progressbar" aria-label="Perfil completo" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $completude['percentual']; ?>">
                            <div class="progress-bar-custom-fill" style="width: <?php echo (int) $completude['percentual']; ?>%;"></div>
                        </div>
                        <?php if (!empty($completude['faltando'])) { ?>
                            <div class="perfil-completo-faltando">
                                <span class="text-muted">Falta:</span>
                                <?php foreach ($completude['faltando'] as $chave_falta => $falta) {
                                    $classe_chip = 'perfil-completo-chip' . ($falta['obrigatorio'] ? ' obrigatorio' : '');
                                    $titulo_chip = $falta['obrigatorio'] ? 'Obrigatório' : 'Opcional';
                                    $rotulo_chip = htmlspecialchars($falta['rotulo']);
                                    $destino = $completar_destino[$chave_falta] ?? '';
                                    if ($destino === 'foto' && $tem_foto && $pode_foto) { ?>
                                        <button type="button" class="<?php echo $classe_chip; ?>" data-foto-escolher title="<?php echo $titulo_chip; ?>"><?php echo $rotulo_chip; ?></button>
                                    <?php } elseif ($destino !== '' && $destino !== 'foto') { ?>
                                        <a href="<?php echo $destino; ?>" class="<?php echo $classe_chip; ?>" title="<?php echo $titulo_chip; ?>"><?php echo $rotulo_chip; ?></a>
                                    <?php } else { ?>
                                        <span class="<?php echo $classe_chip; ?>" title="<?php echo $titulo_chip; ?>"><?php echo $rotulo_chip; ?></span>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </section>
            <?php if ($tem_foto) { ?>
                <form method="POST" action="perfil.php" id="fotoForm" hidden>
                    <?php echo csrf_campo(); ?>
                    <input type="hidden" name="acao" value="foto">
                    <input type="hidden" name="foto_jpeg" value="">
                </form>
                <form method="POST" action="perfil.php" id="fotoRemoverForm" hidden data-confirmar="Remover sua foto de perfil?">
                    <?php echo csrf_campo(); ?>
                    <input type="hidden" name="acao" value="foto_remover">
                </form>
                <input type="file" id="fotoArquivo" accept="image/*" hidden>
            <?php } ?>

            <div class="perfil-colunas">
                <aside class="perfil-coluna-lado">
                    <section class="streaming-info-card text-start mb-4">
                        <div class="d-flex justify-content-between align-items-baseline mb-3">
                            <h2 class="perfil-card-titulo mb-0">Sobre</h2>
                            <a href="configuracoes.php#dados-pessoais" class="small">Editar</a>
                        </div>
                        <ul class="perfil-sobre">
                            <li><i class="fas fa-briefcase" aria-hidden="true"></i><span><?php echo $funcao_exibir !== '' ? htmlspecialchars($funcao_exibir) : '<span class="perfil-dados-vazio">Função a definir pela igreja</span>'; ?></span></li>
                            <?php if ($tem_membro) { ?>
                                <li><i class="fas fa-church" aria-hidden="true"></i><span>
                                    <?php if ($membro_atual === 1 && $membro_desde) { ?>
                                        Membro desde <?php echo htmlspecialchars(fiel_membro_desde_texto($membro_desde)); ?> <small class="text-muted">· há <?php echo htmlspecialchars(fiel_tempo_membresia($membro_desde)); ?></small>
                                    <?php } elseif ($membro_atual === 0) { ?>
                                        Não membro
                                    <?php } else { ?>
                                        <a href="configuracoes.php#igreja">Conte se você é membro da igreja</a>
                                    <?php } ?>
                                </span></li>
                            <?php } ?>
                            <?php if ($filial_exibicao !== '') { ?>
                                <li><i class="fas fa-map-location-dot" aria-hidden="true"></i><span><?php echo htmlspecialchars($filial_exibicao); ?></span></li>
                            <?php } ?>
                            <?php if ($cidade_uf !== '') { ?>
                                <li><i class="fas fa-house" aria-hidden="true"></i><span>Mora em <?php echo htmlspecialchars($cidade_uf); ?></span></li>
                            <?php } ?>
                            <?php if ($nascimento) { ?>
                                <li><i class="fas fa-cake-candles" aria-hidden="true"></i><span>Aniversário em <?php echo (int) substr($nascimento, 8, 2) . ' de ' . fiel_membro_meses()[(int) substr($nascimento, 5, 2)]; ?></span></li>
                            <?php } ?>
                            <?php if (assinatura_modulo_ativo()) { ?>
                                <li><i class="fas fa-crown" aria-hidden="true" style="color:#fbbf24;"></i><span>
                                    <?php if ($perfil_tem_acesso) { ?>
                                        Assinante<?php echo !empty($perfil_assinatura['idp_nome']) ? ' · ' . htmlspecialchars($perfil_assinatura['idp_nome']) : ''; ?>
                                    <?php } else { ?>
                                        <a href="assinar.php">Conheça os planos</a>
                                    <?php } ?>
                                </span></li>
                            <?php } ?>
                        </ul>
                    </section>

                    <section class="streaming-info-card text-start mb-4">
                        <div class="d-flex justify-content-between align-items-baseline mb-3">
                            <h2 class="perfil-card-titulo mb-0">Interesses</h2>
                            <a href="configuracoes.php#preferencias" class="small">Editar</a>
                        </div>
                        <?php if ($temas_escolhidos) { ?>
                            <div class="perfil-interesses">
                                <?php foreach ($temas_escolhidos as $tema) { ?>
                                    <span><i class="fas <?php echo htmlspecialchars(categoria_icone($tema)); ?>" aria-hidden="true"></i><?php echo htmlspecialchars($tema); ?></span>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <p class="text-muted small mb-0">Escolha os temas que você gosta de estudar e receba sugestões melhores. <a href="configuracoes.php#preferencias">Escolher temas</a></p>
                        <?php } ?>
                    </section>
                </aside>

                <div class="perfil-coluna-principal">
                    <div class="perfil-numeros mb-4">
                        <div class="streaming-info-card">
                            <div class="streaming-info-value"><?php echo (int) $stats['cursos_iniciados']; ?></div>
                            <div class="streaming-info-label">Cursos iniciados</div>
                        </div>
                        <div class="streaming-info-card">
                            <div class="streaming-info-value"><?php echo (int) $stats['cursos_concluidos']; ?></div>
                            <div class="streaming-info-label">Cursos concluídos</div>
                        </div>
                        <div class="streaming-info-card">
                            <div class="streaming-info-value"><?php echo number_format($stats['horas_assistidas'], 1, ',', '.'); ?>h</div>
                            <div class="streaming-info-label">Horas assistidas</div>
                        </div>
                        <div class="streaming-info-card">
                            <div class="streaming-info-value"><?php echo (int) $stats['certificados']; ?></div>
                            <div class="streaming-info-label">Certificados</div>
                        </div>
                    </div>

                    <?php if ($tem_medalhas && medalhas_listar($conn)) { ?>
                        <section class="streaming-info-card text-start mb-4" id="medalhas">
                            <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-2 mb-1">
                                <h2 class="perfil-card-titulo mb-0"><i class="fas fa-medal me-2" style="color:#facc15;"></i>Medalhas</h2>
                                <small class="text-muted"><?php echo count($medalhas_conquistadas); ?> de <?php echo count(medalhas_listar($conn)); ?> conquistadas · <a href="desafios.php#medalhas">desafios que valem medalha</a></small>
                            </div>
                            <?php if ($vitrine_erro !== '') { ?>
                                <div class="auth-erro mt-2" role="alert"><?php echo htmlspecialchars($vitrine_erro); ?></div>
                            <?php } ?>
                            <?php if ($medalhas_conquistadas) {
                                // Só as conquistadas; as que faltam ficam em Desafios. A
                                // estrela põe a medalha na vitrine (topo do perfil).
                                $medalhas_grade = array('catalogo' => medalhas_listar($conn), 'conquistadas' => $medalhas_conquistadas, 'ofensiva_atual' => $ofensiva_perfil['atual'], 'progresso' => $medalhas_progresso, 'modo' => 'resumo', 'raridade' => $medalhas_raridade);
                                if (fiel_vitrine_disponivel($conn)) {
                                    $medalhas_grade['vitrine'] = $vitrine_ids;
                                }
                            ?>
                                <?php if (fiel_vitrine_disponivel($conn) && !$vitrine_ids) { ?>
                                    <p class="text-muted small mt-2 mb-0"><i class="far fa-star me-1"></i>Toque na estrela de uma medalha para exibi-la ao lado do seu nome (até <?php echo FIEL_VITRINE_MAX; ?>).</p>
                                <?php } ?>
                                <?php include __DIR__ . '/includes/medalhas_grade.php'; ?>
                            <?php } else { ?>
                                <p class="text-muted small mt-2 mb-0">Você ainda não tem medalhas. Comece pelos <a href="desafios.php#primeiros-passos">Primeiros passos</a> ou veja os <a href="desafios.php#medalhas">desafios que valem medalha</a>.</p>
                            <?php } ?>
                        </section>
                    <?php } ?>

                    <section class="streaming-info-card text-start mb-4">
                        <h2 class="perfil-card-titulo mb-3"><i class="fas fa-chart-line me-2" style="color: var(--primary-light);"></i>Progresso nos estudos</h2>
                        <div class="progress-bar-custom" style="height: 10px;">
                            <div class="progress-bar-custom-fill" style="width: <?php echo $stats['progresso_geral']; ?>%;"></div>
                        </div>
                        <small class="text-muted d-block mt-2"><?php echo $stats['progresso_geral']; ?>% de conclusão média nos cursos em que você está matriculado · <a href="meu_progresso.php">ver meu progresso</a></small>
                    </section>

                    <section class="streaming-info-card text-start mb-4">
                        <div class="d-flex justify-content-between align-items-baseline mb-3">
                            <h2 class="perfil-card-titulo mb-0"><i class="fas fa-certificate me-2" style="color: var(--primary-light);"></i>Certificados</h2>
                            <?php if ($certificados_recentes) { ?><a href="Certificados/certificados.php" class="small">ver todos</a><?php } ?>
                        </div>
                        <?php if ($certificados_recentes) { ?>
                            <ul class="perfil-certificados">
                                <?php foreach ($certificados_recentes as $cert) { ?>
                                    <li>
                                        <a href="Certificados/certificado_view.php?id=<?php echo (int) $cert['IDCERT_ID']; ?>" target="_blank" rel="noopener">
                                            <i class="fas fa-award" aria-hidden="true"></i>
                                            <span><?php echo htmlspecialchars($cert['IDC_TITULO']); ?><small><?php echo date('d/m/Y', strtotime($cert['IDCERT_DATA_CONCLUSAO'])); ?></small></span>
                                        </a>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } else { ?>
                            <p class="text-muted small mb-0">Conclua um curso para ganhar o seu primeiro certificado. <a href="cursos.php">Ver cursos</a></p>
                        <?php } ?>
                    </section>
                </div>
            </div>
        </div>
    </main>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        // Foto de perfil: o navegador recorta no centro (quadrado), reduz para
        // <?php echo FIEL_FOTO_LADO; ?>px e converte para JPEG antes de enviar. O servidor não
        // tem como redimensionar e foto de celular passaria do limite de upload.
        (function () {
            var arquivo = document.getElementById('fotoArquivo');
            var form = document.getElementById('fotoForm');
            var status = document.getElementById('fotoStatus');
            if (!arquivo || !form || !status) return;
            var campo = form.querySelector('[name="foto_jpeg"]');
            var LADO = <?php echo (int) FIEL_FOTO_LADO; ?>;
            var MAX_BYTES = <?php echo (int) FIEL_FOTO_MAX_BYTES; ?>;

            function avisar(texto, tipo) {
                status.textContent = texto;
                status.className = 'perfil-foto-status mt-2 mb-0 ' + (tipo || '');
                status.hidden = !texto;
            }
            function bytes(dataUrl) {
                return Math.floor((dataUrl.split(',')[1] || '').length * 3 / 4);
            }

            document.querySelectorAll('[data-foto-escolher]').forEach(function (botao) {
                botao.addEventListener('click', function () { arquivo.click(); });
            });

            arquivo.addEventListener('change', function () {
                var foto = arquivo.files && arquivo.files[0];
                arquivo.value = '';
                if (!foto) return;
                if (!/^image\//.test(foto.type)) {
                    avisar('Escolha um arquivo de imagem (JPG ou PNG).', 'erro');
                    return;
                }
                avisar('Preparando a foto…');
                // Lê como data: URL (e não URL.createObjectURL): a CSP do
                // .htaccess só libera img-src 'self' data: https: — uma URL
                // blob: seria bloqueada e o onerror disparava sempre.
                var leitor = new FileReader();
                leitor.onerror = function () {
                    avisar('Não foi possível abrir essa imagem. Use uma foto JPG ou PNG.', 'erro');
                };
                leitor.onload = function () { carregar(leitor.result); };
                leitor.readAsDataURL(foto);
            });

            function carregar(url) {
                var img = new Image();
                img.onload = function () {
                    var lado = Math.min(img.naturalWidth, img.naturalHeight);
                    var canvas = document.createElement('canvas');
                    canvas.width = canvas.height = LADO;
                    var ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#ffffff'; // fundo para PNG transparente
                    ctx.fillRect(0, 0, LADO, LADO);
                    ctx.drawImage(img, (img.naturalWidth - lado) / 2, (img.naturalHeight - lado) / 2, lado, lado, 0, 0, LADO, LADO);
                    var dado = canvas.toDataURL('image/jpeg', 0.85);
                    if (bytes(dado) > MAX_BYTES) dado = canvas.toDataURL('image/jpeg', 0.7);
                    if (dado.indexOf('data:image/jpeg') !== 0 || bytes(dado) > MAX_BYTES) {
                        avisar('Não foi possível usar essa imagem. Tente outra.', 'erro');
                        return;
                    }
                    campo.value = dado;
                    avisar('Enviando a foto…');
                    form.submit();
                };
                img.onerror = function () {
                    avisar('Não foi possível abrir essa imagem. Use uma foto JPG ou PNG.', 'erro');
                };
                img.src = url;
            }
        })();
    </script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
