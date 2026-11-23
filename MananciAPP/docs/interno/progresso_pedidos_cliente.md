# Progresso dos pedidos do cliente

Lista de tarefas montada a partir do documento de ideias do cliente
(`Aplicativo (1) (1).pdf`) e do levantamento em
[status_dos_pedidos.md](../para_o_cliente/status_dos_pedidos.md).

Os pedidos foram quebrados em tarefas menores, cada uma pronta para virar
um card na plataforma de gestão. A ordem vai **do mais simples ao mais
complexo**.

Montado em 06/10/2026.

---

## Como usar

- **ID:** código da tarefa (`T01`, `T02`…). O número entre parênteses, como
  `(1.4)`, é o item do PDF do cliente.
- **Tamanho:** estimativa de esforço.
  - **P** = pequeno, até 1 dia
  - **M** = médio, 2 a 3 dias
  - **G** = grande, cerca de 1 semana
  - **GG** = muito grande, 2 semanas ou mais, ou depende de terceiros
- **Depende de:** tarefas que precisam estar prontas antes.
- **Status:** atualizar conforme o andamento: ⬜ A fazer · ⏸ Aguardando o
  cliente · 🔄 Em andamento ·
  🧪 Em teste (código pronto, falta validar no Render) · ✅ Concluído.
- **Commit:** commit que entregou a tarefa — útil para revisar ou fazer
  rollback no Render.
- **SQL:** tarefas que mexem no banco trazem o SQL na própria seção; ele
  também fica em `database/bancosupabase.md`.

---

## Cronograma de entrega

Combinado na call com o cliente em 22/10/2026.

| Data | O quê |
|---|---|
| **15/11/2026** | O cliente começa a subir os cursos no app. |
| **01/12/2026** | **Lançamento:** o site fica disponível, de início para o pessoal da igreja e **gratuito**. Nada de assinatura obrigatória: o único valor é **opcional**, a aba **Contribuir** (oferta geral + missões), que precisa estar ativa e funcionando com dinheiro de verdade. |
| **Fevereiro/março de 2027** (antes, se der certo) | Entrada da **assinatura** (T15). Planos, valores e regras ainda serão discutidos. |

Para o lançamento de 01/12, o que precisa estar pronto está em
[pontos_gerais.md](pontos_gerais.md), seção "Lançamento em 01/12/2026".

---

## Quadro geral

| ID | Tarefa | Item PDF | Tamanho | Depende de | Status | Commit |
|---|---|---|---|---|---|---|
| **Nível 1 — Ganhos rápidos (player e comentários)** ||||||
| T01 | Seletor de velocidade do vídeo (até 2x) | 1.4 | P | — | ✅ | `d106851` |
| T02 | Salvar a velocidade preferida do usuário | 1.4 | P | T01 | ✅ | `6eb27f6` |
| T03 | Voltar o vídeo do ponto onde parou | 1.2 | P | — | ✅ | `360d449` |
| T04 | Barra de progresso no thumbnail de cada vídeo | 1.2 | P | — | ✅ | `2d9be7d` |
| T05 | Próxima aula automática com contagem regressiva | 1.3 | P | — | ✅ | `5e0d88e` |
| T06 | Número de visualizações no vídeo | 4 | P | — | ✅ | `6773633` |
| T07 | Botão "Ver mais comentários" | 4 | P | — | ✅ | `629f30c` |
| T08 | Cadastro de membro / não membro com tempo de membresia | 3.2 | P | — | ✅ | `789ca38` |
| **Nível 2 — Simples (home, perfil e assinaturas)** ||||||
| T09 | Filas por tema/categoria na home | 1 | M | — | ✅ | `1e0d3b5` |
| T10 | Selo "Em alta" nas aulas populares da semana | 1.7 | M | — | ✅ | `49b9be7`, `176f08e` |
| T11 | Novos campos de perfil (WhatsApp, nascimento, foto, família, cidade) | 3 | M | — | ✅ | `d82fe53` |
| T12 | Função na igreja escolhida de uma lista | 3 | P | — | ✅ | `37880e7` |
| T13 | Barra "Perfil X% completo" | 3 | P | T11, T12 | ✅ | `0fe72bd` |
| T14 | Medalhas de crescimento pessoal (7, 21 e 100 dias) | 3.3 | M | — | ✅ | `e314ea2` |
| T15 | Assinaturas: virada para produção (fev/mar 2027) | 5 | M | — | ⏸ | |
| **Nível 3 — Médio (comunidade e perfil completo)** ||||||
| T16 | Votos "Edificante" e "Fora de contexto" nos comentários | 4 | M | — | ✅ | `b51b6c9` |
| T17 | Ordenar comentários e destacar o mais votado | 4 | M | T16 | ✅ | `919d0fb` |
| T18 | Respostas aos comentários com selo "Líder" | 4 | M | — | ✅ | `20d11f0` |
| T19 | Nota do pastor fixada no topo | 4 | P | — | ✅ | `9cc011b` |
| T20 | Compartilhar comentário | 4 | P | — | ✅ | `e729af7` |
| T21 | Citar versículo ou momento da aula no comentário | 4 | M | — | ✅ | `2ad2317` |
| T22 | Vínculo com a igreja: igreja, célula/grupo e tempo de fé | 3 | M | T12 | ✅ ⏸ | `ee78105` |
| T23 | Preferências de conteúdo (temas, tempo/dia, horário) | 3 | M | — | ✅ | `aa73e2c` |
| T24 | Selos exibidos no perfil | 3 | M | T14, T22 | ✅ ⏸ | `0185727` |
| T25 | Chromecast e AirPlay no player | 1.8 | M | — | ✅ | `106ab66` |
| T26 | Mural de testemunhos com destaque na home | 1.6 | G | T16 | ✅ | `624779d` |
| **Nível 4 — Complexo (novas áreas do app)** ||||||
| T27 | Aba "Minhas notas" na aula | 4 | M | — | ✅ | `493cd45` |
| T28 | Aba "Orações" com "amém" | 4 | G | — | ✅ | `e89ed22` |
| T29 | Resumos publicados pelos usuários | 3.3 | G | T16 | ✅ | `310e139` |
| T30 | Medalhas de ensino, resumos e oração | 3.3 | G | T14, T16, T18, T28, T29 | ✅ | `3d21a9c` |
| T31 | Desafios | 3.3 | G | T14 | ✅ | `04da169` |
| T32 | Contribuição em missões (projetos + PIX/cartão) | 6 | G | T15 | ✅ | `23f7f97`, `c0fad62`, `db159d2`, `214cc71`, `c423a06` |
| T33 | Devocionais enviados pelos usuários, com votação e agenda | 4.2 | G | — | ✅ | `76d9092` |
| T34 | Biblioteca de livros digitais | 2.2 | G | — | ⬜ | |
| **Nível 5 — Muito complexo (integrações externas)** ||||||
| T35 | Legenda automática dos vídeos | 1.5 | GG | — | ⬜ | |
| T36 | Bíblia integrada com versões | 2 | GG | — | ⬜ | |
| T37 | Planos de leitura bíblica | 2 | G | T36 | ⬜ | |
| T38 | Devocional diário no WhatsApp | 1.9 | GG | T11, T23, T33 | ⬜ | |
| T39 | Moeda interna (descontos e acessos) | 3.3 | GG | T15, T30, T31 | ⬜ | |

---

## Nível 1 — Ganhos rápidos

Mexem só no player e nos comentários, sem dependências. Bons para começar.

### T01 — Seletor de velocidade do vídeo (1.4) · P

Hoje só existe o controle nativo do navegador, que não aparece em todos os
celulares.

- Botão de velocidade no player com 0,75x, 1x, 1,25x, 1,5x, 1,75x e 2x.
- A velocidade escolhida é aplicada na hora (`video.playbackRate`).
- **Onde:** `php/fiel/assistir_video.php`.
- **Pronto quando:** o seletor aparece e funciona no Chrome, Safari
  (iPhone) e no app instalado (PWA).

> **Entregue — ✅ validado no Render (26/10/2026)** (`d106851`). Botão "Velocidade 1x" abaixo do
> vídeo, à direita, com menu e ✓ na velocidade atual. Acompanha também a
> troca feita pelo menu nativo do navegador.
>
> **Testar:** temas claro e escuro; escolher 1,5x e conferir botão e ✓;
> trocar pelo menu nativo do Chrome e ver o botão atualizar; iPhone
> (Safari) e app instalado; aula com vídeo inválido não mostra o botão.

### T02 — Salvar a velocidade preferida (1.4) · P

- Ao trocar a velocidade, salvar nas preferências do usuário
  (`ID_PREFERENCIA`).
- Ao abrir qualquer aula, aplicar a velocidade salva.
- **Pronto quando:** o usuário escolhe 1,5x numa aula, abre outra aula
  (ou outro aparelho) e ela já começa em 1,5x.

> **Entregue — ✅ validado no Render (26/10/2026)** (`6eb27f6`). Só a escolha feita no seletor do
> app é salva (a do menu nativo vale só para aquela aula). O servidor
> aceita apenas as 6 velocidades do menu.
>
> **SQL** (seção 45 do `bancosupabase.md`):
> ```sql
> ALTER TABLE "id_preferencia" ADD COLUMN IF NOT EXISTS "idpref_velocidade_video" NUMERIC(3,2) NOT NULL DEFAULT 1;
> ```
>
> **Testar:** escolher 1,5x, abrir outra aula, recarregar e entrar em
> outro aparelho — sempre 1,5x; deixar a aula passar sozinha para a
> próxima — continua 1,5x; voltar para 1x.

### T03 — Voltar do ponto onde parou (1.2) · P

O tempo assistido já é salvo (`php/fiel/ajax_progresso.php`), mas o player
sempre começa do zero.

- Ao abrir a aula, posicionar o vídeo no último tempo salvo.
- Se a aula já foi concluída (ou faltam poucos segundos), começar do zero.
- Opcional: aviso "Continuando de 12:34 · Começar do início".
- **Pronto quando:** sair no meio de uma aula e voltar retoma do mesmo
  ponto.

> **Entregue — ✅ validado no Render (26/10/2026)** (`360d449`). A posição fica numa coluna nova
> (o tempo que já existia é um total acumulado, não o ponto onde parou).
> Não retoma se parou nos primeiros 5 s ou nos últimos 10 s; aula
> terminada volta ao início. Aviso "Continuando de 12:34 · Começar do
> início", que some em 10 s.
>
> **SQL** (seção 46 do `bancosupabase.md`):
> ```sql
> ALTER TABLE "id_progresso" ADD COLUMN IF NOT EXISTS "idpr_posicao_segundos" INTEGER NOT NULL DEFAULT 0;
> ```
>
> **Limitações:** só aulas de curso (vídeo avulso não grava progresso); a
> posição é enviada a cada 30 s e ao sair da página, então no celular
> pode voltar até 30 s antes.
>
> **Testar:** assistir até ~2 min, sair e voltar; "Começar do início";
> outro aparelho; aula terminada começa do zero; aviso nos dois temas e
> no celular.

### T04 — Barra de progresso no thumbnail do vídeo (1.2) · P

Hoje existe a barra de % do curso; falta a de cada vídeo.

- Barra fina no rodapé do thumbnail de cada aula com o % assistido.
- Aparece na fila "Continuar Assistindo" e na lista de aulas do curso.
- **Onde:** `php/fiel/includes/card_curso.php`, `php/fiel/curso.php`,
  `php/fiel/dashboard.php`.

> **Entregue — ✅ validado no Render (26/10/2026)** (`2d9be7d`). As aulas não têm thumbnail
> próprio (são linhas numeradas) e "Continuar Assistindo" mostra cards de
> curso, que já tinham barra. Então a barra por aula ficou em:
> - lista de aulas do curso: barrinha + "12:34 min · 45% assistido";
> - lateral do player: barrinha;
> - cards de vídeo de "Recomendados para Você": barra no thumbnail e
>   botão "Continuar".
>
> Só aparece em aula começada e não concluída (a concluída já tem o ✓);
> no card de vídeo, a concluída mostra a barra cheia.
>
> **Correção junto:** a duração das aulas (`IDCT_DURACAO_SEGUNDOS`) nunca
> era gravada, então o % de cada aula ficava 0 até concluir. Agora o player
> manda a duração do vídeo e ela é gravada quando está vazia; o admin zera
> ao trocar o arquivo. Isso também faz aparecer a duração das aulas e dos
> cursos, que estavam vazias. Duração com 1 h ou mais aparecia errada na
> lista do curso — corrigido.
>
> **Sem SQL** (a coluna já existia).
>
> **Atenção:** aulas começadas antes deste deploy estão com 0% no banco; a
> barra aparece depois que o fiel assistir de novo (a duração é gravada na
> primeira vez que alguém abre a aula).
>
> **Testar:** abrir uma aula, assistir ~1 min e sair; na página do curso a
> aula mostra duração, % e a barrinha; na lateral do player também; em
> "Recomendados" o card da aula mostra a barra e "Continuar"; aula
> concluída mostra ✓ sem barrinha na lista; temas claro e escuro; no
> admin, trocar o vídeo de uma aula e conferir que a duração é medida de
> novo.

### T05 — Próxima aula automática (1.3) · P

Hoje há os botões "Aula anterior" e "Próxima aula". Ao terminar o vídeo,
a página já pula para a próxima aula depois de 1,8 s, mas sem aviso e sem
como cancelar.

- Ao terminar o vídeo, mostrar "Próxima aula em 5s… Cancelar".
- Sem cancelar, abrir a próxima aula do curso automaticamente.
- Na última aula do curso, não fazer nada (ou mostrar "Curso concluído").
- **Pronto quando:** terminar uma aula leva sozinho à próxima, e o botão
  Cancelar interrompe.

> **Entregue — ✅ validado no Render (26/10/2026)** (`5e0d88e`). Ao terminar a aula aparece, por
> cima do vídeo: "PRÓXIMA AULA · *título* · Começa em 5s", uma barra que
> esvazia, e os botões **Assistir agora** e **Cancelar**. Também cancelam:
> voltar a tocar o vídeo, arrastar a barra do vídeo ou apertar Esc. Se o
> vídeo estiver em tela cheia, sai dela para o aviso aparecer. Na última
> aula: "Esta foi a última aula do curso" com **Ver o curso** e **Fechar**.
> As cores do aviso são fixas (fundo do vídeo é sempre preto), iguais nos
> dois temas.
>
> **Sem SQL.**
>
> **Atenção:** no iPhone, a próxima aula pode abrir pausada — o Safari
> bloqueia vídeo com som tocando sozinho. Já era assim antes.
>
> **Testar:** terminar uma aula (arrastar para o fim) e esperar os 5 s;
> repetir e clicar Cancelar, depois Assistir agora; terminar em tela
> cheia; última aula do curso; aviso no celular (vídeo pequeno), com os
> botões cabendo; temas claro e escuro.

### T06 — Número de visualizações no vídeo (4) · P

