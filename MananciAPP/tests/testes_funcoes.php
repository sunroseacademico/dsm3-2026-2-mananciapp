<?php
// Testes das funções puras de php/conexao.php (sem banco e sem rede).
//
// Rodar, de dentro de MananciAPP/:
//   C:\xampp\php\php.exe tests\testes_funcoes.php
// Sai com código 1 se algum teste falhar.
//
// O banco não é usado: mysqli_connect() é trocado por uma versão que devolve
// false (o conexao.php só define a dele se ainda não existir), a checagem de
// senha vazada (API externa) é desligada e o R2 recebe credenciais falsas.

function mysqli_connect($host, $usuario, $senha, $banco, $porta = 5432) {
    return false;
}
$_ENV['SENHA_CHECAR_VAZADAS'] = '0';
putenv('SENHA_CHECAR_VAZADAS=0');
define('R2_ACCOUNT_ID', 'conta-teste');
define('R2_ACCESS_KEY_ID', 'CHAVETESTE');
define('R2_SECRET_ACCESS_KEY', 'segredo-teste');
define('R2_BUCKET', 'bucket-teste');
$_SERVER['HTTP_HOST'] = 'mananciapp-render-homolog.onrender.com';
$_SERVER['SERVER_NAME'] = 'mananciapp-render-homolog.onrender.com';

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

// ---- Entrada ----
igual('entrada: apara espaços', site_entrada_texto('  Maria  '), 'Maria');
igual('entrada: array vira vazio', site_entrada_texto(array('x')), '');
igual('entrada: nulo vira vazio', site_entrada_texto(null), '');
igual('entrada: número vira texto', site_entrada_texto(42), '42');
igual('entrada: senha mantém espaços', site_entrada_texto(' S3nh@ ', false), ' S3nh@ ');

// ---- Senha ----
igual('senha: válida', senha_validar_politica('Abcdef1!', 'Abcdef1!'), '');
verdade('senha: curta', senha_validar_politica('Ab1!', 'Ab1!') !== '');
verdade('senha: sem maiúscula', senha_validar_politica('abcdefg1!', 'abcdefg1!') !== '');
verdade('senha: sem especial', senha_validar_politica('Abcdefg12', 'Abcdefg12') !== '');
verdade('senha: confirmação diferente', senha_validar_politica('Abcdef1!', 'Abcdef1?') !== '');
verdade('senha: acima de 72', senha_validar_politica('A!' . str_repeat('a', 71), 'A!' . str_repeat('a', 71)) !== '');

// ---- Telefone ----
igual('telefone: normaliza com +55', fiel_whatsapp_normalizar('+55 (11) 9 8765-4321'), '11987654321');
igual('telefone: vazio', fiel_whatsapp_normalizar(''), '');
igual('telefone: fixo não é celular', fiel_whatsapp_normalizar('(11) 3333-4444'), null);
igual('telefone: formata', formatar_telefone_exibicao('11987654321'), '(11) 9 8765-4321');
igual('telefone: sem número', formatar_telefone_exibicao(''), 'N/A');
igual('telefone: fora do padrão volta igual', formatar_telefone_exibicao('1234'), '1234');

// ---- Data de nascimento ----
igual('nascimento: vazio é opcional', fiel_nascimento_validar(''), array('erro' => null, 'data' => null));
igual('nascimento: válido', fiel_nascimento_validar('1990-05-20')['data'], '1990-05-20');
verdade('nascimento: data impossível', fiel_nascimento_validar('2001-02-30')['erro'] !== null);
verdade('nascimento: futuro', fiel_nascimento_validar(date('Y-m-d', strtotime('+1 day')))['erro'] !== null);

// ---- Redirecionamento depois do login (não pode sair do app) ----
igual('voltar: página do fiel', auth_voltar_valido('php/fiel/curso.php?id=3'), 'php/fiel/curso.php?id=3');
igual('voltar: site externo', auth_voltar_valido('https://site-do-atacante.com'), '');
igual('voltar: //outro-host', auth_voltar_valido('//site-do-atacante.com/php/fiel/a.php'), '');
igual('voltar: subir pasta', auth_voltar_valido('php/fiel/../admin/dashboard.php'), '');
igual('voltar: área do admin', auth_voltar_valido('php/admin/dashboard.php'), '');

// ---- Recuperação de senha e limites ----
verdade('token: formato certo', recuperacao_token_formato_ok(str_repeat('a1', 32)));
verdade('token: curto', !recuperacao_token_formato_ok('abc'));
verdade('token: array', !recuperacao_token_formato_ok(array('x')));
verdade('token: o hash guardado na sessão também tem o formato', recuperacao_token_formato_ok(recuperacao_hash_token(str_repeat('a1', 32))));
verdade('token: hash sem banco não vale', recuperacao_token_valido_por_hash(false, recuperacao_hash_token(str_repeat('a1', 32))) === null);
igual('hash do e-mail ignora caixa e espaços', recuperacao_hash_email(' Fulano@X.com '), recuperacao_hash_email('fulano@x.com'));
igual('IP v4 conta sozinho', site_ip_chave_limite('200.1.2.3'), '200.1.2.3');
igual('IP v6 conta pela faixa /64', site_ip_chave_limite('2804:14c:1:2:aaaa:bbbb:cccc:dddd'), '2804:14c:1:2::/64');
// S14: no Render o último valor do X-Forwarded-For é um proxy interno (10.x).
$xff_antes = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
$remote_antes = $_SERVER['REMOTE_ADDR'] ?? null;
$_SERVER['REMOTE_ADDR'] = '10.0.0.9';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '200.1.2.3, 10.24.90.146';
igual('IP: pula o proxy interno do Render', site_ip_cliente(), '200.1.2.3');
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 200.1.2.3, 10.24.90.146';
igual('IP: forjado à esquerda não vale', site_ip_cliente(), '200.1.2.3');
$_SERVER['HTTP_X_FORWARDED_FOR'] = '2804:14c:1:2::5, 10.24.90.146';
igual('IP: v6 público', site_ip_cliente(), '2804:14c:1:2::5');
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, lixo, 10.24.90.146';
igual('IP: lixo na lista volta ao REMOTE_ADDR', site_ip_cliente(), '10.0.0.9');
$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.1.1.1, 192.168.0.2';
igual('IP: só internos volta ao REMOTE_ADDR', site_ip_cliente(), '10.0.0.9');
unset($_SERVER['HTTP_X_FORWARDED_FOR']);
igual('IP: sem cabeçalho usa REMOTE_ADDR', site_ip_cliente(), '10.0.0.9');
// S17: atrás da Cloudflare o IP do visitante vem em CF-Connecting-IP.
$_SERVER['HTTP_X_FORWARDED_FOR'] = '200.1.2.3, 104.23.254.234, 10.24.90.146';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '200.1.2.3';
igual('IP: Cloudflare vem primeiro', site_ip_cliente(), '200.1.2.3');
$_SERVER['HTTP_CF_CONNECTING_IP'] = '2804:14c:1:2::5';
igual('IP: Cloudflare com IPv6', site_ip_cliente(), '2804:14c:1:2::5');
$_SERVER['HTTP_CF_CONNECTING_IP'] = '10.0.0.1';
igual('IP: Cloudflare com IP interno não vale (cai no X-Forwarded-For)', site_ip_cliente(), '104.23.254.234');
$_SERVER['HTTP_CF_CONNECTING_IP'] = 'lixo';
igual('IP: Cloudflare com lixo não vale', site_ip_cliente(), '104.23.254.234');
unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
if ($xff_antes !== null) { $_SERVER['HTTP_X_FORWARDED_FOR'] = $xff_antes; }
if ($remote_antes === null) { unset($_SERVER['REMOTE_ADDR']); } else { $_SERVER['REMOTE_ADDR'] = $remote_antes; }

