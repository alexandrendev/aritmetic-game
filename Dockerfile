FROM php:8.3-fpm
LABEL authors="alxfn86@gmail.com"

RUN apt-get update && apt-get upgrade -y \
    git unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

COPY app/composer.lock app/composer.json ./

RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY app/ .

RUN chown -R www-data:www-data /var/www/app

EXPOSE 9000

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]