- Mostrar "X visualizações" no cabeçalho da aula (mockup: "847
  assistindo").
- Usar os dados de progresso já salvos (quantas pessoas abriram a aula).
- Opcional depois: "X assistindo agora", usando a presença online que já
  existe.

> **Entregue — ✅ validado no Render (26/10/2026)** (`6773633`). "👁 1.234 visualizações" ao lado
> do título da aula (singular "1 visualização"). Conta **pessoas
> distintas** que já assistiram, em `id_progresso` — o histórico grava uma
> linha a cada 30 s e inflaria o número. Só aparece em aula de curso com
> pelo menos 1 visualização. No celular, desce para baixo do título.
>
> **Sem SQL.**
>
> **Ficou de fora:** "X assistindo agora". A presença online guarda só o
> título da página (pode repetir entre aulas); para ser confiável, ela
> precisaria guardar o ID da aula. Pode virar card.
>
> **Testar:** aula já vista mostra o número; aula nunca vista, assistida
> por alguns segundos com outra conta, passa a mostrar "1 visualização"
> ao recarregar; temas claro e escuro; celular com título longo.

### T07 — Botão "Ver mais comentários" (4) · P

- Mostrar os primeiros N comentários (ex.: 10).
- Botão "Ver mais X comentários" carrega os próximos.
- **Onde:** `php/fiel/ajax_video_comentarios.php`.

> **Entregue — ✅ validado no Render (26/10/2026)** (`629f30c`). Mostra os 10 mais recentes e
> "Ver mais X comentários" carrega os próximos 10 (some quando acaba;
> "Carregando…" enquanto busca; mensagem se der erro). O total no título
> passou a ser o real — antes a página carregava até 200 de uma vez e o
> total parava em 200. Paginação por cursor: comentário novo no meio não
> repete nem pula nenhum. No celular, o botão ocupa a largura toda.
>
> **Sem SQL** (o índice existente cobre a consulta).
>
> **Testar:** aula com mais de 10 comentários, clicar até acabar; comentar
> e excluir conferindo o total; aula sem comentários mostra "Seja o
> primeiro"; temas claro e escuro; celular.

### T08 — Membro / não membro (3.2) · P

- No cadastro e no perfil: "Faço parte da comunidade?" Sim / Não.
- Se sim, pedir mês e ano de entrada.
- No perfil, mostrar "Membro há X anos e Y meses".
- **Banco:** novas colunas no usuário; documentar em
  `database/bancosupabase.md`.

> **Entregue — ✅ validado no Render (26/10/2026)** (`789ca38`). Cadastro: "Você é membro da
> igreja?" (Sim/Não, obrigatório) e, para membro, mês e ano de entrada
> (sem data no futuro; validado também no servidor). Perfil: "Membro há X
> anos e Y meses" no topo e cartão **Vínculo com a igreja** editável —
> quem já tinha conta vê o formulário aberto, porque não respondeu no
> cadastro. O antigo "Membro desde \<cadastro\>" virou "Na plataforma
> desde".
>
> **Junto:** no cadastro, os dados digitados voltam preenchidos quando dá
> erro (antes tudo se perdia) e toda mensagem de erro fica vermelha.
>
> **SQL** (seção 47 do `bancosupabase.md`):
> ```sql
> ALTER TABLE "id_fiel" ADD COLUMN IF NOT EXISTS "idf_membro" SMALLINT;
> ALTER TABLE "id_fiel" ADD COLUMN IF NOT EXISTS "idf_membro_desde" DATE;
> ```
> Sem as colunas, os campos não aparecem e o cadastro segue funcionando.
>
> **Ficou de fora:** mostrar membro/não membro na lista de usuários do
> admin.
>
> **Testar:** cadastro com Sim e com Não; Sim sem mês e com mês no futuro;
> erro de senha mantém os dados; perfil de conta antiga (formulário
> aberto), salvar e editar; temas claro e escuro; celular.

---

## Nível 2 — Simples

### T09 — Filas por tema na home (1) · M

Hoje as filas da home são fixas (Continuar Assistindo, Novos, Mais
Assistidos, Recomendados).

- Uma fila por categoria de curso, com thumbnails grandes, no estilo
  Netflix.
- Mostrar só categorias que têm cursos publicados.
- Opcional: o admin escolhe a ordem e quais categorias aparecem na home.
- **Onde:** `php/fiel/dashboard.php`, `php/fiel/categorias.php`.

> **Entregue — ✅ validado no Render (26/10/2026)** (`1e0d3b5`). Depois das filas fixas, a home
> mostra uma fila por categoria ativa que tenha curso publicado, em ordem
> alfabética, com até 12 cursos (os mais novos primeiro). Cada fila tem o
> ícone da categoria (o mesmo da página Categorias) e "Ver todos" abre a
> lista de cursos já filtrada por ela. Os cards dessas filas são maiores e
> não repetem o selo da categoria no thumbnail. Curso sem categoria não
> entra em nenhuma fila de tema.
>
> **Junto:** cada card de curso fazia até 3 consultas ao banco e o mesmo
> curso aparece em várias filas; agora o resultado é reaproveitado dentro
> da mesma página, então as filas novas quase não pesam no carregamento.
>
> **Sem SQL.**
>
> **Ficou de fora:** o admin escolher a ordem e quais categorias aparecem
> na home — hoje não existe tela de categorias no admin (elas são criadas
> direto no banco). Pode virar card junto com um cadastro de categorias.
>
> **Testar:** home com cursos em 2+ categorias — uma fila por categoria,
> em ordem alfabética; categoria sem curso publicado (ou inativa) não
> aparece; "Ver todos" filtra a categoria certa; curso em andamento mostra
> a barra de progresso também na fila de tema; setas do carrossel no
> computador e arrastar no celular (um card grande + pedaço do próximo);
> página Categorias continua com os ícones; temas claro e escuro.

### T10 — Selo "Em alta" (1.7) · M

Hoje "Mais Assistidos" usa o total de sempre.

- Contar as visualizações de cada aula/curso nos **últimos 7 dias**.
- Acima de um limite (X), mostrar o selo "Em alta" no thumbnail.
- O limite X é configurável no admin (`id_configuracao`).

> **Entregue — ✅ validado no Render (26/10/2026)** (`49b9be7`). Selo laranja/vermelho "🔥 Em
> alta" no canto superior direito do thumbnail, nos cards de curso (todas
> as filas da home, Cursos, Favoritos) e nos cards de aula ("Recomendados
> para Você"). Conta **pessoas distintas** que assistiram nos últimos 7
> dias (mesmo critério da T06), pelo último acesso salvo pelo player — o
> histórico não serve porque o fiel pode apagá-lo. Curso e aula são
> contados separadamente.
>
> **Admin:** tela **Cursos**, caixa "Selo Em alta": mínimo de pessoas em
> 7 dias (padrão 5; 0 desliga) e a lista dos cursos mais vistos da semana
> com o número de pessoas e quem está com o selo — ajuda a calibrar o
> número.
>
> Quando o card tem os dois selos (categoria e Em alta), o da categoria
> encolhe com "…" para não ficar por baixo. As cores do selo são fixas
> (fica sobre a capa), iguais nos dois temas.
>
> **SQL** (seção 48 do `bancosupabase.md`) — opcional; sem ele vale 5 e
> tudo funciona, só a contagem fica mais lenta com o tempo:
> ```sql
> CREATE INDEX IF NOT EXISTS "ix_id_progresso_ultimo_acesso" ON "id_progresso" ("idpr_ultimo_acesso_em");
> INSERT INTO "id_configuracao" ("idcfg_chave", "idcfg_valor") VALUES ('em_alta_minimo', '5')
> ON CONFLICT ("idcfg_chave") DO NOTHING;
> ```
>
> **Complemento — duas filas na home** (`176f08e`):
> - **"Em alta esta semana"** (nova, logo depois de "Continuar
>   Assistindo"): só os cursos com o selo, do mais visto na semana para o
>   menos. Some quando nenhum curso passa do mínimo ou com o selo
>   desligado.
> - **"Mais Assistidos"** (geral, desde sempre), agora com ícone de
>   troféu: passou a contar **pessoas distintas** que assistiram alguma
>   aula. Antes contava linhas do histórico (uma a cada 30 s, inflando
>   quem assistia mais tempo) e a consulta nem rodava no Postgres (o nome
>   da categoria estava fora do `GROUP BY`) — por isso essa fila
>   provavelmente nunca apareceu no Render.
>
> **Testar:** no admin, pôr o mínimo em 1 e conferir que os cursos/aulas
> vistos na semana ganham o selo na home, em Cursos e em Favoritos; voltar
> para 5 e ver o selo sumir de quem tem menos; 0 desliga tudo; valor
> inválido (vazio, negativo) mostra erro; card com categoria comprida +
> selo no celular; temas claro e escuro (home e caixa do admin).
> Filas: com mínimo 1, "Em alta esta semana" aparece com os cursos da
> semana na ordem da lista do admin; com 0 ela some; "Mais Assistidos"
> aparece e não repete o mesmo curso duas vezes.

### T11 — Novos campos de perfil (3) · M

Conforme o mockup do cliente:

- **Identidade:** WhatsApp (obrigatório), data de nascimento e foto de
  perfil (upload).
- **Contexto familiar (opcional):** estado civil, tem filhos?, cidade e
  estado.
- Campos no cadastro (`php/fiel/register.php`) e na edição do perfil.
- O banco já tem telefone e CPF; criar o que faltar.

> **Entregue — ✅ validado no Render (26/10/2026)** (`d82fe53`).
> - **Cadastro:** WhatsApp (obrigatório, com máscara "(11) 9 9999-9999";
>   aceita colar com +55) e data de nascimento (opcional). Foto e contexto
>   familiar ficaram só no perfil, para o cadastro não ficar longo.
> - **Perfil:** o cartão "Dados pessoais" (antes só leitura) ganhou
>   **Editar** com WhatsApp, nascimento, estado civil, "Tem filhos?",
>   cidade e estado. Conta antiga sem WhatsApp vê o formulário já aberto e
>   o aviso "Informe seu WhatsApp para completar o cadastro".
> - **Foto:** botão de câmera no avatar (adicionar, trocar, remover). O
>   navegador recorta no centro, reduz para 320×320 e manda em JPEG; a foto
>   aparece no perfil e no avatar do menu. Fica no banco (tabela própria),
>   porque o disco do Render é apagado a cada deploy e o servidor não tem a
>   extensão de imagem para redimensionar.
> - **Aniversário:** no dia, o topo do perfil mostra "Feliz aniversário!".
> - O WhatsApp usa a coluna de telefone que já existia (só dígitos, como o
>   admin grava), então já aparece no admin e vai para o Asaas.
> - O endereço só aparece no perfil se estiver preenchido (o cadastro
>   nunca pediu e não dá para editar).
>
> **SQL** (seção 49 do `bancosupabase.md`):
> ```sql
> ALTER TABLE "id_fiel" ADD COLUMN IF NOT EXISTS "idf_nascimento" DATE;
> ALTER TABLE "id_fiel" ADD COLUMN IF NOT EXISTS "idf_estado_civil" VARCHAR(20);
> ALTER TABLE "id_fiel" ADD COLUMN IF NOT EXISTS "idf_tem_filhos" SMALLINT;
> ALTER TABLE "id_fiel" ADD COLUMN IF NOT EXISTS "idf_cidade" VARCHAR(100);
> ALTER TABLE "id_fiel" ADD COLUMN IF NOT EXISTS "idf_uf" CHAR(2);
>
> CREATE TABLE IF NOT EXISTS "id_fiel_foto" (
>     "idf_id" BIGINT PRIMARY KEY,
>     "idff_dados" TEXT NOT NULL,
>     "idff_atualizado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     CONSTRAINT "fk_id_fiel_foto_fiel" FOREIGN KEY ("idf_id")
>         REFERENCES "id_fiel" ("idf_id") ON DELETE CASCADE
> );
> ```
> Sem as colunas, cadastro e perfil mostram só o WhatsApp; sem a tabela,
> não aparece o botão de foto.
>
> **Ficou de fora:** o "conteúdo especial" de aniversário (definir com o
> cliente o que seria); foto no lugar das iniciais nos comentários; os
> campos novos nas telas do admin (o WhatsApp já aparece lá como
> telefone).
>
> **Testar:** cadastro sem WhatsApp, com telefone fixo, colando
> "+55 11 9…", com e sem nascimento, nascimento no futuro; perfil de conta
> antiga (formulário aberto + aviso), salvar e editar de novo; deixar tudo
> do contexto em branco; foto: JPG grande de celular, PNG com fundo
> transparente, foto deitada/em pé do iPhone (deve sair na orientação
> certa), trocar e remover, conferir o avatar do menu; arquivo que não é
> imagem mostra erro; colocar o nascimento com o dia de hoje e ver
> "Feliz aniversário!"; temas claro e escuro (menu da câmera, aviso
> amarelo, calendário do campo de data); celular.

### T12 — Função na igreja em lista (3) · P

Hoje a função é texto livre.

- Trocar por uma lista: Membro, Líder, Pastor, Diácono, Obreiro… (lista
  mantida pelo admin).
- Migrar os valores atuais para a lista mais próxima.

> **Entregue — ✅ validado no Render (26/10/2026)** (`37880e7`). Por decisão do cliente, **só o
> admin define a função**; o fiel vê no perfil ("Função na igreja · Definida
> pela equipe da igreja", ou "A definir pela igreja") e não altera. O campo
> "Função" saiu do cadastro.
> - **Admin → Funções na Igreja** (item novo no menu): lista de funções
>   (criar, renomear, ordem, marcar como **liderança**, desativar; excluir
>   só se ninguém usa) e a função de cada fiel, com busca e filtro "sem
>   função definida" — esses aparecem primeiro, com o texto que o fiel
>   tinha digitado no cadastro, para ajudar a escolher.
> - **Admin → Configurações → Editar usuário:** a função virou uma lista.
> - "Liderança" já deixa pronto o selo "Líder" dos comentários (T18).
> - Renomear uma função atualiza o nome em todos os fiéis que a têm; as
>   telas do admin que já mostravam a função continuam certas.
> - Lista inicial: Membro, Obreiro(a), Diácono(isa), Presbítero, Líder,
>   Missionário(a), Evangelista e Pastor(a) (as 5 últimas como liderança).
>
> **SQL** (seção 50 do `bancosupabase.md`) — cria a lista, a coluna e
> migra os textos atuais (copiar a seção inteira, inclusive o bloco
> `DO $$`). Quem não casar com nenhuma função fica "sem função definida"
> para o admin escolher:
> ```sql
> CREATE TABLE IF NOT EXISTS "id_funcao_igreja" (
>     "idfn_id" BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
>     "idfn_nome" VARCHAR(50) NOT NULL,
>     "idfn_ordem" INTEGER NOT NULL DEFAULT 0,
>     "idfn_lider" SMALLINT NOT NULL DEFAULT 0,
>     "idfn_ativo" SMALLINT NOT NULL DEFAULT 1,
>     "idfn_criado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>
>     UNIQUE ("idfn_nome")
> );
>
> INSERT INTO "id_funcao_igreja" ("idfn_nome", "idfn_ordem", "idfn_lider") VALUES
>     ('Membro', 10, 0),
>     ('Obreiro(a)', 20, 0),
>     ('Diácono(isa)', 30, 0),
>     ('Presbítero', 40, 1),
>     ('Líder', 50, 1),
>     ('Missionário(a)', 60, 1),
>     ('Evangelista', 70, 1),
>     ('Pastor(a)', 80, 1)
> ON CONFLICT ("idfn_nome") DO NOTHING;
>
> ALTER TABLE "id_fiel" ADD COLUMN IF NOT EXISTS "idf_funcao_id" BIGINT;
> DO $$ BEGIN
>     ALTER TABLE "id_fiel" ADD CONSTRAINT "fk_id_fiel_funcao_igreja"
>         FOREIGN KEY ("idf_funcao_id") REFERENCES "id_funcao_igreja" ("idfn_id") ON DELETE SET NULL;
> EXCEPTION WHEN duplicate_object THEN NULL;
> END $$;
>
> -- Migração: texto livre atual -> função mais próxima (sem acento, minúsculo).
> -- A ordem do CASE importa: "Pastor de jovens" vira Pastor(a), "Líder de
> -- louvor" vira Líder. Pode rodar de novo sem problema (só pega quem ainda
> -- não tem função).
> UPDATE "id_fiel" f
> SET "idf_funcao_id" = fn."idfn_id", "idf_funcao" = fn."idfn_nome"
> FROM "id_funcao_igreja" fn
> WHERE f."idf_funcao_id" IS NULL
>   AND fn."idfn_nome" = CASE
>       WHEN translate(lower(f."idf_funcao"), 'áàâãéêíóôõúüç', 'aaaaeeiooouuc') LIKE '%pastor%' THEN 'Pastor(a)'
>       WHEN translate(lower(f."idf_funcao"), 'áàâãéêíóôõúüç', 'aaaaeeiooouuc') LIKE '%presbit%' THEN 'Presbítero'
>       WHEN translate(lower(f."idf_funcao"), 'áàâãéêíóôõúüç', 'aaaaeeiooouuc') LIKE '%evangel%' THEN 'Evangelista'
>       WHEN translate(lower(f."idf_funcao"), 'áàâãéêíóôõúüç', 'aaaaeeiooouuc') LIKE '%mission%' THEN 'Missionário(a)'
>       WHEN translate(lower(f."idf_funcao"), 'áàâãéêíóôõúüç', 'aaaaeeiooouuc') LIKE '%lider%' THEN 'Líder'
>       WHEN translate(lower(f."idf_funcao"), 'áàâãéêíóôõúüç', 'aaaaeeiooouuc') LIKE '%diacon%' THEN 'Diácono(isa)'
>       WHEN translate(lower(f."idf_funcao"), 'áàâãéêíóôõúüç', 'aaaaeeiooouuc') LIKE '%obreir%' THEN 'Obreiro(a)'
>       WHEN translate(lower(f."idf_funcao"), 'áàâãéêíóôõúüç', 'aaaaeeiooouuc') LIKE '%membro%' THEN 'Membro'
>   END;
> ```
> Sem o SQL, a tela nova avisa para rodar a seção 50 e a edição de usuário
> continua com texto livre.
>
> **Testar:** rodar o SQL e conferir quem ficou sem função (consulta no fim
> da seção 50); na tela nova, criar, renomear (o nome muda na lista de
> usuários também), desativar (some da escolha, mas quem tem continua) e
> tentar excluir função em uso; definir a função de um fiel e ver no
> perfil dele; "Sem função" volta para "A definir pela igreja"; editar
> usuário pela tela de Configurações trocando e mantendo a função; cadastro
> novo sem o campo Função; temas claro e escuro; celular.

### T13 — Barra "Perfil X% completo" (3) · P

- Calcular o % de campos preenchidos (obrigatórios pesam mais).
- Mostrar a barra no topo do perfil, como no mockup.
- Opcional: aviso na home "Complete seu perfil".

> **Entregue — ✅ validado no Render (26/10/2026)** (`0fe72bd`).
> - **Perfil:** no fim do topo, "Perfil X% completo" com a barra e
>   "Falta:" com um botão para cada campo vazio (bolinha azul =
>   obrigatório, cinza = opcional). Clicar leva ao campo: abre a edição dos
>   dados pessoais, vai até o vínculo com a igreja ou abre a escolha de
>   foto. Com tudo preenchido: "Tudo preenchido".
> - **Home:** aviso "Seu perfil está X% completo · Falta: …" com
>   **Completar perfil**, enquanto faltar algo. O × esconde por 7 dias
>   (naquele aparelho).
> - **Conta:** obrigatórios pesam 2 — nome, e-mail, WhatsApp e "é membro
>   da igreja?"; opcionais pesam 1 — nascimento, foto, estado civil, "tem
>   filhos?" e cidade/estado (só conta com os dois). Arredonda para baixo:
>   100% só com tudo. Só os obrigatórios = 61%; tudo menos a foto = 92%.
> - **Fora da conta:** a função na igreja (é o admin quem define, T12) e
>   campos de tarefas futuras (igreja vinculada e temas de interesse,
>   T22/T23 — entram quando existirem). Campo cuja coluna ainda não existe
>   no banco também não conta.
>
> **Sem SQL.**
>
> **Testar:** conta antiga (baixo %, WhatsApp e vínculo como
> obrigatórios); clicar em cada item do "Falta:" (abre o lugar certo);
> preencher tudo e ver 100% e o aviso sumir da home; na home, fechar o
> aviso e recarregar (continua escondido); temas claro e escuro; celular
> (itens quebram linha).

### T14 — Medalhas de crescimento pessoal (3.3) · M

Usa os dados da **ofensiva**, que já existem (`php/fiel/ofensiva.php`).

- Estrutura de medalhas no banco (medalha, categoria, nível, regra) e
  medalhas conquistadas por usuário.
- Primeiras três medalhas:
  - **Sequência de 7** (Bronze): 7 dias seguidos.
  - **Discípulo de 21** (Prata): 21 dias seguidos.
  - **Centenário** (Ouro): 100 dias seguidos.
- Página/aba "Selos" no perfil, com as medalhas conquistadas e as
  bloqueadas (cinza), como no exemplo do cliente.
- Essa estrutura é a base das outras medalhas (T30).

> **Entregue — ✅ validado no Render (26/10/2026)** (`e314ea2`). Medalhas pela **ofensiva**: a
> medalha sai pelo **recorde** de dias seguidos (a maior sequência que a
> pessoa já fez) — conquistou, é para sempre, e quem já tinha sequências
> antigas recebe as medalhas na hora. As três do cliente + seis novas:
>
> | Dias seguidos | Medalha | Nível |
> |---|---|---|
> | 3 | Primeiros passos | Bronze |
> | **7** | **Sequência de 7** (cliente) | Bronze |
> | 14 | Duas semanas firmes | Bronze |
> | **21** | **Discípulo de 21** (cliente) | Prata |
> | 30 | Fiel de um mês | Prata |
> | 50 | Perseverante | Prata |
> | **100** | **Centenário** (cliente) | Ouro |
> | 180 | Meio ano de fé | Ouro |
> | 365 | Um ano de fidelidade | Diamante |
>
> - **Ofensiva:** "Próxima medalha: Discípulo de 21 · Faltam 12 dias" (no
>   lugar da antiga "próxima meta") e a grade de medalhas, como no exemplo
>   do cliente — conquistadas na cor do nível com a data, bloqueadas em
>   cinza com cadeado; a próxima mostra o progresso da ofensiva atual.
> - **Perfil:** seção "Minhas medalhas" com a mesma grade ("X de 9
>   conquistadas").
> - **Aviso:** "Nova medalha conquistada!" (dourado) quando ganha uma — no
>   primeiro acesso do dia, ou ao abrir a ofensiva/perfil. Tem prioridade
>   sobre o aviso de "+1 dia de ofensiva".
> - **Estrutura:** usa as tabelas `id_badge`/`id_fiel_badge`, que já
>   existiam sem uso, com categoria, nível e regra — base das medalhas de
>   ensino, resumos e oração (T30). Nome, ícone e dias de cada medalha
>   ficam no banco (dá para mudar sem deploy).
>
> **SQL** (seção 51 do `bancosupabase.md`):
> ```sql
> ALTER TABLE "id_badge" ADD COLUMN IF NOT EXISTS "idbadge_chave" VARCHAR(40);
> ALTER TABLE "id_badge" ADD COLUMN IF NOT EXISTS "idbadge_categoria" VARCHAR(40);
> ALTER TABLE "id_badge" ADD COLUMN IF NOT EXISTS "idbadge_nivel" VARCHAR(20);
> ALTER TABLE "id_badge" ADD COLUMN IF NOT EXISTS "idbadge_regra" VARCHAR(40);
> ALTER TABLE "id_badge" ADD COLUMN IF NOT EXISTS "idbadge_regra_valor" INTEGER;
> ALTER TABLE "id_badge" ADD COLUMN IF NOT EXISTS "idbadge_ordem" INTEGER NOT NULL DEFAULT 0;
> CREATE UNIQUE INDEX IF NOT EXISTS "ux_id_badge_chave" ON "id_badge" ("idbadge_chave");
>
> -- As três do exemplo do cliente (7, 21 e 100 dias) + seis intermediárias.
> INSERT INTO "id_badge" ("idbadge_chave", "idbadge_nome", "idbadge_descricao", "idbadge_icone", "idbadge_categoria", "idbadge_nivel", "idbadge_regra", "idbadge_regra_valor", "idbadge_ordem") VALUES
>     ('ofensiva_3',   'Primeiros passos',     '3 dias seguidos',   'fa-shoe-prints',    'crescimento', 'bronze',   'ofensiva_dias', 3,   3),
>     ('ofensiva_7',   'Sequência de 7',       '7 dias seguidos',   'fa-fire',           'crescimento', 'bronze',   'ofensiva_dias', 7,   7),
>     ('ofensiva_14',  'Duas semanas firmes',  '14 dias seguidos',  'fa-calendar-week',  'crescimento', 'bronze',   'ofensiva_dias', 14,  14),
>     ('ofensiva_21',  'Discípulo de 21',      '21 dias seguidos',  'fa-calendar-days',  'crescimento', 'prata',    'ofensiva_dias', 21,  21),
>     ('ofensiva_30',  'Fiel de um mês',       '30 dias seguidos',  'fa-calendar-check', 'crescimento', 'prata',    'ofensiva_dias', 30,  30),
>     ('ofensiva_50',  'Perseverante',         '50 dias seguidos',  'fa-mountain',       'crescimento', 'prata',    'ofensiva_dias', 50,  50),
>     ('ofensiva_100', 'Centenário',           '100 dias seguidos', 'fa-crown',          'crescimento', 'ouro',     'ofensiva_dias', 100, 100),
>     ('ofensiva_180', 'Meio ano de fé',       '180 dias seguidos', 'fa-seedling',       'crescimento', 'ouro',     'ofensiva_dias', 180, 180),
>     ('ofensiva_365', 'Um ano de fidelidade', '365 dias seguidos', 'fa-gem',            'crescimento', 'diamante', 'ofensiva_dias', 365, 365)
> ON CONFLICT ("idbadge_chave") DO NOTHING;
> ```
> Sem o SQL, a ofensiva continua como antes (com a "próxima meta").
>
> **Testar:** rodar o SQL e abrir a ofensiva com uma conta que tem
> sequência antiga (recebe as medalhas e o aviso de uma vez); abrir de
> novo (não repete o aviso); conferir a próxima medalha e o progresso;
> perfil com a seção de medalhas; conta nova (todas bloqueadas, progresso
> em "Primeiros passos"); temas claro e escuro (cores de bronze, prata,
> ouro e diamante legíveis nos dois); celular (grade de 2 colunas).

### T15 — Assinaturas: virada para produção (5) · M

> **Cronograma (call de 22/10/2026):** o app abre em **01/12/2026**
> gratuito, para o pessoal da igreja, **sem assinatura**; a assinatura
> entra em **fevereiro/março de 2027** (antes, se der certo). Até lá o
> único valor é opcional: a aba **Contribuir**. Planos e valores ainda
> serão discutidos. No lançamento, o módulo de assinaturas fica desligado
> ("Quem pode assistir" liberado para todos os cadastrados).
>
> **Interruptor pronto** (`72367d5`, 23/10/2026): Admin →
> Assinaturas → **"Assinaturas para os fiéis"**, fechadas por padrão
> (`assinaturas_abertas()`, chave `assinaturas_abertas` em
> id_configuracao). Fechadas: some "Assinatura" do menu e do perfil,
> `assinar.php` volta para a home, os botões "Assine para assistir" somem
> e ninguém é barrado (`assinatura_modo()` vira livre). Contribuir,
> missões, Pix direto e webhook seguem iguais; o admin continua mexendo
> nos planos. Sem SQL novo (usa a seção 43).
> **Testar:** com o Asaas configurado e a chave fechada, o fiel não vê
> "Assinatura" no menu nem no perfil e `assinar.php` cai na home; um
> curso pago abre normalmente; a aba Contribuir e o QR seguem; abrir a
> chave (pede confirmação): o menu volta e "Quem pode assistir" passa a
> valer; fechar de novo.

A integração com o Asaas já existe no código (ver
`assinaturas/assinaturas_asaas.md`) e roda no sandbox. **Perguntas para o
cliente:** [pontos_para_alinhar.md](../para_o_cliente/pontos_para_alinhar.md),
seção 2. **Passo a passo da virada:**
[assinaturas_virada_producao.md](assinaturas/assinaturas_virada_producao.md). Falta:

- [ ] Confirmar com o cliente os valores: o anual de R$ 19,90 é por mês ou
  o total? O família de R$ 39,00 é mensal ou anual?
- [ ] Testar todo o fluxo no sandbox (`assinaturas/teste_assinaturas_sandbox.md`).
- [ ] Criar a conta de produção do Asaas e trocar as chaves.
- [ ] Cadastrar e ativar os planos no admin.
- [ ] Escolher o modo de acesso ("Quem pode assistir") e ligar.

---

## Nível 3 — Médio

A maior parte deste nível é a **comunidade nos comentários** (item 4 do
PDF). Os votos (T16) são a base do mural de testemunhos, das medalhas e
dos resumos — por isso vêm primeiro.

### T16 — Votos nos comentários (4) · M

- Botões **"Edificante" (↑)** e **"Fora de contexto" (↓)** (o mockup
  do cliente mostra um "joinha", mas o app não tinha nenhuma reação).
- Um voto por usuário por comentário; pode trocar ou desfazer.
- Mostrar as contagens ao lado de cada botão.
- Opcional: muitos votos "Fora de contexto" escondem o comentário e
  avisam o admin.

> **Entregue — ✅ validado no Render (26/10/2026)** (`b51b6c9`). Só nos **comentários dos
> vídeos** (o chat da transmissão ao vivo fica de fora).
>
> - Abaixo de cada comentário: **↑ Edificante N** e **↓ Fora de contexto
>   N**. Clicar vota; clicar no mesmo de novo desfaz; clicar no outro
>   troca. O voto ativo fica colorido (verde / âmbar).
> - **Ninguém vota no próprio comentário**: os botões aparecem com as
>   contagens, mas desabilitados (evita inflar votos para as medalhas da
>   T30). O servidor também recusa.
> - Só conta de fiel aprovado e ativo vota; comentário excluído ou de
>   vídeo sem acesso devolve "Comentário não encontrado".
> - O **opcional** (esconder com muitos "Fora de contexto" e avisar o
>   admin) **não foi feito** — fica para decidir o limite com o cliente.
>
> **SQL** (seção 52 do `bancosupabase.md`):
> ```sql
> CREATE TABLE IF NOT EXISTS "id_conteudo_comentario_voto" (
>     "idcc_id" BIGINT NOT NULL,
>     "idf_id" BIGINT NOT NULL,
>     "idccv_tipo" SMALLINT NOT NULL,
>     "idccv_criado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     "idccv_atualizado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>
>     PRIMARY KEY ("idcc_id", "idf_id"),
>
>     CONSTRAINT "ck_id_conteudo_comentario_voto_tipo"
>         CHECK ("idccv_tipo" IN (1, -1)),
>
>     CONSTRAINT "fk_id_conteudo_comentario_voto_comentario"
>         FOREIGN KEY ("idcc_id")
>         REFERENCES "id_conteudo_comentario" ("idcc_id")
>         ON DELETE CASCADE,
>
>     CONSTRAINT "fk_id_conteudo_comentario_voto_fiel"
>         FOREIGN KEY ("idf_id")
>         REFERENCES "id_fiel" ("idf_id")
>         ON DELETE CASCADE
> );
> ```
> Sem o SQL, os comentários continuam como antes (sem os botões).
>
> **Testar:** rodar o SQL; com a conta A, comentar num vídeo (botões
> desabilitados no próprio comentário); com a conta B, votar Edificante
> (1, verde), trocar para Fora de contexto (Edificante 0, Fora 1, âmbar),
> clicar de novo (desfaz, 0 e 0); recarregar a página (voto mantido);
> "Ver mais comentários" (os carregados depois também têm votos);
> excluir o comentário com a conta A e tentar votar com a B na aba antiga
> (mensagem de erro); temas claro e escuro (verde e âmbar legíveis);
> celular (botões quebram linha sem estourar).

### T17 — Ordenar e destacar comentários (4) · M

- Filtros: **Mais relevantes**, **Mais recentes**, **Líderes** e **Com
  versículo**.
- O comentário mais votado aparece no topo com o selo **"Destaque"**.

> **Entregue — ✅ validado no Render (26/10/2026)** (`919d0fb`). Abas acima dos
> comentários do vídeo (rolam de lado no celular):
>
> | Aba | O que mostra |
> |---|---|
> | **Mais relevantes** (padrão) | Todos, pelo saldo Edificante − Fora de contexto; empate, o mais novo primeiro |
> | **Mais recentes** | Todos, do mais novo para o mais antigo (como era antes) |
> | **Líderes** | Só de quem é da equipe (admin) ou tem função de liderança (Presbítero, Líder, Missionário, Evangelista, Pastor — `idfn_lider = 1` na tela "Funções na Igreja") |
> | **Com versículo** | Só os que citam uma referência bíblica no texto: livro (nome ou abreviação, com ou sem acento) + capítulo:versículo — "Jo 3:16", "1 Co 13.4", "Salmos 23:1". "Salmo 23" sem versículo, horários ("às 10:30") e minutos ("8:42") não contam. Quando a T21 criar o campo de versículo, ele entra neste filtro |
>
> - **Destaque:** em cada aba, o comentário de maior saldo (mínimo +1)
>   vai para o topo com borda azul e o selo "★ Destaque". Se ele é antigo
>   e ainda não foi carregado (ex.: Mais recentes), aparece mesmo assim, e
>   não se repete quando a página dele chega.
> - Selo **"Líder"** ao lado do nome de quem tem função de liderança (a
>   equipe continua com "Equipe") — adianta parte da T18.
> - "Ver mais comentários" continua em todas as abas, com a contagem da
>   aba. Comentário novo entra na aba atual só se passar no filtro dela; o
>   total do título conta todos.
> - Os votos não reordenam a lista na hora — a nova ordem vale ao trocar
>   de aba ou recarregar.
> - Sem SQL novo. Sem a tabela de votos da T16, a aba "Mais relevantes" e
>   o destaque não aparecem (abre em "Mais recentes").
>
> **Testar:** num vídeo com vários comentários, votar Edificante em um
> antigo com 2 contas → recarregar: ele aparece em primeiro com
> "★ Destaque" em Mais relevantes e no topo de Mais recentes, sem
> repetir ao clicar em "Ver mais"; dar Fora de contexto até o saldo
> ficar 0 → recarregar: some o destaque. Comentar "Leia Jo 3:16" → aba
> Com versículo mostra; "às 10:30" não. Dar a função Pastor(a) a uma
> conta no admin → os comentários dela aparecem em Líderes com o selo
> "Líder". Comentar estando na aba Líderes com uma conta sem função (não
> aparece na aba, mas o total do título sobe). Excluir o comentário em
> destaque. Trocar de aba rápido várias vezes (não mistura listas). Temas
> claro e escuro (aba ativa, selo e borda do destaque) e celular (abas
> rolam de lado).

### T18 — Respostas aos comentários (4) · M

- Botão "Responder (X)" abre as respostas abaixo do comentário.
- Pastores e líderes aparecem com o selo **"Líder"** (usa a função da
  T12).

> **Entregue — ✅ validado no Render (26/10/2026)** (`20d11f0`).
>
> - Cada comentário ganhou **"↩ Responder (X)"** ao lado dos votos.
>   Clicar abre a conversa logo abaixo: as respostas (das mais antigas
>   para as mais novas, com linha à esquerda) e o campo para responder.
>   Sem respostas ainda, já abre com o cursor no campo. Clicar de novo
>   (ou em Cancelar) fecha.
> - Numa resposta, **"Responder"** abre o campo do comentário principal já
>   com **"@Nome "** — fica tudo numa conversa só (um nível de respostas).
> - Respostas têm votos, selo "Equipe"/"Líder" e exclusão (autor ou
>   admin), igual aos comentários.
> - Selo **"Líder"**: já entrou na T17 — quem tem função de liderança
>   (`idfn_lider = 1`) ou é da equipe.
> - Respostas **não** entram nas abas, no destaque nem no total do
>   título/"Ver mais" (contam só os comentários principais). O bloqueio de
>   chat e o limite de 1 envio a cada 10 s valem também para responder.
> - "Ver mais comentários" e comentar no topo mantêm aberta a conversa
>   que estava aberta, com o texto que estava sendo digitado. Trocar de
>   aba recarrega a lista e fecha as conversas.
> - Não avisa o autor do comentário quando alguém responde (dá para
>   fazer depois com as notificações do app).
>
> **SQL** (seção 53 do `bancosupabase.md`):
> ```sql
> ALTER TABLE "id_conteudo_comentario" ADD COLUMN IF NOT EXISTS "idcc_pai_id" BIGINT;
> DO $$ BEGIN
>     ALTER TABLE "id_conteudo_comentario" ADD CONSTRAINT "fk_id_conteudo_comentario_pai"
>         FOREIGN KEY ("idcc_pai_id") REFERENCES "id_conteudo_comentario" ("idcc_id") ON DELETE CASCADE;
> EXCEPTION WHEN duplicate_object THEN NULL;
> END $$;
> CREATE INDEX IF NOT EXISTS "ix_id_conteudo_comentario_pai" ON "id_conteudo_comentario" ("idcc_pai_id", "idcc_oculto");
> ```
> Sem o SQL, os comentários continuam como antes (sem "Responder").
>
> **Testar:** rodar o SQL; abrir "Responder (0)" (abre com o cursor no
> campo), responder → "Responder (1)" e a resposta aparece; com outra
> conta (de função Pastor), responder a essa resposta ("@Nome" já vem no
> campo; aparece com o selo "Líder" na mesma conversa); recarregar e abrir
> de novo (carrega as respostas); votar numa resposta; excluir uma
> resposta (o número baixa); conferir que o total do título e as abas não
> contam respostas; responder 2 vezes seguidas em menos de 10 s (aviso);
> excluir o comentário principal (some com as respostas); temas claro e
> escuro e celular (recuo da conversa sem estourar a largura).

### T19 — Nota do pastor fixada (4) · P

- Pastor/admin escreve uma nota que fica fixada no topo dos comentários
  da aula ("Nota do pastor — fixado").
- Só uma nota fixada por aula.

> **Entregue — ✅ validado no Render (26/10/2026)** (`9cc011b`). A nota é um comentário
> normal que é **fixado**:
>
> - O pastor (ou o admin) comenta na aula e clica no **📌** ao lado do
>   nome. O comentário vai para o topo, acima de tudo e em todas as abas,
>   com borda roxa e o rótulo **"📌 Nota do pastor · Fixado"**. Clicar no
>   📌 de novo desfixa.
> - **Quem fixa:** o admin, em comentário de qualquer líder/equipe; o
>   próprio líder (função com "liderança" marcada em "Funções na Igreja":
>   Pastor, Presbítero, Líder, Missionário, Evangelista), no comentário
>   dele. Fiel comum não vê o botão; respostas não podem ser fixadas.
> - **Uma por aula:** fixar outra pede confirmação e troca a anterior
>   (que volta para a lista normal).
> - A nota recebe votos e respostas como os outros comentários. Fica fora
>   das abas, do destaque e do total da aba, mas conta no total do título.
> - Excluir a nota tira ela do topo.
>
> **SQL** (seção 54 do `bancosupabase.md`; precisa do SQL da T18 antes):
> ```sql
> ALTER TABLE "id_conteudo_comentario" ADD COLUMN IF NOT EXISTS "idcc_fixado_em" TIMESTAMPTZ;
> CREATE UNIQUE INDEX IF NOT EXISTS "ux_id_conteudo_comentario_fixado"
>     ON "id_conteudo_comentario" ("idct_id")
>     WHERE "idcc_fixado_em" IS NOT NULL AND "idcc_oculto" = 0;
> ```
> Sem o SQL, os comentários continuam como antes (sem o 📌).
>
> **Testar:** rodar o SQL; com uma conta de função Pastor(a), comentar e
> fixar (vai para o topo com o rótulo; o total do título não muda); trocar
> de aba (a nota continua no topo); com o admin, fixar um comentário de
> outro líder (pede confirmação e troca); conferir que o 📌 não aparece
> em comentário de fiel comum nem em respostas; desfixar; excluir a nota
> fixada; responder e votar na nota; temas claro e escuro (borda e rótulo
> roxos legíveis) e celular (📌 sempre visível, sem precisar de hover).

### T20 — Compartilhar comentário (4) · P

- Botão "Compartilhar" que gera um link direto para o comentário na aula.
- No celular, usar o compartilhamento nativo (`navigator.share`).

> **Entregue — ✅ validado no Render (26/10/2026)** (`e729af7`). Compartilhar é para
> **fora do app** (WhatsApp, Instagram, Telegram…), não para o chat ao
> vivo — decidido com o usuário.
>
> - Botão **"Compartilhar"** em todo comentário e resposta (ao lado de
>   "Responder"; no celular estreito, só o ícone).
> - **Celular:** abre o compartilhar do aparelho com o texto
>   *Ana comentou na aula "Título": "…trecho…"* e o link.
>   **Computador** (ou navegador sem esse recurso): copia o texto + link e
>   o botão mostra "Link copiado!".
> - **O link** (`assistir_video.php?id=AULA&comentario=ID`) abre a aula
>   rolada até o comentário, que pisca em azul por alguns segundos. Se for
>   uma resposta, a conversa abre sozinha e rola até ela. Se o comentário
>   estiver longe (fora da primeira página), ele entra no topo da lista.
>   Comentário excluído ou de outra aula: abre só a aula.
> - **Quem não está logado** cai no login e, depois de entrar, volta
>   direto para o comentário (vale para qualquer página da área do fiel,
>   e também quando a sessão expira). Só aceita voltar para páginas do
>   próprio app — nada de link externo.
> - Sem SQL novo.
>
> **Testar:** no celular, compartilhar um comentário pelo WhatsApp e abrir
> o link (vai até ele, piscando); no computador, clicar em Compartilhar
> ("Link copiado!") e colar numa aba anônima → login → volta para o
> comentário; link de uma resposta (abre a conversa e rola até ela); link
> de um comentário antigo (aparece no topo, sem repetir no "Ver mais");
> link de comentário excluído (abre só a aula); link da nota fixada e do
> destaque; login normal sem link continua indo para o dashboard; temas
> claro e escuro.

### T21 — Versículo e momento da aula no comentário (4) · M

- Botão **"Momento da aula"**: grava o minuto atual do vídeo no
  comentário ("no minuto 8:42"). Clicar no minuto leva o vídeo até ele.
- Botão **"Versículo"**: o usuário digita a referência (ex.: Nm 12:3) e
  ela aparece destacada no comentário. Sem a Bíblia integrada, é só texto;
  depois da T36, pode mostrar o versículo completo.

> **Entregue — ✅ validado no Render (26/10/2026)** (`2ad2317`).
>
> - Ao abrir o campo de comentar, aparecem dois botões:
>   - **⏱ Momento da aula** — marca o minuto em que o vídeo está
>     ("no minuto 8:42"); clicar de novo atualiza para o minuto atual.
>   - **📖 Versículo** — abre um campinho para digitar a referência
>     (ex.: *Nm 12:3*, *Jo 3:16-18*, *1 Co 13:4*, *Sl 23:1,4*).
>   Os dois aparecem como etiquetas acima dos botões, com ✕ para tirar.
> - No comentário publicado, logo abaixo do texto: **"⏱ no minuto 8:42"**
>   (azul — clicar leva o vídeo até esse minuto, dá play e rola até o
>   player) e **"📖 Nm 12:3"** (verde, destacado).
> - O versículo é **conferido no servidor**: precisa ser livro + capítulo
>   :versículo (nome ou abreviação, com ou sem acento). Se não for, o
>   comentário não sai e aparece "Versículo não reconhecido…".
> - O filtro **"Com versículo"** (T17) agora também pega os comentários
>   com versículo citado pelo botão, além dos que têm a referência no
>   texto.
> - Por enquanto só no comentário principal (não nas respostas).
>
> **SQL** (seção 55 do `bancosupabase.md`):
> ```sql
> ALTER TABLE "id_conteudo_comentario" ADD COLUMN IF NOT EXISTS "idcc_momento" INTEGER;
> ALTER TABLE "id_conteudo_comentario" ADD COLUMN IF NOT EXISTS "idcc_versiculo" VARCHAR(40);
> ```
> Sem o SQL, o campo de comentar continua como antes (sem os botões).
>
> **Testar:** rodar o SQL; tocar o vídeo até ~1 min, abrir o campo,
> "Momento da aula" (etiqueta com o minuto), escrever e comentar; clicar
> no minuto do comentário (o vídeo vai até lá); "Versículo" com "Nm 12:3"
> (sai verde no comentário e aparece na aba Com versículo); versículo
> inválido ("Jo 3", "abc") → aviso e o comentário não sai; tirar as
> etiquetas pelo ✕; Cancelar limpa tudo; temas claro e escuro; celular
> (botões quebram linha).

### T22 — Vínculo com a igreja (3) · M

- **Igreja vinculada:** busca por nome ou código, em vez de texto livre.
  Hoje a filial é texto livre — migrar para uma lista de igrejas/filiais.
- **Célula/grupo:** escolhido de uma lista (usado depois em rankings e
  desafios).
- **Tempo de fé:** Novo convertido, Consolidado, etc.

> **⏸ Aguardando o cliente.** A ideia é que cada igreja/filial tenha o
> **próprio site** (lives, fiéis etc.), o que muda a parte de "igreja
> vinculada". Situação atual, proposta e perguntas:
> [pontos_para_alinhar.md](../para_o_cliente/pontos_para_alinhar.md)
> (seções 1 e 3).
>
> **1ª parte entregue — ✅ validado no Render (26/10/2026): a base das filiais** (`ee78105`, 22/10/2026).
> O modelo (um app para todas ou um site por filial) ainda será decidido;
> esta parte serve aos dois.
>
> - **Cadastro:** a filial deixou de ser texto livre e virou uma
>   **lista**. Até o cliente mandar as filiais, a lista são os **27
>   estados**. No fim vem **"Nenhuma filial (não frequento a igreja)"**,
>   para quem é de fora e gostou da plataforma.
> - **Perfil:** cartão **"Sua filial"**, para ver e trocar. Quem tem
>   filial em texto livre (conta antiga) vê o que escreveu e é convidado a
>   escolher na lista. A troca usa a permissão "vínculo de membro" dos
>   Tipos de fiel.
> - **Admin → Filiais** (menu novo, tela `filiais` nos grupos): a lista
>   com nome, código opcional (ex.: MAN-CPS), UF, cidade, ordem e ativa
>   (desativada some da escolha, mas quem tem continua). Embaixo, a filial
>   de cada fiel, com filtros "não informada", "Nenhuma filial" e por
>   filial.
> - **Quando a lista real chegar:** cadastrar as filiais, desativar os
>   estados e ajustar quem escolheu um estado (filtro por estado em
>   Admin → Filiais).
> - Ainda **não** separa cursos, lives nem admins por filial: isso depende
>   da decisão do modelo.
> - Sem a seção 76, o cadastro continua com o texto livre de antes.
>
> **SQL:** seção **76** do `database/bancosupabase.md`.
>
> **Testar:** rodar a seção 76; cadastro: a lista mostra os estados e
> "Nenhuma filial", e não deixa enviar sem escolher; cadastrar uma conta
> com São Paulo e outra com "Nenhuma filial"; aprovar as duas (a filial
> aparece em Aprovar Usuários). Perfil: o cartão "Sua filial" mostra a
> escolha; trocar para Rio de Janeiro; numa conta antiga (texto livre),
> aparece "No cadastro você escreveu…" com a lista aberta; num tipo de
> fiel sem "vínculo de membro", só aparece o texto. Admin → Filiais:
> criar "Manancial Campinas" (código MAN-CPS, SP), tentar nome e código
> repetidos (recusa), desativar Rio de Janeiro (some do cadastro, mas
> quem tem continua vendo), renomear uma filial (o nome muda para os
> fiéis dela), excluir uma sem fiéis (com fiéis não aparece o botão),
> filtrar "não informada" e trocar a filial de um fiel; temas claro e
> escuro e celular.

### T23 — Preferências de conteúdo (3) · M

- **Temas de interesse** (Família, Oração, Liderança…): usar nas filas
  "Recomendados para você".
- **Tempo disponível por dia:** ajusta sugestões (aulas curtas ou longas).
- **Horário da notificação do devocional:** usado pelas notificações push
  (OneSignal) e depois pelo WhatsApp (T38).

> **Entregue — ✅ validado no Render (26/10/2026)** (`aa73e2c`). Cartão **"Preferências de
> conteúdo"** no perfil, logo abaixo do "Vínculo com a igreja" (abre já em
> edição para quem nunca preencheu):
>
> - **Temas de interesse:** chips com as **categorias dos cursos** (as
>   mesmas das filas por tema da T09, mantidas pelo admin), até 8.
> - **Tempo por dia:** Até 10 min · Cerca de 20 · Cerca de 30 · 1 hora ou
>   mais.
> - **Lembrete diário:** "Não quero lembrete" ou um horário das 5h às 23h,
>   de meia em meia hora.
>
> **Onde isso é usado:**
> - **"Recomendados para Você"** (home): passa a vir dos temas escolhidos
>   (antes: das categorias dos cursos em que a pessoa está matriculada,
>   que continua valendo para quem não escolheu temas). Com o tempo por
>   dia, as aulas que **cabem nesse tempo** vêm primeiro, depois as de
>   duração ainda desconhecida (a duração é gravada quando alguém assiste)
>   e por último as mais longas.
> - **Filas por tema da home:** os temas escolhidos aparecem **primeiro**.
> - **Perfil X% completo (T13):** "Temas de interesse" entra como campo
>   opcional, com link no "Falta:" que abre a edição.
> - **Lembrete diário (push):** no horário escolhido (Brasília), "Seu
>   momento com Deus 🙏 — Continue de onde parou: *curso*" (abre o curso);
>   sem curso em andamento, um convite para as recomendações. Uma vez por
>   dia, só com push ligado em Configurações e conta aprovada. Mudar o
>   horário libera o lembrete do dia de novo. Quando houver devocional
>   (T33/T38), o lembrete passa a levar o devocional.
>
> **⚠️ O lembrete precisa de um agendador** — o Render (plano web) não roda
> tarefa agendada. Até configurar, a escolha do horário fica salva mas
> nada é enviado:
> 1. No Render, criar a variável **`LEMBRETE_CRON_CHAVE`** com uma
>    sequência longa e aleatória.
> 2. Num agendador, chamar a cada **15 minutos**
>    `https://<dominio>/lembrete_diario.php` com o cabeçalho
>    `X-Cron-Chave: <a chave>` (desde out/2026 só pelo cabeçalho; `?chave=` na URL não vale mais).
>    Opções: **cron-job.org** (gratuito) ou um **Cron Job do Render**
>    (pago). A resposta mostra quantos lembretes saíram.
>
> **SQL** (seção 56 do `bancosupabase.md`):
> ```sql
> ALTER TABLE "id_preferencia" ADD COLUMN IF NOT EXISTS "idpref_tempo_diario" SMALLINT;
> ALTER TABLE "id_preferencia" ADD COLUMN IF NOT EXISTS "idpref_lembrete_hora" TIME;
> ALTER TABLE "id_preferencia" ADD COLUMN IF NOT EXISTS "idpref_lembrete_enviado_em" DATE;
> CREATE INDEX IF NOT EXISTS "ix_id_preferencia_lembrete" ON "id_preferencia" ("idpref_lembrete_hora") WHERE "idpref_lembrete_hora" IS NOT NULL;
>
> CREATE TABLE IF NOT EXISTS "id_fiel_tema" (
>     "idf_id" BIGINT NOT NULL,
>     "idcat_id" BIGINT NOT NULL,
>     "idft_criado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     PRIMARY KEY ("idf_id", "idcat_id"),
>     CONSTRAINT "fk_id_fiel_tema_fiel" FOREIGN KEY ("idf_id") REFERENCES "id_fiel" ("idf_id") ON DELETE CASCADE,
>     CONSTRAINT "fk_id_fiel_tema_categoria" FOREIGN KEY ("idcat_id") REFERENCES "id_categoria" ("idcat_id") ON DELETE CASCADE
> );
> ```
> Sem o SQL, o perfil e a home continuam como antes (sem o cartão).
>
> **Testar:** rodar o SQL; abrir o perfil (cartão aberto, "Temas de
> interesse" no "Falta:" — clicar abre a edição); marcar 2 temas, "Cerca
> de 20 min" e lembrete → Salvar (resumo no cartão; % do perfil sobe);
> marcar 8 temas (os outros ficam desabilitados); home: filas dos temas
> escolhidos no topo e "Recomendados" desses temas; desmarcar tudo e
> salvar (volta ao comportamento antigo); lembrete: com o agendador
> configurado, escolher o próximo horário (meia hora à frente), aguardar
> o push no celular com notificações ligadas; chamar
> `lembrete_diario.php` sem chave (401) e de novo no mesmo dia (não
> repete); temas claro e escuro (chips marcados legíveis) e celular.

### T24 — Selos no perfil (3) · M

- No topo do perfil e ao lado do nome nos comentários: igreja, célula e
  títulos como "Discípula fiel".
- Definir com o cliente a regra de cada título.

> **1ª parte entregue — ✅ validado no Render (26/10/2026)** (`0185727`). Selos montados com
> o que o app já tem. **Igreja, célula (T22) e títulos ficam para a 2ª
> parte** — perguntas em
> [pontos_para_alinhar.md](../para_o_cliente/pontos_para_alinhar.md),
> seção 4.
>
> - **Topo do perfil:** uma fileira de selos no lugar da linha "Membro
>   há…": **função na igreja** (roxo; "Membro" não vira selo, por ser o
>   padrão), **"Membro há X anos"** (azul) e a **maior medalha** conquistada
>   (na cor do nível — bronze, prata, ouro, diamante; o nome do nível
>   aparece ao passar o mouse).
> - **Comentários:** a etiqueta ao lado do nome mostra a **função de
>   verdade** ("Pastor(a)", "Presbítero"…) em vez do "Líder" genérico da
>   T17/T18. Liderança e equipe ficam em roxo; funções sem liderança
>   (Obreiro, Diácono) em cinza. Sem função: "Equipe" para admin, como
>   antes. A **maior medalha** do autor aparece como ícone na cor do nível
>   (o nome aparece ao passar o mouse).
> - "Maior medalha" = nível mais alto e, no mesmo nível, a de regra maior
>   (ex.: Centenário antes de Fiel de um mês).
> - A etiqueta roxa ganhou cor própria no tema claro (antes ficava com
>   pouco contraste no fundo branco) — vale também para as etiquetas
>   "Equipe" da presença online, da ofensiva e do engajamento.
> - Sem SQL novo.
>
> **Testar:** conta com função Pastor(a), membro há anos e medalhas →
> topo do perfil com os 3 selos; conta com função Membro e sem medalhas
> (só o selo de membresia, ou nenhum); comentar com cada uma e conferir a
> etiqueta e o ícone da medalha ao lado do nome (passar o mouse mostra o
> nome); um Diácono (etiqueta cinza); aba "Líderes" continua igual; temas
> claro e escuro (cores dos níveis legíveis nos dois) e celular (selos
> quebram linha).

### T25 — Chromecast e AirPlay (1.8) · M

- **AirPlay:** o Safari já oferece no `<video>`; garantir que o botão
  aparece no iPhone/Mac.
- **Chromecast:** botão de transmitir com o Google Cast SDK.
- **Atenção:** testar com o vídeo hospedado onde está hoje (a URL precisa
  ser acessível pela TV).

> **Entregue — ✅ validado no Render (26/10/2026)** (`106ab66`). Decidido com o usuário:
> só o recurso padrão do navegador (**Remote Playback**), sem o Google
> Cast SDK. O SDK (botão próprio no computador) ficou como opcional para o
> cliente decidir:
> [pontos_para_alinhar.md](../para_o_cliente/pontos_para_alinhar.md),
> seção 5.
>
> - Botão **"📺 Transmitir"** na barra do player, antes do de velocidade.
>   **Só aparece quando há uma TV disponível** na rede: no Safari abre o
>   **AirPlay**, no Chrome do Android (e no app instalado) o
>   **Chromecast**. Safari antigo sem Remote Playback usa a API própria
>   do AirPlay.
> - Estados: "Conectando…" e **"Transmitindo"** (botão azul). Tocar de
>   novo abre a janela do navegador para parar ou trocar de TV.
>   Transmitindo, o botão não some mesmo se a TV deixar de ser anunciada.
> - O `<video>` da página continua "dono" da reprodução: os controles do
>   player comandam a TV, e o **progresso (T03/T04) e a próxima aula
>   (T05) seguem funcionando** sem mudança.
> - **Computador:** sem botão (o navegador não informa se há TV); o
>   Chrome transmite pelo menu ⋮ → "Transmitir…".
> - Vídeo **OGG**: o botão não aparece (Chromecast e Apple TV não tocam).
>   No admin, os campos de envio de vídeo ganharam a dica "Prefira MP4".
> - **Limites:** iPhone só tem AirPlay (a Apple não permite Chromecast no
>   navegador); Firefox não transmite; ao ir para a **próxima aula**
>   (página nova), a transmissão termina e é preciso tocar em
>   "Transmitir" de novo; a TV pode ignorar a velocidade escolhida (T01).
> - A live é um player do YouTube, que já tem o botão de transmitir dele.
> - Sem SQL. Validado por simulação (7 cenários: sem TV, TV aparece,
>   clicar, conectando/conectado/desconectado, computador, OGG, navegador
>   sem recurso).
>
> **Testar (precisa de TV):** iPhone + Apple TV (ou um Mac recente, que
> recebe AirPlay) na mesma rede: o botão aparece, transmitir, pausar e
> avançar pelo celular, conferir na lista do curso que o progresso
> andou; Android + Chromecast/Google TV: idem; desligar a TV (o botão
> some); computador (sem botão); temas claro e escuro (botão azul
> "Transmitindo") e celular.

### T26 — Mural de testemunhos (1.6) · G

- Página para o usuário enviar um testemunho (com moderação do admin
  antes de publicar).
- Votos nos testemunhos (reaproveitar a lógica da T16).
- Bloco na home com os testemunhos mais votados.

> **Entregue — ✅ validado no Render (26/10/2026)** (`624779d`).
>
> - **Página "Testemunhos"** (novo item no menu principal, ao lado de
>   Cursos):
>   - **"Contar meu testemunho"**: título (3–80) e texto (30–2000, com
>     contador), e a opção **"Publicar sem o meu nome"** (aparece como
>     "Anônimo"; a equipe e o próprio autor veem o nome). Vai para
>     **moderação**; no máximo 3 esperando por pessoa. Quem está
>     bloqueado no chat não envia.
>   - **"Meus testemunhos"**: status (esperando aprovação, publicado com
>     os votos, não publicado com o **motivo**) e remover.
>   - **Mural** com abas **Mais votados** e **Mais recentes**, 12 por vez
>     ("Ver mais testemunhos"). Texto longo recolhido com "Ler mais".
> - **Voto "🙏 Edificante"** em cada testemunho publicado: um por pessoa,
>   liga/desliga, não no próprio (mesma regra da T16; um voto só, sem o
>   "Fora de contexto" — quem cuida disso é a moderação).
> - **Home:** bloco **"Testemunhos"** depois das filas, com os 3 mais
>   votados dos últimos 90 dias (sem nenhum no período, os mais votados
>   de sempre), texto resumido e link para o completo. Sem nenhum
>   publicado: convite "Contar meu testemunho".
> - **Admin → Testemunhos** (menu novo, com o número de pendentes em
>   vermelho): abas Pendentes (os mais antigos primeiro), Publicados,
>   Recusados e Removidos. **Aprovar e publicar**, **Recusar** (motivo
>   obrigatório), **Publicar mesmo assim** (recusados) e **Tirar do
>   mural**. O autor recebe **notificação no app + push** ao ser aprovado
>   (link direto para o testemunho, que pisca no mural) ou recusado.
> - Base para as medalhas de "ensino/compartilhamento" (T30), se o cliente
>   quiser contar votos em testemunhos.
>
> **SQL** (seção 57 do `bancosupabase.md`):
> ```sql
> CREATE TABLE IF NOT EXISTS "id_testemunho" (
>     "idts_id" BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
>     "idf_id" BIGINT NOT NULL,
>     "idts_titulo" VARCHAR(80) NOT NULL,
>     "idts_texto" VARCHAR(2000) NOT NULL,
>     "idts_anonimo" SMALLINT NOT NULL DEFAULT 0,
>     "idts_status" VARCHAR(10) NOT NULL DEFAULT 'pendente',
>     "idts_motivo" VARCHAR(255),
>     "idts_moderado_por" BIGINT,
>     "idts_moderado_em" TIMESTAMPTZ,
>     "idts_criado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     CONSTRAINT "ck_id_testemunho_status" CHECK ("idts_status" IN ('pendente', 'aprovado', 'recusado', 'removido')),
>     CONSTRAINT "fk_id_testemunho_fiel" FOREIGN KEY ("idf_id") REFERENCES "id_fiel" ("idf_id") ON DELETE CASCADE
> );
> CREATE INDEX IF NOT EXISTS "ix_id_testemunho_status" ON "id_testemunho" ("idts_status", "idts_moderado_em");
> CREATE INDEX IF NOT EXISTS "ix_id_testemunho_fiel" ON "id_testemunho" ("idf_id");
>
> CREATE TABLE IF NOT EXISTS "id_testemunho_voto" (
>     "idts_id" BIGINT NOT NULL,
>     "idf_id" BIGINT NOT NULL,
>     "idtv_criado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     PRIMARY KEY ("idts_id", "idf_id"),
>     CONSTRAINT "fk_id_testemunho_voto_testemunho" FOREIGN KEY ("idts_id") REFERENCES "id_testemunho" ("idts_id") ON DELETE CASCADE,
>     CONSTRAINT "fk_id_testemunho_voto_fiel" FOREIGN KEY ("idf_id") REFERENCES "id_fiel" ("idf_id") ON DELETE CASCADE
> );
> ```
> Sem o SQL, a página mostra "ainda não está disponível", a home não
> mostra o bloco e o admin pede para rodar a seção 57.
>
> **Testar:** rodar o SQL; home sem testemunhos (convite); enviar um
> testemunho (aparece em "Meus" como esperando aprovação; o admin vê o
> número no menu); enviar 4 seguidos (o 4º é barrado); no admin, recusar
> um com motivo (o autor recebe o aviso e vê o motivo) e aprovar outro
> (aviso com link; o testemunho pisca no mural); votar com outra conta
> (número sobe; votar de novo desfaz; no próprio, desabilitado);
> anônimo (outra conta vê "Anônimo", o autor e o admin veem o nome);
> home com os mais votados e "Ler o testemunho completo"; texto longo
> ("Ler mais"); "Ver mais testemunhos" com mais de 12; "Tirar do mural"
> e remover o próprio; temas claro e escuro e celular.

---

## Nível 4 — Complexo

### T27 — Aba "Minhas notas" (4) · M

- Aba ao lado de "Comunidade" na aula.
- Anotações pessoais, visíveis só para o próprio usuário, com o minuto da
  aula.

> **Entregue — ✅ validado no Render (26/10/2026)** (`493cd45`).
>
> - **Abas abaixo do vídeo:** **Comunidade (N)** (os comentários, como
>   antes) e **Minhas notas (N)**. A aba escolhida fica lembrada no
>   aparelho; link de comentário compartilhado (T20) abre sempre na
>   Comunidade, e `#notas` no fim do link abre direto as notas. Setas ←/→
>   trocam de aba pelo teclado. Base para a aba "Orações" (T28).
> - **Minhas notas:** aviso "Só você vê suas notas" (nem o admin tem tela
>   para elas). Ao começar a escrever, o **minuto em que o vídeo está**
>   entra sozinho na nota ("no minuto 12:34"); dá para tirar (×) ou trocar
>   por **"Marcar o minuto atual"**. Antes de dar o play, a nota fica sem
>   minuto. **Salvar nota** (ou Ctrl+Enter); até 2000 caracteres (o
>   contador aparece perto do limite) e 100 notas por aula.
> - **Lista na ordem do vídeo** (as sem minuto no fim): tocar no minuto
>   leva o vídeo até o trecho; **editar** no lugar (texto e minuto; Esc
>   cancela; marca "editada") e **apagar** (com confirmação). Nota nova ou
>   editada pisca em verde.
> - Nota escrita e não salva: o navegador pergunta antes de sair da página
>   (inclusive na contagem para a próxima aula).
> - Vale para aula de curso e vídeo avulso; vídeo sem player (URL
>   inválida) aceita nota sem minuto.
>
> **SQL** (seção 58 do `bancosupabase.md`):
> ```sql
> CREATE TABLE IF NOT EXISTS "id_aula_nota" (
>     "idan_id" BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
>     "idf_id" BIGINT NOT NULL,
>     "idct_id" BIGINT NOT NULL,
>     "idan_texto" VARCHAR(2000) NOT NULL,
>     "idan_momento" INTEGER,
>     "idan_criado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     "idan_atualizado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     CONSTRAINT "ck_id_aula_nota_momento" CHECK ("idan_momento" IS NULL OR "idan_momento" >= 0),
>     CONSTRAINT "fk_id_aula_nota_fiel" FOREIGN KEY ("idf_id") REFERENCES "id_fiel" ("idf_id") ON DELETE CASCADE,
>     CONSTRAINT "fk_id_aula_nota_conteudo" FOREIGN KEY ("idct_id") REFERENCES "id_content" ("idct_id") ON DELETE CASCADE
> );
> CREATE INDEX IF NOT EXISTS "ix_id_aula_nota_fiel_conteudo" ON "id_aula_nota" ("idf_id", "idct_id");
> ```
> Sem o SQL, a aba "Minhas notas" aparece com "As notas ainda não estão
> disponíveis" e a Comunidade funciona normal.
>
> **Testar:** antes do SQL, abrir a aba (aviso de indisponível); rodar o
> SQL; aula sem notas ("Você ainda não anotou nada"); dar play, escrever
> (o minuto entra sozinho) e salvar; tirar o minuto (×) e salvar (vai
> para o fim como "Sem minuto"); "Marcar o minuto atual" em outro trecho
> (a lista fica na ordem do vídeo); tocar no minuto de uma nota (o vídeo
> pula para lá); editar (texto e minuto, "editada"; Esc cancela) e
> apagar; contador perto de 2000; escrever e tentar sair sem salvar
> (pergunta); entrar com outra conta (não vê as notas); trocar de aula
> com a aba de notas aberta (continua nela); link de comentário (abre na
> Comunidade); `assistir_video.php?id=N#notas`; temas claro e escuro e
> celular (abas, botões de editar/apagar sempre visíveis no toque).

### T28 — Aba "Orações" com "amém" (4) · G

- Aba ao lado de "Comunidade": pedidos de oração da comunidade.
- Botão **"Amém"** (conta como intercessão).
- Autor pode marcar o pedido como **atendido**.

> **Entregue — ✅ validado no Render (26/10/2026)** (`e89ed22`).
>
> - **Aba "Orações (N)"** entre "Comunidade" e "Minhas notas" (N = pedidos
>   abertos). É um **mural único da igreja**: o mesmo em todas as aulas
>   (a aula só fica guardada para o link das notificações). `#oracoes` no
>   fim do link abre direto a aba.
> - **Pedir oração:** texto de 10 a 1000 caracteres e a opção **"Pedir
>   sem o meu nome"** (os outros veem "Anônimo"; o autor vê a etiqueta
>   "Anônimo" no próprio pedido e o admin vê o nome). **Publica na hora**,
>   sem moderação prévia; no máximo 3 pedidos em 24 horas por pessoa;
>   quem está bloqueado no chat não pede.
> - **"🙏 Amém"** = "orei por você": um por pessoa, liga/desliga ("Orei"),
>   não no próprio pedido e só em pedido aberto. Mostra "N pessoas
>   oraram" ("N pessoas oraram por você" para o autor).
> - **Aviso ao autor** (no app + push) agrupado: com 1, 3, 10, 25, 50 e
>   100 améns e depois a cada 100 ("3 pessoas já oraram pelo seu pedido
>   🙏"), com link que abre a aula na aba Orações e destaca o pedido.
>   Desfazer e refazer o amém não repete o aviso.
> - **Marcar como atendida** (só o autor): campo opcional "como Deus
>   respondeu"; o pedido ganha o selo verde **"Oração atendida"** com o
>   relato e sai da lista de abertos. **Quem orou recebe um aviso no
>   app** ("Uma oração que você fez foi atendida! 🙌"), sem push, porque
>   podem ser muitas pessoas de uma vez. No pedido atendido, o autor vê o
>   link **"Contar como testemunho"** (formulário da T26).
> - **Filtros:** Pedidos (abertos, mais novos primeiro), Atendidas e Meus
>   pedidos; 10 por vez com "Ver mais pedidos".
> - **Remover:** o autor remove o próprio pedido e o **admin remove
>   qualquer um** direto na aba (lixeira); o pedido sai do ar e a linha
>   fica no banco.
> - Tela de notificações: ícones de **Oração** (mãos em prece, roxo) e
>   **Testemunho** (T26, que estava com o sino genérico).
> - Pedido escrito e não enviado: o navegador pergunta antes de sair.
> - Base para as medalhas de oração da T30 (améns dados e orações
>   atendidas).
>
> **SQL** (seção 59 do `bancosupabase.md`):
> ```sql
> CREATE TABLE IF NOT EXISTS "id_oracao" (
>     "idor_id" BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
>     "idf_id" BIGINT NOT NULL,
>     "idct_id" BIGINT,
>     "idor_texto" VARCHAR(1000) NOT NULL,
>     "idor_anonimo" SMALLINT NOT NULL DEFAULT 0,
>     "idor_status" VARCHAR(10) NOT NULL DEFAULT 'aberto',
>     "idor_resposta" VARCHAR(500),
>     "idor_atendido_em" TIMESTAMPTZ,
>     "idor_amens_avisados" INTEGER NOT NULL DEFAULT 0,
>     "idor_removido_por" BIGINT,
>     "idor_removido_em" TIMESTAMPTZ,
>     "idor_criado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     CONSTRAINT "ck_id_oracao_status" CHECK ("idor_status" IN ('aberto', 'atendido', 'removido')),
>     CONSTRAINT "fk_id_oracao_fiel" FOREIGN KEY ("idf_id") REFERENCES "id_fiel" ("idf_id") ON DELETE CASCADE,
>     CONSTRAINT "fk_id_oracao_conteudo" FOREIGN KEY ("idct_id") REFERENCES "id_content" ("idct_id") ON DELETE SET NULL
> );
> CREATE INDEX IF NOT EXISTS "ix_id_oracao_status" ON "id_oracao" ("idor_status", "idor_id");
> CREATE INDEX IF NOT EXISTS "ix_id_oracao_fiel" ON "id_oracao" ("idf_id", "idor_criado_em");
>
> CREATE TABLE IF NOT EXISTS "id_oracao_amem" (
>     "idor_id" BIGINT NOT NULL,
>     "idf_id" BIGINT NOT NULL,
>     "idoa_criado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     PRIMARY KEY ("idor_id", "idf_id"),
>     CONSTRAINT "fk_id_oracao_amem_oracao" FOREIGN KEY ("idor_id") REFERENCES "id_oracao" ("idor_id") ON DELETE CASCADE,
>     CONSTRAINT "fk_id_oracao_amem_fiel" FOREIGN KEY ("idf_id") REFERENCES "id_fiel" ("idf_id") ON DELETE CASCADE
> );
> ```
> Sem o SQL, a aba "Orações" aparece com "ainda não estão disponíveis" e
> o resto da aula funciona normal.
>
> **Testar:** antes do SQL, abrir a aba (aviso); rodar o SQL; mural vazio
> (convite); pedir oração com texto curto (erro) e normal (aparece no
> topo, destacado, e o número da aba sobe); pedir anônimo (outra conta vê
> "Anônimo" e "?" no avatar; o admin vê o nome); 4º pedido em 24 h
> (barrado); com outra conta, "Amém" (vira "Orei" e o número sobe; tocar
> de novo desfaz; no próprio pedido não aparece o botão); o autor recebe
> "Alguém orou pelo seu pedido" e, com 3 améns, o segundo aviso — tocar
> no aviso abre a aula na aba Orações com o pedido piscando; desfazer e
> refazer o amém (sem aviso repetido); marcar como atendida com e sem
> relato (selo verde, sai dos abertos, aparece em "Atendidas"; quem orou
> recebe o aviso no app; "Contar como testemunho" abre o formulário);
> filtros e "Ver mais pedidos" com mais de 10; remover o próprio e, como
> admin, o de outra pessoa; abrir outra aula (mesmo mural); ícones na
> tela de notificações; temas claro e escuro (botão "Amém" roxo, selo
> verde) e celular.
- Moderação pelo admin.

### T29 — Resumos publicados (3.3) · G

Necessário para as medalhas da categoria "Compartilhamento e resumos".

- O usuário escreve um resumo da aula e publica para a comunidade.
- Resumos recebem votos (T16).

> **Entregue — ✅ validado no Render (26/10/2026)** (`310e139`).
>
> - **Aba "Resumos (N)"** logo depois de "Comunidade" (N = resumos
>   publicados na aula). `#resumos` no fim do link abre direto a aba.
> - **"Escrever meu resumo":** de 100 a 3000 caracteres (o contador mostra
>   quanto falta para o mínimo e depois "N / 3000"). **"Trazer minhas
>   notas"** (aparece se a pessoa tem notas da T27 na aula) puxa as notas,
>   com o minuto, para o texto — para reescrever com calma. **Publica na
>   hora**, como os comentários; um resumo por pessoa por aula (o banco
>   também garante); quem está bloqueado no chat não publica.
> - **O seu resumo fica no topo** ("Seu resumo", com destaque azul), com
>   **editar** (os votos ficam; marca "editado") e **remover** (pode
>   escrever outro depois).
> - **Voto "Edificante"** (mesma regra da T16): um por pessoa, liga/desliga,
>   não no próprio. Ordem **Mais votados** / **Mais recentes**, 10 por vez
>   com "Ver mais resumos". Resumo longo vem recolhido com "Ler mais".
> - **Aviso ao autor** (no app + push) agrupado: com 1, 5, 10, 25, 50, 100,
>   250 e 500 votos e depois a cada 500 ("10 pessoas acharam seu resumo
>   edificante 🙌"), com link que abre a aula na aba Resumos e destaca o
>   resumo. Desfazer e refazer o voto não repete o aviso.
> - **Admin** remove qualquer resumo direto na aba (lixeira).
> - Tela de notificações: ícone de **Resumo** (pena, azul).
> - Resumo escrito ou editado e não salvo: o navegador pergunta antes de
>   sair.
> - Base das medalhas de "Compartilhamento e resumos" da T30 (Escriba — 5
>   resumos; Cronista da fé — 20; Evangelista digital — resumo com 500+
>   votos).
>
> **SQL** (seção 60 do `bancosupabase.md`):
> ```sql
> CREATE TABLE IF NOT EXISTS "id_resumo" (
>     "idrs_id" BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
>     "idf_id" BIGINT NOT NULL,
>     "idct_id" BIGINT NOT NULL,
>     "idrs_texto" VARCHAR(3000) NOT NULL,
>     "idrs_status" VARCHAR(10) NOT NULL DEFAULT 'publicado',
>     "idrs_votos_avisados" INTEGER NOT NULL DEFAULT 0,
>     "idrs_removido_por" BIGINT,
>     "idrs_removido_em" TIMESTAMPTZ,
>     "idrs_criado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     "idrs_atualizado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     CONSTRAINT "ck_id_resumo_status" CHECK ("idrs_status" IN ('publicado', 'removido')),
>     CONSTRAINT "fk_id_resumo_fiel" FOREIGN KEY ("idf_id") REFERENCES "id_fiel" ("idf_id") ON DELETE CASCADE,
>     CONSTRAINT "fk_id_resumo_conteudo" FOREIGN KEY ("idct_id") REFERENCES "id_content" ("idct_id") ON DELETE CASCADE
> );
> CREATE INDEX IF NOT EXISTS "ix_id_resumo_conteudo" ON "id_resumo" ("idct_id", "idrs_status");
> CREATE UNIQUE INDEX IF NOT EXISTS "ux_id_resumo_fiel_conteudo" ON "id_resumo" ("idf_id", "idct_id") WHERE "idrs_status" = 'publicado';
>
> CREATE TABLE IF NOT EXISTS "id_resumo_voto" (
>     "idrs_id" BIGINT NOT NULL,
>     "idf_id" BIGINT NOT NULL,
>     "idrv_criado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     PRIMARY KEY ("idrs_id", "idf_id"),
>     CONSTRAINT "fk_id_resumo_voto_resumo" FOREIGN KEY ("idrs_id") REFERENCES "id_resumo" ("idrs_id") ON DELETE CASCADE,
>     CONSTRAINT "fk_id_resumo_voto_fiel" FOREIGN KEY ("idf_id") REFERENCES "id_fiel" ("idf_id") ON DELETE CASCADE
> );
> ```
> Sem o SQL, a aba "Resumos" aparece com "ainda não estão disponíveis" e
> o resto da aula funciona normal.
>
> **Testar:** antes do SQL, abrir a aba (aviso); rodar o SQL; aula sem
> resumos (convite); "Escrever meu resumo" com menos de 100 caracteres
> (contador e erro); com notas na aula, "Trazer minhas notas"; publicar
> (vai para o topo como "Seu resumo", o botão de escrever some e o número
> da aba sobe); editar ("editado", votos mantidos) e cancelar a edição;
> com outra conta: votar Edificante (número sobe; de novo desfaz; no
> próprio, desabilitado), "Mais votados" × "Mais recentes", "Ver mais
> resumos" com mais de 10, "Ler mais" num resumo longo; o autor recebe
> "Seu resumo edificou alguém" e, com 5 votos, o segundo aviso — tocar
> abre a aula na aba Resumos com o resumo piscando; remover o próprio
> (volta o "Escrever meu resumo") e, como admin, o de outra pessoa;
> escrever e sair sem publicar (pergunta); temas claro e escuro e celular
> (4 abas: rolam de lado).

### T30 — Medalhas de ensino, resumos e oração (3.3) · G

Usa a estrutura da T14. Regras do exemplo do cliente:

| Categoria | Bronze | Prata | Ouro |
|---|---|---|---|
| Ensino e resposta | Voz da paz — 10 respostas úteis | Mestre da Palavra — 50 | Sábio da comunidade — 150 |
| Compartilhamento e resumos | Escriba — 5 resumos | Cronista da fé — 20 resumos | Evangelista digital — resumo com 500+ votos |
| Intercessão e oração | Orador fiel — 50 améns | Intercessor — 200 améns | Coluna de fogo — 500 améns + pedidos atendidos |

- Definir com o cliente o que é uma "resposta útil" (sugestão: resposta
  com X votos "Edificante").

> **Entregue — ✅ validado no Render (26/10/2026)** (`3d21a9c`), **com a nossa proposta de
> regras** — as dúvidas foram para
> [pontos_para_alinhar.md](../para_o_cliente/pontos_para_alinhar.md),
> seção 6. Números, nomes e ícones ficam no banco (muda sem deploy).
>
> - **Regras novas** (mesmo catálogo da T14):
>   - **Ensino e resposta:** "resposta útil" = resposta a comentário (T18)
>     com **3+ votos Edificante** de outras pessoas e mais Edificante que
>     "Fora de contexto" (o 3 é `MEDALHA_RESPOSTA_UTIL_VOTOS` no PHP).
>   - **Compartilhamento e resumos:** resumos publicados (T29) e votos do
>     resumo mais votado.
>   - **Intercessão e oração:** améns dados (T28); a "Coluna de fogo" pede
>     500 améns **e** 10 pedidos pelos quais orou que foram atendidos.
>   - Conteúdo removido deixa de contar; medalha ganha não se perde.
> - **15 medalhas novas:** as 9 do cliente + 6 intermediárias (Primeira
>   palavra, Conselheiro, Primeiro resumo, Resumo que inspira, Mãos que
>   oram, Guerreiro de oração).
> - **Quando sai:** na hora da ação (publicar resumo, amém, voto recebido,
>   pedido marcado como atendido), no primeiro acesso do dia e ao abrir o
>   perfil — vale o histórico (retroativo). Quem recebe o voto é avisado
>   por **notificação + push** ("Nova medalha: Voz da paz 🏅"); quem agiu
>   também vê o aviso dourado na próxima página.
> - **Perfil:** "Minhas medalhas" com as 4 categorias e o progresso da
>   próxima de cada uma ("37 de 50 améns"; na Coluna de fogo também "2 de
>   10 pedidos atendidos"). A **ofensiva** passa a mostrar só as de
>   ofensiva, com link "todas as minhas medalhas"; o aviso dourado leva ao
>   perfil.
> - A maior medalha continua aparecendo ao lado do nome nos comentários
>   (T24), agora podendo ser uma das novas.
> - Tela de notificações: ícone de **Medalha**.
> - Arrumado o título da seção de medalhas no `conexao.php` (estava sem).
>
> **SQL** (seção 61 do `bancosupabase.md`; precisa das seções 51, 59 e 60):
> ```sql
> ALTER TABLE "id_badge" ADD COLUMN IF NOT EXISTS "idbadge_regra_valor2" INTEGER NOT NULL DEFAULT 0;
>
> INSERT INTO "id_badge" ("idbadge_chave", "idbadge_nome", "idbadge_descricao", "idbadge_icone", "idbadge_categoria", "idbadge_nivel", "idbadge_regra", "idbadge_regra_valor", "idbadge_regra_valor2", "idbadge_ordem") VALUES
>     ('ensino_1',         'Primeira palavra',     '1 resposta útil',                                   'fa-comment-dots',        'ensino',  'bronze', 'respostas_uteis',    1,   0,  1),
>     ('ensino_10',        'Voz da paz',           '10 respostas úteis',                                'fa-dove',                'ensino',  'bronze', 'respostas_uteis',    10,  0,  10),
>     ('ensino_25',        'Conselheiro',          '25 respostas úteis',                                'fa-comments',            'ensino',  'prata',  'respostas_uteis',    25,  0,  25),
>     ('ensino_50',        'Mestre da Palavra',    '50 respostas úteis',                                'fa-book-open',           'ensino',  'prata',  'respostas_uteis',    50,  0,  50),
>     ('ensino_150',       'Sábio da comunidade',  '150 respostas úteis',                               'fa-scroll',              'ensino',  'ouro',   'respostas_uteis',    150, 0,  150),
>     ('resumos_1',        'Primeiro resumo',      '1 resumo publicado',                                'fa-pen-nib',             'resumos', 'bronze', 'resumos_publicados', 1,   0,  1),
>     ('resumos_5',        'Escriba',              '5 resumos publicados',                              'fa-feather-pointed',     'resumos', 'bronze', 'resumos_publicados', 5,   0,  5),
>     ('resumos_20',       'Cronista da fé',       '20 resumos publicados',                             'fa-book-bookmark',       'resumos', 'prata',  'resumos_publicados', 20,  0,  20),
>     ('resumo_votos_50',  'Resumo que inspira',   'Um resumo com 50 votos Edificante',                 'fa-lightbulb',           'resumos', 'prata',  'resumo_votos',       50,  0,  30),
>     ('resumo_votos_500', 'Evangelista digital',  'Um resumo com 500 votos Edificante',                'fa-bullhorn',            'resumos', 'ouro',   'resumo_votos',       500, 0,  40),
>     ('amens_10',         'Mãos que oram',        '10 améns em pedidos de oração',                     'fa-hands-praying',       'oracao',  'bronze', 'amens_dados',        10,  0,  10),
>     ('amens_50',         'Orador fiel',          '50 améns em pedidos de oração',                     'fa-hand-holding-heart',  'oracao',  'bronze', 'amens_dados',        50,  0,  50),
>     ('amens_100',        'Guerreiro de oração',  '100 améns em pedidos de oração',                    'fa-shield-heart',        'oracao',  'prata',  'amens_dados',        100, 0,  100),
>     ('amens_200',        'Intercessor',          '200 améns em pedidos de oração',                    'fa-handshake-angle',     'oracao',  'prata',  'amens_dados',        200, 0,  200),
>     ('coluna_de_fogo',   'Coluna de fogo',       '500 améns e 10 pedidos pelos quais orou atendidos', 'fa-fire-flame-curved',   'oracao',  'ouro',   'amens_e_atendidos',  500, 10, 500)
> ON CONFLICT ("idbadge_chave") DO NOTHING;
> ```
> Sem o SQL, nada muda (só as medalhas de ofensiva). Sem as tabelas de
> orações/resumos, as medalhas dessas categorias aparecem bloqueadas, sem
> barra de progresso.
>
> **Testar** (dá para baixar os números no banco para testar rápido, ex.:
> `UPDATE id_badge SET idbadge_regra_valor = 2 WHERE idbadge_chave = 'amens_10'`):
> rodar o SQL; abrir o perfil (4 categorias, progresso da próxima de cada);
> publicar o 1º resumo ("Primeiro resumo": aviso dourado na próxima página
> e notificação); dar améns até a meta; com 3 contas, votar Edificante numa
> **resposta** de outra pessoa (no 3º voto o autor recebe "Nova medalha:
> Primeira palavra"); votar "Fora de contexto" (não conta se empatar);
> resumo com votos até "Resumo que inspira" (com o valor baixado); Coluna
> de fogo com valores baixados (améns + pedidos atendidos; a medalha sai
> quando o autor marca o pedido como atendido); remover um resumo (a
> medalha fica; a contagem baixa); ofensiva só com as de ofensiva e o link
> para o perfil; maior medalha ao lado do nome nos comentários; temas
> claro e escuro e celular. **Lembrar de voltar os números** depois.

### T31 — Desafios (3.3) · G

- Admin cria desafios (ex.: "assistir 5 aulas em 7 dias", "ler 1 capítulo
  por dia").
- Usuário entra no desafio e acompanha o progresso.
- Opcional: ranking por célula (T22).

> **Entregue — ✅ validado no Render (26/10/2026)** (`04da169`). Dúvidas para o cliente em
> [pontos_para_alinhar.md](../para_o_cliente/pontos_para_alinhar.md),
> seção 7 (ranking por célula fica para depois da T22).
>
> - **Admin → Desafios** (menu novo): criar, editar, pausar/reativar e
>   excluir (só sem participantes; com participantes, pausa). Campos:
>   título, descrição, **o que conta** (aulas concluídas, dias no app,
>   comentários, resumos, améns ou **"marcar como feito"**, que a própria
>   pessoa confirma — para "ler 1 capítulo por dia"), **meta** (total no
>   período ou por dia) e **período** (começa quando cada um entra, com
>   duração em dias e datas opcionais de inscrição; ou datas fixas para
>   todos). Prévia ao vivo de como o fiel lê ("Concluir 5 aulas em 7
>   dias") e opção **"Avisar todos os fiéis"** (notificação no app, sem
>   push). Mostra participantes e quantos concluíram.
> - **Fiel → Desafios** (menu novo): "Meus desafios" com barra de
>   progresso, bolinhas por dia nos diários (feito, hoje, não cumprido),
>   prazo ("Faltam 5 dias"), aviso quando já não dá para completar todos
>   os dias, **"Fiz hoje"** nos de marcar como feito e "Sair do desafio";
>   "Desafios abertos" com "Participar" (e "Começa em…" nos agendados);
>   histórico de concluídos e encerrados.
> - **Progresso automático** com os dados do app (aula concluída pelo
>   histórico de visualização, acesso do dia, comentários visíveis,
>   resumos publicados, améns). Conta também o que a pessoa já fez no dia
>   em que entrou. A mesma aula concluída duas vezes conta uma vez.
> - **Conclusão** conferida no primeiro acesso do dia, ao abrir a home ou
>   os desafios e depois de cada ação que conta: notificação + push
>   "Desafio concluído! 🏆".
> - **Home:** bloco "Seus desafios" (até 3 em andamento, com progresso)
>   ou convite quando há desafios abertos.
> - **Medalhas** (categoria nova "Desafios" no perfil): Primeiro desafio
>   (1), Desafiante (5) e Incansável (15).
> - Datas no fuso de São Paulo.
>
> **SQL** (seção 62 do `bancosupabase.md`; as medalhas precisam da seção
> 61):
> ```sql
> CREATE TABLE IF NOT EXISTS "id_desafio" (
>     "idds_id" BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
>     "idds_titulo" VARCHAR(80) NOT NULL,
>     "idds_descricao" VARCHAR(500),
>     "idds_metrica" VARCHAR(20) NOT NULL,
>     "idds_modo" VARCHAR(10) NOT NULL DEFAULT 'total',
>     "idds_meta" INTEGER NOT NULL,
>     "idds_periodo" VARCHAR(10) NOT NULL DEFAULT 'ao_entrar',
>     "idds_duracao_dias" INTEGER NOT NULL,
>     "idds_inicio" DATE,
>     "idds_fim" DATE,
>     "idds_ativo" SMALLINT NOT NULL DEFAULT 1,
>     "idds_criado_por" BIGINT,
>     "idds_criado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     CONSTRAINT "ck_id_desafio_metrica" CHECK ("idds_metrica" IN ('aulas', 'dias_app', 'comentarios', 'resumos', 'amens', 'checkin')),
>     CONSTRAINT "ck_id_desafio_modo" CHECK ("idds_modo" IN ('total', 'diario')),
>     CONSTRAINT "ck_id_desafio_periodo" CHECK ("idds_periodo" IN ('ao_entrar', 'fixo')),
>     CONSTRAINT "ck_id_desafio_numeros" CHECK ("idds_meta" >= 1 AND "idds_duracao_dias" >= 1)
> );
>
> CREATE TABLE IF NOT EXISTS "id_desafio_participante" (
>     "iddp_id" BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
>     "idds_id" BIGINT NOT NULL,
>     "idf_id" BIGINT NOT NULL,
>     "iddp_inicio" DATE NOT NULL,
>     "iddp_fim" DATE NOT NULL,
>     "iddp_concluido_em" TIMESTAMPTZ,
>     "iddp_entrou_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     UNIQUE ("idds_id", "idf_id"),
>     CONSTRAINT "fk_id_desafio_participante_desafio" FOREIGN KEY ("idds_id") REFERENCES "id_desafio" ("idds_id") ON DELETE CASCADE,
>     CONSTRAINT "fk_id_desafio_participante_fiel" FOREIGN KEY ("idf_id") REFERENCES "id_fiel" ("idf_id") ON DELETE CASCADE
> );
> CREATE INDEX IF NOT EXISTS "ix_id_desafio_participante_fiel" ON "id_desafio_participante" ("idf_id", "iddp_fim");
>
> CREATE TABLE IF NOT EXISTS "id_desafio_checkin" (
>     "iddp_id" BIGINT NOT NULL,
>     "iddc_data" DATE NOT NULL,
>     "iddc_criado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     PRIMARY KEY ("iddp_id", "iddc_data"),
>     CONSTRAINT "fk_id_desafio_checkin_participante" FOREIGN KEY ("iddp_id") REFERENCES "id_desafio_participante" ("iddp_id") ON DELETE CASCADE
> );
>
> INSERT INTO "id_badge" ("idbadge_chave", "idbadge_nome", "idbadge_descricao", "idbadge_icone", "idbadge_categoria", "idbadge_nivel", "idbadge_regra", "idbadge_regra_valor", "idbadge_regra_valor2", "idbadge_ordem") VALUES
>     ('desafios_1',  'Primeiro desafio', '1 desafio concluído',   'fa-flag-checkered', 'desafios', 'bronze', 'desafios_concluidos', 1,  0, 1),
>     ('desafios_5',  'Desafiante',       '5 desafios concluídos',  'fa-person-running', 'desafios', 'prata',  'desafios_concluidos', 5,  0, 5),
>     ('desafios_15', 'Incansável',       '15 desafios concluídos', 'fa-trophy',         'desafios', 'ouro',   'desafios_concluidos', 15, 0, 15)
> ON CONFLICT ("idbadge_chave") DO NOTHING;
> ```
> Sem o SQL, o menu "Desafios" mostra "ainda não estão disponíveis", a
> home não mostra o bloco e o admin pede para rodar a seção 62.
>
> **Testar:** rodar o SQL; admin: criar "Concluir 2 aulas em 3 dias"
> (conferir a prévia; marcar "Avisar todos" — os fiéis recebem a
> notificação), "Ler 1 capítulo por dia" (marcar como feito, todo dia, 3
> dias) e um de datas fixas começando amanhã (aparece "Agendado" e, para o
> fiel, "Começa em…"); fiel: home com o convite; "Participar" nos três;
> concluir 2 aulas (no fim da 2ª, notificação "Desafio concluído!" e a
> medalha "Primeiro desafio" no perfil); "Fiz hoje" (bolinha verde; o
> botão vira "Hoje já está marcado"); no dia seguinte sem marcar, a
> bolinha fica cinza e aparece "já não dá para completar todos os
> dias"; "Sair do desafio" (volta para os abertos); home com os desafios
> em andamento; admin: pausar (some dos abertos; quem está continua),
> tentar excluir com participantes (pede para pausar), editar datas de um
> fixo (participantes acompanham); notificações com o ícone de desafio;
> temas claro e escuro e celular.

### T32 — Contribuição em missões (6) · G

- Admin cadastra projetos (nome, descrição, foto, valor previsto de
  gasto, data de fim).
- Página com os projetos ativos, valor arrecadado e barra de progresso.
- Contribuição livre via **PIX ou cartão**, usando o Asaas (mesmo
  provedor das assinaturas, T15).
- Histórico de contribuições do usuário e relatório no admin.

> **Entregue — ✅ validado no Render (26/10/2026)** (`23f7f97`, sandbox do Asaas), com as decisões da equipe
> de 22/10/2026: o dinheiro é recebido pela **equipe** (conta do Asaas no
> CPF de um de nós) e entra no controle do **Financeiro** (ver
> [financeiro.md](financeiro.md)); contribuição **avulsa com valor
> livre**; **quadro de apoiadores** com o valor visível ou não, por
> projeto.
>
> - **Admin → Missões** (todos os admins): projeto com nome, local, foto
>   de capa (R2), descrição, meta (opcional), data de encerramento
>   (opcional), situação (rascunho, recebendo, encerrado) e "mostrar o
>   valor no quadro". Lista com arrecadado, % da meta e apoiadores;
>   excluir só sem contribuições.
> - **Só superadmin** (`SUPERADMIN_EMAILS`): lista das contribuições com o
>   nome real (mesmo das anônimas), filtros e "Lançar oferta recebida fora
>   do app" (soma na barra; entra no Financeiro se a equipe recebeu).
> - **Fiel → Missões** (menu novo; hoje dentro de **Contribuir**, ver o ajuste abaixo): resumo (arrecadado, projetos,
>   apoios), cartões com capa, local, barra da meta e dias restantes;
>   "Minhas contribuições" (aguardando, confirmada, vencida, estornada;
>   "Pagar agora" e comprovante); projetos encerrados.
> - **Página do projeto**: história, progresso, **quadro de apoiadores**
>   ("Maria L.", "Apoiador anônimo" ou o nome da oferta; mensagem; valor
>   só se o projeto mostrar) e o cartão **Apoiar**: valor livre (com
>   atalhos de R$ 20/50/100/200, mínimo R$ 5), Pix ou cartão, mensagem
>   opcional (280), "não mostrar meu nome" e CPF só na primeira vez. O
>   pagamento abre numa aba nova (página do Asaas); esta espera a
>   confirmação e mostra "Contribuição confirmada!".
> - **Webhook**: cobrança com `externalReference` `missao_<id>` vai para
>   `missao_aplicar_pagamento()`; na primeira confirmação, notificação
>   "Obrigado pela sua contribuição 🙏". Estorno tira da barra e do
>   Financeiro. Projeto com data de fim passada encerra sozinho.
>
> **SQL:** seções **71** (Financeiro) e **72** (missões) do
> `database/bancosupabase.md`. Sem elas, o menu Missões não aparece para o
> fiel e o admin pede para rodar.
>
> **Testar (sandbox):** rodar as seções 71 e 72; pôr o próprio e-mail em
> `SUPERADMIN_EMAILS` no Render; admin: criar um projeto em rascunho (não
> aparece para o fiel), com capa, meta de R$ 100 e data de fim, e mudar
> para "Recebendo"; fiel: menu Missões → cartão → contribuir R$ 20 por Pix
> com mensagem (pede o CPF; abre a aba do Asaas; pagar com o botão de
> teste do sandbox; a página volta com "confirmada", a barra sobe para
> 20%, o nome aparece como "Maria L." no quadro e chega a notificação);
> contribuir de novo por cartão (cartão de teste do Asaas) marcando
> "não mostrar meu nome" (não pede CPF; aparece "Apoiador anônimo");
> deixar um Pix sem pagar (fica "Aguardando" com "Pagar agora" em
> Minhas contribuições); valor abaixo de R$ 5 (recusa); admin: ligar
> "mostrar o valor" (valores no quadro); como superadmin, lançar uma
> oferta de R$ 50 "recebida pela igreja" (barra sobe; Financeiro não
> muda) e outra "pela equipe" (entra no Financeiro); estornar uma
> contribuição no painel do Asaas (sai da barra); mudar a data de fim
> para ontem (vira "Encerrado"; cartão de apoiar some); admin comum (fora
> do `SUPERADMIN_EMAILS`) não vê o Financeiro nem a lista de
> contribuições; temas claro e escuro e celular.
>
> **Ajuste — aba Contribuir e destaques da home** (`c0fad62`, 22/10/2026,
> a pedido da equipe):
>
> - **Fiel → Contribuir** (substitui o menu Missões; `missoes.php`
>   redireciona): no topo, a **oferta geral** — a oferta livre para manter
>   o app —, com a arte inteira, o texto e "Quero contribuir"; abaixo, as
>   missões, "Minhas contribuições" e os encerrados. A oferta geral é um
>   projeto de missão marcado em **Admin → Missões → "Usar como oferta
>   geral"** (só um por vez; fica fora da lista de missões). Usa a mesma
>   página de contribuir, o webhook, o quadro de apoiadores e o
>   Financeiro (entra como missões). Na página dela, a capa aparece
>   inteira e os textos falam de oferta, não de missão.
> - **Home**: o destaque do topo virou um **carrossel**: o curso em
>   destaque (pode ser desligado) e as **artes** de **Admin → Destaques da
>   home** (menu novo, tela `destaques` nos grupos de permissões). Cada
>   arte é uma imagem pronta (computador e, opcional, celular), com destino
>   do clique, ordem (setas), período opcional e "mostrar no carrossel".
>   Passa sozinho a cada 7 s; pausa com o mouse em cima, com foco do
>   teclado ou por 15 s depois de o fiel mexer; não passa sozinho com
>   "reduzir movimento" ligado no sistema; arrasta no celular (funciona
>   até sem JS). Sem artes nem curso: boas-vindas, como antes.
> - Saiu a **faixa de novidades** (Admin → Missões, "dispensar"): o
>   carrossel faz o papel dela.
>
> **SQL:** seção **75** do `database/bancosupabase.md` (tabela
> `id_home_destaque`) — já cria a primeira arte, "Toda contribuição…"
> (computador + versão quadrada no celular, em `assets/img/destaques/`),
> levando para Contribuir. Sem ela, a home mostra só o curso, e a tela de
> destaques pede para rodar.
>
> **Testar:** rodar a seção 75; Admin → Missões: criar "Ajude o site da
> igreja a crescer" (Recebendo, sem meta, capa = arte) marcando oferta
> geral → selo "Oferta geral" na lista; marcar outro projeto (o primeiro
> perde o selo); fiel → menu **Contribuir**: oferta geral no topo e fora
> da lista de missões, resumo só das missões; abrir a oferta (capa
> inteira, "Contribuir", "Para onde vai a sua oferta"), contribuir R$ 5
> no sandbox e ver em "Minhas contribuições" e no Financeiro; abrir
> `missoes.php` (vai para Contribuir); mudar a oferta para Encerrado (some
> do topo). Admin → Destaques da home: adicionar a arte "Ajude o site"
> levando para Contribuir, outra com arte de celular, uma sem link, uma
> com início amanhã ("Agendada", não aparece) e uma guardada; subir e
> descer; editar trocando a imagem; remover a arte de celular; excluir.
> Home: curso + artes passando a cada 7 s, setas ao passar o mouse,
> pontos embaixo, clique leva ao destino (link https abre em nova aba);
> parar com o mouse em cima; no celular arrastar, sem setas, arte de
> celular quando houver; desligar o curso em destaque; sem artes e curso
> desligado (boas-vindas); um slide só (sem setas nem pontos); Tab passa
> só pelo slide visível; temas claro e escuro; admin num grupo sem
> "Destaques da home" não vê a tela.
>
> **Ajuste 2 — oferta geral pronta e Pix com o valor que quiser**
> (`db159d2`, 22/10/2026):
>
> - A seção **77** cria a oferta geral ("Ajude o site da igreja a
>   crescer") já marcada, e a seção 75 ganhou a segunda arte do
>   carrossel. Ninguém precisa montar nada no admin para a aba Contribuir
>   e o carrossel funcionarem.
> - **Pix direto:** na página da oferta (e de cada missão aberta), antes
>   do formulário, um **QR Code Pix fixo, sem valor**. A pessoa lê no app
>   do banco ou usa "Copiar código" (copia e cola) e **digita o valor
>   que quiser**, sem mínimo. O QR é criado no Asaas na primeira visita
>   (POST /pix/qrCodes/static, com a chave Pix da conta) e guardado. Cada
>   Pix recebido chega pelo webhook (`pixQrCodeId`) e vira contribuição
>   "Oferta via Pix": entra na barra, no quadro (sem nome) e no
>   Financeiro. Não aparece em "Minhas contribuições", porque o Asaas
>   não diz qual fiel pagou; o nome do pagador fica no painel do Asaas.
> - Contribuição pelo formulário (com nome no quadro): sugestões de
>   **R$ 5, 10, 20, 50 e 100** (o mínimo da cobrança do Asaas é R$ 5, por
>   isso não há R$ 2; R$ 2 dá pelo Pix direto).
>
> **SQL:** seção **77** (e rodar a 75 de novo, se já tinha rodado).
>
> **Testar (sandbox):** rodar 75 e 77; home: carrossel com o curso e as
> duas artes; Contribuir: oferta geral no topo sem configurar nada; abrir
> a oferta: QR Code com fundo branco, "Copiar código" (aparece
> "Copiado!"), sugestões de R$ 5 a 100; pagar o QR pelo sandbox do Asaas
> com um valor livre (ex.: R$ 3) e conferir: "Oferta via Pix" no quadro,
> barra/total, Admin → Missões → Contribuições ("Pix direto (QR Code)") e
> o Financeiro; reenviar o mesmo webhook no painel do Asaas (não
> duplica); conta do Asaas sem chave Pix: o QR some e o formulário
> continua; temas claro e escuro e celular (QR menor, botão de copiar
> embaixo).
>
> **Ajuste 3 — Admin → Contribuições e o que o fiel vê** (`214cc71`,
> 22/10/2026):
>
> - **Admin → Contribuições** (menu novo, logo abaixo de Missões, **só
>   superadmin**, fora dos grupos de permissão como o Financeiro): a
>   oferta geral, que é da equipe interna. Editar texto, capa, situação e
>   o que o fiel vê; lançar oferta recebida fora do app; contribuições com
>   filtros. Sem oferta geral, botão "Criar oferta geral" com o texto e
>   a arte padrão. O link da página (`missao.php?id=…`) não muda, então
>   os destaques da home que já apontam para ela continuam valendo.
> - **Admin → Missões** mostra só as missões: a oferta geral saiu da
>   lista, do "Lançar oferta" e da lista de contribuições, e não dá para
>   editá-la por lá. Saiu também a opção "Usar como oferta geral".
> - **O que o fiel vê** (cada projeto): total arrecadado (com barra e
>   %), número de apoiadores, quadro de apoiadores e valor de cada
>   contribuição. Missões vêm com tudo ligado (como antes, menos os
>   valores); a oferta geral, sem o total. A página do projeto, os
>   cartões e o resumo da aba Contribuir seguem essas opções (o resumo
>   some quando não há o que mostrar). Sem quadro, some também o "não
>   mostrar meu nome".
> - **Filtros das contribuições** (Missões e Contribuições): situação,
>   como chegou (pelo app, Pix direto, fora do app), forma (Pix, cartão),
>   período (de/até) e busca por nome, e-mail ou mensagem, com os totais
>   do filtro: confirmado bruto, depois das taxas, quantidade e
>   aguardando.
>
> **SQL:** seção **78**.
>
> **Testar:** rodar a 78; superadmin: menu Contribuições logo abaixo de
> Missões; admin comum: não vê o menu e, abrindo
> `admin_contribuicoes.php`, volta ao dashboard; a oferta geral não
> aparece em Missões; editar a oferta (texto, capa) e ver como fiel: sem
> "R$ 0,00 arrecadados"; ligar o total e conferir que volta; numa missão,
> desligar total, apoiadores e quadro, um de cada vez, e conferir a
> página, o cartão e o resumo da aba Contribuir; lançar uma oferta fora do
> app em cada tela e excluir; filtros: "Pix direto", um período sem
> nada (totais zerados), busca por nome; temas claro e escuro e celular.

>
> **Ajuste 4 — abas separadas: Missões e Contribuir** (`c423a06`,
> 22/10/2026):
>
> - **Missões** (`missoes.php`): só os projetos de missão, as
>   contribuições do fiel para eles e os encerrados.
> - **Contribuir** (`contribuir.php`): só a oferta geral. A aba abre
>   direto a página dela (QR do Pix e formulário). Sem oferta geral
>   recebendo, mostra "As contribuições não estão abertas no momento".
> - Na página de cada projeto (missão ou oferta), "Suas contribuições"
>   lista o que o fiel já deu ali, com "Pagar agora" e "Comprovante". O
>   "voltar" aparece só nas missões; o menu marca Missões ou Contribuir
>   conforme o projeto.
> - Admin → Destaques da home: destino "Aba Missões" além de "Aba
>   Contribuir (oferta geral)".
>
> **Testar:** menu com Missões e Contribuir; Contribuir abre a oferta
> geral (menu marca Contribuir, sem "voltar"); encerrar a oferta em Admin
> → Contribuições: a aba mostra o aviso com "Ver as missões"; Missões
> sem a oferta geral, "Minhas contribuições" só com missões; abrir uma
> missão (menu marca Missões, "Todas as missões" volta); contribuir e
> ver "Suas contribuições" na página; link antigo `contribuir.php` dos
> destaques da home abre a oferta.

### T33 — Devocionais enviados pelos usuários (4.2) · G

- Formulário com limite de caracteres: título, texto base, tema e autor.
- Líderes votam/dão nota aos devocionais enviados.
- Acima de uma nota mínima (configurável), o devocional entra numa
  **agenda automática** de publicação (um por dia).
- Página "Devocional do dia" no app.

> **Entregue — ✅ validado no Render (26/10/2026)** (`76d9092`), **com a nossa proposta** — as
> dúvidas (quem avalia, nota mínima, edição pelo admin, agenda vazia…)
> estão em [pontos_para_alinhar.md](../para_o_cliente/pontos_para_alinhar.md),
> seção 9.
>
> - **Fiel → Devocional** (menu novo): "Enviar um devocional" com título
>   (5–100), **texto base** (referência + trecho opcional do versículo),
>   **tema** (lista fixa) e texto (200–2.500, com contador); autor = quem
>   envia. Até 3 em avaliação por pessoa; bloqueado no chat não envia.
> - **Líderes** (equipe ou função de liderança — mesma regra do filtro
>   "Líderes" dos comentários) veem "Avaliar devocionais" na mesma
>   página: abrem o texto e dão **1 a 5 estrelas** (trocar ou tirar a
>   nota; não o próprio; veem só o número de avaliações, não a média).
> - **Agenda automática:** com `devocional_avaliacoes_minimas` (padrão 3)
>   e média ≥ `devocional_nota_minima` (padrão 4), aprova e agenda na
>   **próxima data livre** a partir de hoje (um por dia — índice único no
>   banco); o autor recebe notificação + push com a data.
> - **Devocional do dia:** o agendado para hoje (sem ele, o último
>   publicado, com a data). Página com tema, título, texto base em
>   destaque, texto e autor; **anteriores** (14, com link) e **Meus
>   devocionais** (em avaliação com o número de avaliações, agendado com a
>   data, publicado, não publicado com o motivo; remover os em avaliação
>   ou recusados). **Home:** cartão "Devocional do dia" logo abaixo do
>   destaque. **Lembrete diário (T23):** com devocional de hoje, o push
>   vira "Devocional do dia 🙏 — *título — referência*" e abre a página.
> - **Admin → Devocionais:** regra da agenda (avaliações e média
>   mínimas); abas Em avaliação (média e avaliações), Agenda, Publicados,
>   Recusados e Removidos; **aprovar e agendar**, **recusar** com motivo
>   (o autor é avisado), **trocar a data**, **voltar para avaliação** e
>   **tirar do ar**. Aviso quando a agenda está vazia.
> - Tela de notificações: ícone de **Devocional**.
>
> **SQL** (seção 63 do `bancosupabase.md`):
> ```sql
> CREATE TABLE IF NOT EXISTS "id_devocional" (
>     "iddv_id" BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
>     "idf_id" BIGINT NOT NULL,
>     "iddv_titulo" VARCHAR(100) NOT NULL,
>     "iddv_referencia" VARCHAR(60) NOT NULL,
>     "iddv_versiculo" VARCHAR(600),
>     "iddv_tema" VARCHAR(40) NOT NULL,
>     "iddv_texto" VARCHAR(2500) NOT NULL,
>     "iddv_status" VARCHAR(10) NOT NULL DEFAULT 'avaliacao',
>     "iddv_data" DATE,
>     "iddv_motivo" VARCHAR(255),
>     "iddv_criado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     "iddv_atualizado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     CONSTRAINT "ck_id_devocional_status" CHECK ("iddv_status" IN ('avaliacao', 'aprovado', 'recusado', 'removido')),
>     CONSTRAINT "fk_id_devocional_fiel" FOREIGN KEY ("idf_id") REFERENCES "id_fiel" ("idf_id") ON DELETE CASCADE
> );
> CREATE INDEX IF NOT EXISTS "ix_id_devocional_status" ON "id_devocional" ("iddv_status", "iddv_data");
> CREATE UNIQUE INDEX IF NOT EXISTS "ux_id_devocional_data" ON "id_devocional" ("iddv_data") WHERE "iddv_status" = 'aprovado';
>
> CREATE TABLE IF NOT EXISTS "id_devocional_nota" (
>     "iddv_id" BIGINT NOT NULL,
>     "idf_id" BIGINT NOT NULL,
>     "iddn_nota" SMALLINT NOT NULL,
>     "iddn_criado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     "iddn_atualizado_em" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
>     PRIMARY KEY ("iddv_id", "idf_id"),
>     CONSTRAINT "ck_id_devocional_nota" CHECK ("iddn_nota" BETWEEN 1 AND 5),
>     CONSTRAINT "fk_id_devocional_nota_devocional" FOREIGN KEY ("iddv_id") REFERENCES "id_devocional" ("iddv_id") ON DELETE CASCADE,
>     CONSTRAINT "fk_id_devocional_nota_fiel" FOREIGN KEY ("idf_id") REFERENCES "id_fiel" ("idf_id") ON DELETE CASCADE
> );
> ```
> Sem o SQL, a página mostra "ainda não está disponível", a home e o
> lembrete seguem como antes e o admin pede para rodar a seção 63.
>
> **Testar** (para testar rápido, baixe a regra no admin para 1
> avaliação): rodar o SQL; página sem devocionais (convite); enviar com
> texto curto (erro e contador) e um válido (em "Meus devocionais": em
> avaliação); 4º envio barrado; com uma conta **líder** (admin ou função
> de liderança), avaliar com estrelas (trocar e tirar a nota; no próprio
> não aparece); com a regra atingida, o devocional é aprovado para hoje
> (o autor recebe o aviso; a home mostra o cartão; a página mostra o
> devocional); aprovar outro (vai para amanhã, aparece na Agenda); admin:
> trocar a data (data ocupada dá erro), voltar para avaliação, recusar
> com motivo (o autor vê), tirar do ar; agenda vazia (aviso no admin e o
> app mostra o último com a data); "Devocionais anteriores" e
> `devocional.php?id=N`; lembrete diário com devocional de hoje; conta
> comum não vê "Avaliar devocionais"; temas claro e escuro (estrelas e
> cartão dourados) e celular.

### T34 — Biblioteca de livros digitais (2.2) · G

- Admin cadastra livros (capa, autor, descrição, arquivo PDF/ePub).
- Leitor dentro do app.
- Controle de acesso por plano de assinatura.
- **Atenção:** confirmar com o cliente os direitos autorais de cada livro.

---

## Nível 5 — Muito complexo

Dependem de serviços externos, custos mensais ou aprovações.

### T35 — Legenda automática (1.5) · GG

- Transcrever cada vídeo com um serviço (ex.: Whisper) e gerar o arquivo
  de legenda `.vtt`.
- Exibir no player com `<track>` e botão para ligar/desligar.
- Rodar automaticamente quando um vídeo novo é enviado.
- **Custo:** cobrança por minuto de vídeo transcrito.

### T36 — Bíblia integrada (2) · GG

- Integração com uma API bíblica com várias versões (ex.: API.Bible ou
  ABíbliaDigital).
- Leitor no app: livro, capítulo, versículo, troca de versão.
- **Atenção:** verificar a licença de cada versão (ARA, NVI etc. têm
  direitos autorais).

### T37 — Planos de leitura (2) · G

- Admin cria planos (ex.: "Bíblia em 1 ano", "Evangelhos em 30 dias").
- Usuário segue o plano e marca a leitura do dia; progresso visível.
- Pode contar para a ofensiva e as medalhas.

### T38 — Devocional diário no WhatsApp (1.9) · GG

- Usuário opta por receber (precisa do WhatsApp da T11 e do horário da
  T23).
- Envio diário do devocional do dia (T33 ou cadastrado pelo admin).
- Integração com a API oficial do WhatsApp Business ou um provedor (Z-API,
  Twilio).
- **Atenção:** a API oficial exige aprovação da Meta e modelos de
  mensagem aprovados; há custo por mensagem.

### T39 — Moeda interna (3.3) · GG

- Usuário ganha moedas com medalhas, desafios e ofensiva.
- Moedas viram descontos na assinatura ou liberam conteúdos.
- Depende das assinaturas em produção (T15) e da gamificação (T30, T31).
- Definir com o cliente as regras (quanto vale cada ação, o que se troca).

---

## Pendências com o cliente

Perguntas que precisam de resposta antes de algumas tarefas. As de T15 e
T22 (e o modelo de um site por igreja) estão detalhadas em
[pontos_para_alinhar.md](../para_o_cliente/pontos_para_alinhar.md).

| Tarefa | Pergunta |
|---|---|
| Geral | Cada igreja/filial terá o próprio site? Existe conteúdo da sede compartilhado? Admin por igreja? |
| T15 | Assinatura só em fevereiro/março de 2027 (ver o cronograma). Desde 22/10/2026 valores, planos e regras de acesso são decididos **meio a meio** entre a equipe e o cliente; a parte financeira (conta do Asaas, recebimento) é da equipe. Falta fechar: o anual individual de R$ 19,90 é por mês ou o total do ano? O família de R$ 39,00 é mensal ou anual? |
| T22 | **Lista real das filiais** (hoje a lista provisória são os estados), células, quem troca a célula e quais etapas de "tempo de fé" (depende do modelo de site por igreja). |
| T24 | Quais títulos existem ("Discípula fiel"…), a regra de cada um e se igreja/célula continuam como selo (ver `pontos_para_alinhar.md`, seção 4). |
| T30 | O que conta como "resposta útil"? |
| T33 | Quem são os líderes que votam e qual a nota mínima? (proposta no app; ver pontos_para_alinhar.md, seção 9) |
| T34 | Quais livros e quem tem os direitos? |
| T36 | Quais versões da Bíblia são obrigatórias? |
| T39 | Regras da moeda: quanto vale cada ação e o que pode ser trocado? |

## Pontos para debater internamente

Foram para [pontos_gerais.md](pontos_gerais.md), seção 5 (cartão da
Cloudflare, vídeo apagado some na hora, aba "Lives anteriores").

## Correções feitas no caminho

Problemas que já existiam, encontrados durante as tarefas e corrigidos em
commit próprio.

| Commit | Encontrado em | Correção | Testar |
|---|---|---|---|
| `24689c2` | T12 | Na edição de usuário do admin (Configurações), o CPF era obrigatório — e o cadastro nunca pediu CPF —, então o admin não conseguia salvar nenhum fiel comum. Telefone vazio aparecia como "N/A" dentro do campo e também travava o envio. Agora CPF e telefone são opcionais (validados se preenchidos; vazio grava NULL, porque o CPF é único no banco). | Editar um fiel sem CPF e salvar; preencher CPF/telefone inválidos (erro) e válidos (salva); dois fiéis sem CPF salvos em seguida. |
| `957c87a` | T11 | Na área do fiel, o `.text-muted` usava o cinza-escuro do Bootstrap (feito para fundo branco) e sumia no tema escuro: e-mail e "Na plataforma desde" no topo do perfil, legendas de vários cartões e telas. Só o admin tinha a correção. | Tema escuro e claro: topo do perfil, Configurações, Assinatura, Ofensiva, Notificações, Termos e a lista de vídeos — textos secundários legíveis. |
| `c28bb2e` | T05 | O botão principal (`.btn-play`) só tinha estilo no banner da home: "Próxima aula" e "Comentar" no player e os botões do topo do curso apareciam quase sem estilo; ao clicar, o fundo ficava transparente. No tema claro, o botão branco do banner quase sumia e os textos/selos do topo do curso ficavam ilegíveis. | Temas escuro e claro: banner da home, topo de um curso (selos, descrição, "Continuar", "Favoritar", "X/Y aulas"), "Próxima aula" e "Comentar" no player; clicar e segurar os botões. |

## Problemas encontrados no caminho

Coisas que já existiam e apareceram durante as tarefas. As três da lista
foram corrigidas na rodada de 19/10/2026.

| Encontrado em | Problema | Corrigido em |
|---|---|---|
| T03 | `idpr_tempo_assistido_segundos` somava a posição mais longe a cada envio (a cada 30 s), inflando o "tempo assistido". | `005829d` (guarda o maior valor) e `92fd2f7` (limitado ao tempo real) |
| T03 | Rever uma aula já concluída voltava o status para "em andamento". | `005829d` |
| T22 | No cadastro, a filial digitada se perdia quando não batia exatamente com uma filial de `id_filial`. | `c523144` (busca sem maiúsculas/espaços e o texto fica em `idf_filial`) |

Problemas novos de qualidade, segurança ou desempenho vão para
[pontos_gerais.md](pontos_gerais.md).

## Já concluído (não precisa de card)

- "Continuar Assistindo" na home e barra de % do curso (parte do 1.2).
- Tempo assistido de cada vídeo salvo no banco.
- Ofensiva com dias seguidos, recorde e ranking.
- Certificados de conclusão.
- Comentários nos vídeos com moderação.
- Integração das assinaturas com o Asaas (falta só a produção, T15).
