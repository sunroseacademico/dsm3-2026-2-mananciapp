# Visão geral do projeto (para agentes)

O MananciAPP é a plataforma de ensino da Missão Evangélica Manancial: área
pública, área do fiel (cursos em vídeo estilo streaming, comentários,
orações, medalhas, desafios, ao vivo) e painel administrativo. PHP puro
(sem framework), rodando no Render via Docker.

## Onde está cada coisa

| O quê | Onde |
|---|---|
| Visão geral, como rodar local, módulos | [README.md](../README.md) |
| Deploy, Render, banco e arquivos (como funcionam) | [DEPLOY_RENDER.md](../DEPLOY_RENDER.md) |
| Schema do banco (Postgres no Supabase) | [database/bancosupabase.md](../database/bancosupabase.md) (as versões MySQL simples/robusta em `database/` são só para a faculdade; o app não as usa) |
| Quadro de tarefas do cliente T01…T39 | [docs/interno/progresso_pedidos_cliente.md](../docs/interno/progresso_pedidos_cliente.md) |
| **O que falta** (configuração, segurança, desempenho, qualidade, decisões internas) | [docs/interno/pontos_gerais.md](../docs/interno/pontos_gerais.md) — consultar antes de propor melhorias e atualizar ao concluir ou achar um item |
| Capacidade (300 simultâneos), LGPD, CI, ideias de engajamento | [docs/interno/capacidade.md](../docs/interno/capacidade.md), [lgpd.md](../docs/interno/lgpd.md), [qualidade_e_ci.md](../docs/interno/qualidade_e_ci.md), [ideias_engajamento.md](../docs/interno/ideias_engajamento.md) |
| **Roteiro do produto** (escala para ~300 simultâneos, LGPD, UX, engajamento, venda para outras igrejas) | [docs/interno/plano_de_melhorias.md](../docs/interno/plano_de_melhorias.md) — item feito vira explicação no doc certo e sai de lá |
| **Segurança** (controles, achados abertos, rotina de testes, incidente) | [docs/interno/seguranca.md](../docs/interno/seguranca.md) — atualizar ao corrigir ou achar algo; rodar `tests/checar_site.php` depois de deploy |
| Índice de toda a documentação | [docs/README.md](../docs/README.md) |
| Política de Git (obrigatória) | [git-policy.md](git-policy.md) |

## Arquitetura em uma linha

PHP no Render (Docker) · banco **Postgres no Supabase** (via shim `mysqli_*`
→ PDO em `php/conexao.php`) · vídeos e capas no **Cloudflare R2** (URLs
pré-assinadas, valor `r2:...` no banco) · fotos de perfil no próprio banco ·
push pelo OneSignal · e-mail pela Brevo · assinaturas pelo Asaas.

## Regras do projeto

- O cadastro cria o fiel com status `pendente`; o login bloqueia contas
  pendentes ou negadas; o admin aprova ou recusa.
- Nada de arquivo enviado pelo usuário no disco do servidor: o disco do
  Render é apagado a cada deploy. Arquivos vão para o R2.
- Dois níveis de admin: **admin** (igreja) e **superadmin** (equipe
  interna, e-mails em `SUPERADMIN_EMAILS`). Tela ou campo só da equipe
  (Financeiro, dados de pagamento) usa `auth_require_superadmin()` /
  `auth_eh_superadmin()`. Ver [docs/interno/financeiro.md](../docs/interno/financeiro.md).
- **Grupos de permissões** (seção 73): cada admin abre só as telas do seu
  grupo (ou todas, com acesso total). Tela nova do painel: registrar a
  chave e os scripts em `admin_telas()` (`php/conexao.php`) e o item em
  `admin_layout_menu()` com a mesma chave — `auth_require(array('admin'))`
  e o menu passam a respeitar o grupo sozinhos.
- **Tipos de fiel** (seção 74): o que cada fiel muda no próprio perfil
  vem do tipo dele (`fiel_pode('nome')` etc., catálogo em
  `fiel_permissoes_catalogo()`). Ação nova do fiel que precise ser
  liberada por tipo: entrar no catálogo e ser conferida com `fiel_pode()`.
- **Destaques da home** (seção 75): o topo da home do fiel é um
  carrossel (`php/fiel/includes/home_carrossel.php`) com o curso em
  destaque e as artes de Admin → Destaques da home (imagem pronta + link).
- **Missões e Contribuir** são abas separadas do fiel: `missoes.php` lista
  as missões; `contribuir.php` abre direto a página da oferta geral (o
  projeto marcado em `missoes_oferta_geral_id()`, editado em Admin →
  Contribuições, só superadmin). As duas usam `missao.php` para contribuir.
- **Filiais** (seção 76): o fiel escolhe a filial de uma lista (Admin →
  Filiais; hoje os estados) ou "Nenhuma filial"; ver "FILIAIS" em
  `php/conexao.php`. Ainda não separa conteúdo nem admins por filial.
