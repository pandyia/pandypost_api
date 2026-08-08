FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize --no-dev

# ---

FROM php:8.5-fpm-alpine

ARG user=laravel
ARG uid=1000

RUN apk add --no-cache \
    supervisor \
    logrotate \
    linux-headers

RUN curl -sSLf https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        gd bcmath bz2 pdo_pgsql pgsql zip ftp redis exif mbstring pcntl posix swoole dom opcache \
    && rm /usr/local/bin/install-php-extensions

COPY docker/php/prod/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/prod/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php/prod/logrotate.conf /etc/logrotate.d/laravel

RUN adduser -u ${uid} -D -h /home/${user} -s /bin/sh ${user} \
    && mkdir -p /home/${user}/.config/psysh /var/log/php \
    && chown -R ${user}:${user} /home/${user}/.config /var/log/php

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN chown -R ${user}:${user} /var/www/html \
    && chmod -R 775 storage bootstrap/cache

USER ${user}

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
