# Capacidade: aguentar ~300 pessoas ao mesmo tempo

Meta: **300 pessoas usando ao mesmo tempo** (70% navegando e vendo aula,
30% numa live), sem lentidão nem erro. Feito em 27/10/2026. O teste que
prova está em [tests/carga/](../../tests/carga/README.md).

---

## 1. Quanto cada pessoa pesa

| O quê | De quanto em quanto | 300 pessoas |
|---|---|---|
| Heartbeat do player (só com o vídeo tocando) | 30 s | ~8 req/s |
| Presença ("pessoas online") | 60 s | ~5 req/s |
| Indicador "Ao vivo" (toda página) | 60 s | ~5 req/s |
| Navegação (1 página a cada ~20 s, ~21 consultas cada) | — | ~15 páginas/s |
| Chat da live (só quem está na live) | 5 s | ~60 req/s com 300 na live |

O vídeo em si não passa pelo servidor: vai direto do Cloudflare R2 (link
assinado), e a live é o YouTube. O peso está no PHP e no banco.

## 2. O que o código já faz (27/10/2026)

| Mudança | Antes | Depois |
|---|---|---|
| **Chat da live em cache compartilhado** (`site_cache_compartilhado()`) | Cada pessoa: sessão + transmissão + 100 mensagens do banco a cada 5 s (~60 consultas/s com 300) | Lista montada uma vez a cada 2 s para todos (~0,5 consulta/s). Enviar mensagem e moderar apagam o cache, então nada atrasa |
| **Visitante sem login não abre sessão** nos endpoints de polling (`SITE_SESSAO_OPCIONAL`) | Com sessões no banco, cada visitante da live criava e conferia uma sessão vazia a cada 5 s (3 consultas) | Zero |
| **Indicador "Ao vivo"** | 1 consulta por pessoa por minuto | 1 consulta a cada 15 s para todos; o admin apaga o cache ao iniciar/encerrar |
| **Pessoas online** | Lista de até 100 pessoas recalculada por pessoa a cada minuto | 1 vez a cada 15 s |
| **Ranking da ofensiva** | Varria todos os dias de acesso de todo mundo a cada abertura da tela | 1 vez a cada 5 min |
| **Catálogo público** (`cursos.php`) | — (era fixo) | 1 consulta a cada 5 min |
| **Cards de vídeo** | Cada card baixava um pedaço do vídeo do R2 ao abrir a página | Capa quando existe; sem capa, a prévia só carrega quando o card aparece |
| **Listas do painel** | Todas as aulas (com uma URL assinada por linha), todos os fiéis em "Enviar Mensagem" e todos os pendentes | Paginadas e com busca |
| **Processos do Apache** | Fixos em 20 | Variável `APACHE_MAX_WORKERS` no Render (padrão 20) |
| **Tabelas que só cresciam** | Sessões, tentativas de login, notificações lidas | Limpeza diária (`site_manutencao_limpar()`) |
| **CSS e JS** (Pacote B) | O navegador perguntava ao servidor por cada CSS/JS em toda página | Com `?v=<versão do deploy>` ficam 1 ano no cache do navegador; a versão muda sozinha a cada deploy |
| **Números da home pública** (Pacote B) | — (eram fixos) | 1 consulta a cada 10 min para todos (`ajax_numeros_publicos.php`) |
| **Chat da live: só o que mudou** (Pacote C) | Cada pessoa baixava as 100 mensagens a cada 5 s, e quem estava logado lia a sessão e o bloqueio no banco em toda busca | Sem novidade, a resposta é só `{"igual": true}`; de 3 a 10 s conforme o movimento do chat; sessão e bloqueio a cada ~30 s |
| **Sessão no banco** (Pacote E) | 3 consultas e 1 escrita em toda requisição (inclusive polling) | 1 consulta; a validade só é renovada a cada 5 min, e a sessão não muda mais em toda página |
| **Player** (`ajax_progresso.php`, Pacote E) | ~18 consultas a cada 30 s por pessoa (metade escrita) | ~10: matrícula e ponto numa consulta, posição no mesmo upsert, % do curso só ao concluir, histórico a cada 2 min |
| **Aviso ao OneSignal** (Pacote E) | 1 pedido e ~6 consultas por página aberta | 1 vez por aba |
| **Home do fiel** (Pacote E) | ~27 consultas por abertura | ~12: blocos iguais para todos em cache de 2 min |
| **Chat: mensagem nova** (Pacote E) | Apagava o cache e todos iam ao banco juntos | Vence o cache: um regera, os outros recebem a cópia anterior |
| **Abertura da live** (Pacote E) | Transmissão e chat do banco para cada pessoa; visitante criava sessão | Do cache; visitante sem sessão |
| **Índices** (Pacote E, seção 88) | Histórico, tentativas de login, comentários por autor, chat, push pendente sem índice | 12 índices novos |
| **Apache** (Pacote E) | Conexão parada segurava o processo 5 s; reserva fixa de 5 a 10 processos | 2 s; reserva proporcional a `APACHE_MAX_WORKERS` |

