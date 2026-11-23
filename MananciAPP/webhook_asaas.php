<?php
// Webhook do Asaas (ver docs/interno/assinaturas/assinaturas_asaas.md, seção 4.3). URL a
// cadastrar no painel do Asaas (Integrações > Webhooks):
// https://<dominio>/webhook_asaas.php, com o "token de autenticação" igual
// a ASAAS_WEBHOOK_TOKEN.
//
// Códigos de resposta importam: o Asaas reenvia enquanto não receber 200 e,
// depois de 15 falhas seguidas, PAUSA a fila inteira até alguém reativar no
// painel. Por isso só devolve erro (500) em falha transitória — banco ou
// API fora do ar —, quando reenviar resolve. Evento desconhecido ou de
// cobrança que não é nossa: registra e responde 200.
require __DIR__ . '/php/conexao.php';

header('Content-Type: application/json; charset=utf-8');

function webhook_asaas_responder($codigo, $mensagem) {
    http_response_code($codigo);
    echo json_encode(array('ok' => $codigo === 200, 'mensagem' => $mensagem));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    webhook_asaas_responder(405, 'Use POST.');
}

$token_esperado = trim((string) ASAAS_WEBHOOK_TOKEN);
$token_recebido = (string) ($_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? '');
if ($token_esperado === '' || !hash_equals($token_esperado, $token_recebido)) {
    webhook_asaas_responder(401, 'Token inválido.');
}

$corpo = (string) file_get_contents('php://input');
$evento = json_decode($corpo, true);
if (!is_array($evento) || empty($evento['event'])) {
    webhook_asaas_responder(400, 'Corpo inválido.');
}

if (!$conn) {
    webhook_asaas_responder(500, 'Banco indisponível.');
}

// O Asaas manda um id único por evento; sem ele, o próprio corpo serve de
// chave (o mesmo reenvio chega byte a byte igual).
$evento_id = (string) ($evento['id'] ?? ('sha1_' . sha1($corpo)));
// Payload regravado a partir do JSON já decodificado, sem caractere nulo
// (\u0000) e com UTF-8 inválido substituído: o jsonb do Postgres recusa os
// dois. Antes, um evento assim dava erro 500 em todo reenvio — e depois de
// 15 falhas seguidas o Asaas pausa a fila inteira de webhooks.
$payload_limpo = str_replace('\u0000', '', (string) json_encode($evento, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
$payload_limpo = $payload_limpo !== '' ? $payload_limpo : '{}';

// Evento já processado: responde sem nem consultar a API.
$res_visto = db_query($conn, "SELECT 1 FROM id_asaas_evento WHERE idae_evento_id = ? LIMIT 1", array(substr($evento_id, 0, 100)));
if ($res_visto && mysqli_num_rows($res_visto) > 0) {
    webhook_asaas_responder(200, 'Recebido.');
}

// A consulta à API do Asaas (até 15 s) fica FORA da transação: dentro dela
// a conexão ficava parada, segurando as linhas do banco.
try {
    $busca = asaas_evento_buscar($evento);
} catch (\Throwable $e) {
    error_log('webhook_asaas ' . $evento_id . ': ' . $e->getMessage());
    site_alerta_erro('error', 'Webhook do Asaas: a API não respondeu (' . mb_substr($e->getMessage(), 0, 200) . ')', 'WebhookAsaas');
    webhook_asaas_responder(500, 'Falha temporária.');
}

// Registro do evento e gravação na MESMA transação: se a gravação falhar,
// o registro também é desfeito e o reenvio do Asaas é processado de novo
// (em vez de ser descartado como "já visto").
if ($conn->inTransaction()) {
    $conn->rollBack();
}
$conn->beginTransaction();

try {
    // Consulta preparada (db_query): mesmo tratamento de erro do asaas_sql().
    $res = db_query($conn, "
        INSERT INTO id_asaas_evento (idae_evento_id, idae_tipo, idae_payload)
        VALUES (?, ?, CAST(? AS jsonb))
        ON CONFLICT (idae_evento_id) DO NOTHING
        RETURNING idae_id
    ", array(substr($evento_id, 0, 100), substr((string) $evento['event'], 0, 60), $payload_limpo));
    if ($res === false) {
        throw new RuntimeException('SQL: ' . mysqli_error($conn));
    }

    if (mysqli_num_rows($res) > 0) {
        asaas_evento_aplicar($conn, $evento, $busca);
    }

    // Com o erro silencioso do PDO, um commit que falha (conexão caiu)
    // devolve false: sem conferir, o Asaas recebia 200 e o pagamento não
    // ficava gravado.
    if (!$conn->commit()) {
        throw new RuntimeException('commit falhou');
    }
} catch (\Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('webhook_asaas ' . $evento_id . ': ' . $e->getMessage());
    // No Sentry também: depois de 15 falhas seguidas o Asaas pausa a fila e
    // ninguém recebe o acesso depois de pagar. Antes só ia para o log.
    site_alerta_erro('error', 'Webhook do Asaas falhou (evento ' . substr((string) ($evento['event'] ?? ''), 0, 60) . '): ' . mb_substr($e->getMessage(), 0, 300), 'WebhookAsaas');
    webhook_asaas_responder(500, 'Falha temporária.');
}

webhook_asaas_responder(200, 'Recebido.');
