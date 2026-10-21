FROM php:8.4.27-fpm-alpine

RUN apk add --no-cache postgresql-dev libzip-dev icu-dev \
    && docker-php-ext-install pdo_pgsql pcntl opcache zip intl \
    && apk del postgresql-dev \
    && apk add --no-cache libpq

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/japa.ini
COPY docker/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-japa.conf

WORKDIR /var/www/api

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN rm -f .env \
    && composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
