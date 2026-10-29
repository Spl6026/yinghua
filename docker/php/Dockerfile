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

# [E-29] 正式版本只複製運行來源；不沿用 development 的品質工具／測試／fixture。
FROM node-bin AS release-frontend
ARG APP_VERSION
ARG VCS_REF
ARG APP_URL
ARG TERMS_VERSION
ENV APP_URL=$APP_URL TERMS_VERSION=$TERMS_VERSION
WORKDIR /release/frontend
COPY frontend/assets ./assets
COPY frontend/pages ./pages
COPY frontend/templates ./templates
COPY frontend/resources ./resources
COPY frontend/scripts ./scripts
COPY backend/database/migrations /release/migrations
COPY backend/src /release/backend/src
COPY backend/config /release/backend/config
COPY backend/public /release/backend/public
COPY backend/bin /release/backend/bin
COPY backend/composer.json backend/composer.lock /release/backend/
COPY backend/resources/openapi.json backend/resources/role-templates.json /release/backend/resources/
COPY scripts/operations/release-manifest.mjs /release/release-manifest.mjs
RUN test -n "$APP_URL" && test -n "$TERMS_VERSION" && node scripts/build.mjs \
    && node /release/release-manifest.mjs "$APP_VERSION" "$VCS_REF"

FROM php-base AS release-base
COPY --from=node-bin /usr/local/bin/node /usr/local/bin/node
COPY docker/php/php.ini /usr/local/etc/php/conf.d/yinghua.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-yinghua.conf
RUN mkdir -p /var/www/media /var/www/publication \
    && chown www-data:www-data /var/www/media /var/www/publication \
    && rm /usr/local/bin/composer
# [E-29] 正式執行不需編譯器／headers／git；依 ELF 實際連結保留 runtime libraries。
RUN apt-get update && apt-get upgrade -y --no-install-recommends \
    && apt-mark auto autoconf dpkg-dev file g++ gcc libc6-dev make pkg-config re2c git unzip libicu-dev libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev \
    && find /usr/local -type f -executable -exec ldd '{}' ';' 2>/dev/null \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) next; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u | xargs -r dpkg-query --search | cut -d: -f1 | sort -u | xargs -r apt-mark manual \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false \
    && rm -rf /var/lib/apt/lists/* \
    && php -m && node --version

FROM release-base AS release
WORKDIR /var/www/app
COPY backend/composer.json backend/composer.lock ./
COPY --from=app-dependencies /var/www/app/vendor ./vendor
COPY backend/src ./src
COPY backend/config ./config
COPY backend/public ./public
COPY backend/bin ./bin
COPY backend/database ./database
COPY backend/resources/openapi.json backend/resources/role-templates.json ./resources/
COPY --from=release-frontend /release/frontend /var/www/frontend-source
COPY --from=release-frontend /release/release.json /var/www/release.json
CMD ["php-fpm"]

FROM nginx:1.30.6-alpine-slim@sha256:d9322bba63c6cfc63dc9e86522fd4f95fd4d9020a02b5bced282bbf38b8c3960 AS release-web
COPY --from=release-frontend /release/frontend/dist /var/www/frontend
COPY --from=release-frontend /release/release.json /var/www/release.json
COPY docker/nginx/default.conf /etc/nginx/yinghua-base.template
COPY docker/nginx/15-production-tls.sh /docker-entrypoint.d/15-production-tls.sh
RUN chmod 755 /docker-entrypoint.d/15-production-tls.sh
