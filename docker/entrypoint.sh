#!/bin/sh
set -e

# Apache refuses to start when two MPMs are enabled, and package upgrades
# during the image build can re-enable mpm_event behind prefork's back.
# Normalize here, at startup, where nothing can override it afterwards.
a2dismod -f mpm_event mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1 || true

# Railway assigns the listening port at runtime.
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
    sed -i "s/:80>/:$PORT>/" /etc/apache2/sites-available/000-default.conf
fi

# The storage volume mounts empty on first boot; recreate the layout Laravel
# expects and keep originals/thumbnails/processed alongside it.
mkdir -p storage/app/original storage/app/processed storage/app/thumbnails \
    storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/logs
chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan migrate --force

# Seeders are idempotent (updateOrCreate) so this is safe on every boot.
php artisan db:seed --force

exec "$@"
