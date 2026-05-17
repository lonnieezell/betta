FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    bash \
    curl \
    icu-dev \
    libxml2-dev \
    libzip-dev \
    oniguruma-dev \
    sqlite-dev \
    $PHPIZE_DEPS

RUN docker-php-ext-install \
    curl \
    dom \
    intl \
    mbstring \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    sqlite3 \
    xml \
    zip

RUN pecl install xdebug && docker-php-ext-enable xdebug

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY .docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
