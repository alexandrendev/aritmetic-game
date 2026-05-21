FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    unzip \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/entrypoint.sh /entrypoint.sh
COPY docker/php/start.sh /start.sh
RUN chmod +x /entrypoint.sh /start.sh

WORKDIR /var/www/html

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/start.sh"]
