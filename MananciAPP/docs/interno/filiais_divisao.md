# Divisão por filiais — desenho completo (antes de codar)

> Situação: **proposta**, nada implementado. Base atual: seção 76 (lista de
> filiais + filial escolhida pelo fiel, sem separar nada).
>
> Decisão do usuário (28/10/2026): **segregação total**. Cada filial é um
> "sistema" próprio dentro do mesmo app: seus fiéis, seus cursos, suas
> lives, seus murais, **suas conquistas** e **sua identidade**. O admin é
> admin só da filial dele e dos usuários dela. O **geral** (para todas)
> existe, mas é exceção, publicada só pela plataforma ou pela sede.
>
> Substitui a ideia de "um site por filial"
> (`docs/para_o_cliente/pontos_para_alinhar.md`, seção 1).

Índice: 1 Modelo mental · 2 Como isolar (alternativas) · 3 Camadas de
proteção · 4 Papéis · 5 Inventário tabela por tabela · 6 Contexto de
filial e troca · 7 Fluxos · 8 Conteúdo geral · 9 Identidade por filial ·
10 Dinheiro · 11 Transição/migração · 12 Riscos · 13 Testes · 14 Fases ·
15 Decisões

---

## 1. Modelo mental

Três coisas, nunca misturadas:

| Coisa | O que é | Exemplo de dado |
|---|---|---|
| **Pessoa (conta)** | Quem faz login. Uma só, mesmo em várias filiais. | e-mail, senha, 2FA, nome, foto, telefone, nascimento, aceite de termos, tema |
| **Vínculo (membro de uma filial)** | A vida da pessoa **dentro** de uma filial. Uma pessoa em 2 filiais = 2 vínculos independentes. | status de aprovação, tipo de fiel, função, membro desde, matrículas, progresso, medalhas, ofensiva, protetores, favoritos, histórico, notificações, comentários, testemunhos, orações |
| **Filial** | O "sistema" de uma igreja local. | identidade (nome, logo, contatos, horários), cursos, lives, categorias, tipos de fiel, funções, tipos de admin, desafios, devocionais, destaques, missões, admins |

O **geral** é tratado como uma filial especial, "**Sede / Todas**":
publica conteúdo que aparece em todas as filiais, mas **o engajamento em
cima desse conteúdo continua sendo de cada filial** (§8).

Regra de ouro: **tudo que não é dado de login ou de identificação da
pessoa pertence a um vínculo ou a uma filial.**

## 2. Como isolar: alternativas

| Alternativa | Isolamento | Pessoa em 2 filiais / troca | Conteúdo geral | Custo de manter | Veredito |
|---|---|---|---|---|---|
| **A. Uma instalação (site + banco) por filial** | Total | Duas contas, sem troca | Copiar à mão em cada uma | N deploys, N bancos, N atualizações | Não atende "troca de filial" nem "geral" |
| **B. Um schema do Postgres por filial** | Muito alto | Difícil (login fora dos schemas) | Consultas entre schemas | Cada seção do banco rodada N vezes; o shim `mysqli` complica | Caro demais para o tamanho da equipe |
| **C. Tabelas compartilhadas + `filial_id` em tudo + várias camadas de proteção** | Alto, **se** as camadas da §3 forem seguidas | Natural (vínculos) | Natural (`filial_id` da Sede) | Um deploy, um banco | **Recomendado** |

O risco da C é **esquecer um filtro**. Por isso não basta "lembrar de
filtrar": a §3 empilha defesas para que um esquecimento vire erro visível,
e não vazamento silencioso.

## 3. Camadas de proteção (defesa em profundidade)

### C1. Um contexto de filial por requisição, decidido uma vez
- `filial_contexto()` é resolvido **uma vez** no início da requisição (em
  `conexao.php`, junto com a sessão) e não muda até o fim.
- Fonte: **somente a sessão validada** contra os vínculos aprovados (área
  logada) ou o código da filial na URL (área pública, só para ler a
  identidade e o conteúdo público).
