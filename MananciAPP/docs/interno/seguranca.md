# Segurança

Documento **vivo**: como o app se protege hoje, o que ainda está aberto e a
rotina de testes que se repete a cada mudança e a cada deploy. Nunca fica
"pronto" — toda rodada de testes, achado novo ou correção entra aqui.

Começou com a auditoria de 23/10/2026 (revisão do código inteiro: painel
admin, área do fiel, páginas públicas e integrações, histórico do git). A
rodada anterior, de 19 a 21/10/2026 (commits `005829d` a `54c9312`), está
resumida na seção 3.

**Regras deste documento:**

- Achado novo entra na seção 2 com o próximo número livre (`S17`, `S18`…),
  severidade, onde está e **como testar**. Nunca reaproveitar um número.
- Corrigiu: o achado sai da seção 2 e vai para a seção 3 com a data e o
  commit. Se a correção tem teste automático, citar qual.
- Toda rodada de testes (seção 4) ganha uma linha na seção 7, com o
  resultado real — inclusive quando algo falhou.
- Nada de senha, chave ou token aqui, nem de exemplo real. Valor de
  segredo vazado: trocar o segredo e anotar só "trocado em dd/mm".
- Este arquivo não vai para o site: `docs/` está no `.dockerignore` e o
  `.htaccess` barra `.md`. O `tests/checar_site.php` confere isso a cada
  rodada.

**Severidade:** Crítico (exploração fácil, dano grande, sem precisar de
conta) · Alto (dano grande, exige conta ou condição simples) · Médio (dano
limitado ou exige conta com privilégio) · Baixo (incômodo, abuso ou defesa
em profundidade) · Info (endurecimento sem risco atual).

---

## Checklist geral

Tudo o que o site precisa ter para ficar seguro, num lugar só: `[x]` existe
e foi conferido; `[ ]` falta. O detalhe de cada item está nas seções
abaixo; aqui é o mapa para a manutenção. Montado em 28/10/2026 a partir
das listas de segurança que circulam (chave protegida, `.env`, RLS, rate
limit, CORS, uptime, backup…), conferidas uma a uma no código. Ao fechar
um `[ ]`, marcar com a data; item novo entra no grupo certo.

### A. No código

**Contas e login**

- [x] Senhas com bcrypt, política mínima e checagem de senha vazada
- [x] Login com a mesma mensagem para e-mail inexistente e senha errada
- [x] Limite de tentativas no login e no 2FA (e-mail + IP, e-mail, IP)
- [x] Sessão revogável no servidor, 1 h parada / 12 h no total; cookie `HttpOnly`, `Secure`, `SameSite`
- [x] "Esqueci minha senha" com token de uso único, só o hash no banco
- [x] Aviso por e-mail de acesso num aparelho novo
- [x] 2FA (TOTP) para admins — **opcional**; ver decisão D1
- [x] CAPTCHA (Turnstile) no cadastro e no "Esqueci minha senha" — [ ] conferir `TURNSTILE_*` preenchidas no Render (sem elas fica desligado)

**Permissões e dados de cada um**

- [x] Permissão conferida no servidor em toda tela e AJAX (`auth_require`, grupos de telas, superadmin)
- [x] O id do fiel vem da sessão, nunca da tela; edição e exclusão filtram pelo dono no SQL
- [x] CSRF em todo POST que altera dado
- [ ] Separação por filial (admin de uma filial não vê outra) — desenho em [filiais_divisao.md](filiais_divisao.md)

**Entrada e saída**

- [x] Validação no servidor (tamanhos, listas fixas, `site_entrada_texto()`)
- [x] SQL com parâmetros (`db_query`); o código antigo só concatena `(int)`
- [x] Texto do usuário escapado na tela (`htmlspecialchars`, `textContent`) e CSP com nonce
- [x] Upload: tipo real conferido, sem SVG, teto de tamanho, vídeo direto ao R2 com tamanho assinado

**Limite de requisições (rate limit)**

- [x] Login, 2FA, cadastro, "Esqueci minha senha", comentários, chat da live, erro de JS, contribuições e consulta ao Asaas
- [x] Comentários respondem `429`
- [ ] Limite geral por IP para qualquer página (S19) — na borda, não no PHP
- [ ] (Opcional) `429` também nas telas de login, cadastro e recuperação bloqueadas (hoje respondem 200 com a mensagem; ajuda só a contar nos logs). `Retry-After` não ajuda formulário de navegador
- Chamada de API de IA: não se aplica (o app não usa IA)

**Cabeçalhos e configuração**

- [x] HSTS, CSP, `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`
- [x] CORS: nenhum cabeçalho `Access-Control-*` — o correto, porque nenhum site de fora chama a API (o navegador bloqueia por padrão). Só abrir se surgir um app/site parceiro, e só para a origem dele
- [x] Debug desligado (`php.ini-production`), erro sem detalhe na tela, sem versão do PHP/Apache
- [x] `.env` fora do git (nunca foi versionado), fora da imagem e barrado no `.htaccess`
- [x] Nenhuma senha ou chave no código nem no histórico do git (varrido em 23 e 28/10/2026)
- [x] Chaves só no servidor; o navegador recebe só o que é público por natureza (app id do OneSignal, site key do Turnstile)

