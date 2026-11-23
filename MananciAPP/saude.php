<?php
// Verificação de saúde para o Render (healthCheckPath no render.yaml): diz
// só se o Apache e o PHP estão respondendo. NÃO consulta o banco de
// propósito — se o Supabase oscilar, o Render reiniciaria o container à toa
// (e derrubaria quem está usando), sem resolver nada. Não abre sessão nem
// carrega php/conexao.php.
//
// saude.php?banco=1 (só para o monitor de "fora do ar", ex.: UptimeRobot
// procurando "ok"): também faz um SELECT 1 no banco, com 5 s de limite, e
// responde 503 "banco fora" se ele não responder. Antes, com o Supabase
// pausado ou o pool lotado, o site inteiro mostrava "Serviço indisponível"
// e o monitor continuava verde.
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if (!empty($_GET['banco'])) {
    $host = trim((string) getenv('DB_HOST'));
    $ok = false;
    if ($host !== '') {
        $dsn = 'pgsql:host=' . $host . ';port=' . (int) (getenv('DB_PORT') ?: 5432) . ';dbname=' . trim((string) (getenv('DB_NAME') ?: 'postgres'))
            . (stripos($host, 'supabase.co') !== false ? ';sslmode=require' : '');
        try {
            // Mesma conexão persistente do app (mesmo DSN e opções): não abre
            // outra no pooler a cada verificação.
            $pdo = new PDO($dsn, trim((string) getenv('DB_USER')), trim((string) getenv('DB_PASS')), array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_TIMEOUT => 5,
            ));
            $res = $pdo->query('SELECT 1');
            $ok = $res && (int) $res->fetchColumn() === 1;
        } catch (\Throwable $e) {
            $ok = false;
        }
    }
    if (!$ok) {
        http_response_code(503);
        echo 'banco fora';
        exit;
    }
}

echo 'ok';
