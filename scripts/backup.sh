#!/usr/bin/env bash
# Nightly backup for xismarket: gzipped database dump + uploaded images.
# Keeps a few recent copies on the local disk (fast restore) and pushes every
# backup off-box to Backblaze B2 (durable, off-site history).
#
# Add to cron (see DEPLOY.md). Override defaults with env vars:
#   BACKUP_DIR=/opt/xismarket-backups KEEP_STORAGE=2 KEEP_DB_DAYS=30 \
#   RCLONE_REMOTE=b2 B2_BUCKET=XISMart B2_PREFIX=backups \
#   B2_KEEP_STORAGE_DAYS=30 B2_KEEP_DB_DAYS=90 ./scripts/backup.sh
#
# Retention is deliberately split:
#   - Storage tarballs are large (whole image library, multiple GB each), so we
#     keep a small COUNT of them locally (KEEP_STORAGE) and prune the oldest
#     *before* writing the new one — otherwise a big tar can fill the disk, fail,
#     and leave the old backups un-rotated (the bug that took sales down once).
#   - DB dumps are tiny, so we keep them locally by age (KEEP_DB_DAYS).
#   - B2 keeps a longer history of both, pruned by age (B2_KEEP_*_DAYS).
# Off-box upload is best-effort: if B2 is unreachable the local backup + rotation
# still complete, and a "WARNING: B2" line is logged for monitoring.
set -euo pipefail
cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker-compose.yml -f docker-compose.prod.yml"
BACKUP_DIR="${BACKUP_DIR:-/opt/xismarket-backups}"
KEEP_STORAGE="${KEEP_STORAGE:-2}"     # local storage tarballs to retain
KEEP_DB_DAYS="${KEEP_DB_DAYS:-30}"    # days of local (tiny) DB dumps to retain
STAMP="$(date +%F-%H%M)"

# Off-box (Backblaze B2 via rclone). Set RCLONE_REMOTE="" to disable uploads.
RCLONE_REMOTE="${RCLONE_REMOTE:-b2}"
B2_BUCKET="${B2_BUCKET:-XISMart}"
B2_PREFIX="${B2_PREFIX:-backups}"
B2_KEEP_STORAGE_DAYS="${B2_KEEP_STORAGE_DAYS:-30}"
B2_KEEP_DB_DAYS="${B2_KEEP_DB_DAYS:-90}"

mkdir -p "$BACKUP_DIR"

# Upload a file to B2, best-effort (never aborts the backup on a B2 failure).
b2_upload() {
    local file="$1"
    [ -n "$RCLONE_REMOTE" ] || return 0
    if rclone copyto "$file" \
        "$RCLONE_REMOTE:$B2_BUCKET/$B2_PREFIX/$(basename "$file")" 2>&1; then
        echo "Uploaded to B2: $(basename "$file")"
    else
        echo "WARNING: B2 upload failed for $(basename "$file") (kept locally)"
    fi
}

# 1. Database — consistent dump using the credentials already in the container.
$COMPOSE exec -T mysql sh -c \
    'exec mysqldump --single-transaction --quick --no-tablespaces -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > "$BACKUP_DIR/db-$STAMP.sql.gz"
b2_upload "$BACKUP_DIR/db-$STAMP.sql.gz"

# 2. Prune old local storage tarballs FIRST so the new one has room to land.
#    Keep the newest (KEEP_STORAGE - 1) now; after we write the new one below the
#    local count is exactly KEEP_STORAGE. Pruning *before* writing is the safety
#    property — it guarantees the big tar always has room and can't fill the disk.
ls -1t "$BACKUP_DIR"/storage-*.tar.gz 2>/dev/null \
    | tail -n +"$KEEP_STORAGE" \
    | while read -r old; do
        echo "Rotating out old local storage backup: $old"
        rm -f "$old"
    done

# 3. Uploaded images (product photos live under storage/app/public).
#    Write to a .partial file and only rename on success, so a failed/truncated
#    tar (e.g. disk full) never leaves a corrupt "storage-*.tar.gz" behind.
PARTIAL="$BACKUP_DIR/storage-$STAMP.tar.gz.partial"
trap 'rm -f "$PARTIAL"' EXIT
tar -czf "$PARTIAL" storage/app/public
mv "$PARTIAL" "$BACKUP_DIR/storage-$STAMP.tar.gz"
trap - EXIT
b2_upload "$BACKUP_DIR/storage-$STAMP.tar.gz"

# 4. Rotate out local DB dumps older than KEEP_DB_DAYS (they are small).
find "$BACKUP_DIR" -name 'db-*.sql.gz' -mtime +"$KEEP_DB_DAYS" -delete

# 5. Prune B2 history by age (best-effort).
if [ -n "$RCLONE_REMOTE" ]; then
    R="$RCLONE_REMOTE:$B2_BUCKET/$B2_PREFIX"
    rclone delete "$R" --include 'storage-*.tar.gz' --min-age "${B2_KEEP_STORAGE_DAYS}d" 2>&1 \
        || echo "WARNING: B2 storage prune failed"
    rclone delete "$R" --include 'db-*.sql.gz' --min-age "${B2_KEEP_DB_DAYS}d" 2>&1 \
        || echo "WARNING: B2 db prune failed"
fi

echo "Backup complete -> $BACKUP_DIR + B2:$B2_BUCKET/$B2_PREFIX (db-$STAMP.sql.gz, storage-$STAMP.tar.gz)"
