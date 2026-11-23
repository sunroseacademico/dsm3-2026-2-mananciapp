<?php
// Comentários abaixo de cada vídeo (php/fiel/assistir_video.php). Ver seção
// 40 de database/bancosupabase.md.
//   GET  ?idct_id=N&ordem=O[&antes_de=ID|&pular=N] -> página de comentários
//        visíveis na ordem/filtro O (relevantes, recentes, lideres,
//        versiculo), os totais, se ainda há mais e, na primeira página,
//        o comentário em destaque
//   GET  ?respostas_de=ID -> respostas do comentário (seção 53 do banco)
//   POST {acao:'comentar', idct_id, mensagem, [idcc_pai_id], [momento],
//         [versiculo], csrf_token}
//        com idcc_pai_id, é uma resposta a esse comentário; momento
//        (segundos) e versiculo (referência) vêm dos botões da T21
//   POST {acao:'excluir', idcc_id, csrf_token}  (autor ou admin)
//   POST {acao:'fixar', idcc_id, fixar, csrf_token}
//        fixa (ou desfixa) a nota do pastor da aula. Seção 54 do banco.
//   POST {acao:'votar', idcc_id, tipo, csrf_token}
//        tipo 'edificante', 'fora' ou '' (desfaz). Seção 52 do banco.
include("../conexao.php");

header('Content-Type: application/json; charset=utf-8');

function video_comentarios_responder($status, $dados) {
    http_response_code($status);
    echo json_encode($dados);
    exit;
}

// Login, inatividade, revogação, Termo e modo "só assinantes" (JSON 401/403).
auth_require_ajax();
if (!isset($_SESSION['Usuario_logado'])) {
    video_comentarios_responder(401, array('ok' => false, 'erro' => 'Não autenticado.'));
}

$fiel_id = auth_resolve_fiel_id();
if ($fiel_id <= 0) {
    video_comentarios_responder(403, array('ok' => false, 'erro' => 'Usuário inválido.'));
}

// Moderação só com a tela de Testemunhos ou de Gerenciar Vídeos.
$eh_admin = admin_pode_moderar_comunidade();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['respostas_de'])) {
    $principal = video_comentario_principal($conn, (int) $_GET['respostas_de']);
    if (!$principal || !video_conteudo_acessivel($conn, $principal['idct_id'])) {
        video_comentarios_responder(404, array('ok' => false, 'erro' => 'Comentário não encontrado.'));
    }
    video_comentarios_responder(200, array(
        'ok' => true,
        'respostas' => video_listar_respostas($conn, $principal['id']),
    ));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $conteudo_id = isset($_GET['idct_id']) ? (int) $_GET['idct_id'] : 0;
    if (!video_conteudo_acessivel($conn, $conteudo_id)) {
        video_comentarios_responder(404, array('ok' => false, 'erro' => 'Vídeo não encontrado.'));
    }
    $antes_de = isset($_GET['antes_de']) ? max(0, (int) $_GET['antes_de']) : 0;
    $pular = isset($_GET['pular']) ? max(0, (int) $_GET['pular']) : 0;
    $ordem = video_comentarios_ordem_valida($conn, $_GET['ordem'] ?? 'recentes');
    // Busca um a mais só para saber se ainda sobra comentário depois desta página.
    $pagina = video_listar_comentarios($conn, $conteudo_id, VIDEO_COMENTARIOS_POR_PAGINA + 1, $antes_de, $ordem, $pular);
    $tem_mais = count($pagina) > VIDEO_COMENTARIOS_POR_PAGINA;
    $resposta = array(
        'ok' => true,
        'ordem' => $ordem,
        'comentarios' => array_slice($pagina, 0, VIDEO_COMENTARIOS_POR_PAGINA),
        'total' => video_contar_comentarios_titulo($conn, $conteudo_id),
        'total_filtro' => video_contar_comentarios($conn, $conteudo_id, $ordem),
        'tem_mais' => $tem_mais,
    );
    // Primeira página (troca de aba): manda junto o comentário em destaque.
    if ($antes_de === 0 && $pular === 0) {
        $resposta['destaque'] = video_comentario_destaque($conn, $conteudo_id, $ordem);
        $resposta['nota'] = video_nota_fixada($conn, $conteudo_id);
    }
    video_comentarios_responder(200, $resposta);
}

