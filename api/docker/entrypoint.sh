#!/bin/sh
set -e

# Volumes start empty: recreate Laravel's storage layout and hand it to the PHP user.
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
    php artisan db:secure
fi

php artisan config:cache
php artisan route:cache
php artisan event:cache

exec "$@"
