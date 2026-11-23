# Qualidade e integração contínua

Como o código é conferido sozinho, como o erro chega até a equipe antes do
fiel reclamar e como separar a homologação da produção. Feito em 27/10/2026.

**Resumo:** o código já está pronto para tudo abaixo. O que falta são
cadastros em painéis (GitHub, Sentry, UptimeRobot, Render), na ordem da
seção 3, mais ou menos 1 h no total, sem custo.

## Por que GitHub Actions (e não Bitbucket)

O repositório já está no GitHub (`The-SunRose/MananciAPP---Render---Homolog`)
e o Render já publica a partir dele. O **GitHub Actions** faz o mesmo que o
Bitbucket Pipelines ou o GitLab CI, é gratuito para este volume (2.000
minutos/mês em repositório privado; cada rodada leva poucos minutos) e não
exige migrar nada.

---

## 1. O que roda sozinho

Arquivo: [`.github/workflows/ci.yml`](../../../.github/workflows/ci.yml)
(na raiz do repositório). Cada linha é um "job" na aba **Actions** do
GitHub.

| Job | Quando | O quê |
|---|---|---|
| **Sintaxe e testes** | Todo push na `main` e todo pull request | Sintaxe de todos os PHP no **PHP 8.2** (o mesmo do Dockerfile), `tests/testes_funcoes.php` (255 testes), sintaxe dos JS e do `manifest.json` |
| **Banco do zero e testes com banco** | Todo push e pull request | Sobe um Postgres vazio, roda o `bancosupabase.md` **inteiro** (prova que a produção monta do zero) e `tests/testes_banco.php`: exclusão e exportação da LGPD (com e sem pagamento), registro de atividade, 2FA no login (código certo, repetido, reserva), senha, ranking, pessoas online, limpeza diária |
| **Navegador (homologação)** | Toda segunda, 9h, e no botão "Run workflow" | Playwright abre o site da homologação **como computador e como celular**: páginas públicas sem erro de PHP, JavaScript ou CSP; cadastro de menor pedindo o responsável; e, com as contas de teste, o fluxo do fiel (barra inferior, cursos, busca, perfil, configurações) e as telas principais do painel |
| **Conferir o site no ar** | Toda segunda, 9h, e no botão | `tests/checar_site.php`: cabeçalhos de segurança, arquivos internos fechados, páginas que exigem login (homologação, e produção se configurada) |

Mais:

- [`.github/dependabot.yml`](../../../.github/dependabot.yml): uma vez por
  mês abre um pull request quando sai versão nova das ações do CI ou da
  imagem `php:8.2.x-apache` do Dockerfile (correção de segurança do PHP).
- **Alerta de erros (Sentry)** já no código (`site_alerta_erro()` em
  `php/conexao.php`): desligado até a variável `SENTRY_DSN` existir.
- **Teste de carga** (300 pessoas): [tests/carga/](../../tests/carga/README.md),
  roda à mão. Ver [capacidade.md](capacidade.md).

**Primeira rodada (27/10/2026):** o `bancosupabase.md` montou o banco
inteiro do zero num Postgres limpo, e 43 de 45 testes com banco passaram.
As 2 falhas eram do próprio teste (o `??` do PHP trocava o `NULL` esperado
por um valor padrão), corrigidas. O job do banco deixou de ser "só aviso":
quebrar o banco do zero marca o commit de vermelho. O job do navegador só
roda na segunda-feira ou no "Run workflow" (passo 3.3).

### Cuidados contra resultado errado

Contra **falso erro** (vermelho sem bug):

- A homologação é "acordada" (`/saude.php`) antes do navegador e do
  `checar_site.php`: o Render gratuito leva mais de 30 s no primeiro acesso.
- Push novo cancela a rodada anterior ainda em andamento.
- Mensagens de serviços de fora (OneSignal, YouTube, Google) no console não
  contam; CSP barrando algo do próprio site conta.
- A comemoração de medalha e o aviso da ofensiva são fechados antes dos
  cliques; conta de teste presa no Termo de Uso ou com senha errada falha
  com mensagem dizendo isso (é configuração, não bug).
- Endereço com ou sem barra no fim funciona.
- O Dependabot só propõe correções do PHP 8.2 (8.2.35 → 8.2.36), nunca 8.3
  ou 8.4.
- Cada teste do navegador tenta de novo uma vez antes de acusar (rede).

**O que o CI não pega** (falso tranquilo):

- JavaScript escrito dentro das páginas PHP só é conferido pelo job do
  navegador (segunda-feira), não a cada push.
- O navegador testa a **homologação como ela está**: rodar "Run workflow"
  logo depois de um push pode testar a versão anterior (o Render leva
  alguns minutos para publicar).
