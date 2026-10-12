#!/bin/sh
set -e

# Dependencias y esquema al día en cada arranque: así `make up` en una máquina
# limpia deja la API funcionando sin más pasos.
if [ ! -f vendor/autoload.php ]; then
    echo "Instalando dependencias de Composer..."
    composer install --no-interaction --optimize-autoloader
fi

mkdir -p var/cache var/log
chown -R www-data:www-data var

# Postgres puede tardar unos segundos más que PHP en aceptar conexiones.
for i in $(seq 1 30); do
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration && break
    echo "Esperando a la base de datos ($i)..."
    sleep 2
done
chown -R www-data:www-data var

exec docker-php-entrypoint "$@" -F
