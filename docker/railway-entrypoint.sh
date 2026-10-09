#!/bin/sh
set -eu

cd /var/www/html
port="${PORT:-8080}"
case "$port" in ''|*[!0-9]*) echo 'PORT must be numeric' >&2; exit 1 ;; esac
if [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then
    echo 'PORT must be between 1 and 65535' >&2
    exit 1
fi
: "${APP_KEY:?Set APP_KEY in Railway variables}"
sed "s/__PORT__/$port/g" /etc/nginx/railway.conf.template > /etc/nginx/nginx.conf

# A newly mounted persistent volume starts without Laravel's storage folders.
mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R aquaoptom:aquaoptom storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
php artisan config:clear --no-interaction
php artisan migrate --force --no-interaction

# Initialize this deployment once, without adding any business/test fixtures.
if [ "${RAILWAY_INITIALIZE:-false}" = true ] && [ ! -f storage/app/private/.railway-initialized ]; then
    php artisan db:seed --class=ProductionReferenceSeeder --force --no-interaction
    has_owner="$(php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo App\Models\User::where("role", "OWNER")->exists() ? "yes" : "no";')"
    if [ "$has_owner" = no ]; then
        : "${RAILWAY_OWNER_EMAIL:?Set the first owner email}"
        : "${RAILWAY_OWNER_PASSWORD:?Set the first owner password}"
        preview_flag=''
        if [ "${PREVIEW_MODE:-false}" = true ]; then
            preview_flag='--preview'
        fi
        php artisan app:bootstrap-owner $preview_flag \
            --name="${RAILWAY_OWNER_NAME:-Owner}" \
            --email="$RAILWAY_OWNER_EMAIL" --password="$RAILWAY_OWNER_PASSWORD" --no-interaction
    fi
    touch storage/app/private/.railway-initialized
    chown aquaoptom:aquaoptom storage/app/private/.railway-initialized
fi
php artisan config:cache --no-interaction
php artisan view:cache --no-interaction
nginx -t
exec "$@"