- Na homologação o PHP não mostra erros na tela: o teste pega erro pelo
  código 500 da página, não pelo texto.
- Telas sem teste: Financeiro, Assinaturas, Missões, Destaques, Desafios,
  Devocionais, Transmissão (admin), aula com vídeo tocando, contribuição Pix.
  Ver seção 4.
- O GitHub desliga os agendados (segunda-feira) depois de 60 dias sem
  nenhum commit; um push reativa.

---

## 2. Como ler o resultado

- GitHub → aba **Actions** → clique na rodada → cada job com ✓ ou ✗.
  Clique no job e no passo vermelho para ver a mensagem.
- Falhou "Sintaxe e testes": a mensagem diz o arquivo e a linha, ou o
  teste (`FALHA nome do teste / esperado / obtido`).
- Falhou "Navegador": no fim da rodada aparece o artefato
  **relatorio-playwright** (baixar, abrir `index.html`): print da tela e
  passo a passo do que o navegador fez.
- O GitHub manda e-mail para quem fez o push quando algo fica vermelho.

**Atenção:** o CI ainda **não impede o deploy**. O Render publica todo push
na `main`, mesmo com ✗. O passo 3.6 resolve isso.

---

## 3. Passo a passo (do seu lado)

### 3.1. Conferir a primeira rodada (5 min)

1. Depois do `git push`, abra o GitHub → **Actions**.
2. Deve aparecer a rodada "CI" do commit. Se a aba pedir para habilitar os
   workflows, clique em **I understand my workflows, go ahead and enable
   them**.
3. "Sintaxe e testes" tem que ficar ✓. Se não, me mande o print do passo
   vermelho.

### 3.2. Job do banco (feito em 27/10/2026)

Já está valendo (ver seção 1). Se um dia ficar ✗: o passo **"Montar o
banco"** vermelho diz qual seção do `bancosupabase.md` não monta num
Postgres limpo (é o que quebraria na criação da produção); o passo
**"Testes com banco"** vermelho mostra o teste, o esperado e o obtido.

### 3.3. Contas de teste para o navegador (10 min)

Na **homologação** (nunca na produção):

1. Crie um fiel de teste pelo cadastro normal (ex.: `teste.fiel@...`) e
   aprove no painel.
2. Crie (ou promova) um admin de teste **sem** verificação em duas etapas,
   num tipo de admin com acesso total.
3. GitHub → Settings → **Secrets and variables → Actions → Secrets** →
   *New repository secret*, um por um:
   - `E2E_FIEL_EMAIL` e `E2E_FIEL_SENHA`
   - `E2E_ADMIN_EMAIL` e `E2E_ADMIN_SENHA`
4. Aba **Actions** → "CI" → **Run workflow** → `main`. O job "Navegador"
   agora roda também os testes com login.

As mesmas contas servem para os testes de controle de acesso da
[seguranca.md](seguranca.md) (seção 4.3).

### 3.4. Alerta de erros: Sentry (15 min, grátis)

1. Crie a conta em **sentry.io** (plano Developer, grátis: 5 mil erros/mês).
2. *Create Project* → plataforma **PHP** → nome `mananciapp-homolog`.
   Repita para `mananciapp-producao` (um projeto por ambiente, para não
   misturar).
3. Em cada projeto: Settings → **Client Keys (DSN)** → copie o DSN
   (`https://<chave>@o<número>.ingest.<região>.sentry.io/<número>`).
4. Render → serviço da homologação → **Environment** → `SENTRY_DSN` = o DSN
   da homologação. Mesma coisa na produção com o dela. Salvar (o Render
   reinicia sozinho).
5. Sentry → Settings → **Alerts**: confirme que há uma regra "a new issue is
   created → e-mail" (vem pronta) e quem recebe (pontos_gerais.md, 5.4:
   quem reage aos alertas).
6. Como saber que funciona: o primeiro erro de banco ou erro fatal aparece
   em **Issues** com a página, o arquivo e a linha. Não vai e-mail, senha,
   token, cookie nem o que foi digitado (só o caminho da página).

O que é enviado: erro fatal do PHP (inclusive exceção não tratada) e todo
erro de banco tratado (`site_erro_banco()`), no máximo 3 por requisição.
Erros de JavaScript no navegador também (Pacote B): `js/erros.js` só é
carregado com `SENTRY_DSN` configurado, manda no máximo 3 erros por página
para `ajax_erro_js.php` (mesma origem, até 10 por IP a cada 10 min), e o
servidor repassa ao Sentry como `ErroJavaScript`. Não usa o SDK do Sentry
nem muda a CSP.

### 3.5. Monitor de "fora do ar": UptimeRobot (5 min, grátis)

