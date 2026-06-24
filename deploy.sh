#!/usr/bin/env bash
# One-command production deploy/update for xismarket.
#
#   First time:
#     cp .env.production.example .env  # then fill in every CHANGE_ME
#     docker compose -f docker-compose.yml -f docker-compose.prod.yml run --rm app php artisan key:generate
#     ./deploy.sh
#
#   Updates:
#     ./deploy.sh                      # pull → build → composer → migrate → cache → restart
set -euo pipefail
cd "$(dirname "$0")"

COMPOSE="docker compose -f docker-compose.yml -f docker-compose.prod.yml"

if [ ! -f .env ]; then
    echo "ERROR: no .env found. Run:  cp .env.production .env  and fill it in first." >&2
    exit 1
fi

echo "==> Pulling latest code"
if [ -d .git ]; then
    git pull --ff-only
else
    echo "    (not a git checkout — skipping git pull)"
fi

echo "==> Building images"
$COMPOSE build

echo "==> Starting services"
$COMPOSE up -d

echo "==> Installing PHP dependencies (production)"
$COMPOSE exec -T app composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Running migrations"
$COMPOSE exec -T app php artisan migrate --force

echo "==> Linking storage (idempotent)"
$COMPOSE exec -T app php artisan storage:link 2>/dev/null || true

echo "==> Caching config, routes and views"
$COMPOSE exec -T app php artisan optimize

echo "==> Restarting queue worker to pick up new code"
$COMPOSE restart worker

echo ""
echo "Deploy complete. Tail logs with:  $COMPOSE logs -f app caddy worker"
