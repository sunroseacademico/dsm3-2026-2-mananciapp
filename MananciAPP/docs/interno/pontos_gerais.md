# Pontos Gerais

Tudo o que ainda falta no projeto, fora as tarefas do cliente, num lugar
só: configuração, segurança, desempenho, qualidade do código, decisões
internas e o que fazer antes da produção. As tarefas do cliente (T01…T39)
continuam em [progresso_pedidos_cliente.md](progresso_pedidos_cliente.md).

Atualizado em 26/10/2026. Juntou o antigo `pendencias_configuracao.md`, o
`CHECKLIST_MIGRACAO.md` (raiz) e o
`assinaturas/perguntas_assinatura_equipe_tecnica.md`. Os três continuam no
histórico do git.

**Como usar:** marcar `[x]` quando terminar e anotar a data ou o commit ao
lado. Item que perdeu o sentido sai da lista, com uma linha explicando por
quê no fim da seção. Item novo entra na seção certa. As variáveis citadas
estão explicadas também no `.env.example`.

---

## Resumo: por onde começar

| # | O quê | Seção | Esforço |
|---|---|---|---|
| 1 | ~~`DB_PORT` = 5432 no Render~~ ✅ (21/10/2026) | [1.1](#11-render) | 1 min |
| 2 | ~~Trocar as senhas `123456` das contas de exemplo~~ ✅ (23/10/2026) | [2](#2-segurança) | 5 min |
| 3 | ~~Lembrete diário: chave no Render + job no cron-job.org~~ ✅ (26/10/2026) | [1.2](#12-lembrete-diário-cron-joborg) | 15 min |
| 4 | ~~`ONESIGNAL_EXTERNAL_ID_SEGREDO` no Render~~ ✅ (21/10/2026) | [2](#2-segurança) | 2 min |
| 5 | ~~Conferências depois do deploy~~ ✅ (26/10/2026) | [1.6](#16-conferências-depois-de-cada-deploy-grande) | 20 min |
| 6 | Região do Render (Oregon → Virginia) | [3](#3-desempenho) | 1 h |
| 7 | ~~Testar as tarefas 🧪 no Render~~ ✅ T01–T33 (26/10/2026) | [7](#7-validar-as-tarefas-do-cliente) | contínuo |
| 8 | ~~Rodar as seções **79 a 84**~~ ✅ (27/10/2026); falta testar a rodada de 27/10 na homologação | [8](#8-antes-da-produção-com-dinheiro-real) e [2](#2-segurança) | 1 h |
| 9 | Plano pago e região do Render para 300 pessoas ([capacidade.md](capacidade.md)) | [3](#3-desempenho) | 1 h + custo |

---

## Lançamento em 01/12/2026

Cronograma combinado com o cliente em 22/10/2026 (detalhes em
[progresso_pedidos_cliente.md](progresso_pedidos_cliente.md#cronograma-de-entrega)):
**15/11** o cliente começa a subir os cursos (e grava o vídeo); **01/12**
o app abre para o pessoal da igreja, **gratuito**, já **na produção**;
**fevereiro/março de 2027** entra a assinatura (T15). No lançamento, o único
valor é **opcional**: a aba **Contribuir**, que precisa estar recebendo
dinheiro de verdade.

**Prioridade geral (28/10/2026):** tudo pronto e testado na homologação até
**15/11**; de 16/11 a 30/11 a produção é montada do zero e conferida; em
**01/12** abre. Depois de 15/11, só correção de bug: nada de funcionalidade
nova até a abertura.

### Fase 1 — até 08/11: terminar o código (do nosso lado)

- [x] (28/10/2026, a testar) **Pacote H** — primeira impressão do fiel (plano_de_melhorias.md
  10.3, os ⭐): Sobre e home pública com a igreja de verdade, aviso de
  aprovação e tela de espera, rótulos e regras da senha visíveis, tirar o
  "Rode .sql", cabeçalho da home no celular, avisos próprios no lugar de
  `alert()`/`confirm()`, letras e toques maiores.
- [x] (28/10/2026, a testar) **Pacote G, parte rápida** (10.2, os ⭐ menos a tesouraria): registro
  de atividade no dinheiro e no conteúdo, lançamento financeiro cancelado
  em vez de apagado, confirmação antes de mandar para a igreja inteira,
  CPF validado no painel, "Saúde do sistema" no Dashboard, admin cadastrar
  uma pessoa.
- [x] (28/10/2026, a testar) Fluxos menores que sobraram do Pacote D (9.1): notificações do admin
  sem link, recusado sem saída, aula sem duração sem aviso na grade, 2FA
  sem recuperação pelo super admin, "Novo curso" perdendo o digitado.
- [x] (28/10/2026, a testar) Bootstrap e Font Awesome servidos pelo próprio site (plano B se o CDN
  cair).

### Fase 2 — até 15/11: testar tudo na homologação (do seu lado, com a igreja)

- [ ] `git push` e rodar as seções **85 a 88** no Supabase da homologação.
- [ ] Roteiros de teste dos Pacotes **A a F** (seção 2 deste arquivo) e dos
  que vierem (G, H); anotar o que falhar.
- [ ] Lighthouse no celular (home, aula, perfil): ≥ 90 em Acessibilidade e
  Desempenho.
- [ ] **Teste de carga do culto** (tests/carga/README.md, `CENARIO=culto`)
  com ~50 contas de teste e uma live AO VIVO: é o que define o plano do
  Render da produção.
- [ ] Upload de um vídeo grande (1 h de culto) pelo admin, até o fim.
- [ ] Conta de admin para quem vai subir os cursos, num grupo com Cursos e
  Vídeos (seção 1.4.2).
- [x] (26/10/2026) Rodar as seções 71 a 78 e validar Destaques da home,
  Contribuir e Filiais (1.4.0 a 1.4.5).
- [ ] **Com o cliente:** textos e fotos reais do "Sobre" e da home (pastor,
  endereço, horários dos cultos, redes), lista real das filiais, quem
  aprova os cadastros nos primeiros dias, prints novos do "Como instalar".

### Fase 3 — de 16/11 a 30/11: montar a produção (do seu lado)

Ordem sugerida; cada item tem o passo a passo na seção indicada.

- [ ] **Supabase de produção:** projeto novo (mesma região do Render),
  rodar o `bancosupabase.md` **inteiro** num banco vazio (seção 8) e
  conferir `SELECT MAX(isv_secao) FROM id_schema_versao;` → 89. Plano com
  backup (Pro) e testar uma restauração.
- [ ] **Render de produção:** serviço novo, plano pago que não hiberna,
  **mesma região do Supabase**, `APACHE_MAX_WORKERS` conforme o teste de
  carga ([capacidade.md](capacidade.md)), branch/commit conferido.
- [ ] **Variáveis do Render** (o Dashboard da equipe avisa o que faltar):
  `DB_*`, `SITE_URL_PUBLICA` (domínio), `SESSOES_NO_BANCO=1`,
  `ASAAS_API_URL` de produção + chaves + `ASAAS_WEBHOOK_TOKEN`,
  `EMAIL_*`, `ONESIGNAL_*` (+ `ONESIGNAL_EXTERNAL_ID_SEGREDO`),
  `LEMBRETE_CRON_CHAVE`, `SENTRY_DSN` (projeto de produção no Sentry),
  `TURNSTILE_*`, `R2_*`, `SUPERADMIN_EMAILS`, `CONTATO_*`,
  `EMAIL_SENDER_NAME`, `APP_CHAVE` (nova, gerada só para a produção).
- [ ] **Domínio próprio** apontando para o Render, com a Cloudflare na frente
  (limite por IP em login/cadastro/`ajax_*`); CORS do R2 e *Site URL* da
  OneSignal com o domínio; Turnstile com o domínio.
- [ ] **Brevo:** SPF, DKIM e DMARC no domínio remetente.
- [ ] **Asaas de produção** só para as contribuições: conta, chaves, chave
  Pix e webhook ([assinaturas_virada_producao.md](assinaturas/assinaturas_virada_producao.md),
  pulando os planos). Em Admin → Assinaturas: **fechada**.
- [ ] **cron-job.org** com a URL e a chave da produção; **UptimeRobot** com
  `https://<domínio>/saude.php?banco=1`.
- [ ] Primeiro super admin, tipos de admin, filiais, funções, categorias,
  destaques da home, oferta geral ligada.
- [ ] **Conferência final:** `tests/checar_site.php` contra a produção sem
  falha; uma contribuição real de R$ 5 conferida no Financeiro; cadastro →
  aprovação → aula → certificado com uma conta de verdade; push chegando no
  celular; e-mail de "esqueci a senha" chegando (fora do spam).
- [ ] Cursos que o cliente subiu na homologação: subir de novo na produção
  (ou decidir antes de 15/11 se ele já sobe direto na produção).

### 01/12 — abertura

- [ ] Avisar a igreja (link do domínio, como instalar, que o cadastro passa
  por aprovação). Ficar de olho no Sentry, no UptimeRobot e na fila de
  aprovação nos primeiros dias.

### Depois da abertura (fevereiro/março de 2027, junto com as assinaturas)

Tesouraria da igreja e modelo de cobrança (10.2), ficha completa do
membro e lista com filtros, agenda/eventos, pipeline de QA com Playwright
(10.4), marca configurável e página de vendas (seção 6 do plano).

---

## 1. Configuração (Render e serviços externos)

Nada aqui derruba o site: sem cada item, o app continua como antes (e
avisa no log do Render quando cai num valor padrão).

Situação em 20/10/2026: seções 65 a 70 do `bancosupabase.md` rodadas na
homologação; `SITE_URL_PUBLICA`, `ASAAS_API_URL`, `SESSOES_NO_BANCO=0` e
`SENHA_CHECAR_VAZADAS=1` já no Render da homologação.

### 1.1. Render

- [x] **`DB_PORT` = 5432** (conferido em 21/10/2026) (Session pooler do Supabase). A conexão do app é
  persistente e não combina com o Transaction pooler (6543).
- [x] **Health check** (21/10/2026, conferido no log: 200 a cada 5 s): Render → Settings → Health Check Path =
  `/saude.php` (só vem do `render.yaml` se o serviço foi criado pelo
  Blueprint).
- [ ] Conferir se todas as variáveis do `.env.example` estão no Render
  (inclusive `ONESIGNAL_APP_ID` e `ONESIGNAL_REST_API_KEY`).
- [ ] **`APP_CHAVE`** (29/10/2026, 2 min): `openssl rand -base64 32` → Render
  → Environment (uma por ambiente). Cifra no banco a chave do 2FA dos
  admins; as já ligadas são cifradas no próximo login de cada um. **Não
  trocar nem apagar depois:** quem tem 2FA só entraria com código de reserva.
- [ ] `APACHE_MAX_WORKERS` (27/10/2026): deixar 20 no plano free; ao passar
  para o Standard, 40, conferindo o Pool Size do Supabase
  ([capacidade.md](capacidade.md), seção 3).
- [ ] **CAPTCHA (Turnstile, grátis, 10 min):** Cloudflare → Turnstile → *Add
  widget* → nome "MananciAPP", domínio do site (o `*.onrender.com` e o
  domínio próprio), modo **Managed** → copiar as duas chaves para o Render:
  `TURNSTILE_SITE_KEY` e `TURNSTILE_SECRET_KEY` (uma dupla por ambiente). Sem
  elas, o cadastro e o "Esqueci minha senha" seguem como antes.

### 1.2. Lembrete diário (cron-job.org)

O fiel escolhe no perfil um horário para receber um push diário. Alguém
precisa chamar `lembrete_diario.php` de tempos em tempos para o app ver de
quem é a hora e mandar os pushes. **Sem isso, nenhum lembrete sai** (hoje
a página responde "LEMBRETE_CRON_CHAVE não configurada").

1. [x] (26/10/2026) Gerar uma chave longa e aleatória (~40 caracteres).
2. [x] (26/10/2026) Render → Environment: `LEMBRETE_CRON_CHAVE` = a chave.
3. [x] (26/10/2026) [cron-job.org](https://cron-job.org) (gratuito): criar conta e um
   job:
   - URL: `https://<site>/lembrete_diario.php`
   - frequência: a cada **10 minutos**
   - Advanced → Headers: `X-Cron-Chave` = a mesma chave
4. [x] (26/10/2026) Conferir no histórico do job que a resposta é `{"ok":true,...}`.

Efeito colateral bom: com uma chamada a cada 10 min, o Render gratuito para
de hibernar (ele dorme depois de 15 min sem acesso, e o primeiro acesso
levava ~12 s). As 750 h/mês do plano gratuito cobrem um serviço ligado o
mês inteiro, se for o único gratuito da conta.

### 1.3. Sessões no banco

Com as sessões em arquivo, todo deploy, reinício ou hibernação do Render
desloga todo mundo. A tabela já existe (seção 70).

- [x] Antes: ligar `PGSQL_ATTR_DISABLE_PREPARES` (ver [3](#3-desempenho)).
- [x] Depois de um deploy estável: Render → `SESSOES_NO_BANCO` = `1`
  (22/10/2026).
- [ ] Conferir que o login continua funcionando e que um novo deploy não
  desloga ninguém.
- Para desligar: voltar para `0` (todos são deslogados uma vez).

### 1.4. Página de contato

A página "Fale conosco" (`php/fiel/contato.php`) lê os dados do Render.
Sem as variáveis, as linhas somem e o formulário cai numa **conta de teste**
do Formspree (as mensagens não chegam a ninguém). Desde 22/10/2026 o
contato (como a parte financeira) é da **equipe**: não depende do cliente.

| Variável | O que colocar |
|---|---|
| `CONTATO_EMAIL` | E-mail de contato da equipe |
| `CONTATO_TELEFONE` | Telefone/WhatsApp, como deve aparecer |
| `CONTATO_ENDERECO` | Endereço (ou deixar em branco: a linha some) |
| `CONTATO_FORMSPREE_URL` | `https://formspree.io/f/<id>` do formulário da equipe |

- [ ] Criar o formulário em formspree.io **com o e-mail da equipe** ("New
  form" → copiar o endereço).
- [ ] Preencher as variáveis no Render.

### 1.4.0. Conferir se as seções 71 a 74 entraram no Supabase

Em 22/10/2026 as seções 71 a 73 foram rodadas com o Supabase mostrando
"Estamos investigando um problema técnico" (incidente do próprio
Supabase). Conferir depois que o status normalizar, no SQL Editor. O
arquivo [database/conferir_secoes_71_a_74.sql](../../database/conferir_secoes_71_a_74.sql)
junta estas consultas e o SQL das quatro seções, para rodar de uma vez:

```sql
-- Deve listar as 5 tabelas (id_fiel_tipo só depois da seção 74).
SELECT table_name FROM information_schema.tables
WHERE table_schema = 'public'
  AND table_name IN ('id_financeiro_lancamento', 'id_missao_projeto', 'id_missao_contribuicao', 'id_admin_grupo', 'id_fiel_tipo')
ORDER BY 1;

-- Deve listar as 3 colunas novas.
SELECT table_name, column_name FROM information_schema.columns
WHERE table_schema = 'public'
  AND (table_name, column_name) IN (('id_pagamento', 'idpg_valor_liquido_centavos'), ('id_admin', 'ida_grupo_id'), ('id_fiel', 'idf_tipo_id'))
ORDER BY 1;

-- Deve mostrar "Fiel" (padrão) e "Fiel com edição completa".
SELECT idft_nome, idft_permissoes, idft_padrao FROM id_fiel_tipo;
```

- [x] (26/10/2026) Rodar as consultas acima. Faltou algo: rodar de novo a seção
  correspondente (todas são idempotentes, podem rodar mais de uma vez).
- [x] (26/10/2026) O app percebe tabela nova em até 5 minutos (cache do schema) ou na
  hora, num deploy.

### 1.4.1. Financeiro e missões (seções 71 e 72)

- [x] (26/10/2026) Rodar as seções **71** (Financeiro) e **72** (missões) do
  `bancosupabase.md` no Supabase.
- [ ] Render → `SUPERADMIN_EMAILS` = e-mails dos admins da equipe,
  separados por vírgula (a conta precisa ser admin no app). Sem isso,
  ninguém vê Admin → Financeiro.
- [x] (26/10/2026) Testar no sandbox: roteiro no bloco da T32 em
  [progresso_pedidos_cliente.md](progresso_pedidos_cliente.md) e as regras
  em [financeiro.md](financeiro.md).
- [ ] Lançar os custos mensais (Render, Supabase, Cloudflare, domínio…) em
  Admin → Financeiro para o lucro sair certo.

### 1.4.2. Grupos de permissões

- [x] (26/10/2026) Rodar a seção **73** do `bancosupabase.md` (grupos de permissões).
  Sem ela, todo admin segue com acesso total, como antes.
- [ ] Admin → Tipos de Admin: criar os tipos (ex.: "Mídia" com Cursos,
  Vídeos e Ao Vivo; "Moderação" com Aprovar Usuários, Testemunhos e
  Devocionais) e escolher o grupo de cada admin da igreja. Admins atuais
  ficam com acesso total até alguém mudar.
- [ ] Testar: admin num grupo só vê no menu as telas do grupo; abrir a URL
  de outra tela volta ao Dashboard com o aviso "Você não tem acesso";
  quem está num grupo não cria grupos nem muda o grupo de ninguém, e só
  promove para o próprio grupo; superadmin sempre vê tudo.
- [ ] Grupos que já existiam não ganham a tela nova **Destaques da home**
  sozinhos: marcar nos grupos que devem cuidar dela.

### 1.4.3. Tipos de fiel (seção 74)

- [x] (26/10/2026) Rodar a seção **74** do `bancosupabase.md`. Ela cria "Fiel"
  (padrão: dados pessoais, foto e vínculo de membro, o mesmo de antes) e
  "Fiel com edição completa" (também nome, e-mail e CPF).
- [ ] Admin → **Tipos de fiel**: ajustar as permissões de cada tipo,
  criar outros se quiser e escolher o tipo de cada fiel (busca por nome ou
  e-mail).
- [ ] Testar com uma conta de fiel: no tipo padrão, o perfil não mostra
  "Dados da conta"; no "edição completa", mostra e deixa trocar nome, CPF
  e e-mail (o e-mail pede a senha atual; depois disso, o login é com o
  e-mail novo); tirar "foto" de um tipo esconde a câmera; tirar "dados"
  esconde o "Editar" dos dados pessoais. Tentar um e-mail/CPF de outra
  conta (recusa).

### 1.4.4. Destaques da home, abas Missões e Contribuir (seções 75, 77 e 78)

O banner de novidades (faixa abaixo do destaque, em Admin → Missões) virou
um **carrossel** no lugar do destaque da home, e a aba Missões virou
**Contribuir** (oferta geral + missões). Roteiro completo no bloco da T32
em [progresso_pedidos_cliente.md](progresso_pedidos_cliente.md).

- [x] (26/10/2026) Rodar a seção **75** do `bancosupabase.md` (tabela das artes). Ela
  já cria a primeira arte, "Toda contribuição, sendo de coração, é bem
  vinda" (com a versão quadrada no celular), levando para a aba
  Contribuir. As imagens ficam em `assets/img/destaques/` (JPG de ~130 KB,
  convertidos dos PNG originais).
- [x] (26/10/2026) Rodar a seção **77**. Ela cria a oferta geral pronta ("Ajude o
  site da igreja a crescer", recebendo, capa = arte do repositório) e as
  colunas do **Pix direto** (QR Code sem valor, a pessoa digita quanto
  quer no app do banco). A seção 75 também ganhou a segunda arte do
  carrossel; se ela já tinha rodado, rodar de novo.
- [ ] A conta do Asaas precisa ter uma **chave Pix** cadastrada (ou
  `ASAAS_PIX_CHAVE` no Render): sem ela, o QR do Pix direto não aparece.
- [x] (26/10/2026) Rodar a seção **78** (o que cada projeto mostra ao fiel: total
  arrecadado, número de apoiadores e quadro). Na primeira vez, a oferta
  geral fica sem o total. A oferta geral agora é editada em
  **Admin → Contribuições** (só superadmin); Admin → Missões mostra só as
  missões.
- [ ] As chaves antigas `home_banner_*` em `id_configuracao` ficaram sem
  uso; podem ser apagadas quando quiserem.

### 1.4.5. Filiais (seção 76)

Base para a igreja se dividir em filiais (T22, 1ª parte). Roteiro no
bloco da T22 em [progresso_pedidos_cliente.md](progresso_pedidos_cliente.md).

- [x] (26/10/2026) Rodar a seção **76** do `bancosupabase.md`. Cria a lista provisória
  com os 27 estados e desativa as filiais de exemplo ("Matriz",
  "Filial Centro").
- [ ] Marcar a tela nova **Filiais** nos grupos de permissões que devem
  cuidar dela.
- [ ] Quando o cliente mandar a lista real: cadastrar as filiais em
  Admin → Filiais, desativar os estados e ajustar quem escolheu um estado.

### 1.5. Serviços externos

- [ ] **OneSignal:** confirmar que os 4 campos de *Advanced Push Settings*
  estão digitados (`/`, `sw.js`, `sw.js`, `/`; o placeholder cinza não
  conta) e que *Identity Verification* está desligado. Detalhes em
  [README.md](../../README.md#notificações-push-onesignal).
- [ ] **Brevo:** confirmar em *Remetentes, domínio, IPs* que
  `sunrosefatec@gmail.com` aparece como remetente verificado.

### 1.6. Conferências depois de cada deploy grande

- [x] (26/10/2026) `/saude.php` responde `ok` e `/server-status` dá 404.
- [x] (26/10/2026) Login, logout, "Esqueci minha senha" (o e-mail chega) e troca de
  senha em Configurações.
- [x] (26/10/2026) Aula assistida até o fim gera certificado e notificação.
- [x] (26/10/2026) Busca com e sem acento; hora do chat da live em horário de Brasília.
- [x] (26/10/2026) Console do navegador sem erro de CSP nem de `integrity`.
- [x] (26/10/2026) Push chegando no celular (aprovar um testemunho, por exemplo).
- [ ] IP real gravado nas sessões (não `::1` nem `10.x`). Em 26/10/2026
  estava errado: todas as sessões tinham IP `10.x` (proxy interno do
  Render). Corrigido no código (S14); conferir de novo depois do deploy:

  ```sql
  SELECT ids_ip, ids_criada_em FROM id_sessao ORDER BY ids_criada_em DESC LIMIT 5;
  ```

  Se vier sempre o mesmo IP para pessoas diferentes, o `X-Forwarded-For`
  do Render não traz o IP do visitante e os limites por IP (reset de
  senha, cadastro e login) estão contando todo mundo junto: ajustar
  `site_ip_cliente()` em `php/conexao.php`.

---

## 2. Segurança

Rodada de segurança de 19/10/2026: commits `005829d` a `54c9312`.

**Desde 23/10/2026 a segurança tem documento próprio:**
[seguranca.md](seguranca.md) — achados abertos (S01…S16), rotina de
testes na pasta e no site e registro de cada rodada. Os itens abaixo
continuam valendo; achado novo vai para lá.

**Rodada de 26/10/2026 (S02–S16, ver seguranca.md seção 3) — testar no
Render depois do deploy:**

- [ ] S14: IP público nas sessões depois do deploy (item 1.6 acima).
- [x] (26/10/2026) S11 na homologação: 0 contas com senha em texto puro.
  O código que aceitava essa senha saiu. **Na produção**, rodar antes do
  deploy: `SELECT COUNT(*) FROM ID_FIEL WHERE IDF_SENHA IS NOT NULL;` —
  se der mais que 0, essas contas só entram por "Esqueci minha senha".
- [ ] S04: aulas sem duração não são concluídas pelo fiel até um admin
  abrir a aula uma vez (o player grava a duração). Na homologação, em
  26/10/2026: aulas 1, 2 e 3 ("teste", "teste3", "teste4"). Listar e
  abrir cada uma como admin (na produção também):
  `SELECT IDCT_ID, IDCT_TITULO FROM ID_CONTENT WHERE COALESCE(IDCT_DURACAO_SEGUNDOS, 0) = 0;`
  Depois, como fiel, assistir uma aula até o fim: conclui e gera o
  certificado como antes.
- [ ] S10: enviar um vídeo novo em Admin → Vídeos e trocar o vídeo de uma
  aula (Editar): os dois uploads terminam (o tamanho agora vai assinado).
- [ ] S03: F12 → Console sem erro de "Content Security Policy" na home, numa
  aula, no perfil e no admin; o convite de push da OneSignal aparece. Se
  algo quebrar: Render → `CSP_SO_AVISAR` = `1` e avisar.
- [ ] S08: cadastrar com um e-mail que já tem conta → a tela diz o mesmo
  que um cadastro novo, e o dono recebe o e-mail "Você já tem cadastro".
- [ ] S12: comentar em várias aulas seguidas → depois de 10 em 1 minuto
  aparece "Você comentou muitas vezes no último minuto".
- [ ] S06: um admin num tipo restrito, com a tela Gerenciar Usuários, tenta
  editar um admin de acesso total → "Só administradores com acesso total…".
- [ ] S13 e S16: `tests/checar_site.php` sem falha; abrir
  `admin_testemunhos.php?status[]=x` não dá erro 500.

**Pacote A (grade do curso, métricas, aviso de aparelho novo, CAPTCHA,
novo aceite) — testar na homologação:**

- [ ] **Grade do curso** (Admin → Cursos → ícone de lista, ou Gerenciar
  Vídeos → "grade do curso"): arrastar uma aula para outra posição no
  computador e usar ↑ ↓ no celular; recarregar e conferir que a ordem ficou;
  a ordem nova aparece igual em `php/fiel/curso.php`.
- [ ] **Envio de vários vídeos:** escolher 2 ou 3 vídeos pequenos de uma
  vez; cada um mostra a barra e vira aula "rascunho" no fim da lista; o fiel
  **não** vê a aula em rascunho; ligar a chave (ou "Publicar todas") e o
  fiel passa a ver.
- [ ] **Métricas** (Admin → Métricas): números do funil e dos dias batem
  com o esperado; escolher um curso e uma aula com alguém que parou no meio
  mostra as colunas de "onde param". Marcar a tela nova **Métricas** nos
  tipos de admin que devem vê-la (acesso total já vê).
- [ ] **Aparelho novo:** entrar com uma conta de teste num navegador que ela
  nunca usou (ex.: outro celular) → chega o e-mail "Novo acesso à sua conta";
  entrar de novo no mesmo navegador → não chega outro.
- [ ] **Novo aceite:** no primeiro acesso depois do deploy, todo mundo vê o
  Termo de Uso de novo (versão 2026-10-27, agora com a Política de
  Privacidade). Depois de aceitar, `idf_privacidade_aceita_em` fica
  preenchido. Avisar a igreja que isso vai acontecer uma vez.
- [ ] **CAPTCHA** (só depois de configurar, item 1.1): o quadrinho aparece no
  cadastro e no "Esqueci minha senha"; sem marcar, recusa.
- [ ] Excluir curso: a confirmação agora avisa que as aulas e os vídeos vão
  junto (o texto antigo dizia o contrário).

**Pacote B (protetor, meta diária, números reais, PiP, erros JS, prévia,
cache) — testar na homologação depois de rodar a seção 85:**

- [ ] **Meta diária:** assistir uma aula por ~2 min com a meta em
  Configurações → Preferências (o padrão é 10 min) → o anel da Home
  enche; ao cumprir, aparece o aviso verde uma vez só.
- [ ] **Protetor de ofensiva:** em Ofensiva aparece "Protetores 0/2" e a
  explicação. Para testar sem esperar 7 dias, no SQL Editor:
  `UPDATE id_fiel SET idf_protetores = 1 WHERE idf_email = '<conta de teste>';`
  e apagar o acesso de ontem dessa conta em `id_fiel_acesso_dia` → no
  próximo acesso aparece o aviso azul, o dia de ontem fica com o escudo e
  a ofensiva continua.
- [ ] **Home pública:** os cartões trocam o texto fixo pelos números reais
  (cursos completos, aulas; pessoas só com 30 ou mais).
- [ ] **Picture-in-picture:** botão novo na barra do player; no celular e
  no computador o vídeo sai para a janelinha e continua tocando.
- [ ] **Skeletons:** abrir Comentários, Orações e Resumos da aula com a
  internet lenta (F12 → Network → Slow 4G) → blocos cinza em vez do
  "carregando".
- [ ] **Cache dos CSS/JS:** F12 → Network → recarregar → `styles.css?v=…`
  vem "(memory/disk cache)" na segunda vez; depois de um deploy o `v=`
  muda sozinho.
- [ ] **Erros de JavaScript** (só com `SENTRY_DSN`): no Console de uma
  página, `setTimeout(function(){ throw new Error('teste JS') })` → em
  alguns segundos aparece no Sentry como `ErroJavaScript`.
- [ ] **Prévia no WhatsApp:** mandar o link da home num chat → aparece a
  imagem `assets/img/compartilhar.jpg`. (O WhatsApp guarda a prévia antiga
  por um tempo; testar com `?x=1` no fim do link.)
- [ ] **Lighthouse** (Chrome → F12 → Lighthouse → modo celular) na home,
  numa aula e no perfil: meta ≥ 90 em Acessibilidade e Desempenho. Mandar
  o que der vermelho.

**Fim da Fase 1 — testar na homologação (sem seção nova de banco):**

- [ ] **Visual de todas as telas** (público, fiel e admin, no celular e no
  computador): o Bootstrap e os ícones agora vêm do próprio site
  (`css/vendor/`, `js/vendor/`). Conferir se menus, modais, ícones e o
  carrossel aparecem como antes; F12 → Console sem erro.
- [ ] **Notificações do admin:** uma notificação com destino (ex.: resposta a
  um comentário) mostra o botão "Ver" e abre a tela certa.
- [ ] **Recusado que se cadastra de novo:** recusar uma conta de teste e
  cadastrar de novo com o mesmo e-mail → vai para a tela de espera, chega o
  e-mail "Recebemos o seu novo pedido" e a conta volta para Aprovar Usuários.
- [ ] **Grade do curso:** uma aula sem duração mostra o aviso amarelo; abrir
  a aula pelo aviso grava a duração e o aviso some.
- [ ] **2FA de outro admin** (só a equipe interna): em Gerenciar Usuários →
  editar um admin com o 2FA ligado aparece "Desligar a verificação", que
  pede a sua senha; depois o admin entra só com a senha e liga de novo.

**Pacote G, parte rápida (confiança no painel) — testar na homologação
depois de rodar a seção 89:**

- [ ] **Registro de atividade:** lançar e cancelar algo no Financeiro, dar
  uma cortesia em Assinaturas, aprovar um testemunho, salvar uma filial →
  tudo aparece em Admin → Registro de atividade. Editar o nome de uma
  pessoa mostra "antes → depois".
- [ ] **Financeiro:** "cancelar" um lançamento pede o motivo; ele fica na
  lista riscado ("Cancelado em … : motivo"), sai dos totais e aparece no
  CSV como "CANCELADO" com valor zero.
- [ ] **Ofertas manuais** (Missões e Contribuições): o mesmo — cancelada com
  motivo, continua na lista e sai dos totais.
- [ ] **Saúde do sistema** no Dashboard (acesso total e equipe): "Tudo certo
  nas últimas 24 horas" e o último aviso de pagamento recebido. Com a Brevo
  ou a OneSignal falhando, aparece a contagem.
- [ ] **Enviar Mensagem:** depois de preencher, aparece "Você vai enviar para
  N pessoa(s)" com a prévia do aviso; só sai com "Confirmar e enviar". No
  fim, a mensagem diz se o aviso no celular saiu.
- [ ] **CPF** em Gerenciar Usuários: um CPF com dígito errado é recusado; um
  que já é de outra conta mostra "Este CPF já pertence a outra conta".
- [ ] **Adicionar pessoa** (Gerenciar Usuários): cadastrar alguém com o seu
  e-mail de teste → chega "Sua conta no app da igreja: crie sua senha";
  pelo link, criar a senha, entrar e aceitar o Termo. Menor de 18 sem
  responsável é recusado.

**Pacote H (primeira impressão do fiel) — testar na homologação (sem seção
nova de banco):**

- [ ] **Admin → Identidade da igreja:** preencher com os dados reais (nome,
  quem somos, pastor, horários, endereço + link do mapa, WhatsApp, redes,
  recebedor e CNPJ). Um link sem `https://` ou um CNPJ errado é recusado
  sem perder o resto do que foi digitado. Aparece em Registro de
  atividade.
- [ ] **Home pública** (no celular e no computador): o nome da igreja no
  topo, a frase, "Como funciona" em 3 passos, "Venha nos visitar" com
  horários/endereço/redes (some se nada estiver preenchido) e a faixa de
  fotos sem os prints do site. No celular: logo, "Entrar" e ☰ abrindo o
  menu.
- [ ] **Sobre:** "Quem somos", pastor, cultos, onde estamos, fale com a
  gente; nada de depoimento inventado.
- [ ] **Cadastro:** o topo avisa que um líder confere; rótulos em todos os
  campos; as 4 regras da senha marcam ✓ enquanto digita; depois de enviar,
  a tela "Cadastro recebido" (com o WhatsApp da igreja, se preenchido).
- [ ] **Login** de conta pendente, recusada ou desativada: a mensagem nova e
  o link de WhatsApp/e-mail da igreja.
- [ ] **Aula:** excluir um comentário/nota/oração abre a confirmação no
  visual do app (não mais a caixa cinza "…onrender.com diz:"); sem
  internet, aparece o aviso embaixo da tela.
- [ ] **Última aula de um curso** até o fim: "Parabéns! Você concluiu o
  curso 🎉" e o botão "Ver meu certificado".
- [ ] **Home do fiel:** "Bom dia/Boa tarde/Boa noite, Nome!"; conta nova vê
  "Comece por aqui"; "Assistir agora" do carrossel abre a aula; rodapé com
  Sobre, Fale conosco, Privacidade e Termos.
- [ ] **Busca:** procurar pelo nome de um curso → aparece em "Cursos";
  voltar no navegador não pede mais "Confirmar reenvio".
- [ ] **Contribuir:** o texto mostra "Você está contribuindo para…" com o
  recebedor e o CNPJ preenchidos na Identidade.
- [ ] **Live sem transmissão:** mostra os horários dos cultos; com uma live
  agendada e a página aberta, ela recarrega sozinha quando ficar ao vivo.

**Pacote F (robustez) — testar na homologação (sem seção nova de banco):**

- [ ] **Dashboard do admin (equipe interna):** na homologação não aparece
  aviso de variável faltando. Se o cron-job.org do lembrete não chamar por
  mais de 26 h, aparece "O agendador do lembrete diário não chama o app
  desde…".
- [ ] **`/saude.php?banco=1`** responde `ok`; `/saude.php` continua `ok`.
  Trocar o UptimeRobot para a URL com `?banco=1` (qualidade_e_ci.md 3.5).
- [ ] **cron-job.org:** a chamada do lembrete continua dando 200 (com 500,
  o próprio cron-job.org avisa por e-mail; o detalhe vai ao Sentry).
- [ ] **Navegar normalmente** (login, aula, live, sair): nada muda para quem
  usa. Num soluço do banco, a página falha uma vez em vez de deslogar.
- [ ] **App instalado:** com o celular em modo avião, abrir o app → aparece
  "Você está sem internet" com "Tentar de novo" (antes, o erro do
  navegador).
- [ ] **Sentry:** depois de uns dias, conferir em Issues se apareceu algo
  como `WebhookAsaas`, `EmailFalhou`, `PushFalhou`, `RotinaFalhou` ou
  `BancoFora` e avisar.

**Pacote E (desempenho) — testar na homologação depois de rodar a seção 88:**

- [ ] **Navegar normalmente** por uns minutos (Home, cursos, aula, perfil,
  sair e entrar): nada de deslogar sozinho nem de erro. A sessão agora é
  lida uma vez por página e renovada a cada 5 min.
- [ ] **Assistir uma aula até o fim:** o % do curso sobe quando a aula
  conclui, a próxima abre de onde parou, o Histórico mostra a aula do dia
  (pode levar até 2 min para o tempo atualizar) e a meta diária anda.
- [ ] **Home:** os cursos aparecem como antes. Criar ou publicar um curso ou
  uma aula no admin aparece na Home na hora (o admin apaga o cache).
- [ ] **Live com 2 navegadores:** mensagem aparece no outro como antes.
- [ ] **Push:** a 2ª página aberta na mesma aba não chama mais
  `ajax_onesignal_sync.php` (F12 → Network); notificações continuam
  chegando.
- [ ] **Teste de carga do culto** (tests/carga/README.md, `CENARIO=culto`),
  com ~50 contas de teste e uma live AO VIVO: anotar o resultado na tabela
  do README. É o que diz se o plano do Render aguenta.

**Pacote D (bugs e segurança da revisão geral) — testar na homologação
depois de rodar a seção 87:**

- [ ] **Push da live:** com um celular com o app instalado e as
  notificações aceitas, **fora do app**, marcar uma live de teste como Ao
  Vivo → chega "Estamos ao vivo!" na hora. Mesmo teste com Admin → Enviar
  Mensagem.
- [ ] **Excluir curso com alunos:** aparece o aviso com o número de alunos e
  de certificados, e os botões "Arquivar (recomendado)" e "Excluir mesmo
  assim". Arquivar tira o curso do ar e o fiel continua vendo o
  certificado.
- [ ] **Contato:** sem `CONTATO_FORMSPREE_URL` no Render, o formulário some
  (ficam só os contatos). Com a variável, funciona como antes.
- [ ] **Menor:** cadastro sem data de nascimento é recusado; a tela de
  aprovação mostra a idade e o selo "Menor" com o responsável. Ver também
  [lgpd.md](lgpd.md), seção 6, itens 9 e 10.
- [ ] **Dashboard do admin:** um tipo de admin só com "Cursos" não vê as
  listas de pendentes e recentes (CPF, telefone). No celular, as tabelas
  rolam de lado.
- [ ] **Modo "só assinantes"** (se estiver ligado): quem não assina abre
  Configurações e Contato (baixar dados, excluir conta).
- [ ] **Baixar meus dados** pede a senha; repetir em menos de 10 min recusa.
- [ ] **Certificado:** Imprimir / Salvar PDF sai com texto escuro em fundo
  branco. Curso 100% concluído mostra "Ver certificado" na página do curso.
- [ ] **Progresso:** assistir uma aula normalmente (1x e 2x) continua
  concluindo e gerando o certificado. Duas aulas abertas ao mesmo tempo
  não somam progresso nas duas.
- [ ] **Moderação:** admin só com "Cursos" não vê mais "excluir" no
  comentário de outra pessoa; com "Testemunhos" ou "Gerenciar Vídeos",
  remove e aparece em Registro de atividade.
- [ ] **Notificações:** "Ver" numa não lida apaga o selo e abre o link.
- [ ] **Live:** uma live antiga "só admins" encerrada não aparece mais para o
  fiel ("nenhuma transmissão"). "Entrar" no chat volta para a live depois do
  login.

**Pacote C (chat da live, registro das seções do banco, imagens) — testar
na homologação depois de rodar a seção 86:**

- [ ] **Chat da live** com 2 navegadores (um logado, outro não): mensagem
  enviada aparece no outro em até ~10 s (com conversa andando, ~3 s);
  ocultar uma mensagem no admin some dos dois em até ~12 s; bloquear o
  fiel no chat trava a caixa dele em até ~30 s (e o envio já é recusado
  na hora). F12 → Network: com o chat parado, as respostas de
  `ajax_transmissao_comentarios.php` vêm com `"igual":true`.
- [ ] **Live só para fiéis:** quem não está logado continua sem ver o chat.
- [ ] **Registro das seções:** antes de rodar a 86, a equipe interna vê no
  Dashboard do admin o aviso amarelo pedindo a seção 86; depois de rodar,
  em até 1 min o aviso some. Um admin comum nunca vê esse aviso.
- [ ] **Home pública:** o carrossel "Sobre nós" continua igual (F12 →
  Network → as imagens vêm em `.webp`).

**Rodada de 27/10/2026 (LGPD, registro de atividade, 2FA, admin) — testar
no Render depois de rodar as seções 82 a 84:**

- [ ] LGPD: roteiro em [lgpd.md](lgpd.md), seção 6 (cadastro de menor,
  aceite obrigatório, baixar dados, excluir conta com e sem pagamento).
- [ ] 2FA: Admin → **Minha segurança** → escanear o QR no Google
  Authenticator, ligar, guardar os códigos de reserva; sair e entrar (pede
  o código); entrar com um código de reserva (vale uma vez só); 5 códigos
  errados voltam para a senha; desligar.
- [ ] Registro de atividade: excluir uma aula de teste, aprovar um cadastro,
  mandar uma mensagem e mudar o tipo de um admin → aparecem em Admin →
  **Registro de atividade**, com filtros. Marcar a tela nova nos tipos de
  admin que devem vê-la (quem tem acesso total já vê).
- [ ] Aprovar usuários: "Aprovar selecionados", busca e paginação; a coluna
  "Por" do histórico passa a mostrar quem decidiu (antes era sempre
  "Sistema": a gravação falhava pela chave estrangeira).
- [ ] Enviar Mensagem: buscar pessoas, e os destinos novos (alunos de um
  curso, quem está sumido há X dias, uma filial).
- [ ] Gerenciar Vídeos: filtro por curso, busca, paginação e "Ver" abrindo
  o vídeo. Ver Progresso: busca e "Últimas aulas assistidas".
- [ ] Live com 2 navegadores: mensagem enviada aparece no outro em até 5 s;
  ocultar no admin some em até 5 s.

**Configuração (fora do código):**

- [x] **Senhas das contas de exemplo** (`admin@igreja.com`, `joao@`,
  `maria@`, `carlos@email.com`): trocadas na homologação em 23/10/2026
  (conferido no banco: `123456` não vale em nenhuma). Se a produção tiver
  banco próprio, trocar lá também, no SQL Editor:

  ```sql
  UPDATE id_fiel SET idf_senha_hash = extensions.crypt('NOVA_SENHA', extensions.gen_salt('bf', 10))
  WHERE lower(idf_email) = 'admin@igreja.com';
  ```

- [x] **`ONESIGNAL_EXTERNAL_ID_SEGREDO`** no Render (criado; conferido em 21/10/2026) (sequência longa e
  aleatória). Hoje o fiel é `fiel-<id>` na OneSignal, e qualquer pessoa
  pode rodar `OneSignal.login('fiel-42')` no console e receber os pushes de
  outro fiel. Ao ligar (ou trocar), cada aparelho só volta a receber push
  quando o fiel abrir o app de novo: não trocar à toa.
- [ ] **Brevo:** reativar o bloqueio de IP para *Chaves API* (desligado
  desde 04/10/2026) e autorizar as faixas de saída do Render (serviço →
  Connect → Outbound). Contexto em
  [README.md](../../README.md#bloqueio-de-ip-na-brevo--decisão-atual).
- [ ] **Brevo:** uma chave por ambiente (`mananciapp-homolog`,
  `mananciapp-prod`) e, de tempos em tempos, olhar *Endereços IP não
  autorizados* e as estatísticas de envio.

**Código (deixados de fora de propósito na rodada de 19/10):**

- [x] `content-type` assinado na URL de PUT do R2 (21/10/2026): o tipo sai
  da extensão (`r2_upload_url.php`) e o R2 recusa outro. Testado contra o
  R2: tipo certo passa, errado ou ausente dá 403.
- [x] Links GET de vídeo valem 2 h (2 a 3 h com o arredondamento) e o
  player renova sozinho (21/10/2026): erro no vídeo depois de 90 min com a
  página aberta → grava a posição, recarrega e continua do mesmo ponto.
- [x] CSP sem `'unsafe-inline'` nos scripts (21/10/2026): cada página PHP
  manda a própria CSP com nonce (`site_csp_politica()` em `conexao.php`);
  os 36 `<script>` embutidos têm nonce e os 36 `onsubmit=`/`onchange=`…
  viraram `data-confirmar`, `data-enviar-ao-mudar` etc. (`js/acoes.js`). O
  `.htaccess` ficou só como padrão para os estáticos (`setifempty`).
  Testado num Apache local: uma CSP por resposta, nonce do cabeçalho igual
  ao dos scripts. Estilos seguem com `'unsafe-inline'` (346 `style=`).
  **Se uma tela quebrar:** Render → `CSP_SO_AVISAR` = `1` — a política nova
  só avisa no Console e a antiga volta a valer, sem reverter código.
- [ ] **Testar no Render depois do deploy** (F12 → Console, procurar
  "Content Security Policy"): login, cadastro, Home, aula (player, prévias
  dos cards), comentários/orações/resumos, live (chat e moderação),
  perfil (foto), assinatura, admin (excluir algo — deve pedir confirmação —,
  filtros que enviam ao mudar, envio de vídeo), certificado (imprimir),
  convite de push da OneSignal.
- [x] Banco fora do ar não derruba mais o cadastro e as telas que chamam
  `tabela_tem_coluna()` com erro 500 (22/10/2026): o
  `mysqli_real_escape_string()` do shim chamava `quote()` numa conexão
  `false`.
- [x] Aparelho de invasor deixa de receber os pushes depois da troca ou do
  reset de senha (21/10/2026): o `external_id` da OneSignal leva uma marca
  da senha atual (`onesignal_marca_senha()`). **No deploy disso**, cada
  aparelho só volta a receber push quando o fiel abrir o app nele (como ao
  ligar o segredo); o mesmo vale para os outros aparelhos de quem trocar a
  senha.

**A conferir:**

- [ ] Se o Render acrescenta o IP real no `X-Forwarded-For` (ver 1.6).
- [ ] Se o rastreamento de cliques da Brevo reescreve o link do e-mail de
  recuperação (o token passaria pelo servidor da Brevo).
- [ ] Se a resposta antecipada de `esqueci_senha.php` chega antes do envio
  do e-mail no Render.
- [x] Vídeos antigos na pasta local `videos/` (22/10/2026): a pasta está no
  `.gitignore` e no `.dockerignore`, então nunca entra na imagem do Render;
  nada ali é servido pelo Apache em produção.

**Jurídico (não é código):**

- [ ] Revisão jurídica do Termo de Uso, principalmente direito de imagem
  nas lives (e menores de idade nos cultos).
- [ ] LGPD: o app guarda CPF, telefone, data de nascimento e, com as
  assinaturas, histórico de cobrança.

---

## 3. Desempenho

Próximo foco combinado depois da rodada de segurança. **Meta de 300
pessoas ao mesmo tempo e o que é pago: [capacidade.md](capacidade.md)**
(cache da live, presença e ranking feitos em 27/10/2026; teste de carga em
`tests/carga/`).

- [ ] Rodar o teste de carga (`tests/carga/README.md`) com 50, 100, 200 e
  300 pessoas, antes e depois da troca de plano/região, e anotar lá.

- [ ] **Região do Render.** O banco está em **us-east-1 (Virgínia)** e o
  serviço parece estar em Oregon (o padrão): cada consulta custa ~55–60 ms
  e a Home faz ~21. Na mesma região, cai para 1–2 ms por consulta.
  - [ ] Confirmar a região em Render → Settings.
  - [ ] Se for Oregon: criar um serviço novo em **Virginia (US East)**,
    copiar todas as variáveis, conferir e trocar o endereço/domínio para
    ele (o Render não muda a região de um serviço existente).
  - [ ] Atualizar `SITE_URL_PUBLICA`, o CORS do R2 e o *Site URL* da
    OneSignal se o endereço mudar.
- [x] **`PGSQL_ATTR_DISABLE_PREPARES`** na conexão (21/10/2026): 1 ida ao
  banco por consulta em vez de 3 (~408 ms → ~133 ms medidos daqui).
- [x] **`session_write_close()`** nos AJAX de polling e de chamada externa
  (21/10/2026): chat público da live, moderação do admin e
  `ajax_onesignal_sync.php`, além dos que já tinham (`ajax_presenca.php`,
  `ajax_transmissao_status.php`, `esqueci_senha.php`). **Não** dá para
  fechar cedo nos AJAX de comentários, orações, resumos e progresso: as
  medalhas e a ofensiva gravam o aviso na sessão.
- [x] **Polling** (21/10/2026): o indicador "Ao Vivo" (toda página, 60 s) e a
  moderação do admin (5 s) pausam com a aba escondida, como já faziam o
  chat e a presença. O heartbeat do player só envia com o vídeo tocando.
- [ ] **Cache da Home** (filas, "Em alta", testemunhos) por alguns minutos.
  **Deixado para depois da troca de região.** Contagem de 21/10/2026: o
  corpo da Home faz 21 consultas (já sem N+1; estatísticas, percentuais e
  "Em alta" têm cache por requisição). Um cache entre requisições pouparia
  ~8 (destaque, novos, estatísticas, "Em alta", mais assistidos, categorias,
  devocional, testemunhos): ~0,45 s com o Render em Oregon, ~10 ms na mesma
  região do banco. Só vale se a região não mudar.

Retirado: "cache do `sw.js` sem limite". O service worker só guarda arquivos
estáticos do próprio site (~3 MB, sem URLs com `?v=`) e apaga o cache
antigo quando o `CACHE_VERSION` muda; não cresce sem fim.

Retirado (22/10/2026): "`session_write_close()` nas páginas comuns". O
corpo das páginas grava caches e avisos na sessão (`ofensiva_cache`,
`foto_versao`, `push_marca`, `medalhas_novas`, `aulas_abertas`); fechar
cedo perderia esses caches (mais consultas por página) e os avisos de
medalha. A trava só atrasava os AJAX de polling, que já fecham a sessão.

---

## 4. Qualidade e manutenção do código

- [x] Texto do usuário só entra no SQL por `db_query` (22/10/2026): todas
  as consultas que interpolavam texto com `mysqli_real_escape_string`
  (`conexao.php`, 10 telas do admin, perfil, termos, cursos, comentários,
  chat da live, presença e webhook do Asaas) viraram consultas preparadas;
  o escape manual só existe no shim. Ficaram interpoladas as que levam só
  `(int)`, constantes ou datas já validadas (seguras; migrar ao passar pelo
  arquivo). `asaas_sql()` aceita `$params` opcional.
- [ ] **Testar no Render depois do deploy** (nada rodou contra Postgres):
  avisos de transmissão e mensagens do admin para todos (`INSERT ...
  SELECT` com `CAST(? AS TEXT)`), oração atendida, novo desafio (com datas
  e "ao entrar"), devocional (enviar, aprovar, recusar), moderar
  testemunho, resumo, nota de aula, preferências (com e sem lembrete),
  perfil (campos vazios, foto, membro desde), comentário em vídeo e chat
  da live, aceite do termo, cursos com filtro, criar/editar curso, aula,
  plano, função e usuário (e-mail duplicado), aprovar/negar cadastro,
  webhook do Asaas (pagamento confirmado, vencido, cancelado). Gravar um
  texto com aspas e barra invertida e conferir que sai igual.
- [x] Rótulo acessível nos campos de login, cadastro, esqueci/redefinir a
  senha e contato (21/10/2026): `aria-label` com o texto do placeholder (a
  busca já tinha `<label>`), e nos botões "mostrar senha".
- [x] Acentos nas mensagens mostradas ao usuário (21/10/2026): login,
  cadastro, contato e envio de vídeo do admin ("inválido", "não", "já
  está"…). Pode sobrar alguma palavra solta fora desse padrão; corrigir ao
  passar pelo arquivo.
- [x] `trim()` de `$_POST`/`$_GET` que pode receber array (21/10/2026): os
  39 pontos diretos usam `site_entrada_texto()` (inclusive o cadastro
  público e a senha dele). Desde 26/10/2026 os 72 `(string) ($_POST[...] ?? '')`
  também (array vira vazio, sem aviso no log); código novo deve usar a função.
- [x] `formatar_telefone_exibicao` agora é uma só, em `php/conexao.php`
  (21/10/2026; antes havia cópias em 3 telas do admin).
- [x] Apagadas as páginas sem uso `php/admin/header_admin.php` e
  `php/fiel/dashboardusuario.php` (21/10/2026).
- [x] Testes automáticos das funções puras (21/10/2026):
  `tests/testes_funcoes.php`, 57 testes sem banco e sem rede (entrada,
  senha, telefone, datas, redirecionamento pós-login, recuperação, IP,
  YouTube, shim SQL, URL assinada do R2). Rodar de dentro de `MananciAPP/`:
  `C:\xampp\php\php.exe tests\testes_funcoes.php`.
- [ ] Testes que precisam de banco (regras de assinatura, medalhas,
  desafios): exigiriam um Postgres de teste separado.
- [x] CI no GitHub Actions (27/10/2026): sintaxe e testes a cada push,
  banco montado do zero com testes com banco (`tests/testes_banco.php`),
  navegador (Playwright, `tests/e2e/`) e `checar_site.php` toda segunda,
  Dependabot, alerta de erros (Sentry) pronto no código.
- [ ] **Passo a passo do seu lado** (contas de teste, Sentry, UptimeRobot,
  homologação separada da produção): [qualidade_e_ci.md](qualidade_e_ci.md), seção 3.
- [ ] Nome antigo **"SiteManancial"** nos e-mails de aprovação
  (`admin_aprovar_usuarios.php`) e no remetente padrão (`conexao.php`);
  confirmar com o cliente o nome definitivo (o `manifest.json` usa
  "Manancial da Esperança").
- [ ] Filiais fictícias no `bancosupabase.md` (`Matriz - São Paulo`,
  `Filial Centro - Campinas`): trocar pelas reais quando vierem (T22).
- [ ] Quando o schema mudar, gerar de novo os bancos MySQL da faculdade
  (`database/Banco Igreja.sql` e `Banco Igreja Robusto.sql`).

---

## 5. Decisões internas (equipe)

Assuntos para a equipe decidir antes de levar ao cliente ou implementar.
Vieram de `progresso_pedidos_cliente.md` (15/10/2026) e da rodada de
segurança.

### 5.1. Cartão de crédito da Cloudflare

A conta da Cloudflare (vídeos e capas no R2) está no e-mail
`sunrosefatec@...` e com o **cartão de um de nós**. Ela exige cartão mesmo
na faixa grátis; hoje o custo é **zero** (bem abaixo dos 10 GB grátis) e há
alerta por e-mail ao passar de 8 GB. O R2 não tem teto que bloqueie a
cobrança. Acima do grátis: US$ 0,015 por GB/mês (50 GB ≈ US$ 0,60/mês); o
tráfego de quem assiste é sempre grátis.

| Opção | Prós | Contras |
|---|---|---|
| **A.** Manter na nossa conta e repassar o custo | Nada muda; controle técnico com a gente | O cartão segue sendo nosso |
| **B.** Trocar pelo cartão da igreja, na mesma conta | Cobrança direto para quem usa | A igreja precisa cadastrar o cartão |
| **C.** Passar a conta para a igreja e ficarmos como membros | A igreja é dona de tudo | Mais trabalho agora; precisamos ser convidados |

**Sugestão:** B agora e C na entrega, de preferência com **cartão virtual
de limite baixo**. Decidir: quem é o dono da conta no longo prazo, quem paga
se passar do grátis (e como entra no contrato) e quem recebe o alerta.

### 5.2. Vídeo apagado some na hora

Ao excluir aula, curso ou trocar vídeo/capa, o app apaga o arquivo do R2 na
hora. O R2 **não tem lixeira**: aula excluída por engano não volta.

| Opção | Como funciona | Esforço |
|---|---|---|
| **A.** Guardar os originais fora do app | Drive/HD da igreja; o app segue apagando na hora | Nenhum código |
| **B.** Lixeira no app | Excluir esconde por 30 dias, com "Restaurar" no admin; depois apaga de vez | ~1 dia + uma coluna no banco |
| **C.** Confirmação dupla | Digitar o nome da aula para excluir | Poucas horas; não desfaz nada |
| **D.** Trava no bucket do R2 | O R2 recusa apagar arquivos recentes | Só painel, mas sobram arquivos para limpar à mão |

**Sugestão:** A desde já + B quando houver conteúdo real. Decidir: fazer a
lixeira agora ou depois; prazo (7, 15 ou 30 dias); vale também para curso
inteiro?

### 5.3. Aba "Lives anteriores"

As lives ficam salvas no YouTube, mas depois que acabam o fiel não as
reencontra pelo app. O app **já guarda** cada live em `id_transmissao`
(título, ID do YouTube, início e fim) e o chat em
`id_transmissao_comentario`, então a lista existe sem API do YouTube.

| Caminho | Como funciona | Prós | Contras |
|---|---|---|---|
| **A.** Aba com o player do YouTube | Lista as lives encerradas (o admin escolhe quais) | ~1 dia; zero armazenamento | Depende do YouTube; não conta progresso/medalhas |
| **B.** "Transformar em aula" | O dono do canal baixa a live no YouTube Studio (⋮ → Baixar), comprime e sobe como aula | Aula completa (progresso, certificado, medalhas) | Manual; ocupa espaço no R2 |
| **C.** Aula tocando o YouTube | A aula guarda o link do YouTube | Sem armazenamento | O player das aulas teria que ser refeito |

Baixar do YouTube **por código** vai contra os termos dele; por isso o B é
manual. **Sugestão:** A primeiro e B como processo para as lives que valem
virar aula. Decidir: todas as lives ou o admin escolhe; só assinantes?; por
quanto tempo ficam na lista; mostrar o chat junto; quem faz o B na igreja.

### 5.4. Outras

- [ ] Lives "Somente fiéis" devem exigir assinatura quando "Quem pode
  assistir" estiver em "Só assinantes"?
- [ ] Antes de cobrar de verdade: criar um **segundo serviço no Render**
  (homologação) com as chaves de sandbox, para testar mudanças na cobrança
  sem mexer na produção? Hoje todo push na `main` vai direto para o ar.
- [ ] Quem recebe e reage aos alertas (site fora do ar, webhook do Asaas
  falhando), e em quanto tempo.
- [ ] Quem tem acesso ao painel do Render, do Supabase, do Asaas e da
  Cloudflare. Trocar as chaves quando alguém sair da equipe.
- [ ] Rotina mínima mensal: chaves válidas, avisos de API do Asaas/OneSignal,
  versões do PHP (Dockerfile) e das bibliotecas do CDN.

### 5.5. Vender para outras igrejas

A conversa com a Manancial está em
[pontos_para_alinhar.md](../para_o_cliente/pontos_para_alinhar.md#12-levar-a-plataforma-a-outras-igrejas);
o roteiro técnico em [plano_de_melhorias.md](plano_de_melhorias.md#6-virar-produto-para-outras-igrejas).
Decisões da equipe, antes de oferecer a qualquer igreja:

| Decisão | Opções | Sugestão |
|---|---|---|
| **Modelo técnico** | A. Uma instalação por igreja (serviço + banco próprios) · B. Um sistema único com várias igrejas (multi-tenant) | A até ~10 igrejas; B quando manter N instalações pesar |
| **Marca configurável** | "Manancial" está fixo em ~130 pontos do código | Centralizar nome, logo, cores, e-mail e domínio em configuração (pré-requisito de qualquer venda) |
| **Preço** | Mensalidade fixa · por número de membros ativos · por GB de vídeo · % das assinaturas | Faixas por membros ativos + limite de GB, com implantação cobrada à parte |
| **Quem é o dono do código** | Equipe · igreja · divisão | Equipe (licença de uso para cada igreja); deixar claro no acordo com a Manancial |
| **Empresa** | CPF de um de nós · MEI · ME/LTDA | Com mais de uma igreja pagando, CNPJ e nota fiscal (falar com contador; ver seção 8) |
| **Contrato** | — | Contrato de serviço + **termo de tratamento de dados (LGPD)**: a igreja é controladora, a equipe é operadora ([lgpd.md](lgpd.md)) |
| **Suporte** | WhatsApp · e-mail · central de ajuda | Canal único com prazo de resposta (ex.: 1 dia útil) e uma central de ajuda curta |
| **Custos por igreja** | Render + Supabase + R2 + domínio | ~US$ 50/mês por igreja no modelo A ([capacidade.md](capacidade.md)); o preço precisa cobrir isso com folga |

- [ ] Responder as decisões acima.
- [ ] Página de vendas `/para-igrejas` e uma demonstração com dados fictícios.

---

## 6. Depende do cliente

As perguntas de cada tarefa estão em
[pontos_para_alinhar.md](../para_o_cliente/pontos_para_alinhar.md); o
resumo, em [progresso_pedidos_cliente.md](progresso_pedidos_cliente.md#pendências-com-o-cliente).

- [ ] **Um site por igreja** (define T15, T22 e outras): responder
  primeiro.
- [ ] **T15:** assinatura só a partir de fevereiro/março de 2027 (ver
  "Lançamento em 01/12/2026"). Valores dos planos e regras de acesso, decididos meio a
  meio com a equipe (a conta que recebe já é da equipe; ver
  [assinaturas_virada_producao.md](assinaturas/assinaturas_virada_producao.md)).
- [ ] **T22 / T24:** lista real das filiais (hoje são os estados), células, tempo de fé e títulos do perfil.
- [ ] **T32:** quais projetos de missão entram, com fotos e textos.
- [ ] **Acordo por escrito** com a igreja sobre a divisão do lucro (hoje
  50%) e o destino do dinheiro de missões.
- [ ] **Licença de música** (ex.: CCLI Streaming License) se o louvor
  das lives usa música de terceiros: sem ela o YouTube pode derrubar ou
  silenciar a live.
- [ ] Refazer o print `assets/img/desktop-passo-5.jpg` (tutorial "Como
  instalar", computador): mostra o atalho "Login Registro de Ponto" e um
  papel de parede de anime.
- [ ] Refazer o print `assets/img/ios-passo-2.jpg` (tutorial "Como
  instalar", iPhone): também é do projeto "Registro de Ponto". Desde
  27/10/2026 os passos 2 e 3 ficaram sem imagem (o print dizia ser a tela de
  login da Manancial); o passo 4 segue com ele só pelo botão "•••". Tirar
  um print da tela de login do app no Safari, com a barra de baixo aparecendo.
- [ ] **Levar a plataforma a outras igrejas**: seção 12 de
  [pontos_para_alinhar.md](../para_o_cliente/pontos_para_alinhar.md#12-levar-a-plataforma-a-outras-igrejas).

---

## 7. Validar as tarefas do cliente

- [x] T01 a T33 validadas no Render (26/10/2026) e marcadas ✅ no quadro
  de [progresso_pedidos_cliente.md](progresso_pedidos_cliente.md). T22 e
  T24 seguem com ⏸: a 1ª parte está validada, o resto espera a lista real
  das filiais e os títulos do perfil.

Tarefa nova entra como 🧪, com o roteiro de teste no bloco "Entregue", e
vira ✅ depois de validada no Render.

---

## 8. Antes da produção (com dinheiro real)

- [ ] **Plano do Render que não hiberna.** Com o gratuito dormindo, o
  webhook do Asaas pode falhar; depois de 15 falhas seguidas o Asaas para
  de enviar e ninguém recebe acesso até reativar no painel. (O
  cron-job.org do item 1.2 ajuda, mas não garante.)
- [ ] **Asaas de produção:** `ASAAS_API_URL` = `https://api.asaas.com/v3`,
  `ASAAS_API_KEY` e `ASAAS_WEBHOOK_TOKEN` novos, webhook cadastrado (passo
  a passo em [assinaturas_virada_producao.md](assinaturas/assinaturas_virada_producao.md)).
- [ ] **Domínio próprio** da igreja (cobrar num `*.onrender.com` passa
  menos confiança) e `SITE_URL_PUBLICA` apontando para ele. Atualizar
  também o CORS do R2 e o *Site URL* da OneSignal.
- [ ] **Brevo:** SPF, DKIM e DMARC no domínio remetente (sem isso o e-mail
  cai no spam).
- [x] (26/10/2026) Rodar as seções 65 a 78 do `bancosupabase.md` no banco de produção.
- [x] (27/10/2026) Rodar as seções **79 a 81** (medalha "No bolso", Primeiros passos e
  capa do perfil, medalhas novas/secretas e vitrine) em **homolog e em
  produção**. São idempotentes e precisam rodar nessa ordem. Sem elas, as
  telas novas de medalhas e o perfil ficam sem as colunas `idad_pelo_app`,
  `idf_capa`, `idf_medalhas_destaque` e `idbadge_secreta`. Para conferir:

  ```sql
  -- Deve listar as 4 colunas.
  SELECT table_name, column_name FROM information_schema.columns
  WHERE table_schema = 'public'
    AND column_name IN ('idad_pelo_app', 'idf_capa', 'idf_medalhas_destaque', 'idbadge_secreta')
  ORDER BY 1;

  -- Deve dar 31 (No bolso + 2 dos Primeiros passos + 28 da seção 81).
  SELECT COUNT(*) FROM id_badge
  WHERE idbadge_chave IN ('app_no_bolso', 'passos_espaco', 'passos_app')
     OR idbadge_categoria IN ('estudos', 'comentarios', 'testemunhos', 'devocionais', 'ao_vivo', 'app', 'secretas')
     OR idbadge_chave IN ('oracao_pedido_1', 'oracao_respondida');
  ```

- [x] (27/10/2026) Rodar as seções **82 a 84** (LGPD; registro de atividade do painel;
  verificação em duas etapas) em **homolog e em produção**. Idempotentes,
  nessa ordem. Sem elas, o app segue como antes (o cadastro não grava o
  aceite, o registro de atividade e o 2FA avisam que falta a seção).
  Para conferir:

  ```sql
  -- Deve listar as 7 colunas e as 2 tabelas.
  SELECT column_name FROM information_schema.columns
  WHERE table_schema = 'public' AND table_name = 'id_fiel'
    AND column_name IN ('idf_privacidade_aceita_em', 'idf_responsavel_nome', 'idf_responsavel_consentiu_em',
                        'idf_2fa_segredo', 'idf_2fa_ativo_em', 'idf_2fa_ultimo_passo', 'idf_2fa_reserva');
  SELECT table_name FROM information_schema.tables
  WHERE table_schema = 'public' AND table_name IN ('id_lgpd_pedido', 'id_admin_log');
  ```

**Seções 85 a 90:** rodadas na homologação em 29/10/2026. Falta só a
produção (cada item abaixo continua aberto até rodar lá).

- [ ] Rodar a seção **85** (protetor de ofensiva e meta diária) em
  **homolog e em produção**. Idempotente. Sem ela o app segue como antes:
  sem protetor e sem o anel da meta. Para conferir:

  ```sql
  -- Deve listar as 2 colunas e a tabela.
  SELECT column_name FROM information_schema.columns
  WHERE table_schema = 'public' AND column_name IN ('idf_protetores', 'idad_segundos_assistidos');
  SELECT table_name FROM information_schema.tables
  WHERE table_schema = 'public' AND table_name = 'id_fiel_protetor_dia';
  ```

- [ ] Rodar a seção **86** (registro das seções rodadas) em **homolog e
  em produção**, depois da 85. Idempotente. Da 86 em diante, cada seção
  grava o próprio número, e o Dashboard do admin avisa a equipe interna
  quando falta alguma. Para conferir: `SELECT MAX(isv_secao) FROM id_schema_versao;`
  → 86.

- [ ] Rodar a seção **87** (relógio do fiel: progresso e meta sem
  trapaça) em **homolog e em produção**, depois da 86. Idempotente. Sem
  ela o progresso segue com o limite antigo (por aula e por sessão). Para
  conferir: `SELECT MAX(isv_secao) FROM id_schema_versao;` → 87.
- [ ] Rodar a seção **88** (12 índices das consultas mais repetidas) em
  **homolog e em produção**, depois da 87. Idempotente, leva segundos.
  Para conferir: `SELECT MAX(isv_secao) FROM id_schema_versao;` → 88.
- [ ] Rodar a seção **89** (cancelar em vez de apagar e Saúde do sistema) em
  **homolog e em produção**, depois da 88. Idempotente. Sem ela, o
  Financeiro e as ofertas manuais continuam apagando como antes e o cartão
  de Saúde não aparece. Para conferir: `SELECT MAX(isv_secao) FROM id_schema_versao;` → 89.
- [ ] Rodar a seção **90** (RLS ligado em todas as tabelas) em **homolog e
  em produção**, depois da 89. Idempotente. Na homologação as tabelas já
  estavam com RLS (conferido em 28/10/2026); lá ela só grava o número 90.
  Para conferir: `SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND NOT rowsecurity;` → nenhuma linha.
- [ ] **Render:** `CONTATO_FORMSPREE_URL` com o formulário da igreja (sem
  ela o formulário de contato agora some, em vez de ir para uma conta de
  teste) e `EMAIL_SENDER_NAME` = "Missão Manancial" (o padrão antigo
  aparecia como "SiteManancial" nos e-mails).

- [x] (Pacote B) Arte 1200×630 da prévia (`assets/img/compartilhar.jpg`) e
  `og:image` absoluto na home (`index.php`, a partir de `SITE_URL_PUBLICA`).
  Com o domínio próprio, basta trocar a variável.
- **Banco de produção do zero:** o `bancosupabase.md` inteiro (seções 1 a
  90 hoje) monta tudo de uma vez. A **seção 1 apaga o schema `public`**:
  rodar o arquivo inteiro só num banco vazio; num banco com dados, rodar
  apenas as seções que faltam. Toda seção nova entra aqui como "falta
  rodar em homolog e produção".
- [ ] **Asaas de produção no nome de quem?** Hoje é o CPF de um de nós.
  Com volume, avaliar CNPJ (MEI tem teto de faturamento) e nota fiscal com
  um contador.
- [ ] **Backup do Supabase:** conferir o que o plano guarda e testar uma
  restauração de verdade. Com assinantes, considerar o Supabase Pro
  (US$ 25/mês: backup diário, sem pausa).
- [ ] **Monitor de "site fora do ar"** (UptimeRobot em `/saude.php?banco=1`, palavra `ok`; ver qualidade_e_ci.md 3.5).
- [ ] Trocar as senhas e decidir se as 4 contas e os 4 cursos de exemplo
  ficam.

---

## 9. Funcionalidades ainda não começadas

Detalhes em [progresso_pedidos_cliente.md](progresso_pedidos_cliente.md).

| ID | Tarefa | Bloqueio |
|---|---|---|
| T34 | Biblioteca de livros digitais | Quais livros e quem tem os direitos |
| T35 | Legenda automática dos vídeos | Aprovar o custo do serviço de transcrição |
| T36 | Bíblia integrada com versões | Quais versões (licenças) |
| T37 | Planos de leitura bíblica | Depende da T36 |
| T38 | Devocional diário no WhatsApp | Aprovar o custo por mensagem e a Meta |
| T39 | Moeda interna | Depende da T15 e das regras do cliente |

Retirado (22/10/2026): T32 (missões), entregue no sandbox (ver o bloco
dela em `progresso_pedidos_cliente.md`).
