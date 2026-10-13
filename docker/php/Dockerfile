FROM composer:2.10.3@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer-bin

FROM php:8.5-fpm-bookworm@sha256:dd198c828c224e479bdaa184cdcf04a28c73d186bf5a885493bf9cc78d7196ef AS php-base

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

FROM php-base AS runtime
COPY docker/php/php.ini /usr/local/etc/php/conf.d/yinghua.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-yinghua.conf
COPY tools/php/composer.json tools/php/composer.lock ./
COPY --from=dependencies /var/www/tools/vendor /var/www/tools/vendor

CMD ["php-fpm"]

FROM runtime AS development
RUN --mount=type=cache,target=/root/.composer/cache composer install --no-interaction --prefer-dist --no-scripts --no-plugins
