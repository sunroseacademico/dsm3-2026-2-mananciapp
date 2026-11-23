# Assinaturas — virada para produção (T15)

Situação da tarefa T15 do [quadro de progresso](../progresso_pedidos_cliente.md)
e tudo o que falta para começar a cobrar de verdade.

Montado em 07/10/2026.

---

## Resumo

- **O sistema de assinaturas está pronto no código**, integrado ao Asaas.
- **Tudo roda no ambiente de teste (sandbox)**: nenhum dinheiro de verdade
  é cobrado. O Render aponta para `https://api-sandbox.asaas.com/v3`.
- **O bloqueio de conteúdo está desligado**: hoje todo mundo assiste tudo
  (modo "Livre").
- **Para ir para produção faltam decisões do cliente** (seção 3) e alguns
  passos de configuração (seção 4). Não falta programar nada, exceto se
  alguma resposta do cliente pedir algo novo.

---

## 1. O que já funciona

| Parte | O que faz |
|---|---|
| **Tela do fiel** (menu do avatar → Assinatura) | Escolhe o plano, informa CPF (ou CNPJ no plano Igreja), paga por Pix, boleto ou cartão na página do Asaas, vê "Minha assinatura", paga uma cobrança em aberto e cancela. No plano Família/Igreja, o titular adiciona as outras pessoas. |
| **Liberação automática** | Quando o Asaas confirma o pagamento, o acesso é liberado sozinho (webhook). O sistema confere cada aviso direto na API do Asaas antes de liberar. |
| **Admin → Assinaturas** | Números do mês, criar/editar/ativar planos, lista de assinantes, cancelar, dar **acesso cortesia** (sem pagar). |
| **"Quem pode assistir"** (admin) | **Livre** (todos assistem; hoje), **Conteúdo pago** (quem não assina só vê os cursos gratuitos) ou **Só assinantes** (quem não assina cai direto na tela de planos ao entrar). |
| **Teste grátis** (admin) | N dias grátis, uma vez por pessoa (0 = sem teste). |
| **Cursos gratuitos** | Caixa "Curso gratuito" no cadastro do curso: fica liberado mesmo com bloqueio ligado. |
| **Atraso e cancelamento** | Quem atrasa continua assistindo por **5 dias** de tolerância. Quem cancela **continua até o fim do período já pago**. |

Detalhes técnicos: [assinaturas_asaas.md](assinaturas_asaas.md).
Roteiro de teste: [teste_assinaturas_sandbox.md](teste_assinaturas_sandbox.md).

---

## 2. Planos cadastrados hoje

Os planos já estão no banco. Só o Mensal está ativo; os outros entraram
**desativados**, esperando a confirmação dos valores.

| Plano | Cadastrado como | Forma de pagamento | Pessoas | Situação |
|---|---|---|---|---|
| Gratuito | Cursos marcados como gratuitos | — | — | Falta escolher os cursos |
| Mensal | R$ 10,00 por mês | Pix, boleto ou cartão | Só o titular | Ativo (no sandbox) |
| Anual individual | **R$ 19,90 por ano** | Só cartão | Só o titular | Desativado — valor a confirmar |
| Família | **R$ 39,00 por mês** | Pix, boleto ou cartão | Titular + 4 | Desativado — ciclo a confirmar |
| Anual igreja | R$ 290,00 por ano | Pix, boleto ou cartão (CNPJ aceito) | Titular + 100 | Desativado — a confirmar |

> **Atenção ao Anual individual:** se os R$ 19,90 do pedido forem **por
> mês**, o anual deveria custar R$ 238,80 (12 × 19,90) — e hoje ele está
> cadastrado como R$ 19,90 pelo ano inteiro. Ajustar no admin antes de
> ativar.

O Asaas não aceita assinatura abaixo de R$ 5,00 (o admin já bloqueia).
Mudar o valor de um plano **não muda** assinaturas já feitas — só vale
para quem assinar depois.

---

## 3. Perguntas para o cliente