// ---- YouTube ----
igual('youtube: ID puro', transmissao_extrair_youtube_id('dQw4w9WgXcQ'), 'dQw4w9WgXcQ');
igual('youtube: watch?v=', transmissao_extrair_youtube_id('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10'), 'dQw4w9WgXcQ');
igual('youtube: youtu.be', transmissao_extrair_youtube_id('https://youtu.be/dQw4w9WgXcQ'), 'dQw4w9WgXcQ');
igual('youtube: /live/', transmissao_extrair_youtube_id('https://www.youtube.com/live/dQw4w9WgXcQ?si=x'), 'dQw4w9WgXcQ');
igual('youtube: lixo', transmissao_extrair_youtube_id('não é um link'), '');

// ---- Shim MySQL -> Postgres ----
igual('sql: LIMIT offset,n', site_ajustar_sql_para_pg('SELECT * FROM t LIMIT 10, 5'), 'SELECT * FROM t LIMIT 5 OFFSET 10');
igual('sql: LIMIT dentro de texto não muda', site_ajustar_sql_para_pg("INSERT INTO t VALUES ('limit 3, 4')"), "INSERT INTO t VALUES ('limit 3, 4')");
igual('sql: UPDATE ... LIMIT sai', site_ajustar_sql_para_pg('UPDATE t SET a = 1 WHERE id = 2 LIMIT 1'), 'UPDATE t SET a = 1 WHERE id = 2');

igual('sql: escape sem conexão não quebra', mysqli_real_escape_string(false, "O'Brien"), "O''Brien");

// ---- R2 (URL assinada) ----
$url = r2_criar_url_assinada('GET', 'aulas/a b.mp4', 7200);
verdade('r2: aponta para o bucket', strpos($url, 'https://conta-teste.r2.cloudflarestorage.com/bucket-teste/aulas/a%20b.mp4?') === 0);
verdade('r2: GET assina só o host', strpos($url, 'X-Amz-SignedHeaders=host&') !== false);
verdade('r2: GET arredonda e soma 1 h (7200 + 3600)', strpos($url, 'X-Amz-Expires=10800') !== false);
igual('r2: GET se repete na mesma hora', r2_criar_url_assinada('GET', 'aulas/a b.mp4', 7200), $url);
$put = r2_criar_url_assinada('PUT', 'aulas/x.mp4', 900, array(), array('Content-Type' => 'video/mp4'));
verdade('r2: PUT assina o content-type', strpos($put, 'X-Amz-SignedHeaders=content-type%3Bhost') !== false);
verdade('r2: PUT vale o pedido (sem a hora extra)', strpos($put, 'X-Amz-Expires=900') !== false);
verdade('r2: assinatura muda com o content-type', $put !== r2_criar_url_assinada('PUT', 'aulas/x.mp4', 900, array(), array('Content-Type' => 'text/html')));

// ---- OneSignal: external_id ----
// site_env() pula valor vazio em $_ENV e cai no getenv() (que o .env local
// pode ter preenchido): troca nos três lugares.
function definir_segredo_push($valor) {
    $_ENV['ONESIGNAL_EXTERNAL_ID_SEGREDO'] = $valor;
    putenv('ONESIGNAL_EXTERNAL_ID_SEGREDO=' . $valor);
    unset($_SERVER['ONESIGNAL_EXTERNAL_ID_SEGREDO']);
}
definir_segredo_push('');
igual('push: sem segredo', onesignal_external_id(42), 'fiel-42');
definir_segredo_push('segredo-push');
// Sem banco (fiel que não é o logado): sem marca, igual ao valor de antes.
igual('push: sem marca = valor antigo', onesignal_external_id(7), 'fiel-7-' . substr(hash_hmac('sha256', 'fiel-7', 'segredo-push'), 0, 24));
$_SESSION['Usuario_id'] = 42;
$_SESSION['push_marca'] = array('id' => 42, 'marca' => 'aaaa1111');
$com_marca_a = onesignal_external_id(42);
verdade('push: marca entra no id', $com_marca_a !== 'fiel-42-' . substr(hash_hmac('sha256', 'fiel-42', 'segredo-push'), 0, 24));
igual('push: formato fiel-<id>-<24>', preg_match('/^fiel-42-[a-f0-9]{24}$/', $com_marca_a), 1);
definir_segredo_push('');

