<?php
// Testes que precisam de banco: rodam no CI (GitHub Actions) contra um
// Postgres vazio montado com o database/bancosupabase.md inteiro — o que,
// de quebra, prova que o arquivo monta a produção do zero.
//
// NUNCA rodar contra a homologação ou a produção: cria e apaga contas.
// Por isso só roda com TESTE_BANCO_DESCARTAVEL=1 e o banco em localhost.
//
//   DB_HOST=localhost DB_USER=postgres DB_PASS=postgres DB_NAME=postgres \
//   TESTE_BANCO_DESCARTAVEL=1 php tests/testes_banco.php
//
// Sai com código 1 se algum teste falhar. Ver docs/interno/qualidade_e_ci.md.

if (getenv('TESTE_BANCO_DESCARTAVEL') !== '1' || !in_array(getenv('DB_HOST'), array('localhost', '127.0.0.1'), true)) {
    fwrite(STDERR, "Recusado: só roda num Postgres descartável em localhost (TESTE_BANCO_DESCARTAVEL=1).\n");
    exit(2);
}
putenv('SENHA_CHECAR_VAZADAS=0');
$_ENV['SENHA_CHECAR_VAZADAS'] = '0';

require __DIR__ . '/../php/conexao.php';

$falhas = 0;
$total = 0;
function igual($nome, $obtido, $esperado) {
    global $falhas, $total;
    $total++;
    if ($obtido !== $esperado) {
        $falhas++;
        echo "FALHA  $nome\n       esperado: " . var_export($esperado, true) . "\n       obtido:   " . var_export($obtido, true) . "\n";
    }
}
function verdade($nome, $condicao) {
    igual($nome, (bool) $condicao, true);
}
function linha($sql, array $params = array()) {
    global $conn;
    $res = db_query($conn, $sql, $params);
    $l = $res ? mysqli_fetch_assoc($res) : null;
    return $l ? array_change_key_case($l, CASE_LOWER) : null;
}
// Valor de uma coluna, distinguindo NULL de "não veio": o ?? do PHP troca
// NULL pelo padrão e fazia o teste de "campo apagado" falhar à toa.
function campo($linha, $coluna) {
    return is_array($linha) && array_key_exists($coluna, $linha) ? $linha[$coluna] : '(coluna não veio)';
}
function novo_fiel($email, $nome = 'Fiel Teste') {
    $l = linha("INSERT INTO id_fiel (idf_nome, idf_email, idf_senha_hash, idf_status, idf_ativo) VALUES (?, ?, ?, 'aprovado', 1) RETURNING idf_id",
        array($nome, $email, password_hash('Senha!Teste1', PASSWORD_DEFAULT)));
    return $l ? (int) $l['idf_id'] : 0;
}

if (!$conn) {
    echo "FALHA  sem conexão com o banco: " . ($GLOBALS['site_pg_last_error'] ?? '') . "\n";
    exit(1);
}

// ---- O banco montou inteiro ----
foreach (array('id_fiel', 'id_admin_log', 'id_lgpd_pedido', 'id_missao_contribuicao', 'id_sessao_php', 'id_home_destaque', 'id_filial') as $tabela) {
    verdade("schema: tabela $tabela", site_tabela_existe($conn, $tabela));
}
verdade('schema: colunas da LGPD (82)', lgpd_disponivel($conn));
verdade('schema: colunas do 2FA (84)', doisfa_disponivel($conn));
verdade('schema: registro de atividade (83)', admin_log_disponivel($conn));
verdade('schema: medalhas da seção 81', (int) (linha("SELECT COUNT(*) AS n FROM id_badge WHERE idbadge_categoria = 'secretas'")['n'] ?? 0) >= 4);
verdade('schema: oferta geral da seção 77', linha("SELECT idmp_id FROM id_missao_projeto LIMIT 1") !== null);

