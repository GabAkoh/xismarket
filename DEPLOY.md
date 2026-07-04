# Deploying xismarket to production

xismarket is a self-contained, Dockerised Laravel application (admin + POS +
storefront). "Going live" means running it on a server and pointing a domain at
it — it is **not** a plugin you add to an existing website.

This guide takes you from a blank server to a live HTTPS site, plus backups and
updates. It assumes the production overlay added to the repo:

| File | Role |
|---|---|
| `docker-compose.prod.yml` | Adds Caddy (TLS on 80/443), unpublishes nginx/MySQL/Redis |
| `docker/caddy/Caddyfile` | Reverse proxy + automatic Let's Encrypt HTTPS |
| `.env.production.example` | Production environment template |
| `deploy.sh` | One-command build → migrate → cache → restart |
| `scripts/backup.sh` | Nightly DB + image backup with rotation |

---

## 1. What runs in production

```
            Internet
               │  :80 / :443
        ┌──────▼──────┐
        │    caddy    │  TLS termination, auto HTTPS
        └──────┬──────┘
               │  (internal docker network only)
        ┌──────▼──────┐   ┌──────────┐   ┌──────────┐
        │    nginx    │──▶│   app    │   │  worker  │  queue:work
        └─────────────┘   │ php-fpm  │   └────┬─────┘
                          └────┬─────┘        │
                       ┌───────▼───────┬──────▼──────┐
                       │     mysql     │    redis    │
                       └───────────────┴─────────────┘
```

Only Caddy is exposed to the internet (80/443). MySQL and Redis are reachable
only from inside the Docker network.

---

## 2. Prerequisites

- A **VPS** (DigitalOcean, Hetzner, Linode, AWS Lightsail…) running **Ubuntu
  22.04+**, ~**2 GB RAM** minimum.
- A **domain name** whose DNS you control.
- An **A record** for your domain (and/or `www`) pointing at the server's public
  IP. HTTPS issuance will not work until DNS resolves to the server.

---

## 3. Provision the server

SSH in as root (or a sudo user) and install Docker + the Compose plugin:

```bash
# Docker Engine + Compose plugin (official convenience script)
curl -fsSL https://get.docker.com | sh

# Run docker as a non-root deploy user
adduser --disabled-password --gecos "" deploy
usermod -aG docker deploy
```

From here on, work as the `deploy` user: `su - deploy`.

---

