#!/usr/bin/env bash
#
# migrate-catalog.sh — rebuild the product catalogue with variants, on a server.
#
# Wipes products and re-imports them from CSVs:
#   1. Shopify products + variants + images  (products_export.csv)
#   2. Odoo-only products, grouped variants   (Odoo Product Variant.csv)
#   3. Odoo product images, base64            (Odoo Products Recent.csv)
#
# DESTRUCTIVE: it deletes all products first. Order/sale history keeps its
# name/price snapshots but loses product links. A full DB backup is taken
# before anything changes (see ROLLBACK at the bottom).
#
# Prerequisite — upload the three CSVs to the repo root first, from your machine:
#   scp products_export.csv "Odoo Product Variant.csv" "Odoo Products Recent.csv" \
#       root@HOST:/opt/xismarket/
#
# Usage (on the server, from the repo root):
#   bash scripts/migrate-catalog.sh --yes
#   # to survive an SSH disconnect (image downloads take a while):
#   nohup bash scripts/migrate-catalog.sh --yes > migrate.log 2>&1 &
#   tail -f migrate.log
#
set -euo pipefail

# --- config (override via env if your filenames differ) ---
SHOPIFY_CSV="${SHOPIFY_CSV:-products_export.csv}"
ODOO_VARIANTS_CSV="${ODOO_VARIANTS_CSV:-Odoo Product Variant.csv}"
ODOO_IMAGES_CSV="${ODOO_IMAGES_CSV:-Odoo Products Recent.csv}"

cd "$(dirname "$0")/.."   # repo root

# --- guard: this wipes products, so require an explicit go-ahead ---
if [ "${1:-}" != "--yes" ] && [ "${CONFIRM:-}" != "yes" ]; then
    echo "This DELETES all products and re-imports them from the CSVs."
    echo "Re-run with --yes (or CONFIRM=yes) once the three CSVs are uploaded to $(pwd)."
    exit 1
fi

# --- check the CSVs are present ---
for f in "$SHOPIFY_CSV" "$ODOO_VARIANTS_CSV" "$ODOO_IMAGES_CSV"; do
    [ -f "$f" ] || { echo "Missing CSV: '$f' — upload it to $(pwd) first."; exit 1; }
done

# 0. Safety backup of the whole DB (uses the mysql container's own credentials)
BACKUP="backup-before-variants-$(date +%F-%H%M).sql"
echo "[0/5] Backing up the database to $BACKUP ..."
docker compose exec -T mysql sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > "$BACKUP"
echo "      $(ls -lh "$BACKUP" | awk '{print $5, $9}')"

# 1. Deploy the variant code + run migrations
echo "[1/5] Deploying code + migrations ..."
git pull
./deploy.sh

# 2. Stage the CSVs inside the app container (where the importers read from)
echo "[2/5] Staging CSVs into the container ..."
docker compose cp "$SHOPIFY_CSV"       app:/var/www/storage/app/_mig_shopify.csv
docker compose cp "$ODOO_VARIANTS_CSV" app:/var/www/storage/app/_mig_odoo_variants.csv
docker compose cp "$ODOO_IMAGES_CSV"   app:/var/www/storage/app/_mig_odoo_images.csv

# 3. Write the migration script into the container
echo "[3/5] Writing migration script ..."
docker compose exec -T app sh -c 'cat > storage/app/_migrate.php' <<'PHP'
<?php
app(App\Support\Tenancy::class)->set(App\Models\Tenant::first());

echo "Wiping products...\n";
App\Models\Inventory\Product::query()->chunkById(1000, fn ($c) => $c->each->delete());

echo "Importing Shopify (downloading images)...\n";
$s = app(App\Services\Inventory\ShopifyProductImporter::class)
    ->import(storage_path('app/_mig_shopify.csv'), true, false);
echo "  Shopify: created={$s['created']} variants={$s['variants']} images={$s['images']} errors=".count($s['errors'])."\n";

echo "Importing Odoo variants (add Odoo-only products)...\n";
$o = app(App\Services\Inventory\OdooProductImporter::class)
    ->import(storage_path('app/_mig_odoo_variants.csv'), 'create');
echo "  Odoo: created={$o['created']} variants={$o['variants']} skipped={$o['skipped']} errors=".count($o['errors'])."\n";

echo "Filling Odoo images...\n";
$i = app(App\Services\Inventory\OdooProductImporter::class)
    ->import(storage_path('app/_mig_odoo_images.csv'), 'images');
echo "  Odoo images: filled={$i['images']} skipped={$i['skipped']}\n";

echo "DONE.\n";
PHP

# 4. Run it (foreground; nohup the whole script to survive a disconnect)
echo "[4/5] Running import — this takes a while (image downloads) ..."
docker compose exec -T app php artisan tinker storage/app/_migrate.php

# 5. Clean up staged files
echo "[5/5] Cleaning up staged files ..."
docker compose exec -T app rm -f \
    storage/app/_mig_shopify.csv \
    storage/app/_mig_odoo_variants.csv \
    storage/app/_mig_odoo_images.csv \
    storage/app/_migrate.php

cat <<EOF

Catalogue migration complete. Backup kept at: $BACKUP
Now smoke-test the live site (storefront product page, POS variant picker).

# ─────────────────────────────────────────────────────────────────────────────
# ROLLBACK — only if the migration went wrong. Restores the pre-migration DB.
# The backup includes DROP TABLE statements, so this fully reverts the schema
# and data. Old image files remain on disk, so the previous images reappear.
# ─────────────────────────────────────────────────────────────────────────────
# docker compose exec -T mysql sh -c 'mysql -u root -p"\$MYSQL_ROOT_PASSWORD" "\$MYSQL_DATABASE"' < $BACKUP \\
#   && docker compose restart app worker nginx \\
#   && echo "Rolled back to $BACKUP"
EOF
