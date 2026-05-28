#!/bin/sh
set -e

mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs

chmod -R 775 storage bootstrap/cache

php artisan storage:link --quiet 2>/dev/null || true

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    php artisan key:generate --force --quiet
fi

if [ "$APP_ENV" != "local" ]; then
    php artisan optimize --quiet
    php artisan migrate --force --quiet
fi

exec "$@"
