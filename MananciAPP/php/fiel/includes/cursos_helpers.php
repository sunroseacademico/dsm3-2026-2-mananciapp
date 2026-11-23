<?php
// Camada de dados para a area do fiel em formato "streaming de cursos".
// Funcoes puras: recebem $conn + IDs, devolvem arrays/booleans. Sem HTML aqui.
//
// Convencoes seguidas (ja usadas em conexao.php/perfil.php):
//  - auth_resolve_fiel_id() para descobrir o IDF_ID correto (admin logado inclusive)
//  - tabela_tem_coluna($conn, $tabela, $coluna) para colunas novas (IDC_PROFESSOR_NOME,
//    IDC_NIVEL, IDC_DESTAQUE) que so existem depois da migracao rodar
//  - site_tabela_existe() (definida abaixo) para as tabelas novas (ID_FAVORITO,
//    ID_HISTORICO_VISUALIZACAO), tambem opcionais ate a migracao rodar
//
// Status usados (identicos nos dois schemas, simples e robusto):
//  ID_MATRICULA.IDMATR_STATUS  : ATIVA | PAUSADA | CONCLUIDA | CANCELADA
//  ID_PROGRESSO.IDPR_STATUS    : NAO_INICIADO | EM_ANDAMENTO | CONCLUIDO

if (!function_exists('site_tabela_existe')) {
    function site_tabela_existe($conn, $tabela) {
        static $cache = array();
        if (isset($cache[$tabela])) {
            return $cache[$tabela];
        }
        // Sem aspas na criação das tabelas no Postgres = tudo guardado em
        // minúsculo, igual à convenção das queries do resto do código.
        $resultado = db_query($conn, "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?", array(strtolower($tabela)));
        $cache[$tabela] = ($resultado && mysqli_num_rows($resultado) > 0);
        return $cache[$tabela];
    }
}

/** Busca (sem criar) a matricula do fiel num curso. Retorna array ou null. */
function curso_obter_matricula($conn, $fiel_id, $curso_id) {
    $fiel_id = (int) $fiel_id;
    $curso_id = (int) $curso_id;
    if ($fiel_id <= 0 || $curso_id <= 0) {
        return null;
    }
    $sql = "SELECT * FROM ID_MATRICULA WHERE IDF_ID = $fiel_id AND IDC_ID = $curso_id LIMIT 1";
    $res = mysqli_query($conn, $sql);
    return $res ? mysqli_fetch_assoc($res) : null;
}

/**
 * Garante que existe uma matricula do fiel no curso, criando-a se necessario.
 * Retorna o IDMATR_ID ou 0 em caso de falha.
 */
function curso_obter_ou_criar_matricula($conn, $fiel_id, $curso_id) {
    $fiel_id = (int) $fiel_id;
    $curso_id = (int) $curso_id;
    if ($fiel_id <= 0 || $curso_id <= 0) {
        return 0;
    }

    $sql = "SELECT IDMATR_ID FROM ID_MATRICULA WHERE IDF_ID = $fiel_id AND IDC_ID = $curso_id LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return (int) $row['IDMATR_ID'];
    }

    // ON CONFLICT: dois envios simultâneos do player (o de 30 s e o do fim da
    // aula) chegavam juntos aqui, os dois viam "sem matrícula" e o segundo
    // INSERT batia no UNIQUE (IDF_ID, IDC_ID) — erro 500 no progresso. O
    // DO UPDATE "vazio" existe só para o RETURNING devolver o id existente.
    $sql_insert = "
        INSERT INTO ID_MATRICULA (IDF_ID, IDC_ID, IDMATR_STATUS, IDMATR_PERCENTUAL, IDMATR_INICIO_EM)
        VALUES ($fiel_id, $curso_id, 'ATIVA', 0.00, NOW())
        ON CONFLICT (IDF_ID, IDC_ID) DO UPDATE SET IDF_ID = EXCLUDED.IDF_ID
        RETURNING IDMATR_ID
    ";
    $res_insert = mysqli_query($conn, $sql_insert);
    if ($res_insert && $row = mysqli_fetch_assoc($res_insert)) {
        return (int) $row['IDMATR_ID'];
    }

    return 0;
}

/**
 * Cria/atualiza o progresso de um conteudo (aula) dentro de uma matricula.
 */
// $posicao (opcional): onde o fiel está no vídeo, para voltar dali (seção
// 46). Vai no mesmo upsert: antes era um UPDATE a mais a cada envio.
function curso_registrar_progresso_conteudo($conn, $matricula_id, $conteudo_id, $percentual, $concluido, $segundos_assistidos = 0, $posicao = null) {
    $matricula_id = (int) $matricula_id;
    $conteudo_id = (int) $conteudo_id;
    if ($matricula_id <= 0 || $conteudo_id <= 0) {
        return false;
    }

    $percentual = max(0, min(100, (float) $percentual));
    $status = $concluido ? 'CONCLUIDO' : ($percentual > 0 ? 'EM_ANDAMENTO' : 'NAO_INICIADO');
    $segundos = max(0, (int) $segundos_assistidos);
    $com_posicao = $posicao !== null && tabela_tem_coluna($conn, 'ID_PROGRESSO', 'IDPR_POSICAO_SEGUNDOS');
    $posicao = max(0, (int) $posicao);

    // Precisa de UNIQUE (IDMATR_ID, IDCT_ID) em ID_PROGRESSO pro ON CONFLICT
    // funcionar (adicionada no schema Postgres — o schema/dump antigo em MySQL
    // não deixava isso explícito, mas é exatamente o que esse upsert pressupõe:
    // 1 linha de progresso por par matrícula+conteúdo).
    $sql = "
        INSERT INTO ID_PROGRESSO (IDMATR_ID, IDCT_ID, IDPR_STATUS, IDPR_PERCENTUAL, IDPR_TEMPO_ASSISTIDO_SEGUNDOS, IDPR_ULTIMO_ACESSO_EM" . ($com_posicao ? ", IDPR_POSICAO_SEGUNDOS" : "") . ")
        VALUES ($matricula_id, $conteudo_id, '$status', $percentual, $segundos, NOW()" . ($com_posicao ? ", $posicao" : "") . ")
        ON CONFLICT (IDMATR_ID, IDCT_ID) DO UPDATE SET
            -- Aula concluída continua concluída: rever a aula mandava
            -- EM_ANDAMENTO e derrubava o curso de 100% (e a matrícula de
            -- CONCLUIDA para ATIVA).
            IDPR_STATUS = CASE WHEN ID_PROGRESSO.IDPR_STATUS = 'CONCLUIDO' THEN 'CONCLUIDO' ELSE EXCLUDED.IDPR_STATUS END,
            IDPR_PERCENTUAL = GREATEST(ID_PROGRESSO.IDPR_PERCENTUAL, EXCLUDED.IDPR_PERCENTUAL),
            -- O player manda o ponto mais longe já assistido (não o trecho
            -- desde o último envio): somar a cada 30 s inflava o tempo.
            IDPR_TEMPO_ASSISTIDO_SEGUNDOS = GREATEST(ID_PROGRESSO.IDPR_TEMPO_ASSISTIDO_SEGUNDOS, EXCLUDED.IDPR_TEMPO_ASSISTIDO_SEGUNDOS),
            IDPR_ULTIMO_ACESSO_EM = NOW()" . ($com_posicao ? ",
            IDPR_POSICAO_SEGUNDOS = EXCLUDED.IDPR_POSICAO_SEGUNDOS" : "") . "
    ";

    return (bool) mysqli_query($conn, $sql);
}

