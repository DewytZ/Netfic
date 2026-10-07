FROM php:8.3-cli

# Extensiones de PHP para PostgreSQL y dependencias de Composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip git curl \
    && docker-php-ext-install pdo_pgsql zip \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencias primero, para aprovechar la caché de Docker
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist

COPY package*.json ./
RUN npm install

COPY . .
RUN composer dump-autoload --optimize && npm run build

EXPOSE 8000

# "optimize" cachea configuración, rutas y vistas; va al arrancar porque el .env llega con docker run.
CMD ["sh", "-c", "php artisan optimize && php artisan serve --host=0.0.0.0 --port=8000"]
