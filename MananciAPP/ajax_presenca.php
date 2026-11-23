<?php
// "Estou aqui" + lista de pessoas online — chamado por js/presenca.js a
// cada 60s enquanto a aba está visível (área do fiel e admin). Ver seção 39
// de database/bancosupabase.md.
//
// De propósito NÃO usa auth_require()/auth_touch(): eles renovam o
// timeout de inatividade da sessão, e uma aba esquecida aberta não pode
// manter a sessão viva pra sempre só com esse polling. Aqui só conferimos
// se a sessão ainda vale e atualizamos o "visto por último" no banco.
//
// CSRF: em vez de token, exige o corpo em application/json, como
// js/presenca.js manda. Outro site não consegue enviar esse tipo sem uma
// liberação de CORS que o app não dá; antes um POST forjado em text/plain
// passava e gravava uma "página atual" falsa no painel do admin.
require __DIR__ . '/php/conexao.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== 0) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'erro' => 'Requisição inválida.'));
    exit;
}

// Mesmas checagens de auth_require() (inatividade, teto de 12 h, revogação,
// Termo e assinatura), sem renovar a inatividade. Antes faltavam o teto, o
// Termo e a assinatura: quem não tinha aceitado o Termo recebia a lista de
// quem está online.
$problema_sessao = auth_problema_sessao();
if ($problema_sessao !== '') {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'erro' => $problema_sessao === 'sem_login' ? 'Não autenticado.' : 'Sessão encerrada.'));
    exit;
}
if (auth_termos_pendente() || auth_assinatura_bloqueia()) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Acesso indisponível.'));
    exit;
}

$fiel_id = auth_resolve_fiel_id();
if ($fiel_id <= 0) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Usuário inválido.'));
    exit;
}

$eh_admin = ($_SESSION['Usuario_tipo'] ?? '') === 'admin';

$dados = json_decode(file_get_contents('php://input'), true);
$pagina = is_array($dados) ? trim((string) ($dados['pagina'] ?? '')) : '';
$pagina = mb_substr(preg_replace('/\s+/', ' ', $pagina), 0, 120);

// Página vazia (null) mantém a última registrada.
@db_query($conn, "UPDATE ID_FIEL SET IDF_LAST_ACTIVITY = NOW(), idf_ultima_pagina = COALESCE(CAST(? AS TEXT), idf_ultima_pagina) WHERE IDF_ID = ?", array($pagina !== '' ? $pagina : null, $fiel_id));
// auth_touch() não repete esta escrita na próxima página.
$_SESSION['presenca_gravou_em'] = time();

// Quem fica com o app aberto virando a meia-noite também ganha o dia novo.
ofensiva_registrar_acesso($conn, $fiel_id);

// Daqui pra baixo só lê do banco: libera a trava da sessão pra a página que
// o fiel estiver abrindo ao mesmo tempo não ficar esperando esta consulta.
session_write_close();

$pessoas = array();
foreach (presenca_listar_online($conn, 100, !$eh_admin) as $linha) {
    $pessoa = array(
        'nome' => $eh_admin ? $linha['IDF_NOME'] : site_nome_curto($linha['IDF_NOME']),
        'equipe' => !empty($linha['eh_admin']),
        'eu' => (int) $linha['IDF_ID'] === $fiel_id,
    );
    if (!empty($linha['foto_versao'])) {
        $pessoa['foto'] = site_url('php/fiel/foto_perfil.php?id=' . (int) $linha['IDF_ID'] . '&v=' . (int) $linha['foto_versao']);
    }
    // Onde cada um está e há quanto tempo — só pro admin acompanhar.
    if ($eh_admin) {
        $pessoa['id'] = (int) $linha['IDF_ID'];
        $pessoa['pagina'] = (string) ($linha['idf_ultima_pagina'] ?? '');
        $pessoa['segundos'] = max(0, (int) $linha['segundos']);
    }
    $pessoas[] = $pessoa;
}

echo json_encode(array('ok' => true, 'total' => count($pessoas), 'pessoas' => $pessoas));
