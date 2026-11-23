# Como testar as assinaturas (Asaas Sandbox)

Guia para testar o fluxo de assinatura do MananciAPP **sem dinheiro de
verdade**, usando o ambiente de testes do Asaas (Sandbox). Como o código
funciona: [assinaturas_asaas.md](assinaturas_asaas.md).

> Links e nomes de botões conferidos na documentação do Asaas em
> outubro/2026. Se algum botão estiver com outro nome no painel, a função
> é a mesma.

---

## 1. Links úteis

| O quê | Link |
|---|---|
| Painel do Sandbox (login) | https://sandbox.asaas.com |
| API do Sandbox (vai em `ASAAS_API_URL`) | `https://api-sandbox.asaas.com/v3` |
| FAQ do Sandbox | https://docs.asaas.com/docs/faq-sandbox |
| Cartões de teste | https://docs.asaas.com/docs/testando-pagamento-com-cart%C3%A3o-de-cr%C3%A9dito |
| Confirmar pagamento via API (só sandbox) | https://docs.asaas.com/reference/confirmar-pagamento |
| Forçar vencimento via API (só sandbox) | https://docs.asaas.com/reference/forcar-vencimento |
| Ações de sandbox no painel (novidades) | https://docs.asaas.com/changelog/novas-a%C3%A7%C3%B5es-de-sandbox-para-testar-status-de-cobran%C3%A7as |
| Webhooks (conceitos) | https://docs.asaas.com/docs/sobre-os-webhooks |
| Assinaturas (conceitos) | https://docs.asaas.com/docs/assinaturas |
| App no Render (homolog) | https://mananciapp-render-homolog.onrender.com |

---

## 2. Configuração (uma vez só)

### 2.1. Conta e chave
1. Criar conta em https://sandbox.asaas.com (dados fictícios servem).
   Para cartão e confirmação pelo painel não precisa "concluir
   cadastro"; para **Pix** precisa (item 3).
2. Ícone de pessoa (canto superior direito) → **Integrações → Chaves de
   API → Gerar chave**. **Desmarcar** "Permitir operações de saque via
   API" e deixar sem data de expiração. Copiar a chave (`$aact_...`).
   - Chave sem uso por 3 meses é desabilitada — se o app parar de criar
     assinaturas depois de um tempo parado, gere outra.
3. **Opcional — só para testar Pix:** o menu Pix só libera depois de
   **concluir o cadastro** (Minha conta, dados fictícios) e a conta ser
   aprovada; aí, **Pix → cadastrar uma chave aleatória**. Sem chave Pix,
   a página de pagamento não gera o QR Code. Dá pra testar tudo sem Pix
   (cartão de teste ou confirmação pelo painel — seção 4).

### 2.2. Variáveis (Render → Environment, e `.env` pra rodar local)

| Variável | Valor |
|---|---|
| `ASAAS_API_URL` | `https://api-sandbox.asaas.com/v3` |
| `ASAAS_API_KEY` | a chave `$aact_...` |
| `ASAAS_WEBHOOK_TOKEN` | um valor aleatório longo (o mesmo do webhook, abaixo) |
| `ASSINATURA_TOLERANCIA_DIAS` | `5` |

### 2.3. Banco (Supabase → SQL Editor)
Rodar **só** as seções **41, 42, 43 e 44** do `database/bancosupabase.md`.
Nunca o arquivo inteiro: o começo dele apaga o schema.

### 2.4. Webhook (Integrações → Webhooks → Adicionar)
- **URL:** `https://mananciapp-render-homolog.onrender.com/webhook_asaas.php`
- **E-mail:** o seu (o Asaas avisa se o webhook falhar)
- **Versão da API:** v3 · **Tipo de envio:** Sequencial
- **Token de autenticação:** o mesmo `ASAAS_WEBHOOK_TOKEN` — **não**
  clicar em "Gerar Token" (gera outro valor, que não bate com o Render)
- Webhook ativo e fila de sincronização ativada
- **Eventos:** todos de **Cobranças** e de **Assinaturas** (só esses)

### 2.5. No app
**Admin → Assinaturas → Quem pode assistir** (a tela de assinatura fica
sempre disponível para o fiel; só o modo decide quem é barrado):
- **Modo:** "Livre" no dia a dia; "Conteúdo pago" e "Só
  assinantes" só nos cenários F e J.