// ---- LGPD: exportar e excluir sem histórico financeiro ----
$a = novo_fiel('lgpd-a@teste.invalid', 'Ana Teste');
verdade('lgpd: fiel A criado', $a > 0);
db_query($conn, "INSERT INTO id_notificacao (idf_id, idn_titulo, idn_mensagem, idn_tipo) VALUES (?, 'Oi', 'Teste', 'info')", array($a));
$exportado = lgpd_exportar_dados($conn, $a);
igual('lgpd: exportação traz a conta', $exportado['conta'][0]['idf_email'] ?? null, 'lgpd-a@teste.invalid');
verdade('lgpd: exportação sem hash da senha', !array_key_exists('idf_senha_hash', $exportado['conta'][0]));
verdade('lgpd: exportação sem coluna duplicada em maiúsculo', !array_key_exists('IDF_EMAIL', $exportado['conta'][0]));
verdade('lgpd: exportação traz a notificação', !empty($exportado['id_notificacao']));
$r = lgpd_excluir_conta($conn, $a, 'proprio');
igual('lgpd: sem pagamento, apaga de vez', $r['modo'], 'apagada');
igual('lgpd: conta A sumiu', linha("SELECT idf_id FROM id_fiel WHERE idf_id = ?", array($a)), null);
verdade('lgpd: pedido registrado', linha("SELECT idlp_id FROM id_lgpd_pedido WHERE idlp_fiel_id = ? AND idlp_tipo = 'exclusao'", array($a)) !== null);

// ---- LGPD: excluir com contribuição (anonimiza) ----
$b = novo_fiel('lgpd-b@teste.invalid', 'Bruno Teste');
$projeto = (int) linha("SELECT idmp_id FROM id_missao_projeto LIMIT 1")['idmp_id'];
db_query($conn, "INSERT INTO id_missao_contribuicao (idmp_id, idf_id, idmc_nome_exibicao, idmc_mensagem, idmc_valor_centavos) VALUES (?, ?, 'Bruno', 'Força!', 500)", array($projeto, $b));
db_query($conn, "INSERT INTO id_conteudo_comentario (idct_id, idf_id, idcc_mensagem) SELECT idct_id, ?, 'meu comentário' FROM id_content LIMIT 1", array($b));
$r = lgpd_excluir_conta($conn, $b, 'admin');
igual('lgpd: com contribuição, anonimiza', $r['modo'], 'anonimizada');
$conta_b = linha("SELECT idf_nome, idf_email, idf_senha_hash, idf_ativo FROM id_fiel WHERE idf_id = ?", array($b));
igual('lgpd: nome anonimizado', $conta_b['idf_nome'] ?? null, 'Conta excluída');
igual('lgpd: e-mail anonimizado', $conta_b['idf_email'] ?? null, 'conta-excluida-' . $b . '@excluida.invalid');
igual('lgpd: sem senha', campo($conta_b, 'idf_senha_hash'), null);
$contrib = linha("SELECT idmc_valor_centavos, idmc_anonimo, idmc_nome_exibicao FROM id_missao_contribuicao WHERE idf_id = ?", array($b));
igual('lgpd: contribuição continua (Financeiro)', (int) ($contrib['idmc_valor_centavos'] ?? 0), 500);
igual('lgpd: contribuição anônima', (int) ($contrib['idmc_anonimo'] ?? 0), 1);
igual('lgpd: sem nome no quadro', campo($contrib, 'idmc_nome_exibicao'), null);
$coment = linha("SELECT idcc_mensagem, idcc_oculto FROM id_conteudo_comentario WHERE idf_id = ?", array($b));
if ($coment) {
    igual('lgpd: comentário sem o texto', $coment['idcc_mensagem'], '[comentário removido]');
}

// ---- LGPD: sem pagamento, mas com comentário respondido por outra pessoa ----
// (o DELETE em cascata levaria a resposta da outra pessoa junto)
$aula = linha("SELECT idct_id FROM id_content LIMIT 1");
if ($aula && tabela_tem_coluna($conn, 'id_conteudo_comentario', 'idcc_pai_id')) {
    $c = novo_fiel('lgpd-c@teste.invalid', 'Carla Teste');
    $d = novo_fiel('lgpd-d@teste.invalid', 'Davi Teste');
    $pai = linha("INSERT INTO id_conteudo_comentario (idct_id, idf_id, idcc_mensagem) VALUES (?, ?, 'pergunta') RETURNING idcc_id", array((int) $aula['idct_id'], $c));
    db_query($conn, "INSERT INTO id_conteudo_comentario (idct_id, idf_id, idcc_mensagem, idcc_pai_id) VALUES (?, ?, 'resposta do Davi', ?)", array((int) $aula['idct_id'], $d, (int) $pai['idcc_id']));
    $r = lgpd_excluir_conta($conn, $c, 'proprio');
    igual('lgpd: comentário respondido por outro anonimiza em vez de apagar', $r['modo'], 'anonimizada');
    verdade('lgpd: a resposta do outro fiel continua', linha("SELECT idcc_id FROM id_conteudo_comentario WHERE idf_id = ?", array($d)) !== null);
}

