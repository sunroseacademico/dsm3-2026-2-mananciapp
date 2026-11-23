<?php
// Chamado via fetch() por php/includes/pwa_head.php, só depois que
// OneSignal.login() resolve no navegador (só nesse momento o dispositivo já
// está de fato vinculado ao IDF_ID do fiel, pronto pra receber push
// direcionado). Ver notificacoes_push_enviar_pendentes() em php/conexao.php.
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

// O envio chama a API da OneSignal (uma chamada por notificação pendente) e
// não usa a sessão: solta a trava antes, senão a página seguinte do fiel
// espera esses envios terminarem.
session_write_close();
notificacoes_push_enviar_pendentes($conn, $fiel_id);

echo json_encode(array('ok' => true));
