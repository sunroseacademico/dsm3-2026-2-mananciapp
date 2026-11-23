# LGPD (proteção de dados)

Como o app atende a Lei Geral de Proteção de Dados (Lei 13.709/2018) e o
que ainda depende de decisão ou de advogado. Feito em 27/10/2026. Código em
"LGPD" de `php/conexao.php`; banco na seção 82 do
[bancosupabase.md](../../database/bancosupabase.md).

**Não é parecer jurídico.** Os textos (Política de Privacidade e Termo de
Uso) precisam de revisão de um advogado antes da produção (item no fim).

---

## 1. Papéis

| Papel na lei | Quem | Por quê |
|---|---|---|
| **Controlador** | A igreja (Missão Evangélica Manancial da Esperança) | Decide para que os dados são usados |
| **Operador** | A equipe técnica | Trata os dados em nome da igreja (hospeda, mantém) |
| **Suboperadores** | Supabase, Render, Cloudflare, OneSignal, Brevo, Asaas, YouTube, Formspree | Cada um processa uma parte (lista na política) |
| **Encarregado (DPO)** | **A definir** — alguém da igreja ou da equipe | Atende os pedidos; o contato aparece na política (`CONTATO_EMAIL`) |

Ao vender para outras igrejas, cada igreja é a controladora dos dados dela e
a equipe continua operadora: precisa de um **contrato de tratamento de
dados (DPA)** com cada uma.

## 2. O que o app faz

| Exigência | Onde | Como |
|---|---|---|
| Transparência (art. 9) | `privacidade.php` (pública) | Quais dados, finalidade e base legal de cada uso, com quem compartilha, por quanto tempo, direitos. Link no cadastro, login, rodapé, Termo de Uso e Configurações |
| Consentimento (art. 7, I e 11, I) | `php/fiel/register.php` | Caixa obrigatória "Li e aceito os Termos e a Política, inclusive o uso dos meus dados de fé". Data gravada em `idf_privacidade_aceita_em` |
| Dado sensível: convicção religiosa (art. 11) | política, seção 3 | Consentimento específico no aceite e uso só para as finalidades descritas |
| Crianças e adolescentes (art. 14) | cadastro + `lgpd_validar_cadastro()` | A data de nascimento é **obrigatória** no cadastro; abaixo de 18 anos pede o nome completo do responsável e a autorização dele (`idf_responsavel_nome`, `idf_responsavel_consentiu_em`). Em Configurações a data não pode ser apagada nem virar a de um menor sem responsável (`lgpd_nascimento_bloqueio()`). A aprovação mostra a idade e o responsável. Menores **não aparecem** para outros fiéis no ranking da ofensiva, na lista de quem está online nem nas conquistas recentes, e a **foto** deles só aparece para eles mesmos e para o admin |
| Acesso e portabilidade (art. 18, II e V) | Configurações → Privacidade e dados → **Baixar meus dados** | Pede a senha; 1 vez a cada 10 min. Arquivo JSON com tudo o que tem `idf_id` no banco (lista tirada do schema real: tabela nova entra sozinha) e os pagamentos. Senhas, tokens, 2FA e a foto em base64 ficam de fora |
| Correção (art. 18, III) | Configurações | Conforme o tipo de fiel (Admin → Tipos de fiel); o resto pelo contato |
| Eliminação (art. 18, VI) | Configurações → **Excluir minha conta** | Pede a senha e digitar EXCLUIR. Ver seção 3. Configurações e Contato abrem mesmo para quem não assina, no modo "só assinantes" |
| Revogar consentimento (art. 18, IX) | Configurações → Notificações e lembrete | Push e e-mail desligáveis; excluir a conta encerra o resto |
| Registro dos pedidos (art. 37) | tabela `id_lgpd_pedido` | Cada exportação e exclusão (do fiel ou do admin), sem dado pessoal |
| Minimização e prazo (art. 15 e 16) | `site_manutencao_limpar()`, diária pelo agendador do lembrete | Seção 4 |
| Segurança (art. 46) | [seguranca.md](seguranca.md) | Senhas em bcrypt, HTTPS, sessões revogáveis, limites de tentativa, grupos de permissão, **registro de atividade do painel** e **verificação em duas etapas** para admins |
| Cookies | política, seção 7 | Só o cookie de sessão (essencial) e armazenamento local de preferências; nada de propaganda. Por isso não há banner de cookies |

## 3. Exclusão de conta

`lgpd_excluir_conta()` — usada pelo fiel (Configurações) **e pelo admin**
(Gerenciar Usuários → Excluir), com o mesmo resultado:

- **Sem histórico financeiro nem conteúdo público:** apaga a conta e tudo
  vai junto (as tabelas ligadas ao fiel têm `ON DELETE CASCADE`).
