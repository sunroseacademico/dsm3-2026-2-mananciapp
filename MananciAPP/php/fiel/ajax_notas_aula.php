<?php
// Aba "Minhas notas" da aula (T27), em php/fiel/assistir_video.php. Ver
// "MINHAS NOTAS NA AULA" em php/conexao.php e a seção 58 de
// database/bancosupabase.md. Só mexe nas notas do próprio fiel.
//   POST {acao:'criar', idct_id, texto, [momento], csrf_token}
//   POST {acao:'editar', idan_id, texto, [momento], csrf_token}
//   POST {acao:'excluir', idan_id, csrf_token}
// momento: segundos da aula, ou null para nota sem minuto.
include("../conexao.php");

header('Content-Type: application/json; charset=utf-8');

function notas_aula_responder($status, $dados) {
    http_response_code($status);
    echo json_encode($dados);
    exit;
}

// Login, inatividade, revogação, Termo e modo "só assinantes" (JSON 401/403).
auth_require_ajax();
if (!isset($_SESSION['Usuario_logado'])) {
    notas_aula_responder(401, array('ok' => false, 'erro' => 'Sua sessão expirou. Entre de novo para salvar a nota.'));
}
$fiel_id = auth_resolve_fiel_id();
if ($fiel_id <= 0) {
    notas_aula_responder(403, array('ok' => false, 'erro' => 'Usuário inválido.'));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    notas_aula_responder(405, array('ok' => false, 'erro' => 'Método não permitido.'));
}
if (!notas_aula_disponivel($conn)) {
    notas_aula_responder(503, array('ok' => false, 'erro' => 'As notas ainda não estão disponíveis.'));
}

$dados = json_decode(file_get_contents('php://input'), true);
if (!is_array($dados)) {
    $dados = $_POST;
}
// 403 e não 419: o Apache não conhece 419 e devolveria 500.
if (!csrf_validar($dados['csrf_token'] ?? null)) {
    notas_aula_responder(403, array('ok' => false, 'erro' => 'Sessão expirada. Atualize a página e tente de novo.'));
}

$acao = $dados['acao'] ?? '';
$momento = array_key_exists('momento', $dados) ? $dados['momento'] : null;

if ($acao === 'criar') {
    $conteudo_id = (int) ($dados['idct_id'] ?? 0);
    if (!video_conteudo_acessivel($conn, $conteudo_id)) {
        notas_aula_responder(404, array('ok' => false, 'erro' => 'Aula não encontrada.'));
    }
    $resultado = nota_aula_criar($conn, $fiel_id, $conteudo_id, $dados['texto'] ?? '', $momento);
    notas_aula_responder($resultado['ok'] ? 200 : 400, $resultado);
}

if ($acao === 'editar') {
    $resultado = nota_aula_editar($conn, $fiel_id, (int) ($dados['idan_id'] ?? 0), $dados['texto'] ?? '', $momento);
    notas_aula_responder($resultado['ok'] ? 200 : 400, $resultado);
}

if ($acao === 'excluir') {
    $ok = nota_aula_excluir($conn, $fiel_id, (int) ($dados['idan_id'] ?? 0));
    notas_aula_responder($ok ? 200 : 404, $ok ? array('ok' => true) : array('ok' => false, 'erro' => 'Nota não encontrada.'));
}

notas_aula_responder(400, array('ok' => false, 'erro' => 'Ação inválida.'));
