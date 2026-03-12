FROM php:8.2-fpm

LABEL maintainer="email2s3"

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql zip sockets pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN git config --global --add safe.directory /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy app only; run.sh installs vendor in the container (avoids slow composer/dump-autoload in build)
COPY . .

RUN mkdir -p database/seeders database/factories \
    bootstrap/cache \
    storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chmod -R 775 bootstrap/cache storage

CMD ["php-fpm"]

