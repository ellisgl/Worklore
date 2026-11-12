ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-fpm-alpine

RUN apk add --no-cache postgresql-libs \
 && apk add --no-cache --virtual .build-deps postgresql-dev \
 && docker-php-ext-install pdo_pgsql pgsql \
 && apk del .build-deps

WORKDIR /var/www/html
