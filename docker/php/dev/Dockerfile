FROM php:8.5-fpm

ARG user=laravel
ARG uid=1000

# Instalar dependências e extensões
RUN apt-get update && apt-get install -y \
    git \
    curl \
    wget \
    supervisor \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libbz2-dev \
    libgmp-dev \
    libonig-dev \
    libicu-dev \
    zip \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar redis-cli (redis-tools)
RUN apt-get update && apt-get install -y redis-tools

# Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && node -v \
    && npm -v \
    && npx -v

# Instalar extensões PHP local (INCLUINDO XDEBUG)
RUN (curl -sSLf https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o - || echo 'return 1') | sh -s \
    gd xdebug oci8 bcmath bz2 pdo_mysql mysqli pdo_oci zip ftp redis exif mbstring pcntl dom

# Copiar arquivos de configuração do PHP e Supervisor local
COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY php.ini /usr/local/etc/php/conf.d/custom.ini
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Criar usuário e dar permissões
RUN useradd -m -u ${uid} -d /home/${user} -s /bin/bash ${user} \
    && mkdir -p /home/${user}/.config/psysh \
    && chown -R ${user}:${user} /home/${user}/.config \
    && chown -R ${user}:${user} /var/www/html

USER ${user}

WORKDIR /var/www/html

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