/**
 * Registra 1 evento no historico de visualizacao (se a tabela ja existir).
 */
function curso_registrar_evento_historico($conn, $fiel_id, $curso_id, $conteudo_id, $segundos_assistidos, $concluido) {
    if (!site_tabela_existe($conn, 'ID_HISTORICO_VISUALIZACAO')) {
        return false;
    }

    $fiel_id = (int) $fiel_id;
    $curso_id = (int) $curso_id;
    $conteudo_id = (int) $conteudo_id;
    $segundos = max(0, (int) $segundos_assistidos);
    $concluido_int = $concluido ? 1 : 0;

    if ($fiel_id <= 0 || $curso_id <= 0 || $conteudo_id <= 0) {
        return false;
    }

    // Uma linha por aula por dia (horário de Brasília): o player chama isto
    // a cada 30 s, e antes cada chamada era uma linha nova — o "Histórico"
    // (200 linhas) cobria só ~100 minutos, com a mesma aula repetida
    // dezenas de vezes, e a tabela crescia ~120 linhas por hora por fiel.
    // Atualiza a linha de hoje (tempo e "concluído" só sobem; a hora vai
    // para agora, para a aula subir no topo) ou cria uma.
    $sql_hoje = "
        UPDATE ID_HISTORICO_VISUALIZACAO SET
            IDHV_TEMPO_ASSISTIDO_SEGUNDOS = GREATEST(IDHV_TEMPO_ASSISTIDO_SEGUNDOS, $segundos),
            IDHV_CONCLUIDO = GREATEST(IDHV_CONCLUIDO, $concluido_int),
            IDHV_CRIADO_EM = NOW()
        WHERE IDHV_ID = (
            SELECT IDHV_ID FROM ID_HISTORICO_VISUALIZACAO
            WHERE IDF_ID = $fiel_id AND IDCT_ID = $conteudo_id
              AND (IDHV_CRIADO_EM AT TIME ZONE 'America/Sao_Paulo')::date = (NOW() AT TIME ZONE 'America/Sao_Paulo')::date
            ORDER BY IDHV_CRIADO_EM DESC
            LIMIT 1
        )
        RETURNING IDHV_ID
    ";
    $res_hoje = mysqli_query($conn, $sql_hoje);
    if ($res_hoje && mysqli_num_rows($res_hoje) > 0) {
        return true;
    }

    $sql = "INSERT INTO ID_HISTORICO_VISUALIZACAO (IDF_ID, IDC_ID, IDCT_ID, IDHV_TEMPO_ASSISTIDO_SEGUNDOS, IDHV_CONCLUIDO) VALUES ($fiel_id, $curso_id, $conteudo_id, $segundos, $concluido_int)";
    return (bool) mysqli_query($conn, $sql);
}

/**
 * Recalcula o percentual geral do curso (aulas concluidas / total de aulas) e
 * atualiza ID_MATRICULA. Marca a matricula como CONCLUIDA quando chega a 100%.
 */
function curso_calcular_progresso($conn, $matricula_id) {
    $matricula_id = (int) $matricula_id;
    if ($matricula_id <= 0) {
        return 0.0;
    }

    $sql_curso = "SELECT IDC_ID, IDF_ID FROM ID_MATRICULA WHERE IDMATR_ID = $matricula_id LIMIT 1";
    $res_curso = mysqli_query($conn, $sql_curso);
    $row_curso = $res_curso ? mysqli_fetch_assoc($res_curso) : null;
    if (!$row_curso) {
        return 0.0;
    }
    $curso_id = (int) $row_curso['IDC_ID'];
    $fiel_id = (int) $row_curso['IDF_ID'];

    $total = curso_contar_aulas($conn, $curso_id);
    if ($total <= 0) {
        return 0.0;
    }

    $sql_concluidas = "
        SELECT COUNT(*) AS total FROM ID_PROGRESSO
        WHERE IDMATR_ID = $matricula_id AND IDPR_STATUS = 'CONCLUIDO'
    ";
    $res_concluidas = mysqli_query($conn, $sql_concluidas);
    $concluidas = 0;
    if ($res_concluidas && $row = mysqli_fetch_assoc($res_concluidas)) {
        $concluidas = (int) $row['total'];
    }

    $percentual = min(100, round(($concluidas / $total) * 100, 2));
    $status = $percentual >= 100 ? 'CONCLUIDA' : 'ATIVA';
    // COALESCE: a data de conclusão é a da PRIMEIRA vez — antes era
    // regravada a cada envio do player enquanto o curso estava em 100%.
    $conclusao_sql = $percentual >= 100 ? "IDMATR_CONCLUSAO_EM = COALESCE(IDMATR_CONCLUSAO_EM, NOW())," : "";

    mysqli_query($conn, "
        UPDATE ID_MATRICULA
        SET IDMATR_PERCENTUAL = $percentual, $conclusao_sql IDMATR_STATUS = '$status'
        WHERE IDMATR_ID = $matricula_id
        LIMIT 1
    ");

    if ($percentual >= 100) {
        certificado_curso_conceder($conn, $fiel_id, $curso_id);
    }

    return $percentual;
}

function curso_contar_aulas_concluidas($conn, $matricula_id) {
    $matricula_id = (int) $matricula_id;
    $sql = "SELECT COUNT(*) AS total FROM ID_PROGRESSO WHERE IDMATR_ID = $matricula_id AND IDPR_STATUS = 'CONCLUIDO'";
    $res = mysqli_query($conn, $sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return (int) $row['total'];
    }
    return 0;
}

function curso_contar_aulas($conn, $curso_id) {
    $curso_id = (int) $curso_id;
    $sql = "SELECT COUNT(*) AS total FROM ID_CONTENT WHERE IDC_ID = $curso_id AND LOWER(IDCT_TIPO) = 'video' AND IDCT_PUBLICADO = 1";
    $res = mysqli_query($conn, $sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return (int) $row['total'];
    }
    return 0;
}

function curso_duracao_total_min($conn, $curso_id) {
    $curso_id = (int) $curso_id;
    $sql = "SELECT COALESCE(SUM(IDCT_DURACAO_SEGUNDOS), 0) AS total FROM ID_CONTENT WHERE IDC_ID = $curso_id AND LOWER(IDCT_TIPO) = 'video' AND IDCT_PUBLICADO = 1";
    $res = mysqli_query($conn, $sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return (int) round(((int) $row['total']) / 60);
    }
    return 0;
}

/** Lista de aulas (ID_CONTENT do tipo video) de um curso, em ordem. */
// Próxima aula do fiel no curso: a primeira ainda não concluída; curso todo
// concluído (ou sem fiel), a primeira. null sem aula. Mesma regra do botão
// de curso.php, usada também no "Assistir agora" do carrossel da Home.
function curso_proxima_aula_id($conn, $curso_id, $fiel_id = null) {
    $aulas = curso_lista_aulas($conn, $curso_id);
    if (!$aulas) {
        return null;
    }
    $matricula = $fiel_id ? curso_obter_matricula($conn, $fiel_id, $curso_id) : null;
    $mapa = $matricula ? curso_mapa_progresso($conn, (int) $matricula['IDMATR_ID']) : array();
    foreach ($aulas as $aula) {
        if (($mapa[(int) $aula['IDCT_ID']]['IDPR_STATUS'] ?? 'NAO_INICIADO') !== 'CONCLUIDO') {
            return (int) $aula['IDCT_ID'];
        }
    }
    return (int) $aulas[0]['IDCT_ID'];
}

function curso_lista_aulas($conn, $curso_id) {
    $curso_id = (int) $curso_id;
    $aulas = array();
    $sql = "SELECT * FROM ID_CONTENT WHERE IDC_ID = $curso_id AND LOWER(IDCT_TIPO) = 'video' AND IDCT_PUBLICADO = 1 ORDER BY IDCT_ORDEM ASC, IDCT_ID ASC";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $aulas[] = $row;
        }
    }
    return $aulas;
}

