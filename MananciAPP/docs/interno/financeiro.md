# Financeiro da equipe

Como o app calcula o lucro e a divisão com a igreja. Tela **Admin →
Financeiro** (`php/admin/admin_financeiro.php`); funções em "FINANCEIRO DA
EQUIPE" de `php/conexao.php`; tabelas na seção 71 do
`database/bancosupabase.md`.

## Quem recebe o dinheiro

Desde 22/10/2026, toda a parte financeira fica com a equipe: a conta do
Asaas (por enquanto no CPF de um de nós, ainda no sandbox) recebe as
assinaturas (T15) e as contribuições em missões (T32). Depois dos lucros, a
ideia é dividir com a igreja (hoje 50%). Esta tela é o controle disso.

## Quem vê

Os **superadmins** veem tudo: admins cujo e-mail está em `SUPERADMIN_EMAILS` no
Render (separados por vírgula). Os admins da igreja não veem o menu
Financeiro nem a lista de contribuições com os nomes (em Admin → Missões).

Exceção liberada por um superadmin: em **Admin → Tipos de Admin**, o
superadmin pode marcar **Financeiro** e/ou **Contribuições** num tipo de
admin e, no Financeiro, escolher as partes que esse tipo vê (resumo e
divisão, "de onde veio", lançamentos, exportar planilha, regras da
divisão). Fica em `idag_telas` como `financeiro`, `financeiro.<parte>` e
`contribuicoes` (`admin_telas_restritas()` e `financeiro_partes()` em
`php/conexao.php`). "Acesso total" **não** inclui essas telas, e só
superadmin marca ou desmarca esses campos.

A lista fica numa variável do Render, e não no banco, para nenhum admin
conseguir se promover pelo painel. É também a base para separar, no futuro,
outras telas e campos entre **superadmin** (equipe) e **admin** (igreja):
basta perguntar a `auth_eh_superadmin()` no código. Para trocar quem é
superadmin, edite a variável no Render (vale no próximo carregamento de
página).

**Crie a conta antes de pôr o e-mail na lista.** O cadastro, a troca de
e-mail pelo fiel e Admin → Gerenciar Usuários recusam e-mails da lista (a não
ser que quem troca já seja superadmin), e só um superadmin promove uma conta
com e-mail da lista. Também só um superadmin remove, desconecta, edita ou
muda o tipo de admin de uma conta da equipe, e só ele põe alguém num tipo de
admin com Financeiro ou Contribuições. Na tela Tipos de Admin, "Super admin"
aparece só para quem está na lista.

## De onde vêm os números

| Origem | Onde fica | Conta no lucro? |
|---|---|---|
| Assinaturas pagas | `id_pagamento` (status RECEIVED, CONFIRMED ou RECEIVED_IN_CASH) | Sim, pelo **líquido** |
| Missões pagas pelo app | `id_missao_contribuicao` com `idmc_origem = 'app'` | Depende do modo (abaixo) |
| Ofertas de missão recebidas pela equipe fora do app | `id_missao_contribuicao` com `idmc_origem = 'manual'` e `idmc_recebido_por = 'equipe'` | Depende do modo |
| Ofertas de missão que a igreja recebeu direto | `idmc_recebido_por = 'igreja'` | Não: só somam na barra do projeto |
| Contribuições pelo app (oferta geral, aba Contribuir) | `id_missao_contribuicao` do projeto `missoes_oferta_geral` | Sim, pelo **líquido**, nos dois modos |
| Contribuições recebidas pela equipe fora do app | idem, `idmc_origem = 'manual'` e `idmc_recebido_por = 'equipe'` | Sim, nos dois modos |
| Contribuições que a igreja recebeu direto | idem, `idmc_recebido_por = 'igreja'` | Não: só informativo |
| Outras receitas (ajuda, doação…) | `id_financeiro_lancamento`, tipo `receita` | Sim |
| Custos (Render, Supabase, domínio…) | `id_financeiro_lancamento`, tipo `custo` | Sim, como despesa |
| Repasse a projeto de missão | `id_financeiro_lancamento`, tipo `repasse`, destino `missao` | Despesa no modo "no lucro"; sai do caixa de missões no modo "separado" |
| Repasse à igreja | `id_financeiro_lancamento`, tipo `repasse`, destino `igreja` | Não: é o pagamento da parte dela |

**Líquido** é o valor menos a taxa do Asaas (o `netValue` que o Asaas
informa em cada cobrança). As cobranças anteriores à seção 71 não têm esse
valor gravado: contam pelo bruto até alguém clicar em **Buscar taxas no
Asaas** (aparece um aviso quando há alguma assim no período).

A data que vale é a do **pagamento** (não a do vencimento). Cobrança
estornada sai da conta.

## Os dois modos de missões

Escolhidos em **Regras da divisão**, na própria tela:

- **Missões no lucro** (decisão atual): o líquido das missões entra na
  receita, junto com as assinaturas. O que for repassado a projetos de
  missão conta como despesa. A divisão é sobre tudo.
- **Missões separadas**: o lucro dividido é só assinaturas + outras
  receitas − custos. As missões viram um **caixa de missões** (o que entrou
  − o que foi repassado aos projetos), mostrado à parte.

A tela sempre mostra também quanto seria o lucro no outro modo, para
comparar.

A **oferta geral** (a oferta livre para manter o app, no topo da aba
Contribuir, Admin → Contribuições) é, por baixo, um projeto de missão
marcado em `id_configuracao` (`missoes_oferta_geral`), mas no Financeiro
ela tem linhas próprias (**Contribuições**) e entra sempre no lucro, nos
dois modos: é dinheiro para manter o app, não de missão. Na planilha
exportada aparece como "Contribuição".

## A divisão

```
lucro = receita líquida − despesas
parte da igreja = lucro × porcentagem   (só se o lucro for positivo)
parte da equipe = lucro − parte da igreja
```

Mês com prejuízo: a igreja recebe 0 e a equipe absorve o resultado.

**Falta repassar** (desde o começo) soma a parte da igreja **mês a mês** e
desconta os repasses à igreja já lançados. Um mês com prejuízo não
desconta dos outros. O cálculo usa a porcentagem e o modo **atuais** para
todos os meses: se a regra mudar, o histórico é recalculado com a regra
nova.

## Rotina sugerida

1. No começo de cada mês, lançar os custos do mês anterior (contas do
   Render, Supabase, Cloudflare, domínio…).
2. Abrir o mês anterior em Financeiro e conferir.
3. Transferir a parte da igreja e lançar como **Repasse → Igreja**.
4. Repasses a projetos de missão: lançar como **Repasse → Projeto de
   missão** (com o projeto, se for para um em especial).
5. **Exportar planilha** do período para guardar ou mandar ao contador.

## Limites conhecidos

- Os números dependem do que foi lançado: custo esquecido aumenta o lucro.
- Não há custo recorrente automático: cada mês é lançado à mão.
- Mudar a porcentagem ou o modo recalcula o passado (não há "regra por
  período").
- O Asaas ainda é o de testes: no sandbox, nada é dinheiro de verdade.
- Conta excluída (pelo próprio fiel ou pelo admin) com pagamento ou
  contribuição não some: vira "Conta excluída", sem dados pessoais, e as
  entradas continuam aqui (desde 27/10/2026; antes, excluir um usuário
  apagava os pagamentos de assinaturas canceladas). Ver [lgpd.md](lgpd.md).