// ---- Admin: registro de atividade ----
$admin = novo_fiel('admin-teste@teste.invalid', 'Admin Teste');
$_SESSION['Usuario_id'] = $admin;
$_SESSION['Usuario_nome'] = 'Admin Teste';
admin_log($conn, 'curso.excluir', 'curso', 42, array('titulo' => 'Curso X'));
$log = linha("SELECT idal_admin_id, idal_acao, idal_alvo_id, idal_detalhes FROM id_admin_log ORDER BY idal_id DESC LIMIT 1");
igual('log: quem', (int) ($log['idal_admin_id'] ?? 0), $admin);
igual('log: ação', $log['idal_acao'] ?? null, 'curso.excluir');
igual('log: detalhes em JSON', json_decode((string) ($log['idal_detalhes'] ?? ''), true), array('titulo' => 'Curso X'));

// ---- 2FA no login ----
$segredo = totp_novo_segredo();
$reserva = doisfa_gerar_reserva();
db_query($conn, "UPDATE id_fiel SET idf_2fa_segredo = ?, idf_2fa_ativo_em = NOW(), idf_2fa_reserva = ? WHERE idf_id = ?", array($segredo, $reserva['json'], $admin));
igual('2fa: estado no login com 2FA ligado', doisfa_estado_login($conn, $admin), 'ligado');
igual('2fa: estado no login sem 2FA', doisfa_estado_login($conn, $b), 'sem_2fa');
igual('2fa: sem conexão recusa (não deixa passar só com a senha)', doisfa_estado_login(false, $admin), 'erro');
$codigo = totp_codigo($segredo, (int) floor(time() / TOTP_PASSO_SEGUNDOS));
verdade('2fa: código certo entra', doisfa_conferir_login($conn, $admin, $codigo));
verdade('2fa: o mesmo código não entra de novo', !doisfa_conferir_login($conn, $admin, $codigo));
verdade('2fa: código errado', !doisfa_conferir_login($conn, $admin, '000000') || $codigo === '000000');
verdade('2fa: código de reserva entra', doisfa_conferir_login($conn, $admin, $reserva['codigos'][0]));
verdade('2fa: reserva usada não entra de novo', !doisfa_conferir_login($conn, $admin, $reserva['codigos'][0]));
verdade('2fa: conta sem 2FA não entra', !doisfa_conferir_login($conn, $b, $codigo));
// Chave antiga (sem cifra) é cifrada no próximo login certo.
$_ENV['APP_CHAVE'] = 'chave-de-teste-com-mais-de-32-letras!';
$codigo = totp_codigo($segredo, (int) floor(time() / TOTP_PASSO_SEGUNDOS) + 1);
verdade('2fa: login com chave antiga entra', doisfa_conferir_login($conn, $admin, $codigo));
$guardado = (string) (linha("SELECT idf_2fa_segredo FROM id_fiel WHERE idf_id = ?", array($admin))['idf_2fa_segredo'] ?? '');
verdade('2fa: chave antiga cifrada no login', doisfa_segredo_cifrado($guardado) && doisfa_decifrar_segredo($guardado, $admin) === $segredo);
$_ENV['APP_CHAVE'] = '';

// ---- Senha antes de ação sem volta ----
igual('senha: certa', senha_conferir_atual($conn, $admin, 'Senha!Teste1'), '');
verdade('senha: errada', senha_conferir_atual($conn, $admin, 'errada') !== '');