/** Mapa IDCT_ID => status/percentual de progresso do fiel numa matricula. */
// Progresso conferido pelo relógio do servidor. O player manda o ponto mais
// longe do vídeo a que o fiel chegou, mas isso vem do navegador: pelo
// console dava para mandar "assisti 99999 s, concluído" e ganhar a aula, o
// certificado, medalhas e desafios sem ver nada. Agora o avanço aceito é no
// máximo o tempo real desde que a página da aula foi aberta, na velocidade
// mais alta do player (com folga), somado ao que já estava gravado. Pular
// para o fim com a barra também não conclui mais a aula.
if (!defined('AULA_PROGRESSO_FOLGA_SEGUNDOS')) {
    define('AULA_PROGRESSO_FOLGA_SEGUNDOS', 60);
}

function aula_registrar_abertura($conteudo_id, $ja_assistido) {
    $conteudo_id = (int) $conteudo_id;
    if ($conteudo_id <= 0) {
        return;
    }
    $abertas = isset($_SESSION['aulas_abertas']) && is_array($_SESSION['aulas_abertas']) ? $_SESSION['aulas_abertas'] : array();
    unset($abertas[$conteudo_id]);
    $abertas[$conteudo_id] = array('em' => time(), 'base' => max(0, (int) $ja_assistido));
    // Só as últimas aulas abertas: a sessão não cresce sem fim.
    $_SESSION['aulas_abertas'] = array_slice($abertas, -5, null, true);
}

// Maior "segundos assistidos" aceitável agora para a aula, ou null se a
// página da aula não foi aberta nesta sessão.
function aula_progresso_limite($conteudo_id) {
    $aberta = $_SESSION['aulas_abertas'][(int) $conteudo_id] ?? null;
    if (!is_array($aberta)) {
        return null;
    }
    $velocidade_max = max(VIDEO_VELOCIDADES) + 0.5;
    $passados = max(0, time() - (int) $aberta['em']);
    return (int) $aberta['base'] + (int) ceil($passados * $velocidade_max) + AULA_PROGRESSO_FOLGA_SEGUNDOS;
}

function curso_mapa_progresso($conn, $matricula_id) {
    $matricula_id = (int) $matricula_id;
    $mapa = array();
    if ($matricula_id <= 0) {
        return $mapa;
    }
    $sql = "SELECT IDCT_ID, IDPR_STATUS, IDPR_PERCENTUAL FROM ID_PROGRESSO WHERE IDMATR_ID = $matricula_id";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $mapa[(int) $row['IDCT_ID']] = $row;
        }
    }
    return $mapa;
}

/**
 * % assistido de uma aula começada e não concluída (para a barrinha da lista
 * de aulas), a partir do mapa de curso_mapa_progresso(). Retorna null quando
 * não há barra para mostrar: aula não iniciada, sem % ou já concluída (essa
 * já aparece com o ✓).
 */
function curso_percentual_aula_em_andamento($mapa_progresso, $conteudo_id) {
    $linha = $mapa_progresso[(int) $conteudo_id] ?? null;
    if (!$linha || ($linha['IDPR_STATUS'] ?? '') === 'CONCLUIDO') {
        return null;
    }
    $percentual = (float) ($linha['IDPR_PERCENTUAL'] ?? 0);
    return ($percentual > 0 && $percentual < 100) ? $percentual : null;
}

/**
 * Quantas pessoas já assistiram a aula (cabeçalho do player). Conta fiéis
 * distintos em ID_PROGRESSO — o histórico não serve, porque grava uma linha
 * a cada envio de progresso (30 s) e inflaria o número.
 */
function conteudo_total_visualizacoes($conn, $conteudo_id) {
    $conteudo_id = (int) $conteudo_id;
    if ($conteudo_id <= 0) {
        return 0;
    }
    $res = mysqli_query($conn, "
        SELECT COUNT(DISTINCT m.IDF_ID) AS total
        FROM ID_PROGRESSO p
        JOIN ID_MATRICULA m ON m.IDMATR_ID = p.IDMATR_ID
        WHERE p.IDCT_ID = $conteudo_id
    ");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
    return $linha ? (int) $linha['total'] : 0;
}

// Selo "Em alta": quantas pessoas distintas precisam ter assistido a um
// curso/aula nos últimos 7 dias. Ajustado no admin (Cursos), guardado em
// id_configuracao.em_alta_minimo; 0 desliga o selo.
define('EM_ALTA_MINIMO_PADRAO', 5);
define('EM_ALTA_MINIMO_MAXIMO', 100000);

function em_alta_minimo() {
    $minimo = (int) site_config('em_alta_minimo', (string) EM_ALTA_MINIMO_PADRAO);
    return max(0, min(EM_ALTA_MINIMO_MAXIMO, $minimo));
}

/**
 * Pessoas distintas que assistiram cada curso e cada aula nos últimos 7
 * dias: array('cursos' => [IDC_ID => n], 'aulas' => [IDCT_ID => n]).
 * Usa o último acesso de ID_PROGRESSO (atualizado a cada envio do player);
 * o histórico não serve porque o fiel pode apagá-lo. Uma vez por requisição.
 */
function em_alta_contagens($conn) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    // Duas agregações sobre 7 dias de progresso, iguais para todos: cache
    // compartilhado de 2 min (antes rodavam a cada Home, curso e aula).
    $guardado = site_cache_compartilhado('em_alta_contagens', 120, function () use ($conn) {
        return em_alta_contagens_banco($conn);
    });
    $cache = is_array($guardado) ? $guardado + array('cursos' => array(), 'aulas' => array()) : array('cursos' => array(), 'aulas' => array());
    return $cache;
}