- **Nunca** vem de campo de formulário, `?filial_id=` ou corpo de AJAX.
  O único lugar que aceita uma filial vinda do cliente é a tela de troca
  (§6), e ela confere o vínculo.

### C2. Funções centrais, nunca filtro escrito à mão
Seção "FILIAIS" em `php/conexao.php`:

```
filial_contexto()                       // id da filial ativa (Sede = id próprio, nunca null)
filial_sede_id()
fiel_vinculo_atual()                    // linha de id_fiel_filial da pessoa na filial ativa
filial_sql($alias)                      // "alias.filial_id = ?" + param — para dados do VÍNCULO/FILIAL
filial_sql_com_geral($alias)            // "alias.filial_id IN (?, sede)" — só para LER conteúdo publicado
filial_obter($conn, $tabela, $id)       // busca por id JÁ com filtro da filial; não achou = 404
filial_gravar_campos()                  // devolve filial_id do contexto para INSERT (nunca do POST)
admin_filial_pode($chave_tela)          // tela + filial juntas
filial_cache_chave($base)               // "base:f<id>"
```

- **Busca por id sempre com a filial no `WHERE`**
  (`WHERE id = ? AND filial_id = ?`). Registro de outra filial responde
  **404 "não encontrado"**, igual a id inexistente: não revela que existe.
- `UPDATE`/`DELETE` também com `AND filial_id = ?`. Se afetar 0 linhas,
  trata como "não encontrado".

### C3. O banco recusa o que não faz sentido
- `filial_id NOT NULL` em todas as tabelas de filial e de vínculo
  (conteúdo geral usa o id da Sede, não `NULL`: `NULL` esquecido não pode
  virar "aparece para todos").
- **Chaves estrangeiras compostas** que impedem misturar filiais mesmo com
  bug no PHP. Exemplo:
  `id_modulo (idc_id, filial_id) → id_curso (idc_id, filial_id)`;
  `id_matricula (vinculo_id, filial_id) → id_fiel_filial (iff_id, idl_id)`.
  Um progresso da filial A **não consegue** apontar para uma aula da
  filial B.
- Exceção controlada: engajamento sobre conteúdo **da Sede** guarda as
  duas filiais (a do vínculo e a do conteúdo), com `CHECK` de que a do
  conteúdo é a do vínculo **ou** a Sede.
- `UNIQUE` incluindo a filial onde o nome se repete por filial
  (categorias, tipos de fiel, funções, tipos de admin).

### C4. Row Level Security do Postgres (segunda trava, fase posterior)
Dá para fazer o próprio banco esconder as linhas de outra filial, mas há
duas armadilhas **neste projeto**:

1. **A conexão é persistente** (`PDO::ATTR_PERSISTENT`, Session pooler).
   Um `SET app.filial = 5` ficaria grudado na conexão e a **próxima
   requisição de outra pessoa** herdaria a filial 5 — exatamente a
   "transição indevida". Só seria seguro com `set_config(..., true)`
   dentro de transação em toda consulta, ou zerando no início **e** no fim
   de toda requisição (inclusive quando ela morre com erro).
2. O usuário `postgres` do Supabase **ignora RLS** (`BYPASSRLS`). Seria
   preciso um papel próprio do app, sem esse poder, e as tarefas de
   plataforma (cron, webhook, superadmin) num segundo papel.

**Proposta:** não colocar no lançamento. Lançar com C1–C3 e C5–C7, que já
pegam o esquecimento, e avaliar o RLS depois, com o tráfego real
(§15, D14).

### C5. Cache e arquivos separados por filial
- Toda chave de `site_cache_compartilhado` com dado de filial passa por
  `filial_cache_chave()`. Hoje há chaves globais que **vazariam**:
  `em_alta_contagens`, `catalogo_publico*`, `transmissao_ao_vivo`,
  `igreja_publica`, `numeros_publicos`, `metricas_*`.
- `igreja_info()` e `site_config()` têm cache estático/arquivo
  (`mananciapp_config_*.json`): passam a ser por filial.
