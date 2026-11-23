<?php
// Aba "Orações" da aula (T28), em php/fiel/assistir_video.php. Ver "PEDIDOS
// DE ORAÇÃO" em php/conexao.php e a seção 59 de database/bancosupabase.md.
//   GET  ?filtro=F&pular=N -> página de pedidos (F: abertos, atendidos,
//        meus), o total do filtro, se há mais e o total de abertos
//   POST {acao:'pedir', idct_id, texto, anonimo, csrf_token}
//   POST {acao:'amem', idor_id, amem: true|false, csrf_token}
//   POST {acao:'atendida', idor_id, resposta, csrf_token}  (só o autor)
//   POST {acao:'remover', idor_id, csrf_token}  (autor ou admin)
include("../conexao.php");

header('Content-Type: application/json; charset=utf-8');

function oracoes_responder($status, $dados) {
    http_response_code($status);
    echo json_encode($dados);
    exit;
}

// Login, inatividade, revogação, Termo e modo "só assinantes" (JSON 401/403).
auth_require_ajax();
if (!isset($_SESSION['Usuario_logado'])) {
    oracoes_responder(401, array('ok' => false, 'erro' => 'Sua sessão expirou. Entre de novo.'));
}
$fiel_id = auth_resolve_fiel_id();
if ($fiel_id <= 0) {
    oracoes_responder(403, array('ok' => false, 'erro' => 'Usuário inválido.'));
}
if (!oracoes_disponivel($conn)) {
    oracoes_responder(503, array('ok' => false, 'erro' => 'Os pedidos de oração ainda não estão disponíveis.'));
}
// Moderação só com a tela de Testemunhos ou de Gerenciar Vídeos.
$eh_admin = admin_pode_moderar_comunidade();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $filtro = $_GET['filtro'] ?? 'abertos';
    if (!isset(oracoes_filtros()[$filtro])) {
        $filtro = 'abertos';
    }
    $pular = isset($_GET['pular']) ? max(0, (int) $_GET['pular']) : 0;
    // Busca um a mais só para saber se ainda sobra pedido depois desta página.
    $pagina = oracoes_listar($conn, $filtro, ORACOES_POR_PAGINA + 1, $pular);
    oracoes_responder(200, array(
        'ok' => true,
        'oracoes' => array_slice($pagina, 0, ORACOES_POR_PAGINA),
        'tem_mais' => count($pagina) > ORACOES_POR_PAGINA,
        'total_filtro' => oracoes_contar($conn, $filtro),
        'total_abertos' => oracoes_contar($conn, 'abertos'),
    ));
}

$dados = json_decode(file_get_contents('php://input'), true);
if (!is_array($dados)) {
    $dados = $_POST;
}
// 403 e não 419: o Apache não conhece 419 e devolveria 500.
if (!csrf_validar($dados['csrf_token'] ?? null)) {
    oracoes_responder(403, array('ok' => false, 'erro' => 'Sessão expirada. Atualize a página e tente de novo.'));
}

$acao = $dados['acao'] ?? '';
$oracao_id = (int) ($dados['idor_id'] ?? 0);

if ($acao === 'pedir') {
    $conteudo_id = (int) ($dados['idct_id'] ?? 0);
    if (!video_conteudo_acessivel($conn, $conteudo_id)) {
        $conteudo_id = 0;
    }
    $resultado = oracao_pedir($conn, $fiel_id, $conteudo_id, $dados['texto'] ?? '', !empty($dados['anonimo']));
    oracoes_responder($resultado['ok'] ? 200 : 400, $resultado);
}

if ($acao === 'amem') {
    $resultado = oracao_amem($conn, $oracao_id, $fiel_id, !empty($dados['amem']));
    if ($resultado['ok'] && !empty($dados['amem'])) {
        medalhas_apos_acao($conn, $fiel_id, array('amens_dados', 'amens_e_atendidos')); // T30
        desafios_verificar($conn, $fiel_id); // T31
    }
    oracoes_responder($resultado['ok'] ? 200 : 400, $resultado);
}

if ($acao === 'atendida') {
    $resultado = oracao_marcar_atendida($conn, $oracao_id, $fiel_id, $dados['resposta'] ?? '');
    if ($resultado['ok']) {
        medalhas_apos_oracao_atendida($conn, $oracao_id); // T30
    }
    oracoes_responder($resultado['ok'] ? 200 : 400, $resultado);
}

if ($acao === 'remover') {
    $ok = oracao_remover($conn, $oracao_id, $fiel_id, $eh_admin);
    if ($ok && $eh_admin) {
        admin_log($conn, 'comunidade.remover', 'oracao', $oracao_id, array());
    }
    oracoes_responder($ok ? 200 : 404, $ok ? array('ok' => true) : array('ok' => false, 'erro' => 'Pedido não encontrado.'));
}

oracoes_responder(400, array('ok' => false, 'erro' => 'Ação inválida.'));