function em_alta_contagens_banco($conn) {
    $cache = array('cursos' => array(), 'aulas' => array());

    // Cursos e aulas numa consulta só (antes eram duas idas ao banco).
    $filtro = "p.IDPR_ULTIMO_ACESSO_EM >= NOW() - INTERVAL '7 days'";
    $res = mysqli_query($conn, "
        SELECT 'cursos' AS tipo, m.IDC_ID AS id, COUNT(DISTINCT m.IDF_ID) AS pessoas
        FROM ID_PROGRESSO p
        JOIN ID_MATRICULA m ON m.IDMATR_ID = p.IDMATR_ID
        WHERE $filtro
        GROUP BY m.IDC_ID
        UNION ALL
        SELECT 'aulas' AS tipo, p.IDCT_ID AS id, COUNT(DISTINCT m.IDF_ID) AS pessoas
        FROM ID_PROGRESSO p
        JOIN ID_MATRICULA m ON m.IDMATR_ID = p.IDMATR_ID
        WHERE $filtro
        GROUP BY p.IDCT_ID
    ");
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $cache[$linha['tipo']][(int) $linha['id']] = (int) $linha['pessoas'];
    }
    return $cache;
}

/** O curso ($tipo = 'cursos') ou a aula ($tipo = 'aulas') está em alta? */
function em_alta($conn, $tipo, $id) {
    $minimo = em_alta_minimo();
    if ($minimo <= 0) {
        return false;
    }
    $contagens = em_alta_contagens($conn);
    return ($contagens[$tipo][(int) $id] ?? 0) >= $minimo;
}

/**
 * % assistido de cada aula para o fiel, para a barra nos cards de vídeo:
 * array(IDCT_ID => percentual). Aula concluída vale 100. Aulas sem
 * progresso ficam de fora.
 */
function videos_percentual_por_fiel($conn, $fiel_id, $conteudo_ids) {
    $fiel_id = (int) $fiel_id;
    $ids = array_filter(array_map('intval', (array) $conteudo_ids));
    if ($fiel_id <= 0 || empty($ids)) {
        return array();
    }
    $lista = implode(',', $ids);
    $sql = "
        SELECT p.IDCT_ID,
               MAX(CASE WHEN p.IDPR_STATUS = 'CONCLUIDO' THEN 100 ELSE p.IDPR_PERCENTUAL END) AS percentual
        FROM ID_PROGRESSO p
        JOIN ID_MATRICULA m ON m.IDMATR_ID = p.IDMATR_ID
        WHERE m.IDF_ID = $fiel_id AND p.IDCT_ID IN ($lista)
        GROUP BY p.IDCT_ID
    ";
    $mapa = array();
    $res = mysqli_query($conn, $sql);
    while ($res && $row = mysqli_fetch_assoc($res)) {
        $percentual = (float) $row['percentual'];
        if ($percentual > 0) {
            $mapa[(int) $row['IDCT_ID']] = min(100, $percentual);
        }
    }
    return $mapa;
}

// $somente_publicado = true faz o fiel comum não conseguir ver curso em
// RASCUNHO/ARQUIVADO nem sabendo o ID de cor (ex.: curso.php) — admin
// continua vendo qualquer status (ex.: admin_editar_curso.php chama com
// false, que é o default, pra poder editar rascunhos).
function curso_obter_por_id($conn, $curso_id, $somente_publicado = false) {
    $curso_id = (int) $curso_id;
    if ($curso_id <= 0) {
        return null;
    }
    $filtro_status = $somente_publicado ? "AND c.IDC_STATUS = 'PUBLICADO'" : "";
    $sql = "
        SELECT c.*, cat.IDCAT_NOME AS categoria_nome
        FROM ID_CURSO c
        LEFT JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = c.IDC_CATEGORIA_ID
        WHERE c.IDC_ID = $curso_id
        $filtro_status
        LIMIT 1
    ";
    $res = mysqli_query($conn, $sql);
    return $res ? mysqli_fetch_assoc($res) : null;
}

function curso_esta_favoritado($conn, $fiel_id, $curso_id) {
    if (!site_tabela_existe($conn, 'ID_FAVORITO')) {
        return false;
    }
    $fiel_id = (int) $fiel_id;
    $curso_id = (int) $curso_id;
    $sql = "SELECT 1 FROM ID_FAVORITO WHERE IDF_ID = $fiel_id AND IDC_ID = $curso_id LIMIT 1";
    $res = mysqli_query($conn, $sql);
    return (bool) ($res && mysqli_num_rows($res) > 0);
}

/** Alterna favorito; retorna o novo estado (true = favoritado). */
function curso_toggle_favorito($conn, $fiel_id, $curso_id) {
    if (!site_tabela_existe($conn, 'ID_FAVORITO')) {
        return false;
    }
    $fiel_id = (int) $fiel_id;
    $curso_id = (int) $curso_id;

    if (curso_esta_favoritado($conn, $fiel_id, $curso_id)) {
        mysqli_query($conn, "DELETE FROM ID_FAVORITO WHERE IDF_ID = $fiel_id AND IDC_ID = $curso_id LIMIT 1");
        return false;
    }

    // "INSERT IGNORE" (MySQL) -> "ON CONFLICT DO NOTHING" (Postgres). Depende
    // de ID_FAVORITO ter UNIQUE (IDF_ID, IDC_ID), senão isso viraria um INSERT
    // simples que falharia (ou duplicaria) em vez de ser idempotente.
    mysqli_query($conn, "INSERT INTO ID_FAVORITO (IDF_ID, IDC_ID) VALUES ($fiel_id, $curso_id) ON CONFLICT (IDF_ID, IDC_ID) DO NOTHING");
    return true;
}

/**
 * Aulas publicadas e duração (min) de TODOS os cursos, numa consulta só:
 * array(IDC_ID => array('aulas' => n, 'duracao_min' => n)). Uma vez por
 * requisição. Antes cada card fazia as suas 2 consultas (contar aulas,
 * somar duração) — com 30 cursos, ~60 idas ao banco remoto só na Home.
 */
