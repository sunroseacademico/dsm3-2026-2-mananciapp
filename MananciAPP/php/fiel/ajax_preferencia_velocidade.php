<?php
// Grava em ID_PREFERENCIA.IDPREF_VELOCIDADE_VIDEO a velocidade escolhida no
// seletor do player (php/fiel/assistir_video.php). Ela passa a valer em
// todas as aulas, em qualquer aparelho.
// Corpo JSON: {"velocidade": 1.5, "csrf_token": "..."}.
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
if (!is_array($dados) || !isset($dados['velocidade']) || !is_numeric($dados['velocidade'])) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'erro' => 'Requisição inválida.'));
    exit;
}

// Mesmo motivo de ajax_preferencia_push.php: corpo JSON, então o token vai
// explícito; 403 (e não 419) porque o Apache não conhece 419.
if (!csrf_validar($dados['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Sessão expirada. Atualize a página e tente de novo.'));
    exit;
}

// Só aceita as velocidades do seletor — nada de 0 ou 16x vindo do console.
$velocidade = (float) $dados['velocidade'];
if (!in_array($velocidade, VIDEO_VELOCIDADES, true)) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'erro' => 'Velocidade inválida.'));
    exit;
}

// Só mexe na coluna da velocidade — numa linha nova, e-mail e push ficam no
// padrão (1), o mesmo que configuracoes.php assume sem preferência salva.
$ok = mysqli_query($conn, "
    INSERT INTO ID_PREFERENCIA (IDF_ID, IDPREF_NOTIFICACOES_EMAIL, IDPREF_NOTIFICACOES_PUSH, IDPREF_VELOCIDADE_VIDEO)
    VALUES ($fiel_id, 1, 1, $velocidade)
    ON CONFLICT (IDF_ID) DO UPDATE SET
        IDPREF_VELOCIDADE_VIDEO = EXCLUDED.IDPREF_VELOCIDADE_VIDEO,
        IDPREF_ATUALIZADO_EM = CURRENT_TIMESTAMP
");

if (!$ok) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'erro' => 'Não foi possível salvar a preferência.'));
    exit;
}

echo json_encode(array('ok' => true, 'velocidade' => $velocidade));
