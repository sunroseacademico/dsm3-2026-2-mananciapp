# Como o app está publicado (Render + Supabase + Cloudflare R2)

Como o MananciAPP roda hoje: servidor, banco, arquivos, variáveis e como
sobe uma versão nova. O que ainda falta configurar está em
[docs/interno/pontos_gerais.md](docs/interno/pontos_gerais.md).

Endereço da homologação: https://mananciapp-render-homolog.onrender.com

> Até 20/10/2026 este arquivo contava a história da migração (MySQL do
> InfinityFree → Postgres no Supabase, vídeos no Supabase Storage → R2).
> Essa versão está no git (commit `54c9312` e anteriores).

## 1. Visão geral

| Parte | Onde roda | Observação |
|---|---|---|
| Aplicação PHP (fiel, admin, AJAX, webhook) | **Render**, Web Service Docker, plano free | Hiberna após 15 min sem acesso |
| Banco de dados | **PostgreSQL no Supabase** (us-east-1) | Session pooler, porta 5432 |
| Vídeos das aulas e capas dos cursos | **Cloudflare R2**, bucket privado | URLs assinadas, sem chave no navegador |
| Fotos de perfil | No próprio banco (`id_fiel_foto`) | |
| Lives | **YouTube** (player embutido) | Ver [transmissao_ao_vivo.md](docs/interno/transmissao_ao_vivo.md) |
| E-mail | **Brevo** (API) | `enviar_email_sistema()` |
| Push | **OneSignal** | Ver [README.md](README.md#notificações-push-onesignal) |
| Assinaturas | **Asaas** (sandbox hoje) | Ver [assinaturas_asaas.md](docs/interno/assinaturas/assinaturas_asaas.md) |

## 2. Render e Docker

O Render não tem runtime nativo de PHP, então o app roda pelo
[Dockerfile](Dockerfile) da pasta `MananciAPP/`:

- base `php:8.2.35-apache` (versão fixada; trocar o número a cada correção de segurança do PHP) com `pdo_pgsql` e `curl`. A extensão `mysqli`
  **não** é instalada de propósito (ver seção 3);
- `php.ini-production` (erros só no log), cookie de sessão `HttpOnly`,
  `Secure`, `SameSite=Lax`, `use_strict_mode`, fuso de Brasília;
- `auto_prepend_file = php/sessao_banco.php`: com `SESSOES_NO_BANCO=1`, as
  sessões vão para o Postgres (sem isso, cada deploy desloga todo mundo);
- OPcache ligado, `mod_status` desligado, no máximo 20 processos do Apache
  (cada um segura uma conexão persistente com o banco);
- `AllowOverride All`: o [.htaccess](.htaccess) define cabeçalhos de
  segurança, gzip, cache e limites de upload. A CSP das páginas PHP vem do
  próprio PHP, com nonce nos scripts (`site_csp_politica()` em
  `php/conexao.php`); a do `.htaccess` só vale para os arquivos estáticos;
- [docker/entrypoint.sh](docker/entrypoint.sh) troca a porta do Apache
  pela `$PORT` do Render.

O [.dockerignore](.dockerignore) deixa fora da imagem o `.env`, a
documentação, `database/` e `agents/` (o Apache serviria esses arquivos).

[render.yaml](render.yaml) é o blueprint: lista as variáveis e o health
check (`/saude.php`). Só vale se o serviço foi criado pelo Blueprint; no
serviço atual, configurar no painel.

### Como sobe uma versão nova

1. Push na `main` do repositório `The-SunRose/MananciAPP---Render---Homolog`.
2. O Render gera a imagem e troca o container (alguns minutos). Durante a
   troca, quem estava logado perde a sessão se `SESSOES_NO_BANCO=0`.
3. Mudança de banco: rodar a seção nova do
   [bancosupabase.md](database/bancosupabase.md) no SQL Editor do Supabase
   **antes** ou logo depois do deploy (as seções são idempotentes; nunca
   rodar o arquivo inteiro, o começo apaga o schema).
4. Conferir a lista da seção 1.6 de
   [pontos_gerais.md](docs/interno/pontos_gerais.md).

**Desfazer:** Render → Events → escolher um deploy anterior → *Rollback*.
O banco não volta junto.

**Logs:** Render → Logs. As mensagens do app começam pelo assunto
(`[R2] ...`, `Brevo: ...`, `OneSignal: ...`, `Recuperação de senha: ...`,
`webhook_asaas ...`, `Sessões: ...`).

## 3. Banco: Postgres no Supabase

O schema e todas as mudanças estão em
[database/bancosupabase.md](database/bancosupabase.md), em seções numeradas
e idempotentes.

**Conexão:** usar o **Session pooler** (porta 5432, usuário
`postgres.<ref>`). A conexão direta é IPv6 e o Render só sai por IPv4; o
Transaction pooler (6543) não combina com a conexão persistente
(`PDO::ATTR_PERSISTENT`).

**Processos × conexões:** cada processo do Apache segura uma conexão no
pooler. O número de processos vem de `APACHE_MAX_WORKERS` (padrão 20, lido
na partida por `docker/entrypoint.sh`); processos × instâncias precisa caber
no *Pool Size* do Supabase (Database → Settings → Connection pooling), com
folga de ~5. Ao trocar de plano no Render, ajustar os dois juntos (ver
[docs/interno/capacidade.md](docs/interno/capacidade.md)).

**Uma ida ao banco por consulta:** `PDO::PGSQL_ATTR_DISABLE_PREPARES` está
ligado (em `mysqli_connect()` e em `php/sessao_banco.php`). Sem ele, o
`pdo_pgsql` faz PREPARE, EXECUTE e DEALLOCATE em toda consulta, inclusive
nas sem parâmetros: medido em 21/10/2026, ~408 ms → ~133 ms por consulta
(de um computador em SP até o Supabase). Os parâmetros continuam separados
do SQL.

**Shim `mysqli_*`:** o app foi escrito para MySQL. Em vez de reescrever as
consultas, [php/conexao.php](php/conexao.php) define funções com os nomes
da extensão `mysqli` (`mysqli_query`, `mysqli_fetch_assoc`,
`mysqli_real_escape_string`…) por cima de PDO + Postgres. Por isso a
extensão `mysqli` de verdade não pode estar carregada. O shim também:

- tira o `LIMIT` de `UPDATE`/`DELETE` e converte `LIMIT offset, n`;
- devolve cada coluna com a chave em maiúsculo e em minúsculo
  (`$row['IDF_ID']` e `$row['idf_id']`), porque o Postgres devolve tudo
  em minúsculo.

**Código novo usa `db_query($conn, $sql, $params)`**, com consultas
preparadas (`?`). O SQL concatenado antigo está sendo migrado aos poucos.

**Identificadores:** criados entre aspas e em minúsculo (`"id_fiel"`). O
app escreve sem aspas (`FROM ID_FIEL`) e o Postgres dobra para minúsculo,
então os dois batem. Colunas de flag são `INTEGER` (0/1), não `BOOLEAN`.

**Ambiente local:** o `.env` local normalmente aponta para o mesmo banco
do Render. Para um banco separado, preencher `DB_HOST_LOCAL` etc.; o app
usa essas credenciais quando o acesso é por `localhost`.

## 4. Variáveis de ambiente

Todas estão comentadas no [.env.example](.env.example). No Render ficam
em *Environment*; no local, no `.env` (que não vai para o git).

| Grupo | Variáveis | Sem elas |
|---|---|---|
| Banco | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` (`DB_*_LOCAL` opcionais) | O app não abre |
| Vídeos | `R2_ACCOUNT_ID`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET` | Aulas "Indisponível", admin não envia vídeo |
| E-mail | `EMAIL_API_KEY`, `EMAIL_SENDER_EMAIL`, `EMAIL_SENDER_NAME` | Nenhum e-mail sai |
| Links | `SITE_URL_PUBLICA` | Usa a URL do Render como padrão |
| Senha | `SENHA_CHECAR_VAZADAS` | Em branco = confere senhas vazadas; `0` desliga |
| Push | `ONESIGNAL_APP_ID`, `ONESIGNAL_REST_API_KEY`, `ONESIGNAL_EXTERNAL_ID_SEGREDO` | Sem push (ou push sem proteção) |
| Lembrete | `LEMBRETE_CRON_CHAVE` | Lembrete diário não sai |
| Assinaturas | `ASAAS_API_URL`, `ASAAS_API_KEY`, `ASAAS_WEBHOOK_TOKEN`, `ASSINATURA_TOLERANCIA_DIAS` | A tela de assinar mostra "ainda não disponível" |
| Contato | `CONTATO_EMAIL`, `CONTATO_TELEFONE`, `CONTATO_ENDERECO`, `CONTATO_FORMSPREE_URL` | Linhas somem, formulário cai na conta de teste |
| Sessões | `SESSOES_NO_BANCO` | Sessões em arquivo |
| 2FA | `APP_CHAVE` (32+ letras; não trocar depois) | Chave do autenticador sem cifra no banco |

## 5. Vídeos e capas: Cloudflare R2

Por que o R2 (custos), como recriar a configuração na Cloudflare e a
orientação de compressão: [videos_cloudflare_r2.md](docs/interno/videos_cloudflare_r2.md).

O valor de `ID_CONTENT.IDCT_URL` (e de `ID_CURSO.IDC_CAPA_URL`) diz onde o
arquivo está:

| Valor | Onde está |
|---|---|
| `r2:aulas/...` / `r2:capas/...` | Cloudflare R2 (o normal) |
| `videos/...` / `assets/capas_cursos/...` | Arquivo no git (legado e capas de exemplo) |
| `http(s)://...` | Link externo |
| qualquer outro valor | Caminho antigo do Supabase Storage: aparece como "Indisponível" |

Como funciona:

- [php/conexao.php](php/conexao.php):
  - `r2_configurado()` diz se as 4 variáveis `R2_*` estão preenchidas;
  - `r2_criar_url_assinada($metodo, $caminho, $expira)`: URL pré-assinada
    AWS SigV4 (API S3 do R2) em PHP puro, **sem chamada de rede**. O
    horário é arredondado para a hora cheia, para a URL se repetir e o
    navegador reaproveitar o cache do vídeo;
  - `site_resolver_url_video()` transforma `r2:...` num GET assinado de
    2 h (2 a 3 h com o arredondamento; o player recarrega sozinho se o link
    vencer com a aula pausada). É o ponto único usado pelo player, pelas
    listas e pelo admin;
  - `r2_enviar_arquivo()` / `site_salvar_imagem_upload()`: capas dos cursos
    (até 5 MB, jpg/png/webp/gif, conferidas como imagem) vão do PHP para o
    bucket;
  - `r2_excluir_arquivo()` / `site_excluir_arquivo_r2_orfao()`: apagam o
    arquivo quando nenhuma aula nem curso aponta mais para ele.
- [php/admin/r2_upload_url.php](php/admin/r2_upload_url.php): exige admin,
  valida a extensão (mp4/webm/ogg/mov) e devolve uma URL de `PUT` de 15 min
  com o `Content-Type` assinado (tirado da extensão; o R2 recusa outro) e o
  `path` que vai para o banco. O navegador envia o vídeo **direto para
  o bucket** (a barra mostra o progresso real) e o admin grava a duração
  lida do arquivo.
- **Exclusão automática** ao excluir aula ou curso e ao trocar vídeo ou
  capa: o arquivo só é apagado **depois** que o banco confirmou e se nada
  mais o usa. Se o R2 não responder em 10 s, o admin segue, o erro vai
  para o log (`[R2] Falha ao excluir ...`) e o arquivo sobra no bucket.
  O R2 não tem lixeira (ver seção 5.2 de
  [pontos_gerais.md](docs/interno/pontos_gerais.md)).
- [.htaccess](.htaccess): `https://*.r2.cloudflarestorage.com` em
  `media-src` e `connect-src` da CSP; sem isso o navegador bloqueia em
  silêncio.

**Na Cloudflare** (conta `sunrosefatec@...`): bucket privado
`mananciapp-videos-homolog`, sem domínio público; token
`mananciapp-homolog` com leitura/gravação só nesse bucket; CORS liberando
`localhost`, `localhost:8000` e o endereço do Render. Local e Render usam
**o mesmo bucket**, porque usam o mesmo banco.

## 6. Rodar localmente

Passo a passo em [README.md](README.md#como-rodar-o-projeto). O essencial:

- XAMPP: no `php.ini`, comentar `extension=mysqli` e ligar
  `extension=pdo_pgsql` (senão o shim da seção 3 não é usado e a conexão
  falha);
- ou, sem Apache: `C:\xampp\php\php.exe -S localhost:8000 -t .` dentro de
  `MananciAPP/` (nesse modo o `.htaccess` não é lido, então não há CSP);
- se o curl do XAMPP der `SSL certificate problem` (errno 60), apontar
  `curl.cainfo` e `openssl.cafile` do `php.ini` para
  `C:\xampp\phpMyAdmin\vendor\composer\ca-bundle\res\cacert.pem` (o
  bundle que vem com o XAMPP é antigo). No Docker isso não acontece.
