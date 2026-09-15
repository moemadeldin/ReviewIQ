FROM php:8.5-cli AS build

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    unzip \
    curl \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install -j$(nproc) \
        mbstring \
        xml \
        curl \
        sockets \
        bcmath \
        zip \
        pcntl \
    && curl -fsSL https://bun.sh/install | bash

ENV PATH="/root/.bun/bin:${PATH}"

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock package.json bun.lock ./

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    && bun install

COPY . .

RUN cp .env.example .env \
    && touch database/database.sqlite \
    && php artisan key:generate --force --quiet \
    && php artisan wayfinder:generate --with-form \
    && composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts \
    && rm -f bootstrap/cache/packages.php bootstrap/cache/services.php

RUN bun run build

FROM php:8.5-fpm AS production

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install -j$(nproc) \
    pgsql \
    pdo_pgsql \
    mbstring \
    xml \
    curl \
    sockets \
    pcntl \
    bcmath \
    zip

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY --from=build /app/vendor ./vendor
COPY --from=build /app/public/build ./public/build
COPY --from=build /root/.bun /root/.bun
COPY . .

ENV PATH="/root/.bun/bin:${PATH}"

RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php

COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
