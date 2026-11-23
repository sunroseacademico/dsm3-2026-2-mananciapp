# Documentação

Organizada em duas pastas: o que pode ser **enviado ao cliente** e o que é
**de uso interno** da equipe. Fora daqui ficam o esquema do banco
([`database/bancosupabase.md`](../database/bancosupabase.md)), como o app
está publicado ([`DEPLOY_RENDER.md`](../DEPLOY_RENDER.md)) e as instruções
para agentes ([`agents/`](../agents/)).

**Regra:** os documentos explicam **como o sistema é hoje**. Plano de algo
que já foi feito vira explicação de como funciona; o que falta vai para
[pontos_gerais.md](interno/pontos_gerais.md) (ou para o quadro de
tarefas, se for pedido do cliente).

## [para_o_cliente/](para_o_cliente/)

Linguagem sem termos técnicos; pode ser enviado como está.

| Arquivo | Para quê |
|---|---|
| [status_dos_pedidos.md](para_o_cliente/status_dos_pedidos.md) | O que foi pedido no PDF do cliente, o que já foi entregue, o que está em parte e o que falta. |
| [pontos_para_alinhar.md](para_o_cliente/pontos_para_alinhar.md) | Decisões que dependem do cliente: um site por igreja, assinaturas, vínculo com a igreja, medalhas, desafios, missões, devocionais, biblioteca, levar a plataforma a outras igrejas. |
| [Aplicativo (1) (1).pdf](<para_o_cliente/Aplicativo (1) (1).pdf>) | O PDF original com os pedidos do cliente (base do quadro T01…T39). |

## [interno/](interno/)

| Arquivo | Para quê |
|---|---|
| [pontos_gerais.md](interno/pontos_gerais.md) | **Tudo o que falta**, fora as tarefas do cliente: configuração (Render, cron-job.org, Formspree, OneSignal), segurança, desempenho, qualidade do código, decisões internas e o que fazer antes da produção. |
| [plano_de_melhorias.md](interno/plano_de_melhorias.md) | **Roteiro do produto:** aguentar ~300 pessoas ao mesmo tempo, LGPD, auditoria, experiência no celular, engajamento, métricas do admin, engenharia e o que falta para vender a outras igrejas, com prioridade e ordem sugerida. |
| [capacidade.md](interno/capacidade.md) | Aguentar ~300 pessoas ao mesmo tempo: quanto cada pessoa pesa, o que o código já faz (caches) e **o que é pago** (plano e região do Render, Supabase Pro, domínio), em ordem. |
| [lgpd.md](interno/lgpd.md) | Proteção de dados: papéis, como o app atende cada exigência (política, aceite, menores, baixar/excluir dados, prazos), pendências jurídicas e roteiro de teste. |
| [qualidade_e_ci.md](interno/qualidade_e_ci.md) | O que o GitHub Actions confere a cada push e toda semana, o teste de carga e os próximos passos (homologação separada, Sentry, testes de ponta a ponta). |
| [ideias_engajamento.md](interno/ideias_engajamento.md) | Banco de ideias para o fiel voltar todo dia e trazer outras pessoas (hábito, descoberta, comunidade, conquistas, crescimento), para escolher depois. |
| [seguranca.md](interno/seguranca.md) | **Segurança, documento vivo:** como o app se protege, achados abertos (S01…), rotina de testes na pasta e no site (`tests/checar_site.php`), o que fazer numa suspeita de ataque e o registro de cada rodada. |
| [progresso_pedidos_cliente.md](interno/progresso_pedidos_cliente.md) | Quadro de tarefas T01…T39: status, commit, SQL e roteiro de teste de cada entrega. |
| [transmissao_ao_vivo.md](interno/transmissao_ao_vivo.md) | Como a live funciona e o passo a passo para transmitir um culto. |
| [videos_cloudflare_r2.md](interno/videos_cloudflare_r2.md) | Vídeos e capas no Cloudflare R2: por que (custos), como recriar a configuração e compressão. |
| [reset_de_senha.md](interno/reset_de_senha.md) | Senhas: regra, login, "Esqueci minha senha", troca de senha e reset pelo admin, com o porquê de cada regra. Citado no código. |
| [financeiro.md](interno/financeiro.md) | Financeiro da equipe: de onde vêm os números, lucro, divisão com a igreja, modos de missões e quem vê (superadmin). Citado no código. |
| [assinaturas/assinaturas_asaas.md](interno/assinaturas/assinaturas_asaas.md) | Como as assinaturas com o Asaas funcionam (checkout, webhook, regra de acesso, admin). Citado no código. |
| [assinaturas/assinaturas_virada_producao.md](interno/assinaturas/assinaturas_virada_producao.md) | Passo a passo para começar a cobrar de verdade (T15) e onde anotar as respostas do cliente. |
| [assinaturas/teste_assinaturas_sandbox.md](interno/assinaturas/teste_assinaturas_sandbox.md) | Roteiro de teste das assinaturas no sandbox do Asaas. |
| [demonstrar_o_projeto/guia_demonstracao.html](interno/demonstrar_o_projeto/guia_demonstracao.html) | Guia completo de uma apresentação do app (abrir no navegador). Usado na demo de 20/10/2026; serve para as próximas. |
| [demonstrar_o_projeto/roteiro_demonstracao.md](interno/demonstrar_o_projeto/roteiro_demonstracao.md) | Roteiro da demonstração: preparação, contas, ordem e o que mostrar. |
| [demonstrar_o_projeto/destaques_do_app.md](interno/demonstrar_o_projeto/destaques_do_app.md) | Resumo dos pontos mais fortes do app para apresentações. |

