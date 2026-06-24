# Publishing xismarket to https://sales.nimisystems.com

A domain-specific runbook for going live on a **VPS** with the Docker kit.
For the full reference and troubleshooting, see [`DEPLOY.md`](DEPLOY.md).

> **Why a VPS?** `sales.nimisystems.com` currently sits on Hostinger *shared*
> hosting (LiteSpeed/hPanel), which cannot run Docker, Redis or a background
> queue worker. This app's deploy kit needs a VPS (root + Docker).

Steps marked 🧑‍💻 are manual (only you can do them); the rest is copy‑paste.

---

## 1. 🧑‍💻 Provision a VPS

- hPanel → **VPS → Get a plan** (~2 GB RAM is plenty).
- OS template: **"Ubuntu 24.04 with Docker"** (pre-installed). If you pick plain
  Ubuntu instead, install Docker per `DEPLOY.md §3`.
- Note the VPS **public IP** (referred to below as `YOUR_VPS_IP`).

## 2. 🧑‍💻 Point the subdomain at the VPS

`sales.nimisystems.com` currently resolves to `82.29.157.185` (shared hosting).
Repoint it:

- hPanel → **Domains → nimisystems.com → DNS Zone**.
- Edit the **A** record named `sales` → value = **`YOUR_VPS_IP`** → **TTL 300** → save.
- Delete any **AAAA** record named `sales` (so it can't serve from the old host).
- Wait for propagation, then confirm:
  ```bash
  nslookup sales.nimisystems.com      # must show YOUR_VPS_IP before deploying
  ```

> Do not run the deploy until DNS resolves to the VPS — Caddy needs it to issue
> the TLS certificate.

## 3. SSH in, firewall, get the code

```bash
ssh root@YOUR_VPS_IP

# Only SSH + web open
ufw allow 22/tcp && ufw allow 80/tcp && ufw allow 443/tcp && ufw --force enable

git clone https://github.com/GabAkoh/xismarket.git /opt/xismarket
cd /opt/xismarket
```

🧑‍💻 **Private-repo auth:** when `git clone` prompts for a password, paste a
GitHub **Personal Access Token** (GitHub → Settings → Developer settings →
Fine-grained tokens → read access to `xismarket`). Your GitHub username is the
username.

## 4. Configure the environment

```bash
cp .env.production.example .env
nano .env
```

Set these for this domain (and strong DB passwords + real SMTP):

```dotenv
APP_URL=https://sales.nimisystems.com
APP_DOMAIN=sales.nimisystems.com
ACME_EMAIL=gab.nimi@gmail.com

DB_PASSWORD=CHANGE_ME_strong_password
DB_ROOT_PASSWORD=CHANGE_ME_strong_root_password

# Real SMTP so receipts & password resets send:
MAIL_MAILER=smtp
MAIL_HOST=CHANGE_ME.smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=CHANGE_ME
MAIL_PASSWORD=CHANGE_ME
MAIL_FROM_ADDRESS="orders@nimisystems.com"

# Optional AI image tools:
# IMAGE_AI_KEY=your-gemini-key
```

Generate the app key:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml run --rm app php artisan key:generate
```

## 5. Deploy

```bash
./deploy.sh
```

On the first request, Caddy obtains a Let's Encrypt certificate for
`sales.nimisystems.com` automatically.

## 6. Create the store + admin login

```bash
C="docker compose -f docker-compose.yml -f docker-compose.prod.yml"

$C exec -T app php artisan db:seed --class=PermissionSeeder --force

$C exec -T app php artisan tinker --execute="
app(\App\Services\TenantProvisioner::class)->provision(
  tenantData: ['name' => 'NimiKiddies', 'currency' => 'NGN'],
  ownerData:  ['name' => 'Admin', 'email' => 'admin@nimisystems.com', 'password' => 'a-strong-password'],
);"
```

> Don't run the full `db:seed`/`DemoSeeder` — that creates demo data with the
> public `owner@demo.test / password` login.

## 7. Verify

```bash
curl -I https://sales.nimisystems.com/up      # expect 200
```

- **Admin:** https://sales.nimisystems.com
- **Storefront:** https://sales.nimisystems.com/shop/&lt;store-slug&gt;

## 8. Nightly backups

```bash
crontab -e
```
```cron
30 2 * * * cd /opt/xismarket && BACKUP_DIR=/opt/xismarket-backups KEEP_DAYS=14 ./scripts/backup.sh >> /opt/xismarket-backups/backup.log 2>&1
```

## 9. Future updates

```bash
cd /opt/xismarket && ./deploy.sh
```

---

### Watch-outs
- Deploy **only after** `nslookup sales.nimisystems.com` shows the VPS IP.
- The root site `nimisystems.com` (Hostinger Horizons) is untouched — only the
  `sales` subdomain moves to the VPS.
- Keep `APP_DEBUG=false` and use strong, unique DB passwords.
