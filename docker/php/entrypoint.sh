#!/bin/sh
# Ensure Laravel's writable directories are owned by the php-fpm runtime user.
# storage/ and bootstrap/cache are bind-mounted from the host and arrive owned
# by root, which makes Blade view compilation (tempnam) fail under php-fpm
# (which runs as www-data). Fix it on every container start.
set -e

for dir in /var/www/storage /var/www/bootstrap/cache; do
    if [ -d "$dir" ]; then
        chown -R www-data:www-data "$dir" 2>/dev/null || true
        chmod -R ug+rwX "$dir" 2>/dev/null || true
    fi
done

exec "$@"
