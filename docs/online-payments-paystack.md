# Online payments (Paystack)

The storefront accepts card payments through **Paystack** (NGN). Card details are
entered on Paystack's secure page — no card data ever touches this app.

## Flow

```
Checkout → "Pay now (card)"
  → server initializes a Paystack transaction (amount in kobo, unique reference)
  → customer is redirected to Paystack's hosted page (enters card there)
  → on return, the callback verifies the payment
  → a signature-checked webhook confirms it server-to-server (backup path)
  → order is marked PAID and lands in the back office; staff just fulfil
```

- **Callback** (`/shop/<slug>/checkout/callback`) settles the order when the
  customer returns.
- **Webhook** (`/shop/<slug>/paystack/webhook`) settles it server-to-server even
  if the customer closes the tab. Both paths are **idempotent** — the order is
  never paid or emailed twice.
- If Paystack keys aren't configured, checkout offers **pay on delivery/pickup**
  only (no online option shown).

---

## Activation

### 1. Add your API keys to `.env` (on the server)

```
PAYSTACK_SECRET_KEY=sk_live_xxxxxxxx
PAYSTACK_PUBLIC_KEY=pk_live_xxxxxxxx
```

Use **test keys** (`sk_test_…` / `pk_test_…`) to trial the flow first, then swap
to live. Get them from the Paystack dashboard → *Settings → API Keys & Webhooks*.

### 2. Deploy

```bash
cd /opt/xismarket && git pull && ./deploy.sh
```

`deploy.sh` runs `php artisan optimize`, which caches config — so the new keys
take effect. (If you edit `.env` again later, re-run `./deploy.sh` or
`php artisan config:clear`.)

### 3. Register the webhook in the Paystack dashboard

*Settings → API Keys & Webhooks → Webhook URL:*

```
https://<your-domain>/shop/<your-store-slug>/paystack/webhook
```

Use the store slug from your storefront URL (e.g. `.../shop/nimikiddies/...`).

That's it — with the keys set, checkout automatically shows **"Pay now (card)"**.

---

## Configuration reference

| Env var | Purpose |
| --- | --- |
| `PAYSTACK_SECRET_KEY` | Server-side API calls + webhook signature. **Secret — never expose.** |
| `PAYSTACK_PUBLIC_KEY` | Public key (reserved for inline/JS use). |
| `PAYSTACK_BASE_URL` | Defaults to `https://api.paystack.co`; rarely changed. |

Config lives in `config/services.php` under `paystack`.

---

## Testing (before going live)

1. Set **test keys** and deploy.
2. Register the webhook URL (test mode has its own webhook field).
3. Place a storefront order → **Pay now** → use a Paystack **test card**
   (e.g. `4084 0840 8408 4081`, any future expiry, any CVV, OTP `123456`).
4. Confirm:
   - You're redirected back and see the **order confirmation**.
   - The order shows **paid** in the back office (Orders).
   - The webhook fired (Paystack dashboard → the transaction shows the webhook
     delivered `200`).
5. Switch to **live keys**, redeploy, update the live webhook URL.

---

## How the money is recorded

- Payment is recorded on the order (`payment_status = paid`, `payment_method =
  paystack`, the Paystack reference stored).
- Revenue / COGS post to the books when staff **fulfil** the order (unchanged from
  the existing order flow).

---

## Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| No "Pay now" option at checkout | Keys not set, or config not re-cached — set `PAYSTACK_SECRET_KEY` and run `./deploy.sh`. |
| Redirects to Paystack but order stays unpaid | Webhook not registered / unreachable, and the customer didn't return to the callback. Register the webhook URL; ensure HTTPS is reachable. |
| Webhook returns 401 | Signature mismatch — the `PAYSTACK_SECRET_KEY` on the server doesn't match the account sending the webhook (test vs live key mismatch). |
| "Could not start the payment" | Paystack API rejected `initialize` — check the secret key and that the amount/email are valid. |
| Customer has no receipt | Online payment requires an **email** (enforced at checkout); check the address was entered. |

---

## Notes

- **Currency:** NGN. Amounts are converted to **kobo** (×100) automatically.
- **HTTPS required** — you already have it via Caddy; Paystack won't call an
  insecure webhook.
- No PCI scope for you: card entry happens on Paystack, not your form.
- For a **Windows till / Android tablet** receipt printer setup, see
  [receipt-printer-setup.md](./receipt-printer-setup.md) and
  [receipt-printer-android.md](./receipt-printer-android.md).
