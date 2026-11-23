# Assinaturas com o Asaas: como funciona

Como as assinaturas pagas funcionam no MananciAPP. O sistema está pronto e
roda no **sandbox** do Asaas (nenhuma cobrança real); o que falta para
cobrar de verdade está em
[assinaturas_virada_producao.md](assinaturas_virada_producao.md) e o roteiro
de teste em [teste_assinaturas_sandbox.md](teste_assinaturas_sandbox.md).

Substitui o antigo `plano_assinaturas_asaas.md` (o desenho de antes da
implementação, com esboços de código), que está no git. A numeração das
seções 4.2 e 4.3 foi mantida porque o código cita.

> Informações do Asaas conferidas em docs.asaas.com em outubro/2026.

---

## 1. Por que o Asaas

- Recebe em **CPF ou CNPJ** (não depende de a igreja ter CNPJ).
- **Pix, boleto e cartão** recorrente; assinatura pronta na API
  (`POST /v3/subscriptions` gera as cobranças sozinho).
- Página de pagamento hospedada pelo Asaas (`invoiceUrl`): o app nunca
  toca em dado de cartão.
- Documentação e suporte em português.

**Limite:** se o app for para a Play Store/App Store, assinatura de
conteúdo digital comprada dentro do app precisa passar pela loja. Aí entra
o RevenueCat (avaliado; está no git, commit `0bd52dd`). A regra de acesso
lê só o banco local, então outra origem de pagamento encaixa sem mudar o
resto.

## 2. Conceitos do Asaas

| Termo | No MananciAPP |
|---|---|
| **Customer** (`cus_...`) | 1 por fiel, criado na primeira assinatura |
| **Subscription** (`sub_...`) | 1 ativa por fiel |
| **Payment** (`pay_...`) | Cada cobrança do ciclo, com `status`, `dueDate` e `invoiceUrl` |
| **billingType** | Vem do plano: `UNDEFINED` (o fiel escolhe), ou só cartão |
| **cycle** | `MONTHLY` ou `YEARLY` |
| **externalReference** | `fiel_{idf_id}`, montado no servidor |

Criar a assinatura **não confirma pagamento**. Quem libera o acesso é o
webhook, depois de reconfirmar a cobrança na API.

| Forma | Primeiro mês | Meses seguintes |
|---|---|---|
| Cartão | Fiel paga na página do Asaas | O Asaas cobra sozinho |
| Pix / boleto | Fiel paga | O Asaas gera nova cobrança e avisa; o fiel paga de novo (por isso há tolerância) |

## 3. Visão do fluxo

```
Fiel                      MananciAPP (Render)                 Asaas
1. "Assinar" ─────────►  php/fiel/assinar.php
                           garante o customer ───────────►  POST /customers
                           cria a assinatura ────────────►  POST /subscriptions
                           busca a 1ª cobrança ◄─────────── invoiceUrl
2. vai para o invoiceUrl ◄─
3. paga ───────────────────────────────────────────────────►  confirma
                          webhook_asaas.php ◄──────────────── evento
                           confere o token, ignora repetido,
                           GET /payments/{id}, atualiza id_assinatura
4. páginas de conteúdo: regra de acesso lê só o banco local
```

## 4. Peça por peça

### 4.1. API

`asaas_api($metodo, $caminho, $corpo)` em `php/conexao.php`: header
`access_token`, `User-Agent` com o nome do app, timeout curto. A URL vem
de `ASAAS_API_URL` (sandbox se ficar em branco, para nunca cobrar por
engano).

### 4.2. Assinar: `php/fiel/assinar.php`

Menu do avatar → Assinatura (resumo também no Perfil).

- Lista os planos ativos (`assinatura_planos_ativos()`). **Valor e plano
  vêm sempre de `id_plano`**, nunca do formulário.
- Pede CPF (ou CNPJ, no plano que aceita) e valida
  (`assinatura_documento_valido()`).
