<?php
// Dados públicos da igreja para a página inicial (index.html,
// js/igreja-publica.js): nome, frase, horários dos cultos, endereço, mapa,
// contatos e redes, de Admin → Identidade da igreja (igreja_publica() em
// php/conexao.php). Sem CNPJ nem razão social. Igual para todos: 10 min de
// cache no servidor (o admin apaga ao salvar) e 5 min no navegador; visitante
// não abre sessão.
define('SITE_SESSAO_OPCIONAL', true);
require __DIR__ . '/php/conexao.php';
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$igreja = $conn ? site_cache_compartilhado('igreja_publica', 600, function () {
    return igreja_publica();
}) : false;

if (!is_array($igreja)) {
    http_response_code(503);
    echo json_encode(array('ok' => false));
    exit;
}
echo json_encode(array('ok' => true, 'igreja' => $igreja), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
