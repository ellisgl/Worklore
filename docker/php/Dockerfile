ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-fpm-alpine

# Runtime libs + tools Composer/Symfony need
RUN apk add --no-cache \
        git unzip \
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

# Docker runs rootless on this host: container root == your host user.
# Run FPM workers as root so files they write (var/cache, var/log) stay owned by you.
COPY fpm-pool.conf /usr/local/etc/php-fpm.d/zz-user.conf

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /var/www/html
CMD ["php-fpm", "--allow-to-run-as-root"]