$dados = json_decode(file_get_contents('php://input'), true);
if (!is_array($dados)) {
    $dados = $_POST;
}

if (!csrf_validar($dados['csrf_token'] ?? null)) {
    // 403 e não 419: o Apache não conhece 419 e devolveria 500.
    video_comentarios_responder(403, array('ok' => false, 'erro' => 'Sessão expirada. Atualize a página e tente de novo.'));
}

$acao = (string) ($dados['acao'] ?? '');

if ($acao === 'excluir') {
    $comentario_id = isset($dados['idcc_id']) ? (int) $dados['idcc_id'] : 0;
    // Autor apaga o próprio; admin apaga qualquer um (moderação).
    $filtro_autor = $eh_admin ? '' : "AND idf_id = $fiel_id";
    mysqli_query($conn, "
        UPDATE id_conteudo_comentario
        SET idcc_oculto = 1, idcc_oculto_por = $fiel_id
        WHERE idcc_id = $comentario_id $filtro_autor
    ");

    if (mysqli_affected_rows($conn) < 1) {
        video_comentarios_responder(404, array('ok' => false, 'erro' => 'Comentário não encontrado.'));
    }
    if ($eh_admin) {
        admin_log($conn, 'comunidade.remover', 'comentario', $comentario_id, array('aula' => (int) ($dados['idct_id'] ?? 0)));
    }
    video_comentarios_responder(200, array('ok' => true));
}

if ($acao === 'fixar') {
    if (!video_nota_disponivel($conn)) {
        video_comentarios_responder(503, array('ok' => false, 'erro' => 'Nota fixada indisponível no momento.'));
    }
    $comentario_id = isset($dados['idcc_id']) ? (int) $dados['idcc_id'] : 0;
    $fixar = !empty($dados['fixar']);

    $res_alvo = mysqli_query($conn, video_comentarios_select_sql($conn, $fiel_id) . "
        WHERE c.idcc_id = $comentario_id AND c.idcc_oculto = 0
    ");
    $alvo = $res_alvo ? mysqli_fetch_assoc($res_alvo) : null;
    if (!$alvo || !video_conteudo_acessivel($conn, (int) $alvo['idct_id'])) {
        video_comentarios_responder(404, array('ok' => false, 'erro' => 'Comentário não encontrado.'));
    }
    $formatado = video_formatar_comentario($alvo, $fiel_id, $eh_admin);
    if (empty($formatado['pode_fixar'])) {
        video_comentarios_responder(403, array('ok' => false, 'erro' => 'Só o admin ou o próprio líder fixam uma nota de líder.'));
    }
    if (!video_fixar_comentario($conn, $comentario_id, (int) $alvo['idct_id'], $fixar)) {
        video_comentarios_responder(500, array('ok' => false, 'erro' => 'Não foi possível salvar. Tente de novo.'));
    }
    video_comentarios_responder(200, array('ok' => true));
}

if ($acao === 'votar') {
    if (!video_votos_disponivel($conn)) {
        video_comentarios_responder(503, array('ok' => false, 'erro' => 'Votos indisponíveis no momento.'));
    }
    $comentario_id = isset($dados['idcc_id']) ? (int) $dados['idcc_id'] : 0;
    $tipo = (string) ($dados['tipo'] ?? '');
    if ($tipo !== '' && !isset(video_voto_tipos()[$tipo])) {
        video_comentarios_responder(400, array('ok' => false, 'erro' => 'Voto inválido.'));
    }

    $res_alvo = mysqli_query($conn, "
        SELECT c.idct_id, c.idf_id AS autor_id, f.IDF_STATUS AS fiel_status, f.IDF_ATIVO AS fiel_ativo
        FROM id_conteudo_comentario c
        JOIN ID_FIEL f ON f.IDF_ID = $fiel_id
        WHERE c.idcc_id = $comentario_id AND c.idcc_oculto = 0
        LIMIT 1
    ");
    $alvo = $res_alvo ? mysqli_fetch_assoc($res_alvo) : null;

    if (!$alvo || !video_conteudo_acessivel($conn, (int) $alvo['idct_id'])) {
        video_comentarios_responder(404, array('ok' => false, 'erro' => 'Comentário não encontrado.'));
    }
    if ($alvo['fiel_status'] !== 'aprovado' || (int) $alvo['fiel_ativo'] !== 1) {
        video_comentarios_responder(403, array('ok' => false, 'erro' => 'Sua conta não está aprovada para votar.'));
    }
    if ((int) $alvo['autor_id'] === $fiel_id) {
        video_comentarios_responder(403, array('ok' => false, 'erro' => 'Você não pode votar no próprio comentário.'));
    }
    if (!video_votar_comentario($conn, $comentario_id, $fiel_id, $tipo)) {
        video_comentarios_responder(500, array('ok' => false, 'erro' => 'Não foi possível registrar o voto.'));
    }
    // Resposta que chegou aos votos de "útil" pode dar medalha de Ensino
    // ao autor (T30), avisado por notificação.
    if ($tipo === 'edificante') {
        medalhas_apos_acao($conn, 0, array(), (int) $alvo['autor_id'], array('respostas_uteis'));
    }

    video_comentarios_responder(200, array(
        'ok' => true,
        'votos' => video_votos_do_comentario($conn, $comentario_id, $fiel_id),
    ));
}

if ($acao !== 'comentar') {
    video_comentarios_responder(400, array('ok' => false, 'erro' => 'Ação inválida.'));
}

$conteudo_id = isset($dados['idct_id']) ? (int) $dados['idct_id'] : 0;
$mensagem = trim((string) ($dados['mensagem'] ?? ''));

if ($mensagem === '') {
    video_comentarios_responder(400, array('ok' => false, 'erro' => 'Escreva algo antes de enviar.'));
}
if (mb_strlen($mensagem) > 1000) {
    $mensagem = mb_substr($mensagem, 0, 1000);
}

// Resposta (T18): pai_id aponta para o comentário principal (responder a
// uma resposta vai para o mesmo principal) e o vídeo é o dele.
// Momento da aula e versículo (T21) — só com as colunas no banco.
$momento = null;
$versiculo = '';
if (video_citacoes_disponivel($conn)) {
    $momento = video_momento_normalizar($dados['momento'] ?? null);
    $versiculo_digitado = trim((string) ($dados['versiculo'] ?? ''));
    $versiculo = video_versiculo_normalizar($versiculo_digitado);
    if ($versiculo_digitado !== '' && $versiculo === '') {
        video_comentarios_responder(400, array('ok' => false, 'erro' => 'Versículo não reconhecido. Use livro, capítulo e versículo — ex.: Jo 3:16.'));
    }
}

$pai_id = isset($dados['idcc_pai_id']) ? (int) $dados['idcc_pai_id'] : 0;
if ($pai_id > 0) {
    $principal = video_comentario_principal($conn, $pai_id);
    if (!$principal) {
        video_comentarios_responder(404, array('ok' => false, 'erro' => 'O comentário que você quer responder não existe mais.'));
    }
    $pai_id = $principal['id'];
    $conteudo_id = $principal['idct_id'];
}

if (!video_conteudo_acessivel($conn, $conteudo_id)) {
    video_comentarios_responder(404, array('ok' => false, 'erro' => 'Vídeo não encontrado.'));
}

// Mesmo esquema do chat ao vivo (ajax_transmissao_comentar.php): transação
// + advisory lock por fiel, pra um duplo clique não furar o limite de 1
// comentário a cada 10s por vídeo nem o de 10 por minuto no total (só o
// limite por vídeo deixava comentar em várias aulas ao mesmo tempo).
if ($conn->inTransaction()) {
    $conn->rollBack();
}
$conn->beginTransaction();

db_query($conn, "SELECT pg_advisory_xact_lock(hashtext(CAST(? AS TEXT)))", array('video:fiel:' . $fiel_id));

$res_check = mysqli_query($conn, "
    SELECT
        f.IDF_STATUS AS fiel_status,
        f.IDF_ATIVO AS fiel_ativo,
        EXISTS(
            SELECT 1 FROM id_conteudo_comentario
            WHERE idct_id = $conteudo_id AND idf_id = $fiel_id
              AND idcc_criado_em > NOW() - INTERVAL '10 seconds'
        ) AS enviou_recente,
        (
            SELECT COUNT(*) FROM id_conteudo_comentario
            WHERE idf_id = $fiel_id AND idcc_criado_em > NOW() - INTERVAL '1 minute'
        ) AS enviados_minuto,
        -- Teto do dia: 10 por minuto ainda deixava ~14 mil por dia (e cumprir
        -- na hora os desafios de comentário).
        (
            SELECT COUNT(*) FROM id_conteudo_comentario
            WHERE idf_id = $fiel_id AND idcc_criado_em > NOW() - INTERVAL '1 day'
        ) AS enviados_dia,
        -- Mesma regra de transmissao_fiel_bloqueado_chat() (conexao.php):
        -- o bloqueio de chat vale também pros comentários dos vídeos.
        EXISTS(
            SELECT 1 FROM id_fiel_bloqueio_chat
            WHERE idf_id = $fiel_id AND (idbc_expira_em IS NULL OR idbc_expira_em > NOW())
        ) AS bloqueado
    FROM ID_FIEL f
    WHERE f.IDF_ID = $fiel_id
    LIMIT 1
");
$check = $res_check ? mysqli_fetch_assoc($res_check) : null;

$erro = null;
if (!$check || $check['fiel_status'] !== 'aprovado' || (int) $check['fiel_ativo'] !== 1) {
    $erro = array(403, 'Sua conta não está aprovada para comentar.');
} elseif (!empty($check['bloqueado'])) {
    $erro = array(403, 'Você está impedido de comentar no momento.');
} elseif (!empty($check['enviou_recente'])) {
    $erro = array(429, 'Aguarde alguns segundos antes de comentar de novo.');
} elseif ((int) $check['enviados_minuto'] >= 10) {
    $erro = array(429, 'Você comentou muitas vezes no último minuto. Aguarde um pouco.');
} elseif ((int) $check['enviados_dia'] >= 100) {
    $erro = array(429, 'Você chegou ao limite de 100 comentários por dia. Volte amanhã.');
}

if ($erro) {
    $conn->rollBack();
    video_comentarios_responder($erro[0], array('ok' => false, 'erro' => $erro[1]));
}

$colunas = array('idct_id', 'idf_id', 'idcc_mensagem');
$valores = array($conteudo_id, $fiel_id, $mensagem);
if ($pai_id > 0) {
    $colunas[] = 'idcc_pai_id';
    $valores[] = $pai_id;
}
if ($momento !== null) {
    $colunas[] = 'idcc_momento';
    $valores[] = $momento;
}
if ($versiculo !== '') {
    $colunas[] = 'idcc_versiculo';
    $valores[] = $versiculo;
}
$res_insert = db_query($conn, "
    INSERT INTO id_conteudo_comentario (" . implode(', ', $colunas) . ")
    VALUES (" . implode(', ', array_fill(0, count($valores), '?')) . ")
    RETURNING idcc_id
", $valores);
$inserido = $res_insert ? mysqli_fetch_assoc($res_insert) : null;

if (!$inserido) {
    $conn->rollBack();
    video_comentarios_responder(500, array('ok' => false, 'erro' => 'Não foi possível enviar o comentário.'));
}

$comentario_id = (int) $inserido['idcc_id'];
$res_linha = mysqli_query($conn, video_comentarios_select_sql($conn, $fiel_id) . "
    WHERE c.idcc_id = $comentario_id
");
$linha = $res_linha ? mysqli_fetch_assoc($res_linha) : null;

if (!db_commit($conn)) {
    video_comentarios_responder(500, array('ok' => false, 'erro' => 'Não foi possível publicar o comentário. Tente de novo.'));
}
desafios_verificar($conn, $fiel_id); // T31: desafios de comentários

video_comentarios_responder(200, array(
    'ok' => true,
    'comentario' => $linha ? video_formatar_comentario($linha, $fiel_id, $eh_admin) : null,
));
