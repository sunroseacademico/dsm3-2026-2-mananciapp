# Pontos para alinhar com o cliente

Decisões que precisam do cliente antes de seguir com algumas partes do app.
Cada seção diz como o sistema está hoje, o que propomos e o que perguntar.
O andamento de cada pedido está em
[status_dos_pedidos.md](status_dos_pedidos.md).

Montado em 07/10/2026.

| Seção | Tarefas | Situação |
|---|---|---|
| [1. Um site por igreja](#1-um-site-por-igreja) | T15, T22 e outras | Define o modelo do produto — responder primeiro |
| [2. Assinaturas em produção](#2-assinaturas-em-produção-t15) | T15 | Código pronto no sandbox; faltam respostas para cobrar |
| [3. Vínculo com a igreja](#3-vínculo-com-a-igreja-t22) | T22 | Proposta pronta; aguardando as respostas |
| [4. Selos e títulos no perfil](#4-selos-e-títulos-no-perfil-t24) | T24 | Primeira parte entregue; faltam os títulos e os selos de igreja/célula |
| [5. Transmitir para a TV no computador](#5-transmitir-para-a-tv-no-computador-t25) | T25 | Celular entregue; decidir se vale um botão próprio no computador |
| [6. Medalhas de ensino, resumos e oração](#6-medalhas-de-ensino-resumos-e-oração-t30) | T30 | Entregue com a nossa proposta; confirmar as regras e os números |
| [7. Desafios](#7-desafios-t31) | T31 | Entregue; confirmar o que mais o desafio pode contar e o ranking por célula |
| [8. Contribuição em missões](#8-contribuição-em-missões-t32) | T32 | Entregue no ambiente de teste, com as decisões da equipe; faltam detalhes opcionais |
| [9. Devocionais enviados pelos fiéis](#9-devocionais-enviados-pelos-fiéis-t33) | T33 | Entregue com a nossa proposta; confirmar quem avalia, a nota mínima e as regras de publicação |
| [10. Biblioteca de livros digitais](#10-biblioteca-de-livros-digitais-t34) | T34 | Ainda não feito; proposta pronta, aguardando as respostas (principalmente os direitos dos livros) |
| [11. Tamanho dos vídeos das aulas](#11-tamanho-dos-vídeos-das-aulas) | — | Para conversar depois; não muda nada no app |
| [12. Levar a plataforma a outras igrejas](#12-levar-a-plataforma-a-outras-igrejas) | — | Novo (27/10/2026): conversar antes de oferecermos a outras igrejas |

---

## 1. Um site por igreja

**A ideia:** cada igreja/filial terá **o seu próprio site** (app), com suas
lives, seus fiéis, seus cursos e seu admin — em vez de um app único com
todas as igrejas dentro.

Isso muda o desenho de várias tarefas, por isso vem antes de tudo:

| Onde pesa | Com um site por igreja |
|---|---|
| **T22 — Vínculo com a igreja** | A "igreja vinculada" passa a ser o próprio site; não faz sentido o fiel buscar a igreja numa lista. Sobram a **célula/grupo** e o **tempo de fé**. |
| **T15 — Assinaturas** | Cada igreja recebe na **própria conta do Asaas**? Ou uma conta central que repassa? Os planos e valores são iguais para todas ou cada igreja define os seus? |
| **Lives e conteúdo** | Cada igreja tem as suas lives e cursos. Existe conteúdo **compartilhado** entre as igrejas (ex.: cursos da sede que todas veem)? |
| **Fiéis** | Uma pessoa que frequenta duas igrejas tem **duas contas**, uma em cada site? |
| **Rankings e desafios (T30, T31)** | Ficam dentro de cada igreja (por célula), ou existe ranking **entre igrejas**? |

### Perguntas

| # | Pergunta |
|---|---|
| 1 | Confirmado que cada igreja/filial terá **um site separado** (endereço próprio, ex.: `igrejaX.manancial…`)? |
| 2 | Existe **conteúdo da sede** que todas as igrejas precisam ver (cursos, lives)? Ou cada uma só vê o seu? |
| 3 | Quem administra cada site: um **admin por igreja** (pastor local) e um **admin geral** acima de todos? |
| 4 | O fiel pode **trocar de igreja** (mudou de cidade)? O histórico dele (cursos, medalhas, ofensiva) vai junto? |
| 5 | Quantas igrejas/filiais existem hoje e quantas devem entrar no começo? |

**Para a equipe técnica:** a resposta define se o caminho é **um sistema
com várias igrejas no mesmo banco** (cada registro marcado com a igreja,
mais barato de manter) ou **uma instalação por igreja** (isolamento total,
mas cada atualização precisa ser feita em todas). Vale decidir isso antes
de mexer em banco para a T22.

---

## 2. Assinaturas em produção (T15)

O sistema de assinaturas com o Asaas **está pronto e roda no ambiente de
teste** (nenhuma cobrança de verdade). Para ligar a cobrança faltam as
respostas abaixo. Ao lado de cada uma, o que o sistema faz hoje — se o
cliente concordar, não muda nada.

**Decidido em 22/10/2026:** a parte financeira fica com a **equipe**: a
conta do Asaas (por enquanto no CPF de um de nós, ainda no ambiente de
teste) recebe as assinaturas e as contribuições de missões, e a equipe
divide o lucro com a igreja (hoje 50%). Isso responde às perguntas 12 a 17
e a de uma conta por igreja. Valores, planos e regras de acesso (2.1 e 2.3)
são decididos **meio a meio** entre a equipe e a igreja.

**Ligado à seção 1:** com um site por igreja, os planos são os mesmos para
todas? (O plano "Anual igreja" de R$ 290,00 continua fazendo sentido nesse
modelo?)

### 2.1. Valores e planos

| # | Pergunta | Hoje o sistema está assim |
|---|---|---|
| 1 | O **Anual individual** de R$ 19,90 é **por mês** (R$ 238,80 no ano) ou o **total do ano**? | R$ 19,90 pelo ano todo |
| 2 | O **Família** de R$ 39,00 é **mensal** ou **anual**? | Mensal |
| 3 | O **Mensal** fica mesmo em **R$ 10,00**? (no pedido original era R$ 39,90) | R$ 10,00 |
| 4 | O **Anual igreja** de R$ 290,00 cobre **quantos membros**? | Até 100 pessoas além de quem paga |
| 5 | O **Família** é titular + **4** pessoas? | Titular + 4 |
| 6 | O **Anual individual** é **só no cartão**, ou aceita Pix/boleto também? | Só cartão |
| 7 | Vai ter **teste grátis**? De quantos dias? | Sem teste |
| 8 | Quais **cursos são gratuitos** (o plano "Gratuito")? | Nenhum marcado |
| 9 | **Cursos novos** entram automaticamente para quem já assina, sem custo extra? | Sim — a assinatura libera tudo que não for gratuito |
| 10 | Vai ter conteúdo **por tempo limitado** (ex.: curso disponível só por 30 dias)? | Não existe hoje |
| 11 | O preço pode ter **reajuste** no futuro? Como avisar quem já assina? | O admin muda o valor do plano; quem já assina continua no valor antigo até cancelar |

### 2.2. Quem recebe o dinheiro

| # | Pergunta | Por que importa |
|---|---|---|
| 12 | A conta do Asaas vai ficar em nome de quem: **CNPJ da igreja** (ou associação) ou **CPF** de um responsável? | É o cadastro da conta de produção; o Asaas pede documentos dessa pessoa ou empresa. Decidir com o tesoureiro/contador. |
| 13 | Precisa emitir **nota fiscal ou recibo** de cada cobrança? | Decisão do **contador**. O Asaas emite nota, mas precisa ser configurado. |
| 14 | A cobrança é apresentada como **assinatura de serviço** ou **contribuição com benefício**? | Muda o texto das telas e o tratamento fiscal (contador). |
| 15 | As **isenções fiscais** da igreja valem para essa receita? Existe **limite de faturamento** que preocupa (ex.: teto de MEI)? | Contador. |
| 16 | Quem vai **acompanhar os recebimentos** (conferir, estornar, responder dúvida de cobrança)? | Essa pessoa precisa de acesso ao painel do Asaas e ao admin. |
| 17 | As **taxas do Asaas** por Pix, boleto e cartão estão aprovadas? | Elas saem de cada pagamento. |

### 2.3. Regras de acesso

| # | Pergunta | Hoje o sistema está assim |
|---|---|---|
| 18 | Depois de ligar, quem não assina: **vê só os cursos gratuitos** ou **não entra no app**? | Livre (todos assistem) |
| 19 | Quem **já usa de graça** hoje: passa a pagar, ganha **cortesia** (para sempre ou por um tempo) ou ganha teste grátis? | Ninguém tem cortesia |
| 20 | Existe quem **nunca paga** (pastores, equipe, voluntários, quem não pode pagar)? Quem aprova? | O admin dá cortesia caso a caso |
| 21 | Pagamento atrasado: quantos dias de **tolerância** antes de bloquear? | 5 dias |
| 22 | Ao **cancelar**, o fiel mantém o acesso até o fim do período já pago? | Sim (o fiel cancela sozinho, pelo app) |
| 23 | Existe **política de reembolso**? (ex.: arrependimento em 7 dias, cobrança em dobro) | Reembolso é manual, pelo painel do Asaas |

### 2.4. Comunicação

| # | Pergunta |
|---|---|
| 24 | **Data** para começar a cobrar e **quanto tempo de aviso** aos fiéis antes? |
| 25 | Por onde avisar (notificação no app, WhatsApp, culto)? Quem escreve o texto? |
| 26 | Qual o **canal de dúvidas sobre cobrança** (WhatsApp, e-mail) que vai aparecer para o fiel? |
| 27 | Vale ter uma página de **perguntas frequentes sobre a assinatura** no app? |

---

## 3. Vínculo com a igreja (T22)

**Pedido do cliente:** igreja vinculada (busca por nome ou código),
célula/grupo escolhido de uma lista e tempo de fé ("Novo convertido",
"Consolidado"…).

### Como está hoje

- O banco já tem a tabela de filiais (`id_filial`: nome, cidade, UF) e o
  fiel aponta para uma delas.
- **Atualizado em 22/10/2026:** no **cadastro** e no **perfil**, a
  filial agora é escolhida de uma **lista**, mantida pela equipe em
  Admin → Filiais. Enquanto vocês não mandam a lista das filiais, ela
  mostra os **estados** (São Paulo, Rio de Janeiro…). Há também a opção
  **"Nenhuma filial"**, para quem não frequenta a igreja mas gostou da
  plataforma.
- **Precisamos de vocês:** a lista das filiais (nome, cidade e estado de
  cada uma). Com ela, trocamos os estados pelas filiais.
- Quem se cadastrou antes, com a filial digitada, é convidado a escolher
  na lista ao abrir o perfil.
- Não existe célula/grupo nem tempo de fé.

### O que propomos

Seguindo o modelo das "Funções na Igreja" (T12), que o admin já usa:

1. **Igreja/filial** — *depende da seção 1.*
   - **Se for um app só para todas:** tela no admin "Igrejas e Células"
     (nome, **código** curto como `MAN-CPS`, cidade/UF, ativa). No
     cadastro, o texto livre vira uma **busca** por nome, cidade ou
     código. No perfil, o fiel vê e troca a igreja. Quem está sem igreja
     aparece para o admin numa lista "sem igreja definida".
   - **Se for um site por igreja:** não há escolha de igreja — o site já
     é a igreja. O campo sai do cadastro.
2. **Célula/grupo** — lista cadastrada pelo admin (nome, igreja, líder
   opcional, ativa). No perfil, o fiel escolhe uma célula **da igreja
   dele**. Opcional (nem todo mundo participa). Base para rankings e
   desafios por célula.
3. **Tempo de fé** — escolha no perfil, diferente do "tempo de membresia"
   da T08 (que é a data em que entrou na igreja). Lista sugerida:
   *Novo convertido · Em consolidação · Consolidado · Batizado ·
   Discípulo · Em liderança*.
4. **"Perfil X% completo" (T13)** passa a contar célula e tempo de fé.

### Perguntas

| # | Pergunta | Nossa sugestão |
|---|---|---|
| 1 | (Se for um app só) Já existe uma **lista das igrejas/filiais com código**? | Se não, o admin cadastra na tela nova |
| 2 | (Se for um app só) No cadastro, se a pessoa **não achar a igreja** na lista: bloqueia ou deixa passar para o admin resolver na aprovação? | Deixar passar com "Não encontrei minha igreja" |
| 3 | **Quem troca a célula** (e a igreja): o próprio fiel no perfil, ou só o admin (como a função)? | O fiel troca; o admin pode corrigir |
| 4 | Quais são as **células/grupos** hoje e quem é o líder de cada uma? | O admin cadastra |
| 5 | A lista de **tempo de fé** acima serve, ou a igreja usa outros nomes/etapas? | Lista fixa; ou editável pelo admin, como as funções |
| 6 | O tempo de fé é **escolhido pelo fiel** ou **definido pelo líder/admin** (como etapa do discipulado)? | Escolhido pelo fiel |

---

## 4. Selos e títulos no perfil (T24)

**Pedido do cliente:** selos no topo do perfil e ao lado do nome nos
comentários — igreja, célula e títulos como "Discípula fiel".

### O que já foi feito (com os dados que o app já tem)

| Selo | Exemplo | Onde aparece |
|---|---|---|
| **Função na igreja** (definida pelo admin) | "Pastor(a)", "Diácono(isa)" | Topo do perfil e ao lado do nome nos comentários. "Membro" não vira selo, por ser o padrão. |
| **Tempo de membresia** | "Membro há 3 anos" | Topo do perfil |
| **Maior medalha conquistada** | "Centenário" (Ouro) | Topo do perfil e, como ícone na cor do nível, ao lado do nome nos comentários |

### O que falta definir

| # | Pergunta | Nossa sugestão |
|---|---|---|
| 1 | Quais **títulos** existem ("Discípula fiel"…) e qual a **regra** de cada um? Ex.: "Discípula fiel = concluiu X cursos" ou "= 100 dias de ofensiva". | Títulos ganhos por regra automática (como as medalhas), para ninguém precisar dar na mão |
| 2 | Algum título é **dado pelo pastor/admin** (não automático)? Ex.: "Intercessora", "Líder de louvor". | O admin marca na ficha do fiel |
| 3 | Títulos têm **versão feminina e masculina** ("Discípula/Discípulo fiel")? | Sim — usar o gênero informado no perfil (hoje o app não pergunta o gênero; seria um campo novo) |
| 4 | Os selos de **igreja** e **célula** continuam fazendo sentido com **um site por igreja** (seção 1)? Num site só da igreja, todos teriam o mesmo selo de igreja. | Só o selo de **célula** |
| 5 | Quantos selos mostrar **ao lado do nome nos comentários**? Muitos selos poluem a lista. | Função + 1 título (o principal); o resto só no perfil |
| 6 | Mostrar a **ofensiva atual** ("🔥 45 dias") como selo? | Não — muda todo dia; já aparece na tela de ofensiva |

---

## 5. Transmitir para a TV no computador (T25)

**Pedido do cliente:** Chromecast e AirPlay nas aulas.

### O que já foi feito

Um botão **"Transmitir"** abaixo do vídeo, que só aparece quando há uma
TV disponível na mesma rede Wi-Fi:

| Aparelho | O que acontece |
|---|---|
| **iPhone / iPad / Mac** (Safari) | Abre a lista de **AirPlay** (Apple TV e TVs compatíveis). |
| **Android** (Chrome e o app instalado) | Abre a lista de **Chromecast** (Chromecast e TVs com Google TV). |
| **Computador** (Chrome / Edge) | Sem botão próprio. Dá para transmitir pelo **menu ⋮ do Chrome → "Transmitir…"**. |

Enquanto a aula está na TV, o celular funciona como controle (pausar,
avançar) e o progresso da aula continua sendo salvo.

**Limites que não dependem de nós:** no iPhone só existe AirPlay (a Apple
não permite Chromecast pelo navegador); o Firefox não transmite; vídeos
em formato OGG não tocam na TV (o admin passou a ver a dica "prefira MP4").

### Botão próprio também no computador (opcional)

Para ter o botão "Transmitir" também no Chrome/Edge do computador, é
preciso usar a **biblioteca oficial do Google para Chromecast (Google
Cast SDK)**. O que teria que ser feito:

| Parte | O que é |
|---|---|
| Biblioteca do Google | Carregar o script do Google na página da aula e liberar esse endereço nas regras de segurança do site. |
| Conexão com a TV | O app manda para o Chromecast o link do vídeo, o título da aula, a imagem do curso e o minuto em que a pessoa está. Quem toca o vídeo é a própria TV. |
| Controle pela página | Enquanto transmite, a área do vídeo vira um painel "Transmitindo para *Sala*" com pausar, avançar e barra de tempo, comandando a TV. |
| Progresso e próxima aula | Passam a ler o tempo informado pela TV (hoje leem o vídeo da página). Ao terminar, a próxima aula vai para a TV também. |
| Voltar para a tela | Ao desconectar, o vídeo continua no computador do ponto onde a TV parou. |
| Link do vídeo | O link de cada vídeo vale 4 horas; transmissões mais longas pedem um link novo. |

- **Esforço:** cerca de **2 a 3 dias** de desenvolvimento.
- **Na TV**, o vídeo abre no player padrão do Google (título e imagem da
  aula, sem a marca da igreja). Uma tela com a marca da igreja exige um
  cadastro de desenvolvedor no Google (**US$ 5**, pagamento único) e mais
  trabalho.
- **Teste:** precisa de um Chromecast ou TV com Google TV.

### Perguntas

| # | Pergunta | Nossa sugestão |
|---|---|---|
| 1 | Quantos fiéis assistem às aulas **no computador** e iriam querer jogar para a TV? | Se forem poucos, o menu do Chrome resolve |
| 2 | Vale investir os **2 a 3 dias** no botão próprio para o computador agora? | Deixar para depois; rever se houver pedidos dos fiéis |
| 3 | A tela da TV precisa ter a **marca da igreja**? | Não por enquanto (player padrão do Google) |
| 4 | A equipe tem **Chromecast e Apple TV** (ou TVs com Google TV/AirPlay) para os testes? | — |

---

## 6. Medalhas de ensino, resumos e oração (T30)

**Pedido do cliente:** três novas categorias de medalhas, além das de
ofensiva (crescimento pessoal):

| Categoria | Bronze | Prata | Ouro |
|---|---|---|---|
| Ensino e resposta | Voz da paz — 10 respostas úteis | Mestre da Palavra — 50 | Sábio da comunidade — 150 |
| Compartilhamento e resumos | Escriba — 5 resumos | Cronista da fé — 20 resumos | Evangelista digital — resumo com 500+ votos |
| Intercessão e oração | Orador fiel — 50 améns | Intercessor — 200 améns | Coluna de fogo — 500 améns + pedidos atendidos |

### O que já foi feito

As medalhas já estão no app, **com as regras que propomos abaixo**. Os
nomes, os números e os ícones ficam guardados no banco: se o cliente
quiser outros valores, mudamos sem precisar de uma nova versão do app.

| Categoria | Como conta |
|---|---|
| **Ensino e resposta** | Respostas que a pessoa deu a comentários das aulas e que outras pessoas acharam **úteis** (veja a pergunta 1). |
| **Compartilhamento e resumos** | Resumos das aulas que a pessoa publicou (aba "Resumos" da aula) e os votos "Edificante" que eles receberam. |
| **Intercessão e oração** | Vezes em que a pessoa tocou em **"Amém"** num pedido de oração de outra pessoa (aba "Orações"). Cada pedido conta uma vez só. |

- A medalha sai **na hora** (ao publicar o resumo, ao dizer amém, ao
  receber o voto que faltava) e vale também para o que a pessoa já tinha
  feito antes.
- Quem ganha uma medalha por um voto recebido é **avisado por
  notificação** ("Nova medalha: Voz da paz 🏅").
- No **perfil**, as medalhas aparecem separadas por categoria, com o
  progresso da próxima (ex.: "Intercessor: 87 de 200 améns").
- **Medalha conquistada não se perde.**
- Além das 9 do exemplo, colocamos **medalhas intermediárias** (como
  fizemos na ofensiva), para a pessoa ter uma conquista logo no começo:

| Categoria | Medalhas (as do cliente em **negrito**) |
|---|---|
| Ensino e resposta | Primeira palavra (1 resposta útil, bronze) · **Voz da paz** (10, bronze) · Conselheiro (25, prata) · **Mestre da Palavra** (50, prata) · **Sábio da comunidade** (150, ouro) |
| Compartilhamento e resumos | Primeiro resumo (1, bronze) · **Escriba** (5, bronze) · **Cronista da fé** (20, prata) · Resumo que inspira (um resumo com 50 votos, prata) · **Evangelista digital** (um resumo com 500 votos, ouro) |
| Intercessão e oração | Mãos que oram (10 améns, bronze) · **Orador fiel** (50, bronze) · Guerreiro de oração (100, prata) · **Intercessor** (200, prata) · **Coluna de fogo** (500 améns + 10 pedidos atendidos, ouro) |

### O que falta definir

| # | Pergunta | Nossa sugestão (o que está no app hoje) |
|---|---|---|
| 1 | O que é uma **"resposta útil"**? | Uma resposta a um comentário que recebeu **3 ou mais votos "Edificante"** de outras pessoas, e mais "Edificante" do que "Fora de contexto". Outras opções: qualquer resposta com 1 voto (mais fácil de ganhar), ou só a resposta que o autor do comentário ou o pastor marcar como "útil" (precisaria de um botão novo). |
| 2 | Na "Coluna de fogo", **"pedidos atendidos"** são quais, e quantos? | **10 pedidos pelos quais a pessoa orou** (disse amém) e que depois foram marcados como atendidos. A outra leitura seria: pedidos **da própria pessoa** que foram atendidos. |
| 3 | "Evangelista digital" pede **um resumo com 500 votos**. Para o tamanho da igreja, isso pode nunca acontecer. Mantemos? | Manter 500 como meta "lendária" e ter a **"Resumo que inspira" (50 votos)** como degrau antes. Se preferir, baixamos para 100. |
| 4 | Ficam as **medalhas intermediárias** (tabela acima) ou só as 9 do exemplo? Querem uma de nível **Diamante** em cada categoria, como na ofensiva (365 dias)? | Ficar com as intermediárias; Diamante só se o cliente quiser uma meta ainda maior. |
| 5 | Conteúdo **removido pela moderação** (resposta escondida, resumo ou pedido tirado do ar) continua contando? | **Deixa de contar** para as próximas medalhas, mas a medalha já ganha **fica** com a pessoa. |
| 6 | Os nomes e ícones das medalhas intermediárias ("Primeira palavra", "Conselheiro", "Mãos que oram"…) estão bons? | Podem ser trocados à vontade. |
| 7 | Pedidos de oração e resumos **publicam na hora**, sem aprovação do admin (o admin pode tirar do ar depois). Como isso vale medalha, alguém poderia publicar resumos fracos só para ganhar. Preferem que o **resumo passe pelo admin** antes de valer? | Publicar na hora. O resumo precisa ter pelo menos 100 caracteres, e é um por pessoa por aula. Se aparecer abuso, ligamos a aprovação do admin. |
| 8 | **Rankings** (quem tem mais medalhas, mais améns…) entram agora? Ficam por célula ou por igreja (seção 1)? | Ainda não: as medalhas não dependem disso. Rankings entram junto com os desafios (T31). |

---

## 7. Desafios (T31)

**Pedido do cliente:** o admin cria desafios (ex.: "assistir 5 aulas em 7
dias", "ler 1 capítulo por dia"); o fiel entra e acompanha o progresso.
Opcional: ranking por célula.

### O que já foi feito

- **No admin (menu "Desafios"):** criar, editar, pausar e excluir
  desafios. Para cada um, a equipe escolhe:

| Campo | Opções |
|---|---|
| **O que conta** | Aulas concluídas · Dias acessando o app · Comentários nas aulas · Resumos publicados · Améns em pedidos de oração · **"Marcar como feito"** (a própria pessoa confirma o dia — para o que o app não mede, como ler a Bíblia, jejum ou culto presencial) |
| **Meta** | **Total no período** ("Concluir 5 aulas em 7 dias") ou **todo dia** ("Concluir 1 aula por dia durante 7 dias") |
| **Período** | **Começa quando cada pessoa entra** (ex.: 7 dias a partir de quando entrou; dá para limitar até quando aceita inscrições) ou **datas fixas para todos** (ex.: "Semana de oração, de 10/10 a 17/10") |

  A tela mostra como o fiel vai ler o desafio enquanto a equipe preenche,
  e dá para **avisar todos os fiéis** quando um desafio novo sai.
- **Para o fiel (menu "Desafios"):** desafios abertos com o botão
  "Participar", os seus em andamento com a barra de progresso (nos
  diários, uma bolinha por dia), "Fiz hoje" nos de marcar como feito,
  "Sair do desafio" e o histórico de concluídos. A **home** mostra os
  desafios em andamento (ou o convite quando há desafios abertos).
- **O progresso é contado sozinho** com o que a pessoa faz no app — inclusive
  o que ela já fez hoje antes de entrar. Ao completar, ela recebe o aviso
  "Desafio concluído! 🏆".
- Cada desafio mostra **quantos participam e quantos já concluíram**.
- **Medalhas por desafios concluídos** (nova categoria "Desafios"):
  Primeiro desafio (1, bronze), Desafiante (5, prata) e Incansável (15,
  ouro).

### O que falta definir

| # | Pergunta | Nossa sugestão (o que está no app hoje) |
|---|---|---|
| 1 | **"Ler 1 capítulo por dia":** hoje é a própria pessoa que marca "Fiz hoje" (o app ainda não tem a Bíblia). Quando a Bíblia integrada (T36) e os planos de leitura (T37) existirem, o app passa a contar sozinho. Tudo bem por enquanto? | Sim: "Marcar como feito" até a T36/T37 |
| 2 | **Ranking por célula:** depende do cadastro de célula (T22, seção 3) e do modelo de um site por igreja (seção 1). Por enquanto mostramos só "N participantes · M concluíram". Quer ranking? Por célula, por igreja ou os dois? Mostra nomes ou só posições? | Esperar a T22; quando entrar, ranking por célula mostrando só o **número de pessoas que concluíram** em cada célula (sem expor quem não conseguiu) |
| 3 | O que mais um desafio deveria **contar**? Ex.: concluir um **curso inteiro**, assistir a **live** (ao vivo), **convidar** alguém, pedidos de oração feitos. | Começar com as 6 opções atuais e acrescentar conforme os desafios que a igreja quiser fazer |
| 4 | **Prêmio** ao concluir: além do aviso e das medalhas, vale algo? (ex.: moedas da T39, certificado, menção no culto) | Por enquanto aviso + medalhas; moedas entram com a T39 |
| 5 | A pessoa pode **refazer** um desafio que já concluiu (ou que perdeu)? | Hoje não: um por pessoa. Para repetir, a equipe cria uma nova edição (ex.: "Semana de imersão — novembro") |
| 6 | Nomes e números das **medalhas de desafios** (Primeiro desafio, Desafiante, Incansável — 1, 5 e 15) estão bons? | Podem ser trocados à vontade, sem nova versão |
| 7 | Quem pode **criar desafios**: só o admin ou também líderes de célula? | Só o admin por enquanto |

---

## 8. Contribuição em missões (T32)

**Pedido do cliente:** o admin cadastra projetos missionários (nome,
descrição, foto, valor previsto de gasto, data de fim); o app mostra os
projetos ativos com o valor arrecadado e uma barra de progresso; o fiel
contribui com o valor que quiser por **Pix ou cartão**; o fiel vê o
histórico das contribuições dele e o admin tem um relatório.

**Situação:** **feito no ambiente de teste** (sem dinheiro de verdade),
com as decisões da equipe de 22/10/2026:

- O dinheiro é recebido pela **equipe** (mesma conta do Asaas das
  assinaturas) e entra no controle financeiro e na divisão com a igreja
  (perguntas 1, 2, 3, 11 e 13).
- Contribuição **avulsa**, com o **valor que a pessoa quiser** (mínimo de
  R$ 5), por **Pix ou cartão** (perguntas 4, 5 e 6).
- **Quadro de apoiadores** na página do projeto: nome curto ("Maria L."),
  mensagem e, se o projeto quiser, o valor; quem preferir aparece como
  "Apoiador anônimo" (pergunta 7).
- Projeto continua recebendo até a data de fim e encerra sozinho
  (pergunta 8); ofertas recebidas fora do app podem ser lançadas e somam na
  barra (pergunta 10); só missões, sem dízimo (pergunta 12).

Sobra para conversar com a igreja: quais projetos entram, as fotos e
textos de cada um e as atualizações do campo (pergunta 9, segunda etapa).
A proposta original segue abaixo como referência.

### Como propomos fazer

| Parte | Como fica |
|---|---|
| **Admin → Missões** | Cadastro do projeto: nome, descrição, foto, valor previsto, data de fim e situação (rascunho, ativo, encerrado). Cada projeto mostra o arrecadado, o que está aguardando pagamento e quantas contribuições teve. |
| **Relatório no admin** | Lista das contribuições com data, projeto, nome de quem contribuiu (a tesouraria vê mesmo quando a pessoa pediu anonimato), valor, forma de pagamento e situação. Filtro por projeto, situação e período, totais, e **exportação para Excel (CSV)** para a tesouraria. |
| **Fiel → Missões** (menu novo) | Cartões dos projetos ativos com foto, "R$ 3.200 de R$ 10.000", barra de progresso, dias restantes e número de contribuições. Projetos encerrados aparecem abaixo, com o total final. |
| **Contribuir** | Valores sugeridos (ex.: R$ 20, 50, 100) ou outro valor, Pix ou cartão, opção "contribuir sem o meu nome" e uma mensagem opcional. A pessoa vai para a **página segura de pagamento do Asaas** (QR Code do Pix ou dados do cartão) — o app nunca vê os dados do cartão. |
| **Confirmação** | Quando o Asaas confirma o pagamento, a barra do projeto sobe sozinha e a pessoa recebe "Obrigado pela sua contribuição 🙏". Pagamento não feito (Pix vencido, cartão recusado) não entra na soma. |
| **Minhas contribuições** | Histórico do fiel com a situação de cada uma (aguardando, confirmada, vencida, estornada), o link "Pagar agora" para as pendentes e o comprovante do Asaas para as pagas. |
| **CPF** | O Asaas exige o CPF de quem paga. Reaproveitamos o da assinatura (T15), se a pessoa já assina; senão, ela informa na primeira contribuição. |

- Usa a **mesma integração com o Asaas** das assinaturas (T15). Dá para
  construir e testar no **ambiente de teste do Asaas** (sem cobrança de
  verdade) antes de ligar; **dinheiro de verdade só depois da virada da
  T15 para produção** (seção 2).
- **Esforço estimado:** cerca de 1 semana.

### O que falta definir

| # | Pergunta | Nossa sugestão |
|---|---|---|
| 1 | O dinheiro de missões cai na **mesma conta do Asaas** das assinaturas ou numa **conta (ou subconta) separada**, para não misturar com o restante? | Subconta só de missões, se a contabilidade da igreja separar os recursos |
| 2 | A contribuição precisa de **recibo de doação** ou **nota fiscal**? | Decisão do contador; o comprovante do Asaas já serve como recibo simples |
| 3 | As **taxas do Asaas** (Pix e cartão) ficam por conta da igreja, ou oferecemos ao fiel a opção "quero cobrir a taxa"? | Igreja absorve; a opção de cobrir a taxa pode vir depois |
| 4 | Quais os **valores sugeridos** e o **valor mínimo**? | R$ 20, R$ 50 e R$ 100; mínimo de R$ 5 (mínimo do Asaas) |
| 5 | Aceita **boleto** também, ou só Pix e cartão (como no pedido)? | Só Pix e cartão |
| 6 | Vale ter **contribuição mensal recorrente** (ex.: R$ 30 todo mês para um missionário), ou só avulsa? | Começar só com avulsa |
| 7 | O que fica **público** para os fiéis: o valor arrecadado e o número de contribuições? Querem um **mural de agradecimento** com os nomes de quem contribuiu? | Mostrar valor e número de contribuições; sem lista de nomes |
| 8 | Projeto que **bateu a meta** continua recebendo? E ao chegar a **data de fim**, encerra sozinho? | Continua recebendo até a data de fim (mostrando "meta alcançada 🎉"); na data de fim, encerra sozinho |
| 9 | A equipe vai querer postar **atualizações do projeto** (fotos e notícias do campo, prestação de contas) na página? | Sim, numa segunda etapa |
| 10 | Ofertas recebidas **fora do app** (no culto, Pix direto para a igreja) devem **somar na barra**? | Sim: um botão no admin para lançar esses valores à mão |
| 11 | **Quem acessa o relatório**: só o admin ou também um perfil de **tesoureiro** (vê as contribuições, mas não mexe no resto do admin)? | Começar com o admin; o perfil de tesoureiro pode vir depois |
| 12 | O escopo é **só missões**, ou já pensam em **dízimos e ofertas gerais** pelo app? | Só missões agora; dízimos mudam o desenho (privacidade, recorrência) e ficam para outra conversa |
| 13 | Com **um site por igreja** (seção 1), cada igreja tem os seus projetos e a sua conta? Existe projeto **compartilhado** entre as igrejas (ex.: missão da sede)? | Cada igreja com os seus projetos e a sua conta |

---

## 9. Devocionais enviados pelos fiéis (T33)

**Pedido do cliente:** formulário com limite de caracteres (título, texto
base, tema e autor); líderes dão nota aos devocionais enviados; acima de
uma nota mínima (configurável), o devocional entra numa **agenda
automática** de publicação, um por dia; página "Devocional do dia".

### O que já foi feito

- **Enviar um devocional** (menu "Devocional"): título, **texto base**
  (a referência, ex.: Salmos 23:1-3, e opcionalmente o trecho do
  versículo), **tema** (Fé, Esperança, Amor, Família, Oração, Gratidão,
  Perdão, Propósito, Ansiedade e paz, Santidade, Serviço, Outro) e o
  texto (de 200 a 2.500 caracteres). O **autor** é quem envia, com o nome
  do perfil. No máximo 3 em avaliação por pessoa.
- **Avaliação dos líderes:** na mesma página, quem é líder vê os
  devocionais esperando avaliação e dá **nota de 1 a 5 estrelas** (pode
  trocar ou tirar a nota; não avalia o próprio). Para não influenciar,
  o líder vê só quantas avaliações já existem, não a média.
- **Agenda automática:** com **3 avaliações** e **média a partir de 4**,
  o devocional é aprovado sozinho e entra na **próxima data livre** (um
  por dia). O autor recebe o aviso "Seu devocional foi aprovado! Será o
  devocional do dia em 15/10". Os dois números são configurados pelo
  admin.
- **Devocional do dia:** página com o devocional de hoje (tema, título,
  texto base em destaque, texto e autor), os anteriores e "Meus
  devocionais" (em avaliação, agendado com a data, publicado ou não
  publicado com o motivo). Também aparece no **topo da home** e o
  **lembrete diário** (T23) passa a ser "Devocional do dia 🙏 — *título*".
- **Admin → Devocionais:** a regra da agenda (avaliações e média
  mínimas), e as abas Em avaliação (com média e número de avaliações),
  Agenda, Publicados, Recusados e Removidos. O admin pode **aprovar
  direto**, **recusar** com motivo (o autor vê), **trocar a data**,
  **voltar para avaliação** ou **tirar do ar**.
- **Agenda vazia:** se nenhum devocional estiver agendado para o dia, o
  app mostra o último publicado (com a data dele) e o admin vê um aviso.

### O que falta definir

| # | Pergunta | Nossa sugestão (o que está no app hoje) |
|---|---|---|
| 1 | **Quem são os líderes que avaliam?** | A equipe (admin) e quem tem uma **função de liderança** na igreja (cadastro de funções da T12, ex.: Pastor, Diácono, Líder de célula) — a mesma regra do filtro "Líderes" nos comentários |
| 2 | Qual a **nota mínima** e **quantas avaliações** são precisas? | Média a partir de **4** com pelo menos **3** avaliações (o admin muda quando quiser) |
| 3 | A nota é de **1 a 5 estrelas**, ou preferem outro formato (ex.: aprovar/reprovar)? | 1 a 5 estrelas |
| 4 | Devocional com **média baixa**: fica esperando mais avaliações ou é recusado sozinho? | Fica em avaliação; quem recusa (com motivo) é o admin |
| 5 | Os líderes devem poder **deixar um comentário** para o autor (sugestões de melhoria)? | Hoje não; só o admin manda o motivo ao recusar |
| 6 | O admin pode **corrigir o texto** (ortografia, tamanho) antes de publicar? | Hoje não: só aprovar ou recusar. Podemos liberar a edição pelo admin |
| 7 | Os **limites** estão bons: título até 100 caracteres, devocional de 200 a 2.500, trecho do versículo até 600, até 3 em avaliação por pessoa? | Sim |
| 8 | A **lista de temas** está boa? | Pode ser trocada à vontade |
| 9 | O nome do **autor** aparece sempre? Querem a opção de publicar **sem o nome**? | Sempre com o nome (o pedido cita o autor) |
| 10 | **Agenda vazia:** repetir o último devocional (como hoje) ou ter um **banco de devocionais da igreja** (cadastrados pelo admin) para cobrir os dias vazios? | Repetir o último; o admin pode enviar devocionais pelo mesmo formulário e aprovar direto |
| 11 | Existem **datas especiais** (Páscoa, Natal) em que a igreja quer escolher o devocional? | O admin já consegue trocar a data de um devocional agendado |
| 12 | **Versões da Bíblia** citadas no trecho do versículo (NVI, ARA…) têm direitos autorais. A igreja tem alguma orientação (ex.: usar só a Almeida)? | Deixar livre e ver com a T36 (Bíblia integrada) |
| 13 | O devocional do dia vai também para o **WhatsApp** (T38)? | Sim, quando a T38 for feita |

---

## 10. Biblioteca de livros digitais (T34)

**Pedido do cliente:** o admin cadastra livros (capa, autor, descrição e
o arquivo PDF ou ePub); o fiel lê **dentro do app**; o acesso pode depender
do plano de assinatura. O próprio pedido já avisa: **confirmar os direitos
autorais de cada livro**.

**Situação:** ainda **não foi feito**. Abaixo, como propomos fazer e o
que precisamos do cliente antes — a começar pelos direitos dos livros,
que decidem o que pode entrar.

### Como propomos fazer

| Parte | Como fica |
|---|---|
| **Admin → Biblioteca** | Cadastro do livro: título, autor, descrição, categoria, capa, arquivo (PDF ou ePub), situação (rascunho ou publicado) e acesso (livre para todos ou só assinantes). A lista mostra quantas pessoas estão lendo e quantas terminaram cada livro. |
| **Onde o arquivo fica** | No **armazenamento privado** do Supabase, o mesmo que já guarda os vídeos das aulas. O livro nunca fica num link público: cada leitura usa um link que vale só por algumas horas. |
| **Fiel → Biblioteca** (menu novo) | Estante com as capas, busca por título ou autor e filtro por categoria. Página do livro com capa, autor, descrição e o botão "Ler" (ou "Continuar lendo — página 47"). |
| **Leitor dentro do app** | **PDF:** passar página, zoom e ir para uma página (com o leitor de PDF da Mozilla, o mesmo do Firefox). **ePub:** o texto se ajusta à tela do celular, com tamanho de letra e modo claro/escuro. Nos dois, o app **guarda onde a pessoa parou** e reabre ali. |
| **Continue lendo** | Os livros em andamento, com a barra de progresso, na biblioteca e na home. |
| **Controle de acesso** | Livro "só assinantes" segue a mesma regra dos cursos pagos (T15): quem não assina vê a capa e a descrição, e o botão leva para os planos. Enquanto o app estiver no modo "livre", todos leem tudo. |
| **Extras** | Opção de desafio "terminar 1 livro no mês" (T31) e medalhas de leitura (T30). |

- **Limite importante:** o app não mostra botão de baixar e o link do
  arquivo expira, mas **quem lê pelo navegador recebe o arquivo** — uma
  pessoa determinada consegue copiá-lo. Proteção forte (DRM, como a dos
  e-books das lojas) não é possível num app web. Uma marca d'água com o
  nome do leitor desencoraja a cópia (pergunta 5).
- **Esforço estimado:** cerca de 1 semana.

### O que falta definir

| # | Pergunta | Nossa sugestão |
|---|---|---|
| 1 | **Quais livros** vão entrar e a igreja tem **autorização por escrito** para distribuí-los em formato digital? Livros de autores da própria igreja ou em domínio público são simples; livros comprados de editora, em geral, **não** podem ser colocados no app. | Começar só com livros da igreja (ou com autorização por escrito) e guardar a autorização de cada um |
| 2 | Os livros serão em **PDF, ePub** ou os dois? | Aceitar os dois; ePub quando houver, porque fica melhor no celular |
| 3 | **Acesso:** os livros são livres, só para assinantes, ou decide-se livro a livro? Há diferença entre os planos (individual, família, igreja)? | Decidir livro a livro (livre ou assinantes), sem diferença entre planos |
| 4 | Pode **baixar para ler sem internet**, ou só ler dentro do app? | Só ler dentro do app |
| 5 | Colocar **marca d'água** com o nome/e-mail do leitor nas páginas, para desencorajar a cópia? | Sim, se algum livro tiver restrição do autor/editora |
| 6 | Vai ter **venda de livro avulso** (pagar por livro), além da assinatura? | Não por enquanto (aumenta o escopo: pagamento pelo Asaas, recibo etc.) |
| 7 | Quais **categorias** da biblioteca? (ex.: Vida cristã, Estudo bíblico, Família, Liderança, Devocionais) | O admin cadastra as categorias |
| 8 | **Quantos livros** e de que **tamanho** (em MB)? O armazenamento tem uma parte grátis e, acima dela, custo conforme o volume. | Saber a lista para estimar o custo antes |
| 9 | Vai ter **audiobooks** também, ou só livros para ler? | Só livros para ler agora |
| 10 | Com **um site por igreja** (seção 1), cada igreja tem a sua biblioteca, ou existe um **acervo da sede** compartilhado entre todas? | Definir junto com a seção 1 |

---

## 11. Tamanho dos vídeos das aulas

Não é uma pendência do app: é uma orientação para quem grava e envia as
aulas. Fica anotada para conversarmos depois.

### Por que importa

Um vídeo gravado no celular ou na câmera sai com muito mais qualidade do que
uma aula precisa. Uma aula de 1 hora costuma ter de **1 a 4 GB**. Salvando
o mesmo vídeo de novo num tamanho menor ("comprimir"), ela fica com cerca de
**300 a 500 MB**, sem diferença que se perceba numa pregação ou aula.

| | Vídeo como sai da câmera (~2 GB por aula) | Vídeo comprimido (~400 MB por aula) |
|---|---|---|
| Aulas guardadas sem custo | cerca de 5 | cerca de 25 |
| Envio pelo painel do administrador | Demorado; pode falhar com internet fraca | Rápido |
| Fiel assistindo no 4G | Pode travar e gasta muito do plano de dados | Carrega rápido e gasta 4 a 5 vezes menos dados |

### Como seria feito

Com um programa gratuito (o **HandBrake**), no computador de quem cuida das
aulas, antes de enviar:

1. Abrir o vídeo no programa.
2. Escolher a opção pronta **"Fast 720p30"**.
3. Salvar e enviar pelo painel o arquivo novo (não o original).

São alguns minutos por aula. Se ajudar, preparamos um passo a passo com
imagens para a equipe.

### O que falta definir

| # | Pergunta | Nossa sugestão |
|---|---|---|
| 1 | **Quem** grava e envia as aulas? Essa pessoa consegue fazer a compressão antes de enviar? | Sim; mostramos o passo a passo uma vez |
| 2 | As aulas são gravadas em que aparelho (celular, câmera, transmissão ao vivo gravada)? | Saber para indicar a melhor configuração |
| 3 | Querem que o próprio app avise quando um vídeo enviado for muito grande? | Sim, um aviso simples (acima de 1 GB, por exemplo) |

---

## 12. Levar a plataforma a outras igrejas

**A ideia:** a plataforma que fizemos para a Manancial pode servir a outras
igrejas e missões — cada uma com o **próprio app, a própria marca e os
próprios fiéis**, sem misturar nada com a Manancial. Para a Manancial, isso
significa um produto que melhora mais rápido (cada melhoria pedida por uma
igreja chega a todas) e custos de manutenção divididos.

### Como funcionaria

| Ponto | Como seria |
|---|---|
| **Dados** | Cada igreja num ambiente separado (site e banco próprios). Os fiéis, cursos, contribuições e conversas da Manancial **nunca** ficam visíveis para outra igreja. |
| **Marca** | Cada igreja com o seu nome, logo, cores e endereço (ex.: app.igrejaexemplo.com.br). Nada da Manancial aparece no app de outra igreja. |
| **Conteúdo** | Os cursos e vídeos da Manancial continuam só da Manancial, a não ser que ela queira compartilhar algum. |
| **Melhorias** | O que for melhorado para qualquer igreja chega também à Manancial, sem custo a mais. |
| **A Manancial** | Continua do mesmo jeito; nada muda para os fiéis daqui. |

### O que falta definir

| # | Pergunta | Nossa sugestão |
|---|---|---|
| 1 | A igreja concorda que a equipe ofereça a plataforma a outras igrejas? | Sim, com as garantias acima por escrito |
| 2 | Podemos citar a Manancial como a **primeira igreja** a usar (nome, depoimento, prints sem dados de fiéis)? | Sim — é a melhor apresentação do produto |
| 3 | Algum conteúdo da Manancial (um curso, um devocional) poderia ser oferecido a outras igrejas? | Decidir caso a caso; por padrão, nada é compartilhado |
| 4 | Há igrejas parceiras ou filiais que a Manancial gostaria de indicar primeiro? | Começar por 1 ou 2 igrejas conhecidas |
| 5 | Isso muda o acordo atual (divisão do lucro das assinaturas da Manancial)? | Não: o que é da Manancial continua da Manancial; o acordo com cada igreja nova é separado |
| 6 | Relação com o **"Um site por igreja"** (seção 1): as filiais da Manancial entrariam do mesmo jeito, cada uma com o seu app? | A mesma base técnica serve aos dois casos; decidir junto com a seção 1 |
