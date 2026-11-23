# Plano de melhorias (produto, escala, segurança e venda)

Análise do projeto inteiro feita em 27/10/2026 com um objetivo: transformar
o MananciAPP num **produto vendável para outras igrejas**, que aguente
**~300 pessoas ao mesmo tempo**, seja gostoso de usar no celular e no
computador e faça o fiel voltar todo dia.

Este arquivo é o **roteiro do que vem pela frente**. O que já está mapeado
continua nos lugares de sempre e não se repete aqui:
[pontos_gerais.md](pontos_gerais.md) (configuração, produção, decisões) e
[seguranca.md](seguranca.md) (achados S01…, rotina de testes). Quando um
item daqui for feito, vira explicação no documento certo e sai daqui.

**Como ler:** cada item tem prioridade (🔴 antes do lançamento de 01/12,
🟠 até o fim de janeiro, 🟡 depois) e, quando dá, onde está no código e a
inspiração (Netflix, Duolingo, YouVersion…).

---

## Andamento

**27/10/2026 (sem commit ainda; testar no Render depois de rodar as seções
82 a 84):**

- ✅ **Capacidade, parte de código** (1.3, parte de 1.4 e 1.5): cache
  compartilhado do chat, status da live, presença e ranking; visitante sem
  sessão no polling; cards sem baixar vídeo; listas do painel paginadas;
  `APACHE_MAX_WORKERS`; limpeza diária; teste de carga em `tests/carga/`.
  O que é pago e a ordem: [capacidade.md](capacidade.md).
- ✅ **LGPD** (2.1): [lgpd.md](lgpd.md).
- ✅ **Admin** (2.2 e 4.1): registro de atividade, 2FA, aprovar em lote,
  busca em Ver Progresso, segmentos em Enviar Mensagem, Gerenciar Vídeos
  paginado, taxa de conclusão corrigida.
- ✅ **Celular** (3.1 e 3.2): barra inferior, barrinha de carregamento,
  tela de bloqueio/fone (MediaSession), toque duplo ±10 s, app instalado
  liberado para deitar, atalhos no ícone.
- ✅ **CI** (5): GitHub Actions + Dependabot ([qualidade_e_ci.md](qualidade_e_ci.md)).
- ✅ **Bugs** (7): catálogo público real, contato falso no rodapé, print do
  iPhone de outro projeto (passos 2 e 3), metatags/Open Graph, `robots.txt`,
  `noindex` na homologação, link da Política de Privacidade.
- ✅ **Pacote A** (27/10/2026, a testar): grade do curso (ordem arrastando,
  publicar/esconder, envio em lote em rascunho), Métricas, aviso de login em
  aparelho novo, CAPTCHA pronto para ligar, novo aceite do termo com a
  Política de Privacidade. Corrigido junto: o aviso de excluir curso dizia
  que os vídeos não seriam apagados.
- ✅ **Pacote B** (27/10/2026, a testar; rodar a seção 85): protetor de
  ofensiva e meta diária, números reais na home pública, skeletons, cache
  longo dos CSS/JS com versão, picture-in-picture, erros de JavaScript no
  Sentry, imagem da prévia no WhatsApp e rótulos de acessibilidade. Falta
  o Lighthouse (feito por pessoa, no Chrome).
- ✅ **Pacote C** (27/10/2026, a testar; rodar a seção 86): chat da live mais
  leve (versão, intervalo adaptativo, sessão a cada 30 s), registro das
  seções do banco, imagens da home em WebP e títulos das abas.
