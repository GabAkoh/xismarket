# Email (Mailgun SMTP)

The app sends customer **order receipts** and **status-update** emails. Locally it
uses the `log` driver (emails are written to the application log). Production uses
**Mailgun over SMTP** — no extra Composer package is required (Laravel's built-in
SMTP transport handles it).

## Going live with Mailgun

1. **Add & verify a sending domain** in Mailgun (e.g. `mg.yourstore.com`): add the
   DNS records Mailgun gives you (SPF + DKIM `TXT`, and the `MX` records) and wait
   for verification.

2. **Get SMTP credentials** — Mailgun dashboard → *Sending* → *Domain settings* →
   *SMTP credentials*. You get a username (`postmaster@mg.yourstore.com`) and a
   password.

3. **Set these in `.env`** (values shown are examples):

   ```dotenv
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.mailgun.org          # EU domains: smtp.eu.mailgun.org
   MAIL_PORT=587
   MAIL_SCHEME=null                    # 587 uses STARTTLS automatically
   MAIL_USERNAME="postmaster@mg.yourstore.com"
   MAIL_PASSWORD="your-mailgun-smtp-password"
   MAIL_TIMEOUT=10
   MAIL_FROM_ADDRESS="orders@mg.yourstore.com"   # must be on the verified domain
   MAIL_FROM_NAME="Your Store"
   ```

   > Port 465 alternative (implicit TLS): `MAIL_PORT=465` and `MAIL_SCHEME=smtps`.

4. **Reload config**: `php artisan config:clear` (and `config:cache` in production).

5. **Send a test**:

   ```bash
   php artisan tinker --execute "Illuminate\Support\Facades\Mail::raw('xismarket test', fn(\$m) => \$m->to('you@example.com')->subject('Mailgun test'));"
   ```

   It should appear in Mailgun → *Sending* → *Logs*, then in your inbox.

## Notes

- **Per-tenant sender:** receipts/status emails set the *from name* to the store's
  name and *reply-to* to the store's email; the *from address* stays the verified
  Mailgun address (required for deliverability).
- **Best-effort:** all sends are wrapped in try/catch — a mail outage never blocks
  checkout or order updates. `MAIL_TIMEOUT=10` keeps a misconfigured SMTP from
  hanging web requests.
- **Throughput:** for high volume, make the mailables queued (`ShouldQueue`) and run
  a queue worker (`php artisan queue:work`) so sending happens out-of-band. The
  `QUEUE_CONNECTION` is already `database`.
- **Switch back to local logging** any time with `MAIL_MAILER=log`.
