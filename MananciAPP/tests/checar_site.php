<?php
// Conferência de segurança de um site no ar (homologação, produção ou o
// Apache do XAMPP). Só faz GET, sem login e sem cookie: não altera nada e
// pode rodar a qualquer hora. Ver docs/interno/seguranca.md, seção 4.
//
// Rodar, de dentro de MananciAPP/:
//   C:\xampp\php\php.exe tests\checar_site.php https://mananciapp-render-homolog.onrender.com/
//   C:\xampp\php\php.exe tests\checar_site.php http://localhost/MananciAPP/
// Sai com código 1 se alguma conferência falhar.
//
// Em http (local) os cabeçalhos que só fazem sentido em HTTPS (HSTS, cookie
// Secure, redirecionamento para https) viram aviso. O servidor embutido
// (php -S) não lê o .htaccess: use o Apache do XAMPP para conferir a pasta.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$base = rtrim((string) ($argv[1] ?? 'http://localhost/MananciAPP/'), '/') . '/';
if (!preg_match('#^https?://[^/]+#i', $base)) {
    fwrite(STDERR, "Uso: php tests/checar_site.php <url do site>\n");
    exit(2);
}
$https = stripos($base, 'https://') === 0;

$falhas = 0;
$avisos = 0;
$total = 0;

function checar_get($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'MananciAPP-checar-site',
    ));
    $resposta = curl_exec($ch);
    if ($resposta === false) {
        $erro = curl_error($ch);
        curl_close($ch);
        return array('codigo' => 0, 'cabecalhos' => array(), 'cookies' => array(), 'corpo' => '', 'erro' => $erro);
    }
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $tamanho = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $cabecalhos = array();
    $cookies = array();
    foreach (preg_split('/\r?\n/', substr($resposta, 0, $tamanho)) as $linha) {
        $pos = strpos($linha, ':');
        if ($pos === false) {
            continue;
        }
        $nome = strtolower(trim(substr($linha, 0, $pos)));
        $valor = trim(substr($linha, $pos + 1));
        if ($nome === 'set-cookie') {
            $cookies[] = $valor;
        }
        $cabecalhos[$nome] = $valor;
    }
    return array('codigo' => $codigo, 'cabecalhos' => $cabecalhos, 'cookies' => $cookies, 'corpo' => (string) substr($resposta, $tamanho), 'erro' => '');
}

// $grave = false: problema que não quebra a segurança sozinho, ou que só
// vale em HTTPS e o teste está rodando em http.
function checar($nome, $ok, $detalhe = '', $grave = true) {
    global $falhas, $avisos, $total;
    $total++;
    if ($ok) {
        echo "  ok     $nome\n";
        return;
    }
    if ($grave) {
        $falhas++;
        echo "  FALHA  $nome" . ($detalhe !== '' ? " ($detalhe)" : '') . "\n";
    } else {
        $avisos++;
        echo "  aviso  $nome" . ($detalhe !== '' ? " ($detalhe)" : '') . "\n";
    }
}

echo "Conferindo $base\n";

// ---- O site responde ----
echo "\nSite no ar\n";
$saude = checar_get($base . 'saude.php');
if ($saude['codigo'] === 0) {
    echo "  FALHA  não conectou: {$saude['erro']}\n";
    exit(1);
}
checar('saude.php responde 200', $saude['codigo'] === 200, 'código ' . $saude['codigo']);

// ---- Cabeçalhos de segurança numa página PHP ----
echo "\nCabeçalhos (login.php)\n";
$login = checar_get($base . 'login.php');
$h = $login['cabecalhos'];
checar('login.php responde 200', $login['codigo'] === 200, 'código ' . $login['codigo']);
$csp = (string) ($h['content-security-policy'] ?? '');
checar('CSP presente', $csp !== '');
$script_src = preg_match('/script-src([^;]*)/', $csp, $m) ? $m[1] : '';
checar('CSP: script-src com nonce', strpos($script_src, "'nonce-") !== false);
checar("CSP: script-src sem 'unsafe-inline'", strpos($script_src, "'unsafe-inline'") === false);
checar("CSP: object-src 'none'", strpos($csp, "object-src 'none'") !== false);
checar('CSP: frame-ancestors', strpos($csp, 'frame-ancestors') !== false);
checar('X-Content-Type-Options: nosniff', strtolower((string) ($h['x-content-type-options'] ?? '')) === 'nosniff');
checar('X-Frame-Options', isset($h['x-frame-options']));
checar('Referrer-Policy', isset($h['referrer-policy']));
checar('Permissions-Policy', isset($h['permissions-policy']), '', false);
checar('Strict-Transport-Security (HSTS)', isset($h['strict-transport-security']), $https ? '' : 'só em HTTPS', $https);
checar('sem X-Powered-By (versão do PHP)', !isset($h['x-powered-by']), (string) ($h['x-powered-by'] ?? ''), $https);
checar('Server sem número de versão', !preg_match('/\d/', (string) ($h['server'] ?? '')), (string) ($h['server'] ?? ''), $https);