**Banco**

- [x] RLS ligado em todas as tabelas (homologação conferida em 28/10/2026; seção 90 garante na produção; `tests/testes_banco.php` confere) — o navegador nunca fala com o Supabase
- [ ] Usuário do banco (`DB_USER`) só com acesso às tabelas do app (seção 5)
- [ ] (Info) `sslmode=verify-full` em vez de `require`

**Registro e alerta**

- [x] Registro de atividade do painel (quem fez o quê, 2 anos)
- [x] Cartão "Saúde do sistema" no Dashboard (e-mail, push, webhook, rotina diária)
- [x] Alerta de erro (Sentry) no PHP e no JS — [ ] criar a conta e pôr `SENTRY_DSN` no Render (homolog e produção)
- [x] `saude.php?banco=1` para monitor — [ ] criar o monitor no UptimeRobot (palavra `ok`)

### B. Contas e serviços (fora do código)

Quem entra numa destas contas não precisa invadir o site.

- [ ] **2FA ligado** em: Render, Supabase, GitHub, Cloudflare (R2), Asaas, Brevo, OneSignal, Sentry, registro do domínio e nos e-mails dos donos dessas contas
- [ ] Lista de quem tem acesso a cada conta; ninguém com conta compartilhada; tirar quem saiu da equipe
- [ ] **GitHub:** repositório privado; *secret scanning* ligado; proteção da `main` (sem force push)
- [ ] **Backup:** conferir o que o plano do Supabase guarda e **testar uma restauração**; `pg_dump` cifrado fora do Supabase ([plano_de_melhorias.md](plano_de_melhorias.md))
- [ ] **R2:** bucket sem acesso público (só URL assinada); bucket antigo do Supabase Storage apagado ou privado
- [ ] **Brevo:** bloqueio de IP das chaves e uma chave por ambiente
- [ ] **Render:** variáveis de cada serviço (seção 5) e senhas fortes, diferentes entre homologação e produção
- [ ] **Domínio próprio pela Cloudflare** com regra de rate limit e WAF grátis (fecha o S19)
- [x] Senhas das contas de exemplo trocadas na homologação (S01) — [ ] repetir na produção, se o banco for outro

### C. Antes de abrir a produção

- [ ] Rodar as seções pendentes do banco ([pontos_gerais.md](pontos_gerais.md), seção 8), inclusive a 90
- [ ] `tests/checar_site.php` na URL de produção, sem falha
- [ ] Roteiro manual da seção 4.3 com contas de teste
- [ ] Entrar pelo Wi-Fi e pelo 4G/5G e conferir os IPs das sessões (S17)
- [ ] Produção **sem** `X-Robots-Tag: noindex` (só a homologação tem)
- [ ] PageSpeed nas páginas públicas e na home do fiel
- [ ] Search Console com o domínio (só as páginas públicas importam; criar `sitemap.xml`)
- [ ] Revisão completa de segurança depois da divisão por filiais

### D. Decisões pendentes

- [ ] **D1:** 2FA obrigatório para admin com acesso total e para a equipe interna?
- [ ] **D2:** S09 — o limite de 30 erros por e-mail continua bloqueando ou vira só alerta?
- [ ] **D3:** analytics? Se sim, um sem cookie e citado na Política de Privacidade (LGPD)
- [ ] **D4:** itens "Info" da seção 2 (foto de menor em cache, nota do pastor, busca mostrando curso pago)

### E. Rotina

- A cada mudança de código: seção 4.1.
- A cada deploy e uma vez por semana: seção 4.2 (`checar_site.php`).
- Todo mês e antes de cada lançamento: seções 4.3 e 4.4.
- A cada pacote grande de funcionalidade: revisão completa do código, anotada na seção 7.

---

## 1. Como o app se protege hoje

Resumo do que já está no código e foi conferido em 23/10/2026. Ao mexer
num destes pontos, rodar de novo a conferência da seção 4.