- Toda mudança de banco: documentar em `database/bancosupabase.md` (seção
  numerada, idempotente) e passar o SQL pronto para o usuário rodar no
  Supabase. O arquivo inteiro precisa montar o banco de produção do zero:
  nada de tabela, coluna ou dado fixo (medalhas, categorias, configurações)
  criado só à mão no painel. Ao criar seção nova, anotar em
  `docs/interno/pontos_gerais.md` (seção 8) que ela falta rodar em homolog
  e em produção. Da seção 86 em diante, toda seção nova **termina com**
  `INSERT INTO "id_schema_versao" ("isv_secao") VALUES (<número>) ON CONFLICT ("isv_secao") DO NOTHING;`
  e o mesmo número vai para `BANCO_SECAO_ESPERADA` em `php/conexao.php`
  (o CI confere as duas coisas).
- **Dados da igreja** (nome, contatos, endereço, horários, redes, quem recebe
  as contribuições): sempre de `igreja_info()` (Admin → Identidade da
  igreja, em `php/conexao.php`), nunca escritos à mão na página. Na
  `index.html` (estática), por `js/igreja-publica.js`.
- **Avisos e confirmações no JS:** `siteAvisar(texto, 'erro'|'ok'|'info')` e
  `siteConfirmar(texto, {botao, perigo}).then(...)` (`js/acoes.js`); nunca
  `alert()`/`confirm()` do navegador. Formulário com confirmação:
  `data-confirmar="..."`; que não pode perder o digitado: `data-avisar-ao-sair`.
- **Registro de atividade** (seção 83): ação nova e sensível do painel
  (excluir, aprovar, mudar permissão, mandar para muitos) chama
  `admin_log($conn, 'area.acao', 'tipo', $id, array(...))` depois de dar
  certo, e a ação entra em `admin_log_acoes()`.
- **Muita gente pedindo a mesma coisa** (polling, listas públicas): usar
  `site_cache_compartilhado($chave, $segundos, $gerar)` e apagar com
  `site_cache_apagar($prefixo)` onde o dado muda. Endpoint público de
  polling define `SITE_SESSAO_OPCIONAL` antes de incluir `conexao.php`. Ver
  [capacidade.md](../docs/interno/capacidade.md).
- **LGPD**: tabela nova com `idf_id` entra sozinha em "Baixar meus dados" e
  na exclusão de conta (`lgpd_tabelas_do_fiel()`); coluna com segredo
  precisa ter `senha`, `hash`, `token`, `segredo` ou `_2fa_` no nome para
  ficar fora da exportação. Excluir conta é sempre `lgpd_excluir_conta()`,
  nunca `DELETE FROM ID_FIEL` direto. Ver [lgpd.md](../docs/interno/lgpd.md).
- O CI (`.github/workflows/ci.yml`) roda `php -l` e `tests/testes_funcoes.php`
  a cada push: manter os testes passando.
- Textos, comentários e mensagens de commit em português do Brasil.

## Como trabalhar

1. Ler o arquivo atual e os vizinhos antes de editar; seguir o estilo e a
   densidade de comentários que já existem.
2. Corrigir na origem do problema, com a menor mudança possível; nada de
   mudança de estilo fora do escopo.
3. Validar: `C:\xampp\php\php.exe -l <arquivo>` em cada PHP alterado, rodar
   `C:\xampp\php\php.exe tests\testes_funcoes.php` (funções puras, sem
   banco; acrescentar teste ao mexer numa delas) e, quando der, testar o
   fluxo (servidor local em `php -S localhost:8000 -t .`).
4. Interface: funcionar no celular e no computador, temas claro e escuro,
   feedback de erro/sucesso. A CSP só roda script com nonce: todo
   `<script>` embutido leva `nonce="<?php echo site_csp_nonce(); ?>"`, e
   nada de `onclick=`/`onsubmit=` no HTML — usar `addEventListener` ou os
   atributos de `js/acoes.js` (`data-confirmar`, `data-enviar-ao-mudar`…).
5. Segurança: validar no cliente e no servidor, usar consultas preparadas
   (`db_query($conn, $sql, $params)`) no código novo, ler texto de
   `$_POST`/`$_GET` com `site_entrada_texto()` (array vira ''), escapar o que vai à
   tela (`htmlspecialchars`), conferir sessão/permissão e CSRF
   (`csrf_validar()`) nos formulários.
6. Documentação: os `.md` descrevem o sistema como ele é hoje. Ao entregar
   algo que estava planejado, transformar o plano em explicação; o que
   ficar faltando vai para `docs/interno/pontos_gerais.md`.
7. Git só quando pedido (ver [git-policy.md](git-policy.md)). Ao terminar,
   informar o que mudou, quais arquivos e que está pronto para commit.
