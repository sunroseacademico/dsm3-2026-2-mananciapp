<?php
// Endpoint chamado via fetch() pelo JS do admin antes de cada upload de vídeo
// (ver seção 5 de DEPLOY_RENDER.md).
// Só devolve uma URL pré-assinada de PUT (válida por pouco tempo) depois de
// confirmar que quem está pedindo é um admin com sessão válida. A
// R2_SECRET_ACCESS_KEY nunca sai daqui.
session_start();
header('Content-Type: application/json');
include("../conexao.php");

// Mesmas checagens das páginas do admin (inatividade, revogação, admin
// removido), respondendo JSON.
auth_require_ajax(array('admin'));

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'erro' => 'Método não permitido.'));
    exit;
}

// O JS de admin_conteudos.php / admin_editar_conteudo.php manda o token do
// formulário junto.
if (!csrf_validar()) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Sessão expirada. Atualize a página e tente de novo.'));
    exit;
}

if (!r2_configurado()) {
    http_response_code(502);
    echo json_encode(array('ok' => false, 'erro' => 'R2 não configurado no servidor (defina R2_ACCOUNT_ID, R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY e R2_BUCKET).'));
    exit;
}

$nome_original = basename(site_entrada_texto($_POST['nome'] ?? ''));
if ($nome_original === '') {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'erro' => 'Nome do arquivo não informado.'));
    exit;
}

$extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
// O tipo do arquivo sai da extensão e entra na assinatura: a URL só aceita
// esse Content-Type. Antes, com a URL na mão, dava para gravar qualquer
// coisa (uma página HTML, por exemplo) no lugar do vídeo.
$tipos = array('mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg', 'mov' => 'video/quicktime');
if (!isset($tipos[$extensao])) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'erro' => 'Formato de vídeo não permitido.'));
    exit;
}

// O tamanho também entra na assinatura: o PUT só passa com exatamente esse
// número de bytes. Sem isso, com a URL na mão dava para mandar um arquivo
// de qualquer tamanho (custo no R2). Teto de 5 GB, o máximo de um PUT único.
$tamanho = (int) site_entrada_texto($_POST['tamanho'] ?? '');
$tamanho_maximo = 5 * 1024 * 1024 * 1024;
if ($tamanho <= 0 || $tamanho > $tamanho_maximo) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'erro' => $tamanho > $tamanho_maximo ? 'Vídeo maior que 5 GB. Comprima antes de enviar.' : 'Tamanho do arquivo não informado. Atualize a página e tente de novo.'));
    exit;
}

$nome_limpo = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($nome_original, PATHINFO_FILENAME));
$caminho = 'aulas/' . time() . '_' . $nome_limpo . '.' . $extensao;
// 15 min bastam: a URL só precisa valer para COMEÇAR o envio (um PUT já
// iniciado termina mesmo depois de ela vencer). Menos tempo de validade =
// menos tempo para uma URL vazada ser reaproveitada.
$url_upload = r2_criar_url_assinada('PUT', $caminho, 900, array(), array('content-type' => $tipos[$extensao], 'content-length' => (string) $tamanho));

echo json_encode(array(
    'ok' => true,
    'uploadUrl' => $url_upload,
    // O PUT tem que mandar exatamente este Content-Type (está assinado).
    'contentType' => $tipos[$extensao],
    // É isso que vai pro IDCT_URL: o prefixo diz ao site_resolver_url_video()
    // que o arquivo está no R2.
    'path' => 'r2:' . $caminho,
));
