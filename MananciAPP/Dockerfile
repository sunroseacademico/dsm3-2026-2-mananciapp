# O Render não tem um runtime nativo de PHP (só Docker, Node, Python, Go, Ruby,
# Rust e Elixir), então esse Dockerfile é o que permite rodar o projeto lá.
# Versão fixada (não só "8.2") para garantir a correção do php2shell
# (CVE-2026-109618 e CVE-2026-109619, corrigidas na 8.2.35). Ao sair uma
# nova correção de segurança, troque o número aqui e faça o deploy de novo.
FROM php:8.2.35-apache

# Extensões usadas pelo projeto:
# - pdo_pgsql: conexão com o Postgres do Supabase (php/conexao.php). O banco
#   original era MySQL/mysqli; o app agora fala com o Postgres via um shim de
#   compatibilidade (funções mysqli_* reescritas por cima do PDO, ver
#   conexao.php) — por isso a extensão mysqli NÃO é instalada aqui de propósito.
# - curl: chamadas HTTP à API da Brevo (e-mail) e do Supabase Storage.
# libpq-dev é só uma dependência de build do pdo_pgsql (headers do cliente
# Postgres), não precisa continuar na imagem final, mas simplifica manter.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_pgsql curl \
    && rm -rf /var/lib/apt/lists/*

# A imagem oficial php:8.2-apache NAO vem com nenhum php.ini por padrao — sem
# copiar um, o PHP roda com os valores padrao compilados, e um deles e
# "display_errors = On". Em producao isso significa qualquer warning/notice
# (variavel nao definida, indice inexistente etc.) aparecendo direto na
# pagina pro visitante, vazando caminho de arquivos do servidor e outros
# detalhes internos. php.ini-production ja vem com display_errors=Off e
# log_errors=On (os erros continuam indo pro log do container, so nao pro
# navegador de quem acessa o site).
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Reforca o cookie de sessao (PHPSESSID) direto no php.ini — assim vale pra
# TODA pagina, nao importa se ela chama session_start() direto ou via
# php/conexao.php, e nao importa a ordem entre elas (esses parametros so tem
# efeito se definidos ANTES do primeiro session_start() da requisicao, e
# arquivos diferentes chamam isso em pontos diferentes do codigo).
# - httponly: JS (document.cookie) nao consegue ler o cookie de sessao —
#   limita o estrago de um XSS futuro (hoje nao encontramos nenhum, mas o
#   app nao usa nenhuma camada de sanitizacao dedicada, so htmlspecialchars()
#   pontual em cada saida).
# - samesite=Lax: o navegador NAO manda esse cookie em requisicoes
#   cross-site (ex.: um POST disparado por um <form> escondido num site
#   malicioso) — mitiga CSRF sem precisar de token em cada formulario do
#   painel admin (o app nao tem nenhum hoje).
# - secure: cookie só trafega em HTTPS. Sem efeito em producao (Render só
#   serve HTTPS pro navegador do visitante, mesmo que internamente o
#   proxy fale HTTP com o container) e nao afeta o XAMPP local, que usa o
#   php.ini da maquina do dev, nao este.
# - expose_php=Off: tira o header "X-Powered-By: PHP/8.2.x" das respostas
#   (não entrega de graça a versão exata do PHP pra quem procura falhas
#   conhecidas daquela versão).
# - use_strict_mode: o PHP recusa um id de sessão que ele mesmo não criou
#   (alguém plantando um PHPSESSID conhecido no navegador da vítima).
# - gc_maxlifetime: o padrão (1440 s = 24 min) apagava a sessão antes da
#   1 h de inatividade que o app promete (SESSION_TIMEOUT_SECONDS).
# - date.timezone: horário de Brasília também para o PHP fora do app
#   (conexao.php já faz date_default_timezone_set).
RUN { \
        echo "session.cookie_httponly = 1"; \
        echo "session.cookie_samesite = Lax"; \
        echo "session.cookie_secure = 1"; \
        echo "session.use_strict_mode = 1"; \
        echo "session.gc_maxlifetime = 3600"; \
        echo "date.timezone = America/Sao_Paulo"; \
        echo "expose_php = Off"; \
        echo "auto_prepend_file = /var/www/html/php/sessao_banco.php"; \
    } >> "$PHP_INI_DIR/php.ini"
# auto_prepend_file: php/sessao_banco.php roda antes de toda página e, com
# SESSOES_NO_BANCO=1 (e a tabela da seção 70 criada), passa as sessões do
# disco do container — apagado a cada deploy/hibernação — para o Postgres.
# Com a variável em 0 (padrão), não faz nada.

# OPcache: a imagem oficial traz a extensão mas NÃO a liga. Sem ela o PHP
# relê e recompila todos os arquivos a cada requisição — inclusive o
# php/conexao.php (~6 mil linhas), incluído em toda página. Com ela o código
# compilado fica em memória e só é feito uma vez por container.
# - validate_timestamps=0: dentro do container o código nunca muda (cada
#   deploy gera uma imagem nova), então não precisa checar se o arquivo foi
#   alterado a cada requisição.
# - memory_consumption pequeno de propósito: o plano free tem 512 MB de RAM.
RUN docker-php-ext-enable opcache \
    && { \
        echo "opcache.enable = 1"; \
        echo "opcache.memory_consumption = 64"; \
        echo "opcache.interned_strings_buffer = 8"; \
        echo "opcache.max_accelerated_files = 4000"; \
        echo "opcache.validate_timestamps = 0"; \
        echo "realpath_cache_size = 4096K"; \
        echo "realpath_cache_ttl = 600"; \
    } > "$PHP_INI_DIR/conf.d/zz-opcache.ini"

# O .htaccess da raiz já define os limites de upload/execução (upload_max_filesize,
# post_max_size, memory_limit, max_execution_time). Isso só funciona se o Apache
# permitir overrides — o vhost padrão do Debian vem com AllowOverride None.
# deflate (compressão gzip) é usado pelas regras de desempenho do .htaccess.
RUN a2enmod rewrite headers deflate \
    && sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# mod_status vem ligado no Debian com "Require local", mas no Render toda
# requisição chega ao Apache como vinda de ::1 — e /server-status ficava
# aberto para qualquer um, listando as requisições em andamento com a URL
# completa (inclusive o ?token= de quem estivesse abrindo o link de
# redefinir senha). O app não usa essa página: o módulo sai de vez.
# ServerTokens/ServerSignature: a resposta e as páginas de erro deixam de
# informar a versão exata do Apache e do Debian.
RUN a2dismod -f status \
    && { \
        echo "ServerTokens Prod"; \
        echo "ServerSignature Off"; \
    } >> /etc/apache2/apache2.conf

# Até 20 processos do Apache (o padrão do prefork é 150). Cada processo
# segura uma conexão persistente com o Session Pooler do Supabase, que tem
# limite de clientes: num pico, passar disso fazia as conexões novas
# falharem — e o container tem 512 MB de RAM. Quem chega além dos 20 espera
# na fila em vez de derrubar o banco. Num plano maior, a variável
# APACHE_MAX_WORKERS do Render troca esse número na partida
# (docker/entrypoint.sh).
RUN sed -i 's/^\(\s*MaxRequestWorkers\s\+\).*/\120/' /etc/apache2/mods-available/mpm_prefork.conf

