# syntax=docker/dockerfile:1

FROM php:8.3-fpm-alpine AS base

ENV PHP_INI_DIR=/usr/local/etc/php \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

RUN set -eux; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev zlib-dev libzip-dev libxml2-dev mysql-dev freetype-dev libjpeg-turbo-dev libpng-dev oniguruma-dev; \
    apk add --no-cache icu-libs libzip mysql-client libjpeg-turbo freetype libpng tzdata bash git curl; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install intl pdo pdo_mysql gd mbstring opcache zip; \
    pecl install apcu; \
    docker-php-ext-enable apcu opcache; \
    apk del .build-deps

# PHP runtime config
RUN { \
    echo "memory_limit=512M"; \
    echo "upload_max_filesize=20M"; \
    echo "post_max_size=20M"; \
    echo "expose_php=0"; \
    echo "session.use_strict_mode=1"; \
    echo "date.timezone=UTC"; \
  } > /usr/local/etc/php/conf.d/app.ini

# Opcache tuning
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.enable_cli=1"; \
    echo "opcache.memory_consumption=192"; \
    echo "opcache.interned_strings_buffer=16"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.save_comments=1"; \
  } > /usr/local/etc/php/conf.d/opcache.ini


FROM composer:2 AS composer-prod
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN --mount=type=cache,target=/root/.composer \
    composer install --no-dev --no-scripts --no-progress --no-interaction --prefer-dist

FROM composer:2 AS composer-dev
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN --mount=type=cache,target=/root/.composer \
    composer install --no-scripts --no-progress --no-interaction --prefer-dist


FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json ./
COPY --from=composer-prod /app/vendor ./vendor
RUN npm install --no-audit --no-fund --force
COPY webpack.config.js postcss.config.js tailwind.config.js tsconfig*.json ./
COPY assets ./assets
COPY themes ./themes
COPY public ./public
RUN npm run build


FROM base AS production
WORKDIR /app

# App sources
COPY bin ./bin
COPY config ./config
COPY migrations ./migrations
COPY src ./src
COPY templates ./templates
COPY public ./public
COPY composer.json composer.lock symfony.lock ./

# Vendors and built assets
COPY --from=composer-prod /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN set -eux; \
    mkdir -p var public/media; \
    chown -R www-data:www-data var public/media

# Warm up cache (does not require DB connection in typical Symfony setups)
RUN php bin/console cache:warmup --no-ansi --no-interaction || true

EXPOSE 9000
USER www-data
CMD ["php-fpm"]


FROM production AS development
USER root
RUN rm -rf /app/vendor
COPY --from=composer-dev /app/vendor ./vendor
RUN chown -R www-data:www-data /app/vendor
USER www-data