- **Teste grátis (dias):** 0, exceto no cenário K.

O aviso amarelo "ambiente de TESTE (sandbox)" no topo dessa tela confirma
que nenhuma cobrança é real.

---

## 3. Antes de cada rodada de testes

1. Abrir o app uma vez e esperar carregar — o Render (plano free)
   hiberna, e as primeiras entregas do webhook podem falhar enquanto ele
   acorda.
2. No Asaas, **Integrações → Webhooks**: conferir que a fila **não** está
   interrompida (fica interrompida depois de 15 falhas seguidas — é só
   reativar).
3. Usar uma conta de **fiel** (não admin) — admin sempre tem acesso e
   não serve pra testar bloqueio.

---

## 4. Como "pagar" no sandbox

| Jeito | Como | Quando usar |
|---|---|---|
| **Pelo painel** (mais fácil) | Painel → **Cobranças** → abrir a cobrança → **Confirmar pagamento** / **Receber cobrança** | Qualquer forma de pagamento |
| **Cartão de teste** | Na página de pagamento, cartão `4444 4444 4444 4444`, validade futura qualquer, CVV `123` | Testar o fluxo real do cartão (aprovado) |
| **Cartão recusado** | Mastercard `5184 0197 4037 3151` ou Visa `4916 5613 5824 0741` | Testar pagamento recusado |
| **Via API** | `POST /v3/sandbox/payment/{id}/confirm` (exemplo abaixo) | Automatizar / quando o botão não aparece |
| **Forçar atraso via API** | `POST /v3/sandbox/payment/{id}/overdue` | Testar cobrança vencida sem esperar |
| **Pix de verdade (simulado)** | Uma 2ª conta sandbox "pagadora" paga o Pix copia-e-cola da fatura (seção 4.1) | Testar o fluxo do Pix ponta a ponta, sem "forçar" |

Nunca usar cartão real. O id da cobrança (`pay_...`) aparece no painel
do Asaas e na tabela `id_pagamento` (`idpg_asaas_payment_id`).

Exemplo via API (Git Bash; troque a chave e o id):

```bash
curl -X POST "https://api-sandbox.asaas.com/v3/sandbox/payment/pay_XXXX/confirm" \
  -H "access_token: \$aact_SUA_CHAVE" -H "User-Agent: MananciAPP"
```

O `\$` antes de `aact` evita que o shell troque `$aact...` por uma
variável vazia.

### 4.1. Pagar um Pix de verdade (duas contas sandbox)

"Confirmar pagamento" só muda o status da cobrança. Para o Pix passar
pelo caminho real (QR Code → pagamento → webhook), o Asaas exige **duas
contas sandbox**: a do app, que gera o QR Code, e uma **pagadora**, com
saldo, que paga. O pagamento é pela API — o painel do sandbox não tem
botão para pagar QR Code. Documentação:
https://docs.asaas.com/docs/testar-pagamento-de-qrcodes-pix

**Preparar a conta pagadora (uma vez só):**
1. Criar outra conta em https://sandbox.asaas.com, com **outro e-mail**
   (dados fictícios servem) e concluir o cadastro (Minha conta).
2. Dar saldo a ela: nessa conta, **Criar cobrança** (qualquer cliente,
   ex.: R$ 100,00, Pix ou boleto) → abrir a cobrança → **Confirmar
   pagamento**. O valor cai no saldo da conta pagadora.
3. Gerar a chave de API dela (Integrações → Chaves de API), também sem
   permissão de saque. **Não** usar essa chave no Render — é só pra pagar.

**A cada teste:**
1. No app, assinar normalmente → na página do Asaas escolher **Pix** →
   **Copiar Código** (o "Pix copia e cola", começa com `000201...`).
2. Pagar com a conta pagadora (Git Bash; troque a chave, o código e o
   valor do plano):

   ```bash
   curl -X POST "https://api-sandbox.asaas.com/v3/pix/qrCodes/pay" \
     -H "access_token: \$aact_CHAVE_DA_CONTA_PAGADORA" \
     -H "Content-Type: application/json" -H "User-Agent: MananciAPP" \
     -d '{"qrCode": {"payload": "COLE_AQUI_O_CODIGO_PIX"}, "value": 10.00}'
   ```

