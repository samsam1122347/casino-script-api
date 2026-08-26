# syntax=docker/dockerfile:1

# Composer must run with the same extensions the app needs (Filament requires ext-intl). The standalone
# `composer:2` image often lacks intl — use php:8.*-cli-alpine (matches `base`), install intl, then bring in composer binary.
FROM php:8.4-cli-alpine AS vendor
# Match runtime extensions: Filament → openspout → ext-zip; composer validates platform here.
RUN apk add --no-cache \
        icu-dev \
        icu-libs \
        libzip-dev \
        zip \
        linux-headers \
    && apk add --no-cache --virtual .php-build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j "$(nproc)" intl zip \
    && apk del .php-build-deps \
    && rm -rf /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1
WORKDIR /app
COPY api/composer.json api/composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
        curl \
        nginx \
        supervisor \
        icu-dev \
        icu-libs \
        libzip-dev \
        libpq-dev \
        linux-headers \
        zip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j "$(nproc)" \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

WORKDIR /var/www/html

COPY api/ /var/www/html/
COPY --from=vendor /app/vendor /var/www/html/vendor

RUN rm -rf bootstrap/cache/* \
    && mkdir -p storage/logs storage/framework/sessions storage/framework/views storage/framework/cache/data bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY deploy/nginx-api.conf /etc/nginx/http.d/default.conf
COPY deploy/docker/api/php-opcache.ini /usr/local/etc/php/conf.d/docker-php-opcache.ini
COPY deploy/docker/api/supervisord.conf /etc/supervisord.conf
COPY deploy/docker/api/docker-entrypoint-web.sh /usr/local/bin/docker-entrypoint-web.sh
COPY deploy/docker/api/docker-entrypoint-worker.sh /usr/local/bin/docker-entrypoint-worker.sh

RUN chmod +x /usr/local/bin/docker-entrypoint-web.sh /usr/local/bin/docker-entrypoint-worker.sh \
    && mkdir -p /run/nginx

FROM base AS web
ENTRYPOINT ["/usr/local/bin/docker-entrypoint-web.sh"]
EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

FROM base AS worker
ENTRYPOINT ["/usr/local/bin/docker-entrypoint-worker.sh"]
CMD ["php", "artisan", "queue:work", "redis", "--sleep=3", "--tries=3"]