- 📋 **Revisão geral** (27/10/2026): segurança, desempenho e fluxos na
  [seção 9](#9-revisão-geral-de-27102026-segurança-desempenho-e-fluxos)
  (Pacotes D e E).
- ✅ **Pacote D** (27/10/2026, a testar; rodar a seção 87): bugs e segurança
  da revisão geral (seção 9.1).
- ✅ **Pacote E** (27/10/2026, a testar; rodar a seção 88): desempenho da
  revisão geral (seção 9.2): sessão, player, OneSignal, Home e chat em
  cache, índices, Apache e teste de carga do culto.
- 📋 **Revisão de 28/10/2026** (experiência, painel e robustez): seção 10
  (Pacotes F, G e H).
- ✅ **Pacote F** (28/10/2026, a testar): robustez da seção 10.1.
- ✅ **Pacote H** (28/10/2026, a testar): primeira impressão do fiel (10.3) e
  a tela Admin → Identidade da igreja.
- ✅ **Pacote G, parte rápida** (28/10/2026, a testar; rodar a seção 89):
  confiança da igreja no painel (10.2, os ⭐ menos a ficha do membro e a
  tesouraria).
- ✅ **Fase 1 concluída** (28/10/2026, a testar): notificações do admin com
  link, recusado vira pedido de revisão, aviso de aula sem duração na grade,
  super admin desliga o 2FA de outro admin, Bootstrap e Font Awesome locais.
- 📋 **Engajamento** guardado para depois: [ideias_engajamento.md](ideias_engajamento.md).
- 📋 **Venda para outras igrejas**: conversa com o cliente em
  [pontos_para_alinhar.md](../para_o_cliente/pontos_para_alinhar.md#12-levar-a-plataforma-a-outras-igrejas),
  decisões da equipe em [pontos_gerais.md](pontos_gerais.md#55-vender-para-outras-igrejas).

---

## Resumo: as 10 coisas que mais pesam

| # | O quê | Por quê | Seção |
|---|---|---|---|
| 1 | 🔴 Plano pago do Render na **mesma região do banco** | No gratuito (0,1 CPU, 512 MB, Oregon ↔ banco na Virgínia) 300 pessoas derrubam o site | [1](#1-aguentar-300-pessoas-ao-mesmo-tempo) |
| 2 | 🔴 Chat da live com **resposta compartilhada em cache** | 300 pessoas na live = ~60 req/s só de chat, cada uma indo ao banco | [1.3](#13-live-o-ponto-mais-crítico) |
| 3 | 🔴 **Teste de carga** antes de 01/12 | Hoje ninguém sabe onde o site quebra | [1.5](#15-medir-antes-de-acreditar) |
| 4 | 🔴 **LGPD**: política de privacidade, excluir e exportar a conta | Obrigação legal; o app guarda CPF, nascimento e pagamentos | [2.1](#21-lgpd-obrigatório) |
| 5 | 🟠 **Log de auditoria** do admin + **2FA** para admin | Vários admins mexendo em dinheiro e conteúdo, sem rastro | [2.2](#22-segurança-além-do-que-já-existe) |
| 6 | 🟠 **Barra de navegação inferior** no celular | É o padrão de todo app que vicia (YouTube, Instagram, Netflix) | [3.1](#31-celular-primeiro) |
| 7 | 🟠 **Métricas de aprendizado** no admin | O admin não vê conclusão por aula nem onde o fiel desiste | [4.2](#42-métricas-que-o-pastor-quer-ver) |
| 8 | 🟠 **CI + monitor de erros + homologação separada** | Todo push na `main` vai direto ao ar sem teste automático | [5](#5-qualidade-de-engenharia) |
| 9 | 🟡 **Marca configurável** (nome, logo, cores, domínio) | "Manancial" está fixo em ~130 pontos do código: não dá para vender | [6](#6-virar-produto-para-outras-igrejas) |
| 10 | 🟡 Engajamento: **protetor de ofensiva, metas, retrospectiva, quiz** | É o que faz o fiel abrir o app todo dia | [3.3](#33-engajamento-o-que-faz-voltar-todo-dia) |

---

## 1. Aguentar 300 pessoas ao mesmo tempo

### 1.1. Onde o app está hoje

| Peça | Situação | Limite |
|---|---|---|
| Render | Plano **free**: 0,1 CPU, 512 MB, região provável Oregon (`render.yaml`) | Cada consulta custa ~55–60 ms (banco na Virgínia); a Home faz ~21 |
| Apache | prefork + mod_php, `MaxRequestWorkers 20` (`Dockerfile`) | 20 requisições por vez; cada processo com mod_php ocupa ~25–40 MB → 20 processos já passam de 512 MB |
| Banco | Supabase, Session pooler (5432), conexão persistente por processo | O *pool size* do pooler precisa ser ≥ processos × instâncias (**conferir** em Supabase → Database → Connection pooling) |
| Sessão | No banco (`SESSOES_NO_BANCO=1`): leitura + escrita a cada requisição | Permite várias instâncias (bom), mas soma 2 consultas por requisição |
| Vídeo | MP4 direto do R2 (URL assinada) | Escala bem (o tráfego não passa pelo servidor). Ponto fraco: sem qualidade adaptativa (ver 1.4) |

**Conta de padeiro para 300 pessoas logadas** (sem live):

| Fonte | Intervalo | Req/s |
|---|---|---|
| Heartbeat do player (`assistir_video.php:712`) | 30 s | ~8 |
| Presença (`js/presenca.js`) | 60 s | ~5 |
| Indicador "Ao vivo" (`js/live-indicator.js`) | 60 s | ~5 |
| Navegação (1 página a cada ~20 s, ~21 consultas cada) | — | ~15 páginas/s ≈ 300 consultas/s |

Com live: **+60 req/s** de chat (5 s, `php/includes/transmissao_script.php:115`).

### 1.2. Infraestrutura (🔴)

- [ ] **Render na Virgínia (US East)**, mesma região do Supabase (já
  previsto em pontos_gerais.md seção 3). Sozinho, divide o tempo das
  páginas por ~10.
- [ ] **Plano pago**: no mínimo *Standard* (1 CPU, 2 GB) para o
  lançamento; com o pico de culto/live, 2 instâncias (as sessões já estão
  no banco, então o Render pode balancear entre elas). O *Starter*
  (0,5 CPU, 512 MB) não aguenta 300.
- [ ] **Trocar prefork + mod_php por PHP-FPM** (imagem `php:8.2-fpm` +
  nginx, ou Apache `mpm_event` + FPM). Processos mais leves e o número de
  workers PHP passa a ser ajustado separado das conexões HTTP (que ficam
  baratas, inclusive as estáticas).
- [ ] **Pool do Supabase**: conferir o *pool size*; regra: workers PHP ×
  instâncias ≤ pool size − 5 (folga para o painel e o cron). Se precisar
  de mais, plano Pro do Supabase (também traz backup diário; já citado em
  pontos_gerais.md seção 8).
- [ ] **Cloudflare na frente do domínio próprio** (proxy laranja): cache
  dos estáticos na borda, HTTP/3, proteção contra robôs e regras de
  limite por IP de graça. Os estáticos param de gastar CPU do Render.

### 1.3. Live: o ponto mais crítico (🔴)

Hoje cada pessoa assistindo pede **as 100 mensagens inteiras** a cada 5 s
(`ajax_transmissao_comentarios.php`), e cada pedido lê a sessão no banco,
confere a transmissão e lista os comentários.

- [x] (27/10/2026) **Resposta compartilhada**: gerar o JSON do chat **uma vez a cada
  2–3 s para todo mundo** (APCu, ou arquivo em `/tmp` com trava) e servir
  o mesmo conteúdo a todos. 300 pessoas passam a custar ~1 consulta a cada
  2 s, não 60 por segundo. O "estou bloqueado no chat" vai num campo à
  parte, calculado uma vez por sessão.
- [x] (Pacote C) **Só o que mudou**: a lista tem uma versão (muda com mensagem
  nova ou moderação); o navegador manda a última que recebeu e, sem
  mudança, a resposta é só `{"igual": true}`. Quem está logado só abre a
  sessão (e confere o bloqueio do chat) a cada ~30 s.
- [x] (27/10/2026) **Não abrir sessão** para visitante anônimo nesse endpoint.
- [x] (Pacote C) Intervalo adaptativo: 3 s com mensagens chegando, até 10 s
  parado, pausa com aba escondida.
- [ ] Mais à frente: trocar polling por **Server-Sent Events** ou pelo
  *Realtime* do Supabase (canal por transmissão), como o chat do YouTube.

O mesmo vale para o **indicador "Ao vivo"**: a resposta é igual para
todos → cache de 30 s no servidor e `Cache-Control: public, max-age=30`
(a Cloudflare segura o pico).

### 1.4. Vídeo (🟠)

- [x] (27/10/2026) **Cards da home com `<video preload="metadata">`**
  (`php/fiel/includes/card_video.php:25`): cada card baixa um pedaço do
  vídeo do R2 ao abrir a página. No 4G, uma home com 20 cards gasta dados
  à toa. Trocar por **imagem de capa (poster)** e carregar a prévia só ao
  passar o mouse / ao ficar visível no celular (como a Netflix).
- [ ] **Qualidade adaptativa (HLS)**: hoje é um MP4 só. Quem está no 4G
  fraco trava; quem está no Wi-Fi não ganha qualidade. Opções: Cloudflare
  Stream (pago por minuto, faz tudo), ou converter no upload com ffmpeg
  para 360p/720p/1080p + `hls.js`. Decidir pelo custo depois de medir o
  uso real.
- [ ] Gerar **miniatura automática** de cada aula (frame do vídeo) quando
  o admin não envia capa.
- [ ] **Legendas** (`<track>`): nenhuma aula tem hoje. Ajuda surdos,
  quem assiste sem som no ônibus e a busca (T35 já prevê a transcrição).

### 1.5. Medir antes de acreditar (🔴)

- [x] (27/10/2026) **Teste de carga com k6** (gratuito) em `tests/carga/`: 300
  usuários virtuais fazendo login, abrindo a home, abrindo uma aula,
  mandando heartbeat e, num cenário separado, 300 na live. Rodar
  **contra a homologação**, nunca em produção, e anotar aqui o resultado
  (req/s, p95, erros) antes e depois de cada melhoria.
- [ ] **Cache da Home entre requisições** (já listado em pontos_gerais.md
  seção 3): depois da troca de região, medir de novo e decidir.
- [ ] Ativar `pg_stat_statements` no Supabase e olhar as 10 consultas mais
  caras uma vez por mês.
- [x] (27/10/2026) Limpeza periódica (no cron que já existe): `id_sessao_php` vencida,
  `id_login_tentativa` antiga, `id_historico_visualizacao` muito velho.
  Tabela que só cresce deixa tudo mais lento com o tempo.

---

## 2. Segurança e privacidade

O básico está muito bem feito (ver [seguranca.md](seguranca.md)). Os
itens abaixo **não estão** em nenhum documento ainda.

### 2.1. LGPD (obrigatório)

- [x] (27/10/2026) 🔴 **Política de privacidade**: não existe (nenhuma página fala em
  "política de privacidade"). Dizer que dados guarda (nome, e-mail, CPF,
  telefone, nascimento, foto, progresso, pagamentos), para quê, com quem
  compartilha (Supabase, Cloudflare, OneSignal, Brevo, Asaas, YouTube),
  por quanto tempo e quem é o encarregado (DPO). Link no cadastro, no
  rodapé e em Configurações.
- [x] (27/10/2026) 🔴 **Excluir minha conta** em Configurações (com senha), apagando ou
  anonimizando os dados pessoais e mantendo só o exigido por lei
  (pagamentos). Também é exigência da Apple/Google se um dia o app for
  para as lojas.
- [x] (27/10/2026) 🟠 **Baixar meus dados** (JSON/CSV com perfil, progresso,
  certificados, comentários, orações).
- [x] (27/10/2026) 🟠 **Menores de idade**: o cadastro pede nascimento; abaixo de 18,
  pedir o consentimento do responsável (LGPD art. 14) e esconder a pessoa
  de listas públicas (ranking, "quem está online").
- [x] (27/10/2026) 🟡 Prazo de guarda: apagar automaticamente tentativas de login,
  sessões e logs com mais de X meses.

### 2.2. Segurança além do que já existe

- [x] (27/10/2026) 🟠 **Log de auditoria do admin**: hoje só fica registrado quem
  aprovou/negou cadastro. Ninguém sabe quem excluiu um curso
  (`admin_cursos.php:104`), uma aula (`admin_conteudos.php:80`) ou mandou
  push para todos. Tabela `id_admin_log` (admin, ação, o quê, id,
  antes/depois em JSON, IP, data) e uma tela **Atividade** (inspiração:
  audit log do Stripe). Com várias igrejas e grupos de permissão, é
  essencial.
- [ ] (em parte, 27/10/2026) 🟠 **2FA (código de app autenticador) para admin**, obrigatório para
  quem vê Financeiro/Contribuições. Opcional para o fiel.
- [x] (Pacote A) 🟠 **Aviso de login em aparelho novo** por e-mail ("Foi você?"),
  com link para encerrar as outras sessões (já existe a revogação).
- [x] (Pacote A) 🟠 **CAPTCHA invisível** (Cloudflare Turnstile, gratuito) no
  cadastro, "Esqueci minha senha" e contato. Hoje só há limite por IP;
  robô com muitos IPs enche a fila de aprovação.
- [ ] 🟡 Tela "Meus aparelhos" para o fiel (as sessões já estão em
  `ID_SESSAO`).
- [ ] 🟡 **Backup fora do Supabase**: `pg_dump` diário cifrado para o R2,
  com teste de restauração por trimestre.
- [ ] 🟡 Bootstrap e Font Awesome **servidos pelo próprio site** em vez do
  CDN: menos domínios na CSP e o site não depende do jsDelivr.

---

## 3. Experiência do fiel

### 3.1. Celular primeiro

- [x] (27/10/2026) 🟠 **Barra de navegação inferior** no celular (Início, Cursos, Ao
  vivo, Comunidade, Perfil). Hoje o celular só tem o menu lateral
  (`navbar_fiel.php:211`, offcanvas): tudo fica a dois toques. É o padrão
  de YouTube, Instagram, Netflix e Duolingo, e é o que faz o PWA parecer
  app de verdade.
- [x] (Pacote B: comentários, orações e resumos da aula) 🟠 **Skeletons** (blocos cinza piscando) nas listas e na home em
  vez de tela vazia enquanto carrega (nenhum hoje em `css/styles.css`).
- [x] (Pacote B) 🟠 **Peso**: `css/styles.css` tem ~190 KB, mas ~37 KB com gzip. O
  custo real era o navegador revalidar o arquivo em toda página. Agora os
  CSS/JS levam `?v=<versão>` e ficam 1 ano em cache (`.htaccess`); a versão
  muda sozinha a cada deploy (`site_versao_estatico()`).
- [ ] 🟡 Gesto de **puxar para atualizar** e **voltar com deslizar** no
  PWA; tela de **sem internet** amigável (o `sw.js` já tem cache).
- [x] (27/10/2026) 🟡 Atalhos do ícone (`shortcuts` no `manifest.json`): "Continuar
  assistindo", "Ao vivo", "Devocional do dia". `orientation: portrait`
  impede o vídeo deitado no PWA instalado: deixar livre.

### 3.2. Player e conteúdo (inspiração: Netflix, YouTube)

Já existe: continuar assistindo, contagem para a próxima aula, velocidade
salva, atalhos de teclado, renovação do link. Faltam:

- [x] (27/10/2026 e Pacote B) 🟠 **Picture-in-picture** e continuar ouvindo com a tela apagada
  (pregação vira "podcast"; `MediaSession` com capa e botões na tela de
  bloqueio).
- [x] (27/10/2026) 🟠 **"Pular 10 s"** com toque duplo nas laterais (celular) e as
  setas no computador.
- [ ] 🟡 **Baixar para ver sem internet** (Netflix): guardar a aula no
  cache do PWA por X dias, só para quem tem acesso.
- [ ] 🟡 **Capítulos** dentro da aula (o admin marca 00:00 Introdução,
  12:30 Oração…).
- [ ] 🟡 **Materiais da aula** (PDF, slides, esboço do sermão) para baixar.
- [ ] 🟡 **Quiz no fim do módulo** (3 a 5 perguntas) liberando o
  certificado — como Hotmart/Coursera. Dá sentido ao certificado.
- [ ] 🟡 **Certificado com código de validação** público
  (`/validar.php?c=XXXX` + QR no PDF): valoriza o certificado fora do app.

### 3.3. Engajamento: o que faz voltar todo dia

**Movido (27/10/2026) para [ideias_engajamento.md](ideias_engajamento.md)**,
com mais ideias. A tabela abaixo fica como resumo.

Já existe: ofensiva, medalhas (inclusive secretas e vitrine no perfil),
desafios, primeiros passos, lembrete diário, devocional, testemunhos,
orações. O que os apps que viciam têm a mais:

| Ideia | Inspiração | Prioridade |
|---|---|---|
| **Protetor de ofensiva** (1 por semana, ganho com medalha ou contribuição) — perder a ofensiva por um dia é o maior motivo de abandono | Duolingo (streak freeze) | ✅ Pacote B |
| **Meta diária** escolhida pelo fiel (5, 10, 20 min) com o anel de progresso na home | Duolingo, Apple Fitness | ✅ Pacote B |
| **Lembrete no horário em que a pessoa costuma usar** (calculado do histórico) em vez de só o horário fixo | Duolingo | 🟡 |
| **Versículo do dia em imagem** pronta para o status do WhatsApp/stories | YouVersion | 🟠 |
| **Compartilhar** medalha, certificado e ofensiva (Web Share API, imagem gerada) — cada compartilhamento é propaganda grátis | Strava, Duolingo | 🟠 |
| **Retrospectiva do ano** ("Seu 2026 na Manancial": horas, cursos, ofensiva, versículo mais lido), em dezembro | Spotify Wrapped | 🟡 (ótima para o 1º dezembro) |
| **Grupos/células** com mural, ranking amigável e plano de estudo em grupo; o líder vê o progresso do grupo | Strava (clubes), YouVersion (planos com amigos), Planning Center Groups | 🟡 |
| **Quiz ao vivo** durante a live/culto, com placar na tela | Kahoot | 🟡 |
| **"Orei por você"** (contador nos pedidos de oração, sem expor quem) | — | 🟡 |
| **Recomendação**: "Porque você assistiu X" e "Em alta na sua igreja esta semana" (top 10) | Netflix | 🟡 |
| **Minha lista** visível na home (favoritos já existem em `favoritos.php`) | Netflix | 🟡 |
| **Convite com link** ("traga um amigo"), com medalha quando o convidado conclui a 1ª aula | Dropbox, Nubank | 🟡 |
| **Agenda da igreja** (cultos, eventos) com "adicionar ao calendário" e inscrição | Church Center | 🟡 |
| **Novidades do app** dentro do app ("o que mudou nesta versão") — mostra que o produto melhora a cada atualização | Notion, Linear | 🟡 |

**Cuidado**: gamificação em igreja precisa de tom. Nada de ranking
público de quem contribuiu mais; rankings só entre quem aceitar aparecer.

### 3.4. Acessibilidade

- [ ] (rótulos de acessibilidade corrigidos no Pacote B; falta rodar) 🟠 Rodar o **Lighthouse** (Chrome → F12) na home, aula e perfil, no
  modo celular, e corrigir o que der vermelho em Acessibilidade e
  Desempenho. Meta: ≥ 90 nas duas.
- [ ] 🟡 Tamanho de fonte ajustável em Configurações (público mais velho).
- [ ] 🟡 Legendas (1.4) e descrição das imagens do carrossel.

---

## 4. Painel do admin

### 4.1. Produtividade

- [x] (27/10/2026) 🔴 **Listas sem limite** que pesam com muita gente:
  - `admin_conteudos.php:113` traz todas as aulas e assina uma URL do R2
    por linha → paginar, filtrar por curso, assinar só ao clicar "Ver".
  - `admin_enviar_mensagem.php:12` lista **todos** os fiéis em caixinhas →
    busca com autocompletar e envio por **segmento** (curso, filial, tipo
    de fiel, parados há X dias).
  - `admin_aprovar_usuarios.php`: sem limite nem **aprovar
    selecionados** (no dia de abertura vão chegar dezenas de uma vez).
- [x] (27/10/2026) 🟠 **Visão do usuário** (`admin_visao_usuario.php:15`): só os 100
  primeiros fiéis em ordem alfabética, sem busca; do 101º em diante
  ninguém abre. A lista de vídeos ali (linha 46) não é a do fiel.
- [x] (Pacote A; módulos ainda não, porque nenhuma tela os usa) 🟠 **Grade do curso** numa tela só: módulos e aulas **arrastáveis**
  para reordenar (hoje é digitar a ordem, `admin_conteudos.php:314`),
  com a coluna "Curso" na lista de vídeos (hoje mostra `r2:...`).
- [x] (Pacote A) 🟡 **Upload em lote** (vários arquivos viram aulas em rascunho com o
  nome do arquivo) e **agendar publicação** por aula.
- [ ] 🟡 Busca global no painel (Ctrl+K), como no Stripe.
- [ ] 🟡 Exportar CSV de membros e de progresso.
- [ ] 🟡 Padronizar os `confirm()` nativos que sobraram
  (`admin_transmissao.php`, `admin_assinaturas.php`) no `data-confirmar`.
- [x] (27/10/2026) 🟡 Telas com escuro fixo quebram o tema claro (`table-dark` em
  `admin_conteudos.php:458`, `bg-dark` em `admin_enviar_mensagem.php`).
  Textos sem acento: "Videos enviados", "Titulo", "descricao".

### 4.2. Métricas que o pastor quer ver

Nenhuma tela do admin usa `id_progresso`, que já guarda o tempo assistido.

- [x] (Pacote A) 🟠 **Funil**: cadastros → aprovados → assistiram a 1ª aula →
  concluíram um curso.
- [x] (Pacote A) 🟠 **Conclusão por aula e onde o fiel para dentro do vídeo**
  (retenção: "50% param no minuto 12") — como o YouTube Studio.
- [x] (Pacote A) 🟠 **Ativos por dia/semana/mês** em gráfico (hoje são números
  soltos em `admin_engajamento.php`).
- [x] (27/10/2026) 🟠 Corrigir a **taxa de conclusão** do dashboard
  (`php/admin/dashboard.php:64`): ignora quem está em 0% e infla o número.
- [ ] 🟡 **Régua de reengajamento**: "parou na aula 3 há 7 dias" → push e
  e-mail automáticos (push e e-mail já estão integrados). Inspiração:
  automações do Kajabi.
- [ ] 🟡 Relatório mensal automático por e-mail para a liderança.

---

## 5. Qualidade de engenharia

- [x] (27/10/2026) 🟠 **CI no GitHub Actions** (grátis): a cada push, `php -l` em tudo,
  `tests/testes_funcoes.php` e, depois do deploy, `tests/checar_site.php`.
  Hoje nada roda sozinho.
- [ ] 🟠 **Homologação separada da produção** (já está em pontos_gerais.md
  5.4 como pergunta): com dinheiro e 300 pessoas, todo push direto na
  produção é arriscado. Fluxo: `main` → homologação; tag/branch `prod` →
  produção.
- [x] (PHP em 27/10/2026, JS no Pacote B; falta criar a conta) 🟠 **Monitor de erros** (Sentry, plano grátis) no PHP e no JS: hoje
  um erro só aparece se alguém abrir o log do Render. Junto, o monitor de
  "fora do ar" (já em pontos_gerais.md seção 8).
- [ ] (em parte, 27/10/2026) 🟠 **Testes de ponta a ponta** (Playwright) dos fluxos que dão
  dinheiro ou prejuízo: cadastro → aprovação → login → assistir → certificado;
  contribuição Pix; live.
- [ ] 🟡 **Dividir `php/conexao.php`** (~10 mil linhas, 431 funções) em
  arquivos por assunto (`php/lib/auth.php`, `r2.php`, `asaas.php`,
  `medalhas.php`…), carregados pelo próprio `conexao.php`. Facilita
  revisar, testar e vender (outro dev entende mais rápido).
- [x] (Pacote C: seção 86, `id_schema_versao`, aviso no dashboard para a equipe e conferência no CI; não no `saude.php`, que não consulta o banco de propósito) 🟡 **Migrações com registro**: tabela `schema_versao` com o número
  da última seção rodada, conferida no `saude.php`. Acaba com o "será que
  a seção 79 rodou?".
- [ ] 🟡 **Feature flags** em `id_configuracao` (ligar uma novidade só
  para admins ou 10% dos fiéis antes de todos) — já é feito assim com as
  assinaturas; generalizar.
- [ ] 🟡 CSS inline repetido: ~380 linhas de `<style>` em cada página
  pública e ~300 no dashboard do admin. Mover para os `.css` (cache do
  navegador + uma fonte da verdade).

---

## 6. Virar produto para outras igrejas

### 6.1. A decisão que vem primeiro

"Um site por igreja" já é a primeira pergunta em
[pontos_gerais.md](pontos_gerais.md#6-depende-do-cliente). Recomendação:

| Fase | Como | Quando |
|---|---|---|
| **A. Uma instalação por igreja** | Mesmo código, um serviço no Render + um banco por igreja; marca e cores por variável/configuração | Até ~10 igrejas. Simples, isolado, sem risco de misturar dados |
| **B. Multi-igreja (multi-tenant)** | Tabela `id_igreja` + `igreja_id` em todas as tabelas + RLS no Supabase; domínio de cada igreja apontando para o mesmo serviço (Cloudflare for SaaS) | Quando manter N instalações virar trabalho demais |

A fase A só precisa do 6.2; a B é um projeto à parte (semanas).

### 6.2. Marca configurável (🟡, pré-requisito de qualquer venda)

- [ ] "Manancial" aparece em ~130 pontos de `.php`/`.html`/`.js`/`.json`
  (títulos, `manifest.json`, logo fixa em `admin_layout.php:78`, e-mails).
  Centralizar em configuração: nome, nome curto, logo, ícones, cor
  principal (variáveis CSS), e-mail remetente, redes sociais, domínio.
- [ ] `manifest.json` gerado por PHP a partir da configuração.
- [ ] Tela **Admin → Identidade da igreja** para trocar sem programador.

### 6.3. O que um comprador vai perguntar

- [ ] **Página de vendas** `/para-igrejas` (não existe): dor → vídeo de
  demonstração → recursos → preços → perguntas frequentes → "Agendar
  demonstração". Usar a Manancial como caso real. Inspiração:
  churchcenter.com, kajabi.com/pricing.
- [ ] **Planos** por tamanho (membros ativos e GB de vídeo) e uma tela
  **Plano e uso** para a igreja.
- [ ] **Assistente de primeiros passos da igreja**: logo e cores → 1º
  curso → 1ª aula → convidar líderes → link de cadastro (como o "setup
  guide" do Stripe).
- [ ] **Contrato, termo de tratamento de dados (LGPD, a igreja é a
  controladora e a equipe a operadora), SLA e canal de suporte.**
- [ ] **Central de ajuda** curta (vídeos de 1 min por tela do admin) e a
  página de **status** do sistema.
- [ ] **Importar membros** de planilha (toda igreja já tem uma).

---

## 7. Páginas públicas e aquisição

- [x] 🔴 **Bug** (27/10/2026): em `como-instalar.php` os passos 2, 3 e 4
  do iPhone usavam `ios-passo-2.jpg`, que é print de outro projeto
  ("Registro de Ponto"). Saiu dos passos 2 e 3; falta um print novo
  (pontos_gerais.md, seção 6).
- [x] (27/10/2026 e Pacote B: `assets/img/compartilhar.jpg`) 🟠 **Prévia no WhatsApp**: nenhuma página pública tem Open Graph
  (`og:title`, `og:image` 1200×630). O link compartilhado no grupo da
  igreja aparece sem imagem — é o principal canal de divulgação.
- [x] (27/10/2026) 🟠 **SEO básico**: `meta description`, `robots.txt` (bloqueando
  `/php/`), `sitemap.xml` e dados estruturados `Church`/`Course`. Nenhum
  existe hoje.
- [x] (27/10/2026) 🟠 **Catálogo público real**: `cursos.php` mostra 4 cartões fixos
  sem ler o banco. Listar os cursos publicados (capa, nº de aulas, aula
  grátis de amostra) — "experimente antes de se cadastrar", como a Netflix
  fazia com o 1º episódio.
- [x] (Pacote B: cursos completos, aulas, pessoas ou horas, só quando o número impressiona) 🟠 **Números reais** na home pública em vez de "+20 estudos / 24/7"
  (`index.html` ~632–644): membros ativos, horas assistidas, depoimentos
  aprovados.
- [x] (Pacote C: carrossel em WebP, de ~455 KB para ~215 KB; o `logo.png` de 153 KB não é usado por nenhuma página) 🟡 Imagens: WebP, `loading="lazy"`, `width/height` no carrossel
  público (`index.html` ~654–668) e `assets/logo.png` (153 KB).
- [ ] 🟡 **Entrar com Google** (e "link mágico" por e-mail): menos
  senha esquecida, cadastro mais rápido. O cadastro continua pendente de
  aprovação.
- [ ] 🟡 Aprovação **automática opcional** (ex.: e-mail confirmado + CPF
  válido) para a igreja que não quiser aprovar um a um.
- [x] (já estava com o visual do login; no Pacote C os títulos das abas foram padronizados) 🟡 `esqueci_senha.php` usa o visual da área logada (`styles.css`) e o
  título "Missão Evangélica"; deixar igual ao login e às páginas públicas.
- [ ] 🟡 Dashboard do admin: "Usuários recentes" e "Vídeos recentes" sem
  "ver todos" nem filtro de período.

---

## 8. Ordem sugerida

1. **Até 15/11**: 1.2 (região + plano), 1.5 (teste de carga), bug do
   `como-instalar.php`, listas sem limite do admin (4.1).
2. **Até 01/12**: chat da live em cache (1.3), política de privacidade e
   excluir conta (2.1), Open Graph (7), CAPTCHA (2.2), aprovar em lote.
3. **Dezembro/janeiro**: barra inferior no celular, skeletons, cards com
   capa em vez de vídeo (3.1, 1.4), auditoria + 2FA, CI + Sentry +
   homologação separada, métricas do admin, protetor de ofensiva e meta
   diária, compartilhar, retrospectiva do ano.
4. **Fevereiro/março** (junto com as assinaturas): marca configurável
   (6.2), página de vendas e planos (6.3), HLS se o uso pedir.
5. **Depois**: grupos/células, quiz, offline, multi-igreja (6.1 B).

---

## 9. Revisão geral de 27/10/2026 (segurança, desempenho e fluxos)

Três revisões independentes do código (só leitura), com os achados mais
graves conferidos depois no código. Os números de desempenho são
estimativas a partir do código, não medição. Não acharam: SQL injection,
XSS, open redirect, IDOR (fora a foto), medalha ou certificado em dobro.

### 9.1. Pacote D — bugs e segurança (antes do lançamento)

- [x] (Pacote D: envio em lote na hora, `notificacoes_push_lote()`) 🔴 **Push da live e das mensagens do admin não sai** para quem está
  fora do app: `transmissao_notificar_fieis()` e `admin_enviar_mensagem.php`
  só gravam em `ID_NOTIFICACAO`; o envio só acontece quando a pessoa abre
  o app (`ajax_onesignal_sync.php`).
- [x] (Pacote D: com alunos, oferece Arquivar ou "Excluir mesmo assim") 🔴 **Excluir curso apaga certificados e progresso de todos**
  (`ON DELETE CASCADE` em matrícula e certificado) e diz "excluído com
  sucesso" mesmo quando falha (`admin_cursos.php`). Bloquear com
  matrículas/certificados e sugerir arquivar.
- [x] (Pacote D) 🔴 **Contato**: sem `CONTATO_FORMSPREE_URL`, as mensagens vão para a
  conta de teste do Formspree (`php/fiel/contato.php`). Sem a variável,
  esconder o formulário.
- [x] (Pacote D) 🔴 **Foto de qualquer conta** (inclusive menores) trocando o id em
  `foto_perfil.php`; "conquistas recentes" da Home mostram menores e a
  equipe interna.
- [x] (Pacote D) 🔴 **Admin restrito vê CPF, telefone e endereço** no Dashboard
  (pendentes e recentes); a lista também mostra a equipe interna.
- [x] (Pacote D: data obrigatória no cadastro, não some nem vira menor sem responsável em Configurações, idade na aprovação) 🔴 **Menor sem responsável**: nascimento opcional no cadastro e
  editável depois; a aprovação não mostra idade nem responsável.
- [x] (Pacote D) 🔴 **Modo "só assinantes" bloqueia LGPD**: quem não assina não chega
  a Configurações (excluir conta, baixar dados) nem ao Contato.
- [x] (Pacote D) 🟠 **Certificado impresso em branco** (cores do tema escuro, fundo
  branco na impressão).
- [x] (Pacote D: relógio único por fiel no banco, seção 87) 🟠 **Curso concluído em minutos** abrindo várias aulas/sessões em
  paralelo (limite por aula e por sessão); idem meta diária.
- [x] (Pacote D: folga de 5 min, 1 min para admin) 🟠 **Sessão revogada vale com o banco fora** (`auth_sessao_revogada()`
  devolve "não revogada" no erro).
- [x] (Pacote D: todos os itens desta linha) 🟠 **Repetição**: contribuição pendente em paralelo (sem trava),
  `missao.php?aguardando=` consulta o Asaas sem freio, comentário de aula
  sem teto diário, "Baixar meus dados" sem senha nem limite, beneficiário
  do plano familiar descobre e-mails, moderação do lado do fiel liberada
  para qualquer admin sem `admin_log`, admin regrava duração da aula.
- [x] (Pacote D) 🟠 `ajax_presenca.php` sem o teto de 12 h, o Termo e o bloqueio de
  assinatura.
- [x] (Pacote D e fim da Fase 1, 28/10/2026: tudo; o "Novo curso" perdendo o digitado ficou para o aviso ao sair) (Pacote D fez: certificado no fim do curso, live antiga restrita, `?voltar=` do chat, notificação lida ao abrir, tabelas do Dashboard no celular, e-mail no login, textos; falta: notificações do admin sem link, recusado sem saída, aviso de aula sem duração na grade, recuperação de 2FA pelo super admin, "Novo curso" perdendo o digitado) 🟡 Fluxos: fim do curso sem caminho para o certificado; live antiga
  "só admins" trava a tela do fiel; "Entrar" do chat sem `?voltar=`;
  notificação não fica lida ao clicar; notificações do admin sem link;
  recusado que se cadastra de novo fica sem saída; aula sem duração nunca
  é concluída (a grade não avisa); 2FA sem recuperação pelo super admin;
  tabelas do Dashboard cortadas no celular; login e "Novo curso" perdem o
  que foi digitado; textos sem acento ("Video nao encontrado", "1 aulas",
  "Equipe SiteManancial").

### 9.2. Pacote E — desempenho no código

Estimativa de hoje com 300 pessoas: ~650–700 consultas/s.

- [x] (Pacote E) 🔴 **Sessão no banco**: 3 consultas e 1 escrita por requisição
  (`php/sessao_banco.php`): ler uma vez e só renovar a data a cada ~5 min.
- [x] (Pacote E: matrícula e ponto numa consulta, posição no upsert, % do curso só ao concluir, histórico a cada 2 min, meta pelo relógio) 🔴 **Heartbeat do player** (`ajax_progresso.php`): ~18 consultas (9
  escritas) a cada 30 s → ~5 (posição no mesmo upsert, dados da aula na
  sessão, recalcular o curso só ao concluir).
- [x] (Pacote E: 1 vez por aba) 🔴 **`ajax_onesignal_sync.php` em toda página logada**: só com push
  pendente ou 1 vez por sessão.
- [x] (Pacote E: novos, mais assistidos, destaque, filas por categoria, estatísticas e "Em alta", 2 min) 🟠 **Home**: blocos iguais para todos em cache compartilhado
  (~27 → ~12 consultas).
- [x] (Pacote E) 🟠 **Chat**: a mensagem nova apaga o cache e todos geram ao mesmo
  tempo; manter a cópia velha enquanto um só gera. Abertura da live
  (`ao_vivo.php`/`ao-vivo.php`) com o cache; página pública sem sessão.
- [ ] (fica para depois: precisa de colunas novas e mexe na ordenação) 🟠 **Comentários da aula**: subconsultas por linha e ordem pela soma
  dos votos; saldo e total de respostas guardados na linha.
- [x] (Pacote E: 12 índices, seção 88) 🟠 **~13 índices que faltam** (histórico, tentativas de login,
  comentários por autor, progresso por matrícula, chat, push pendente,
  sessões) → seção 87.
- [ ] (em parte: OneSignal com 3/8 s e push em massa em lote; a fila fica para depois) 🟠 **Push e e-mail fora da requisição** (fila) e timeouts de 3–5 s.
- [x] (Pacote E; e a sessão deixou de mudar em toda página) 🟡 `auth_touch` e a presença gravam `IDF_LAST_ACTIVITY` duas vezes.
- [x] (Pacote E) 🟡 **Apache**: `KeepAliveTimeout 2`, `Timeout 30`, processos já
  iniciados para o pico.
- [x] (Pacote E: CENARIO=culto, CONTAS em CSV) 🟡 **Teste de carga do culto**: player, fiéis logados no chat com
  `v=`/`b=1`, envio de mensagens, 300 chegando em 1 min, ~50 contas de
  teste, limites por endpoint.

### 9.3. Infraestrutura (do nosso lado, antes da produção)

- Render **na mesma região do Supabase** e plano pago (longe do banco,
  ~36 processos para 20 disponíveis).
- `SESSOES_NO_BANCO=1` no Render (o `render.yaml` vem com `0`; obrigatório
  com 2+ instâncias).
- Testar o **transaction pooler** do Supabase (porta 6543); o código já é
  compatível.
- **Cloudflare**: limite por IP em login, cadastro, esqueci a senha e
  `ajax_*`; cache dos estáticos.
- Depois: Supabase Pro, 2ª instância só se o teste pedir, PHP-FPM, Redis
  (Upstash) para o cache entre instâncias.

### 9.4. Campos e validações

- Nome com regras diferentes no cadastro (50, só letras) e no perfil (100,
  qualquer coisa).
- Curso: título sem limite, carga horária livre, publicar com 0 aulas.
- Certificado sem carga horária, nome da igreja e assinatura; código
  previsível (`CC-curso-fiel`).
- Live sem data/hora prevista, descrição e capa; não dá para editar.
- "Notificações por e-mail" salva e não faz nada.
- Motivo da recusa sem limite; Enviar Mensagem sem links para Ao vivo,
  Devocional, Testemunhos, Missão, Desafios e Contribuir.
- Cadastro sem "como conheceu / quem convidou"; telefone só celular;
  nascimento sem idade mínima.
- Aula sem capa, amostra grátis por aula, anexo e "liberar só depois da
  anterior".

### 9.5. Ideias novas

| Ideia | Inspiração | Esforço |
|---|---|---|
| Check-in no culto por QR (conta na ofensiva e na frequência) | Planning Center Check-Ins | M |
| "Sou novo aqui" com fila de acompanhamento do pastor | Church Center | M |
| Esboço do sermão para completar durante a live | YouVersion Events | M |
| Aniversariantes da semana com botão de WhatsApp | Facebook | P |
| Pedido de oração privado (só pastores/intercessores) | Pushpay | M |
| Comprovante anual de contribuições (IR) | Tithe.ly | P |
| Contribuição recorrente para missões | Pushpay, Tithe.ly | M |
| Escala de voluntários | Planning Center Services | G |
| Carteirinha digital de membro com QR | Sympla, Nubank | M |
| Aprovação delegada ao líder da filial/célula | Planning Center People | M |
| Modo telão (avisos, versículo, QR do check-in) | ProPresenter | M |
| Central de ajuda e novidades para o admin | Intercom, Notion | P |
| Pipeline de QA com Playwright em cima do CI (futuro) | — | M |

---

## 10. Revisão de 28/10/2026 (experiência, painel e robustez)

Três revisões independentes do código (só leitura), sem repetir a seção 9.
Os achados mais graves foram conferidos no código. Não acharam: dinheiro em
float, datas sem fuso no banco, service worker guardando página logada,
CDN sem SRI.

### 10.1. Pacote F — robustez (antes do lançamento)

- [x] (Pacote F) 🔴 **Sessão no banco desloga em qualquer soluço do banco**
  (`php/sessao_banco.php`): erro de consulta e "sessão não existe" voltam
  iguais; com erro, o `write()` pode gravar uma sessão vazia por cima.
- [x] (Pacote F: banco fora 1×/min, webhook, Brevo, push 1×/5 min; `saude.php?banco=1`) 🔴 **Alertas que não chegam a ninguém** (só `error_log`): banco fora
  do ar (`conexao.php`, conexão), webhook do Asaas falhando
  (`webhook_asaas.php`), e-mail recusado pela Brevo (e o `mail()` de reserva
  não existe no Render), push recusado. Mandar ao Sentry e criar
  `saude.php?banco=1` só para o UptimeRobot.
- [x] (Pacote F: 500 + Sentry na falha, "última execução" e aviso de agendador parado no Dashboard da equipe) 🔴 **Rotinas que "dão certo" quebradas**: `lembrete_diario.php`
  responde 200 com o SELECT falhando; a limpeza diária marca o dia antes de
  rodar; ninguém percebe se o cron parar. Gravar "última execução" e
  mostrar à equipe.
- [x] (Pacote F: aviso no Dashboard da equipe e no Sentry, 1×/h) 🟠 **Variáveis de produção** (`SITE_URL_PUBLICA`, `ASAAS_API_URL`…) caem
  no padrão de homologação/sandbox sem alarme: checar uma lista no boot e
  avisar o super admin.
- [ ] (em parte, Pacote F: Termo, ações da live, aprovação sem "@", todos os `commit()` conferidos com `db_commit()`; falta o resto das ~100 chamadas) 🟠 **Erros de SQL ignorados** em telas do painel e no termo (111
  chamadas sem conferir o retorno; ex.: "Transmissão excluída." sem
  conferir, laço sem mensagem no aceite do termo). Helper `db_exec()` com
  log + Sentry. Aprovação de cadastro sem transação; `commit()` nunca
  conferido.
- [x] (Pacote F) 🟠 **Lembrete diário**: hora entre 00:00 e 01:59 sai ~2 h atrasada
  (volta da meia-noite); um push por vez (até 300 × 8 s) — mandar em lote.
- [x] (Pacote F) 🟠 **`CURRENT_DATE` em UTC** no Postgres: projeto de missão "encerra" às
  21 h do último dia, maioridade vira às 21 h da véspera.
- [x] (Pacote F: consulta antes, só a gravação na transação; commit conferido) 🟠 **Webhook do Asaas** chama a API com a transação aberta (até 15 s).
- [x] (Pacote F: cache por caminho, `offline.html`, import da OneSignal protegido) 🟡 **Service worker**: cache cresce a cada deploy, sem página offline,
  e `importScripts` da OneSignal derruba a instalação se o CDN falhar.
- [x] (fim da Fase 1: `css/vendor/` e `js/vendor/`, conferidos pelo SRI) 🟡 **CDN sem plano B** (Bootstrap e Font Awesome em 67 páginas): servir
  de `vendor/` por um include único.

### 10.2. Pacote G — confiança da igreja (painel)

- [x] (Pacote G: registro automático em 11 telas, `admin_log_ao_redirecionar()`; "editar usuário" com antes/depois) ⭐ **Registro de atividade sem dinheiro e conteúdo**: Financeiro (%,
  lançamentos), ofertas manuais, projetos de missão, assinaturas (inclusive
  cortesia), devocionais, testemunhos, desafios, destaques, filiais,
  funções, tipos de fiel; "editar usuário" sem antes/depois.
- [x] (Pacote G, seção 89) ⭐ **Lançamento financeiro e oferta manual apagados de vez**: cancelar
  com motivo (fica riscado, fora dos totais, no CSV).
- [x] (Pacote G, seção 89; "quantos têm push ativo" fica de fora: a OneSignal não informa ao app) ⭐ **"Saúde do sistema"** no Dashboard: último webhook, eventos com
  erro, e-mails e push que falharam, quantos têm push ativo; "Enviar
  Mensagem" passa a dizer "push entregue a X de N".
- [x] (Pacote G) ⭐ **Envio para a igreja inteira sem confirmação**: "Você vai enviar
  para N pessoas (X com push)" com prévia.
- [x] (Pacote G) ⭐ **CPF no painel** sem dígito verificador e duplicado com erro
  genérico (o validador `assinatura_cpf_valido()` já existe).
- [x] (Pacote G: convite de 24 h) ⭐ **Admin cadastrar uma pessoa** (já aprovada, com convite para criar a
  senha).
- [ ] ⭐ **Ficha do membro** (`admin_membro.php`): dados, igreja, jornada,
  contribuições e histórico numa tela só.
- [ ] Lista de membros: busca por telefone/CPF, filtros, ordenação, ações
  em lote. Aniversariantes no Dashboard. Bloco "Precisa de você hoje".
- [ ] Pastor escrever e agendar devocional pelo painel.
- [ ] Permissão por ação (ver / editar / excluir), começando por Usuários.
- [ ] Estorno avisa todos os admins com nome (filtrar por quem tem
  Assinaturas/Financeiro); estornada como situação própria nas listas.
- [ ] Lixeira de 30 dias também para live, arte da home, desafio; aviso de
  quantos fiéis um tipo de fiel afeta.
- [ ] Formulários do admin perdem o digitado (aviso ao sair + campos
  devolvidos no erro).
- [ ] Segmentos novos em Enviar Mensagem (função, tipo, membro, idade),
  agendamento e "também por e-mail".
- [ ] ⭐ (decidir antes de vender) **Tesouraria da igreja** separada do
  repasse da plataforma: dízimo/ofertas por categoria, contribuinte,
  recibo numerado, relatório mensal, CSV para o contador. Junto, o "modelo
  de cobrança" por instalação (esconder a divisão quando a igreja é só
  cliente) e o fuso por igreja.
- [ ] "Exportar tudo" da igreja (ZIP de CSVs, com senha/2FA e no log).
- [ ] Agenda/eventos com inscrição e lembrete (G).

### 10.3. Pacote H — primeira impressão e uso pelo fiel

- [x] (Pacote H: Admin → Identidade da igreja) ⭐ **"Sobre" com depoimentos genéricos** ("— Usuário fiel"): trocar pelos
  testemunhos reais e um bloco "Quem somos" (pastor, endereço, horários).
- [x] (Pacote H) ⭐ **Home pública sem a igreja**: tirar os prints do site da faixa
  "Momentos reais"; hero com foto do culto, nome e horários; "Como
  funciona" em 3 passos.
- [x] (Pacote H: `cadastro_recebido.php`) ⭐ **Aprovação sem expectativa**: avisar no cadastro, tela de espera com
  o que fazer, recusado com contato.
- [x] (Pacote H) ⭐ **Rótulos só no placeholder** no cadastro/login e regras da senha
  escondidas: rótulos visíveis e checklist da senha ao vivo.
- [x] (Pacote H) ⭐ **"Rode database/…sql"** na tela do fiel (favoritos, histórico).
- [x] (Pacote H: `js/menu-publico.js`) ⭐ **Cabeçalho da home pública no celular** com 7 links quebrando em
  linhas: logo + Entrar + Criar conta + ☰.
- [x] (Pacote H: `siteAvisar()`/`siteConfirmar()` em `js/acoes.js`; sobram `confirm()` em 3 telas do admin) ⭐ **`alert()`/`confirm()` nativos** (25 só na aula): toast e modal
  próprios.
- [x] (Pacote H) ⭐ **Textos e alvos de toque pequenos** (barra inferior ~11 px, ícones de
  28–30 px): mínimo ~13 px e 44 × 44 px.
- [x] (Pacote H, em parte: nome e contatos pela Identidade em rodapés, Sobre, Contato e contribuição; o rodapé institucional ficou na Home) Nome da igreja diferente em cada lugar; rodapé institucional na área
  logada (Contato, Privacidade, Termos); recebedor claro na contribuição.
- [x] (Pacote H) Boas-vindas e "comece por aqui" no topo para o novato; saudação pelo
  horário; estados vazios com um botão de ação.
- [ ] (Pacote H fez: busca por GET com cursos e nome do curso; "Assistir agora" abre a aula; falta: menu do celular longo, ícones duplicados no topo) Menu do celular longo; busca só de aulas, por POST, "Tipo: VIDEO";
  "Pessoas online" e ☰ duplicados no topo; "Assistir agora" do carrossel
  não abre a aula.
- [ ] (fica para depois; o contraste dos cards não se confirmou: o Lighthouse mede) Botões com classes diferentes para a mesma ação; 49 cores fixas
  inline (somem no tema claro); contraste do texto secundário nos cards
  (~2,9:1).
- [x] (Pacote H) Mensagens de sucesso somem em 3,5 s; tela sem live promete aviso que
  pode não vir.
- [x] (Pacote H) Comemorar o fim do curso (confete + certificado); animações
  respeitando "reduzir movimento".

### 10.4. Pipeline de QA (futuro, base já existe)

- CI com o app em container + Postgres a cada push, Playwright contra
  `localhost` (não só a homologação toda semana), falhando com qualquer
  resposta ≥ 500 ou erro de JS; os testes com login falham (em vez de
  pular) quando faltam os segredos; PHPStan nível 0–1.
- Testes que valem mais: cadastro → aprovação → login → termo; aula até o
  certificado (`page.clock`); webhook do Asaas com eventos falsos e
  repetidos; Pix no sandbox; chat com dois navegadores; esqueci a senha;
  sessão com erro de banco; lembrete nos horários de borda; permissões
  (fiel A abrindo coisa de B); varredura de todas as telas.