O cache fica num arquivo do `/tmp` de cada instância: com duas instâncias,
cada uma tem o seu (no máximo 2 s de diferença no chat). Some no deploy,
sem problema.

## 3. O que é pago (e você provavelmente vai ter que fazer)

Preços de referência em 10/2026; **conferir no site de cada serviço** antes
de contratar.

| # | O quê | Onde | Custo aprox. | Por quê |
|---|---|---|---|---|
| 1 | **Render na mesma região do banco** (Virginia / US East) | Render → criar serviço novo na região certa (não dá para mudar a de um serviço existente) | 0 | O banco está na Virgínia; com o servidor em Oregon, cada consulta custa ~55 ms. Na mesma região, ~1–2 ms. Sozinho, é o que mais acelera |
| 2 | **Plano Standard do Render** (1 CPU, 2 GB) | Render → Settings → Instance Type | ~US$ 25/mês | O free (0,1 CPU, 512 MB) dorme e não aguenta 300. O Starter (0,5 CPU, 512 MB) também fica curto |
| 3 | **`APACHE_MAX_WORKERS` = 40** depois do Standard | Render → Environment | 0 | Mais requisições ao mesmo tempo; cabe nos 2 GB |
| 4 | **Conferir o Pool Size do Supabase** | Supabase → Database → Settings → Connection pooling | 0 | Cada processo do Apache segura uma conexão: workers × instâncias precisa caber no pool, com folga de ~5. Se não couber, aumentar o pool ou o plano |
| 5 | **Supabase Pro** | Supabase → Billing | ~US$ 25/mês | Backup diário, sem pausa por inatividade, mais conexões e CPU. Com dinheiro de verdade passando pelo app, recomendado (já em pontos_gerais.md, seção 8) |
| 6 | **Domínio próprio + Cloudflare na frente** (proxy laranja) | Registro.br + Cloudflare (grátis) | ~R$ 40/ano (domínio) | Cache dos arquivos estáticos na borda, proteção contra robôs, HTTPS. Atualizar `SITE_URL_PUBLICA`, CORS do R2 e OneSignal |
| 7 | **Segunda instância** no pico (cultos) | Render → Scaling (manual ou automático) | +US$ 25/mês por instância | Só se o teste de carga mostrar que uma não basta. As sessões já estão no banco, então funciona |
| 8 | **Monitor de erros** (Sentry) e de "fora do ar" (UptimeRobot) | sites deles | 0 (planos grátis) | Saber do problema antes do fiel reclamar |

**Ordem sugerida:** 1 → 2 → 3 → 4 → rodar o teste de carga com 50, 100,
200 e 300 pessoas → decidir 5 e 7 pelo resultado.

**Total provável no lançamento:** ~US$ 50/mês (Render Standard + Supabase
Pro) + domínio. Sem o Supabase Pro, ~US$ 25/mês.

## 4. Depois

- Trocar `prefork + mod_php` por **PHP-FPM** no Dockerfile (processos mais
  leves; mais conexões com a mesma memória). Mudança de infraestrutura:
  testar numa homologação separada antes.
- Chat da live por **Server-Sent Events** ou Supabase Realtime em vez de
  polling, se as lives passarem de ~500 pessoas.
- **Vídeo em qualidade adaptativa (HLS)** se o uso no 4G mostrar
  travamentos (Cloudflare Stream ou conversão no upload).
- Ligar `pg_stat_statements` no Supabase e olhar as consultas mais caras
  uma vez por mês.