| Área | Como funciona | Onde |
|---|---|---|
| Senhas | `password_hash` (bcrypt), `password_verify`, rehash automático; política mínima e checagem de senha vazada (HIBP por k-anonimato) | `php/conexao.php` (`senha_*`), [reset_de_senha.md](reset_de_senha.md) |
| Login | Mesma mensagem para e-mail inexistente e senha errada; limites por e-mail+IP, por IP e por e-mail; status da conta só aparece com a senha certa | `login.php`, `conexao.php` (`login_*`) |
| Sessão | `session_regenerate_id` no login e no reset; 1 h parada ou 12 h no total; revogação no servidor (`ID_SESSAO`) conferida a cada página; troca de papel ou conta desativada derruba a sessão | `conexao.php` (`auth_*`) |
| Cookie | `HttpOnly`, `Secure`, `SameSite=Lax`, `use_strict_mode` | `Dockerfile` |
| Redefinir senha | Token de 256 bits, banco guarda só o SHA-256, uso único, 60 min (30 para admin), só o link mais recente vale, sai da URL na primeira abertura, revoga todas as sessões | `esqueci_senha.php`, `redefinir_senha.php` |
| CSRF | Token por sessão (`random_bytes`, `hash_equals`) em todo POST que altera dado, inclusive os AJAX; logout por GET exige token próprio | `csrf_validar()` |
| Permissões | `auth_require(array('admin'))` antes de qualquer efeito; grupos de telas (`admin_telas()`); telas da equipe com `auth_require_superadmin()`; o fiel só muda o que `fiel_pode()` libera; cadastro sempre `pendente` | `conexao.php`, [overview.md](../../agents/overview.md) |
| Dados de outro fiel | Toda edição/exclusão (notas, orações, resumos, testemunhos, comentários, notificações, certificados, beneficiários) filtra pelo dono no SQL | `php/fiel/ajax_*.php` |
| SQL | `db_query($conn, $sql, $params)` no código novo; o antigo concatena só inteiros (`(int)`) ou valores de lista fixa; LIKE com escape de `% _ \` | todo o código |
| XSS | `htmlspecialchars` na saída; JSON embutido com `JSON_HEX_*`; o JS preenche dados com `textContent`; links do usuário só `*.php` interno ou `https://` | todo o código |
| CSP | Por página, com nonce nos scripts e sem `'unsafe-inline'` em `script-src`; `.htaccess` só para estáticos. Emergência: `CSP_SO_AVISAR=1` no Render | `site_csp_politica()`, `.htaccess` |
| Cabeçalhos | HSTS, `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`; sem `X-Powered-By` nem versão do Apache | `.htaccess`, `Dockerfile` |
| Uploads | Foto de perfil: só JPEG conferido com `getimagesize`, 32–1024 px. Imagens do admin: tipo real conferido, sem SVG, 5 MB. Vídeos: direto para o R2 com URL assinada (tipo assinado), chave `aulas/[A-Za-z0-9_-]` | `foto_perfil.php`, `r2_upload_url.php` |
| Vídeos | URL pré-assinada do R2 gerada só depois de checar publicação e assinatura | `assistir_video.php` |
| Webhook do Asaas | Token com `hash_equals`, evento processado uma vez só, valor e status relidos na API (nunca confia no corpo) | `webhook_asaas.php` |
| Lembrete diário | Chave só no cabeçalho, `hash_equals`, 503 sem chave configurada | `lembrete_diario.php` |
| Erros | `php.ini-production` (sem `display_errors`), detalhe só no log, e-mail mascarado no log | `Dockerfile`, `site_erro_banco()` |
| Arquivos internos | `.dockerignore` tira docs, banco, testes e config da imagem; `.htaccess` barra `.md/.sql/.env/...`, `conexao.php` e as pastas `includes/`; `mod_status` desligado | `.dockerignore`, `.htaccess`, `Dockerfile` |
| Chamadas de saída | cURL sempre com verificação de TLS e timeout; hosts fixos ou de variável de ambiente (sem SSRF) | `conexao.php` |
| Segredos | Só em variáveis de ambiente do Render; `.env` nunca foi versionado; histórico do git varrido em 23/10/2026 sem chave real | `.env.example`, `render.yaml` |
| Verificação em duas etapas (admin) | TOTP (RFC 6238) opcional por admin, ligado em Admin → Minha segurança; o login para em `login_2fa.php` até o código; mesmo código não vale duas vezes; 8 códigos de reserva (só o SHA-256 no banco); chave do autenticador cifrada no banco com a `APP_CHAVE` (AES-256-GCM, presa à conta; as antigas são cifradas no próximo login); 5 erros ou 5 min voltam para a senha e contam no limite do e-mail. QR gerado no navegador (`js/vendor/qrcode-generator-1.4.4.js`, MIT, sem CDN) | `conexao.php` ("VERIFICAÇÃO EM DUAS ETAPAS"), seção 84 |
| Registro de atividade do painel | `admin_log()` grava quem, o quê, em quê, detalhes e IP nas ações sensíveis (excluir curso/aula/usuário, aprovar/negar, permissões, tipos de admin, mensagens, live, 2FA); tela Admin → Registro de atividade; ações da equipe interna só para ela; guardado 2 anos | `conexao.php`, `admin_atividade.php`, seção 83 |
| LGPD | Política de Privacidade, aceite no cadastro, responsável do menor, baixar dados e excluir conta, limpeza periódica | [lgpd.md](lgpd.md), seção 82 |
| Aviso de aparelho novo | Login num tipo de navegador/sistema que a conta nunca usou ("Chrome no Windows") manda e-mail "Novo acesso à sua conta" com o caminho para trocar a senha; primeiro login não avisa; compara com as sessões guardadas (7 meses) | `login_avisar_aparelho_novo()`, `site_aparelho_descricao()` |
| CAPTCHA (opcional) | Cloudflare Turnstile no cadastro e no "Esqueci minha senha", ligado por `TURNSTILE_SITE_KEY`/`TURNSTILE_SECRET_KEY`; resposta conferida no servidor; Cloudflare fora do ar deixa passar (os limites por IP seguem) | `turnstile_*()`, CSP libera `challenges.cloudflare.com` |
| Erros de JavaScript | `ajax_erro_js.php`: só POST da mesma origem (Origin/Referer), até 10 por IP a cada 10 min, texto cortado e sem query string; só repassa ao Sentry; responde sempre 204 | `ajax_erro_js.php`, `js/erros.js` |
| Cache dos CSS/JS | Só arquivos com `?v=` ficam 1 ano (`immutable`); sem versão continuam `no-cache`, então uma correção nunca fica presa no navegador | `.htaccess`, `site_versao_estatico()` |
| Banco (RLS) | RLS ligado e sem política em todas as tabelas: a API REST que o Supabase publica sozinho não enxerga nada; o PHP entra como dono das tabelas e não passa pelo RLS | seção 90 do `bancosupabase.md`, `tests/testes_banco.php` |
| CORS | Nenhum cabeçalho `Access-Control-*`: só o próprio site chama os AJAX | — |
| Indexação | Homologação manda `X-Robots-Tag: noindex`; `robots.txt` tira `/php/` e os endpoints do Google | `conexao.php`, `robots.txt` |

