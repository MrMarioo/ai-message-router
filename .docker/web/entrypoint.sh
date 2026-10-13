#!/bin/sh
# Prepares the Laravel application on container start, then hands over to Apache.
set -e

cd /var/www

[ -f .env ] || cp .env-docker.example .env

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

[ -f database/database.sqlite ] || touch database/database.sqlite
chown -R www-data:www-data storage bootstrap/cache database

php artisan migrate --force --no-interaction
php artisan optimize --no-interaction

exec "$@"