- **Com comentário respondido por outra pessoa ou devocional publicado:**
  anonimiza (o apagar em cascata levaria junto o que é de outros fiéis).
- **Com assinatura ou contribuição:** a lei fiscal manda guardar os
  registros de pagamento. A conta vira "Conta excluída", sem nome, e-mail,
  CPF, telefone, nascimento, endereço, foto ou senha, e o resto do que é
  dela é apagado. As contribuições ficam anônimas no quadro de apoiadores.
- Comentário com resposta de outra pessoa fica oculto e sem o texto
  (apagar levaria as respostas junto). Devocional já publicado fica, sem o
  nome do autor.
- Não deixa excluir: conta de **admin** (outro admin tira o acesso antes) e
  quem tem **assinatura ativa** (cancelar antes, para não seguir cobrando).
- O e-mail de confirmação sai antes da exclusão.

**Correção junto:** o "Excluir" do admin fazia `DELETE` direto, que levava
junto os pagamentos de assinaturas canceladas — o Financeiro perdia
entradas antigas. Agora passa pela mesma rotina.

## 4. Prazos de guarda

| Dado | Prazo | Onde |
|---|---|---|
| Conta e conteúdo | Enquanto a conta existir | — |
| Registros de acesso (IP, aparelho) em `id_sessao` | 7 meses depois de encerrada (Marco Civil, art. 15: mínimo de 6) | limpeza diária |
| Sessões PHP (`id_sessao_php`) | 7 dias parada | limpeza diária |
| Tentativas de login | 30 dias | limpeza diária |
| Notificações já lidas | 180 dias | limpeza diária |
| Registro de atividade do painel | 2 anos | limpeza diária |
| Pagamentos | Prazo fiscal (em geral 5 anos) | fica; anônimo se a conta for excluída |

## 5. Pendências

- [x] (27/10/2026) Rodar a **seção 82** do `bancosupabase.md` em homolog e produção (sem
  ela o cadastro funciona como antes, sem gravar o aceite).
- [ ] **Revisão jurídica** da Política de Privacidade e do Termo de Uso
  (principalmente: dado religioso, menores, imagem nas lives, transferência
  internacional). Ao mudar a política, trocar `PRIVACIDADE_VERSAO_ATUAL`.
- [ ] Definir o **encarregado (DPO)** e preencher `CONTATO_EMAIL` no Render.
- [x] (Pacote A: o Termo de Uso mudou de versão e o novo aceite grava a data da política) Contas criadas antes da seção 82 não têm `idf_privacidade_aceita_em`:
  decidir se pedem um novo aceite no próximo login (dá para reaproveitar o
  fluxo do Termo de Uso).
- [ ] Exclusão apaga os registros de acesso junto com a conta; o Marco Civil
  pede 6 meses. Avaliar com o advogado se vale guardar só IP + data,
  separados da conta.
- [ ] Contratos com os suboperadores: aceitar o DPA de cada serviço no
  painel deles (Supabase, Cloudflare, OneSignal e Brevo têm).
- [ ] Plano de resposta a incidente (avisar ANPD e titulares): base em
  [seguranca.md](seguranca.md).

## 6. Como testar

1. Cadastro com nascimento de 15 anos: aparece o bloco do responsável;
   sem nome ou sem a caixa, recusa. Com 30 anos, não aparece.
2. Sem marcar "Li e aceito", o cadastro não passa.
3. Configurações → Privacidade e dados → Baixar meus dados (pede a senha;
   de novo em menos de 10 min, recusa): baixa um JSON
   com a conta e as tabelas, **sem** `idf_senha_hash`, tokens e 2FA.
4. Excluir minha conta de um fiel de teste sem pagamentos: some do banco
   (`SELECT ... WHERE idf_id = X` vazio) e cai no login com o aviso.
5. Fiel de teste com uma contribuição: depois de excluir, a linha em
   `id_fiel` fica "Conta excluída" sem dados e a contribuição continua no
   Financeiro.
6. Admin tentando excluir a própria conta pelo fiel: recusa com o motivo.
7. `SELECT * FROM id_lgpd_pedido ORDER BY idlp_id DESC LIMIT 5;` mostra os
   pedidos.
8. Ranking da ofensiva e "pessoas online" com um fiel menor de idade logado:
   ele não aparece para outro fiel; aparece para o admin.
9. Cadastro sem data de nascimento: recusa ("Informe sua data de
   nascimento"). Em Configurações, apagar a data ou trocar para uma de
   menor de 18 sem responsável: recusa.
10. Foto de um fiel menor: outro fiel não a vê (aparecem as iniciais;
    `foto_perfil.php?id=X` dá 404); ele mesmo e o admin veem.