// ---- CSP ----
$politica = site_csp_politica();
preg_match("/script-src ([^;]*)/", $politica, $script_src);
verdade('csp: nonce no script-src', strpos($script_src[1] ?? '', "'nonce-" . site_csp_nonce() . "'") !== false);
verdade('csp: script-src sem unsafe-inline', strpos($script_src[1] ?? '', 'unsafe-inline') === false);
igual('csp: nonce estável na requisição', site_csp_nonce(), site_csp_nonce());
igual('csp: nonce de 16 bytes em base64', strlen(base64_decode(site_csp_nonce(), true)), 16);
verdade('csp: sem object nem iframe de fora', strpos($politica, "object-src 'none'") !== false && strpos($politica, "frame-ancestors 'self'") !== false);
// S03: jsDelivr só no caminho do Bootstrap; cdnjs fora dos scripts.
verdade('csp: script-src sem jsdelivr inteiro', preg_match('#https://cdn\.jsdelivr\.net(\s|$)#', $script_src[1] ?? '') === 0);
verdade('csp: script-src com o caminho do bootstrap', strpos($script_src[1] ?? '', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/') !== false);
verdade('csp: script-src sem cdnjs', strpos($script_src[1] ?? '', 'cdnjs.cloudflare.com') === false);
verdade('csp: estilos seguem com o cdnjs', preg_match('/style-src [^;]*cdnjs\.cloudflare\.com/', $politica) === 1);

// ---- Ambiente local (S15) ----
$server_name_antes = $_SERVER['SERVER_NAME'];
$render_antes = getenv('RENDER');
$_SERVER['SERVER_NAME'] = 'localhost';
putenv('RENDER');
verdade('local: localhost fora do Render', site_ambiente_local());
putenv('RENDER=true');
verdade('local: localhost no Render não é local', !site_ambiente_local());
$_SERVER['SERVER_NAME'] = $server_name_antes;
putenv($render_antes === false ? 'RENDER' : 'RENDER=' . $render_antes);
verdade('local: domínio do Render não é local', !site_ambiente_local());

// ---- Dinheiro ----
igual('valor: vírgula', site_valor_para_centavos('39,90'), 3990);
igual('valor: milhar e vírgula', site_valor_para_centavos('1.290,00'), 129000);
igual('valor: ponto decimal', site_valor_para_centavos('39.90'), 3990);
igual('valor: só milhar', site_valor_para_centavos('1.000'), 100000);
igual('valor: ponto com 2 casas é decimal', site_valor_para_centavos('1.50'), 150);
igual('valor: com R$ e espaço', site_valor_para_centavos('R$ 50'), 5000);
igual('valor: texto não é valor', site_valor_para_centavos('abc'), 0);
igual('valor: negativo não é valor', site_valor_para_centavos('-10'), 0);
igual('asaas: netValue', asaas_valor_liquido_centavos(array('netValue' => 48.51)), 4851);
igual('asaas: sem netValue', asaas_valor_liquido_centavos(array('value' => 50)), null);

// ---- Financeiro: divisão do lucro ----
$mov = array_merge(financeiro_linha_vazia(), array(
    'assinaturas_bruto' => 10000, 'assinaturas_liquido' => 9500,
    'missoes_bruto' => 4000, 'missoes_liquido' => 3800, 'missoes_manual_equipe' => 200,
    'receitas' => 500, 'custos' => 2000, 'repasses_missao' => 1000, 'repasses_igreja' => 3000,
));
$junto = financeiro_divisao($mov, 50, true);
igual('financeiro junto: taxas', $junto['taxas'], 700);
igual('financeiro junto: bruto', $junto['bruto'], 14700);
igual('financeiro junto: lucro (9500+3800+200+500-2000-1000)', $junto['lucro'], 11000);
igual('financeiro junto: igreja 50%', $junto['parte_igreja'], 5500);
igual('financeiro junto: equipe', $junto['parte_equipe'], 5500);
igual('financeiro junto: sem caixa de missões', $junto['caixa_missoes'], null);
$separado = financeiro_divisao($mov, 50, false);
igual('financeiro separado: lucro (9500+500-2000)', $separado['lucro'], 8000);
igual('financeiro separado: caixa de missões (3800+200-1000)', $separado['caixa_missoes'], 3000);
igual('financeiro separado: igreja 30%', financeiro_divisao($mov, 30, false)['parte_igreja'], 2400);
$prejuizo = financeiro_divisao(array_merge(financeiro_linha_vazia(), array('custos' => 1500)), 50, true);
igual('financeiro prejuízo: lucro negativo', $prejuizo['lucro'], -1500);
igual('financeiro prejuízo: igreja não recebe', $prejuizo['parte_igreja'], 0);
igual('financeiro prejuízo: equipe absorve', $prejuizo['parte_equipe'], -1500);
igual('financeiro: soma meses', financeiro_somar(array(array('custos' => 100), array('custos' => 250)))['custos'], 350);
$mov_c = array_merge($mov, array('contribuicoes_bruto' => 1000, 'contribuicoes_liquido' => 950, 'contribuicoes_manual_equipe' => 100, 'contribuicoes_manual_igreja' => 400));
igual('financeiro contribuições: bruto (+1000+100)', financeiro_divisao($mov_c, 50, true)['bruto'], 15800);
igual('financeiro contribuições: taxas (+50)', financeiro_divisao($mov_c, 50, true)['taxas'], 750);
igual('financeiro contribuições: no lucro junto (+950+100)', financeiro_divisao($mov_c, 50, true)['lucro'], 12050);
igual('financeiro contribuições: no lucro separado, fora do caixa', array(financeiro_divisao($mov_c, 50, false)['lucro'], financeiro_divisao($mov_c, 50, false)['caixa_missoes']), array(9050, 3000));

// ---- Missões ----
igual('missão: referência válida', missao_id_da_referencia('missao_42'), 42);
igual('missão: referência de assinatura', missao_id_da_referencia('fiel_42'), 0);
igual('missão: nome curto', missao_nome_publico('Maria Souza Lima'), 'Maria L.');
igual('missão: nome único', missao_nome_publico('Joana'), 'Joana');
igual('missão: nome vazio', missao_nome_publico('  '), 'Apoiador');
igual('missão: rascunho', missao_situacao(array('idmp_status' => 'rascunho', 'idmp_data_fim' => null)), 'rascunho');
igual('missão: aberto sem data', missao_situacao(array('idmp_status' => 'ativo', 'idmp_data_fim' => null)), 'aberto');
igual('missão: data de fim passada encerra', missao_situacao(array('idmp_status' => 'ativo', 'idmp_data_fim' => '2000-01-01')), 'encerrado');
igual('missão: termina hoje ainda aberto', missao_situacao(array('idmp_status' => 'ativo', 'idmp_data_fim' => site_hoje())), 'aberto');
igual('missão: status pago', missao_status_info('CONFIRMED')[0], 'Confirmada');
igual('missão: status vencido', missao_status_info('OVERDUE')[0], 'Vencida');

// ---- Grupos de permissões ----
igual('permissões: lista limpa', admin_telas_da_lista(' cursos,xyz,conteudos,cursos,financeiro,financeiro.resumo,financeiro.xyz'), array('cursos', 'conteudos', 'financeiro', 'financeiro.resumo'));
igual('permissões: lista vazia', admin_telas_da_lista(''), array());
igual('permissões: script da tela', admin_tela_do_script('admin_editar_curso.php'), 'cursos');
igual('permissões: upload de vídeo é da tela de vídeos', admin_tela_do_script('r2_upload_url.php'), 'conteudos');
igual('permissões: dashboard é livre', admin_tela_do_script('dashboard.php'), null);
verdade('permissões: todo menu do admin tem tela', count(array_diff(array('cursos', 'conteudos', 'transmissao', 'aprovar', 'testemunhos', 'desafios', 'devocionais', 'funcoes', 'progresso', 'engajamento', 'assinaturas', 'missoes', 'permissoes', 'usuarios', 'mensagem'), array_keys(admin_telas()))) === 0);
$sessao_antes = $_SESSION;
$_SESSION = array('Usuario_tipo' => 'fiel');
verdade('permissões: fiel não abre tela do painel', !admin_pode_abrir_script('admin_cursos.php'));
$_SESSION = $sessao_antes;

// ---- Tipos de fiel ----
igual('tipos de fiel: lista limpa', fiel_permissoes_da_lista('nome, xyz,foto,nome'), array('nome', 'foto'));
igual('tipos de fiel: sem banco vale o de antes', fiel_permissoes(false, 1), array('dados', 'foto', 'membro'));
igual('tipos de fiel: tela no painel', admin_tela_do_script('admin_tipos_fiel.php'), 'tipos_fiel');

// ---- Destaques da home ----
verdade('destaques: página do app', home_destaque_link_valido('contribuir.php'));
verdade('destaques: projeto', home_destaque_link_valido('missao.php?id=3'));
verdade('destaques: https', home_destaque_link_valido('https://exemplo.com/ajuda'));
verdade('destaques: recusa http', !home_destaque_link_valido('http://exemplo.com'));
verdade('destaques: recusa javascript', !home_destaque_link_valido('javascript:alert(1)'));
verdade('destaques: recusa outra pasta', !home_destaque_link_valido('../admin/admin_financeiro.php'));
verdade('destaques: recusa aspas', !home_destaque_link_valido('https://x.com/"onmouseover=1'));
$arte = array('idhd_ativo' => 1, 'idhd_inicio' => null, 'idhd_fim' => null);
igual('destaques: sem período fica no ar', home_destaque_situacao($arte, '2026-10-22'), 'no_ar');
igual('destaques: guardada', home_destaque_situacao(array('idhd_ativo' => 0) + $arte, '2026-10-22'), 'guardada');
igual('destaques: começa amanhã', home_destaque_situacao(array('idhd_inicio' => '2026-10-23') + $arte, '2026-10-22'), 'agendada');
igual('destaques: começa hoje', home_destaque_situacao(array('idhd_inicio' => '2026-10-22') + $arte, '2026-10-22'), 'no_ar');
igual('destaques: termina hoje ainda vale', home_destaque_situacao(array('idhd_fim' => '2026-10-22') + $arte, '2026-10-22'), 'no_ar');
igual('destaques: terminou ontem', home_destaque_situacao(array('idhd_fim' => '2026-10-21') + $arte, '2026-10-22'), 'expirada');
igual('destaques: tela no painel', admin_tela_do_script('admin_destaques_home.php'), 'destaques');
igual('destaques: sem banco não há artes', home_destaques(false), array());
verdade('destaques: arte sem imagem fica de fora', home_destaque_slide(array('idhd_titulo' => 'X', 'idhd_imagem_url' => '')) === null);
$slide = home_destaque_slide(array('idhd_titulo' => 'Ajude', 'idhd_imagem_url' => 'https://exemplo.com/a.png', 'idhd_link' => 'javascript:alert(1)'));
igual('destaques: link inválido vira sem clique', $slide['link'], '');
igual('destaques: arte do repositório', home_destaque_imagem_url('assets/img/destaques/contribuicao-coracao.jpg'), '../../assets/img/destaques/contribuicao-coracao.jpg');
verdade('destaques: recusa caminho fora da pasta', home_destaque_imagem_url('assets/img/destaques/../../php/conexao.php') === null);
verdade('destaques: recusa outra pasta de assets', home_destaque_imagem_url('assets/logo.png') === null);
verdade('destaques: arte do repositório existe', is_file(__DIR__ . '/../assets/img/destaques/contribuicao-coracao-celular.jpg'));

// ---- Pix direto (QR Code estático) ----
igual('pix qr: resposta como nos exemplos', asaas_pix_qr_da_resposta(array('id' => 'QRC123', 'encodedImage' => 'iVBORw0KGgo=', 'payload' => '00020126...')), array('id' => 'QRC123', 'imagem' => 'iVBORw0KGgo=', 'payload' => '00020126...'));
igual('pix qr: campos trocados como na descrição', asaas_pix_qr_da_resposta(array('id' => 'iVBORw0KGgo=', 'encodedImage' => 'QRC123', 'payload' => '000201')), array('id' => 'QRC123', 'imagem' => 'iVBORw0KGgo=', 'payload' => '000201'));
verdade('pix qr: sem payload', asaas_pix_qr_da_resposta(array('id' => 'QRC123', 'encodedImage' => 'iVBORw0KGgo=')) === null);
verdade('pix qr: sem imagem', asaas_pix_qr_da_resposta(array('id' => 'QRC123', 'encodedImage' => 'XYZ', 'payload' => '000201')) === null);
verdade('pix qr: erro do Asaas', asaas_pix_qr_da_resposta(array('errors' => array())) === null);
igual('capa: arte do repositório', missao_capa_url('assets/img/destaques/ajude-o-site-crescer.jpg'), '../../assets/img/destaques/ajude-o-site-crescer.jpg');
verdade('capa: recusa caminho fora da pasta', missao_capa_url('assets/../php/conexao.php') === null);

// ---- O que cada projeto mostra e filtros das contribuições ----
igual('exibição: sem a seção 78, tudo como antes', missao_exibicao(array('idmp_mostrar_valores' => 0)), array('total' => true, 'apoiadores' => true, 'quadro' => true, 'valores' => false));
igual('exibição: oferta geral sem total', missao_exibicao(array('idmp_mostrar_total' => 0, 'idmp_mostrar_apoiadores' => 1, 'idmp_mostrar_quadro' => 1, 'idmp_mostrar_valores' => 1)), array('total' => false, 'apoiadores' => true, 'quadro' => true, 'valores' => true));
igual('exibição: sem quadro não mostra valores', missao_exibicao(array('idmp_mostrar_quadro' => 0, 'idmp_mostrar_valores' => 1))['valores'], false);
igual('filtros: vazios', missao_contribuicoes_filtros(array()), array('projeto' => 0, 'situacao' => '', 'origem' => '', 'forma' => '', 'de' => '', 'ate' => '', 'busca' => ''));
igual('filtros: válidos', missao_contribuicoes_filtros(array('projeto' => '3', 'situacao' => 'pagas', 'origem' => 'qrcode', 'forma' => 'PIX', 'de' => '2026-10-01', 'ate' => '2026-10-31', 'busca' => ' maria ')), array('projeto' => 3, 'situacao' => 'pagas', 'origem' => 'qrcode', 'forma' => 'PIX', 'de' => '2026-10-01', 'ate' => '2026-10-31', 'busca' => 'maria'));
igual('filtros: inválidos somem', missao_contribuicoes_filtros(array('projeto' => '-2', 'situacao' => 'x', 'origem' => "app' OR 1=1", 'forma' => 'BOLETO', 'de' => '2026-02-31', 'ate' => 'ontem')), array('projeto' => 0, 'situacao' => '', 'origem' => '', 'forma' => '', 'de' => '', 'ate' => '', 'busca' => ''));
igual('contribuições: tela restrita', admin_tela_do_script('admin_contribuicoes.php'), 'contribuicoes');
igual('financeiro: tela restrita', admin_tela_do_script('admin_financeiro.php'), 'financeiro');
verdade('financeiro: admin comum não acessa', !admin_pode_acessar('financeiro') && !financeiro_pode_ver('resumo'));
verdade('contribuições: admin comum não acessa', !admin_pode_acessar('contribuicoes'));

// ---- Assinaturas abertas ou fechadas ----
verdade('assinaturas: fechadas por padrão', !assinaturas_abertas());
verdade('assinaturas: fechadas escondem o módulo', !assinatura_modulo_ativo());
igual('assinaturas: fechadas ninguém é barrado', assinatura_modo(), 'livre');
verdade('assinaturas: fechadas liberam curso pago', assinatura_curso_liberado(false, 0));

// ---- Filiais ----
$filiais_teste = array(
    25 => array('id' => 25, 'nome' => 'São Paulo', 'ativa' => true),
    19 => array('id' => 19, 'nome' => 'Rio de Janeiro', 'ativa' => false),
);
$v = fiel_filial_validar('25', $filiais_teste);
igual('filiais: escolhe uma ativa', array($v['erro'], $v['filial_id'], $v['sem_filial'], $v['nome']), array(null, 25, 0, 'São Paulo'));
$v = fiel_filial_validar(FILIAL_NENHUMA, $filiais_teste);
igual('filiais: nenhuma filial', array($v['erro'], $v['filial_id'], $v['sem_filial'], $v['nome']), array(null, null, 1, 'Nenhuma filial'));
verdade('filiais: vazio pede a escolha', fiel_filial_validar('', $filiais_teste)['erro'] !== null);
verdade('filiais: id inexistente', fiel_filial_validar('999', $filiais_teste)['erro'] !== null);
verdade('filiais: texto qualquer', fiel_filial_validar('25 OR 1=1', $filiais_teste)['erro'] !== null);
verdade('filiais: desativada recusa quem não tem', fiel_filial_validar('19', $filiais_teste)['erro'] !== null);
verdade('filiais: desativada vale para quem já tem', fiel_filial_validar('19', $filiais_teste, '19')['erro'] === null);
igual('filiais: escolha atual com filial', fiel_filial_escolha_atual(25, 0), '25');
igual('filiais: escolha atual nenhuma', fiel_filial_escolha_atual(null, 1), FILIAL_NENHUMA);
igual('filiais: escolha atual não informada', fiel_filial_escolha_atual(null, 0), '');
igual('filiais: rótulo da filial', fiel_filial_rotulo('São Paulo', 0, 'sp'), 'São Paulo');
igual('filiais: rótulo nenhuma', fiel_filial_rotulo('', 1), 'Nenhuma filial');
igual('filiais: rótulo do texto antigo', fiel_filial_rotulo('', 0, ' Campinas '), 'Campinas');
igual('filiais: tela no painel', admin_tela_do_script('admin_filiais.php'), 'filiais');
igual('filiais: sem banco não há lista', filiais_listar(false), array());

// ---- Certificado de boas-vindas (arte em PDF com nome e data) ----
// Falha aqui = a arte assets/Certificados/BoasVindas.pdf mudou: refazer as
// medidas de certificado_boas_vindas_pdf().
$cert_pdf = certificado_boas_vindas_pdf('João (Teste)', '2026-09-20');
verdade('certificado: gera o PDF a partir da arte', is_string($cert_pdf) && strpos($cert_pdf, '%PDF-') === 0 && substr(rtrim($cert_pdf), -5) === '%%EOF');
verdade('certificado: nome em Windows-1252 e escapado', is_string($cert_pdf) && strpos($cert_pdf, "(Jo\xE3o \\(Teste\\)) Tj") !== false);
verdade('certificado: data de ingresso real', is_string($cert_pdf) && strpos($cert_pdf, '(20 de setembro de 2026) Tj') !== false);
igual('certificado: largura na Times', certificado_pdf_largura_times("Jo\xE3o"), 389 + 500 + 444 + 500);

// ---- Uso pelo app instalado (medalha "No bolso") ----
$sessao_antes = $_SESSION;
$_SESSION['acesso_app'] = array('fiel' => 7, 'data' => site_hoje());
verdade('app: registrado hoje para a mesma conta', acesso_app_registrado_hoje(7));
verdade('app: outra conta no mesmo navegador não herda', !acesso_app_registrado_hoje(8));
$_SESSION['acesso_app'] = array('fiel' => 7, 'data' => '2000-01-01');
verdade('app: registro de outro dia não vale hoje', !acesso_app_registrado_hoje(7));
$_SESSION = $sessao_antes;
verdade('app: sem banco não registra', !acesso_app_registrar(false, 7) && !acesso_app_disponivel(false));

// ---- Primeiros passos (missões de boas-vindas) ----
$pp_teste = array(
    'foto' => array('grupo' => 'espaco', 'titulo' => 'Foto', 'descricao' => '', 'icone' => 'fa-camera', 'link' => 'perfil.php', 'feito' => true),
    'app' => array('grupo' => 'espaco', 'titulo' => 'App', 'descricao' => '', 'icone' => 'fa-mobile', 'link' => null, 'feito' => false),
    'temas' => array('grupo' => 'espaco', 'titulo' => 'Temas', 'descricao' => '', 'icone' => 'fa-heart', 'link' => 'configuracoes.php', 'feito' => false),
    'aula' => array('grupo' => 'app', 'titulo' => 'Aula', 'descricao' => '', 'icone' => 'fa-play', 'link' => 'cursos.php', 'feito' => true),
);
$pp_r = primeiros_passos_resumo($pp_teste);
igual('passos: total e feitos', array($pp_r['total'], $pp_r['feitos']), array(4, 2));
igual('passos: próxima pula a missão sem atalho (desafio do app)', $pp_r['proxima'], 'temas');
igual('passos: grupo "Conheça o app" completo', $pp_r['grupos']['app'], array('total' => 1, 'feitos' => 1));
verdade('passos: ainda não completo', !$pp_r['completo']);
$pp_teste['temas']['feito'] = true;
igual('passos: só sobrou a sem atalho, ela vira a próxima', primeiros_passos_resumo($pp_teste)['proxima'], 'app');
$pp_teste['app']['feito'] = true;
verdade('passos: tudo feito = completo, sem próxima', primeiros_passos_resumo($pp_teste)['completo'] && primeiros_passos_resumo($pp_teste)['proxima'] === null);
verdade('passos: lista vazia não conta como completa', !primeiros_passos_resumo(array())['completo']);
verdade('passos: sem banco não há missões', primeiros_passos(false, 7) === array());
verdade('capa: padrão sem classe e opções com classe', perfil_capas()['padrao'][1] === '' && perfil_capas()['aurora'][1] === 'capa-aurora');
igual('capa: sem banco é a padrão', perfil_capa_do_fiel(false, 7), 'padrao');

// ---- Mais medalhas, secretas e ostentar (seção 81) ----
$agora_teste = strtotime('2026-10-23 12:00:00');
igual('tempo: agora há pouco', site_tempo_relativo('2026-10-23 11:59:30', $agora_teste), 'agora há pouco');
igual('tempo: minutos', site_tempo_relativo('2026-10-23 11:45:00', $agora_teste), 'há 15 min');
igual('tempo: horas', site_tempo_relativo('2026-10-23 09:00:00', $agora_teste), 'há 3 h');
igual('tempo: ontem', site_tempo_relativo('2026-10-22 10:00:00', $agora_teste), 'ontem');
igual('tempo: dias', site_tempo_relativo('2026-10-19 12:00:00', $agora_teste), 'há 4 dias');
igual('tempo: mais de uma semana vira data', site_tempo_relativo('2026-10-01 12:00:00', $agora_teste), '01/10');
igual('tempo: data inválida', site_tempo_relativo('ontem à tarde?', $agora_teste), '');
igual('raridade: ninguém tem', medalhas_raridade_texto(null), 'Ninguém tem ainda — seja o primeiro!');
igual('raridade: rara com decimal', medalhas_raridade_texto(4.5), 'Rara · 4,5% dos fiéis têm');
igual('raridade: rara redonda sem ",0"', medalhas_raridade_texto(3.0), 'Rara · 3% dos fiéis têm');
igual('raridade: comum', medalhas_raridade_texto(37.4), '37% dos fiéis têm');
igual('raridade: menos de 1%', medalhas_raridade_texto(0.4), 'Rara · menos de 1% dos fiéis têm');
verdade('medalhas: categorias novas na ordem', array_slice(array_keys(medalhas_categorias()), 0, 4) === array('primeiros_passos', 'crescimento', 'estudos', 'comentarios') && array_key_last(medalhas_categorias()) === 'secretas');
verdade('medalhas: secretas e "sim ou não" sem barra', in_array('madrugador', medalhas_regras_sem_barra(), true) && !in_array('aulas_concluidas', medalhas_regras_sem_barra(), true));
verdade('vitrine: sem banco fica vazia', fiel_vitrine(false, 7) === array() && fiel_vitrine_alternar(false, 7, 1) !== '');

// ---- Cache compartilhado (chat e status da live) ----
site_cache_apagar('teste_cache');
$cache_chamadas = 0;
$cache_gerar = function () use (&$cache_chamadas) { $cache_chamadas++; return array('n' => $cache_chamadas); };
igual('cache: primeira vez gera', site_cache_compartilhado('teste_cache_a', 60, $cache_gerar), array('n' => 1));
igual('cache: dentro do prazo não gera de novo', site_cache_compartilhado('teste_cache_a', 60, $cache_gerar), array('n' => 1));
igual('cache: prazo 0 gera de novo', site_cache_compartilhado('teste_cache_a', 0, $cache_gerar), array('n' => 2));
igual('cache: false (erro de banco) não fica guardado', site_cache_compartilhado('teste_cache_b', 60, function () { return false; }), false);
igual('cache: depois do erro, gera', site_cache_compartilhado('teste_cache_b', 60, function () { return 7; }), 7);
igual('cache: lista vazia fica guardada', site_cache_compartilhado('teste_cache_c', 60, function () { return array(); }), array());
igual('cache: lista vazia vem do cache', site_cache_compartilhado('teste_cache_c', 60, function () { return array('x'); }), array());
site_cache_apagar('teste_cache');
igual('cache: apagar por prefixo', site_cache_compartilhado('teste_cache_a', 60, $cache_gerar), array('n' => 3));
site_cache_vencer('teste_cache_a');
igual('cache: vencido gera de novo', site_cache_compartilhado('teste_cache_a', 60, $cache_gerar), array('n' => 4));
// Vencido e com outro pedido gerando (trava pega): devolve a cópia anterior.
site_cache_vencer('teste_cache_a');
$trava_teste = fopen(site_cache_arquivo('teste_cache_a') . '.trava', 'c');
flock($trava_teste, LOCK_EX);
igual('cache: vencido com outro gerando devolve a cópia anterior', site_cache_compartilhado('teste_cache_a', 60, $cache_gerar), array('n' => 4));
flock($trava_teste, LOCK_UN);
fclose($trava_teste);
site_cache_apagar('teste_cache');
site_cache_apagar('intervalo_teste');
verdade('intervalo: primeira vez pode', site_intervalo_livre('teste', 60));
verdade('intervalo: logo depois não pode', !site_intervalo_livre('teste', 60));
verdade('intervalo: com prazo 0 pode de novo', site_intervalo_livre('teste', 0));
site_cache_apagar('intervalo_teste');
verdade('cache: nome de arquivo sem caminho', strpos(basename(site_cache_arquivo('../x/y')), '/') === false && strpos(site_cache_arquivo('../x'), '..') === false);
site_cache_apagar('teste_cache');

// ---- Verificação em duas etapas (seção 84) ----
// Vetores da RFC 6238 (SHA-1, chave "12345678901234567890"), 6 dígitos.
$totp_chave = totp_base32_codificar('12345678901234567890');
igual('2fa: base32 da chave da RFC', $totp_chave, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
igual('2fa: base32 ida e volta', totp_base32_decodificar(strtolower(chunk_split($totp_chave, 4, ' '))), '12345678901234567890');
igual('2fa: base32 inválido', totp_base32_decodificar('ABC1!'), false);
igual('2fa: RFC t=59', totp_codigo($totp_chave, (int) floor(59 / 30)), '287082');
igual('2fa: RFC t=1111111109', totp_codigo($totp_chave, (int) floor(1111111109 / 30)), '081804');
igual('2fa: RFC t=1234567890', totp_codigo($totp_chave, (int) floor(1234567890 / 30)), '005924');
igual('2fa: RFC t=2000000000', totp_codigo($totp_chave, (int) floor(2000000000 / 30)), '279037');
igual('2fa: confere e devolve o passo', totp_conferir($totp_chave, '081 804', 1111111109), 37037036);
igual('2fa: aceita o passo anterior (relógio atrasado)', totp_conferir($totp_chave, '081804', 1111111109 + 30), 37037036);
igual('2fa: recusa dois passos atrás', totp_conferir($totp_chave, '081804', 1111111109 + 60), false);
igual('2fa: mesmo código não vale duas vezes', totp_conferir($totp_chave, '081804', 1111111109, 37037036), false);
igual('2fa: código com letra', totp_conferir($totp_chave, '08180a', 1111111109), false);
verdade('2fa: segredo novo tem 32 letras base32', (bool) preg_match('/^[A-Z2-7]{32}$/', totp_novo_segredo()));
verdade('2fa: uri do app', strpos(totp_uri('ABC', 'a@b.com', 'Manancial'), 'otpauth://totp/Manancial%3Aa%40b.com?secret=ABC&issuer=Manancial') === 0);
// Chave cifrada com a APP_CHAVE.
$_ENV['APP_CHAVE'] = '';
igual('2fa: sem APP_CHAVE grava como antes', doisfa_cifrar_segredo($totp_chave, 7), $totp_chave);
$_ENV['APP_CHAVE'] = str_repeat('a', 31);
igual('2fa: APP_CHAVE curta não vale', doisfa_cifrar_segredo($totp_chave, 7), $totp_chave);
$_ENV['APP_CHAVE'] = 'chave-de-teste-com-mais-de-32-letras!';
$totp_cifrada = doisfa_cifrar_segredo($totp_chave, 7);
verdade('2fa: cifrada cabe na coluna (64)', strlen($totp_cifrada) === 64 && doisfa_segredo_cifrado($totp_cifrada));
verdade('2fa: cifrada não mostra a chave', strpos($totp_cifrada, $totp_chave) === false && $totp_cifrada !== doisfa_cifrar_segredo($totp_chave, 7));
verdade('2fa: chave antiga não parece cifrada', !doisfa_segredo_cifrado($totp_chave));
igual('2fa: decifra', doisfa_decifrar_segredo($totp_cifrada, 7), $totp_chave);
igual('2fa: chave antiga passa direto', doisfa_decifrar_segredo($totp_chave, 7), $totp_chave);
igual('2fa: de outra conta não decifra', doisfa_decifrar_segredo($totp_cifrada, 8), '');
igual('2fa: adulterada não decifra', doisfa_decifrar_segredo(substr_replace($totp_cifrada, $totp_cifrada[40] === 'A' ? 'B' : 'A', 40, 1), 7), '');
$_ENV['APP_CHAVE'] = 'outra-chave-de-teste-com-mais-de-32-letras';
igual('2fa: APP_CHAVE trocada não decifra', doisfa_decifrar_segredo($totp_cifrada, 7), '');
$_ENV['APP_CHAVE'] = '';
igual('2fa: sem APP_CHAVE a cifrada não decifra', doisfa_decifrar_segredo($totp_cifrada, 7), '');
igual('2fa: chave vazia não confere código', totp_conferir('', '000000'), false);
$reserva_teste = doisfa_gerar_reserva();
igual('2fa: 8 códigos de reserva', count($reserva_teste['codigos']), 8);
verdade('2fa: formato xxxx-xxxx', (bool) preg_match('/^[0-9a-f]{4}-[0-9a-f]{4}$/', $reserva_teste['codigos'][0]));
verdade('2fa: banco guarda só o hash', strpos($reserva_teste['json'], $reserva_teste['codigos'][0]) === false);
$reserva_usada = doisfa_usar_reserva($reserva_teste['json'], strtoupper(str_replace('-', '', $reserva_teste['codigos'][2])));
verdade('2fa: código de reserva vale (sem traço, maiúsculo)', $reserva_usada !== false && count(json_decode($reserva_usada, true)) === 7);
igual('2fa: código de reserva usado não vale de novo', doisfa_usar_reserva($reserva_usada, $reserva_teste['codigos'][2]), false);
igual('2fa: código de reserva errado', doisfa_usar_reserva($reserva_teste['json'], '0000-0000'), false);

// ---- LGPD (seção 82) ----
igual('idade: completa no aniversário', site_idade('2008-10-27', '2026-10-27'), 18);
igual('idade: véspera do aniversário', site_idade('2008-10-28', '2026-10-27'), 17);
igual('idade: sem data', site_idade('', '2026-10-27'), null);
igual('idade: data no futuro', site_idade('2030-01-01', '2026-10-27'), null);
verdade('menor: 17 anos', lgpd_eh_menor('2009-01-01', '2026-10-27'));
verdade('menor: 18 anos não é', !lgpd_eh_menor('2008-01-01', '2026-10-27'));
verdade('menor: sem data não é', !lgpd_eh_menor(null, '2026-10-27'));
verdade('cadastro: sem aceite recusa', lgpd_validar_cadastro(false, null, '', false) !== '');
igual('cadastro: adulto com aceite', lgpd_validar_cadastro(true, '1990-05-01', '', false), '');
$nasc_menor = date('Y-m-d', strtotime('-15 years'));
verdade('cadastro: menor sem responsável recusa', lgpd_validar_cadastro(true, $nasc_menor, '', true) !== '');
verdade('cadastro: menor com nome de uma palavra recusa', lgpd_validar_cadastro(true, $nasc_menor, 'Maria', true) !== '');
verdade('cadastro: menor sem autorização recusa', lgpd_validar_cadastro(true, $nasc_menor, 'Maria Souza', false) !== '');
igual('cadastro: menor com responsável e autorização', lgpd_validar_cadastro(true, $nasc_menor, 'Maria Souza', true), '');
verdade('exportação: esconde senha, token, 2FA e foto', lgpd_coluna_oculta('idf_senha_hash') && lgpd_coluna_oculta('ids_token') && lgpd_coluna_oculta('idf_2fa_segredo') && lgpd_coluna_oculta('idff_dados'));
verdade('exportação: mostra nome e e-mail', !lgpd_coluna_oculta('idf_nome') && !lgpd_coluna_oculta('idf_email'));
igual('busca: escapa curingas do LIKE', site_like_escapar('50%_a\\b'), '50\\%\\_a\\\\b');

// ---- Aviso de aparelho novo (Pacote A) ----
igual('aparelho: Chrome no Android', site_aparelho_descricao('Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36'), 'Chrome no Android');
igual('aparelho: Safari no iPhone', site_aparelho_descricao('Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1'), 'Safari no iPhone');
igual('aparelho: Chrome no iPhone', site_aparelho_descricao('Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/129.0 Mobile/15E148 Safari/604.1'), 'Chrome no iPhone');
igual('aparelho: Edge no Windows (não confunde com Chrome)', site_aparelho_descricao('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36 Edg/129.0'), 'Edge no Windows');
igual('aparelho: Samsung Internet', site_aparelho_descricao('Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/25.0 Chrome/121.0 Mobile Safari/537.36'), 'Samsung Internet no Android');
igual('aparelho: Firefox no Mac', site_aparelho_descricao('Mozilla/5.0 (Macintosh; Intel Mac OS X 14.6; rv:131.0) Gecko/20100101 Firefox/131.0'), 'Firefox no Mac');
igual('aparelho: versão nova do mesmo navegador é o mesmo aparelho', site_aparelho_descricao('Mozilla/5.0 (Linux; Android 15; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Mobile Safari/537.36'), 'Chrome no Android');
igual('aparelho: vazio', site_aparelho_descricao(''), 'outro navegador no outro sistema');

// ---- CAPTCHA (Turnstile) desligado: nada muda ----
// Sem depender da máquina: o .env local pode ter as chaves.
$turnstile_antes = array();
foreach (array('TURNSTILE_SITE_KEY', 'TURNSTILE_SECRET_KEY') as $chave_t) {
    $turnstile_antes[$chave_t] = getenv($chave_t);
    $_ENV[$chave_t] = '';
    putenv($chave_t);
    unset($_SERVER[$chave_t]);
}
verdade('turnstile: desligado sem as chaves', !turnstile_ativo());
igual('turnstile: sem quadrinho na tela', turnstile_campo_html(), '');
verdade('turnstile: desligado sempre deixa passar', turnstile_validar());
$_ENV['TURNSTILE_SITE_KEY'] = 'chave-de-teste';
$_ENV['TURNSTILE_SECRET_KEY'] = 'segredo-de-teste';
verdade('turnstile: com as chaves, mostra o quadrinho', strpos(turnstile_campo_html(), 'data-sitekey="chave-de-teste"') !== false);
$_POST['cf-turnstile-response'] = '';
verdade('turnstile: ligado e sem resposta do quadrinho, recusa', !turnstile_validar());
unset($_POST['cf-turnstile-response']);
foreach ($turnstile_antes as $chave_t => $valor_t) {
    $_ENV[$chave_t] = $valor_t === false ? '' : $valor_t;
    if ($valor_t !== false) {
        putenv($chave_t . '=' . $valor_t);
    }
}
verdade('csp: libera o Turnstile', strpos(site_csp_politica(), 'https://challenges.cloudflare.com') !== false);

// ---- Protetor de ofensiva e meta diária (seção 85) ----
igual('protetor: entrou ontem, nada faltando', ofensiva_dias_faltando('2026-10-26', '2026-10-27'), 0);
igual('protetor: faltou 1 dia', ofensiva_dias_faltando('2026-10-25', '2026-10-27'), 1);
igual('protetor: faltaram 3 dias', ofensiva_dias_faltando('2026-10-23', '2026-10-27'), 3);
igual('protetor: virada de mês', ofensiva_dias_faltando('2026-10-30', '2026-11-01'), 1);
igual('protetor: primeiro acesso da vida', ofensiva_dias_faltando(null, '2026-10-27'), 0);
igual('protetor: data igual ou futura', ofensiva_dias_faltando('2026-10-27', '2026-10-27'), 0);
verdade('protetor: máximo de 2, um a cada 7 dias', OFENSIVA_PROTETORES_MAX === 2 && OFENSIVA_PROTETOR_A_CADA === 7);
verdade('meta: sem banco devolve a meta padrão de 10 min', meta_diaria_hoje(false, 7) === array('feitos' => 0, 'alvo' => 10, 'segundos' => 0));

// ---- Números da home pública ----
igual('home: cursos, aulas e comunidade grande', numeros_publicos_cartoes(array('cursos' => 12, 'aulas' => 140, 'horas' => 90, 'pessoas' => 320)), array(
    array('numero' => '12', 'texto' => 'cursos completos'),
    array('numero' => '140', 'texto' => 'aulas em vídeo'),
    array('numero' => '320', 'texto' => 'pessoas na comunidade'),
));
igual('home: comunidade pequena vira horas de conteúdo', numeros_publicos_cartoes(array('cursos' => 1, 'aulas' => 1, 'horas' => 3, 'pessoas' => 12))[2], array('numero' => '3 h', 'texto' => 'de conteúdo'));
igual('home: singular', numeros_publicos_cartoes(array('cursos' => 1, 'aulas' => 1, 'horas' => 0, 'pessoas' => 0)), array(
    array('numero' => '1', 'texto' => 'curso completo'),
    array('numero' => '1', 'texto' => 'aula em vídeo'),
));
igual('home: sem nada publicado, nenhum número', numeros_publicos_cartoes(array('cursos' => 0, 'aulas' => 0, 'horas' => 0, 'pessoas' => 5)), array());

// ---- Versão dos CSS/JS (cache de 1 ano com ?v=) ----
verdade('estáticos: versão sem caractere estranho', (bool) preg_match('/^[a-z0-9]+$/i', site_versao_estatico()));
verdade('compartilhar: imagem 1200x630 existe', is_file(__DIR__ . '/../assets/img/compartilhar.jpg') && getimagesize(__DIR__ . '/../assets/img/compartilhar.jpg')[0] === 1200);

// ---- Relógio do fiel (seção 87) ----
igual('relógio: primeiro envio do dia libera só a folga', relogio_calcular(null, 0, 500), array(0, 0, 60));
igual('relógio: 30 s a 1x avança 30 e guarda o resto', relogio_calcular(30, 0, 30), array(30, 45, 30));
igual('relógio: conta no máximo 40 s por envio', relogio_calcular(3600, 0, 0)[0], 40);
igual('relógio: saldo para em 10 min', relogio_calcular(40, 590, 0)[1], RELOGIO_SALDO_MAX);
// Duas aulas em paralelo: o 2º envio chega 1 s depois do 1º e quase não ganha nada.
list(, $saldo_par) = relogio_calcular(30, 0, 75);
igual('relógio: aula em paralelo não ganha tempo novo', relogio_calcular(1, $saldo_par, 75)[2], 3);
igual('relógio: avanço negativo não gasta saldo', relogio_calcular(30, 0, -50), array(30, 75, 0));

// ---- Menor de idade nas Configurações ----
$nasc_menor = date('Y-m-d', strtotime('-15 years'));
$nasc_adulto = date('Y-m-d', strtotime('-30 years'));
igual('lgpd: data informada não pode ser apagada', lgpd_nascimento_bloqueio($nasc_adulto, '', ''), 'A data de nascimento não pode ficar em branco.');
verdade('lgpd: virar menor sem responsável é recusado', lgpd_nascimento_bloqueio($nasc_adulto, '', $nasc_menor) !== '');
igual('lgpd: menor com responsável pode corrigir a data', lgpd_nascimento_bloqueio($nasc_menor, 'Maria da Silva', date('Y-m-d', strtotime('-16 years'))), '');
igual('lgpd: adulto troca a data à vontade', lgpd_nascimento_bloqueio($nasc_adulto, '', date('Y-m-d', strtotime('-40 years'))), '');
igual('lgpd: sem data antes e sem data agora, tudo bem', lgpd_nascimento_bloqueio(null, '', ''), '');

// ---- Identidade da igreja (Pacote H) ----
igual('igreja: link sem https é recusado', igreja_validar_campo('instagram', 'instagram.com/igreja')[1] !== '', true);
igual('igreja: link https passa', igreja_validar_campo('instagram', 'https://instagram.com/igreja'), array('https://instagram.com/igreja', ''));
igual('igreja: e-mail inválido é recusado', igreja_validar_campo('email', 'igreja@') [1] !== '', true);
igual('igreja: CNPJ no formato passa', igreja_validar_campo('cnpj', '12.345.678/0001-90')[1], '');
igual('igreja: CNPJ errado é recusado', igreja_validar_campo('cnpj', '123')[1] !== '', true);
igual('igreja: WhatsApp sem DDD é recusado', igreja_validar_campo('whatsapp', '99999-9999')[1] !== '', true);
igual('igreja: texto passa do limite', igreja_validar_campo('nome', str_repeat('a', 61))[1] !== '', true);
igual('igreja: campo de uma linha junta os espaços', igreja_validar_campo('endereco', "Rua A,\n  123")[0], 'Rua A, 123');
igual('igreja: texto longo mantém as linhas', igreja_validar_campo('horarios', "Domingo 18h\nQuarta 20h")[0], "Domingo 18h\nQuarta 20h");
verdade('igreja: campo desconhecido é recusado', igreja_validar_campo('senha', 'x')[1] !== '');
verdade('igreja: o público nunca leva CNPJ nem razão social', !array_key_exists('cnpj', igreja_publica()) && !array_key_exists('recebedor', igreja_publica()));
verdade('igreja: nome padrão quando ninguém preencheu', igreja_info()['nome'] !== '');

// ---- Variáveis de produção (Pacote F) ----
igual('produção: na homologação não cobra nada', site_config_producao_faltando('mananciapp-render-homolog.onrender.com'), array());
$faltando_prod = site_config_producao_faltando('app.igreja-exemplo.com.br');
verdade('produção: fora da homologação cobra a sessão no banco', isset($faltando_prod['SESSOES_NO_BANCO']) || getenv('SESSOES_NO_BANCO') === '1');
verdade('produção: cobra a APP_CHAVE', isset($faltando_prod['APP_CHAVE']) === (doisfa_chave_cifra() === ''));
verdade('produção: cada falta vem com o motivo', $faltando_prod === array() || count(array_filter($faltando_prod, 'is_string')) === count($faltando_prod));

// ---- Registro das seções do banco (seção 86) ----
// A última seção do bancosupabase.md tem que ser a que o código espera, e
// toda seção da 86 em diante termina se registrando em id_schema_versao.
$banco_md = (string) file_get_contents(__DIR__ . '/../database/bancosupabase.md');
preg_match_all('/^-- (\d+)\. /m', $banco_md, $secoes_md);
$ultima_md = $secoes_md[1] ? max(array_map('intval', $secoes_md[1])) : 0;
igual('banco: BANCO_SECAO_ESPERADA é a última seção do bancosupabase.md', BANCO_SECAO_ESPERADA, $ultima_md);
$sem_registro = array();
foreach (preg_split('/^-- (?=\d+\. )/m', $banco_md) as $trecho) {
    if (preg_match('/^(\d+)\. /', $trecho, $num) && (int) $num[1] > 86
        && !preg_match('/INSERT INTO "id_schema_versao" \("isv_secao"\) VALUES \(' . (int) $num[1] . '\)/', $trecho)) {
        $sem_registro[] = (int) $num[1];
    }
}
igual('banco: toda seção depois da 86 grava o próprio número', $sem_registro, array());
igual('banco: tudo rodado, sem aviso', banco_aviso_secoes(86, 86), '');
igual('banco: falta uma seção', banco_aviso_secoes(85, 86), 'O banco está na seção 85 e este código usa até a 86: rode a seção 86 do database/bancosupabase.md, nessa ordem.');
verdade('banco: faltam várias', strpos(banco_aviso_secoes(86, 89), 'as seções 87 a 89') !== false);
verdade('banco: sem registro pede a 86', strpos(banco_aviso_secoes(null, 86), 'seção 86') !== false);

echo "\n" . ($total - $falhas) . " de $total testes passaram.\n";
exit($falhas > 0 ? 1 : 0);
