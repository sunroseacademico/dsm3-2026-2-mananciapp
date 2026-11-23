<?php
// Grava em ID_PREFERENCIA.IDPREF_NOTIFICACOES_PUSH a escolha do fiel sobre
// push, sempre que ela muda no navegador:
//   - no convite da OneSignal (Permitir / Mais tarde) — ver pwa_head.php;
//   - no switch "Receber notificações push" de configuracoes.php.
// Corpo JSON: {"ativo": true|false, "csrf_token": "..."}.
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
if (!is_array($dados) || !array_key_exists('ativo', $dados)) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'erro' => 'Requisição inválida.'));
    exit;
}

// Mesmo motivo de ajax_transmissao_comentar.php: corpo JSON, então o token
// vai explícito; 403 (e não 419) porque o Apache não conhece 419.
if (!csrf_validar($dados['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Sessão expirada. Atualize a página e tente de novo.'));
    exit;
}

$ativo = !empty($dados['ativo']) ? 1 : 0;

// Só mexe na coluna de push — numa linha nova, o e-mail fica no padrão (1),
// o mesmo que configuracoes.php assume quando ainda não há preferência salva.
$ok = mysqli_query($conn, "
    INSERT INTO ID_PREFERENCIA (IDF_ID, IDPREF_NOTIFICACOES_EMAIL, IDPREF_NOTIFICACOES_PUSH)
    VALUES ($fiel_id, 1, $ativo)
    ON CONFLICT (IDF_ID) DO UPDATE SET
        IDPREF_NOTIFICACOES_PUSH = EXCLUDED.IDPREF_NOTIFICACOES_PUSH
");

if (!$ok) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'erro' => 'Não foi possível salvar a preferência.'));
    exit;
}

echo json_encode(array('ok' => true, 'ativo' => (bool) $ativo));
