FROM composer:2.10.3@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer-bin

# [E-12／E-18] 僅執行前端內容產生工具，版本與 frontend 服務相同。
FROM node:24-bookworm-slim@sha256:d6aa754f16b3197301076f047b5def2f02ea1dbbc2ca920407d46d7ec7f87b20 AS node-bin

FROM php:8.5.12-fpm-bookworm@sha256:d3db5340c7ecfb1e3a26af5d3d9de8b4e160cdd75692a830b5cb74b0b85db2cf AS php-base

# [E-12 / 2026-10-22] Fail the build if the pinned security release is not running.
RUN test "$(php -r 'echo PHP_VERSION;')" = '8.5.12' && php-fpm -v

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_mysql mbstring intl gd zip \
    && rm -rf /var/lib/apt/lists/*

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/tools
COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

FROM php-base AS dependencies
COPY tools/php/composer.json tools/php/composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-plugins

FROM php-base AS app-dependencies
WORKDIR /var/www/app
COPY backend/composer.json backend/composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-plugins

FROM php-base AS runtime
COPY --from=node-bin /usr/local/bin/node /usr/local/bin/node
RUN mkdir -p /var/www/media /var/www/publication && chown -R www-data:www-data /var/www/media /var/www/publication
COPY frontend /var/www/frontend-source
COPY docker/php/php.ini /usr/local/etc/php/conf.d/yinghua.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-yinghua.conf
COPY tools/php/composer.json tools/php/composer.lock ./
COPY --from=dependencies /var/www/tools/vendor /var/www/tools/vendor
COPY backend /var/www/app
COPY --from=app-dependencies /var/www/app/vendor /var/www/app/vendor
WORKDIR /var/www/app

CMD ["php-fpm"]

FROM runtime AS development
RUN --mount=type=cache,target=/root/.composer/cache composer install --working-dir=/var/www/tools --no-interaction --prefer-dist --no-scripts --no-plugins
RUN --mount=type=cache,target=/root/.composer/cache composer install --working-dir=/var/www/app --no-interaction --prefer-dist --no-scripts --no-plugins
