# MananciAPP

Plataforma de ensino da Missão Evangélica Manancial da Esperança: cursos em
vídeo no estilo streaming, comunidade em volta de cada aula, gamificação,
transmissão ao vivo, assinaturas e painel administrativo. PHP puro (sem
framework), instalável como app (PWA).

**Homologação:** https://mananciapp-render-homolog.onrender.com

| Documento | Para quê |
|---|---|
| [DEPLOY_RENDER.md](DEPLOY_RENDER.md) | Como está publicado: Render, banco, R2, variáveis, deploy |
| [database/bancosupabase.md](database/bancosupabase.md) | Schema do banco (Postgres no Supabase), em seções numeradas |
| [docs/README.md](docs/README.md) | Índice da documentação (cliente e interna) |
| [docs/interno/pontos_gerais.md](docs/interno/pontos_gerais.md) | **O que falta**: configuração, segurança, desempenho, qualidade |
| [docs/interno/progresso_pedidos_cliente.md](docs/interno/progresso_pedidos_cliente.md) | Quadro das tarefas do cliente (T01…T39) |
| [AGENTS.md](AGENTS.md) | Instruções para assistentes de IA |

## Como rodar o projeto

1. Copie a pasta `MananciAPP` para o `htdocs` do XAMPP (ou use o servidor
   embutido, abaixo).
