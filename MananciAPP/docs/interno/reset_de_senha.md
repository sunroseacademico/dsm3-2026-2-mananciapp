# Senhas: login, "Esqueci minha senha" e troca de senha

Como o app protege as senhas e o porquê de cada regra. A lógica fica em
`php/conexao.php`, seção "SENHA E RECUPERAÇÃO DE SENHA".

Entregue em 19/10/2026 (commit `c523144`, revisado na rodada de segurança
até `54c9312`). O levantamento original, com o checklist completo de um
reset seguro e as alternativas avaliadas (código de 6 dígitos, CAPTCHA),
está no git nesse commit.

## 1. Regra de senha

`senha_validar_politica()`, usada no cadastro, no reset e na troca de
senha (uma regra só, para não divergirem):

- 8 a 72 caracteres (o bcrypt ignora o que passa de 72 bytes);
- pelo menos 1 maiúscula e 1 caractere especial;
- confirmação igual;
- **não pode estar em vazamentos conhecidos** (`senha_vazada()`, Have I
  Been Pwned por k-anonimato: só os 5 primeiros caracteres do SHA-1 saem do
  servidor). Se a API não responder em 3 s, a senha é aceita.
  `SENHA_CHECAR_VAZADAS=0` desliga.

Gravada com `password_hash()` em `IDF_SENHA_HASH`. Senha antiga em texto
puro (`IDF_SENHA`) vira hash no primeiro login.

## 2. Login (`login.php`)

Três contadores em `ID_LOGIN_TENTATIVA`, janela de 15 min:

| Contador | Limite | Contra o quê |
|---|---|---|
| E-mail + IP | 5 erros | Alguém chutando a senha de uma conta (sem trancar o dono, que está em outro IP) |
| Só o e-mail | 30 erros | Ataque distribuído em muitos IPs |
| Só o IP | 50 erros | Uma senha comum testada em muitas contas ("password spraying") |

Os limites por IP são folgados por causa do Wi-Fi da igreja e das redes de
celular (CGNAT), em que muita gente sai pelo mesmo IP. Login certo zera o
e-mail e o par e-mail + IP (o do IP fica). Conta pendente, negada ou
desativada não entra. A sessão só abre se for gravada em `ID_SESSAO`
(`auth_iniciar_sessao()`).

**Verificação em duas etapas (admins que ligaram, desde 27/10/2026):** a
senha certa não abre a sessão; `login_2fa.php` pede o código do app
autenticador ou um código de reserva. 5 minutos e 5 erros; depois volta
para a senha, e cada erro conta no limite do e-mail. "Esqueci minha senha"
não abre sessão (a pessoa entra de novo depois), então também passa pelo
código. Ligar e desligar: Admin → Minha segurança.

Cadastro: no máximo 30 por hora por IP (`CADASTRO_MAX_POR_HORA`).

## 3. "Esqueci minha senha"

```
login.php → esqueci_senha.php (informa o e-mail)
   resposta SEMPRE igual, exista ou não a conta
   servidor: token aleatório → guarda só o HASH → e-mail com o link
redefinir_senha.php?token=...
   GET: só valida, guarda o token na sessão e recarrega sem ele na URL
   POST (CSRF): nova senha → consome o token → grava → derruba as sessões
   → e-mail de confirmação → volta para o login
```

### Pedido (`esqueci_senha.php`, `recuperacao_pedir()`)

- **Mesma mensagem** para e-mail com e sem conta, e a página responde
  **antes** de consultar o banco e chamar a Brevo, para o tempo de resposta
  não denunciar.
- Só conta aprovada e ativa recebe o link; pendente, negada ou inativa não
  recebe nada.
- Limites: **3 por hora por e-mail**, **20 por hora por IP** (IPv6 pela
  faixa /64), **60 s** entre dois pedidos do mesmo e-mail, e um **teto do
  site inteiro** de 30 por hora e 100 por dia (protege a cota da Brevo,
  senão param todos os e-mails do app). Conferência e gravação numa
  transação com trava, para pedidos simultâneos não passarem juntos.
