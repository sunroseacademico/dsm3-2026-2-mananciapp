<?php
// Voto "Edificante" nos testemunhos (T26), usado no mural
// (php/fiel/testemunhos.php) e no bloco da home. Ver "MURAL DE TESTEMUNHOS"
// em php/conexao.php e a seção 57 de database/bancosupabase.md.
//   POST {acao:'votar', idts_id, votar: true|false, csrf_token}
include("../conexao.php");

header('Content-Type: application/json; charset=utf-8');

function testemunhos_responder($status, $dados) {
    http_response_code($status);
    echo json_encode($dados);
    exit;
}

// Login, inatividade, revogação, Termo e modo "só assinantes" (JSON 401/403).
auth_require_ajax();
if (!isset($_SESSION['Usuario_logado'])) {
    testemunhos_responder(401, array('ok' => false, 'erro' => 'Não autenticado.'));
}
$fiel_id = auth_resolve_fiel_id();
if ($fiel_id <= 0) {
    testemunhos_responder(403, array('ok' => false, 'erro' => 'Usuário inválido.'));
}
if (!testemunhos_disponivel($conn)) {
    testemunhos_responder(503, array('ok' => false, 'erro' => 'Mural indisponível no momento.'));
}

$dados = json_decode(file_get_contents('php://input'), true);
if (!is_array($dados)) {
    $dados = $_POST;
}
// 403 e não 419: o Apache não conhece 419 e devolveria 500.
if (!csrf_validar($dados['csrf_token'] ?? null)) {
    testemunhos_responder(403, array('ok' => false, 'erro' => 'Sessão expirada. Atualize a página e tente de novo.'));
}
if (($dados['acao'] ?? '') !== 'votar') {
    testemunhos_responder(400, array('ok' => false, 'erro' => 'Ação inválida.'));
}

$resultado = testemunho_votar($conn, (int) ($dados['idts_id'] ?? 0), $fiel_id, !empty($dados['votar']));
testemunhos_responder($resultado['ok'] ? 200 : 400, $resultado);
