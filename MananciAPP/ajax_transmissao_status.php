<?php
// Endpoint público e bem leve — só diz "tem alguma transmissão AO_VIVO
// agora?" (sem título, sem audiência, sem nada de contexto), pra
// js/live-indicator.js decidir se pisca o pontinho "Ao Vivo" do menu em
// TODAS as telas (landing, área do fiel, admin). De propósito não checa
// idtr_audiencia aqui: mesmo uma live "só admins" faz o indicador piscar
// pra todo mundo — é só um aviso genérico de "tem algo ao vivo agora",
// não revela do que se trata.
// Não usa nada da sessão: visitante sem cookie nem abre uma.
define('SITE_SESSAO_OPCIONAL', true);
require __DIR__ . '/php/conexao.php';

// Solta a trava da sessão de quem está logado na hora, senão esta chamada
// (feita em toda página) segura a próxima navegação do mesmo usuário.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/json; charset=utf-8');

// A resposta é a mesma para todo mundo: com 300 pessoas, uma consulta a
// cada 15 s em vez de ~5 por segundo. O admin apaga o cache ao iniciar ou
// encerrar a live, então o ponto acende sem esperar.
$ao_vivo = site_cache_compartilhado('transmissao_ao_vivo', 15, function () use ($conn) {
    $res = mysqli_query($conn, "SELECT 1 FROM id_transmissao WHERE idtr_status = 'AO_VIVO' LIMIT 1");
    return $res ? (mysqli_num_rows($res) > 0 ? 1 : 0) : false;
});

echo json_encode(array('ok' => true, 'ao_vivo' => (bool) $ao_vivo));