- `assinatura_iniciar()` cria ou reaproveita o customer, cria a
  assinatura e manda o fiel para o `invoiceUrl` da 1ª cobrança. Duplo
  clique e duas abas não criam duas assinaturas (`UNIQUE (idf_id)` + trava
  na transação).
- "Minha assinatura": status, acesso até, **Pagar agora** (cobrança em
  aberto), **Cancelar** (`assinatura_cancelar()`; o fiel usa até o fim do
  que já pagou) e, nos planos Família/Igreja, as pessoas do plano
  (`assinatura_adicionar_beneficiario()` / `..._remover_...`).
- **Rede de segurança:** ao voltar da página de pagamento com a assinatura
  ainda pendente, `asaas_sincronizar_assinatura()` consulta a API direto
  (o webhook pode atrasar com o Render hibernando).

### 4.3. Webhook: `webhook_asaas.php` (raiz)

Cadastrado no painel do Asaas (*Integrações → Webhooks*) com o token de
autenticação igual a `ASAAS_WEBHOOK_TOKEN` (não é a API key).

1. Confere o header `asaas-access-token` com `hash_equals()`; errado → 401.
2. Grava o evento em `id_asaas_evento` (`UNIQUE` no id): repetido → 200 e
   sai.
3. `asaas_processar_evento()`: para eventos de cobrança, busca
   `GET /payments/{id}` e usa **o status da API**, não o do corpo.
4. `asaas_aplicar_pagamento()` atualiza `id_pagamento` e `id_assinatura`.
5. Só devolve erro (500) em falha transitória (banco ou API fora), quando
   reenviar resolve. Evento desconhecido → 200. Depois de **15 falhas
   seguidas o Asaas pausa a fila** até alguém reativar no painel.

| Evento | Efeito |
|---|---|
| Cobrança paga (`PAYMENT_RECEIVED` / `PAYMENT_CONFIRMED`) | `ATIVA`; acesso até vencimento + 1 ciclo + 1 dia. Cobrança antiga fora de ordem nunca encurta o acesso. |
| Cobrança vencida | `ATRASADA` só quando o período já pago acabou |
| Estorno / chargeback | Acesso recalculado só com as cobranças que continuam pagas (nunca aumenta); aviso no sininho dos admins |
| `SUBSCRIPTION_DELETED` / `SUBSCRIPTION_INACTIVATED` | `CANCELADA`; mantém o acesso até `idas_acesso_ate` |

### 4.4. Regra de acesso

`assinatura_fiel_tem_acesso()` em `php/conexao.php`:

- admin → sempre passa;
- fiel → passa se `idas_acesso_ate` + tolerância está no futuro
  (`ASSINATURA_TOLERANCIA_DIAS`, 5 hoje). Cortesia e teste grátis não
  têm tolerância;
- vale também para quem é **beneficiário** de um plano Família/Igreja com
  acesso em dia;
- um "sim" fica guardado na sessão por 1 min (não consulta a cada página).

A regra é **por data**, não por status: cancelar ou atrasar não tira o que
já foi pago.

Onde o bloqueio é aplicado: o vídeo (`assistir_video.php`,
`video_conteudo_acessivel()`), os comentários do vídeo, as prévias em vídeo
dos cards (`assinatura_previews_liberados()`) e `todos_videos.php`.
`curso.php` continua aberto como vitrine, com "Assine para assistir".
Cursos marcados como **gratuitos** ficam livres; vídeo solto (sem curso)
conta como pago.

## 5. "Quem pode assistir" e teste grátis

Admin → Assinaturas (valor em `id_configuracao`, lido por `site_config()`;
muda na hora, sem deploy):

| Modo | Efeito |
|---|---|
| **Livre** (hoje) | Todos assistem tudo; assinar é opcional |
| **Conteúdo pago** | Entra no app, mas sem acesso só assiste os cursos gratuitos |
| **Só assinantes** | Sem acesso, o fiel cai na tela de planos ao entrar (`auth_require()`) |

