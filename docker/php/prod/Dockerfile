# syntax=docker/dockerfile:1
#
# Estágios ordenados do que muda menos para o que muda mais, para o cache do
# CI (ver cache-from/cache-to em .github/workflows/deploy.yml) reaproveitar o
# máximo:
#   base   — runtime PHP + extensões. Só refaz quando este Dockerfile ou a
#            imagem php:8.5-cli-alpine mudam. É a etapa cara.
#   vendor — dependências do Composer. Só refaz quando composer.lock muda.
#   final  — código da aplicação. Refaz a cada deploy, em segundos.

# --- base ---------------------------------------------------------------
# Imagem CLI: o HTTP é servido pelo Octane (Swoole), não há PHP-FPM.
# A mesma imagem roda php (Octane), reverb, horizon e scheduler.
FROM php:8.5-cli-alpine AS base

ARG user=laravel
ARG uid=1000

# libstdc++ é dependência de runtime do Swoole (C++).
RUN apk add --no-cache \
    supervisor \
    logrotate \
    bash \
    curl \
    procps \
    redis \
    libstdc++

# posix é exigido por laravel/horizon (ao lado de pcntl) para o master
# supervisor sinalizar os workers.
# Versão do instalador fixada: com "latest" o build deixa de ser reproduzível.
ADD --chmod=755 https://github.com/mlocati/docker-php-extension-installer/releases/download/2.12.1/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        gd bcmath bz2 pdo_pgsql pgsql zip ftp redis exif mbstring pcntl posix dom opcache intl gmp \
    && rm /usr/local/bin/install-php-extensions \
    && php -m | grep -qx pcntl \
    && php -m | grep -qx posix \
    && php -m | grep -qx redis \
    && php -m | grep -qx intl

# Swoole via pecl, só com o que o Octane usa. Pelo install-php-extensions ele
# vem com todos os hooks de coroutine (curl, pgsql, sqlite, ssh2, firebird...)
# e o instalador compila o Firebird do fonte — ~4 min de build e libs a mais
# na imagem para nada: o Octane roda com enable_coroutine=false.
ARG SWOOLE_VERSION=6.2.3
RUN apk add --no-cache --virtual .swoole-build $PHPIZE_DEPS linux-headers openssl-dev \
    && MAKEFLAGS="-j$(nproc)" pecl install -D 'enable-sockets="no" enable-openssl="yes" enable-mysqlnd="no" enable-swoole-curl="no" enable-cares="no" enable-brotli="no" enable-zstd="no" enable-swoole-pgsql="no" enable-swoole-sqlite="no" with-swoole-firebird="no" with-swoole-odbc="no" with-swoole-oracle="no" with-swoole-ssh2="no" enable-swoole-ftp="no" enable-iouring="no" enable-uring-socket="no" enable-swoole-thread="no"' swoole-${SWOOLE_VERSION} \
    && docker-php-ext-enable swoole \
    && apk del .swoole-build \
    && rm -rf /tmp/pear \
    && php -m | grep -qx swoole

RUN adduser -u ${uid} -D -h /home/${user} -s /bin/sh ${user} \
    && mkdir -p /home/${user}/.config/psysh /var/log/php \
    && chown -R ${user}:${user} /home/${user}/.config /var/log/php

COPY docker/php/prod/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY --chmod=755 docker/php/prod/supervisor-fatal-listener.sh /usr/local/bin/supervisor-fatal-listener.sh
COPY docker/php/prod/logrotate.conf /etc/logrotate.d/laravel
COPY --chmod=755 docker/php/prod/entrypoint.sh /usr/local/bin/entrypoint.sh

# --- vendor -------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-scripts

# --- final --------------------------------------------------------------
FROM base

ARG user=laravel

WORKDIR /var/www/html

# O código vem primeiro e o vendor depois: na ordem inversa, um `vendor/` no
# contexto de build sobrescreveria o vendor otimizado --no-dev do estágio
# anterior. (.dockerignore também exclui vendor/, mas a ordem correta é a
# garantia de verdade.)
# --chown no COPY em vez de `RUN chown -R` depois: o RUN gravava uma segunda
# cópia de todo o app (vendor incluso) numa camada nova.
COPY --chown=${user}:${user} . .
COPY --chown=${user}:${user} --from=vendor /app/vendor ./vendor

RUN chmod -R 775 storage bootstrap/cache

# Defaults do Octane; o compose pode sobrescrever por serviço.
# O state file fica em /tmp (camada do container) e não em storage/logs, que é
# bind mount: um state file herdado do container anterior traz um PID que pode
# existir no container novo e o octane:start recusa subir ("already running").
ENV OCTANE_WORKERS=4 \
    OCTANE_MAX_REQUESTS=1000 \
    OCTANE_STATE_FILE=/tmp/octane-server-state.json

USER ${user}

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