function cursos_estatisticas($conn) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    // Igual para todos: cache compartilhado de 2 min (Home, cursos, curso).
    // O admin mexendo no catálogo apaga as chaves "catalogo_publico".
    $cache = site_cache_compartilhado('catalogo_publico_estatisticas', 120, function () use ($conn) {
        $res = mysqli_query($conn, "
            SELECT IDC_ID, COUNT(*) AS aulas, COALESCE(SUM(IDCT_DURACAO_SEGUNDOS), 0) AS segundos
            FROM ID_CONTENT
            WHERE IDC_ID IS NOT NULL AND LOWER(IDCT_TIPO) = 'video' AND IDCT_PUBLICADO = 1
            GROUP BY IDC_ID
        ");
        if (!$res) {
            return false;
        }
        $mapa = array();
        while ($linha = mysqli_fetch_assoc($res)) {
            $mapa[(int) $linha['IDC_ID']] = array(
                'aulas' => (int) $linha['aulas'],
                'duracao_min' => (int) round(((int) $linha['segundos']) / 60),
            );
        }
        return $mapa;
    });
    $cache = is_array($cache) ? $cache : array();
    return $cache;
}

// Linhas de uma consulta do catálogo (iguais para todos), guardadas 2 min no
// cache compartilhado. As filas da Home rodavam a cada abertura; o
// progresso de cada fiel continua sendo montado por cima (curso_montar_card).
// Chave com "catalogo_publico": o admin mexendo nos cursos apaga o cache.
function cursos_linhas_cache($conn, $chave, $sql) {
    $linhas = site_cache_compartilhado('catalogo_publico_' . $chave, 120, function () use ($conn, $sql) {
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return false;
        }
        $lista = array();
        while ($row = mysqli_fetch_assoc($res)) {
            $lista[] = $row;
        }
        return $lista;
    });
    return is_array($linhas) ? $linhas : array();
}

/** Percentual de cada matrícula do fiel: array(IDC_ID => %). Uma consulta por requisição. */
function cursos_percentuais_do_fiel($conn, $fiel_id) {
    static $cache = array();
    $fiel_id = (int) $fiel_id;
    if (isset($cache[$fiel_id])) {
        return $cache[$fiel_id];
    }
    $cache[$fiel_id] = array();
    $res = mysqli_query($conn, "SELECT IDC_ID, IDMATR_PERCENTUAL FROM ID_MATRICULA WHERE IDF_ID = $fiel_id");
    while ($res && $linha = mysqli_fetch_assoc($res)) {
        $cache[$fiel_id][(int) $linha['IDC_ID']] = (float) $linha['IDMATR_PERCENTUAL'];
    }
    return $cache[$fiel_id];
}

/** Constrói o array de dados que php/fiel/includes/card_curso.php espera. */
function curso_montar_card($conn, $curso_row, $fiel_id = null, $progresso = null) {
    // Aulas/duração e matrícula vêm de mapas montados uma vez por requisição
    // (cursos_estatisticas, cursos_percentuais_do_fiel): o card não consulta
    // o banco sozinho.
    $curso_id = (int) ($curso_row['IDC_ID'] ?? 0);

    if ($progresso === null && $fiel_id) {
        $progresso = cursos_percentuais_do_fiel($conn, $fiel_id)[$curso_id] ?? null;
    }

    $estatisticas = cursos_estatisticas($conn)[$curso_id] ?? array('aulas' => 0, 'duracao_min' => 0);
    $total_aulas = $estatisticas['aulas'];
    if ($total_aulas <= 0) {
        // Sem aulas publicadas, nenhum percentual salvo faz sentido (mesmo
        // que exista uma matricula com valor antigo/manual no banco).
        $progresso = null;
    }

    return array(
        'id' => $curso_id,
        'titulo' => $curso_row['IDC_TITULO'] ?? 'Curso',
        'capa_url' => $curso_row['IDC_CAPA_URL'] ?? null,
        'categoria' => $curso_row['categoria_nome'] ?? null,
        'nivel' => $curso_row['IDC_NIVEL'] ?? null,
        'professor' => $curso_row['IDC_PROFESSOR_NOME'] ?? null,
        'aulas' => $total_aulas,
        'duracao_min' => $estatisticas['duracao_min'],
        'progresso' => $progresso,
        'em_alta' => em_alta($conn, 'cursos', $curso_id),
        'link' => 'curso.php?id=' . $curso_id,
    );
}

/** Cursos publicados em que o fiel tem matricula ativa/em andamento. */
function cursos_continuar_assistindo($conn, $fiel_id, $limit = 10) {
    $fiel_id = (int) $fiel_id;
    $limit = (int) $limit;
    $resultado = array();
    if ($fiel_id <= 0) {
        return $resultado;
    }

    $sql = "
        SELECT c.*, cat.IDCAT_NOME AS categoria_nome, m.IDMATR_PERCENTUAL
        FROM ID_MATRICULA m
        JOIN ID_CURSO c ON c.IDC_ID = m.IDC_ID
        LEFT JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = c.IDC_CATEGORIA_ID
        WHERE m.IDF_ID = $fiel_id
          AND m.IDMATR_STATUS IN ('ATIVA', 'PAUSADA')
          AND m.IDMATR_PERCENTUAL > 0 AND m.IDMATR_PERCENTUAL < 100
        ORDER BY m.IDMATR_INICIO_EM DESC
        LIMIT $limit
    ";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            // Sem aulas publicadas, nao ha nada para "continuar assistindo"
            // (pode acontecer com matriculas antigas/manuais ou se o admin
            // despublicar todos os videos do curso depois).
            if ((cursos_estatisticas($conn)[(int) $row['IDC_ID']]['aulas'] ?? 0) <= 0) {
                continue;
            }
            $resultado[] = curso_montar_card($conn, $row, null, (float) $row['IDMATR_PERCENTUAL']);
        }
    }
    return $resultado;
}

function cursos_novos($conn, $limit = 10, $fiel_id = null) {
    $limit = (int) $limit;
    $resultado = array();
    $sql = "
        SELECT c.*, cat.IDCAT_NOME AS categoria_nome
        FROM ID_CURSO c
        LEFT JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = c.IDC_CATEGORIA_ID
        WHERE c.IDC_STATUS = 'PUBLICADO'
        ORDER BY c.IDC_CRIADO_EM DESC
        LIMIT $limit
    ";
    foreach (cursos_linhas_cache($conn, 'novos_' . $limit, $sql) as $row) {
        $resultado[] = curso_montar_card($conn, $row, $fiel_id);
    }
    return $resultado;
}

/**
 * Ranking geral (desde sempre): cursos publicados com mais pessoas distintas
 * que assistiram alguma aula. Antes contava linhas do histórico (uma a cada
 * 30 s de vídeo, o que inflava quem assistia mais tempo) e a consulta nem
 * rodava no Postgres (nome da categoria fora do GROUP BY).
 */