3. Resposta com `"status"` da transferência = pagamento enviado. Em
   segundos a cobrança fica **Recebida** na conta do app, o webhook
   chega com `PAYMENT_RECEIVED` e a forma de pagamento aparece como
   **PIX** em `id_pagamento`.

Erros comuns: **404** = a conta do app não tem chave Pix cadastrada ou o
QR Code foi gerado antes dela (assine de novo depois de cadastrar a
chave); **saldo insuficiente** = repetir o passo 2 do preparo na conta
pagadora; código copiado com espaço ou quebra de linha também falha.

---

## 5. Cenários de teste

Para cada cenário: **passos → o que deve acontecer**. Onde conferir está
na seção 6.

### A. Assinar e pagar (caminho feliz)
1. Fiel → menu do avatar → **Assinatura** → plano **Mensal** → CPF de
   teste `529.982.247-25` → **Assinar**.
2. Abre a página de pagamento do Asaas (sandbox). Pagar com cartão de
   teste ou confirmar pelo painel.
3. Voltar ao app → **Assinatura**.

✅ Status **Ativa**, "Acesso garantido até" = vencimento + 1 mês + 1 dia.
No Asaas: cliente e assinatura criados. Webhook com resposta **200**.

### B. Webhook atrasado (rede de segurança)
1. Em Integrações → Webhooks, **desativar** o webhook temporariamente.
2. Fazer o cenário A com outra conta de fiel.
3. Voltar ao app → **Assinatura** (ou clicar em "Já paguei — atualizar").

✅ Mesmo sem webhook, a tela consulta o Asaas e mostra **Ativa**.
Reativar o webhook depois.

### C. Duplo clique / duas abas
1. Com uma conta sem assinatura, abrir **Assinatura** em duas abas e
   clicar **Assinar** nas duas quase ao mesmo tempo.

✅ Só **uma** assinatura no Asaas para esse cliente; a segunda aba cai na
mesma página de pagamento.

### D. Pagamento atrasado
1. Assinar (cenário A) **sem pagar**.
2. Forçar o vencimento: `POST /v3/sandbox/payment/{id}/overdue` (ou
   botão equivalente no painel).

✅ Status **Pagamento atrasado**, botão **Pagar agora** aparece. Quem já
tinha período pago continua com acesso até a data + tolerância.

### E. Cancelar
1. Com assinatura **Ativa** → **Assinatura** → **Cancelar assinatura**.

✅ Status **Cancelada**, "Acesso garantido até" **não muda** (o fiel usa
até o fim do que pagou). No Asaas a assinatura some/fica inativa e não
gera novas cobranças. Dá pra assinar de novo depois.

### F. Modo "Conteúdo pago" (freemium)
1. Admin → Assinaturas → Quem pode assistir → **Conteúdo pago** → Salvar.
2. Admin → Cursos → marcar um curso como **Curso gratuito**.
3. Entrar com um fiel **sem** assinatura:
   - abrir um vídeo de curso pago;
   - abrir um vídeo do curso gratuito;
   - ver os cards da home/biblioteca.
4. Entrar com o fiel do cenário A (Ativa).
5. Voltar para **Livre** no fim.

