<?php
include("../conexao.php");

header('Content-Type: application/json; charset=utf-8');

// Login, inatividade, revogação, Termo e modo "só assinantes" (JSON 401/403).
auth_require_ajax();
if (!isset($_SESSION['Usuario_logado'])) {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'erro' => 'Não autenticado.'));
    exit;
}

$fiel_id = auth_resolve_fiel_id();
if ($fiel_id <= 0) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Usuário inválido.'));
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true);
if (!is_array($dados)) {
    $dados = $_POST;
}

// O corpo chega como JSON (não como $_POST tradicional), por isso passamos
// o token explicitamente pra csrf_validar() em vez de deixar ela olhar
// $_POST sozinha.
// 403 e não 419: o Apache não conhece 419 (status não padrão do Laravel) e
// devolvia 500 pro navegador no lugar.
if (!csrf_validar($dados['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Sessão expirada. Atualize a página e tente de novo.'));
    exit;
}

$transmissao_id = isset($dados['idtr_id']) ? (int) $dados['idtr_id'] : 0;
$mensagem = trim((string) ($dados['mensagem'] ?? ''));

if ($transmissao_id <= 0) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'erro' => 'Transmissão inválida.'));
    exit;
}

if ($mensagem === '') {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'erro' => 'Escreva algo antes de enviar.'));
    exit;
}

if (mb_strlen($mensagem) > 500) {
    $mensagem = mb_substr($mensagem, 0, 500);
}

// Tudo dentro de uma transação, travada por um advisory lock de Postgres
// específico desse par (transmissão, fiel): se o mesmo fiel disparar duas
// requisições quase juntas (double-tap, retry de rede antes do botão
// desabilitar no navegador), a segunda espera a primeira terminar (e seu
// INSERT já ter sido commitado) antes de rodar sua própria checagem de
// "mandou há menos de 5s" — sem o lock, as duas podiam ler "nada recente"
// ao mesmo tempo e furar o limite de 1 mensagem a cada 5s.
$conn->beginTransaction();

db_query($conn, "SELECT pg_advisory_xact_lock(hashtext(CAST(? AS TEXT)))", array($transmissao_id . ':' . $fiel_id));

// Uma consulta só: status/audiência da transmissão + status atual do fiel
// (pra cobrir o caso de a sessão continuar válida depois que o cadastro
// foi reprovado/desativado — mesma exigência já aplicada em outras rotas,
// ex. admin_enviar_mensagem.php) + se ele mandou algo nos últimos 5s + se
// está bloqueado do chat.
$res_check = mysqli_query($conn, "
    SELECT
        t.idtr_status,
        t.idtr_audiencia,
        f.IDF_STATUS AS fiel_status,
        f.IDF_ATIVO AS fiel_ativo,
        EXISTS(
            SELECT 1 FROM id_transmissao_comentario
            WHERE idtr_id = t.idtr_id AND idf_id = $fiel_id
              AND idtc_criado_em > NOW() - INTERVAL '5 seconds'
        ) AS enviou_recente,
        -- Mesma regra de transmissao_fiel_bloqueado_chat() (conexao.php) —
        -- embutida aqui (em vez de chamar a função, que faria outra
        -- consulta) pra ficar tudo numa única ida ao banco nesse caminho
        -- crítico. Se a regra mudar, atualize as duas.
        EXISTS(
            SELECT 1 FROM id_fiel_bloqueio_chat
            WHERE idf_id = $fiel_id AND (idbc_expira_em IS NULL OR idbc_expira_em > NOW())
        ) AS bloqueado
    FROM id_transmissao t
    CROSS JOIN ID_FIEL f
    WHERE t.idtr_id = $transmissao_id AND f.IDF_ID = $fiel_id
    LIMIT 1
");
$check = $res_check ? mysqli_fetch_assoc($res_check) : null;

if (!$check) {
    $conn->rollBack();
    http_response_code(404);
    echo json_encode(array('ok' => false, 'erro' => 'Transmissão não encontrada.'));
    exit;
}

if ($check['idtr_status'] !== 'AO_VIVO') {
    $conn->rollBack();
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Essa transmissão não está mais recebendo mensagens.'));
    exit;
}

if ($check['idtr_audiencia'] === 'ADMINS' && ($_SESSION['Usuario_tipo'] ?? '') !== 'admin') {
    $conn->rollBack();
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Essa transmissão é restrita à equipe administrativa.'));
    exit;
}

if ($check['fiel_status'] !== 'aprovado' || (int) $check['fiel_ativo'] !== 1) {
    $conn->rollBack();
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Sua conta não está mais aprovada para comentar.'));
    exit;
}

if (!empty($check['bloqueado'])) {
    $conn->rollBack();
    http_response_code(403);
    // "bloqueado" faz o JS travar a caixa do chat na hora (ver
    // transmissao_script.php), sem esperar o próximo poll.
    $bloqueio_chat = transmissao_fiel_bloqueio_chat_info($conn, $fiel_id);
    echo json_encode(array(
        'ok' => false,
        'bloqueado' => true,
        'erro' => transmissao_texto_bloqueio_chat($bloqueio_chat ?: array()),
    ));
    exit;
}

if (!empty($check['enviou_recente'])) {
    $conn->rollBack();
    http_response_code(429);
    echo json_encode(array('ok' => false, 'erro' => 'Aguarde alguns segundos antes de enviar de novo.'));
    exit;
}

$res_insert = db_query($conn, "
    INSERT INTO id_transmissao_comentario (idtr_id, idf_id, idtc_mensagem)
    VALUES (?, ?, ?)
    RETURNING idtc_id, idtc_criado_em
", array($transmissao_id, $fiel_id, $mensagem));
$linha_inserida = $res_insert ? mysqli_fetch_assoc($res_insert) : null;

if (!$linha_inserida) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(array('ok' => false, 'erro' => 'Não foi possível enviar a mensagem.'));
    exit;
}

$res_nome = mysqli_query($conn, "SELECT IDF_NOME FROM ID_FIEL WHERE IDF_ID = $fiel_id LIMIT 1");
$linha_nome = $res_nome ? mysqli_fetch_assoc($res_nome) : null;

if (!db_commit($conn)) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'erro' => 'Não foi possível enviar a mensagem. Tente de novo.'));
    exit;
}

// Os outros veem a mensagem no próximo poll, sem esperar o cache vencer.
// Vence em vez de apagar: um só pedido regera a lista e os outros recebem
// a cópia anterior nesse meio-tempo (apagando, todos iam ao banco juntos).
site_cache_vencer('transmissao_chat_' . $transmissao_id);

echo json_encode(array(
    'ok' => true,
    'comentario' => array(
        'id' => (int) $linha_inserida['idtc_id'],
        'nome' => site_nome_curto($linha_nome['IDF_NOME'] ?? ''),
        'mensagem' => $mensagem,
        // Mesmo formato do polling (antes ia o timestamp cru do banco).
        'criado_em' => site_data_hora($linha_inserida['idtc_criado_em'], 'd/m H:i'),
    ),
));
