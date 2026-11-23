<?php
include("../conexao.php");
require_once __DIR__ . '/includes/cursos_helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Login, inatividade, revogação, Termo e modo "só assinantes" (JSON 401/403).
auth_require_ajax();
if (!isset($_SESSION['Usuario_logado'])) {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'erro' => 'Não autenticado.'));
    exit;
}

$fiel_id = auth_resolve_fiel_id();
if ($fiel_id <= 0) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Usuário inválido.'));
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true);
if (!is_array($dados)) {
    $dados = $_POST;
}

// Sem token, qualquer site aberto no navegador do fiel conseguia marcar
// aulas como concluídas por ele.
if (!csrf_validar($dados['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Sessão expirada. Atualize a página.'));
    exit;
}

$conteudo_id = isset($dados['conteudo_id']) ? (int) $dados['conteudo_id'] : 0;
$segundos_assistidos = isset($dados['segundos_assistidos']) ? (int) $dados['segundos_assistidos'] : 0;
$concluido = !empty($dados['concluido']);
// Ponto do vídeo onde o fiel está agora (para voltar dele na próxima vez).
$posicao_segundos = isset($dados['posicao_segundos']) ? max(0, (int) $dados['posicao_segundos']) : null;

if ($conteudo_id <= 0) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'erro' => 'Conteúdo inválido.'));
    exit;
}

$sql_conteudo = "
    SELECT ct.IDC_ID, ct.IDCT_DURACAO_SEGUNDOS, ct.IDCT_PUBLICADO, c.IDC_STATUS
    FROM ID_CONTENT ct
    LEFT JOIN ID_CURSO c ON c.IDC_ID = ct.IDC_ID
    WHERE ct.IDCT_ID = $conteudo_id
    LIMIT 1
";
$res_conteudo = mysqli_query($conn, $sql_conteudo);
$conteudo = $res_conteudo ? mysqli_fetch_assoc($res_conteudo) : null;

if (!$conteudo) {
    http_response_code(404);
    echo json_encode(array('ok' => false, 'erro' => 'Conteúdo não encontrado.'));
    exit;
}

// Mesma regra de assistir_video.php (video_conteudo_acessivel): fiel comum
// não registra progresso em conteúdo/curso não publicado nem, com o bloqueio
// de assinatura ligado, em curso pago sem assinatura. Antes só a publicação
// era conferida: um fiel sem assinatura marcava aulas pagas como concluídas
// (matrícula, desafios e medalhas) sem nunca ter acesso a elas.
if (!video_conteudo_acessivel($conn, $conteudo_id)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erro' => 'Conteúdo não disponível.'));
    exit;
}

// Avanço maior que o tempo real desde a abertura da aula não vale (ver
// aula_progresso_limite() em cursos_helpers.php). Página não aberta nesta
// sessão: nada a registrar.
$limite_segundos = aula_progresso_limite($conteudo_id);
if ($limite_segundos === null) {
    http_response_code(409);
    echo json_encode(array('ok' => false, 'erro' => 'Atualize a página da aula.'));
    exit;
}
if ($segundos_assistidos > $limite_segundos) {
    $segundos_assistidos = $limite_segundos;
}

// A duração vem do upload do admin (admin_conteudos.php). Para aulas antigas,
// sem duração gravada, só o admin grava a que o player manda, ao abrir a
// aula (e corrige uma diferente da real). Limites: entre 10 s e 6 h, e nunca
// menor que o trecho já visto. A do fiel não é gravada: com ela, uma
// duração forjada de 10 s concluía a aula (e o certificado) em segundos.
// Ela só serve para mostrar o % enquanto a aula não tem duração.
$eh_admin = ($_SESSION['Usuario_tipo'] ?? '') === 'admin';
$duracao_total = (int) ($conteudo['IDCT_DURACAO_SEGUNDOS'] ?? 0);
$duracao_cliente = isset($dados['duracao_segundos']) ? (int) $dados['duracao_segundos'] : 0;
$duracao_valida = $duracao_cliente >= 10 && $duracao_cliente <= 6 * 3600 && $duracao_cliente >= $segundos_assistidos;
$duracao_so_exibicao = 0;
// Gravar a duração só para quem cuida das aulas: qualquer tipo de admin
// regravava 10 s numa aula e a concluía na hora.
if ($duracao_valida && $eh_admin && ($duracao_total <= 0 || abs($duracao_cliente - $duracao_total) > 5)
    && (admin_pode_acessar('conteudos') || admin_pode_acessar('cursos'))) {
    mysqli_query($conn, "UPDATE ID_CONTENT SET IDCT_DURACAO_SEGUNDOS = $duracao_cliente WHERE IDCT_ID = $conteudo_id");
    $duracao_total = $duracao_cliente;
} elseif ($duracao_valida && $duracao_total <= 0) {
    $duracao_so_exibicao = $duracao_cliente;
}

