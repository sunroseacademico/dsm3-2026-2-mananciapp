<?php
// Registra que o fiel está usando o app instalado (aberto pelo ícone, modo
// standalone): php/includes/pwa_head.php chama uma vez por dia por sessão.
// Marca o dia em id_fiel_acesso_dia e concede a medalha "No bolso" — ver
// "USO PELO APP INSTALADO" em php/conexao.php e a seção 79 do bancosupabase.md.
// Corpo JSON: {"csrf_token": "..."}.
include("../conexao.php");

header('Content-Type: application/json; charset=utf-8');

// Login, inatividade, revogação, Termo e modo "só assinantes" (JSON 401/403).
auth_require_ajax();

$fiel_id = auth_resolve_fiel_id();
if ($fiel_id <= 0) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Usuário inválido.'));
    exit;
}

// Mesmo motivo de ajax_preferencia_push.php: corpo JSON, então o token vai
// explícito; 403 (e não 419) porque o Apache não conhece 419.
$dados = json_decode(file_get_contents('php://input'), true);
if (!csrf_validar(is_array($dados) ? ($dados['csrf_token'] ?? null) : null)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Sessão expirada.'));
    exit;
}

// Já registrado hoje nesta sessão: nada a fazer (outra aba mandou antes).
if (acesso_app_registrado_hoje($fiel_id)) {
    echo json_encode(array('ok' => true));
    exit;
}

if (!acesso_app_registrar($conn, $fiel_id)) {
    // Sem a seção 79 ou erro de banco: a próxima página tenta de novo.
    http_response_code(503);
    echo json_encode(array('ok' => false));
    exit;
}

echo json_encode(array('ok' => true));