$cookie_sessao = '';
foreach ($login['cookies'] as $cookie) {
    if (stripos($cookie, 'PHPSESSID=') === 0) {
        $cookie_sessao = $cookie;
    }
}
if ($cookie_sessao !== '') {
    checar('cookie de sessão HttpOnly', stripos($cookie_sessao, 'httponly') !== false);
    checar('cookie de sessão SameSite', stripos($cookie_sessao, 'samesite') !== false);
    checar('cookie de sessão Secure', stripos($cookie_sessao, 'secure') !== false, $https ? '' : 'só em HTTPS', $https);
} else {
    checar('cookie de sessão enviado no login.php', false, 'nenhum PHPSESSID — conferir à mão', false);
}

if ($https) {
    $http = checar_get('http://' . substr($base, 8) . 'login.php');
    $destino = (string) ($http['cabecalhos']['location'] ?? '');
    checar('http:// redireciona para https://', in_array($http['codigo'], array(301, 302, 307, 308), true) && stripos($destino, 'https://') === 0, 'código ' . $http['codigo']);
}

// ---- Arquivos que nunca podem ser baixados ----
// No Render a maioria nem entra na imagem (.dockerignore) e dá 404; no XAMPP
// o .htaccess barra com 403. Qualquer 200 é vazamento.
echo "\nArquivos internos bloqueados (esperado: não 200)\n";
$bloqueados = array(
    '.env', '.env.example', '.git/HEAD', '.gitignore', '.htaccess', '.user.ini',
    'Dockerfile', 'render.yaml', 'README.md', 'DEPLOY_RENDER.md',
    'docker/entrypoint.sh', 'database/bancosupabase.md', 'database/Banco%20Igreja.sql',
    'docs/interno/pontos_gerais.md', 'docs/interno/seguranca.md', 'agents/overview.md',
    'tests/testes_funcoes.php', 'tests/checar_site.php',
    'php/conexao.php', 'php/sessao_banco.php', 'php/admin/admin_layout.php',
    'php/includes/pwa_head.php', 'php/fiel/includes/navbar_fiel.php',
    'server-status', 'server-info',
);
foreach ($bloqueados as $caminho) {
    $r = checar_get($base . $caminho);
    checar($caminho, $r['codigo'] !== 200, 'respondeu 200');
}
// Bloqueado como as outras pastas includes/ (.htaccess próprio desde a
// correção do S13 em docs/interno/seguranca.md).
$r = checar_get($base . 'php/admin/includes/contribuicoes_lista.php');
checar('php/admin/includes/ bloqueado', $r['codigo'] === 403 || $r['codigo'] === 404, 'código ' . $r['codigo']);

// ---- Áreas que exigem login ou chave ----
echo "\nSem login, sem chave (esperado: não 200)\n";
$protegidos = array(
    'php/admin/dashboard.php', 'php/admin/admin_gerenciar_usuarios.php',
    'php/admin/admin_gerenciar_permissoes.php', 'php/admin/admin_financeiro.php',
    'php/admin/r2_upload_url.php', 'php/fiel/dashboard.php', 'php/fiel/perfil.php',
    'php/fiel/ajax_progresso.php', 'php/fiel/ajax_oracoes.php', 'php/fiel/ajax_acesso_app.php',
    'php/fiel/Certificados/certificado_view.php?id=1', 'lembrete_diario.php',
    // Telas e respostas de 27/10/2026 (a busca de pessoas devolve nome e e-mail).
    'php/admin/admin_atividade.php', 'php/admin/admin_seguranca_conta.php',
    'php/admin/admin_enviar_mensagem.php?buscar=ad', 'php/admin/admin_conteudos.php?ver=1',
    'php/admin/admin_visao_usuario.php?q=ad', 'php/fiel/configuracoes.php',
    'php/admin/admin_grade_curso.php?id=1', 'php/admin/admin_metricas.php',
);
foreach ($protegidos as $caminho) {
    $r = checar_get($base . $caminho);
    $vazou = $r['codigo'] === 200;
    checar($caminho, !$vazou, 'respondeu 200 sem login');
    if (!$vazou && in_array($r['codigo'], array(301, 302, 303, 307), true)) {
        $destino = (string) ($r['cabecalhos']['location'] ?? '');
        $host_base = parse_url($base, PHP_URL_HOST);
        $host_destino = parse_url($destino, PHP_URL_HOST);
        checar("$caminho redireciona para o próprio site", $host_destino === null || $host_destino === $host_base, $destino);
    }
}
$webhook = checar_get($base . 'webhook_asaas.php');
checar('webhook_asaas.php recusa GET (405)', $webhook['codigo'] === 405, 'código ' . $webhook['codigo']);

// ---- Nenhuma mensagem de erro do PHP na tela ----
echo "\nSem erro do PHP exposto\n";
$paginas = array('login.php', 'esqueci_senha.php', 'redefinir_senha.php?token=x', 'php/fiel/register.php', 'cursos.php', 'ao-vivo.php');
foreach ($paginas as $caminho) {
    $r = checar_get($base . $caminho);
    $vaza = preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Stack trace|PDOException|SQLSTATE|\/var\/www\/)/', $r['corpo'], $m);
    checar($caminho, !$vaza, $vaza ? 'contém "' . $m[1] . '"' : '');
}

echo "\n$total conferências: " . ($total - $falhas - $avisos) . " ok, $avisos aviso(s), $falhas falha(s).\n";
exit($falhas > 0 ? 1 : 0);