// ---- Métricas do painel (Pacote A) ----
$geral = metricas_geral($conn, site_hoje());
verdade('métricas: geral roda', is_array($geral) && count($geral['funil']) === 5);
$aula_m = linha("SELECT idct_id, idc_id FROM id_content WHERE idc_id IS NOT NULL LIMIT 1");
if ($aula_m) {
    // Uma pessoa que parou aos 35 s de uma aula de 100 s (faixa 3 de 0..9).
    db_query($conn, "UPDATE id_content SET idct_duracao_segundos = 100 WHERE idct_id = ?", array((int) $aula_m['idct_id']));
    $matr = linha("INSERT INTO id_matricula (idf_id, idc_id, idmatr_status, idmatr_percentual) VALUES (?, ?, 'ATIVA', 10) RETURNING idmatr_id", array($admin, (int) $aula_m['idc_id']));
    db_query($conn, "INSERT INTO id_progresso (idmatr_id, idct_id, idpr_status, idpr_percentual, idpr_tempo_assistido_segundos, idpr_posicao_segundos) VALUES (?, ?, 'EM_ANDAMENTO', 35, 35, 35)", array((int) $matr['idmatr_id'], (int) $aula_m['idct_id']));
    $aulas_m = metricas_aulas_curso($conn, (int) $aula_m['idc_id']);
    $esta = null;
    foreach ((array) $aulas_m as $a) {
        if ($a['id'] === (int) $aula_m['idct_id']) {
            $esta = $a;
        }
    }
    verdade('métricas: aula aparece com quem começou', $esta && $esta['comecaram'] >= 1 && $esta['duracao'] === 100);
    $faixas_m = metricas_paradas_aula($conn, (int) $aula_m['idct_id'], 100);
    verdade('métricas: parou na faixa 30–40% do vídeo', is_array($faixas_m) && ($faixas_m[3] ?? 0) >= 1);
    verdade('métricas: funil conta quem começou', (int) (metricas_geral($conn, site_hoje())['funil'][2][1] ?? 0) >= 1);
}

// ---- Protetor de ofensiva e meta diária (seção 85) ----
verdade('schema: protetor e meta (85)', ofensiva_protetor_disponivel($conn) && meta_diaria_disponivel($conn));
$p = novo_fiel('protetor@teste.invalid', 'Pedro Teste');
$hoje_t = site_hoje();
$dia = function ($menos) use ($hoje_t) { return date('Y-m-d', strtotime($hoje_t . " -$menos days")); };
// Entrou de 5 a 2 dias atrás (4 dias seguidos), faltou ontem, voltou hoje com 1 protetor.
foreach (array(5, 4, 3, 2) as $menos) {
    db_query($conn, "INSERT INTO id_fiel_acesso_dia (idf_id, idad_data) VALUES (?, ?)", array($p, $dia($menos)));
}
db_query($conn, "UPDATE id_fiel SET idf_protetores = 1 WHERE idf_id = ?", array($p));
db_query($conn, "INSERT INTO id_fiel_acesso_dia (idf_id, idad_data) VALUES (?, ?)", array($p, $hoje_t));
unset($_SESSION['ofensiva_protegida'], $_SESSION['protetor_ganho']);
ofensiva_protetor_processar($conn, $p, $hoje_t);
igual('protetor: cobriu o dia que faltou', (int) ($_SESSION['ofensiva_protegida'] ?? 0), 1);
igual('protetor: gastou o protetor', ofensiva_protetores($conn, $p), 0);
$res_p = ofensiva_resumo($conn, $p);
igual('protetor: sequência continua (6 dias)', $res_p['atual'], 6);
igual('protetor: dia salvo não conta como acesso de verdade', $res_p['total'], 5);
verdade('protetor: dia salvo marcado na semana', ($res_p['semana'][$dia(1)] ?? null) === 'protegido');
verdade('protetor: ranking soma o dia salvo', (function () use ($conn, $p) {
    foreach ((array) ofensiva_ranking_banco($conn, 500, false) as $l) {
        if ((int) $l['idf_id'] === $p) {
            return (int) $l['atual'] === 6;
        }
    }
    return false;
})());
// Faltaram 3 dias com só 2 protetores: não gasta (não salvaria a sequência).
$q = novo_fiel('protetor2@teste.invalid', 'Quiteria Teste');
db_query($conn, "INSERT INTO id_fiel_acesso_dia (idf_id, idad_data) VALUES (?, ?)", array($q, $dia(4)));
db_query($conn, "INSERT INTO id_fiel_acesso_dia (idf_id, idad_data) VALUES (?, ?)", array($q, $hoje_t));
db_query($conn, "UPDATE id_fiel SET idf_protetores = 2 WHERE idf_id = ?", array($q));
unset($_SESSION['ofensiva_protegida']);
ofensiva_protetor_processar($conn, $q, $hoje_t);
igual('protetor: buraco maior que os protetores não gasta', ofensiva_protetores($conn, $q), 2);
// 7 dias seguidos: ganha um protetor.
$r = novo_fiel('protetor3@teste.invalid', 'Rute Teste');
for ($menos = 6; $menos >= 0; $menos--) {
    db_query($conn, "INSERT INTO id_fiel_acesso_dia (idf_id, idad_data) VALUES (?, ?)", array($r, $dia($menos)));
}
unset($_SESSION['protetor_ganho']);
ofensiva_protetor_processar($conn, $r, $hoje_t);
igual('protetor: 7 dias seguidos ganham um protetor', ofensiva_protetores($conn, $r), 1);
verdade('protetor: aviso de protetor ganho', !empty($_SESSION['protetor_ganho']));