O Sentry (3.4) avisa erros dentro do app; o UptimeRobot avisa quando o
site inteiro não responde. Desde o Pacote F, também vão ao Sentry: banco
fora do ar, webhook do Asaas falhando, e-mail que a Brevo recusou, push
que a OneSignal recusou e o lembrete diário com falha (o cron-job.org vê
erro 500).

1. Crie a conta em **uptimerobot.com**.
2. *Add New Monitor* → tipo **Keyword** → URL
   `https://<site>/saude.php?banco=1` → palavra-chave `ok` → intervalo
   **5 min**. Com `?banco=1` ele também confere o banco (responde 503
   "banco fora" se o Supabase não responder); o `saude.php` sem nada, usado
   pelo Render, continua sem olhar o banco de propósito.
3. *Alert contacts*: e-mail de quem cuida (e o app deles no celular, se
   quiser push).
4. Um monitor para a homologação e outro para a produção.
5. Opcional: *Status page* pública (ex.: para mostrar às igrejas clientes).

Com o Render gratuito, o monitor também ajuda a não hibernar (como o
cron-job.org do lembrete).

### 3.6. Homologação separada da produção (40 min)

Hoje todo push na `main` vai direto para o ar. Com dinheiro de verdade e
300 pessoas, o certo é: **`main` → homologação** (testa) → **`producao` →
produção** (só o que passou).

1. **Branch `producao`:** GitHub → aba *Code* → seletor de branch → digite
   `producao` → *Create branch: producao from main*.
2. **Render, serviço de produção:** *New → Web Service* → mesmo repositório,
   branch **`producao`**, runtime Docker, região **Virginia (US East)**
   (mesma do banco, ver [capacidade.md](capacidade.md)), plano pago.
   Copie todas as variáveis do `.env.example` com os valores de
   **produção** (banco de produção, `ASAAS_API_URL` de produção,
   `SITE_URL_PUBLICA` do domínio, `SENTRY_DSN` da produção…). Health
   check `/saude.php`.
3. **Render, serviço atual (homologação):** Settings → confirme que a
   branch é **`main`** e que as chaves são de sandbox.
4. **Proteger a produção:** GitHub → Settings → **Branches** → *Add branch
   ruleset* (ou *branch protection rule*) para `producao`:
   - *Require a pull request before merging*;
   - *Require status checks to pass* → marque **Sintaxe e testes** e
     **Banco do zero e testes com banco** (aparecem depois da primeira
     rodada; o do banco só depois do passo 3.2);
   - *Block force pushes*.
5. **Fluxo do dia a dia:**
   1. Commit e push na `main` → a homologação atualiza.
   2. Testar na homologação (roteiro de pontos_gerais.md).
   3. GitHub → *Pull requests* → *New* → base **`producao`** ← compare
      **`main`** → o CI roda → *Merge*. A produção atualiza.
   4. Deu problema na produção: Render → serviço de produção → *Events* →
      **Rollback** para o deploy anterior (1 clique).
6. **Serviços que apontam para um endereço:** cron-job.org (um job por
   ambiente, cada um com a sua `LEMBRETE_CRON_CHAVE`), webhook do Asaas
   (produção no Asaas de produção, homologação no sandbox), OneSignal (um
   app por ambiente ou o *Site URL* da produção), CORS do R2 (os dois
   endereços).
7. Na `vars` do GitHub (Settings → Secrets and variables → Actions →
   **Variables**): `SITE_PRODUCAO_URL` = endereço da produção, para o
   `checar_site.php` semanal conferir os dois.

### 3.7. Dependabot (contínuo)

Quando chegar um pull request do Dependabot ("Bump php from 8.2.35 to
8.2.36…"): o CI roda nele; ✓ → *Merge* na `main`, testar na homologação e
seguir o fluxo do 3.6. Não juntar vários de uma vez.

---

## 4. Do nosso lado, depois

- [x] (Pacote B) Erros de JavaScript no Sentry, pelo próprio servidor
  (`ajax_erro_js.php`), sem CDN nem mudança na CSP.
- [ ] Mais testes com banco: assinaturas (webhook do Asaas com eventos
  falsos), medalhas e desafios.
- [ ] Mais testes de navegador: assistir uma aula até o fim e receber o
  certificado; contribuição Pix no sandbox; chat da live.
- [ ] **Registro de migrações:** tabela com o número da última seção do
  `bancosupabase.md` rodada, conferida no `saude.php` (precisa de uma seção
  nova do banco).
- [ ] **Dividir `php/conexao.php`** (~10,9 mil linhas) em arquivos por
  assunto, carregados por ele mesmo, com o CI garantindo que nada quebrou.
