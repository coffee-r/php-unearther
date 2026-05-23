FROM composer:2.2 AS composer-bin

FROM php:7.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev \
    && docker-php-ext-install pgsql pdo_pgsql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/bin/composer
COPY . /workspace/php-unearth
COPY tests/e2e/codeigniter3-sqlite/app /var/www/html
COPY tests/e2e/codeigniter3-postgresql/app /var/www/html
RUN if [ -d /var/www/html/application/controllers/Api ]; then \
        mv /var/www/html/application/controllers/Api /tmp/ci3-api-controllers \
        && mv /tmp/ci3-api-controllers /var/www/html/application/controllers/api; \
    fi

WORKDIR /var/www/html
RUN composer install --no-interaction --prefer-dist
RUN mkdir -p /var/www/html/runtime/logs /var/www/html/application/logs \
    && chown -R www-data:www-data /var/www/html/runtime /var/www/html/application/logs
