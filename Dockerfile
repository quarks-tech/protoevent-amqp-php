FROM php:8.1.0-fpm-alpine3.15

ARG AMQP_VERSION=1.11.0
ARG MCRYPT_VERSION=1.0.5
ENV PHP_EXTRA_CONFIGURE_ARGS --enable-fpm --with-zip --with-fpm-user=www-data --with-fpm-group=www-data --enable-opcache

RUN apk add --no-cache --update fcgi rabbitmq-c-dev libmcrypt-dev readline-dev oniguruma-dev zlib libzip-dev icu icu-dev icu-libs grpc

RUN apk add --no-cache --virtual .build-deps ${PHPIZE_DEPS} wget \
    && mkdir -p /var/run/shared/ \
    && curl -sSLf \
       -o /usr/local/bin/install-php-extensions \
       https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions && \
       chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        apcu \
        mcrypt-${MCRYPT_VERSION} \
        amqp-${AMQP_VERSION} \
        zip \
        mbstring \
        bcmath \
        pdo_mysql \
        opcache \
        pcntl \
        sockets \
        intl \
    && docker-php-ext-enable apcu mcrypt amqp \
    && apk del .build-deps

COPY --chown=www-data ./ /var/www/application

WORKDIR /var/www/application

STOPSIGNAL SIGTERM

CMD ["php-fpm"]