**Teste grátis:** N dias, uma vez por pessoa (status `TESTE`). 0 = sem
teste.

**Desfazer em produção:** voltar para **Livre** libera todo mundo na hora.

## 6. Admin

`php/admin/admin_assinaturas.php` (menu "Assinaturas"): números do mês,
criar/editar/ativar planos (o Asaas recusa abaixo de R$ 5,00), lista de
assinantes, cancelar, **acesso cortesia** com data
(`assinatura_definir_cortesia()`), modo de acesso e teste grátis. Aviso
amarelo no topo quando está no sandbox. Caixa "Curso gratuito" em
`admin_cursos.php` e `admin_editar_curso.php`.

## 7. Banco

Seções do [bancosupabase.md](../../../database/bancosupabase.md):

| Seção | O quê |
|---|---|
| 41 | `id_plano`, `id_assinatura`, `id_pagamento`, `id_asaas_evento` + plano Mensal (R$ 10,00) |
| 42 | Forma de pagamento e nº de beneficiários por plano, CPF/CNPJ do pagador, `id_curso.idc_gratuito`, `id_assinatura_beneficiario`, planos Anual/Família/Igreja **desativados** |
| 43 | `id_configuracao` (interruptores do admin) |
| 44 | Modo de acesso e teste grátis |

Valores em **centavos** (inteiro). Planos cadastrados e o que falta
confirmar: [assinaturas_virada_producao.md](assinaturas_virada_producao.md),
seção 2.

## 8. Variáveis de ambiente

| Variável | Para quê |
|---|---|
| `ASAAS_API_URL` | Sandbox (padrão) ou `https://api.asaas.com/v3` |
| `ASAAS_API_KEY` | Chave da API (**secreta**; sandbox e produção são diferentes). Sem ela, a tela de assinar mostra "ainda não disponível". |
| `ASAAS_WEBHOOK_TOKEN` | Token do webhook (**secreto**, diferente da API key). Sem ele, o webhook recusa tudo. |
| `ASSINATURA_TOLERANCIA_DIAS` | Dias de tolerância depois do vencimento |

`*.asaas.com` está liberado em `form-action` na CSP do `.htaccess`.

## 9. Segurança (o que o código garante)

- API key só no servidor; token do webhook diferente da API key.
- Webhook autenticado com `hash_equals()` e idempotente.
- Status sempre reconfirmado na API, nunca pelo corpo do webhook nem pela
  volta do fiel da página de pagamento.
- Valor e plano do servidor; assinatura ligada ao fiel **da sessão**.
- Admin nunca é barrado.
- CPF enviado ao Asaas só na hora de assinar (LGPD).

## 10. Limitações conhecidas

- Vídeos antigos na pasta local `videos/` são servidos direto pelo Apache
  e não passam pelo bloqueio.
- No plano Igreja o customer leva o nome de quem assinou (o CNPJ vai como
  documento); nota fiscal em nome da igreja pediria um campo de razão
  social.
- Mudar o valor de um plano não muda assinaturas já criadas no Asaas.
- Render free hibernando pode atrasar o webhook (ver "Antes da produção"
  em [pontos_gerais.md](../pontos_gerais.md)).

## 11. Melhorias futuras

- **Pix Automático:** o fiel autoriza uma vez no app do banco e as
  cobranças seguintes são debitadas sozinhas. É um fluxo de autorização à
  parte, com eventos próprios; bastaria uma coluna para o id da
  autorização em `id_assinatura` (a regra de acesso não muda). Ver
  docs.asaas.com → Pix Automático.
- Lembretes por push quando uma cobrança de Pix/boleto é criada ou vence.
- Alerta para o admin se o webhook começar a falhar.

## Referências

- Assinaturas: https://docs.asaas.com/docs/assinaturas
- Webhooks: https://docs.asaas.com/docs/sobre-os-webhooks
- Pix Automático: https://docs.asaas.com/docs/pix-automatico