## 4. Firewall — open only 22 / 80 / 443

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp     # SSH
sudo ufw allow 80/tcp     # HTTP (Caddy; also needed for cert issuance)
sudo ufw allow 443/tcp    # HTTPS
sudo ufw enable
sudo ufw status
```

MySQL (3306) and Redis (6379) are **not** opened — the prod overlay keeps them
off the host entirely, so there is nothing public to firewall.

---

## 5. Get the code

```bash
git clone <your-repo-url> /opt/xismarket
cd /opt/xismarket
```

No git remote yet? `rsync`/`scp` the project up instead, but **exclude**
`.env`, `vendor/`, and `node_modules/`.

---

## 6. Configure the environment

```bash
cp .env.production.example .env
nano .env        # fill in every CHANGE_ME
```

Set at minimum:

- `APP_URL=https://yourdomain.com`
- `APP_DOMAIN=yourdomain.com` and `ACME_EMAIL=you@yourdomain.com` (used by Caddy)
- `DB_PASSWORD` and `DB_ROOT_PASSWORD` — strong, unique
- `MAIL_*` — real SMTP (receipts & password resets won't send otherwise)
- `IMAGE_AI_KEY` — only if you want the AI image tools

Then generate the app key:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml run --rm app php artisan key:generate
```

> Keep `APP_DEBUG=false` in production, and never commit the filled-in `.env`
> (it's gitignored).

---

## 7. First deploy

```bash
./deploy.sh
```

This builds the images, starts the stack, installs production dependencies,
runs migrations, links storage, caches config/routes/views, and starts the
queue worker. On the very first request to your domain, Caddy obtains a
Let's Encrypt certificate automatically.

Check it's up:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml ps
curl -I https://yourdomain.com/up        # Laravel health check -> 200
```

---

## 8. Create your store and admin login

Seed the role/permission catalogue, then provision your real tenant + owner:

```bash
C="docker compose -f docker-compose.yml -f docker-compose.prod.yml"

# Required: roles & permissions
$C exec -T app php artisan db:seed --class=PermissionSeeder --force

# Create your store + admin user
$C exec -T app php artisan tinker --execute="
app(\App\Services\TenantProvisioner::class)->provision(
    tenantData: ['name' => 'My Store', 'currency' => 'NGN'],
    ownerData:  ['name' => 'Admin', 'email' => 'admin@yourdomain.com', 'password' => 'a-strong-password'],
);
"
```

Then log in at `https://yourdomain.com` and your storefront lives at
`https://yourdomain.com/shop/<store-slug>`.

> Don't run the full `db:seed` / `DemoSeeder` on a real store — that creates the
> NimiKiddies demo data with the public `owner@demo.test / password` login.

---

## 9. Nightly backups (DB + images)

`scripts/backup.sh` writes a gzipped SQL dump and a tarball of the uploaded
images to `/opt/xismarket-backups`, and pushes every backup off-box to
Backblaze B2. Retention is split because the image tarballs are multiple GB
each: it keeps a small **count** of storage tarballs locally (`KEEP_STORAGE`,
default 2) and prunes the oldest **before** writing the new one — so a big tar
can never fill the disk, fail, and leave old backups un-rotated. DB dumps are
tiny and kept locally by age (`KEEP_DB_DAYS`). B2 keeps a longer history of both
(`B2_KEEP_STORAGE_DAYS` / `B2_KEEP_DB_DAYS`).

> **Off-box is not optional.** The image tarballs are ~6 GB and the VPS disk is
> ~48 GB; keeping many local copies fills the disk and takes the site down (500s
> because Laravel can't write). Off-box upload is what makes aggressive local
> pruning safe.

**One-time B2 setup:** create a private B2 bucket + an application key (Read/Write,
restricted to that bucket), then configure the rclone remote on the VPS:

```bash
curl -fsSL https://rclone.org/install.sh | bash        # if rclone isn't installed
rclone config create b2 b2 account <keyID> key <applicationKey>
rclone lsf b2:<bucket>                                  # verify access (empty is fine)
```

Test it once by hand:

```bash
./scripts/backup.sh
ls -lh /opt/xismarket-backups
rclone lsl b2:<bucket>/backups        # confirm the upload landed off-box
```

Then schedule it via cron (`crontab -e`) — 02:30 every night:

```cron
30 2 * * * cd /opt/xismarket && BACKUP_DIR=/opt/xismarket-backups KEEP_STORAGE=2 KEEP_DB_DAYS=30 RCLONE_REMOTE=b2 B2_BUCKET=<bucket> B2_PREFIX=backups B2_KEEP_STORAGE_DAYS=30 B2_KEEP_DB_DAYS=90 ./scripts/backup.sh >> /opt/xismarket-backups/backup.log 2>&1
```

To run without off-box upload (local only), set `RCLONE_REMOTE=` (empty).

### Restore from a backup

If the backup isn't on the local disk anymore (older than local retention),
pull it from B2 first:

```bash
rclone lsl b2:<bucket>/backups                                   # list what's off-box
rclone copy b2:<bucket>/backups/db-YYYY-MM-DD-HHMM.sql.gz /opt/xismarket-backups/
rclone copy b2:<bucket>/backups/storage-YYYY-MM-DD-HHMM.tar.gz /opt/xismarket-backups/
```

Then restore:

```bash
C="docker compose -f docker-compose.yml -f docker-compose.prod.yml"

# Database
gunzip < /opt/xismarket-backups/db-YYYY-MM-DD-HHMM.sql.gz \
  | $C exec -T mysql sh -c 'exec mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'

# Images
tar -xzf /opt/xismarket-backups/storage-YYYY-MM-DD-HHMM.tar.gz
```

---

## 10. Updating to a new version

```bash
cd /opt/xismarket
./deploy.sh        # pull → build → composer install → migrate → cache → restart worker
```

`deploy.sh` is safe to re-run any time; it is idempotent.

---

## 11. Logs & troubleshooting

```bash
C="docker compose -f docker-compose.yml -f docker-compose.prod.yml"

$C logs -f caddy            # TLS / cert issuance problems
$C logs -f app worker       # application & queue errors
$C ps                       # container health
$C exec app php artisan about
```

Common issues:

- **Cert not issuing** → DNS isn't pointing at the server yet, or port 80/443 is
  blocked. Caddy needs inbound 80 for the ACME challenge.
- **Images 404** → `storage:link` didn't run (re-run `deploy.sh`).
- **Emails not sending** → `MAIL_MAILER` still `log`, or SMTP creds wrong; test
  with `php artisan mail:test you@example.com`.
- **Pages over plain http / redirect loops** → `APP_URL` must be `https://…`
  (trusted-proxy handling is already configured in `bootstrap/app.php`).
- **Config changes ignored** → config is cached; re-run `deploy.sh` (or
  `php artisan optimize`) after editing `.env`.