- Arquivos no R2 com prefixo `f<id>/...`. A geração do link pré-assinado
  confere se o caminho pertence à filial do conteúdo.

### C6. Testes de isolamento e trava no CI (§13)
- Uma bateria de testes que tenta, como admin de A, abrir/editar/apagar
  cada coisa de B por id direto, e espera 404.
- Um script no CI que **falha** se encontrar SQL em tabela de filial sem
  passar pelas funções da C2 (lista de tabelas e um marcador de exceção
  justificada).

### C7. Vigilância
- Toda recusa por "outra filial" grava `filial.acesso_negado` no
  registro de atividade (quem, o quê, qual id). Uma tentativa isolada é
  link velho; várias seguidas é alguém testando ids.
- Painel do superadmin mostra essas recusas.

## 4. Papéis

| Papel | Quem | Pode | Não pode |
|---|---|---|---|
| **Superadmin** (plataforma) | Equipe interna (`SUPERADMIN_EMAILS`) | Criar/desativar filiais, nomear o primeiro admin de cada uma, ver tudo para suporte, financeiro da plataforma | — (todo acesso dele a uma filial fica no registro de atividade) |
| **Admin da Sede** | Pessoas da sede da igreja | Publicar conteúdo **geral** (cursos, lives, comunicados, vídeos) e configurar a Sede | Ver fiéis, progresso, murais ou métricas das filiais (vê só **números somados** do conteúdo geral) |
| **Admin de filial** | Pastor/equipe local | Tudo da própria filial, conforme o tipo de admin dele **naquela** filial | Ver ou mexer em outra filial; publicar geral; bloquear/excluir a **conta** de alguém |
| **Fiel** | Vínculo aprovado | Usar a filial ativa | Ver pessoas/murais de outra filial |
| **Sem filial** | Escolheu "Nenhuma filial" | Ver só o geral (como um vínculo na Sede, sem comunidade) | — |
| **Visitante** | Sem login | Páginas públicas da filial do link | — |

- Admin **é por filial**: `id_admin_filial (pessoa, filial, tipo de admin)`.
  A mesma pessoa pode ser admin de A com "acesso total" e só fiel em B.
- Admin de filial também é membro dela (o vínculo é criado junto).
- "Ver como superadmin" uma filial é **troca explícita** de contexto, com
  aviso fixo na tela ("Você está vendo a Filial X como suporte"), e fica
  registrado.

## 5. Inventário tabela por tabela

Legenda: **Conta** = da pessoa, global · **Vínculo** = por pessoa+filial ·
**Filial** = da filial (pode ser a Sede) · **Config** = configuração por
filial · **Plataforma** = global, só superadmin.