---

## 2. Achados abertos

Ordenados por prioridade. "Como testar" é o que prova que a correção
funcionou — vira teste automático sempre que der. A rodada de 26/10/2026
corrigiu S02 a S08 e S10 a S16, mais o token CSRF no login (ver seção 3).

### Crítico

Nenhum aberto (S01 resolvido em 23/10/2026, ver seção 3).

### Médio

Nenhum aberto.

### Baixo

- [ ] **S09 — Bloqueio do login de outra pessoa** por 30 erros em 15 min
  vindos de IPs diferentes (`LOGIN_MAX_POR_EMAIL`, `conexao.php`). Com o
  S14 corrigido, o limite por e-mail + IP e o por IP passam a funcionar
  por pessoa; este continua sendo o freio contra ataque distribuído.
  Decidir com a equipe se fica assim (o "Esqueci minha senha" já zera o
  contador) ou se vira só um alerta.
- [ ] **S19 — Sem limite geral de requisições.** As rotas sensíveis têm
  limite próprio, mas uma enxurrada de pedidos em qualquer página chega
  inteira ao PHP (container de 512 MB). Corrigir na borda, não no código:
  domínio próprio pela Cloudflare com uma regra de rate limit por IP
  (folgada, por causa do Wi-Fi da igreja e do 4G que dividem IP).
  **Testar:** ~200 GETs seguidos de um IP numa página pública recebem 429
  da Cloudflare, e o uso normal não.

### Info

- [ ] Menores: foto de perfil fica 1 ano no
  cache do navegador (aparelho compartilhado); Postgres com
  `sslmode=require` sem `verify-full`; líder pode substituir a "Nota do
  pastor" fixada (confirmar regra com o cliente); a busca lista título de
  curso pago para quem não assina (confirmar se é intencional).

### A conferir no Render

- [ ] **S17 depois do deploy:** entrar de duas redes diferentes (Wi-Fi e 4G) e conferir que os IPs em `id_sessao` são diferentes e não são `104.x`/`172.6x.x` (faixas da Cloudflare). Mesma consulta do S14 abaixo.
- [ ] **S14 depois do deploy:** o IP gravado nas sessões e nas tentativas
  deve ser público e mudar de pessoa para pessoa (antes era sempre um
  `10.x`). Conferir:
  `SELECT ids_ip, ids_criada_em FROM id_sessao ORDER BY ids_criada_em DESC LIMIT 10;`
  Se aparecer sempre o mesmo IP público para pessoas diferentes, o Render
  pôs mais um proxy público na frente: avisar para ajustar
  `site_ip_cliente()`.
- [ ] `ONESIGNAL_EXTERNAL_ID_SEGREDO` e `SITE_URL_PUBLICA` preenchidas em
  **cada** serviço (homologação e produção).

---

## 3. Corrigidos

