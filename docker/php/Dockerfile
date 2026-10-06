# syntax=docker/dockerfile:1
# Citra produksi Alias FKIP: aset (Vite) → vendor (Composer --no-dev) → runtime PHP-FPM 8.4.

FROM node:22-alpine AS aset
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm ci --no-audit --no-fund && npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

FROM php:8.4-fpm-alpine AS app
COPY --from=ghcr.io/mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl gd bcmath pcntl pdo_sqlite pdo_mysql redis zip opcache

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-alias.conf
COPY docker/php/entrypoint.sh /usr/local/bin/alias-entrypoint

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=aset --chown=www-data:www-data /app/public/build /var/www/html/public/build
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/tmp bootstrap/cache database \
    && chown -R www-data:www-data storage bootstrap/cache database

USER www-data
EXPOSE 9000
ENTRYPOINT ["alias-entrypoint"]
CMD ["php-fpm"]
