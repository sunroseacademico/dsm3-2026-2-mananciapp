<?php
// Lembrete diário (T23): manda o push "Seu momento com Deus" para quem
// escolheu um horário em Perfil > Preferências de conteúdo. Ver
// lembretes_diarios_enviar() em php/conexao.php e a seção 56 do
// database/bancosupabase.md.
//
// Precisa de um agendador chamando esta URL a cada 15 minutos (o Render não
// roda tarefa agendada no plano web): um Cron Job do Render ou um serviço
// gratuito como o cron-job.org.
//   https://<dominio>/lembrete_diario.php
//   cabeçalho: X-Cron-Chave: <LEMBRETE_CRON_CHAVE>
// Só pelo cabeçalho: o ?chave= na URL ficava gravado nos logs de acesso do
// Render e do agendador. Sem LEMBRETE_CRON_CHAVE configurada, responde 503 e
// não manda nada.
require __DIR__ . '/php/conexao.php';

header('Content-Type: application/json; charset=utf-8');

function lembrete_diario_responder($codigo, $dados) {
    http_response_code($codigo);
    echo json_encode($dados);
    exit;
}

$chave_esperada = trim((string) LEMBRETE_CRON_CHAVE);
if ($chave_esperada === '') {
    lembrete_diario_responder(503, array('ok' => false, 'erro' => 'LEMBRETE_CRON_CHAVE não configurada.'));
}
$chave_recebida = (string) ($_SERVER['HTTP_X_CRON_CHAVE'] ?? '');
if (!hash_equals($chave_esperada, $chave_recebida)) {
    lembrete_diario_responder(401, array('ok' => false, 'erro' => 'Chave inválida.'));
}
if (!$conn) {
    lembrete_diario_responder(500, array('ok' => false, 'erro' => 'Banco indisponível.'));
}

// Até 300 pushes por chamada, cada um com seu tempo de rede: o limite
// padrão de 60 s (.htaccess) não basta.
set_time_limit(300);
$stats = lembretes_diarios_enviar($conn);
// Aproveita o agendador para a limpeza diária (sessões velhas, tentativas de
// login, notificações lidas antigas — ver site_manutencao_limpar()). Roda
// uma vez por dia; nas outras chamadas devolve vazio.
$limpeza = site_manutencao_limpar($conn);
if ($limpeza) {
    $stats['limpeza'] = $limpeza;
}

// Última execução: a equipe vê no Dashboard do admin quando o agendador
// parou de chamar (antes ninguém percebia).
site_config_salvar($conn, 'rotina_lembrete_ultima', date('c'));

// Falha de verdade responde 500, para o agendador (cron-job.org) avisar:
// consulta que falhou, ou ninguém recebeu por erro da OneSignal. Antes
// tudo saía 200 "ok".
$falhou = !empty($stats['erro'])
    || ($stats['falhas'] > 0 && $stats['enviados'] === 0)
    || !empty($limpeza['falhas']);
if ($falhou) {
    site_alerta_erro('error', 'Lembrete diário/limpeza com falha: ' . json_encode($stats), 'RotinaFalhou');
    lembrete_diario_responder(500, array('ok' => false) + $stats);
}
lembrete_diario_responder(200, array('ok' => true) + $stats);