| Data | O quê | Commit / teste |
|---|---|---|
| 19–21/10/2026 | Rodada de segurança: CSP com nonce, CSRF em todos os formulários, sessões revogáveis, reset de senha seguro, limites de login/cadastro/recuperação, `mod_status` desligado, `display_errors` desligado, uploads conferidos, `content-type` assinado no R2, links de vídeo de 2 h, push protegido por `external_id` com segredo | `005829d` a `54c9312`; detalhes em [pontos_gerais.md](pontos_gerais.md#2-segurança) |
| 22/10/2026 | Banco fora do ar não derruba mais cadastro e telas com `tabela_tem_coluna()` | ver pontos_gerais.md |
| 23/10/2026 | **S01 (Crítico)** — senhas das contas de exemplo (`admin@igreja.com`, `joao@`, `maria@`, `carlos@email.com`) trocadas no banco da homologação; as 68 sessões abertas do admin foram encerradas | Conferido no banco: a senha de exemplo não vale em nenhuma das 4. Se a produção tiver banco próprio, repetir lá |
| 23/10/2026 | **Super admin (Alto)** — (1) o cadastro aceitava um e-mail de `SUPERADMIN_EMAILS` ainda sem conta: promovida por um admin da igreja, a conta virava super admin; (2) um admin com acesso total removia o admin da equipe interna e depois podia mudar o e-mail/status da conta; (3) um admin da igreja podia pôr outro num tipo de admin com Financeiro/Contribuições (repassando o acesso ao dinheiro); (4) Gerenciar Usuários deixava editar/desconectar a conta da equipe. Agora: o cadastro recusa esses e-mails; só super admin promove, remove ou troca o tipo de uma conta da equipe e põe alguém num tipo com dinheiro; Gerenciar Usuários barra as ações na conta da equipe. Na tela, "Super admin" só aparece para quem está em `SUPERADMIN_EMAILS` (o `IDA_NIVEL` do banco era só um rótulo). | `register.php`, `admin_gerenciar_permissoes.php`, `admin_gerenciar_usuarios.php`, `usuario_e_superadmin()` |
| 23/10/2026 | **Termo "super admin" restrito** — para quem não é super admin, o termo (e "equipe interna", `SUPERADMIN_EMAILS`) não aparece em nenhuma tela: as contas da equipe somem das listas (Tipos de Admin, Gerenciar Usuários, pessoas online), os tipos com Financeiro/Contribuições somem dos seletores e as mensagens ficam neutras ("Usuário não encontrado"). **Regra para código novo:** texto com o termo só dentro de `if (auth_eh_superadmin())`; `docs/`, `tests/` e `database/` não vão para a imagem (`.dockerignore`) e `*.md` é bloqueado no `.htaccess`. | Varredura em todo o `.php`/`.js`/`.html` |
| 26/10/2026 | **Rodada S02–S16.** **S02/S05** já resolvidos pelo item "Super admin" acima (conferido no código). **S06:** admin num tipo restrito não edita, exclui, desconecta nem manda link de senha para admin com acesso total (`admin_alvo_protegido()`). **S07:** erro de banco ao ler o tipo do admin mostra só o Dashboard, em vez de todas as telas. **S03:** `script-src` só com `cdn.jsdelivr.net/npm/bootstrap@5.3.2/` e a OneSignal (cdnjs fica só em estilos e fontes); vale no PHP e no `.htaccess` — biblioteca nova de CDN precisa entrar nos dois. **S04:** só o admin grava a duração da aula; o fiel não conclui aula sem duração (a do navegador só mostra o %) — antes, sem duração, até um `concluido=true` sem duração nenhuma passava. **S08:** e-mail já cadastrado responde igual a um cadastro novo, e o dono recebe "Você já tem cadastro". **S10:** o tamanho do arquivo entra na assinatura do PUT (teto de 5 GB); testado contra o R2: tamanho certo 200, outro 403. **S12:** além de 1 por 10 s por aula, 10 comentários por minuto no total por fiel. **S13:** `.htaccess` em `php/admin/includes/` (o `checar_site.php` agora falha se abrir). **S15:** "local" só com `localhost` **e** sem a variável `RENDER` (`site_ambiente_local()`). **S16:** `?aba[]=` e `?status[]=` não dão mais 500. **CSRF:** token novo a cada login. | `tests/testes_funcoes.php` (CSP e ambiente local); roteiro em [pontos_gerais.md](pontos_gerais.md#2-segurança) |
| 26/10/2026 | **S14 (Médio, achado no teste).** Em vez de forjável, o IP era sempre o de um proxy interno do Render (`10.x`): todas as sessões e tentativas da homologação tinham IPs `10.24…`/`10.26…`, e os limites por IP (login, cadastro, recuperação) contavam todo mundo junto — 50 erros somados de várias pessoas travariam o login de todas. `site_ip_cliente()` agora lê o `X-Forwarded-For` da direita para a esquerda, pula IPs internos e fica com o primeiro público; o que vier à esquerda (forjável) não conta. Testado na homologação antes da correção: um `X-Forwarded-For: 1.2.3.4` forjado não passou. **S11:** nenhuma conta com senha em texto puro (`COUNT = 0` na homologação); a comparação em texto puro saiu do login, da troca de senha e da troca de e-mail. | `tests/testes_funcoes.php` (6 casos de IP) |
| 26/10/2026 | **Token de "esqueci a senha" na sessão.** Com `SESSOES_NO_BANCO=1`, o token ficava em claro na tabela `ID_SESSAO` por até 15 min: quem lesse essa tabela trocava a senha de alguém. Agora a sessão guarda só o SHA-256 (o mesmo que `id_recuperacao_senha` já guardava): `recuperacao_token_valido_por_hash()` e `recuperacao_redefinir_por_hash()`. **Entrada padronizada:** os 72 `(string) ($_POST[...] ?? '')` viraram `site_entrada_texto()` (array vira vazio, sem aviso no log), mais `$modo`/`$acao` de Gerenciar Usuários e o `?token=` da nova senha. | `tests/testes_funcoes.php` (210); páginas públicas abertas num servidor local sem erro |
| 27/10/2026 | **Rodada de produto (sem commit ainda).** (1) **2FA para admins** e **registro de atividade** (ver seção 1). (2) **Excluir usuário pelo admin apagava pagamentos:** o `DELETE` em `ID_FIEL` levava em cascata `id_assinatura` → `id_pagamento` de assinaturas canceladas, e o Financeiro perdia entradas; agora passa por `lgpd_excluir_conta()` (anonimiza quando há histórico financeiro). (3) **Quem aprovou o cadastro não era gravado:** `IDSA_ADMIN_ID` tem chave estrangeira para `ID_ADMIN.IDA_ID`, mas recebia o id do fiel — a gravação falhava em silêncio (ou apontava para o admin errado); agora grava o `IDA_ID`, com upsert. (4) Rodapé das páginas públicas mostrava telefone e e-mail de exemplo; agora vem das variáveis `CONTATO_*`. (5) Endpoints de polling da live não abrem sessão para visitante sem cookie (`SITE_SESSAO_OPCIONAL`). | `tests/testes_funcoes.php` (255: vetores da RFC 6238, reserva, idade, aceite, colunas ocultas, cache) |
| 27/10/2026 | **S17 (Médio, achado ao conferir o S14 no Render).** O Render serve o site atrás da Cloudflare (inclusive o `*.onrender.com`): a sessão mais recente da homologação tinha IP `104.23.x` (borda da Cloudflare). Com isso o último IP público do `X-Forwarded-For` podia ser o da borda, o mesmo para muita gente, e os limites por IP voltavam a juntar pessoas diferentes (o S14 de novo). `site_ip_cliente()` agora usa primeiro o `CF-Connecting-IP` (a Cloudflare sempre troca o valor que o cliente tente mandar; não há caminho até o Render sem passar por ela) e só depois o `X-Forwarded-For`. Vale também para o IP do registro de atividade. | `tests/testes_funcoes.php` (4 casos novos de IP, 259 no total) |
| 27/10/2026 | **Revisão independente da rodada (6 achados, corrigidos antes do deploy).** **Médio:** (1) cada senha certa zerava as tentativas antes do código do 2FA, e quem já tivesse a senha ganhava mais 5 chutes de código a cada login; agora só zera depois do código certo, e o `login_2fa.php` respeita o limite acumulado do e-mail (~30 chutes por 15 min contra 1 milhão de códigos). (2) Erro de banco ao ler o 2FA deixava o admin entrar só com a senha; agora `doisfa_estado_login()` consulta direto (sem o cache do schema) e recusa o login se não conseguir conferir. (3) A exclusão de conta sem pagamento apagava em cascata as respostas de outras pessoas aos comentários do fiel e os devocionais publicados; agora esses casos anonimizam. **Baixo:** (4) "Aprovar selecionados" aprovava qualquer id enviado; agora só pendentes, e conta excluída não é reaprovada. (5) Contas excluídas apareciam e podiam ser editadas em Gerenciar Usuários. (6) A busca de pessoas em Enviar Mensagem mostrava as contas da equipe interna. | `tests/testes_banco.php` (casos de 2FA e de comentário respondido) |
| 27/10/2026 | **Revisão geral (Pacote D, sem commit ainda).** Médios: (1) foto de qualquer conta, inclusive menores, trocando o id em `foto_perfil.php`, e menores nas "conquistas recentes" da Home → foto de menor só para ele e o admin, lista sem menores e sem a equipe interna; (2) contato caía numa conta de teste do Formspree sem `CONTATO_FORMSPREE_URL` → formulário escondido; (3) admin de qualquer tipo via CPF, telefone e endereço no Dashboard → só com Aprovar ou Gerenciar Usuários; (4) `missao.php?aguardando=` consultava o Asaas sem freio → 1 a cada 12 s por fiel (`site_intervalo_livre()`); (5) curso concluído em minutos com várias aulas/sessões em paralelo e meta somando por sessão → relógio único por fiel no banco (seção 87). Baixos: "Baixar meus dados" sem senha nem limite; moderação do lado do fiel para qualquer admin e sem registro (agora Testemunhos ou Vídeos, com `admin_log`) e qualquer admin regravando a duração da aula; comentários sem teto diário (100); beneficiário do plano familiar contava só busca sem resultado; contribuições pendentes em paralelo (1 a cada 5 s); sessão revogada valia para sempre com o banco fora (folga de 5 min, 1 min admin); `ajax_presenca.php` sem teto de 12 h, Termo e assinatura. Também: excluir curso apagava certificados e progresso (agora pede confirmação ou arquiva); menor passava sem responsável deixando a data em branco | `tests/testes_funcoes.php`: 304 de 304 (local); `tests/testes_banco.php` no CI |
| 28/10/2026 | **S18 (Baixo, sem commit ainda).** Só 13 das 62 tabelas ligavam o RLS no `bancosupabase.md`; as outras foram ligadas à mão no painel da homologação (conferido: nenhuma sem RLS lá). Uma produção montada do zero pelo arquivo nasceria com ~49 tabelas abertas na API REST do Supabase para quem tivesse a chave anon. Seção 90 liga o RLS em toda tabela do `public` que estiver sem. | `tests/testes_banco.php` ("toda tabela com RLS ligado", roda no CI com o banco do zero) |

---

## 4. Rotina de testes

Três momentos. Os comandos rodam de dentro de `MananciAPP/` com o PHP do
XAMPP (`C:\xampp\php\php.exe`, fora do PATH).

### 4.1. Na pasta, a cada mudança de código

1. `php -l` em cada arquivo PHP alterado.
2. `php tests\testes_funcoes.php` — funções puras, sem banco (210 testes
   em 26/10/2026). Mexeu numa função de segurança (senha, entrada, URL,
   permissão)? Acrescentar o caso lá.
3. Mexeu em `.htaccess`, cabeçalho, CSP ou pasta nova: subir o Apache do
   XAMPP e rodar `php tests\checar_site.php http://localhost/MananciAPP/`
   (o `php -S` não lê `.htaccess`; em http, HSTS e cookie `Secure` viram
   aviso).
4. Código novo segue as regras de [overview.md](../../agents/overview.md)
   (item "Segurança"): `db_query` com parâmetros, `site_entrada_texto()`,
   `htmlspecialchars`, `csrf_validar()`, `auth_require`, nonce nos
   `<script>`.

### 4.2. No site, depois de cada deploy (e uma vez por semana)

```
C:\xampp\php\php.exe tests\checar_site.php https://mananciapp-render-homolog.onrender.com/
```

Só faz GET, sem login: pode rodar a qualquer hora, inclusive em produção
(trocar a URL). Confere:

- `saude.php` no ar; `http://` redireciona para `https://`;
- CSP com nonce e sem `'unsafe-inline'` em scripts, HSTS, `nosniff`,
  `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, sem versão do
  PHP/Apache; cookie de sessão `HttpOnly`, `Secure`, `SameSite`;
- 25 arquivos internos (`.env`, `.git`, docs, banco, testes, `conexao.php`,
  includes, `server-status`…) **não** respondem 200;
- telas do admin e do fiel, AJAX, certificado e lembrete **não** abrem sem
  login/chave, e os redirecionamentos ficam no próprio domínio;
- webhook recusa GET;
- nenhuma página pública mostra erro do PHP, SQL ou caminho do servidor.

Sai com código 1 se algo falhar. Falhou: abrir achado na seção 2. Resultado
de cada rodada: seção 7.

### 4.3. Manual, uma vez por mês e antes de cada lançamento

Com contas de teste (nunca as reais) na homologação:

- [ ] **Entre perfis:** fiel tentando abrir `php/admin/*.php`; admin de
  grupo restrito tentando tela fora do grupo (direto pela URL); admin comum
  tentando Financeiro/Contribuições; fiel A trocando o id em
  `certificado_view.php?id=`, ao excluir nota/oração/comentário de B.
- [ ] **CSP:** F12 → Console, procurar "Content Security Policy" em: login,
  cadastro, home, aula (player e prévias), comentários, live (chat e
  moderação), perfil (foto), assinatura, admin (excluir algo, filtros,
  envio de vídeo), certificado, convite de push.
- [ ] **Senha:** o 6º erro seguido no login (limite
  `LOGIN_MAX_POR_EMAIL_IP` = 5) é bloqueado; link de redefinição
  usado duas vezes falha; link velho falha depois de pedir outro.
- [ ] **Entrada estranha:** `<script>alert(1)</script>` e `"><img src=x
  onerror=alert(1)>` no nome, comentário, oração e testemunho aparecem
  como texto.
- [ ] Cada achado corrigido da seção 3 testado de novo pelo "Testar" dele.

### 4.4. Mensal, fora do código

- [ ] Supabase: `SELECT` em `ID_ADMIN` com o e-mail de cada admin — algum
  desconhecido?
- [ ] Render → Logs: picos de 404 (varredura), muitos POST em `login.php`,
  respostas 500 repetidas.
- [ ] Brevo: *Endereços IP não autorizados* e estatísticas de envio.
- [ ] Asaas: webhooks com falha ou fila pausada.
- [ ] Atualização de segurança do PHP: trocar a versão no `Dockerfile`
  (`FROM php:8.2.x-apache`) quando sair uma nova.
- [ ] GitHub: commits ou colaboradores que ninguém reconhece.

---

## 5. Hospedagem e serviços (fora do código)

Não dá para corrigir isso mexendo só no código.

- [x] **Senhas das contas de exemplo** (S01) — trocadas em 23/10/2026.
- [ ] **Brevo:** reativar o bloqueio de IP das chaves de API, autorizando as
  faixas de saída do Render; uma chave por ambiente.
- [ ] **Variáveis do Render**, por serviço: `ONESIGNAL_EXTERNAL_ID_SEGREDO`,
  `SITE_URL_PUBLICA`, `ASAAS_API_URL` (produção = API real),
  `ASAAS_WEBHOOK_TOKEN`, `LEMBRETE_CRON_CHAVE`, `SUPERADMIN_EMAILS`, `APP_CHAVE`
  (32+ letras, uma por ambiente; não trocar depois).
- [ ] **Supabase:** a conta que o app usa (`DB_USER`) deveria poder só ler e
  gravar as tabelas do app (sem criar papel nem mexer em outros esquemas);
  conferir se os backups automáticos do plano estão ativos e anotar até
  onde dá para voltar.
- [ ] **Proteção na frente do site (opcional, grátis):** domínio próprio
  passando pelo proxy da Cloudflare dá WAF básico, limite de requisições e
  proteção contra excesso de tráfego. Ao ligar, conferir S14 de novo (o IP
  real passa a vir em `CF-Connecting-IP`). Não bloquear IPs no atacado:
  igreja, operadora de celular e Wi-Fi público dividem IP (NAT).
- [ ] **GitHub:** se o repositório for público, lembrar que os e-mails das
  contas de exemplo e a estrutura do banco ficam visíveis — mais uma razão
  para S01. Ligar o *secret scanning* do GitHub se disponível.

---

## 6. Suspeita de ataque: o que fazer

Muitos 404 ou tentativas de login são varredura automática — acontece com
todo site e **não** é invasão. Invasão é quando algo mudou sem explicação.

1. **Guardar as provas antes de mexer:** baixar os logs do Render do
   período, exportar `ID_ADMIN`, a auditoria e as sessões ativas do
   Supabase, anotar data e hora.
2. **Ver o tamanho:** admin desconhecido? Senha ou e-mail de admin trocado?
   Conteúdo alterado? Commit estranho no GitHub? Cobrança ou webhook
   estranho no Asaas?
3. **Conter:**
   - derrubar todas as sessões no SQL Editor do Supabase:
     `UPDATE ID_SESSAO SET IDS_REVOGADA = 1 WHERE IDS_REVOGADA = 0;`
     (cada página confere a revogação; todos voltam ao login);
   - remover o admin estranho; trocar as senhas dos admins;
   - trocar as chaves que podem ter vazado (Supabase `DB_PASS`, R2, Brevo,
     Asaas, OneSignal, tokens de webhook e cron) no painel de cada serviço
     e depois no Render;
   - modo manutenção, se preciso: suspender o serviço no Render.
4. **Corrigir a causa** (achado novo na seção 2), deploy, rodar a seção 4.
5. **Registrar** na seção 7 o que aconteceu, sem dados pessoais.

## Plano de recuperação de uma correção

- Código: `git revert <commit>` e deploy. Cada correção vai num commit
  separado justamente para isso.
- CSP quebrando tela: `CSP_SO_AVISAR=1` no Render (sem deploy de código).
- Banco: toda mudança de SQL é idempotente e documentada em
  `database/bancosupabase.md`; antes de rodar SQL que apaga ou altera em
  massa, exportar a tabela.

---

## 7. Registro das rodadas

| Data | O quê | Resultado |
|---|---|---|
| 23/10/2026 | Auditoria completa (leitura do código, histórico do git) | 0 Crítico/Alto no código; S01 (configuração) Crítico, resolvido no mesmo dia; 3 Médios, 8 Baixos, Info. Sem segredo real no histórico do git. Sem logs do Render ainda para avaliar os ataques observados. |
| 23/10/2026 | `tests/testes_funcoes.php` (local) | 156 de 156 passaram |
| 23/10/2026 | `tests/checar_site.php` na homologação | 69 conferências: 68 ok, 1 aviso (S13), 0 falha |
| 23/10/2026 | S01: senhas das 4 contas de exemplo trocadas (homologação) | Conferido no banco: a senha de exemplo não vale em nenhuma; 68 sessões abertas do admin encerradas |
| 26/10/2026 | Correção de S02–S16 (exceto S09) e token CSRF no login | `tests/testes_funcoes.php`: 208 de 208; PUT no R2 com tamanho assinado: certo 200, outro 403 (arquivo de teste apagado). Falta o passeio no Render depois do deploy. |
| 27/10/2026 | Rodada de produto (LGPD, 2FA, registro de atividade, admin, cache da live) — local | `tests/testes_funcoes.php`: 255 de 255; `php -l` em 105 arquivos sem erro; páginas públicas, termo só leitura, cadastro, status e chat da live abertos num servidor local sem erro do PHP; status da live sem cookie de sessão para visitante. **Falta:** rodar as seções 82–84 e o roteiro de [pontos_gerais.md](pontos_gerais.md#2-segurança) no Render |
| 28/10/2026 | Conferência das listas de segurança (21 itens de segurança, 20 de "site no ar", rate limit) contra o código; RLS na homologação | Código confere com a seção 1; varredura de segredos no código e no histórico do git sem achado; RLS: nenhuma tabela sem na homologação. Achados: S18 (corrigido, seção 90) e S19 (aberto). Checklist geral criado no topo. `tests/testes_funcoes.php`: 324 de 324 |