- A tabela de pedidos (`id_recuperacao_pedido`) guarda só o SHA-256 do
  e-mail.

### O link

- Token de 256 bits (`random_bytes(32)`); o banco guarda só o **SHA-256**.
  Se o banco vazar, os links não servem.
- Validade: **60 min** (30 min para conta de admin). Uso único; pedir um
  novo anula os anteriores.
- Montado com `SITE_URL_PUBLICA` (`site_url_publica()`), **nunca** com o
  `Host` da requisição: senão um pedido com `Host: site-do-atacante`
  mandaria ao dono da conta um link legítimo apontando para o atacante.
- SDK do OneSignal (JS de terceiros) não carrega nas telas de senha.

### Redefinir (`redefinir_senha.php`, `recuperacao_redefinir()`)

- **GET não gasta o token** (scanners de e-mail abrem os links sozinhos).
  Ele vai para a sessão e a página recarrega sem o token na URL
  (histórico, logs, `Referer`). Guardado na sessão, vale **15 min**
  (computador compartilhado).
- Link inválido, vencido ou usado: a mesma mensagem genérica.
- POST com CSRF; numa transação: consome o token (só se ainda válido),
  grava a senha, anula os outros links, **revoga todas as sessões** da
  conta e limpa as tentativas de login.
- E-mail de confirmação ("sua senha foi alterada; se não foi você...").
  Se a conta é de admin, **os outros admins são avisados**.
- Não há checagem de "igual à senha atual": deixaria quem tem o link
  testar chutes da senha atual sem limite.
- Registro: IP do pedido, IP e navegador de quem usou o link.

## 4. Troca de senha logado (Configurações)

`senha_trocar()`: exige a senha atual (erros contam no limite do login),
aplica a mesma regra, anula links de reset pendentes, **derruba as outras
sessões** (a atual continua) e manda o e-mail de aviso.

## 5. Pelo admin

- **"Enviar link de nova senha"** em Gerenciar Usuários
  (`recuperacao_enviar_pelo_admin()`): o link vai para o e-mail **do
  fiel**; o admin nunca vê o link nem escolhe a senha. Fora dos limites do
  pedido público, com 1 min de intervalo por fiel.
- Trocar o e-mail de um fiel anula os links mandados ao e-mail antigo
  (`recuperacao_invalidar_links()`).
- E-mail único sem diferenciar maiúsculas (índice em `LOWER(idf_email)`).

## 6. Banco

Seções do `database/bancosupabase.md`: **12** (`id_recuperacao_senha`),
**65** (`id_recuperacao_pedido` e índice; sem ela o link não é enviado) e
**66** (e-mail único sem diferenciar maiúsculas).

## 7. Como testar

1. E-mail aprovado → recebe o link, troca a senha, entra com a nova; a
   antiga não funciona mais e outro navegador logado é derrubado.
2. E-mail sem conta / conta pendente → mesma mensagem, nenhum e-mail.
3. Link usado duas vezes ou vencido → "inválido ou expirado".
4. Dois pedidos seguidos (após 60 s) → só o último link funciona.
5. Abrir o link várias vezes sem enviar → continua válido.
6. Senha fraca, vazada (ex.: `Senha@123`) ou confirmação diferente →
   recusada, o link continua valendo.
7. Bloqueado por erros de login → depois do reset consegue entrar.
8. 4º pedido do mesmo e-mail na hora → nada é enviado, mensagem igual.
9. E-mail de confirmação chega; conta de admin avisa os outros admins.
10. Celular e computador, tema claro e escuro.

## 8. Pendências

Estão em [pontos_gerais.md](pontos_gerais.md), seção 2: conferir se o
Render manda o IP real no `X-Forwarded-For`, se o rastreamento de cliques
da Brevo reescreve o link, se a resposta antecipada chega antes do envio no
Render, e o aparelho de invasor que continua inscrito no push.