# RequestReadTimeout é uma diretiva só de configuração de servidor (o Apache
# recusa com erro 500 se ela aparecer num .htaccess), por isso fica aqui e
# não no .htaccess. O módulo reqtimeout já vem habilitado por padrão no
# Debian/Apache desta imagem.
RUN echo "RequestReadTimeout header=20-40,MinRate=500 body=20-900,MinRate=500" \
    >> /etc/apache2/apache2.conf

# Com prefork, cada conexão ocupa um processo inteiro: uma conexão parada
# esperando a próxima requisição (KeepAlive) segurava um dos 20 processos
# por 5 s, e o Apache esperava até 300 s por um cliente lento. Num pico
# (300 pessoas na live), isso enchia a fila à toa. Os estáticos têm cache de
# 1 ano (?v=), então quase não sobra o que reaproveitar da conexão.
RUN sed -i 's/^KeepAliveTimeout .*/KeepAliveTimeout 2/; s/^Timeout .*/Timeout 30/' /etc/apache2/apache2.conf

COPY . /var/www/html/
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    # docker/entrypoint.sh ja foi copiado pro lugar certo (/entrypoint.sh,
    # usado pelo ENTRYPOINT abaixo) — a pasta docker/ que "COPY . /var/www/html/"
    # deixou dentro da webroot e so um resto sem uso, e o Apache serviria
    # esse .sh como download estatico pra qualquer um que acessasse a URL.
    && rm -rf /var/www/html/docker

EXPOSE 10000

ENTRYPOINT ["/entrypoint.sh"]