✅ Sem assinatura: vídeo pago redireciona para **Assinatura** ("exclusivo
para assinantes"); curso gratuito abre normal; página do curso mostra
"Assine para assistir"; cards sem prévia em vídeo (só capa). Com
assinatura: tudo abre. Admin nunca é bloqueado.

### G. Modo "Livre"
1. Admin → Assinaturas → Quem pode assistir → **Livre** → Salvar.
2. Entrar com um fiel sem assinatura.

✅ Navega e assiste tudo normalmente. O item "Assinatura" continua no
menu e no Perfil, para quem quiser assinar.

### H. Cortesia
1. Admin → Assinaturas → **Dar acesso cortesia** → e-mail de um fiel sem
   assinatura + data futura.
2. Com o modo "Conteúdo pago" ou "Só assinantes", entrar com esse fiel.

✅ Fiel assiste normalmente; na tela Assinatura aparece "Acesso
cortesia até ...". Na lista do admin, status **Cortesia**. Apagar a data
(campo vazio + ✓) remove o acesso.

### I. Plano Família / Igreja
1. Admin → Assinaturas → **Planos** → ativar "Família" (marcar "Ativo" e
   Salvar).
2. Fiel A assina o Família e paga.
3. Fiel A → **Assinatura → Pessoas no meu plano** → e-mail do fiel B
   (conta já aprovada no app) → Adicionar.
4. Com o modo "Conteúdo pago", entrar com o fiel B.
5. Testar também: adicionar além do limite (4), e-mail inexistente,
   remover o fiel B.
6. Para o plano Igreja: marcar "Aceita CNPJ" e usar um CNPJ de teste
   válido (ex.: `11.222.333/0001-81`).
7. **Desativar** os planos de novo no fim, se os valores ainda não
   estiverem confirmados com o cliente.

✅ Fiel B assiste e vê "Você faz parte do plano Família de ...". Acima do
limite: mensagem de limite. Removido: perde o acesso (com o modo
"Conteúdo pago" ou "Só assinantes").

### J. Modo "Só assinantes" (barra no login)
1. Admin → Assinaturas → Quem pode assistir → **Só assinantes** → Salvar
   (o app pede confirmação).
2. Entrar com um fiel **sem** acesso (ex.: Maria).
3. Tentar abrir qualquer página pelo endereço (ex.:
   `php/fiel/cursos.php`).
4. Entrar com o fiel do cenário A (Ativa) e com o admin.
5. Voltar para **Livre** no fim.

✅ Sem acesso: logo depois do login aparece a tela cheia "Você ainda não
tem uma assinatura", sem o menu do app, só com os planos e o botão Sair.
Qualquer outra página volta para essa tela. Com assinatura (ou admin):
navega normal. Quem tem cobrança pendente vê "Falta só o pagamento" com
o botão **Pagar agora**.

### K. Teste grátis
1. Admin → Assinaturas → modo **Conteúdo pago** ou **Só assinantes** +
   **Teste grátis (dias)** = 7 → Salvar.
2. Entrar com um fiel que **nunca** teve acesso (sem assinatura, sem
   cortesia) → tela de planos → **Começar teste grátis**.
3. Voltar à tela Assinatura com o mesmo fiel.
4. Entrar com o fiel do cenário A (já pagou).
5. Para simular o fim do teste (Supabase):
   `UPDATE id_assinatura SET idas_acesso_ate = NOW() - INTERVAL '1 day' WHERE idf_id = <id do fiel>;`
6. Voltar teste grátis para 0 e o modo para **Livre** no fim.

✅ Passo 2: vai direto para o início do app com acesso liberado; na
lista do admin, status **Teste grátis**. Passo 3: o botão de teste não
aparece mais (uma vez por pessoa) e mostra "Seu teste grátis vai até
...". Passo 4: o fiel que já pagou **não** vê o botão de teste. Passo 5:
no modo "Só assinantes", aparece "Seu teste grátis terminou" com os
planos (o teste não tem os 5 dias de tolerância).

### L. Segurança do webhook
No Git Bash:

```bash
# sem token -> 401
curl -i -X POST https://mananciapp-render-homolog.onrender.com/webhook_asaas.php \
  -H "Content-Type: application/json" -d '{"event":"PAYMENT_RECEIVED","payment":{"id":"pay_falso"}}'

# token errado -> 401
curl -i -X POST https://mananciapp-render-homolog.onrender.com/webhook_asaas.php \
  -H "asaas-access-token: errado" -d '{}'

# GET -> 405
curl -i https://mananciapp-render-homolog.onrender.com/webhook_asaas.php
```

✅ 401 / 401 / 405, e nada muda no banco.

Reenvio: no Asaas, **Integrações → Logs de Webhooks** → reenviar um
evento já entregue → resposta 200 e **nenhuma** linha nova em
`id_asaas_evento` nem acesso duplicado.

---

## 6. Onde conferir

**No app (admin):** Admin → **Assinaturas** — contadores do mês, lista de
assinantes com status e "acesso até".

**No Asaas:**
- **Cobranças** — cobranças geradas e status.
- **Meus Clientes** — cliente criado com `externalReference = fiel_{id}`.
- **Integrações → Logs de Webhooks** — cada envio e a resposta do app
  (200 = processado; 401 = token diferente; 500 = erro no app/banco).
- **Integrações → Logs de Requisições** — chamadas que o app fez à API.

**No Supabase (SQL Editor):**

```sql
-- assinaturas e acesso
SELECT f.idf_email, a.idas_status, a.idas_acesso_ate, p.idp_nome,
       a.idas_asaas_subscription_id, a.idas_cobranca_aberta_url
FROM id_assinatura a
JOIN id_fiel f ON f.idf_id = a.idf_id
LEFT JOIN id_plano p ON p.idp_id = a.idp_id
ORDER BY a.idas_atualizado_em DESC;

-- cobranças espelhadas do Asaas
SELECT idpg_asaas_payment_id, idpg_status, idpg_forma, idpg_valor_centavos,
       idpg_vencimento, idpg_pago_em
FROM id_pagamento ORDER BY idpg_atualizado_em DESC;

-- eventos recebidos pelo webhook
SELECT idae_tipo, idae_evento_id, idae_recebido_em
FROM id_asaas_evento ORDER BY idae_id DESC LIMIT 20;

-- interruptores do admin
SELECT * FROM id_configuracao;
```

**No Render:** serviço → **Logs**. Erros do fluxo aparecem com o prefixo
`assinatura_iniciar`, `asaas_aplicar_pagamento`,
`asaas_sincronizar_assinatura` ou `webhook_asaas`.

---

## 7. Problemas comuns

| Sintoma | Causa provável | O que fazer |
|---|---|---|
| Tela de Assinatura diz "ainda não estão disponíveis" | `ASAAS_API_KEY` vazia no Render | Conferir a variável e reiniciar o serviço |
| "Não foi possível iniciar a assinatura" | Chave inválida/expirada, CPF recusado pelo Asaas | Ver o log do Render (`assinatura_iniciar ...`); gerar outra chave |
| Clicar em Assinar não abre a página do Asaas | Navegador bloqueando o redirect (CSP) | Conferir que o deploy tem o `.htaccess` novo (`form-action ... https://*.asaas.com`) |
| Pix sem QR Code na página de pagamento | Conta sandbox sem cadastro concluído / sem chave Pix | Concluir cadastro (Minha conta) e cadastrar chave em Pix — ou testar com cartão/painel |
| Pagou e o app continua "Aguardando pagamento" | Webhook falhou (Render dormindo, token diferente) | Abrir a tela Assinatura de novo (ela consulta o Asaas); ver Logs de Webhooks |
| Logs de Webhooks com **401** | Token do painel ≠ `ASAAS_WEBHOOK_TOKEN` | Igualar os dois (sem espaços) |
| Logs de Webhooks com **404** | Deploy sem `webhook_asaas.php` ou URL errada | Conferir o deploy e a URL |
| Logs de Webhooks com **500** | Banco fora / tabela faltando | Ver log do Render; rodar seções 41–43 |
| Fila do webhook **interrompida** | 15 falhas seguidas | Corrigir a causa e reativar a fila no painel |
| Admin → Assinaturas diz "tabelas ainda não existem" | SQL não rodado | Rodar seções 41 a 44 |

---

## 8. Limpar depois dos testes

- **No app:** voltar "Quem pode assistir" para **Livre** e teste grátis para 0; desativar planos com valores
  ainda não confirmados; desmarcar cursos gratuitos de teste.
- **Dados de teste no banco** (só se quiser zerar — apaga tudo de
  assinaturas, inclusive cortesias):

  ```sql
  DELETE FROM id_asaas_evento;
  DELETE FROM id_assinatura;   -- apaga junto id_pagamento e beneficiários
  ```

- **No Asaas sandbox:** pode deixar como está; não interfere na produção.

---

## 9. Ir para produção (resumo)

1. Conta **real** no Asaas (https://www.asaas.com), cadastro concluído
   com os dados de quem recebe (CPF/CNPJ — decidir com o tesoureiro).
2. Gerar a chave de **produção** e trocar no Render: `ASAAS_API_URL` =
   `https://api.asaas.com/v3`, `ASAAS_API_KEY` = chave nova.
3. Criar o webhook **na conta de produção** (mesma URL, token novo e
   forte) e trocar `ASAAS_WEBHOOK_TOKEN`.
4. Considerar o plano pago do Render (o free hiberna e atrasa o webhook).
5. Assinar de verdade com uma conta própria e conferir o fluxo inteiro;
   cancelar e estornar pelo painel depois.
6. Só então escolher "Conteúdo pago" ou "Só assinantes" (depois de marcar cursos gratuitos
   e dar cortesias).
