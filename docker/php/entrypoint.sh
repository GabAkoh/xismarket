#!/bin/sh
# Ensure Laravel's writable directories are owned by the php-fpm runtime user.
# storage/ and bootstrap/cache are bind-mounted from the host and arrive owned
# by root, which makes Blade view compilation (tempnam) fail under php-fpm
# (which runs as www-data). Fix it on every container start.
set -e

# Pre-create dirs that a root CLI/worker might otherwise create first (owned by
# root), which would lock out php-fpm (www-data) — e.g. the import staging dir.
mkdir -p /var/www/storage/app/private/imports /var/www/storage/app/public 2>/dev/null || true

for dir in /var/www/storage /var/www/bootstrap/cache; do
    if [ -d "$dir" ]; then
        chown -R www-data:www-data "$dir" 2>/dev/null || true
        chmod -R ug+rwX "$dir" 2>/dev/null || true
    fi
done

exec "$@"