function cursos_mais_assistidos($conn, $limit = 10, $fiel_id = null) {
    $limit = (int) $limit;
    $resultado = array();

    $sql = "
        SELECT c.*, cat.IDCAT_NOME AS categoria_nome, v.pessoas
        FROM (
            -- Pessoas que já assistiram algo de cada curso. A matrícula é
            -- única por fiel+curso, então contar as matrículas com algum
            -- progresso dá o mesmo número que o COUNT(DISTINCT) sobre todo
            -- o ID_PROGRESSO (que agregava a tabela inteira a cada Home) — e
            -- o EXISTS para no primeiro progresso, usando o índice único
            -- (IDMATR_ID, IDCT_ID).
            SELECT m.IDC_ID, COUNT(*) AS pessoas
            FROM ID_MATRICULA m
            WHERE EXISTS (SELECT 1 FROM ID_PROGRESSO p WHERE p.IDMATR_ID = m.IDMATR_ID)
            GROUP BY m.IDC_ID
        ) v
        JOIN ID_CURSO c ON c.IDC_ID = v.IDC_ID
        LEFT JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = c.IDC_CATEGORIA_ID
        WHERE c.IDC_STATUS = 'PUBLICADO'
        ORDER BY v.pessoas DESC, c.IDC_CRIADO_EM DESC
        LIMIT $limit
    ";

    foreach (cursos_linhas_cache($conn, 'mais_assistidos_' . $limit, $sql) as $row) {
        $resultado[] = curso_montar_card($conn, $row, $fiel_id);
    }
    return $resultado;
}

/**
 * Fila "Em alta esta semana": só os cursos publicados com o selo "Em alta"
 * (pessoas distintas nos últimos 7 dias >= mínimo do admin), do mais visto
 * para o menos. Vazia se o selo estiver desligado ou ninguém passar do
 * mínimo — aí a fila não aparece.
 */
function cursos_em_alta_semana($conn, $limit = 10, $fiel_id = null) {
    $minimo = em_alta_minimo();
    if ($minimo <= 0) {
        return array();
    }
    $pessoas = array_filter(em_alta_contagens($conn)['cursos'], function ($n) use ($minimo) {
        return $n >= $minimo;
    });
    if (empty($pessoas)) {
        return array();
    }

    $lista = implode(',', array_map('intval', array_keys($pessoas)));
    $res = mysqli_query($conn, "
        SELECT c.*, cat.IDCAT_NOME AS categoria_nome
        FROM ID_CURSO c
        LEFT JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = c.IDC_CATEGORIA_ID
        WHERE c.IDC_STATUS = 'PUBLICADO' AND c.IDC_ID IN ($lista)
    ");
    $linhas = array();
    while ($res && $row = mysqli_fetch_assoc($res)) {
        $linhas[] = $row;
    }
    usort($linhas, function ($a, $b) use ($pessoas) {
        return ($pessoas[(int) $b['IDC_ID']] ?? 0) <=> ($pessoas[(int) $a['IDC_ID']] ?? 0)
            ?: strcmp((string) $b['IDC_CRIADO_EM'], (string) $a['IDC_CRIADO_EM']);
    });

    $resultado = array();
    foreach (array_slice($linhas, 0, (int) $limit) as $row) {
        $resultado[] = curso_montar_card($conn, $row, $fiel_id);
    }
    return $resultado;
}

/**
 * Recomendacao simples: cursos publicados na mesma categoria de cursos que o
 * fiel ja iniciou, excluindo os que ja tem matricula. Sem matricula nenhuma,
 * cai para "mais assistidos".
 */
function cursos_recomendados($conn, $fiel_id, $limit = 10) {
    $fiel_id = (int) $fiel_id;
    $limit = (int) $limit;
    $resultado = array();

    if ($fiel_id <= 0) {
        return cursos_mais_assistidos($conn, $limit);
    }

    $sql = "
        SELECT c.*, cat.IDCAT_NOME AS categoria_nome
        FROM ID_CURSO c
        LEFT JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = c.IDC_CATEGORIA_ID
        WHERE c.IDC_STATUS = 'PUBLICADO'
          AND c.IDC_ID NOT IN (SELECT IDC_ID FROM ID_MATRICULA WHERE IDF_ID = $fiel_id)
          AND c.IDC_CATEGORIA_ID IN (
              SELECT DISTINCT cc.IDC_CATEGORIA_ID FROM ID_MATRICULA mm
              JOIN ID_CURSO cc ON cc.IDC_ID = mm.IDC_ID
              WHERE mm.IDF_ID = $fiel_id AND cc.IDC_CATEGORIA_ID IS NOT NULL
          )
        ORDER BY c.IDC_CRIADO_EM DESC
        LIMIT $limit
    ";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $resultado[] = curso_montar_card($conn, $row, $fiel_id);
        }
    }

    if (empty($resultado)) {
        $sql_fallback = "
            SELECT c.*, cat.IDCAT_NOME AS categoria_nome
            FROM ID_CURSO c
            LEFT JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = c.IDC_CATEGORIA_ID
            WHERE c.IDC_STATUS = 'PUBLICADO'
              AND c.IDC_ID NOT IN (SELECT IDC_ID FROM ID_MATRICULA WHERE IDF_ID = $fiel_id)
            ORDER BY c.IDC_CRIADO_EM DESC
            LIMIT $limit
        ";
        $res_fb = mysqli_query($conn, $sql_fallback);
        if ($res_fb) {
            while ($row = mysqli_fetch_assoc($res_fb)) {
                $resultado[] = curso_montar_card($conn, $row, $fiel_id);
            }
        }
    }

    return $resultado;
}

/** Constrói o array de dados que php/fiel/includes/card_video.php espera. */
function video_montar_card($row) {
    global $conn;
    return array(
        'id' => (int) ($row['IDCT_ID'] ?? 0),
        'titulo' => $row['IDCT_TITULO'] ?? 'Vídeo',
        'curso_id' => (int) ($row['IDC_ID'] ?? 0),
        'curso_titulo' => $row['curso_titulo'] ?? '',
        'categoria' => $row['categoria_nome'] ?? null,
        'capa_url' => $row['IDC_CAPA_URL'] ?? null,
        'video_url' => $row['IDCT_URL'] ?? null,
        'duracao_segundos' => (int) ($row['IDCT_DURACAO_SEGUNDOS'] ?? 0),
        'em_alta' => $conn ? em_alta($conn, 'aulas', (int) ($row['IDCT_ID'] ?? 0)) : false,
        'link' => 'assistir_video.php?id=' . (int) ($row['IDCT_ID'] ?? 0),
    );
}

