#!/bin/sh
# O Render define a porta que o container precisa escutar na variável $PORT.
# O Apache do image php:apache vem configurado pra escutar na porta 80 por
# padrão, então aqui a gente troca isso em runtime antes de subir o servidor.
set -e

PORT="${PORT:-10000}"

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Quantos processos do Apache (requisições PHP ao mesmo tempo). O padrão de
# 20 cabe nos 512 MB do plano free; num plano maior, subir pelo painel do
# Render (APACHE_MAX_WORKERS) sem mexer no código. Cada processo segura uma
# conexão com o pooler do Supabase: workers × instâncias precisa caber no
# "Pool Size" dele (ver DEPLOY_RENDER.md).
WORKERS="${APACHE_MAX_WORKERS:-20}"
case "$WORKERS" in
    ''|*[!0-9]*) WORKERS=20 ;;
esac
sed -i "s/^\(\s*MaxRequestWorkers\s\+\).*/\1${WORKERS}/" /etc/apache2/mods-available/mpm_prefork.conf

# Processos já prontos para o pico: o padrão (5 na partida, até 10 parados)
# fazia o Apache criar processo aos poucos quando 300 pessoas chegavam no
# início do culto. Um quarto dos workers na partida e parado, até metade.
RESERVA=$((WORKERS / 4))
[ "$RESERVA" -lt 2 ] && RESERVA=2
MAX_RESERVA=$((WORKERS / 2))
[ "$MAX_RESERVA" -le "$RESERVA" ] && MAX_RESERVA=$((RESERVA + 1))
sed -i \
    -e "s/^\(\s*StartServers\s\+\).*/\1${RESERVA}/" \
    -e "s/^\(\s*MinSpareServers\s\+\).*/\1${RESERVA}/" \
    -e "s/^\(\s*MaxSpareServers\s\+\).*/\1${MAX_RESERVA}/" \
    /etc/apache2/mods-available/mpm_prefork.conf

exec apache2-foreground
