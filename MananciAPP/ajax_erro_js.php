<?php
// Recebe os erros de JavaScript das páginas (js/erros.js) e manda para o
// Sentry pelo mesmo site_alerta_erro() do PHP: a chave do Sentry fica no
// servidor e a CSP não precisa liberar o script deles. Só funciona com
// SENTRY_DSN no Render (sem ela, o js/erros.js nem é carregado).
//
// Contra abuso (alguém gastando a cota do Sentry de propósito): só aceita
// pedido do próprio site, no máximo 10 por 10 min por IP (mesma tabela dos
// limites de login) e textos cortados. Sempre responde 204, sem detalhe.
define('SITE_SESSAO_OPCIONAL', true);
require __DIR__ . '/php/conexao.php';
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

http_response_code(204);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !site_alerta_dsn() || !$conn) {
    exit;
}
// Mesmo site: o Origin (ou o Referer) tem que ser o próprio host.
$origem = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
if ($origem === '' || parse_url($origem, PHP_URL_HOST) !== parse_url('https://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    exit;
}

$chave = mb_substr('jserro:' . site_ip_chave_limite(site_ip_cliente()), 0, 100);
$res = db_query($conn, "SELECT COUNT(*) AS n FROM ID_LOGIN_TENTATIVA WHERE IDLT_EMAIL = ? AND IDLT_CRIADO_EM > NOW() - INTERVAL '10 minutes'", array($chave));
if (!$res || (int) (mysqli_fetch_assoc($res)['n'] ?? 0) >= 10) {
    exit;
}
db_query($conn, "INSERT INTO ID_LOGIN_TENTATIVA (IDLT_EMAIL) VALUES (?)", array($chave));

$dados = json_decode((string) file_get_contents('php://input', false, null, 0, 8192), true);
if (!is_array($dados)) {
    exit;
}
$texto = function ($valor, $limite) {
    return mb_substr(trim(is_scalar($valor) ? (string) $valor : ''), 0, $limite);
};
$mensagem = $texto($dados['mensagem'] ?? '', 500);
if ($mensagem === '') {
    exit;
}
// Só o caminho da página e do arquivo (sem a query string, que pode ter token).
$pagina = (string) strtok($texto($dados['pagina'] ?? '', 300), '?');
$arquivo = (string) strtok($texto($dados['arquivo'] ?? '', 300), '?');
$navegador = site_aparelho_descricao($_SERVER['HTTP_USER_AGENT'] ?? '');

site_alerta_erro('error', 'JS: ' . $mensagem . ' (' . $navegador . ')', 'ErroJavaScript', $arquivo, (int) ($dados['linha'] ?? 0), $pagina);
