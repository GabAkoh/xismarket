#!/usr/bin/env bash
# Nightly backup for xismarket: gzipped database dump + uploaded images.
# Add to cron (see DEPLOY.md). Override defaults with env vars:
#   BACKUP_DIR=/opt/xismarket-backups  KEEP_DAYS=14  ./scripts/backup.sh
set -euo pipefail
cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker-compose.yml -f docker-compose.prod.yml"
BACKUP_DIR="${BACKUP_DIR:-/opt/xismarket-backups}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date +%F-%H%M)"

mkdir -p "$BACKUP_DIR"

# 1. Database — consistent dump using the credentials already in the container.
$COMPOSE exec -T mysql sh -c \
    'exec mysqldump --single-transaction --quick --no-tablespaces -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > "$BACKUP_DIR/db-$STAMP.sql.gz"

# 2. Uploaded images (product photos live under storage/app/public).
tar -czf "$BACKUP_DIR/storage-$STAMP.tar.gz" storage/app/public

# 3. Rotate out anything older than KEEP_DAYS.
find "$BACKUP_DIR" -name 'db-*.sql.gz' -mtime +"$KEEP_DAYS" -delete
find "$BACKUP_DIR" -name 'storage-*.tar.gz' -mtime +"$KEEP_DAYS" -delete

echo "Backup complete -> $BACKUP_DIR (db-$STAMP.sql.gz, storage-$STAMP.tar.gz)"
