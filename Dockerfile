# ---------------------------------------------------------------
# Etapa 1: compila React con Vite (genera public/build)
# ---------------------------------------------------------------
FROM node:22-slim AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---------------------------------------------------------------
# Etapa 2: PHP 8.4 con PostgreSQL y MongoDB
# (8.4 y no 8.3: composer.lock trae Symfony 8, que exige PHP 8.4.1 o más nuevo)
# ---------------------------------------------------------------
FROM php:8.4-cli

# pdo_pgsql para PostgreSQL, mongodb para el catálogo y zip para Composer.
# La extensión mongodb va en la serie 1.21 porque composer.lock usa mongodb/mongodb 1.21.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev libssl-dev pkg-config unzip git \
    && docker-php-ext-install pdo_pgsql zip pcntl \
    && pecl install mongodb-1.21.0 \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Dependencias primero, para aprovechar la caché de Docker.
# Se instalan también las de desarrollo para poder correr las pruebas dentro del contenedor.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist --no-progress

COPY . .
COPY --from=assets /app/public/build ./public/build

# sed quita los \r por si el script se clonó en Windows con saltos de línea CRLF.
RUN composer dump-autoload --optimize \
    && sed -i 's/\r$//' docker/entrypoint.sh \
    && chmod +x docker/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker/entrypoint.sh"]
# --no-reload permite usar PHP_CLI_SERVER_WORKERS (varias peticiones a la vez).
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
