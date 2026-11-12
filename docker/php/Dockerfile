ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-fpm-alpine

ARG UID=1000
ARG GID=1000

# Runtime libs + tools Composer/Symfony need
RUN apk add --no-cache \
        git unzip shadow \
        icu-libs libzip postgresql-libs

# PHP extensions
#   Already in base image: ctype iconv mbstring pcre session SimpleXML tokenizer xml dom opcache
#   Added: intl (Symfony), zip (Composer), pdo_pgsql/pgsql (Doctrine), apcu (cache)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
        icu-dev libzip-dev postgresql-dev \
 && docker-php-ext-install -j"$(nproc)" intl zip pdo_pgsql pgsql \
 && pecl install apcu \
 && docker-php-ext-enable apcu \
 && pecl clear-cache \
 && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY php.ini $PHP_INI_DIR/conf.d/zz-app.ini

# Match www-data to the host user so files written to ./App stay owned by you
RUN usermod -u ${UID} www-data && groupmod -g ${GID} www-data

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /var/www/html