Ficam no documento do cliente, junto com os outros pontos em aberto:
[pontos_para_alinhar.md](../../para_o_cliente/pontos_para_alinhar.md),
seção 2 (27 perguntas: valores, quem recebe, regras de acesso e
comunicação). A numeração da seção 6 abaixo segue a de lá.

---

## 4. Passos para subir (depois das respostas)

Na ordem. Os marcados com 👤 dependem do cliente ou de quem tem acesso às
contas.

1. **Testar tudo no sandbox** — cenários A a L do
   [roteiro de teste](teste_assinaturas_sandbox.md) (assinar, pagar,
   atraso, cancelar, cortesia, Família/Igreja, teste grátis, os três modos
   de acesso, segurança do webhook). Anotar o que falhar.
2. 👤 **Criar a conta de produção no Asaas** (https://www.asaas.com) no
   nome decidido na pergunta 12, com os documentos aprovados.
3. 👤 **Plano do Render:** hoje é o gratuito, que **hiberna**. Com ele
   dormindo, o aviso de pagamento do Asaas pode falhar; se falhar muitas
   vezes seguidas, o Asaas **para de enviar** e ninguém recebe acesso até
   reativar no painel. Recomendado: plano pago do Render antes de cobrar.
4. **Trocar as variáveis no Render** (Environment):
   - `ASAAS_API_URL` = `https://api.asaas.com/v3`
   - `ASAAS_API_KEY` = chave **de produção**
   - `ASAAS_WEBHOOK_TOKEN` = token novo e forte
   - `ASSINATURA_TOLERANCIA_DIAS` = resposta da pergunta 21
5. **Cadastrar o webhook na conta de produção** (mesma URL do sandbox,
   com o token novo).
6. **Ajustar e ativar os planos** no Admin → Assinaturas com os valores
   confirmados (perguntas 1 a 6).
7. **Marcar os cursos gratuitos** (pergunta 8) e configurar o **teste
   grátis** (pergunta 7).
8. **Dar cortesia** a quem não vai pagar (perguntas 19 e 20).
9. **Pagamento real de teste:** assinar com uma conta própria, conferir o
   acesso liberado, cancelar e **estornar pelo painel do Asaas**.
10. 👤 **Avisar os fiéis** com a antecedência combinada (perguntas 24 a
    26).
11. **Ligar "Quem pode assistir"** no modo escolhido (pergunta 18) — só
    depois dos passos 6 a 9.
12. Acompanhar os primeiros dias: Admin → Assinaturas e o painel do
    Asaas (cobranças e webhooks).

---

## 5. Cuidados conhecidos

- **Vídeos antigos** na pasta local `videos/` (de antes do Supabase) não
  passam pelo bloqueio. Conferir se ainda existe algum antes de ligar.
- **Pix e boleto têm inadimplência maior** que cartão: a tolerância
  (pergunta 21) e os lembretes do próprio Asaas ajudam.
- **Mudar o valor de um plano depois** não altera quem já assina.
- **Desfazer:** se algo der errado depois de ligar, voltar "Quem pode
  assistir" para **Livre** libera todo mundo na hora, sem deploy.

---

## 6. Respostas do cliente

Preencher conforme as respostas chegarem.

| # | Resposta | Data |
|---|---|---|
| 1 | | |
| 2 | | |
| 3 | | |
| 4 | | |
| 5 | | |
| 6 | | |
| 7 | | |
| 8 | | |
| 9 | | |
| 10 | | |
| 11 | | |
| 12 | Conta da equipe, por enquanto no CPF de um de nós (e no sandbox até a virada). Toda a parte financeira é da equipe; o lucro é dividido com a igreja (ver [financeiro.md](../financeiro.md)). | 22/10/2026 |
| 13 | | |
| 14 | | |
| 15 | | |
| 16 | | |
| 17 | | |
| 18 | | |
| 19 | | |
| 20 | | |
| 21 | | |
| 22 | | |
| 23 | | |
| 24 | | |
| 25 | | |
| 26 | | |
| 27 | | |
