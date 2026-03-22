FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql \
    && apk del $PHPIZE_DEPS

WORKDIR /var/www/html
