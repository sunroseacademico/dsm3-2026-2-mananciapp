<?php
// Destaques da home: as artes que passam no carrossel do topo da home do
// fiel (contribuição, novidades, avisos), junto com o curso em destaque.
// Funções em "DESTAQUES DA HOME" de php/conexao.php; tabela na seção 75 do
// bancosupabase.md. O carrossel é php/fiel/includes/home_carrossel.php.
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';
require_once __DIR__ . '/../fiel/includes/cursos_helpers.php';

auth_require(array('admin'));
// Ações que deram certo vão para o Registro de atividade (admin_log_ao_redirecionar()).
admin_log_ao_redirecionar($conn, 'destaques');

$mensagem = "";
$erro = "";
$hoje = site_hoje();
$admin_id = auth_resolve_fiel_id();
$disponivel = home_destaques_disponivel($conn);
$situacoes = array(
    'no_ar' => array('No ar', 'bg-success'),
    'agendada' => array('Agendada', 'bg-info text-dark'),
    'expirada' => array('Expirou', 'bg-dark'),
    'guardada' => array('Guardada', 'bg-secondary'),
);

// Destinos prontos do clique (o resto vai em "Outra página ou link").
$destinos = array('' => 'Sem clique (só a imagem)', 'contribuir.php' => 'Aba Contribuir (oferta geral)', 'missoes.php' => 'Aba Missões');
$oferta_geral_id = missoes_oferta_geral_id();
foreach (missoes_projetos($conn) as $p) {
    if ($p['situacao'] === 'aberto') {
        $destinos['missao.php?id=' . (int) $p['idmp_id']] = ((int) $p['idmp_id'] === $oferta_geral_id ? 'Oferta geral: ' : 'Missão: ') . $p['idmp_titulo'];
    }
}
$destinos += array(
    'cursos.php' => 'Cursos',
    'ao_vivo.php' => 'Ao Vivo',
    'devocional.php' => 'Devocional',
    'testemunhos.php' => 'Testemunhos',
    'desafios.php' => 'Desafios',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_curso') {
    // Não depende da tabela: só liga/desliga o slide do curso.
    if (site_config_salvar($conn, 'home_destaque_curso', isset($_POST['curso']) ? '1' : '0')) {
        $_SESSION['destaques_ok'] = 'Preferência do curso em destaque salva.';
        header('Location: admin_destaques_home.php');
        exit;
    }
    $erro = 'Não foi possível salvar. Confira se a seção 43 do bancosupabase.md já rodou.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $disponivel) {
    $acao = site_entrada_texto($_POST['acao'] ?? '', false);
    $destaque_id = (int) ($_POST['idhd_id'] ?? 0);
    $anterior = null;
    if ($destaque_id > 0) {
        $res = db_query($conn, "SELECT * FROM id_home_destaque WHERE idhd_id = ?", array($destaque_id));
        $anterior = $res ? mysqli_fetch_assoc($res) : null;
    }

    if ($acao === 'salvar') {
        $titulo = trim(site_entrada_texto($_POST['titulo'] ?? ''));
        $destino = site_entrada_texto($_POST['destino'] ?? '');
        $link = $destino === 'outro' ? trim(site_entrada_texto($_POST['link_outro'] ?? '')) : $destino;
        $inicio = trim(site_entrada_texto($_POST['inicio'] ?? ''));
        $fim = trim(site_entrada_texto($_POST['fim'] ?? ''));
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $remover_celular = isset($_POST['remover_celular']);
        $data_ok = function ($data) {
            return $data === '' || (($d = DateTime::createFromFormat('Y-m-d', $data)) && $d->format('Y-m-d') === $data);
        };

        if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 120) {
            $erro = "Informe o nome da arte (de 3 a 120 caracteres). Ele também é lido por quem usa leitor de tela.";
        } elseif ($link !== '' && (!home_destaque_link_valido($link) || mb_strlen($link) > 255)) {
            $erro = "Destino inválido: use uma página do app (ex.: contribuir.php) ou um link que comece com https://.";
        } elseif (!$data_ok($inicio) || !$data_ok($fim)) {
            $erro = "Data inválida.";
        } elseif ($inicio !== '' && $fim !== '' && $inicio > $fim) {
            $erro = "A data de início precisa ser antes do fim.";
        } elseif ($destaque_id > 0 && !$anterior) {
            $erro = "Arte não encontrada.";
        } elseif ($destaque_id === 0 && (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] === UPLOAD_ERR_NO_FILE)) {
            $erro = "Escolha a imagem da arte.";
        } else {
            $imagem = site_salvar_imagem_upload('imagem', 'destaques', $erro);
            $celular = $imagem === false ? false : site_salvar_imagem_upload('imagem_celular', 'destaques', $erro);
            if ($imagem === false || $celular === false) {
                // Uma subiu e a outra falhou: não deixa a que subiu sobrando.
                if ($imagem) {
                    site_excluir_arquivo_r2_orfao($imagem);
                }
            } else {
                $celular_final = $celular ?: ($remover_celular ? null : ($anterior['idhd_imagem_celular_url'] ?? null));
                if ($destaque_id > 0) {
                    $ok = db_query($conn, "
                        UPDATE id_home_destaque SET
                            idhd_titulo = ?, idhd_imagem_url = ?, idhd_imagem_celular_url = ?, idhd_link = ?,
                            idhd_ativo = ?, idhd_inicio = ?, idhd_fim = ?, idhd_atualizado_em = NOW()
                        WHERE idhd_id = ?
                    ", array($titulo, $imagem ?: $anterior['idhd_imagem_url'], $celular_final, $link !== '' ? $link : null, $ativo, $inicio !== '' ? $inicio : null, $fim !== '' ? $fim : null, $destaque_id));
                } else {
                    // Nova arte entra no fim do carrossel.
                    $ok = db_query($conn, "
                        INSERT INTO id_home_destaque (idhd_titulo, idhd_imagem_url, idhd_imagem_celular_url, idhd_link, idhd_ativo, idhd_inicio, idhd_fim, idhd_ordem, idhd_criado_por)
                        VALUES (?, ?, ?, ?, ?, ?, ?, (SELECT COALESCE(MAX(idhd_ordem), 0) + 1 FROM id_home_destaque), ?)
                    ", array($titulo, $imagem, $celular_final, $link !== '' ? $link : null, $ativo, $inicio !== '' ? $inicio : null, $fim !== '' ? $fim : null, $admin_id));
                }
                if ($ok) {
                    // Imagens trocadas ou removidas ficam sem uso no R2.
                    if ($anterior) {
                        if ($imagem) {
                            site_excluir_arquivo_r2_orfao($anterior['idhd_imagem_url']);
                        }
                        if (!empty($anterior['idhd_imagem_celular_url']) && $anterior['idhd_imagem_celular_url'] !== $celular_final) {
                            site_excluir_arquivo_r2_orfao($anterior['idhd_imagem_celular_url']);
                        }
                    }
                    $_SESSION['destaques_ok'] = 'Arte salva.';
                    header('Location: admin_destaques_home.php');
                    exit;
                }
                foreach (array($imagem, $celular) as $nova) {
                    if ($nova) {
                        site_excluir_arquivo_r2_orfao($nova);
                    }
                }
                $erro = site_erro_banco('Não foi possível salvar a arte.');
            }
        }
    } elseif ($acao === 'excluir') {
        if (!$anterior) {
            $erro = "Arte não encontrada.";
        } elseif (db_query($conn, "DELETE FROM id_home_destaque WHERE idhd_id = ?", array($destaque_id))) {
            site_excluir_arquivo_r2_orfao($anterior['idhd_imagem_url']);
            if (!empty($anterior['idhd_imagem_celular_url'])) {
                site_excluir_arquivo_r2_orfao($anterior['idhd_imagem_celular_url']);
            }
            $_SESSION['destaques_ok'] = 'Arte excluída.';
            header('Location: admin_destaques_home.php');
            exit;
        } else {
            $erro = site_erro_banco('Não foi possível excluir a arte.');
        }
    } elseif ($acao === 'mover') {
        // Troca de lugar com a vizinha e renumera 1, 2, 3… (a ordem salva
        // pode ter buracos depois de exclusões).
        $ids = array_map(function ($d) { return (int) $d['idhd_id']; }, home_destaques($conn, true));
        $pos = array_search($destaque_id, $ids, true);
        $alvo = $pos === false ? false : $pos + (($_POST['direcao'] ?? '') === 'cima' ? -1 : 1);
        if ($pos !== false && $alvo >= 0 && $alvo < count($ids)) {
            list($ids[$pos], $ids[$alvo]) = array($ids[$alvo], $ids[$pos]);
            foreach ($ids as $i => $id) {
                db_query($conn, "UPDATE id_home_destaque SET idhd_ordem = ? WHERE idhd_id = ?", array($i + 1, $id));
            }
        }
        header('Location: admin_destaques_home.php');
        exit;
    }
}
if (!empty($_SESSION['destaques_ok'])) {
    $mensagem = $_SESSION['destaques_ok'];
    unset($_SESSION['destaques_ok']);
}

