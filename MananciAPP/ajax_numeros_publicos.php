<?php
// Números reais da página inicial pública (index.html, js/numeros-publicos.js):
// cursos publicados, aulas publicadas, horas de conteúdo e tamanho da
// comunidade. Antes os cartões diziam "+20 estudos", "100% acolhedor" e
// "24/7", inventados. Público e igual para todos: 10 min de cache no servidor
// e no navegador, e visitante não abre sessão.
// A comunidade só aparece a partir de 30 pessoas (número pequeno passa a
// impressão contrária); no lugar, as horas de conteúdo.
define('SITE_SESSAO_OPCIONAL', true);
require __DIR__ . '/php/conexao.php';
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=600');

$numeros = $conn ? site_cache_compartilhado('numeros_publicos', 600, function () use ($conn) {
    return numeros_publicos($conn);
}) : false;

if (!is_array($numeros)) {
    http_response_code(503);
    echo json_encode(array('ok' => false));
    exit;
}
echo json_encode(array('ok' => true, 'cartoes' => numeros_publicos_cartoes($numeros)));
