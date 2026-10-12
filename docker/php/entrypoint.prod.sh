#!/bin/sh
set -e

mkdir -p var/cache var/log

echo "→ Esperando a la base de datos…"
tries=0
until php bin/console dbal:run-sql 'select 1' >/dev/null 2>&1; do
    tries=$((tries + 1))
    if [ "$tries" -ge 30 ]; then
        echo "✗ Sin base de datos tras 60 s. El error:" >&2
        php bin/console dbal:run-sql 'select 1' >&2 || true
        exit 1
    fi
    sleep 2
done

echo "→ Migraciones…"
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# La caché se calienta aquí y no al construir: depende de las variables de
# entorno, que sólo existen al arrancar.
echo "→ Caché…"
php bin/console cache:clear --no-debug
php bin/console cache:warmup --no-debug
chown -R www-data:www-data var

echo "→ PHP-FPM listo"
exec docker-php-entrypoint "$@" -F