$artes = $disponivel ? home_destaques($conn, true) : array();
$editar_id = (int) ($_GET['editar'] ?? 0);
$editando = null;
foreach ($artes as $d) {
    if ((int) $d['idhd_id'] === $editar_id) {
        $editando = $d;
    }
}
$link_atual = (string) ($editando['idhd_link'] ?? '');
$destino_outro = $link_atual !== '' && !isset($destinos[$link_atual]);
$curso_ligado = home_destaque_curso_ligado();
$curso_atual = curso_destaque($conn);
$no_ar = count(array_filter($artes, function ($d) { return $d['situacao'] === 'no_ar'; }));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Destaques da home</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'destaques', 'Destaques da home', 'fa-images'); ?>

    <?php if ($mensagem !== "") { ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
    <?php } ?>
    <?php if ($erro !== "") { ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
    <?php } ?>

    <p class="text-muted">O topo da tela inicial do fiel é um carrossel: o curso em destaque e as artes abaixo passam sozinhos a cada 7 segundos, e o fiel pode arrastar ou usar as setas. Use para contribuição, novidades, eventos e avisos.</p>

    <div class="card mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color);">
        <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
            <h5 class="mb-0"><i class="fas fa-star me-2" style="color:#60a5fa;"></i>Curso em destaque</h5>
            <small class="text-muted">Escolhido sozinho (o curso marcado como destaque ou o mais recente). Entra como o primeiro slide.</small>
        </div>
        <div class="card-body">
            <form method="POST" class="d-flex flex-wrap align-items-center gap-3">
                <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="salvar_curso">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" name="curso" id="curso" <?php echo $curso_ligado ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="curso">Mostrar o curso em destaque no carrossel<?php echo $curso_atual ? ' (hoje: <strong>' . htmlspecialchars($curso_atual['IDC_TITULO']) . '</strong>)' : ''; ?></label>
                </div>
                <button type="submit" class="btn btn-sm btn-salvar"><i class="fas fa-save me-1"></i>Salvar</button>
            </form>
            <?php if (!$curso_ligado && $no_ar === 0) { ?>
                <small class="text-muted d-block mt-2">Sem curso e sem artes no ar, a home mostra só a mensagem de boas-vindas.</small>
            <?php } ?>
        </div>
    </div>

    <?php if (!$disponivel) { ?>
        <div class="alert alert-danger">A tabela das artes ainda não existe no banco. Rode a seção 75 do <code>database/bancosupabase.md</code> no SQL Editor do Supabase. Até lá, a home mostra só o curso em destaque.</div>
    <?php } else { ?>

    <div class="card mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color);">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
            <div>
                <h5 class="mb-0"><i class="fas fa-images me-2" style="color:#60a5fa;"></i>Artes do carrossel</h5>
                <small class="text-muted">Na ordem em que aparecem, depois do curso. <?php echo $no_ar === 1 ? '1 no ar agora.' : $no_ar . ' no ar agora.'; ?></small>
            </div>
            <?php if ($editando) { ?><a href="admin_destaques_home.php#formArte" class="btn btn-sm btn-outline-secondary"><i class="fas fa-plus me-1"></i>Nova arte</a><?php } ?>
        </div>
        <div class="card-body p-0">
            <?php if (!$artes) { ?>
                <p class="text-muted p-3 mb-0">Nenhuma arte ainda. Envie a primeira abaixo.</p>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr><th class="ps-3">Ordem</th><th>Arte</th><th>Situação</th><th>Período</th><th>Ao clicar</th><th class="pe-3">Ações</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($artes as $i => $d) {
                                $s = $situacoes[$d['situacao']];
                                $miniatura = home_destaque_imagem_url($d['idhd_imagem_url']);
                                $link = (string) ($d['idhd_link'] ?? '');
                            ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="d-flex gap-1">
                                            <?php foreach (array('cima' => array('fa-arrow-up', 'Subir', $i > 0), 'baixo' => array('fa-arrow-down', 'Descer', $i < count($artes) - 1)) as $direcao => $b) { ?>
                                                <form method="POST">
                                                    <?php echo csrf_campo(); ?>
                                                    <input type="hidden" name="acao" value="mover">
                                                    <input type="hidden" name="idhd_id" value="<?php echo (int) $d['idhd_id']; ?>">
                                                    <input type="hidden" name="direcao" value="<?php echo $direcao; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?php echo $b[1]; ?>" aria-label="<?php echo $b[1]; ?>"<?php echo $b[2] ? '' : ' disabled'; ?>><i class="fas <?php echo $b[0]; ?>"></i></button>
                                                </form>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($miniatura) { ?><img src="<?php echo htmlspecialchars($miniatura); ?>" alt="" style="width:104px;height:40px;object-fit:cover;border-radius:6px;"><?php } ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($d['idhd_titulo']); ?></strong>
                                                <?php if (!empty($d['idhd_imagem_celular_url'])) { ?><br><small class="text-muted"><i class="fas fa-mobile-screen me-1"></i>com arte de celular</small><?php } ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge <?php echo $s[1]; ?>"><?php echo $s[0]; ?></span></td>
                                    <td>
                                        <?php if (empty($d['idhd_inicio']) && empty($d['idhd_fim'])) { ?>
                                            <small class="text-muted">Sempre</small>
                                        <?php } else { ?>
                                            <small><?php echo !empty($d['idhd_inicio']) ? date('d/m/Y', strtotime($d['idhd_inicio'])) : '…'; ?> a <?php echo !empty($d['idhd_fim']) ? date('d/m/Y', strtotime($d['idhd_fim'])) : '…'; ?></small>
                                        <?php } ?>
                                    </td>
                                    <td><small><?php echo $link === '' ? '<span class="text-muted">—</span>' : htmlspecialchars($destinos[$link] ?? $link); ?></small></td>
                                    <td class="pe-3">
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="admin_destaques_home.php?editar=<?php echo (int) $d['idhd_id']; ?>#formArte" class="btn btn-sm btn-salvar"><i class="fas fa-pen me-1"></i>Editar</a>
                                            <form method="POST" data-confirmar="Excluir esta arte? Não dá para desfazer.">
                                                <?php echo csrf_campo(); ?>
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="idhd_id" value="<?php echo (int) $d['idhd_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir" aria-label="Excluir"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>

    <div class="card mb-4" id="formArte" style="background: var(--bg-light); border: 1px solid var(--border-color);">
        <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
            <h5 class="mb-0"><i class="fas <?php echo $editando ? 'fa-pen' : 'fa-plus'; ?> me-2" style="color:#60a5fa;"></i><?php echo $editando ? 'Editar: ' . htmlspecialchars($editando['idhd_titulo']) : 'Nova arte'; ?></h5>
            <small class="text-muted">A arte vai inteira, com o texto já desenhado nela. Computador: larga, cerca de <strong>2000 × 770 px</strong>. Celular (opcional): quadrada, cerca de <strong>1080 × 1080 px</strong>; sem ela, o celular mostra a do computador inteira, menor. Prefira JPG ou WebP de até 500 KB: carrega rápido no celular.</small>
        </div>
        <div class="card-body">
            <?php if ($editando) {
                $previa = home_destaque_imagem_url($editando['idhd_imagem_url']);
                $previa_celular = home_destaque_imagem_url($editando['idhd_imagem_celular_url'] ?? '');
            ?>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <?php if ($previa) { ?><figure class="mb-0"><img src="<?php echo htmlspecialchars($previa); ?>" alt="" style="max-width:360px;width:100%;border-radius:8px;"><figcaption class="small text-muted">Computador</figcaption></figure><?php } ?>
                    <?php if ($previa_celular) { ?><figure class="mb-0"><img src="<?php echo htmlspecialchars($previa_celular); ?>" alt="" style="width:140px;border-radius:8px;"><figcaption class="small text-muted">Celular</figcaption></figure><?php } ?>
                </div>
            <?php } ?>
            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <?php echo csrf_campo(); ?>
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="idhd_id" value="<?php echo (int) ($editando['idhd_id'] ?? 0); ?>">
                <div class="col-md-8">
                    <label class="form-label" for="titulo">Nome da arte</label>
                    <input type="text" name="titulo" id="titulo" class="form-control custom-input" maxlength="120" required placeholder="Ex.: Ajude o site da igreja a crescer" value="<?php echo htmlspecialchars((string) ($editando['idhd_titulo'] ?? '')); ?>">
                    <small class="text-muted">Escreva o que a arte diz: é o que o leitor de tela lê para quem não enxerga a imagem.</small>
                </div>
                <div class="col-md-4 d-flex align-items-center">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="ativo" id="ativo" <?php echo !$editando || (int) $editando['idhd_ativo'] === 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ativo">Mostrar no carrossel</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="imagem">Arte do computador<?php echo $editando ? ' (trocar)' : ''; ?></label>
                    <input type="file" name="imagem" id="imagem" class="form-control custom-input" accept="image/jpeg,image/png,image/webp,image/gif"<?php echo $editando ? '' : ' required'; ?>>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="imagem_celular">Arte do celular (opcional<?php echo !empty($editando['idhd_imagem_celular_url']) ? ', trocar' : ''; ?>)</label>
                    <input type="file" name="imagem_celular" id="imagem_celular" class="form-control custom-input" accept="image/jpeg,image/png,image/webp,image/gif">
                    <?php if (!empty($editando['idhd_imagem_celular_url'])) { ?>
                        <div class="form-check mt-1">
                            <input type="checkbox" class="form-check-input" name="remover_celular" id="remover_celular">
                            <label class="form-check-label small" for="remover_celular">Remover a arte do celular</label>
                        </div>
                    <?php } ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="destino">Ao clicar, levar para</label>
                    <select name="destino" id="destino" class="form-select custom-input">
                        <?php foreach ($destinos as $valor => $rotulo) { ?><option value="<?php echo htmlspecialchars($valor); ?>" <?php echo !$destino_outro && $link_atual === $valor ? 'selected' : ''; ?>><?php echo htmlspecialchars($rotulo); ?></option><?php } ?>
                        <option value="outro" <?php echo $destino_outro ? 'selected' : ''; ?>>Outra página ou link…</option>
                    </select>
                </div>
                <div class="col-md-6" id="destino_outro_campo"<?php echo $destino_outro ? '' : ' hidden'; ?>>
                    <label class="form-label" for="link_outro">Página do app ou link</label>
                    <input type="text" name="link_outro" id="link_outro" class="form-control custom-input" maxlength="255" placeholder="ex.: curso.php?id=4 ou https://..." value="<?php echo $destino_outro ? htmlspecialchars($link_atual) : ''; ?>">
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="form-label" for="inicio">Aparece a partir de (opcional)</label>
                    <input type="date" name="inicio" id="inicio" class="form-control custom-input" value="<?php echo htmlspecialchars((string) ($editando['idhd_inicio'] ?? '')); ?>">
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="form-label" for="fim">Até (opcional)</label>
                    <input type="date" name="fim" id="fim" class="form-control custom-input" value="<?php echo htmlspecialchars((string) ($editando['idhd_fim'] ?? '')); ?>">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-salvar"><i class="fas fa-save me-1"></i><?php echo $editando ? 'Salvar alterações' : 'Adicionar arte'; ?></button>
                    <?php if ($editando) { ?><a href="admin_destaques_home.php" class="btn btn-outline-secondary">Cancelar</a><?php } ?>
                </div>
            </form>
            <script nonce="<?php echo site_csp_nonce(); ?>">
                (function () {
                    var destino = document.getElementById('destino');
                    destino.addEventListener('change', function () {
                        document.getElementById('destino_outro_campo').hidden = destino.value !== 'outro';
                    });
                })();
            </script>
        </div>
    </div>

    <?php } ?>

    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
<script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