| Tabela | Classe | O que muda |
|---|---|---|
| `id_filial` | Plataforma | + código público, Sede marcada, ativa/encerrada |
| `id_fiel` | Conta | Saem para o vínculo: `idf_status` (aprovação), `idf_tipo_id`, `idf_funcao_id`, `idf_funcao`, `idf_membro`, `idf_membro_desde`, `idf_protetores`, `idf_medalhas_destaque`, `idf_ultima_pagina`, `idf_filial*`. Ficam: login, 2FA, nome, foto, capa, contato, nascimento, endereço, LGPD/termos |
| **`id_fiel_filial`** (nova) | Vínculo | status (pendente/aprovado/recusado/saiu/removido), tipo, função, membro, protetores, medalhas em destaque, datas, quem decidiu |
| `id_admin` | → `id_admin_filial` | Admin por filial com o tipo de admin daquela filial |
| `id_admin_grupo` (tipos de admin) | Config | Cada filial tem os seus, criados a partir de um modelo |
| `id_categoria` | Config | Por filial; a Sede tem as do conteúdo geral |
| `id_curso`, `id_modulo`, `id_content` | Filial | `filial_id` nos três (FK composta) |
| `id_matricula`, `id_progresso`, `id_historico_visualizacao`, `id_favorito`, `id_aula_nota`, `id_avaliacao` | Vínculo | Por vínculo; com curso da Sede, guarda as duas filiais |
| `id_certificado` | Vínculo | Emitido com a identidade da filial onde foi feito (D7) |
| `id_resumo`, `id_resumo_voto` | Vínculo | Visíveis só dentro da filial |
| `id_conteudo_comentario`, `_voto` | Vínculo | Comentários de aula **geral** separados por filial (D4) |
| `id_transmissao` | Filial | Uma live por filial ao mesmo tempo + a da Sede |
| `id_transmissao_comentario` | Vínculo | Chat da live geral separado por filial (D4) |
| `id_fiel_bloqueio_chat` | Vínculo | Bloqueio vale na filial de quem bloqueou |
| `id_badge` | Plataforma | Catálogo e regras são do sistema |
| `id_fiel_badge` | Vínculo | **Conquistas por filial** |
| `id_fiel_acesso_dia`, `id_fiel_protetor_dia` | Vínculo | **Ofensiva por filial**: acessar a filial A não mantém a ofensiva de B (D5) |
| `id_desafio`, `_participante`, `_checkin` | Filial / Vínculo | Desafio e ranking por filial |
| `id_devocional`, `id_devocional_nota` | Filial / Vínculo | |
| `id_testemunho`, `_voto`, `id_oracao`, `_amem` | Vínculo | Murais por filial |
| `id_home_destaque` | Filial | Carrossel = destaques da Sede + da filial |
| `id_notificacao` | Vínculo | Notificação aparece só na filial dela; push leva o código da filial |
| `id_solicitacao_acesso` | Vínculo | |
| `id_missao_projeto`, `id_missao_contribuicao` | Filial | Ver §10 |
| `id_plano`, `id_assinatura`, `id_pagamento`, `id_asaas_evento`, `id_assinatura_beneficiario` | Plataforma ou Filial | **Decisão de dinheiro** (§10) |
| `id_financeiro_lancamento` | Plataforma | + `filial_id` para o relatório por filial |
| `id_configuracao` | Plataforma + Config | Dividir: chaves da plataforma × chaves por filial (`id_filial_config`) |
| `id_fiel_tipo`, `id_funcao_igreja` | Config | Por filial, a partir de modelo |
| `id_preferencia`, `id_fiel_tema`, `id_fiel_foto` | Conta | |
| `id_sessao`, `id_sessao_php`, `id_login_tentativa`, `id_recuperacao_*` | Conta | Sessão guarda a filial ativa |
| `id_lgpd_pedido` | Conta | Atendido pela plataforma; exportação traz todas as filiais da pessoa |
| `id_admin_log` | Filial + Plataforma | Admin de filial lê só o da filial |
| `id_schema_versao`, `id_sistema_falha` | Plataforma | |

Toda tabela nova daqui para frente precisa dizer, na seção do banco, a
qual classe pertence. Vira regra em `agents/overview.md`.

## 6. Contexto de filial e troca

### 6.1 Área logada
- Ao logar: última filial usada (se o vínculo ainda estiver aprovado) →
  filial principal → primeira aprovada → Sede (só geral).
- Sessão guarda `filial_ativa`; é **revalidada a cada requisição** (cache
  de poucos segundos), porque o vínculo pode ser removido com a pessoa
  logada. Vínculo perdido: volta para outra filial dela, com aviso.
- **Seletor** de filial na navbar e no topo do painel, só com 2+ opções.
  O nome e o logo da filial ativa ficam sempre visíveis.

### 6.2 Troca de filial
- `POST` + CSRF → confere o vínculo → regera o id da sessão → grava →
  limpa caches estáticos da requisição → vai para a **home** da nova
  filial (nunca "volta para a mesma página": a página era da outra).

### 6.3 Várias abas (o caso mais traiçoeiro)
A pessoa troca para B na aba 1; a aba 2 ainda mostra A e ela envia um
testemunho ali → ele cairia em **B**.
- Todo formulário e todo AJAX levam `filial_ctx` (a filial em que a página
  foi montada). O servidor **recusa** quando difere da sessão: "Você
  trocou de filial em outra aba. Recarregue a página." Nada é gravado.