## Juntados, reescritos ou retirados em 21/10/2026

Continuam no histórico do git (commit `54c9312` e anteriores).

| Arquivo | O que aconteceu |
|---|---|
| `interno/pendencias_configuracao.md`, `CHECKLIST_MIGRACAO.md` (raiz), `interno/assinaturas/perguntas_assinatura_equipe_tecnica.md` | Juntados em `interno/pontos_gerais.md`. O checklist da migração para a organização estava quase todo feito; o que sobrou foi para lá. |
| `interno/plano_transmissao_ao_vivo.md` | Era o plano de antes da live existir. Reescrito como `interno/transmissao_ao_vivo.md` (como funciona + passo a passo). |
| `interno/assinaturas/plano_assinaturas_asaas.md` | Era o desenho de antes da implementação, com esboços de código. Reescrito como `interno/assinaturas/assinaturas_asaas.md`. |
| `interno/reset_de_senha.md` | Era o levantamento de 500 linhas do que um reset seguro precisa. Reescrito como explicação do que o app faz. |
| `DEPLOY_RENDER.md` | Era o histórico da migração para Render/Supabase/R2. Reescrito com o estado atual. |
| `README.md` | Funcionalidades atualizadas (comunidade, gamificação, devocionais, assinaturas); histórico tirado. |
| Seções "Pontos para debater internamente" e "Problemas encontrados" de `progresso_pedidos_cliente.md` | Os pontos foram para `pontos_gerais.md`, seção 5; os três problemas já estavam corrigidos. |

## Juntados ou retirados em 15/10/2026

| Arquivo | O que aconteceu |
|---|---|
| `docs/interno/custos_armazenamento_videos.md` e `docs/interno/cronograma_cloudflare_r2.md` | Juntados em `interno/videos_cloudflare_r2.md` (commit `3257e41` e anteriores). |
| `SETUP.md` | Ainda ensinava a importar o banco MySQL. As dicas que valem foram para o "Como rodar" do `README.md`. |
| `agents/README.md`, `agents/copilot-context.md`, `agents/workflow.md` e os 7 arquivos de `agents/guides/` | Repetidos ou desatualizados. Juntados em `agents/overview.md`. |
| `database/Banco Igreja.sql`, `Banco Igreja Robusto.sql`, `Banco Igreja.txt` | Schemas MySQL antigos. Os dois `.sql` foram **refeitos** com o banco de hoje (versão simples e robusta para a faculdade); o `.txt` saiu. |

## Retirados em 07/10/2026

No git, commit `0bd52dd` e anteriores.

| Arquivo | Motivo |
|---|---|
| `requisitos.md`, `requisitos_status.md`, `regras_de_negocio_requisitos.md`, `lista_funcionalidades.txt` | Requisitos da versão acadêmica. O estado atual está em `status_dos_pedidos.md`. |
| `dicionario_de_dados.md`, `diagrama_relacional.mmd` | Cobriam só as 10 tabelas originais. O esquema atual está em `database/bancosupabase.md`. |
| `respostas_estrutura_de_dados.md` | Respostas de trabalho da faculdade. |
| `códgicoIA.md` | Rascunho de tutorial de envio de e-mail (Brevo), já implementado. |
| `perguntas_assinatura_igreja.md` | Juntado em `pontos_para_alinhar.md`, seção 2. |
| `plano_assinaturas_pagamento.md` | Guia genérico de antes da escolha do Asaas. |
| `plano_assinaturas_revenuecat.md` | Alternativa avaliada e não escolhida. Volta a valer se o app for para as lojas. |