/**
 * Resolve a URL de preview (thumbnail em movimento) de um video: videos dentro
 * de "videos/" sao servidos localmente (com file_exists check, legado de antes
 * da migracao pro Supabase); qualquer outro valor e tratado como caminho dentro
 * do bucket PRIVADO do Supabase Storage e vira uma URL assinada de leitura (via
 * site_resolver_url_video(), definida em conexao.php) - ou, se ja for uma URL
 * http(s) pronta, usada como esta. Retorna '' se nao houver preview possivel.
 */
function video_resolver_preview($video_url, $prefixo_relativo = '../../') {
    global $conn;
    $video_url = trim((string) $video_url);
    // A prévia é o vídeo inteiro: sem assinatura (com bloqueio ligado), nada.
    if ($video_url === '' || (function_exists('assinatura_previews_liberados') && !assinatura_previews_liberados($conn))) {
        return '';
    }
    if (strpos($video_url, 'videos/') === 0) {
        $caminho_local = $prefixo_relativo . $video_url;
        // O prefixo relativo aponta a partir da pagina que inclui o card;
        // aqui so conseguimos checar o arquivo a partir da raiz do projeto.
        $caminho_absoluto = __DIR__ . '/../../../' . $video_url;
        return file_exists($caminho_absoluto) ? $caminho_local : '';
    }
    if (function_exists('site_resolver_url_video')) {
        return site_resolver_url_video($video_url, $prefixo_relativo, 3600);
    }
    return $video_url;
}

/**
 * Vídeos (aulas) recomendados individualmente para o fiel, com o curso de
 * origem indicado em cada card. Critério simples: aulas de cursos na mesma
 * categoria de cursos que o fiel já iniciou; sem matrícula nenhuma (ou sem
 * resultado), cai para os vídeos mais recentes de qualquer curso publicado.
 */
function videos_recomendados($conn, $fiel_id, $limit = 12) {
    $fiel_id = (int) $fiel_id;
    $limit = (int) $limit;
    $resultado = array();

    // Temas escolhidos no perfil (T23) mandam; sem eles, as categorias dos
    // cursos em que o fiel está matriculado.
    $temas = fiel_temas_ids($conn, $fiel_id);
    $filtro_categoria = "";
    if (!empty($temas)) {
        $filtro_categoria = " AND c.IDC_CATEGORIA_ID IN (" . implode(', ', array_map('intval', $temas)) . ") ";
    } elseif ($fiel_id > 0) {
        $filtro_categoria = "
            AND c.IDC_CATEGORIA_ID IN (
                SELECT DISTINCT cc.IDC_CATEGORIA_ID FROM ID_MATRICULA mm
                JOIN ID_CURSO cc ON cc.IDC_ID = mm.IDC_ID
                WHERE mm.IDF_ID = $fiel_id AND cc.IDC_CATEGORIA_ID IS NOT NULL
            )
        ";
    }

    // Tempo por dia (T23): primeiro as aulas que cabem nele, depois as de
    // duração ainda desconhecida (a duração é gravada quando alguém assiste),
    // por último as mais longas. Dentro de cada grupo, as mais novas.
    $prefs = $fiel_id > 0 ? preferencias_conteudo_ler($conn, $fiel_id) : array('tempo' => null);
    $ordem = "ct.IDCT_CRIADO_EM DESC";
    if ($prefs['tempo'] !== null) {
        $limite_seg = (int) $prefs['tempo'] * 60;
        $ordem = "CASE WHEN ct.IDCT_DURACAO_SEGUNDOS > 0 AND ct.IDCT_DURACAO_SEGUNDOS <= $limite_seg THEN 0
                       WHEN COALESCE(ct.IDCT_DURACAO_SEGUNDOS, 0) = 0 THEN 1 ELSE 2 END, " . $ordem;
    }

    $sql_base = "
        SELECT ct.IDCT_ID, ct.IDCT_TITULO, ct.IDCT_DURACAO_SEGUNDOS, ct.IDCT_URL,
               c.IDC_ID, c.IDC_TITULO AS curso_titulo, c.IDC_CAPA_URL,
               cat.IDCAT_NOME AS categoria_nome
        FROM ID_CONTENT ct
        JOIN ID_CURSO c ON c.IDC_ID = ct.IDC_ID
        LEFT JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = c.IDC_CATEGORIA_ID
        WHERE LOWER(ct.IDCT_TIPO) = 'video' AND ct.IDCT_PUBLICADO = 1 AND c.IDC_STATUS = 'PUBLICADO'
    ";

    $res = mysqli_query($conn, $sql_base . $filtro_categoria . " ORDER BY $ordem LIMIT $limit");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $resultado[] = video_montar_card($row);
        }
    }

    if (empty($resultado) && $filtro_categoria !== "") {
        $res_fb = mysqli_query($conn, $sql_base . " ORDER BY $ordem LIMIT $limit");
        if ($res_fb) {
            while ($row = mysqli_fetch_assoc($res_fb)) {
                $resultado[] = video_montar_card($row);
            }
        }
    }

    // Barra de progresso no thumbnail (uma consulta para todos os cards).
    $percentuais = videos_percentual_por_fiel($conn, $fiel_id, array_column($resultado, 'id'));
    foreach ($resultado as &$card) {
        $card['progresso'] = $percentuais[$card['id']] ?? null;
    }
    unset($card);

    return $resultado;
}

/** Curso em destaque para o hero da Home. */
function curso_destaque($conn) {
    $tem_destaque = tabela_tem_coluna($conn, 'ID_CURSO', 'IDC_DESTAQUE');
    $ordem = $tem_destaque ? 'c.IDC_DESTAQUE DESC, c.IDC_CRIADO_EM DESC' : 'c.IDC_CRIADO_EM DESC';

    $sql = "
        SELECT c.*, cat.IDCAT_NOME AS categoria_nome
        FROM ID_CURSO c
        LEFT JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = c.IDC_CATEGORIA_ID
        WHERE c.IDC_STATUS = 'PUBLICADO'
        ORDER BY $ordem
        LIMIT 1
    ";
    $linhas = cursos_linhas_cache($conn, 'destaque', $sql);
    return $linhas ? $linhas[0] : null;
}

/** Todas as categorias com contagem de cursos publicados. */
function categorias_com_contagem($conn) {
    $categorias = array();
    $sql = "
        SELECT cat.IDCAT_ID, cat.IDCAT_NOME, cat.IDCAT_DESCRICAO, COUNT(c.IDC_ID) AS total_cursos
        FROM ID_CATEGORIA cat
        LEFT JOIN ID_CURSO c ON c.IDC_CATEGORIA_ID = cat.IDCAT_ID AND c.IDC_STATUS = 'PUBLICADO'
        WHERE cat.IDCAT_ATIVO = 1
        GROUP BY cat.IDCAT_ID
        ORDER BY cat.IDCAT_NOME ASC
    ";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $categorias[] = $row;
        }
    }
    return $categorias;
}

/**
 * Filas da Home por categoria: uma por categoria ativa que tenha curso
 * publicado, em ordem alfabética, com os cursos mais novos primeiro.
 * Retorna [['id', 'nome', 'cursos' => [cards...]], ...].
 */
