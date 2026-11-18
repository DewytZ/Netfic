#!/bin/sh
# Prepara la app antes de arrancar: clave, migraciones y datos de ejemplo.
set -e

cd /var/www/html

# Carpetas que Laravel necesita (storage es un volumen y puede venir vacío).
mkdir -p storage/app/private storage/app/public storage/logs \
    storage/framework/cache/data storage/framework/sessions storage/framework/views \
    bootstrap/cache

# Si no se pasa APP_KEY, se genera una sola vez y se guarda en el volumen de storage.
if [ -z "$APP_KEY" ]; then
    KEY_FILE=storage/app/private/app_key
    if [ ! -f "$KEY_FILE" ]; then
        php -r "echo 'base64:'.base64_encode(random_bytes(32));" > "$KEY_FILE"
        echo "APP_KEY nueva generada en $KEY_FILE"
    fi
    APP_KEY=$(cat "$KEY_FILE")
    export APP_KEY
fi

# Un .env solo con la clave, para que también la vean los comandos de "docker compose exec"
# (artisan, tinker, pruebas). El resto de variables llega desde docker-compose.yml.
echo "APP_KEY=$APP_KEY" > .env

php artisan migrate --force

# Los seeders se pueden correr en cada arranque: actualizan los datos en vez de duplicarlos.
if [ "${SEED_ON_START:-true}" = "true" ]; then
    php artisan db:seed --force
fi

# Sin config:cache a propósito: así las pruebas dentro del contenedor no heredan la configuración de la app.
php artisan route:cache || echo "Aviso: no se pudieron cachear las rutas; la app funciona igual."
php artisan view:cache || echo "Aviso: no se pudieron cachear las vistas; la app funciona igual."

exec "$@"
