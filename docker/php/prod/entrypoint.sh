#!/bin/sh
set -e

# storage/ é volume nomeado: o Docker só copia o conteúdo da imagem na PRIMEIRA
# criação do volume. Diretórios adicionados em releases posteriores nunca
# apareceriam. Recriar o esqueleto a cada boot é barato e idempotente.
mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs

# Cada container (php, horizon, scheduler) tem o próprio bootstrap/cache na sua
# camada de escrita, então todos precisam gerar o cache — não dá para depender
# de um `artisan optimize` rodado via exec só no container php.
# view:cache fica de fora de propósito: storage/framework/views é volume
# compartilhado e os três containers subiriam escrevendo nele ao mesmo tempo.
php artisan config:cache
php artisan route:cache
php artisan event:cache

exec "$@"
