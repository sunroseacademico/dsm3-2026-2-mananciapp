<?php
// Página inicial: entrega o index.html (estático) com a imagem da prévia do
// link (WhatsApp, Facebook) preenchida com o endereço completo, que o Open
// Graph exige e o HTML estático não sabe. Não inclui php/conexao.php de
// propósito: o index.html usa scripts embutidos sem nonce e continua com a
// CSP do .htaccess, como antes. Endereço: SITE_URL_PUBLICA (Render); sem
// ela, o próprio host da requisição.
$base = rtrim(trim((string) getenv('SITE_URL_PUBLICA')), '/');
if ($base === '' || !preg_match('#^https://[A-Za-z0-9.-]+(:\d+)?$#', $base)) {
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    $base = ($host !== '' ? 'https://' . $host : '');
}
$imagem = $base . '/assets/img/compartilhar.jpg';
$metas = '<meta property="og:image" content="' . htmlspecialchars($imagem) . '">' . "\n"
    . '    <meta property="og:image:width" content="1200">' . "\n"
    . '    <meta property="og:image:height" content="630">' . "\n"
    . '    <meta property="og:url" content="' . htmlspecialchars($base . '/') . '">' . "\n"
    . '    <meta name="twitter:card" content="summary_large_image">';

// Mesma versão dos CSS/JS das páginas PHP (site_versao_estatico(), em
// php/conexao.php): com "?v=" o .htaccess guarda o arquivo por 1 ano.
$commit = preg_replace('/[^a-f0-9]/i', '', (string) getenv('RENDER_GIT_COMMIT'));
$versao = $commit !== '' ? substr($commit, 0, 10) : (string) (int) @filemtime(__DIR__ . '/css/styles.css');
$html = str_replace('<!--OG_IMAGEM-->', $metas, (string) file_get_contents(__DIR__ . '/index.html'));
$html = preg_replace('#((?:href|src)="(?:css|js)/[a-z0-9_-]+\.(?:css|js))"#', '$1?v=' . $versao . '"', $html);

header('Content-Type: text/html; charset=utf-8');
echo $html;