function cursos_por_categoria($conn, $fiel_id = null, $limit_por_categoria = 12) {
    $limit_por_categoria = (int) $limit_por_categoria;
    $filas = array();
    $sql = "
        SELECT c.*, cat.IDCAT_ID AS categoria_id, cat.IDCAT_NOME AS categoria_nome
        FROM ID_CURSO c
        JOIN ID_CATEGORIA cat ON cat.IDCAT_ID = c.IDC_CATEGORIA_ID
        WHERE c.IDC_STATUS = 'PUBLICADO' AND cat.IDCAT_ATIVO = 1
        ORDER BY cat.IDCAT_NOME ASC, c.IDC_CRIADO_EM DESC
    ";
    foreach (cursos_linhas_cache($conn, 'por_categoria', $sql) as $row) {
        $categoria_id = (int) $row['categoria_id'];
        if (!isset($filas[$categoria_id])) {
            $filas[$categoria_id] = array(
                'id' => $categoria_id,
                'nome' => $row['categoria_nome'],
                'cursos' => array(),
            );
        }
        if (count($filas[$categoria_id]['cursos']) >= $limit_por_categoria) {
            continue;
        }
        $card = curso_montar_card($conn, $row, $fiel_id);
        // A fila já é da categoria: o selo no thumbnail seria repetido.
        $card['categoria'] = null;
        $filas[$categoria_id]['cursos'][] = $card;
    }
    // Temas de interesse do fiel (T23) vêm primeiro, mantendo a ordem
    // alfabética dentro de cada grupo (usort é estável no PHP 8).
    $temas = $fiel_id ? array_flip(fiel_temas_ids($conn, $fiel_id)) : array();
    $filas = array_values($filas);
    if (!empty($temas)) {
        usort($filas, function ($a, $b) use ($temas) {
            return (isset($temas[$a['id']]) ? 0 : 1) <=> (isset($temas[$b['id']]) ? 0 : 1);
        });
    }
    return $filas;
}

/** Ícone Font Awesome de uma categoria, pelo nome (padrão: fa-th-large). */
function categoria_icone($nome) {
    static $mapa = array(
        'discipulado' => 'fa-hands-praying',
        'família' => 'fa-people-roof',
        'familia' => 'fa-people-roof',
        'casamento' => 'fa-ring',
        'jovens' => 'fa-users',
        'escola bíblica' => 'fa-bible',
        'escola biblica' => 'fa-bible',
        'bíblia' => 'fa-bible',
        'biblia' => 'fa-bible',
        'liderança' => 'fa-user-tie',
        'lideranca' => 'fa-user-tie',
        'devocionais' => 'fa-book-bible',
        'espiritualidade' => 'fa-dove',
        'missões' => 'fa-globe',
        'missoes' => 'fa-globe',
    );
    $chave = mb_strtolower(trim((string) $nome));
    return $mapa[$chave] ?? 'fa-th-large';
}

/**
 * Resolve a URL de exibicao da capa do curso:
 * - "r2:capas/..." => imagem no Cloudflare R2 (URL pre-assinada, 4h; enviada
 *   pelo admin, ver site_salvar_imagem_upload() em conexao.php);
 * - "assets/..." => imagem que vem junto com o codigo (no git);
 * - "http(s)://..." => link externo.
 */
function curso_resolver_capa($capa_url, $prefixo_relativo = '../../') {
    $capa_url = trim((string) $capa_url);
    if ($capa_url === '') {
        return null;
    }
    if (strpos($capa_url, 'r2:') === 0) {
        $url = function_exists('r2_criar_url_assinada') ? r2_criar_url_assinada('GET', substr($capa_url, 3), 14400) : '';
        return $url !== '' ? $url : null;
    }
    if (preg_match('#^https?://#i', $capa_url)) {
        return $capa_url;
    }
    if (strpos($capa_url, 'assets/') === 0) {
        return $prefixo_relativo . $capa_url;
    }
    return $capa_url;
}

function curso_formatar_duracao_min($minutos) {
    $minutos = (int) $minutos;
    if ($minutos <= 0) {
        return '';
    }
    if ($minutos < 60) {
        return $minutos . 'min';
    }
    $horas = intdiv($minutos, 60);
    $resto = $minutos % 60;
    return $resto > 0 ? ($horas . 'h ' . $resto . 'min') : ($horas . 'h');
}

/** Estatísticas agregadas do perfil do fiel (cards + progresso geral). */
function perfil_estatisticas($conn, $fiel_id) {
    $fiel_id = (int) $fiel_id;
    $stats = array(
        'cursos_iniciados' => 0,
        'cursos_concluidos' => 0,
        'horas_assistidas' => 0.0,
        'certificados' => 0,
        'progresso_geral' => 0.0,
    );
    if ($fiel_id <= 0) {
        return $stats;
    }

    $sql = "
        SELECT COUNT(*) AS total,
               SUM(CASE WHEN IDMATR_STATUS = 'CONCLUIDA' THEN 1 ELSE 0 END) AS concluidos,
               AVG(IDMATR_PERCENTUAL) AS media
        FROM ID_MATRICULA WHERE IDF_ID = $fiel_id
    ";
    $res = mysqli_query($conn, $sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $stats['cursos_iniciados'] = (int) $row['total'];
        $stats['cursos_concluidos'] = (int) $row['concluidos'];
        $stats['progresso_geral'] = round((float) ($row['media'] ?? 0), 1);
    }

    $sql_horas = "
        SELECT COALESCE(SUM(p.IDPR_TEMPO_ASSISTIDO_SEGUNDOS), 0) AS total_segundos
        FROM ID_PROGRESSO p
        JOIN ID_MATRICULA m ON m.IDMATR_ID = p.IDMATR_ID
        WHERE m.IDF_ID = $fiel_id
    ";
    $res_horas = mysqli_query($conn, $sql_horas);
    if ($res_horas && $row = mysqli_fetch_assoc($res_horas)) {
        $stats['horas_assistidas'] = round(((int) $row['total_segundos']) / 3600, 1);
    }

    $sql_cert = "SELECT COUNT(*) AS total FROM ID_CERTIFICADO WHERE IDF_ID = $fiel_id";
    $res_cert = mysqli_query($conn, $sql_cert);
    if ($res_cert && $row = mysqli_fetch_assoc($res_cert)) {
        $stats['certificados'] = (int) $row['total'];
    }

    return $stats;
}

function curso_formatar_nivel($nivel) {
    $mapa = array(
        'INICIANTE' => 'Iniciante',
        'INTERMEDIARIO' => 'Intermediário',
        'AVANCADO' => 'Avançado',
    );
    $nivel = strtoupper(trim((string) $nivel));
    return $mapa[$nivel] ?? '';
}