2. Crie um projeto no [Supabase](https://supabase.com) e rode o
   `database/bancosupabase.md` inteiro no **SQL Editor** (ele apaga e
   recria o schema `public`; num banco que já está em uso, rode só as
   seções novas).
3. Pegue a conexão em **Connect → Session pooler** (não a "Direct
   connection", que é IPv6).
4. Copie `.env.example` para `.env` e preencha `DB_HOST`, `DB_PORT`
   (5432), `DB_USER` (`postgres.<ref-do-projeto>`), `DB_PASS` (a senha do
   banco, não a chave da API) e `DB_NAME` (`postgres`). Para enviar e
   assistir vídeos, preencha também as 4 variáveis `R2_*` (peça a quem
   administra a conta da Cloudflare).
5. Acesse `http://localhost/MananciAPP/index.html`.
6. O script cria 4 contas **sem senha** (admin `admin@igreja.com`; fiéis
   `joao@email.com`, `maria@email.com`, `carlos@email.com`) e 4 cursos.
   Defina a senha do admin com o comando da seção dos usuários iniciais do
   `bancosupabase.md`. Os fiéis usam "Esqueci minha senha", ou o admin manda
   o link pelo botão da chave em Gerenciar Usuários. Senha nunca vai escrita
   em documento do repositório.

Dicas:

- **XAMPP:** no `php.ini`, comente `extension=mysqli` e descomente
  `extension=pdo_pgsql`. O app fala com o Postgres por um shim que imita as
  funções `mysqli_*`; com a extensão real ligada, a conexão falha.
- **Sem o Apache:** dentro de `MananciAPP/`, rode
  `C:\xampp\php\php.exe -S localhost:8000 -t .` e acesse
  `http://localhost:8000/login.php`. Nesse modo o `.htaccess` não é lido
  (sem CSP).
- **O `.env` local costuma apontar para o mesmo banco do Render:** o que
  for criado no local aparece lá. Para testes, use um curso em "Rascunho" e
  exclua depois.
- **Erro de CORS ao enviar vídeo:** o endereço local, com a porta, precisa
  estar no CORS do bucket ([videos_cloudflare_r2.md](docs/interno/videos_cloudflare_r2.md), seção 3).
- **Erro de certificado no curl** (errno 60): ver seção 6 do
  [DEPLOY_RENDER.md](DEPLOY_RENDER.md).
- Erros do PHP: `C:\xampp\apache\logs\error.log` ou o próprio terminal.

## Como o sistema funciona

1. O visitante cria uma conta; ela fica `pendente`.
2. O admin aprova ou recusa (o fiel recebe e-mail).
3. No primeiro acesso, o fiel aceita o **Termo de Uso** (de novo sempre
   que a versão mudar).
4. O fiel assiste, participa e acompanha o próprio crescimento; o
   progresso de cada aula é salvo sozinho.
5. O admin cuida de usuários, conteúdo, lives, moderação e assinaturas.

## Funcionalidades

### Área pública

- Página inicial, login, cadastro, "Esqueci minha senha".
- **Ao vivo** (`ao-vivo.php`): live do YouTube com chat próprio.
- Sobre, contato e "Como instalar" (tutorial do app por aparelho).
- **Cursos** (`cursos.php`): catálogo com os cursos publicados.
- **Política de Privacidade** (`privacidade.php`, LGPD) e Termo de Uso para
  leitura antes do cadastro.

### Área do fiel (`php/fiel/`)

- **Home** (`dashboard.php`): banner, "Continuar assistindo", filas por
  tema, "Em alta", "Recomendados para você" (pelas preferências),
  devocional do dia e testemunhos em destaque.
- **Cursos, categorias e curso** (`cursos.php`, `categorias.php`,
  `curso.php`), busca, favoritos e histórico.
- **Player** (`assistir_video.php`): volta de onde parou, velocidade até
  2x salva, próxima aula automática, Chromecast/AirPlay, picture-in-picture,
  toque duplo ±10 s, controles na tela de bloqueio, visualizações e
  selo "Em alta". Abaixo do vídeo:
  - **Comunidade:** votos "Edificante"/"Fora de contexto", abas, respostas
    com selo "Líder", nota do pastor fixada, compartilhar, citar versículo
    ou minuto da aula;
  - **Resumos** publicados pelos fiéis, **Orações** com "Amém" e "Oração
    atendida", **Minhas notas** ligadas ao minuto do vídeo.
- **Perfil** (`perfil.php`): dados completos (WhatsApp, nascimento, foto,
  família, cidade, membro desde, função na igreja), "Perfil X% completo",
  selos, preferências de conteúdo e lembrete diário.
- **Crescimento:** ofensiva com protetor (a cada 7 dias seguidos ganha 1,
  até 2, que cobrem um dia sem entrar) e ranking (`ofensiva.php`), meta
  diária em minutos com anel na Home, medalhas,
  desafios (`desafios.php`), certificados (`Certificados/`) e
  `meu_progresso.php`.
- **Testemunhos** (`testemunhos.php`) e **Devocionais** (`devocional.php`):
  o fiel envia, a equipe aprova; os devocionais com nota dos líderes entram
  na agenda.
- **Assinatura** (`assinar.php`), notificações, configurações (inclusive
  troca de senha e push) e Ao vivo dentro do layout logado.
- **Privacidade e dados** (Configurações): baixar meus dados e excluir a
  conta. Cadastro de menor de 18 com nome e autorização do responsável.
- **No celular:** barra de navegação inferior, controles na tela de
  bloqueio e no fone, toque duplo para avançar/voltar 10 s.

### Área administrativa (`php/admin/`)

- Aprovação de cadastros, usuários (com "Enviar link de nova senha"),
  permissões de admin e funções na igreja.
- Cursos (capa, categoria, professor, nível, destaque, gratuito) e aulas
  (upload direto para o R2).
- Transmissão ao vivo e moderação do chat.
- Testemunhos, devocionais e desafios.
- Assinaturas (planos, assinantes, cortesia, "Quem pode assistir").
- Mensagens e notificações para todos, para alunos de um curso, para
  quem está sumido, para uma filial ou para pessoas escolhidas;
  engajamento e "Ver Progresso" de cada fiel.
- **Registro de atividade** (quem fez o quê no painel) e **Minha
  segurança** (verificação em duas etapas do admin).
- **Grade do curso** (ordem das aulas arrastando, publicar/esconder cada aula,
  envio de vários vídeos de uma vez em rascunho) e **Métricas** (caminho do
  fiel, acessos por dia, conclusão por aula e onde o fiel para no vídeo).

Detalhes de cada entrega: [progresso_pedidos_cliente.md](docs/interno/progresso_pedidos_cliente.md).
Live: [transmissao_ao_vivo.md](docs/interno/transmissao_ao_vivo.md).
Assinaturas: [assinaturas_asaas.md](docs/interno/assinaturas/assinaturas_asaas.md).
Senhas: [reset_de_senha.md](docs/interno/reset_de_senha.md).
LGPD: [lgpd.md](docs/interno/lgpd.md). Capacidade (300 pessoas): [capacidade.md](docs/interno/capacidade.md).
CI e testes: [qualidade_e_ci.md](docs/interno/qualidade_e_ci.md).

## Regras do sistema

- Nome sem números; e-mail válido e único (sem diferenciar maiúsculas).
- Senha de 8 a 72 caracteres, com maiúscula e caractere especial, que não
  esteja em vazamentos conhecidos.
- Cadastro novo entra como `pendente`; o login bloqueia contas pendentes,
  negadas ou desativadas.
- Sessão expira após 1 hora sem uso.
- Termo de Uso obrigatório na versão atual (`TERMOS_VERSAO_ATUAL` em
  `php/conexao.php`; mudar a constante pede novo aceite de todos). O texto
  é um ponto de partida e **não substitui revisão jurídica**.
- Curso só aparece para o fiel quando está `PUBLICADO`.
- Uma live cadastrada por vez; comentar exige conta aprovada e não estar
  bloqueado.

## Estrutura principal

| Caminho | O quê |
|---|---|
| `index.html` | Página inicial pública (HTML estático) |
| `login.php`, `esqueci_senha.php`, `redefinir_senha.php` | Entrada e senhas |
| `ao-vivo.php`, `ajax_transmissao_*.php`, `ajax_presenca.php` | Live pública e endpoints públicos |
| `lembrete_diario.php` | Chamado pelo cron-job.org para o lembrete diário |
| `webhook_asaas.php` | Avisos de pagamento do Asaas |
| `saude.php` | Health check do Render |
| `php/conexao.php` | Conexão, shim `mysqli_*`, sessão, segurança e quase todas as regras do app |
| `php/sessao_banco.php` | Sessões no Postgres (`SESSOES_NO_BANCO=1`) |
| `php/includes/` | `pwa_head.php` (PWA + OneSignal), widget e script da live |
| `php/fiel/`, `php/fiel/includes/` | Área do fiel; `navbar_fiel.php`, cards e `cursos_helpers.php` (matrícula, progresso, recomendações) |
| `php/admin/` | Painel; `admin_layout.php` é o layout comum |
| `css/`, `js/`, `assets/` | Estilos, scripts (tema, carrosséis, PWA, indicador da live) e imagens |
| `manifest.json`, `sw.js` | PWA (o `sw.js` também é o service worker da OneSignal) |
| `database/` | `bancosupabase.md` (o que o app usa) e as versões MySQL para a faculdade |
| `docs/`, `agents/` | Documentação e instruções para agentes |
| `Dockerfile`, `docker/`, `render.yaml`, `.htaccess` | Publicação no Render |

## Banco de dados

O app usa só o **Postgres do Supabase** (`database/bancosupabase.md`). Como
o shim funciona e as particularidades do schema: seção 3 do
[DEPLOY_RENDER.md](DEPLOY_RENDER.md).

Para o trabalho da faculdade, o mesmo banco existe em **MySQL 8**, em duas
versões com as mesmas tabelas e colunas:

| Arquivo | O que tem |
|---|---|
| `database/Banco Igreja.sql` (simples, banco `igreja_cursos`) | Chave primária, chaves estrangeiras básicas e valores padrão |
| `database/Banco Igreja Robusto.sql` (robusto, banco `igreja_cursos_v2`) | Tudo o que o banco real garante: `ON UPDATE`/`ON DELETE`, `UNIQUE`, `CHECK`, índices |

Gerados em 15/10/2026 a partir do Postgres e testados no MariaDB 10.4 do
XAMPP. 3 índices únicos "parciais" viraram índices comuns e a ligação com
`auth.users` do Supabase não existe. **Se o banco do app mudar, os dois
precisam ser gerados de novo.**

## PWA (aplicativo instalável)

O site instala como app (Android, iOS e desktop) via `manifest.json` e
`sw.js`. O service worker só guarda arquivos estáticos; páginas PHP e dados
sempre vêm da rede. O botão "Instalar aplicativo" aparece na página
inicial, no login e no cadastro; se o navegador não liberar a instalação,
leva para `como-instalar.php`.

## E-mail transacional (Brevo)

Os e-mails (cadastro aprovado/recusado, link de nova senha, avisos) saem
pela API da [Brevo](https://www.brevo.com/), em `enviar_email_sistema()`
(`php/conexao.php`).

| Variável | Valor |
|---|---|
| `EMAIL_API_KEY` (ou `BREVO_API_KEY`) | Chave de API da Brevo |
| `EMAIL_SENDER_EMAIL` (ou `BREVO_SENDER_EMAIL`) | Remetente verificado (`sunrosefatec@gmail.com`) |
| `EMAIL_SENDER_NAME` | Nome do remetente |

Se o envio falhar, o admin vê "o e-mail não pôde ser enviado
automaticamente" e o motivo vai para o log (`Brevo: falha ao enviar
e-mail…`). Um `http_code=401` normalmente é bloqueio de IP, não chave
inválida.

### Bloqueio de IP na Brevo — decisão atual

Em **Brevo → Configurações → Segurança → IPs autorizados**, o bloqueio de
IPs não autorizados para **Chaves API** está **desativado** desde
04/10/2026: com ele ativo, as chamadas vindas do Render eram recusadas.

- **Ganho:** o envio funciona de qualquer servidor, mesmo se o Render
  trocar o IP de saída.
- **Custo:** quem obtiver a chave consegue enviar e-mails em nome do
  remetente e gastar a cota.
- **Mitigações:** a chave fica só no servidor e é variável secreta no
  Render.

O que fazer antes da produção está em
[pontos_gerais.md](docs/interno/pontos_gerais.md), seção 2.

## Notificações Push (OneSignal)

Push no navegador pela [OneSignal](https://onesignal.com/).

**1. Inicialização:** `php/includes/pwa_head.php` (em toda página PHP) e
`index.html` (página estática). O `appId` é público (só inscreve o
aparelho; enviar exige a REST API Key, que fica só no servidor).

**2. Um service worker só:** o `sw.js` do PWA importa o da OneSignal
(`importScripts('https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js')`),
e o `OneSignal.init()` aponta para ele. Dois service workers no mesmo
escopo brigariam.

**Configuração no painel** (Settings → Push & In-App → Web Settings →
Advanced Push Settings → Service Workers): ligar "Customize service worker
paths and filenames" e preencher:

| Campo | Valor |
| --- | --- |
| Path to service worker files | `/` |
| Main service worker filename | `sw.js` |
| Updater service worker filename | `sw.js` |
| Service worker registration scope | `/` |

> O `/` cinza é só placeholder: é preciso digitar. Sem isso o navegador
> busca `/OneSignalSDKWorker.js` (404) e ninguém é inscrito, mesmo com o
> convite aparecendo.

*Site URL* no painel: a URL do Render (a inscrição fica presa a essa
origem; em `localhost` pode não registrar). *Identity Verification* deve
ficar desligado (só existe para os SDKs de celular).

**3. Push para um fiel:** depois do login, `pwa_head.php` chama
`OneSignal.login(<external_id>)`; o valor vem de `onesignal_external_id()`
(`fiel-<id>`, com sufixo HMAC quando `ONESIGNAL_EXTERNAL_ID_SEGREDO` está
definido, para ninguém se passar por outro fiel). Ao sair, `?onesignal_logout=1`
faz `pwa_head.php` chamar `OneSignal.logout()`. O backend manda com
`onesignal_enviar_push()` (exige `ONESIGNAL_REST_API_KEY`).

**4. Fila de push:** toda notificação nasce com `IDN_PUSH_ENVIADO = 0`.
Depois que o `OneSignal.login()` termina no navegador, `pwa_head.php` chama
`php/fiel/ajax_onesignal_sync.php`, que manda as pendentes daquele fiel
(`notificacoes_push_enviar_pendentes()`). Assim o push não se perde quando
o aparelho ainda não estava vinculado.

**Reativar depois de recusar:** o toggle "Receber notificações push" em
Configurações pede a permissão de novo e mostra o estado real do navegador.
Se a pessoa clicou em "Bloquear" no navegador, só ela consegue liberar,
nas permissões do site.

**Visitante x fiel:** quem aceita o convite sem login (para avisos de
live) não ganha tag; quem loga ganha a tag `papel = fiel`. Para mandar só
aos fiéis pelo painel, criar um segmento com essa tag.
