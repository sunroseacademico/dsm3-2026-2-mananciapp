<?php
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));

$mensagem = "";
$erro = "";

// ============================================================================
// AÇÕES (POST)
// ============================================================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && !csrf_validar()) {
    $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente de novo.";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "criar") {
    $titulo = site_entrada_texto($_POST["idtr_titulo"] ?? "");
    $youtube_input = site_entrada_texto($_POST["youtube_input"] ?? "");
    $youtube_id = transmissao_extrair_youtube_id($youtube_input);
    $audiencias_validas = array('TODOS', 'FIEIS', 'ADMINS');
    $audiencia = $_POST["idtr_audiencia"] ?? 'TODOS';
    if (!in_array($audiencia, $audiencias_validas, true)) {
        $audiencia = 'TODOS';
    }

    if ($titulo === "") {
        $erro = "Informe um título para a transmissão.";
    } elseif ($youtube_id === "") {
        $erro = "Não consegui reconhecer o link/ID do YouTube. Cole a URL completa da transmissão (ex: https://www.youtube.com/watch?v=XXXXXXXXXXX) ou só o ID do vídeo.";
    } else {
        // Só existe 1 transmissão de cada vez: enquanto houver uma cadastrada
        // (agendada, ao vivo ou encerrada), não dá pra criar outra — o admin
        // precisa excluir a atual antes. A checagem fica dentro do lock pra
        // dois admins criando ao mesmo tempo não passarem os dois.
        $ja_existe = false;
        $ok = transmissao_transacao_com_lock($conn, function ($conn) use ($titulo, $youtube_id, $audiencia, &$ja_existe) {
            $res_existe = mysqli_query($conn, "SELECT 1 FROM id_transmissao LIMIT 1");
            if (!$res_existe) {
                return false;
            }
            if (mysqli_num_rows($res_existe) > 0) {
                $ja_existe = true;
                return false;
            }

            if (!db_query($conn, "INSERT INTO id_transmissao (idtr_titulo, idtr_youtube_id, idtr_status, idtr_audiencia) VALUES (?, ?, 'AGENDADA', ?)", array($titulo, $youtube_id, $audiencia))) {
                return false;
            }

            // Avisa quem pode assistir essa transmissão que ela foi agendada
            // (o "vai ao ar" continua sendo o aviso principal, feito em
            // marcar_ao_vivo — este aqui é só o "fique de olho").
            transmissao_notificar_fieis(
                $conn,
                'Nova transmissão agendada',
                "A transmissão \"$titulo\" foi agendada. Você será avisado quando ela começar.",
                'transmissao_agendada',
                $audiencia
            );

            return true;
        });

        if ($ok) {
            $mensagem = "Transmissão criada. Quando o culto começar, clique em \"Marcar como Ao Vivo\".";
        } elseif ($ja_existe) {
            $erro = "Já existe uma transmissão cadastrada. Exclua a transmissão atual antes de criar outra.";
        } else {
            $erro = "Erro ao criar a transmissão. Tente de novo.";
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "marcar_ao_vivo") {
    $id = (int) ($_POST["idtr_id"] ?? 0);

    if ($id <= 0) {
        $erro = "Transmissão inválida.";
    } else {
        // Tudo dentro de 1 transação, travada por um advisory lock fixo (mesma
        // chave sempre, representando "a regra de só 1 transmissão AO_VIVO por
        // vez"): sem isso, 2 cliques em "Marcar como Ao Vivo" quase juntos (2
        // abas, ou o admin clicando 2x) podiam intercalar os UPDATEs de tal
        // jeito que as duas transmissões terminassem AO_VIVO ao mesmo tempo, e
        // a notificação em massa (mais abaixo) saísse em dobro pros fiéis.
        $push_ao_vivo = null;
        $ok = transmissao_transacao_com_lock($conn, function ($conn) use ($id, &$push_ao_vivo) {
            // Só uma transmissão "AO_VIVO" por vez — encerra qualquer outra que
            // tenha ficado esquecida nesse status (ex.: admin fechou a aba sem
            // clicar em "Encerrar" da vez anterior).
            if (!mysqli_query($conn, "UPDATE id_transmissao SET idtr_status = 'ENCERRADA', idtr_encerrada_em = NOW() WHERE idtr_status = 'AO_VIVO' AND idtr_id != $id")) {
                return false;
            }

            if (!mysqli_query($conn, "UPDATE id_transmissao SET idtr_status = 'AO_VIVO', idtr_iniciada_em = NOW() WHERE idtr_id = $id")) {
                return false;
            }

            // Notificação em massa só na PRIMEIRA vez que essa transmissão vai
            // ao ar — idtr_notificacao_enviada evita reenviar se o admin
            // marcar "Ao Vivo" de novo (ex.: caiu e voltou) ou editar depois.
            // Estar dentro do mesmo lock garante que 2 cliques concorrentes
            // não leem os dois essa flag ainda como 0 antes de qualquer um
            // marcar como enviada.
            $res_check = mysqli_query($conn, "SELECT idtr_titulo, idtr_notificacao_enviada, idtr_audiencia FROM id_transmissao WHERE idtr_id = $id LIMIT 1");
            $linha_check = $res_check ? mysqli_fetch_assoc($res_check) : null;
            if (!$linha_check) {
                return false;
            }

            if ((int) $linha_check['idtr_notificacao_enviada'] === 0) {
                $enviado = transmissao_notificar_fieis(
                    $conn,
                    'Estamos ao vivo!',
                    "A transmissão \"{$linha_check['idtr_titulo']}\" começou agora. Toque para assistir.",
                    'transmissao_ao_vivo',
                    $linha_check['idtr_audiencia'],
                    $ids_ao_vivo
                );
                $push_ao_vivo = array('ids' => $ids_ao_vivo, 'titulo' => 'Estamos ao vivo!', 'texto' => "A transmissão \"{$linha_check['idtr_titulo']}\" começou agora. Toque para assistir.");
                if ($enviado && !mysqli_query($conn, "UPDATE id_transmissao SET idtr_notificacao_enviada = 1 WHERE idtr_id = $id")) {
                    return false;
                }
            }

            return true;
        });

        if ($ok && $push_ao_vivo) {
            // Depois da transação: a chamada à OneSignal não segura a trava.
            notificacoes_push_lote($conn, $push_ao_vivo['ids'], $push_ao_vivo['titulo'], $push_ao_vivo['texto'], 'ao_vivo.php');
        }
        if ($ok) {
            $mensagem = "Transmissão marcada como Ao Vivo.";
        } else {
            $erro = "Não foi possível marcar a transmissão como Ao Vivo. Tente de novo.";
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "encerrar") {
    $id = (int) ($_POST["idtr_id"] ?? 0);
    if ($id > 0) {
        // Mesmo lock das outras transições de status, pra não mandar a
        // notificação de "encerrou" em dobro se o admin clicar 2x rápido.
        $ok = transmissao_transacao_com_lock($conn, function ($conn) use ($id) {
            $res_check = mysqli_query($conn, "SELECT idtr_titulo, idtr_status, idtr_notificacao_encerrada, idtr_audiencia FROM id_transmissao WHERE idtr_id = $id LIMIT 1");
            $linha_check = $res_check ? mysqli_fetch_assoc($res_check) : null;
            if (!$linha_check) {
                return false;
            }

            if (!mysqli_query($conn, "UPDATE id_transmissao SET idtr_status = 'ENCERRADA', idtr_encerrada_em = NOW() WHERE idtr_id = $id")) {
                return false;
            }

            // Só avisa se ela realmente esteve AO_VIVO (encerrar uma que ainda
            // estava só AGENDADA não tem replay nenhum pra assistir, não faz
            // sentido notificar) e só uma vez por transmissão.
            if ($linha_check['idtr_status'] === 'AO_VIVO' && (int) $linha_check['idtr_notificacao_encerrada'] === 0) {
                $enviado = transmissao_notificar_fieis(
                    $conn,
                    'Transmissão encerrada',
                    "A transmissão \"{$linha_check['idtr_titulo']}\" foi encerrada. A gravação já está disponível pra assistir.",
                    'transmissao_encerrada',
                    $linha_check['idtr_audiencia']
                );
                if ($enviado && !mysqli_query($conn, "UPDATE id_transmissao SET idtr_notificacao_encerrada = 1 WHERE idtr_id = $id")) {
                    return false;
                }
            }

            return true;
        });

        if ($ok) {
            $mensagem = "Transmissão encerrada.";
        } else {
            $erro = "Não foi possível encerrar a transmissão. Tente de novo.";
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "excluir") {
    $id = (int) ($_POST["idtr_id"] ?? 0);
    if ($id > 0) {
        if (mysqli_query($conn, "DELETE FROM id_transmissao WHERE idtr_id = $id")) {
            $mensagem = "Transmissão excluída.";
        } else {
            $erro = site_erro_banco("Não foi possível excluir a transmissão.");
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "alterar_audiencia") {
    // Corrige pra quem a transmissão é (ex.: criou como "Todos" mas era só
    // pra fiéis). Vale na hora: a página da live e o polling do chat checam
    // idtr_audiencia a cada requisição. Não reenvia notificações.
    $id = (int) ($_POST["idtr_id"] ?? 0);
    $audiencia = $_POST["idtr_audiencia"] ?? '';
    $rotulos_audiencia_msg = array('TODOS' => 'todos', 'FIEIS' => 'somente fiéis cadastrados', 'ADMINS' => 'apenas administradores');

    if ($id <= 0 || !isset($rotulos_audiencia_msg[$audiencia])) {
        $erro = "Selecione um público válido para a transmissão.";
    } elseif (mysqli_query($conn, "UPDATE id_transmissao SET idtr_audiencia = '$audiencia' WHERE idtr_id = $id")) {
        $mensagem = "Público da transmissão alterado para " . $rotulos_audiencia_msg[$audiencia] . ".";
    } else {
        $erro = "Não foi possível alterar o público da transmissão.";
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "ocultar_comentario") {
    $id = (int) ($_POST["idtc_id"] ?? 0);
    if ($id > 0) {
        if (mysqli_query($conn, "UPDATE id_transmissao_comentario SET idtc_oculto = 1 WHERE idtc_id = $id")) {
            $mensagem = "Comentário ocultado.";
        } else {
            $erro = site_erro_banco("Não foi possível ocultar o comentário.");
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "mostrar_comentario") {
    $id = (int) ($_POST["idtc_id"] ?? 0);
    if ($id > 0) {
        if (mysqli_query($conn, "UPDATE id_transmissao_comentario SET idtc_oculto = 0 WHERE idtc_id = $id")) {
            $mensagem = "Comentário restaurado.";
        } else {
            $erro = site_erro_banco("Não foi possível restaurar o comentário.");
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "excluir_comentario") {
    $id = (int) ($_POST["idtc_id"] ?? 0);
    if ($id > 0) {
        if (mysqli_query($conn, "DELETE FROM id_transmissao_comentario WHERE idtc_id = $id")) {
            $mensagem = "Comentário excluído.";
        } else {
            $erro = site_erro_banco("Não foi possível excluir o comentário.");
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "bloquear_fiel") {
    $fiel_id = (int) ($_POST["idf_id"] ?? 0);
    $duracao = $_POST["duracao"] ?? '';
    // Mapa duração -> fração de INTERVAL do Postgres; 'permanente' vira
    // NULL em idbc_expira_em (ver transmissao_fiel_bloqueado_chat()).
    $duracoes_validas = array(
        '1h'  => "NOW() + INTERVAL '1 hour'",
        '1d'  => "NOW() + INTERVAL '1 day'",
        '7d'  => "NOW() + INTERVAL '7 days'",
        '30d' => "NOW() + INTERVAL '30 days'",
        'permanente' => 'NULL',
    );

    if ($fiel_id <= 0 || !isset($duracoes_validas[$duracao])) {
        $erro = "Selecione um usuário e uma duração válidos pra bloquear.";
    } else {
        // ON CONFLICT (idf_id): no máximo 1 bloqueio ativo por fiel — bloquear
        // de novo (ex.: duplo clique, ou trocar de "1 dia" pra "permanente")
        // só atualiza o prazo da mesma linha, em vez de duplicar.
        $expira_sql = $duracoes_validas[$duracao];
        $sql = "
            INSERT INTO id_fiel_bloqueio_chat (idf_id, idbc_expira_em)
            VALUES ($fiel_id, $expira_sql)
            ON CONFLICT (idf_id) DO UPDATE SET
                idbc_expira_em = EXCLUDED.idbc_expira_em,
                idbc_criado_em = NOW()
        ";
        if (mysqli_query($conn, $sql)) {
            $mensagem = "Usuário bloqueado no chat" . ($duracao === 'permanente' ? " permanentemente." : ".");
        } else {
            $erro = "Não foi possível bloquear o usuário.";
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "desbloquear_fiel") {
    $fiel_id = (int) ($_POST["idf_id"] ?? 0);
    if ($fiel_id > 0) {
        if (mysqli_query($conn, "DELETE FROM id_fiel_bloqueio_chat WHERE idf_id = $fiel_id")) {
            $mensagem = "Bloqueio removido.";
        } else {
            $erro = site_erro_banco("Não foi possível remover o bloqueio.");
        }
    }
}

// O chat e o status da live ficam até 15 s em cache para quem assiste
// (site_cache_compartilhado): qualquer ação aqui vale na hora.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"])) {
    site_cache_apagar('transmissao');
    // Registro de atividade (ver admin_log()): só o que deu certo.
    $acao_live = (string) $_POST["acao"];
    if ($erro === '' && $mensagem !== '') {
        $moderacao = in_array($acao_live, array('ocultar_comentario', 'mostrar_comentario', 'excluir_comentario', 'bloquear_fiel', 'desbloquear_fiel'), true);
        admin_log($conn, $moderacao ? 'live.moderar' : 'live.status', $moderacao ? 'comentario' : 'transmissao',
            (int) ($_POST['idtc_id'] ?? $_POST['idf_id'] ?? $_POST['idtr_id'] ?? 0) ?: null,
            array('acao' => $acao_live));
    }
}

// As ações de moderação do chat (ocultar/mostrar/excluir/bloquear/
// desbloquear) são chamadas via fetch() pelo JS lá embaixo, pra não
// precisar recarregar a página inteira (e reiniciar o vídeo) a cada
// clique — ver seção "Chat (moderação)". O JS manda o cabeçalho
// X-Requested-With, então aqui a resposta vira só um JSON com o
// resultado, em vez do HTML da página inteira.
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch' && $_SERVER["REQUEST_METHOD"] === "POST") {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => $erro === '', 'mensagem' => $mensagem, 'erro' => $erro));
    exit;
}

// ============================================================================
// DADOS PRA TELA
// ============================================================================

$transmissoes = array();
$res_transmissoes = mysqli_query($conn, "SELECT idtr_id, idtr_titulo, idtr_youtube_id, idtr_status, idtr_audiencia, idtr_iniciada_em, idtr_encerrada_em, idtr_criado_em FROM id_transmissao ORDER BY idtr_id DESC");
if ($res_transmissoes) {
    while ($linha = mysqli_fetch_assoc($res_transmissoes)) {
        $transmissoes[] = $linha;
    }
}

// Transmissão selecionada pra ver/moderar comentários: via ?ver=, ou a mais
// recente por padrão (mesma prioridade que ao-vivo.php usa pra decidir o que
// mostrar ao público: AO_VIVO primeiro, senão a mais recente de qualquer jeito).
$transmissao_selecionada_id = isset($_GET['ver']) ? (int) $_GET['ver'] : 0;
if ($transmissao_selecionada_id <= 0 && !empty($transmissoes)) {
    $prioridade = null;
    foreach ($transmissoes as $t) {
        if ($t['idtr_status'] === 'AO_VIVO') {
            $prioridade = $t;
            break;
        }
    }
    $transmissao_padrao = $prioridade ?? $transmissoes[0];
    $transmissao_selecionada_id = (int) $transmissao_padrao['idtr_id'];
}

// A própria linha da transmissão selecionada (pra mostrar o vídeo junto da
// moderação) — já está em $transmissoes, não precisa de outra consulta.
$transmissao_selecionada = null;
foreach ($transmissoes as $t) {
    if ((int) $t['idtr_id'] === $transmissao_selecionada_id) {
        $transmissao_selecionada = $t;
        break;
    }
}

$comentarios = array();
if ($transmissao_selecionada_id > 0) {
    // EXISTS(...) repete a mesma regra de transmissao_fiel_bloqueado_chat()
    // (conexao.php) — aqui embutida na mesma consulta pra trazer junto com
    // cada comentário, em vez de 1 query por linha. Se a regra de bloqueio
    // mudar (ex.: bloqueio por transmissão em vez de global), atualize as
    // duas.
    $res_comentarios = mysqli_query($conn, "
        SELECT c.idtc_id, c.idtc_mensagem, c.idtc_oculto, c.idtc_criado_em, f.IDF_NOME, f.IDF_ID,
            EXISTS(
                SELECT 1 FROM id_fiel_bloqueio_chat b
                WHERE b.idf_id = f.IDF_ID AND (b.idbc_expira_em IS NULL OR b.idbc_expira_em > NOW())
            ) AS ja_bloqueado
        FROM id_transmissao_comentario c
        JOIN ID_FIEL f ON f.IDF_ID = c.idf_id
        WHERE c.idtr_id = $transmissao_selecionada_id
        ORDER BY c.idtc_id DESC
        LIMIT 200
    ");
    if ($res_comentarios) {
        while ($linha = mysqli_fetch_assoc($res_comentarios)) {
            $comentarios[] = $linha;
        }
    }
}

// Fiéis atualmente bloqueados do chat (de qualquer transmissão) — pra
// mostrar a lista com opção de desbloquear. Mesma regra de
// transmissao_fiel_bloqueado_chat() (conexao.php), aqui com JOIN pro nome.
$fieis_bloqueados = array();
$res_bloqueados = mysqli_query($conn, "
    SELECT b.idbc_id, b.idf_id, b.idbc_expira_em, f.IDF_NOME
    FROM id_fiel_bloqueio_chat b
    JOIN ID_FIEL f ON f.IDF_ID = b.idf_id
    WHERE b.idbc_expira_em IS NULL OR b.idbc_expira_em > NOW()
    ORDER BY b.idbc_criado_em DESC
");
if ($res_bloqueados) {
    while ($linha = mysqli_fetch_assoc($res_bloqueados)) {
        $fieis_bloqueados[] = $linha;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transmissão ao vivo - Admin</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
    <style>
        .badge-status-AGENDADA { background: #475569; }
        .badge-status-AO_VIVO { background: #dc2626; }
        .badge-status-ENCERRADA { background: #334155; }
        .comentario-oculto { opacity: 0.5; }
        .admin-player-wrap {
            position: relative;
            width: 100%;
            padding-top: 56.25%;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
        }
        .admin-player-wrap iframe {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }
        .audience-picker {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .audience-option { cursor: pointer; }
        .audience-option input {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
        }
        .audience-option-inner {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.7rem 1.1rem;
            border-radius: 12px;
            border: 2px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.03);
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .audience-option-inner i {
            font-size: 1.15rem;
            color: var(--text-muted, #94a3b8);
        }
        .audience-option:has(input:checked) .audience-option-inner {
            border-color: #60a5fa;
            background: rgba(96,165,250,0.14);
        }
        .audience-option:has(input:checked) .audience-option-inner i {
            color: #60a5fa;
        }
        .audience-option input:focus-visible + .audience-option-inner {
            outline: 2px solid #60a5fa;
            outline-offset: 2px;
        }
    </style>
</head>
<body class="admin-layout admin-shell" style="transition: background 0.3s ease;">
    <?php admin_layout_abrir($conn, 'transmissao', 'Ao Vivo', 'fa-tower-broadcast'); ?>

        <p class="text-muted mb-4">Cadastre a live do YouTube, marque como "Ao Vivo" quando o culto começar e modere o chat.</p>

        <div class="admin-card">

            <?php if ($mensagem !== "") { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" style="background: #14532d; color: #fff; border-color: #166534;">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensagem); ?>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="alert"></button>
                </div>
            <?php } ?>

            <?php if ($erro !== "") { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($erro); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php } ?>

            <div class="card mb-4" style="background: var(--card-bg, #111827); border-color: var(--border-color, rgba(255,255,255,0.12));">
                <div class="card-body">
                    <h2 class="h5 mb-3">Nova transmissão</h2>
                    <?php if (!empty($transmissoes)) { ?>
                        <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
                            <i class="fas fa-lock mt-1"></i>
                            <div>
                                Já existe uma transmissão cadastrada (<strong><?php echo htmlspecialchars($transmissoes[0]['idtr_titulo']); ?></strong>).
                                Para criar outra, exclua a atual na lista abaixo.
                            </div>
                        </div>
                    <?php } ?>
                    <form method="POST" id="form-criar-transmissao">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="criar">
                        <fieldset class="row g-3"<?php echo !empty($transmissoes) ? ' disabled' : ''; ?>>
                        <div class="col-md-6">
                            <label class="form-label">Título</label>
                            <input type="text" name="idtr_titulo" class="form-control custom-input" maxlength="150" placeholder="Culto de Domingo" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Link ou ID do YouTube</label>
                            <input type="text" name="youtube_input" class="form-control custom-input" placeholder="https://youtube.com/live/..." required>
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">Escolha para quem será a live</label>
                            <div class="audience-picker">
                                <label class="audience-option">
                                    <input type="radio" name="idtr_audiencia" value="TODOS" data-rotulo="todos (visitantes e fiéis)" checked>
                                    <span class="audience-option-inner">
                                        <i class="fas fa-earth-americas"></i>
                                        <span>Todos</span>
                                    </span>
                                </label>
                                <label class="audience-option">
                                    <input type="radio" name="idtr_audiencia" value="FIEIS" data-rotulo="somente fiéis cadastrados">
                                    <span class="audience-option-inner">
                                        <i class="fas fa-user-check"></i>
                                        <span>Somente fiéis cadastrados</span>
                                    </span>
                                </label>
                                <label class="audience-option">
                                    <input type="radio" name="idtr_audiencia" value="ADMINS" data-rotulo="apenas administradores">
                                    <span class="audience-option-inner">
                                        <i class="fas fa-user-shield"></i>
                                        <span>Apenas admins</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Criar transmissão</button>
                        </div>
                        </fieldset>
                    </form>
                    <small class="form-text text-muted d-block mt-2">
                        Crie a transmissão no YouTube Studio (pode ser "Não listada") antes de colar o link aqui.
                        Só existe 1 transmissão por vez.
                    </small>
                </div>
            </div>

            <div class="card mb-4" style="background: var(--card-bg, #111827); border-color: var(--border-color, rgba(255,255,255,0.12));">
                <div class="card-body">
                    <h2 class="h5 mb-3">Transmissões</h2>
                    <?php if (empty($transmissoes)) { ?>
                        <p class="text-muted mb-0">Nenhuma transmissão cadastrada ainda.</p>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Título</th>
                                        <th>Status</th>
                                        <th>Audiência</th>
                                        <th>YouTube</th>
                                        <th>Criada em</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $rotulos_audiencia = array('TODOS' => 'Todos', 'FIEIS' => 'Somente fiéis', 'ADMINS' => 'Apenas admins');
                                    foreach ($transmissoes as $t) { ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($t['idtr_titulo']); ?></td>
                                            <td><span class="badge badge-status-<?php echo htmlspecialchars($t['idtr_status']); ?>"><?php echo htmlspecialchars($t['idtr_status']); ?></span></td>
                                            <td><?php echo htmlspecialchars($rotulos_audiencia[$t['idtr_audiencia']] ?? $t['idtr_audiencia']); ?></td>
                                            <td>
                                                <a href="https://www.youtube.com/watch?v=<?php echo urlencode($t['idtr_youtube_id']); ?>" target="_blank" rel="noopener">
                                                    <?php echo htmlspecialchars($t['idtr_youtube_id']); ?> <i class="fas fa-arrow-up-right-from-square ms-1" style="font-size:0.75rem;"></i>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars(site_data_hora($t['idtr_criado_em'])); ?></td>
                                            <td class="text-end">
                                                <div class="d-flex gap-1 justify-content-end flex-wrap">
                                                    <a href="?ver=<?php echo (int) $t['idtr_id']; ?>" class="btn btn-sm btn-outline-light"><i class="fas fa-comments"></i></a>
                                                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#audienciaModal<?php echo (int) $t['idtr_id']; ?>" title="Alterar público">
                                                        <i class="fas fa-users-gear"></i>
                                                    </button>
                                                    <?php if ($t['idtr_status'] !== 'AO_VIVO') { ?>
                                                        <form method="POST" class="d-inline" data-confirmar="Deseja iniciar a transmissão &quot;<?php echo htmlspecialchars($t['idtr_titulo'], ENT_QUOTES); ?>&quot; agora? Os fiéis aprovados serão notificados.">
                                                            <?php echo csrf_campo(); ?>
                                                            <input type="hidden" name="acao" value="marcar_ao_vivo">
                                                            <input type="hidden" name="idtr_id" value="<?php echo (int) $t['idtr_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-tower-broadcast me-1"></i>Ao Vivo</button>
                                                        </form>
                                                    <?php } ?>
                                                    <?php if ($t['idtr_status'] === 'AO_VIVO') { ?>
                                                        <form method="POST" class="d-inline" data-confirmar="Deseja encerrar a transmissão &quot;<?php echo htmlspecialchars($t['idtr_titulo'], ENT_QUOTES); ?>&quot;? Ela para de aceitar novas mensagens no chat.">
                                                            <?php echo csrf_campo(); ?>
                                                            <input type="hidden" name="acao" value="encerrar">
                                                            <input type="hidden" name="idtr_id" value="<?php echo (int) $t['idtr_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-secondary">Encerrar</button>
                                                        </form>
                                                    <?php } ?>
                                                    <form method="POST" class="d-inline" data-confirmar="Excluir esta transmissão e todo o chat dela?">
                                                        <?php echo csrf_campo(); ?>
                                                        <input type="hidden" name="acao" value="excluir">
                                                        <input type="hidden" name="idtr_id" value="<?php echo (int) $t['idtr_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Excluir"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <?php foreach ($transmissoes as $t) { ?>
                            <!-- Modal Alterar público -->
                            <div class="modal fade" id="audienciaModal<?php echo (int) $t['idtr_id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Alterar público</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <?php echo csrf_campo(); ?>
                                            <input type="hidden" name="acao" value="alterar_audiencia">
                                            <input type="hidden" name="idtr_id" value="<?php echo (int) $t['idtr_id']; ?>">
                                            <div class="modal-body">
                                                <p>Para quem é a transmissão <strong><?php echo htmlspecialchars($t['idtr_titulo']); ?></strong>?</p>
                                                <div class="audience-picker">
                                                    <?php
                                                    $opcoes_audiencia = array(
                                                        'TODOS' => array('fa-earth-americas', 'Todos'),
                                                        'FIEIS' => array('fa-user-check', 'Somente fiéis cadastrados'),
                                                        'ADMINS' => array('fa-user-shield', 'Apenas admins'),
                                                    );
                                                    foreach ($opcoes_audiencia as $valor => $opcao) { ?>
                                                        <label class="audience-option">
                                                            <input type="radio" name="idtr_audiencia" value="<?php echo $valor; ?>"<?php echo $t['idtr_audiencia'] === $valor ? ' checked' : ''; ?>>
                                                            <span class="audience-option-inner">
                                                                <i class="fas <?php echo $opcao[0]; ?>"></i>
                                                                <span><?php echo $opcao[1]; ?></span>
                                                            </span>
                                                        </label>
                                                    <?php } ?>
                                                </div>
                                                <p class="text-muted small mt-3 mb-0">A mudança vale na hora, inclusive com a live no ar: quem não fizer parte do novo público não consegue mais abrir a transmissão, e o chat para de atualizar para quem já estava com a página aberta. Nenhuma notificação nova é enviada.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i>Salvar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>

            <?php if ($transmissao_selecionada_id > 0) { ?>
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="card h-100" style="background: var(--card-bg, #111827); border-color: var(--border-color, rgba(255,255,255,0.12));">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                    <h2 class="h5 mb-0">
                                        <?php echo $transmissao_selecionada ? htmlspecialchars($transmissao_selecionada['idtr_titulo']) : 'Transmissão'; ?>
                                    </h2>
                                    <?php if ($transmissao_selecionada) { ?>
                                        <span class="badge badge-status-<?php echo htmlspecialchars($transmissao_selecionada['idtr_status']); ?>"><?php echo htmlspecialchars($transmissao_selecionada['idtr_status']); ?></span>
                                    <?php } ?>
                                </div>
                                <?php if ($transmissao_selecionada) { ?>
                                    <div class="admin-player-wrap">
                                        <iframe
                                            src="https://www.youtube-nocookie.com/embed/<?php echo urlencode($transmissao_selecionada['idtr_youtube_id']); ?>?modestbranding=1&rel=0&iv_load_policy=3"
                                            title="<?php echo htmlspecialchars($transmissao_selecionada['idtr_titulo']); ?>"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                            allowfullscreen
                                        ></iframe>
                                    </div>
                                    <small class="form-text text-muted d-block mt-2">
                                        Assista aqui pra acompanhar junto com o chat ao lado — o chat atualiza sozinho a cada poucos segundos, sem precisar recarregar a página.
                                    </small>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card h-100" style="background: var(--card-bg, #111827); border-color: var(--border-color, rgba(255,255,255,0.12));">
                            <div class="card-body">
                                <h2 class="h5 mb-3">Chat (moderação)</h2>
                                <div id="lista-moderacao" class="d-flex flex-column gap-2" style="max-height: 480px; overflow-y: auto;">
                                <?php if (empty($comentarios)) { ?>
                                    <p class="text-muted mb-0" id="moderacao-vazio">Nenhuma mensagem ainda nesta transmissão.</p>
                                <?php } else { ?>
                                        <?php foreach ($comentarios as $c) { ?>
                                            <div class="d-flex justify-content-between align-items-start gap-2 p-2 border rounded <?php echo ((int) $c['idtc_oculto'] === 1) ? 'comentario-oculto' : ''; ?>" style="border-color: rgba(255,255,255,0.12) !important;">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($c['IDF_NOME']); ?></strong>
                                                    <span class="text-muted small ms-2"><?php echo htmlspecialchars(site_data_hora($c['idtc_criado_em'], 'd/m H:i')); ?></span>
                                                    <?php if (!empty($c['ja_bloqueado'])) { ?>
                                                        <span class="badge bg-danger ms-2">Bloqueado</span>
                                                    <?php } ?>
                                                    <div><?php echo nl2br(htmlspecialchars($c['idtc_mensagem'])); ?></div>
                                                </div>
                                                <div class="d-flex flex-column gap-1 flex-shrink-0" style="min-width: 150px;">
                                                    <form method="POST">
                                                        <?php echo csrf_campo(); ?>
                                                        <input type="hidden" name="idtc_id" value="<?php echo (int) $c['idtc_id']; ?>">
                                                        <?php if ((int) $c['idtc_oculto'] === 1) { ?>
                                                            <input type="hidden" name="acao" value="mostrar_comentario">
                                                            <button type="submit" class="btn btn-sm btn-outline-light w-100"><i class="fas fa-eye me-1"></i>Mostrar</button>
                                                        <?php } else { ?>
                                                            <input type="hidden" name="acao" value="ocultar_comentario">
                                                            <button type="submit" class="btn btn-sm btn-outline-warning w-100"><i class="fas fa-eye-slash me-1"></i>Ocultar</button>
                                                        <?php } ?>
                                                    </form>
                                                    <form method="POST" data-confirmar="Excluir esta mensagem definitivamente? Não dá pra desfazer.">
                                                        <?php echo csrf_campo(); ?>
                                                        <input type="hidden" name="acao" value="excluir_comentario">
                                                        <input type="hidden" name="idtc_id" value="<?php echo (int) $c['idtc_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-trash me-1"></i>Excluir</button>
                                                    </form>
                                                    <?php if (!empty($c['ja_bloqueado'])) { ?>
                                                        <form method="POST">
                                                            <?php echo csrf_campo(); ?>
                                                            <input type="hidden" name="acao" value="desbloquear_fiel">
                                                            <input type="hidden" name="idf_id" value="<?php echo (int) $c['IDF_ID']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-light w-100"><i class="fas fa-unlock me-1"></i>Desbloquear</button>
                                                        </form>
                                                    <?php } else { ?>
                                                        <form method="POST" class="d-flex gap-1" data-confirmar="Bloquear <?php echo htmlspecialchars($c['IDF_NOME'], ENT_QUOTES); ?> de comentar em qualquer transmissão, pelo prazo selecionado?">
                                                            <?php echo csrf_campo(); ?>
                                                            <input type="hidden" name="acao" value="bloquear_fiel">
                                                            <input type="hidden" name="idf_id" value="<?php echo (int) $c['IDF_ID']; ?>">
                                                            <select name="duracao" class="form-select form-select-sm" style="width:auto;">
                                                                <option value="1h">1 hora</option>
                                                                <option value="1d">1 dia</option>
                                                                <option value="7d">7 dias</option>
                                                                <option value="30d">30 dias</option>
                                                                <option value="permanente">Permanente</option>
                                                            </select>
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Bloquear do chat"><i class="fas fa-ban"></i></button>
                                                        </form>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        <?php } ?>
                                <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if (!empty($fieis_bloqueados)) { ?>
                <div class="card mt-4" style="background: var(--card-bg, #111827); border-color: var(--border-color, rgba(255,255,255,0.12));">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Bloqueados do chat</h2>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Fiel</th>
                                        <th>Até quando</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fieis_bloqueados as $b) { ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($b['IDF_NOME']); ?></td>
                                            <td><?php echo $b['idbc_expira_em'] ? htmlspecialchars(site_data_hora($b['idbc_expira_em'])) : 'Permanente'; ?></td>
                                            <td class="text-end">
                                                <form method="POST" class="d-inline">
                                                    <?php echo csrf_campo(); ?>
                                                    <input type="hidden" name="acao" value="desbloquear_fiel">
                                                    <input type="hidden" name="idf_id" value="<?php echo (int) $b['idf_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-light"><i class="fas fa-unlock me-1"></i>Desbloquear</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php admin_layout_fechar(); ?>

    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
    <script src="../../js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        (function () {
            // Confirmação ao criar, citando o grupo escolhido (e o aviso de
            // que isso apaga a transmissão atual, quando for o caso) — o
            // grupo só é conhecido no navegador (é o rádio marcado na hora),
            // por isso isso não dá pra montar só no PHP.
            var formCriar = document.getElementById('form-criar-transmissao');
            if (formCriar) {
                formCriar.addEventListener('submit', function (ev) {
                    var escolhido = formCriar.querySelector('input[name="idtr_audiencia"]:checked');
                    var rotulo = escolhido ? escolhido.dataset.rotulo : 'todos';
                    var aviso = 'Você deseja criar a live para "' + rotulo + '" da igreja?';
                    if (!confirm(aviso)) {
                        ev.preventDefault();
                    }
                });
            }
        })();
    </script>
    <?php if ($transmissao_selecionada_id > 0) { ?>
    <script nonce="<?php echo site_csp_nonce(); ?>">
        (function () {
            const TRANSMISSAO_ID = <?php echo (int) $transmissao_selecionada_id; ?>;
            const CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;
            const lista = document.getElementById('lista-moderacao');

            // Manda uma ação de moderação via fetch (em vez de submeter um
            // form de verdade e recarregar a página inteira) — assim o
            // vídeo ao lado não reinicia a cada ocultar/excluir/bloquear, e
            // a lista já atualiza sozinha logo em seguida.
            async function enviarAcao(campos) {
                try {
                    const params = new URLSearchParams(Object.assign({ csrf_token: CSRF_TOKEN }, campos));
                    const resp = await fetch('admin_transmissao.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'fetch',
                        },
                        body: params.toString(),
                    });
                    const dados = await resp.json().catch(function () { return null; });
                    if (!dados || !dados.ok) {
                        (window.siteAvisar || window.alert)((dados && dados.erro) || 'Não foi possível concluir a ação.', 'erro');
                    }
                } catch (e) {
                    (window.siteAvisar || window.alert)('Falha de conexão. Tente de novo.', 'erro');
                }
                atualizarModeracao();
            }

            function criarBotao(texto, classe, icone, aoClicar) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm ' + classe + ' w-100';
                btn.innerHTML = '<i class="fas ' + icone + ' me-1"></i>' + texto;
                btn.addEventListener('click', aoClicar);
                return btn;
            }

            function formatarComentario(c) {
                const linha = document.createElement('div');
                linha.className = 'd-flex justify-content-between align-items-start gap-2 p-2 border rounded' + (c.oculto ? ' comentario-oculto' : '');
                linha.style.borderColor = 'rgba(255,255,255,0.12)';

                const info = document.createElement('div');
                const nome = document.createElement('strong');
                nome.textContent = c.nome;
                info.appendChild(nome);
                const hora = document.createElement('span');
                hora.className = 'text-muted small ms-2';
                hora.textContent = c.criado_em;
                info.appendChild(hora);
                if (c.bloqueado) {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-danger ms-2';
                    badge.textContent = 'Bloqueado';
                    info.appendChild(badge);
                }
                const texto = document.createElement('div');
                texto.style.whiteSpace = 'pre-wrap';
                texto.textContent = c.mensagem;
                info.appendChild(texto);

                const acoes = document.createElement('div');
                acoes.className = 'd-flex flex-column gap-1 flex-shrink-0';
                acoes.style.minWidth = '150px';

                acoes.appendChild(
                    c.oculto
                        ? criarBotao('Mostrar', 'btn-outline-light', 'fa-eye', function () {
                            enviarAcao({ acao: 'mostrar_comentario', idtc_id: c.id });
                        })
                        : criarBotao('Ocultar', 'btn-outline-warning', 'fa-eye-slash', function () {
                            enviarAcao({ acao: 'ocultar_comentario', idtc_id: c.id });
                        })
                );

                acoes.appendChild(
                    criarBotao('Excluir', 'btn-outline-danger', 'fa-trash', function () {
                        if (confirm('Excluir esta mensagem definitivamente? Não dá pra desfazer.')) {
                            enviarAcao({ acao: 'excluir_comentario', idtc_id: c.id });
                        }
                    })
                );

                if (c.bloqueado) {
                    acoes.appendChild(
                        criarBotao('Desbloquear', 'btn-outline-light', 'fa-unlock', function () {
                            enviarAcao({ acao: 'desbloquear_fiel', idf_id: c.idf_id });
                        })
                    );
                } else {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'd-flex gap-1';

                    const select = document.createElement('select');
                    select.className = 'form-select form-select-sm';
                    select.style.width = 'auto';
                    [['1h', '1 hora'], ['1d', '1 dia'], ['7d', '7 dias'], ['30d', '30 dias'], ['permanente', 'Permanente']].forEach(function (opt) {
                        const o = document.createElement('option');
                        o.value = opt[0];
                        o.textContent = opt[1];
                        select.appendChild(o);
                    });

                    const botaoBloquear = document.createElement('button');
                    botaoBloquear.type = 'button';
                    botaoBloquear.className = 'btn btn-sm btn-outline-danger';
                    botaoBloquear.title = 'Bloquear do chat';
                    botaoBloquear.innerHTML = '<i class="fas fa-ban"></i>';
                    botaoBloquear.addEventListener('click', function () {
                        if (confirm('Bloquear ' + c.nome + ' de comentar em qualquer transmissão, pelo prazo selecionado?')) {
                            enviarAcao({ acao: 'bloquear_fiel', idf_id: c.idf_id, duracao: select.value });
                        }
                    });

                    wrapper.appendChild(select);
                    wrapper.appendChild(botaoBloquear);
                    acoes.appendChild(wrapper);
                }

                linha.appendChild(info);
                linha.appendChild(acoes);
                return linha;
            }

            async function atualizarModeracao() {
                try {
                    const resp = await fetch('ajax_admin_transmissao_comentarios.php?idtr_id=' + TRANSMISSAO_ID);
                    const dados = await resp.json();
                    if (!dados.ok) return;

                    // A lista inteira é reconstruída a cada ciclo (o estado
                    // de um comentário — oculto, bloqueado — pode ter mudado
                    // desde o último poll), então guarda e restaura o scroll
                    // pra não "pular" pro topo a cada 5s enquanto o admin
                    // está lendo mensagens mais antigas.
                    const scrollAntes = lista.scrollTop;
                    lista.innerHTML = '';
                    if (dados.comentarios.length === 0) {
                        const vazio = document.createElement('p');
                        vazio.className = 'text-muted mb-0';
                        vazio.textContent = 'Nenhuma mensagem ainda nesta transmissão.';
                        lista.appendChild(vazio);
                        return;
                    }
                    dados.comentarios.forEach(function (c) {
                        lista.appendChild(formatarComentario(c));
                    });
                    lista.scrollTop = scrollAntes;
                } catch (e) {
                    // Falha de rede pontual — mantém o que já está na tela e
                    // tenta de novo no próximo ciclo.
                }
            }

            // Pausa com a aba em segundo plano (mesmo padrão do chat do fiel)
            // e atualiza na hora quando ela volta.
            atualizarModeracao();
            setInterval(function () {
                if (document.visibilityState === 'visible') atualizarModeracao();
            }, 5000);
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') atualizarModeracao();
            });
        })();
    </script>
    <?php } ?>
</body>
</html>