// Relógio do fiel (seção 87): o avanço desta aula além do ponto já gravado
// gasta um saldo único do fiel, que cresce com o tempo real assistido em
// qualquer aula ou sessão. Várias aulas/sessões em paralelo não somam mais.
// Matrícula, % do curso e ponto já gravado numa consulta só (este endpoint
// roda a cada 30 s para cada pessoa assistindo).
$ja_gravado = 0;
$matricula = null;
$curso_id = (int) $conteudo['IDC_ID'];
if ($curso_id > 0) {
    $res_matricula = db_query($conn, "
        SELECT m.IDMATR_ID, m.IDMATR_PERCENTUAL, p.IDPR_TEMPO_ASSISTIDO_SEGUNDOS AS gravado
        FROM ID_MATRICULA m
        LEFT JOIN ID_PROGRESSO p ON p.IDMATR_ID = m.IDMATR_ID AND p.IDCT_ID = ?
        WHERE m.IDF_ID = ? AND m.IDC_ID = ?
        LIMIT 1
    ", array($conteudo_id, $fiel_id, $curso_id));
    $matricula = $res_matricula ? mysqli_fetch_assoc($res_matricula) : null;
    $ja_gravado = (int) ($matricula['gravado'] ?? 0);
}
$relogio = relogio_assistir($conn, $fiel_id, max(0, $segundos_assistidos - $ja_gravado));
if ($relogio !== null && $segundos_assistidos > $ja_gravado) {
    $segundos_assistidos = $ja_gravado + $relogio['liberado'];
}

// Meta diária (seção 85): minutos assistidos hoje, inclusive em vídeo avulso.
// O player mostra "Meta de hoje cumprida!" quando 'cumpriu_agora' vem true.
$meta = meta_diaria_somar($conn, $fiel_id, $conteudo_id, $relogio);

if ($curso_id <= 0) {
    // Conteúdo avulso (sem curso vinculado): nada para acompanhar.
    echo json_encode(array('ok' => true, 'percentual' => null, 'meta' => $meta));
    exit;
}

// O player só manda "concluído" no fim do vídeo, quando o ponto mais longe
// assistido já está perto da duração. Um "concluído" com bem menos que isso
// veio de fora do player: vale só como progresso. Sem duração gravada não
// há como conferir: o fiel conclui depois que um admin abrir a aula.
if ($concluido && ($duracao_total > 0 ? $segundos_assistidos < $duracao_total * 0.8 : !$eh_admin)) {
    $concluido = false;
}
$duracao_percentual = $duracao_total > 0 ? $duracao_total : $duracao_so_exibicao;
$percentual_aula = $concluido ? 100 : ($duracao_percentual > 0 ? min(99, round(($segundos_assistidos / $duracao_percentual) * 100, 2)) : 0);

$matricula_id = $matricula ? (int) $matricula['IDMATR_ID'] : curso_obter_ou_criar_matricula($conn, $fiel_id, $curso_id);
if ($matricula_id <= 0) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'erro' => 'Não foi possível registrar a matrícula.'));
    exit;
}

// A posição (onde voltar na próxima vez) vai no mesmo upsert do progresso.
curso_registrar_progresso_conteudo($conn, $matricula_id, $conteudo_id, $percentual_aula, $concluido, $segundos_assistidos, $posicao_segundos);

// Histórico: uma gravação a cada ~2 min por aula (ou ao concluir), em vez
// de a cada envio. O "Histórico" mostra a aula do dia e o tempo; 2 min de
// atraso no tempo não mudam nada para quem olha.
$historico_em = $_SESSION['historico_gravado_em'][$conteudo_id] ?? 0;
if ($concluido || time() - (int) $historico_em >= 120) {
    curso_registrar_evento_historico($conn, $fiel_id, $curso_id, $conteudo_id, $segundos_assistidos, $concluido);
    $_SESSION['historico_gravado_em'] = array($conteudo_id => time());
}

// O % do curso só muda quando uma aula vira concluída: só então recalcula
// (4 consultas e a escrita na matrícula). Nos outros envios devolve o que
// já está gravado.
$percentual_curso = ($concluido || !$matricula) ? curso_calcular_progresso($conn, $matricula_id) : (float) $matricula['IDMATR_PERCENTUAL'];
if ($concluido) {
    desafios_verificar($conn, $fiel_id); // T31: desafios de aulas
}

echo json_encode(array('ok' => true, 'percentual' => $percentual_curso, 'meta' => $meta));
