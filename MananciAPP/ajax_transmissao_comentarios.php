<?php
// Endpoint público (sem auth_require) — a leitura do chat é pública, só o
// ato de enviar mensagem exige login (ver php/fiel/ajax_transmissao_comentar.php).
// Usado pelo polling de ao-vivo.php.
// Visitante sem cookie não abre sessão (ver SITE_SESSAO_OPCIONAL).
define('SITE_SESSAO_OPCIONAL', true);
// Quem está logado também não abre a sessão em todo poll: ela (e o bloqueio
// do chat) só é conferida quando o JS pede com ?b=1, a cada ~30 s. Com 300
// pessoas, eram ~4 consultas de sessão por pessoa a cada 5 s só para isso.
// Live restrita (só fiéis/admins) abre a sessão sempre, mais abaixo.
if (empty($_GET['b'])) {
    define('SITE_SESSAO_PULAR', true);
}
require __DIR__ . '/php/conexao.php';

header('Content-Type: application/json; charset=utf-8');

$transmissao_id = isset($_GET['idtr_id']) ? (int) $_GET['idtr_id'] : 0;
if ($transmissao_id <= 0) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'erro' => 'Transmissão inválida.'));
    exit;
}

// Mesma checagem de audiência da página (idtr_audiencia) — sem isso, uma
// transmissão "Apenas admins"/"Somente fiéis" continuaria vazando o texto
// do chat pra qualquer um que chamasse esse endpoint direto, mesmo com o
// vídeo/chat escondidos na tela.
// Só a transmissão que as páginas mostram (a atual), e só ao vivo ou
// encerrada: antes, trocando o idtr_id, qualquer um lia o chat de todas as
// lives antigas.
$transmissao = transmissao_obter_atual_cache($conn);
if ($transmissao && in_array($transmissao['idtr_audiencia'] ?? 'TODOS', array('ADMINS', 'FIEIS'), true)
    && session_status() !== PHP_SESSION_ACTIVE && isset($_COOKIE[session_name()])) {
    session_start();
}
if (!$transmissao || (int) $transmissao['idtr_id'] !== $transmissao_id
    || !in_array($transmissao['idtr_status'], array('AO_VIVO', 'ENCERRADA'), true)
    || !transmissao_pode_assistir($transmissao)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Você não tem acesso a essa transmissão.'));
    exit;
}

// Daqui em diante só lê a sessão: solta a trava dela, senão este polling
// (a cada 5 s) segura a próxima navegação do mesmo usuário. O id do fiel é
// resolvido antes, porque auth_resolve_fiel_id() pode gravar na sessão.
$fiel_logado_id = isset($_SESSION['Usuario_logado']) ? auth_resolve_fiel_id() : 0;
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Devolve sempre o conjunto atual de mensagens visíveis (não só as novas)
// — assim, se o admin ocultar uma mensagem durante a live, ela some da
// tela de quem está assistindo no próximo poll também, não só de quem
// abrir a página depois. O JS do lado do cliente reconcilia por id (só
// mexe no DOM do que realmente mudou), então isso não recria o chat
// inteiro a cada 5s.
// A lista é a mesma para todo mundo: montada uma vez a cada 2 s e servida
// do cache (transmissao_chat_lista()). O envio de mensagem vence o cache
// e a moderação o apaga, e o JS não tira da tela uma mensagem mais nova que a
// última da resposta (a do próprio autor, recém-enviada).
// "versao" resume a lista: o JS manda a última que recebeu (?v=) e, sem
// mudança, a resposta é só {"igual": true} em vez das 100 mensagens.
$chat = transmissao_chat_lista($conn, $transmissao_id);
$comentarios = $chat['lista'];
$versao = $chat['versao'];

$resposta = ($versao !== '' && (string) ($_GET['v'] ?? '') === $versao)
    ? array('ok' => true, 'igual' => true, 'versao' => $versao)
    : array('ok' => true, 'versao' => $versao, 'comentarios' => $comentarios);

// Pra quem está logado, diz também se ele está bloqueado no chat — assim a
// caixa de mensagem trava (ou destrava) sozinha no próximo poll quando o
// admin bloquear/desbloquear durante a live, sem precisar recarregar.
if (isset($_SESSION['Usuario_logado'])) {
    $bloqueio_chat = transmissao_fiel_bloqueio_chat_info($conn, $fiel_logado_id);
    $resposta['bloqueio'] = $bloqueio_chat
        ? array('bloqueado' => true, 'texto' => transmissao_texto_bloqueio_chat($bloqueio_chat))
        : array('bloqueado' => false);
}

echo json_encode($resposta);
