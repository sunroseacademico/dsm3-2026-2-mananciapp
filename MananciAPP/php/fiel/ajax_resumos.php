<?php
// Aba "Resumos" da aula (T29), em php/fiel/assistir_video.php. Ver
// "RESUMOS DAS AULAS" em php/conexao.php e a seção 60 de
// database/bancosupabase.md.
//   GET  ?idct_id=N&ordem=O&pular=N -> página de resumos da aula (sem o
//        de quem vê), se há mais e o total publicado. O: votados, recentes
//   POST {acao:'publicar', idct_id, texto, csrf_token}
//   POST {acao:'editar', idrs_id, texto, csrf_token}  (só o autor)
//   POST {acao:'votar', idrs_id, votar: true|false, csrf_token}
//   POST {acao:'remover', idrs_id, csrf_token}  (autor ou admin)
include("../conexao.php");

header('Content-Type: application/json; charset=utf-8');

function resumos_responder($status, $dados) {
    http_response_code($status);
    echo json_encode($dados);
    exit;
}

// Login, inatividade, revogação, Termo e modo "só assinantes" (JSON 401/403).
auth_require_ajax();
if (!isset($_SESSION['Usuario_logado'])) {
    resumos_responder(401, array('ok' => false, 'erro' => 'Sua sessão expirou. Entre de novo.'));
}
$fiel_id = auth_resolve_fiel_id();
if ($fiel_id <= 0) {
    resumos_responder(403, array('ok' => false, 'erro' => 'Usuário inválido.'));
}
if (!resumos_disponivel($conn)) {
    resumos_responder(503, array('ok' => false, 'erro' => 'Os resumos ainda não estão disponíveis.'));
}
// Moderação só com a tela de Testemunhos ou de Gerenciar Vídeos.
$eh_admin = admin_pode_moderar_comunidade();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $conteudo_id = isset($_GET['idct_id']) ? (int) $_GET['idct_id'] : 0;
    if (!video_conteudo_acessivel($conn, $conteudo_id)) {
        resumos_responder(404, array('ok' => false, 'erro' => 'Aula não encontrada.'));
    }
    $ordem = ($_GET['ordem'] ?? '') === 'recentes' ? 'recentes' : 'votados';
    $pular = isset($_GET['pular']) ? max(0, (int) $_GET['pular']) : 0;
    // Busca um a mais só para saber se ainda sobra resumo depois desta página.
    $pagina = resumos_listar($conn, $conteudo_id, $ordem, RESUMOS_POR_PAGINA + 1, $pular);
    resumos_responder(200, array(
        'ok' => true,
        'resumos' => array_slice($pagina, 0, RESUMOS_POR_PAGINA),
        'tem_mais' => count($pagina) > RESUMOS_POR_PAGINA,
        'total' => resumos_contar($conn, $conteudo_id),
    ));
}

$dados = json_decode(file_get_contents('php://input'), true);
if (!is_array($dados)) {
    $dados = $_POST;
}
// 403 e não 419: o Apache não conhece 419 e devolveria 500.
if (!csrf_validar($dados['csrf_token'] ?? null)) {
    resumos_responder(403, array('ok' => false, 'erro' => 'Sessão expirada. Atualize a página e tente de novo.'));
}

$acao = $dados['acao'] ?? '';
$resumo_id = (int) ($dados['idrs_id'] ?? 0);

if ($acao === 'publicar') {
    $conteudo_id = (int) ($dados['idct_id'] ?? 0);
    if (!video_conteudo_acessivel($conn, $conteudo_id)) {
        resumos_responder(404, array('ok' => false, 'erro' => 'Aula não encontrada.'));
    }
    $resultado = resumo_publicar($conn, $fiel_id, $conteudo_id, $dados['texto'] ?? '');
    if ($resultado['ok']) {
        medalhas_apos_acao($conn, $fiel_id, array('resumos_publicados')); // T30
        desafios_verificar($conn, $fiel_id); // T31
    }
    resumos_responder($resultado['ok'] ? 200 : 400, $resultado);
}

if ($acao === 'editar') {
    $resultado = resumo_editar($conn, $fiel_id, $resumo_id, $dados['texto'] ?? '');
    resumos_responder($resultado['ok'] ? 200 : 400, $resultado);
}

if ($acao === 'votar') {
    $resultado = resumo_votar($conn, $resumo_id, $fiel_id, !empty($dados['votar']));
    if ($resultado['ok'] && !empty($dados['votar'])) {
        medalhas_apos_acao($conn, 0, array(), $resultado['autor_id'], array('resumo_votos')); // T30
    }
    unset($resultado['autor_id']);
    resumos_responder($resultado['ok'] ? 200 : 400, $resultado);
}

if ($acao === 'remover') {
    $ok = resumo_remover($conn, $resumo_id, $fiel_id, $eh_admin);
    if ($ok && $eh_admin) {
        admin_log($conn, 'comunidade.remover', 'resumo', $resumo_id, array());
    }
    resumos_responder($ok ? 200 : 404, $ok ? array('ok' => true) : array('ok' => false, 'erro' => 'Resumo não encontrado.'));
}

resumos_responder(400, array('ok' => false, 'erro' => 'Ação inválida.'));
