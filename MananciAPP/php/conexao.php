<?php
// Módulo de conexão com o banco de dados

// O container do Render roda em UTC: todo date('d/m/Y H:i', strtotime(...))
// do app saía 3 h adiantado (certificado ou notificação depois das 21h com a
// data do dia seguinte, histórico agrupando "hoje" como "ontem"). Os
// timestamps do banco vêm com o fuso (+00), então strtotime() converte certo.
// Nenhum date() vai para o SQL — o que precisa de "hoje" no banco já usa
// site_hoje() ou AT TIME ZONE explícito, e continua igual.
date_default_timezone_set('America/Sao_Paulo');

// SITE_SESSAO_OPCIONAL (definida antes do include pelos endpoints de polling
// públicos): quem chega sem cookie de sessão (visitante) não abre uma. Com
// as sessões no banco, cada visitante da live criava e conferia uma sessão
// vazia a cada 5 s (3 consultas) só para nada. $_SESSION fica sem existir.
// SITE_SESSAO_PULAR: nem quem tem cookie abre (o polling do chat só precisa
// da sessão de vez em quando; ver ajax_transmissao_comentarios.php).
if (session_status() !== PHP_SESSION_ACTIVE && !defined('SITE_SESSAO_PULAR')
    && !(defined('SITE_SESSAO_OPCIONAL') && !isset($_COOKIE[session_name()]))) {
    session_start();
}

if (!defined('SITE_BASE_URL')) {
    // O Render (e a maioria dos PaaS) termina o HTTPS na borda e encaminha a
    // requisição pro container em HTTP puro — então $_SERVER['HTTPS'] vem
    // sempre vazio aqui dentro, mesmo com o visitante acessando por HTTPS de
    // verdade. Sem checar X-Forwarded-Proto (o header padrão que proxies
    // reversos usam pra avisar qual era o protocolo original), SITE_BASE_URL
    // sempre virava "http://..." em produção — inofensivo pra a maioria dos
    // usos (redirects/hrefs), mas qualquer fetch() do navegador pra essa URL
    // (ex.: php/includes/pwa_head.php avisando o backend do OneSignal) era
    // bloqueado pelo navegador como "mixed content" (https carregando http),
    // e o manifest.json/ícones do PWA sofriam do mesmo problema.
    $https_ligado = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0])) === 'https');
    $http_protocol = $https_ligado ? 'https://' : 'http://';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    // O Host vem de quem faz o pedido e SITE_BASE_URL sai sem escape em
    // href e em Location: só nome de host (e porta) passa.
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/D', $host)) {
        $host = 'localhost';
    }

    // A raiz do projeto no disco é a pasta que contém "php/" (um nível acima
    // deste arquivo). Comparamos com o script realmente executado para achar
    // a URL da raiz, não importa a profundidade da página atual (raiz,
    // php/admin/*, php/fiel/*, etc.) — necessário para o manifest/service
    // worker do PWA e para os redirects de auth apontarem sempre pra raiz.
    $project_root_fs = str_replace('\\', '/', rtrim(dirname(__DIR__), '\\/'));
    $script_filename = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');

    $base_path = null;
    if ($script_filename !== '' && strpos($script_filename, $project_root_fs . '/') === 0) {
        $relative = substr($script_filename, strlen($project_root_fs) + 1);
        $suffix_len = strlen($relative);
        if ($suffix_len > 0 && $suffix_len < strlen($script_name) && substr($script_name, -$suffix_len) === $relative) {
            $root_url = substr($script_name, 0, strlen($script_name) - $suffix_len);
            $base_path = ($root_url === '') ? '/' : rtrim($root_url, '/') . '/';
        }
    }

    if ($base_path === null) {
        // Fallback (mesmo comportamento de antes) para servidores onde não dá
        // pra calcular a raiz com segurança pelo caminho em disco.
        $script_dir = dirname($script_name);
        $base_path = ($script_dir !== '/' && $script_dir !== '\\') ? rtrim($script_dir, '/') . '/' : '/';
    }

    define('SITE_BASE_URL', $http_protocol . $host . $base_path);
}

if (!defined('SESSION_TIMEOUT_SECONDS')) {
    define('SESSION_TIMEOUT_SECONDS', 3600);
}

// Versão do Termo de Uso (ver php/fiel/termos.php). Todo fiel/admin precisa
// ter aceitado ESSA versão exata (IDF_TERMOS_VERSAO_ACEITA) pra usar o
// app — ver o gate em auth_require() mais abaixo. Se o termo mudar de
// verdade (não só erro de digitação), troque esse valor: todo mundo
// (inclusive quem já tinha aceitado uma versão antiga) é obrigado a
// aceitar de novo no próximo acesso.
// 2026-10-27: o termo passou a citar a Política de Privacidade (LGPD) e o
// uso do dado de fé; o novo aceite grava também idf_privacidade_aceita_em
// de quem se cadastrou antes da seção 82 (ver php/fiel/termos.php).
if (!defined('TERMOS_VERSAO_ATUAL')) {
    define('TERMOS_VERSAO_ATUAL', '2026-10-27');
}

// Velocidades do seletor do player (php/fiel/assistir_video.php). Também é
// a lista do que pode ser salvo em ID_PREFERENCIA.IDPREF_VELOCIDADE_VIDEO
// (php/fiel/ajax_preferencia_velocidade.php).
if (!defined('VIDEO_VELOCIDADES')) {
    define('VIDEO_VELOCIDADES', array(0.75, 1.0, 1.25, 1.5, 1.75, 2.0));
}

function site_load_env($path = null) {
    $env = array();
    $path = $path ?? dirname(__DIR__) . '/.env';

    if (!is_file($path) || !is_readable($path)) {
        return $env;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $env;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        if ($key === '') {
            continue;
        }

        if ((strlen($value) >= 2) && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        $env[$key] = $value;
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }

    return $env;
}

function site_env($key, $default = '') {
    if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }

    return $default;
}

// XAMPP/servidor local? O SERVER_NAME sozinho podia vir do cabeçalho Host
// (quem faz o pedido escolhe); a variável RENDER, que o Render põe em todo
// serviço, não vem da requisição: no Render nunca é local.
function site_ambiente_local() {
    return site_env('RENDER') === ''
        && in_array($_SERVER['SERVER_NAME'] ?? '', array('localhost', '127.0.0.1'), true);
}

// Valor de $_POST/$_GET como texto. Com "campo[]=" na requisição o valor
// chega como array, e trim()/mb_strlen() de array é erro fatal no PHP 8
// (página 500). Array ou nulo vira ''. $aparar = false para senhas (espaço
// nas pontas faz parte da senha).
function site_entrada_texto($valor, $aparar = true) {
    if (!is_scalar($valor)) {
        return '';
    }
    $valor = (string) $valor;
    return $aparar ? trim($valor) : $valor;
}

// ============================================================================
// IDENTIDADE DA IGREJA (Admin → Identidade da igreja)
// ============================================================================
// Nome, quem somos, pastor, horários dos cultos, endereço, contatos, redes e
// quem recebe as contribuições. Fica em id_configuracao (chaves "igreja_*"),
// sem tabela nova: a própria igreja preenche e corrige sem programador, e
// outra igreja que comprar o app troca tudo pela mesma tela. Campo vazio
// cai na variável antiga do Render (CONTATO_*) ou no padrão abaixo.
// tipo: texto (1 linha), longo (várias linhas), url (https://), email.
function igreja_campos() {
    return array(
        'nome' => array('Nome curto da igreja', 'texto', 60, 'Missão Manancial'),
        'nome_completo' => array('Nome completo (como no CNPJ ou no estatuto)', 'texto', 120, 'Missão Evangélica Manancial da Esperança'),
        'frase' => array('Frase de apresentação (topo da página inicial)', 'texto', 140, ''),
        'quem_somos' => array('Quem somos (página Sobre)', 'longo', 1500, ''),
        'pastor' => array('Pastor ou liderança', 'texto', 120, ''),
        'horarios' => array('Horários dos cultos (um por linha, ex.: "Domingo, 18h — Culto da família")', 'longo', 600, ''),
        'endereco' => array('Endereço', 'texto', 200, ''),
        'mapa_url' => array('Link do mapa (Google Maps)', 'url', 300, ''),
        'telefone' => array('Telefone', 'texto', 30, ''),
        'whatsapp' => array('WhatsApp (com DDD)', 'texto', 20, ''),
        'email' => array('E-mail de contato', 'email', 120, ''),
        'instagram' => array('Instagram (link)', 'url', 200, ''),
        'youtube' => array('YouTube (link)', 'url', 200, ''),
        'facebook' => array('Facebook (link)', 'url', 200, ''),
        'site' => array('Site da igreja (link)', 'url', 200, ''),
        'recebedor' => array('Quem recebe as contribuições (razão social)', 'texto', 120, ''),
        'cnpj' => array('CNPJ de quem recebe', 'texto', 18, ''),
    );
}

// Todos os campos, já com o reserva aplicado (variáveis antigas e padrão).
function igreja_info() {
    static $info = null;
    if ($info !== null) {
        return $info;
    }
    $reserva = array(
        'telefone' => site_env('CONTATO_TELEFONE'),
        'email' => site_env('CONTATO_EMAIL'),
        'endereco' => site_env('CONTATO_ENDERECO'),
    );
    $info = array();
    foreach (igreja_campos() as $campo => $def) {
        $valor = trim((string) site_config('igreja_' . $campo, ''));
        if ($valor === '') {
            $valor = trim((string) ($reserva[$campo] ?? ''));
        }
        $info[$campo] = $valor !== '' ? $valor : (string) $def[3];
    }
    return $info;
}

// Confere e limpa um campo da tela do admin. Devolve array(valor, erro).
function igreja_validar_campo($campo, $valor) {
    $campos = igreja_campos();
    if (!isset($campos[$campo])) {
        return array('', 'Campo desconhecido.');
    }
    list($rotulo, $tipo, $maximo) = $campos[$campo];
    $valor = str_replace("\r\n", "\n", trim((string) $valor));
    if ($tipo !== 'longo') {
        $valor = preg_replace('/\s+/', ' ', $valor);
    }
    if (mb_strlen($valor) > $maximo) {
        return array($valor, $rotulo . ': até ' . $maximo . ' caracteres.');
    }
    if ($valor !== '' && $tipo === 'url' && !preg_match('#^https://[^\s<>"]+$#i', $valor)) {
        return array($valor, $rotulo . ': use um link completo começando com https://');
    }
    if ($valor !== '' && $tipo === 'email' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
        return array($valor, $rotulo . ': e-mail inválido.');
    }
    if ($valor !== '' && $campo === 'cnpj' && !preg_match('/^\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}$/', $valor)) {
        return array($valor, 'CNPJ: use o formato 00.000.000/0000-00.');
    }
    if ($valor !== '' && $campo === 'whatsapp' && strlen(preg_replace('/\D/', '', $valor)) < 10) {
        return array($valor, 'WhatsApp: informe com DDD.');
    }
    return array($valor, '');
}

// Link wa.me do WhatsApp da igreja ('' sem número). Número sem 55 ganha o 55.
function igreja_whatsapp_link($mensagem = '') {
    $numero = preg_replace('/\D/', '', igreja_info()['whatsapp']);
    if (strlen($numero) < 10) {
        return '';
    }
    if (strlen($numero) <= 11) {
        $numero = '55' . $numero;
    }
    return 'https://wa.me/' . $numero . ($mensagem !== '' ? '?text=' . rawurlencode($mensagem) : '');
}

// Horários como lista (uma linha por culto).
function igreja_horarios() {
    return array_values(array_filter(array_map('trim', explode("\n", igreja_info()['horarios'])), 'strlen'));
}

// O que as páginas públicas podem mostrar (ajax_igreja_publica.php e sobre.php).
// Sem o CNPJ/recebedor (só na tela de contribuição) e sem nada vazio.
function igreja_publica() {
    $info = igreja_info();
    $saida = array();
    foreach (array('nome', 'nome_completo', 'frase', 'quem_somos', 'pastor', 'endereco', 'mapa_url', 'telefone', 'email', 'instagram', 'youtube', 'facebook', 'site') as $campo) {
        if ($info[$campo] !== '') {
            $saida[$campo] = $info[$campo];
        }
    }
    $horarios = igreja_horarios();
    if ($horarios) {
        $saida['horarios'] = $horarios;
    }
    $whatsapp = igreja_whatsapp_link();
    if ($whatsapp !== '') {
        $saida['whatsapp'] = $whatsapp;
    }
    return $saida;
}

// Linhas de contato do rodapé das páginas públicas (Identidade da igreja;
// sem preencher, as variáveis CONTATO_* do Render). Campo vazio, linha
// some. Antes o rodapé mostrava um telefone e um e-mail de exemplo, que
// não eram de ninguém. Mais o link da Política de Privacidade (LGPD).
function site_rodape_contato_html() {
    $html = '';
    $igreja = igreja_info();
    $telefone = $igreja['telefone'];
    $email = $igreja['email'];
    $endereco = $igreja['endereco'];
    if ($telefone !== '') {
        $html .= '<p><i class="fas fa-phone me-2"></i> ' . htmlspecialchars($telefone) . '</p>';
    }
    if ($email !== '') {
        $html .= '<p><i class="fas fa-envelope me-2"></i> <a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a></p>';
    }
    if ($endereco !== '') {
        $html .= '<p><i class="fas fa-map-marker-alt me-2"></i> ' . htmlspecialchars($endereco) . '</p>';
    }
    $whatsapp = igreja_whatsapp_link();
    if ($whatsapp !== '') {
        $html .= '<p><i class="fab fa-whatsapp me-2"></i> <a href="' . htmlspecialchars($whatsapp) . '" target="_blank" rel="noopener">Falar no WhatsApp</a></p>';
    }
    if ($html === '') {
        $html = '<p>Já tem conta? Fale com a gente em <strong>Contato</strong>, dentro do app.</p>';
    }
    return $html . '<p><a href="' . htmlspecialchars(site_url('privacidade.php')) . '"><i class="fas fa-user-shield me-2"></i>Política de Privacidade</a></p>';
}

// Versão dos CSS/JS do próprio site, para o "?v=" nos links: com ela o
// .htaccess guarda o arquivo por 1 ano sem perguntar de novo ao servidor
// (antes era "no-cache": a cada página, uma ida e volta por arquivo para
// saber se mudou). Muda a cada deploy (commit do Render) e, fora dele, com a
// data do styles.css — então um deploy novo chega na hora.
function site_versao_estatico() {
    static $versao = null;
    if ($versao === null) {
        $commit = preg_replace('/[^a-f0-9]/i', '', (string) getenv('RENDER_GIT_COMMIT'));
        $versao = $commit !== '' ? substr($commit, 0, 10) : (string) (int) @filemtime(dirname(__DIR__) . '/css/styles.css');
    }
    return $versao;
}

// Metatags das páginas públicas: descrição para o Google e a prévia do link
// no WhatsApp/Facebook (Open Graph), que precisa de endereço absoluto.
// Imagem padrão: assets/img/compartilhar.jpg (1200×630, logo e nome).
function site_meta_publica($titulo, $descricao, $caminho = '', $imagem = 'assets/img/compartilhar.jpg') {
    $url = site_url_publica($caminho);
    $html = '<meta name="description" content="' . htmlspecialchars($descricao) . '">' . "\n"
        . '    <link rel="canonical" href="' . htmlspecialchars($url) . '">' . "\n"
        . '    <meta property="og:type" content="website">' . "\n"
        . '    <meta property="og:locale" content="pt_BR">' . "\n"
        . '    <meta property="og:site_name" content="Missão Manancial">' . "\n"
        . '    <meta property="og:title" content="' . htmlspecialchars($titulo) . '">' . "\n"
        . '    <meta property="og:description" content="' . htmlspecialchars($descricao) . '">' . "\n"
        . '    <meta property="og:url" content="' . htmlspecialchars($url) . '">' . "\n"
        . '    <meta property="og:image" content="' . htmlspecialchars(site_url_publica($imagem)) . '">' . "\n"
        . '    <meta property="og:image:width" content="1200">' . "\n"
        . '    <meta property="og:image:height" content="630">' . "\n"
        . '    <meta name="twitter:card" content="summary_large_image">' . "\n";
    return $html;
}

// Texto digitado numa busca com LIKE: "%", "_" e "\" valem como texto, não
// como curinga. Usar com '%' . site_like_escapar($termo) . '%'.
function site_like_escapar($texto) {
    return str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), (string) $texto);
}

site_load_env();

// ---- CSP com nonce ----
// Cada página PHP manda a própria Content-Security-Policy, com um nonce
// novo a cada requisição: só roda o <script> embutido que tiver o atributo
// nonce com o valor de site_csp_nonce(). Um script injetado num comentário
// (XSS) não tem o nonce e é bloqueado — antes, com 'unsafe-inline', rodaria.
// Atributos de evento (onclick=, onsubmit=...) também deixam de rodar: as
// páginas usam data-confirmar, data-enviar-ao-mudar etc. (js/acoes.js).
// O resto da política é igual à do .htaccess, que fica só como padrão para o
// que não passa por aqui (arquivos estáticos e o index.html). Estilos
// continuam com 'unsafe-inline' (centenas de style=; injetar estilo não roda
// código).
function site_csp_nonce() {
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}

function site_csp_politica() {
    $cdn = 'https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.onesignal.com https://onesignal.com https://*.onesignal.com';
    // Scripts: só o Bootstrap (caminho exato) e a OneSignal. O jsDelivr e o
    // cdnjs servem código de qualquer pessoa (ex.: cdn.jsdelivr.net/gh/...):
    // liberados inteiros, uma injeção de HTML rodaria script sem o nonce.
    // Biblioteca nova de CDN: acrescentar o caminho dela aqui e no .htaccess.
    $cdn_scripts = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/ https://cdn.onesignal.com https://onesignal.com https://*.onesignal.com https://challenges.cloudflare.com';
    return "default-src 'self'; "
        . "script-src 'self' 'nonce-" . site_csp_nonce() . "' $cdn_scripts; "
        . "style-src 'self' 'unsafe-inline' $cdn; "
        . "font-src 'self' https://cdnjs.cloudflare.com data:; "
        . "img-src 'self' data: https:; "
        . "media-src 'self' blob: https://*.r2.cloudflarestorage.com; "
        . "connect-src 'self' https://bible-api.com https://*.r2.cloudflarestorage.com https://*.onesignal.com https://onesignal.com https://challenges.cloudflare.com; "
        . "frame-src https://*.onesignal.com https://www.youtube.com https://www.youtube-nocookie.com https://challenges.cloudflare.com; "
        . "object-src 'none'; base-uri 'self'; frame-ancestors 'self'; "
        . "form-action 'self' https://formspree.io https://*.asaas.com";
}

// CSP_SO_AVISAR=1 no Render: a política nova só avisa no Console
// (Report-Only) e a antiga, com 'unsafe-inline', volta a valer pelo
// .htaccess (que só entra quando não há outra "Content-Security-Policy").
// Para destravar uma tela quebrada pela CSP sem reverter código.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    $csp_cabecalho = site_env('CSP_SO_AVISAR') === '1' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
    header($csp_cabecalho . ': ' . site_csp_politica());
    // A homologação não aparece no Google (cursos de teste, contas de
    // exemplo). A produção, em domínio próprio, não tem "homolog" no nome.
    if (stripos((string) ($_SERVER['HTTP_HOST'] ?? ''), 'homolog') !== false) {
        header('X-Robots-Tag: noindex, nofollow');
    }
}

// ---- Alerta de erros (Sentry, opcional) ----
// Com SENTRY_DSN no Render (sentry.io → projeto PHP → Client Keys), erro
// fatal (inclusive exceção não tratada) e erro de banco (site_erro_banco())
// viram um alerta com página, arquivo e linha, e o Sentry manda e-mail.
// Sem a variável, nada muda. Nunca vai: query string da URL (pode ter
// token), corpo do POST, cookies, e-mail (trocado por "[e-mail]"). No
// máximo 3 alertas por requisição; 2 s de limite para não segurar a página.
// Passo a passo em docs/interno/qualidade_e_ci.md.
function site_alerta_dsn() {
    static $dsn = null;
    if ($dsn === null) {
        $dsn = preg_match('#^https://([0-9a-f]+)@([A-Za-z0-9.-]+)/(\d+)$#', trim((string) site_env('SENTRY_DSN')), $m)
            ? array('chave' => $m[1], 'host' => $m[2], 'projeto' => $m[3])
            : false;
    }
    return $dsn;
}

// $pagina: o erro do navegador (ajax_erro_js.php) vem com a página onde
// aconteceu, não com o endereço do endpoint.
// Falhas que a igreja precisa ver (cartão "Saúde do sistema" do Dashboard),
// guardadas também no banco (seção 89) — com ou sem Sentry. Só estes tipos.
function sistema_falha_tipos() {
    return array(
        'EmailFalhou' => 'E-mails que não saíram',
        'PushFalhou' => 'Notificações no celular que não saíram',
        'WebhookAsaas' => 'Avisos de pagamento do Asaas com erro',
        'RotinaFalhou' => 'Lembrete diário ou limpeza com erro',
    );
}

function sistema_falha_registrar($tipo, $mensagem) {
    global $conn;
    static $gravadas = 0;
    // No máximo 3 por requisição, e nunca derruba quem chamou.
    if (!$conn || $gravadas >= 3 || !isset(sistema_falha_tipos()[$tipo]) || !site_tabela_existe($conn, 'id_sistema_falha')) {
        return;
    }
    $gravadas++;
    @db_query($conn, "INSERT INTO id_sistema_falha (isf_tipo, isf_mensagem) VALUES (?, ?)", array($tipo, mb_substr((string) $mensagem, 0, 300)));
}

// Resumo das últimas 24 h para o Dashboard: por tipo, quantas e quando foi a
// última; mais o último aviso de pagamento recebido. null sem a seção 89.
function sistema_saude($conn) {
    if (!site_tabela_existe($conn, 'id_sistema_falha')) {
        return null;
    }
    $saude = array('falhas' => array(), 'ultimo_webhook' => null);
    $res = mysqli_query($conn, "
        SELECT isf_tipo, COUNT(*) AS total, MAX(isf_criado_em) AS ultima
        FROM id_sistema_falha WHERE isf_criado_em > NOW() - INTERVAL '24 hours'
        GROUP BY isf_tipo
    ");
    while ($res && ($linha = mysqli_fetch_assoc($res))) {
        $saude['falhas'][$linha['isf_tipo']] = array('total' => (int) $linha['total'], 'ultima' => $linha['ultima']);
    }
    if (site_tabela_existe($conn, 'id_asaas_evento')) {
        $res_w = mysqli_query($conn, "SELECT MAX(idae_recebido_em) AS ultimo FROM id_asaas_evento");
        $linha_w = $res_w ? mysqli_fetch_assoc($res_w) : null;
        $saude['ultimo_webhook'] = $linha_w['ultimo'] ?? null;
    }
    return $saude;
}

function site_alerta_erro($nivel, $mensagem, $tipo = 'Erro', $arquivo = '', $linha = 0, $pagina = '') {
    static $enviados = 0;
    sistema_falha_registrar($tipo, $mensagem);
    $dsn = site_alerta_dsn();
    if (!$dsn || PHP_SAPI === 'cli' || $enviados >= 3 || !function_exists('curl_init')) {
        return;
    }
    $enviados++;
    $mensagem = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[e-mail]', mb_substr((string) $mensagem, 0, 2000));
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $caminho = strtok($pagina !== '' ? (string) $pagina : (string) ($_SERVER['REQUEST_URI'] ?? ''), '?');
    $evento = array(
        'event_id' => bin2hex(random_bytes(16)),
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'level' => $nivel,
        'platform' => 'php',
        'logger' => 'mananciapp',
        'environment' => stripos($host, 'homolog') !== false ? 'homologacao' : 'producao',
        'server_name' => $host,
        'transaction' => $caminho,
        'exception' => array('values' => array(array(
            'type' => $tipo,
            'value' => $mensagem,
            'stacktrace' => $arquivo !== '' ? array('frames' => array(array('filename' => str_replace(dirname(__DIR__), '', $arquivo), 'lineno' => (int) $linha))) : null,
        ))),
        'request' => array('url' => 'https://' . $host . $caminho, 'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? '')),
        'user' => isset($_SESSION['Usuario_id']) ? array('id' => (string) (int) $_SESSION['Usuario_id']) : null,
        'tags' => array('tipo_usuario' => (string) ($_SESSION['Usuario_tipo'] ?? 'visitante')),
    );
    $ch = curl_init('https://' . $dsn['host'] . '/api/' . $dsn['projeto'] . '/store/');
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($evento),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'X-Sentry-Auth: Sentry sentry_version=7, sentry_client=mananciapp/1.0, sentry_key=' . $dsn['chave'],
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_SSL_VERIFYPEER => true,
    ));
    @curl_exec($ch);
    curl_close($ch);
}

// Erro fatal e exceção não tratada aparecem em error_get_last() no fim da
// requisição: o PHP continua mostrando a página 500 e gravando no log como
// antes; aqui só sai o alerta.
if (site_alerta_dsn()) {
    register_shutdown_function(function () {
        $erro = error_get_last();
        if ($erro && in_array($erro['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true)) {
            site_alerta_erro('fatal', $erro['message'], 'ErroFatal', $erro['file'], $erro['line']);
        }
    });
}

// ---- CAPTCHA invisível (Cloudflare Turnstile, opcional) ----
// No cadastro e no "Esqueci minha senha": sem ele, um robô com muitos IPs
// enchia a fila de aprovação e mandava e-mails pela Brevo. Gratuito; na
// maioria das vezes a pessoa nem vê (só um quadrinho que se marca sozinho).
// Liga com TURNSTILE_SITE_KEY e TURNSTILE_SECRET_KEY no Render (Cloudflare →
// Turnstile → Add site). Sem as duas, nada muda. A CSP já libera
// challenges.cloudflare.com (site_csp_politica() e .htaccess).
function turnstile_ativo() {
    return trim((string) site_env('TURNSTILE_SITE_KEY')) !== '' && trim((string) site_env('TURNSTILE_SECRET_KEY')) !== '';
}

// O quadrinho do formulário (vazio com o Turnstile desligado).
function turnstile_campo_html() {
    if (!turnstile_ativo()) {
        return '';
    }
    return '<div class="cf-turnstile" data-sitekey="' . htmlspecialchars(trim((string) site_env('TURNSTILE_SITE_KEY'))) . '" data-language="pt-br" data-theme="auto" data-size="flexible"></div>'
        . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

// Confere a resposta do quadrinho no servidor. Desligado: sempre passa. Se a
// Cloudflare não responder (rede), deixa passar e registra no log: os limites
// por IP continuam valendo, e travar todo cadastro por uma falha de fora
// seria pior. Resposta "inválido" da Cloudflare: recusa.
function turnstile_validar() {
    if (!turnstile_ativo()) {
        return true;
    }
    $token = site_entrada_texto($_POST['cf-turnstile-response'] ?? '');
    if ($token === '' || strlen($token) > 2048) {
        return false;
    }
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(array(
            'secret' => trim((string) site_env('TURNSTILE_SECRET_KEY')),
            'response' => $token,
            'remoteip' => site_ip_cliente(),
        )),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ));
    $resposta = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resposta === false || $codigo !== 200) {
        error_log('Turnstile: sem resposta da Cloudflare (HTTP ' . $codigo . '); pedido aceito sem a conferência.');
        return true;
    }
    $dados = json_decode((string) $resposta, true);
    return is_array($dados) && !empty($dados['success']);
}

// ---- Cloudflare R2 (armazenamento dos vídeos) ----
// Ver seção 5 de DEPLOY_RENDER.md. Os vídeos ficam num bucket privado e são
// gravados em IDCT_URL com o prefixo "r2:" (ex.: "r2:aulas/123_culto.mp4").
// O banco continua no Supabase (variáveis DB_*); só os arquivos saíram de lá.
if (!defined('R2_ACCOUNT_ID')) {
    define('R2_ACCOUNT_ID', site_env('R2_ACCOUNT_ID', ''));
}

if (!defined('R2_ACCESS_KEY_ID')) {
    define('R2_ACCESS_KEY_ID', site_env('R2_ACCESS_KEY_ID', ''));
}

if (!defined('R2_SECRET_ACCESS_KEY')) {
    // Fica só no backend, NUNCA é enviada pro navegador — é usada só pra
    // assinar as URLs de upload, leitura e exclusão.
    define('R2_SECRET_ACCESS_KEY', site_env('R2_SECRET_ACCESS_KEY', ''));
}

if (!defined('R2_BUCKET')) {
    define('R2_BUCKET', site_env('R2_BUCKET', 'mananciapp-videos-homolog'));
}

function r2_configurado() {
    return R2_ACCOUNT_ID !== '' && R2_ACCESS_KEY_ID !== '' && R2_SECRET_ACCESS_KEY !== '' && R2_BUCKET !== '';
}

// Gera uma URL pré-assinada do Cloudflare R2 (API compatível com S3) pra um
// caminho dentro do bucket de vídeos. Serve tanto pro upload (PUT) quanto pra
// assistir (GET). A assinatura (AWS SigV4) é calculada localmente, sem chamada
// de rede — por isso não precisa de cache.
// $query_extra: parâmetros a mais na URL (ex.: list-type/prefix para listar
// o bucket em r2_listar_objetos()); entram na assinatura.
// $cabecalhos: cabeçalhos que entram na assinatura (ex.: content-type do
// upload). Quem usa a URL tem que mandar exatamente esses valores, senão o
// R2 recusa (403).
function r2_criar_url_assinada($metodo, $caminho_arquivo, $expira_em_segundos = 14400, array $query_extra = array(), array $cabecalhos = array()) {
    if (!r2_configurado()) {
        return '';
    }

    $host = R2_ACCOUNT_ID . '.r2.cloudflarestorage.com';
    $regiao = 'auto';
    $servico = 's3';
    // GET (tocar vídeo/capa): arredonda pra hora cheia — a URL se repete
    // entre páginas dentro da mesma hora e o navegador reaproveita o cache do
    // vídeo. Soma 1h na validade pra compensar o tempo "perdido" no
    // arredondamento. PUT/DELETE não têm cache a aproveitar: valem
    // exatamente o pedido (o upload ficava aberto até 2h por causa disso).
    $arredonda = strtoupper($metodo) === 'GET' && empty($query_extra);
    $instante = $arredonda ? (int) (floor(time() / 3600) * 3600) : time();
    $agora = gmdate('Ymd\THis\Z', $instante);
    $data = gmdate('Ymd', $instante);
    $escopo = $data . '/' . $regiao . '/' . $servico . '/aws4_request';
    $expira = min((int) $expira_em_segundos + ($arredonda ? 3600 : 0), 604800);

    $uri = '/' . rawurlencode(R2_BUCKET) . '/'
        . implode('/', array_map('rawurlencode', explode('/', ltrim($caminho_arquivo, '/'))));

    // Cabeçalhos canônicos: nome em minúsculo, em ordem alfabética.
    $assinados = array('host' => $host);
    foreach ($cabecalhos as $nome_cabecalho => $valor_cabecalho) {
        $assinados[strtolower(trim((string) $nome_cabecalho))] = trim((string) $valor_cabecalho);
    }
    ksort($assinados);
    $cabecalhos_canonicos = '';
    foreach ($assinados as $nome_cabecalho => $valor_cabecalho) {
        $cabecalhos_canonicos .= $nome_cabecalho . ':' . $valor_cabecalho . "\n";
    }
    $lista_assinados = implode(';', array_keys($assinados));

    $params = array(
        'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential' => R2_ACCESS_KEY_ID . '/' . $escopo,
        'X-Amz-Date' => $agora,
        'X-Amz-Expires' => (string) $expira,
        'X-Amz-SignedHeaders' => $lista_assinados,
    );
    foreach ($query_extra as $chave_extra => $valor_extra) {
        $params[(string) $chave_extra] = (string) $valor_extra;
    }
    ksort($params);
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $requisicao_canonica = $metodo . "\n" . $uri . "\n" . $query . "\n"
        . $cabecalhos_canonicos . "\n" . $lista_assinados . "\n" . 'UNSIGNED-PAYLOAD';

    $texto_assinar = "AWS4-HMAC-SHA256\n" . $agora . "\n" . $escopo . "\n"
        . hash('sha256', $requisicao_canonica);

    $chave = hash_hmac('sha256', $data, 'AWS4' . R2_SECRET_ACCESS_KEY, true);
    $chave = hash_hmac('sha256', $regiao, $chave, true);
    $chave = hash_hmac('sha256', $servico, $chave, true);
    $chave = hash_hmac('sha256', 'aws4_request', $chave, true);
    $assinatura = hash_hmac('sha256', $texto_assinar, $chave);

    return 'https://' . $host . $uri . '?' . $query . '&X-Amz-Signature=' . $assinatura;
}

// Apaga um arquivo do bucket do R2. Timeout curto pra nunca travar o admin:
// se falhar, só fica registrado no log e o arquivo sobra no bucket (como
// acontecia antes). 204/404 contam como sucesso (já não existe mais).
function r2_excluir_arquivo($caminho_arquivo) {
    $url = r2_criar_url_assinada('DELETE', $caminho_arquivo, 600);
    if ($url === '') {
        return false;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
    ));
    curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $ok = ($codigo >= 200 && $codigo < 300) || $codigo === 404;
    if (!$ok) {
        error_log('[R2] Falha ao excluir ' . $caminho_arquivo . ' (HTTP ' . $codigo . ')');
    }
    return $ok;
}

// Envia um arquivo que já está no servidor (ex.: o tmp de um upload de
// imagem) pro bucket do R2. Usado pras imagens, que são pequenas; os vídeos
// vão direto do navegador pro bucket (r2_upload_url.php) e não passam aqui.
function r2_enviar_arquivo($caminho_arquivo, $arquivo_local, $content_type) {
    $url = r2_criar_url_assinada('PUT', $caminho_arquivo, 600);
    $conteudo = @file_get_contents($arquivo_local);
    if ($url === '' || $conteudo === false) {
        return false;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $conteudo,
        CURLOPT_HTTPHEADER => array('Content-Type: ' . $content_type),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 60,
    ));
    curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($codigo < 200 || $codigo >= 300) {
        error_log('[R2] Falha ao enviar ' . $caminho_arquivo . ' (HTTP ' . $codigo . ')');
        return false;
    }
    return true;
}

define('IMAGEM_UPLOAD_MAX_BYTES', 5 * 1024 * 1024);

// Recebe uma imagem de $_FILES[$campo] e guarda no R2 em "<pasta>/...".
// Nada de imagem enviada pelo admin fica no disco do servidor: no Render ele
// é apagado a cada deploy/reinício e a imagem sumiria. Devolve:
// - null  => nenhum arquivo foi escolhido (manter o que já existe);
// - false => erro (mensagem em $erro);
// - "r2:<pasta>/<arquivo>" => o valor a gravar no banco.
function site_salvar_imagem_upload($campo, $pasta, &$erro) {
    if (!isset($_FILES[$campo]) || $_FILES[$campo]["error"] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$campo]["error"] !== UPLOAD_ERR_OK) {
        $erro = "Falha no upload da imagem (código " . $_FILES[$campo]["error"] . ").";
        return false;
    }

    $tmp = $_FILES[$campo]["tmp_name"];
    $nome_original = basename($_FILES[$campo]["name"]);
    $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
    $permitidas = array("jpg", "jpeg", "png", "webp", "gif");

    if (!in_array($extensao, $permitidas, true)) {
        $erro = "Formato de imagem não permitido. Use jpg, png, webp ou gif.";
        return false;
    }
    if ((int) $_FILES[$campo]["size"] > IMAGEM_UPLOAD_MAX_BYTES) {
        $erro = "A imagem passa de 5 MB. Diminua o tamanho e tente de novo.";
        return false;
    }

    // Confere que é imagem de verdade (não só a extensão) e pega o tipo real.
    $info = is_uploaded_file($tmp) ? @getimagesize($tmp) : false;
    $tipos = array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF);
    if (!$info || !in_array($info[2], $tipos, true)) {
        $erro = "O arquivo enviado não é uma imagem válida.";
        return false;
    }

    if (!r2_configurado()) {
        $erro = "Armazenamento de imagens não configurado no servidor (variáveis R2_*).";
        return false;
    }

    $nome_limpo = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($nome_original, PATHINFO_FILENAME));
    $caminho = trim($pasta, '/') . '/' . time() . '_' . $nome_limpo . '.' . $extensao;

    if (!r2_enviar_arquivo($caminho, $tmp, $info['mime'])) {
        $erro = "Não foi possível salvar a imagem. Tente de novo.";
        return false;
    }
    return 'r2:' . $caminho;
}

// Chamada DEPOIS que uma aula ou curso foi excluído, ou teve o vídeo/capa
// trocado: se o arquivo antigo estava no R2 e nada mais aponta pra ele
// (nenhuma aula em IDCT_URL e nenhum curso em IDC_CAPA_URL), apaga do bucket
// pra não ficar acumulando. Arquivos locais em assets/ ou videos/ e links
// externos são ignorados.
// Lista os objetos do bucket com um prefixo (API ListObjectsV2), até
// $limite. Devolve array de array('chave', 'tamanho', 'modificado_ts'), ou
// null se a listagem falhar.
function r2_listar_objetos($prefixo, $limite = 5000) {
    $objetos = array();
    $continuacao = '';
    do {
        $query = array('list-type' => '2', 'prefix' => $prefixo, 'max-keys' => '1000');
        if ($continuacao !== '') {
            $query['continuation-token'] = $continuacao;
        }
        $url = r2_criar_url_assinada('GET', '', 600, $query);
        if ($url === '') {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
        ));
        $corpo = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($codigo !== 200 || !is_string($corpo)) {
            error_log('[R2] Falha ao listar ' . $prefixo . ' (HTTP ' . $codigo . ')');
            return null;
        }
        $xml = @simplexml_load_string($corpo);
        if ($xml === false) {
            return null;
        }
        foreach ($xml->Contents as $item) {
            $objetos[] = array(
                'chave' => (string) $item->Key,
                'tamanho' => (int) $item->Size,
                'modificado_ts' => (int) strtotime((string) $item->LastModified),
            );
        }
        $continuacao = (string) $xml->IsTruncated === 'true' ? (string) $xml->NextContinuationToken : '';
    } while ($continuacao !== '' && count($objetos) < $limite);
    return $objetos;
}

// Vídeos de aula esquecidos no bucket: o navegador do admin sobe o arquivo
// direto pro R2 ANTES de salvar a aula, então upload sem "salvar" (aba
// fechada, erro no formulário, desistiu) deixava o arquivo lá para sempre,
// pagando armazenamento. Considera órfão o objeto de "aulas/" que nenhuma
// aula (ID_CONTENT.IDCT_URL) usa e que tem mais de 24 h — a folga protege um
// upload em andamento. $apagar = false só conta (prévia na tela do admin).
// Devolve array('total', 'bytes', 'apagados'), ou null se não deu para
// listar o bucket ou ler as aulas (aí não apaga nada).
function r2_limpar_videos_orfaos($conn, $apagar = false) {
    if (!$conn || !r2_configurado()) {
        return null;
    }
    $objetos = r2_listar_objetos('aulas/');
    $res = mysqli_query($conn, "SELECT IDCT_URL FROM ID_CONTENT WHERE IDCT_URL LIKE 'r2:aulas/%'");
    if ($objetos === null || !$res) {
        return null;
    }
    $em_uso = array();
    while ($linha = mysqli_fetch_assoc($res)) {
        $em_uso[substr((string) $linha['IDCT_URL'], 3)] = true;
    }

    $resultado = array('total' => 0, 'bytes' => 0, 'apagados' => 0);
    foreach ($objetos as $objeto) {
        if (isset($em_uso[$objeto['chave']]) || $objeto['modificado_ts'] <= 0 || $objeto['modificado_ts'] > time() - 86400) {
            continue;
        }
        $resultado['total']++;
        $resultado['bytes'] += $objeto['tamanho'];
        if ($apagar && r2_excluir_arquivo($objeto['chave'])) {
            $resultado['apagados']++;
        }
    }
    return $resultado;
}

function site_excluir_arquivo_r2_orfao($valor) {
    global $conn;
    $valor = trim((string) $valor);
    if (strpos($valor, 'r2:') !== 0 || !$conn) {
        return;
    }

    // Capas dos projetos de missão (seção 72) e artes dos destaques da home
    // (seção 75) também ficam no R2.
    $sql_extra = '';
    $params = array($valor, $valor);
    if (site_tabela_existe($conn, 'id_missao_projeto')) {
        $sql_extra .= " UNION ALL SELECT 1 FROM id_missao_projeto WHERE idmp_capa_url = ?";
        $params[] = $valor;
    }
    if (site_tabela_existe($conn, 'id_home_destaque')) {
        $sql_extra .= " UNION ALL SELECT 1 FROM id_home_destaque WHERE idhd_imagem_url = ? OR idhd_imagem_celular_url = ?";
        $params[] = $valor;
        $params[] = $valor;
    }
    $res = db_query($conn, "
        SELECT 1 FROM ID_CONTENT WHERE IDCT_URL = ?
        UNION ALL
        SELECT 1 FROM ID_CURSO WHERE IDC_CAPA_URL = ?
        $sql_extra
        LIMIT 1
    ", $params);
    if (!$res || mysqli_num_rows($res) > 0) {
        // Erro na consulta ou ainda está em uso: na dúvida, mantém.
        return;
    }

    r2_excluir_arquivo(substr($valor, 3));
}

// Resolve o que está salvo em IDCT_URL pra algo renderizável num <video>/<a href>:
// - "videos/..." => arquivo local legado (de antes de ir pra nuvem).
// - "http(s)://..." => link externo já pronto, usa como está.
// - "r2:..." => caminho dentro do bucket privado do Cloudflare R2 (URL
//   pré-assinada calculada na hora, sem cache — não há chamada de rede).
// - qualquer outro valor => caminho do antigo Supabase Storage, que foi
//   desativado em out/2026 (todas as aulas passaram pro R2): não toca mais.
// Validade padrão de 2 h (antes 4 h; com o arredondamento para a hora cheia
// de r2_criar_url_assinada, a URL vale de 2 a 3 h): quem perde o acesso
// (cancelou, foi negado) para de assistir mais cedo, e uma URL repassada a
// quem não assina expira antes. Ainda cobre uma aula longa com pausas — o
// player pede trechos do vídeo à URL enquanto toca.
function site_resolver_url_video($valor, $prefixo_local = '../../', $expira_em_segundos = 7200) {
    $valor = trim((string) $valor);
    if ($valor === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $valor)) {
        return $valor;
    }

    if (strpos($valor, 'videos/') === 0) {
        $caminho_local = rtrim($prefixo_local, '/') . '/' . $valor;
        return is_file($caminho_local) ? $caminho_local : '';
    }

    if (strpos($valor, 'r2:') === 0) {
        return r2_criar_url_assinada('GET', substr($valor, 3), $expira_em_segundos);
    }

    return '';
}

// Email provider configuration - prefer generic names, but keep backward compatibility
if (!defined('EMAIL_API_KEY')) {
    $email_api = site_env('EMAIL_API_KEY');
    if (empty($email_api)) {
        $email_api = site_env('BREVO_API_KEY');
    }
    define('EMAIL_API_KEY', $email_api);
}

if (!defined('EMAIL_SENDER_EMAIL')) {
    $sender = site_env('EMAIL_SENDER_EMAIL');
    if (empty($sender)) {
        $sender = site_env('BREVO_SENDER_EMAIL');
    }
    define('EMAIL_SENDER_EMAIL', $sender);
}

if (!defined('EMAIL_SENDER_NAME')) {
    $sender_name = site_env('EMAIL_SENDER_NAME', site_env('BREVO_SENDER_NAME', 'Missão Manancial'));
    define('EMAIL_SENDER_NAME', $sender_name);
}

// OneSignal (push). ONESIGNAL_APP_ID já era usado hardcoded no client-side
// (php/includes/pwa_head.php, index.html) — mantido aqui como default pra
// não precisar configurar de novo, mas pode ser sobrescrito via env. Já
// ONESIGNAL_REST_API_KEY só existe no servidor (nunca vai pro navegador) —
// é a credencial usada por onesignal_enviar_push() abaixo pra mandar push
// via API REST. Sem ela configurada, onesignal_enviar_push() não faz nada
// (retorna false), mas o resto do app funciona normal.
if (!defined('ONESIGNAL_APP_ID')) {
    define('ONESIGNAL_APP_ID', site_env('ONESIGNAL_APP_ID', 'b9ae1abb-e78f-4100-b453-62495b382aff'));
}
if (!defined('ONESIGNAL_REST_API_KEY')) {
    define('ONESIGNAL_REST_API_KEY', site_env('ONESIGNAL_REST_API_KEY'));
}

// "external_id" do fiel na OneSignal: o IDF_ID com prefixo "fiel-". Sem o
// prefixo, o IDF_ID 1 (admin) vira "1", que a OneSignal bloqueia ("external_id
// is blocked") junto com outros valores genericos ("0", "-1", "null"...).
// Usado tanto no OneSignal.login() (php/includes/pwa_head.php) quanto no envio
// abaixo — os dois precisam gerar exatamente o mesmo valor.
//
// Com ONESIGNAL_EXTERNAL_ID_SEGREDO definido, o valor ganha um sufixo HMAC
// ("fiel-42-3f9a…"): só o servidor sabe montá-lo. Sem isso, qualquer pessoa
// rodava OneSignal.login('fiel-42') no console do navegador e passava a
// receber os pushes do fiel 42 (oração, mensagens do admin). A "Identity
// Verification" da OneSignal resolveria isso, mas ela só existe nos SDKs de
// celular — a própria OneSignal manda não ligá-la com SDK web. Ao ligar o
// segredo, cada aparelho volta a receber push assim que o fiel abrir o app
// de novo (o OneSignal.login() da página passa a usar o valor novo).
//
// O HMAC também leva uma marca da senha atual (onesignal_marca_senha()):
// trocar ou redefinir a senha muda o external_id. Antes, o aparelho de quem
// invadiu a conta continuava recebendo os pushes do fiel mesmo depois do
// reset; agora ele fica preso ao valor antigo, e os aparelhos do dono se
// vinculam ao novo sozinhos na próxima página aberta.
function onesignal_external_id($fiel_id) {
    $fiel_id = (int) $fiel_id;
    if (trim((string) site_env('ONESIGNAL_EXTERNAL_ID_SEGREDO')) === '') {
        return 'fiel-' . $fiel_id;
    }
    return onesignal_external_id_com_marca($fiel_id, onesignal_marca_senha($fiel_id));
}

// Mesma conta de onesignal_external_id() com a marca da senha já em mãos
// (o envio em lote lê o hash de todos numa consulta só).
function onesignal_external_id_com_marca($fiel_id, $marca) {
    $fiel_id = (int) $fiel_id;
    $segredo = trim((string) site_env('ONESIGNAL_EXTERNAL_ID_SEGREDO'));
    if ($segredo === '') {
        return 'fiel-' . $fiel_id;
    }
    $marca = (string) $marca;
    return 'fiel-' . $fiel_id . '-' . substr(hash_hmac('sha256', 'fiel-' . $fiel_id . ($marca !== '' ? '|' . $marca : ''), $segredo), 0, 24);
}

// Marca da senha a partir do hash guardado (mesma de onesignal_marca_senha()).
function onesignal_marca_do_hash($hash) {
    $hash = (string) $hash;
    return $hash !== '' ? substr(hash('sha256', $hash), 0, 8) : '';
}

// 8 caracteres do SHA-256 do hash da senha do fiel ('' se não houver hash
// ou o banco falhar). Do fiel logado fica guardada na sessão — pwa_head.php
// monta o external_id em toda página e não vale uma consulta a mais por
// navegação. A sessão não fica velha: redefinir a senha derruba todas as
// sessões, e o login e senha_trocar() apagam a marca guardada.
function onesignal_marca_senha($fiel_id) {
    global $conn;
    static $cache = array();
    $fiel_id = (int) $fiel_id;
    if (isset($cache[$fiel_id])) {
        return $cache[$fiel_id];
    }
    $eh_o_logado = (int) ($_SESSION['Usuario_id'] ?? 0) === $fiel_id;
    $guardada = $_SESSION['push_marca'] ?? null;
    if ($eh_o_logado && is_array($guardada) && (int) ($guardada['id'] ?? 0) === $fiel_id) {
        return $cache[$fiel_id] = (string) ($guardada['marca'] ?? '');
    }

    $res = $conn ? db_query($conn, "SELECT IDF_SENHA_HASH FROM ID_FIEL WHERE IDF_ID = ? LIMIT 1", array($fiel_id)) : false;
    if (!$res) {
        return ''; // não guarda: tenta de novo na próxima vez
    }
    $linha = mysqli_fetch_assoc($res);
    $hash = $linha ? (string) ($linha['IDF_SENHA_HASH'] ?? '') : '';
    $cache[$fiel_id] = onesignal_marca_do_hash($hash);
    if ($eh_o_logado && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['push_marca'] = array('id' => $fiel_id, 'marca' => $cache[$fiel_id]);
    }
    return $cache[$fiel_id];
}

// Manda 1 push pra 1 fiel especifico via OneSignal, usando o IDF_ID dele como
// "external_id" (ver OneSignal.login() em php/includes/pwa_head.php, que
// amarra o dispositivo/navegador logado a esse mesmo ID no client-side).
// $link (opcional) e um caminho relativo a php/fiel/ (ex.:
// "Certificados/certificados.php", "curso.php?id=5") pra abrir ao clicar no
// push — vira o campo "url" da API da OneSignal, sempre montado a partir de
// SITE_BASE_URL (nunca aceita URL absoluta vinda de fora, so caminho local).
// Só funciona se: (1) ONESIGNAL_REST_API_KEY estiver configurada, e (2) o
// fiel tiver aberto o app nesse navegador e aceitado a permissao de
// notificacao (senao o OneSignal so tem o external_id, sem nenhum
// dispositivo inscrito pra mandar nada). Falha em silencio (retorna false)
// em qualquer um desses casos — nunca quebra o fluxo que chamou.
// $resultado (opcional, por referência): 'ok', 'sem_destino' (a OneSignal
// aceitou o pedido, mas o fiel não tem aparelho inscrito — tentar de novo não
// adianta) ou 'falha' (rede, chave errada, erro da API — vale tentar depois).
function onesignal_enviar_push($fiel_id, $titulo, $mensagem, $link = null, &$resultado = null) {
    $resultado = 'falha';
    $fiel_id = (int) $fiel_id;
    if ($fiel_id <= 0) {
        return false;
    }
    return onesignal_enviar_push_aliases(array(onesignal_external_id($fiel_id)), $titulo, $mensagem, $link, $resultado, 'o fiel ' . $fiel_id);
}

// Um push para vários aparelhos numa chamada só (a OneSignal aceita milhares
// de external_id por pedido). Mesmos retornos de onesignal_enviar_push().
function onesignal_enviar_push_aliases(array $external_ids, $titulo, $mensagem, $link = null, &$resultado = null, $rotulo_log = 'um lote') {
    $resultado = 'falha';
    $rest_key = trim((string) ONESIGNAL_REST_API_KEY);
    $external_ids = array_values(array_filter(array_map('strval', $external_ids)));

    if (!$external_ids || $rest_key === '' || !function_exists('curl_init')) {
        return false;
    }

    $payload = array(
        'app_id' => ONESIGNAL_APP_ID,
        'include_aliases' => array('external_id' => $external_ids),
        'target_channel' => 'push',
        'headings' => array('en' => $titulo),
        'contents' => array('en' => $mensagem),
    );

    $link = trim((string) $link);
    if ($link !== '') {
        // URL fixa do site (SITE_URL_PUBLICA), não a montada a partir do
        // cabeçalho Host da requisição que disparou o push — que pode ser de
        // outro fiel (o "amém" de alguém manda push ao autor da oração).
        $payload['url'] = site_url_publica('php/fiel/' . ltrim($link, '/'));
    }

    $ch = curl_init('https://api.onesignal.com/notifications');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Key ' . $rest_key,
            'Content-Type: application/json',
            'Accept: application/json',
        ),
        // Prazo curto: o push roda dentro da requisição de quem comentou ou
        // postou (antes 15 s seguravam um processo do Apache).
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
    ));

    $response = curl_exec($ch);
    $curl_error = curl_errno($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curl_error || $http_code < 200 || $http_code >= 300) {
        error_log('OneSignal: falha ao enviar push para ' . $rotulo_log . ' (http_code=' . $http_code . ', curl_error=' . $curl_error . ')');
        // No Sentry no máximo a cada 5 min (pode acontecer a cada comentário).
        if (site_intervalo_livre('alerta_push', 300)) {
            site_alerta_erro('error', 'Push não saiu pela OneSignal (http_code=' . $http_code . ', curl_error=' . $curl_error . ')', 'PushFalhou');
        }
        return false;
    }
    // A OneSignal responde 200 mesmo quando ninguém recebe (fiel sem aparelho
    // inscrito): o "id" vem vazio e o motivo vem em "errors". Antes isso
    // contava como enviado.
    $corpo = json_decode((string) $response, true);
    if (!is_array($corpo) || empty($corpo['id'])) {
        $resultado = 'sem_destino';
        return false;
    }
    $resultado = 'ok';
    return true;
}

function site_url($path = '') {
    return rtrim(SITE_BASE_URL, '/') . '/' . ltrim($path, '/');
}

// "fulano@gmail.com" -> "fu***@gmail.com", para logs.
function site_mascarar_email($email) {
    $email = (string) $email;
    $arroba = strrpos($email, '@');
    if ($arroba === false) {
        return '***';
    }
    return mb_substr(substr($email, 0, $arroba), 0, 2) . '***' . substr($email, $arroba);
}

function enviar_email_sistema($destinatario, $assunto, $mensagem_html, $mensagem_texto = '') {
    $destinatario = trim((string) $destinatario);
    if ($destinatario === '') {
        return false;
    }

    $api_key = trim((string) EMAIL_API_KEY);
    $sender_email = trim((string) EMAIL_SENDER_EMAIL);

    if ($api_key !== '' && $sender_email !== '' && function_exists('curl_init')) {
        $payload = array(
            'sender' => array(
                'name' => trim((string) EMAIL_SENDER_NAME) !== '' ? EMAIL_SENDER_NAME : 'Missão Manancial',
                'email' => $sender_email,
            ),
            'to' => array(
                array(
                    'email' => $destinatario,
                ),
            ),
            'subject' => $assunto,
            'htmlContent' => $mensagem_html,
        );

        if ($mensagem_texto !== '') {
            $payload['textContent'] = $mensagem_texto;
        }

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => array(
                'api-key: ' . $api_key,
                'Content-Type: application/json',
                'Accept: application/json',
            ),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
        ));

        $response = curl_exec($ch);
        $curl_error = curl_errno($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$curl_error && $http_code >= 200 && $http_code < 300) {
            return true;
        }

        // E-mail mascarado (fu***@gmail.com): o log do Render não é lugar
        // de dado pessoal, e o domínio já basta para investigar a falha.
        error_log('Brevo: falha ao enviar e-mail para ' . site_mascarar_email($destinatario) . ' (http_code=' . $http_code . ', curl_error=' . $curl_error . ', response=' . $response . ')');
        // No Sentry também (antes só no log): chave vencida ou IP bloqueado
        // na Brevo paravam todos os e-mails (esqueci a senha, aprovação) e a
        // tela continuava dizendo "enviamos". Sem o destinatário.
        site_alerta_erro('error', 'E-mail não saiu pela Brevo (http_code=' . $http_code . ', curl_error=' . $curl_error . ')', 'EmailFalhou');
    }

    if ($api_key === '' || $sender_email === '') {
        // No Render não há sendmail: sem a Brevo configurada, o mail() abaixo
        // falha em silêncio. Fica no log para alguém perceber.
        error_log('E-mail: EMAIL_API_KEY/EMAIL_SENDER_EMAIL não configuradas; tentando mail() para ' . site_mascarar_email($destinatario) . '.');
    }

    // No Render (variável RENDER) não existe sendmail: o mail() só fingia
    // tentar. Lá a falha já foi avisada acima.
    if (!function_exists('mail') || getenv('RENDER') !== false) {
        return false;
    }

    $assunto_formatado = '=?UTF-8?B?' . base64_encode($assunto) . '?=';
    $headers = array();
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . (trim((string) EMAIL_SENDER_NAME) !== '' ? EMAIL_SENDER_NAME : 'Missão Manancial') . ' <' . ($sender_email !== '' ? $sender_email : 'no-reply@localhost') . '>';
    $headers[] = 'Reply-To: ' . ($sender_email !== '' ? $sender_email : 'no-reply@localhost');

    $html = $mensagem_html;
    if ($mensagem_texto !== '') {
        $html .= '<hr><pre style="font-family: Arial, sans-serif; white-space: pre-wrap; color: #334155;">' . htmlspecialchars($mensagem_texto) . '</pre>';
    }

    return @mail($destinatario, $assunto_formatado, $html, implode("\r\n", $headers));
}

// Página para onde voltar depois do login (ex.: link de comentário
// compartilhado, T20). Só aceita página da área do fiel com query simples —
// nada de URL externa (evita redirecionamento aberto).
function auth_voltar_valido($voltar) {
    $voltar = (string) $voltar;
    return preg_match('#^php/fiel/[a-z0-9_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$#D', $voltar) ? $voltar : '';
}

// A página atual, se valer a pena voltar a ela depois do login (GET de
// página da área do fiel; endpoints ajax_ não).
function auth_voltar_atual() {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return '';
    }
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $arquivo = basename($script);
    if (strpos($script, '/php/fiel/') === false || strpos($arquivo, 'ajax_') === 0) {
        return '';
    }
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    return auth_voltar_valido('php/fiel/' . $arquivo . ($query !== '' ? '?' . $query : ''));
}

function auth_logout($reason = '', $voltar = '') {
    global $conn;

    if ($conn && isset($_SESSION['Usuario_token']) && isset($_SESSION['Usuario_id'])) {
        $token = (string) $_SESSION['Usuario_token'];
        $usuario_id = (int) $_SESSION['Usuario_id'];
        @db_query($conn, "UPDATE ID_SESSAO SET IDS_REVOGADA = 1 WHERE IDS_TOKEN = ? AND IDF_ID = ? LIMIT 1", array($token, $usuario_id));
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    session_unset();
    session_destroy();

    $query_params = $reason !== '' ? array('reason' => $reason) : array();
    $query_params['onesignal_logout'] = '1'; // desfaz o vinculo do OneSignal (ver php/includes/pwa_head.php)
    $voltar = auth_voltar_valido($voltar);
    if ($voltar !== '') {
        $query_params['voltar'] = $voltar;
    }
    header('Location: ' . site_url('login.php') . '?' . http_build_query($query_params));
    exit;
}

// Abre a sessão de quem acabou de provar quem é: senha (login.php) ou senha
// + código da verificação em duas etapas (login_2fa.php). $conta traz
// IDF_ID, IDF_NOME, IDF_EMAIL e EH_ADMIN. Devolve false se não conseguiu
// gravar em ID_SESSAO (aí o login é recusado, com a sessão limpa).
function auth_iniciar_sessao($conn, array $conta) {
    session_regenerate_id(true);
    // Token CSRF novo junto com a sessão: o de antes do login podia ter sido
    // visto (ou fixado) por outra pessoa no mesmo aparelho. A marca da senha
    // do external_id da OneSignal é recalculada (o login pode ter acabado de
    // regravar o hash da senha).
    unset($_SESSION['csrf_token'], $_SESSION['push_marca'], $_SESSION['login_2fa']);
    $_SESSION["Usuario_id"] = (int) $conta['IDF_ID'];
    // O e-mail como está no banco (o digitado pode ter outras maiúsculas).
    $_SESSION["Usuario_logado"] = (string) $conta['IDF_EMAIL'];
    $_SESSION["Usuario_nome"] = (string) ($conta['IDF_NOME'] ?? $conta['IDF_EMAIL']);
    $_SESSION["Usuario_tipo"] = (int) ($conta['EH_ADMIN'] ?? 0) === 1 ? 'admin' : 'fiel';
    $_SESSION["Usuario_token"] = bin2hex(random_bytes(32));
    $_SESSION["revogacao_ok_em"] = time();
    $_SESSION["Usuario_login_at"] = time();
    $_SESSION["Usuario_last_activity"] = time();

    // Sem a linha em ID_SESSAO a sessão não poderia ser derrubada (logout
    // remoto, troca de senha) — e auth_require() a trata como encerrada.
    if (!auth_store_session($conn)) {
        session_unset();
        return false;
    }
    login_avisar_aparelho_novo($conn, (int) $conta['IDF_ID'], (string) $conta['IDF_EMAIL'], (string) ($conta['IDF_NOME'] ?? ''));
    return true;
}

// "Chrome no Android", "Safari no iPhone"… a partir do User-Agent. Só o
// tipo de navegador e de sistema: versão muda a cada atualização e faria
// todo aparelho conhecido parecer novo.
function site_aparelho_descricao($user_agent) {
    $ua = (string) $user_agent;
    $sistemas = array(
        'iPhone' => '/iPhone|iPod/i', 'iPad' => '/iPad/i', 'Android' => '/Android/i',
        'Windows' => '/Windows/i', 'Mac' => '/Macintosh|Mac OS X/i', 'Linux' => '/Linux|CrOS/i',
    );
    $navegadores = array(
        'Edge' => '/Edg(e|A|iOS)?\//i', 'Opera' => '/OPR\/|Opera/i', 'Samsung Internet' => '/SamsungBrowser/i',
        'Firefox' => '/Firefox|FxiOS/i', 'Chrome' => '/Chrome|CriOS/i', 'Safari' => '/Safari/i',
    );
    $sistema = 'outro sistema';
    foreach ($sistemas as $nome => $padrao) {
        if (preg_match($padrao, $ua)) {
            $sistema = $nome;
            break;
        }
    }
    $navegador = 'outro navegador';
    foreach ($navegadores as $nome => $padrao) {
        if (preg_match($padrao, $ua)) {
            $navegador = $nome;
            break;
        }
    }
    return $navegador . ' no ' . $sistema;
}

// Login num tipo de aparelho que a conta nunca usou ("Chrome no Windows"
// quando antes só havia "Safari no iPhone"): e-mail "Foi você?" para o dono,
// com o caminho para trocar a senha (que desconecta os outros aparelhos).
// Primeiro login da conta não avisa. Compara com as sessões guardadas
// (ID_SESSAO, 7 meses). Falha no envio não muda nada no login.
function login_avisar_aparelho_novo($conn, $fiel_id, $email, $nome) {
    if (!$conn || $fiel_id <= 0 || $email === '' || !defined('EMAIL_API_KEY') || trim((string) EMAIL_API_KEY) === '') {
        return;
    }
    $atual = site_aparelho_descricao($_SERVER['HTTP_USER_AGENT'] ?? '');
    $res = db_query($conn, "SELECT IDS_USER_AGENT FROM ID_SESSAO WHERE IDF_ID = ? AND IDS_TOKEN <> ? ORDER BY IDS_CRIADA_EM DESC LIMIT 100", array($fiel_id, (string) ($_SESSION['Usuario_token'] ?? '')));
    if (!$res || mysqli_num_rows($res) === 0) {
        return; // primeiro acesso (ou erro de banco): sem aviso
    }
    while ($linha = mysqli_fetch_assoc($res)) {
        if (site_aparelho_descricao($linha['IDS_USER_AGENT'] ?? '') === $atual) {
            return; // aparelho já conhecido
        }
    }
    $quando = date('d/m/Y') . ' às ' . date('H:i');
    $link = site_url_publica('esqueci_senha.php');
    $nome_html = htmlspecialchars($nome !== '' ? $nome : 'olá');
    try {
        enviar_email_sistema(
            $email,
            'Novo acesso à sua conta',
            '<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;"><h2 style="margin: 0 0 16px; color: #1d4ed8;">Novo acesso à sua conta</h2>'
                . '<p>Olá, <strong>' . $nome_html . '</strong>.</p>'
                . '<p>Sua conta foi acessada de um aparelho novo: <strong>' . htmlspecialchars($atual) . '</strong>, em ' . htmlspecialchars($quando) . '.</p>'
                . '<p>Se foi você, pode ignorar este e-mail.</p>'
                . '<p><strong>Se não foi você</strong>, troque a senha agora: <a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a>. Trocar a senha desconecta todos os outros aparelhos.</p></div>',
            "Olá, {$nome}!\n\nSua conta foi acessada de um aparelho novo: {$atual}, em {$quando}.\n\nSe foi você, pode ignorar este e-mail.\n\nSe não foi você, troque a senha agora: {$link}\nTrocar a senha desconecta todos os outros aparelhos."
        );
    } catch (\Throwable $e) {
        // Aviso é cortesia: não atrapalha o login.
    }
}

// Para onde vai quem acabou de entrar: o link de antes do login (?voltar=)
// ou o painel/área de cada tipo.
function auth_destino_pos_login($eh_admin, $voltar = '') {
    $voltar = auth_voltar_valido($voltar);
    if ($voltar !== '') {
        return site_url($voltar);
    }
    return site_url($eh_admin ? 'php/admin/dashboard.php' : 'php/fiel/dashboard.php');
}

// Grava (ou renova) a linha desta sessão em ID_SESSAO. Devolve false se não
// conseguiu: o login usa isso para não abrir uma sessão que ninguém
// conseguiria derrubar depois (ver auth_sessao_revogada()).
function auth_store_session($database = null) {
    global $conn;
    $database = $database ?? $conn;

    $usuario_id = auth_resolve_fiel_id();
    if (!$database || $usuario_id <= 0 || !isset($_SESSION['Usuario_token'])) {
        return false;
    }

    // ID_TOKEN agora tem UNIQUE de verdade (ver seção 33 do bancosupabase.md)
    // — sem isso, como auth_touch() roda a cada página (pra checar o timeout
    // de inatividade), isso aqui virava um INSERT novo a cada página vista
    // pelo mesmo usuário na mesma sessão, enchendo ID_SESSAO sem necessidade.
    // ON CONFLICT atualiza a linha existente em vez de duplicar.
    $sql_insert = "
        INSERT INTO ID_SESSAO (IDF_ID, IDS_TOKEN, IDS_IP, IDS_USER_AGENT, IDS_EXPIRA_EM, IDS_REVOGADA)
        VALUES (?, ?, ?, ?, NOW() + INTERVAL '1 hour', 0)
        ON CONFLICT (IDS_TOKEN) DO UPDATE SET
            IDS_IP = EXCLUDED.IDS_IP,
            IDS_USER_AGENT = EXCLUDED.IDS_USER_AGENT,
            IDS_EXPIRA_EM = EXCLUDED.IDS_EXPIRA_EM
    ";
    // Cortados no tamanho das colunas: um User-Agent com mais de 255
    // caracteres (comum em navegador embutido de app) fazia o INSERT falhar
    // em silêncio, e a sessão ficava sem linha em ID_SESSAO.
    return db_query($database, $sql_insert, array(
        $usuario_id,
        (string) $_SESSION['Usuario_token'],
        // REMOTE_ADDR no Render é sempre "::1" (proxy local); o IP real vem
        // do X-Forwarded-For (ver site_ip_cliente()).
        substr(site_ip_cliente(), 0, 45),
        mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    )) !== false;
}

// Verdadeiro se a sessão deste navegador não vale mais. Uma consulta só
// (roda em toda página) confere:
// - a linha em ID_SESSAO: revogada (logout, admin "desconectar", troca ou
//   redefinição de senha) ou INEXISTENTE — antes, sessão sem linha (gravação
//   do login que falhou) passava direto e nenhuma revogação a derrubava;
// - a conta: deixou de estar aprovada/ativa (admin negou ou desativou);
// - o papel: o tipo guardado na sessão no login ('admin'/'fiel') não bate
//   mais com ID_ADMIN. Antes, um admin removido em Permissões continuava
//   com o painel inteiro até a sessão expirar; agora cai na hora (e quem
//   foi promovido entra de novo já como admin).
// Erro de banco não derruba ninguém de imediato (seria deslogar todo mundo
// num soluço do Supabase), mas só por um tempo: vale enquanto a última
// conferência boa tiver até 5 min (1 min para admin). Antes valia para
// sempre, e um admin removido seguia entrando enquanto o banco falhasse.
function auth_sessao_revogada($conn) {
    if (!$conn) {
        return false;
    }
    if (!isset($_SESSION['Usuario_token'])) {
        return true;
    }
    $res = db_query($conn, "
        SELECT s.IDS_REVOGADA, f.IDF_STATUS, COALESCE(f.IDF_ATIVO, 1) AS ATIVO,
               EXISTS (SELECT 1 FROM ID_ADMIN a WHERE a.IDA_FIEL_ID = s.IDF_ID AND a.IDA_ATIVO = 1) AS EH_ADMIN
        FROM ID_SESSAO s
        LEFT JOIN ID_FIEL f ON f.IDF_ID = s.IDF_ID
        WHERE s.IDS_TOKEN = ?
        LIMIT 1
    ", array((string) $_SESSION['Usuario_token']));
    if ($res === false) {
        $folga = (($_SESSION['Usuario_tipo'] ?? '') === 'admin') ? 60 : 300;
        return time() - (int) ($_SESSION['revogacao_ok_em'] ?? 0) > $folga;
    }
    $linha = mysqli_fetch_assoc($res);
    if (!$linha || (int) $linha['IDS_REVOGADA'] === 1) {
        return true;
    }
    if (($linha['IDF_STATUS'] ?? '') !== 'aprovado' || (int) $linha['ATIVO'] !== 1) {
        return true;
    }
    $tipo_banco = !empty($linha['EH_ADMIN']) ? 'admin' : 'fiel';
    if (($_SESSION['Usuario_tipo'] ?? '') !== $tipo_banco) {
        return true;
    }
    $_SESSION['revogacao_ok_em'] = time();
    return false;
}

// Derruba todas as sessões abertas de um fiel (auth_require() confere a cada
// página). Usado quando o admin muda algo que a sessão guardou no login:
// status da conta, permissão de admin, "desconectar".
function auth_revogar_sessoes($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    if (!$conn || $fiel_id <= 0) {
        return false;
    }
    return db_query($conn, "UPDATE ID_SESSAO SET IDS_REVOGADA = 1 WHERE IDF_ID = ? AND IDS_REVOGADA = 0", array($fiel_id)) !== false;
}

function auth_resolve_fiel_id() {
    global $conn;
    // Cache só desta requisição: a função é chamada várias vezes por página
    // (auth_touch, gate do termo, navbar, a própria página...) e cada
    // chamada era 1-2 consultas ao Postgres remoto do Supabase.
    static $cache = array();

    if (!$conn || !isset($_SESSION['Usuario_id'])) {
        return 0;
    }

    $usuario_id = (int) $_SESSION['Usuario_id'];
    if (isset($cache[$usuario_id])) {
        return $cache[$usuario_id];
    }
    // Já confirmado nesta sessão: não consulta de novo em toda página. Se a
    // conta for apagada, as linhas de ID_SESSAO vão junto (ON DELETE CASCADE)
    // e auth_sessao_revogada() derruba a sessão antes de chegar aqui.
    if (($_SESSION['Usuario_id_confirmado'] ?? 0) === $usuario_id) {
        return $cache[$usuario_id] = $usuario_id;
    }

    $sql_fiel = "SELECT IDF_ID FROM ID_FIEL WHERE IDF_ID = $usuario_id LIMIT 1";
    $res_fiel = mysqli_query($conn, $sql_fiel);
    if ($res_fiel && mysqli_num_rows($res_fiel) > 0) {
        $_SESSION['Usuario_id_confirmado'] = $usuario_id;
        return $cache[$usuario_id] = $usuario_id;
    }

    $sql_admin = "SELECT IDA_FIEL_ID FROM ID_ADMIN WHERE IDA_ID = $usuario_id LIMIT 1";
    $res_admin = mysqli_query($conn, $sql_admin);
    if ($res_admin && mysqli_num_rows($res_admin) > 0) {
        $row_admin = mysqli_fetch_assoc($res_admin);
        $resolved_id = (int) ($row_admin['IDA_FIEL_ID'] ?? 0);
        if ($resolved_id > 0) {
            $_SESSION['Usuario_id'] = $resolved_id;
            $cache[$resolved_id] = $resolved_id;
            return $cache[$usuario_id] = $resolved_id;
        }
    }

    return $cache[$usuario_id] = 0;
}

// De quanto em quanto tempo auth_touch() grava a atividade no banco
// (IDF_LAST_ACTIVITY + renovação de ID_SESSAO). Gravar a cada página eram 2
// escritas no Postgres remoto por navegação, sem ganho nenhum: o timeout de
// inatividade usa $_SESSION (atualizado sempre) e a lista de "pessoas
// online" tolera PRESENCA_ONLINE_SEGUNDOS (bem maior que isso).
if (!defined('AUTH_TOUCH_INTERVALO_SEGUNDOS')) {
    define('AUTH_TOUCH_INTERVALO_SEGUNDOS', 60);
}

function auth_touch($database = null) {
    global $conn;
    $database = $database ?? $conn;
    
    if (!isset($_SESSION['Usuario_logado'])) {
        return;
    }

    if (!isset($_SESSION['Usuario_login_at'])) {
        $_SESSION['Usuario_login_at'] = time();
    }

    // Só regrava com 1 min de diferença: mudar a sessão em toda página fazia
    // ela ser regravada no banco a cada navegação (com a sessão sem mudança,
    // o PHP só renova a validade, e isso a cada 5 min). O limite de
    // inatividade é de 1 h: 1 min a mais não muda nada.
    if (time() - (int) ($_SESSION['Usuario_last_activity'] ?? 0) >= 60) {
        $_SESSION['Usuario_last_activity'] = time();
    }

    // Atualizar last_activity no banco de dados
    $usuario_id = auth_resolve_fiel_id();

    if (!$database || $usuario_id <= 0) {
        return;
    }

    // Guardado junto com o id do usuário pra não herdar o "já gravei" de
    // outra conta logada antes no mesmo navegador.
    $ultimo_touch = $_SESSION['Usuario_db_touch'] ?? array();
    $precisa_gravar = ($ultimo_touch['id'] ?? 0) !== $usuario_id
        || (time() - (int) ($ultimo_touch['em'] ?? 0)) >= AUTH_TOUCH_INTERVALO_SEGUNDOS;

    if ($precisa_gravar) {
        // A presença (ajax_presenca.php, a cada 60 s) já grava o "visto por
        // último": se gravou há pouco, não repete a mesma escrita em ID_FIEL.
        if (time() - (int) ($_SESSION['presenca_gravou_em'] ?? 0) >= AUTH_TOUCH_INTERVALO_SEGUNDOS) {
            $sql_touch = "UPDATE ID_FIEL SET IDF_LAST_ACTIVITY = NOW() WHERE IDF_ID = $usuario_id LIMIT 1";
            @mysqli_query($database, $sql_touch);
        }
        auth_store_session($database);
        $_SESSION['Usuario_db_touch'] = array('id' => $usuario_id, 'em' => time());
    }

    // Já tem cache próprio em $_SESSION (1 INSERT por dia no máximo).
    ofensiva_registrar_acesso($database, $usuario_id);
}

// Verdadeiro se esse fiel/admin ainda não aceitou a versão ATUAL do Termo
// de Uso (TERMOS_VERSAO_ATUAL) — nunca aceitou nenhuma (conta antiga, de
// antes dessa feature existir) ou aceitou uma versão anterior.
function termos_fiel_precisa_aceitar($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    if (!$conn || $fiel_id <= 0) {
        return false;
    }

    $res = mysqli_query($conn, "SELECT IDF_TERMOS_VERSAO_ACEITA FROM ID_FIEL WHERE IDF_ID = $fiel_id LIMIT 1");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if (!$linha) {
        return false;
    }

    return (string) ($linha['IDF_TERMOS_VERSAO_ACEITA'] ?? '') !== TERMOS_VERSAO_ATUAL;
}

// Teto absoluto de uma sessão, mesmo com uso contínuo: depois disso, entra
// de novo. Sem ele, uma sessão roubada (ou esquecida num aparelho que fica
// em uso) valia para sempre enquanto alguém clicasse a cada hora.
if (!defined('SESSION_MAX_SECONDS')) {
    define('SESSION_MAX_SECONDS', 43200); // 12 horas
}

// Problema da sessão atual, ou '' se ela vale: 'sem_login', 'sessao_expirada'
// ou 'sessao_revogada'. Usada por auth_require() (páginas) e
// auth_require_ajax() (endpoints JSON) — a mesma regra nos dois.
function auth_problema_sessao() {
    global $conn;

    if (!isset($_SESSION['Usuario_logado'])) {
        return 'sem_login';
    }

    // SESSION_TIMEOUT_SECONDS existia declarada mas nunca era checada em
    // lugar nenhum — a sessão nunca expirava por inatividade de verdade
    // (só pelo GC probabilístico do PHP). Sem isso, uma sessão esquecida
    // aberta num computador compartilhado ficava válida indefinidamente.
    $ultima_atividade = (int) ($_SESSION['Usuario_last_activity'] ?? 0);
    if ($ultima_atividade > 0 && (time() - $ultima_atividade) > SESSION_TIMEOUT_SECONDS) {
        return 'sessao_expirada';
    }
    $login_em = (int) ($_SESSION['Usuario_login_at'] ?? 0);
    if ($login_em > 0 && (time() - $login_em) > SESSION_MAX_SECONDS) {
        return 'sessao_expirada';
    }

    // ID_SESSAO existia só como log — auth_logout() gravava IDS_REVOGADA=1,
    // mas nada nunca voltava a checar isso. Sem essa consulta, um admin
    // "desconectando" outro usuário (ver admin_gerenciar_usuarios.php) não
    // tinha efeito nenhum na prática — a sessão daquele usuário continuava
    // valendo normalmente até expirar sozinha.
    if (auth_sessao_revogada($conn)) {
        return 'sessao_revogada';
    }
    return '';
}

// Verdadeiro se o fiel logado ainda precisa aceitar o Termo de Uso. Depois
// de confirmado uma vez nesta sessão, não consulta de novo — a chave inclui
// a versão do termo, então trocar TERMOS_VERSAO_ATUAL volta a exigir o
// aceite na hora.
function auth_termos_pendente() {
    global $conn;
    $usuario_id_termos = auth_resolve_fiel_id();
    $termos_chave = $usuario_id_termos . '|' . TERMOS_VERSAO_ATUAL;
    if ($usuario_id_termos <= 0 || ($_SESSION['termos_ok'] ?? '') === $termos_chave) {
        return false;
    }
    if (termos_fiel_precisa_aceitar($conn, $usuario_id_termos)) {
        return true;
    }
    $_SESSION['termos_ok'] = $termos_chave;
    return false;
}

// Modo "só assinantes" (Admin → Assinaturas): verdadeiro se o fiel logado
// não tem acesso. Admin nunca é barrado.
function auth_assinatura_bloqueia() {
    global $conn;
    if (($_SESSION['Usuario_tipo'] ?? '') === 'admin' || !assinatura_exige_no_login()) {
        return false;
    }
    $usuario_id_assinatura = auth_resolve_fiel_id();
    return $usuario_id_assinatura > 0 && !assinatura_fiel_tem_acesso($conn, $usuario_id_assinatura);
}

// Endpoints que rodam em toda página (inclusive na de planos e na do termo):
// ficam de fora do bloqueio de termo e de assinatura.
function auth_script_livre_de_bloqueio($script_atual) {
    return in_array($script_atual, array('assinar.php', 'termos.php', 'sair.php', 'ajax_onesignal_sync.php', 'ajax_preferencia_push.php'), true);
}

// Versão de auth_require() para endpoints AJAX: as mesmas checagens
// (login, inatividade, teto de 12 h, revogação, papel, termo, assinatura),
// mas respondendo JSON 401/403 em vez de redirecionar — quem chama é um
// fetch() esperando JSON. Antes, os ajax_* do fiel só olhavam se havia
// $_SESSION['Usuario_logado']: sessão revogada pelo admin, conta negada e
// fiel sem assinatura continuavam postando comentários, orações etc.
function auth_require_ajax(array $allowedRoles = array()) {
    global $conn;

    $responder = function ($codigo, $mensagem) {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'erro' => $mensagem));
        exit;
    };

    $problema = auth_problema_sessao();
    if ($problema === 'sem_login') {
        $responder(401, 'Sua sessão expirou. Entre de novo.');
    }
    if ($problema !== '') {
        // Limpa a sessão local também, para a próxima página já ir ao login.
        session_unset();
        $responder(401, 'Sua sessão foi encerrada. Entre de novo.');
    }

    auth_touch($conn);

    if (!empty($allowedRoles) && !in_array($_SESSION['Usuario_tipo'] ?? '', $allowedRoles, true)) {
        $responder(403, 'Acesso negado.');
    }

    $script_atual = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    // Grupo de permissões do admin (seção 73).
    if (in_array('admin', $allowedRoles, true) && !admin_pode_abrir_script($script_atual)) {
        $responder(403, 'Você não tem acesso a esta tela do painel.');
    }
    if (!auth_script_livre_de_bloqueio($script_atual)) {
        if (auth_termos_pendente()) {
            $responder(403, 'Aceite o Termo de Uso para continuar.');
        }
        if (auth_assinatura_bloqueia()) {
            $responder(403, 'Assinatura necessária.');
        }
    }
}

function auth_require(array $allowedRoles = array()) {
    global $conn;

    $problema = auth_problema_sessao();
    if ($problema === 'sem_login') {
        auth_logout('', auth_voltar_atual());
    }
    if ($problema === 'sessao_expirada') {
        auth_logout('sessao_expirada', auth_voltar_atual());
    }
    if ($problema === 'sessao_revogada') {
        auth_logout('sessao_revogada');
    }

    auth_touch($conn);

    // Exige aceite do Termo de Uso antes de qualquer página — exceto a
    // própria tela do termo e o logout (senão quem ainda não aceitou nunca
    // conseguiria chegar lá, ou ficaria preso sem conseguir nem sair). Ver
    // php/fiel/termos.php e TERMOS_VERSAO_ATUAL logo acima neste arquivo.
    // No modo "só assinantes", fiel sem acesso não navega pelo app — toda
    // página leva pra tela de planos. Os AJAX respondem 403 (quem chama
    // espera JSON).
    $script_atual = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!auth_script_livre_de_bloqueio($script_atual)) {
        $eh_ajax = strpos($script_atual, 'ajax_') === 0;
        if (auth_termos_pendente()) {
            if ($eh_ajax) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array('ok' => false, 'erro' => 'Aceite o Termo de Uso para continuar.'));
                exit;
            }
            header('Location: ' . site_url('php/fiel/termos.php'));
            exit;
        }
        // Configurações e Contato ficam livres do bloqueio de assinatura (o
        // Termo continua valendo): quem não assina segue podendo baixar os
        // dados, excluir a conta (LGPD) e falar com a igreja.
        if (auth_assinatura_bloqueia() && !in_array($script_atual, array('configuracoes.php', 'contato.php'), true)) {
            if ($eh_ajax) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array('ok' => false, 'erro' => 'Assinatura necessária.'));
                exit;
            }
            header('Location: ' . site_url('php/fiel/assinar.php'));
            exit;
        }
    }

    if (!empty($allowedRoles)) {
        $role = $_SESSION['Usuario_tipo'] ?? '';
        if (!in_array($role, $allowedRoles, true)) {
            if ($role === 'admin') {
                // Dashboard do painel: vale para qualquer grupo de permissões.
                header('Location: ' . site_url('php/admin/dashboard.php'));
            } else {
                header('Location: ' . site_url('php/fiel/dashboard.php'));
            }
            exit;
        }
        // Grupo de permissões do admin (seção 73): tela fora do grupo volta
        // para o dashboard do painel, que mostra o aviso.
        if (in_array('admin', $allowedRoles, true) && !admin_pode_abrir_script(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')))) {
            header('Location: ' . site_url('php/admin/dashboard.php?sem_permissao=1'));
            exit;
        }
    }
}

// ============================================================================
// CSRF (Cross-Site Request Forgery)
// ============================================================================
// Sem isso, um site malicioso podia montar um <form> escondido, auto-enviado
// via JS, mirando qualquer ação POST deste app (ex.: promover um usuário a
// admin) — como o navegador de quem estivesse logado mandaria o cookie de
// sessão junto, a ação sairia como se fosse a própria pessoa clicando. O
// cookie já tem SameSite=Lax (mitiga a maioria dos casos nos navegadores
// atuais), mas um token por sessão fecha a brecha de vez, independente do
// navegador. Um token só é gerado por sessão (não por formulário), guardado
// em $_SESSION — mais simples que gerar um por formulário, e já suficiente
// pra bloquear um POST forjado vindo de fora.
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Token do link "Sair" (php/fiel/sair.php?t=...). Sem ele, qualquer site
// derrubava a sessão do fiel com um <img src=".../sair.php">. É derivado do
// token CSRF em vez de ser ele mesmo: o link fica em log e histórico, e quem
// o vir só consegue deslogar, não postar formulários.
function logout_token() {
    return substr(hash_hmac('sha256', 'sair', csrf_token()), 0, 32);
}

// Cola isso dentro de todo <form method="POST"> que muda algo no banco.
function csrf_campo() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

// Chame logo no início de todo bloco que processa um POST sensível. Se
// retornar false, NÃO processe a ação — só mostre um erro pedindo pra
// atualizar a página (o token pode ter expirado junto com a sessão, ou o
// POST pode ser forjado).
// $token opcional: por padrão valida contra $_POST['csrf_token'] (form
// tradicional); endpoints que recebem o token dentro de um corpo JSON (ex.:
// php/fiel/ajax_transmissao_comentar.php) passam o valor já extraído aqui,
// em vez de reimplementar essa comparação.
function csrf_validar($token = null) {
    $token_sessao = (string) ($_SESSION['csrf_token'] ?? '');
    $token_recebido = (string) ($token ?? ($_POST['csrf_token'] ?? ''));
    return $token_sessao !== '' && $token_recebido !== '' && hash_equals($token_sessao, $token_recebido);
}

// ============================================================================
// SENHA E RECUPERAÇÃO DE SENHA (esqueci_senha.php / redefinir_senha.php)
// ============================================================================
// O porquê de cada regra está em docs/interno/reset_de_senha.md. Tabelas:
// id_recuperacao_senha (seção 12) e id_recuperacao_pedido (seção 65) do
// bancosupabase.md.

// bcrypt (password_hash) ignora tudo depois do byte 72.
if (!defined('SENHA_MIN')) {
    define('SENHA_MIN', 8);
}
if (!defined('SENHA_MAX')) {
    define('SENHA_MAX', 72);
}
if (!defined('RECUPERACAO_VALIDADE_MINUTOS')) {
    define('RECUPERACAO_VALIDADE_MINUTOS', 60);
}
// Conta de admin: link mais curto — invadir o e-mail de um admin dá acesso ao
// painel inteiro.
if (!defined('RECUPERACAO_VALIDADE_ADMIN_MINUTOS')) {
    define('RECUPERACAO_VALIDADE_ADMIN_MINUTOS', 30);
}
// Depois de aberto o link, o token fica guardado na sessão do navegador só
// por esse tempo (computador compartilhado: quem sentar depois não conclui a
// troca).
if (!defined('RECUPERACAO_SESSAO_MINUTOS')) {
    define('RECUPERACAO_SESSAO_MINUTOS', 15);
}
// Pedidos por hora. O limite por e-mail impede encher a caixa de alguém; o
// por IP segura um robô só. O por IP é folgado de propósito: em rede de
// celular (CGNAT) e no Wi-Fi da igreja muita gente sai pelo mesmo IP.
if (!defined('RECUPERACAO_MAX_POR_EMAIL')) {
    define('RECUPERACAO_MAX_POR_EMAIL', 3);
}
if (!defined('RECUPERACAO_MAX_POR_IP')) {
    define('RECUPERACAO_MAX_POR_IP', 20);
}
// Teto de e-mails de recuperação do SITE INTEIRO (por hora e por dia). É o
// que protege a cota da Brevo: com uma lista de e-mails de membros e vários
// IPs, os limites acima não impedem gastar a cota do dia — e aí param todos
// os e-mails do app (aprovação de cadastro, o próprio reset). Uso normal de
// uma igreja fica muito abaixo disso.
if (!defined('RECUPERACAO_MAX_GLOBAL_HORA')) {
    define('RECUPERACAO_MAX_GLOBAL_HORA', 30);
}
if (!defined('RECUPERACAO_MAX_GLOBAL_DIA')) {
    define('RECUPERACAO_MAX_GLOBAL_DIA', 100);
}
// Intervalo mínimo entre dois pedidos do mesmo e-mail (clique duplo).
if (!defined('RECUPERACAO_INTERVALO_SEGUNDOS')) {
    define('RECUPERACAO_INTERVALO_SEGUNDOS', 60);
}

// Regra única de senha para cadastro e redefinição. Devolve a mensagem de
// erro, ou '' se a senha serve.
function senha_validar_politica($senha, $senha_confirma) {
    $senha = (string) $senha;
    if (strlen($senha) < SENHA_MIN || strlen($senha) > SENHA_MAX) {
        return "Senha inválida. Use entre " . SENHA_MIN . " e " . SENHA_MAX . " caracteres.";
    }
    // O pattern do <input> já pede isso no navegador, mas um POST direto
    // (curl/Postman) ignora o pattern.
    if (!preg_match('/[A-Z]/', $senha) || !preg_match('/[^A-Za-z0-9]/', $senha)) {
        return "Senha inválida. Use pelo menos 1 letra maiúscula e 1 caractere especial.";
    }
    if ($senha !== (string) $senha_confirma) {
        return "As senhas não conferem. Tente novamente.";
    }
    // Por último: é a única regra que faz chamada externa.
    if (senha_vazada($senha)) {
        return "Essa senha já apareceu em vazamentos de dados na internet e é das primeiras que um invasor tenta. Escolha outra.";
    }
    return '';
}

// Verdadeiro se a senha está na base do Have I Been Pwned (senhas expostas em
// vazamentos). Senhas como "Senha@123" passam na regra acima e estão no topo
// das listas que robôs testam. A senha não sai daqui: vão só os 5 primeiros
// caracteres do SHA-1 dela, a API devolve todos os finais com esse começo
// (centenas) e a comparação é feita aqui (k-anonimato). Add-Padding embaralha
// o tamanho da resposta. Se a API não responder em 3 s, a senha é aceita —
// o cadastro não pode parar por causa de um serviço de fora.
// SENHA_CHECAR_VAZADAS=0 no .env desliga a checagem.
function senha_vazada($senha) {
    if (!function_exists('curl_init') || trim((string) site_env('SENHA_CHECAR_VAZADAS', '1')) === '0') {
        return false;
    }
    $sha1 = strtoupper(sha1((string) $senha));
    $ch = curl_init('https://api.pwnedpasswords.com/range/' . substr($sha1, 0, 5));
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_HTTPHEADER => array('Add-Padding: true', 'User-Agent: MananciAPP'),
    ));
    $resposta = curl_exec($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($resposta) || $http_code !== 200) {
        return false;
    }
    $sufixo = substr($sha1, 5);
    foreach (explode("\n", $resposta) as $linha) {
        $partes = explode(':', trim($linha));
        // As linhas de enchimento (Add-Padding) vêm com contagem 0.
        if (count($partes) === 2 && $partes[0] === $sufixo && (int) $partes[1] > 0) {
            return true;
        }
    }
    return false;
}

// Limite de cadastros (register.php) por conexão: CADASTRO_MAX_POR_HORA
// tentativas por IP (IPv6 pela faixa /64). Folgado de propósito — no Wi-Fi
// da igreja muita gente se cadastra pelo mesmo IP —, mas segura um script
// criando milhares de contas pendentes e disparando e-mails pela Brevo.
// Usa a tabela de tentativas de login (ID_LOGIN_TENTATIVA) com a chave
// "cadastro:<ip>" no lugar do e-mail, para não precisar de tabela nova.
if (!defined('CADASTRO_MAX_POR_HORA')) {
    define('CADASTRO_MAX_POR_HORA', 30);
}

function cadastro_limite_atingido($conn, $chave) {
    if (!$conn) {
        return false;
    }
    $chave = mb_substr((string) $chave, 0, 100);
    db_query($conn, "DELETE FROM ID_LOGIN_TENTATIVA WHERE IDLT_EMAIL LIKE 'cadastro:%' AND IDLT_CRIADO_EM <= NOW() - INTERVAL '1 hour'");
    $res = db_query($conn, "SELECT COUNT(*) AS total FROM ID_LOGIN_TENTATIVA WHERE IDLT_EMAIL = ?", array($chave));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha && (int) $linha['total'] >= CADASTRO_MAX_POR_HORA;
}

function cadastro_registrar_tentativa($conn, $chave) {
    if ($conn) {
        db_query($conn, "INSERT INTO ID_LOGIN_TENTATIVA (IDLT_EMAIL) VALUES (?)", array(mb_substr((string) $chave, 0, 100)));
    }
}

// Limite de tentativas do login (login.php), em três contadores na mesma
// tabela ID_LOGIN_TENTATIVA, todos na janela de 15 min:
// - e-mail + IP: 5 erros. É o que barra quem chuta senhas de uma conta.
//   Antes o bloqueio era só pelo e-mail: 5 POSTs de qualquer lugar
//   trancavam a conta de outra pessoa (o admin, por exemplo) e dava para
//   repetir sem fim. Agora quem erra fica preso só no próprio IP.
// - só o e-mail: 30 erros, vindos de muitos IPs (ataque distribuído).
// - só o IP: 50 erros, em e-mails diferentes ("password spraying": uma
//   senha comum testada em centenas de contas, que o limite por e-mail não
//   pegava). Folgado por causa do Wi-Fi da igreja.
// A linha do e-mail continua sendo o próprio e-mail (senha_trocar() conta
// essas mesmas linhas); as outras usam prefixo, como "cadastro:<ip>".
if (!defined('LOGIN_JANELA_SEGUNDOS')) {
    define('LOGIN_JANELA_SEGUNDOS', 900);
    define('LOGIN_MAX_POR_EMAIL_IP', 5);
    define('LOGIN_MAX_POR_EMAIL', 30);
    define('LOGIN_MAX_POR_IP', 50);
}

function login_chaves_limite($email_lower) {
    $ip = site_ip_chave_limite(site_ip_cliente());
    return array(
        'email' => mb_substr((string) $email_lower, 0, 100),
        'par' => 'login-par:' . substr(hash('sha256', $email_lower . '|' . $ip), 0, 40),
        'ip' => mb_substr('login-ip:' . $ip, 0, 100),
    );
}

// Verdadeiro se algum dos três contadores já chegou no limite.
function login_tentativas_bloqueado($conn, $email_lower) {
    if (!$conn) {
        return false;
    }
    $chaves = login_chaves_limite($email_lower);
    db_query($conn, "DELETE FROM ID_LOGIN_TENTATIVA WHERE IDLT_EMAIL IN (?, ?, ?) AND IDLT_CRIADO_EM <= NOW() - INTERVAL '" . LOGIN_JANELA_SEGUNDOS . " seconds'", array($chaves['email'], $chaves['par'], $chaves['ip']));
    $res = db_query($conn, "SELECT IDLT_EMAIL AS chave, COUNT(*) AS total FROM ID_LOGIN_TENTATIVA WHERE IDLT_EMAIL IN (?, ?, ?) GROUP BY IDLT_EMAIL", array($chaves['email'], $chaves['par'], $chaves['ip']));
    $totais = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $totais[(string) $linha['chave']] = (int) $linha['total'];
    }
    return ($totais[$chaves['par']] ?? 0) >= LOGIN_MAX_POR_EMAIL_IP
        || ($totais[$chaves['email']] ?? 0) >= LOGIN_MAX_POR_EMAIL
        || ($totais[$chaves['ip']] ?? 0) >= LOGIN_MAX_POR_IP;
}

function login_registrar_falha($conn, $email_lower) {
    if (!$conn) {
        return;
    }
    $chaves = login_chaves_limite($email_lower);
    db_query($conn, "INSERT INTO ID_LOGIN_TENTATIVA (IDLT_EMAIL) VALUES (?), (?), (?)", array($chaves['email'], $chaves['par'], $chaves['ip']));
}

// Login certo: zera o e-mail e o par e-mail + IP. O contador do IP fica
// (senão quem acerta a própria senha de vez em quando zeraria o "spraying").
function login_limpar_tentativas($conn, $email_lower) {
    if (!$conn) {
        return;
    }
    $chaves = login_chaves_limite($email_lower);
    db_query($conn, "DELETE FROM ID_LOGIN_TENTATIVA WHERE IDLT_EMAIL IN (?, ?)", array($chaves['email'], $chaves['par']));
}

// Troca de senha pelo próprio fiel, logado (Configurações). Exige a senha
// atual: uma sessão esquecida aberta num computador não basta para tomar a
// conta. Erros de senha atual contam no mesmo limite do login
// (ID_LOGIN_TENTATIVA, 5 em 15 min por e-mail), senão esta tela viraria um
// jeito de testar senhas sem limite. Deu certo: grava o hash, anula links de
// "Esqueci minha senha" pendentes, derruba as OUTRAS sessões da conta (esta
// continua) e manda o e-mail de aviso. Devolve a mensagem de erro, ou ''.
// Confere a senha do fiel logado antes de uma ação sem volta (excluir a
// conta, ligar a verificação em duas etapas), com o mesmo limite de 5 erros
// em 15 min da troca de senha. Devolve '' ou a mensagem de erro.
function senha_conferir_atual($conn, $fiel_id, $senha) {
    $res = db_query($conn, "SELECT IDF_EMAIL, IDF_SENHA_HASH FROM ID_FIEL WHERE IDF_ID = ? LIMIT 1", array((int) $fiel_id));
    $fiel = $res ? mysqli_fetch_assoc($res) : null;
    if (!$fiel) {
        return 'Não foi possível conferir a senha agora. Tente novamente em instantes.';
    }
    $email_lower = mb_substr(mb_strtolower((string) $fiel['IDF_EMAIL']), 0, 100);
    db_query($conn, "DELETE FROM ID_LOGIN_TENTATIVA WHERE LOWER(IDLT_EMAIL) = ? AND IDLT_CRIADO_EM <= NOW() - INTERVAL '900 seconds'", array($email_lower));
    $res_tentativas = db_query($conn, "SELECT COUNT(*) AS total FROM ID_LOGIN_TENTATIVA WHERE LOWER(IDLT_EMAIL) = ?", array($email_lower));
    $linha_tentativas = $res_tentativas ? mysqli_fetch_assoc($res_tentativas) : null;
    if ($linha_tentativas && (int) $linha_tentativas['total'] >= 5) {
        return 'Muitas tentativas com a senha errada. Aguarde 15 minutos e tente de novo.';
    }
    $hash = (string) ($fiel['IDF_SENHA_HASH'] ?? '');
    if ($hash === '' || !password_verify((string) $senha, $hash)) {
        db_query($conn, "INSERT INTO ID_LOGIN_TENTATIVA (IDLT_EMAIL) VALUES (?)", array($email_lower));
        return 'A senha está incorreta.';
    }
    return '';
}

function senha_trocar($conn, $fiel_id, $senha_atual, $senha_nova, $senha_confirma) {
    $fiel_id = (int) $fiel_id;
    if (!$conn || $fiel_id <= 0) {
        return "Não foi possível trocar a senha agora. Tente novamente em instantes.";
    }

    $res = db_query($conn, "SELECT IDF_NOME, IDF_EMAIL, IDF_SENHA_HASH FROM ID_FIEL WHERE IDF_ID = ? LIMIT 1", array($fiel_id));
    $fiel = $res ? mysqli_fetch_assoc($res) : null;
    if (!$fiel) {
        return "Não foi possível trocar a senha agora. Tente novamente em instantes.";
    }
    $email_lower = mb_substr(mb_strtolower((string) $fiel['IDF_EMAIL']), 0, 100);

    db_query($conn, "DELETE FROM ID_LOGIN_TENTATIVA WHERE LOWER(IDLT_EMAIL) = ? AND IDLT_CRIADO_EM <= NOW() - INTERVAL '900 seconds'", array($email_lower));
    $res_tentativas = db_query($conn, "SELECT COUNT(*) AS total FROM ID_LOGIN_TENTATIVA WHERE LOWER(IDLT_EMAIL) = ?", array($email_lower));
    $linha_tentativas = $res_tentativas ? mysqli_fetch_assoc($res_tentativas) : null;
    if ($linha_tentativas && (int) $linha_tentativas['total'] >= 5) {
        return "Muitas tentativas com a senha errada. Aguarde 15 minutos e tente de novo.";
    }

    $hash_atual = (string) ($fiel['IDF_SENHA_HASH'] ?? '');
    $senha_atual = (string) $senha_atual;
    // Só hash: a senha antiga em texto puro não vale mais (S11).
    $atual_ok = $hash_atual !== '' && password_verify($senha_atual, $hash_atual);
    if (!$atual_ok) {
        db_query($conn, "INSERT INTO ID_LOGIN_TENTATIVA (IDLT_EMAIL) VALUES (?)", array($email_lower));
        return "A senha atual está incorreta.";
    }

    $erro_politica = senha_validar_politica($senha_nova, $senha_confirma);
    if ($erro_politica !== '') {
        return $erro_politica;
    }
    if (hash_equals($senha_atual, (string) $senha_nova)) {
        return "A nova senha precisa ser diferente da atual.";
    }

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $conn->beginTransaction();
    try {
        $ok = db_query($conn, "UPDATE ID_FIEL SET IDF_SENHA_HASH = ?, IDF_SENHA = NULL WHERE IDF_ID = ?", array(password_hash((string) $senha_nova, PASSWORD_DEFAULT), $fiel_id))
            && db_query($conn, "UPDATE id_recuperacao_senha SET idr_usado_em = NOW() WHERE idf_id = ? AND idr_usado_em IS NULL", array($fiel_id))
            && db_query($conn, "UPDATE ID_SESSAO SET IDS_REVOGADA = 1 WHERE IDF_ID = ? AND IDS_REVOGADA = 0 AND IDS_TOKEN <> ?", array($fiel_id, (string) ($_SESSION['Usuario_token'] ?? '')));
        if (!$ok) {
            $conn->rollBack();
            return "Não foi possível trocar a senha agora. Tente novamente em instantes.";
        }
        if (!db_commit($conn)) {
            throw new RuntimeException('commit falhou');
        }
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return "Não foi possível trocar a senha agora. Tente novamente em instantes.";
    }

    db_query($conn, "DELETE FROM ID_LOGIN_TENTATIVA WHERE LOWER(IDLT_EMAIL) = ?", array($email_lower));
    recuperacao_email_confirmacao((string) $fiel['IDF_NOME'], (string) $fiel['IDF_EMAIL'], 'perfil');
    // A senha mudou: o external_id da OneSignal também (os outros aparelhos
    // param de receber push até o dono abrir o app neles).
    unset($_SESSION['push_marca']);
    return '';
}

// URL pública fixa do site, para links que saem por e-mail. NÃO usar
// SITE_BASE_URL nesses links: ela vem do cabeçalho Host da requisição, que
// quem faz o pedido controla — um pedido de recuperação com "Host:
// site-do-atacante" mandaria ao dono da conta um link legítimo apontando
// para o atacante, que receberia o token ao ser clicado.
function site_url_publica($path = '') {
    $base = trim((string) site_env('SITE_URL_PUBLICA'));
    if ($base === '') {
        // Sem a variável, os links de e-mail e push apontam para a
        // homologação — em produção isso seria um erro de configuração.
        site_avisar_config_faltando('SITE_URL_PUBLICA', 'links de e-mail/push usando a URL da homologação');
        $base = 'https://mananciapp-render-homolog.onrender.com/';
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

// Registra no log (uma vez por requisição) uma variável de ambiente que
// faltou e caiu no valor padrão de homologação. No XAMPP local não avisa.
function site_avisar_config_faltando($chave, $efeito) {
    static $avisados = array();
    $local = site_ambiente_local();
    if ($local || isset($avisados[$chave])) {
        return;
    }
    $avisados[$chave] = true;
    error_log('Configuração: ' . $chave . ' não definida — ' . $efeito . '. Defina no painel do Render.');
}

// IP de quem fez a requisição. No Render o PHP fica atrás de proxies, então
// REMOTE_ADDR é o de um proxy; o IP real vem no X-Forwarded-For. Lê a lista
// da direita para a esquerda, pulando os IPs internos (10.x e afins, dos
// proxies do Render), e fica com o primeiro público: é o que o Render viu
// chegando. O que estiver à esquerda dele pode ter sido enviado pelo próprio
// cliente para se passar por outro IP. Antes ficava com o último valor, que
// no Render é sempre um 10.x: todo mundo contava junto nos limites por IP.
//
// S17: o Render serve o site atrás da Cloudflare (inclusive o
// *.onrender.com), e aí o último IP público do X-Forwarded-For pode ser o da
// borda da Cloudflare (104.x, 172.64.x…), igual para muita gente. A
// Cloudflare manda o IP do visitante em CF-Connecting-IP e sempre troca o
// valor que o cliente tente enviar nesse cabeçalho; como não há caminho até
// o Render sem passar por ela, ele vem primeiro.
function site_ip_cliente() {
    $cloudflare = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cloudflare !== '' && filter_var($cloudflare, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return $cloudflare;
    }
    $encaminhado = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($encaminhado !== '') {
        $partes = array_reverse(array_map('trim', explode(',', $encaminhado)));
        foreach ($partes as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                break; // lixo na lista: dali para a esquerda não dá para confiar
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

// Chave do IP para limites de uso. IPv6 conta pela faixa /64: quem tem uma
// conexão IPv6 recebe uma faixa inteira e troca de endereço à vontade dentro
// dela — contando endereço por endereço, o limite por IP não seguraria nada.
function site_ip_chave_limite($ip) {
    $ip = (string) $ip;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $binario = inet_pton($ip);
        if ($binario !== false) {
            return inet_ntop(substr($binario, 0, 8) . str_repeat("\0", 8)) . '/64';
        }
    }
    return $ip;
}

// id_recuperacao_pedido guarda só o hash do e-mail: o limite funciona igual e
// a tabela não vira uma lista de e-mails (inclusive de quem nem tem conta).
function recuperacao_hash_email($email) {
    return hash('sha256', mb_strtolower(trim((string) $email)));
}

// O banco guarda só o SHA-256 do token; o token em si existe apenas no link
// do e-mail. Se o banco vazar, os links continuam inúteis. (SHA-256 simples
// basta: o token tem 256 bits aleatórios, não há o que adivinhar.)
function recuperacao_hash_token($token) {
    return hash('sha256', (string) $token);
}

function recuperacao_token_formato_ok($token) {
    return is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1;
}

// Pedido de recuperação. Aplica os limites, gera o token e devolve o e-mail
// pronto para enviar (array com destinatario/assunto/html/texto), ou null
// quando não há nada a enviar (conta inexistente ou que não pode entrar,
// limite atingido, erro de banco). Quem chama mostra a MESMA mensagem em
// todos os casos — não pode dar para saber se o e-mail tem conta.
function recuperacao_pedir($conn, $email) {
    if (!$conn) {
        return null;
    }

    $email_lower = mb_strtolower(trim((string) $email));
    if ($email_lower === '') {
        return null;
    }
    $email_hash = recuperacao_hash_email($email_lower);
    $ip_real = substr(site_ip_cliente(), 0, 45);
    $ip_chave = substr(site_ip_chave_limite($ip_real), 0, 45);

    // Faxina: pedidos antigos (só servem para os limites da última hora) e
    // tokens vencidos há mais de uma semana (o limite diário conta os
    // tokens das últimas 24 h, que continuam lá).
    @mysqli_query($conn, "DELETE FROM id_recuperacao_pedido WHERE idrp_criado_em < NOW() - INTERVAL '1 day'");
    @mysqli_query($conn, "DELETE FROM id_recuperacao_senha WHERE idr_expira_em < NOW() - INTERVAL '7 days'");

    // Conferir os limites e gravar o pedido numa transação com lock: sem
    // isso, vários pedidos chegando juntos liam a mesma contagem e passavam
    // todos do limite. O lock é um só para o fluxo inteiro — o volume é
    // baixo, e assim o teto global também fica exato.
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $conn->beginTransaction();
    try {
        mysqli_query($conn, "SELECT pg_advisory_xact_lock(hashtext('recuperacao_senha'))");

        $res_limites = db_query($conn, "
            SELECT
                COUNT(*) FILTER (WHERE idrp_email = ?) AS por_email,
                COUNT(*) FILTER (WHERE idrp_ip = ?) AS por_ip,
                COUNT(*) FILTER (WHERE idrp_email = ? AND idrp_criado_em > NOW() - INTERVAL '" . RECUPERACAO_INTERVALO_SEGUNDOS . " seconds') AS recentes,
                (SELECT COUNT(*) FROM id_recuperacao_senha WHERE idr_criado_em > NOW() - INTERVAL '1 hour') AS global_hora,
                (SELECT COUNT(*) FROM id_recuperacao_senha WHERE idr_criado_em > NOW() - INTERVAL '1 day') AS global_dia
            FROM id_recuperacao_pedido
            WHERE idrp_criado_em > NOW() - INTERVAL '1 hour'
        ", array($email_hash, $ip_chave, $email_hash));
        $limites = $res_limites ? mysqli_fetch_assoc($res_limites) : null;
        if (!$limites) {
            // Sem a tabela (seção 65 não rodada) não há como limitar: melhor
            // não enviar nada do que virar disparador de e-mail.
            error_log('Recuperação de senha: falha ao consultar os limites (' . mysqli_error($conn) . ')');
            $conn->rollBack();
            return null;
        }
        if ((int) $limites['por_email'] >= RECUPERACAO_MAX_POR_EMAIL
            || (int) $limites['por_ip'] >= RECUPERACAO_MAX_POR_IP
            || (int) $limites['recentes'] > 0) {
            $conn->rollBack();
            return null;
        }
        if ((int) $limites['global_hora'] >= RECUPERACAO_MAX_GLOBAL_HORA
            || (int) $limites['global_dia'] >= RECUPERACAO_MAX_GLOBAL_DIA) {
            // Muito acima do uso normal: provável ataque. Fica no log do
            // Render para alguém olhar.
            error_log('Recuperação de senha: teto global atingido (' . (int) $limites['global_hora'] . ' na última hora, ' . (int) $limites['global_dia'] . ' no dia); pedidos ignorados.');
            $conn->rollBack();
            return null;
        }

        db_query($conn, "INSERT INTO id_recuperacao_pedido (idrp_email, idrp_ip) VALUES (?, ?)", array($email_hash, $ip_chave));

        // Só conta aprovada e ativa recebe link: pendente/negada não entraria
        // mesmo com a senha nova (ver login.php).
        $res_fiel = db_query($conn, "
            SELECT f.IDF_ID, f.IDF_NOME, f.IDF_EMAIL,
                   CASE WHEN EXISTS (SELECT 1 FROM ID_ADMIN a WHERE a.IDA_FIEL_ID = f.IDF_ID AND a.IDA_ATIVO = 1) THEN 1 ELSE 0 END AS EH_ADMIN
            FROM ID_FIEL f
            WHERE LOWER(f.IDF_EMAIL) = ? AND f.IDF_STATUS = 'aprovado' AND COALESCE(f.IDF_ATIVO, 1) = 1
            ORDER BY f.IDF_ID
            LIMIT 1
        ", array($email_lower));
        $fiel = $res_fiel ? mysqli_fetch_assoc($res_fiel) : null;
        if (!$fiel) {
            if (!db_commit($conn)) { // o pedido conta para os limites mesmo sem conta
                throw new RuntimeException('commit falhou');
            }
            return null;
        }
        $envio = recuperacao_criar_envio($conn, $fiel, $ip_real);
        if (!$envio) {
            $conn->rollBack();
            return null;
        }
        if (!db_commit($conn)) {
            throw new RuntimeException('commit falhou');
        }
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('Recuperação de senha: erro no pedido (' . $e->getMessage() . ')');
        return null;
    }

    return $envio;
}

// Gera o token de um fiel (anulando os links anteriores dele) e monta o
// e-mail com o link. $fiel: IDF_ID, IDF_NOME, IDF_EMAIL e EH_ADMIN. Usada
// pelo pedido público (recuperacao_pedir) e pelo botão do admin
// (recuperacao_enviar_pelo_admin). Devolve o array do e-mail, ou null.
// $convite: conta criada pelo admin (Gerenciar Usuários → Adicionar pessoa).
// O e-mail dá as boas-vindas em vez de "recebemos um pedido para redefinir",
// e o link vale 24 h (quem foi cadastrado no balcão pode abrir depois).
function recuperacao_criar_envio($conn, array $fiel, $ip_real, $convite = false) {
    $fiel_id = (int) $fiel['IDF_ID'];
    $eh_admin = (int) ($fiel['EH_ADMIN'] ?? 0) === 1;
    $validade = $convite ? 1440 : ($eh_admin ? RECUPERACAO_VALIDADE_ADMIN_MINUTOS : RECUPERACAO_VALIDADE_MINUTOS);

    // Só o link mais recente vale.
    db_query($conn, "UPDATE id_recuperacao_senha SET idr_usado_em = NOW() WHERE idf_id = ? AND idr_usado_em IS NULL", array($fiel_id));

    $token = bin2hex(random_bytes(32));
    $ok = db_query($conn, "INSERT INTO id_recuperacao_senha (idf_id, idr_token, idr_expira_em, idr_ip_pedido) VALUES (?, ?, NOW() + INTERVAL '" . (int) $validade . " minutes', ?)", array($fiel_id, recuperacao_hash_token($token), substr((string) $ip_real, 0, 45)));
    if (!$ok) {
        error_log('Recuperação de senha: falha ao gravar o token (' . mysqli_error($conn) . ')');
        return null;
    }

    $link = site_url_publica('redefinir_senha.php?token=' . $token);
    $nome = (string) $fiel['IDF_NOME'];
    $nome_html = htmlspecialchars($nome);
    $link_html = htmlspecialchars($link);

    $texto = "Olá, {$nome}!\n\nRecebemos um pedido para redefinir a senha da sua conta. Para criar uma nova senha, abra o link abaixo:\n{$link}\n\nO link vale por {$validade} minutos e só pode ser usado uma vez.\n\nSe você não pediu, ignore este e-mail: sua senha continua a mesma.\n\nAtenciosamente,\nEquipe Missão Manancial";
    $html = '<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">'
        . '<h2 style="margin: 0 0 16px; color: #1d4ed8;">Redefinir sua senha</h2>'
        . '<p>Olá, <strong>' . $nome_html . '</strong>.</p>'
        . '<p>Recebemos um pedido para redefinir a senha da sua conta. Para criar uma nova senha, clique no botão abaixo:</p>'
        . '<p><a href="' . $link_html . '" style="display: inline-block; background: #1d4ed8; color: #ffffff; padding: 12px 22px; border-radius: 8px; text-decoration: none; font-weight: bold;">Criar nova senha</a></p>'
        . '<p style="font-size: 14px; color: #475569;">Se o botão não funcionar, copie e cole este endereço no navegador:<br><a href="' . $link_html . '" style="color: #1d4ed8; word-break: break-all;">' . $link_html . '</a></p>'
        . '<p>O link vale por <strong>' . $validade . ' minutos</strong> e só pode ser usado uma vez.</p>'
        . '<p>Se você não pediu, ignore este e-mail: sua senha continua a mesma.</p>'
        . '<p>Atenciosamente,<br>Equipe Missão Manancial</p>'
        . '</div>';

    if ($convite) {
        $igreja_nome = igreja_info()['nome'];
        $texto = "Olá, {$nome}!\n\nA {$igreja_nome} criou a sua conta no app da igreja. Para entrar, crie a sua senha pelo link abaixo:\n{$link}\n\nO link vale por 24 horas e só pode ser usado uma vez. Depois, é só entrar com este e-mail e a senha que você criou.\n\nSe você não conhece a igreja, ignore este e-mail.\n\nAtenciosamente,\n{$igreja_nome}";
        $html = '<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">'
            . '<h2 style="margin: 0 0 16px; color: #1d4ed8;">Bem-vindo(a) ao app da igreja</h2>'
            . '<p>Olá, <strong>' . $nome_html . '</strong>.</p>'
            . '<p>A ' . htmlspecialchars($igreja_nome) . ' criou a sua conta. Para entrar, crie a sua senha:</p>'
            . '<p><a href="' . $link_html . '" style="display: inline-block; background: #1d4ed8; color: #ffffff; padding: 12px 22px; border-radius: 8px; text-decoration: none; font-weight: bold;">Criar minha senha</a></p>'
            . '<p style="font-size: 14px; color: #475569;">Se o botão não funcionar, copie e cole este endereço no navegador:<br><a href="' . $link_html . '" style="color: #1d4ed8; word-break: break-all;">' . $link_html . '</a></p>'
            . '<p>O link vale por <strong>24 horas</strong> e só pode ser usado uma vez.</p>'
            . '<p>Se você não conhece a igreja, ignore este e-mail.</p>'
            . '<p>Atenciosamente,<br>' . htmlspecialchars($igreja_nome) . '</p>'
            . '</div>';
    }

    return array(
        'destinatario' => (string) $fiel['IDF_EMAIL'],
        'assunto' => $convite ? 'Sua conta no app da igreja: crie sua senha' : 'Redefinir sua senha',
        'html' => $html,
        'texto' => $texto,
    );
}

// Dados do fiel dono de um token ainda válido (não usado, não vencido, conta
// aprovada), ou null. Não diferencia "inexistente", "vencido" e "usado".
function recuperacao_token_valido($conn, $token) {
    return recuperacao_token_formato_ok($token) ? recuperacao_token_valido_por_hash($conn, recuperacao_hash_token($token)) : null;
}

// O mesmo, a partir do SHA-256 do token: é o que redefinir_senha.php guarda
// na sessão. Com SESSOES_NO_BANCO=1 a sessão fica na tabela ID_SESSAO, e o
// token em si ali dava para trocar a senha de alguém lendo essa tabela; o
// hash não serve para montar o link.
function recuperacao_token_valido_por_hash($conn, $hash_token) {
    if (!$conn || !recuperacao_token_formato_ok($hash_token)) {
        return null;
    }
    $res = db_query($conn, "
        SELECT f.IDF_ID, f.IDF_NOME
        FROM id_recuperacao_senha r
        JOIN ID_FIEL f ON f.IDF_ID = r.idf_id
        WHERE r.idr_token = ?
          AND r.idr_usado_em IS NULL
          AND r.idr_expira_em > NOW()
          AND f.IDF_STATUS = 'aprovado'
          AND COALESCE(f.IDF_ATIVO, 1) = 1
        LIMIT 1
    ", array($hash_token));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ?: null;
}

// Troca a senha usando o token, tudo numa transação: consome o token (o
// UPDATE ... AND idr_usado_em IS NULL garante que dois envios simultâneos
// não passem os dois), grava o hash novo, invalida os outros links e
// derruba todas as sessões abertas da conta (auth_require() checa
// IDS_REVOGADA a cada página). Devolve os dados do fiel ou null.
function recuperacao_redefinir($conn, $token, $senha_nova) {
    return recuperacao_token_formato_ok($token) ? recuperacao_redefinir_por_hash($conn, recuperacao_hash_token($token), $senha_nova) : null;
}

// O mesmo, a partir do SHA-256 do token (ver recuperacao_token_valido_por_hash()).
function recuperacao_redefinir_por_hash($conn, $hash_token, $senha_nova) {
    if (!$conn || !recuperacao_token_formato_ok($hash_token)) {
        return null;
    }
    $hash_senha = password_hash((string) $senha_nova, PASSWORD_DEFAULT);
    // Quem usou o link (auditoria: ajuda a investigar um "não fui eu").
    $ip_uso = substr(site_ip_cliente(), 0, 45);
    $agente_uso = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $conn->beginTransaction();
    try {
        $res = db_query($conn, "UPDATE id_recuperacao_senha SET idr_usado_em = NOW(), idr_ip_uso = ?, idr_user_agent_uso = ? WHERE idr_token = ? AND idr_usado_em IS NULL AND idr_expira_em > NOW() RETURNING idf_id", array($ip_uso, $agente_uso, $hash_token));
        $linha = $res ? mysqli_fetch_assoc($res) : null;
        $fiel_id = $linha ? (int) $linha['idf_id'] : 0;
        if ($fiel_id <= 0) {
            $conn->rollBack();
            return null;
        }

        $res_fiel = db_query($conn, "UPDATE ID_FIEL SET IDF_SENHA_HASH = ?, IDF_SENHA = NULL WHERE IDF_ID = ? AND IDF_STATUS = 'aprovado' AND COALESCE(IDF_ATIVO, 1) = 1 RETURNING IDF_NOME, IDF_EMAIL", array($hash_senha, $fiel_id));
        $fiel = $res_fiel ? mysqli_fetch_assoc($res_fiel) : null;
        if (!$fiel) {
            $conn->rollBack();
            return null;
        }

        $ok = mysqli_query($conn, "UPDATE id_recuperacao_senha SET idr_usado_em = NOW() WHERE idf_id = $fiel_id AND idr_usado_em IS NULL")
            && mysqli_query($conn, "UPDATE ID_SESSAO SET IDS_REVOGADA = 1 WHERE IDF_ID = $fiel_id AND IDS_REVOGADA = 0");
        if (!$ok) {
            $conn->rollBack();
            return null;
        }
        if (!db_commit($conn)) {
            throw new RuntimeException('commit falhou');
        }
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return null;
    }

    // Quem acabou de provar que é dono do e-mail não deve continuar
    // bloqueado pelas tentativas de login erradas (ver login.php).
    db_query($conn, "DELETE FROM ID_LOGIN_TENTATIVA WHERE LOWER(IDLT_EMAIL) = ?", array(mb_substr(mb_strtolower((string) $fiel['IDF_EMAIL']), 0, 100)));

    $res_admin = @mysqli_query($conn, "SELECT 1 FROM ID_ADMIN WHERE IDA_FIEL_ID = $fiel_id AND IDA_ATIVO = 1 LIMIT 1");

    return array(
        'id' => $fiel_id,
        'nome' => (string) $fiel['IDF_NOME'],
        'email' => (string) $fiel['IDF_EMAIL'],
        'eh_admin' => $res_admin && mysqli_num_rows($res_admin) > 0,
    );
}

// Botão "Enviar link de nova senha" do painel (admin_gerenciar_usuarios.php),
// para o fiel que pede ajuda. O link vai para o e-mail DO FIEL — o admin
// nunca vê o link nem escolhe a senha, então não passa a conhecer a senha de
// ninguém. Fora dos limites do pedido público (é um admin logado agindo),
// mas com intervalo de 1 minuto por fiel contra clique repetido. Devolve a
// mensagem de erro, ou ''.
function recuperacao_enviar_pelo_admin($conn, $fiel_id, $convite = false) {
    $fiel_id = (int) $fiel_id;
    if (!$conn || $fiel_id <= 0) {
        return "Usuário inválido.";
    }

    $res = db_query($conn, "
        SELECT f.IDF_ID, f.IDF_NOME, f.IDF_EMAIL, f.IDF_STATUS, COALESCE(f.IDF_ATIVO, 1) AS ATIVO,
               CASE WHEN EXISTS (SELECT 1 FROM ID_ADMIN a WHERE a.IDA_FIEL_ID = f.IDF_ID AND a.IDA_ATIVO = 1) THEN 1 ELSE 0 END AS EH_ADMIN,
               EXISTS (SELECT 1 FROM id_recuperacao_senha r WHERE r.idf_id = f.IDF_ID AND r.idr_criado_em > NOW() - INTERVAL '" . RECUPERACAO_INTERVALO_SEGUNDOS . " seconds') AS RECENTE
        FROM ID_FIEL f
        WHERE f.IDF_ID = ?
        LIMIT 1
    ", array($fiel_id));
    $fiel = $res ? mysqli_fetch_assoc($res) : null;
    if (!$fiel) {
        return "Usuário não encontrado.";
    }
    if ($fiel['IDF_STATUS'] !== 'aprovado' || (int) $fiel['ATIVO'] !== 1) {
        return "Só contas aprovadas e ativas podem redefinir a senha (a conta não entraria mesmo com a senha nova).";
    }
    if (!empty($fiel['RECENTE'])) {
        return "Um link acabou de ser enviado para esse usuário. Aguarde um minuto antes de enviar outro.";
    }

    $envio = recuperacao_criar_envio($conn, $fiel, site_ip_cliente(), $convite);
    if (!$envio) {
        return "Não foi possível gerar o link agora. Tente novamente em instantes.";
    }
    try {
        if (!enviar_email_sistema($envio['destinatario'], $envio['assunto'], $envio['html'], $envio['texto'])) {
            return "O link foi gerado, mas o e-mail não pôde ser enviado. Confira a configuração da Brevo e tente de novo.";
        }
    } catch (\Throwable $e) {
        return "O link foi gerado, mas o e-mail não pôde ser enviado. Tente de novo.";
    }
    return '';
}

// Admin cadastra uma pessoa (Gerenciar Usuários → Adicionar pessoa): o irmão
// idoso, o membro novo no balcão. A conta já nasce aprovada, sem senha
// conhecida (hash aleatório), e a pessoa recebe o convite por e-mail para
// criar a senha (link de 24 h). No primeiro acesso ela aceita o Termo e a
// Política, como todo mundo. Devolve array('ok', 'erro', 'id', 'aviso').
function admin_criar_pessoa($conn, array $dados) {
    $falha = function ($erro) {
        return array('ok' => false, 'erro' => $erro, 'id' => 0, 'aviso' => '');
    };
    $nome = trim(preg_replace('/\s+/', ' ', (string) ($dados['nome'] ?? '')));
    $email = mb_strtolower(trim((string) ($dados['email'] ?? '')));
    $whatsapp_texto = trim((string) ($dados['whatsapp'] ?? ''));
    $whatsapp = $whatsapp_texto === '' ? '' : fiel_whatsapp_normalizar($whatsapp_texto);
    $nascimento = fiel_nascimento_validar((string) ($dados['nascimento'] ?? ''));
    $responsavel = trim(preg_replace('/\s+/', ' ', (string) ($dados['responsavel'] ?? '')));

    if (mb_strlen($nome) < 3 || mb_strlen($nome) > 100 || preg_match('/\d/', $nome)) {
        return $falha('Nome inválido: de 3 a 100 letras, sem números.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        return $falha('Informe um e-mail válido: é por ele que a pessoa recebe o convite.');
    }
    if ($whatsapp === null) {
        return $falha('WhatsApp inválido. Use o celular com DDD, ex.: (11) 9 9999-9999.');
    }
    if ($nascimento['erro']) {
        return $falha($nascimento['erro']);
    }
    if (lgpd_eh_menor($nascimento['data']) && mb_strlen($responsavel) < 5) {
        // LGPD art. 14: menor só com o responsável, como no cadastro.
        return $falha('Menor de 18 anos: informe o nome completo do pai, mãe ou responsável que autorizou.');
    }
    if (empty($dados['autorizou'])) {
        return $falha('Confirme que a pessoa (ou o responsável) autorizou o cadastro.');
    }
    if (in_array($email, superadmin_emails(), true)) {
        return $falha('Esse e-mail não pode ser usado.');
    }
    $res_existe = db_query($conn, "SELECT 1 FROM ID_FIEL WHERE LOWER(IDF_EMAIL) = ? LIMIT 1", array($email));
    if ($res_existe && mysqli_num_rows($res_existe) > 0) {
        return $falha('Já existe uma conta com esse e-mail. Procure a pessoa na lista.');
    }

    $colunas = array('IDF_NOME', 'IDF_EMAIL', 'IDF_TELEFONE', 'IDF_SENHA_HASH', 'IDF_STATUS', 'IDF_ATIVO', 'IDF_ENDERECO', 'IDF_FILIAL');
    $valores = array($nome, $email, $whatsapp !== '' ? $whatsapp : null, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), 'aprovado', 1, '', '');
    if ($nascimento['data'] && tabela_tem_coluna($conn, 'id_fiel', 'idf_nascimento')) {
        $colunas[] = 'IDF_NASCIMENTO';
        $valores[] = $nascimento['data'];
        if (lgpd_eh_menor($nascimento['data']) && tabela_tem_coluna($conn, 'id_fiel', 'idf_responsavel_nome')) {
            array_push($colunas, 'IDF_RESPONSAVEL_NOME', 'IDF_RESPONSAVEL_CONSENTIU_EM');
            array_push($valores, mb_substr($responsavel, 0, 100), date('c'));
        }
    }
    $res = db_query($conn, "INSERT INTO ID_FIEL (" . implode(', ', $colunas) . ") VALUES (" . implode(', ', array_fill(0, count($valores), '?')) . ") RETURNING IDF_ID", $valores);
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if (!$linha) {
        return $falha(site_erro_banco('Não foi possível cadastrar a pessoa.'));
    }
    $id = (int) $linha['IDF_ID'];
    $erro_convite = recuperacao_enviar_pelo_admin($conn, $id, true);
    return array(
        'ok' => true,
        'erro' => '',
        'id' => $id,
        'aviso' => $erro_convite === '' ? '' : 'A conta foi criada, mas o convite não saiu: ' . $erro_convite . ' Use "Enviar link de senha" na lista depois.',
    );
}

// Anula os links de recuperação ainda pendentes de um fiel. Usado quando o
// e-mail da conta muda (admin_gerenciar_usuarios.php): um link mandado para o
// e-mail antigo — que pode ser justamente o que foi invadido — não pode
// continuar valendo.
function recuperacao_invalidar_links($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    if (!$conn || $fiel_id <= 0) {
        return;
    }
    @mysqli_query($conn, "UPDATE id_recuperacao_senha SET idr_usado_em = NOW() WHERE idf_id = $fiel_id AND idr_usado_em IS NULL");
}

// Senha de ADMIN redefinida pelo link: avisa os outros admins ativos. Se o
// e-mail de um admin for invadido, alguém além dele fica sabendo na hora.
function recuperacao_avisar_admins($conn, $fiel_id, $nome) {
    $fiel_id = (int) $fiel_id;
    if (!$conn || $fiel_id <= 0) {
        return;
    }
    $res = @mysqli_query($conn, "
        SELECT f.IDF_NOME, f.IDF_EMAIL
        FROM ID_ADMIN a
        JOIN ID_FIEL f ON f.IDF_ID = a.IDA_FIEL_ID
        WHERE a.IDA_ATIVO = 1 AND f.IDF_ID <> $fiel_id AND COALESCE(f.IDF_ATIVO, 1) = 1
    ");
    if (!$res) {
        return;
    }
    $quando = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y \à\s H:i');
    $nome_html = htmlspecialchars((string) $nome);
    while ($admin = mysqli_fetch_assoc($res)) {
        $texto = "Olá, {$admin['IDF_NOME']}!\n\nA senha da conta de administrador de {$nome} foi redefinida pelo link de \"Esqueci minha senha\" em {$quando} (horário de Brasília).\n\nSe não for confirmado que foi essa pessoa, desative o acesso de administrador dessa conta no painel.\n\nAtenciosamente,\nEquipe Missão Manancial";
        $html = '<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">'
            . '<h2 style="margin: 0 0 16px; color: #b45309;">Senha de administrador redefinida</h2>'
            . '<p>Olá, <strong>' . htmlspecialchars((string) $admin['IDF_NOME']) . '</strong>.</p>'
            . '<p>A senha da conta de administrador de <strong>' . $nome_html . '</strong> foi redefinida pelo link de "Esqueci minha senha" em <strong>' . $quando . '</strong> (horário de Brasília).</p>'
            . '<p>Se não for confirmado que foi essa pessoa, desative o acesso de administrador dessa conta no painel.</p>'
            . '<p>Atenciosamente,<br>Equipe Missão Manancial</p>'
            . '</div>';
        try {
            enviar_email_sistema($admin['IDF_EMAIL'], 'Senha de administrador redefinida', $html, $texto);
        } catch (\Throwable $e) {
            // Falha no aviso não desfaz a troca de senha.
        }
    }
}

// Aviso de "sua senha foi alterada" — é por ele que o dono da conta descobre
// uma troca que não fez. $origem 'perfil' = troca feita logada em
// Configurações (o aparelho usado continua conectado; os outros, não).
function recuperacao_email_confirmacao($nome, $email, $origem = 'reset') {
    $quando = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y \à\s H:i');
    $link_login = site_url_publica('login.php');
    $nome_html = htmlspecialchars((string) $nome);
    $desconectados = $origem === 'perfil'
        ? 'Por segurança, os outros aparelhos conectados foram desconectados.'
        : 'Por segurança, todos os aparelhos conectados foram desconectados.';

    $texto = "Olá, {$nome}!\n\nA senha da sua conta foi alterada em {$quando} (horário de Brasília). {$desconectados}\n\nSe foi você, está tudo certo: entre com a nova senha em {$link_login}\n\nSe NÃO foi você, peça agora um novo link em \"Esqueci minha senha\" e avise a administração da igreja.\n\nAtenciosamente,\nEquipe Missão Manancial";
    $html = '<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">'
        . '<h2 style="margin: 0 0 16px; color: #1d4ed8;">Sua senha foi alterada</h2>'
        . '<p>Olá, <strong>' . $nome_html . '</strong>.</p>'
        . '<p>A senha da sua conta foi alterada em <strong>' . $quando . '</strong> (horário de Brasília). ' . $desconectados . '</p>'
        . '<p>Se foi você, está tudo certo: <a href="' . htmlspecialchars($link_login) . '" style="color: #1d4ed8; font-weight: bold;">entre com a nova senha</a>.</p>'
        . '<p><strong>Se não foi você</strong>, peça agora um novo link em "Esqueci minha senha" e avise a administração da igreja.</p>'
        . '<p>Atenciosamente,<br>Equipe Missão Manancial</p>'
        . '</div>';

    try {
        enviar_email_sistema($email, 'Sua senha foi alterada', $html, $texto);
    } catch (\Throwable $e) {
        // Falha no aviso não desfaz a troca de senha.
    }
}

// Aviso ao e-mail ANTIGO quando a administração troca o e-mail de uma conta.
// Trocar o e-mail e depois mandar "link de nova senha" entrega a conta a
// quem controla o e-mail novo; sem aviso, o dono nem ficava sabendo.
function conta_email_alterado_avisar($nome, $email_antigo, $email_novo) {
    $quando = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y \à\s H:i');
    $novo_mascarado = site_mascarar_email($email_novo);
    $nome_html = htmlspecialchars((string) $nome);

    $texto = "Olá, {$nome}!\n\nO e-mail da sua conta foi alterado pela administração em {$quando} (horário de Brasília) para {$novo_mascarado}. Os avisos e links de senha passam a ir para o novo endereço.\n\nSe você não pediu essa mudança, avise a administração da igreja.\n\nAtenciosamente,\nEquipe Missão Manancial";
    $html = '<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">'
        . '<h2 style="margin: 0 0 16px; color: #1d4ed8;">O e-mail da sua conta foi alterado</h2>'
        . '<p>Olá, <strong>' . $nome_html . '</strong>.</p>'
        . '<p>O e-mail da sua conta foi alterado pela administração em <strong>' . $quando . '</strong> (horário de Brasília) para <strong>' . htmlspecialchars($novo_mascarado) . '</strong>. Os avisos e links de senha passam a ir para o novo endereço.</p>'
        . '<p><strong>Se você não pediu essa mudança</strong>, avise a administração da igreja.</p>'
        . '<p>Atenciosamente,<br>Equipe Missão Manancial</p>'
        . '</div>';

    try {
        enviar_email_sistema($email_antigo, 'O e-mail da sua conta foi alterado', $html, $texto);
    } catch (\Throwable $e) {
        // Falha no aviso não desfaz a alteração.
    }
}

// ============================================================================
// TRANSMISSÃO AO VIVO (embed do YouTube)
// ============================================================================
// Aceita o que o admin colar em admin_transmissao.php: um link comum do
// YouTube (várias formas possíveis) ou só o ID puro do vídeo — e devolve
// só o ID, que é o que o resto do código (embed, thumbnail, link "abrir
// no YouTube") precisa. Retorna '' se não conseguir reconhecer nada.
function transmissao_extrair_youtube_id($entrada) {
    $entrada = trim((string) $entrada);
    if ($entrada === '') {
        return '';
    }

    // ID puro (formato oficial do YouTube: 11 caracteres, [A-Za-z0-9_-]).
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $entrada)) {
        return $entrada;
    }

    $padroes = array(
        '#[?&]v=([A-Za-z0-9_-]{11})#',      // watch?v=ID
        '#youtu\.be/([A-Za-z0-9_-]{11})#',  // youtu.be/ID
        '#/live/([A-Za-z0-9_-]{11})#',      // youtube.com/live/ID
        '#/embed/([A-Za-z0-9_-]{11})#',     // youtube.com/embed/ID
        '#/shorts/([A-Za-z0-9_-]{11})#',    // youtube.com/shorts/ID
    );

    foreach ($padroes as $padrao) {
        if (preg_match($padrao, $entrada, $m)) {
            return $m[1];
        }
    }

    return '';
}

// Notifica os fiéis sobre um evento da transmissão (agendada, ao vivo,
// encerrada) — mesmo padrão de 1 INSERT em massa usado em
// admin_enviar_mensagem.php. Um só lugar pras chamadas em
// admin_transmissao.php não divergirem (link de destino, filtro de quem
// recebe, etc.).
//
// $audiencia espelha idtr_audiencia: se for 'ADMINS', só quem tem linha
// ativa em ID_ADMIN recebe (não faz sentido avisar fiéis comuns de uma
// transmissão que eles nem vão conseguir assistir); qualquer outro valor
// ('TODOS'/'FIEIS') notifica todo mundo aprovado/ativo, como antes.
// $ids (por referência): os IDN_ID gravados, para o push em lote sair depois
// da transação (notificacoes_push_lote()).
function transmissao_notificar_fieis($conn, $titulo, $mensagem, $tipo, $audiencia = 'TODOS', &$ids = null) {
    $ids = array();
    // INSERT ... SELECT: no SELECT o Postgres não deduz o tipo do parâmetro
    // pela coluna de destino, por isso o CAST explícito.
    if ($audiencia === 'ADMINS') {
        $sql = "
            INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK)
            SELECT f.IDF_ID, CAST(? AS TEXT), CAST(? AS TEXT), CAST(? AS TEXT), 'ao_vivo.php'
            FROM ID_ADMIN a
            JOIN ID_FIEL f ON f.IDF_ID = a.IDA_FIEL_ID
            WHERE a.IDA_ATIVO = 1 AND f.IDF_STATUS = 'aprovado' AND f.IDF_ATIVO = 1
            RETURNING IDN_ID
        ";
    } else {
        $sql = "
            INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK)
            SELECT IDF_ID, CAST(? AS TEXT), CAST(? AS TEXT), CAST(? AS TEXT), 'ao_vivo.php'
            FROM ID_FIEL
            WHERE IDF_STATUS = 'aprovado' AND IDF_ATIVO = 1
            RETURNING IDN_ID
        ";
    }

    $res = db_query($conn, $sql, array($titulo, $mensagem, $tipo));
    while ($res && ($linha = mysqli_fetch_assoc($res))) {
        $ids[] = (int) $linha['IDN_ID'];
    }
    return (bool) $res;
}

// Executa $fn dentro de uma transação Postgres, travada por um advisory
// lock fixo (mesma chave em toda transição de status de id_transmissao —
// "marcar ao vivo" e "encerrar" usam essa mesma trava, pra serializar
// ações concorrentes entre si). $fn recebe $conn e deve devolver true (a
// transação é commitada) ou false (é desfeita).
//
// $conn é uma conexão PERSISTENTE (PDO::ATTR_PERSISTENT, ver mais abaixo
// neste arquivo) — reaproveitada entre requisições atendidas pelo mesmo
// worker do Apache. Se uma transação ficasse aberta por causa de um erro
// não tratado (ex.: PHP encerrado no meio por timeout), ela continuaria
// pendurada pra PRÓXIMA requisição que caísse nesse worker, quebrando um
// admin qualquer com "transação já em andamento" e travando quem
// dependesse do mesmo advisory lock. Por isso: desfaz qualquer transação
// deixada aberta por engano antes de começar, e sempre desfaz em caso de
// exceção.
function transmissao_transacao_com_lock($conn, callable $fn) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    $conn->beginTransaction();
    try {
        mysqli_query($conn, "SELECT pg_advisory_xact_lock(727270001)");
        $sucesso = (bool) $fn($conn);
        if ($sucesso) {
            $sucesso = db_commit($conn);
        } else {
            $conn->rollBack();
        }
        return $sucesso;
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return false;
    }
}

// ============================================================================
// CACHE COMPARTILHADO ENTRE REQUISIÇÕES
// ============================================================================
// Para respostas iguais para todo mundo que muitas pessoas pedem ao mesmo
// tempo (chat e status da live): 300 pessoas na live pedindo o chat a cada
// 5 s eram ~60 consultas por segundo; com o cache, ~1 a cada 2 s. Fica num
// arquivo do /tmp do container (cada instância tem o seu; some no deploy,
// o que não faz mal). Só guarda o que vira JSON (arrays, números, texto).
//
// $gerar devolve o valor; null ou false (erro de banco) não entra no cache.
// Se outra requisição já está gerando, devolve o valor vencido em vez de
// ir ao banco junto (sem "estouro" na virada do cache).
function site_cache_arquivo($chave) {
    return sys_get_temp_dir() . '/mananciapp_cache_' . preg_replace('/[^a-z0-9_]/', '_', strtolower((string) $chave));
}

function site_cache_compartilhado($chave, $segundos, callable $gerar) {
    $arquivo = site_cache_arquivo($chave);
    $conteudo = @file_get_contents($arquivo);
    $guardado = $conteudo !== false ? json_decode($conteudo, true) : null;
    if (is_array($guardado) && array_key_exists('v', $guardado) && (int) ($guardado['em'] ?? 0) > time() - (int) $segundos) {
        return $guardado['v'];
    }

    $trava = @fopen($arquivo . '.trava', 'c');
    $travou = $trava && flock($trava, LOCK_EX | LOCK_NB);
    if (!$travou && is_array($guardado) && array_key_exists('v', $guardado)) {
        if ($trava) {
            fclose($trava);
        }
        return $guardado['v'];
    }

    $valor = $gerar();
    if ($valor !== null && $valor !== false) {
        $temporario = $arquivo . '.' . getmypid() . '.tmp';
        if (@file_put_contents($temporario, json_encode(array('em' => time(), 'v' => $valor))) !== false) {
            @rename($temporario, $arquivo);
        }
    }
    if ($trava) {
        if ($travou) {
            flock($trava, LOCK_UN);
        }
        fclose($trava);
    }
    return $valor;
}

// Intervalo mínimo entre execuções de algo caro (ex.: consultar o Asaas),
// por chave: true = pode rodar agora (e já marca a hora). Arquivo com
// trava, então vale também para pedidos em paralelo da mesma pessoa (a
// marca na sessão não segurava: as abas liam a sessão antes de qualquer uma
// gravar). Por instância, como o cache compartilhado.
function site_intervalo_livre($chave, $segundos) {
    $arquivo = site_cache_arquivo('intervalo_' . $chave);
    $f = @fopen($arquivo, 'c+');
    if (!$f) {
        return true;
    }
    if (!flock($f, LOCK_EX | LOCK_NB)) {
        fclose($f);
        return false;
    }
    $ultimo = (int) stream_get_contents($f);
    $livre = $ultimo <= time() - (int) $segundos;
    if ($livre) {
        ftruncate($f, 0);
        rewind($f);
        fwrite($f, (string) time());
    }
    flock($f, LOCK_UN);
    fclose($f);
    return $livre;
}

// Marca a chave como vencida SEM apagar: o próximo pedido gera de novo, e
// quem chega junto (sem pegar a trava) recebe a cópia anterior em vez de ir
// ao banco também. Apagar fazia todos gerarem ao mesmo tempo: numa live
// movimentada, cada mensagem nova virava dezenas de consultas simultâneas.
function site_cache_vencer($chave) {
    $arquivo = site_cache_arquivo($chave);
    $guardado = json_decode((string) @file_get_contents($arquivo), true);
    if (!is_array($guardado) || !array_key_exists('v', $guardado)) {
        return;
    }
    $guardado['em'] = 0;
    $temporario = $arquivo . '.' . getmypid() . '.tmp';
    if (@file_put_contents($temporario, json_encode($guardado)) !== false) {
        @rename($temporario, $arquivo);
    }
}

// Apaga as chaves que começam com $prefixo (ex.: 'transmissao' depois que o
// admin muda a live), para a mudança aparecer no próximo pedido.
function site_cache_apagar($prefixo) {
    foreach ((array) glob(site_cache_arquivo($prefixo) . '*') as $arquivo) {
        if (substr($arquivo, -6) !== '.trava') {
            @unlink($arquivo);
        }
    }
}

// Transmissão a mostrar "agora": prioriza uma "AO_VIVO" (se houver), senão
// pega a mais recente de qualquer status. Usada tanto pela página pública
// (ao-vivo.php) quanto pela versão dentro da área do fiel
// (php/fiel/ao_vivo.php) — um só lugar pra essa regra de prioridade.
function transmissao_obter_atual($conn) {
    $res = mysqli_query($conn, "
        SELECT idtr_id, idtr_titulo, idtr_youtube_id, idtr_status, idtr_iniciada_em, idtr_audiencia
        FROM id_transmissao
        ORDER BY CASE idtr_status WHEN 'AO_VIVO' THEN 0 WHEN 'AGENDADA' THEN 1 ELSE 2 END, idtr_id DESC
        LIMIT 1
    ");

    return $res ? mysqli_fetch_assoc($res) : null;
}

// O mesmo, com até 3 s de atraso, para o polling do chat (muitas pessoas ao
// mesmo tempo). O admin apaga o cache ao mexer na live.
function transmissao_obter_atual_cache($conn) {
    $linha = site_cache_compartilhado('transmissao_atual', 3, function () use ($conn) {
        $res = mysqli_query($conn, "
            SELECT idtr_id, idtr_titulo, idtr_youtube_id, idtr_status, idtr_iniciada_em, idtr_audiencia
            FROM id_transmissao
            ORDER BY CASE idtr_status WHEN 'AO_VIVO' THEN 0 WHEN 'AGENDADA' THEN 1 ELSE 2 END, idtr_id DESC
            LIMIT 1
        ");
        // Sem transmissão nenhuma vira array() (vai para o cache); erro de
        // banco vira false (não vai).
        return $res ? (mysqli_fetch_assoc($res) ?: array()) : false;
    });
    return $linha ?: null;
}

// Quem pode ASSISTIR (não só comentar) essa transmissão, de acordo com
// idtr_audiencia: 'TODOS' (qualquer um, inclusive sem login), 'FIEIS'
// (precisa estar logado — fiel ou admin, tanto faz) ou 'ADMINS' (só sessão
// de admin). Usada tanto por ao-vivo.php (pública) quanto por
// php/fiel/ao_vivo.php, pra decidir se mostra o vídeo/chat ou uma tela de
// "restrito".
// Transmissão a mostrar para quem está vendo: uma ENCERRADA que a pessoa não
// pode assistir (ex.: teste "só admins") some, em vez de deixar a tela do
// fiel em "Transmissão restrita" até a próxima live. No ar, restrita,
// continua aparecendo (com o aviso para entrar).
function transmissao_para_quem_ve($transmissao) {
    if ($transmissao && ($transmissao['idtr_status'] ?? '') === 'ENCERRADA' && !transmissao_pode_assistir($transmissao)) {
        return null;
    }
    return $transmissao;
}

function transmissao_pode_assistir($transmissao) {
    if (!$transmissao) {
        return true;
    }

    $audiencia = $transmissao['idtr_audiencia'] ?? 'TODOS';
    if ($audiencia !== 'ADMINS' && $audiencia !== 'FIEIS') {
        return true;
    }

    // As páginas ao vivo não passam por auth_require(): sem conferir a
    // sessão aqui, um admin removido das permissões ou uma conta negada,
    // desativada ou deslogada por outro aparelho continuava assistindo
    // (vídeo e chat) só abrindo /ao-vivo.php com a sessão antiga.
    if (auth_problema_sessao() !== '') {
        return false;
    }

    if ($audiencia === 'ADMINS') {
        return ($_SESSION['Usuario_tipo'] ?? '') === 'admin';
    }

    return true;
}

// Um fiel bloqueado não consegue mandar mensagem em NENHUM chat de
// transmissão (bloqueio "global" do recurso, não de uma transmissão
// específica) até idbc_expira_em passar — NULL é bloqueio permanente. Ver
// seção 36 de database/bancosupabase.md.
function transmissao_fiel_bloqueado_chat($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $res = mysqli_query($conn, "
        SELECT 1 FROM id_fiel_bloqueio_chat
        WHERE idf_id = $fiel_id AND (idbc_expira_em IS NULL OR idbc_expira_em > NOW())
        LIMIT 1
    ");

    return (bool) ($res && mysqli_num_rows($res) > 0);
}

// Igual a transmissao_fiel_bloqueado_chat(), mas devolvendo os detalhes do
// bloqueio pra mostrar na tela do próprio fiel: null se ele não está
// bloqueado, senão array('ate' => 'dd/mm às HH:MM', ou null se for
// permanente). A data já sai formatada no fuso de Brasília pelo Postgres
// (o PHP não tem timezone configurado).
function transmissao_fiel_bloqueio_chat_info($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    if ($fiel_id <= 0) {
        return null;
    }

    $res = mysqli_query($conn, "
        SELECT to_char(idbc_expira_em AT TIME ZONE 'America/Sao_Paulo', 'DD/MM \"às\" HH24:MI') AS ate
        FROM id_fiel_bloqueio_chat
        WHERE idf_id = $fiel_id AND (idbc_expira_em IS NULL OR idbc_expira_em > NOW())
        LIMIT 1
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if (!$linha) {
        return null;
    }

    return array('ate' => $linha['ate']);
}

// Texto do aviso que substitui a caixa do chat pro fiel bloqueado.
function transmissao_texto_bloqueio_chat($info) {
    return 'Você foi bloqueado no chat pela moderação'
        . (!empty($info['ate']) ? ' até ' . $info['ate'] : '')
        . ' e não pode enviar mensagens.';
}

// Busca as últimas $limite mensagens NÃO ocultas de uma transmissão, em
// ordem cronológica (mais antiga -> mais nova, pronta pra exibir). Usada
// tanto no SSR inicial (ao-vivo.php) quanto no polling
// (ajax_transmissao_comentarios.php) — um só lugar pra essa regra evitar
// que os dois pontos divirjam.
//
// A ordenação por dentro (DESC + LIMIT) e só depois reordenar pra ASC é
// proposital: pega sempre as $limite mensagens MAIS RECENTES. Buscar
// direto em ASC LIMIT $limite faria o chat travar nas primeiras
// mensagens da transmissão pra sempre assim que ela passasse de $limite
// mensagens — as novas nunca apareceriam (e o polling, ao não vê-las na
// resposta, chegaria a apagar da tela a própria mensagem que o autor
// acabou de mandar, por parecer "oculta pela moderação").
// Chat da live como os fiéis veem (nome curto, data formatada), igual para
// todos: cache compartilhado de 2 s, também usado pela abertura das páginas
// (antes cada uma das 300 pessoas chegando no início do culto consultava o
// banco). Devolve array('versao' => resumo da lista, 'lista' => [...]).
function transmissao_chat_lista($conn, $transmissao_id) {
    $transmissao_id = (int) $transmissao_id;
    $chat = site_cache_compartilhado('transmissao_chat_' . $transmissao_id, 2, function () use ($conn, $transmissao_id) {
        $lista = array();
        foreach (transmissao_listar_comentarios_visiveis($conn, $transmissao_id, 100) as $linha) {
            $lista[] = array(
                'id' => (int) $linha['idtc_id'],
                // Nome curto ("Maria L."), como no resto do app para outros
                // fiéis. O admin vê o completo na moderação.
                'nome' => site_nome_curto($linha['IDF_NOME']),
                'mensagem' => $linha['idtc_mensagem'],
                'criado_em' => site_data_hora($linha['idtc_criado_em'], 'd/m H:i'),
            );
        }
        return array('versao' => substr(md5(json_encode($lista)), 0, 12), 'lista' => $lista);
    });
    if (!is_array($chat)) {
        return array('versao' => '', 'lista' => array());
    }
    // Cache no formato antigo (só a lista, gravado antes do deploy): sem versão.
    return array_key_exists('lista', $chat)
        ? array('versao' => (string) ($chat['versao'] ?? ''), 'lista' => (array) $chat['lista'])
        : array('versao' => '', 'lista' => $chat);
}

function transmissao_listar_comentarios_visiveis($conn, $transmissao_id, $limite = 100) {
    $transmissao_id = (int) $transmissao_id;
    $limite = (int) $limite;

    $res = mysqli_query($conn, "
        SELECT * FROM (
            SELECT c.idtc_id, c.idtc_mensagem, c.idtc_criado_em, f.IDF_NOME
            FROM id_transmissao_comentario c
            JOIN ID_FIEL f ON f.IDF_ID = c.idf_id
            WHERE c.idtr_id = $transmissao_id AND c.idtc_oculto = 0
            ORDER BY c.idtc_id DESC
            LIMIT $limite
        ) mais_recentes
        ORDER BY idtc_id ASC
    ");

    $comentarios = array();
    if ($res) {
        while ($linha = mysqli_fetch_assoc($res)) {
            $comentarios[] = $linha;
        }
    }

    return $comentarios;
}

// ============================================================================
// OFENSIVA DE ACESSOS (estilo Duolingo)
// ============================================================================
// Ver seção 38 de database/bancosupabase.md. O "dia" é sempre o de Brasília:
// o container roda em UTC, e sem isso quem abrisse o app às 22h teria o
// acesso contado no dia seguinte (e poderia "perder" a ofensiva sem ter
// faltado dia nenhum).
// Timestamp do banco ("2026-10-18 23:15:42.123+00") no horário de Brasília,
// para exibir. Antes o chat da live e as telas da transmissão mostravam o
// valor cru do Postgres, em UTC.
function site_data_hora($timestamp, $formato = 'd/m/Y H:i') {
    $ts = strtotime((string) $timestamp);
    return $ts ? date($formato, $ts) : '';
}

function site_hoje() {
    $agora = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
    return $agora->format('Y-m-d');
}

// "agora há pouco", "há 5 min", "há 3 h", "ontem", "há 4 dias" ou a data.
// $quando: data/hora que strtotime() entende; $agora só para os testes.
function site_tempo_relativo($quando, $agora = null) {
    $ts = strtotime((string) $quando);
    if ($ts === false) {
        return '';
    }
    $seg = max(0, ($agora ?? time()) - $ts);
    if ($seg < 120) {
        return 'agora há pouco';
    }
    if ($seg < 3600) {
        return 'há ' . floor($seg / 60) . ' min';
    }
    if ($seg < 86400) {
        return 'há ' . floor($seg / 3600) . ' h';
    }
    $dias = (int) floor($seg / 86400);
    if ($dias === 1) {
        return 'ontem';
    }
    return $dias < 7 ? "há $dias dias" : date('d/m', $ts);
}

// ============================================================================
// PROTETOR DE OFENSIVA E META DIÁRIA (seção 85 do bancosupabase.md)
// ============================================================================
// Protetor (como o "streak freeze" do Duolingo): perder a ofensiva por um dia
// é o maior motivo de a pessoa desistir. A cada 7 dias seguidos o fiel ganha
// um protetor (guarda até 2). Se ele passar um ou dois dias sem entrar, ao
// voltar os protetores cobrem esses dias sozinhos e a sequência continua.
// Os dias cobertos ficam em id_fiel_protetor_dia — NÃO em id_fiel_acesso_dia,
// para medalhas, desafios e métricas continuarem contando só dias de verdade.
if (!defined('OFENSIVA_PROTETORES_MAX')) {
    define('OFENSIVA_PROTETORES_MAX', 2);
}
if (!defined('OFENSIVA_PROTETOR_A_CADA')) {
    define('OFENSIVA_PROTETOR_A_CADA', 7);
}

function ofensiva_protetor_disponivel($conn) {
    return $conn && tabela_tem_coluna($conn, 'id_fiel', 'idf_protetores') && site_tabela_existe($conn, 'id_fiel_protetor_dia');
}

// Datas cobertas pelo protetor [Y-m-d => true].
function ofensiva_dias_protegidos($conn, $fiel_id) {
    if (!ofensiva_protetor_disponivel($conn)) {
        return array();
    }
    $res = db_query($conn, "SELECT idpd_data FROM id_fiel_protetor_dia WHERE idf_id = ?", array((int) $fiel_id));
    $dias = array();
    while ($res && ($linha = mysqli_fetch_assoc($res))) {
        $dias[substr((string) $linha['idpd_data'], 0, 10)] = true;
    }
    return $dias;
}

function ofensiva_protetores($conn, $fiel_id) {
    if (!ofensiva_protetor_disponivel($conn)) {
        return 0;
    }
    $res = db_query($conn, "SELECT idf_protetores FROM id_fiel WHERE idf_id = ? LIMIT 1", array((int) $fiel_id));
    return $res ? (int) (mysqli_fetch_assoc($res)['idf_protetores'] ?? 0) : 0;
}

// Dias que faltaram entre o último dia da sequência e hoje (0 = entrou
// ontem). Função pura, para os testes.
function ofensiva_dias_faltando($ultimo_dia, $hoje) {
    $ultimo = $ultimo_dia ? date_create((string) $ultimo_dia) : null;
    $agora = date_create((string) $hoje);
    if (!$ultimo || !$agora || $ultimo >= $agora) {
        return 0;
    }
    return max(0, (int) $ultimo->diff($agora)->days - 1);
}

// Primeiro acesso do dia: usa protetores nos dias que faltaram (se der para
// cobrir todos) e dá um protetor a cada 7 dias de sequência. Avisos vão para
// a sessão (ofensiva_protegida, protetor_ganho) e a navbar mostra.
function ofensiva_protetor_processar($conn, $fiel_id, $hoje) {
    if (!ofensiva_protetor_disponivel($conn)) {
        return;
    }
    $fiel_id = (int) $fiel_id;
    $protetores = ofensiva_protetores($conn, $fiel_id);
    $res = db_query($conn, "
        SELECT MAX(d) AS ultimo FROM (
            SELECT idad_data AS d FROM id_fiel_acesso_dia WHERE idf_id = ? AND idad_data < CAST(? AS DATE)
            UNION SELECT idpd_data FROM id_fiel_protetor_dia WHERE idf_id = ?
        ) dias
    ", array($fiel_id, $hoje, $fiel_id));
    $ultimo = $res ? (mysqli_fetch_assoc($res)['ultimo'] ?? null) : null;
    $faltando = ofensiva_dias_faltando($ultimo, $hoje);

    // Só cobre se der para cobrir todos os dias que faltaram (um buraco que
    // sobra quebraria a sequência do mesmo jeito e gastaria à toa).
    if ($faltando > 0 && $faltando <= $protetores) {
        $ok = db_query($conn, "UPDATE id_fiel SET idf_protetores = idf_protetores - ? WHERE idf_id = ? AND idf_protetores >= ?", array($faltando, $fiel_id, $faltando));
        if ($ok !== false && (int) ($GLOBALS['site_pg_last_affected_rows'] ?? 0) === 1) {
            for ($i = 1; $i <= $faltando; $i++) {
                db_query($conn, "INSERT INTO id_fiel_protetor_dia (idf_id, idpd_data) VALUES (?, CAST(? AS DATE) - ?) ON CONFLICT DO NOTHING", array($fiel_id, $hoje, $i));
            }
            $protetores -= $faltando;
            $_SESSION['ofensiva_protegida'] = $faltando;
        }
    }

    // Ganhou um protetor? (a cada 7 dias da sequência atual, até o máximo)
    $resumo = ofensiva_resumo($conn, $fiel_id);
    if ($resumo['atual'] > 0 && $resumo['atual'] % OFENSIVA_PROTETOR_A_CADA === 0 && $protetores < OFENSIVA_PROTETORES_MAX) {
        db_query($conn, "UPDATE id_fiel SET idf_protetores = LEAST(?, idf_protetores + 1) WHERE idf_id = ?", array(OFENSIVA_PROTETORES_MAX, $fiel_id));
        $_SESSION['protetor_ganho'] = true;
    }
}

// Meta diária: os minutos que o fiel escolheu em Configurações → Conteúdo e
// lembrete ("tempo por dia", id_preferencia.idpref_tempo_diario; sem escolha,
// 10 min) contra os minutos que assistiu hoje. O tempo de hoje soma o
// intervalo entre dois envios do player (que só envia com o vídeo tocando, a
// cada ~30 s), no máximo 40 s por envio: pausar e voltar depois não conta o
// tempo parado.
function meta_diaria_disponivel($conn) {
    return $conn && tabela_tem_coluna($conn, 'id_fiel_acesso_dia', 'idad_segundos_assistidos');
}

// ---- Relógio do fiel (seção 87) ----
// Um relógio por fiel e por dia, no banco, para todas as aulas e sessões
// juntas: cada envio do player conta no máximo 40 s de tempo real desde o
// envio anterior (de qualquer aula), e o progresso das aulas só avança
// gastando um saldo que cresce no máximo RELOGIO_VELOCIDADE × esse tempo.
// Antes o limite era por aula e por sessão: 5 aulas abertas (ou N sessões)
// concluíam um curso em minutos, e a meta diária somava cada sessão.
if (!defined('RELOGIO_VELOCIDADE')) {
    define('RELOGIO_VELOCIDADE', 2.5); // 2x do player + folga
}
if (!defined('RELOGIO_SALDO_MAX')) {
    define('RELOGIO_SALDO_MAX', 600); // pular adiante até 10 min já "ganhos"
}
if (!defined('RELOGIO_SALDO_INICIAL')) {
    define('RELOGIO_SALDO_INICIAL', 60); // folga de buffer ao começar
}

function relogio_disponivel($conn) {
    return $conn && tabela_tem_coluna($conn, 'id_fiel_acesso_dia', 'idad_relogio_em')
        && tabela_tem_coluna($conn, 'id_fiel_acesso_dia', 'idad_saldo_seg');
}

// Regra pura (testável sem banco). $passados: segundos desde o último envio
// (null = primeiro do dia). Devolve array(tempo que conta, saldo novo,
// segundos liberados para o avanço pedido).
function relogio_calcular($passados, $saldo, $pedido) {
    $primeiro = $passados === null;
    $tempo = $primeiro ? 0 : min(40, max(0, (int) $passados));
    $saldo = $primeiro ? RELOGIO_SALDO_INICIAL : (int) $saldo;
    $saldo = min(RELOGIO_SALDO_MAX, $saldo + (int) ceil($tempo * RELOGIO_VELOCIDADE));
    $liberado = min(max(0, (int) $pedido), $saldo);
    return array($tempo, $saldo - $liberado, $liberado);
}

// Registra um envio do player. $pedido: quantos segundos a aula quer avançar
// além do ponto já gravado. Devolve array('tempo' => segundos para a meta,
// 'liberado' => quanto do avanço vale) ou null sem a seção 87 / em erro.
function relogio_assistir($conn, $fiel_id, $pedido) {
    if (!relogio_disponivel($conn)) {
        return null;
    }
    $fiel_id = (int) $fiel_id;
    $hoje = site_hoje();
    try {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $conn->beginTransaction();
        db_query($conn, "INSERT INTO id_fiel_acesso_dia (idf_id, idad_data) VALUES (?, CAST(? AS DATE)) ON CONFLICT (idf_id, idad_data) DO NOTHING", array($fiel_id, $hoje));
        // FOR UPDATE: dois envios ao mesmo tempo (aulas ou sessões em
        // paralelo) esperam um pelo outro e o segundo já vê o relógio novo.
        $res = db_query($conn, "
            SELECT idad_saldo_seg, EXTRACT(EPOCH FROM (NOW() - idad_relogio_em))::int AS passados
            FROM id_fiel_acesso_dia WHERE idf_id = ? AND idad_data = CAST(? AS DATE) FOR UPDATE
        ", array($fiel_id, $hoje));
        $linha = $res ? mysqli_fetch_assoc($res) : null;
        if (!$linha) {
            $conn->rollBack();
            return null;
        }
        list($tempo, $saldo, $liberado) = relogio_calcular($linha['passados'] === null ? null : (int) $linha['passados'], (int) $linha['idad_saldo_seg'], $pedido);
        $ok = db_query($conn, "
            UPDATE id_fiel_acesso_dia
            SET idad_relogio_em = NOW(), idad_saldo_seg = ?, idad_segundos_assistidos = idad_segundos_assistidos + ?
            WHERE idf_id = ? AND idad_data = CAST(? AS DATE)
            RETURNING idad_segundos_assistidos
        ", array($saldo, $tempo, $fiel_id, $hoje));
        $linha_ok = $ok ? mysqli_fetch_assoc($ok) : null;
        if (!$linha_ok) {
            $conn->rollBack();
            return null;
        }
        if (!db_commit($conn)) {
            throw new RuntimeException('commit falhou');
        }
        return array('tempo' => $tempo, 'liberado' => $liberado, 'segundos_hoje' => (int) $linha_ok['idad_segundos_assistidos']);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return null;
    }
}

// Chamado por ajax_progresso.php. Devolve array('feitos', 'alvo', 'cumpriu_agora') em minutos, ou null.
// $relogio: o que relogio_assistir() devolveu (o tempo já foi somado lá);
// sem a seção 87, conta pelo intervalo entre envios guardado na sessão.
function meta_diaria_somar($conn, $fiel_id, $conteudo_id, $relogio = null) {
    if (!meta_diaria_disponivel($conn)) {
        return null;
    }
    if (is_array($relogio)) {
        // O total de hoje já veio do relógio; o alvo (preferência do fiel)
        // fica 10 min na sessão. Sem consulta a mais a cada envio.
        $alvo_guardado = $_SESSION['meta_alvo'] ?? null;
        if (!is_array($alvo_guardado) || (int) ($alvo_guardado['fiel'] ?? 0) !== (int) $fiel_id || (int) ($alvo_guardado['em'] ?? 0) < time() - 600) {
            $alvo_guardado = array('fiel' => (int) $fiel_id, 'em' => time(), 'alvo' => meta_diaria_hoje($conn, $fiel_id)['alvo']);
            $_SESSION['meta_alvo'] = $alvo_guardado;
        }
        $segundos_hoje = isset($relogio['segundos_hoje']) ? (int) $relogio['segundos_hoje'] : meta_diaria_hoje($conn, $fiel_id)['segundos'];
        $depois = array('feitos' => intdiv($segundos_hoje, 60), 'alvo' => (int) $alvo_guardado['alvo'], 'segundos' => $segundos_hoje);
        $antes_segundos = $depois['segundos'] - (int) $relogio['tempo'];
        $depois['cumpriu_agora'] = intdiv(max(0, $antes_segundos), 60) < $depois['alvo'] && $depois['feitos'] >= $depois['alvo'];
        return $depois;
    }
    $agora = time();
    $ultimo = (int) ($_SESSION['meta_ultimo_envio'][(int) $conteudo_id] ?? 0);
    $_SESSION['meta_ultimo_envio'] = array((int) $conteudo_id => $agora); // só a aula atual
    $segundos = $ultimo > 0 ? min(40, max(0, $agora - $ultimo)) : 0;
    $antes = meta_diaria_hoje($conn, $fiel_id);
    if ($segundos > 0) {
        db_query($conn, "
            INSERT INTO id_fiel_acesso_dia (idf_id, idad_data, idad_segundos_assistidos) VALUES (?, CAST(? AS DATE), ?)
            ON CONFLICT (idf_id, idad_data) DO UPDATE SET idad_segundos_assistidos = id_fiel_acesso_dia.idad_segundos_assistidos + EXCLUDED.idad_segundos_assistidos
        ", array((int) $fiel_id, site_hoje(), $segundos));
    }
    $depois = meta_diaria_hoje($conn, $fiel_id);
    $depois['cumpriu_agora'] = $antes['feitos'] < $antes['alvo'] && $depois['feitos'] >= $depois['alvo'];
    return $depois;
}

// array('feitos' => minutos hoje, 'alvo' => minutos da meta, 'segundos' => segundos hoje).
function meta_diaria_hoje($conn, $fiel_id) {
    $saida = array('feitos' => 0, 'alvo' => 10, 'segundos' => 0);
    if (!meta_diaria_disponivel($conn)) {
        return $saida;
    }
    $res = db_query($conn, "
        SELECT COALESCE((SELECT idad_segundos_assistidos FROM id_fiel_acesso_dia WHERE idf_id = ? AND idad_data = CAST(? AS DATE)), 0) AS segundos,
               (SELECT idpref_tempo_diario FROM id_preferencia WHERE idf_id = ?) AS alvo
    ", array((int) $fiel_id, site_hoje(), (int) $fiel_id));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if ($linha) {
        $saida['segundos'] = (int) $linha['segundos'];
        $saida['feitos'] = intdiv($saida['segundos'], 60);
        $saida['alvo'] = $linha['alvo'] !== null && (int) $linha['alvo'] > 0 ? (int) $linha['alvo'] : 10;
    }
    return $saida;
}

// Marcos mostrados como "próxima meta" na tela da ofensiva.
function ofensiva_marcos() {
    return array(3, 7, 14, 30, 60, 100, 180, 365);
}

// Grava "esse fiel acessou hoje" — no máximo 1 INSERT por dia por sessão
// (o dia já registrado fica em $_SESSION, junto com o id do fiel, pra não
// valer pra outra conta que logar no mesmo navegador sem passar pelo
// "Sair"). Quando é o PRIMEIRO acesso do dia, marca ofensiva_comemorar
// pra navbar do fiel mostrar o aviso de "+1 dia de ofensiva".
function ofensiva_registrar_acesso($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    if (!$conn || $fiel_id <= 0) {
        return;
    }

    $hoje = site_hoje();
    $estado = $_SESSION['ofensiva'] ?? array();
    if (($estado['fiel'] ?? 0) === $fiel_id && ($estado['registrado_em'] ?? '') === $hoje) {
        return;
    }

    $res = @mysqli_query($conn, "
        INSERT INTO id_fiel_acesso_dia (idf_id, idad_data)
        VALUES ($fiel_id, DATE '$hoje')
        ON CONFLICT DO NOTHING
        RETURNING idf_id
    ");
    if ($res === false) {
        return; // tabela ainda não criada (migração pendente) — tenta de novo na próxima página
    }

    $_SESSION['ofensiva'] = array('fiel' => $fiel_id, 'registrado_em' => $hoje);
    if (mysqli_num_rows($res) > 0) {
        $_SESSION['ofensiva_comemorar'] = true;
        unset($_SESSION['ofensiva_cache']);
        // Protetor de ofensiva (seção 85): cobre o(s) dia(s) que faltou e,
        // a cada 7 dias seguidos, dá um protetor novo. Primeiro acesso do
        // dia só (o INSERT acima com RETURNING garante uma vez).
        ofensiva_protetor_processar($conn, $fiel_id, $hoje);
        // Primeiro acesso do dia: o recorde pode ter alcançado uma medalha
        // (T14). Uma vez por dia por fiel.
        if (medalhas_disponivel($conn)) {
            $resumo = ofensiva_resumo($conn, $fiel_id);
            $_SESSION['ofensiva_cache'] = array('fiel' => $fiel_id, 'data' => $hoje, 'atual' => $resumo['atual']);
            medalhas_avisar_novas(medalhas_conceder_ofensiva($conn, $fiel_id, $resumo['recorde']));
            // Medalhas da comunidade (T30): pega o que ficou para trás
            // (ex.: votos recebidos enquanto estava fora).
            medalhas_avisar_novas(medalhas_conceder_atividade($conn, $fiel_id));
        }
        // Desafios (T31): "dias no app" conta hoje; e fecha os que chegaram à meta.
        desafios_verificar($conn, $fiel_id);
    }
}

// ============================================================================
// USO PELO APP INSTALADO (seção 79 do bancosupabase.md)
// ============================================================================
// Aberto pelo ícone do app (modo standalone), o navegador avisa uma vez
// (php/includes/pwa_head.php → php/fiel/ajax_acesso_app.php) e o dia do
// fiel em id_fiel_acesso_dia fica com idad_pelo_app = 1. Dá a medalha "No
// bolso" (regra app_instalado). Não dá para saber o momento da instalação,
// só quando o app instalado é aberto — na prática, o mesmo.

function acesso_app_disponivel($conn) {
    return $conn && tabela_tem_coluna($conn, 'id_fiel_acesso_dia', 'idad_pelo_app');
}

// O uso pelo app já foi registrado hoje nesta sessão (para esta conta)?
// Enquanto não, pwa_head.php põe na página o aviso do navegador.
function acesso_app_registrado_hoje($fiel_id) {
    $estado = $_SESSION['acesso_app'] ?? array();
    return ($estado['fiel'] ?? 0) === (int) $fiel_id && ($estado['data'] ?? '') === site_hoje();
}

// Marca o dia de hoje do fiel como "usou pelo app" (cria a linha do dia, se
// ainda não houver) e concede a medalha. Devolve false se não gravou.
function acesso_app_registrar($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    if ($fiel_id <= 0 || !acesso_app_disponivel($conn)) {
        return false;
    }
    $hoje = site_hoje();
    $ok = db_query($conn, "
        INSERT INTO id_fiel_acesso_dia (idf_id, idad_data, idad_pelo_app)
        VALUES (?, CAST(? AS DATE), 1)
        ON CONFLICT (idf_id, idad_data) DO UPDATE SET idad_pelo_app = 1
    ", array($fiel_id, $hoje));
    if (!$ok) {
        return false;
    }
    $_SESSION['acesso_app'] = array('fiel' => $fiel_id, 'data' => $hoje);
    if (medalhas_disponivel($conn)) {
        // Aviso na próxima página (como as de ofensiva) e notificação no app.
        // Abrir pelo app é também uma missão dos Primeiros passos.
        medalhas_avisar_novas(medalhas_conceder_atividade($conn, $fiel_id, array('app_instalado', 'passos_espaco'), true));
    }
    return true;
}

// ============================================================================
// PRIMEIROS PASSOS (seção 80 do bancosupabase.md)
// ============================================================================
// Missões fixas de boas-vindas, para todo fiel (o admin não cria): deixar a
// conta com a cara dele e conhecer o app. Aparecem no topo de Desafios
// (php/fiel/includes/primeiros_passos.php), numa faixa na Home até terminar
// e no "Perfil X% completo". Cada grupo completo dá uma medalha (regras
// passos_espaco e passos_app); tudo completo libera a capa do perfil
// personalizável (Configurações → Aparência). Quem já tinha feito antes
// conclui na hora. Só entram as missões que o recurso existe no banco e que
// o tipo de fiel deixa fazer (ver fiel_pode()).

function primeiros_passos_grupos() {
    return array(
        'espaco' => array('titulo' => 'Personalize seu espaço', 'icone' => 'fa-house-user', 'regra' => 'passos_espaco'),
        'app' => array('titulo' => 'Conheça o app', 'icone' => 'fa-compass', 'regra' => 'passos_app'),
    );
}

// Missões do fiel, na ordem: chave => array(grupo, titulo, descricao, icone,
// link (null = sem atalho, é um desafio), feito). Uma consulta só, guardada
// na requisição (a Home e as medalhas pedem de novo).
function primeiros_passos($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    static $cache = array();
    if (isset($cache[$fiel_id])) {
        return $cache[$fiel_id];
    }
    if (!$conn || $fiel_id <= 0) {
        return array();
    }

    $tem_perfil = fiel_perfil_disponivel($conn);
    $tem_membro = fiel_membro_disponivel($conn);
    $tem_filiais = filiais_disponivel($conn);
    $tem_prefs = preferencias_conteudo_disponivel($conn);
    $sel = array('f.IDF_TELEFONE');
    if ($tem_perfil) {
        array_push($sel, 'f.IDF_NASCIMENTO', 'f.IDF_CIDADE', 'f.IDF_UF');
    }
    if ($tem_membro) {
        $sel[] = 'f.IDF_MEMBRO';
    }
    if ($tem_filiais) {
        array_push($sel, 'f.IDF_FILIAL_ID', 'f.IDF_SEM_FILIAL');
    }
    $existe = function ($sql) {
        return "(CASE WHEN EXISTS ($sql) THEN 1 ELSE 0 END)";
    };
    $sel[] = $existe("SELECT 1 FROM id_preferencia p WHERE p.idf_id = f.idf_id AND p.idpref_notificacoes_push = 1") . ' AS push';
    $sel[] = $existe("SELECT 1 FROM id_historico_visualizacao h WHERE h.idf_id = f.idf_id") . ' AS aula';
    $sel[] = $existe("SELECT 1 FROM id_favorito v WHERE v.idf_id = f.idf_id") . ' AS favorito';
    if ($tem_prefs) {
        $sel[] = '(SELECT p.idpref_tempo_diario FROM id_preferencia p WHERE p.idf_id = f.idf_id) AS tempo';
        $sel[] = '(SELECT p.idpref_lembrete_hora FROM id_preferencia p WHERE p.idf_id = f.idf_id) AS lembrete';
    }
    if (acesso_app_disponivel($conn)) {
        $sel[] = $existe("SELECT 1 FROM id_fiel_acesso_dia a WHERE a.idf_id = f.idf_id AND a.idad_pelo_app = 1") . ' AS app';
    }
    if (oracoes_disponivel($conn)) {
        $sel[] = $existe("SELECT 1 FROM id_oracao_amem o WHERE o.idf_id = f.idf_id") . ' AS amem';
    }
    if (desafios_disponivel($conn)) {
        $sel[] = $existe("SELECT 1 FROM id_desafio_participante d WHERE d.idf_id = f.idf_id") . ' AS desafio';
    }
    $res = mysqli_query($conn, 'SELECT ' . implode(', ', $sel) . " FROM ID_FIEL f WHERE f.IDF_ID = $fiel_id LIMIT 1");
    $f = $res ? mysqli_fetch_assoc($res) : null;
    if (!$f) {
        return array();
    }
    $tem = function ($valor) {
        return $valor !== null && trim((string) $valor) !== '';
    };

    $passos = array();
    $passo = function ($chave, $grupo, $titulo, $descricao, $icone, $link, $feito) use (&$passos) {
        $passos[$chave] = array('grupo' => $grupo, 'titulo' => $titulo, 'descricao' => $descricao, 'icone' => $icone, 'link' => $link, 'feito' => (bool) $feito);
    };

    // ---- Personalize seu espaço ----
    if (fiel_foto_disponivel($conn) && fiel_pode('foto')) {
        $passo('foto', 'espaco', 'Coloque sua foto', 'No seu perfil, toque na câmera.', 'fa-camera', 'perfil.php', fiel_foto_versao($conn, $fiel_id) !== null);
    }
    if (fiel_pode('dados')) {
        $dados_ok = (bool) fiel_whatsapp_normalizar($f['IDF_TELEFONE']);
        if ($tem_perfil) {
            $dados_ok = $dados_ok && $tem($f['IDF_NASCIMENTO']) && $tem($f['IDF_CIDADE']) && $tem($f['IDF_UF']);
        }
        $passo('dados', 'espaco', 'Conte quem você é', $tem_perfil ? 'WhatsApp, nascimento e cidade.' : 'Seu WhatsApp.', 'fa-id-card', 'configuracoes.php#dados-pessoais', $dados_ok);
    }
    if (($tem_membro || $tem_filiais) && fiel_pode('membro')) {
        $igreja_ok = (!$tem_membro || $f['IDF_MEMBRO'] !== null)
            && (!$tem_filiais || !empty($f['IDF_FILIAL_ID']) || (int) $f['IDF_SEM_FILIAL'] === 1);
        $passo('igreja', 'espaco', $tem_filiais ? 'Diga se é membro e escolha sua filial' : 'Diga se é membro da igreja', 'Assim a igreja te conhece melhor.', 'fa-church', 'configuracoes.php#igreja', $igreja_ok);
    }
    if ($tem_prefs) {
        $passo('temas', 'espaco', 'Escolha seus temas de interesse', 'As sugestões da página inicial seguem eles.', 'fa-heart', 'configuracoes.php#preferencias', !empty(fiel_temas_ids($conn, $fiel_id)));
        $passo('tempo', 'espaco', 'Diga quanto tempo tem por dia', 'As aulas que cabem nele aparecem primeiro.', 'fa-hourglass-half', 'configuracoes.php#preferencias', $f['tempo'] !== null);
        $passo('lembrete', 'espaco', 'Defina um lembrete diário', 'Um aviso para o seu momento com Deus.', 'fa-clock', 'configuracoes.php#preferencias', $f['lembrete'] !== null);
    }
    $passo('notificacoes', 'espaco', 'Ative as notificações', 'Para não perder avisos, lives e o lembrete.', 'fa-bell', 'configuracoes.php#notificacoes', (int) $f['push'] === 1);
    if (isset($f['app'])) {
        // Um desafio: só a dica, sem atalho nem passo a passo (medalha "No bolso").
        $passo('app', 'espaco', 'Leve a Missão Manancial com você', 'Descubra como. 😉', 'fa-mobile-screen-button', null, (int) $f['app'] === 1);
    }

    // ---- Conheça o app ----
    $passo('aula', 'app', 'Assista à sua primeira aula', 'Escolha um curso e dê o play.', 'fa-circle-play', 'cursos.php', (int) $f['aula'] === 1);
    $passo('favorito', 'app', 'Salve um curso nos favoritos', 'No coração da página do curso.', 'fa-bookmark', 'cursos.php', (int) $f['favorito'] === 1);
    if (isset($f['amem'])) {
        $passo('amem', 'app', 'Diga "Amém" num pedido de oração', 'Na aba Orações de qualquer aula.', 'fa-hands-praying', 'cursos.php', (int) $f['amem'] === 1);
    }
    if (isset($f['desafio'])) {
        $passo('desafio', 'app', 'Entre no seu primeiro desafio', 'Os abertos ficam logo abaixo, em Desafios.', 'fa-flag-checkered', 'desafios.php#abertos', (int) $f['desafio'] === 1);
    }

    return $cache[$fiel_id] = $passos;
}

// Resumo: total, feitos, por grupo (grupo => array(total, feitos)), a
// próxima missão (a primeira não feita com atalho; sem nenhuma com atalho,
// a primeira não feita) e se terminou tudo.
function primeiros_passos_resumo(array $passos) {
    $resumo = array('total' => count($passos), 'feitos' => 0, 'grupos' => array(), 'proxima' => null, 'completo' => false);
    $proxima_sem_link = null;
    foreach ($passos as $chave => $p) {
        $resumo['grupos'][$p['grupo']]['total'] = ($resumo['grupos'][$p['grupo']]['total'] ?? 0) + 1;
        $resumo['grupos'][$p['grupo']]['feitos'] = ($resumo['grupos'][$p['grupo']]['feitos'] ?? 0) + ($p['feito'] ? 1 : 0);
        if ($p['feito']) {
            $resumo['feitos']++;
        } elseif ($p['link'] !== null && $resumo['proxima'] === null) {
            $resumo['proxima'] = $chave;
        } elseif ($proxima_sem_link === null) {
            $proxima_sem_link = $chave;
        }
    }
    $resumo['proxima'] = $resumo['proxima'] ?? $proxima_sem_link;
    $resumo['completo'] = $resumo['total'] > 0 && $resumo['feitos'] === $resumo['total'];
    return $resumo;
}

// Para medalhas_contagens(): 1 se o grupo está completo, 0 se não.
function primeiros_passos_contagens($conn, $fiel_id) {
    $resumo = primeiros_passos_resumo(primeiros_passos($conn, $fiel_id));
    $contagens = array();
    foreach (primeiros_passos_grupos() as $grupo => $info) {
        $g = $resumo['grupos'][$grupo] ?? array('total' => 0, 'feitos' => 0);
        $contagens[$info['regra']] = $g['total'] > 0 && $g['feitos'] === $g['total'] ? 1 : 0;
    }
    return $contagens;
}

// ---- Capa do perfil (recompensa de completar tudo) ----
// chave => array(rótulo, classe CSS em .perfil-capa). 'padrao' é a de todo mundo.
function perfil_capas() {
    return array(
        'padrao' => array('Azul (padrão)', ''),
        'aurora' => array('Aurora', 'capa-aurora'),
        'por_do_sol' => array('Pôr do sol', 'capa-por-do-sol'),
        'floresta' => array('Floresta', 'capa-floresta'),
        'oceano' => array('Oceano', 'capa-oceano'),
        'vinho' => array('Vinho', 'capa-vinho'),
        'noite' => array('Noite estrelada', 'capa-noite'),
    );
}

function perfil_capa_disponivel($conn) {
    return $conn && tabela_tem_coluna($conn, 'id_fiel', 'idf_capa');
}

function perfil_capa_do_fiel($conn, $fiel_id) {
    if (!perfil_capa_disponivel($conn) || (int) $fiel_id <= 0) {
        return 'padrao';
    }
    $res = db_query($conn, "SELECT idf_capa FROM id_fiel WHERE idf_id = ? LIMIT 1", array((int) $fiel_id));
    $capa = (string) (($res ? mysqli_fetch_assoc($res) : null)['idf_capa'] ?? '');
    return isset(perfil_capas()[$capa]) ? $capa : 'padrao';
}

// Grava a capa escolhida (só com os Primeiros passos completos). Devolve '' ou o erro.
function perfil_capa_salvar($conn, $fiel_id, $capa) {
    if (!perfil_capa_disponivel($conn)) {
        return 'A escolha de capa ainda não está disponível.';
    }
    if (!isset(perfil_capas()[$capa])) {
        return 'Capa inválida.';
    }
    if (!primeiros_passos_resumo(primeiros_passos($conn, $fiel_id))['completo']) {
        return 'Complete os Primeiros passos para liberar a capa.';
    }
    $ok = db_query($conn, "UPDATE id_fiel SET idf_capa = ? WHERE idf_id = ?", array($capa === 'padrao' ? null : $capa, (int) $fiel_id));
    return $ok ? '' : 'Não foi possível salvar a capa.';
}

// Ofensiva atual (dias seguidos até hoje/ontem), recorde, total de dias e os
// últimos 7 dias (data => acessou?) de UM fiel. Busca só as datas e calcula
// em PHP — no máximo ~365 linhas por ano por fiel.
function ofensiva_resumo($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $resumo = array('atual' => 0, 'recorde' => 0, 'total' => 0, 'acessou_hoje' => false, 'semana' => array(), 'protegidos' => array());

    $hoje = new DateTime(site_hoje());
    for ($i = 6; $i >= 0; $i--) {
        $dia = (clone $hoje)->modify("-$i day")->format('Y-m-d');
        $resumo['semana'][$dia] = false;
    }

    if (!$conn || $fiel_id <= 0) {
        return $resumo;
    }

    // Dias salvos pelo protetor (seção 85) contam na sequência; 'total'
    // continua sendo só dias em que a pessoa entrou de verdade.
    $protegidos = ofensiva_dias_protegidos($conn, $fiel_id);
    $resumo['protegidos'] = $protegidos;
    $res = mysqli_query($conn, $protegidos
        ? "SELECT idad_data FROM id_fiel_acesso_dia WHERE idf_id = $fiel_id UNION SELECT idpd_data FROM id_fiel_protetor_dia WHERE idf_id = $fiel_id ORDER BY 1 DESC"
        : "SELECT idad_data FROM id_fiel_acesso_dia WHERE idf_id = $fiel_id ORDER BY idad_data DESC");
    if (!$res) {
        return $resumo;
    }

    $anterior = null;
    $sequencia = 0;
    $sequencia_atual_aberta = true;
    $ontem = (clone $hoje)->modify('-1 day')->format('Y-m-d');

    while ($linha = mysqli_fetch_assoc($res)) {
        $data = substr((string) $linha['idad_data'], 0, 10);
        $protegido = isset($protegidos[$data]);
        if (!$protegido) {
            $resumo['total']++;
        }
        if (isset($resumo['semana'][$data])) {
            // true = entrou; 'protegido' = o protetor salvou o dia.
            $resumo['semana'][$data] = $protegido ? 'protegido' : true;
        }

        if ($anterior === null) {
            $sequencia = 1;
            // A ofensiva só continua "viva" se o último acesso foi hoje ou
            // ontem (ontem = ainda dá tempo de manter entrando hoje).
            $sequencia_atual_aberta = ($data === $hoje->format('Y-m-d') || $data === $ontem);
            $resumo['acessou_hoje'] = ($data === $hoje->format('Y-m-d'));
        } elseif ((new DateTime($data))->modify('+1 day')->format('Y-m-d') === $anterior) {
            $sequencia++;
        } else {
            if ($sequencia_atual_aberta) {
                $resumo['atual'] = $sequencia;
                $sequencia_atual_aberta = false;
            }
            $resumo['recorde'] = max($resumo['recorde'], $sequencia);
            $sequencia = 1;
        }

        $anterior = $data;
    }

    if ($sequencia_atual_aberta) {
        $resumo['atual'] = $sequencia;
    }
    $resumo['recorde'] = max($resumo['recorde'], $sequencia);

    return $resumo;
}

// Ofensiva atual do fiel pra mostrar na navbar em toda página — guardada em
// $_SESSION e recalculada só quando o dia muda (ofensiva_registrar_acesso()
// também apaga o cache ao gravar o primeiro acesso do dia), em vez de 1
// consulta a mais por página.
function ofensiva_atual_cache($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $hoje = site_hoje();
    $cache = $_SESSION['ofensiva_cache'] ?? array();

    if (($cache['fiel'] ?? 0) === $fiel_id && ($cache['data'] ?? '') === $hoje) {
        return (int) $cache['atual'];
    }

    $resumo = ofensiva_resumo($conn, $fiel_id);
    $_SESSION['ofensiva_cache'] = array('fiel' => $fiel_id, 'data' => $hoje, 'atual' => $resumo['atual']);

    return $resumo['atual'];
}

// Ranking geral: ofensiva atual, recorde e total de dias de todos os
// fiéis/admins aprovados e ativos. "Gaps and islands": dentro de uma
// sequência de dias seguidos, data - posição dá sempre o mesmo valor, então
// agrupar por isso separa cada sequência.
//
// $sem_menores: a tela dos fiéis não mostra menores de 18 (LGPD art. 14; o
// admin vê todos). A consulta varre todos os dias de acesso de todo mundo:
// o resultado fica 5 min no cache compartilhado (muda no máximo uma vez por
// dia por pessoa).
function ofensiva_ranking($conn, $limite = 50, $sem_menores = false) {
    $limite = (int) $limite;
    $sem_menores = $sem_menores && tabela_tem_coluna($conn, 'id_fiel', 'idf_nascimento');
    $ranking = site_cache_compartilhado('ofensiva_ranking_' . site_hoje() . '_' . $limite . ($sem_menores ? '_sm' : ''), 300, function () use ($conn, $limite, $sem_menores) {
        return ofensiva_ranking_banco($conn, $limite, $sem_menores);
    });
    return is_array($ranking) ? $ranking : array();
}

function ofensiva_ranking_banco($conn, $limite, $sem_menores) {
    $hoje = site_hoje();
    $filtro_menor = $sem_menores
        ? "AND (f.IDF_NASCIMENTO IS NULL OR f.IDF_NASCIMENTO <= DATE '$hoje' - INTERVAL '" . (int) LGPD_MAIORIDADE . " years')"
        : '';

    // Dias salvos pelo protetor de ofensiva (seção 85) entram na sequência.
    $origem_dias = ofensiva_protetor_disponivel($conn)
        ? "(SELECT idf_id, idad_data FROM id_fiel_acesso_dia UNION SELECT idf_id, idpd_data FROM id_fiel_protetor_dia)"
        : "id_fiel_acesso_dia";
    $res = mysqli_query($conn, "
        WITH dias AS (
            SELECT idf_id, idad_data,
                idad_data - (ROW_NUMBER() OVER (PARTITION BY idf_id ORDER BY idad_data))::int AS grupo
            FROM $origem_dias AS todos_os_dias
        ), sequencias AS (
            SELECT idf_id, COUNT(*) AS tamanho, MAX(idad_data) AS fim
            FROM dias
            GROUP BY idf_id, grupo
        ), resumo AS (
            SELECT idf_id,
                COALESCE(MAX(CASE WHEN fim >= DATE '$hoje' - 1 THEN tamanho END), 0) AS atual,
                MAX(tamanho) AS recorde,
                SUM(tamanho) AS total
            FROM sequencias
            GROUP BY idf_id
        )
        SELECT r.idf_id, r.atual, r.recorde, r.total, f.IDF_NOME,
            EXISTS(SELECT 1 FROM ID_ADMIN a WHERE a.IDA_FIEL_ID = f.IDF_ID AND a.IDA_ATIVO = 1) AS eh_admin
        FROM resumo r
        JOIN ID_FIEL f ON f.IDF_ID = r.idf_id
        WHERE f.IDF_STATUS = 'aprovado' AND f.IDF_ATIVO = 1
          $filtro_menor
        ORDER BY r.atual DESC, r.recorde DESC, r.total DESC, f.IDF_NOME ASC
        LIMIT $limite
    ");

    if (!$res) {
        return false; // erro de banco não vai para o cache
    }
    $ranking = array();
    while ($linha = mysqli_fetch_assoc($res)) {
        $ranking[] = $linha;
    }

    return $ranking;
}

// "Maria Souza Lima" -> "Maria L." — nome mostrado a OUTROS fiéis (ranking,
// pessoas online). O admin sempre vê o nome completo.
function site_nome_curto($nome) {
    $partes = preg_split('/\s+/', trim((string) $nome));
    $partes = array_values(array_filter($partes, 'strlen'));
    if (empty($partes)) {
        return 'Usuário';
    }
    if (count($partes) === 1) {
        return $partes[0];
    }
    return $partes[0] . ' ' . mb_strtoupper(mb_substr($partes[count($partes) - 1], 0, 1)) . '.';
}

// ============================================================================
// PESSOAS ONLINE (presença)
// ============================================================================
// Ver seção 39 de database/bancosupabase.md. js/presenca.js manda um "estou
// aqui" a cada 60s com a aba visível, então 3 minutos dá folga pra 1-2
// batidas perdidas antes de a pessoa sumir da lista.
if (!defined('PRESENCA_ONLINE_SEGUNDOS')) {
    define('PRESENCA_ONLINE_SEGUNDOS', 180);
}

// Cada pessoa logada pede esta lista a cada 60 s (ajax_presenca.php): com
// 300 pessoas, 5 consultas por segundo. A lista fica 15 s no cache
// compartilhado (uma por variante: com/sem super admins, com/sem menores).
// $sem_menores: o que os fiéis veem (LGPD art. 14); o admin vê todos.
function presenca_listar_online($conn, $limite = 100, $sem_menores = false) {
    $sem_menores = $sem_menores && tabela_tem_coluna($conn, 'id_fiel', 'idf_nascimento');
    $chave = 'presenca_online_' . (int) $limite . (auth_eh_superadmin() ? '_super' : '') . ($sem_menores ? '_sm' : '');
    $lista = site_cache_compartilhado($chave, 15, function () use ($conn, $limite, $sem_menores) {
        return presenca_listar_online_banco($conn, $limite, $sem_menores);
    });
    return is_array($lista) ? $lista : array();
}

function presenca_listar_online_banco($conn, $limite, $sem_menores) {
    $limite = (int) $limite;
    $janela = (int) PRESENCA_ONLINE_SEGUNDOS;

    // foto_versao: muda quando a pessoa troca a foto (vai no ?v= da URL,
    // igual a fiel_foto_url()); null se não tem foto.
    $tem_foto = fiel_foto_disponivel($conn);
    $foto_coluna = $tem_foto ? 'EXTRACT(EPOCH FROM ff.idff_atualizado_em)::bigint AS foto_versao' : 'NULL AS foto_versao';
    $foto_join = $tem_foto ? 'LEFT JOIN id_fiel_foto ff ON ff.idf_id = f.IDF_ID' : '';

    // Super admins (equipe interna, SUPERADMIN_EMAILS) não aparecem para os
    // outros — nem na lista nem no contador. Só um super admin vê os demais.
    $superadmins = superadmin_emails();
    $sem_superadmin = '';
    $params = array();
    if ($superadmins && !auth_eh_superadmin()) {
        $sem_superadmin = 'AND LOWER(f.IDF_EMAIL) NOT IN (' . implode(', ', array_fill(0, count($superadmins), '?')) . ')';
        $params = $superadmins;
    }

    $res = db_query($conn, "
        SELECT f.IDF_ID, f.IDF_NOME, f.idf_ultima_pagina,
            EXTRACT(EPOCH FROM (NOW() - f.IDF_LAST_ACTIVITY))::int AS segundos,
            EXISTS(SELECT 1 FROM ID_ADMIN a WHERE a.IDA_FIEL_ID = f.IDF_ID AND a.IDA_ATIVO = 1) AS eh_admin,
            $foto_coluna
        FROM ID_FIEL f
        $foto_join
        WHERE f.IDF_LAST_ACTIVITY > NOW() - INTERVAL '$janela seconds'
          AND f.IDF_STATUS = 'aprovado' AND f.IDF_ATIVO = 1
          $sem_superadmin
          " . ($sem_menores ? "AND (f.IDF_NASCIMENTO IS NULL OR f.IDF_NASCIMENTO <= (NOW() AT TIME ZONE 'America/Sao_Paulo')::date - INTERVAL '" . (int) LGPD_MAIORIDADE . " years')" : '') . "
          AND EXISTS(
              SELECT 1 FROM ID_SESSAO s
              WHERE s.IDF_ID = f.IDF_ID AND s.IDS_REVOGADA = 0 AND s.IDS_EXPIRA_EM > NOW()
          )
        ORDER BY f.IDF_LAST_ACTIVITY DESC
        LIMIT $limite
    ", $params);

    if (!$res) {
        return false; // erro de banco não vai para o cache
    }
    $online = array();
    while ($linha = mysqli_fetch_assoc($res)) {
        $online[] = $linha;
    }

    return $online;
}

// ============================================================================
// COMENTÁRIOS NOS VÍDEOS
// ============================================================================
// Ver seção 40 de database/bancosupabase.md.

// Mesma regra de visibilidade de php/fiel/assistir_video.php: fiel só vê
// (e portanto só comenta em) vídeo publicado de curso publicado; admin vê
// tudo. Mantenha as duas em sincronia se mudar.
function video_conteudo_acessivel($conn, $conteudo_id) {
    $conteudo_id = (int) $conteudo_id;
    if ($conteudo_id <= 0) {
        return false;
    }

    $eh_admin = ($_SESSION['Usuario_tipo'] ?? '') === 'admin';
    $filtro_publicacao = $eh_admin ? '' : "AND ct.IDCT_PUBLICADO = 1 AND (c.IDC_ID IS NULL OR c.IDC_STATUS = 'PUBLICADO')";
    // idc_gratuito só é lido com o bloqueio ligado (a coluna vem da seção 42
    // do bancosupabase.md — sem ela e sem bloqueio, nada muda aqui).
    $coluna_gratuito = assinatura_bloqueio_ativo() ? 'COALESCE(c.IDC_GRATUITO, 0)' : '1';
    $res = mysqli_query($conn, "
        SELECT $coluna_gratuito AS curso_gratuito
        FROM ID_CONTENT ct
        LEFT JOIN ID_CURSO c ON c.IDC_ID = ct.IDC_ID
        WHERE ct.IDCT_ID = $conteudo_id
        $filtro_publicacao
        LIMIT 1
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : null;

    return $linha !== null && assinatura_curso_liberado($conn, $linha['curso_gratuito']);
}

// Comentários visíveis de um vídeo, já no formato que o JS da página usa
// (tanto no carregamento inicial quanto depois de comentar). pode_excluir:
// autor do comentário ou admin.
// Comentários mostrados por vez abaixo do vídeo; o resto vem pelo botão
// "Ver mais comentários" (php/fiel/assistir_video.php).
if (!defined('VIDEO_COMENTARIOS_POR_PAGINA')) {
    define('VIDEO_COMENTARIOS_POR_PAGINA', 10);
}

// Comentário em destaque: o de maior saldo (Edificante − Fora de contexto)
// dentro do filtro, com pelo menos este saldo.
if (!defined('VIDEO_DESTAQUE_MIN_SALDO')) {
    define('VIDEO_DESTAQUE_MIN_SALDO', 1);
}

// Ordens/filtros da lista (abas acima dos comentários). "relevantes"
// precisa da tabela de votos; sem ela a página usa "recentes".
function video_comentarios_ordens() {
    return array(
        'relevantes' => 'Mais relevantes',
        'recentes' => 'Mais recentes',
        'lideres' => 'Líderes',
        'versiculo' => 'Com versículo',
    );
}

function video_comentarios_ordem_valida($conn, $ordem) {
    $ordem = (string) $ordem;
    if (!isset(video_comentarios_ordens()[$ordem])) {
        $ordem = 'relevantes';
    }
    if ($ordem === 'relevantes' && !video_votos_disponivel($conn)) {
        $ordem = 'recentes';
    }
    return $ordem;
}

// Referência bíblica no texto: livro (nome ou abreviação, sem acento) +
// capítulo:versículo ou capítulo.versículo — "Jo 3:16", "1 Co 13.4",
// "Salmos 23:1". Até a T21 (campo de versículo no comentário), é assim
// que o filtro "Com versículo" acha os comentários. O mesmo padrão roda
// no Postgres (~) e no PHP; não usar "?" (o PDO trataria como parâmetro).
function video_versiculo_regex() {
    $livros = 'genesis|gen|gn|exodo|ex|levitico|lev|lv|numeros|num|nm|deuteronomio|deut|dt|josue|js|juizes|jz|rute|rt'
        . '|samuel|sm|reis|rs|cronicas|cr|esdras|ed|neemias|ne|ester|et|jo|salmos|salmo|sl|proverbios|prov|pv'
        . '|eclesiastes|ec|canticos|cantares|ct|isaias|is|jeremias|jr|lamentacoes|lm|ezequiel|ez|daniel|dn'
        . '|oseias|os|joel|jl|amos|am|obadias|ob|jonas|jn|miqueias|mq|naum|habacuque|hc|sofonias|sf|ageu|ag'
        . '|zacarias|zc|malaquias|ml|mateus|mt|marcos|mc|lucas|lc|joao|atos|at|romanos|rm|corintios|co'
        . '|galatas|gl|efesios|ef|filipenses|fp|colossenses|cl|tessalonicenses|ts|timoteo|tm|tito|tt'
        . '|filemom|fm|hebreus|hb|tiago|tg|pedro|pe|judas|jd|apocalipse|ap';
    return '(^|[^a-z0-9])([1-3]\s*){0,1}(' . $livros . ')\.{0,1}\s*[0-9]{1,3}\s*[:.]\s*[0-9]{1,3}';
}

function video_texto_sem_acento($texto) {
    return strtr(mb_strtolower((string) $texto, 'UTF-8'), array(
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
    ));
}

function video_texto_tem_versiculo($texto) {
    return preg_match('/' . video_versiculo_regex() . '/u', video_texto_sem_acento($texto)) === 1;
}

// ============================================================================
// MOMENTO DA AULA E VERSÍCULO NO COMENTÁRIO (seção 55 do bancosupabase.md)
// ============================================================================
// idcc_momento: segundo do vídeo marcado pelo botão "Momento da aula".
// idcc_versiculo: referência digitada no botão "Versículo" (ex.: Nm 12:3).

if (!defined('VIDEO_VERSICULO_MAX')) {
    define('VIDEO_VERSICULO_MAX', 40);
}

function video_citacoes_disponivel($conn) {
    return tabela_tem_coluna($conn, 'id_conteudo_comentario', 'idcc_momento')
        && tabela_tem_coluna($conn, 'id_conteudo_comentario', 'idcc_versiculo');
}

// Referência limpa (espaços normalizados) ou '' se não parecer uma
// referência bíblica: livro + capítulo:versículo, como no filtro
// "Com versículo". Aceita intervalo/lista depois (Jo 3:16-18, Sl 23:1,4).
function video_versiculo_normalizar($referencia) {
    $referencia = trim(preg_replace('/\s+/u', ' ', (string) $referencia));
    if ($referencia === '' || mb_strlen($referencia) > VIDEO_VERSICULO_MAX) {
        return '';
    }
    $regex = '/^\s*' . substr(video_versiculo_regex(), strlen('(^|[^a-z0-9])')) . '([-–,]\s*[0-9]{1,3})*\s*$/u';
    return preg_match($regex, video_texto_sem_acento($referencia)) === 1 ? $referencia : '';
}

// Segundo do vídeo enviado pelo navegador: inteiro entre 0 e 24h, ou null.
function video_momento_normalizar($momento) {
    if ($momento === null || $momento === '' || !is_numeric($momento)) {
        return null;
    }
    $momento = (int) floor((float) $momento);
    return ($momento >= 0 && $momento <= 86400) ? $momento : null;
}

// SELECT base dos comentários (alias c = comentário, f = autor), com tudo
// que video_formatar_comentario() usa. Usado na lista, no destaque e na
// resposta depois de comentar.
function video_comentarios_select_sql($conn, $fiel_id) {
    $colunas_votos = video_comentario_colunas_votos($conn, $fiel_id);
    $colunas_respostas = video_respostas_disponivel($conn) ? ",
            c.idcc_pai_id AS pai_id,
            (SELECT COUNT(*) FROM id_conteudo_comentario r WHERE r.idcc_pai_id = c.idcc_id AND r.idcc_oculto = 0) AS total_respostas" : '';
    $coluna_fixado = video_nota_disponivel($conn) ? ",
            (c.idcc_fixado_em IS NOT NULL) AS fixado" : '';
    $colunas_citacao = video_citacoes_disponivel($conn) ? ",
            c.idcc_momento AS momento,
            c.idcc_versiculo AS versiculo_ref" : '';
    // Selos do autor (T24): função na igreja e maior medalha.
    $colunas_selos = (funcoes_igreja_disponivel($conn) ? ",
            " . selos_funcao_sql() . " AS funcao_nome" : '')
        . (medalhas_disponivel($conn) ? ",
            " . selos_medalha_topo_sql() . " AS medalha_topo" : '');
    return "
        SELECT c.idcc_id, c.idct_id, c.idcc_mensagem, c.idf_id,
            EXTRACT(EPOCH FROM c.idcc_criado_em)::bigint AS criado_ts,
            f.IDF_NOME,
            EXISTS(SELECT 1 FROM ID_ADMIN a WHERE a.IDA_FIEL_ID = f.IDF_ID AND a.IDA_ATIVO = 1) AS eh_admin,
            " . video_comentario_lider_sql($conn) . " AS eh_lider
            $colunas_votos
            $colunas_respostas
            $coluna_fixado
            $colunas_citacao
            $colunas_selos
        FROM id_conteudo_comentario c
        JOIN ID_FIEL f ON f.IDF_ID = c.idf_id";
}

// ============================================================================
// RESPOSTAS AOS COMENTÁRIOS DOS VÍDEOS (seção 53 do bancosupabase.md)
// ============================================================================
// id_conteudo_comentario.idcc_pai_id: NULL = comentário principal; senão,
// resposta a ele. Um nível só: responder a uma resposta vira resposta ao
// mesmo comentário principal. Respostas não entram nas abas, no destaque
// nem nas contagens da lista principal.

if (!defined('VIDEO_RESPOSTAS_LIMITE')) {
    define('VIDEO_RESPOSTAS_LIMITE', 200);
}

function video_respostas_disponivel($conn) {
    return tabela_tem_coluna($conn, 'id_conteudo_comentario', 'idcc_pai_id');
}

// Comentário principal da conversa de $comentario_id (ele mesmo ou, se for
// uma resposta, o pai), desde que ele e o pai estejam visíveis. Devolve
// array('id' => ..., 'idct_id' => ...) ou null.
function video_comentario_principal($conn, $comentario_id) {
    if (!video_respostas_disponivel($conn)) {
        return null;
    }
    $comentario_id = (int) $comentario_id;
    for ($nivel = 0; $nivel < 2 && $comentario_id > 0; $nivel++) {
        $res = mysqli_query($conn, "SELECT idcc_id, idct_id, idcc_pai_id FROM id_conteudo_comentario WHERE idcc_id = $comentario_id AND idcc_oculto = 0");
        $linha = $res ? mysqli_fetch_assoc($res) : null;
        if (!$linha) {
            return null;
        }
        if ($linha['idcc_pai_id'] === null) {
            return array('id' => (int) $linha['idcc_id'], 'idct_id' => (int) $linha['idct_id']);
        }
        $comentario_id = (int) $linha['idcc_pai_id'];
    }
    return null;
}

// Link compartilhado (T20): assistir_video.php?id=AULA&comentario=ID.
// Devolve o comentário principal da conversa já formatado, desde que seja
// desta aula e esteja visível; null se não achar (o link só abre a aula).
function video_comentario_do_link($conn, $conteudo_id, $comentario_id) {
    $conteudo_id = (int) $conteudo_id;
    $comentario_id = (int) $comentario_id;
    if ($comentario_id <= 0) {
        return null;
    }
    if (video_respostas_disponivel($conn)) {
        $principal = video_comentario_principal($conn, $comentario_id);
        if (!$principal || $principal['idct_id'] !== $conteudo_id) {
            return null;
        }
        $comentario_id = $principal['id'];
    }
    $fiel_id = auth_resolve_fiel_id();
    $eh_admin = ($_SESSION['Usuario_tipo'] ?? '') === 'admin';
    $res = mysqli_query($conn, video_comentarios_select_sql($conn, $fiel_id) . "
        WHERE c.idcc_id = $comentario_id AND c.idct_id = $conteudo_id AND c.idcc_oculto = 0
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? video_formatar_comentario($linha, $fiel_id, $eh_admin) : null;
}

// Respostas visíveis de um comentário, das mais antigas para as mais novas
// (leitura de conversa).
function video_listar_respostas($conn, $comentario_id) {
    if (!video_respostas_disponivel($conn)) {
        return array();
    }
    $comentario_id = (int) $comentario_id;
    $limite = (int) VIDEO_RESPOSTAS_LIMITE;
    $fiel_id = auth_resolve_fiel_id();
    $eh_admin = ($_SESSION['Usuario_tipo'] ?? '') === 'admin';

    $res = mysqli_query($conn, video_comentarios_select_sql($conn, $fiel_id) . "
        WHERE c.idcc_pai_id = $comentario_id AND c.idcc_oculto = 0
        ORDER BY c.idcc_id ASC
        LIMIT $limite
    ");
    $respostas = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $respostas[] = video_formatar_comentario($linha, $fiel_id, $eh_admin);
    }
    return $respostas;
}

// Líder = da equipe (admin) ou com função de liderança na igreja
// (id_funcao_igreja.idfn_lider = 1, seção 50).
function video_comentario_lider_sql($conn) {
    $sql = "EXISTS(SELECT 1 FROM ID_ADMIN a WHERE a.IDA_FIEL_ID = f.IDF_ID AND a.IDA_ATIVO = 1)";
    if (funcoes_igreja_disponivel($conn)) {
        $sql = "($sql OR EXISTS(SELECT 1 FROM id_funcao_igreja fn WHERE fn.idfn_id = f.idf_funcao_id AND fn.idfn_lider = 1))";
    }
    return $sql;
}

// Condição extra do WHERE para a ordem/filtro (alias c e f). Sempre deixa
// de fora as respostas (T18): a lista, o destaque e as contagens são só de
// comentários principais.
function video_comentarios_filtro_sql($conn, $ordem) {
    $sql = video_respostas_disponivel($conn) ? 'AND c.idcc_pai_id IS NULL ' : '';
    // A nota fixada (T19) vai à parte, no topo de todas as abas.
    $sql .= video_nota_disponivel($conn) ? 'AND c.idcc_fixado_em IS NULL ' : '';
    if ($ordem === 'lideres') {
        return $sql . 'AND ' . video_comentario_lider_sql($conn);
    }
    if ($ordem === 'versiculo') {
        // Maiúsculas acentuadas também no translate: conforme o locale do
        // banco, o lower() não mexe nelas.
        // Com a T21, também conta o versículo citado pelo botão "Versículo".
        $no_texto = "translate(lower(c.idcc_mensagem), 'áàâãäéèêëíìîïóòôõöúùûüçÁÀÂÃÄÉÈÊËÍÌÎÏÓÒÔÕÖÚÙÛÜÇ', 'aaaaaeeeeiiiiooooouuuucaaaaaeeeeiiiiooooouuuuc') ~ '" . video_versiculo_regex() . "'";
        return $sql . (video_citacoes_disponivel($conn)
            ? "AND (c.idcc_versiculo IS NOT NULL OR $no_texto)"
            : "AND $no_texto");
    }
    return $sql;
}

// Saldo do comentário: Edificante (1) − Fora de contexto (-1).
function video_comentario_saldo_sql() {
    return "(SELECT COALESCE(SUM(v.idccv_tipo), 0) FROM id_conteudo_comentario_voto v WHERE v.idcc_id = c.idcc_id)";
}

// Uma página da lista. "recentes", "lideres" e "versiculo" vêm dos mais
// novos para os mais antigos e paginam por cursor ($antes_de = menor id já
// carregado): comentário novo no meio não repete nem pula nenhum.
// "relevantes" vem por saldo de votos e pagina por posição ($pular), já
// que o saldo muda; o JS descarta repetidos.
function video_listar_comentarios($conn, $conteudo_id, $limite = 200, $antes_de = 0, $ordem = 'recentes', $pular = 0) {
    $conteudo_id = (int) $conteudo_id;
    $limite = (int) $limite;
    $antes_de = (int) $antes_de;
    $pular = max(0, (int) $pular);
    $ordem = video_comentarios_ordem_valida($conn, $ordem);
    $fiel_id = auth_resolve_fiel_id();
    $eh_admin = ($_SESSION['Usuario_tipo'] ?? '') === 'admin';
    $filtro = video_comentarios_filtro_sql($conn, $ordem);

    if ($ordem === 'relevantes') {
        $ordenacao = 'ORDER BY ' . video_comentario_saldo_sql() . " DESC, c.idcc_id DESC LIMIT $limite OFFSET $pular";
    } else {
        $filtro .= $antes_de > 0 ? " AND c.idcc_id < $antes_de" : '';
        $ordenacao = "ORDER BY c.idcc_id DESC LIMIT $limite";
    }

    $res = mysqli_query($conn, video_comentarios_select_sql($conn, $fiel_id) . "
        WHERE c.idct_id = $conteudo_id AND c.idcc_oculto = 0 $filtro
        $ordenacao
    ");

    $comentarios = array();
    if ($res) {
        while ($linha = mysqli_fetch_assoc($res)) {
            $comentarios[] = video_formatar_comentario($linha, $fiel_id, $eh_admin);
        }
    }

    return $comentarios;
}

// O comentário de maior saldo dentro do filtro (null se nenhum chegou a
// VIDEO_DESTAQUE_MIN_SALDO ou sem a tabela de votos).
function video_comentario_destaque($conn, $conteudo_id, $ordem = 'recentes') {
    if (!video_votos_disponivel($conn)) {
        return null;
    }
    $conteudo_id = (int) $conteudo_id;
    $ordem = video_comentarios_ordem_valida($conn, $ordem);
    $fiel_id = auth_resolve_fiel_id();
    $eh_admin = ($_SESSION['Usuario_tipo'] ?? '') === 'admin';
    $saldo = video_comentario_saldo_sql();
    $minimo = (int) VIDEO_DESTAQUE_MIN_SALDO;

    $res = mysqli_query($conn, video_comentarios_select_sql($conn, $fiel_id) . "
        WHERE c.idct_id = $conteudo_id AND c.idcc_oculto = 0 " . video_comentarios_filtro_sql($conn, $ordem) . "
          AND $saldo >= $minimo
        ORDER BY $saldo DESC, c.idcc_id DESC
        LIMIT 1
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? video_formatar_comentario($linha, $fiel_id, $eh_admin) : null;
}

// Total de comentários visíveis; com $ordem, só os que passam no filtro.
function video_contar_comentarios($conn, $conteudo_id, $ordem = 'recentes') {
    $conteudo_id = (int) $conteudo_id;
    $filtro = video_comentarios_filtro_sql($conn, video_comentarios_ordem_valida($conn, $ordem));
    $res = mysqli_query($conn, "
        SELECT COUNT(*) AS total
        FROM id_conteudo_comentario c
        JOIN ID_FIEL f ON f.IDF_ID = c.idf_id
        WHERE c.idct_id = $conteudo_id AND c.idcc_oculto = 0 $filtro
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? (int) $linha['total'] : 0;
}

function video_formatar_comentario($linha, $fiel_id, $eh_admin) {
    $autor_id = (int) $linha['idf_id'];
    $comentario = array(
        'id' => (int) $linha['idcc_id'],
        'nome' => $eh_admin ? $linha['IDF_NOME'] : site_nome_curto($linha['IDF_NOME']),
        'equipe' => !empty($linha['eh_admin']),
        'lider' => !empty($linha['eh_lider']),
        // Selos do autor (T24): função (sem "Membro") e maior medalha.
        'funcao' => selos_funcao_texto($linha['funcao_nome'] ?? ''),
        'medalha' => selos_medalha_formatar($linha['medalha_topo'] ?? ''),
        'versiculo' => !empty($linha['versiculo_ref']) || video_texto_tem_versiculo($linha['idcc_mensagem']),
        // T21: minuto da aula (segundos) e versículo citados pelos botões.
        'momento' => isset($linha['momento']) ? (int) $linha['momento'] : null,
        'versiculo_ref' => isset($linha['versiculo_ref']) ? (string) $linha['versiculo_ref'] : '',
        'mensagem' => $linha['idcc_mensagem'],
        'criado_ts' => (int) $linha['criado_ts'],
        'meu' => $autor_id === (int) $fiel_id,
        'pode_excluir' => $eh_admin || $autor_id === (int) $fiel_id,
    );
    // Sem a tabela de votos (seção 52) a linha não traz essas colunas e a
    // página simplesmente não mostra os botões.
    if (array_key_exists('votos_edificante', $linha)) {
        $comentario['votos'] = video_formatar_votos($linha);
    }
    // Idem para as respostas (seção 53): sem a coluna, sem "Responder".
    if (array_key_exists('total_respostas', $linha)) {
        $comentario['pai_id'] = $linha['pai_id'] !== null ? (int) $linha['pai_id'] : 0;
        $comentario['respostas'] = (int) $linha['total_respostas'];
    }
    // Nota do pastor (seção 54). Fixa: admin, em comentário principal de
    // líder/equipe; o próprio líder, no comentário dele. O admin sempre
    // consegue desfixar (mesmo se o autor deixou de ser líder).
    if (array_key_exists('fixado', $linha)) {
        $principal = empty($linha['pai_id']);
        $autor_lider = !empty($linha['eh_lider']);
        $comentario['fixado'] = !empty($linha['fixado']);
        $comentario['pode_fixar'] = $principal && (
            ($eh_admin && ($autor_lider || $comentario['fixado']))
            || ($autor_lider && $autor_id === (int) $fiel_id)
        );
    }
    return $comentario;
}

// ============================================================================
// NOTA DO PASTOR FIXADA (seção 54 do bancosupabase.md)
// ============================================================================
// id_conteudo_comentario.idcc_fixado_em: preenchido = comentário fixado no
// topo da aula como "Nota do pastor". Um por aula (índice único parcial).
// A nota sai das abas, do destaque e das contagens da lista e aparece à
// parte, acima de tudo.

function video_nota_disponivel($conn) {
    return video_respostas_disponivel($conn) && tabela_tem_coluna($conn, 'id_conteudo_comentario', 'idcc_fixado_em');
}

function video_nota_fixada($conn, $conteudo_id) {
    if (!video_nota_disponivel($conn)) {
        return null;
    }
    $conteudo_id = (int) $conteudo_id;
    $fiel_id = auth_resolve_fiel_id();
    $eh_admin = ($_SESSION['Usuario_tipo'] ?? '') === 'admin';
    $res = mysqli_query($conn, video_comentarios_select_sql($conn, $fiel_id) . "
        WHERE c.idct_id = $conteudo_id AND c.idcc_oculto = 0 AND c.idcc_fixado_em IS NOT NULL
        LIMIT 1
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? video_formatar_comentario($linha, $fiel_id, $eh_admin) : null;
}

// Fixa (desfixando a nota anterior da aula) ou desfixa um comentário.
// Quem pode já foi conferido por quem chama (pode_fixar).
function video_fixar_comentario($conn, $comentario_id, $conteudo_id, $fixar) {
    $comentario_id = (int) $comentario_id;
    $conteudo_id = (int) $conteudo_id;
    if (!$fixar) {
        return (bool) mysqli_query($conn, "UPDATE id_conteudo_comentario SET idcc_fixado_em = NULL WHERE idcc_id = $comentario_id");
    }
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $conn->beginTransaction();
    // Trava por aula: dois pedidos ao mesmo tempo não brigam pelo índice único.
    mysqli_query($conn, "SELECT pg_advisory_xact_lock(hashtext('nota:$conteudo_id'))");
    $ok = mysqli_query($conn, "UPDATE id_conteudo_comentario SET idcc_fixado_em = NULL WHERE idct_id = $conteudo_id AND idcc_fixado_em IS NOT NULL")
        && mysqli_query($conn, "UPDATE id_conteudo_comentario SET idcc_fixado_em = CURRENT_TIMESTAMP WHERE idcc_id = $comentario_id");
    if ($ok) {
        $ok = db_commit($conn);
    } else {
        $conn->rollBack();
    }
    return (bool) $ok;
}

// Total do título: comentários principais + a nota fixada.
function video_contar_comentarios_titulo($conn, $conteudo_id) {
    return video_contar_comentarios($conn, $conteudo_id) + (video_nota_fixada($conn, $conteudo_id) ? 1 : 0);
}

// ============================================================================
// VOTOS NOS COMENTÁRIOS DOS VÍDEOS (seção 52 do bancosupabase.md)
// ============================================================================
// "Edificante" (↑) e "Fora de contexto" (↓). Um voto por fiel por
// comentário (troca ou desfaz); ninguém vota no próprio comentário.
// No banco: idccv_tipo 1 = edificante, -1 = fora de contexto.

function video_votos_disponivel($conn) {
    return tabela_tem_coluna($conn, 'id_conteudo_comentario_voto', 'idccv_tipo');
}

function video_voto_tipos() {
    return array('edificante' => 1, 'fora' => -1);
}

// Colunas extras (começam com vírgula) para o SELECT de comentários com
// alias "c": as duas contagens e o voto de quem está vendo. '' sem a tabela.
function video_comentario_colunas_votos($conn, $fiel_id) {
    if (!video_votos_disponivel($conn)) {
        return '';
    }
    $fiel_id = (int) $fiel_id;
    return ",
            (SELECT COUNT(*) FROM id_conteudo_comentario_voto v WHERE v.idcc_id = c.idcc_id AND v.idccv_tipo = 1) AS votos_edificante,
            (SELECT COUNT(*) FROM id_conteudo_comentario_voto v WHERE v.idcc_id = c.idcc_id AND v.idccv_tipo = -1) AS votos_fora,
            (SELECT v.idccv_tipo FROM id_conteudo_comentario_voto v WHERE v.idcc_id = c.idcc_id AND v.idf_id = $fiel_id) AS meu_voto";
}

function video_formatar_votos($linha) {
    $meu = (int) ($linha['meu_voto'] ?? 0);
    return array(
        'edificante' => (int) $linha['votos_edificante'],
        'fora' => (int) $linha['votos_fora'],
        'meu' => (string) (array_search($meu, video_voto_tipos(), true) ?: ''),
    );
}

// Contagens e voto do fiel num comentário só (resposta depois de votar).
function video_votos_do_comentario($conn, $comentario_id, $fiel_id) {
    $comentario_id = (int) $comentario_id;
    $colunas = video_comentario_colunas_votos($conn, $fiel_id);
    $res = mysqli_query($conn, "SELECT c.idcc_id $colunas FROM id_conteudo_comentario c WHERE c.idcc_id = $comentario_id");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? video_formatar_votos($linha) : array('edificante' => 0, 'fora' => 0, 'meu' => '');
}

// Grava o voto ($tipo 'edificante' ou 'fora') ou o desfaz ($tipo '').
function video_votar_comentario($conn, $comentario_id, $fiel_id, $tipo) {
    $comentario_id = (int) $comentario_id;
    $fiel_id = (int) $fiel_id;
    $tipos = video_voto_tipos();

    if ($tipo === '') {
        return (bool) mysqli_query($conn, "DELETE FROM id_conteudo_comentario_voto WHERE idcc_id = $comentario_id AND idf_id = $fiel_id");
    }
    $valor = $tipos[$tipo];
    return (bool) mysqli_query($conn, "
        INSERT INTO id_conteudo_comentario_voto (idcc_id, idf_id, idccv_tipo)
        VALUES ($comentario_id, $fiel_id, $valor)
        ON CONFLICT (idcc_id, idf_id) DO UPDATE SET
            idccv_tipo = EXCLUDED.idccv_tipo,
            idccv_atualizado_em = CURRENT_TIMESTAMP
    ");
}

// ============================================================================
// MEMBRO DA IGREJA (seção 47 do bancosupabase.md)
// ============================================================================
// ID_FIEL.IDF_MEMBRO: NULL = não informou, 1 = membro, 0 = não membro.
// ID_FIEL.IDF_MEMBRO_DESDE: 1º dia do mês/ano em que entrou (só membro).
// Pedido no cadastro (php/fiel/register.php) e editável no perfil
// (php/fiel/perfil.php). Sem as colunas no banco, nada disso aparece.

function fiel_membro_disponivel($conn) {
    return tabela_tem_coluna($conn, 'ID_FIEL', 'IDF_MEMBRO') && tabela_tem_coluna($conn, 'ID_FIEL', 'IDF_MEMBRO_DESDE');
}

function fiel_membro_meses() {
    return array(1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro');
}

// Valida o que veio do formulário: $membro '1' ou '0'; mês e ano só contam
// para membro. Devolve array('erro' => string|null, 'membro' => 0|1,
// 'desde' => 'AAAA-MM-01'|null).
function fiel_membro_validar($membro, $mes, $ano) {
    $membro = (string) $membro;
    if ($membro !== '1' && $membro !== '0') {
        return array('erro' => 'Informe se você é membro da igreja.', 'membro' => null, 'desde' => null);
    }
    if ($membro === '0') {
        return array('erro' => null, 'membro' => 0, 'desde' => null);
    }

    $mes = (int) $mes;
    $ano = (int) $ano;
    $ano_atual = (int) date('Y');
    if ($mes < 1 || $mes > 12 || $ano < 1900 || $ano > $ano_atual) {
        return array('erro' => 'Informe o mês e o ano em que você se tornou membro.', 'membro' => 1, 'desde' => null);
    }
    if ($ano === $ano_atual && $mes > (int) date('n')) {
        return array('erro' => 'O mês de entrada não pode ser no futuro.', 'membro' => 1, 'desde' => null);
    }
    return array('erro' => null, 'membro' => 1, 'desde' => sprintf('%04d-%02d-01', $ano, $mes));
}

// "3 anos e 2 meses", "1 ano", "5 meses", "menos de 1 mês" desde $desde.
function fiel_tempo_membresia($desde, $hoje = null) {
    $inicio = date_create((string) $desde);
    if (!$inicio) {
        return '';
    }
    $diferenca = $inicio->diff($hoje ? date_create($hoje) : new DateTime('today'));
    if ($diferenca->invert) {
        return 'menos de 1 mês';
    }
    $partes = array();
    if ($diferenca->y > 0) {
        $partes[] = $diferenca->y . ($diferenca->y === 1 ? ' ano' : ' anos');
    }
    if ($diferenca->m > 0) {
        $partes[] = $diferenca->m . ($diferenca->m === 1 ? ' mês' : ' meses');
    }
    return $partes ? implode(' e ', $partes) : 'menos de 1 mês';
}

// "março de 2021"
function fiel_membro_desde_texto($desde) {
    $data = date_create((string) $desde);
    if (!$data) {
        return '';
    }
    $meses = fiel_membro_meses();
    return $meses[(int) $data->format('n')] . ' de ' . $data->format('Y');
}

// ============================================================================
// PERFIL: IDENTIDADE E CONTEXTO FAMILIAR (seção 49 do bancosupabase.md)
// ============================================================================
// WhatsApp: usa ID_FIEL.IDF_TELEFONE (já existia; o admin e o Asaas leem
//   dali), sempre 11 dígitos: DDD + celular começando com 9.
// ID_FIEL.IDF_NASCIMENTO, IDF_ESTADO_CIVIL, IDF_TEM_FILHOS (NULL = não
//   informou, 1/0), IDF_CIDADE, IDF_UF: opcionais.
// ID_FIEL_FOTO: foto de perfil (JPEG em base64, 1 por fiel). Fica no banco
//   porque o disco do Render é apagado a cada deploy; o navegador já manda a
//   foto recortada e reduzida (o servidor não tem a extensão GD).
// Sem as colunas/tabela no banco, esses campos não aparecem.

define('FIEL_FOTO_LADO', 320);
define('FIEL_FOTO_MAX_BYTES', 300 * 1024);

function fiel_perfil_disponivel($conn) {
    foreach (array('IDF_NASCIMENTO', 'IDF_ESTADO_CIVIL', 'IDF_TEM_FILHOS', 'IDF_CIDADE', 'IDF_UF') as $coluna) {
        if (!tabela_tem_coluna($conn, 'ID_FIEL', $coluna)) {
            return false;
        }
    }
    return true;
}

function fiel_foto_disponivel($conn) {
    return tabela_tem_coluna($conn, 'ID_FIEL_FOTO', 'IDFF_DADOS');
}

// '11987654321' a partir do que foi digitado ("(11) 9 8765-4321", com ou sem
// +55). '' se vazio; null se não for um celular brasileiro válido.
function fiel_whatsapp_normalizar($texto) {
    $digitos = preg_replace('/\D/', '', (string) $texto);
    if ($digitos === '') {
        return '';
    }
    if (strlen($digitos) === 13 && strpos($digitos, '55') === 0) {
        $digitos = substr($digitos, 2);
    }
    if (!preg_match('/^[1-9][1-9]9\d{8}$/', $digitos)) {
        return null;
    }
    return $digitos;
}

// "(11) 9 8765-4321" (mesmo formato do admin). Valor fora do padrão volta
// como está.
function fiel_whatsapp_formatar($telefone) {
    $digitos = preg_replace('/\D/', '', (string) $telefone);
    if (strlen($digitos) !== 11) {
        return (string) $telefone;
    }
    return sprintf('(%s) %s %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 1), substr($digitos, 3, 4), substr($digitos, 7, 4));
}

// Telefone nas listas do admin: igual a fiel_whatsapp_formatar(), com "N/A"
// quando não há número. Antes era uma cópia em cada tela do admin.
function formatar_telefone_exibicao($telefone) {
    return preg_replace('/\D/', '', (string) $telefone) === '' ? 'N/A' : fiel_whatsapp_formatar($telefone);
}

// Data de nascimento do <input type="date"> ('AAAA-MM-DD'), opcional.
// Devolve array('erro' => string|null, 'data' => 'AAAA-MM-DD'|null).
function fiel_nascimento_validar($texto) {
    $texto = trim((string) $texto);
    if ($texto === '') {
        return array('erro' => null, 'data' => null);
    }
    $data = DateTime::createFromFormat('!Y-m-d', $texto);
    if (!$data || $data->format('Y-m-d') !== $texto) {
        return array('erro' => 'Data de nascimento inválida.', 'data' => null);
    }
    if ((int) $data->format('Y') < 1900 || $data > new DateTime('today')) {
        return array('erro' => 'Confira a data de nascimento.', 'data' => null);
    }
    return array('erro' => null, 'data' => $texto);
}

// Aniversário hoje? (29/02 comemora em 28/02 nos anos não bissextos.)
function fiel_aniversario_hoje($nascimento, $hoje = null) {
    $data = $nascimento ? date_create((string) $nascimento) : null;
    if (!$data) {
        return false;
    }
    $hoje = $hoje ? date_create($hoje) : new DateTime('today');
    $dia_mes = $data->format('m-d');
    if ($dia_mes === '02-29' && !$hoje->format('L')) {
        $dia_mes = '02-28';
    }
    return $dia_mes === $hoje->format('m-d');
}

// "12 de março de 1990"
function fiel_data_extenso($data) {
    $data = $data ? date_create((string) $data) : null;
    if (!$data) {
        return '';
    }
    $meses = fiel_membro_meses();
    return (int) $data->format('j') . ' de ' . $meses[(int) $data->format('n')] . ' de ' . $data->format('Y');
}

// ============================================================================
// LGPD (seção 82 do bancosupabase.md; regras em docs/interno/lgpd.md)
// ============================================================================
// Política de privacidade (privacidade.php), aceite no cadastro, cadastro de
// menor com o responsável, "Baixar meus dados" e "Excluir minha conta" em
// Configurações, e a limpeza periódica do que não precisa ficar guardado.
if (!defined('LGPD_MAIORIDADE')) {
    define('LGPD_MAIORIDADE', 18);
}
// Trocar quando a política mudar de forma relevante (vai no aceite do cadastro).
if (!defined('PRIVACIDADE_VERSAO_ATUAL')) {
    define('PRIVACIDADE_VERSAO_ATUAL', '2026-10-27');
}

function lgpd_disponivel($conn) {
    return $conn && tabela_tem_coluna($conn, 'id_fiel', 'idf_privacidade_aceita_em');
}

// Idade em anos completos (null sem data válida ou data no futuro).
function site_idade($nascimento, $hoje = null) {
    $data = $nascimento ? date_create((string) $nascimento) : null;
    $hoje = $hoje ? date_create((string) $hoje) : new DateTime('today');
    if (!$data || !$hoje || $data > $hoje) {
        return null;
    }
    return (int) $data->diff($hoje)->y;
}

function lgpd_eh_menor($nascimento, $hoje = null) {
    $idade = site_idade($nascimento, $hoje);
    return $idade !== null && $idade < LGPD_MAIORIDADE;
}

// Aceite do cadastro. Menor de 18 (pela data de nascimento) só entra com o
// nome do pai, mãe ou responsável e a autorização dele (LGPD, art. 14).
// Devolve '' ou a mensagem de erro.
function lgpd_validar_cadastro($aceitou, $nascimento, $responsavel_nome, $responsavel_autorizou) {
    if (!$aceitou) {
        return 'Para criar a conta, aceite os Termos de Uso e a Política de Privacidade.';
    }
    if (!lgpd_eh_menor($nascimento)) {
        return '';
    }
    $nome = trim((string) $responsavel_nome);
    if (mb_strlen($nome) < 5 || mb_strlen($nome) > 100 || strpos($nome, ' ') === false
        || !preg_match("/^[\p{L}\p{M}' .-]+$/uD", $nome)) {
        return 'Menores de 18 anos: informe o nome completo do pai, mãe ou responsável.';
    }
    if (!$responsavel_autorizou) {
        return 'Menores de 18 anos: o responsável precisa autorizar o cadastro.';
    }
    return '';
}

// Configurações: a data de nascimento já informada não pode ser apagada, e
// uma data que torna a pessoa menor só vale se a conta já tem o responsável
// (sem isso, um menor aprovado trocava a data e ficava sem responsável).
// Devolve '' ou a mensagem de erro.
function configuracoes_nascimento_bloqueio($conn, $fiel_id, $nova) {
    $res = db_query($conn, 'SELECT idf_nascimento' . (tabela_tem_coluna($conn, 'id_fiel', 'idf_responsavel_nome') ? ', idf_responsavel_nome' : ', NULL AS idf_responsavel_nome') . ' FROM id_fiel WHERE idf_id = ? LIMIT 1', array((int) $fiel_id));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if (!$linha) {
        return '';
    }
    return lgpd_nascimento_bloqueio($linha['idf_nascimento'] ?? null, $linha['idf_responsavel_nome'] ?? null, $nova);
}

// Regra pura de configuracoes_nascimento_bloqueio() (testável sem banco).
function lgpd_nascimento_bloqueio($atual, $responsavel, $nova) {
    if (!empty($atual) && empty($nova)) {
        return 'A data de nascimento não pode ficar em branco.';
    }
    if (!empty($nova) && lgpd_eh_menor($nova) && trim((string) $responsavel) === '') {
        return 'Para menores de 18 anos, a data de nascimento é alterada pela equipe da igreja, com a autorização do responsável.';
    }
    return '';
}

// Tabelas com a coluna idf_id (dados ligados ao fiel), do schema real:
// tabela nova entra sozinha na exportação e na exclusão.
function lgpd_tabelas_do_fiel($conn) {
    $mapa = site_schema_mapa($conn);
    $tabelas = array();
    foreach ((array) $mapa as $tabela => $colunas) {
        if ($tabela !== 'id_fiel' && isset($colunas['idf_id']) && preg_match('/^[a-z0-9_]+$/', $tabela)) {
            $tabelas[] = $tabela;
        }
    }
    sort($tabelas);
    return $tabelas;
}

// Colunas que nunca saem na exportação (segredos, 2FA e a foto em base64).
function lgpd_coluna_oculta($coluna) {
    return (bool) preg_match('/(senha|hash|token|segredo|_2fa_|idff_dados)/i', (string) $coluna);
}

// "Baixar meus dados": tudo o que o app guarda do fiel, por tabela.
function lgpd_exportar_dados($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $limpar = function ($linhas) {
        $saida = array();
        foreach ($linhas as $linha) {
            foreach (array_keys($linha) as $coluna) {
                // O shim devolve cada coluna também em MAIÚSCULO
                // (SitePgResult): no arquivo fica só a do banco.
                if (lgpd_coluna_oculta($coluna) || $coluna !== strtolower($coluna)) {
                    unset($linha[$coluna]);
                }
            }
            $saida[] = $linha;
        }
        return $saida;
    };
    $ler = function ($sql) use ($conn, $fiel_id) {
        $res = db_query($conn, $sql, array($fiel_id));
        $linhas = array();
        while ($res && ($linha = mysqli_fetch_assoc($res))) {
            $linhas[] = $linha;
        }
        return $linhas;
    };

    $dados = array(
        'gerado_em' => date('d/m/Y H:i'),
        'sobre' => 'Cópia dos seus dados guardados no app (LGPD, art. 18). Senhas e códigos de acesso não aparecem; a foto de perfil fica no seu perfil.',
        'conta' => $limpar($ler("SELECT * FROM id_fiel WHERE idf_id = ?")),
    );
    foreach (lgpd_tabelas_do_fiel($conn) as $tabela) {
        $linhas = $limpar($ler("SELECT * FROM $tabela WHERE idf_id = ? LIMIT 5000"));
        if ($linhas) {
            $dados[$tabela] = $linhas;
        }
    }
    if (site_tabela_existe($conn, 'id_pagamento') && site_tabela_existe($conn, 'id_assinatura')) {
        $pagamentos = $limpar($ler("SELECT p.* FROM id_pagamento p JOIN id_assinatura a ON a.idas_id = p.idas_id WHERE a.idf_id = ?"));
        if ($pagamentos) {
            $dados['id_pagamento'] = $pagamentos;
        }
    }
    return $dados;
}

// Motivo que impede o próprio fiel de excluir a conta agora ('' = pode).
function lgpd_exclusao_bloqueio($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $res = db_query($conn, "SELECT 1 FROM ID_ADMIN WHERE IDA_FIEL_ID = ? AND IDA_ATIVO = 1 LIMIT 1", array($fiel_id));
    if ($res && mysqli_num_rows($res) > 0) {
        return 'Sua conta é de administrador. Peça para outro administrador remover seu acesso de admin antes de excluir a conta.';
    }
    if (site_tabela_existe($conn, 'id_assinatura')) {
        $res = db_query($conn, "SELECT 1 FROM id_assinatura WHERE idf_id = ? AND idas_asaas_subscription_id IS NOT NULL AND idas_status <> 'CANCELADA' LIMIT 1", array($fiel_id));
        if ($res && mysqli_num_rows($res) > 0) {
            return 'Você tem uma assinatura ativa. Cancele a assinatura antes de excluir a conta, para não continuar sendo cobrado.';
        }
    }
    return '';
}

// Exclui a conta (pedido do próprio fiel ou do admin). Sem histórico de
// pagamento nem conteúdo público, apaga a linha e tudo vai junto (ON DELETE
// CASCADE). Com histórico (assinatura ou contribuição; a lei manda guardar
// os registros financeiros) ou com comentário respondido por outra pessoa
// ou devocional publicado: a conta vira "Conta excluída", sem nenhum dado
// pessoal, e o resto do que é dela é apagado. Antes, o DELETE do admin apagava junto os
// pagamentos de assinaturas canceladas e o Financeiro perdia as entradas.
// Comentário com respostas de outras pessoas fica oculto e sem o texto
// (apagar levaria as respostas junto); devocional já publicado fica, sem o
// nome de quem escreveu. Devolve array('ok', 'erro', 'modo').
function lgpd_excluir_conta($conn, $fiel_id, $origem = 'proprio') {
    $fiel_id = (int) $fiel_id;
    $resultado = array('ok' => false, 'erro' => 'Não foi possível excluir a conta. Tente de novo.', 'modo' => '');
    if (!$conn || $fiel_id <= 0) {
        return $resultado;
    }

    $tem_financeiro = false;
    foreach (array('id_assinatura', 'id_missao_contribuicao') as $tabela) {
        if (site_tabela_existe($conn, $tabela)) {
            $res = db_query($conn, "SELECT 1 FROM $tabela WHERE idf_id = ? LIMIT 1", array($fiel_id));
            $tem_financeiro = $tem_financeiro || ($res && mysqli_num_rows($res) > 0);
        }
    }
    // Conteúdo que é de outras pessoas também: o DELETE em cascata levaria as
    // respostas de outros fiéis aos comentários dele (idcc_pai_id) e os
    // devocionais já publicados para toda a igreja. Com isso, anonimiza.
    $tem_publico = false;
    if (tabela_tem_coluna($conn, 'id_conteudo_comentario', 'idcc_pai_id')) {
        $res = db_query($conn, "
            SELECT 1 FROM id_conteudo_comentario r
            JOIN id_conteudo_comentario c ON c.idcc_id = r.idcc_pai_id
            WHERE c.idf_id = ? AND r.idf_id <> c.idf_id LIMIT 1
        ", array($fiel_id));
        $tem_publico = $res === false || mysqli_num_rows($res) > 0;
    }
    if (!$tem_publico && site_tabela_existe($conn, 'id_devocional')) {
        $res = db_query($conn, "SELECT 1 FROM id_devocional WHERE idf_id = ? AND iddv_status = 'aprovado' LIMIT 1", array($fiel_id));
        $tem_publico = $res === false || mysqli_num_rows($res) > 0;
    }

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    // 1) Sem histórico financeiro nem conteúdo público: apaga de vez.
    if (!$tem_financeiro && !$tem_publico) {
        $conn->beginTransaction();
        if (db_query($conn, "DELETE FROM ID_FIEL WHERE IDF_ID = ?", array($fiel_id)) !== false && db_commit($conn)) {
            lgpd_registrar($conn, $fiel_id, 'exclusao', $origem);
            return array('ok' => true, 'erro' => '', 'modo' => 'apagada');
        }
        // Algum registro de outra tabela aponta para a conta sem cascata
        // (ex.: foi admin e moderou algo): segue para a anonimização.
        $conn->rollBack();
    }

    // 2) Anonimiza, numa transação só.
    $conn->beginTransaction();
    $ok = true;
    $rodar = function ($sql, $params) use ($conn, &$ok) {
        if ($ok && db_query($conn, $sql, $params) === false) {
            $ok = false;
        }
    };
    $mantidas = array('id_assinatura', 'id_missao_contribuicao', 'id_conteudo_comentario', 'id_devocional');
    if (site_tabela_existe($conn, 'id_conteudo_comentario')) {
        $rodar("UPDATE id_conteudo_comentario SET idcc_mensagem = '[comentário removido]', idcc_oculto = 1 WHERE idf_id = ?", array($fiel_id));
    }
    if (site_tabela_existe($conn, 'id_devocional')) {
        $rodar("DELETE FROM id_devocional WHERE idf_id = ? AND iddv_status <> 'aprovado'", array($fiel_id));
    }
    if (tabela_tem_coluna($conn, 'id_missao_contribuicao', 'idmc_nome_exibicao')) {
        $rodar("UPDATE id_missao_contribuicao SET idmc_anonimo = 1, idmc_nome_exibicao = NULL, idmc_mensagem = NULL WHERE idf_id = ?", array($fiel_id));
    }
    foreach (lgpd_tabelas_do_fiel($conn) as $tabela) {
        if (!in_array($tabela, $mantidas, true)) {
            $rodar("DELETE FROM $tabela WHERE idf_id = ?", array($fiel_id));
        }
    }

    $sets = array("IDF_NOME = 'Conta excluída'", "IDF_EMAIL = ?", 'IDF_ATIVO = 0', "IDF_STATUS = 'negado'", 'IDF_REMOVIDO_EM = NOW()');
    $params = array('conta-excluida-' . $fiel_id . '@excluida.invalid');
    $pessoais = array('idf_telefone', 'idf_cpf', 'idf_endereco', 'idf_nascimento', 'idf_estado_civil', 'idf_tem_filhos',
        'idf_cidade', 'idf_uf', 'idf_filial', 'idf_funcao', 'idf_auth_user_id', 'idf_senha', 'idf_senha_hash',
        'idf_ultima_pagina', 'idf_membro', 'idf_membro_desde', 'idf_capa', 'idf_medalhas_destaque', 'idf_responsavel_nome');
    foreach ($pessoais as $coluna) {
        if (tabela_tem_coluna($conn, 'id_fiel', $coluna)) {
            $sets[] = strtoupper($coluna) . ' = NULL';
        }
    }
    $params[] = $fiel_id;
    $rodar("UPDATE ID_FIEL SET " . implode(', ', $sets) . " WHERE IDF_ID = ?", $params);

    if (!$ok) {
        $conn->rollBack();
        error_log('LGPD: falha ao anonimizar a conta ' . $fiel_id . ' (' . (string) ($GLOBALS['site_pg_last_error'] ?? '') . ')');
        return $resultado;
    }
    if (!db_commit($conn)) {
        return $resultado;
    }
    lgpd_registrar($conn, $fiel_id, 'exclusao', $origem);
    return array('ok' => true, 'erro' => '', 'modo' => 'anonimizada');
}

// Registro de que o pedido foi atendido (prova para a ANPD), sem dado
// pessoal: só o número da conta, o tipo, quem pediu e a data.
function lgpd_registrar($conn, $fiel_id, $tipo, $origem = 'proprio') {
    if (site_tabela_existe($conn, 'id_lgpd_pedido')) {
        @db_query($conn, "INSERT INTO id_lgpd_pedido (idlp_fiel_id, idlp_tipo, idlp_origem) VALUES (?, ?, ?)", array((int) $fiel_id, (string) $tipo, (string) $origem));
    }
}

// Limpeza do que não precisa ficar guardado (minimização, LGPD art. 16) e
// das tabelas que só cresciam. Roda no máximo uma vez por dia por container,
// chamada pelo agendador do lembrete diário (lembrete_diario.php).
function site_manutencao_limpar($conn) {
    $marca = sys_get_temp_dir() . '/mananciapp_manutencao_' . site_hoje();
    if (!$conn || is_file($marca)) {
        return array();
    }
    @touch($marca);
    $regras = array(
        // Registro de acesso (IP, aparelho): o Marco Civil (art. 15) manda
        // guardar por 6 meses; sai um pouco depois disso.
        'sessoes' => array('id_sessao', "DELETE FROM ID_SESSAO WHERE (IDS_REVOGADA = 1 OR IDS_EXPIRA_EM < NOW()) AND IDS_CRIADA_EM < NOW() - INTERVAL '7 months'"),
        'sessoes_php' => array('id_sessao_php', "DELETE FROM id_sessao_php WHERE atualizado_em < NOW() - INTERVAL '7 days'"),
        'tentativas_login' => array('id_login_tentativa', "DELETE FROM ID_LOGIN_TENTATIVA WHERE IDLT_CRIADO_EM < NOW() - INTERVAL '30 days'"),
        'notificacoes_lidas' => array('id_notificacao', "DELETE FROM ID_NOTIFICACAO WHERE IDN_LIDA = 1 AND IDN_CRIADO_EM < NOW() - INTERVAL '180 days'"),
        'log_admin' => array('id_admin_log', "DELETE FROM id_admin_log WHERE idal_criado_em < NOW() - INTERVAL '2 years'"),
        'falhas_sistema' => array('id_sistema_falha', "DELETE FROM id_sistema_falha WHERE isf_criado_em < NOW() - INTERVAL '30 days'"),
    );
    $apagados = array();
    $falhas = array();
    foreach ($regras as $nome => $regra) {
        if (!site_tabela_existe($conn, $regra[0])) {
            continue;
        }
        if (db_query($conn, $regra[1]) !== false) {
            $apagados[$nome] = (int) ($GLOBALS['site_pg_last_affected_rows'] ?? 0);
        } else {
            $falhas[] = $nome;
        }
    }
    if ($falhas) {
        // A marca do dia sai: a próxima chamada do agendador tenta de novo
        // (antes só no dia seguinte, e sem registro nenhum).
        @unlink($marca);
        error_log('Limpeza diária: falhou em ' . implode(', ', $falhas));
        $apagados['falhas'] = $falhas;
    }
    return $apagados;
}

function fiel_estados_civis() {
    return array(
        'solteiro' => 'Solteiro(a)',
        'casado' => 'Casado(a)',
        'uniao_estavel' => 'União estável',
        'divorciado' => 'Divorciado(a)',
        'viuvo' => 'Viúvo(a)',
    );
}

function fiel_ufs() {
    return array('AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
        'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO');
}

// Contexto familiar, tudo opcional. Devolve array('erro' => string|null,
// 'estado_civil' => código|null, 'tem_filhos' => 1|0|null, 'cidade' =>
// string|null, 'uf' => string|null).
function fiel_contexto_validar($estado_civil, $tem_filhos, $cidade, $uf) {
    $estado_civil = trim((string) $estado_civil);
    $tem_filhos = (string) $tem_filhos;
    $cidade = trim(preg_replace('/\s+/', ' ', (string) $cidade));
    $uf = strtoupper(trim((string) $uf));
    $resultado = array('erro' => null, 'estado_civil' => null, 'tem_filhos' => null, 'cidade' => null, 'uf' => null);

    if ($estado_civil !== '' && !isset(fiel_estados_civis()[$estado_civil])) {
        $resultado['erro'] = 'Estado civil inválido.';
    } elseif ($tem_filhos !== '' && $tem_filhos !== '1' && $tem_filhos !== '0') {
        $resultado['erro'] = 'Resposta inválida para "Tem filhos?".';
    } elseif (mb_strlen($cidade) > 100 || preg_match('/[0-9<>]/', $cidade)) {
        $resultado['erro'] = 'Cidade inválida (até 100 letras, sem números).';
    } elseif ($uf !== '' && !in_array($uf, fiel_ufs(), true)) {
        $resultado['erro'] = 'Estado (UF) inválido.';
    } else {
        $resultado['estado_civil'] = $estado_civil !== '' ? $estado_civil : null;
        $resultado['tem_filhos'] = $tem_filhos !== '' ? (int) $tem_filhos : null;
        $resultado['cidade'] = $cidade !== '' ? $cidade : null;
        $resultado['uf'] = $uf !== '' ? $uf : null;
    }
    return $resultado;
}

// Foto enviada pelo navegador como data URL JPEG. Devolve array('erro' =>
// string|null, 'base64' => string|null). Só confere (sem GD não dá para
// reprocessar): JPEG de verdade, tamanho e dimensões dentro do esperado.
function fiel_foto_validar($data_url) {
    $data_url = (string) $data_url;
    $erro = array('erro' => 'Não foi possível ler a foto. Tente outra imagem.', 'base64' => null);
    if (!preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', $data_url, $m)) {
        return $erro;
    }
    $binario = base64_decode($m[1], true);
    if ($binario === false || $binario === '') {
        return $erro;
    }
    if (strlen($binario) > FIEL_FOTO_MAX_BYTES) {
        return array('erro' => 'A foto ficou grande demais. Tente outra imagem.', 'base64' => null);
    }
    $info = @getimagesizefromstring($binario);
    if (!$info || $info[2] !== IMAGETYPE_JPEG || $info[0] < 32 || $info[1] < 32 || $info[0] > 1024 || $info[1] > 1024) {
        return $erro;
    }
    return array('erro' => null, 'base64' => base64_encode($binario));
}

// Versão (timestamp) da foto do fiel, ou null se não tiver. Uma consulta por
// fiel por requisição (o menu e o perfil pedem a mesma).
function fiel_foto_versao($conn, $fiel_id) {
    static $cache = array();
    $fiel_id = (int) $fiel_id;
    if ($fiel_id <= 0 || !fiel_foto_disponivel($conn)) {
        return null;
    }
    // A foto do próprio fiel aparece na navbar de toda página: a versão fica
    // na sessão (fiel_foto_versao_esquecer() limpa quando ele troca a foto).
    $eh_o_proprio = isset($_SESSION['Usuario_id']) && (int) $_SESSION['Usuario_id'] === $fiel_id;
    if ($eh_o_proprio && isset($_SESSION['foto_versao']) && array_key_exists('v', $_SESSION['foto_versao']) && ($_SESSION['foto_versao']['id'] ?? 0) === $fiel_id) {
        $cache[$fiel_id] = $_SESSION['foto_versao']['v'];
    }
    if (!array_key_exists($fiel_id, $cache)) {
        // Foto de menor só para ele mesmo e para o admin (foto_perfil.php
        // recusa para os outros): sem URL, a tela mostra as iniciais em vez
        // de uma imagem quebrada.
        $conferir_menor = !$eh_o_proprio && ($_SESSION['Usuario_tipo'] ?? '') !== 'admin' && tabela_tem_coluna($conn, 'id_fiel', 'idf_nascimento');
        $res = $conferir_menor
            ? mysqli_query($conn, "SELECT ff.idff_atualizado_em, f.idf_nascimento FROM id_fiel_foto ff JOIN id_fiel f ON f.idf_id = ff.idf_id WHERE ff.idf_id = $fiel_id LIMIT 1")
            : mysqli_query($conn, "SELECT idff_atualizado_em FROM id_fiel_foto WHERE idf_id = $fiel_id LIMIT 1");
        $linha = $res ? mysqli_fetch_assoc($res) : null;
        if ($linha && $conferir_menor && lgpd_eh_menor($linha['idf_nascimento'] ?? null)) {
            $linha = null;
        }
        $cache[$fiel_id] = $linha ? (string) strtotime($linha['idff_atualizado_em']) : null;
        if ($eh_o_proprio && $res) {
            $_SESSION['foto_versao'] = array('id' => $fiel_id, 'v' => $cache[$fiel_id]);
        }
    }
    return $cache[$fiel_id];
}

// Chamar depois de salvar ou remover a foto do fiel logado (perfil.php).
function fiel_foto_versao_esquecer() {
    unset($_SESSION['foto_versao']);
}

// URL da foto (relativa a php/fiel/), com a versão na query para o cache do
// navegador renovar quando a foto muda. null se o fiel não tem foto.
function fiel_foto_url($conn, $fiel_id, $prefixo = '') {
    $versao = fiel_foto_versao($conn, $fiel_id);
    return $versao === null ? null : $prefixo . 'foto_perfil.php?id=' . (int) $fiel_id . '&v=' . $versao;
}

// ============================================================================
// FUNÇÃO NA IGREJA (seção 50 do bancosupabase.md)
// ============================================================================
// ID_FUNCAO_IGREJA: lista mantida pelo admin (php/admin/admin_funcoes.php).
//   IDFN_LIDER = 1 marca as funções de liderança (selo "Líder" nos
//   comentários, T18).
// ID_FIEL.IDF_FUNCAO_ID: função escolhida pelo ADMIN — o fiel só vê no
//   perfil. ID_FIEL.IDF_FUNCAO (texto livre antigo) passa a guardar o nome da
//   função escolhida, para as telas que já mostravam esse texto continuarem
//   certas; fiel antigo sem correspondência na lista fica com o texto antigo
//   até o admin escolher.

define('FUNCAO_IGREJA_NOME_MAX', 50);

function funcoes_igreja_disponivel($conn) {
    return tabela_tem_coluna($conn, 'ID_FUNCAO_IGREJA', 'IDFN_NOME') && tabela_tem_coluna($conn, 'ID_FIEL', 'IDF_FUNCAO_ID');
}

// Todas as funções (ativas primeiro na ordem do admin), indexadas pelo id:
// [id => array('id', 'nome', 'ordem', 'ativo', 'lider')].
function funcoes_igreja_listar($conn) {
    if (!funcoes_igreja_disponivel($conn)) {
        return array();
    }
    $lista = array();
    $res = mysqli_query($conn, "SELECT idfn_id, idfn_nome, idfn_ordem, idfn_ativo, idfn_lider FROM id_funcao_igreja ORDER BY idfn_ativo DESC, idfn_ordem ASC, idfn_nome ASC");
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $lista[(int) $linha['idfn_id']] = array(
            'id' => (int) $linha['idfn_id'],
            'nome' => (string) $linha['idfn_nome'],
            'ordem' => (int) $linha['idfn_ordem'],
            'ativo' => (int) $linha['idfn_ativo'] === 1,
            'lider' => (int) $linha['idfn_lider'] === 1,
        );
    }
    return $lista;
}

// Define (ou limpa, com $funcao_id = 0) a função de um fiel. Só aceita
// função ativa, a não ser que seja a que o fiel já tem. Devolve null se deu
// certo ou a mensagem de erro.
function fiel_funcao_definir($conn, $fiel_id, $funcao_id) {
    $fiel_id = (int) $fiel_id;
    $funcao_id = (int) $funcao_id;
    if ($fiel_id <= 0) {
        return 'Fiel inválido.';
    }
    if ($funcao_id === 0) {
        $ok = mysqli_query($conn, "UPDATE id_fiel SET idf_funcao_id = NULL, idf_funcao = NULL WHERE idf_id = $fiel_id");
        return $ok ? null : 'Não foi possível salvar a função.';
    }

    $funcoes = funcoes_igreja_listar($conn);
    if (!isset($funcoes[$funcao_id])) {
        return 'Função inválida.';
    }
    if (!$funcoes[$funcao_id]['ativo']) {
        $res = mysqli_query($conn, "SELECT idf_funcao_id FROM id_fiel WHERE idf_id = $fiel_id LIMIT 1");
        $linha = $res ? mysqli_fetch_assoc($res) : null;
        if (!$linha || (int) $linha['idf_funcao_id'] !== $funcao_id) {
            return 'Essa função está desativada.';
        }
    }
    $ok = db_query($conn, "UPDATE id_fiel SET idf_funcao_id = ?, idf_funcao = ? WHERE idf_id = ?", array($funcao_id, $funcoes[$funcao_id]['nome'], $fiel_id));
    return $ok ? null : 'Não foi possível salvar a função.';
}

// ============================================================================
// FILIAIS (seção 76 do bancosupabase.md)
// ============================================================================
// Base para a igreja se dividir em filiais: Admin → Filiais
// (php/admin/admin_filiais.php) mantém a lista e a filial de cada fiel; o
// fiel escolhe a dele no cadastro e no perfil (php/fiel/includes/
// campo_filial.php). Hoje a lista provisória são os estados, até o cliente
// mandar as filiais de verdade. Três situações por fiel:
//   - filial escolhida: ID_FIEL.IDF_FILIAL_ID (e o nome copiado em
//     IDF_FILIAL, para as telas que já mostravam esse texto);
//   - "Nenhuma filial" (pessoa de fora): IDF_SEM_FILIAL = 1;
//   - não informada: nenhum dos dois (contas antigas, texto livre).
// Ainda não separa conteúdo nem admins por filial: isso depende de o
// cliente decidir entre um app para todas ou um site por filial (ver
// docs/para_o_cliente/pontos_para_alinhar.md, seção 1).

define('FILIAL_NENHUMA', 'nenhuma');
define('FILIAL_NENHUMA_ROTULO', 'Nenhuma filial');
define('FILIAL_NOME_MAX', 100);

function filiais_disponivel($conn) {
    return tabela_tem_coluna($conn, 'ID_FILIAL', 'IDL_ORDEM') && tabela_tem_coluna($conn, 'ID_FIEL', 'IDF_SEM_FILIAL');
}

// Todas as filiais (ativas primeiro, na ordem do admin), indexadas pelo id:
// [id => array('id', 'nome', 'codigo', 'estado', 'cidade', 'ordem', 'ativa')].
function filiais_listar($conn) {
    if (!filiais_disponivel($conn)) {
        return array();
    }
    $lista = array();
    $res = mysqli_query($conn, "SELECT idl_id, idl_nome, idl_codigo, idl_estado, idl_cidade, idl_ordem, idl_ativa FROM id_filial ORDER BY idl_ativa DESC, idl_ordem ASC, idl_nome ASC");
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $lista[(int) $linha['idl_id']] = array(
            'id' => (int) $linha['idl_id'],
            'nome' => (string) $linha['idl_nome'],
            'codigo' => (string) ($linha['idl_codigo'] ?? ''),
            'estado' => (string) ($linha['idl_estado'] ?? ''),
            'cidade' => (string) ($linha['idl_cidade'] ?? ''),
            'ordem' => (int) $linha['idl_ordem'],
            'ativa' => (int) $linha['idl_ativa'] === 1,
        );
    }
    return $lista;
}

// Valor da escolha (o que o <select> manda) de um fiel: o id da filial,
// FILIAL_NENHUMA ou '' (não informada). Função pura.
function fiel_filial_escolha_atual($filial_id, $sem_filial) {
    if ((int) $filial_id > 0) {
        return (string) (int) $filial_id;
    }
    return (int) $sem_filial === 1 ? FILIAL_NENHUMA : '';
}

// Confere a escolha de filial. $atual = escolha que o fiel já tem (uma
// filial desativada só vale se já for a dele). Devolve array('erro',
// 'filial_id' (int|null), 'sem_filial' (0|1), 'nome' (texto para
// IDF_FILIAL)). Função pura: $filiais vem de filiais_listar().
function fiel_filial_validar($escolha, array $filiais, $atual = '') {
    $escolha = trim((string) $escolha);
    if ($escolha === FILIAL_NENHUMA) {
        return array('erro' => null, 'filial_id' => null, 'sem_filial' => 1, 'nome' => FILIAL_NENHUMA_ROTULO);
    }
    $id = ctype_digit($escolha) ? (int) $escolha : 0;
    if ($id <= 0) {
        return array('erro' => 'Escolha a sua filial (ou "' . FILIAL_NENHUMA_ROTULO . '").', 'filial_id' => null, 'sem_filial' => 0, 'nome' => null);
    }
    if (!isset($filiais[$id]) || (!$filiais[$id]['ativa'] && (string) $id !== (string) $atual)) {
        return array('erro' => 'Essa filial não está disponível. Escolha outra.', 'filial_id' => null, 'sem_filial' => 0, 'nome' => null);
    }
    return array('erro' => null, 'filial_id' => $id, 'sem_filial' => 0, 'nome' => $filiais[$id]['nome']);
}

// Grava a filial de um fiel (perfil e admin). Devolve null ou o erro.
function fiel_filial_definir($conn, $fiel_id, $escolha) {
    $fiel_id = (int) $fiel_id;
    if ($fiel_id <= 0 || !filiais_disponivel($conn)) {
        return 'Não foi possível salvar a filial.';
    }
    $res = db_query($conn, "SELECT idf_filial_id, idf_sem_filial FROM id_fiel WHERE idf_id = ? LIMIT 1", array($fiel_id));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if (!$linha) {
        return 'Fiel não encontrado.';
    }
    $v = fiel_filial_validar($escolha, filiais_listar($conn), fiel_filial_escolha_atual($linha['idf_filial_id'], $linha['idf_sem_filial']));
    if ($v['erro']) {
        return $v['erro'];
    }
    $ok = db_query($conn, "UPDATE id_fiel SET idf_filial_id = ?, idf_sem_filial = ?, idf_filial = ? WHERE idf_id = ?", array($v['filial_id'], $v['sem_filial'], $v['nome'], $fiel_id));
    return $ok ? null : 'Não foi possível salvar a filial.';
}

// Texto da filial para mostrar: nome, "Nenhuma filial" ou '' (não
// informada; quem chama decide o texto). $texto_antigo = IDF_FILIAL
// digitado no cadastro antigo, mostrado enquanto não há escolha.
function fiel_filial_rotulo($nome_filial, $sem_filial, $texto_antigo = '') {
    if (trim((string) $nome_filial) !== '') {
        return (string) $nome_filial;
    }
    if ((int) $sem_filial === 1) {
        return FILIAL_NENHUMA_ROTULO;
    }
    return trim((string) $texto_antigo);
}

// ============================================================================
// PERFIL X% COMPLETO (T13)
// ============================================================================
// Obrigatórios pesam 2 e opcionais 1 (mockup do cliente). Só entram campos
// que o próprio fiel consegue preencher e cuja coluna existe no banco — a
// função na igreja fica de fora, porque é o admin quem define.
// Devolve array('percentual' => 0..100, 'faltando' => [chave => array('rotulo',
// 'obrigatorio')]), na ordem: obrigatórios primeiro.

function perfil_completude($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $resultado = array('percentual' => 0, 'faltando' => array());
    if ($fiel_id <= 0) {
        return $resultado;
    }

    $tem_membro = fiel_membro_disponivel($conn);
    $tem_perfil = fiel_perfil_disponivel($conn);
    $colunas = 'IDF_NOME, IDF_EMAIL, IDF_TELEFONE'
        . ($tem_membro ? ', IDF_MEMBRO' : '')
        . ($tem_perfil ? ', IDF_NASCIMENTO, IDF_ESTADO_CIVIL, IDF_TEM_FILHOS, IDF_CIDADE, IDF_UF' : '');
    $res = mysqli_query($conn, "SELECT $colunas FROM ID_FIEL WHERE IDF_ID = $fiel_id LIMIT 1");
    $f = $res ? mysqli_fetch_assoc($res) : null;
    if (!$f) {
        return $resultado;
    }
    $preenchido = function ($valor) {
        return $valor !== null && trim((string) $valor) !== '';
    };

    // chave => array(rótulo, obrigatório, preenchido)
    $campos = array(
        'nome' => array('Nome completo', true, $preenchido($f['IDF_NOME'])),
        'email' => array('E-mail', true, $preenchido($f['IDF_EMAIL'])),
        'whatsapp' => array('WhatsApp', true, (bool) fiel_whatsapp_normalizar($f['IDF_TELEFONE'])),
    );
    if ($tem_membro) {
        $campos['membro'] = array('Vínculo com a igreja', true, $f['IDF_MEMBRO'] !== null);
    }
    if ($tem_perfil) {
        $campos['nascimento'] = array('Data de nascimento', false, $preenchido($f['IDF_NASCIMENTO']));
    }
    if (fiel_foto_disponivel($conn)) {
        $campos['foto'] = array('Foto de perfil', false, fiel_foto_versao($conn, $fiel_id) !== null);
    }
    if ($tem_perfil) {
        $campos['estado_civil'] = array('Estado civil', false, $preenchido($f['IDF_ESTADO_CIVIL']));
        $campos['tem_filhos'] = array('Tem filhos?', false, $f['IDF_TEM_FILHOS'] !== null);
        $campos['cidade'] = array('Cidade / estado', false, $preenchido($f['IDF_CIDADE']) && $preenchido($f['IDF_UF']));
    }
    if (preferencias_conteudo_disponivel($conn)) {
        $campos['temas'] = array('Temas de interesse', false, !empty(fiel_temas_ids($conn, $fiel_id)));
    }

    $total = 0;
    $feito = 0;
    foreach ($campos as $chave => $campo) {
        $peso = $campo[1] ? 2 : 1;
        $total += $peso;
        if ($campo[2]) {
            $feito += $peso;
        } else {
            $resultado['faltando'][$chave] = array('rotulo' => $campo[0], 'obrigatorio' => $campo[1]);
        }
    }
    // Para baixo: 100% só com tudo preenchido.
    $resultado['percentual'] = $total > 0 ? (int) floor($feito * 100 / $total) : 100;
    return $resultado;
}

// ============================================================================
// PREFERÊNCIAS DE CONTEÚDO (T23, seção 56 do bancosupabase.md)
// ============================================================================
// Temas de interesse (as categorias dos cursos, id_fiel_tema), tempo
// disponível por dia e horário do lembrete diário (id_preferencia). Os temas
// e o tempo ajustam "Recomendados para Você" e a ordem das filas por tema da
// home; o horário dispara o lembrete push (lembretes_diarios_enviar(), via
// lembrete_diario.php chamado por um agendador). Quando houver devocional
// (T33/T38), o lembrete passa a levar o devocional do dia.

if (!defined('PREFERENCIAS_TEMAS_MAX')) {
    define('PREFERENCIAS_TEMAS_MAX', 8);
}

function preferencias_conteudo_disponivel($conn) {
    return tabela_tem_coluna($conn, 'id_preferencia', 'idpref_tempo_diario')
        && tabela_tem_coluna($conn, 'id_preferencia', 'idpref_lembrete_hora')
        && tabela_tem_coluna($conn, 'id_preferencia', 'idpref_lembrete_enviado_em')
        && tabela_tem_coluna($conn, 'id_fiel_tema', 'idcat_id');
}

// Minutos por dia => rótulo.
function preferencias_tempos() {
    return array(10 => 'Até 10 min', 20 => 'Cerca de 20 min', 30 => 'Cerca de 30 min', 60 => '1 hora ou mais');
}

// Horários do lembrete: das 5h às 23h, de meia em meia hora ('HH:MM').
function preferencias_horarios() {
    $horarios = array();
    for ($minutos = 5 * 60; $minutos <= 23 * 60; $minutos += 30) {
        $horarios[] = sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }
    return $horarios;
}

// Temas que o fiel pode escolher: categorias ativas [id => nome].
function preferencias_temas_opcoes($conn) {
    $temas = array();
    $res = mysqli_query($conn, "SELECT IDCAT_ID, IDCAT_NOME FROM ID_CATEGORIA WHERE IDCAT_ATIVO = 1 ORDER BY IDCAT_NOME ASC");
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $temas[(int) $linha['IDCAT_ID']] = (string) $linha['IDCAT_NOME'];
    }
    return $temas;
}

// Ids das categorias escolhidas como tema (só as ativas).
// Guardado por requisição: a Home chamava isto 4 vezes (recomendados, filas
// por tema, completude do perfil...). preferencias_conteudo_salvar() limpa.
function fiel_temas_ids($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    if ($fiel_id <= 0 || !preferencias_conteudo_disponivel($conn)) {
        return array();
    }
    if (isset($GLOBALS['site_cache_temas'][$fiel_id])) {
        return $GLOBALS['site_cache_temas'][$fiel_id];
    }
    $ids = array();
    $res = mysqli_query($conn, "
        SELECT t.idcat_id FROM id_fiel_tema t
        JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = t.idcat_id AND cat.IDCAT_ATIVO = 1
        WHERE t.idf_id = $fiel_id
    ");
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $ids[] = (int) $linha['idcat_id'];
    }
    if ($res) {
        $GLOBALS['site_cache_temas'][$fiel_id] = $ids;
    }
    return $ids;
}

// array('temas' => [ids], 'tempo' => minutos|null, 'lembrete' => 'HH:MM'|null)
function preferencias_conteudo_ler($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $prefs = array('temas' => array(), 'tempo' => null, 'lembrete' => null);
    if ($fiel_id <= 0 || !preferencias_conteudo_disponivel($conn)) {
        return $prefs;
    }
    $prefs['temas'] = fiel_temas_ids($conn, $fiel_id);
    $res = mysqli_query($conn, "SELECT idpref_tempo_diario, to_char(idpref_lembrete_hora, 'HH24:MI') AS lembrete FROM id_preferencia WHERE idf_id = $fiel_id");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if ($linha) {
        $prefs['tempo'] = $linha['idpref_tempo_diario'] !== null ? (int) $linha['idpref_tempo_diario'] : null;
        $prefs['lembrete'] = $linha['lembrete'] !== null ? (string) $linha['lembrete'] : null;
    }
    return $prefs;
}

// Confere o que veio do formulário. Devolve array('erro' => string|null,
// 'temas' => [ids], 'tempo' => int|null, 'lembrete' => 'HH:MM'|null).
function preferencias_conteudo_validar($temas, $tempo, $lembrete, array $temas_validos) {
    $ids = array();
    foreach ((array) $temas as $id) {
        $id = (int) $id;
        if (isset($temas_validos[$id])) {
            $ids[$id] = $id;
        }
    }
    $ids = array_values($ids);
    if (count($ids) > PREFERENCIAS_TEMAS_MAX) {
        return array('erro' => 'Escolha no máximo ' . PREFERENCIAS_TEMAS_MAX . ' temas.', 'temas' => $ids, 'tempo' => null, 'lembrete' => null);
    }
    $tempo = ($tempo === '' || $tempo === null) ? null : (int) $tempo;
    if ($tempo !== null && !isset(preferencias_tempos()[$tempo])) {
        return array('erro' => 'Escolha um tempo por dia da lista.', 'temas' => $ids, 'tempo' => null, 'lembrete' => null);
    }
    $lembrete = trim((string) $lembrete);
    if ($lembrete === '') {
        $lembrete = null;
    } elseif (!in_array($lembrete, preferencias_horarios(), true)) {
        return array('erro' => 'Escolha um horário da lista.', 'temas' => $ids, 'tempo' => $tempo, 'lembrete' => null);
    }
    return array('erro' => null, 'temas' => $ids, 'tempo' => $tempo, 'lembrete' => $lembrete);
}

// Grava tudo de uma vez (temas trocados inteiros). Mudar o horário libera
// o lembrete de hoje de novo, se o novo horário ainda não passou.
function preferencias_conteudo_salvar($conn, $fiel_id, array $prefs) {
    $fiel_id = (int) $fiel_id;
    if ($fiel_id <= 0 || !preferencias_conteudo_disponivel($conn)) {
        return false;
    }
    unset($GLOBALS['site_cache_temas'][$fiel_id]); // fiel_temas_ids() relê depois de salvar
    unset($_SESSION['meta_alvo']); // meta_diaria_somar() relê a meta nova
    $tempo = $prefs['tempo'] === null ? null : (int) $prefs['tempo'];

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $conn->beginTransaction();
    $ok = db_query($conn, "
        INSERT INTO id_preferencia (idf_id, idpref_notificacoes_email, idpref_notificacoes_push, idpref_tempo_diario, idpref_lembrete_hora)
        VALUES (?, 1, 1, ?, ?)
        ON CONFLICT (idf_id) DO UPDATE SET
            idpref_tempo_diario = EXCLUDED.idpref_tempo_diario,
            idpref_lembrete_enviado_em = CASE
                WHEN id_preferencia.idpref_lembrete_hora IS DISTINCT FROM EXCLUDED.idpref_lembrete_hora THEN NULL
                ELSE id_preferencia.idpref_lembrete_enviado_em END,
            idpref_lembrete_hora = EXCLUDED.idpref_lembrete_hora,
            idpref_atualizado_em = CURRENT_TIMESTAMP
    ", array($fiel_id, $tempo, $prefs['lembrete'])) && mysqli_query($conn, "DELETE FROM id_fiel_tema WHERE idf_id = $fiel_id");
    if ($ok && !empty($prefs['temas'])) {
        $valores = array();
        foreach ($prefs['temas'] as $id) {
            $valores[] = "($fiel_id, " . (int) $id . ")";
        }
        $ok = mysqli_query($conn, "INSERT INTO id_fiel_tema (idf_id, idcat_id) VALUES " . implode(', ', $valores));
    }
    if ($ok) {
        $ok = db_commit($conn);
    } else {
        $conn->rollBack();
    }
    return (bool) $ok;
}

// Texto curto das preferências para o cartão do perfil.
function preferencias_tempo_texto($tempo) {
    return $tempo !== null ? (preferencias_tempos()[$tempo] ?? '') . ' por dia' : '';
}

// ----------------------------------------------------------------------------
// Lembrete diário (push). Chamado por lembrete_diario.php a cada ~15 min.
// Manda para quem escolheu horário, tem push ligado, conta aprovada e ainda
// não recebeu hoje, quando o horário (Brasília) chegou — até 2 h depois (se
// o agendador ficou fora do ar, não manda lembrete atrasado de madrugada).
// "Marca antes, manda depois": duas chamadas ao mesmo tempo não duplicam.
// ----------------------------------------------------------------------------
if (!defined('LEMBRETE_CRON_CHAVE')) {
    define('LEMBRETE_CRON_CHAVE', site_env('LEMBRETE_CRON_CHAVE'));
}

function lembretes_diarios_enviar($conn, $limite = 300) {
    $stats = array('pendentes' => 0, 'enviados' => 0, 'falhas' => 0);
    if (!preferencias_conteudo_disponivel($conn)) {
        return $stats;
    }
    $limite = (int) $limite;
    $agora = "(CURRENT_TIMESTAMP AT TIME ZONE 'America/Sao_Paulo')";
    // Hora do lembrete comparada como data + hora (não só a hora): com a
    // comparação só de hora, quem marcou entre 00:00 e 01:59 recebia ~2 h
    // atrasado (a janela de 2 h "dava a volta" na meia-noite).
    $res = mysqli_query($conn, "
        SELECT p.idf_id, p.idpref_tempo_diario, f.IDF_SENHA_HASH
        FROM id_preferencia p
        JOIN ID_FIEL f ON f.IDF_ID = p.idf_id
        WHERE p.idpref_lembrete_hora IS NOT NULL
          AND COALESCE(p.idpref_notificacoes_push, 1) = 1
          AND f.IDF_STATUS = 'aprovado' AND f.IDF_ATIVO = 1
          AND (p.idpref_lembrete_enviado_em IS NULL OR p.idpref_lembrete_enviado_em < ($agora)::date)
          AND (($agora)::date + p.idpref_lembrete_hora) <= ($agora)
          AND (($agora)::date + p.idpref_lembrete_hora) > ($agora) - INTERVAL '2 hours'
        ORDER BY p.idpref_lembrete_hora
        LIMIT $limite
    ");
    if (!$res) {
        // Antes saía "0 pendentes, ok": o agendador nunca sabia da falha.
        $stats['erro'] = 'consulta';
        return $stats;
    }
    $fieis = array();
    while ($linha = mysqli_fetch_assoc($res)) {
        $fieis[] = $linha;
    }
    $stats['pendentes'] = count($fieis);

    // Agrupa quem recebe a mesma mensagem (o devocional do dia é igual para
    // todos): uma chamada à OneSignal por grupo, em vez de até 300 seguidas
    // de até 8 s cada (o agendador desistia antes de acabar).
    $grupos = array();
    foreach ($fieis as $linha) {
        $fiel_id = (int) $linha['idf_id'];
        mysqli_query($conn, "
            UPDATE id_preferencia SET idpref_lembrete_enviado_em = ($agora)::date
            WHERE idf_id = $fiel_id
              AND (idpref_lembrete_enviado_em IS NULL OR idpref_lembrete_enviado_em < ($agora)::date)
        ");
        if (mysqli_affected_rows($conn) < 1) {
            continue; // outra chamada já pegou
        }
        $msg = lembrete_diario_mensagem($conn, $fiel_id, $linha['idpref_tempo_diario'] !== null ? (int) $linha['idpref_tempo_diario'] : null);
        $chave = md5($msg['titulo'] . "\n" . $msg['texto'] . "\n" . $msg['link']);
        if (!isset($grupos[$chave])) {
            $grupos[$chave] = array('msg' => $msg, 'aliases' => array());
        }
        $grupos[$chave]['aliases'][] = onesignal_external_id_com_marca($fiel_id, onesignal_marca_do_hash($linha['IDF_SENHA_HASH'] ?? ''));
    }
    foreach ($grupos as $grupo) {
        foreach (array_chunk($grupo['aliases'], 2000) as $bloco) {
            $resultado = 'falha';
            onesignal_enviar_push_aliases($bloco, $grupo['msg']['titulo'], $grupo['msg']['texto'], $grupo['msg']['link'], $resultado, count($bloco) . ' lembretes');
            if ($resultado === 'falha') {
                $stats['falhas'] += count($bloco);
            } elseif ($resultado === 'sem_destino') {
                // A OneSignal aceitou, mas ninguém do grupo tem aparelho inscrito.
                $stats['sem_aparelho'] = ($stats['sem_aparelho'] ?? 0) + count($bloco);
            } else {
                $stats['enviados'] += count($bloco);
            }
        }
    }
    return $stats;
}

// Conteúdo do lembrete: o curso em andamento mais recente; sem nenhum,
// um convite para as recomendações (com o tempo que a pessoa escolheu).
function lembrete_diario_mensagem($conn, $fiel_id, $tempo) {
    $fiel_id = (int) $fiel_id;
    // Com devocional publicado hoje (T33), o lembrete leva o devocional.
    static $devocional_hoje = false;
    if ($devocional_hoje === false) {
        $devocional_hoje = devocionais_disponivel($conn) ? devocional_do_dia($conn) : null;
        if ($devocional_hoje && $devocional_hoje['data'] !== site_hoje()) {
            $devocional_hoje = null;
        }
    }
    if ($devocional_hoje) {
        return array(
            'titulo' => 'Devocional do dia 🙏',
            'texto' => $devocional_hoje['titulo'] . ' — ' . $devocional_hoje['referencia'],
            'link' => 'devocional.php',
        );
    }
    $res = mysqli_query($conn, "
        SELECT c.IDC_ID, c.IDC_TITULO
        FROM ID_MATRICULA m
        JOIN ID_CURSO c ON c.IDC_ID = m.IDC_ID
        WHERE m.IDF_ID = $fiel_id AND m.IDMATR_STATUS IN ('ATIVA', 'PAUSADA')
          AND m.IDMATR_PERCENTUAL > 0 AND m.IDMATR_PERCENTUAL < 100
          AND c.IDC_STATUS = 'PUBLICADO'
        ORDER BY m.IDMATR_INICIO_EM DESC
        LIMIT 1
    ");
    $curso = $res ? mysqli_fetch_assoc($res) : null;
    if ($curso) {
        return array(
            'titulo' => 'Seu momento com Deus 🙏',
            'texto' => 'Continue de onde parou: ' . $curso['IDC_TITULO'],
            'link' => 'curso.php?id=' . (int) $curso['IDC_ID'],
        );
    }
    $texto = $tempo !== null && $tempo <= 30
        ? 'Separe ' . $tempo . ' minutos hoje: escolhemos aulas para você.'
        : 'Escolhemos aulas para você hoje. Vamos começar?';
    return array('titulo' => 'Seu momento com Deus 🙏', 'texto' => $texto, 'link' => 'dashboard.php');
}

// ============================================================================
// MURAL DE TESTEMUNHOS (T26, seção 57 do bancosupabase.md)
// ============================================================================
// O fiel envia (php/fiel/testemunhos.php); o admin aprova ou recusa
// (php/admin/admin_testemunhos.php) e o autor é avisado. Publicados recebem
// o voto "Edificante" (um por fiel, não no próprio) e os mais votados vão
// para a home. Status: pendente -> aprovado | recusado; "removido" = tirado
// do ar pelo autor ou pelo admin (a linha fica para auditoria).

if (!defined('TESTEMUNHO_TITULO_MAX')) {
    define('TESTEMUNHO_TITULO_MAX', 80);
    define('TESTEMUNHO_TEXTO_MIN', 30);
    define('TESTEMUNHO_TEXTO_MAX', 2000);
    define('TESTEMUNHOS_PENDENTES_MAX', 3);
    define('TESTEMUNHOS_POR_PAGINA', 12);
}

function testemunhos_disponivel($conn) {
    return tabela_tem_coluna($conn, 'id_testemunho', 'idts_status')
        && tabela_tem_coluna($conn, 'id_testemunho_voto', 'idts_id');
}

// Título e texto limpos e o erro (ou null).
function testemunho_validar($titulo, $texto) {
    $titulo = trim(preg_replace('/\s+/u', ' ', (string) $titulo));
    // No texto, mantém as quebras de linha (no máximo uma linha em branco).
    $texto = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", (string) $texto)));
    $erro = null;
    if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > TESTEMUNHO_TITULO_MAX) {
        $erro = 'Dê um título de 3 a ' . TESTEMUNHO_TITULO_MAX . ' caracteres.';
    } elseif (mb_strlen($texto) < TESTEMUNHO_TEXTO_MIN) {
        $erro = 'Conte um pouco mais: o testemunho precisa de pelo menos ' . TESTEMUNHO_TEXTO_MIN . ' caracteres.';
    } elseif (mb_strlen($texto) > TESTEMUNHO_TEXTO_MAX) {
        $erro = 'O testemunho pode ter no máximo ' . TESTEMUNHO_TEXTO_MAX . ' caracteres.';
    }
    return array('erro' => $erro, 'titulo' => $titulo, 'texto' => $texto);
}

// Envia para moderação. Devolve null se deu certo, senão a mensagem de erro.
function testemunho_enviar($conn, $fiel_id, $titulo, $texto, $anonimo) {
    $fiel_id = (int) $fiel_id;
    $validado = testemunho_validar($titulo, $texto);
    if ($validado['erro']) {
        return $validado['erro'];
    }
    $res = mysqli_query($conn, "
        SELECT f.IDF_STATUS AS status, f.IDF_ATIVO AS ativo,
            EXISTS(SELECT 1 FROM id_fiel_bloqueio_chat b WHERE b.idf_id = f.IDF_ID AND (b.idbc_expira_em IS NULL OR b.idbc_expira_em > NOW())) AS bloqueado,
            (SELECT COUNT(*) FROM id_testemunho t WHERE t.idf_id = f.IDF_ID AND t.idts_status = 'pendente') AS pendentes
        FROM ID_FIEL f WHERE f.IDF_ID = $fiel_id
    ");
    $f = $res ? mysqli_fetch_assoc($res) : null;
    if (!$f || $f['status'] !== 'aprovado' || (int) $f['ativo'] !== 1) {
        return 'Sua conta não está aprovada para enviar testemunhos.';
    }
    if (!empty($f['bloqueado'])) {
        return 'Você está impedido de publicar no momento.';
    }
    if ((int) $f['pendentes'] >= TESTEMUNHOS_PENDENTES_MAX) {
        return 'Você já tem ' . TESTEMUNHOS_PENDENTES_MAX . ' testemunhos esperando aprovação. Aguarde a equipe revisar.';
    }
    $anonimo = $anonimo ? 1 : 0;
    $ok = db_query($conn, "
        INSERT INTO id_testemunho (idf_id, idts_titulo, idts_texto, idts_anonimo)
        VALUES (?, ?, ?, ?)
    ", array($fiel_id, $validado['titulo'], $validado['texto'], $anonimo));
    return $ok ? null : 'Não foi possível enviar. Tente novamente.';
}

// Colunas comuns (alias t = testemunho, f = autor): votos e o voto de quem vê.
function testemunhos_select_sql($fiel_id) {
    $fiel_id = (int) $fiel_id;
    return "
        SELECT t.idts_id, t.idf_id, t.idts_titulo, t.idts_texto, t.idts_anonimo, t.idts_status,
            t.idts_motivo, f.IDF_NOME,
            EXTRACT(EPOCH FROM COALESCE(t.idts_moderado_em, t.idts_criado_em))::bigint AS publicado_ts,
            EXTRACT(EPOCH FROM t.idts_criado_em)::bigint AS criado_ts,
            (SELECT COUNT(*) FROM id_testemunho_voto v WHERE v.idts_id = t.idts_id) AS votos,
            EXISTS(SELECT 1 FROM id_testemunho_voto v WHERE v.idts_id = t.idts_id AND v.idf_id = $fiel_id) AS meu_voto
        FROM id_testemunho t
        JOIN ID_FIEL f ON f.IDF_ID = t.idf_id";
}

function testemunho_formatar($linha, $fiel_id, $eh_admin) {
    $anonimo = (int) $linha['idts_anonimo'] === 1;
    $meu = (int) $linha['idf_id'] === (int) $fiel_id;
    // Anônimo: só o admin (e o próprio autor) sabem quem escreveu.
    if ($anonimo && !$eh_admin && !$meu) {
        $nome = 'Anônimo';
    } else {
        $nome = $eh_admin ? (string) $linha['IDF_NOME'] : site_nome_curto($linha['IDF_NOME']);
    }
    return array(
        'id' => (int) $linha['idts_id'],
        'titulo' => (string) $linha['idts_titulo'],
        'texto' => (string) $linha['idts_texto'],
        'nome' => $nome,
        'anonimo' => $anonimo,
        'meu' => $meu,
        'status' => (string) $linha['idts_status'],
        'motivo' => (string) ($linha['idts_motivo'] ?? ''),
        'publicado_ts' => (int) $linha['publicado_ts'],
        'criado_ts' => (int) $linha['criado_ts'],
        'votos' => (int) $linha['votos'],
        'meu_voto' => !empty($linha['meu_voto']),
    );
}

// Publicados. $ordem 'votados' (mais votos; empate, o mais novo) ou
// 'recentes'. $dias > 0: só os publicados nesse período.
function testemunhos_listar($conn, $ordem = 'votados', $limite = TESTEMUNHOS_POR_PAGINA, $pular = 0, $dias = 0) {
    $fiel_id = auth_resolve_fiel_id();
    $eh_admin = ($_SESSION['Usuario_tipo'] ?? '') === 'admin';
    $limite = (int) $limite;
    $pular = max(0, (int) $pular);
    $dias = (int) $dias;
    $filtro_dias = $dias > 0 ? "AND COALESCE(t.idts_moderado_em, t.idts_criado_em) > NOW() - INTERVAL '$dias days'" : '';
    $ordenar = $ordem === 'recentes'
        ? 'COALESCE(t.idts_moderado_em, t.idts_criado_em) DESC, t.idts_id DESC'
        : 'votos DESC, COALESCE(t.idts_moderado_em, t.idts_criado_em) DESC, t.idts_id DESC';
    $res = mysqli_query($conn, testemunhos_select_sql($fiel_id) . "
        WHERE t.idts_status = 'aprovado' $filtro_dias
        ORDER BY $ordenar
        LIMIT $limite OFFSET $pular
    ");
    $lista = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $lista[] = testemunho_formatar($linha, $fiel_id, $eh_admin);
    }
    return $lista;
}

function testemunhos_contar_publicados($conn) {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM id_testemunho WHERE idts_status = 'aprovado'");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? (int) $linha['total'] : 0;
}

// Bloco da home: os mais votados dos últimos 90 dias; se não houver
// nenhum no período, os mais votados de sempre.
function testemunhos_destaque_home($conn, $limite = 3) {
    $lista = testemunhos_listar($conn, 'votados', $limite, 0, 90);
    return $lista ?: testemunhos_listar($conn, 'votados', $limite);
}

// Os do próprio fiel, de qualquer status (menos os que ele mesmo tirou).
function testemunhos_do_fiel($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $res = mysqli_query($conn, testemunhos_select_sql($fiel_id) . "
        WHERE t.idf_id = $fiel_id AND t.idts_status <> 'removido'
        ORDER BY t.idts_criado_em DESC
        LIMIT 50
    ");
    $lista = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $lista[] = testemunho_formatar($linha, $fiel_id, false);
    }
    return $lista;
}

// Voto "Edificante": liga ou desliga. Só em publicado e não no próprio.
// Devolve array('ok', 'erro', 'votos', 'meu_voto').
function testemunho_votar($conn, $testemunho_id, $fiel_id, $votar) {
    $testemunho_id = (int) $testemunho_id;
    $fiel_id = (int) $fiel_id;
    $res = mysqli_query($conn, "SELECT idf_id FROM id_testemunho WHERE idts_id = $testemunho_id AND idts_status = 'aprovado'");
    $alvo = $res ? mysqli_fetch_assoc($res) : null;
    if (!$alvo) {
        return array('ok' => false, 'erro' => 'Testemunho não encontrado.');
    }
    if ((int) $alvo['idf_id'] === $fiel_id) {
        return array('ok' => false, 'erro' => 'Você não pode votar no próprio testemunho.');
    }
    $ok = $votar
        ? mysqli_query($conn, "INSERT INTO id_testemunho_voto (idts_id, idf_id) VALUES ($testemunho_id, $fiel_id) ON CONFLICT (idts_id, idf_id) DO NOTHING")
        : mysqli_query($conn, "DELETE FROM id_testemunho_voto WHERE idts_id = $testemunho_id AND idf_id = $fiel_id");
    if (!$ok) {
        return array('ok' => false, 'erro' => 'Não foi possível registrar o voto.');
    }
    $res = mysqli_query($conn, "
        SELECT COUNT(*) AS votos, BOOL_OR(idf_id = $fiel_id) AS meu
        FROM id_testemunho_voto WHERE idts_id = $testemunho_id
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return array('ok' => true, 'erro' => null, 'votos' => (int) ($linha['votos'] ?? 0), 'meu_voto' => !empty($linha['meu']));
}

// O autor tira o próprio testemunho (de qualquer status).
function testemunho_remover_proprio($conn, $testemunho_id, $fiel_id) {
    $testemunho_id = (int) $testemunho_id;
    $fiel_id = (int) $fiel_id;
    mysqli_query($conn, "
        UPDATE id_testemunho SET idts_status = 'removido', idts_moderado_por = $fiel_id, idts_moderado_em = CURRENT_TIMESTAMP
        WHERE idts_id = $testemunho_id AND idf_id = $fiel_id AND idts_status <> 'removido'
    ");
    return mysqli_affected_rows($conn) > 0;
}

// Admin: lista por status, mais antigos primeiro nos pendentes (fila).
function testemunhos_admin_listar($conn, $status) {
    $status = in_array($status, array('pendente', 'aprovado', 'recusado', 'removido'), true) ? $status : 'pendente';
    $ordenar = $status === 'pendente' ? 't.idts_criado_em ASC' : 'COALESCE(t.idts_moderado_em, t.idts_criado_em) DESC';
    $fiel_id = auth_resolve_fiel_id();
    $res = mysqli_query($conn, testemunhos_select_sql($fiel_id) . "
        WHERE t.idts_status = '$status'
        ORDER BY $ordenar
        LIMIT 200
    ");
    $lista = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $item = testemunho_formatar($linha, $fiel_id, true);
        $item['autor_id'] = (int) $linha['idf_id'];
        $lista[] = $item;
    }
    return $lista;
}

function testemunhos_admin_contar($conn) {
    $contagem = array('pendente' => 0, 'aprovado' => 0, 'recusado' => 0, 'removido' => 0);
    $res = mysqli_query($conn, "SELECT idts_status, COUNT(*) AS total FROM id_testemunho GROUP BY idts_status");
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        if (isset($contagem[$linha['idts_status']])) {
            $contagem[$linha['idts_status']] = (int) $linha['total'];
        }
    }
    return $contagem;
}

// Aprovar, recusar (com motivo) ou tirar do ar. Avisa o autor (notificação
// no app + push) ao aprovar e ao recusar. Devolve null ou a mensagem de erro.
function testemunho_moderar($conn, $testemunho_id, $admin_fiel_id, $acao, $motivo = '') {
    $testemunho_id = (int) $testemunho_id;
    $admin_fiel_id = (int) $admin_fiel_id;
    $de_para = array('aprovar' => array("'pendente', 'recusado'", 'aprovado'), 'recusar' => array("'pendente'", 'recusado'), 'remover' => array("'aprovado'", 'removido'));
    if (!isset($de_para[$acao])) {
        return 'Ação inválida.';
    }
    $motivo = trim(preg_replace('/\s+/u', ' ', (string) $motivo));
    if ($acao === 'recusar' && $motivo === '') {
        return 'Escreva o motivo da recusa — o autor vai ver.';
    }
    $motivo = mb_substr($motivo, 0, 255);
    list($de, $para) = $de_para[$acao];
    // $para e $de são constantes fixas do $de_para acima.
    $res = db_query($conn, "
        UPDATE id_testemunho
        SET idts_status = '$para', idts_motivo = ?, idts_moderado_por = ?, idts_moderado_em = CURRENT_TIMESTAMP
        WHERE idts_id = ? AND idts_status IN ($de)
        RETURNING idf_id, idts_titulo
    ", array($acao === 'recusar' ? $motivo : null, $admin_fiel_id, $testemunho_id));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if (!$linha) {
        return 'Testemunho não encontrado ou já moderado por outra pessoa.';
    }
    if ($acao !== 'remover') {
        $autor_id = (int) $linha['idf_id'];
        $titulo_curto = mb_strimwidth((string) $linha['idts_titulo'], 0, 60, '…');
        $titulo_notif = $acao === 'aprovar' ? 'Seu testemunho foi publicado! 🙌' : 'Seu testemunho não foi publicado';
        $mensagem_notif = $acao === 'aprovar'
            ? '"' . $titulo_curto . '" já está no mural de testemunhos.'
            : '"' . $titulo_curto . '": ' . $motivo;
        $link = 'testemunhos.php' . ($acao === 'aprovar' ? '#t-' . $testemunho_id : '#meus');
        db_query($conn, "
            INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK)
            VALUES (?, ?, ?, 'testemunho', ?)
        ", array($autor_id, $titulo_notif, $mensagem_notif, $link));
        notificacoes_push_enviar_pendentes($conn, $autor_id);
    }
    return null;
}

// ============================================================================
// MINHAS NOTAS NA AULA (T27, seção 58 do bancosupabase.md)
// ============================================================================
// Anotações pessoais do fiel em cada aula (aba "Minhas notas" ao lado de
// "Comunidade" em php/fiel/assistir_video.php), com o minuto da aula
// opcional. Só o próprio autor vê — nem o admin tem tela para elas.

if (!defined('NOTA_AULA_TEXTO_MAX')) {
    define('NOTA_AULA_TEXTO_MAX', 2000);
    define('NOTAS_AULA_MAX', 100);
}

function notas_aula_disponivel($conn) {
    return tabela_tem_coluna($conn, 'id_aula_nota', 'idan_momento');
}

// Texto limpo (mantém as quebras de linha, no máximo uma em branco) e o
// erro (ou null). Momento: segundos inteiros ou null (sem minuto).
function nota_aula_validar($texto, $momento) {
    $texto = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", (string) $texto)));
    $erro = null;
    if ($texto === '') {
        $erro = 'Escreva a nota antes de salvar.';
    } elseif (mb_strlen($texto) > NOTA_AULA_TEXTO_MAX) {
        $erro = 'A nota pode ter no máximo ' . NOTA_AULA_TEXTO_MAX . ' caracteres.';
    }
    $momento = ($momento === null || $momento === '') ? null : max(0, min(86400, (int) $momento));
    return array('erro' => $erro, 'texto' => $texto, 'momento' => $momento);
}

function nota_aula_formatar($linha) {
    return array(
        'id' => (int) $linha['idan_id'],
        'texto' => (string) $linha['idan_texto'],
        'momento' => $linha['idan_momento'] === null ? null : (int) $linha['idan_momento'],
        'criado_ts' => (int) $linha['criado_ts'],
        'editado' => (int) $linha['atualizado_ts'] > (int) $linha['criado_ts'] + 5,
    );
}

function notas_aula_select_sql() {
    return "
        SELECT idan_id, idan_texto, idan_momento,
            EXTRACT(EPOCH FROM idan_criado_em)::bigint AS criado_ts,
            EXTRACT(EPOCH FROM idan_atualizado_em)::bigint AS atualizado_ts
        FROM id_aula_nota";
}

// Notas do fiel na aula, na ordem do vídeo; as sem minuto vão no fim, na
// ordem em que foram escritas.
function notas_aula_listar($conn, $fiel_id, $conteudo_id) {
    $fiel_id = (int) $fiel_id;
    $conteudo_id = (int) $conteudo_id;
    $res = mysqli_query($conn, notas_aula_select_sql() . "
        WHERE idf_id = $fiel_id AND idct_id = $conteudo_id
        ORDER BY idan_momento ASC NULLS LAST, idan_id ASC
    ");
    $lista = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $lista[] = nota_aula_formatar($linha);
    }
    return $lista;
}

// Devolve array('ok' => true, 'nota' => ...) ou array('ok' => false, 'erro' => ...).
function nota_aula_criar($conn, $fiel_id, $conteudo_id, $texto, $momento) {
    $fiel_id = (int) $fiel_id;
    $conteudo_id = (int) $conteudo_id;
    $validado = nota_aula_validar($texto, $momento);
    if ($validado['erro']) {
        return array('ok' => false, 'erro' => $validado['erro']);
    }
    $res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM id_aula_nota WHERE idf_id = $fiel_id AND idct_id = $conteudo_id");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if ($linha && (int) $linha['total'] >= NOTAS_AULA_MAX) {
        return array('ok' => false, 'erro' => 'Você chegou ao limite de ' . NOTAS_AULA_MAX . ' notas nesta aula. Junte ou apague algumas.');
    }
    $res = db_query($conn, "
        INSERT INTO id_aula_nota (idf_id, idct_id, idan_texto, idan_momento)
        VALUES (?, ?, ?, ?)
        RETURNING idan_id, idan_texto, idan_momento,
            EXTRACT(EPOCH FROM idan_criado_em)::bigint AS criado_ts,
            EXTRACT(EPOCH FROM idan_atualizado_em)::bigint AS atualizado_ts
    ", array($fiel_id, $conteudo_id, $validado['texto'], $validado['momento']));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha
        ? array('ok' => true, 'nota' => nota_aula_formatar($linha))
        : array('ok' => false, 'erro' => 'Não foi possível salvar a nota. Tente novamente.');
}

// Edita o texto e o minuto. Só o autor (o WHERE garante).
function nota_aula_editar($conn, $fiel_id, $nota_id, $texto, $momento) {
    $fiel_id = (int) $fiel_id;
    $nota_id = (int) $nota_id;
    $validado = nota_aula_validar($texto, $momento);
    if ($validado['erro']) {
        return array('ok' => false, 'erro' => $validado['erro']);
    }
    $res = db_query($conn, "
        UPDATE id_aula_nota
        SET idan_texto = ?, idan_momento = ?, idan_atualizado_em = CURRENT_TIMESTAMP
        WHERE idan_id = ? AND idf_id = ?
        RETURNING idan_id, idan_texto, idan_momento,
            EXTRACT(EPOCH FROM idan_criado_em)::bigint AS criado_ts,
            EXTRACT(EPOCH FROM idan_atualizado_em)::bigint AS atualizado_ts
    ", array($validado['texto'], $validado['momento'], $nota_id, $fiel_id));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha
        ? array('ok' => true, 'nota' => nota_aula_formatar($linha))
        : array('ok' => false, 'erro' => 'Nota não encontrada.');
}

function nota_aula_excluir($conn, $fiel_id, $nota_id) {
    $fiel_id = (int) $fiel_id;
    $nota_id = (int) $nota_id;
    mysqli_query($conn, "DELETE FROM id_aula_nota WHERE idan_id = $nota_id AND idf_id = $fiel_id");
    return mysqli_affected_rows($conn) > 0;
}

// ============================================================================
// PEDIDOS DE ORAÇÃO COM "AMÉM" (T28, seção 59 do bancosupabase.md)
// ============================================================================
// Mural único de pedidos de oração da igreja, na aba "Orações" ao lado de
// "Comunidade" em php/fiel/assistir_video.php (o pedido não fica preso à
// aula; idct_id só guarda de onde veio, para o link das notificações).
// Publica na hora (sem moderação prévia); o admin e o autor podem tirar do
// ar. "Amém" = intercessão (um por fiel, não no próprio pedido) — o autor é
// avisado aos poucos (1, 3, 10, 25, 50, 100 e a cada 100), não a cada amém.
// O autor marca como atendida (com o relato opcional) e quem orou é avisado.
// Status: aberto -> atendido; removido = tirado do ar (a linha fica).

if (!defined('ORACAO_TEXTO_MAX')) {
    define('ORACAO_TEXTO_MIN', 10);
    define('ORACAO_TEXTO_MAX', 1000);
    define('ORACAO_RESPOSTA_MAX', 500);
    define('ORACOES_POR_DIA', 3);
    define('ORACOES_POR_PAGINA', 10);
}

function oracoes_disponivel($conn) {
    return tabela_tem_coluna($conn, 'id_oracao', 'idor_status')
        && tabela_tem_coluna($conn, 'id_oracao_amem', 'idor_id');
}

function oracoes_filtros() {
    return array('abertos' => 'Pedidos', 'atendidos' => 'Atendidas', 'meus' => 'Meus pedidos');
}

// Mantém as quebras de linha (no máximo uma linha em branco).
function oracao_limpar_texto($texto) {
    return trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", (string) $texto)));
}

// Colunas comuns (alias o = pedido, f = autor): améns e o amém de quem vê.
function oracoes_select_sql($fiel_id) {
    $fiel_id = (int) $fiel_id;
    return "
        SELECT o.idor_id, o.idf_id, o.idct_id, o.idor_texto, o.idor_anonimo, o.idor_status, o.idor_resposta,
            f.IDF_NOME,
            EXTRACT(EPOCH FROM o.idor_criado_em)::bigint AS criado_ts,
            EXTRACT(EPOCH FROM o.idor_atendido_em)::bigint AS atendido_ts,
            (SELECT COUNT(*) FROM id_oracao_amem a WHERE a.idor_id = o.idor_id) AS amens,
            EXISTS(SELECT 1 FROM id_oracao_amem a WHERE a.idor_id = o.idor_id AND a.idf_id = $fiel_id) AS meu_amem
        FROM id_oracao o
        JOIN ID_FIEL f ON f.IDF_ID = o.idf_id";
}

function oracao_formatar($linha, $fiel_id, $eh_admin) {
    $anonimo = (int) $linha['idor_anonimo'] === 1;
    $meu = (int) $linha['idf_id'] === (int) $fiel_id;
    // Anônimo: só o admin e o próprio autor sabem quem pediu.
    if ($anonimo && !$eh_admin && !$meu) {
        $nome = 'Anônimo';
    } else {
        $nome = $eh_admin ? (string) $linha['IDF_NOME'] : site_nome_curto($linha['IDF_NOME']);
    }
    return array(
        'id' => (int) $linha['idor_id'],
        'texto' => (string) $linha['idor_texto'],
        'nome' => $nome,
        'anonimo' => $anonimo,
        'meu' => $meu,
        'status' => (string) $linha['idor_status'],
        'resposta' => (string) ($linha['idor_resposta'] ?? ''),
        'criado_ts' => (int) $linha['criado_ts'],
        'atendido_ts' => $linha['atendido_ts'] === null ? null : (int) $linha['atendido_ts'],
        'amens' => (int) $linha['amens'],
        'meu_amem' => !empty($linha['meu_amem']),
        'pode_remover' => $meu || $eh_admin,
    );
}

function oracoes_filtro_sql($filtro, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    if ($filtro === 'atendidos') {
        return array("o.idor_status = 'atendido'", 'o.idor_atendido_em DESC, o.idor_id DESC');
    }
    if ($filtro === 'meus') {
        return array("o.idf_id = $fiel_id AND o.idor_status <> 'removido'", 'o.idor_id DESC');
    }
    return array("o.idor_status = 'aberto'", 'o.idor_id DESC');
}

function oracoes_listar($conn, $filtro, $limite = ORACOES_POR_PAGINA, $pular = 0) {
    $fiel_id = auth_resolve_fiel_id();
    $eh_admin = ($_SESSION['Usuario_tipo'] ?? '') === 'admin';
    list($onde, $ordem) = oracoes_filtro_sql($filtro, $fiel_id);
    $limite = max(1, (int) $limite);
    $pular = max(0, (int) $pular);
    $res = mysqli_query($conn, oracoes_select_sql($fiel_id) . "
        WHERE $onde
        ORDER BY $ordem
        LIMIT $limite OFFSET $pular
    ");
    $lista = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $lista[] = oracao_formatar($linha, $fiel_id, $eh_admin);
    }
    return $lista;
}

function oracoes_contar($conn, $filtro) {
    list($onde) = oracoes_filtro_sql($filtro, auth_resolve_fiel_id());
    $res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM id_oracao o WHERE $onde");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? (int) $linha['total'] : 0;
}

// Um pedido visível (aberto ou atendido), para o link da notificação.
function oracao_obter($conn, $oracao_id) {
    $oracao_id = (int) $oracao_id;
    if ($oracao_id <= 0) {
        return null;
    }
    $fiel_id = auth_resolve_fiel_id();
    $res = mysqli_query($conn, oracoes_select_sql($fiel_id) . "
        WHERE o.idor_id = $oracao_id AND o.idor_status <> 'removido'
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? oracao_formatar($linha, $fiel_id, ($_SESSION['Usuario_tipo'] ?? '') === 'admin') : null;
}

// Link da notificação: a aula de onde o pedido veio, já na aba Orações.
function oracao_link($conteudo_id, $oracao_id) {
    $conteudo_id = (int) $conteudo_id;
    return $conteudo_id > 0
        ? 'assistir_video.php?id=' . $conteudo_id . '&oracao=' . (int) $oracao_id
        : 'notificacoes.php';
}

// Devolve array('ok' => true, 'oracao' => ...) ou array('ok' => false, 'erro' => ...).
function oracao_pedir($conn, $fiel_id, $conteudo_id, $texto, $anonimo) {
    $fiel_id = (int) $fiel_id;
    $conteudo_id = (int) $conteudo_id;
    $texto = oracao_limpar_texto($texto);
    if (mb_strlen($texto) < ORACAO_TEXTO_MIN) {
        return array('ok' => false, 'erro' => 'Conte um pouco mais do seu pedido (pelo menos ' . ORACAO_TEXTO_MIN . ' caracteres).');
    }
    if (mb_strlen($texto) > ORACAO_TEXTO_MAX) {
        return array('ok' => false, 'erro' => 'O pedido pode ter no máximo ' . ORACAO_TEXTO_MAX . ' caracteres.');
    }
    $res = mysqli_query($conn, "
        SELECT f.IDF_STATUS AS status, f.IDF_ATIVO AS ativo,
            EXISTS(SELECT 1 FROM id_fiel_bloqueio_chat b WHERE b.idf_id = f.IDF_ID AND (b.idbc_expira_em IS NULL OR b.idbc_expira_em > NOW())) AS bloqueado,
            (SELECT COUNT(*) FROM id_oracao o WHERE o.idf_id = f.IDF_ID AND o.idor_criado_em > NOW() - INTERVAL '1 day') AS hoje
        FROM ID_FIEL f WHERE f.IDF_ID = $fiel_id
    ");
    $f = $res ? mysqli_fetch_assoc($res) : null;
    if (!$f || $f['status'] !== 'aprovado' || (int) $f['ativo'] !== 1) {
        return array('ok' => false, 'erro' => 'Sua conta não está aprovada para enviar pedidos.');
    }
    if (!empty($f['bloqueado'])) {
        return array('ok' => false, 'erro' => 'Você está impedido de publicar no momento.');
    }
    if ((int) $f['hoje'] >= ORACOES_POR_DIA) {
        return array('ok' => false, 'erro' => 'Você já fez ' . ORACOES_POR_DIA . ' pedidos nas últimas 24 horas. Tente de novo mais tarde.');
    }
    $anonimo = $anonimo ? 1 : 0;
    $res = db_query($conn, "
        INSERT INTO id_oracao (idf_id, idct_id, idor_texto, idor_anonimo)
        VALUES (?, ?, ?, ?)
        RETURNING idor_id
    ", array($fiel_id, $conteudo_id > 0 ? $conteudo_id : null, $texto, $anonimo));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    $oracao = $linha ? oracao_obter($conn, (int) $linha['idor_id']) : null;
    return $oracao
        ? array('ok' => true, 'oracao' => $oracao)
        : array('ok' => false, 'erro' => 'Não foi possível enviar o pedido. Tente novamente.');
}

// Próximo número de amém que avisa o autor (1, 3, 10, 25, 50, 100, 200…).
function oracao_marco_aviso($amens) {
    $marcos = array(100, 50, 25, 10, 3, 1);
    if ($amens >= 100) {
        return (int) (floor($amens / 100) * 100);
    }
    foreach ($marcos as $marco) {
        if ($amens >= $marco) {
            return $marco;
        }
    }
    return 0;
}

// Amém (ou desfaz). Só em pedido aberto e não no próprio.
function oracao_amem($conn, $oracao_id, $fiel_id, $amem) {
    $oracao_id = (int) $oracao_id;
    $fiel_id = (int) $fiel_id;
    $res = mysqli_query($conn, "SELECT idf_id, idct_id, idor_texto, idor_status FROM id_oracao WHERE idor_id = $oracao_id");
    $pedido = $res ? mysqli_fetch_assoc($res) : null;
    if (!$pedido || $pedido['idor_status'] === 'removido') {
        return array('ok' => false, 'erro' => 'Pedido não encontrado.');
    }
    if ((int) $pedido['idf_id'] === $fiel_id) {
        return array('ok' => false, 'erro' => 'Este pedido é seu.');
    }
    if ($pedido['idor_status'] !== 'aberto') {
        return array('ok' => false, 'erro' => 'Esta oração já foi atendida. 🙌');
    }
    $ok = $amem
        ? mysqli_query($conn, "INSERT INTO id_oracao_amem (idor_id, idf_id) VALUES ($oracao_id, $fiel_id) ON CONFLICT (idor_id, idf_id) DO NOTHING")
        : mysqli_query($conn, "DELETE FROM id_oracao_amem WHERE idor_id = $oracao_id AND idf_id = $fiel_id");
    if (!$ok) {
        return array('ok' => false, 'erro' => 'Não foi possível registrar. Tente de novo.');
    }
    $res = mysqli_query($conn, "
        SELECT COUNT(*) AS amens, EXISTS(SELECT 1 FROM id_oracao_amem WHERE idor_id = $oracao_id AND idf_id = $fiel_id) AS meu
        FROM id_oracao_amem WHERE idor_id = $oracao_id
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : array('amens' => 0, 'meu' => false);
    $amens = (int) $linha['amens'];

    // Aviso ao autor só ao passar de um marco novo (o UPDATE condicional
    // evita aviso repetido quando duas pessoas dizem amém juntas, ou quando
    // alguém desfaz e faz de novo).
    $marco = $amem ? oracao_marco_aviso($amens) : 0;
    if ($marco > 0) {
        $res = mysqli_query($conn, "
            UPDATE id_oracao SET idor_amens_avisados = $marco
            WHERE idor_id = $oracao_id AND idor_amens_avisados < $marco
            RETURNING idor_id
        ");
        if ($res && mysqli_fetch_assoc($res)) {
            $autor_id = (int) $pedido['idf_id'];
            $titulo = $marco === 1 ? 'Alguém orou pelo seu pedido 🙏' : $marco . ' pessoas já oraram pelo seu pedido 🙏';
            $mensagem = '"' . mb_strimwidth((string) $pedido['idor_texto'], 0, 80, '…') . '"';
            $link = oracao_link($pedido['idct_id'], $oracao_id);
            db_query($conn, "
                INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK)
                VALUES (?, ?, ?, 'oracao', ?)
            ", array($autor_id, $titulo, $mensagem, $link));
            notificacoes_push_enviar_pendentes($conn, $autor_id);
        }
    }
    return array('ok' => true, 'amens' => $amens, 'meu_amem' => !empty($linha['meu']));
}

// O autor marca como atendida, com o relato opcional. Quem orou recebe um
// aviso no app (sem push: podem ser muitas pessoas de uma vez).
function oracao_marcar_atendida($conn, $oracao_id, $fiel_id, $resposta) {
    $oracao_id = (int) $oracao_id;
    $fiel_id = (int) $fiel_id;
    $resposta = oracao_limpar_texto($resposta);
    if (mb_strlen($resposta) > ORACAO_RESPOSTA_MAX) {
        return array('ok' => false, 'erro' => 'O relato pode ter no máximo ' . ORACAO_RESPOSTA_MAX . ' caracteres.');
    }
    $res = db_query($conn, "
        UPDATE id_oracao SET idor_status = 'atendido', idor_resposta = ?, idor_atendido_em = CURRENT_TIMESTAMP
        WHERE idor_id = ? AND idf_id = ? AND idor_status = 'aberto'
        RETURNING idct_id, idor_texto
    ", array($resposta === '' ? null : $resposta, $oracao_id, $fiel_id));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if (!$linha) {
        return array('ok' => false, 'erro' => 'Pedido não encontrado ou já marcado como atendido.');
    }
    $titulo = 'Uma oração que você fez foi atendida! 🙌';
    $mensagem = '"' . mb_strimwidth((string) $linha['idor_texto'], 0, 80, '…') . '"';
    $link = oracao_link($linha['idct_id'], $oracao_id);
    // INSERT ... SELECT: o Postgres não deduz o tipo do "?" na lista do
    // SELECT, então o cast explícito evita "could not determine data type".
    db_query($conn, "
        INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK, IDN_PUSH_ENVIADO)
        SELECT a.idf_id, CAST(? AS TEXT), CAST(? AS TEXT), 'oracao', CAST(? AS TEXT), 1
        FROM id_oracao_amem a WHERE a.idor_id = ?
    ", array($titulo, $mensagem, $link, $oracao_id));
    return array('ok' => true, 'oracao' => oracao_obter($conn, $oracao_id));
}

// O autor (ou o admin) tira o pedido do ar.
function oracao_remover($conn, $oracao_id, $fiel_id, $eh_admin) {
    $oracao_id = (int) $oracao_id;
    $fiel_id = (int) $fiel_id;
    $so_autor = $eh_admin ? '' : "AND idf_id = $fiel_id";
    mysqli_query($conn, "
        UPDATE id_oracao SET idor_status = 'removido', idor_removido_por = $fiel_id, idor_removido_em = CURRENT_TIMESTAMP
        WHERE idor_id = $oracao_id AND idor_status <> 'removido' $so_autor
    ");
    return mysqli_affected_rows($conn) > 0;
}

// ============================================================================
// RESUMOS DAS AULAS (T29, seção 60 do bancosupabase.md)
// ============================================================================
// O fiel escreve um resumo da aula com as próprias palavras e publica para
// a comunidade (aba "Resumos" em php/fiel/assistir_video.php). Um resumo
// por pessoa por aula (dá para editar ou remover e escrever outro).
// Publica na hora, como os comentários; o admin pode tirar do ar. Recebe
// o voto "Edificante" (mesma regra da T16: um por fiel, não no próprio) e
// o autor é avisado aos poucos (1, 5, 10, 25, 50, 100, 250, 500 e a cada
// 500). Base das medalhas de "Compartilhamento e resumos" (T30).

if (!defined('RESUMO_TEXTO_MAX')) {
    define('RESUMO_TEXTO_MIN', 100);
    define('RESUMO_TEXTO_MAX', 3000);
    define('RESUMOS_POR_PAGINA', 10);
}

function resumos_disponivel($conn) {
    return tabela_tem_coluna($conn, 'id_resumo', 'idrs_status')
        && tabela_tem_coluna($conn, 'id_resumo_voto', 'idrs_id');
}

function resumos_ordens() {
    return array('votados' => 'Mais votados', 'recentes' => 'Mais recentes');
}

// Texto limpo (mantém as quebras de linha, no máximo uma em branco) e o
// erro (ou null).
function resumo_validar($texto) {
    $texto = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", (string) $texto)));
    $erro = null;
    if (mb_strlen($texto) < RESUMO_TEXTO_MIN) {
        $erro = 'Escreva um pouco mais: o resumo precisa de pelo menos ' . RESUMO_TEXTO_MIN . ' caracteres.';
    } elseif (mb_strlen($texto) > RESUMO_TEXTO_MAX) {
        $erro = 'O resumo pode ter no máximo ' . RESUMO_TEXTO_MAX . ' caracteres.';
    }
    return array('erro' => $erro, 'texto' => $texto);
}

// Colunas comuns (alias r = resumo, f = autor): votos e o voto de quem vê.
function resumos_select_sql($fiel_id) {
    $fiel_id = (int) $fiel_id;
    return "
        SELECT r.idrs_id, r.idf_id, r.idct_id, r.idrs_texto, f.IDF_NOME,
            EXTRACT(EPOCH FROM r.idrs_criado_em)::bigint AS criado_ts,
            EXTRACT(EPOCH FROM r.idrs_atualizado_em)::bigint AS atualizado_ts,
            (SELECT COUNT(*) FROM id_resumo_voto v WHERE v.idrs_id = r.idrs_id) AS votos,
            EXISTS(SELECT 1 FROM id_resumo_voto v WHERE v.idrs_id = r.idrs_id AND v.idf_id = $fiel_id) AS meu_voto
        FROM id_resumo r
        JOIN ID_FIEL f ON f.IDF_ID = r.idf_id";
}

function resumo_formatar($linha, $fiel_id, $eh_admin) {
    $meu = (int) $linha['idf_id'] === (int) $fiel_id;
    return array(
        'id' => (int) $linha['idrs_id'],
        'idct_id' => (int) $linha['idct_id'],
        'texto' => (string) $linha['idrs_texto'],
        'nome' => $eh_admin ? (string) $linha['IDF_NOME'] : site_nome_curto($linha['IDF_NOME']),
        'meu' => $meu,
        'criado_ts' => (int) $linha['criado_ts'],
        'editado' => (int) $linha['atualizado_ts'] > (int) $linha['criado_ts'] + 5,
        'votos' => (int) $linha['votos'],
        'meu_voto' => !empty($linha['meu_voto']),
        'pode_remover' => $meu || $eh_admin,
    );
}

// Resumos publicados da aula, sem o de quem vê (o dele vai no topo, à parte).
function resumos_listar($conn, $conteudo_id, $ordem = 'votados', $limite = RESUMOS_POR_PAGINA, $pular = 0) {
    $conteudo_id = (int) $conteudo_id;
    $fiel_id = auth_resolve_fiel_id();
    $eh_admin = ($_SESSION['Usuario_tipo'] ?? '') === 'admin';
    $ordenar = $ordem === 'recentes' ? 'r.idrs_id DESC' : 'votos DESC, r.idrs_id DESC';
    $limite = max(1, (int) $limite);
    $pular = max(0, (int) $pular);
    $res = mysqli_query($conn, resumos_select_sql($fiel_id) . "
        WHERE r.idct_id = $conteudo_id AND r.idrs_status = 'publicado' AND r.idf_id <> " . (int) $fiel_id . "
        ORDER BY $ordenar
        LIMIT $limite OFFSET $pular
    ");
    $lista = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $lista[] = resumo_formatar($linha, $fiel_id, $eh_admin);
    }
    return $lista;
}

function resumos_contar($conn, $conteudo_id) {
    $conteudo_id = (int) $conteudo_id;
    $res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM id_resumo WHERE idct_id = $conteudo_id AND idrs_status = 'publicado'");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? (int) $linha['total'] : 0;
}

// Um resumo publicado (por id), ou o do fiel na aula.
function resumo_obter($conn, $resumo_id = 0, $fiel_autor = 0, $conteudo_id = 0) {
    $fiel_id = auth_resolve_fiel_id();
    $onde = (int) $resumo_id > 0
        ? 'r.idrs_id = ' . (int) $resumo_id
        : 'r.idf_id = ' . (int) $fiel_autor . ' AND r.idct_id = ' . (int) $conteudo_id;
    $res = mysqli_query($conn, resumos_select_sql($fiel_id) . "
        WHERE $onde AND r.idrs_status = 'publicado'
        LIMIT 1
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? resumo_formatar($linha, $fiel_id, ($_SESSION['Usuario_tipo'] ?? '') === 'admin') : null;
}

// Devolve array('ok' => true, 'resumo' => ...) ou array('ok' => false, 'erro' => ...).
function resumo_publicar($conn, $fiel_id, $conteudo_id, $texto) {
    $fiel_id = (int) $fiel_id;
    $conteudo_id = (int) $conteudo_id;
    $validado = resumo_validar($texto);
    if ($validado['erro']) {
        return array('ok' => false, 'erro' => $validado['erro']);
    }
    $res = mysqli_query($conn, "
        SELECT f.IDF_STATUS AS status, f.IDF_ATIVO AS ativo,
            EXISTS(SELECT 1 FROM id_fiel_bloqueio_chat b WHERE b.idf_id = f.IDF_ID AND (b.idbc_expira_em IS NULL OR b.idbc_expira_em > NOW())) AS bloqueado,
            EXISTS(SELECT 1 FROM id_resumo r WHERE r.idf_id = f.IDF_ID AND r.idct_id = $conteudo_id AND r.idrs_status = 'publicado') AS ja_tem
        FROM ID_FIEL f WHERE f.IDF_ID = $fiel_id
    ");
    $f = $res ? mysqli_fetch_assoc($res) : null;
    if (!$f || $f['status'] !== 'aprovado' || (int) $f['ativo'] !== 1) {
        return array('ok' => false, 'erro' => 'Sua conta não está aprovada para publicar resumos.');
    }
    if (!empty($f['bloqueado'])) {
        return array('ok' => false, 'erro' => 'Você está impedido de publicar no momento.');
    }
    if (!empty($f['ja_tem'])) {
        return array('ok' => false, 'erro' => 'Você já publicou um resumo desta aula. Edite o seu se quiser mudar.');
    }
    // O índice único (um publicado por fiel e aula) barra dois envios juntos.
    $res = db_query($conn, "
        INSERT INTO id_resumo (idf_id, idct_id, idrs_texto)
        VALUES (?, ?, ?)
        RETURNING idrs_id
    ", array($fiel_id, $conteudo_id, $validado['texto']));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    $resumo = $linha ? resumo_obter($conn, (int) $linha['idrs_id']) : null;
    return $resumo
        ? array('ok' => true, 'resumo' => $resumo)
        : array('ok' => false, 'erro' => 'Não foi possível publicar. Se você já publicou um resumo desta aula, atualize a página.');
}

// Edita o texto do próprio resumo (os votos ficam).
function resumo_editar($conn, $fiel_id, $resumo_id, $texto) {
    $fiel_id = (int) $fiel_id;
    $resumo_id = (int) $resumo_id;
    $validado = resumo_validar($texto);
    if ($validado['erro']) {
        return array('ok' => false, 'erro' => $validado['erro']);
    }
    $res = db_query($conn, "
        UPDATE id_resumo SET idrs_texto = ?, idrs_atualizado_em = CURRENT_TIMESTAMP
        WHERE idrs_id = ? AND idf_id = ? AND idrs_status = 'publicado'
        RETURNING idrs_id
    ", array($validado['texto'], $resumo_id, $fiel_id));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha
        ? array('ok' => true, 'resumo' => resumo_obter($conn, $resumo_id))
        : array('ok' => false, 'erro' => 'Resumo não encontrado.');
}

// Próximo número de votos que avisa o autor (1, 5, 10, 25, 50, 100, 250,
// 500, 1000…).
function resumo_marco_aviso($votos) {
    if ($votos >= 500) {
        return (int) (floor($votos / 500) * 500);
    }
    foreach (array(250, 100, 50, 25, 10, 5, 1) as $marco) {
        if ($votos >= $marco) {
            return $marco;
        }
    }
    return 0;
}

// Voto "Edificante" (ou desfaz). Não no próprio resumo.
function resumo_votar($conn, $resumo_id, $fiel_id, $votar) {
    $resumo_id = (int) $resumo_id;
    $fiel_id = (int) $fiel_id;
    $res = mysqli_query($conn, "
        SELECT r.idf_id, r.idct_id, ct.IDCT_TITULO AS aula
        FROM id_resumo r JOIN ID_CONTENT ct ON ct.IDCT_ID = r.idct_id
        WHERE r.idrs_id = $resumo_id AND r.idrs_status = 'publicado'
    ");
    $resumo = $res ? mysqli_fetch_assoc($res) : null;
    if (!$resumo) {
        return array('ok' => false, 'erro' => 'Resumo não encontrado.');
    }
    if ((int) $resumo['idf_id'] === $fiel_id) {
        return array('ok' => false, 'erro' => 'Você não pode votar no próprio resumo.');
    }
    $ok = $votar
        ? mysqli_query($conn, "INSERT INTO id_resumo_voto (idrs_id, idf_id) VALUES ($resumo_id, $fiel_id) ON CONFLICT (idrs_id, idf_id) DO NOTHING")
        : mysqli_query($conn, "DELETE FROM id_resumo_voto WHERE idrs_id = $resumo_id AND idf_id = $fiel_id");
    if (!$ok) {
        return array('ok' => false, 'erro' => 'Não foi possível votar. Tente de novo.');
    }
    $res = mysqli_query($conn, "
        SELECT COUNT(*) AS votos, EXISTS(SELECT 1 FROM id_resumo_voto WHERE idrs_id = $resumo_id AND idf_id = $fiel_id) AS meu
        FROM id_resumo_voto WHERE idrs_id = $resumo_id
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : array('votos' => 0, 'meu' => false);
    $votos = (int) $linha['votos'];

    // Aviso ao autor só ao passar de um marco novo (o UPDATE condicional
    // evita aviso repetido com votos simultâneos ou desfazer e refazer).
    $marco = $votar ? resumo_marco_aviso($votos) : 0;
    if ($marco > 0) {
        $res = mysqli_query($conn, "
            UPDATE id_resumo SET idrs_votos_avisados = $marco
            WHERE idrs_id = $resumo_id AND idrs_votos_avisados < $marco
            RETURNING idrs_id
        ");
        if ($res && mysqli_fetch_assoc($res)) {
            $autor_id = (int) $resumo['idf_id'];
            $titulo = $marco === 1 ? 'Seu resumo edificou alguém 🙌' : $marco . ' pessoas acharam seu resumo edificante 🙌';
            $mensagem = 'Resumo da aula "' . mb_strimwidth((string) $resumo['aula'], 0, 70, '…') . '".';
            $link = 'assistir_video.php?id=' . (int) $resumo['idct_id'] . '&resumo=' . $resumo_id;
            db_query($conn, "
                INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK)
                VALUES (?, ?, ?, 'resumo', ?)
            ", array($autor_id, $titulo, $mensagem, $link));
            notificacoes_push_enviar_pendentes($conn, $autor_id);
        }
    }
    return array('ok' => true, 'votos' => $votos, 'meu_voto' => !empty($linha['meu']), 'autor_id' => (int) $resumo['idf_id']);
}

// O autor (ou o admin) tira o resumo do ar; o autor pode escrever outro.
function resumo_remover($conn, $resumo_id, $fiel_id, $eh_admin) {
    $resumo_id = (int) $resumo_id;
    $fiel_id = (int) $fiel_id;
    $so_autor = $eh_admin ? '' : "AND idf_id = $fiel_id";
    mysqli_query($conn, "
        UPDATE id_resumo SET idrs_status = 'removido', idrs_removido_por = $fiel_id, idrs_removido_em = CURRENT_TIMESTAMP
        WHERE idrs_id = $resumo_id AND idrs_status = 'publicado' $so_autor
    ");
    return mysqli_affected_rows($conn) > 0;
}

// ============================================================================
// MEDALHAS (T14 e T30, seções 51 e 61 do bancosupabase.md)
// ============================================================================
// ID_BADGE é o catálogo (nome, categoria, nível, regra) e ID_FIEL_BADGE as
// medalhas conquistadas. Medalha conquistada não se perde. Regras:
//   ofensiva_dias       RECORDE de dias seguidos (T14; sequências antigas
//                       também contam) >= idbadge_regra_valor
//   respostas_uteis     respostas (T18) com pelo menos MEDALHA_RESPOSTA_UTIL_VOTOS
//                       votos "Edificante" de outras pessoas e mais
//                       Edificante que "Fora de contexto" (T16)
//   resumos_publicados  resumos publicados (T29)
//   resumo_votos        votos "Edificante" do resumo mais votado (T29)
//   amens_dados         améns dados em pedidos de oração (T28)
//   amens_e_atendidos   améns dados >= valor E pedidos em que orou que foram
//                       atendidos >= idbadge_regra_valor2
//   desafios_concluidos desafios concluídos (T31)
// Conteúdo removido pela moderação (ou pelo autor) deixa de contar para as
// próximas, mas a medalha já ganha fica. As de ofensiva são dadas no
// primeiro acesso do dia e ao abrir o perfil ou a ofensiva; as da T30 no
// primeiro acesso do dia, ao abrir o perfil e na hora da ação (publicar,
// votar, amém, oração atendida), inclusive para quem recebe o voto — esse
// é avisado por notificação.
// Pontos em aberto com o cliente: docs/para_o_cliente/pontos_para_alinhar.md,
// seção 6.

if (!defined('MEDALHA_RESPOSTA_UTIL_VOTOS')) {
    define('MEDALHA_RESPOSTA_UTIL_VOTOS', 3);
}

function medalhas_disponivel($conn) {
    return tabela_tem_coluna($conn, 'ID_BADGE', 'IDBADGE_REGRA') && tabela_tem_coluna($conn, 'ID_FIEL_BADGE', 'IDFB_DESBLOQUEADA_EM');
}

function medalhas_niveis() {
    return array('bronze' => 'Bronze', 'prata' => 'Prata', 'ouro' => 'Ouro', 'diamante' => 'Diamante');
}

// Na ordem de exibição.
function medalhas_categorias() {
    return array(
        'primeiros_passos' => 'Primeiros passos',
        'crescimento' => 'Crescimento pessoal',
        'estudos' => 'Estudos',
        'comentarios' => 'Comentários',
        'ensino' => 'Ensino e resposta',
        'resumos' => 'Compartilhamento e resumos',
        'oracao' => 'Intercessão e oração',
        'testemunhos' => 'Testemunhos',
        'devocionais' => 'Devocionais',
        'ao_vivo' => 'Ao Vivo',
        'app' => 'No app',
        'desafios' => 'Desafios',
        'secretas' => 'Secretas',
    );
}

// Unidade mostrada no progresso da próxima medalha ("12 de 50 améns").
function medalhas_unidades() {
    return array(
        'ofensiva_dias' => 'dias',
        'respostas_uteis' => 'respostas úteis',
        'resumos_publicados' => 'resumos',
        'resumo_votos' => 'votos no seu melhor resumo',
        'amens_dados' => 'améns',
        'amens_e_atendidos' => 'améns',
        'desafios_concluidos' => 'desafios',
        'aulas_concluidas' => 'aulas',
        'aulas_mesmo_dia' => 'aulas no mesmo dia (seu recorde)',
        'cursos_concluidos' => 'cursos',
        'comentarios' => 'comentários',
        'pedidos_oracao' => 'pedidos',
        'testemunhos_publicados' => 'testemunhos',
        'testemunhos_apoiados' => 'testemunhos apoiados',
        'devocionais_enviados' => 'devocionais',
        'devocionais_publicados' => 'devocionais publicados',
        'lives' => 'lives',
        'app_dias' => 'dias pelo app',
    );
}

// Medalhas de "sim ou não": sem barra "0 de 1" na grade, só a dica.
function medalhas_regras_sem_barra() {
    return array('app_instalado', 'passos_espaco', 'passos_app', 'oracoes_respondidas', 'madrugador', 'aniversario', 'filho_prodigo');
}

// Números atuais do fiel em cada regra da T30 (só as que têm tabela no
// banco): array('respostas_uteis' => n, ..., 'oracoes_atendidas' => n).
// $regras limita o cálculo (null = todas).
function medalhas_contagens($conn, $fiel_id, $regras = null) {
    $fiel_id = (int) $fiel_id;
    $quer = function ($regra) use ($regras) {
        return $regras === null || in_array($regra, $regras, true);
    };
    $colunas = array();
    if ($quer('respostas_uteis') && video_respostas_disponivel($conn) && video_votos_disponivel($conn)) {
        $minimo = (int) MEDALHA_RESPOSTA_UTIL_VOTOS;
        $colunas[] = "(SELECT COUNT(*) FROM id_conteudo_comentario c
            JOIN LATERAL (
                SELECT COUNT(*) FILTER (WHERE v.idccv_tipo = 1) AS edificante,
                       COUNT(*) FILTER (WHERE v.idccv_tipo = -1) AS fora
                FROM id_conteudo_comentario_voto v WHERE v.idcc_id = c.idcc_id AND v.idf_id <> $fiel_id
            ) t ON TRUE
            WHERE c.idf_id = $fiel_id AND c.idcc_pai_id IS NOT NULL AND c.idcc_oculto = 0
              AND t.edificante >= $minimo AND t.edificante > t.fora) AS respostas_uteis";
    }
    if (($quer('resumos_publicados') || $quer('resumo_votos')) && resumos_disponivel($conn)) {
        $colunas[] = "(SELECT COUNT(*) FROM id_resumo r WHERE r.idf_id = $fiel_id AND r.idrs_status = 'publicado') AS resumos_publicados";
        $colunas[] = "(SELECT COALESCE(MAX(t.votos), 0) FROM (
                SELECT COUNT(v.idf_id) AS votos FROM id_resumo r
                LEFT JOIN id_resumo_voto v ON v.idrs_id = r.idrs_id
                WHERE r.idf_id = $fiel_id AND r.idrs_status = 'publicado'
                GROUP BY r.idrs_id
            ) t) AS resumo_votos";
    }
    if (($quer('amens_dados') || $quer('amens_e_atendidos')) && oracoes_disponivel($conn)) {
        $colunas[] = "(SELECT COUNT(*) FROM id_oracao_amem a JOIN id_oracao o ON o.idor_id = a.idor_id
            WHERE a.idf_id = $fiel_id AND o.idor_status <> 'removido') AS amens_dados";
        $colunas[] = "(SELECT COUNT(*) FROM id_oracao_amem a JOIN id_oracao o ON o.idor_id = a.idor_id
            WHERE a.idf_id = $fiel_id AND o.idor_status = 'atendido') AS oracoes_atendidas";
    }
    if ($quer('desafios_concluidos') && desafios_disponivel($conn)) {
        $colunas[] = "(SELECT COUNT(*) FROM id_desafio_participante p
            WHERE p.idf_id = $fiel_id AND p.iddp_concluido_em IS NOT NULL) AS desafios_concluidos";
    }
    // Medalha "No bolso" (seção 79): 1 se já usou pelo app instalado.
    if ($quer('app_instalado') && acesso_app_disponivel($conn)) {
        $colunas[] = "(SELECT COUNT(*) FROM (SELECT 1 FROM id_fiel_acesso_dia a
            WHERE a.idf_id = $fiel_id AND a.idad_pelo_app = 1 LIMIT 1) t) AS app_instalado";
    }
    // ---- Seção 81: estudos, comentários, testemunhos, devocionais, lives,
    // app e as secretas. Cada uma só entra se a tabela existir.
    $fuso = 'America/Sao_Paulo';
    if ($quer('aulas_concluidas')) {
        $colunas[] = "(SELECT COUNT(DISTINCT h.idct_id) FROM id_historico_visualizacao h
            WHERE h.idf_id = $fiel_id AND h.idhv_concluido = 1) AS aulas_concluidas";
    }
    if ($quer('aulas_mesmo_dia')) {
        // Maior número de aulas diferentes concluídas num mesmo dia.
        $colunas[] = "(SELECT COALESCE(MAX(t.qtd), 0) FROM (
                SELECT COUNT(DISTINCT h.idct_id) AS qtd FROM id_historico_visualizacao h
                WHERE h.idf_id = $fiel_id AND h.idhv_concluido = 1
                GROUP BY (h.idhv_criado_em AT TIME ZONE '$fuso')::date
            ) t) AS aulas_mesmo_dia";
    }
    if ($quer('cursos_concluidos')) {
        // Certificados de cursos de verdade (o de boas-vindas não conta).
        $curso_bv = (int) certificado_boas_vindas_curso_id($conn);
        $colunas[] = "(SELECT COUNT(*) FROM id_certificado ce
            WHERE ce.idf_id = $fiel_id AND ce.idc_id <> $curso_bv) AS cursos_concluidos";
    }
    if ($quer('comentarios')) {
        $colunas[] = "(SELECT COUNT(*) FROM id_conteudo_comentario c
            WHERE c.idf_id = $fiel_id AND c.idcc_oculto = 0) AS comentarios";
    }
    if (($quer('pedidos_oracao') || $quer('oracoes_respondidas') || $quer('amem_dias_seguidos')) && oracoes_disponivel($conn)) {
        $colunas[] = "(SELECT COUNT(*) FROM id_oracao o WHERE o.idf_id = $fiel_id AND o.idor_status <> 'removido') AS pedidos_oracao";
        $colunas[] = "(SELECT COUNT(*) FROM id_oracao o WHERE o.idf_id = $fiel_id AND o.idor_status = 'atendido') AS oracoes_respondidas";
        // Maior sequência de dias seguidos com pelo menos um amém (ilhas
        // de datas: dia - posição é igual dentro de cada sequência).
        $colunas[] = "(SELECT COALESCE(MAX(t.qtd), 0) FROM (
                SELECT COUNT(*) AS qtd FROM (
                    SELECT d.dia, d.dia - (ROW_NUMBER() OVER (ORDER BY d.dia))::int AS ilha
                    FROM (SELECT DISTINCT (a.idoa_criado_em AT TIME ZONE '$fuso')::date AS dia
                          FROM id_oracao_amem a WHERE a.idf_id = $fiel_id) d
                ) s GROUP BY s.ilha
            ) t) AS amem_dias_seguidos";
    }
    if (($quer('testemunhos_publicados') || $quer('testemunhos_apoiados')) && testemunhos_disponivel($conn)) {
        $colunas[] = "(SELECT COUNT(*) FROM id_testemunho t WHERE t.idf_id = $fiel_id AND t.idts_status = 'aprovado') AS testemunhos_publicados";
        $colunas[] = "(SELECT COUNT(*) FROM id_testemunho_voto v JOIN id_testemunho t ON t.idts_id = v.idts_id
            WHERE v.idf_id = $fiel_id AND t.idf_id <> $fiel_id) AS testemunhos_apoiados";
    }
    if (($quer('devocionais_enviados') || $quer('devocionais_publicados')) && devocionais_disponivel($conn)) {
        $colunas[] = "(SELECT COUNT(*) FROM id_devocional d WHERE d.idf_id = $fiel_id AND d.iddv_status <> 'removido') AS devocionais_enviados";
        $colunas[] = "(SELECT COUNT(*) FROM id_devocional d WHERE d.idf_id = $fiel_id AND d.iddv_status = 'aprovado') AS devocionais_publicados";
    }
    if ($quer('lives') && site_tabela_existe($conn, 'id_transmissao_comentario')) {
        $colunas[] = "(SELECT COUNT(DISTINCT c.idtr_id) FROM id_transmissao_comentario c
            WHERE c.idf_id = $fiel_id AND c.idtc_oculto = 0) AS lives";
    }
    if ($quer('app_dias') && acesso_app_disponivel($conn)) {
        $colunas[] = "(SELECT COUNT(*) FROM id_fiel_acesso_dia a WHERE a.idf_id = $fiel_id AND a.idad_pelo_app = 1) AS app_dias";
    }
    // Secretas: o primeiro acesso do dia foi antes das 6h; entrou no dia do
    // próprio aniversário; voltou depois de mais de 30 dias sem entrar.
    if ($quer('madrugador')) {
        $colunas[] = "(SELECT COUNT(*) FROM (SELECT 1 FROM id_fiel_acesso_dia a
            WHERE a.idf_id = $fiel_id AND (a.idad_criado_em AT TIME ZONE '$fuso')::time < TIME '06:00' LIMIT 1) t) AS madrugador";
    }
    if ($quer('aniversario') && fiel_perfil_disponivel($conn)) {
        $colunas[] = "(SELECT COUNT(*) FROM (SELECT 1 FROM id_fiel_acesso_dia a JOIN id_fiel f ON f.idf_id = a.idf_id
            WHERE a.idf_id = $fiel_id AND f.idf_nascimento IS NOT NULL
              AND to_char(a.idad_data, 'MM-DD') = to_char(f.idf_nascimento, 'MM-DD') LIMIT 1) t) AS aniversario";
    }
    if ($quer('filho_prodigo')) {
        $colunas[] = "(SELECT COUNT(*) FROM (SELECT 1 FROM (
                SELECT a.idad_data - LAG(a.idad_data) OVER (ORDER BY a.idad_data) AS intervalo
                FROM id_fiel_acesso_dia a WHERE a.idf_id = $fiel_id
            ) s WHERE s.intervalo > 30 LIMIT 1) t) AS filho_prodigo";
    }
    // Primeiros passos (seção 80): 1 por grupo completo. Só para o próprio
    // fiel logado — as missões dependem das permissões dele (fiel_pode()).
    $extras = array();
    if (($quer('passos_espaco') || $quer('passos_app')) && $fiel_id === (int) auth_resolve_fiel_id()) {
        $extras = primeiros_passos_contagens($conn, $fiel_id);
    }
    if (!$colunas) {
        return $extras;
    }
    // Mesma consulta na mesma requisição sai do guardado (o Perfil e a Home
    // faziam essa conta pesada duas vezes: ao conceder e ao mostrar).
    $sql = 'SELECT ' . implode(",\n", $colunas);
    $chave_cache = md5($sql);
    if (isset($GLOBALS['site_cache_contagens'][$chave_cache])) {
        return $GLOBALS['site_cache_contagens'][$chave_cache] + $extras;
    }
    $res = mysqli_query($conn, $sql);
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    $contagens = array();
    foreach ((array) $linha as $chave => $valor) {
        $contagens[$chave] = (int) $valor;
    }
    if (isset($contagens['amens_dados'])) {
        $contagens['amens_e_atendidos'] = $contagens['amens_dados'];
    }
    if ($res) {
        $GLOBALS['site_cache_contagens'][$chave_cache] = $contagens;
    }
    return $contagens + $extras;
}

// Concede as medalhas da T30 que o fiel já alcança. $regras limita a
// conferência (null = todas). Com $notificar, avisa por notificação no app
// + push (para quem recebeu um voto, que não está nesta sessão). Devolve
// as medalhas NOVAS.
function medalhas_conceder_atividade($conn, $fiel_id, $regras = null, $notificar = false) {
    $fiel_id = (int) $fiel_id;
    $novas = array();
    if ($fiel_id <= 0 || !medalhas_disponivel($conn)) {
        return $novas;
    }
    $pendentes = array();
    $ja_tem = null;
    foreach (medalhas_listar($conn) as $id => $medalha) {
        if ($medalha['regra'] === 'ofensiva_dias' || ($regras !== null && !in_array($medalha['regra'], $regras, true))) {
            continue;
        }
        if ($ja_tem === null) {
            $ja_tem = medalhas_do_fiel($conn, $fiel_id);
        }
        if (!isset($ja_tem[$id])) {
            $pendentes[$id] = $medalha;
        }
    }
    if (!$pendentes) {
        return $novas;
    }
    $contagens = medalhas_contagens($conn, $fiel_id, array_values(array_unique(array_column($pendentes, 'regra'))));
    foreach ($pendentes as $id => $medalha) {
        $atual = $contagens[$medalha['regra']] ?? null;
        if ($atual === null || $atual < $medalha['valor']) {
            continue;
        }
        if ($medalha['regra'] === 'amens_e_atendidos' && ($contagens['oracoes_atendidas'] ?? 0) < $medalha['valor2']) {
            continue;
        }
        // RETURNING só devolve linha se inseriu — duas requisições ao mesmo
        // tempo não dão a mesma medalha "nova" duas vezes.
        unset($GLOBALS['site_cache_medalhas'][$fiel_id]); // medalhas_do_fiel() relê
        $res = mysqli_query($conn, "INSERT INTO id_fiel_badge (idf_id, idbadge_id) VALUES ($fiel_id, $id) ON CONFLICT (idf_id, idbadge_id) DO NOTHING RETURNING idbadge_id");
        if ($res && mysqli_num_rows($res) > 0) {
            $novas[$id] = $medalha;
        }
    }
    if ($novas && $notificar) {
        $nomes = array_column($novas, 'nome');
        $titulo = count($nomes) === 1 ? 'Nova medalha: ' . $nomes[0] . ' 🏅' : count($nomes) . ' novas medalhas! 🏅';
        $mensagem = count($nomes) === 1 ? (string) reset($novas)['descricao'] : implode(', ', $nomes);
        db_query($conn, "
            INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK)
            VALUES (?, ?, ?, 'medalha', 'perfil.php#medalhas')
        ", array($fiel_id, $titulo, $mensagem));
        notificacoes_push_enviar_pendentes($conn, $fiel_id);
    }
    return $novas;
}

// Depois de uma ação na comunidade: confere as medalhas de quem agiu (aviso
// na próxima página, como as de ofensiva) e, se houver, de quem recebeu o
// voto (aviso por notificação). Nunca derruba a ação se algo falhar.
function medalhas_apos_acao($conn, $fiel_id, array $regras, $outro_fiel_id = 0, array $regras_outro = array()) {
    if (!medalhas_disponivel($conn)) {
        return;
    }
    if ($fiel_id > 0 && $regras) {
        medalhas_avisar_novas(medalhas_conceder_atividade($conn, $fiel_id, $regras, true));
    }
    if ($outro_fiel_id > 0 && $outro_fiel_id !== (int) $fiel_id && $regras_outro) {
        medalhas_conceder_atividade($conn, $outro_fiel_id, $regras_outro, true);
    }
}

// Pedido marcado como atendido: quem orou por ele pode completar a
// "Coluna de fogo" (améns + pedidos atendidos). Só confere quem já tem
// améns suficientes para alguma medalha dessa regra.
function medalhas_apos_oracao_atendida($conn, $oracao_id) {
    if (!medalhas_disponivel($conn)) {
        return;
    }
    $minimo = null;
    foreach (medalhas_listar($conn) as $medalha) {
        if ($medalha['regra'] === 'amens_e_atendidos') {
            $minimo = $minimo === null ? $medalha['valor'] : min($minimo, $medalha['valor']);
        }
    }
    if ($minimo === null) {
        return;
    }
    $oracao_id = (int) $oracao_id;
    $minimo = (int) $minimo;
    $res = mysqli_query($conn, "
        SELECT a.idf_id FROM id_oracao_amem a
        WHERE a.idor_id = $oracao_id
          AND (SELECT COUNT(*) FROM id_oracao_amem a2 WHERE a2.idf_id = a.idf_id) >= $minimo
    ");
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        medalhas_conceder_atividade($conn, (int) $linha['idf_id'], array('amens_e_atendidos'), true);
    }
}

// ============================================================================
// DESAFIOS (T31, seção 62 do bancosupabase.md)
// ============================================================================
// O admin cria desafios (php/admin/admin_desafios.php) e o fiel entra e
// acompanha (php/fiel/desafios.php). Cada desafio conta uma "métrica" com
// os dados que o app já tem (aulas concluídas, dias no app, comentários,
// resumos, améns) ou o "marcar como feito" da própria pessoa (para o que o
// app não mede, ex.: "ler 1 capítulo por dia"). Modos:
//   total   chegar a idds_meta dentro do período ("5 aulas em 7 dias")
//   diario  idds_meta por dia, todos os dias do período ("1 aula por dia
//           durante 7 dias")
// Período: 'ao_entrar' = cada um começa no dia em que entra e tem
// idds_duracao_dias (idds_inicio/idds_fim, se houver, limitam quando dá
// para entrar); 'fixo' = de idds_inicio a idds_fim para todos.
// A conclusão é conferida no primeiro acesso do dia, ao abrir a página de
// desafios ou a home e depois das ações que contam; quem conclui recebe
// notificação + push. Datas no fuso de São Paulo.

if (!defined('DESAFIO_TITULO_MAX')) {
    define('DESAFIO_TITULO_MAX', 80);
    define('DESAFIO_DESCRICAO_MAX', 500);
    define('DESAFIO_FUSO', 'America/Sao_Paulo');
}

function desafios_disponivel($conn) {
    return tabela_tem_coluna($conn, 'id_desafio', 'idds_metrica')
        && tabela_tem_coluna($conn, 'id_desafio_participante', 'iddp_fim')
        && tabela_tem_coluna($conn, 'id_desafio_checkin', 'iddc_data');
}

// chave => rótulo no admin, singular, plural, verbo ("Concluir 5 aulas").
// dias_app e checkin contam no máximo 1 por dia.
function desafios_metricas() {
    return array(
        'aulas' => array('rotulo' => 'Aulas concluídas', 'um' => 'aula', 'varios' => 'aulas', 'verbo' => 'Concluir'),
        'dias_app' => array('rotulo' => 'Dias acessando o app', 'um' => 'dia', 'varios' => 'dias', 'verbo' => 'Acessar o app em'),
        'comentarios' => array('rotulo' => 'Comentários nas aulas', 'um' => 'comentário', 'varios' => 'comentários', 'verbo' => 'Fazer'),
        'resumos' => array('rotulo' => 'Resumos de aula publicados', 'um' => 'resumo', 'varios' => 'resumos', 'verbo' => 'Publicar'),
        'amens' => array('rotulo' => 'Améns em pedidos de oração', 'um' => 'pedido de oração', 'varios' => 'pedidos de oração', 'verbo' => 'Orar por'),
        'checkin' => array('rotulo' => 'Marcar como feito (a pessoa confirma)', 'um' => 'vez', 'varios' => 'vezes', 'verbo' => 'Marcar como feito'),
    );
}

// Métricas que só contam 1 por dia (no modo diário, a meta é sempre 1).
function desafio_metrica_diaria_unica($metrica) {
    return $metrica === 'dias_app' || $metrica === 'checkin';
}

// Métrica disponível no banco (resumos e améns dependem das seções 59/60).
function desafio_metrica_disponivel($conn, $metrica) {
    if ($metrica === 'resumos') {
        return resumos_disponivel($conn);
    }
    if ($metrica === 'amens') {
        return oracoes_disponivel($conn);
    }
    return isset(desafios_metricas()[$metrica]);
}

function desafio_data_br($data) {
    return $data ? date('d/m/Y', strtotime($data)) : '';
}

// "Concluir 5 aulas em 7 dias", "Concluir 1 aula por dia durante 7 dias"…
function desafio_meta_texto($d) {
    $metricas = desafios_metricas();
    $m = $metricas[$d['metrica']] ?? $metricas['checkin'];
    $meta = (int) $d['meta'];
    $dias = (int) $d['dias_total'];
    $periodo = $d['periodo'] === 'fixo'
        ? 'de ' . date('d/m', strtotime($d['inicio'])) . ' a ' . date('d/m', strtotime($d['fim']))
        : 'em ' . $dias . ($dias === 1 ? ' dia' : ' dias');
    if ($d['modo'] === 'diario') {
        $duracao = $d['periodo'] === 'fixo' ? $periodo : 'durante ' . $dias . ($dias === 1 ? ' dia' : ' dias');
        if (desafio_metrica_diaria_unica($d['metrica'])) {
            return ($d['metrica'] === 'dias_app' ? 'Acessar o app' : 'Marcar como feito') . ' todos os dias ' . $duracao;
        }
        return $m['verbo'] . ' ' . $meta . ' ' . ($meta === 1 ? $m['um'] : $m['varios']) . ' por dia ' . $duracao;
    }
    if (desafio_metrica_diaria_unica($d['metrica'])) {
        // "Acessar o app em 5 dos 7 dias" / "… em 5 dias, de 10/10 a 17/10"
        $acao = $d['metrica'] === 'dias_app' ? 'Acessar o app' : 'Marcar como feito';
        return $d['periodo'] === 'fixo'
            ? $acao . ' em ' . $meta . ($meta === 1 ? ' dia, ' : ' dias, ') . $periodo
            : $acao . ' em ' . $meta . ' dos ' . $dias . ' dias';
    }
    return $m['verbo'] . ' ' . $meta . ' ' . ($meta === 1 ? $m['um'] : $m['varios']) . ' ' . $periodo;
}

function desafio_formatar($linha) {
    $d = array(
        'id' => (int) $linha['idds_id'],
        'titulo' => (string) $linha['idds_titulo'],
        'descricao' => (string) ($linha['idds_descricao'] ?? ''),
        'metrica' => (string) $linha['idds_metrica'],
        'modo' => $linha['idds_modo'] === 'diario' ? 'diario' : 'total',
        'meta' => max(1, (int) $linha['idds_meta']),
        'periodo' => $linha['idds_periodo'] === 'fixo' ? 'fixo' : 'ao_entrar',
        'duracao' => (int) $linha['idds_duracao_dias'],
        'inicio' => $linha['idds_inicio'] ? substr((string) $linha['idds_inicio'], 0, 10) : null,
        'fim' => $linha['idds_fim'] ? substr((string) $linha['idds_fim'], 0, 10) : null,
        'ativo' => (int) $linha['idds_ativo'] === 1,
        'participantes' => (int) ($linha['participantes'] ?? 0),
        'concluiram' => (int) ($linha['concluiram'] ?? 0),
    );
    $d['dias_total'] = $d['periodo'] === 'fixo' && $d['inicio'] && $d['fim']
        ? (int) round((strtotime($d['fim']) - strtotime($d['inicio'])) / 86400) + 1
        : max(1, $d['duracao']);
    $d['meta_texto'] = desafio_meta_texto($d);
    return $d;
}

function desafios_select_sql() {
    return "
        SELECT d.*,
            (SELECT COUNT(*) FROM id_desafio_participante p WHERE p.idds_id = d.idds_id) AS participantes,
            (SELECT COUNT(*) FROM id_desafio_participante p WHERE p.idds_id = d.idds_id AND p.iddp_concluido_em IS NOT NULL) AS concluiram
        FROM id_desafio d";
}

function desafio_obter($conn, $desafio_id) {
    $desafio_id = (int) $desafio_id;
    $res = mysqli_query($conn, desafios_select_sql() . " WHERE d.idds_id = $desafio_id");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? desafio_formatar($linha) : null;
}

// Admin: todos, os ativos primeiro e os mais novos antes.
function desafios_admin_listar($conn) {
    $res = mysqli_query($conn, desafios_select_sql() . " ORDER BY d.idds_ativo DESC, d.idds_id DESC");
    $lista = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $lista[] = desafio_formatar($linha);
    }
    return $lista;
}

// Dá para entrar hoje? Devolve null (pode) ou o motivo.
function desafio_motivo_fechado($d, $hoje) {
    if (!$d['ativo']) {
        return 'Este desafio está pausado.';
    }
    if ($d['fim'] && $hoje > $d['fim']) {
        return 'As inscrições deste desafio já terminaram.';
    }
    if ($d['periodo'] === 'ao_entrar' && $d['inicio'] && $hoje < $d['inicio']) {
        return 'Este desafio abre em ' . desafio_data_br($d['inicio']) . '.';
    }
    return null;
}

// Desafios em que o fiel ainda não entrou e que estão abertos (ou abrem logo).
function desafios_abertos($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $hoje = site_hoje();
    $res = mysqli_query($conn, desafios_select_sql() . "
        WHERE d.idds_ativo = 1
          AND (d.idds_fim IS NULL OR d.idds_fim >= DATE '$hoje')
          AND NOT EXISTS (SELECT 1 FROM id_desafio_participante p WHERE p.idds_id = d.idds_id AND p.idf_id = $fiel_id)
        ORDER BY COALESCE(d.idds_inicio, DATE '$hoje') ASC, d.idds_id DESC
    ");
    $lista = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $d = desafio_formatar($linha);
        if (desafio_metrica_disponivel($conn, $d['metrica'])) {
            $d['fechado'] = desafio_motivo_fechado($d, $hoje);
            $lista[] = $d;
        }
    }
    return $lista;
}

// SQL das ações que contam (colunas: dia DATE, item TEXT) do fiel no período.
function desafio_eventos_sql($metrica, $fiel_id, $participacao_id, $inicio, $fim) {
    $fiel_id = (int) $fiel_id;
    $participacao_id = (int) $participacao_id;
    $fuso = DESAFIO_FUSO;
    $entre = function ($coluna) use ($inicio, $fim, $fuso) {
        return "($coluna AT TIME ZONE '$fuso')::date BETWEEN DATE '$inicio' AND DATE '$fim'";
    };
    $dia = function ($coluna) use ($fuso) {
        return "($coluna AT TIME ZONE '$fuso')::date";
    };
    switch ($metrica) {
        case 'aulas':
            // A mesma aula concluída de novo não conta duas vezes no total.
            return "SELECT " . $dia('h.idhv_criado_em') . " AS dia, h.idct_id::text AS item
                FROM id_historico_visualizacao h
                WHERE h.idf_id = $fiel_id AND h.idhv_concluido = 1 AND " . $entre('h.idhv_criado_em');
        case 'dias_app':
            return "SELECT a.idad_data AS dia, a.idad_data::text AS item
                FROM id_fiel_acesso_dia a
                WHERE a.idf_id = $fiel_id AND a.idad_data BETWEEN DATE '$inicio' AND DATE '$fim'";
        case 'comentarios':
            return "SELECT " . $dia('c.idcc_criado_em') . " AS dia, c.idcc_id::text AS item
                FROM id_conteudo_comentario c
                WHERE c.idf_id = $fiel_id AND c.idcc_oculto = 0 AND " . $entre('c.idcc_criado_em');
        case 'resumos':
            return "SELECT " . $dia('r.idrs_criado_em') . " AS dia, r.idrs_id::text AS item
                FROM id_resumo r
                WHERE r.idf_id = $fiel_id AND r.idrs_status = 'publicado' AND " . $entre('r.idrs_criado_em');
        case 'amens':
            return "SELECT " . $dia('a.idoa_criado_em') . " AS dia, a.idor_id::text AS item
                FROM id_oracao_amem a
                WHERE a.idf_id = $fiel_id AND " . $entre('a.idoa_criado_em');
        default: // checkin
            return "SELECT k.iddc_data AS dia, k.iddc_data::text AS item
                FROM id_desafio_checkin k
                WHERE k.iddp_id = $participacao_id AND k.iddc_data BETWEEN DATE '$inicio' AND DATE '$fim'";
    }
}

// Progresso de uma participação (linha de id_desafio_participante + o
// desafio formatado). Devolve: atual, meta, percentual, dias (dia =>
// cumprido?) no modo diário, hoje (quanto fez hoje), situação
// (andamento|concluido|encerrado|aguardando) e se ainda dá para completar.
function desafio_progresso($conn, $d, $p) {
    $hoje = site_hoje();
    $inicio = $p['inicio'];
    $fim = $p['fim'];
    $sql = desafio_eventos_sql($d['metrica'], $p['fiel_id'], $p['id'], $inicio, $fim);
    $por_dia = array();
    $total_distinto = 0;
    if (desafio_metrica_disponivel($conn, $d['metrica'])) {
        $res = mysqli_query($conn, "SELECT dia, COUNT(DISTINCT item) AS n FROM ($sql) ev GROUP BY dia");
        while ($res && $linha = mysqli_fetch_assoc($res)) {
            $por_dia[substr((string) $linha['dia'], 0, 10)] = (int) $linha['n'];
        }
        if ($d['modo'] === 'total') {
            $res = mysqli_query($conn, "SELECT COUNT(DISTINCT item) AS n FROM ($sql) ev");
            $linha = $res ? mysqli_fetch_assoc($res) : null;
            $total_distinto = $linha ? (int) $linha['n'] : 0;
        }
    }
    $meta_dia = desafio_metrica_diaria_unica($d['metrica']) ? 1 : $d['meta'];
    $dias = array();
    $cumpridos = 0;
    // DateTime + 1 dia (e não +86400 s) para não escorregar em horário de verão.
    for ($dia = new DateTime($inicio); $dia->format('Y-m-d') <= $fim; $dia->modify('+1 day')) {
        $data = $dia->format('Y-m-d');
        $ok = ($por_dia[$data] ?? 0) >= $meta_dia;
        $dias[$data] = $ok;
        $cumpridos += $ok ? 1 : 0;
    }
    if ($d['modo'] === 'diario') {
        $atual = $cumpridos;
        $meta = count($dias);
    } else {
        $atual = $total_distinto;
        $meta = $d['meta'];
    }
    $concluido = $p['concluido_em'] !== null || $atual >= $meta;
    if ($concluido) {
        $situacao = 'concluido';
    } elseif ($hoje > $fim) {
        $situacao = 'encerrado';
    } elseif ($hoje < $inicio) {
        $situacao = 'aguardando';
    } else {
        $situacao = 'andamento';
    }
    // Diário: dias que ainda podem ser cumpridos (hoje em diante).
    $restantes = 0;
    foreach ($dias as $data => $ok) {
        if (!$ok && $data >= $hoje) {
            $restantes++;
        }
    }
    $possivel = $d['modo'] === 'diario' ? $cumpridos + $restantes >= $meta : true;
    return array(
        'atual' => min($atual, $meta),
        'meta' => $meta,
        'percentual' => $meta > 0 ? min(100, (int) floor($atual * 100 / $meta)) : 0,
        'dias' => $d['modo'] === 'diario' ? $dias : array(),
        'hoje' => $por_dia[$hoje] ?? 0,
        'meta_dia' => $meta_dia,
        'situacao' => $situacao,
        'possivel' => $possivel,
        'concluido_agora' => $p['concluido_em'] === null && $atual >= $meta,
    );
}

function desafio_participacao_formatar($linha) {
    return array(
        'id' => (int) $linha['iddp_id'],
        'fiel_id' => (int) $linha['idf_id'],
        'desafio_id' => (int) $linha['idds_id'],
        'inicio' => substr((string) $linha['iddp_inicio'], 0, 10),
        'fim' => substr((string) $linha['iddp_fim'], 0, 10),
        // Dia da conclusão no fuso de São Paulo (o servidor pode estar em UTC).
        'concluido_em' => $linha['iddp_concluido_em'] !== null ? (string) $linha['concluido_dia'] : null,
        'entrou_em' => (string) $linha['iddp_entrou_em'],
    );
}

// Desafios do fiel com o progresso, os em andamento primeiro. Marca como
// concluídos os que acabaram de chegar à meta (e avisa).
function desafios_do_fiel($conn, $fiel_id, $so_ativos = false) {
    $fiel_id = (int) $fiel_id;
    $hoje = site_hoje();
    // Ativos: sem conclusão e ainda no período (ou terminado ontem — a
    // última ação do dia ainda conta).
    $filtro = $so_ativos ? "AND p.iddp_concluido_em IS NULL AND p.iddp_fim >= DATE '$hoje' - 1" : '';
    $res = mysqli_query($conn, "
        SELECT p.*, d.*, to_char(p.iddp_concluido_em AT TIME ZONE 'America/Sao_Paulo', 'YYYY-MM-DD') AS concluido_dia,
            (SELECT COUNT(*) FROM id_desafio_participante p2 WHERE p2.idds_id = d.idds_id) AS participantes,
            (SELECT COUNT(*) FROM id_desafio_participante p2 WHERE p2.idds_id = d.idds_id AND p2.iddp_concluido_em IS NOT NULL) AS concluiram
        FROM id_desafio_participante p
        JOIN id_desafio d ON d.idds_id = p.idds_id
        WHERE p.idf_id = $fiel_id $filtro
        ORDER BY p.iddp_concluido_em IS NOT NULL, p.iddp_fim ASC
    ");
    $lista = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $d = desafio_formatar($linha);
        $p = desafio_participacao_formatar($linha);
        $progresso = desafio_progresso($conn, $d, $p);
        if ($progresso['concluido_agora'] && desafio_marcar_concluido($conn, $d, $p)) {
            $p['concluido_em'] = $hoje;
        }
        $lista[] = array('desafio' => $d, 'participacao' => $p, 'progresso' => $progresso);
    }
    // Andamento, aguardando, concluído, encerrado.
    $ordem = array('andamento' => 0, 'aguardando' => 1, 'concluido' => 2, 'encerrado' => 3);
    usort($lista, function ($a, $b) use ($ordem) {
        return $ordem[$a['progresso']['situacao']] - $ordem[$b['progresso']['situacao']];
    });
    return $lista;
}

// Grava a conclusão (uma vez só, mesmo com duas abas) e avisa o fiel.
function desafio_marcar_concluido($conn, $d, $p) {
    $participacao_id = (int) $p['id'];
    $res = mysqli_query($conn, "
        UPDATE id_desafio_participante SET iddp_concluido_em = CURRENT_TIMESTAMP
        WHERE iddp_id = $participacao_id AND iddp_concluido_em IS NULL
        RETURNING iddp_id
    ");
    if (!$res || !mysqli_fetch_assoc($res)) {
        return false;
    }
    $fiel_id = (int) $p['fiel_id'];
    $titulo = 'Desafio concluído! 🏆';
    $mensagem = 'Você completou "' . mb_strimwidth($d['titulo'], 0, 70, '…') . '". Parabéns!';
    $link = 'desafios.php#d-' . (int) $d['id'];
    db_query($conn, "
        INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK)
        VALUES (?, ?, ?, 'desafio', ?)
    ", array($fiel_id, $titulo, $mensagem, $link));
    notificacoes_push_enviar_pendentes($conn, $fiel_id);
    // Medalhas por desafios concluídos (seção 62).
    medalhas_avisar_novas(medalhas_conceder_atividade($conn, $fiel_id, array('desafios_concluidos'), true));
    return true;
}

// Confere os desafios em andamento do fiel (depois de uma ação que conta).
// Barato quando ele não está em nenhum.
function desafios_verificar($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    if ($fiel_id <= 0 || !desafios_disponivel($conn)) {
        return;
    }
    $hoje = site_hoje();
    $res = mysqli_query($conn, "
        SELECT 1 FROM id_desafio_participante
        WHERE idf_id = $fiel_id AND iddp_concluido_em IS NULL AND iddp_fim >= DATE '$hoje' - 1
        LIMIT 1
    ");
    if ($res && mysqli_num_rows($res) > 0) {
        desafios_do_fiel($conn, $fiel_id, true);
    }
}

// Entrar no desafio. Devolve null ou a mensagem de erro.
function desafio_entrar($conn, $fiel_id, $desafio_id) {
    $fiel_id = (int) $fiel_id;
    $d = desafio_obter($conn, $desafio_id);
    if (!$d || !desafio_metrica_disponivel($conn, $d['metrica'])) {
        return 'Desafio não encontrado.';
    }
    $hoje = site_hoje();
    $motivo = desafio_motivo_fechado($d, $hoje);
    if ($motivo) {
        return $motivo;
    }
    if ($d['periodo'] === 'fixo') {
        $inicio = $d['inicio'];
        $fim = $d['fim'];
    } else {
        $inicio = $hoje;
        $fim = date('Y-m-d', strtotime($hoje . ' +' . ($d['dias_total'] - 1) . ' days'));
    }
    $desafio_id = (int) $d['id'];
    $res = mysqli_query($conn, "
        INSERT INTO id_desafio_participante (idds_id, idf_id, iddp_inicio, iddp_fim)
        VALUES ($desafio_id, $fiel_id, DATE '$inicio', DATE '$fim')
        ON CONFLICT (idds_id, idf_id) DO NOTHING
        RETURNING iddp_id
    ");
    if (!$res || !mysqli_fetch_assoc($res)) {
        return 'Você já está neste desafio.';
    }
    return null;
}

// Desistir: apaga a participação (e as marcações). Concluído não sai.
function desafio_desistir($conn, $fiel_id, $desafio_id) {
    $fiel_id = (int) $fiel_id;
    $desafio_id = (int) $desafio_id;
    mysqli_query($conn, "
        DELETE FROM id_desafio_participante
        WHERE idds_id = $desafio_id AND idf_id = $fiel_id AND iddp_concluido_em IS NULL
    ");
    return mysqli_affected_rows($conn) > 0;
}

// "Marquei hoje" (métrica checkin). Devolve null ou a mensagem de erro.
function desafio_checkin($conn, $fiel_id, $desafio_id) {
    $fiel_id = (int) $fiel_id;
    $desafio_id = (int) $desafio_id;
    $hoje = site_hoje();
    $res = mysqli_query($conn, "
        SELECT p.iddp_id FROM id_desafio_participante p
        JOIN id_desafio d ON d.idds_id = p.idds_id
        WHERE p.idds_id = $desafio_id AND p.idf_id = $fiel_id AND d.idds_metrica = 'checkin'
          AND DATE '$hoje' BETWEEN p.iddp_inicio AND p.iddp_fim
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if (!$linha) {
        return 'Não dá para marcar este desafio hoje.';
    }
    $participacao_id = (int) $linha['iddp_id'];
    mysqli_query($conn, "INSERT INTO id_desafio_checkin (iddp_id, iddc_data) VALUES ($participacao_id, DATE '$hoje') ON CONFLICT DO NOTHING");
    return null;
}

// Admin: valida e salva (cria ou edita). Devolve array('erro' => ?, 'id' => ?).
function desafio_salvar($conn, $dados, $admin_fiel_id, $desafio_id = 0) {
    $titulo = trim(preg_replace('/\s+/u', ' ', (string) ($dados['titulo'] ?? '')));
    $descricao = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", (string) ($dados['descricao'] ?? ''))));
    $metrica = (string) ($dados['metrica'] ?? '');
    $modo = ($dados['modo'] ?? '') === 'diario' ? 'diario' : 'total';
    $meta = (int) ($dados['meta'] ?? 0);
    $periodo = ($dados['periodo'] ?? '') === 'fixo' ? 'fixo' : 'ao_entrar';
    $duracao = (int) ($dados['duracao'] ?? 0);
    $valida_data = function ($valor) {
        $valor = trim((string) $valor);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) && strtotime($valor) ? $valor : null;
    };
    $inicio = $valida_data($dados['inicio'] ?? '');
    $fim = $valida_data($dados['fim'] ?? '');

    if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > DESAFIO_TITULO_MAX) {
        return array('erro' => 'Dê um título de 3 a ' . DESAFIO_TITULO_MAX . ' caracteres.');
    }
    if (mb_strlen($descricao) > DESAFIO_DESCRICAO_MAX) {
        return array('erro' => 'A descrição pode ter no máximo ' . DESAFIO_DESCRICAO_MAX . ' caracteres.');
    }
    if (!isset(desafios_metricas()[$metrica])) {
        return array('erro' => 'Escolha o que conta no desafio.');
    }
    if (!desafio_metrica_disponivel($conn, $metrica)) {
        return array('erro' => 'Essa opção ainda não está liberada no banco (rode as seções 59 e 60 do bancosupabase.md).');
    }
    if ($modo === 'diario' && desafio_metrica_diaria_unica($metrica)) {
        $meta = 1; // conta 1 por dia
    }
    if ($meta < 1 || $meta > 1000) {
        return array('erro' => 'A meta precisa ser de 1 a 1000.');
    }
    if ($periodo === 'fixo') {
        if (!$inicio || !$fim || $fim < $inicio) {
            return array('erro' => 'Informe a data de início e a de fim (o fim não pode ser antes do início).');
        }
        $dias = (int) round((strtotime($fim) - strtotime($inicio)) / 86400) + 1;
        if ($dias > 366) {
            return array('erro' => 'O desafio pode durar no máximo 1 ano.');
        }
        $duracao = $dias;
    } else {
        if ($duracao < 1 || $duracao > 366) {
            return array('erro' => 'A duração precisa ser de 1 a 366 dias.');
        }
        if ($inicio && $fim && $fim < $inicio) {
            return array('erro' => 'O fim das inscrições não pode ser antes da abertura.');
        }
    }
    $limite_dias = $modo === 'total' && desafio_metrica_diaria_unica($metrica) ? $duracao : null;
    if ($limite_dias !== null && $meta > $limite_dias) {
        return array('erro' => 'Essa meta conta no máximo 1 por dia: em ' . $duracao . ' dias, o máximo é ' . $duracao . '.');
    }

    $descricao_db = $descricao === '' ? null : $descricao;
    $admin_fiel_id = (int) $admin_fiel_id;
    $desafio_id = (int) $desafio_id;
    if ($desafio_id > 0) {
        $res = db_query($conn, "
            UPDATE id_desafio SET idds_titulo = ?, idds_descricao = ?, idds_metrica = ?,
                idds_modo = ?, idds_meta = ?, idds_periodo = ?, idds_duracao_dias = ?,
                idds_inicio = ?, idds_fim = ?
            WHERE idds_id = ?
            RETURNING idds_id
        ", array($titulo, $descricao_db, $metrica, $modo, $meta, $periodo, $duracao, $inicio, $fim, $desafio_id));
    } else {
        $res = db_query($conn, "
            INSERT INTO id_desafio (idds_titulo, idds_descricao, idds_metrica, idds_modo, idds_meta, idds_periodo, idds_duracao_dias, idds_inicio, idds_fim, idds_criado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING idds_id
        ", array($titulo, $descricao_db, $metrica, $modo, $meta, $periodo, $duracao, $inicio, $fim, $admin_fiel_id));
    }
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if (!$linha) {
        return array('erro' => 'Não foi possível salvar o desafio.');
    }
    // Período fixo editado: quem já está no desafio acompanha as datas novas.
    if ($desafio_id > 0 && $periodo === 'fixo') {
        mysqli_query($conn, "UPDATE id_desafio_participante SET iddp_inicio = DATE '$inicio', iddp_fim = DATE '$fim' WHERE idds_id = $desafio_id AND iddp_concluido_em IS NULL");
    }
    return array('erro' => null, 'id' => (int) $linha['idds_id']);
}

// Aviso no app (sem push, é para todos) de desafio novo.
function desafio_avisar_todos($conn, $d) {
    $titulo = 'Novo desafio: ' . mb_strimwidth($d['titulo'], 0, 60, '…') . ' 🏁';
    $mensagem = $d['meta_texto'] . '. Topa?';
    $link = 'desafios.php#d-' . (int) $d['id'];
    // Cast explícito: no INSERT ... SELECT o Postgres não deduz o tipo do "?".
    db_query($conn, "
        INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK, IDN_PUSH_ENVIADO)
        SELECT f.IDF_ID, CAST(? AS TEXT), CAST(? AS TEXT), 'desafio', CAST(? AS TEXT), 1
        FROM ID_FIEL f WHERE f.IDF_STATUS = 'aprovado' AND f.IDF_ATIVO = 1
    ", array($titulo, $mensagem, $link));
}

// ============================================================================
// DEVOCIONAIS ENVIADOS PELOS FIÉIS (T33, seção 63 do bancosupabase.md)
// ============================================================================
// O fiel envia um devocional (título, texto base, tema e texto) em
// php/fiel/devocional.php. Os líderes (equipe ou função de liderança na
// igreja — a mesma regra do filtro "Líderes" dos comentários) dão nota de
// 1 a 5. Com pelo menos devocional_avaliacoes_minimas notas e média >=
// devocional_nota_minima (configuráveis no admin, id_configuracao), o
// devocional entra sozinho na AGENDA: um por dia, na próxima data livre. O
// "Devocional do dia" é o aprovado com data de hoje (sem ele, o último já
// publicado). O admin (php/admin/admin_devocionais.php) também aprova direto,
// recusa (com motivo), troca a data ou tira do ar.
// Status: avaliacao -> aprovado (com iddv_data) | recusado; removido.

if (!defined('DEVOCIONAL_TITULO_MAX')) {
    define('DEVOCIONAL_TITULO_MAX', 100);
    define('DEVOCIONAL_REFERENCIA_MAX', 60);
    define('DEVOCIONAL_VERSICULO_MAX', 600);
    define('DEVOCIONAL_TEXTO_MIN', 200);
    define('DEVOCIONAL_TEXTO_MAX', 2500);
    define('DEVOCIONAIS_EM_AVALIACAO_MAX', 3);
}

function devocionais_disponivel($conn) {
    return tabela_tem_coluna($conn, 'id_devocional', 'iddv_status')
        && tabela_tem_coluna($conn, 'id_devocional_nota', 'iddn_nota');
}

function devocional_temas() {
    return array('Fé', 'Esperança', 'Amor', 'Família', 'Oração', 'Gratidão', 'Perdão', 'Propósito', 'Ansiedade e paz', 'Santidade', 'Serviço', 'Outro');
}

// Regras configuráveis no admin.
function devocional_nota_minima() {
    $valor = (float) str_replace(',', '.', site_config('devocional_nota_minima', '4'));
    return max(1.0, min(5.0, $valor));
}

function devocional_avaliacoes_minimas() {
    return max(1, min(50, (int) site_config('devocional_avaliacoes_minimas', '3')));
}

// O fiel é líder (pode avaliar)? Equipe (admin) ou função de liderança.
function devocional_eh_lider($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    if ($fiel_id <= 0) {
        return false;
    }
    if (($_SESSION['Usuario_tipo'] ?? '') === 'admin') {
        return true;
    }
    $res = mysqli_query($conn, "SELECT " . video_comentario_lider_sql($conn) . " AS lider FROM ID_FIEL f WHERE f.IDF_ID = $fiel_id");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha && !empty($linha['lider']);
}

function devocionais_select_sql($fiel_id) {
    $fiel_id = (int) $fiel_id;
    return "
        SELECT d.*, f.IDF_NOME,
            EXTRACT(EPOCH FROM d.iddv_criado_em)::bigint AS criado_ts,
            (SELECT COUNT(*) FROM id_devocional_nota n WHERE n.iddv_id = d.iddv_id) AS avaliacoes,
            (SELECT ROUND(AVG(n.iddn_nota)::numeric, 1) FROM id_devocional_nota n WHERE n.iddv_id = d.iddv_id) AS media,
            (SELECT n.iddn_nota FROM id_devocional_nota n WHERE n.iddv_id = d.iddv_id AND n.idf_id = $fiel_id) AS minha_nota
        FROM id_devocional d
        JOIN ID_FIEL f ON f.IDF_ID = d.idf_id";
}

function devocional_formatar($linha, $fiel_id = 0, $eh_admin = false) {
    return array(
        'id' => (int) $linha['iddv_id'],
        'autor_id' => (int) $linha['idf_id'],
        'autor' => $eh_admin ? (string) $linha['IDF_NOME'] : site_nome_curto($linha['IDF_NOME']),
        'meu' => (int) $linha['idf_id'] === (int) $fiel_id,
        'titulo' => (string) $linha['iddv_titulo'],
        'referencia' => (string) $linha['iddv_referencia'],
        'versiculo' => (string) ($linha['iddv_versiculo'] ?? ''),
        'tema' => (string) $linha['iddv_tema'],
        'texto' => (string) $linha['iddv_texto'],
        'status' => (string) $linha['iddv_status'],
        'motivo' => (string) ($linha['iddv_motivo'] ?? ''),
        'data' => $linha['iddv_data'] ? substr((string) $linha['iddv_data'], 0, 10) : null,
        'criado_ts' => (int) $linha['criado_ts'],
        'avaliacoes' => (int) $linha['avaliacoes'],
        'media' => $linha['media'] !== null ? (float) $linha['media'] : null,
        'minha_nota' => $linha['minha_nota'] !== null ? (int) $linha['minha_nota'] : null,
    );
}

function devocional_validar(array $dados) {
    $limpar = function ($texto) {
        return trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", (string) $texto)));
    };
    $d = array(
        'titulo' => trim(preg_replace('/\s+/u', ' ', (string) ($dados['titulo'] ?? ''))),
        'referencia' => trim(preg_replace('/\s+/u', ' ', (string) ($dados['referencia'] ?? ''))),
        'versiculo' => $limpar($dados['versiculo'] ?? ''),
        'tema' => (string) ($dados['tema'] ?? ''),
        'texto' => $limpar($dados['texto'] ?? ''),
    );
    $erro = null;
    if (mb_strlen($d['titulo']) < 5 || mb_strlen($d['titulo']) > DEVOCIONAL_TITULO_MAX) {
        $erro = 'Dê um título de 5 a ' . DEVOCIONAL_TITULO_MAX . ' caracteres.';
    } elseif (mb_strlen($d['referencia']) < 3 || mb_strlen($d['referencia']) > DEVOCIONAL_REFERENCIA_MAX) {
        $erro = 'Informe o texto base (ex.: Salmos 23:1-3).';
    } elseif (mb_strlen($d['versiculo']) > DEVOCIONAL_VERSICULO_MAX) {
        $erro = 'O trecho do versículo pode ter no máximo ' . DEVOCIONAL_VERSICULO_MAX . ' caracteres.';
    } elseif (!in_array($d['tema'], devocional_temas(), true)) {
        $erro = 'Escolha o tema.';
    } elseif (mb_strlen($d['texto']) < DEVOCIONAL_TEXTO_MIN) {
        $erro = 'O devocional precisa de pelo menos ' . DEVOCIONAL_TEXTO_MIN . ' caracteres.';
    } elseif (mb_strlen($d['texto']) > DEVOCIONAL_TEXTO_MAX) {
        $erro = 'O devocional pode ter no máximo ' . DEVOCIONAL_TEXTO_MAX . ' caracteres.';
    }
    $d['erro'] = $erro;
    return $d;
}

// Envia para a avaliação dos líderes. Devolve null ou a mensagem de erro.
function devocional_enviar($conn, $fiel_id, array $dados) {
    $fiel_id = (int) $fiel_id;
    $d = devocional_validar($dados);
    if ($d['erro']) {
        return $d['erro'];
    }
    $res = mysqli_query($conn, "
        SELECT f.IDF_STATUS AS status, f.IDF_ATIVO AS ativo,
            EXISTS(SELECT 1 FROM id_fiel_bloqueio_chat b WHERE b.idf_id = f.IDF_ID AND (b.idbc_expira_em IS NULL OR b.idbc_expira_em > NOW())) AS bloqueado,
            (SELECT COUNT(*) FROM id_devocional d WHERE d.idf_id = f.IDF_ID AND d.iddv_status = 'avaliacao') AS em_avaliacao
        FROM ID_FIEL f WHERE f.IDF_ID = $fiel_id
    ");
    $f = $res ? mysqli_fetch_assoc($res) : null;
    if (!$f || $f['status'] !== 'aprovado' || (int) $f['ativo'] !== 1) {
        return 'Sua conta não está aprovada para enviar devocionais.';
    }
    if (!empty($f['bloqueado'])) {
        return 'Você está impedido de publicar no momento.';
    }
    if ((int) $f['em_avaliacao'] >= DEVOCIONAIS_EM_AVALIACAO_MAX) {
        return 'Você já tem ' . DEVOCIONAIS_EM_AVALIACAO_MAX . ' devocionais em avaliação. Aguarde os líderes avaliarem.';
    }
    $ok = db_query($conn, "
        INSERT INTO id_devocional (idf_id, iddv_titulo, iddv_referencia, iddv_versiculo, iddv_tema, iddv_texto)
        VALUES (?, ?, ?, ?, ?, ?)
    ", array($fiel_id, $d['titulo'], $d['referencia'], $d['versiculo'] === '' ? null : $d['versiculo'], $d['tema'], $d['texto']));
    return $ok ? null : 'Não foi possível enviar. Tente novamente.';
}

function devocional_obter($conn, $devocional_id) {
    $devocional_id = (int) $devocional_id;
    $fiel_id = auth_resolve_fiel_id();
    $res = mysqli_query($conn, devocionais_select_sql($fiel_id) . " WHERE d.iddv_id = $devocional_id");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? devocional_formatar($linha, $fiel_id, ($_SESSION['Usuario_tipo'] ?? '') === 'admin') : null;
}

// Devocional do dia: o agendado para hoje; sem ele, o último publicado.
function devocional_do_dia($conn) {
    $hoje = site_hoje();
    $fiel_id = auth_resolve_fiel_id();
    $res = mysqli_query($conn, devocionais_select_sql($fiel_id) . "
        WHERE d.iddv_status = 'aprovado' AND d.iddv_data <= DATE '$hoje'
        ORDER BY d.iddv_data DESC LIMIT 1
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? devocional_formatar($linha, $fiel_id) : null;
}

// Publicados (data até hoje), do mais novo para o mais antigo.
function devocionais_publicados($conn, $limite = 14, $pular = 0) {
    $hoje = site_hoje();
    $fiel_id = auth_resolve_fiel_id();
    $limite = max(1, (int) $limite);
    $pular = max(0, (int) $pular);
    $res = mysqli_query($conn, devocionais_select_sql($fiel_id) . "
        WHERE d.iddv_status = 'aprovado' AND d.iddv_data <= DATE '$hoje'
        ORDER BY d.iddv_data DESC LIMIT $limite OFFSET $pular
    ");
    $lista = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $lista[] = devocional_formatar($linha, $fiel_id);
    }
    return $lista;
}

function devocionais_do_fiel($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $res = mysqli_query($conn, devocionais_select_sql($fiel_id) . "
        WHERE d.idf_id = $fiel_id AND d.iddv_status <> 'removido'
        ORDER BY d.iddv_id DESC LIMIT 50
    ");
    $lista = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $lista[] = devocional_formatar($linha, $fiel_id);
    }
    return $lista;
}

// Fila dos líderes: em avaliação, de outras pessoas; os que o líder ainda
// não avaliou primeiro, e os mais antigos antes.
function devocionais_para_avaliar($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $res = mysqli_query($conn, devocionais_select_sql($fiel_id) . "
        WHERE d.iddv_status = 'avaliacao' AND d.idf_id <> $fiel_id
        ORDER BY EXISTS(SELECT 1 FROM id_devocional_nota n WHERE n.iddv_id = d.iddv_id AND n.idf_id = $fiel_id), d.iddv_id ASC
        LIMIT 50
    ");
    $lista = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $lista[] = devocional_formatar($linha, $fiel_id);
    }
    return $lista;
}

// Próxima data livre na agenda, a partir de hoje.
function devocional_proxima_data_livre($conn) {
    $hoje = site_hoje();
    $res = mysqli_query($conn, "
        SELECT iddv_data FROM id_devocional
        WHERE iddv_status = 'aprovado' AND iddv_data >= DATE '$hoje'
        ORDER BY iddv_data
    ");
    $ocupadas = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $ocupadas[substr((string) $linha['iddv_data'], 0, 10)] = true;
    }
    $dia = new DateTime($hoje);
    while (isset($ocupadas[$dia->format('Y-m-d')])) {
        $dia->modify('+1 day');
    }
    return $dia->format('Y-m-d');
}

// Aprova e agenda na próxima data livre (o índice único garante um por dia;
// se outra aprovação pegou a mesma data no mesmo instante, tenta a seguinte).
// Avisa o autor. Devolve a data ou null.
function devocional_aprovar($conn, $devocional_id, $de_status = "'avaliacao'") {
    $devocional_id = (int) $devocional_id;
    for ($tentativa = 0; $tentativa < 3; $tentativa++) {
        $data = devocional_proxima_data_livre($conn);
        $res = mysqli_query($conn, "
            UPDATE id_devocional SET iddv_status = 'aprovado', iddv_data = DATE '$data', iddv_motivo = NULL, iddv_atualizado_em = CURRENT_TIMESTAMP
            WHERE iddv_id = $devocional_id AND iddv_status IN ($de_status)
            RETURNING idf_id, iddv_titulo
        ");
        $linha = $res ? mysqli_fetch_assoc($res) : null;
        if ($linha) {
            $autor_id = (int) $linha['idf_id'];
            $quando = $data === site_hoje() ? 'hoje' : 'em ' . date('d/m', strtotime($data));
            $titulo = 'Seu devocional foi aprovado! 🙌';
            $mensagem = '"' . mb_strimwidth((string) $linha['iddv_titulo'], 0, 60, '…') . '" será o devocional do dia ' . $quando . '.';
            db_query($conn, "
                INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK)
                VALUES (?, ?, ?, 'devocional', 'devocional.php#meus')
            ", array($autor_id, $titulo, $mensagem));
            notificacoes_push_enviar_pendentes($conn, $autor_id);
            return $data;
        }
        // Sem linha: ou não estava mais no status esperado, ou a data foi
        // pega por outro ao mesmo tempo (o índice único recusou).
        $res = mysqli_query($conn, "SELECT 1 FROM id_devocional WHERE iddv_id = $devocional_id AND iddv_status IN ($de_status)");
        if (!$res || mysqli_num_rows($res) === 0) {
            return null;
        }
    }
    return null;
}

// Nota do líder (1 a 5; 0 = tira a nota). Depois confere se entrou na agenda.
// Devolve array('ok', 'erro', 'aprovado_em').
function devocional_avaliar($conn, $fiel_id, $devocional_id, $nota) {
    $fiel_id = (int) $fiel_id;
    $devocional_id = (int) $devocional_id;
    $nota = (int) $nota;
    if (!devocional_eh_lider($conn, $fiel_id)) {
        return array('ok' => false, 'erro' => 'Só líderes avaliam devocionais.');
    }
    $d = devocional_obter($conn, $devocional_id);
    if (!$d || $d['status'] !== 'avaliacao') {
        return array('ok' => false, 'erro' => 'Este devocional não está mais em avaliação.');
    }
    if ($d['autor_id'] === $fiel_id) {
        return array('ok' => false, 'erro' => 'Você não pode avaliar o próprio devocional.');
    }
    if ($nota === 0) {
        mysqli_query($conn, "DELETE FROM id_devocional_nota WHERE iddv_id = $devocional_id AND idf_id = $fiel_id");
        return array('ok' => true, 'erro' => null, 'aprovado_em' => null);
    }
    if ($nota < 1 || $nota > 5) {
        return array('ok' => false, 'erro' => 'Nota inválida.');
    }
    mysqli_query($conn, "
        INSERT INTO id_devocional_nota (iddv_id, idf_id, iddn_nota) VALUES ($devocional_id, $fiel_id, $nota)
        ON CONFLICT (iddv_id, idf_id) DO UPDATE SET iddn_nota = EXCLUDED.iddn_nota, iddn_atualizado_em = CURRENT_TIMESTAMP
    ");
    $res = mysqli_query($conn, "SELECT COUNT(*) AS n, AVG(iddn_nota) AS media FROM id_devocional_nota WHERE iddv_id = $devocional_id");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    $aprovado_em = null;
    if ($linha && (int) $linha['n'] >= devocional_avaliacoes_minimas() && (float) $linha['media'] >= devocional_nota_minima() - 0.0001) {
        $aprovado_em = devocional_aprovar($conn, $devocional_id);
    }
    return array('ok' => true, 'erro' => null, 'aprovado_em' => $aprovado_em);
}

// Admin: lista por status (avaliacao, agendado, publicado, recusado, removido).
function devocionais_admin_listar($conn, $aba) {
    $hoje = site_hoje();
    $fiel_id = auth_resolve_fiel_id();
    $filtros = array(
        'avaliacao' => array("d.iddv_status = 'avaliacao'", 'd.iddv_id ASC'),
        'agendado' => array("d.iddv_status = 'aprovado' AND d.iddv_data > DATE '$hoje'", 'd.iddv_data ASC'),
        'publicado' => array("d.iddv_status = 'aprovado' AND d.iddv_data <= DATE '$hoje'", 'd.iddv_data DESC'),
        'recusado' => array("d.iddv_status = 'recusado'", 'd.iddv_atualizado_em DESC'),
        'removido' => array("d.iddv_status = 'removido'", 'd.iddv_atualizado_em DESC'),
    );
    list($onde, $ordem) = $filtros[$aba] ?? $filtros['avaliacao'];
    $res = mysqli_query($conn, devocionais_select_sql($fiel_id) . " WHERE $onde ORDER BY $ordem LIMIT 200");
    $lista = array();
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $lista[] = devocional_formatar($linha, $fiel_id, true);
    }
    return $lista;
}

function devocionais_admin_contar($conn) {
    $hoje = site_hoje();
    $res = mysqli_query($conn, "
        SELECT
            COUNT(*) FILTER (WHERE iddv_status = 'avaliacao') AS avaliacao,
            COUNT(*) FILTER (WHERE iddv_status = 'aprovado' AND iddv_data > DATE '$hoje') AS agendado,
            COUNT(*) FILTER (WHERE iddv_status = 'aprovado' AND iddv_data <= DATE '$hoje') AS publicado,
            COUNT(*) FILTER (WHERE iddv_status = 'recusado') AS recusado,
            COUNT(*) FILTER (WHERE iddv_status = 'removido') AS removido
        FROM id_devocional
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return array_map('intval', $linha ?: array('avaliacao' => 0, 'agendado' => 0, 'publicado' => 0, 'recusado' => 0, 'removido' => 0));
}

// Admin: recusar (com motivo, o autor vê), tirar do ar, devolver para a
// avaliação ou trocar a data. Devolve null ou a mensagem de erro.
function devocional_moderar($conn, $devocional_id, $acao, $motivo = '', $data = '') {
    $devocional_id = (int) $devocional_id;
    if ($acao === 'aprovar') {
        return devocional_aprovar($conn, $devocional_id, "'avaliacao', 'recusado'") ? null : 'Não foi possível aprovar (já foi moderado?).';
    }
    if ($acao === 'recusar') {
        $motivo = trim(preg_replace('/\s+/u', ' ', (string) $motivo));
        if ($motivo === '') {
            return 'Escreva o motivo da recusa — o autor vai ver.';
        }
        $res = db_query($conn, "
            UPDATE id_devocional SET iddv_status = 'recusado', iddv_motivo = ?, iddv_data = NULL, iddv_atualizado_em = CURRENT_TIMESTAMP
            WHERE iddv_id = ? AND iddv_status IN ('avaliacao', 'aprovado')
            RETURNING idf_id, iddv_titulo
        ", array(mb_substr($motivo, 0, 255), $devocional_id));
        $linha = $res ? mysqli_fetch_assoc($res) : null;
        if (!$linha) {
            return 'Devocional não encontrado.';
        }
        $autor_id = (int) $linha['idf_id'];
        $titulo = 'Seu devocional não foi publicado';
        $mensagem = '"' . mb_strimwidth((string) $linha['iddv_titulo'], 0, 60, '…') . '": ' . $motivo;
        db_query($conn, "
            INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK)
            VALUES (?, ?, ?, 'devocional', 'devocional.php#meus')
        ", array($autor_id, $titulo, $mensagem));
        notificacoes_push_enviar_pendentes($conn, $autor_id);
        return null;
    }
    if ($acao === 'remover') {
        mysqli_query($conn, "UPDATE id_devocional SET iddv_status = 'removido', iddv_data = NULL, iddv_atualizado_em = CURRENT_TIMESTAMP WHERE iddv_id = $devocional_id AND iddv_status <> 'removido'");
        return mysqli_affected_rows($conn) > 0 ? null : 'Devocional não encontrado.';
    }
    if ($acao === 'reavaliar') {
        mysqli_query($conn, "UPDATE id_devocional SET iddv_status = 'avaliacao', iddv_data = NULL, iddv_atualizado_em = CURRENT_TIMESTAMP WHERE iddv_id = $devocional_id AND iddv_status = 'aprovado'");
        return mysqli_affected_rows($conn) > 0 ? null : 'Devocional não encontrado.';
    }
    if ($acao === 'data') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data) || $data < site_hoje()) {
            return 'Escolha uma data de hoje em diante.';
        }
        $res = mysqli_query($conn, "SELECT 1 FROM id_devocional WHERE iddv_status = 'aprovado' AND iddv_data = DATE '$data' AND iddv_id <> $devocional_id");
        if ($res && mysqli_num_rows($res) > 0) {
            return 'Já existe um devocional agendado para ' . date('d/m/Y', strtotime($data)) . '.';
        }
        mysqli_query($conn, "UPDATE id_devocional SET iddv_data = DATE '$data', iddv_atualizado_em = CURRENT_TIMESTAMP WHERE iddv_id = $devocional_id AND iddv_status = 'aprovado'");
        return mysqli_affected_rows($conn) > 0 ? null : 'Não foi possível trocar a data.';
    }
    return 'Ação inválida.';
}

// O autor tira o próprio devocional (ainda em avaliação ou recusado).
function devocional_remover_proprio($conn, $fiel_id, $devocional_id) {
    $fiel_id = (int) $fiel_id;
    $devocional_id = (int) $devocional_id;
    mysqli_query($conn, "
        UPDATE id_devocional SET iddv_status = 'removido', iddv_atualizado_em = CURRENT_TIMESTAMP
        WHERE iddv_id = $devocional_id AND idf_id = $fiel_id AND iddv_status IN ('avaliacao', 'recusado')
    ");
    return mysqli_affected_rows($conn) > 0;
}

// Catálogo das medalhas ativas, na ordem de exibição: [id => array(...)].
function medalhas_listar($conn) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = array();
    if (!medalhas_disponivel($conn)) {
        return $cache;
    }
    // idbadge_regra_valor2 vem da seção 61 (T30); antes dela, fica 0.
    $coluna_valor2 = tabela_tem_coluna($conn, 'id_badge', 'idbadge_regra_valor2') ? 'idbadge_regra_valor2' : '0';
    // idbadge_secreta vem da seção 81; antes dela, nenhuma é secreta.
    $coluna_secreta = tabela_tem_coluna($conn, 'id_badge', 'idbadge_secreta') ? 'idbadge_secreta' : '0';
    $res = mysqli_query($conn, "
        SELECT idbadge_id, idbadge_chave, idbadge_nome, idbadge_descricao, idbadge_icone,
               idbadge_categoria, idbadge_nivel, idbadge_regra, idbadge_regra_valor, $coluna_valor2 AS valor2, $coluna_secreta AS secreta
        FROM id_badge
        WHERE idbadge_ativo = 1 AND idbadge_regra IS NOT NULL
        ORDER BY idbadge_categoria, idbadge_ordem, idbadge_regra_valor
    ");
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $cache[(int) $linha['idbadge_id']] = array(
            'id' => (int) $linha['idbadge_id'],
            'chave' => (string) $linha['idbadge_chave'],
            'nome' => (string) $linha['idbadge_nome'],
            'descricao' => (string) $linha['idbadge_descricao'],
            'icone' => preg_match('/^fa-[a-z0-9-]+$/', (string) $linha['idbadge_icone']) ? $linha['idbadge_icone'] : 'fa-medal',
            'categoria' => (string) $linha['idbadge_categoria'],
            'nivel' => isset(medalhas_niveis()[$linha['idbadge_nivel']]) ? $linha['idbadge_nivel'] : 'bronze',
            'regra' => (string) $linha['idbadge_regra'],
            'valor' => (int) $linha['idbadge_regra_valor'],
            'valor2' => (int) $linha['valor2'],
            // Secreta: aparece como "???" (sem nome nem dica) até ser conquistada.
            'secreta' => (int) $linha['secreta'] === 1,
        );
    }
    return $cache;
}

// ============================================================================
// SELOS NO PERFIL E NOS COMENTÁRIOS (T24, 1ª parte)
// ============================================================================
// Montados com o que já existe: função na igreja (T12, menos "Membro", que é
// o padrão), tempo de membresia (T08) e a maior medalha conquistada (T14).
// Igreja, célula (T22) e títulos como "Discípula fiel" entram aqui quando o
// cliente definir (docs/para_o_cliente/pontos_para_alinhar.md, seção 4).

// Subconsulta SQL (autor no alias f) com a maior medalha: "nome|icone|nivel".
// Maior = nível mais alto e, no mesmo nível, a de regra maior.
function selos_medalha_topo_sql() {
    global $conn;
    // Vitrine (seção 81): a primeira medalha que o fiel escolheu destacar
    // vem antes da maior (só se ele ainda tiver a medalha).
    $vitrine = fiel_vitrine_disponivel($conn)
        ? "CASE WHEN b.idbadge_id::text = split_part(COALESCE(f.idf_medalhas_destaque, ''), ',', 1) THEN 0 ELSE 1 END,
                 "
        : '';
    return "(SELECT b.idbadge_nome || '|' || COALESCE(b.idbadge_icone, 'fa-medal') || '|' || COALESCE(b.idbadge_nivel, 'bronze')
        FROM id_fiel_badge fb
        JOIN id_badge b ON b.idbadge_id = fb.idbadge_id AND b.idbadge_ativo = 1
        WHERE fb.idf_id = f.IDF_ID
        ORDER BY {$vitrine}CASE b.idbadge_nivel WHEN 'diamante' THEN 4 WHEN 'ouro' THEN 3 WHEN 'prata' THEN 2 ELSE 1 END DESC,
                 b.idbadge_regra_valor DESC NULLS LAST
        LIMIT 1)";
}

// Subconsulta SQL (autor no alias f) com o nome da função na igreja.
function selos_funcao_sql() {
    return "(SELECT fn.idfn_nome FROM id_funcao_igreja fn WHERE fn.idfn_id = f.idf_funcao_id)";
}

// "nome|icone|nivel" -> array('nome', 'icone', 'nivel', 'nivel_nome') ou null.
function selos_medalha_formatar($valor) {
    $partes = explode('|', (string) $valor);
    if (count($partes) !== 3 || trim($partes[0]) === '') {
        return null;
    }
    $niveis = medalhas_niveis();
    $nivel = isset($niveis[$partes[2]]) ? $partes[2] : 'bronze';
    return array(
        'nome' => $partes[0],
        'icone' => preg_match('/^fa-[a-z0-9-]+$/', $partes[1]) ? $partes[1] : 'fa-medal',
        'nivel' => $nivel,
        'nivel_nome' => $niveis[$nivel],
    );
}

// Função que vira selo: qualquer uma, menos a padrão "Membro".
function selos_funcao_texto($nome) {
    $nome = trim((string) $nome);
    return ($nome === '' || mb_strtolower($nome) === 'membro') ? '' : $nome;
}

// Selos do topo do perfil, na ordem de exibição:
// [array('tipo' => 'funcao'|'membro'|'medalha', 'texto', 'icone', 'nivel'?)].
function fiel_selos($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $selos = array();
    if ($fiel_id <= 0) {
        return $selos;
    }
    $colunas = array();
    if (funcoes_igreja_disponivel($conn)) {
        $colunas[] = selos_funcao_sql() . ' AS funcao_nome';
        $colunas[] = "(SELECT fn.idfn_lider FROM id_funcao_igreja fn WHERE fn.idfn_id = f.idf_funcao_id) AS funcao_lider";
    }
    if (fiel_membro_disponivel($conn)) {
        $colunas[] = 'f.IDF_MEMBRO AS membro, f.IDF_MEMBRO_DESDE AS membro_desde';
    }
    if (medalhas_disponivel($conn)) {
        $colunas[] = selos_medalha_topo_sql() . ' AS medalha_topo';
    }
    if (!$colunas) {
        return $selos;
    }
    $res = mysqli_query($conn, "SELECT " . implode(', ', $colunas) . " FROM ID_FIEL f WHERE f.IDF_ID = $fiel_id LIMIT 1");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if (!$linha) {
        return $selos;
    }
    $funcao = selos_funcao_texto($linha['funcao_nome'] ?? '');
    if ($funcao !== '') {
        $selos[] = array('tipo' => 'funcao', 'texto' => $funcao, 'icone' => !empty($linha['funcao_lider']) ? 'fa-user-tie' : 'fa-hands-helping');
    }
    if ((int) ($linha['membro'] ?? 0) === 1 && !empty($linha['membro_desde'])) {
        $selos[] = array('tipo' => 'membro', 'texto' => 'Membro há ' . fiel_tempo_membresia($linha['membro_desde']), 'icone' => 'fa-church');
    }
    $medalha = selos_medalha_formatar($linha['medalha_topo'] ?? '');
    if ($medalha) {
        $selos[] = array('tipo' => 'medalha', 'texto' => $medalha['nome'], 'icone' => $medalha['icone'], 'nivel' => $medalha['nivel'], 'titulo' => 'Maior medalha: ' . $medalha['nome'] . ' (' . $medalha['nivel_nome'] . ')');
    }
    return $selos;
}

// Medalhas conquistadas pelo fiel: [idbadge_id => 'AAAA-MM-DD ...'].
// Guardado por requisição (o Perfil lia 3 vezes, a Home 2); quem concede
// medalha nova limpa o guardado (ver os INSERT INTO id_fiel_badge).
function medalhas_do_fiel($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $conquistadas = array();
    if ($fiel_id <= 0 || !medalhas_disponivel($conn)) {
        return $conquistadas;
    }
    if (isset($GLOBALS['site_cache_medalhas'][$fiel_id])) {
        return $GLOBALS['site_cache_medalhas'][$fiel_id];
    }
    $res = mysqli_query($conn, "SELECT idbadge_id, idfb_desbloqueada_em FROM id_fiel_badge WHERE idf_id = $fiel_id");
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $conquistadas[(int) $linha['idbadge_id']] = (string) $linha['idfb_desbloqueada_em'];
    }
    if ($res) {
        $GLOBALS['site_cache_medalhas'][$fiel_id] = $conquistadas;
    }
    return $conquistadas;
}

// Concede as medalhas de ofensiva que o recorde já alcança e ainda não foram
// dadas. Devolve as medalhas NOVAS (para o aviso "Nova medalha!").
function medalhas_conceder_ofensiva($conn, $fiel_id, $recorde) {
    $fiel_id = (int) $fiel_id;
    $recorde = (int) $recorde;
    $novas = array();
    if ($fiel_id <= 0 || $recorde <= 0) {
        return $novas;
    }
    $catalogo = medalhas_listar($conn);
    if (!$catalogo) {
        return $novas;
    }
    $ja_tem = medalhas_do_fiel($conn, $fiel_id);
    foreach ($catalogo as $id => $medalha) {
        if ($medalha['regra'] !== 'ofensiva_dias' || $medalha['valor'] > $recorde || isset($ja_tem[$id])) {
            continue;
        }
        // RETURNING só devolve linha se inseriu — duas abas ao mesmo tempo
        // não dão a mesma medalha "nova" duas vezes.
        unset($GLOBALS['site_cache_medalhas'][$fiel_id]); // medalhas_do_fiel() relê
        $res = mysqli_query($conn, "INSERT INTO id_fiel_badge (idf_id, idbadge_id) VALUES ($fiel_id, $id) ON CONFLICT (idf_id, idbadge_id) DO NOTHING RETURNING idbadge_id");
        if ($res && mysqli_num_rows($res) > 0) {
            $novas[$id] = $medalha;
        }
    }
    return $novas;
}

// Guarda as medalhas novas na sessão para a navbar mostrar o aviso uma vez.
function medalhas_avisar_novas(array $novas) {
    if (!$novas) {
        return;
    }
    // A comemoração (navbar_fiel.php) mostra a medalha inteira: guarda o
    // que ela precisa, não só o nome.
    $guardar = array();
    foreach ($novas as $m) {
        $guardar[] = array(
            'id' => (int) ($m['id'] ?? 0), 'nome' => (string) ($m['nome'] ?? ''), 'icone' => (string) ($m['icone'] ?? 'fa-medal'),
            'nivel' => (string) ($m['nivel'] ?? 'bronze'), 'descricao' => (string) ($m['descricao'] ?? ''), 'secreta' => !empty($m['secreta']),
        );
    }
    $_SESSION['medalhas_novas'] = array_slice(array_merge($_SESSION['medalhas_novas'] ?? array(), $guardar), 0, 12);
}

// ---- Ostentar as medalhas (seção 81) ----

// Raridade: % dos fiéis aprovados que têm cada medalha (idbadge_id => %).
// Guardada na sessão por 10 minutos — é uma contagem da tabela inteira.
function medalhas_raridade($conn) {
    $cache = $_SESSION['medalhas_raridade'] ?? null;
    if (is_array($cache) && ($cache['ate'] ?? 0) > time()) {
        return $cache['dados'];
    }
    $dados = array();
    if (medalhas_disponivel($conn)) {
        $res = mysqli_query($conn, "
            SELECT fb.idbadge_id, COUNT(*) AS qtd,
                   (SELECT COUNT(*) FROM id_fiel f WHERE f.idf_status = 'aprovado') AS total
            FROM id_fiel_badge fb
            GROUP BY fb.idbadge_id
        ");
        while ($res && $l = mysqli_fetch_assoc($res)) {
            $total = max(1, (int) $l['total']);
            $dados[(int) $l['idbadge_id']] = min(100, round((int) $l['qtd'] * 100 / $total, 1));
        }
    }
    $_SESSION['medalhas_raridade'] = array('ate' => time() + 600, 'dados' => $dados);
    return $dados;
}

// "Rara: 4% dos fiéis têm" / "18% dos fiéis têm" / "Ninguém tem ainda".
function medalhas_raridade_texto($pct) {
    if ($pct === null || $pct <= 0) {
        return 'Ninguém tem ainda — seja o primeiro!';
    }
    $numero = $pct < 1 ? 'menos de 1' : number_format($pct, $pct < 10 ? 1 : 0, ',', '.');
    $numero = preg_replace('/,0$/', '', $numero);
    return ($pct < 10 ? 'Rara · ' : '') . $numero . '% dos fiéis têm';
}

function fiel_vitrine_disponivel($conn) {
    return $conn && tabela_tem_coluna($conn, 'id_fiel', 'idf_medalhas_destaque');
}

// Vitrine: até 3 medalhas que o fiel escolheu mostrar (ids, na ordem). A
// primeira vira o selo ao lado do nome nos comentários.
if (!defined('FIEL_VITRINE_MAX')) {
    define('FIEL_VITRINE_MAX', 3);
}
function fiel_vitrine($conn, $fiel_id) {
    if (!fiel_vitrine_disponivel($conn) || (int) $fiel_id <= 0) {
        return array();
    }
    $res = db_query($conn, "SELECT idf_medalhas_destaque FROM id_fiel WHERE idf_id = ? LIMIT 1", array((int) $fiel_id));
    $texto = (string) (($res ? mysqli_fetch_assoc($res) : null)['idf_medalhas_destaque'] ?? '');
    $conquistadas = medalhas_do_fiel($conn, $fiel_id);
    $ids = array();
    foreach (explode(',', $texto) as $id) {
        $id = (int) $id;
        if ($id > 0 && isset($conquistadas[$id]) && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    return array_slice($ids, 0, FIEL_VITRINE_MAX);
}

// Põe ou tira uma medalha da vitrine. Devolve '' ou o erro.
function fiel_vitrine_alternar($conn, $fiel_id, $badge_id) {
    $badge_id = (int) $badge_id;
    if (!fiel_vitrine_disponivel($conn)) {
        return 'A vitrine ainda não está disponível.';
    }
    if (!isset(medalhas_do_fiel($conn, $fiel_id)[$badge_id])) {
        return 'Você ainda não tem essa medalha.';
    }
    $ids = fiel_vitrine($conn, $fiel_id);
    if (in_array($badge_id, $ids, true)) {
        $ids = array_values(array_diff($ids, array($badge_id)));
    } elseif (count($ids) >= FIEL_VITRINE_MAX) {
        return 'A vitrine já tem ' . FIEL_VITRINE_MAX . ' medalhas. Tire uma antes de pôr outra.';
    } else {
        $ids[] = $badge_id;
    }
    $ok = db_query($conn, "UPDATE id_fiel SET idf_medalhas_destaque = ? WHERE idf_id = ?", array($ids ? implode(',', $ids) : null, (int) $fiel_id));
    return $ok ? '' : 'Não foi possível salvar a vitrine.';
}

// Últimas conquistas da comunidade (faixa da Home): nome curto, foto e a
// medalha. As secretas saem sem nome ("uma medalha secreta"). Só fiéis
// aprovados e ativos; medalhas ativas.
function medalhas_conquistas_recentes($conn, $limite = 8) {
    if (!medalhas_disponivel($conn)) {
        return array();
    }
    $coluna_secreta = tabela_tem_coluna($conn, 'id_badge', 'idbadge_secreta') ? 'b.idbadge_secreta' : '0';
    // Versão da foto na mesma consulta (como a lista de quem está online):
    // sem uma consulta por pessoa.
    $coluna_foto = fiel_foto_disponivel($conn) ? ",
               (SELECT EXTRACT(EPOCH FROM ff.idff_atualizado_em)::bigint FROM id_fiel_foto ff WHERE ff.idf_id = fb.idf_id) AS foto_versao" : '';
    // Vai para a Home de todos os fiéis: sem menores (LGPD art. 14) e sem a
    // equipe interna, como a lista de online e o ranking.
    $filtro = tabela_tem_coluna($conn, 'id_fiel', 'idf_nascimento')
        ? "AND (f.idf_nascimento IS NULL OR f.idf_nascimento <= (NOW() AT TIME ZONE 'America/Sao_Paulo')::date - INTERVAL '" . (int) LGPD_MAIORIDADE . " years')"
        : '';
    $params = array();
    $superadmins = superadmin_emails();
    if ($superadmins && !auth_eh_superadmin()) {
        $filtro .= ' AND LOWER(f.idf_email) NOT IN (' . implode(', ', array_fill(0, count($superadmins), '?')) . ')';
        $params = $superadmins;
    }
    $res = db_query($conn, "
        SELECT fb.idf_id, f.IDF_NOME, fb.idfb_desbloqueada_em,
               b.idbadge_nome, b.idbadge_icone, b.idbadge_nivel, $coluna_secreta AS secreta$coluna_foto
        FROM id_fiel_badge fb
        JOIN id_badge b ON b.idbadge_id = fb.idbadge_id AND b.idbadge_ativo = 1
        JOIN id_fiel f ON f.idf_id = fb.idf_id AND f.idf_status = 'aprovado' AND COALESCE(f.idf_ativo, 1) = 1
        WHERE 1 = 1 $filtro
        ORDER BY fb.idfb_desbloqueada_em DESC
        LIMIT " . (int) $limite, $params);
    $lista = array();
    while ($res && $l = mysqli_fetch_assoc($res)) {
        $secreta = (int) $l['secreta'] === 1;
        $lista[] = array(
            'fiel_id' => (int) $l['idf_id'],
            'nome' => site_nome_curto($l['IDF_NOME']),
            'foto' => !empty($l['foto_versao']) ? 'foto_perfil.php?id=' . (int) $l['idf_id'] . '&v=' . (int) $l['foto_versao'] : null,
            'medalha' => $secreta ? 'uma medalha secreta 🤫' : (string) $l['idbadge_nome'],
            'icone' => $secreta ? 'fa-user-secret' : (preg_match('/^fa-[a-z0-9-]+$/', (string) $l['idbadge_icone']) ? $l['idbadge_icone'] : 'fa-medal'),
            'nivel' => isset(medalhas_niveis()[$l['idbadge_nivel']]) ? $l['idbadge_nivel'] : 'bronze',
            'quando' => (string) $l['idfb_desbloqueada_em'],
        );
    }
    return $lista;
}

// Próxima medalha de ofensiva ainda bloqueada (a de menor meta), ou null.
function medalhas_proxima_ofensiva($conn, array $conquistadas) {
    foreach (medalhas_listar($conn) as $id => $medalha) {
        if ($medalha['regra'] === 'ofensiva_dias' && !isset($conquistadas[$id])) {
            return $medalha;
        }
    }
    return null;
}

// ============================================================================
// CERTIFICADO DE BOAS-VINDAS
// ============================================================================
// Certificado simbólico dado a todo fiel aprovado, só de boas-vindas — não é
// vinculado a um curso de verdade. Como ID_CERTIFICADO exige um IDC_ID válido
// (FK NOT NULL), ele fica pendurado num curso técnico chamado "Boas-vindas"
// com IDC_STATUS = 'RASCUNHO' (nunca aparece no catálogo do fiel, que só
// lista IDC_STATUS = 'PUBLICADO'). Esse curso + o certificado para os fiéis
// que já existiam antes desta feature são criados via SQL direto — ver seção
// "CERTIFICADO DE BOAS-VINDAS" em database/bancosupabase.md.
function certificado_boas_vindas_curso_id($conn) {
    static $idc_id = null;
    if ($idc_id !== null) {
        return $idc_id;
    }

    $res = mysqli_query($conn, "SELECT IDC_ID FROM ID_CURSO WHERE IDC_TITULO = 'Boas-vindas' LIMIT 1");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    $idc_id = $linha ? (int) $linha['IDC_ID'] : 0;

    return $idc_id;
}

// Concede o certificado de boas-vindas a um fiel, se ele ainda não tiver.
// Chamado quando o cadastro é aprovado (admin_aprovar_usuarios.php) — os
// fiéis que já existiam antes dessa feature recebem o certificado direto via
// SQL (ver bancosupabase.md), não por aqui. Idempotente: pode chamar de novo
// sem duplicar.
function certificado_boas_vindas_conceder($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $curso_id = certificado_boas_vindas_curso_id($conn);

    if ($fiel_id <= 0 || $curso_id <= 0) {
        return false;
    }

    $sql_existe = "SELECT IDCERT_ID FROM ID_CERTIFICADO WHERE IDF_ID = $fiel_id AND IDC_ID = $curso_id LIMIT 1";
    $res_existe = mysqli_query($conn, $sql_existe);
    if ($res_existe && mysqli_num_rows($res_existe) > 0) {
        return true;
    }

    $sql_insert = "INSERT INTO ID_CERTIFICADO (IDF_ID, IDC_ID, IDCERT_NUMERO, IDCERT_DATA_CONCLUSAO) VALUES (?, ?, ?, NOW())";

    return (bool) @db_query($conn, $sql_insert, array($fiel_id, $curso_id, 'BV-' . $fiel_id));
}

// Largura (em milésimos do tamanho da fonte) de um texto Windows-1252 na
// Times-Roman, uma das 14 fontes padrão do PDF (não precisa embutir). Letra
// acentuada tem a largura da letra sem acento.
function certificado_pdf_largura_times($texto_1252) {
    static $larguras = null;
    if ($larguras === null) {
        $larguras = array(' ' => 250, '!' => 333, '"' => 408, '&' => 778, "'" => 333, '(' => 333, ')' => 333, ',' => 250, '-' => 333, '.' => 250, '/' => 278, ':' => 278, ';' => 278, '?' => 444,
            'A' => 722, 'B' => 667, 'C' => 667, 'D' => 722, 'E' => 611, 'F' => 556, 'G' => 722, 'H' => 722, 'I' => 333, 'J' => 389, 'K' => 722, 'L' => 611, 'M' => 889,
            'N' => 722, 'O' => 722, 'P' => 556, 'Q' => 722, 'R' => 667, 'S' => 556, 'T' => 611, 'U' => 722, 'V' => 722, 'W' => 944, 'X' => 722, 'Y' => 722, 'Z' => 611,
            'a' => 444, 'b' => 500, 'c' => 444, 'd' => 500, 'e' => 444, 'f' => 333, 'g' => 500, 'h' => 500, 'i' => 278, 'j' => 278, 'k' => 500, 'l' => 278, 'm' => 778,
            'n' => 500, 'o' => 500, 'p' => 500, 'q' => 500, 'r' => 333, 's' => 389, 't' => 278, 'u' => 500, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500, 'z' => 444);
        $acentos = array('ÀÁÂÃÄÅ' => 'A', 'Ç' => 'C', 'ÈÉÊË' => 'E', 'ÌÍÎÏ' => 'I', 'Ñ' => 'N', 'ÒÓÔÕÖ' => 'O', 'ÙÚÛÜ' => 'U', 'Ý' => 'Y',
            'àáâãäå' => 'a', 'ç' => 'c', 'èéêë' => 'e', 'ìíîï' => 'i', 'ñ' => 'n', 'òóôõö' => 'o', 'ùúûü' => 'u', 'ýÿ' => 'y');
        foreach ($acentos as $letras => $base) {
            foreach (mb_str_split($letras) as $letra) {
                $larguras[iconv('UTF-8', 'Windows-1252', $letra)] = $larguras[$base];
            }
        }
    }
    $total = 0;
    foreach (str_split((string) $texto_1252) as $c) {
        $total += $larguras[$c] ?? 500; // algarismos e o resto: 500
    }
    return $total;
}

// Gera o certificado de boas-vindas personalizado a partir da arte feita no
// Canva (assets/Certificados/BoasVindas.pdf): escreve o nome do fiel na
// linha e a data de ingresso real — a arte veio com "05 de janeiro de 2027"
// escrito fixo, que é apagado aqui. É uma "atualização incremental" do PDF
// (objetos novos no fim do arquivo + xref nova), sem biblioteca de PDF.
// Depende da estrutura dessa arte (A4 deitado, PDF 1.4 com xref clássica,
// página obj 10 desenhando tudo pelo XObject obj 16); medidas em pontos,
// tiradas do próprio arquivo. Se a arte for trocada, refazer as medidas.
// Devolve o PDF (string) ou null se a arte não tiver o formato esperado.
function certificado_boas_vindas_pdf($nome, $data_ymd) {
    $pdf = @file_get_contents(__DIR__ . '/../assets/Certificados/BoasVindas.pdf');
    if ($pdf === false || !preg_match('/trailer\s*<<(.*?)>>\s*startxref\s*(\d+)\s*%%EOF\s*$/s', $pdf, $trailer)
        || !preg_match('/\/Size (\d+)/', $trailer[1], $size) || (int) $size[1] !== 83
        || !preg_match('/\/Root 1 0 R/', $trailer[1]) || !preg_match('/\/ID\s*\[[^\]]*\]/', $trailer[1], $id_pdf)) {
        return null;
    }

    // XObject da arte sem a data fixa: o bloco de texto que começa em
    // "1 0 0 -1 1.8125 31 Tm" (logo abaixo de "Data de ingresso").
    if (!preg_match('/\n16 0 obj\s*(<<.*?\/Length (\d+).*?>>)\s*stream\r?\n/s', $pdf, $obj16, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $conteudo = @gzuncompress(substr($pdf, $obj16[0][1] + strlen($obj16[0][0]), (int) $obj16[2][0]));
    $sem_data = $conteudo === false ? null
        : preg_replace('/\nBT\n\/F10 13\.3299999 Tf\n1 0 0 -1 1\.8125 31 Tm\n.*?\nET\n/s', "\n", $conteudo, 1, $trocas);
    if ($sem_data === null || $trocas !== 1) {
        return null;
    }
    $sem_data = gzcompress($sem_data);
    $dict16 = preg_replace('/\/Length \d+/', '/Length ' . strlen($sem_data), $obj16[1][0], 1);

    // Página com a fonte do nome e o conteúdo novo por cima. O conteúdo da
    // arte vai entre "q" e "Q" para o texto novo começar do estado gráfico
    // padrão (a arte deixa uma transparência ligada no fim).
    if (!preg_match('/\n10 0 obj\s*(<<.*?>>)\s*endobj/s', $pdf, $obj10)
        || strpos($obj10[1], '/Contents 17 0 R') === false || strpos($obj10[1], '/Font') !== false) {
        return null;
    }
    $pagina = str_replace('/Contents 17 0 R', '/Contents [83 0 R 17 0 R 84 0 R]', $obj10[1]);
    $pagina = preg_replace('/\/Resources <</', "/Resources <<\n/Font << /FNome 85 0 R >>", $pagina, 1);

    $texto = function ($s) {
        $s = trim(preg_replace('/\s+/u', ' ', (string) $s));
        $s = (string) @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        return preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $s);
    };
    $escapar = function ($s) {
        return strtr($s, array('\\' => '\\\\', '(' => '\\(', ')' => '\\)'));
    };
    $nome_1252 = $texto(mb_substr((string) $nome, 0, 80));
    $ts = strtotime((string) $data_ymd) ?: time();
    $data_1252 = $texto(date('d', $ts) . ' de ' . fiel_membro_meses()[(int) date('n', $ts)] . ' de ' . date('Y', $ts));

    // Nome: centrado como "Este certificado é concedido a:" (x 425), logo
    // acima da linha (y 136); encolhe para caber em 560 pt. Data: no lugar
    // da que foi apagada (centro x 208, base y 76,3, 10 pt).
    $largura_nome = max(1, certificado_pdf_largura_times($nome_1252));
    $tam_nome = min(30, 560 * 1000 / $largura_nome);
    $x_nome = 425 - $largura_nome * $tam_nome / 2000;
    $x_data = 208.3 - certificado_pdf_largura_times($data_1252) * 10 / 2000;
    $sobreposicao = sprintf("Q\nq\nBT\n/FNome %.2F Tf\n.1216 .2431 .4078 rg\n%.2F 150 Td\n(%s) Tj\nET\nBT\n/FNome 10 Tf\n0 0 0 rg\n%.2F 76.29 Td\n(%s) Tj\nET\nQ\n",
        $tam_nome, $x_nome, $escapar($nome_1252), $x_data, $escapar($data_1252));

    $saida = rtrim($pdf) . "\n";
    $offsets = array();
    $objetos = array(
        10 => $pagina,
        16 => $dict16 . "\nstream\n" . $sem_data . "\nendstream",
        83 => "<< /Length 2 >>\nstream\nq\n\nendstream",
        84 => '<< /Length ' . strlen($sobreposicao) . " >>\nstream\n" . $sobreposicao . "\nendstream",
        85 => '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>',
    );
    foreach ($objetos as $numero => $corpo) {
        $offsets[$numero] = strlen($saida);
        $saida .= $numero . " 0 obj\n" . $corpo . "\nendobj\n";
    }
    $xref = strlen($saida);
    $saida .= "xref\n0 1\n0000000000 65535 f\r\n";
    foreach (array(array(10), array(16), array(83, 84, 85)) as $faixa) {
        $saida .= $faixa[0] . ' ' . count($faixa) . "\n";
        foreach ($faixa as $numero) {
            $saida .= sprintf("%010d 00000 n\r\n", $offsets[$numero]);
        }
    }
    $info = preg_match('/\/Info (\d+ 0 R)/', $trailer[1], $m) ? '/Info ' . $m[1] . ' ' : '';
    $saida .= "trailer\n<< /Size 86 /Root 1 0 R " . $info . $id_pdf[0] . ' /Prev ' . $trailer[2] . " >>\nstartxref\n" . $xref . "\n%%EOF\n";
    return $saida;
}

// Certificado de conclusão de um curso de verdade — chamado por
// curso_calcular_progresso() (php/fiel/includes/cursos_helpers.php) quando a
// matrícula chega a 100%. Antes nada emitia esse certificado: a tela de
// Certificados só mostrava o de boas-vindas, mesmo para quem concluía
// cursos. Idempotente (um por fiel e curso): o INSERT ... WHERE NOT EXISTS
// roda sob um lock por fiel+curso, então dois envios simultâneos do player
// não geram dois certificados. Cria a notificação junto (o push sai depois
// por notificacoes_push_enviar_pendentes()). Devolve true se emitiu agora.
function certificado_curso_conceder($conn, $fiel_id, $curso_id) {
    $fiel_id = (int) $fiel_id;
    $curso_id = (int) $curso_id;
    if (!$conn || $fiel_id <= 0 || $curso_id <= 0 || $curso_id === certificado_boas_vindas_curso_id($conn)) {
        return false;
    }

    $ja_tinha_transacao = $conn->inTransaction();
    if (!$ja_tinha_transacao) {
        $conn->beginTransaction();
    }
    try {
        db_query($conn, "SELECT pg_advisory_xact_lock(?, ?)", array($fiel_id, $curso_id));
        $res = db_query($conn, "
            INSERT INTO ID_CERTIFICADO (IDF_ID, IDC_ID, IDCERT_NUMERO, IDCERT_DATA_CONCLUSAO)
            SELECT ?, ?, ?, NOW()
            WHERE NOT EXISTS (SELECT 1 FROM ID_CERTIFICADO WHERE IDF_ID = ? AND IDC_ID = ?)
            RETURNING IDCERT_ID
        ", array($fiel_id, $curso_id, 'CC-' . $curso_id . '-' . $fiel_id, $fiel_id, $curso_id));
        $emitiu = $res && mysqli_num_rows($res) > 0;

        if ($emitiu) {
            $res_curso = db_query($conn, "SELECT IDC_TITULO FROM ID_CURSO WHERE IDC_ID = ?", array($curso_id));
            $curso = $res_curso ? mysqli_fetch_assoc($res_curso) : null;
            $titulo_curso = $curso ? (string) $curso['IDC_TITULO'] : 'o curso';
            db_query($conn, "INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK) VALUES (?, ?, ?, 'certificado', ?)", array(
                $fiel_id,
                'Você concluiu um curso!',
                mb_substr('Parabéns por concluir ' . $titulo_curso . '. Seu certificado já está em Certificados.', 0, 500),
                'Certificados/certificados.php',
            ));
        }

        if (!$ja_tinha_transacao) {
            if (!db_commit($conn)) {
                throw new RuntimeException('commit falhou');
            }
        }
        return $emitiu;
    } catch (\Throwable $e) {
        if (!$ja_tinha_transacao && $conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('certificado_curso_conceder: ' . $e->getMessage());
        return false;
    }
}

// Verifica se o fiel tem certificado de boas-vindas e ainda não foi
// notificado sobre ele — se for o caso, cria a notificação. Só notifica UMA
// VEZ por usuário: depois que a notificação é criada, nunca mais se repete
// (mesmo que o usuário apague essa notificação depois). Chamado no login
// (login.php), logo após autenticar com sucesso.
function certificado_boas_vindas_notificar_se_necessario($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $curso_id = certificado_boas_vindas_curso_id($conn);

    if ($fiel_id <= 0 || $curso_id <= 0) {
        return;
    }

    $sql_tem_certificado = "SELECT 1 FROM ID_CERTIFICADO WHERE IDF_ID = $fiel_id AND IDC_ID = $curso_id LIMIT 1";
    $res_tem = mysqli_query($conn, $sql_tem_certificado);
    if (!$res_tem || mysqli_num_rows($res_tem) === 0) {
        return;
    }

    $sql_ja_notificado = "SELECT 1 FROM ID_NOTIFICACAO WHERE IDF_ID = $fiel_id AND IDN_TIPO = 'certificado_boas_vindas' LIMIT 1";
    $res_notif = mysqli_query($conn, $sql_ja_notificado);
    if ($res_notif && mysqli_num_rows($res_notif) > 0) {
        return;
    }

    $titulo = "Você recebeu um certificado de boas-vindas!";
    $msg = "Como parte da nossa comunidade, você já ganhou seu certificado de boas-vindas. Confira em Certificados.";
    $sql_insert = "INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK) VALUES (?, ?, ?, 'certificado_boas_vindas', ?)";
    @db_query($conn, $sql_insert, array($fiel_id, $titulo, $msg, 'Certificados/certificados.php'));
    // O push (OneSignal) NÃO é mandado aqui — nesse momento (durante o POST
    // de login, antes do redirect) o navegador ainda não teve chance de
    // rodar OneSignal.login() (isso só acontece no carregamento da PRÓXIMA
    // página). Fica marcado como pendente (IDN_PUSH_ENVIADO = 0, default) e
    // é enviado depois por notificacoes_push_enviar_pendentes(), chamada via
    // AJAX assim que o navegador confirma o vínculo — ver
    // php/fiel/ajax_onesignal_sync.php e OneSignal.login() em
    // php/includes/pwa_head.php.
}

// Manda o push (OneSignal) de qualquer notificação deste fiel que ainda não
// tenha sido entregue (IDN_PUSH_ENVIADO = 0) — chamada via AJAX pelo
// navegador logo depois que OneSignal.login() resolve (só nesse momento o
// dispositivo está de fato vinculado ao IDF_ID, pronto pra receber push
// direcionado). Idempotente: só marca IDN_PUSH_ENVIADO = 1 quando o envio
// realmente teve sucesso — se onesignal_enviar_push() falhar (ex.: REST key
// não configurada, ou o aparelho ainda não aceitou a permissão), a
// notificação continua pendente e é tentada de novo na próxima chamada.
//
// Checagem de segurança: o JOIN com ID_FIEL abaixo reconfirma, na hora de
// mandar (não só na hora em que a notificação foi criada), que o fiel
// continua aprovado/ativo — cobre o caso de alguém ser desativado/negado
// DEPOIS que uma notificação já foi criada pra ele mas ANTES do push sair.
// Isso vale pra QUALQUER notificação (certificado, aprovação de cadastro,
// mensagem do admin — todas passam por aqui), não é algo específico de uma
// tela só.
function notificacoes_push_enviar_pendentes($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    if ($fiel_id <= 0) {
        return;
    }

    $sql = "
        SELECT n.IDN_ID, n.IDN_TITULO, n.IDN_MENSAGEM, n.IDN_LINK
        FROM ID_NOTIFICACAO n
        JOIN ID_FIEL f ON f.IDF_ID = n.IDF_ID
        WHERE n.IDF_ID = $fiel_id
          AND n.IDN_PUSH_ENVIADO = 0
          AND f.IDF_STATUS = 'aprovado' AND f.IDF_ATIVO = 1
        ORDER BY n.IDN_CRIADO_EM ASC
        LIMIT 5
    ";
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return;
    }

    while ($row = mysqli_fetch_assoc($res)) {
        // Marca ANTES de enviar e só envia quem conseguiu marcar: duas abas
        // (ou duas páginas carregando juntas) liam a mesma pendente e
        // mandavam o mesmo push duas vezes.
        $notificacao_id = (int) $row['IDN_ID'];
        $marcou = db_query($conn, "UPDATE ID_NOTIFICACAO SET IDN_PUSH_ENVIADO = 1 WHERE IDN_ID = ? AND IDN_PUSH_ENVIADO = 0 RETURNING IDN_ID", array($notificacao_id));
        if (!$marcou || mysqli_num_rows($marcou) === 0) {
            continue;
        }
        $resultado = 'falha';
        onesignal_enviar_push($fiel_id, $row['IDN_TITULO'], $row['IDN_MENSAGEM'] ?? '', $row['IDN_LINK'] ?? null, $resultado);
        if ($resultado === 'falha') {
            // Rede, chave ou API fora: volta para pendente e para por aqui —
            // as próximas falhariam igual, cada uma esperando o timeout.
            db_query($conn, "UPDATE ID_NOTIFICACAO SET IDN_PUSH_ENVIADO = 0 WHERE IDN_ID = ?", array($notificacao_id));
            break;
        }
        // 'sem_destino' fica como enviada: o fiel não tem aparelho inscrito,
        // e a notificação continua no sininho do app.
    }
}

// Push na hora para notificações em massa já gravadas (live no ar, mensagem
// do admin). Antes elas só saíam quando cada fiel abria o app
// (notificacoes_push_enviar_pendentes(), via ajax_onesignal_sync.php): o
// "Estamos ao vivo!" não chegava a quem estava fora. Mesmo texto para todos;
// uma chamada à OneSignal a cada 2.000 pessoas. Marca como enviada antes de
// mandar (como o envio individual) e devolve para pendente se a OneSignal
// falhar, para sair quando a pessoa abrir o app. Chamar FORA de transação
// (a chamada externa não pode segurar trava). Devolve quantas saíram.
function notificacoes_push_lote($conn, array $notificacao_ids, $titulo, $mensagem, $link = null) {
    $notificacao_ids = array_values(array_unique(array_filter(array_map('intval', $notificacao_ids))));
    if (!$notificacao_ids || trim((string) ONESIGNAL_REST_API_KEY) === '') {
        return 0;
    }
    $enviadas = 0;
    foreach (array_chunk($notificacao_ids, 2000) as $bloco) {
        $marcadores = implode(', ', array_fill(0, count($bloco), '?'));
        $res = db_query($conn, "
            UPDATE ID_NOTIFICACAO n SET IDN_PUSH_ENVIADO = 1
            FROM ID_FIEL f
            WHERE n.IDN_ID IN ($marcadores) AND n.IDN_PUSH_ENVIADO = 0
              AND f.IDF_ID = n.IDF_ID AND f.IDF_STATUS = 'aprovado' AND f.IDF_ATIVO = 1
            RETURNING n.IDN_ID, f.IDF_ID, f.IDF_SENHA_HASH
        ", $bloco);
        $marcadas = array();
        $aliases = array();
        while ($res && ($linha = mysqli_fetch_assoc($res))) {
            $marcadas[] = (int) $linha['IDN_ID'];
            $aliases[] = onesignal_external_id_com_marca($linha['IDF_ID'], onesignal_marca_do_hash($linha['IDF_SENHA_HASH'] ?? ''));
        }
        if (!$marcadas) {
            continue;
        }
        $resultado = 'falha';
        onesignal_enviar_push_aliases($aliases, $titulo, $mensagem, $link, $resultado, count($aliases) . ' pessoas');
        if ($resultado === 'falha') {
            db_query($conn, 'UPDATE ID_NOTIFICACAO SET IDN_PUSH_ENVIADO = 0 WHERE IDN_ID IN (' . implode(', ', array_fill(0, count($marcadas), '?')) . ')', $marcadas);
            break;
        }
        $enviadas += count($marcadas);
    }
    return $enviadas;
}

// ============================================================================
// ASSINATURAS (ASAAS)
// ============================================================================
// Ver docs/interno/assinaturas/assinaturas_asaas.md e a seção 41 de
// database/bancosupabase.md. Quem cria a assinatura é php/fiel/assinar.php;
// quem diz que o fiel pagou é o webhook_asaas.php (raiz), sempre depois de
// reconfirmar a cobrança na API — nunca pelo corpo do webhook nem pela
// volta do fiel da página de pagamento.
//
// ASAAS_API_URL: sandbox (padrão, pra nunca cobrar de verdade por engano)
// ou https://api.asaas.com/v3 em produção. Cada ambiente tem a sua
// ASAAS_API_KEY. ASAAS_WEBHOOK_TOKEN é o "token de autenticação"
// cadastrado no webhook do painel do Asaas — NÃO é a API key. Sem a API
// key, assinar.php só mostra "ainda não disponível"; sem o token, o
// webhook recusa tudo.
if (!defined('ASAAS_API_URL')) {
    define('ASAAS_API_URL', site_env('ASAAS_API_URL', 'https://api-sandbox.asaas.com/v3'));
}
if (!defined('ASAAS_API_KEY')) {
    define('ASAAS_API_KEY', site_env('ASAAS_API_KEY'));
}
if (!defined('ASAAS_WEBHOOK_TOKEN')) {
    define('ASAAS_WEBHOOK_TOKEN', site_env('ASAAS_WEBHOOK_TOKEN'));
}
// Tolerância: dias de acesso que continuam valendo depois de
// idas_acesso_ate (atraso de Pix/boleto).
if (!defined('ASSINATURA_TOLERANCIA_DIAS')) {
    define('ASSINATURA_TOLERANCIA_DIAS', max(0, (int) site_env('ASSINATURA_TOLERANCIA_DIAS', '5')));
}

function asaas_configurado() {
    $ok = trim((string) ASAAS_API_KEY) !== '' && function_exists('curl_init');
    if ($ok && trim((string) site_env('ASAAS_API_URL')) === '') {
        // Com a chave mas sem a URL, tudo vai para o sandbox: uma chave de
        // produção nem funcionaria lá, e uma de sandbox libera acesso com
        // cartão de teste.
        site_avisar_config_faltando('ASAAS_API_URL', 'usando o Asaas SANDBOX');
    }
    return $ok;
}

// Interruptores do painel admin (tabela id_configuracao, seção 43 do
// bancosupabase.md). Lidos uma vez por requisição; sem a tabela ou sem a
// chave, vale $padrao.
// Os interruptores do admin mudam raramente e eram lidos do banco em toda
// página: ficam num arquivo do container por 30 s, e o arquivo é apagado
// quando o admin salva (site_config_salvar), então a mudança vale na hora.
function site_config_arquivo() {
    return sys_get_temp_dir() . '/mananciapp_config_' . md5((string) site_env('DB_HOST') . '|' . (string) site_env('DB_NAME')) . '.json';
}

function site_config($chave, $padrao = '') {
    global $conn;
    static $cache = null;

    if ($cache === null) {
        $arquivo = site_config_arquivo();
        if (is_file($arquivo) && (time() - (int) @filemtime($arquivo)) < 30) {
            $lido = json_decode((string) @file_get_contents($arquivo), true);
            if (is_array($lido)) {
                $cache = $lido;
            }
        }
    }
    if ($cache === null) {
        $cache = array();
        $res = $conn ? mysqli_query($conn, "SELECT idcfg_chave, idcfg_valor FROM id_configuracao") : false;
        while ($res && $linha = mysqli_fetch_assoc($res)) {
            $cache[$linha['idcfg_chave']] = $linha['idcfg_valor'];
        }
        if ($res) {
            $arquivo = site_config_arquivo();
            $temporario = $arquivo . '.' . getmypid() . '.tmp';
            if (@file_put_contents($temporario, json_encode($cache)) !== false) {
                @rename($temporario, $arquivo);
            }
        }
    }

    return $cache[$chave] ?? $padrao;
}

function site_config_salvar($conn, $chave, $valor) {
    $ok = (bool) db_query($conn, "
        INSERT INTO id_configuracao (idcfg_chave, idcfg_valor) VALUES (?, ?)
        ON CONFLICT (idcfg_chave) DO UPDATE SET idcfg_valor = EXCLUDED.idcfg_valor, idcfg_atualizado_em = NOW()
    ", array((string) $chave, (string) $valor));
    @unlink(site_config_arquivo());
    return $ok;
}

// Assinaturas abertas para os fiéis? Interruptor de Admin → Assinaturas
// (id_configuracao, chave assinaturas_abertas), DESLIGADO por padrão: o
// Asaas fica ligado por causa das contribuições (aba Contribuir, missões),
// mas planos e valores só abrem quando a equipe decidir (T15, previsto
// para fev/mar de 2027). A antiga chave assinaturas_visiveis (seção 43)
// não é mais usada.
function assinaturas_abertas() {
    return site_config('assinaturas_abertas', '0') === '1';
}

// Assinaturas aparecem pro fiel (menu, perfil, tela de assinar, botões
// "Assine para assistir")? Só com o Asaas configurado E as assinaturas
// abertas. Fechadas, ninguém é barrado (assinatura_modo() vira 'livre') e
// as contribuições seguem funcionando. Quem decide se alguém é barrado é o
// "Quem pode assistir" do admin.
function assinatura_modulo_ativo() {
    return asaas_configurado() && assinaturas_abertas();
}

// Modo de acesso escolhido no admin (seção 44 do bancosupabase.md):
//   'livre'    — todos assistem tudo; assinar é opcional;
//   'conteudo' — entra no app, mas vídeo de curso pago exige acesso
//                (freemium: cursos gratuitos ficam livres);
//   'login'    — sem acesso, o fiel cai direto na tela de planos ao
//                entrar (ver auth_require()).
// Sem Asaas configurado (ninguém consegue assinar), sempre 'livre'.
function assinatura_modo() {
    if (!assinatura_modulo_ativo()) {
        return 'livre';
    }
    $modo = site_config('assinaturas_modo', '');
    if ($modo === '') {
        // Antes da seção 44 existia só o liga/desliga do bloqueio.
        $modo = site_config('assinaturas_bloqueio', '0') === '1' ? 'conteudo' : 'livre';
    }
    return in_array($modo, array('livre', 'conteudo', 'login'), true) ? $modo : 'livre';
}

function assinatura_exige_no_login() {
    return assinatura_modo() === 'login';
}

// Dias de teste grátis oferecidos (0 = sem teste). Só faz sentido com
// algum bloqueio ligado — no modo livre ninguém precisa testar.
function assinatura_teste_dias() {
    if (assinatura_modo() === 'livre') {
        return 0;
    }
    return min(365, max(0, (int) site_config('assinaturas_teste_dias', '0')));
}

// Chamada à API REST do Asaas. Devolve array(status_http, corpo_decodificado);
// status 0 = nem chegou a responder (rede/timeout).
function asaas_api($metodo, $caminho, $corpo = null) {
    if (!asaas_configurado()) {
        return array(0, null);
    }

    $ch = curl_init(rtrim((string) ASAAS_API_URL, '/') . $caminho);
    curl_setopt_array($ch, array(
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: MananciAPP',
            'access_token: ' . trim((string) ASAAS_API_KEY),
        ),
    ));
    if ($corpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo));
    }

    $resposta = curl_exec($ch);
    $http_code = curl_errno($ch) ? 0 : (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return array($http_code, is_string($resposta) ? json_decode($resposta, true) : null);
}

// Cria/atualiza um customer. Celular e e-mail são opcionais pro Asaas, mas
// ele recusa o cliente inteiro se vierem num formato que não aceita (ex.:
// telefone fictício "11999999999" no cadastro do fiel) — nesse caso tenta
// de novo uma vez sem o campo recusado.
function asaas_enviar_customer($metodo, $caminho, array $dados) {
    list($status, $resposta) = asaas_api($metodo, $caminho, $dados);

    $recusados = array();
    foreach ((array) ($resposta['errors'] ?? array()) as $erro_asaas) {
        $codigo = (string) ($erro_asaas['code'] ?? '');
        if ($codigo === 'invalid_mobilePhone' || $codigo === 'invalid_phone') {
            $recusados[] = 'mobilePhone';
        } elseif ($codigo === 'invalid_email') {
            $recusados[] = 'email';
        }
    }

    if ($status === 400 && !empty($recusados)) {
        list($status, $resposta) = asaas_api($metodo, $caminho, array_diff_key($dados, array_flip($recusados)));
    }
    return array($status, $resposta);
}

// mysqli_query() (o shim lá embaixo) devolve false em erro, sem exceção. No
// fluxo de pagamento um UPDATE falhando em silêncio = fiel pagou e não foi
// liberado, então aqui erro de SQL vira exceção (e a transação é desfeita).
// Com $params, vai como consulta preparada (db_query, marcadores "?").
function asaas_sql($conn, $sql, array $params = array()) {
    $res = $params ? db_query($conn, $sql, $params) : mysqli_query($conn, $sql);
    if ($res === false) {
        throw new RuntimeException('SQL: ' . mysqli_error($conn));
    }
    return $res;
}

// Mensagem de erro de banco para as telas do admin: o detalhe do Postgres
// (nomes de tabela, restrições, valores) vai só para o log do Render; na
// tela, a frase dada + um motivo legível nos casos comuns.
// Commit conferido. Com o erro silencioso do PDO, um commit que falha
// (conexão caiu no meio) devolve false, e a tela dizia "salvo" com nada
// gravado. Avisa no log e no Sentry; quem chama decide (lança ou devolve).
function db_commit($conn) {
    if ($conn->commit()) {
        return true;
    }
    error_log('Banco: commit falhou (' . (string) ($GLOBALS['site_pg_last_error'] ?? '') . ')');
    site_alerta_erro('error', 'Banco: commit de transação falhou.', 'ErroBanco');
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    return false;
}

function site_erro_banco($mensagem) {
    global $conn;
    $detalhe = mysqli_error($conn);
    error_log('Banco: ' . $mensagem . ' (' . $detalhe . ')');
    site_alerta_erro('warning', 'Banco: ' . $mensagem . ' (' . $detalhe . ')', 'ErroBanco');
    if (stripos($detalhe, 'foreign key') !== false) {
        return $mensagem . ' Há outros registros ligados a ele (por exemplo, cursos ou devocionais criados por essa conta).';
    }
    if (stripos($detalhe, 'too long') !== false) {
        return $mensagem . ' Algum campo passou do tamanho permitido.';
    }
    if (stripos($detalhe, 'duplicate key') !== false) {
        return $mensagem . ' Já existe um registro com esses dados.';
    }
    return $mensagem . ' Tente de novo em instantes.';
}

// Caminho de vídeo que o admin pode gravar numa aula: só o que o upload do
// R2 gera ("r2:aulas/<nome>", ver php/admin/r2_upload_url.php). Antes
// qualquer texto ia para IDCT_URL — inclusive "r2:" de outra pasta do
// bucket, que site_excluir_arquivo_r2_orfao() apagaria ao excluir a aula.
function admin_video_url_valida($url) {
    return preg_match('#^r2:aulas/[A-Za-z0-9_.-]+$#D', (string) $url) === 1;
}

// Conta de OUTRO administrador ativo. Todos os admins têm o mesmo poder, e
// trocar o e-mail de outro admin + "Enviar link de nova senha" entregava a
// conta dele a quem fez a troca. E-mail, status e exclusão de um admin só
// depois de tirar a permissão em Permissões (que avisa o próprio admin).
function usuario_e_outro_admin($conn, $id, $admin_id) {
    $id = (int) $id;
    if ($id <= 0 || $id === (int) $admin_id) {
        return false;
    }
    $res = db_query($conn, "SELECT 1 FROM ID_ADMIN WHERE IDA_FIEL_ID = ? AND IDA_ATIVO = 1 LIMIT 1", array($id));
    return $res && mysqli_num_rows($res) > 0;
}

// Duração (s) que o navegador do admin leu do arquivo enviado, ou "NULL" (texto
// para o SQL) se não veio ou é absurda. Com ela gravada no upload, o % e a
// regra de 80% para concluir não dependem do que o player do fiel informa.
function admin_video_duracao_sql($valor) {
    $segundos = (int) $valor;
    return ($segundos >= 1 && $segundos <= 6 * 3600) ? (string) $segundos : "NULL";
}

// Verdadeiro se o módulo pertence ao curso (ou se não há módulo). Sem isso,
// a aula podia ficar num curso e num módulo de outro curso.
function admin_modulo_do_curso($conn, $modulo_id, $curso_id) {
    if ($modulo_id === null) {
        return true;
    }
    $res = db_query($conn, "SELECT 1 FROM ID_MODULO WHERE IDM_ID = ? AND IDC_ID = ? LIMIT 1", array((int) $modulo_id, (int) $curso_id));
    return $res && mysqli_num_rows($res) > 0;
}

// Verdadeiro se o plano tem assinatura viva no Asaas e o admin está mudando
// o que ela cobra (valor, ciclo ou forma). Nome, descrição, ordem e
// "ativo" continuam editáveis. Ver admin_assinaturas.php.
function plano_tem_assinantes_e_mudou_cobranca($conn, $plano_id, $valor_centavos, $ciclo, $forma) {
    $res = db_query($conn, "
        SELECT p.idp_valor_centavos, p.idp_ciclo, p.idp_forma_pagamento,
               EXISTS (SELECT 1 FROM id_assinatura a WHERE a.idp_id = p.idp_id AND a.idas_asaas_subscription_id IS NOT NULL AND a.idas_status <> 'CANCELADA') AS tem_assinantes
        FROM id_plano p
        WHERE p.idp_id = ?
    ", array((int) $plano_id));
    $plano = $res ? mysqli_fetch_assoc($res) : null;
    if (!$plano || empty($plano['tem_assinantes'])) {
        return false;
    }
    return (int) $plano['idp_valor_centavos'] !== (int) $valor_centavos
        || (string) $plano['idp_ciclo'] !== (string) $ciclo
        || (string) $plano['idp_forma_pagamento'] !== (string) $forma;
}

function assinatura_ciclo_intervalo($ciclo) {
    $intervalos = array(
        'WEEKLY' => '1 week',
        'MONTHLY' => '1 month',
        'QUARTERLY' => '3 months',
        'SEMIANNUALLY' => '6 months',
        'YEARLY' => '1 year',
    );
    return $intervalos[$ciclo] ?? '1 month';
}

function assinatura_ciclo_texto($ciclo) {
    return $ciclo === 'YEARLY' ? 'ano' : 'mês';
}

function assinatura_formatar_valor($centavos) {
    return 'R$ ' . number_format(((int) $centavos) / 100, 2, ',', '.');
}

function assinatura_cpf_valido($cpf) {
    $cpf = preg_replace('/\D/', '', (string) $cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int) $cpf[$i] * (($t + 1) - $i);
        }
        $digito = ((10 * $soma) % 11) % 10;
        if ((int) $cpf[$t] !== $digito) {
            return false;
        }
    }
    return true;
}

function assinatura_cnpj_valido($cnpj) {
    $cnpj = preg_replace('/\D/', '', (string) $cnpj);
    if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }
    foreach (array(12, 13) as $t) {
        $soma = 0;
        $peso = $t - 7;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int) $cnpj[$i] * $peso;
            $peso = ($peso === 2) ? 9 : $peso - 1;
        }
        $digito = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        if ((int) $cnpj[$t] !== $digito) {
            return false;
        }
    }
    return true;
}

function assinatura_documento_valido($documento, $aceita_cnpj) {
    $documento = preg_replace('/\D/', '', (string) $documento);
    return assinatura_cpf_valido($documento) || ($aceita_cnpj && assinatura_cnpj_valido($documento));
}

function assinatura_planos_ativos($conn) {
    $res = mysqli_query($conn, "
        SELECT idp_id, idp_nome, idp_descricao, idp_valor_centavos, idp_ciclo,
               idp_forma_pagamento, idp_max_beneficiarios, idp_aceita_cnpj
        FROM id_plano
        WHERE idp_ativo = 1
        ORDER BY idp_ordem, idp_valor_centavos
    ");
    return $res ? $res->todas_as_linhas() : array();
}

function assinatura_do_fiel($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $res = mysqli_query($conn, "
        SELECT a.*, p.idp_nome, p.idp_ciclo, p.idp_valor_centavos, p.idp_max_beneficiarios
        FROM id_assinatura a
        LEFT JOIN id_plano p ON p.idp_id = a.idp_id
        WHERE a.idf_id = $fiel_id
        LIMIT 1
    ");
    return $res ? mysqli_fetch_assoc($res) : null;
}

// Vídeo pago exige acesso? Vale nos modos 'conteudo' e 'login' (no 'login'
// é uma segunda camada: o fiel sem acesso nem chega nas páginas). Só ligar
// com o pagamento em produção e os cursos gratuitos e cortesias
// configurados, senão todo fiel perde o acesso de uma vez.
function assinatura_bloqueio_ativo() {
    return assinatura_modo() !== 'livre';
}

// Teste grátis disponível pra esse fiel: há dias configurados, ele nunca
// começou um teste e nunca teve acesso (pago ou cortesia). Uma vez por
// pessoa.
function assinatura_teste_disponivel($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    if (assinatura_teste_dias() <= 0 || !$conn || $fiel_id <= 0 || ($_SESSION['Usuario_tipo'] ?? '') === 'admin') {
        return false;
    }
    $res = mysqli_query($conn, "SELECT idas_teste_em, idas_acesso_ate FROM id_assinatura WHERE idf_id = $fiel_id LIMIT 1");
    if (!$res) {
        return false;
    }
    $linha = mysqli_fetch_assoc($res);
    return !$linha || (empty($linha['idas_teste_em']) && empty($linha['idas_acesso_ate']));
}

// Começa o teste grátis (acesso até agora + N dias, status TESTE). A
// condição no UPDATE repete a regra de assinatura_teste_disponivel(), então
// dois cliques ao mesmo tempo nunca dão dois testes.
function assinatura_iniciar_teste($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $dias = assinatura_teste_dias();
    if (!assinatura_teste_disponivel($conn, $fiel_id)) {
        return 'O teste grátis não está disponível para a sua conta.';
    }
    $ok = mysqli_query($conn, "
        INSERT INTO id_assinatura (idf_id, idas_status, idas_acesso_ate, idas_teste_em)
        VALUES ($fiel_id, 'TESTE', NOW() + INTERVAL '$dias days', NOW())
        ON CONFLICT (idf_id) DO UPDATE SET
            idas_acesso_ate = EXCLUDED.idas_acesso_ate,
            idas_teste_em = EXCLUDED.idas_teste_em,
            idas_status = CASE WHEN id_assinatura.idas_asaas_subscription_id IS NULL THEN 'TESTE' ELSE id_assinatura.idas_status END,
            idas_atualizado_em = NOW()
        WHERE id_assinatura.idas_teste_em IS NULL AND id_assinatura.idas_acesso_ate IS NULL
    ");
    return ($ok && mysqli_affected_rows($conn) === 1) ? '' : 'Não foi possível começar o teste grátis. Atualize a página e tente de novo.';
}

// Única regra de "pode ver conteúdo pago?". Admin sempre pode. Fiel pode se
// tiver acesso em data (idas_acesso_ate + tolerância; sem tolerância pra
// teste grátis e cortesia, que têm data exata) numa assinatura
// própria OU como beneficiário de um plano Família/Igreja. Status não
// entra: cancelar ou atrasar nunca tira o período já pago. Lê só o banco
// local (o Asaas nunca é consultado a cada clique). Cache por requisição.
function assinatura_fiel_tem_acesso($conn, $fiel_id) {
    static $cache = array();
    $fiel_id = (int) $fiel_id;

    if (($_SESSION['Usuario_tipo'] ?? '') === 'admin') {
        return true;
    }
    if (!$conn || $fiel_id <= 0) {
        return false;
    }
    if (isset($cache[$fiel_id])) {
        return $cache[$fiel_id];
    }
    // "Tem acesso" confirmado há menos de 1 min nesta sessão: não consulta
    // de novo a cada página (com o modo "só assinantes" ligado, isso rodava
    // em toda navegação). Só o SIM fica guardado — quem acabou de pagar não
    // pode ficar preso num "não" antigo.
    $guardado = $_SESSION['assinatura_acesso_ok'] ?? array();
    if (($guardado['id'] ?? 0) === $fiel_id && (time() - (int) ($guardado['em'] ?? 0)) < 60) {
        return $cache[$fiel_id] = true;
    }

    $tolerancia = (int) ASSINATURA_TOLERANCIA_DIAS;
    $res = mysqli_query($conn, "
        SELECT 1
        FROM id_assinatura a
        LEFT JOIN id_plano p ON p.idp_id = a.idp_id
        WHERE a.idas_acesso_ate + CASE WHEN a.idas_status IN ('TESTE', 'CORTESIA') THEN INTERVAL '0 days' ELSE INTERVAL '$tolerancia days' END > NOW()
          AND (
              a.idf_id = $fiel_id
              OR (COALESCE(p.idp_max_beneficiarios, 0) > 0
                  AND EXISTS (SELECT 1 FROM id_assinatura_beneficiario b WHERE b.idas_id = a.idas_id AND b.idf_id = $fiel_id))
          )
        LIMIT 1
    ");

    $cache[$fiel_id] = (bool) ($res && mysqli_num_rows($res) > 0);
    if ($cache[$fiel_id] && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['assinatura_acesso_ok'] = array('id' => $fiel_id, 'em' => time());
    }
    return $cache[$fiel_id];
}

// Conteúdo de um curso pode ser assistido? $curso_gratuito = id_curso.idc_gratuito
// (vídeo solto, sem curso, conta como pago).
function assinatura_curso_liberado($conn, $curso_gratuito) {
    if (!assinatura_bloqueio_ativo() || (int) $curso_gratuito === 1) {
        return true;
    }
    return assinatura_fiel_tem_acesso($conn, auth_resolve_fiel_id());
}

// As prévias em vídeo dos cards são URLs assinadas do vídeo INTEIRO — quem
// não tem acesso não recebe nenhuma (o card cai na capa do curso).
function assinatura_previews_liberados($conn) {
    return !assinatura_bloqueio_ativo() || assinatura_fiel_tem_acesso($conn, auth_resolve_fiel_id());
}

// Se o fiel tem acesso como beneficiário, devolve de quem (nome do titular e
// plano) — só pra mostrar na tela de assinatura.
function assinatura_beneficio_do_fiel($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $res = mysqli_query($conn, "
        SELECT f.idf_nome AS titular_nome, p.idp_nome, a.idas_acesso_ate
        FROM id_assinatura_beneficiario b
        JOIN id_assinatura a ON a.idas_id = b.idas_id
        JOIN id_plano p ON p.idp_id = a.idp_id
        JOIN id_fiel f ON f.idf_id = a.idf_id
        WHERE b.idf_id = $fiel_id AND p.idp_max_beneficiarios > 0
        ORDER BY a.idas_acesso_ate DESC NULLS LAST
        LIMIT 1
    ");
    return $res ? mysqli_fetch_assoc($res) : null;
}

function assinatura_beneficiarios($conn, $idas_id) {
    $idas_id = (int) $idas_id;
    $res = mysqli_query($conn, "
        SELECT b.idf_id, f.idf_nome, f.idf_email
        FROM id_assinatura_beneficiario b
        JOIN id_fiel f ON f.idf_id = b.idf_id
        WHERE b.idas_id = $idas_id
        ORDER BY f.idf_nome
    ");
    return $res ? $res->todas_as_linhas() : array();
}

// Titular libera outra conta JÁ CADASTRADA e aprovada (pelo e-mail dela).
// Retorna '' em caso de sucesso ou a mensagem de erro.
function assinatura_adicionar_beneficiario($conn, $titular_id, $email) {
    $titular_id = (int) $titular_id;
    $assinatura = assinatura_do_fiel($conn, $titular_id);
    $limite = (int) ($assinatura['idp_max_beneficiarios'] ?? 0);
    if (!$assinatura || $limite <= 0) {
        return 'Seu plano não permite adicionar outras pessoas.';
    }

    // A resposta "não encontramos uma conta" diz a quem tem plano família
    // se um e-mail tem conta no app. Ela continua (é útil para quem está
    // adicionando a família), mas com limite de 10 buscas por hora por
    // titular — não dá para testar uma lista de e-mails. Conta TODA busca:
    // antes só as sem resultado contavam, e adicionar e remover em sequência
    // revelava quem tem conta sem limite. Usa a
    // tabela de tentativas do login, com chave própria (sem tabela nova).
    $chave_limite = 'beneficiario:' . $titular_id;
    db_query($conn, "DELETE FROM ID_LOGIN_TENTATIVA WHERE IDLT_EMAIL LIKE 'beneficiario:%' AND IDLT_CRIADO_EM <= NOW() - INTERVAL '1 hour'");
    $res_limite = db_query($conn, "SELECT COUNT(*) AS total FROM ID_LOGIN_TENTATIVA WHERE IDLT_EMAIL = ?", array($chave_limite));
    $linha_limite = $res_limite ? mysqli_fetch_assoc($res_limite) : null;
    if ($linha_limite && (int) $linha_limite['total'] >= 10) {
        return 'Muitas tentativas em pouco tempo. Confira o e-mail com a pessoa e tente de novo em uma hora.';
    }
    db_query($conn, "INSERT INTO ID_LOGIN_TENTATIVA (IDLT_EMAIL) VALUES (?)", array($chave_limite));

    $res = db_query($conn, "SELECT idf_id FROM id_fiel WHERE LOWER(idf_email) = ? AND idf_status = 'aprovado' AND idf_ativo = 1 LIMIT 1", array(mb_strtolower(trim((string) $email))));
    $alvo = $res ? mysqli_fetch_assoc($res) : null;
    if (!$alvo) {
        return 'Não encontramos uma conta aprovada com esse e-mail. A pessoa precisa se cadastrar no app primeiro.';
    }
    $alvo_id = (int) $alvo['idf_id'];
    if ($alvo_id === $titular_id) {
        return 'Você já tem acesso como titular.';
    }

    $idas_id = (int) $assinatura['idas_id'];
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $conn->beginTransaction();
    try {
        // Trava por assinatura: dois cadastros ao mesmo tempo não passam do limite.
        asaas_sql($conn, "SELECT pg_advisory_xact_lock(727270003, $idas_id)");
        $total = (int) (mysqli_fetch_assoc(asaas_sql($conn, "SELECT COUNT(*) AS cnt FROM id_assinatura_beneficiario WHERE idas_id = $idas_id"))['cnt'] ?? 0);
        if ($total >= $limite) {
            $conn->rollBack();
            return 'Seu plano já está com o limite de ' . $limite . ' pessoa(s).';
        }
        asaas_sql($conn, "INSERT INTO id_assinatura_beneficiario (idas_id, idf_id) VALUES ($idas_id, $alvo_id) ON CONFLICT (idas_id, idf_id) DO NOTHING");
        if (!db_commit($conn)) {
            throw new RuntimeException('commit falhou');
        }
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('assinatura_adicionar_beneficiario: ' . $e->getMessage());
        return 'Não foi possível adicionar agora. Tente de novo.';
    }
    return '';
}

function assinatura_remover_beneficiario($conn, $titular_id, $beneficiario_id) {
    $titular_id = (int) $titular_id;
    $beneficiario_id = (int) $beneficiario_id;
    return (bool) mysqli_query($conn, "
        DELETE FROM id_assinatura_beneficiario
        WHERE idf_id = $beneficiario_id
          AND idas_id IN (SELECT idas_id FROM id_assinatura WHERE idf_id = $titular_id)
    ");
}

// Cancela no Asaas (DELETE /subscriptions/{id} — para de gerar cobranças) e
// marca CANCELADA aqui. idas_acesso_ate fica como está: o fiel usa até o
// fim do período pago. 404 = já não existe lá, cancela só aqui.
//
// Quem nunca teve acesso (clicou em Assinar e desistiu antes de pagar) não
// tem "assinatura cancelada" de verdade: a assinatura sai da linha e o fiel
// volta a ser alguém que nunca assinou (o customer do Asaas é mantido pra
// reaproveitar numa próxima assinatura).
function assinatura_cancelar($conn, $fiel_id) {
    $assinatura = assinatura_do_fiel($conn, $fiel_id);
    $subscription_id = (string) ($assinatura['idas_asaas_subscription_id'] ?? '');
    if (!$assinatura || $subscription_id === '' || $assinatura['idas_status'] === 'CANCELADA') {
        return 'Não há assinatura ativa para cancelar.';
    }

    list($status) = asaas_api('DELETE', '/subscriptions/' . rawurlencode($subscription_id));
    if (($status < 200 || $status >= 300) && $status !== 404) {
        error_log('assinatura_cancelar ' . $subscription_id . ': HTTP ' . $status);
        return 'Não foi possível cancelar agora. Tente de novo em alguns minutos.';
    }

    $idas_id = (int) $assinatura['idas_id'];
    if (empty($assinatura['idas_acesso_ate'])) {
        mysqli_query($conn, "UPDATE id_assinatura SET idas_status = 'CANCELADA', idas_asaas_subscription_id = NULL, idp_id = NULL, idas_cobranca_aberta_url = NULL, idas_atualizado_em = NOW() WHERE idas_id = $idas_id");
    } else {
        mysqli_query($conn, "UPDATE id_assinatura SET idas_status = 'CANCELADA', idas_cobranca_aberta_url = NULL, idas_atualizado_em = NOW() WHERE idas_id = $idas_id");
    }
    return '';
}

// Admin define até quando o fiel tem acesso sem pagar (cortesia — ex.: quem
// já usava o app antes do bloqueio ligar). $data 'Y-m-d' vale até o fim do
// dia em Brasília; '' remove o acesso. Quem não tem assinatura no Asaas
// fica com status CORTESIA; quem tem mantém o status dela.
function assinatura_definir_cortesia($conn, $fiel_id, $data) {
    $fiel_id = (int) $fiel_id;
    $data = (string) $data;
    if ($fiel_id <= 0 || ($data !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data))) {
        return false;
    }
    $acesso_sql = $data !== '' ? "'$data 23:59:59 America/Sao_Paulo'::timestamptz" : 'NULL';
    return (bool) mysqli_query($conn, "
        INSERT INTO id_assinatura (idf_id, idas_status, idas_acesso_ate)
        VALUES ($fiel_id, 'CORTESIA', $acesso_sql)
        ON CONFLICT (idf_id) DO UPDATE SET
            idas_acesso_ate = EXCLUDED.idas_acesso_ate,
            idas_status = CASE WHEN id_assinatura.idas_asaas_subscription_id IS NULL THEN 'CORTESIA' ELSE id_assinatura.idas_status END,
            idas_atualizado_em = NOW()
    ");
}

// Cria (ou reaproveita) o customer e cria a assinatura no Asaas pro fiel da
// sessão, e devolve a URL da 1ª cobrança (página de pagamento do próprio
// Asaas — o app nunca vê dado de cartão). $documento = CPF (ou CNPJ, se o
// plano aceitar) do pagador — o Asaas exige pra cobrar.
// Retorna array('ok' => bool, 'url' => string, 'erro' => string).
//
// O advisory lock por fiel segura duplo clique / duas abas: a segunda
// requisição espera a primeira terminar e cai no "já existe assinatura".
function assinatura_iniciar($conn, $fiel_id, $plano_id, $documento) {
    $fiel_id = (int) $fiel_id;
    $plano_id = (int) $plano_id;
    $documento = preg_replace('/\D/', '', (string) $documento);
    $falha = function ($erro) {
        return array('ok' => false, 'url' => '', 'erro' => $erro);
    };

    if (!$conn || $fiel_id <= 0 || !asaas_configurado()) {
        return $falha('As assinaturas ainda não estão disponíveis.');
    }

    $res_plano = mysqli_query($conn, "SELECT idp_id, idp_nome, idp_valor_centavos, idp_ciclo, idp_forma_pagamento, idp_aceita_cnpj FROM id_plano WHERE idp_id = $plano_id AND idp_ativo = 1 LIMIT 1");
    $plano = $res_plano ? mysqli_fetch_assoc($res_plano) : null;
    if (!$plano) {
        return $falha('Plano inválido.');
    }
    if (!assinatura_documento_valido($documento, (int) $plano['idp_aceita_cnpj'] === 1)) {
        return $falha((int) $plano['idp_aceita_cnpj'] === 1 ? 'Informe um CPF ou CNPJ válido.' : 'Informe um CPF válido.');
    }

    $res_fiel = mysqli_query($conn, "SELECT idf_nome, idf_email, idf_telefone FROM id_fiel WHERE idf_id = $fiel_id LIMIT 1");
    $fiel = $res_fiel ? mysqli_fetch_assoc($res_fiel) : null;
    if (!$fiel) {
        return $falha('Conta não encontrada.');
    }

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $conn->beginTransaction();

    try {
        asaas_sql($conn, "SELECT pg_advisory_xact_lock(727270002, $fiel_id)");

        $atual = assinatura_do_fiel($conn, $fiel_id);
        if ($atual && !empty($atual['idas_asaas_subscription_id']) && $atual['idas_status'] !== 'CANCELADA') {
            if (!db_commit($conn)) {
                throw new RuntimeException('commit falhou');
            }
            $url = (string) ($atual['idas_cobranca_aberta_url'] ?? '');
            return $url !== ''
                ? array('ok' => true, 'url' => $url, 'erro' => '')
                : $falha('Você já tem uma assinatura. Veja o status abaixo.');
        }

        $customer_id = (string) ($atual['idas_asaas_customer_id'] ?? '');
        $dados_customer = array(
            'name' => $fiel['idf_nome'],
            'cpfCnpj' => $documento,
            'email' => $fiel['idf_email'],
            'mobilePhone' => preg_replace('/\D/', '', (string) ($fiel['idf_telefone'] ?? '')),
            'externalReference' => 'fiel_' . $fiel_id,
        );
        if ($customer_id === '') {
            list($status, $resposta) = asaas_enviar_customer('POST', '/customers', $dados_customer);
            if ($status < 200 || $status >= 300 || empty($resposta['id'])) {
                throw new RuntimeException('POST /customers HTTP ' . $status . ': ' . json_encode($resposta['errors'] ?? $resposta));
            }
            $customer_id = (string) $resposta['id'];
        } elseif ((string) ($atual['idas_documento'] ?? '') !== $documento) {
            // Assinando de novo com outro pagador (ex.: trocou pro plano Igreja).
            list($status, $resposta) = asaas_enviar_customer('PUT', '/customers/' . rawurlencode($customer_id), $dados_customer);
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException('PUT /customers HTTP ' . $status . ': ' . json_encode($resposta['errors'] ?? $resposta));
            }
        }

        $forma = in_array($plano['idp_forma_pagamento'], array('UNDEFINED', 'CREDIT_CARD', 'PIX', 'BOLETO'), true) ? $plano['idp_forma_pagamento'] : 'UNDEFINED';
        list($status, $assinatura) = asaas_api('POST', '/subscriptions', array(
            'customer' => $customer_id,
            'billingType' => $forma, // UNDEFINED = o fiel escolhe Pix, boleto ou cartão na página do Asaas
            'value' => ((int) $plano['idp_valor_centavos']) / 100,
            'nextDueDate' => site_hoje(),
            'cycle' => $plano['idp_ciclo'],
            'description' => 'MananciAPP - Plano ' . $plano['idp_nome'],
            'externalReference' => 'fiel_' . $fiel_id,
        ));
        if ($status < 200 || $status >= 300 || empty($assinatura['id'])) {
            throw new RuntimeException('POST /subscriptions HTTP ' . $status . ': ' . json_encode($assinatura['errors'] ?? $assinatura));
        }
        $subscription_id = (string) $assinatura['id'];

        // A resposta da assinatura não traz link de pagamento — ele fica na
        // cobrança que o Asaas gera junto.
        list($status, $cobrancas) = asaas_api('GET', '/subscriptions/' . rawurlencode($subscription_id) . '/payments');
        $url = (string) ($cobrancas['data'][0]['invoiceUrl'] ?? '');

        $plano_id = (int) $plano['idp_id'];

        asaas_sql($conn, "
            INSERT INTO id_assinatura (idf_id, idp_id, idas_status, idas_asaas_customer_id, idas_asaas_subscription_id, idas_cobranca_aberta_url, idas_documento)
            VALUES (?, ?, 'PENDENTE', ?, ?, ?, ?)
            ON CONFLICT (idf_id) DO UPDATE SET
                idp_id = EXCLUDED.idp_id,
                idas_status = 'PENDENTE',
                idas_asaas_customer_id = EXCLUDED.idas_asaas_customer_id,
                idas_asaas_subscription_id = EXCLUDED.idas_asaas_subscription_id,
                idas_cobranca_aberta_url = EXCLUDED.idas_cobranca_aberta_url,
                idas_documento = EXCLUDED.idas_documento,
                idas_atualizado_em = NOW()
        ", array($fiel_id, $plano_id, $customer_id, $subscription_id, $url !== '' ? $url : null, $documento));

        if (!db_commit($conn)) {
            throw new RuntimeException('commit falhou');
        }
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        // Se o erro veio depois do POST /subscriptions, a assinatura ficou
        // criada no Asaas sem registro aqui: ela cobraria o fiel sem nunca
        // liberar acesso (os webhooks não achariam a quem aplicar), e uma
        // nova tentativa criaria a segunda cobrança. Apaga lá na hora; se
        // nem isso der, o id vai no log pra cancelar no painel.
        $apagou_no_asaas = false;
        if (isset($subscription_id)) {
            try {
                list($status_delete) = asaas_api('DELETE', '/subscriptions/' . rawurlencode($subscription_id));
                $apagou_no_asaas = $status_delete >= 200 && $status_delete < 300;
            } catch (\Throwable $e_delete) {
                $apagou_no_asaas = false;
            }
        }
        error_log('assinatura_iniciar fiel ' . $fiel_id . (isset($subscription_id) ? ' (subscription ' . $subscription_id . ($apagou_no_asaas ? ', apagada no Asaas' : ', NÃO apagada no Asaas — cancelar no painel') . ')' : '') . ': ' . $e->getMessage());
        return $falha('Não foi possível iniciar a assinatura agora. Tente de novo em alguns minutos.');
    }

    return $url !== ''
        ? array('ok' => true, 'url' => $url, 'erro' => '')
        : $falha('Assinatura criada, mas a cobrança ainda está sendo gerada. Atualize a página em instantes.');
}

// Aplica UMA cobrança (objeto "payment" vindo da API do Asaas — nunca do
// corpo do webhook direto) em id_pagamento/id_assinatura. Retorna false se
// a cobrança não é de nenhuma assinatura nossa (ex.: cobrança avulsa
// criada à mão no painel).
//
// Regra do acesso: cada cobrança paga garante acesso até o vencimento dela
// + 1 ciclo (+1 dia, pra valer o dia do próximo vencimento inteiro).
// GREATEST: cobrança antiga chegando fora de ordem nunca encurta o acesso.
function asaas_aplicar_pagamento($conn, array $pagamento) {
    $subscription_id = (string) ($pagamento['subscription'] ?? '');
    $payment_id = (string) ($pagamento['id'] ?? '');
    if ($subscription_id === '' || $payment_id === '') {
        return false;
    }

    $res = asaas_sql($conn, "
        SELECT a.idas_id, p.idp_ciclo
        FROM id_assinatura a
        LEFT JOIN id_plano p ON p.idp_id = a.idp_id
        WHERE a.idas_asaas_subscription_id = ?
        LIMIT 1
    ", array($subscription_id));
    $assinatura = mysqli_fetch_assoc($res);
    if (!$assinatura) {
        error_log('asaas_aplicar_pagamento: subscription ' . $subscription_id . ' sem id_assinatura');
        return false;
    }

    $idas_id = (int) $assinatura['idas_id'];
    $status = strtoupper((string) ($pagamento['status'] ?? ''));
    $data_db = function ($valor) {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $valor) ? (string) $valor : null;
    };
    $vencimento = $data_db($pagamento['dueDate'] ?? '');
    $pago_em = $data_db($pagamento['paymentDate'] ?? ($pagamento['confirmedDate'] ?? ''));
    $invoice = (string) ($pagamento['invoiceUrl'] ?? '');
    $forma = (string) ($pagamento['billingType'] ?? '');
    $valor_centavos = (int) round(((float) ($pagamento['value'] ?? 0)) * 100);

    // Valor líquido (depois da taxa do Asaas) para o Financeiro, só se a
    // seção 71 já rodou: sem a coluna, o INSERT falharia e o webhook
    // devolveria erro em todo pagamento.
    if (tabela_tem_coluna($conn, 'id_pagamento', 'idpg_valor_liquido_centavos')) {
        asaas_sql($conn, "
            INSERT INTO id_pagamento (idas_id, idpg_asaas_payment_id, idpg_valor_centavos, idpg_valor_liquido_centavos, idpg_forma, idpg_status, idpg_vencimento, idpg_pago_em, idpg_invoice_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (idpg_asaas_payment_id) DO UPDATE SET
                idpg_valor_centavos = EXCLUDED.idpg_valor_centavos,
                idpg_valor_liquido_centavos = COALESCE(EXCLUDED.idpg_valor_liquido_centavos, id_pagamento.idpg_valor_liquido_centavos),
                idpg_forma = EXCLUDED.idpg_forma,
                idpg_status = EXCLUDED.idpg_status,
                idpg_vencimento = EXCLUDED.idpg_vencimento,
                idpg_pago_em = EXCLUDED.idpg_pago_em,
                idpg_invoice_url = EXCLUDED.idpg_invoice_url,
                idpg_atualizado_em = NOW()
        ", array($idas_id, $payment_id, $valor_centavos, asaas_valor_liquido_centavos($pagamento), $forma, $status, $vencimento, $pago_em, $invoice));
    } else {
        asaas_sql($conn, "
            INSERT INTO id_pagamento (idas_id, idpg_asaas_payment_id, idpg_valor_centavos, idpg_forma, idpg_status, idpg_vencimento, idpg_pago_em, idpg_invoice_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (idpg_asaas_payment_id) DO UPDATE SET
                idpg_valor_centavos = EXCLUDED.idpg_valor_centavos,
                idpg_forma = EXCLUDED.idpg_forma,
                idpg_status = EXCLUDED.idpg_status,
                idpg_vencimento = EXCLUDED.idpg_vencimento,
                idpg_pago_em = EXCLUDED.idpg_pago_em,
                idpg_invoice_url = EXCLUDED.idpg_invoice_url,
                idpg_atualizado_em = NOW()
        ", array($idas_id, $payment_id, $valor_centavos, $forma, $status, $vencimento, $pago_em, $invoice));
    }

    $removida = !empty($pagamento['deleted']);

    if (!$removida && in_array($status, array('RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'), true) && $vencimento !== null) {
        $intervalo = assinatura_ciclo_intervalo($assinatura['idp_ciclo'] ?? 'MONTHLY');
        asaas_sql($conn, "
            UPDATE id_assinatura SET
                idas_status = CASE WHEN idas_status = 'CANCELADA' THEN 'CANCELADA' ELSE 'ATIVA' END,
                idas_acesso_ate = GREATEST(idas_acesso_ate, (CAST(? AS DATE) + INTERVAL '$intervalo' + INTERVAL '1 day')::timestamptz),
                idas_cobranca_aberta_url = CASE WHEN idas_cobranca_aberta_url = CAST(? AS TEXT) THEN NULL ELSE idas_cobranca_aberta_url END,
                idas_atualizado_em = NOW()
            WHERE idas_id = ?
        ", array($vencimento, $invoice, $idas_id));
    } elseif (!$removida && in_array($status, array('PENDING', 'OVERDUE', 'AWAITING_RISK_ANALYSIS'), true)) {
        // Vencida só vira ATRASADA quando o período já pago acabou — uma
        // cobrança atrasada antiga não derruba quem já pagou a seguinte.
        $atrasada = $status === 'OVERDUE' ? "CASE WHEN idas_acesso_ate IS NULL OR idas_acesso_ate < NOW() THEN 'ATRASADA' ELSE idas_status END" : 'idas_status';
        asaas_sql($conn, "
            UPDATE id_assinatura SET
                idas_status = $atrasada,
                idas_cobranca_aberta_url = ?,
                idas_atualizado_em = NOW()
            WHERE idas_id = ? AND idas_status <> 'CANCELADA'
        ", array($invoice, $idas_id));
    } else {
        asaas_sql($conn, "
            UPDATE id_assinatura SET idas_cobranca_aberta_url = NULL, idas_atualizado_em = NOW()
            WHERE idas_id = ? AND idas_cobranca_aberta_url = ?
        ", array($idas_id, $invoice));

        // Estorno ou chargeback (seção 4.3 de assinaturas_asaas.md: "recalcular
        // idas_acesso_ate"): antes só ia para o log, e quem pedia estorno de
        // um plano anual continuava com o ano inteiro de acesso. Agora o
        // acesso passa a ir só até onde as cobranças que CONTINUAM pagas
        // cobrem (vencimento + 1 ciclo + 1 dia, a mesma regra do pagamento);
        // sem nenhuma, acaba agora. LEAST: o recálculo nunca aumenta o
        // acesso. Cortesia e teste grátis (dados pelo admin) não mudam.
        $estornos = array('REFUNDED', 'REFUND_IN_PROGRESS', 'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE', 'AWAITING_CHARGEBACK_REVERSAL');
        if (!$removida && in_array($status, $estornos, true)) {
            $intervalo = assinatura_ciclo_intervalo($assinatura['idp_ciclo'] ?? 'MONTHLY');
            asaas_sql($conn, "
                UPDATE id_assinatura SET
                    idas_acesso_ate = LEAST(
                        COALESCE(idas_acesso_ate, NOW()),
                        COALESCE((
                            SELECT MAX((pg.idpg_vencimento + INTERVAL '$intervalo' + INTERVAL '1 day')::timestamptz)
                            FROM id_pagamento pg
                            WHERE pg.idas_id = $idas_id
                              AND pg.idpg_status IN ('RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH')
                              AND pg.idpg_vencimento IS NOT NULL
                        ), NOW())
                    ),
                    idas_atualizado_em = NOW()
                WHERE idas_id = $idas_id AND idas_status NOT IN ('CORTESIA', 'TESTE')
            ");
            asaas_avisar_admins_estorno($conn, $idas_id, $status);
        } elseif (!$removida && $status !== 'PENDING') {
            error_log('asaas_aplicar_pagamento: cobranca ' . $payment_id . ' com status ' . $status . ' — revisar acesso do idas_id ' . $idas_id);
        }
    }

    return true;
}

// Aviso no sininho dos admins quando uma cobrança é estornada ou contestada
// (o acesso do fiel já foi recalculado em asaas_aplicar_pagamento). Não
// lança erro: roda dentro da transação do webhook.
function asaas_avisar_admins_estorno($conn, $idas_id, $status) {
    $res = db_query($conn, "SELECT f.IDF_NOME FROM id_assinatura a JOIN ID_FIEL f ON f.IDF_ID = a.idf_id WHERE a.idas_id = ? LIMIT 1", array((int) $idas_id));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    $nome = $linha ? (string) $linha['IDF_NOME'] : 'um assinante';
    $motivo = strpos($status, 'CHARGEBACK') !== false ? 'uma contestação (chargeback)' : 'um estorno';
    db_query($conn, "
        INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO)
        SELECT f.IDF_ID, ?, ?, 'assinatura'
        FROM ID_ADMIN a JOIN ID_FIEL f ON f.IDF_ID = a.IDA_FIEL_ID
        WHERE a.IDA_ATIVO = 1 AND f.IDF_STATUS = 'aprovado' AND COALESCE(f.IDF_ATIVO, 1) = 1
    ", array(
        'Pagamento estornado',
        'A assinatura de ' . $nome . ' teve ' . $motivo . ' no Asaas (' . $status . '). O acesso foi recalculado pelas cobranças que continuam pagas. Confira em Admin → Assinaturas.',
    ));
}

// Consulta direto na API as cobranças da assinatura e aplica cada uma —
// rede de segurança pra quando o webhook atrasar (Render hibernando) ou
// falhar. Usada por assinar.php quando o fiel volta da página de pagamento.
function asaas_sincronizar_assinatura($conn, $assinatura) {
    $subscription_id = (string) ($assinatura['idas_asaas_subscription_id'] ?? '');
    if ($subscription_id === '' || !asaas_configurado()) {
        return false;
    }

    list($status, $resposta) = asaas_api('GET', '/subscriptions/' . rawurlencode($subscription_id) . '/payments?limit=20');
    if ($status !== 200 || !isset($resposta['data']) || !is_array($resposta['data'])) {
        return false;
    }

    try {
        foreach ($resposta['data'] as $pagamento) {
            if (is_array($pagamento)) {
                asaas_aplicar_pagamento($conn, $pagamento);
            }
        }
    } catch (\Throwable $e) {
        error_log('asaas_sincronizar_assinatura ' . $subscription_id . ': ' . $e->getMessage());
        return false;
    }
    return true;
}

// Processa um evento do webhook (já autenticado e ainda não visto). Lança
// exceção só em falha transitória (API do Asaas fora, erro de banco) — o
// webhook devolve 500 e o Asaas reenvia. Evento que não nos interessa
// passa batido.
function asaas_processar_evento($conn, array $evento) {
    asaas_evento_aplicar($conn, $evento, asaas_evento_buscar($evento));
}

// 1ª metade: relê o pagamento/assinatura na API do Asaas (nunca confia no
// corpo do webhook). Fica FORA da transação no webhook_asaas.php: a chamada
// leva até 15 s e, dentro dela, segurava a conexão e as linhas do banco.
// Devolve null quando não há nada a fazer.
function asaas_evento_buscar(array $evento) {
    $tipo = (string) ($evento['event'] ?? '');

    if (strpos($tipo, 'PAYMENT_') === 0) {
        $payment_id = (string) ($evento['payment']['id'] ?? '');
        if ($payment_id === '') {
            return null;
        }
        list($status, $pagamento) = asaas_api('GET', '/payments/' . rawurlencode($payment_id));
        if ($status === 404) {
            return null;
        }
        if ($status !== 200 || !is_array($pagamento)) {
            throw new RuntimeException('GET /payments/' . $payment_id . ' HTTP ' . $status);
        }
        return array('pagamento' => $pagamento);
    }

    if (in_array($tipo, array('SUBSCRIPTION_DELETED', 'SUBSCRIPTION_INACTIVATED'), true)) {
        $subscription_id = (string) ($evento['subscription']['id'] ?? '');
        if ($subscription_id === '') {
            return null;
        }
        list($status, $assinatura) = asaas_api('GET', '/subscriptions/' . rawurlencode($subscription_id));
        if ($status !== 200 && $status !== 404) {
            throw new RuntimeException('GET /subscriptions/' . $subscription_id . ' HTTP ' . $status);
        }
        $encerrada = $status === 404 || !empty($assinatura['deleted']) || ($assinatura['status'] ?? '') !== 'ACTIVE';
        return $encerrada ? array('assinatura_encerrada' => $subscription_id) : null;
    }
    return null;
}

// 2ª metade: grava no banco o que asaas_evento_buscar() trouxe.
function asaas_evento_aplicar($conn, array $evento, $busca) {
    if (!is_array($busca)) {
        return;
    }
    if (isset($busca['pagamento'])) {
        $pagamento = $busca['pagamento'];
        // Pix direto: cobrança criada pelo Asaas quando alguém paga o QR
        // estático de um projeto (missao_pix_qr()).
        if (!empty($pagamento['pixQrCodeId']) && missao_aplicar_pix_qr($conn, $pagamento)) {
            return;
        }
        // Contribuição em missões: cobrança avulsa (sem assinatura) criada
        // por missao_contribuir() com externalReference "missao_<idmc_id>".
        if (missao_id_da_referencia($pagamento['externalReference'] ?? '') > 0) {
            missao_aplicar_pagamento($conn, $pagamento);
            return;
        }
        asaas_aplicar_pagamento($conn, $pagamento);
        return;
    }
    if (isset($busca['assinatura_encerrada'])) {
        // Mantém idas_acesso_ate: o fiel usa até o fim do que já pagou.
        asaas_sql($conn, "
            UPDATE id_assinatura SET idas_status = 'CANCELADA', idas_cobranca_aberta_url = NULL, idas_atualizado_em = NOW()
            WHERE idas_asaas_subscription_id = ?
        ", array($busca['assinatura_encerrada']));
    }
}

// ============================================================================
// SUPERADMIN (equipe interna)
// ============================================================================
// Os admins da igreja e os da equipe têm o mesmo papel no app (ID_ADMIN).
// Superadmin é o admin cujo e-mail está em SUPERADMIN_EMAILS (Render,
// separados por vírgula): só eles veem o Financeiro e as contribuições de
// missões. Fica numa variável do Render, e não no banco, para nenhum admin
// conseguir se promover pelo painel. Telas e campos que forem só da equipe
// devem perguntar a auth_eh_superadmin().
function superadmin_emails() {
    $lista = array();
    foreach (explode(',', (string) site_env('SUPERADMIN_EMAILS')) as $email) {
        $email = mb_strtolower(trim($email));
        if ($email !== '') {
            $lista[] = $email;
        }
    }
    return $lista;
}

function auth_eh_superadmin() {
    global $conn;
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $emails = superadmin_emails();
    if (!$conn || !$emails || ($_SESSION['Usuario_tipo'] ?? '') !== 'admin') {
        return $cache = false;
    }
    $fiel_id = auth_resolve_fiel_id();
    $res = $fiel_id > 0 ? db_query($conn, "SELECT IDF_EMAIL FROM ID_FIEL WHERE IDF_ID = ? LIMIT 1", array($fiel_id)) : false;
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $cache = $linha && in_array(mb_strtolower(trim((string) $linha['IDF_EMAIL'])), $emails, true);
}

// A conta $fiel_id é de super admin (e-mail em SUPERADMIN_EMAILS)? Para as
// telas de admin não deixarem um admin da igreja mexer nela.
function usuario_e_superadmin($conn, $fiel_id) {
    $emails = superadmin_emails();
    if (!$conn || !$emails || (int) $fiel_id <= 0) {
        return false;
    }
    $res = db_query($conn, "SELECT IDF_EMAIL FROM ID_FIEL WHERE IDF_ID = ? LIMIT 1", array((int) $fiel_id));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha && in_array(mb_strtolower(trim((string) $linha['IDF_EMAIL'])), $emails, true);
}

// A conta $fiel_id está fora do alcance do admin logado? Vale para a conta
// da equipe (quando quem pede não é super admin) e, para admin num tipo
// restrito, para um admin com acesso total: quem tem menos acesso não
// edita, exclui nem desconecta quem tem mais. Na dúvida (erro de banco),
// protege.
function admin_alvo_protegido($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    if ($fiel_id <= 0 || $fiel_id === auth_resolve_fiel_id() || auth_eh_superadmin()) {
        return false;
    }
    if (usuario_e_superadmin($conn, $fiel_id)) {
        return true;
    }
    if (admin_gerencia_grupos() || !$conn || !admin_grupos_disponivel($conn)) {
        return false;
    }
    $res = db_query($conn, "SELECT ida_grupo_id FROM ID_ADMIN WHERE IDA_FIEL_ID = ? AND IDA_ATIVO = 1 LIMIT 1", array($fiel_id));
    if (!$res) {
        return true;
    }
    $linha = mysqli_fetch_assoc($res);
    return $linha && $linha['ida_grupo_id'] === null;
}

// Como auth_require(array('admin')), mas só deixa passar superadmin; o
// admin comum volta para o dashboard do painel.
function auth_require_superadmin() {
    auth_require(array('admin'));
    if (!auth_eh_superadmin()) {
        header('Location: ' . site_url('php/admin/dashboard.php'));
        exit;
    }
}

// ============================================================================
// REGISTRO DE ATIVIDADE DO PAINEL (seção 83 do bancosupabase.md)
// ============================================================================
// admin_log($conn, 'curso.excluir', 'curso', 42, array('titulo' => ...))
// depois de uma ação do painel que deu certo. Nunca derruba a ação: sem a
// tabela ou com erro de banco, não grava e segue. Tela: admin_atividade.php.
function admin_log_disponivel($conn) {
    return $conn && site_tabela_existe($conn, 'id_admin_log');
}

function admin_log($conn, $acao, $alvo_tipo = null, $alvo_id = null, array $detalhes = array()) {
    if (!admin_log_disponivel($conn)) {
        return;
    }
    $detalhes_json = $detalhes ? json_encode($detalhes, JSON_UNESCAPED_UNICODE) : null;
    if ($detalhes_json !== null && strlen($detalhes_json) > 2000) {
        $detalhes_json = mb_substr($detalhes_json, 0, 2000);
    }
    @db_query($conn, "
        INSERT INTO id_admin_log (idal_admin_id, idal_admin_nome, idal_acao, idal_alvo_tipo, idal_alvo_id, idal_detalhes, idal_ip)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ", array(
        auth_resolve_fiel_id(),
        mb_substr((string) ($_SESSION['Usuario_nome'] ?? ''), 0, 100),
        mb_substr((string) $acao, 0, 60),
        $alvo_tipo !== null ? mb_substr((string) $alvo_tipo, 0, 30) : null,
        $alvo_id !== null ? (int) $alvo_id : null,
        $detalhes_json,
        mb_substr((string) site_ip_cliente(), 0, 64),
    ));
}

// ============================================================================
// MÉTRICAS DO PAINEL (admin_metricas.php)
// ============================================================================
// Consultas separadas da tela para o CI rodá-las num Postgres de verdade
// (tests/testes_banco.php). false = erro de banco (não entra no cache).
function metricas_contar($conn, $sql, array $params = array()) {
    $res = db_query($conn, $sql, $params);
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? (int) array_values($linha)[0] : false;
}

// Funil e acessos: array('funil' => [[rótulo, n]...], 'dias' => [Y-m-d => n], 'hoje', 'semana', 'mes').
function metricas_geral($conn, $hoje) {
    $etapas = array(
        'Cadastraram' => array("SELECT COUNT(*) FROM ID_FIEL WHERE IDF_REMOVIDO_EM IS NULL", array()),
        'Aprovados' => array("SELECT COUNT(*) FROM ID_FIEL WHERE IDF_STATUS = 'aprovado' AND COALESCE(IDF_ATIVO, 1) = 1", array()),
        'Começaram a assistir' => array("SELECT COUNT(DISTINCT m.IDF_ID) FROM ID_MATRICULA m JOIN ID_PROGRESSO p ON p.IDMATR_ID = m.IDMATR_ID WHERE p.IDPR_TEMPO_ASSISTIDO_SEGUNDOS > 0 OR p.IDPR_STATUS IN ('EM_ANDAMENTO', 'CONCLUIDO')", array()),
        'Concluíram uma aula' => array("SELECT COUNT(DISTINCT m.IDF_ID) FROM ID_MATRICULA m JOIN ID_PROGRESSO p ON p.IDMATR_ID = m.IDMATR_ID WHERE p.IDPR_STATUS = 'CONCLUIDO'", array()),
        'Concluíram um curso' => array("SELECT COUNT(DISTINCT IDF_ID) FROM ID_MATRICULA WHERE IDMATR_CONCLUSAO_EM IS NOT NULL OR IDMATR_PERCENTUAL >= 100", array()),
    );
    $funil = array();
    foreach ($etapas as $rotulo => $consulta) {
        $n = metricas_contar($conn, $consulta[0], $consulta[1]);
        if ($n === false) {
            return false;
        }
        $funil[] = array($rotulo, $n);
    }
    $res = db_query($conn, "SELECT idad_data AS dia, COUNT(*) AS n FROM id_fiel_acesso_dia WHERE idad_data > CAST(? AS DATE) - 30 GROUP BY idad_data", array($hoje));
    if (!$res) {
        return false;
    }
    $dias = array();
    while ($l = mysqli_fetch_assoc($res)) {
        $dias[substr((string) $l['dia'], 0, 10)] = (int) $l['n'];
    }
    return array(
        'funil' => $funil,
        'dias' => $dias,
        'hoje' => (int) metricas_contar($conn, "SELECT COUNT(*) FROM id_fiel_acesso_dia WHERE idad_data = CAST(? AS DATE)", array($hoje)),
        'semana' => (int) metricas_contar($conn, "SELECT COUNT(DISTINCT idf_id) FROM id_fiel_acesso_dia WHERE idad_data > CAST(? AS DATE) - 7", array($hoje)),
        'mes' => (int) metricas_contar($conn, "SELECT COUNT(DISTINCT idf_id) FROM id_fiel_acesso_dia WHERE idad_data > CAST(? AS DATE) - 30", array($hoje)),
    );
}

// Aulas de um curso, na ordem do fiel, com quantos começaram e terminaram.
function metricas_aulas_curso($conn, $curso_id) {
    $res = db_query($conn, "
        SELECT ct.IDCT_ID AS id, ct.IDCT_TITULO AS titulo, ct.IDCT_DURACAO_SEGUNDOS AS duracao,
               COUNT(p.IDPR_ID) FILTER (WHERE p.IDPR_TEMPO_ASSISTIDO_SEGUNDOS > 0 OR p.IDPR_STATUS IN ('EM_ANDAMENTO', 'CONCLUIDO')) AS comecaram,
               COUNT(p.IDPR_ID) FILTER (WHERE p.IDPR_STATUS = 'CONCLUIDO') AS concluiram
        FROM ID_CONTENT ct
        LEFT JOIN ID_PROGRESSO p ON p.IDCT_ID = ct.IDCT_ID
        WHERE ct.IDC_ID = ? AND LOWER(ct.IDCT_TIPO) = 'video'
        GROUP BY ct.IDCT_ID, ct.IDCT_TITULO, ct.IDCT_DURACAO_SEGUNDOS, ct.IDCT_ORDEM
        ORDER BY ct.IDCT_ORDEM ASC NULLS LAST, ct.IDCT_ID ASC
    ", array((int) $curso_id));
    if (!$res) {
        return false;
    }
    $lista = array();
    while ($l = mysqli_fetch_assoc($res)) {
        $lista[] = array('id' => (int) $l['id'], 'titulo' => (string) $l['titulo'], 'duracao' => (int) $l['duracao'], 'comecaram' => (int) $l['comecaram'], 'concluiram' => (int) $l['concluiram']);
    }
    return $lista;
}

// Onde param numa aula: [faixa 0..9 => pessoas], em décimos do vídeo, de quem
// começou e não terminou.
function metricas_paradas_aula($conn, $aula_id, $duracao) {
    if ((int) $duracao <= 0) {
        return array();
    }
    $res = db_query($conn, "
        SELECT LEAST(9, FLOOR(10.0 * IDPR_POSICAO_SEGUNDOS / ?))::int AS faixa, COUNT(*) AS n
        FROM ID_PROGRESSO
        WHERE IDCT_ID = ? AND COALESCE(IDPR_STATUS, '') <> 'CONCLUIDO' AND IDPR_POSICAO_SEGUNDOS > 0
        GROUP BY 1
    ", array((int) $duracao, (int) $aula_id));
    if (!$res) {
        return false;
    }
    $faixas = array();
    while ($l = mysqli_fetch_assoc($res)) {
        $faixas[(int) $l['faixa']] = (int) $l['n'];
    }
    return $faixas;
}

// Números da página inicial pública (ajax_numeros_publicos.php). false = erro de banco.
function numeros_publicos($conn) {
    $res = db_query($conn, "
        SELECT
            (SELECT COUNT(*) FROM ID_CURSO WHERE IDC_STATUS = 'PUBLICADO') AS cursos,
            (SELECT COUNT(*) FROM ID_CONTENT ct JOIN ID_CURSO c ON c.IDC_ID = ct.IDC_ID
              WHERE c.IDC_STATUS = 'PUBLICADO' AND ct.IDCT_PUBLICADO = 1 AND LOWER(ct.IDCT_TIPO) = 'video') AS aulas,
            (SELECT COALESCE(SUM(ct.IDCT_DURACAO_SEGUNDOS), 0) FROM ID_CONTENT ct JOIN ID_CURSO c ON c.IDC_ID = ct.IDC_ID
              WHERE c.IDC_STATUS = 'PUBLICADO' AND ct.IDCT_PUBLICADO = 1) AS segundos,
            (SELECT COUNT(*) FROM ID_FIEL WHERE IDF_STATUS = 'aprovado' AND COALESCE(IDF_ATIVO, 1) = 1) AS pessoas
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if (!$linha) {
        return false;
    }
    return array(
        'cursos' => (int) $linha['cursos'],
        'aulas' => (int) $linha['aulas'],
        'horas' => (int) floor((int) $linha['segundos'] / 3600),
        'pessoas' => (int) $linha['pessoas'],
    );
}

// Os 3 cartões da home pública a partir dos números (função pura, testada).
// Só entra número que impressiona de verdade: zero ou muito pouco some.
function numeros_publicos_cartoes(array $n) {
    $cartoes = array();
    if (($n['cursos'] ?? 0) > 0) {
        $cartoes[] = array('numero' => (string) $n['cursos'], 'texto' => $n['cursos'] === 1 ? 'curso completo' : 'cursos completos');
    }
    if (($n['aulas'] ?? 0) > 0) {
        $cartoes[] = array('numero' => (string) $n['aulas'], 'texto' => $n['aulas'] === 1 ? 'aula em vídeo' : 'aulas em vídeo');
    }
    if (($n['pessoas'] ?? 0) >= 30) {
        $cartoes[] = array('numero' => (string) $n['pessoas'], 'texto' => 'pessoas na comunidade');
    } elseif (($n['horas'] ?? 0) >= 1) {
        $cartoes[] = array('numero' => (string) $n['horas'] . ' h', 'texto' => 'de conteúdo');
    }
    return $cartoes;
}

// Rótulo de cada ação na tela (ação desconhecida aparece com o código).
// Registro automático das telas do painel que seguem o padrão "deu certo →
// redireciona" (com erro, a tela não redireciona: mostra o erro). Chamado
// logo depois do auth_require() em Financeiro, Missões, Contribuições,
// Assinaturas, Devocionais, Testemunhos, Desafios, Destaques, Filiais,
// Funções e Tipos de fiel, que antes não registravam nada (nem quem apagou
// um lançamento ou deu uma cortesia). Grava "<area>.<acao>" com os campos
// curtos do formulário; nunca senha, token, CPF, documento nem texto longo.
function admin_log_ao_redirecionar($conn, $area) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $acao = preg_replace('/[^a-z_]/', '', strtolower((string) ($_POST['acao'] ?? ''))) ?: 'salvar';
    $detalhes = array();
    foreach ($_POST as $campo => $valor) {
        if ($campo === 'csrf_token' || $campo === 'acao' || !is_scalar($valor)
            || preg_match('/senha|token|cpf|cartao|documento|cvv/i', (string) $campo)) {
            continue;
        }
        $valor = trim((string) $valor);
        if ($valor !== '' && mb_strlen($valor) <= 80) {
            $detalhes[(string) $campo] = $valor;
        }
    }
    $detalhes = array_slice($detalhes, 0, 12, true);
    register_shutdown_function(function () use ($conn, $area, $acao, $detalhes) {
        foreach (headers_list() as $cabecalho) {
            if (stripos($cabecalho, 'Location:') === 0) {
                admin_log($conn, $area . '.' . $acao, null, null, $detalhes);
                return;
            }
        }
    });
}

function admin_log_acoes() {
    return array(
        'financeiro.lancar' => 'Financeiro: lançou um valor',
        'financeiro.excluir_lancamento' => 'Financeiro: cancelou um lançamento',
        'financeiro.configuracao' => 'Financeiro: mudou a configuração (divisão)',
        'financeiro.sincronizar_taxas' => 'Financeiro: atualizou as taxas do Asaas',
        'missoes.salvar_projeto' => 'Missões: salvou um projeto',
        'missoes.excluir_projeto' => 'Missões: excluiu um projeto',
        'missoes.lancar_oferta' => 'Missões: lançou uma oferta manual',
        'missoes.excluir_oferta' => 'Missões: cancelou uma oferta manual',
        'contribuicoes.salvar_projeto' => 'Contribuições: salvou a oferta geral',
        'contribuicoes.criar_geral' => 'Contribuições: criou a oferta geral',
        'contribuicoes.lancar_oferta' => 'Contribuições: lançou uma oferta manual',
        'contribuicoes.excluir_oferta' => 'Contribuições: cancelou uma oferta manual',
        'assinaturas.interruptores' => 'Assinaturas: ligou/desligou para os fiéis',
        'assinaturas.salvar_plano' => 'Assinaturas: salvou um plano',
        'assinaturas.cortesia' => 'Assinaturas: deu ou tirou uma cortesia',
        'assinaturas.cancelar' => 'Assinaturas: cancelou uma assinatura',
        'testemunhos.aprovar' => 'Testemunhos: publicou um testemunho',
        'testemunhos.recusar' => 'Testemunhos: recusou um testemunho',
        'testemunhos.remover' => 'Testemunhos: tirou um testemunho do mural',
        'devocionais.aprovar' => 'Devocionais: aprovou um devocional',
        'devocionais.recusar' => 'Devocionais: recusou um devocional',
        'devocionais.remover' => 'Devocionais: removeu um devocional',
        'devocionais.regra' => 'Devocionais: mudou as regras',
        'devocionais.data' => 'Devocionais: mudou a data de um devocional',
        'devocionais.reavaliar' => 'Devocionais: voltou um devocional para avaliação',
        'devocionais.salvar' => 'Devocionais: salvou um devocional',
        'desafios.salvar' => 'Desafios: salvou um desafio',
        'desafios.excluir' => 'Desafios: excluiu um desafio',
        'desafios.pausar' => 'Desafios: pausou um desafio',
        'desafios.reativar' => 'Desafios: reativou um desafio',
        'destaques.salvar' => 'Destaques da home: salvou uma arte',
        'destaques.excluir' => 'Destaques da home: excluiu uma arte',
        'destaques.mover' => 'Destaques da home: mudou a ordem',
        'destaques.salvar_curso' => 'Destaques da home: curso em destaque',
        'filiais.filial_salvar' => 'Filiais: salvou uma filial',
        'filiais.filial_excluir' => 'Filiais: excluiu uma filial',
        'filiais.fiel_filial' => 'Filiais: mudou a filial de uma pessoa',
        'funcoes.funcao_salvar' => 'Funções: salvou uma função',
        'funcoes.funcao_excluir' => 'Funções: excluiu uma função',
        'funcoes.fiel_funcao' => 'Funções: mudou a função de uma pessoa',
        'tipos_fiel.salvar_tipo' => 'Tipos de fiel: salvou um tipo',
        'tipos_fiel.excluir_tipo' => 'Tipos de fiel: excluiu um tipo',
        'tipos_fiel.tornar_padrao' => 'Tipos de fiel: mudou o tipo padrão',
        'tipos_fiel.definir_tipo' => 'Tipos de fiel: mudou o tipo de uma pessoa',
        'curso.excluir' => 'Excluiu um curso',
        'curso.arquivar' => 'Arquivou um curso',
        'igreja.editar' => 'Mudou a identidade da igreja',
        'comunidade.remover' => 'Removeu algo da comunidade (comentário, oração ou resumo)',
        'curso.status' => 'Mudou o status de um curso',
        'aula.excluir' => 'Excluiu uma aula',
        'aula.ordem' => 'Reordenou as aulas de um curso',
        'aula.publicar' => 'Publicou ou escondeu uma aula',
        'aula.lote' => 'Criou aula pelo envio em lote',
        'usuario.aprovar' => 'Aprovou cadastro',
        'usuario.negar' => 'Recusou cadastro',
        'usuario.editar' => 'Editou um usuário',
        'usuario.excluir' => 'Excluiu um usuário',
        'usuario.desconectar' => 'Desconectou um usuário',
        'usuario.link_senha' => 'Mandou link de nova senha',
        'usuario.criar' => 'Cadastrou uma pessoa (com convite)',
        'admin.promover' => 'Deu acesso de admin',
        'admin.remover' => 'Tirou acesso de admin',
        'admin.tipo' => 'Mudou o tipo de um admin',
        'grupo.salvar' => 'Criou ou editou um tipo de admin',
        'grupo.excluir' => 'Excluiu um tipo de admin',
        'mensagem.enviar' => 'Enviou mensagem/notificação',
        'live.status' => 'Mudou a live (criar, iniciar, encerrar, excluir)',
        'live.moderar' => 'Moderou o chat da live',
        'conta.2fa' => 'Ligou ou desligou a verificação em duas etapas',
        'conta.2fa_outro' => 'Desligou a verificação em duas etapas de outro admin',
    );
}

// ============================================================================
// VERIFICAÇÃO EM DUAS ETAPAS (seção 84 do bancosupabase.md)
// ============================================================================
// TOTP (RFC 6238): o app autenticador e o servidor calculam o mesmo código
// de 6 dígitos a partir da chave e da hora (passos de 30 s). Aceita o passo
// anterior e o seguinte (relógio do celular adiantado/atrasado) e nunca o
// mesmo passo duas vezes. Só para admins: ligado em Admin → Minha
// segurança; o login pede o código em login_2fa.php.
if (!defined('TOTP_PASSO_SEGUNDOS')) {
    define('TOTP_PASSO_SEGUNDOS', 30);
}

function doisfa_disponivel($conn) {
    return $conn && tabela_tem_coluna($conn, 'id_fiel', 'idf_2fa_segredo');
}

function totp_base32_codificar($binario) {
    $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split((string) $binario) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }
    $saida = '';
    foreach (str_split($bits, 5) as $grupo) {
        $saida .= $alfabeto[bindec(str_pad($grupo, 5, '0', STR_PAD_RIGHT))];
    }
    return $saida;
}

// Aceita minúsculas, espaços e "=" (como os apps mostram). false se inválido.
function totp_base32_decodificar($texto) {
    $texto = strtoupper(preg_replace('/[\s=]/', '', (string) $texto));
    if ($texto === '' || !preg_match('/^[A-Z2-7]+$/', $texto)) {
        return false;
    }
    $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($texto) as $letra) {
        $bits .= str_pad(decbin(strpos($alfabeto, $letra)), 5, '0', STR_PAD_LEFT);
    }
    $saida = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $saida .= chr(bindec($byte));
        }
    }
    return $saida;
}

// Chave nova: 20 bytes aleatórios (160 bits, o recomendado pela RFC 4226).
function totp_novo_segredo() {
    return totp_base32_codificar(random_bytes(20));
}

function totp_codigo($segredo, $passo) {
    $chave = totp_base32_decodificar($segredo);
    if ($chave === false) {
        return '';
    }
    $contador = pack('N2', (int) floor($passo / 4294967296), (int) $passo & 0xFFFFFFFF);
    $hmac = hash_hmac('sha1', $contador, $chave, true);
    $inicio = ord($hmac[19]) & 0x0F;
    $numero = ((ord($hmac[$inicio]) & 0x7F) << 24) | (ord($hmac[$inicio + 1]) << 16) | (ord($hmac[$inicio + 2]) << 8) | ord($hmac[$inicio + 3]);
    return str_pad((string) ($numero % 1000000), 6, '0', STR_PAD_LEFT);
}

// Passo aceito (para gravar em idf_2fa_ultimo_passo) ou false.
function totp_conferir($segredo, $codigo, $agora = null, $ultimo_passo = 0) {
    $codigo = preg_replace('/\D/', '', (string) $codigo);
    if (strlen($codigo) !== 6) {
        return false;
    }
    $passo_atual = (int) floor(($agora ?? time()) / TOTP_PASSO_SEGUNDOS);
    foreach (array(0, -1, 1) as $desvio) {
        $passo = $passo_atual + $desvio;
        if ($passo > (int) $ultimo_passo && hash_equals(totp_codigo($segredo, $passo), $codigo)) {
            return $passo;
        }
    }
    return false;
}

// Endereço que o app autenticador entende (vai no QR Code).
function totp_uri($segredo, $conta, $emissor) {
    return 'otpauth://totp/' . rawurlencode($emissor . ':' . $conta)
        . '?secret=' . rawurlencode($segredo) . '&issuer=' . rawurlencode($emissor) . '&algorithm=SHA1&digits=6&period=' . TOTP_PASSO_SEGUNDOS;
}

// Chave no banco cifrada com a APP_CHAVE (variável do Render, 32+ letras):
// quem só lê a tabela id_fiel (backup, acesso indevido ao Supabase) não
// gera códigos. AES-256-GCM dos 20 bytes da chave, com o id da conta como
// dado associado (copiada para outra linha, não decifra), em base64url:
// nonce (12) + tag (16) + 20 bytes = 64 letras, o tamanho da coluna. A
// chave antiga (base32, 32 letras) segue valendo e é cifrada no próximo
// login certo (doisfa_conferir_login). Sem APP_CHAVE grava como antes.
function doisfa_chave_cifra() {
    $chave = trim((string) site_env('APP_CHAVE'));
    return strlen($chave) >= 32 ? hash_hmac('sha256', 'manancial-2fa-segredo', $chave, true) : '';
}

function doisfa_segredo_cifrado($valor) {
    return (bool) preg_match('/^[A-Za-z0-9_-]{64}$/', (string) $valor);
}

function doisfa_cifrar_segredo($segredo, $fiel_id) {
    $chave = doisfa_chave_cifra();
    $bruto = totp_base32_decodificar($segredo);
    if ($chave === '' || $bruto === false || strlen($bruto) !== 20) {
        return (string) $segredo;
    }
    $nonce = random_bytes(12);
    $tag = '';
    $cifrado = openssl_encrypt($bruto, 'aes-256-gcm', $chave, OPENSSL_RAW_DATA, $nonce, $tag, 'idf:' . (int) $fiel_id, 16);
    if ($cifrado === false) {
        return (string) $segredo;
    }
    return rtrim(strtr(base64_encode($nonce . $tag . $cifrado), '+/', '-_'), '=');
}

// Chave em base32 para o TOTP; '' se não decifra (sem APP_CHAVE, APP_CHAVE
// trocada ou valor de outra conta): nenhum código confere.
function doisfa_decifrar_segredo($valor, $fiel_id) {
    $valor = (string) $valor;
    if (!doisfa_segredo_cifrado($valor)) {
        return $valor;
    }
    $chave = doisfa_chave_cifra();
    $dados = base64_decode(strtr($valor, '-_', '+/'), true);
    if ($chave === '' || $dados === false || strlen($dados) !== 48) {
        return '';
    }
    $bruto = openssl_decrypt(substr($dados, 28), 'aes-256-gcm', $chave, OPENSSL_RAW_DATA, substr($dados, 0, 12), substr($dados, 12, 16), 'idf:' . (int) $fiel_id);
    return $bruto === false ? '' : totp_base32_codificar($bruto);
}

// 8 códigos de reserva "xxxx-xxxx" (uso único). Devolve os códigos (para
// mostrar UMA vez) e o JSON com o SHA-256 de cada um (para o banco).
function doisfa_gerar_reserva() {
    $codigos = array();
    $hashes = array();
    for ($i = 0; $i < 8; $i++) {
        $bruto = strtolower(bin2hex(random_bytes(4)));
        $codigo = substr($bruto, 0, 4) . '-' . substr($bruto, 4, 4);
        $codigos[] = $codigo;
        $hashes[] = hash('sha256', $codigo);
    }
    return array('codigos' => $codigos, 'json' => json_encode($hashes));
}

// Confere um código de reserva; se valer, devolve o JSON sem ele (para
// gravar), senão false.
function doisfa_usar_reserva($json, $codigo) {
    $codigo = strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string) $codigo));
    if (strlen($codigo) !== 8) {
        return false;
    }
    $hash = hash('sha256', substr($codigo, 0, 4) . '-' . substr($codigo, 4, 4));
    $lista = json_decode((string) $json, true);
    if (!is_array($lista)) {
        return false;
    }
    foreach ($lista as $i => $guardado) {
        if (is_string($guardado) && hash_equals($guardado, $hash)) {
            unset($lista[$i]);
            return json_encode(array_values($lista));
        }
    }
    return false;
}

// Dados do 2FA de uma conta (null sem as colunas).
function doisfa_da_conta($conn, $fiel_id) {
    if (!doisfa_disponivel($conn)) {
        return null;
    }
    $res = db_query($conn, "SELECT idf_2fa_segredo, idf_2fa_ativo_em, idf_2fa_ultimo_passo, idf_2fa_reserva FROM id_fiel WHERE idf_id = ? LIMIT 1", array((int) $fiel_id));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ?: null;
}

// Estado do 2FA na hora do login: 'ligado', 'sem_2fa' ou 'erro'. Consulta o
// banco direto (sem o cache do schema, que pode estar até 5 min atrasado
// num container) e separa "a coluna não existe" (banco sem a seção 84 =
// sem 2FA) de falha de banco, que recusa o login (fail-closed).
function doisfa_estado_login($conn, $fiel_id) {
    if (!$conn) {
        return 'erro';
    }
    $res = db_query($conn, "SELECT idf_2fa_ativo_em FROM id_fiel WHERE idf_id = ? LIMIT 1", array((int) $fiel_id));
    if ($res === false) {
        $detalhe = (string) ($GLOBALS['site_pg_last_error'] ?? '');
        return (stripos($detalhe, 'idf_2fa_ativo_em') !== false && stripos($detalhe, 'does not exist') !== false) ? 'sem_2fa' : 'erro';
    }
    $linha = mysqli_fetch_assoc($res);
    return !empty($linha['idf_2fa_ativo_em']) ? 'ligado' : 'sem_2fa';
}

// Confere o código digitado no login (6 dígitos do app ou um código de
// reserva) e grava o passo/reserva usados. Devolve true/false.
function doisfa_conferir_login($conn, $fiel_id, $codigo) {
    $conta = doisfa_da_conta($conn, $fiel_id);
    if (!$conta || empty($conta['idf_2fa_ativo_em'])) {
        return false;
    }
    $guardado = (string) $conta['idf_2fa_segredo'];
    $passo = totp_conferir(doisfa_decifrar_segredo($guardado, $fiel_id), $codigo, null, (int) ($conta['idf_2fa_ultimo_passo'] ?? 0));
    $ok = false;
    if ($passo !== false) {
        // "AND ultimo < passo": duas abas com o mesmo código não entram as duas.
        db_query($conn, "UPDATE id_fiel SET idf_2fa_ultimo_passo = ? WHERE idf_id = ? AND COALESCE(idf_2fa_ultimo_passo, 0) < ?", array($passo, (int) $fiel_id, $passo));
        $ok = (int) ($GLOBALS['site_pg_last_affected_rows'] ?? 0) === 1;
    } else {
        $nova_reserva = doisfa_usar_reserva($conta['idf_2fa_reserva'] ?? '', $codigo);
        if ($nova_reserva !== false) {
            db_query($conn, "UPDATE id_fiel SET idf_2fa_reserva = ? WHERE idf_id = ? AND idf_2fa_reserva = ?", array($nova_reserva, (int) $fiel_id, (string) $conta['idf_2fa_reserva']));
            $ok = (int) ($GLOBALS['site_pg_last_affected_rows'] ?? 0) === 1;
        }
    }
    // Chave antiga (sem cifra): cifra agora que a APP_CHAVE existe.
    if ($ok && !doisfa_segredo_cifrado($guardado)) {
        $cifrado = doisfa_cifrar_segredo($guardado, $fiel_id);
        if ($cifrado !== $guardado) {
            db_query($conn, "UPDATE id_fiel SET idf_2fa_segredo = ? WHERE idf_id = ? AND idf_2fa_segredo = ?", array($cifrado, (int) $fiel_id, $guardado));
        }
    }
    return $ok;
}

// ============================================================================
// GRUPOS DE PERMISSÕES DOS ADMINS (seção 73 do bancosupabase.md)
// ============================================================================
// Cada admin fica num grupo (id_admin_grupo) que lista as telas do painel
// que ele abre, ou com "acesso total" (ida_grupo_id NULL, como era antes).
// auth_require(array('admin')) e auth_require_ajax() barram a tela fora do
// grupo; admin_layout_menu() esconde o item. Superadmin sempre tem tudo.
// Gerenciado em Admin → Tipos de Admin ("grupo" no banco = tipo de admin).

// Telas que entram nos grupos: chave => array(rótulo, scripts de php/admin/).
// O Dashboard e as notificações do painel ficam fora (valem para todos); o
// Financeiro e as Contribuições estão em admin_telas_restritas().
function admin_telas() {
    return array(
        'cursos' => array('Cursos', array('admin_cursos.php', 'admin_editar_curso.php')),
        'conteudos' => array('Gerenciar Vídeos', array('admin_conteudos.php', 'admin_editar_conteudo.php', 'r2_upload_url.php', 'admin_grade_curso.php')),
        'transmissao' => array('Ao Vivo', array('admin_transmissao.php', 'ajax_admin_transmissao_comentarios.php')),
        'aprovar' => array('Aprovar Usuários', array('admin_aprovar_usuarios.php')),
        'testemunhos' => array('Testemunhos', array('admin_testemunhos.php')),
        'desafios' => array('Desafios', array('admin_desafios.php')),
        'devocionais' => array('Devocionais', array('admin_devocionais.php')),
        'funcoes' => array('Funções na Igreja', array('admin_funcoes.php')),
        'filiais' => array('Filiais', array('admin_filiais.php')),
        'progresso' => array('Ver Progresso', array('admin_visao_usuario.php')),
        'engajamento' => array('Online e Ofensiva', array('admin_engajamento.php')),
        'assinaturas' => array('Assinaturas', array('admin_assinaturas.php')),
        'missoes' => array('Missões', array('admin_missoes.php')),
        'destaques' => array('Destaques da home', array('admin_destaques_home.php')),
        'igreja' => array('Identidade da igreja', array('admin_igreja.php')),
        'permissoes' => array('Tipos de Admin', array('admin_gerenciar_permissoes.php')),
        'usuarios' => array('Gerenciar Usuários', array('admin_gerenciar_usuarios.php')),
        'tipos_fiel' => array('Tipos de fiel', array('admin_tipos_fiel.php')),
        'mensagem' => array('Enviar Mensagem', array('admin_enviar_mensagem.php')),
        'atividade' => array('Registro de atividade', array('admin_atividade.php')),
        'metricas' => array('Métricas', array('admin_metricas.php')),
    );
}

// Telas com dinheiro da equipe: só superadmin, a não ser que um superadmin
// as marque num tipo de admin. "Acesso total" NÃO inclui estas, e só
// superadmin marca ou desmarca (Admin → Tipos de Admin).
function admin_telas_restritas() {
    return array(
        'financeiro' => array('Financeiro', array('admin_financeiro.php')),
        'contribuicoes' => array('Contribuições (oferta geral)', array('admin_contribuicoes.php')),
    );
}

// Partes do Financeiro que um tipo de admin com a tela Financeiro vê. No
// tipo ficam como "financeiro.<parte>" na mesma lista das telas.
function financeiro_partes() {
    return array(
        'resumo' => 'Resumo: entradas, lucro, divisão e repasses à igreja',
        'origens' => 'De onde veio e para onde foi',
        'lancamentos' => 'Lançamentos manuais (ver, lançar e excluir)',
        'exportar' => 'Exportar planilha',
        'regras' => 'Regras da divisão (porcentagem e modo)',
    );
}

function admin_grupos_disponivel($conn) {
    return tabela_tem_coluna($conn, 'id_admin', 'ida_grupo_id') && site_tabela_existe($conn, 'id_admin_grupo');
}

// "cursos, conteudos,xyz" → array('cursos', 'conteudos') (só chaves válidas,
// incluindo as restritas e as partes do Financeiro).
function admin_telas_da_lista($texto) {
    $validas = admin_telas() + admin_telas_restritas();
    foreach (financeiro_partes() as $parte => $rotulo) {
        $validas['financeiro.' . $parte] = true;
    }
    $lista = array();
    foreach (explode(',', (string) $texto) as $chave) {
        $chave = trim($chave);
        if ($chave !== '' && isset($validas[$chave]) && !in_array($chave, $lista, true)) {
            $lista[] = $chave;
        }
    }
    return $lista;
}

// Telas do admin logado: null = acesso total; array = só essas.
function admin_telas_do_logado() {
    global $conn;
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    if (($_SESSION['Usuario_tipo'] ?? '') !== 'admin') {
        return $cache = array();
    }
    if (auth_eh_superadmin() || !$conn || !admin_grupos_disponivel($conn)) {
        return $cache = null;
    }
    $res = db_query($conn, "
        SELECT a.ida_grupo_id, g.idag_telas
        FROM ID_ADMIN a
        LEFT JOIN id_admin_grupo g ON g.idag_id = a.ida_grupo_id
        WHERE a.IDA_FIEL_ID = ? AND a.IDA_ATIVO = 1
        LIMIT 1
    ", array(auth_resolve_fiel_id()));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if (!$res) {
        // Erro de banco: na dúvida, só o Dashboard (sem cache, para a
        // próxima chamada tentar de novo). Abrir tudo dava a um admin
        // restrito as telas que o tipo dele não tem.
        return array();
    }
    if (!$linha || $linha['ida_grupo_id'] === null) {
        return $cache = null;
    }
    return $cache = admin_telas_da_lista($linha['idag_telas'] ?? '');
}

// O admin logado pode abrir a tela $chave (de admin_telas() ou
// admin_telas_restritas())?
// Moderação da comunidade feita pelo lado do fiel (ocultar comentário de
// outra pessoa, remover oração ou resumo, fixar nota na aula): só admin com
// a tela de Testemunhos ou de Gerenciar Vídeos. Antes qualquer tipo de
// admin (até um só de Cursos) apagava o que quisesse, sem registro.
function admin_pode_moderar_comunidade() {
    return ($_SESSION['Usuario_tipo'] ?? '') === 'admin'
        && (admin_pode_acessar('testemunhos') || admin_pode_acessar('conteudos'));
}

function admin_pode_acessar($chave) {
    $telas = admin_telas_do_logado();
    // Financeiro e Contribuições: superadmin, ou tipo de admin que tenha a
    // tela marcada. Acesso total (e erro de banco, que vira null) não basta.
    if (isset(admin_telas_restritas()[$chave])) {
        return auth_eh_superadmin() || (is_array($telas) && in_array($chave, $telas, true));
    }
    return $telas === null || in_array($chave, $telas, true);
}

// O admin logado vê a parte $parte (de financeiro_partes()) do Financeiro?
function financeiro_pode_ver($parte) {
    if (auth_eh_superadmin()) {
        return true;
    }
    $telas = admin_telas_do_logado();
    return admin_pode_acessar('financeiro') && is_array($telas) && in_array('financeiro.' . $parte, $telas, true);
}

// Tela de um script de php/admin/ (null = livre para todo admin).
function admin_tela_do_script($script) {
    foreach (admin_telas() + admin_telas_restritas() as $chave => $info) {
        if (in_array($script, $info[1], true)) {
            return $chave;
        }
    }
    return null;
}

function admin_pode_abrir_script($script) {
    $chave = admin_tela_do_script($script);
    return $chave === null || admin_pode_acessar($chave);
}

// O admin logado gerencia grupos e escolhe o grupo de cada admin? Só quem
// tem acesso total (ou é superadmin): quem está num grupo restrito, mesmo
// com a tela Permissões, não consegue se dar mais acesso.
function admin_gerencia_grupos() {
    return admin_telas_do_logado() === null;
}

function admin_grupos_lista($conn) {
    if (!admin_grupos_disponivel($conn)) {
        return array();
    }
    $res = mysqli_query($conn, "
        SELECT g.*, (SELECT COUNT(*) FROM ID_ADMIN a WHERE a.ida_grupo_id = g.idag_id) AS membros
        FROM id_admin_grupo g
        ORDER BY LOWER(g.idag_nome)
    ");
    $grupos = array();
    foreach ($res ? $res->todas_as_linhas() : array() as $g) {
        $g['telas'] = admin_telas_da_lista($g['idag_telas']);
        $grupos[(int) $g['idag_id']] = $g;
    }
    return $grupos;
}

// ============================================================================
// TIPOS DE FIEL (seção 74 do bancosupabase.md)
// ============================================================================
// Como os grupos dos admins, mas para os fiéis: cada tipo (id_fiel_tipo)
// diz o que o fiel pode mudar no próprio perfil. Quem não tem tipo
// escolhido (idf_tipo_id NULL) fica no tipo padrão. Gerenciado em Admin →
// Tipos de fiel. Permissão nova: acrescentar aqui e conferir com
// fiel_pode() onde a ação acontece.

// Sem a seção 74 (ou sem tipo padrão), vale o que o perfil já permitia.
define('FIEL_PERMISSOES_SEM_TIPO', 'dados,foto,membro');

function fiel_permissoes_catalogo() {
    return array(
        'nome' => 'Trocar o próprio nome',
        'email' => 'Trocar o e-mail de login (pede a senha atual)',
        'cpf' => 'Informar ou corrigir o CPF',
        'dados' => 'Editar dados pessoais (WhatsApp, nascimento, família, cidade)',
        'foto' => 'Trocar a foto de perfil',
        'membro' => 'Dizer se é membro da igreja, desde quando e a filial',
    );
}

function fiel_tipos_disponivel($conn) {
    return tabela_tem_coluna($conn, 'id_fiel', 'idf_tipo_id') && site_tabela_existe($conn, 'id_fiel_tipo');
}

// "nome,xyz,foto" → array('nome', 'foto') (só chaves do catálogo).
function fiel_permissoes_da_lista($texto) {
    $catalogo = fiel_permissoes_catalogo();
    $lista = array();
    foreach (explode(',', (string) $texto) as $chave) {
        $chave = trim($chave);
        if ($chave !== '' && isset($catalogo[$chave]) && !in_array($chave, $lista, true)) {
            $lista[] = $chave;
        }
    }
    return $lista;
}

// Permissões de um fiel (o tipo dele ou o padrão).
function fiel_permissoes($conn, $fiel_id) {
    static $cache = array();
    $fiel_id = (int) $fiel_id;
    if (isset($cache[$fiel_id])) {
        return $cache[$fiel_id];
    }
    if (!$conn || $fiel_id <= 0 || !fiel_tipos_disponivel($conn)) {
        return $cache[$fiel_id] = fiel_permissoes_da_lista(FIEL_PERMISSOES_SEM_TIPO);
    }
    $res = db_query($conn, "
        SELECT COALESCE(t.idft_permissoes, p.idft_permissoes) AS permissoes
        FROM ID_FIEL f
        LEFT JOIN id_fiel_tipo t ON t.idft_id = f.idf_tipo_id
        LEFT JOIN id_fiel_tipo p ON p.idft_padrao = 1
        WHERE f.IDF_ID = ?
        LIMIT 1
    ", array($fiel_id));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if (!$linha || $linha['permissoes'] === null) {
        return $cache[$fiel_id] = fiel_permissoes_da_lista(FIEL_PERMISSOES_SEM_TIPO);
    }
    return $cache[$fiel_id] = fiel_permissoes_da_lista($linha['permissoes']);
}

// O fiel logado pode $chave (de fiel_permissoes_catalogo())?
function fiel_pode($chave) {
    global $conn;
    return in_array($chave, fiel_permissoes($conn, auth_resolve_fiel_id()), true);
}

function fiel_tipos_lista($conn) {
    if (!fiel_tipos_disponivel($conn)) {
        return array();
    }
    $res = mysqli_query($conn, "
        SELECT t.*, (SELECT COUNT(*) FROM ID_FIEL f WHERE f.idf_tipo_id = t.idft_id AND NOT EXISTS (SELECT 1 FROM ID_ADMIN a WHERE a.IDA_FIEL_ID = f.IDF_ID AND a.IDA_ATIVO = 1)) AS membros
        FROM id_fiel_tipo t
        ORDER BY t.idft_padrao DESC, LOWER(t.idft_nome)
    ");
    $tipos = array();
    foreach ($res ? $res->todas_as_linhas() : array() as $t) {
        $t['permissoes'] = fiel_permissoes_da_lista($t['idft_permissoes']);
        $tipos[(int) $t['idft_id']] = $t;
    }
    return $tipos;
}

// Nome, e-mail e CPF mudados pelo próprio fiel (perfil.php), cada um só
// se o tipo dele permitir. $dados: nome, email, cpf, senha_atual (exigida
// para trocar o e-mail, que é o login). Devolve '' ou a mensagem de erro.
function fiel_identificacao_salvar($conn, $fiel_id, array $dados) {
    $fiel_id = (int) $fiel_id;
    $res = db_query($conn, "SELECT IDF_NOME, IDF_EMAIL, IDF_CPF, IDF_SENHA_HASH FROM ID_FIEL WHERE IDF_ID = ? LIMIT 1", array($fiel_id));
    $atual = $res ? mysqli_fetch_assoc($res) : null;
    if (!$atual) {
        return 'Não foi possível salvar agora. Tente de novo em instantes.';
    }

    $sets = array();
    $params = array();

    if (fiel_pode('nome') && array_key_exists('nome', $dados)) {
        $nome = trim(preg_replace('/\s+/', ' ', (string) $dados['nome']));
        if (mb_strlen($nome) < 3 || mb_strlen($nome) > 100) {
            return 'Informe o nome completo (de 3 a 100 caracteres).';
        }
        if ($nome !== (string) $atual['IDF_NOME']) {
            $sets[] = 'IDF_NOME = ?';
            $params[] = $nome;
        }
    }

    if (fiel_pode('cpf') && array_key_exists('cpf', $dados)) {
        $cpf = preg_replace('/\D/', '', (string) $dados['cpf']);
        if ($cpf !== '' && !assinatura_cpf_valido($cpf)) {
            return 'CPF inválido.';
        }
        if ($cpf !== preg_replace('/\D/', '', (string) ($atual['IDF_CPF'] ?? ''))) {
            $res_cpf = $cpf !== '' ? db_query($conn, "SELECT 1 FROM ID_FIEL WHERE IDF_CPF = ? AND IDF_ID <> ? LIMIT 1", array($cpf, $fiel_id)) : false;
            if ($res_cpf && mysqli_num_rows($res_cpf) > 0) {
                return 'Esse CPF já está em outra conta. Fale com a equipe pelo Contato.';
            }
            // Vazio vira NULL: IDF_CPF é UNIQUE.
            $sets[] = 'IDF_CPF = ?';
            $params[] = $cpf !== '' ? $cpf : null;
        }
    }

    $email_novo = null;
    if (fiel_pode('email') && array_key_exists('email', $dados)) {
        $email = mb_strtolower(trim((string) $dados['email']));
        if ($email !== mb_strtolower((string) $atual['IDF_EMAIL'])) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
                return 'Informe um e-mail válido.';
            }
            // Mesmo limite de senha_trocar(): 5 erros em 15 min.
            $email_lower = mb_substr(mb_strtolower((string) $atual['IDF_EMAIL']), 0, 100);
            db_query($conn, "DELETE FROM ID_LOGIN_TENTATIVA WHERE LOWER(IDLT_EMAIL) = ? AND IDLT_CRIADO_EM <= NOW() - INTERVAL '900 seconds'", array($email_lower));
            $res_tentativas = db_query($conn, "SELECT COUNT(*) AS total FROM ID_LOGIN_TENTATIVA WHERE LOWER(IDLT_EMAIL) = ?", array($email_lower));
            $tentativas = $res_tentativas ? mysqli_fetch_assoc($res_tentativas) : null;
            if ($tentativas && (int) $tentativas['total'] >= 5) {
                return 'Muitas tentativas com a senha errada. Aguarde 15 minutos e tente de novo.';
            }
            $hash = (string) ($atual['IDF_SENHA_HASH'] ?? '');
            $senha = (string) ($dados['senha_atual'] ?? '');
            $senha_ok = $hash !== '' && password_verify($senha, $hash);
            if (!$senha_ok) {
                db_query($conn, "INSERT INTO ID_LOGIN_TENTATIVA (IDLT_EMAIL) VALUES (?)", array($email_lower));
                return 'Para trocar o e-mail, confirme com a sua senha atual.';
            }
            if (in_array($email, superadmin_emails(), true)) {
                return 'Esse e-mail não pode ser usado. Fale com a equipe pelo Contato.';
            }
            $res_email = db_query($conn, "SELECT 1 FROM ID_FIEL WHERE LOWER(IDF_EMAIL) = ? AND IDF_ID <> ? LIMIT 1", array($email, $fiel_id));
            if (!$res_email || mysqli_num_rows($res_email) > 0) {
                return 'Esse e-mail já está em outra conta.';
            }
            $sets[] = 'IDF_EMAIL = ?';
            $params[] = $email;
            $email_novo = $email;
        }
    }

    if (!$sets) {
        return '';
    }
    $sets[] = 'IDF_ATUALIZADO_EM = NOW()';
    $params[] = $fiel_id;
    if (!db_query($conn, 'UPDATE ID_FIEL SET ' . implode(', ', $sets) . ' WHERE IDF_ID = ?', $params)) {
        return site_erro_banco('Não foi possível salvar.');
    }
    // A sessão guarda o e-mail (login) e o nome mostrados no menu.
    if ($email_novo !== null) {
        $_SESSION['Usuario_logado'] = $email_novo;
    }
    if (in_array('IDF_NOME = ?', $sets, true)) {
        $_SESSION['Usuario_nome'] = $params[array_search('IDF_NOME = ?', $sets, true)];
    }
    return '';
}

// ============================================================================
// VALORES EM DINHEIRO
// ============================================================================
// Texto digitado → centavos. Aceita "39,90", "1.290,00", "1.000", "39.90" e
// "R$ 50". Devolve 0 para texto que não é valor.
function site_valor_para_centavos($texto) {
    $texto = str_replace(array('R$', ' ', "\xC2\xA0"), '', trim((string) $texto));
    if (preg_match('/^\d{1,3}(\.\d{3})+$/', $texto)) {
        // Só pontos de milhar ("1.000", "12.500.000"), sem centavos.
        $texto = str_replace('.', '', $texto);
    } elseif (strpos($texto, ',') !== false) {
        $texto = str_replace(array('.', ','), array('', '.'), $texto);
    }
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $texto)) {
        return 0;
    }
    return (int) round(((float) $texto) * 100);
}

// netValue da cobrança do Asaas (o que sobra depois da taxa), em centavos;
// null quando o Asaas não informou.
function asaas_valor_liquido_centavos(array $pagamento) {
    if (!isset($pagamento['netValue']) || !is_numeric($pagamento['netValue'])) {
        return null;
    }
    return (int) round(((float) $pagamento['netValue']) * 100);
}

// ============================================================================
// FINANCEIRO DA EQUIPE (seção 71 do bancosupabase.md)
// ============================================================================
// Admin → Financeiro (php/admin/admin_financeiro.php), só superadmin. Junta
// o que entrou pelo Asaas (assinaturas e missões), o que foi lançado à mão
// (custos, outras receitas, repasses) e calcula o lucro e a parte da
// igreja. Regras em docs/interno/financeiro.md.

// Status do Asaas que contam como dinheiro recebido.
function financeiro_status_pagos() {
    return array('RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH');
}

function financeiro_disponivel($conn) {
    return site_tabela_existe($conn, 'id_financeiro_lancamento');
}

// Porcentagem do lucro que vai para a igreja (padrão 50).
function financeiro_percentual_igreja() {
    return min(100, max(0, (int) site_config('financeiro_percentual_igreja', '50')));
}

// true = o dinheiro de missões entra no lucro dividido (decisão atual);
// false = missões ficam num caixa separado, repassado aos projetos.
function financeiro_missoes_no_lucro() {
    return site_config('financeiro_missoes_no_lucro', '1') === '1';
}

function financeiro_categorias() {
    return array(
        'custo' => array('Hospedagem (Render)', 'Banco de dados (Supabase)', 'Armazenamento (Cloudflare R2)', 'E-mail (Brevo)', 'Notificações (OneSignal)', 'Domínio', 'Taxas bancárias', 'Equipamento', 'Outros'),
        'receita' => array('Ajuda', 'Doação', 'Serviço avulso', 'Outros'),
        'repasse' => array('Divisão do lucro', 'Repasse de missão', 'Outros'),
    );
}

// Linha zerada de um mês (ou do total) do Financeiro.
function financeiro_linha_vazia() {
    return array(
        'assinaturas_bruto' => 0, 'assinaturas_liquido' => 0, 'assinaturas_qtd' => 0, 'assinaturas_sem_taxa' => 0,
        'missoes_bruto' => 0, 'missoes_liquido' => 0, 'missoes_qtd' => 0, 'missoes_sem_taxa' => 0,
        'missoes_manual_equipe' => 0, 'missoes_manual_igreja' => 0,
        // Contribuições = oferta geral (missoes_oferta_geral_id()), fora das missões.
        'contribuicoes_bruto' => 0, 'contribuicoes_liquido' => 0, 'contribuicoes_qtd' => 0, 'contribuicoes_sem_taxa' => 0,
        'contribuicoes_manual_equipe' => 0, 'contribuicoes_manual_igreja' => 0,
        'receitas' => 0, 'custos' => 0, 'repasses_igreja' => 0, 'repasses_missao' => 0,
    );
}

// Movimentos de $inicio a $fim (datas 'Y-m-d', inclusive), um item por mês
// ('2026-10' => financeiro_linha_vazia() preenchida), em ordem.
// As datas de pagamento vêm do Asaas sem hora: idpg_pago_em é lido com
// ::date (gravado à meia-noite UTC) e idmc_pago_em já é DATE.
function financeiro_movimentos_por_mes($conn, $inicio, $fim) {
    $meses = array();
    $cursor = strtotime(substr($inicio, 0, 7) . '-01');
    $ultimo = strtotime(substr($fim, 0, 7) . '-01');
    while ($cursor !== false && $cursor <= $ultimo && count($meses) < 600) {
        $meses[date('Y-m', $cursor)] = financeiro_linha_vazia();
        $cursor = strtotime('+1 month', $cursor);
    }
    $somar = function ($mes, array $valores) use (&$meses) {
        if (!isset($meses[$mes])) {
            return;
        }
        foreach ($valores as $chave => $valor) {
            $meses[$mes][$chave] += (int) $valor;
        }
    };
    $pagos = financeiro_status_pagos();

    if (site_tabela_existe($conn, 'id_pagamento')) {
        $liquido = tabela_tem_coluna($conn, 'id_pagamento', 'idpg_valor_liquido_centavos') ? 'idpg_valor_liquido_centavos' : 'NULL';
        $res = db_query($conn, "
            SELECT to_char(idpg_pago_em::date, 'YYYY-MM') AS mes,
                   SUM(idpg_valor_centavos) AS bruto,
                   SUM(COALESCE($liquido, idpg_valor_centavos)) AS liquido,
                   COUNT(*) AS qtd,
                   COUNT(*) FILTER (WHERE $liquido IS NULL) AS sem_taxa
            FROM id_pagamento
            WHERE idpg_status IN (?, ?, ?) AND idpg_pago_em IS NOT NULL
              AND idpg_pago_em::date BETWEEN CAST(? AS DATE) AND CAST(? AS DATE)
            GROUP BY 1
        ", array_merge($pagos, array($inicio, $fim)));
        while ($res && $l = mysqli_fetch_assoc($res)) {
            $somar($l['mes'], array('assinaturas_bruto' => $l['bruto'], 'assinaturas_liquido' => $l['liquido'], 'assinaturas_qtd' => $l['qtd'], 'assinaturas_sem_taxa' => $l['sem_taxa']));
        }
    }

    if (missoes_disponivel($conn)) {
        // A oferta geral (aba Contribuir) vira "contribuicoes_*"; o resto, "missoes_*".
        $res = db_query($conn, "
            SELECT to_char(idmc_pago_em, 'YYYY-MM') AS mes, idmc_origem AS origem, idmc_recebido_por AS recebido_por,
                   CASE WHEN idmp_id = ? THEN 1 ELSE 0 END AS geral,
                   SUM(idmc_valor_centavos) AS bruto,
                   SUM(COALESCE(idmc_valor_liquido_centavos, idmc_valor_centavos)) AS liquido,
                   COUNT(*) AS qtd,
                   COUNT(*) FILTER (WHERE idmc_valor_liquido_centavos IS NULL) AS sem_taxa
            FROM id_missao_contribuicao
            WHERE idmc_status IN (?, ?, ?) AND idmc_pago_em BETWEEN CAST(? AS DATE) AND CAST(? AS DATE)
            GROUP BY 1, 2, 3, 4
        ", array_merge(array(missoes_oferta_geral_id()), $pagos, array($inicio, $fim)));
        while ($res && $l = mysqli_fetch_assoc($res)) {
            $p = (int) $l['geral'] === 1 ? 'contribuicoes' : 'missoes';
            if ($l['origem'] === 'manual') {
                $somar($l['mes'], array($p . ($l['recebido_por'] === 'equipe' ? '_manual_equipe' : '_manual_igreja') => $l['bruto']));
            } elseif ($l['recebido_por'] === 'equipe') {
                $somar($l['mes'], array($p . '_bruto' => $l['bruto'], $p . '_liquido' => $l['liquido'], $p . '_qtd' => $l['qtd'], $p . '_sem_taxa' => $l['sem_taxa']));
            }
        }
    }

    if (financeiro_disponivel($conn)) {
        $res = db_query($conn, "
            SELECT to_char(idfl_data, 'YYYY-MM') AS mes, idfl_tipo AS tipo, COALESCE(idfl_destino, '') AS destino, SUM(idfl_valor_centavos) AS total
            FROM id_financeiro_lancamento
            WHERE idfl_data BETWEEN CAST(? AS DATE) AND CAST(? AS DATE)
              " . (financeiro_cancelamento_disponivel($conn) ? "AND idfl_cancelado_em IS NULL" : "") . "
            GROUP BY 1, 2, 3
        ", array($inicio, $fim));
        while ($res && $l = mysqli_fetch_assoc($res)) {
            $chave = $l['tipo'] === 'custo' ? 'custos' : ($l['tipo'] === 'receita' ? 'receitas' : ($l['destino'] === 'missao' ? 'repasses_missao' : 'repasses_igreja'));
            $somar($l['mes'], array($chave => $l['total']));
        }
    }

    return $meses;
}

function financeiro_somar(array $meses) {
    $total = financeiro_linha_vazia();
    foreach ($meses as $linha) {
        foreach ($total as $chave => $valor) {
            $total[$chave] += (int) ($linha[$chave] ?? 0);
        }
    }
    return $total;
}

// Lucro e divisão de uma linha de movimentos (função pura, testada em
// tests/testes_funcoes.php). Valores em centavos.
// - Missões no lucro: o líquido das missões (e as ofertas que a equipe
//   recebeu à mão) entra na receita, e o repasse a projetos de missão conta
//   como despesa.
// - Missões separadas: o lucro dividido é só assinaturas + outras receitas
//   - custos; missões viram um caixa à parte (entrou - repassado).
// - Contribuições (oferta geral, para manter o app) entram sempre no lucro,
//   nos dois modos: não são de missão.
// A igreja só recebe sobre lucro positivo; prejuízo fica com a equipe.
// O repasse à igreja não reduz o lucro: é o pagamento da parte dela.
function financeiro_divisao(array $t, $percentual_igreja, $missoes_no_lucro) {
    $t += financeiro_linha_vazia();
    $percentual_igreja = min(100, max(0, (int) $percentual_igreja));
    $missoes_entrada = (int) $t['missoes_liquido'] + (int) $t['missoes_manual_equipe'];
    $contribuicoes_entrada = (int) $t['contribuicoes_liquido'] + (int) $t['contribuicoes_manual_equipe'];
    $bruto = (int) $t['assinaturas_bruto'] + (int) $t['missoes_bruto'] + (int) $t['missoes_manual_equipe']
        + (int) $t['contribuicoes_bruto'] + (int) $t['contribuicoes_manual_equipe'] + (int) $t['receitas'];
    $taxas = ((int) $t['assinaturas_bruto'] - (int) $t['assinaturas_liquido']) + ((int) $t['missoes_bruto'] - (int) $t['missoes_liquido'])
        + ((int) $t['contribuicoes_bruto'] - (int) $t['contribuicoes_liquido']);

    if ($missoes_no_lucro) {
        $receita = (int) $t['assinaturas_liquido'] + $missoes_entrada + $contribuicoes_entrada + (int) $t['receitas'];
        $despesas = (int) $t['custos'] + (int) $t['repasses_missao'];
        $caixa_missoes = null;
    } else {
        $receita = (int) $t['assinaturas_liquido'] + $contribuicoes_entrada + (int) $t['receitas'];
        $despesas = (int) $t['custos'];
        $caixa_missoes = $missoes_entrada - (int) $t['repasses_missao'];
    }

    $lucro = $receita - $despesas;
    $parte_igreja = $lucro > 0 ? (int) round($lucro * $percentual_igreja / 100) : 0;

    return array(
        'bruto' => $bruto,
        'taxas' => $taxas,
        'receita' => $receita,
        'despesas' => $despesas,
        'lucro' => $lucro,
        'percentual_igreja' => $percentual_igreja,
        'parte_igreja' => $parte_igreja,
        'parte_equipe' => $lucro - $parte_igreja,
        'missoes_entrada' => $missoes_entrada,
        'contribuicoes_entrada' => $contribuicoes_entrada,
        'caixa_missoes' => $caixa_missoes,
    );
}

// Quanto ainda falta repassar à igreja, somando todos os meses desde o
// começo. A parte de cada mês é calculada sobre o lucro DAQUELE mês (mês
// com prejuízo não desconta dos outros), com a porcentagem e o modo atuais.
function financeiro_saldo_igreja($conn) {
    $meses = financeiro_movimentos_por_mes($conn, financeiro_primeiro_dia($conn), site_hoje());
    $percentual = financeiro_percentual_igreja();
    $no_lucro = financeiro_missoes_no_lucro();
    $devido = 0;
    $repassado = 0;
    $caixa_missoes = 0;
    foreach ($meses as $linha) {
        $divisao = financeiro_divisao($linha, $percentual, $no_lucro);
        $devido += $divisao['parte_igreja'];
        $repassado += (int) $linha['repasses_igreja'];
        $caixa_missoes += (int) ($divisao['caixa_missoes'] ?? 0);
    }
    return array('devido' => $devido, 'repassado' => $repassado, 'saldo' => $devido - $repassado, 'caixa_missoes' => $no_lucro ? null : $caixa_missoes);
}

// Data do primeiro movimento registrado (para o "desde o começo").
function financeiro_primeiro_dia($conn) {
    $partes = array();
    if (site_tabela_existe($conn, 'id_pagamento')) {
        $partes[] = "SELECT MIN(idpg_pago_em::date) AS d FROM id_pagamento";
    }
    if (missoes_disponivel($conn)) {
        $partes[] = "SELECT MIN(idmc_pago_em) AS d FROM id_missao_contribuicao";
    }
    if (financeiro_disponivel($conn)) {
        $partes[] = "SELECT MIN(idfl_data) AS d FROM id_financeiro_lancamento";
    }
    $res = $partes ? mysqli_query($conn, "SELECT MIN(d) AS d FROM (" . implode(' UNION ALL ', $partes) . ") x") : false;
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    $data = (string) ($linha['d'] ?? '');
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) ? $data : site_hoje();
}

function financeiro_lancamentos($conn, $inicio, $fim) {
    if (!financeiro_disponivel($conn)) {
        return array();
    }
    $projetos = missoes_disponivel($conn) ? "LEFT JOIN id_missao_projeto p ON p.idmp_id = l.idmp_id" : '';
    $titulo = $projetos !== '' ? 'p.idmp_titulo' : 'NULL';
    $res = db_query($conn, "
        SELECT l.*, $titulo AS projeto_titulo
        FROM id_financeiro_lancamento l
        $projetos
        WHERE l.idfl_data BETWEEN CAST(? AS DATE) AND CAST(? AS DATE)
        ORDER BY l.idfl_data DESC, l.idfl_id DESC
    ", array($inicio, $fim));
    return $res ? $res->todas_as_linhas() : array();
}

// Grava um lançamento manual. Devolve '' ou a mensagem de erro.
function financeiro_lancamento_salvar($conn, array $dados, $admin_id) {
    $tipo = (string) ($dados['tipo'] ?? '');
    $destino = (string) ($dados['destino'] ?? '');
    $categoria = trim((string) ($dados['categoria'] ?? ''));
    $descricao = trim((string) ($dados['descricao'] ?? ''));
    $valor = site_valor_para_centavos($dados['valor'] ?? '');
    $data = (string) ($dados['data'] ?? '');
    $projeto_id = (int) ($dados['projeto_id'] ?? 0);

    if (!in_array($tipo, array('custo', 'receita', 'repasse'), true)) {
        return 'Escolha o tipo do lançamento.';
    }
    if ($tipo === 'repasse' && !in_array($destino, array('igreja', 'missao'), true)) {
        return 'Escolha para quem foi o repasse.';
    }
    if ($categoria === '' || mb_strlen($categoria) > 40 || mb_strlen($descricao) > 200) {
        return 'Informe a categoria (até 40 caracteres) e uma descrição de até 200.';
    }
    if ($valor <= 0) {
        return 'Informe um valor válido.';
    }
    $data_ok = DateTime::createFromFormat('Y-m-d', $data);
    if (!$data_ok || $data_ok->format('Y-m-d') !== $data) {
        return 'Informe uma data válida.';
    }
    if ($tipo !== 'repasse') {
        $destino = null;
    }
    if ($tipo !== 'repasse' || $destino !== 'missao' || $projeto_id <= 0) {
        $projeto_id = null;
    }

    $ok = db_query($conn, "
        INSERT INTO id_financeiro_lancamento (idfl_tipo, idfl_destino, idmp_id, idfl_categoria, idfl_descricao, idfl_valor_centavos, idfl_data, idfl_criado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ", array($tipo, $destino, $projeto_id, $categoria, $descricao !== '' ? $descricao : null, $valor, $data, (int) $admin_id));
    return $ok ? '' : site_erro_banco('Não foi possível salvar o lançamento.');
}

function financeiro_lancamento_excluir($conn, $id) {
    $ok = db_query($conn, "DELETE FROM id_financeiro_lancamento WHERE idfl_id = ?", array((int) $id));
    return $ok && mysqli_affected_rows($conn) > 0;
}

// Seção 89: lançamento cancelado em vez de apagado.
function financeiro_cancelamento_disponivel($conn) {
    return tabela_tem_coluna($conn, 'id_financeiro_lancamento', 'idfl_cancelado_em');
}

// Cancela um lançamento (fica na lista riscado, com quem, quando e o motivo,
// e sai dos totais). Em contabilidade não se apaga: antes era um DELETE, e
// "quem apagou essa entrada?" ficava sem resposta. Sem a seção 89, apaga
// como antes. Devolve '' ou a mensagem de erro.
function financeiro_lancamento_cancelar($conn, $id, $admin_id, $motivo) {
    $motivo = trim(preg_replace('/\s+/', ' ', (string) $motivo));
    if (!financeiro_cancelamento_disponivel($conn)) {
        return financeiro_lancamento_excluir($conn, $id) ? '' : 'Lançamento não encontrado.';
    }
    if (mb_strlen($motivo) < 3) {
        return 'Diga o motivo do cancelamento (ex.: "lançado em dobro").';
    }
    $res = db_query($conn, "
        UPDATE id_financeiro_lancamento
        SET idfl_cancelado_em = NOW(), idfl_cancelado_por = ?, idfl_cancelado_motivo = ?
        WHERE idfl_id = ? AND idfl_cancelado_em IS NULL
        RETURNING idfl_id
    ", array((int) $admin_id ?: null, mb_substr($motivo, 0, 200), (int) $id));
    if (!$res) {
        return site_erro_banco('Não foi possível cancelar o lançamento.');
    }
    return mysqli_num_rows($res) > 0 ? '' : 'Lançamento não encontrado (ou já cancelado).';
}

// Busca no Asaas a taxa (netValue) das cobranças pagas que ainda não têm o
// valor líquido gravado — as de antes da seção 71 e as manuais. Uma
// chamada à API por cobrança, até $limite por vez. Devolve quantas
// atualizou.
function financeiro_sincronizar_taxas($conn, $limite = 30) {
    if (!asaas_configurado()) {
        return 0;
    }
    $pendentes = array();
    $placeholders = implode(', ', array_fill(0, count(financeiro_status_pagos()), '?'));
    if (tabela_tem_coluna($conn, 'id_pagamento', 'idpg_valor_liquido_centavos')) {
        $res = db_query($conn, "SELECT 'pagamento' AS origem, idpg_asaas_payment_id AS payment_id FROM id_pagamento WHERE idpg_valor_liquido_centavos IS NULL AND idpg_status IN ($placeholders) LIMIT " . (int) $limite, financeiro_status_pagos());
        $pendentes = $res ? $res->todas_as_linhas() : array();
    }
    if (missoes_disponivel($conn) && count($pendentes) < $limite) {
        $res = db_query($conn, "SELECT 'missao' AS origem, idmc_asaas_payment_id AS payment_id FROM id_missao_contribuicao WHERE idmc_origem = 'app' AND idmc_asaas_payment_id IS NOT NULL AND idmc_valor_liquido_centavos IS NULL AND idmc_status IN ($placeholders) LIMIT " . (int) ($limite - count($pendentes)), financeiro_status_pagos());
        $pendentes = array_merge($pendentes, $res ? $res->todas_as_linhas() : array());
    }

    $atualizadas = 0;
    foreach ($pendentes as $linha) {
        list($status, $pagamento) = asaas_api('GET', '/payments/' . rawurlencode((string) $linha['payment_id']));
        $liquido = ($status === 200 && is_array($pagamento)) ? asaas_valor_liquido_centavos($pagamento) : null;
        if ($liquido === null) {
            continue;
        }
        $sql = $linha['origem'] === 'missao'
            ? "UPDATE id_missao_contribuicao SET idmc_valor_liquido_centavos = ? WHERE idmc_asaas_payment_id = ?"
            : "UPDATE id_pagamento SET idpg_valor_liquido_centavos = ? WHERE idpg_asaas_payment_id = ?";
        if (db_query($conn, $sql, array($liquido, (string) $linha['payment_id']))) {
            $atualizadas++;
        }
    }
    return $atualizadas;
}

// Todas as entradas e saídas do período, uma por linha, para a exportação
// em CSV (planilha da equipe). Ordem: data.
function financeiro_extrato($conn, $inicio, $fim) {
    $linhas = array();
    $pagos = financeiro_status_pagos();

    if (site_tabela_existe($conn, 'id_pagamento')) {
        $liquido = tabela_tem_coluna($conn, 'id_pagamento', 'idpg_valor_liquido_centavos') ? 'pg.idpg_valor_liquido_centavos' : 'NULL';
        $res = db_query($conn, "
            SELECT pg.idpg_pago_em::date AS data, pg.idpg_valor_centavos AS bruto, $liquido AS liquido, pg.idpg_forma AS forma,
                   f.idf_nome AS nome, p.idp_nome AS plano
            FROM id_pagamento pg
            JOIN id_assinatura a ON a.idas_id = pg.idas_id
            LEFT JOIN id_fiel f ON f.idf_id = a.idf_id
            LEFT JOIN id_plano p ON p.idp_id = a.idp_id
            WHERE pg.idpg_status IN (?, ?, ?) AND pg.idpg_pago_em IS NOT NULL
              AND pg.idpg_pago_em::date BETWEEN CAST(? AS DATE) AND CAST(? AS DATE)
        ", array_merge($pagos, array($inicio, $fim)));
        while ($res && $l = mysqli_fetch_assoc($res)) {
            $linhas[] = array('data' => $l['data'], 'tipo' => 'Entrada', 'origem' => 'Assinatura', 'descricao' => trim(($l['plano'] ?? '') . ' - ' . ($l['nome'] ?? ''), ' -'), 'forma' => (string) $l['forma'], 'bruto' => (int) $l['bruto'], 'liquido' => $l['liquido'] !== null ? (int) $l['liquido'] : (int) $l['bruto']);
        }
    }

    if (missoes_disponivel($conn)) {
        $res = db_query($conn, "
            SELECT c.idmc_pago_em AS data, c.idmc_valor_centavos AS bruto, c.idmc_valor_liquido_centavos AS liquido, c.idmc_forma AS forma,
                   c.idmc_origem AS origem, c.idmc_recebido_por AS recebido_por, c.idmc_nome_exibicao AS nome_exibicao,
                   c.idmp_id AS projeto_id, f.idf_nome AS nome, p.idmp_titulo AS projeto
            FROM id_missao_contribuicao c
            JOIN id_missao_projeto p ON p.idmp_id = c.idmp_id
            LEFT JOIN id_fiel f ON f.idf_id = c.idf_id
            WHERE c.idmc_status IN (?, ?, ?) AND c.idmc_pago_em BETWEEN CAST(? AS DATE) AND CAST(? AS DATE)
        ", array_merge($pagos, array($inicio, $fim)));
        while ($res && $l = mysqli_fetch_assoc($res)) {
            $quem = $l['nome'] ?? $l['nome_exibicao'] ?? '';
            $nome_origem = (int) $l['projeto_id'] === missoes_oferta_geral_id() ? 'Contribuição' : 'Missão';
            $origem = $l['origem'] === 'manual' ? $nome_origem . ' (oferta fora do app, recebida pela ' . $l['recebido_por'] . ')' : $nome_origem;
            $linhas[] = array('data' => $l['data'], 'tipo' => $l['recebido_por'] === 'equipe' ? 'Entrada' : 'Informativo', 'origem' => $origem, 'descricao' => trim($l['projeto'] . ' - ' . $quem, ' -'), 'forma' => (string) $l['forma'], 'bruto' => (int) $l['bruto'], 'liquido' => $l['liquido'] !== null ? (int) $l['liquido'] : (int) $l['bruto']);
        }
    }

    foreach (financeiro_lancamentos($conn, $inicio, $fim) as $l) {
        $tipos = array('custo' => 'Saída', 'receita' => 'Entrada', 'repasse' => 'Saída');
        $origem = $l['idfl_tipo'] === 'custo' ? 'Custo' : ($l['idfl_tipo'] === 'receita' ? 'Outra receita' : ($l['idfl_destino'] === 'missao' ? 'Repasse a missão' : 'Repasse à igreja'));
        $descricao = trim($l['idfl_categoria'] . ' - ' . ($l['idfl_descricao'] ?? '') . (!empty($l['projeto_titulo']) ? ' (' . $l['projeto_titulo'] . ')' : ''), ' -');
        // Cancelado continua na planilha (rastro), com valor zero para não
        // somar, e o valor original e o motivo na descrição.
        if (!empty($l['idfl_cancelado_em'])) {
            $linhas[] = array('data' => $l['idfl_data'], 'tipo' => 'Cancelado', 'origem' => $origem, 'descricao' => 'CANCELADO (' . assinatura_formatar_valor($l['idfl_valor_centavos']) . '): ' . (string) ($l['idfl_cancelado_motivo'] ?? '') . ' — ' . $descricao, 'forma' => '', 'bruto' => 0, 'liquido' => 0);
            continue;
        }
        $linhas[] = array('data' => $l['idfl_data'], 'tipo' => $tipos[$l['idfl_tipo']], 'origem' => $origem, 'descricao' => $descricao, 'forma' => '', 'bruto' => (int) $l['idfl_valor_centavos'], 'liquido' => (int) $l['idfl_valor_centavos']);
    }

    usort($linhas, function ($a, $b) {
        return strcmp((string) $a['data'], (string) $b['data']);
    });
    return $linhas;
}

// ============================================================================
// CONTRIBUIÇÃO EM MISSÕES (T32, seção 72 do bancosupabase.md)
// ============================================================================
// Admin → Missões cadastra os projetos; o fiel contribui em
// php/fiel/missao.php com o valor que quiser, por Pix ou cartão, numa
// cobrança avulsa do Asaas (externalReference "missao_<idmc_id>"). O
// webhook confirma (missao_aplicar_pagamento) e a contribuição entra na
// barra do projeto e no quadro de apoiadores.

define('MISSAO_VALOR_MINIMO_CENTAVOS', 500); // mínimo do Asaas
define('MISSAO_VALOR_MAXIMO_CENTAVOS', 5000000); // R$ 50.000 por vez
define('MISSAO_PENDENTES_MAX', 5);

function missoes_disponivel($conn) {
    return site_tabela_existe($conn, 'id_missao_contribuicao');
}

function missao_id_da_referencia($referencia) {
    return preg_match('/^missao_(\d+)$/', (string) $referencia, $m) ? (int) $m[1] : 0;
}

// rascunho | aberto | encerrado (ativo com data de fim no passado também
// é encerrado).
function missao_situacao(array $projeto) {
    if ($projeto['idmp_status'] === 'rascunho') {
        return 'rascunho';
    }
    if ($projeto['idmp_status'] === 'encerrado' || (!empty($projeto['idmp_data_fim']) && $projeto['idmp_data_fim'] < site_hoje())) {
        return 'encerrado';
    }
    return 'aberto';
}

// Capa: "r2:..." (enviada pelo admin), link http(s) ou uma arte que vem no
// repositório ("assets/img/destaques/...", como a da oferta geral da seção
// 77; caminho relativo a php/fiel/ e php/admin/, que ficam na mesma altura).
function missao_capa_url($valor) {
    $valor = trim((string) $valor);
    if (preg_match('#^assets/img/destaques/[a-z0-9_-]+\.(jpe?g|png|webp)$#i', $valor)) {
        return '../../' . $valor;
    }
    if (strpos($valor, 'r2:') === 0) {
        $url = r2_criar_url_assinada('GET', substr($valor, 3), 14400);
        return $url !== '' ? $url : null;
    }
    return preg_match('#^https?://#i', $valor) ? $valor : null;
}

// "Maria Souza Lima" → "Maria L." (o quadro de apoiadores não expõe o nome
// completo).
function missao_nome_publico($nome) {
    $partes = preg_split('/\s+/', trim((string) $nome), -1, PREG_SPLIT_NO_EMPTY);
    if (!$partes) {
        return 'Apoiador';
    }
    $primeiro = $partes[0];
    return count($partes) > 1 ? $primeiro . ' ' . mb_strtoupper(mb_substr(end($partes), 0, 1)) . '.' : $primeiro;
}

// Projetos com os totais (arrecadado = pago, de qualquer origem). Sem
// $incluir_rascunho, só os que o fiel pode ver. Mais recentes primeiro,
// abertos antes dos encerrados.
function missoes_projetos($conn, $incluir_rascunho = false, $projeto_id = 0) {
    if (!missoes_disponivel($conn)) {
        return array();
    }
    $pagos = financeiro_status_pagos();
    $params = $pagos;
    $where = array();
    if (!$incluir_rascunho) {
        $where[] = "p.idmp_status <> 'rascunho'";
    }
    if ($projeto_id > 0) {
        $where[] = 'p.idmp_id = ?';
        $params[] = (int) $projeto_id;
    }
    $res = db_query($conn, "
        SELECT p.*,
               COALESCE(t.arrecadado, 0) AS arrecadado,
               COALESCE(t.contribuicoes, 0) AS contribuicoes,
               COALESCE(t.apoiadores, 0) AS apoiadores
        FROM id_missao_projeto p
        LEFT JOIN (
            SELECT idmp_id,
                   SUM(idmc_valor_centavos) AS arrecadado,
                   COUNT(*) AS contribuicoes,
                   COUNT(DISTINCT idf_id) + COUNT(*) FILTER (WHERE idf_id IS NULL) AS apoiadores
            FROM id_missao_contribuicao
            WHERE idmc_status IN (?, ?, ?)
            GROUP BY idmp_id
        ) t ON t.idmp_id = p.idmp_id
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY CASE WHEN p.idmp_status = 'encerrado' OR (p.idmp_data_fim IS NOT NULL AND p.idmp_data_fim < (NOW() AT TIME ZONE 'America/Sao_Paulo')::date) THEN 1 ELSE 0 END,
                 p.idmp_criado_em DESC
    ", $params);
    $projetos = $res ? $res->todas_as_linhas() : array();
    foreach ($projetos as &$p) {
        $p['situacao'] = missao_situacao($p);
        $p['arrecadado'] = (int) $p['arrecadado'];
        $p['percentual'] = !empty($p['idmp_meta_centavos']) ? min(100, (int) floor($p['arrecadado'] * 100 / (int) $p['idmp_meta_centavos'])) : null;
    }
    unset($p);
    return $projetos;
}

function missao_projeto($conn, $projeto_id, $incluir_rascunho = false) {
    $lista = missoes_projetos($conn, $incluir_rascunho, (int) $projeto_id);
    return $lista ? $lista[0] : null;
}

// O que a página do projeto mostra ao fiel (seção 78; sem ela, tudo, como
// antes): total arrecadado (com barra e % da meta), número de apoiadores,
// quadro de apoiadores e o valor de cada contribuição no quadro. Missões
// costumam mostrar o total (a meta é chegar a X); a oferta geral, que é da
// equipe interna, vem sem. Função pura.
function missao_exibicao(array $p) {
    $ler = function ($coluna, $padrao) use ($p) {
        return isset($p[$coluna]) ? (int) $p[$coluna] === 1 : $padrao;
    };
    $quadro = $ler('idmp_mostrar_quadro', true);
    return array(
        'total' => $ler('idmp_mostrar_total', true),
        'apoiadores' => $ler('idmp_mostrar_apoiadores', true),
        'quadro' => $quadro,
        'valores' => $quadro && $ler('idmp_mostrar_valores', false),
    );
}

function missoes_exibicao_disponivel($conn) {
    return tabela_tem_coluna($conn, 'id_missao_projeto', 'idmp_mostrar_total');
}

// Cria ou atualiza um projeto (Admin → Missões e Admin → Contribuições).
// $d: idmp_id (0 = novo), titulo, local, descricao, meta (texto em R$),
// data_fim, status (rascunho|ativo|encerrado) e os interruptores
// mostrar_total, mostrar_apoiadores, mostrar_quadro, mostrar_valores. A
// capa vem do upload "capa". Devolve array('erro' => '', 'id' => int).
function missao_projeto_salvar($conn, array $d, $admin_id) {
    $projeto_id = (int) ($d['idmp_id'] ?? 0);
    $titulo = trim((string) ($d['titulo'] ?? ''));
    $local = trim((string) ($d['local'] ?? ''));
    $descricao = trim(str_replace("\r\n", "\n", (string) ($d['descricao'] ?? '')));
    $meta_texto = trim((string) ($d['meta'] ?? ''));
    $meta = $meta_texto === '' ? null : site_valor_para_centavos($meta_texto);
    $data_fim = trim((string) ($d['data_fim'] ?? ''));
    $status = (string) ($d['status'] ?? '');
    $data_fim_ok = $data_fim === '' || (($dt = DateTime::createFromFormat('Y-m-d', $data_fim)) && $dt->format('Y-m-d') === $data_fim);
    $anterior = $projeto_id > 0 ? missao_projeto($conn, $projeto_id, true) : null;
    $falha = function ($erro) {
        return array('erro' => $erro, 'id' => 0);
    };

    if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 120) {
        return $falha('Informe o nome (de 3 a 120 caracteres).');
    }
    if (mb_strlen($local) > 100 || mb_strlen($descricao) > 5000) {
        return $falha('O local pode ter até 100 caracteres e a descrição até 5.000.');
    }
    if ($meta !== null && $meta < 100) {
        return $falha('Informe uma meta válida (ou deixe em branco para não ter meta).');
    }
    if (!$data_fim_ok) {
        return $falha('Data de encerramento inválida.');
    }
    if (!in_array($status, array('rascunho', 'ativo', 'encerrado'), true)) {
        return $falha('Situação inválida.');
    }
    if ($projeto_id > 0 && !$anterior) {
        return $falha('Projeto não encontrado.');
    }

    $erro = '';
    $capa_nova = site_salvar_imagem_upload('capa', 'missoes', $erro);
    if ($capa_nova === false) {
        return $falha($erro);
    }

    $colunas = array(
        'idmp_titulo' => $titulo,
        'idmp_local' => $local !== '' ? $local : null,
        'idmp_descricao' => $descricao,
        'idmp_meta_centavos' => $meta,
        'idmp_data_fim' => $data_fim !== '' ? $data_fim : null,
        'idmp_status' => $status,
        'idmp_mostrar_valores' => !empty($d['mostrar_valores']) ? 1 : 0,
    );
    if (missoes_exibicao_disponivel($conn)) {
        $colunas['idmp_mostrar_total'] = !empty($d['mostrar_total']) ? 1 : 0;
        $colunas['idmp_mostrar_apoiadores'] = !empty($d['mostrar_apoiadores']) ? 1 : 0;
        $colunas['idmp_mostrar_quadro'] = !empty($d['mostrar_quadro']) ? 1 : 0;
    }
    if ($capa_nova) {
        $colunas['idmp_capa_url'] = $capa_nova;
    }

    if ($projeto_id > 0) {
        $sets = implode(', ', array_map(function ($c) { return $c . ' = ?'; }, array_keys($colunas)));
        $ok = db_query($conn, "UPDATE id_missao_projeto SET $sets, idmp_atualizado_em = NOW() WHERE idmp_id = ?", array_merge(array_values($colunas), array($projeto_id)));
        $id_salvo = $ok ? $projeto_id : 0;
    } else {
        $colunas['idmp_criado_por'] = (int) $admin_id > 0 ? (int) $admin_id : null;
        $ok = db_query($conn, "
            INSERT INTO id_missao_projeto (" . implode(', ', array_keys($colunas)) . ")
            VALUES (" . implode(', ', array_fill(0, count($colunas), '?')) . ")
            RETURNING idmp_id
        ", array_values($colunas));
        $id_salvo = $ok ? (int) (mysqli_fetch_assoc($ok)['idmp_id'] ?? 0) : 0;
    }

    if ($id_salvo <= 0) {
        if ($capa_nova) {
            site_excluir_arquivo_r2_orfao($capa_nova);
        }
        return $falha(site_erro_banco('Não foi possível salvar.'));
    }
    // Capa trocada: a antiga fica sem uso no R2.
    if ($capa_nova && !empty($anterior['idmp_capa_url'])) {
        site_excluir_arquivo_r2_orfao($anterior['idmp_capa_url']);
    }
    return array('erro' => '', 'id' => $id_salvo);
}

// Cria a oferta geral com o texto padrão (o mesmo da seção 77) e a marca.
// Devolve o id, ou 0.
function missao_oferta_geral_criar($conn, $admin_id) {
    $descricao = "Com a sua generosidade, conseguimos manter e melhorar o nosso site, levando a mensagem de Jesus para mais pessoas.\n\n"
        . "A sua oferta ajuda a pagar a hospedagem do app, o armazenamento dos vídeos das aulas e o desenvolvimento de novas funções. Mais pessoas alcançadas, novos conteúdos e recursos, um espaço mais acolhedor: juntos, fazemos mais pelo Reino.\n\n"
        . "Toda contribuição, sendo de coração, é bem-vinda.";
    $colunas = array(
        'idmp_titulo' => 'Ajude o site da igreja a crescer',
        'idmp_descricao' => $descricao,
        'idmp_capa_url' => 'assets/img/destaques/ajude-o-site-crescer.jpg',
        'idmp_status' => 'ativo',
        'idmp_mostrar_valores' => 0,
        'idmp_criado_por' => (int) $admin_id > 0 ? (int) $admin_id : null,
    );
    if (missoes_exibicao_disponivel($conn)) {
        $colunas['idmp_mostrar_total'] = 0;
    }
    $res = db_query($conn, "
        INSERT INTO id_missao_projeto (" . implode(', ', array_keys($colunas)) . ")
        VALUES (" . implode(', ', array_fill(0, count($colunas), '?')) . ")
        RETURNING idmp_id
    ", array_values($colunas));
    $id = $res ? (int) (mysqli_fetch_assoc($res)['idmp_id'] ?? 0) : 0;
    if ($id > 0 && !site_config_salvar($conn, 'missoes_oferta_geral', (string) $id)) {
        return 0;
    }
    return $id;
}

// Filtros da lista de contribuições do admin, a partir do GET. Função pura:
// o que não for válido some.
function missao_contribuicoes_filtros(array $get) {
    $data = function ($valor) {
        $valor = trim((string) $valor);
        $dt = DateTime::createFromFormat('Y-m-d', $valor);
        return $dt && $dt->format('Y-m-d') === $valor ? $valor : '';
    };
    return array(
        'projeto' => max(0, (int) ($get['projeto'] ?? 0)),
        'situacao' => in_array($get['situacao'] ?? '', array('pagas', 'pendentes', 'outras'), true) ? $get['situacao'] : '',
        'origem' => in_array($get['origem'] ?? '', array('app', 'qrcode', 'manual'), true) ? $get['origem'] : '',
        'forma' => in_array($get['forma'] ?? '', array('PIX', 'CREDIT_CARD'), true) ? $get['forma'] : '',
        'de' => $data($get['de'] ?? ''),
        'ate' => $data($get['ate'] ?? ''),
        'busca' => mb_substr(trim((string) ($get['busca'] ?? '')), 0, 100),
    );
}

// Contribuições para o admin (nome real, mesmo de quem pediu anonimato), com
// os filtros de missao_contribuicoes_filtros(). $so_projeto > 0 limita a um
// projeto; $sem_projeto > 0 tira um (a oferta geral sai da lista de
// Missões). Devolve array('linhas' (até 300, mais recentes primeiro),
// 'pago_bruto', 'pago_liquido', 'pago_qtd', 'pendentes_qtd') — os totais
// valem para o filtro inteiro, não só para as 300.
function missao_contribuicoes_admin($conn, array $f, $so_projeto = 0, $sem_projeto = 0) {
    $vazio = array('linhas' => array(), 'pago_bruto' => 0, 'pago_liquido' => 0, 'pago_qtd' => 0, 'pendentes_qtd' => 0);
    if (!missoes_disponivel($conn)) {
        return $vazio;
    }
    $pagos = financeiro_status_pagos();
    $where = array();
    $params = array();
    if ((int) $so_projeto > 0) {
        $where[] = 'c.idmp_id = ?';
        $params[] = (int) $so_projeto;
    } elseif ($f['projeto'] > 0) {
        $where[] = 'c.idmp_id = ?';
        $params[] = $f['projeto'];
    }
    if ((int) $sem_projeto > 0) {
        $where[] = 'c.idmp_id <> ?';
        $params[] = (int) $sem_projeto;
    }
    if ($f['situacao'] === 'pagas') {
        $where[] = 'c.idmc_status IN (?, ?, ?)';
        $params = array_merge($params, $pagos);
    } elseif ($f['situacao'] === 'pendentes') {
        $where[] = "c.idmc_status IN ('PENDING', 'AWAITING_RISK_ANALYSIS')";
    } elseif ($f['situacao'] === 'outras') {
        $where[] = "c.idmc_status NOT IN ('RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH', 'PENDING', 'AWAITING_RISK_ANALYSIS')";
    }
    if ($f['origem'] !== '') {
        $where[] = 'c.idmc_origem = ?';
        $params[] = $f['origem'];
    }
    if ($f['forma'] !== '') {
        $where[] = 'c.idmc_forma = ?';
        $params[] = $f['forma'];
    }
    // Data que vale: a do pagamento; sem pagamento, a do pedido.
    if ($f['de'] !== '') {
        $where[] = 'COALESCE(c.idmc_pago_em, c.idmc_criado_em::date) >= CAST(? AS DATE)';
        $params[] = $f['de'];
    }
    if ($f['ate'] !== '') {
        $where[] = 'COALESCE(c.idmc_pago_em, c.idmc_criado_em::date) <= CAST(? AS DATE)';
        $params[] = $f['ate'];
    }
    if ($f['busca'] !== '') {
        $like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $f['busca']) . '%';
        $where[] = '(f.idf_nome ILIKE ? OR f.idf_email ILIKE ? OR c.idmc_nome_exibicao ILIKE ? OR c.idmc_mensagem ILIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    $sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $de = "
        FROM id_missao_contribuicao c
        JOIN id_missao_projeto p ON p.idmp_id = c.idmp_id
        LEFT JOIN id_fiel f ON f.idf_id = c.idf_id
        $sql_where
    ";

    $res = db_query($conn, "SELECT c.*, p.idmp_titulo, f.idf_nome, f.idf_email $de ORDER BY c.idmc_criado_em DESC LIMIT 300", $params);
    $saida = $vazio;
    $saida['linhas'] = $res ? $res->todas_as_linhas() : array();
    $res = db_query($conn, "
        SELECT COALESCE(SUM(c.idmc_valor_centavos) FILTER (WHERE c.idmc_status IN (?, ?, ?)), 0) AS bruto,
               COALESCE(SUM(COALESCE(c.idmc_valor_liquido_centavos, c.idmc_valor_centavos)) FILTER (WHERE c.idmc_status IN (?, ?, ?)), 0) AS liquido,
               COUNT(*) FILTER (WHERE c.idmc_status IN (?, ?, ?)) AS qtd,
               COUNT(*) FILTER (WHERE c.idmc_status IN ('PENDING', 'AWAITING_RISK_ANALYSIS')) AS pendentes
        $de
    ", array_merge($pagos, $pagos, $pagos, $params));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if ($linha) {
        $saida['pago_bruto'] = (int) $linha['bruto'];
        $saida['pago_liquido'] = (int) $linha['liquido'];
        $saida['pago_qtd'] = (int) $linha['qtd'];
        $saida['pendentes_qtd'] = (int) $linha['pendentes'];
    }
    return $saida;
}

// Quadro de apoiadores: as contribuições pagas mais recentes do projeto.
// Nome curto (missao_nome_publico), "Apoiador anônimo" para quem pediu, e o
// valor só se o projeto mostrar valores.
function missao_apoiadores($conn, $projeto_id, $limite = 40) {
    if (!missoes_disponivel($conn)) {
        return array();
    }
    $res = db_query($conn, "
        SELECT c.idmc_id, c.idf_id, c.idmc_anonimo, c.idmc_mensagem, c.idmc_valor_centavos, c.idmc_origem,
               c.idmc_nome_exibicao, c.idmc_pago_em, f.idf_nome
        FROM id_missao_contribuicao c
        LEFT JOIN id_fiel f ON f.idf_id = c.idf_id
        WHERE c.idmp_id = ? AND c.idmc_status IN (?, ?, ?)
          AND (c.idf_id IS NOT NULL OR c.idmc_origem IN ('manual', 'qrcode'))
        ORDER BY c.idmc_pago_em DESC NULLS LAST, c.idmc_id DESC
        LIMIT " . (int) $limite, array_merge(array((int) $projeto_id), financeiro_status_pagos()));
    return $res ? $res->todas_as_linhas() : array();
}

function missao_contribuicoes_do_fiel($conn, $fiel_id, $limite = 50) {
    if (!missoes_disponivel($conn)) {
        return array();
    }
    $res = db_query($conn, "
        SELECT c.*, p.idmp_titulo
        FROM id_missao_contribuicao c
        JOIN id_missao_projeto p ON p.idmp_id = c.idmp_id
        WHERE c.idf_id = ? AND c.idmc_origem = 'app'
        ORDER BY c.idmc_criado_em DESC
        LIMIT " . (int) $limite, array((int) $fiel_id));
    return $res ? $res->todas_as_linhas() : array();
}

// Situação de uma contribuição para mostrar ao fiel/admin:
// array(rótulo, classe do selo).
// Oferta lançada à mão: cancelada (fica na lista como "Cancelada", com quem,
// quando e o motivo, e sai dos totais, que só somam os status pagos) em vez
// de apagada. Sem a seção 89, apaga como antes. $do_projeto: só ofertas
// desse projeto; $fora_do_projeto: de qualquer projeto menos esse (Missões
// não mexe na oferta geral, que é das Contribuições). Devolve '' ou o erro.
function missao_oferta_manual_cancelar($conn, $idmc_id, $admin_id, $motivo, $do_projeto = null, $fora_do_projeto = null) {
    $condicao = $do_projeto !== null ? 'idmp_id = ?' : 'idmp_id <> ?';
    $projeto = (int) ($do_projeto !== null ? $do_projeto : $fora_do_projeto);
    if (!tabela_tem_coluna($conn, 'id_missao_contribuicao', 'idmc_cancelado_em')) {
        $ok = db_query($conn, "DELETE FROM id_missao_contribuicao WHERE idmc_id = ? AND idmc_origem = 'manual' AND $condicao", array((int) $idmc_id, $projeto));
        return $ok && mysqli_affected_rows($conn) > 0 ? '' : 'Oferta não encontrada.';
    }
    $motivo = trim(preg_replace('/\s+/', ' ', (string) $motivo));
    if (mb_strlen($motivo) < 3) {
        return 'Diga o motivo do cancelamento (ex.: "valor errado").';
    }
    $res = db_query($conn, "
        UPDATE id_missao_contribuicao
        SET idmc_status = 'CANCELADA', idmc_cancelado_em = NOW(), idmc_cancelado_por = ?, idmc_cancelado_motivo = ?
        WHERE idmc_id = ? AND idmc_origem = 'manual' AND idmc_cancelado_em IS NULL AND $condicao
        RETURNING idmc_id
    ", array((int) $admin_id ?: null, mb_substr($motivo, 0, 200), (int) $idmc_id, $projeto));
    if (!$res) {
        return site_erro_banco('Não foi possível cancelar a oferta.');
    }
    return mysqli_num_rows($res) > 0 ? '' : 'Oferta não encontrada (ou já cancelada).';
}

function missao_status_info($status) {
    $status = strtoupper((string) $status);
    if (in_array($status, financeiro_status_pagos(), true)) {
        return array('Confirmada', 'is-aprovado');
    }
    if (in_array($status, array('PENDING', 'AWAITING_RISK_ANALYSIS'), true)) {
        return array('Aguardando pagamento', 'is-pendente');
    }
    if ($status === 'OVERDUE') {
        return array('Vencida', 'is-recusado');
    }
    if (strpos($status, 'REFUND') !== false || strpos($status, 'CHARGEBACK') !== false) {
        return array('Estornada', 'is-recusado');
    }
    return array('Cancelada', 'is-recusado');
}

// Customer do Asaas que o fiel já tem (de uma contribuição anterior ou da
// assinatura) — com ele não é preciso pedir o CPF de novo.
function missao_customer_do_fiel($conn, $fiel_id) {
    $res = db_query($conn, "
        SELECT idmc_asaas_customer_id AS c FROM id_missao_contribuicao
        WHERE idf_id = ? AND idmc_asaas_customer_id IS NOT NULL
        ORDER BY idmc_id DESC LIMIT 1
    ", array((int) $fiel_id));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if ($linha && $linha['c'] !== '') {
        return (string) $linha['c'];
    }
    if (site_tabela_existe($conn, 'id_assinatura')) {
        $res = db_query($conn, "SELECT idas_asaas_customer_id AS c FROM id_assinatura WHERE idf_id = ? AND idas_asaas_customer_id IS NOT NULL LIMIT 1", array((int) $fiel_id));
        $linha = $res ? mysqli_fetch_assoc($res) : null;
        if ($linha && $linha['c'] !== '') {
            return (string) $linha['c'];
        }
    }
    return '';
}

// Cria a contribuição e a cobrança no Asaas. Devolve
// array('ok', 'url' da página de pagamento, 'erro').
function missao_contribuir($conn, $fiel_id, $projeto_id, $valor_centavos, $forma, $documento, $anonimo, $mensagem) {
    $fiel_id = (int) $fiel_id;
    $valor_centavos = (int) $valor_centavos;
    $documento = preg_replace('/\D/', '', (string) $documento);
    $mensagem = trim(preg_replace('/\s+/', ' ', (string) $mensagem));
    $falha = function ($erro) {
        return array('ok' => false, 'url' => '', 'erro' => $erro);
    };

    if (!$conn || $fiel_id <= 0 || !missoes_disponivel($conn) || !asaas_configurado()) {
        return $falha('As contribuições ainda não estão disponíveis.');
    }
    $projeto = missao_projeto($conn, $projeto_id);
    if (!$projeto || $projeto['situacao'] !== 'aberto') {
        return $falha('Este projeto não está recebendo contribuições.');
    }
    if ($valor_centavos < MISSAO_VALOR_MINIMO_CENTAVOS) {
        return $falha('O valor mínimo é ' . assinatura_formatar_valor(MISSAO_VALOR_MINIMO_CENTAVOS) . '.');
    }
    if ($valor_centavos > MISSAO_VALOR_MAXIMO_CENTAVOS) {
        return $falha('Para contribuir com mais de ' . assinatura_formatar_valor(MISSAO_VALOR_MAXIMO_CENTAVOS) . ', fale com a equipe pelo Contato.');
    }
    if (!in_array($forma, array('PIX', 'CREDIT_CARD'), true)) {
        return $falha('Escolha Pix ou cartão.');
    }
    if (mb_strlen($mensagem) > 280) {
        return $falha('A mensagem pode ter até 280 caracteres.');
    }
    // Uma cobrança nova a cada 5 s por fiel: vários POSTs ao mesmo tempo
    // passavam juntos pela contagem abaixo e criavam N cobranças (e N
    // clientes repetidos no Asaas).
    if (!site_intervalo_livre('missao_contribuir_' . $fiel_id, 5)) {
        return $falha('Aguarde alguns segundos antes de criar outra contribuição.');
    }

    $res = db_query($conn, "
        SELECT COUNT(*) AS total FROM id_missao_contribuicao
        WHERE idf_id = ? AND idmc_status = 'PENDING' AND idmc_criado_em > NOW() - INTERVAL '1 day'
    ", array($fiel_id));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    if ((int) ($linha['total'] ?? 0) >= MISSAO_PENDENTES_MAX) {
        return $falha('Você tem contribuições aguardando pagamento. Pague ou espere vencerem em "Minhas contribuições" antes de criar outra.');
    }

    $customer_id = missao_customer_do_fiel($conn, $fiel_id);
    if ($customer_id === '' && !assinatura_cpf_valido($documento)) {
        return $falha('Informe um CPF válido (o Asaas exige o CPF de quem paga).');
    }

    $res = db_query($conn, "
        INSERT INTO id_missao_contribuicao (idmp_id, idf_id, idmc_anonimo, idmc_mensagem, idmc_valor_centavos, idmc_forma, idmc_status)
        VALUES (?, ?, ?, ?, ?, ?, 'PENDING')
        RETURNING idmc_id
    ", array((int) $projeto['idmp_id'], $fiel_id, $anonimo ? 1 : 0, $mensagem !== '' ? $mensagem : null, $valor_centavos, $forma));
    $idmc_id = (int) (($res ? mysqli_fetch_assoc($res) : null)['idmc_id'] ?? 0);
    if ($idmc_id <= 0) {
        error_log('missao_contribuir: INSERT falhou (' . mysqli_error($conn) . ')');
        return $falha('Não foi possível registrar a contribuição agora. Tente de novo em instantes.');
    }

    try {
        if ($customer_id === '') {
            $res_fiel = db_query($conn, "SELECT idf_nome, idf_email, idf_telefone FROM id_fiel WHERE idf_id = ? LIMIT 1", array($fiel_id));
            $fiel = $res_fiel ? mysqli_fetch_assoc($res_fiel) : null;
            if (!$fiel) {
                throw new RuntimeException('fiel ' . $fiel_id . ' não encontrado');
            }
            list($status, $resposta) = asaas_enviar_customer('POST', '/customers', array(
                'name' => $fiel['idf_nome'],
                'cpfCnpj' => $documento,
                'email' => $fiel['idf_email'],
                'mobilePhone' => preg_replace('/\D/', '', (string) ($fiel['idf_telefone'] ?? '')),
                'externalReference' => 'fiel_' . $fiel_id,
            ));
            if ($status < 200 || $status >= 300 || empty($resposta['id'])) {
                throw new RuntimeException('POST /customers HTTP ' . $status . ': ' . json_encode($resposta['errors'] ?? $resposta));
            }
            $customer_id = (string) $resposta['id'];
        }

        // Pix vence em 3 dias (dá tempo de pagar depois); no cartão o
        // vencimento não importa, a cobrança é na hora.
        list($status, $cobranca) = asaas_api('POST', '/payments', array(
            'customer' => $customer_id,
            'billingType' => $forma,
            'value' => $valor_centavos / 100,
            'dueDate' => date('Y-m-d', strtotime(site_hoje() . ' +3 days')),
            'description' => 'Contribuição para a missão: ' . mb_substr((string) $projeto['idmp_titulo'], 0, 100),
            'externalReference' => 'missao_' . $idmc_id,
        ));
        if ($status < 200 || $status >= 300 || empty($cobranca['id']) || empty($cobranca['invoiceUrl'])) {
            throw new RuntimeException('POST /payments HTTP ' . $status . ': ' . json_encode($cobranca['errors'] ?? $cobranca));
        }
        $payment_id = (string) $cobranca['id'];

        $ok = db_query($conn, "
            UPDATE id_missao_contribuicao SET
                idmc_asaas_customer_id = ?, idmc_asaas_payment_id = ?, idmc_invoice_url = ?, idmc_atualizado_em = NOW()
            WHERE idmc_id = ?
        ", array($customer_id, $payment_id, (string) $cobranca['invoiceUrl'], $idmc_id));
        if (!$ok) {
            throw new RuntimeException('UPDATE: ' . mysqli_error($conn));
        }
    } catch (\Throwable $e) {
        // Cobrança criada sem registro aqui nunca seria reconhecida: apaga
        // lá (se der) e tira a linha pendente.
        if (isset($payment_id)) {
            list($status_delete) = asaas_api('DELETE', '/payments/' . rawurlencode($payment_id));
            error_log('missao_contribuir: cobrança ' . $payment_id . ($status_delete >= 200 && $status_delete < 300 ? ' apagada no Asaas' : ' NÃO apagada no Asaas — cancelar no painel'));
        }
        db_query($conn, "DELETE FROM id_missao_contribuicao WHERE idmc_id = ? AND idmc_status = 'PENDING'", array($idmc_id));
        error_log('missao_contribuir fiel ' . $fiel_id . ': ' . $e->getMessage());
        return $falha('Não foi possível gerar o pagamento agora. Tente de novo em alguns minutos.');
    }

    return array('ok' => true, 'url' => (string) $cobranca['invoiceUrl'], 'erro' => '');
}

// Aplica uma cobrança de missão vinda da API do Asaas (nunca do corpo do
// webhook direto). Lança exceção em erro de banco (o webhook devolve 500 e
// o Asaas reenvia). Na primeira vez que fica paga, agradece ao fiel.
function missao_aplicar_pagamento($conn, array $pagamento) {
    $idmc_id = missao_id_da_referencia($pagamento['externalReference'] ?? '');
    $payment_id = (string) ($pagamento['id'] ?? '');
    if ($idmc_id <= 0 || $payment_id === '') {
        return false;
    }

    $res = asaas_sql($conn, "
        SELECT c.idmc_status, c.idf_id, c.idmp_id, p.idmp_titulo
        FROM id_missao_contribuicao c
        JOIN id_missao_projeto p ON p.idmp_id = c.idmp_id
        WHERE c.idmc_id = ?
    ", array($idmc_id));
    $atual = mysqli_fetch_assoc($res);
    if (!$atual) {
        error_log('missao_aplicar_pagamento: contribuição ' . $idmc_id . ' não existe (cobrança ' . $payment_id . ')');
        return false;
    }

    $status = !empty($pagamento['deleted']) ? 'DELETED' : strtoupper((string) ($pagamento['status'] ?? 'PENDING'));
    $pago_em = (string) ($pagamento['paymentDate'] ?? ($pagamento['confirmedDate'] ?? ''));
    $pago_em = preg_match('/^\d{4}-\d{2}-\d{2}$/', $pago_em) ? $pago_em : null;
    $paga = in_array($status, financeiro_status_pagos(), true);
    if ($paga && $pago_em === null) {
        $pago_em = site_hoje();
    }
    $valor = (int) round(((float) ($pagamento['value'] ?? 0)) * 100);

    // Quem agradece é só quem de fato tirou a contribuição de "não paga": o
    // webhook e a sincronização da página podem processar a mesma cobrança
    // ao mesmo tempo, e o segundo UPDATE espera o primeiro e já não casa.
    $virou_paga = false;
    if ($paga) {
        $pagos = financeiro_status_pagos();
        $res_virou = asaas_sql($conn, "
            UPDATE id_missao_contribuicao SET idmc_status = ?
            WHERE idmc_id = ? AND idmc_status NOT IN (?, ?, ?)
            RETURNING idmc_id
        ", array_merge(array($status, $idmc_id), $pagos));
        $virou_paga = mysqli_num_rows($res_virou) > 0;
    }

    asaas_sql($conn, "
        UPDATE id_missao_contribuicao SET
            idmc_status = ?,
            idmc_asaas_payment_id = COALESCE(idmc_asaas_payment_id, ?),
            idmc_valor_centavos = CASE WHEN CAST(? AS INTEGER) > 0 THEN CAST(? AS INTEGER) ELSE idmc_valor_centavos END,
            idmc_valor_liquido_centavos = COALESCE(CAST(? AS INTEGER), idmc_valor_liquido_centavos),
            idmc_forma = COALESCE(NULLIF(CAST(? AS TEXT), 'UNDEFINED'), idmc_forma),
            idmc_pago_em = ?,
            idmc_invoice_url = COALESCE(NULLIF(CAST(? AS TEXT), ''), idmc_invoice_url),
            idmc_atualizado_em = NOW()
        WHERE idmc_id = ?
    ", array($status, $payment_id, $valor, $valor, asaas_valor_liquido_centavos($pagamento), (string) ($pagamento['billingType'] ?? ''), $paga ? $pago_em : null, (string) ($pagamento['invoiceUrl'] ?? ''), $idmc_id));

    if ($virou_paga && !empty($atual['idf_id'])) {
        // Dentro da transação do webhook, um INSERT que falha abortaria a
        // transação inteira (e o COMMIT viraria ROLLBACK em silêncio): o
        // savepoint isola o agradecimento, que é o de menos.
        $em_transacao = $conn->inTransaction();
        if ($em_transacao) {
            $conn->exec('SAVEPOINT missao_agradecimento');
        }
        $notificou = db_query($conn, "
            INSERT INTO ID_NOTIFICACAO (IDF_ID, IDN_TITULO, IDN_MENSAGEM, IDN_TIPO, IDN_LINK)
            VALUES (?, ?, ?, 'missao', ?)
        ", array(
            (int) $atual['idf_id'],
            'Obrigado pela sua contribuição 🙏',
            'Sua contribuição de ' . assinatura_formatar_valor($valor) . ' para "' . mb_strimwidth((string) $atual['idmp_titulo'], 0, 60, '…') . '" foi confirmada. Deus abençoe!',
            'missao.php?id=' . (int) $atual['idmp_id'] . '#apoiadores',
        ));
        if ($em_transacao) {
            $conn->exec($notificou ? 'RELEASE SAVEPOINT missao_agradecimento' : 'ROLLBACK TO SAVEPOINT missao_agradecimento');
        }
    }
    return true;
}

// Ao voltar da página de pagamento: consulta no Asaas as contribuições do
// fiel ainda pendentes (rede de segurança para o webhook atrasado).
function missao_sincronizar_pendentes($conn, $fiel_id) {
    if (!missoes_disponivel($conn) || !asaas_configurado()) {
        return;
    }
    $res = db_query($conn, "
        SELECT idmc_asaas_payment_id FROM id_missao_contribuicao
        WHERE idf_id = ? AND idmc_status IN ('PENDING', 'AWAITING_RISK_ANALYSIS') AND idmc_asaas_payment_id IS NOT NULL
          AND idmc_criado_em > NOW() - INTERVAL '2 days'
        ORDER BY idmc_id DESC LIMIT 3
    ", array((int) $fiel_id));
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        list($status, $pagamento) = asaas_api('GET', '/payments/' . rawurlencode((string) $linha['idmc_asaas_payment_id']));
        if ($status !== 200 || !is_array($pagamento)) {
            continue;
        }
        try {
            missao_aplicar_pagamento($conn, $pagamento);
        } catch (\Throwable $e) {
            error_log('missao_sincronizar_pendentes: ' . $e->getMessage());
        }
    }
}

// Oferta recebida fora do app (culto, Pix direto), lançada pelo superadmin:
// soma na barra do projeto e, se a equipe recebeu, no Financeiro.
function missao_lancar_oferta($conn, $projeto_id, $valor_texto, $data, $nome, $recebido_por, $admin_id) {
    $valor = site_valor_para_centavos($valor_texto);
    $nome = trim((string) $nome);
    $data_ok = DateTime::createFromFormat('Y-m-d', (string) $data);
    if (!missao_projeto($conn, $projeto_id, true)) {
        return 'Projeto não encontrado.';
    }
    if ($valor <= 0) {
        return 'Informe um valor válido.';
    }
    if (!$data_ok || $data_ok->format('Y-m-d') !== $data || $data > site_hoje()) {
        return 'Informe uma data válida (hoje ou antes).';
    }
    if (!in_array($recebido_por, array('equipe', 'igreja'), true)) {
        return 'Informe quem recebeu o dinheiro.';
    }
    if (mb_strlen($nome) > 100) {
        return 'O nome exibido pode ter até 100 caracteres.';
    }
    $ok = db_query($conn, "
        INSERT INTO id_missao_contribuicao (idmp_id, idmc_origem, idmc_recebido_por, idmc_nome_exibicao, idmc_valor_centavos, idmc_valor_liquido_centavos, idmc_forma, idmc_status, idmc_pago_em, idmc_lancado_por)
        VALUES (?, 'manual', ?, ?, ?, ?, 'MANUAL', 'RECEIVED_IN_CASH', ?, ?)
    ", array((int) $projeto_id, $recebido_por, $nome !== '' ? $nome : null, $valor, $valor, $data, (int) $admin_id));
    return $ok ? '' : site_erro_banco('Não foi possível lançar a oferta.');
}

// ---- Pix direto: QR Code estático (seção 77) ----
// Além da contribuição com valor (missao_contribuir), cada projeto aberto
// tem um QR Code Pix fixo SEM valor: a pessoa lê (ou copia o código) no
// app do banco e digita quanto quer dar — inclusive menos que o mínimo de
// R$ 5 da cobrança. O QR é criado no Asaas na primeira vez que alguém abre
// o projeto e fica guardado em idmp_pix_*. Cada Pix recebido vira uma
// cobrança no Asaas com pixQrCodeId; o webhook (missao_aplicar_pix_qr)
// registra como contribuição de origem 'qrcode', sem nome: o Asaas não diz
// qual fiel pagou.

function missao_pix_qr_disponivel($conn) {
    return tabela_tem_coluna($conn, 'id_missao_projeto', 'idmp_pix_qr_id');
}

// Chave Pix que recebe: ASAAS_PIX_CHAVE (Render) ou a primeira chave ativa
// da conta do Asaas. '' se não houver.
function asaas_pix_chave() {
    $chave = trim((string) site_env('ASAAS_PIX_CHAVE'));
    if ($chave !== '') {
        return $chave;
    }
    list($status, $resposta) = asaas_api('GET', '/pix/addressKeys?status=ACTIVE&limit=1');
    return $status === 200 && !empty($resposta['data'][0]['key']) ? (string) $resposta['data'][0]['key'] : '';
}

// Separa a resposta do POST /pix/qrCodes/static em id, imagem e "copia e
// cola". A documentação do Asaas troca a descrição de "id" e
// "encodedImage"; a imagem é a que começa como PNG em base64 ("iVBOR").
// Função pura.
function asaas_pix_qr_da_resposta($resposta) {
    if (!is_array($resposta) || empty($resposta['payload'])) {
        return null;
    }
    $id = '';
    $imagem = '';
    foreach (array('id', 'encodedImage') as $campo) {
        $valor = (string) ($resposta[$campo] ?? '');
        if (strpos($valor, 'iVBOR') === 0) {
            $imagem = $valor;
        } elseif ($valor !== '') {
            $id = $valor;
        }
    }
    if ($id === '' || $imagem === '' || strlen($id) > 60) {
        return null;
    }
    return array('id' => $id, 'imagem' => $imagem, 'payload' => (string) $resposta['payload']);
}

// QR Pix do projeto aberto: array('imagem' => PNG em base64, 'payload' =>
// "copia e cola") ou null (sem a seção 77, sem Asaas ou se o Asaas falhar;
// depois de uma falha, espera 10 minutos antes de tentar de novo).
function missao_pix_qr($conn, array $projeto) {
    if (!missao_pix_qr_disponivel($conn) || !asaas_configurado() || ($projeto['situacao'] ?? '') !== 'aberto') {
        return null;
    }
    if (!empty($projeto['idmp_pix_payload']) && !empty($projeto['idmp_pix_imagem'])) {
        return array('imagem' => (string) $projeto['idmp_pix_imagem'], 'payload' => (string) $projeto['idmp_pix_payload']);
    }
    $chave_falha = 'missao_pix_qr_falhou_' . (int) $projeto['idmp_id'];
    if (time() - (int) ($_SESSION[$chave_falha] ?? 0) < 600) {
        return null;
    }

    $pix_chave = asaas_pix_chave();
    $qr = null;
    if ($pix_chave !== '') {
        $corpo = array(
            'addressKey' => $pix_chave,
            'description' => mb_substr('Oferta: ' . $projeto['idmp_titulo'], 0, 60),
            'format' => 'ALL',
            'allowsMultiplePayments' => true,
            'externalReference' => 'missao_qr_' . (int) $projeto['idmp_id'],
        );
        // Com data de encerramento, o banco recusa Pix depois dela.
        if (!empty($projeto['idmp_data_fim'])) {
            $corpo['expirationDate'] = $projeto['idmp_data_fim'] . ' 23:59:59';
        }
        list($status, $resposta) = asaas_api('POST', '/pix/qrCodes/static', $corpo);
        $qr = $status >= 200 && $status < 300 ? asaas_pix_qr_da_resposta($resposta) : null;
        if (!$qr) {
            error_log('missao_pix_qr projeto ' . (int) $projeto['idmp_id'] . ': HTTP ' . $status . ' ' . json_encode($resposta['errors'] ?? $resposta));
        }
    } else {
        error_log('missao_pix_qr: a conta do Asaas não tem chave Pix ativa (ou defina ASAAS_PIX_CHAVE).');
    }
    if (!$qr) {
        $_SESSION[$chave_falha] = time();
        return null;
    }

    // Só grava se ninguém gravou antes (duas pessoas abrindo ao mesmo tempo):
    // vale o QR que chegou primeiro.
    db_query($conn, "
        UPDATE id_missao_projeto SET idmp_pix_qr_id = ?, idmp_pix_payload = ?, idmp_pix_imagem = ?
        WHERE idmp_id = ? AND idmp_pix_qr_id IS NULL
    ", array($qr['id'], $qr['payload'], $qr['imagem'], (int) $projeto['idmp_id']));
    $res = db_query($conn, "SELECT idmp_pix_payload, idmp_pix_imagem FROM id_missao_projeto WHERE idmp_id = ?", array((int) $projeto['idmp_id']));
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha && !empty($linha['idmp_pix_payload'])
        ? array('imagem' => (string) $linha['idmp_pix_imagem'], 'payload' => (string) $linha['idmp_pix_payload'])
        : array('imagem' => $qr['imagem'], 'payload' => $qr['payload']);
}

// Pix recebido por um QR estático de projeto (webhook). Grava ou atualiza
// a contribuição pela cobrança do Asaas (idmc_asaas_payment_id é único,
// então o reenvio do webhook não duplica). false = o QR não é de projeto
// nenhum (segue o fluxo normal). Erro de banco lança exceção (o webhook
// devolve 500 e o Asaas reenvia).
function missao_aplicar_pix_qr($conn, array $pagamento) {
    $qr_id = (string) ($pagamento['pixQrCodeId'] ?? '');
    $payment_id = (string) ($pagamento['id'] ?? '');
    if ($qr_id === '' || $payment_id === '' || !missao_pix_qr_disponivel($conn)) {
        return false;
    }
    $res = asaas_sql($conn, "SELECT idmp_id FROM id_missao_projeto WHERE idmp_pix_qr_id = ?", array($qr_id));
    $projeto = mysqli_fetch_assoc($res);
    if (!$projeto) {
        return false;
    }

    $status = !empty($pagamento['deleted']) ? 'DELETED' : strtoupper((string) ($pagamento['status'] ?? 'RECEIVED'));
    $paga = in_array($status, financeiro_status_pagos(), true);
    $pago_em = (string) ($pagamento['paymentDate'] ?? ($pagamento['confirmedDate'] ?? ''));
    $pago_em = preg_match('/^\d{4}-\d{2}-\d{2}$/', $pago_em) ? $pago_em : ($paga ? site_hoje() : null);
    $valor = (int) round(((float) ($pagamento['value'] ?? 0)) * 100);
    if ($valor <= 0) {
        return true;
    }

    asaas_sql($conn, "
        INSERT INTO id_missao_contribuicao (idmp_id, idmc_origem, idmc_recebido_por, idmc_anonimo, idmc_valor_centavos, idmc_valor_liquido_centavos, idmc_forma, idmc_status, idmc_asaas_payment_id, idmc_asaas_customer_id, idmc_pago_em, idmc_invoice_url)
        VALUES (?, 'qrcode', 'equipe', 1, ?, ?, 'PIX', ?, ?, ?, ?, ?)
        ON CONFLICT (idmc_asaas_payment_id) DO UPDATE SET
            idmc_status = EXCLUDED.idmc_status,
            idmc_valor_centavos = EXCLUDED.idmc_valor_centavos,
            idmc_valor_liquido_centavos = COALESCE(EXCLUDED.idmc_valor_liquido_centavos, id_missao_contribuicao.idmc_valor_liquido_centavos),
            idmc_pago_em = EXCLUDED.idmc_pago_em,
            idmc_atualizado_em = NOW()
    ", array(
        (int) $projeto['idmp_id'], $valor, asaas_valor_liquido_centavos($pagamento), $status, $payment_id,
        (string) ($pagamento['customer'] ?? '') !== '' ? (string) $pagamento['customer'] : null,
        $paga ? $pago_em : null,
        (string) ($pagamento['transactionReceiptUrl'] ?? ($pagamento['invoiceUrl'] ?? '')) ?: null,
    ));
    return true;
}

// Oferta geral: a oferta livre para manter o app, da equipe interna
// (Admin → Contribuições). É um projeto de missão marcado aqui; a aba
// Contribuir do fiel (php/fiel/contribuir.php) abre direto a página dele, e
// ele fica fora da aba Missões. Usa a mesma contribuição, webhook e
// Financeiro das missões. 0 = nenhum.
function missoes_oferta_geral_id() {
    return max(0, (int) site_config('missoes_oferta_geral', '0'));
}

// ============================================================================
// DESTAQUES DA HOME (carrossel; seção 75 do bancosupabase.md)
// ============================================================================
// O topo da home do fiel (php/fiel/dashboard.php) é um carrossel: o curso em
// destaque (se ligado) e as artes cadastradas em Admin → Destaques da home
// (php/admin/admin_destaques_home.php) — contribuição, novidades, avisos.
// Cada arte é uma imagem pronta (o texto vem desenhado nela), com destino
// do clique opcional, ordem e período em que aparece.

function home_destaques_disponivel($conn) {
    return site_tabela_existe($conn, 'id_home_destaque');
}

// O curso em destaque entra como primeiro slide? (padrão: sim)
function home_destaque_curso_ligado() {
    return site_config('home_destaque_curso', '1') === '1';
}

// Destino do clique: página do app (relativa a php/fiel/, ex.:
// "contribuir.php", "missao.php?id=3") ou link https externo.
function home_destaque_link_valido($link) {
    $link = trim((string) $link);
    return preg_match('#^[a-z0-9_]+\.php(\?[a-z0-9_=&%-]*)?(\#[a-z0-9_-]*)?$#i', $link) === 1
        || (preg_match('#^https://[^\s"<>]+$#i', $link) === 1 && filter_var($link, FILTER_VALIDATE_URL) !== false);
}

// Situação de uma arte em $hoje: no_ar | agendada | expirada | guardada.
function home_destaque_situacao(array $d, $hoje) {
    if ((int) $d['idhd_ativo'] !== 1) {
        return 'guardada';
    }
    if (!empty($d['idhd_inicio']) && $d['idhd_inicio'] > $hoje) {
        return 'agendada';
    }
    if (!empty($d['idhd_fim']) && $d['idhd_fim'] < $hoje) {
        return 'expirada';
    }
    return 'no_ar';
}

// Artes na ordem do carrossel, com 'situacao'. Sem $todas, só as no ar hoje.
function home_destaques($conn, $todas = false) {
    if (!home_destaques_disponivel($conn)) {
        return array();
    }
    $res = db_query($conn, "SELECT * FROM id_home_destaque ORDER BY idhd_ordem, idhd_id");
    $hoje = site_hoje();
    $lista = array();
    foreach ($res ? $res->todas_as_linhas() : array() as $d) {
        $d['situacao'] = home_destaque_situacao($d, $hoje);
        if ($todas || $d['situacao'] === 'no_ar') {
            $lista[] = $d;
        }
    }
    return $lista;
}

// URL de uma imagem de arte: "r2:destaques/..." (enviada pelo admin) ou
// "assets/img/destaques/..." (arte que já vem no repositório, como as da
// seção 75). Mesma regra das capas de missão.
function home_destaque_imagem_url($valor) {
    return missao_capa_url($valor);
}

// Arte pronta para o carrossel (php/fiel/includes/home_carrossel.php):
// URLs das imagens resolvidas e o link conferido. null se a imagem não
// abrir (ex.: R2 fora do ar).
function home_destaque_slide(array $d) {
    $imagem = home_destaque_imagem_url($d['idhd_imagem_url'] ?? '');
    if (!$imagem) {
        return null;
    }
    $link = trim((string) ($d['idhd_link'] ?? ''));
    $link = home_destaque_link_valido($link) ? $link : '';
    return array(
        'titulo' => (string) $d['idhd_titulo'],
        'imagem' => $imagem,
        'imagem_celular' => home_destaque_imagem_url($d['idhd_imagem_celular_url'] ?? ''),
        'link' => $link,
        'externo' => stripos($link, 'https://') === 0,
    );
}

if (!defined('MODO_BANCO')) {
    // 'robusto' usa password_hash()/password_verify() de verdade (IDF_SENHA_HASH);
    // o modo 'simples' guarda a senha em TEXTO PURO (IDF_SENHA) — como o schema
    // Postgres novo já tem as colunas do modo robusto, não tem motivo pra manter
    // o modo simples (mais fraco) como padrão.
    define('MODO_BANCO', 'robusto');
}

function banco_eh_robusto() {
    return MODO_BANCO === 'robusto';
}

// Mapa tabela => colunas do schema public, para tabela_tem_coluna() e
// site_tabela_existe() (o app pergunta "essa seção do bancosupabase.md já
// rodou?" em ~50 lugares). Antes cada pergunta era uma consulta ao
// information_schema em toda requisição — várias idas ao banco remoto por
// página. Agora é UMA consulta, guardada num arquivo do container por
// SCHEMA_CACHE_SEGUNDOS: depois de rodar um SQL novo no Supabase, o app
// percebe em até 5 min (ou na hora, num deploy). Devolve null se o banco
// não respondeu (aí quem chama consulta direto, como antes).
if (!defined('SCHEMA_CACHE_SEGUNDOS')) {
    define('SCHEMA_CACHE_SEGUNDOS', 300);
}

function site_schema_mapa($conn) {
    static $mapa = null;
    if ($mapa !== null) {
        return $mapa;
    }
    $arquivo = sys_get_temp_dir() . '/mananciapp_schema_' . md5((string) site_env('DB_HOST') . '|' . (string) site_env('DB_NAME')) . '.json';
    if (is_file($arquivo) && (time() - (int) @filemtime($arquivo)) < SCHEMA_CACHE_SEGUNDOS) {
        $lido = json_decode((string) @file_get_contents($arquivo), true);
        if (is_array($lido) && !empty($lido)) {
            return $mapa = $lido;
        }
    }
    $res = $conn ? mysqli_query($conn, "SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = 'public'") : false;
    if (!$res) {
        return null;
    }
    $novo = array();
    while ($linha = mysqli_fetch_assoc($res)) {
        $novo[strtolower((string) $linha['table_name'])][strtolower((string) $linha['column_name'])] = true;
    }
    if (!empty($novo)) {
        // Grava num temporário e renomeia: outra requisição nunca lê um
        // arquivo pela metade.
        $temporario = $arquivo . '.' . getmypid() . '.tmp';
        if (@file_put_contents($temporario, json_encode($novo)) !== false) {
            @rename($temporario, $arquivo);
        }
    }
    return $mapa = $novo;
}

// A versão antiga continua em php/fiel/includes/cursos_helpers.php (dentro de
// um function_exists), mas esta é definida antes e é a que vale.
function site_tabela_existe($conn, $tabela) {
    $mapa = site_schema_mapa($conn);
    if ($mapa !== null) {
        return isset($mapa[strtolower((string) $tabela)]);
    }
    $resultado = db_query($conn, "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?", array(strtolower($tabela)));
    return $resultado && mysqli_num_rows($resultado) > 0;
}

function tabela_tem_coluna($conn, $tabela, $coluna) {
    // Os nomes das tabelas/colunas foram criados SEM aspas no Postgres, então
    // o próprio banco já guarda tudo em minúsculo (mesma convenção usada nas
    // queries do código, que também não usam aspas).
    $mapa = site_schema_mapa($conn);
    if ($mapa !== null) {
        return isset($mapa[strtolower((string) $tabela)][strtolower((string) $coluna)]);
    }
    $resultado = db_query($conn, "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ?", array(strtolower($tabela), strtolower($coluna)));
    return $resultado && mysqli_num_rows($resultado) > 0;
}

// ============================================================================
// SEÇÕES DO BANCO RODADAS (seção 86 do bancosupabase.md)
// ============================================================================
// Última seção do database/bancosupabase.md que este código usa. Toda seção
// nova termina com INSERT em id_schema_versao e sobe este número junto (o
// CI confere os dois). O dashboard do admin avisa a equipe interna quando o
// banco está atrás.
define('BANCO_SECAO_ESPERADA', 90);

// Maior seção registrada no banco; null quando nem a 86 rodou (sem
// registro, não dá para saber). Cache de 1 min para todos.
function banco_secao_atual($conn) {
    if (!site_tabela_existe($conn, 'id_schema_versao')) {
        return null;
    }
    $secao = site_cache_compartilhado('banco_secao_atual', 60, function () use ($conn) {
        $res = mysqli_query($conn, 'SELECT MAX(isv_secao) AS secao FROM id_schema_versao');
        $linha = $res ? mysqli_fetch_assoc($res) : null;
        return $linha ? (int) $linha['secao'] : false;
    });
    return $secao === false || $secao === null ? null : (int) $secao;
}

// Variáveis do Render sem as quais a produção funciona "pela metade" sem
// avisar: links indo para a homologação, Asaas em sandbox, e-mail e push
// mudos, lembrete sem chave, sessões perdidas a cada deploy. Devolve a lista
// do que falta ('' => motivo). Na homologação e no XAMPP, lista vazia.
function site_config_producao_faltando($host = null) {
    $host = (string) ($host ?? ($_SERVER['HTTP_HOST'] ?? ''));
    if (site_ambiente_local() || stripos($host, 'homolog') !== false || $host === '') {
        return array();
    }
    $faltando = array();
    $exigidas = array(
        'SITE_URL_PUBLICA' => 'links de e-mail e push',
        'ASAAS_API_URL' => 'sem ela o Asaas fica no SANDBOX',
        'ASAAS_API_KEY' => 'pagamentos',
        'ASAAS_WEBHOOK_TOKEN' => 'liberação depois do pagamento',
        'EMAIL_API_KEY' => 'e-mails (esqueci a senha, aprovação)',
        'EMAIL_SENDER_EMAIL' => 'remetente dos e-mails',
        'ONESIGNAL_REST_API_KEY' => 'notificações no celular',
        'LEMBRETE_CRON_CHAVE' => 'lembrete diário e limpeza',
        'SENTRY_DSN' => 'alertas de erro',
    );
    foreach ($exigidas as $chave => $motivo) {
        if (trim((string) site_env($chave)) === '') {
            $faltando[$chave] = $motivo;
        }
    }
    if (stripos((string) site_env('ASAAS_API_URL'), 'sandbox') !== false) {
        $faltando['ASAAS_API_URL'] = 'está apontando para o SANDBOX';
    }
    if ((string) getenv('SESSOES_NO_BANCO') !== '1') {
        $faltando['SESSOES_NO_BANCO'] = 'sem "1", todo deploy desloga todo mundo';
    }
    if (doisfa_chave_cifra() === '') {
        $faltando['APP_CHAVE'] = 'chave do 2FA sem cifra no banco; 32+ letras';
    }
    return $faltando;
}

// Avisos de operação para a equipe interna (Dashboard do admin): seções do
// banco, variáveis de produção e o agendador do lembrete parado. Também
// manda as variáveis faltando ao Sentry, no máximo 1 vez por hora.
function site_avisos_equipe($conn) {
    $avisos = array();
    $secoes = banco_aviso_secoes(banco_secao_atual($conn));
    if ($secoes !== '') {
        $avisos[] = $secoes;
    }
    $faltando = site_config_producao_faltando();
    if ($faltando) {
        $partes = array();
        foreach ($faltando as $chave => $motivo) {
            $partes[] = $chave . ' (' . $motivo . ')';
        }
        $avisos[] = 'Variáveis do Render faltando na produção: ' . implode('; ', $partes) . '.';
        if (site_intervalo_livre('alerta_config_producao', 3600)) {
            site_alerta_erro('warning', 'Produção sem: ' . implode(', ', array_keys($faltando)), 'ConfigFaltando');
        }
    }
    $ultima = (string) site_config('rotina_lembrete_ultima', '');
    if (trim((string) site_env('LEMBRETE_CRON_CHAVE')) !== '') {
        $quando = $ultima !== '' ? strtotime($ultima) : false;
        if (!$quando) {
            $avisos[] = 'O agendador do lembrete diário (cron-job.org) ainda não chamou o app nenhuma vez.';
        } elseif ($quando < time() - 26 * 3600) {
            $avisos[] = 'O agendador do lembrete diário não chama o app desde ' . date('d/m H:i', $quando) . ': lembretes e limpeza parados.';
        }
    }
    return $avisos;
}

// Texto do aviso ('' quando está tudo rodado).
function banco_aviso_secoes($atual, $esperada = BANCO_SECAO_ESPERADA) {
    $esperada = (int) $esperada;
    if ($atual === null) {
        return 'O banco não tem o registro das seções: rode a seção 86 do database/bancosupabase.md (e as anteriores que faltarem).';
    }
    $atual = (int) $atual;
    if ($atual >= $esperada) {
        return '';
    }
    $faltam = $atual + 1 === $esperada ? 'a seção ' . $esperada : 'as seções ' . ($atual + 1) . ' a ' . $esperada;
    return 'O banco está na seção ' . $atual . ' e este código usa até a ' . $esperada . ': rode ' . $faltam . ' do database/bancosupabase.md, nessa ordem.';
}

// ============================================================================
// SHIM DE COMPATIBILIDADE mysqli_* -> PDO/Postgres
// ============================================================================
// O app inteiro foi escrito chamando mysqli_connect/mysqli_query/etc direto,
// sem nenhuma camada de abstração (nem PDO, nem ORM) — são ~123 chamadas
// espalhadas em ~30 arquivos. Reescrever cada uma delas seria um trabalho
// grande e arriscado. Em vez disso, este bloco define funções com o MESMO
// NOME das funções da extensão mysqli, mas usando PDO+Postgres por baixo —
// assim o resto do código não precisa mudar. Isso só funciona porque a
// extensão mysqli NÃO está instalada no Dockerfile (senão haveria conflito
// de nome com as funções nativas da extensão).
//
// Result set: como o driver pdo_pgsql não garante contagem de linhas
// confiável via PDOStatement::rowCount() para SELECTs, a solução é buscar
// todas as linhas de uma vez (fetchAll) e simular um "cursor" simples com a
// classe SitePgResult abaixo. Para uma aplicação deste porte (poucas centenas
// de linhas por consulta) isso não é um problema de performance.
if (!class_exists('SitePgResult')) {
    class SitePgResult {
        private $linhas;
        private $posicao = 0;
        public $num_rows;

        public function __construct(array $linhas) {
            // O Postgres sempre devolve nome de coluna/alias em minúsculo pra
            // qualquer referência sem aspas (mesmo escrevendo "AS EH_ADMIN" ou
            // "SELECT IDF_ID", o resultado vem com a chave "eh_admin"/"idf_id").
            // O MySQL/mysqli original preservava a caixa exatamente como
            // escrita na query — e o código usa os dois padrões hoje (maioria
            // em MAIÚSCULO, tipo $row['IDF_ID'], mas alguns aliases em
            // minúsculo, tipo $row['categoria_nome']). Pra não ter que tocar
            // em nenhuma query nem em nenhum ponto de leitura espalhado pelo
            // código, cada linha ganha as DUAS versões de cada chave.
            $this->linhas = array_map(function ($linha) {
                return array_merge($linha, array_change_key_case($linha, CASE_UPPER));
            }, array_values($linhas));
            $this->num_rows = count($this->linhas);
        }

        public function proxima_linha() {
            if ($this->posicao >= count($this->linhas)) {
                return null;
            }
            return $this->linhas[$this->posicao++];
        }

        public function todas_as_linhas() {
            return $this->linhas;
        }
    }
}

// Ajusta diferenças de sintaxe MySQL -> Postgres que são seguras de tratar de
// forma genérica (sem risco de mexer em texto que não seja SQL de verdade):
// - UPDATE/DELETE ... LIMIT n (Postgres não aceita LIMIT nesses comandos; o
//   WHERE já é específico o bastante nos dois casos, então só removemos).
// - LIMIT offset, count (sintaxe MySQL de 2 argumentos) -> LIMIT count OFFSET offset.
if (!function_exists('site_ajustar_sql_para_pg')) {
    function site_ajustar_sql_para_pg($sql) {
        if (preg_match('/^\s*(UPDATE|DELETE)\b/i', $sql)) {
            $sql = preg_replace('/\s+LIMIT\s+\d+\s*$/i', '', $sql);
        }
        // Só fora dos textos entre aspas simples: antes a troca rodava no SQL
        // inteiro e reescrevia o que o usuário digitou — um comentário com
        // "limit 3, 4" era gravado como "LIMIT 4 OFFSET 3". O split separa os
        // literais ('...', com '' como aspa escapada) nas posições ímpares.
        // Quantificadores possessivos: a versão "(?:[^']|'')*" estourava a
        // pilha do JIT do PCRE com um literal acima de ~10 KB (ex.: a foto de
        // perfil em base64 contendo "limit" por acaso), o preg_split
        // devolvia false e a página caía com erro 500.
        if (stripos($sql, 'limit') !== false) {
            $partes = preg_split("/('(?:[^']++|'')*+')/", $sql, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($partes === false) {
                return $sql;
            }
            foreach ($partes as $i => $parte) {
                if ($i % 2 === 0) {
                    $partes[$i] = preg_replace('/\bLIMIT\s+(\d+)\s*,\s*(\d+)/i', 'LIMIT $2 OFFSET $1', $parte);
                }
            }
            $sql = implode('', $partes);
        }
        return $sql;
    }
}

if (!function_exists('mysqli_connect')) {
    function mysqli_connect($host, $usuario, $senha, $banco, $porta = 5432) {
        try {
            $dsn = "pgsql:host=$host;port=$porta;dbname=$banco";
            // Supabase: exige TLS. O padrão do libpq ("prefer") aceitava cair
            // para conexão sem criptografia se o TLS falhasse no caminho.
            // Postgres local (DB_HOST_LOCAL) costuma não ter TLS: fica no padrão.
            if (stripos((string) $host, 'supabase.co') !== false) {
                $dsn .= ';sslmode=require';
            }
            $pdo = new PDO($dsn, $usuario, $senha, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                // Conexão persistente: o Postgres do Supabase é remoto (ao
                // contrário do MySQL local de antes), então abrir uma conexão
                // nova a cada página (handshake TCP+TLS+auth) pesa bastante na
                // navegação. Com conexão persistente, o processo do Apache
                // reaproveita a mesma conexão entre requisições — só paga o
                // handshake na primeira vez que aquele worker atende alguém.
                PDO::ATTR_PERSISTENT => true,
            ));
            // Sem isso o pdo_pgsql faz 3 idas ao banco por consulta — PREPARE,
            // EXECUTE e DEALLOCATE —, inclusive no query() sem parâmetros
            // (todo mysqli_query do shim). Ligado, manda tudo numa ida só
            // (PQexecParams): os parâmetros continuam separados do SQL, só
            // não fica um prepared statement guardado no servidor. Medido
            // contra o Supabase: ~408 ms → ~133 ms por consulta.
            $pdo->setAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES, true);
            return $pdo;
        } catch (PDOException $e) {
            $GLOBALS['site_pg_last_error'] = $e->getMessage();
            return false;
        }
    }
}

if (!function_exists('mysqli_query')) {
    function mysqli_query($conn, $sql) {
        if (!$conn) {
            return false;
        }

        $sql = site_ajustar_sql_para_pg($sql);
        // Zerado a cada consulta: antes, se um UPDATE/DELETE falhava,
        // mysqli_affected_rows() devolvia o número da consulta anterior (ex.:
        // "Desafio excluído" sem ter excluído nada).
        $GLOBALS['site_pg_last_affected_rows'] = 0;

        $stmt = $conn->query($sql);
        if ($stmt === false) {
            $info = $conn->errorInfo();
            $GLOBALS['site_pg_last_error'] = $info[2] ?? 'Erro desconhecido ao consultar o banco.';
            return false;
        }

        $GLOBALS['site_pg_last_affected_rows'] = $stmt->rowCount();
        if ($stmt->columnCount() === 0) {
            // INSERT/UPDATE/DELETE sem RETURNING: não há linhas pra buscar.
            return new SitePgResult(array());
        }

        return new SitePgResult($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}

if (!function_exists('mysqli_fetch_assoc')) {
    function mysqli_fetch_assoc($resultado) {
        if (!($resultado instanceof SitePgResult)) {
            return null;
        }
        return $resultado->proxima_linha();
    }
}

if (!function_exists('mysqli_num_rows')) {
    function mysqli_num_rows($resultado) {
        return ($resultado instanceof SitePgResult) ? $resultado->num_rows : 0;
    }
}

if (!function_exists('mysqli_real_escape_string')) {
    function mysqli_real_escape_string($conn, $valor) {
        // PDO::quote() devolve o valor JÁ com aspas simples em volta; como o
        // resto do código sempre adiciona as próprias aspas ao montar o SQL
        // (igual ao contrato do mysqli_real_escape_string original), removemos
        // as aspas externas aqui pra manter o mesmo comportamento de sempre.
        // Sem conexão (banco fora do ar): a consulta nem vai rodar
        // (mysqli_query devolve false), mas antes isto dava erro fatal e a
        // página caía com 500 em vez de mostrar o aviso de banco indisponível.
        if (!$conn) {
            return str_replace("'", "''", (string) $valor);
        }
        $com_aspas = $conn->quote((string) $valor);
        return substr($com_aspas, 1, -1);
    }
}

if (!function_exists('mysqli_insert_id')) {
    function mysqli_insert_id($conn) {
        // Funciona de forma genérica (sem precisar saber o nome da sequence)
        // porque toda coluna GENERATED ... AS IDENTITY cria uma sequence, e o
        // Postgres guarda "o último nextval() desta sessão" via lastval().
        try {
            return (int) $conn->lastInsertId();
        } catch (PDOException $e) {
            return 0;
        }
    }
}

if (!function_exists('mysqli_affected_rows')) {
    function mysqli_affected_rows($conn) {
        return (int) ($GLOBALS['site_pg_last_affected_rows'] ?? 0);
    }
}

if (!function_exists('mysqli_error')) {
    function mysqli_error($conn) {
        return (string) ($GLOBALS['site_pg_last_error'] ?? '');
    }
}

if (!function_exists('mysqli_close')) {
    function mysqli_close($conn) {
        return true;
    }
}

if (!function_exists('mysqli_set_charset')) {
    function mysqli_set_charset($conn, $charset) {
        // Postgres já conecta em UTF-8 por padrão; nada a fazer aqui.
        return true;
    }
}

if (!function_exists('mysqli_report')) {
    function mysqli_report($niveis) {
        return true;
    }
}

// MYSQLI_REPORT_OFF é uma constante da extensão mysqli, que não está mais
// instalada — definimos um valor qualquer só pra chamada abaixo não quebrar.
if (!defined('MYSQLI_REPORT_OFF')) {
    define('MYSQLI_REPORT_OFF', 0);
}

mysqli_report(MYSQLI_REPORT_OFF);

// Consulta preparada: o SQL leva marcadores "?" e os valores vão separados,
// sem passar por mysqli_real_escape_string — esquecer de escapar um valor
// deixa de abrir injeção de SQL, porque o valor nunca entra no texto do SQL.
// Devolve o mesmo que mysqli_query() (SitePgResult ou false), então quem
// chama lê o resultado igual (mysqli_fetch_assoc, mysqli_num_rows...).
// Código novo deve usar esta função; o antigo vai migrando aos poucos,
// começando pelas telas públicas (login, cadastro, recuperação de senha).
// Os parâmetros vão separados do SQL numa ida só ao banco
// (PGSQL_ATTR_DISABLE_PREPARES, em mysqli_connect()). Usar o Session pooler
// do Supabase (porta 5432, ver .env.example): a conexão é persistente.
function db_query($conn, $sql, array $params = array()) {
    if (!$conn) {
        return false;
    }

    $GLOBALS['site_pg_last_affected_rows'] = 0;
    $stmt = $conn->prepare(site_ajustar_sql_para_pg($sql));
    if ($stmt === false) {
        $info = $conn->errorInfo();
        $GLOBALS['site_pg_last_error'] = $info[2] ?? 'Erro desconhecido ao preparar a consulta.';
        return false;
    }

    $posicao = 1;
    foreach (array_values($params) as $valor) {
        if ($valor === null) {
            $stmt->bindValue($posicao++, null, PDO::PARAM_NULL);
        } elseif (is_int($valor) || is_bool($valor)) {
            $stmt->bindValue($posicao++, (int) $valor, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($posicao++, (string) $valor, PDO::PARAM_STR);
        }
    }

    if (!$stmt->execute()) {
        $info = $stmt->errorInfo();
        $GLOBALS['site_pg_last_error'] = $info[2] ?? 'Erro desconhecido ao consultar o banco.';
        return false;
    }

    $GLOBALS['site_pg_last_affected_rows'] = $stmt->rowCount();
    if ($stmt->columnCount() === 0) {
        return new SitePgResult(array());
    }

    return new SitePgResult($stmt->fetchAll(PDO::FETCH_ASSOC));
}

$site_db_error = '';

// Banco de dados: Postgres do Supabase. Em produção (Render) e em desenvolvimento
// local (XAMPP) dá pra apontar pro MESMO banco do Supabase (ele aceita conexão
// de qualquer lugar) — mas a separação DB_*/DB_*_LOCAL continua disponível caso
// você prefira usar um Postgres local pra testar sem tocar nos dados de produção.
$eh_ambiente_local = site_ambiente_local();

if ($eh_ambiente_local && site_env('DB_HOST_LOCAL') !== '') {
    $host = trim((string) site_env('DB_HOST_LOCAL', 'localhost'));
    $porta = (int) site_env('DB_PORT_LOCAL', '5432');
    $usuario = trim((string) site_env('DB_USER_LOCAL', 'postgres'));
    $senha = trim((string) site_env('DB_PASS_LOCAL', ''));
    $banco = trim((string) site_env('DB_NAME_LOCAL', 'postgres'));
} else {
    $host = trim((string) site_env('DB_HOST'));
    $porta = (int) site_env('DB_PORT', '5432');
    $usuario = trim((string) site_env('DB_USER'));
    $senha = trim((string) site_env('DB_PASS'));
    $banco = trim((string) site_env('DB_NAME', 'postgres'));
}

$conn = mysqli_connect($host, $usuario, $senha, $banco, $porta);

if (!$conn) {
    // O detalhe da PDOException traz host, porta e usuário do banco: vai só
    // para o log. Na tela (login.php é pública), só no XAMPP local, onde
    // ajuda quem está configurando o .env.
    $detalhe_conexao = (string) ($GLOBALS['site_pg_last_error'] ?? '');
    error_log('Banco: falha ao conectar (' . $detalhe_conexao . ')');
    // Também no Sentry (antes só no log, e o site inteiro ficava em "Serviço
    // indisponível" sem ninguém saber). No máximo 1 aviso por minuto, e sem
    // o detalhe (que traz host e usuário do banco).
    if (!$eh_ambiente_local && site_intervalo_livre('alerta_banco_fora', 60)) {
        site_alerta_erro('error', 'Banco fora do ar: falha ao conectar ao Postgres/Supabase.', 'BancoFora');
    }
    $site_db_error = $eh_ambiente_local
        ? 'Não foi possível conectar ao banco de dados (Postgres/Supabase). Confira DB_HOST, DB_PORT, DB_USER, DB_PASS e DB_NAME no arquivo .env.'
            . ($detalhe_conexao !== '' ? ' Detalhe: ' . $detalhe_conexao : '')
        : 'Serviço temporariamente indisponível. Tente novamente em alguns minutos.';
}

$GLOBALS['site_db_error'] = $site_db_error;
?>