// Meta diária: dois envios do player com 30 s de intervalo somam 30 s.
$_SESSION['meta_ultimo_envio'] = array(999 => time() - 30);
$m = meta_diaria_somar($conn, $r, 999);
verdade('meta: soma o tempo entre dois envios', is_array($m) && $m['segundos'] >= 29 && $m['segundos'] <= 41);
$_SESSION['meta_ultimo_envio'] = array(999 => time() - 3600);
$m2 = meta_diaria_somar($conn, $r, 999);
verdade('meta: pausa longa conta no máximo 40 s', $m2['segundos'] - $m['segundos'] <= 40);
// Meta de 2 min (até aqui ~70 s): envios de 40 s até cruzar; o aviso sai
// uma vez, no envio que cruza.
db_query($conn, "INSERT INTO id_preferencia (idf_id, idpref_tempo_diario) VALUES (?, 2) ON CONFLICT (idf_id) DO UPDATE SET idpref_tempo_diario = 2", array($r));
$avisos = 0;
for ($i = 0; $i < 4; $i++) {
    $_SESSION['meta_ultimo_envio'] = array(999 => time() - 40);
    $m3 = meta_diaria_somar($conn, $r, 999);
    $avisos += !empty($m3['cumpriu_agora']) ? 1 : 0;
}
verdade('meta: cumpriu a meta de 2 min', $m3['alvo'] === 2 && $m3['feitos'] >= 2);
igual('meta: avisa uma vez só, no envio que cruza', $avisos, 1);

// ---- Números da home pública ----
$numeros_t = numeros_publicos($conn);
verdade('home: números públicos rodam', is_array($numeros_t) && isset($numeros_t['cursos'], $numeros_t['aulas'], $numeros_t['horas'], $numeros_t['pessoas']));

// ---- Relógio do fiel (seção 87) ----
db_query($conn, "DELETE FROM id_fiel_acesso_dia WHERE idf_id = ? AND idad_data = CAST(? AS DATE)", array($r, site_hoje()));
$rel1 = relogio_assistir($conn, $r, 500);
igual('relógio: primeiro envio libera só a folga', is_array($rel1) ? $rel1['liberado'] : null, RELOGIO_SALDO_INICIAL);
db_query($conn, "UPDATE id_fiel_acesso_dia SET idad_relogio_em = NOW() - INTERVAL '30 seconds' WHERE idf_id = ? AND idad_data = CAST(? AS DATE)", array($r, site_hoje()));
$rel2 = relogio_assistir($conn, $r, 30);
verdade('relógio: 30 s depois, conta ~30 s e libera o avanço', is_array($rel2) && $rel2['tempo'] >= 29 && $rel2['tempo'] <= 31 && $rel2['liberado'] === 30);
$rel3 = relogio_assistir($conn, $r, 500);
verdade('relógio: outra aula logo em seguida quase não avança', is_array($rel3) && $rel3['tempo'] <= 1 && $rel3['liberado'] <= 50);
$m_rel = meta_diaria_somar($conn, $r, 999, $rel3);
verdade('relógio: a meta usa o tempo do relógio', is_array($m_rel) && isset($m_rel['segundos']));