- Polling (chat, status da live, presença) recebe `troca_de_filial` e
  recarrega.
- Entra no mesmo lugar que já confere o CSRF, para não depender de cada
  tela lembrar.

### 6.4 Links vindos de fora (push, e-mail, link compartilhado)
- Todo link gerado pelo sistema leva o código da filial (`?f=CODIGO`).
- Logado e com vínculo naquela filial, mas em outra: **troca sozinho** e
  avisa ("Você está agora na Filial X").
- Sem vínculo: 404 de conteúdo ("não encontrado ou não disponível para
  você"), sem dizer de que filial é.

### 6.5 Área pública
O endereço diz qual filial é (D1): `?f=CODIGO`, `/f/codigo` ou subdomínio.
Sem código: Sede. Login, cadastro, "sobre", "ao vivo" e catálogo público
usam a identidade e o conteúdo da filial do endereço.

## 7. Fluxos

| Fluxo | Como funciona |
|---|---|
| **Cadastro** | Pelo link da filial (já vem escolhida) ou escolhendo na lista. Conta `pendente` + vínculo `pendente`. **Admins daquela filial aprovam.** "Nenhuma filial": admins da Sede. Aprovar o primeiro vínculo libera a conta. |
| **Já tem conta e se cadastra em outra filial** | O e-mail já existe: "Entre com sua conta e peça para participar desta filial" (mesma mensagem genérica do cadastro atual, sem confirmar a terceiros que o e-mail existe). |
| **Participar de outra filial** | Configurações → Minhas filiais → Pedir para participar. Vínculo novo `pendente`, **zerado** (sem conquistas). O admin da filial aprova. |
| **Sair de uma filial** | O próprio fiel, com confirmação. Vínculo vira `saiu`; os dados ficam guardados e escondidos. Se voltar e for aprovado, recupera tudo. |
| **Mudou de cidade** | Pede a nova e sai da antiga. **Conquistas não migram** (são da filial). O que vai junto é o que é da pessoa (conta, certificados já emitidos). D6 decide se cursos da Sede contam lá também. |
| **Admin remove alguém da filial** | Vínculo `removido`. A pessoa continua com a conta e as outras filiais. Só a plataforma bloqueia ou exclui a **conta**. |
| **Admin de A também em B** | Painel só mostra A; em B a pessoa é fiel comum. O seletor do painel lista só as filiais em que ela é admin. |
| **Nomear admin** | Admin de filial com a tela "Tipos de Admin" promove um membro **da própria filial**, com tipo de admin **da própria filial** que não dê mais telas do que ele tem. Primeiro admin de cada filial: superadmin. |
| **Desativar filial** | Some da lista de cadastro; fiéis veem aviso e só o geral; nada é apagado. Encerrar de vez: exportação para a igreja + prazo + exclusão (LGPD). |
| **Juntar duas filiais** | Raro. Ferramenta do superadmin só se for pedida (D12); no começo, não existe. |
| **Excluir conta (LGPD)** | `lgpd_excluir_conta()` apaga a pessoa e **todos** os vínculos; a exportação traz tudo, de todas as filiais. |
| **Trocar e-mail ou senha, 2FA** | Da conta; valem para todas as filiais. Admin de filial não mexe. |

## 8. Conteúdo geral (Sede)

- Quem publica: admin da Sede ou superadmin. Na tela de criar, a escolha
  é "**Só a Sede/geral**"; o admin de filial nunca vê essa opção.
- Onde aparece: em todas as filiais, junto do conteúdo delas, com um selo
  "Geral".
- **O engajamento continua separado por filial:**
  - comentários e chat da live geral: cada filial vê só os da própria
    gente; moderação do admin daquela filial (D4);
  - progresso, conquistas e ofensiva: do vínculo onde foi feito (D6);
  - "em alta", visualizações e ranking: contados por filial;
  - a Sede vê só números somados, sem nomes.
- Comunicado geral: uma notificação por pessoa (não uma por vínculo),
  mostrada em qualquer filial.
- Admin de filial pode **esconder** um conteúdo geral na filial dele? (D8)

## 9. Identidade por filial

- Admin → **Identidade da igreja** passa a editar a filial ativa:
  nome, logo, cores, contatos, WhatsApp, endereço, horários, redes, texto
  "sobre", quem recebe as contribuições.
- Campo vazio herda da Sede (ex.: termos de uso, política de privacidade).
- `igreja_info()` → `igreja_info($filial_id)`. Chamadas sem filial passam
  a ser erro de programação, não "pega a global".
- Aparece em: área do fiel, páginas públicas (pela filial do endereço),
  e-mails (nome do remetente e resposta para o e-mail da filial),
  certificados, push (título com o nome da filial), páginas de erro.
- **Limites:** o PWA instalado tem **um** nome e **um** ícone no aparelho
  (`manifest.json`). Proposta: o app instalado se chama "Manancial" e a
  marca da filial aparece dentro dele (D3). `index.html` é estática e lê a
  identidade por `js/igreja-publica.js`: passa a mandar a filial do
  endereço.
- Cores por filial: só variáveis de CSS a partir de uma cor principal,
  conferindo contraste nos temas claro e escuro.

## 10. Dinheiro

É a decisão de maior impacto fora do código (D9):

| Opção | Como | Prós | Contras |
|---|---|---|---|
| **Central** | Uma conta Asaas; relatório e repasse por filial | Já funciona; menos burocracia | A sede repassa à mão; a filial depende da sede |
| **Subconta por filial** | Asaas com subcontas ou split | Cada filial recebe direto | CNPJ/documentos por filial; integração nova; mais para testar |

Proposta: **central no lançamento**, com `filial_id` em
pagamento/contribuição/lançamento para o relatório por filial; subcontas
depois, se pedirem. Planos de assinatura: iguais para todas (plataforma).
Missões: cada filial tem as suas; a oferta geral continua da Sede.

## 11. Transição (migração) sem passagem indevida de dados

**Ponto decisivo:** a produção ainda não existe (abertura 01/12/2026). Se
a divisão entrar **antes** da produção, ela nasce separada e não há
migração de gente de verdade. Fazer depois significa mexer em contas,
conquistas e murais com pessoas usando. **Proposta: fazer antes** (D13),
mesmo que isso mova a data.

Se ainda assim houver dados a migrar (homolog, ou a produção já aberta):

1. **Pré-requisitos:** lista real das filiais, a Sede, o primeiro admin
   de cada filial e o destino de cada estado provisório.
2. **Cópia do banco** e ensaio completo nela, com contagens antes e
   depois.
3. **Código sobe desligado** (`filiais_divisao_ativa = 0`): lê as colunas
   novas e se comporta como hoje.
4. **Preenchimento** (seção do banco, idempotente):
   - cada filial e a Sede ganham a configuração copiada da global
     (identidade, categorias, tipos de fiel, funções, tipos de admin);
   - conteúdo atual → Sede (geral) ou filial certa, conforme a lista;
   - cada fiel → um vínculo na filial que escolheu; conquistas, ofensiva,
     protetores, progresso, favoritos, notificações, comentários,
     testemunhos e orações vão para **esse** vínculo;
   - quem escolheu um **estado** provisório ou não informou: vínculo
     `pendente_confirmacao` + tela obrigatória no próximo login
     ("Confirme sua filial"); os dados vão para a filial confirmada;
   - admins atuais → admin da filial certa (lista do cliente); quem não
     estiver na lista perde o painel, com aviso.
5. **Consultas de conferência**, que precisam dar zero: linha de filial
   sem `filial_id`; engajamento cuja filial não bate com a do vínculo;
   aula de curso de outra filial; admin sem filial. Somas iguais antes e
   depois (medalhas, matrículas, comentários).
6. **Ligar:** derrubar todas as sessões (`auth_revogar_sessoes` em todos),
   ligar a chave, rodar a bateria de isolamento (§13) em produção com os
   usuários de teste.
7. **Volta atrás:** desligar a chave. As colunas novas não atrapalham o
   comportamento antigo, então voltar não perde nada.

## 12. Riscos

| # | Risco | Como acontece | Prevenção | Como se percebe |
|---|---|---|---|---|
| R1 | Consulta sem filtro | Tela nova ou dos 23 arquivos que leem `id_curso` direto | C2 + trava no CI (C6) | Teste de isolamento |
| R2 | Id na URL de outra filial | `?id=` trocado à mão | `filial_obter()` sempre; 404 | `filial.acesso_negado` no registro |
| R3 | Cache misturado | Chave sem filial | `filial_cache_chave()`; lista de chaves revisada | Teste com 2 filiais em sequência |
| R4 | Conexão persistente herdando filial | `SET` de sessão no banco | Não usar estado no banco; filial só no PHP (C4) | — |
| R5 | Várias abas | Troca numa aba, envia na outra | `filial_ctx` em formulário/AJAX (6.3) | Recusa visível |
| R6 | Gravar na filial errada | `filial_id` vindo do POST | Só `filial_gravar_campos()` | FK composta recusa |
| R7 | Admin escala privilégio | Promove alguém a admin de outra filial ou da Sede | Regras de §7 no servidor | Registro de atividade |
| R8 | Tarefa automática sem contexto | `lembrete_diario.php`, webhook, push | Laço por filial com contexto explícito | Teste da tarefa com 2 filiais |
| R9 | E-mail/push com identidade errada | Modelo usa `igreja_info()` global | Função exige filial | Revisão dos modelos |
| R10 | Busca e autocompletar | Lista pessoas/cursos de todas | Mesmas funções | Teste |
| R11 | Métrica/contagem revelando outra filial | "X fiéis no total" | Números por filial; Sede só soma | Revisão de telas |
| R12 | Link pré-assinado do R2 | Gera link sem conferir a filial do vídeo | Conferir antes; prefixo `f<id>/` | Teste |
| R13 | Conquista concedida na filial errada | Regra de medalha conta tudo da pessoa | Contagens por vínculo | Teste com pessoa em 2 filiais |
| R14 | Migração atribui dado errado | Estado provisório, fiel sem filial | Tela "Confirme sua filial"; conferências da §11 | Consultas de conferência |
| R15 | Superadmin vendo dados pessoais sem necessidade | "Ver tudo" é o padrão | Troca explícita + registro | Registro de atividade |
| R16 | Prazo | Mudança grande perto da abertura | Fases com a chave desligada; decidir D13 | Cronograma |
| R17 | LGPD: quem é o controlador | Filial com CNPJ próprio × a mesma igreja | D10 antes de abrir | — |

## 13. Testes

- **Funções puras** em `tests/testes_funcoes.php`: decisão de acesso para
  cada papel × cada classe de dado × divisão ligada/desligada.
- **Bateria de isolamento** (`tests/isolamento_filiais.php`, roda contra o
  servidor local ou a homolog):
  - massa fixa: filiais A e B, a Sede; fiel A, fiel B, fiel AB, sem
    filial; admin A, admin B, admin da Sede, superadmin; um item de cada
    tipo em cada filial;
  - para **cada** tela/endpoint de `admin_telas()`, cada `ajax_*` e cada
    página do fiel com `?id=`: cada ator tenta ler, editar e apagar o
    item da outra filial → espera 404/recusa e que **nada** mude no banco;
  - casos de troca: duas abas, link de push de outra filial, vínculo
    removido com a pessoa logada;
  - conquistas: o fiel AB faz uma ação em A → só A ganha.
- **Trava no CI:** script que lista SQL nas tabelas de filial fora das
  funções centrais e falha se aparecer uma nova sem marcador justificado.
- Depende dos usuários de automação em homolog (pendência já anotada).

## 14. Fases

| Fase | Entrega | Pronto quando |
|---|---|---|
| 0. Decisões | §15 respondida; lista real de filiais e admins | D1, D2, D9, D13 respondidas |
| 1. Fundação | Seção do banco (vínculos, admin por filial, Sede, `filial_id` + FKs compostas), funções C2, contexto, troca, várias abas, chave desligada, testes puros | testes passando; nada muda com a chave desligada |
| 2. Identidade e config por filial | `igreja_info($filial)`, `id_filial_config`, categorias, tipos de fiel, funções, tipos de admin por filial | cada filial com cara própria |
| 3. Pessoas | Cadastro/aprovação por vínculo, minhas filiais, admin por filial, gerenciar usuários | admin A não vê ninguém de B |
| 4. Conteúdo | Cursos/aulas/vídeos/R2, ao vivo por filial, busca, caches | bateria de isolamento verde nessas telas |
| 5. Engajamento | Progresso, conquistas, ofensiva, ranking, desafios, devocionais, murais, comentários, notificações, push | fiel AB com conquistas separadas |
| 6. Painel e tarefas | Métricas, dashboard, registro de atividade, mensagem, lembrete diário, webhook, e-mails | bateria completa verde |
| 7. Público e dinheiro | Páginas públicas por filial, relatório financeiro por filial | — |
| 8. Ligar | §11 passos 5–7 | conferências zeradas |

A divisão só é ligada depois da 6. Com o prazo de homolog até 15/11, **as
fases 1–6 não cabem com segurança**: é preciso decidir D13.

## 15. Decisões

**Já decididas pelo usuário:** segregação total; conquistas por filial;
admin só da própria filial e dos usuários dela; identidade configurável
por filial; geral só pela plataforma ou pela Sede; "Nenhuma filial" vê só
o geral; troca de filial para quem está em mais de uma.

**Em aberto (com proposta):**

| # | Decisão | Proposta |
|---|---|---|
| D1 | Como o endereço identifica a filial na área pública? `?f=CODIGO`, `/f/codigo` ou subdomínio (`filialx.…`)? | `?f=CODIGO` agora (zero infraestrutura); subdomínio depois, se quiserem |
| D2 | Lista real das filiais, quem é a Sede e o primeiro admin de cada uma | **Bloqueia** ligar |
| D3 | App instalado: nome único da plataforma ou da filial? | Único ("Manancial"), marca da filial por dentro |
| D4 | Comentários e chat em conteúdo/live **geral**: separados por filial ou todos juntos? | Separados (coerente com "tudo segregado") |
| D5 | Ofensiva: por filial (acessar A não conta para B)? | Por filial, como as conquistas |
| D6 | Curso da **Sede** feito na filial A: conta só para A ou para todas as filiais da pessoa? | Só para a filial onde foi feito; nas outras, o progresso aparece, mas não gera conquista |
| D7 | Certificado de curso geral: em nome de quem? | Da filial onde foi concluído, com "conteúdo da Sede" |
| D8 | Admin de filial pode esconder um conteúdo geral na filial dele? | Não no lançamento (geral é obrigatório); rever depois |
| D9 | Dinheiro: conta central ou subconta por filial? Planos iguais para todas? | Central + relatório por filial; planos iguais |
| D10 | LGPD: as filiais são a mesma pessoa jurídica? Quem responde pelos dados? | Confirmar com o cliente antes de abrir |
| D11 | Dados da pessoa (nome, foto, telefone) iguais em todas as filiais? | Sim (são da conta) |
| D12 | Ferramenta de juntar filiais / transferir conquistas? | Não no lançamento |
| D13 | A divisão entra **antes** da produção (pode mover a data) ou depois (migração com gente usando)? | **Antes** |
| D14 | Row Level Security do Postgres como segunda trava? | Depois do lançamento, com papel próprio no banco e sem estado na conexão persistente |
| D15 | Tipos de admin, tipos de fiel, funções e categorias: cada filial cria os seus a partir de um modelo, ou só a plataforma? | Cada filial, a partir de um modelo copiado ao criar a filial |