// ---- Pacote G: cadastro pelo admin, cancelar em vez de apagar, saúde ----
$email_novo = 'criado.admin.' . time() . '@teste.local';
$sem_aceite = admin_criar_pessoa($conn, array('nome' => 'Pessoa Teste', 'email' => $email_novo, 'autorizou' => false));
verdade('admin cria pessoa: sem a autorização é recusado', !$sem_aceite['ok']);
$criada_t = admin_criar_pessoa($conn, array('nome' => 'Pessoa Teste', 'email' => $email_novo, 'autorizou' => true));
verdade('admin cria pessoa: conta criada', $criada_t['ok'] && $criada_t['id'] > 0);
$res_criada = db_query($conn, "SELECT idf_status FROM id_fiel WHERE idf_id = ?", array($criada_t['id']));
igual('admin cria pessoa: já nasce aprovada', ($res_criada ? mysqli_fetch_assoc($res_criada)['idf_status'] : null), 'aprovado');
verdade('admin cria pessoa: e-mail repetido é recusado', !admin_criar_pessoa($conn, array('nome' => 'Outra Pessoa', 'email' => strtoupper($email_novo), 'autorizou' => true))['ok']);
$menor_t = admin_criar_pessoa($conn, array('nome' => 'Menor Teste', 'email' => 'menor.' . $email_novo, 'nascimento' => date('Y-m-d', strtotime('-12 years')), 'autorizou' => true));
verdade('admin cria pessoa: menor sem responsável é recusado', !$menor_t['ok']);

$res_lanc = db_query($conn, "INSERT INTO id_financeiro_lancamento (idfl_tipo, idfl_categoria, idfl_valor_centavos, idfl_data) VALUES ('custo', 'Teste', 1234, CAST(? AS DATE)) RETURNING idfl_id", array(site_hoje()));
$lanc_id = $res_lanc ? (int) mysqli_fetch_assoc($res_lanc)['idfl_id'] : 0;
verdade('financeiro: cancelar sem motivo é recusado', financeiro_lancamento_cancelar($conn, $lanc_id, 0, '') !== '');
igual('financeiro: cancela com motivo', financeiro_lancamento_cancelar($conn, $lanc_id, 0, 'lançado em dobro'), '');
verdade('financeiro: não cancela duas vezes', financeiro_lancamento_cancelar($conn, $lanc_id, 0, 'de novo') !== '');
$res_ainda = db_query($conn, "SELECT idfl_cancelado_motivo FROM id_financeiro_lancamento WHERE idfl_id = ?", array($lanc_id));
igual('financeiro: o cancelado continua gravado, com o motivo', $res_ainda ? mysqli_fetch_assoc($res_ainda)['idfl_cancelado_motivo'] : null, 'lançado em dobro');

sistema_falha_registrar('EmailFalhou', 'teste do CI');
$saude_t = sistema_saude($conn);
verdade('saúde: a falha aparece no resumo das 24 h', is_array($saude_t) && ($saude_t['falhas']['EmailFalhou']['total'] ?? 0) >= 1);
sistema_falha_registrar('TipoQualquer', 'não entra');
$res_qualquer = db_query($conn, "SELECT COUNT(*) AS n FROM id_sistema_falha WHERE isf_tipo = 'TipoQualquer'");
igual('saúde: tipo fora da lista não é gravado', $res_qualquer ? (int) mysqli_fetch_assoc($res_qualquer)['n'] : -1, 0);

// ---- Registro das seções (o CI roda o bancosupabase.md inteiro) ----
site_cache_apagar('banco_secao_atual');
igual('banco: registro chega na seção que o código espera', banco_secao_atual($conn), BANCO_SECAO_ESPERADA);
// Sem RLS, a API REST do Supabase abre a tabela para quem tiver a chave anon (seção 90).
$res_rls = db_query($conn, "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND NOT rowsecurity ORDER BY tablename");
$sem_rls = array();
while ($res_rls && $linha = mysqli_fetch_assoc($res_rls)) {
    $sem_rls[] = $linha['tablename'];
}
igual('banco: toda tabela com RLS ligado', $res_rls ? $sem_rls : null, array());

// ---- Consultas das telas mais usadas ----
verdade('ranking da ofensiva roda', is_array(ofensiva_ranking_banco($conn, 10, true)));
verdade('pessoas online roda', is_array(presenca_listar_online_banco($conn, 10, true)));
@unlink(sys_get_temp_dir() . '/mananciapp_manutencao_' . site_hoje());
verdade('lembrete diário: a consulta roda (data + hora, sem volta da meia-noite)', !isset(lembretes_diarios_enviar($conn, 0)['erro']));
verdade('limpeza diária roda', is_array(site_manutencao_limpar($conn)) && site_manutencao_limpar($conn) === array());
verdade('transmissão atual (cache) roda', transmissao_obter_atual_cache($conn) === null || is_array(transmissao_obter_atual_cache($conn)));
site_cache_apagar('');

echo "\n" . ($total - $falhas) . " de $total testes com banco passaram.\n";
exit($falhas > 0 ? 1 : 0);
