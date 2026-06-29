# Daily POS routine

The day-to-day cashier workflow in xismarket, built around the **shift**: each
session opens with a counted cash float and closes with a counted drawer, so the
cash reconciles at the end of the day.

> The shift is the backbone. Open a shift **before** selling — that's what makes
> the end-of-day expected-vs-counted reconciliation add up. Sales rung without an
> open shift won't be reconciled against a drawer.

---

## 🟢 Opening (start of day)

1. **Open the shift** on the register (*Registers → open shift*). Count the cash
   drawer and enter it as the **opening float**. The shift goes live and every
   sale and cash movement is tied to it.
2. *(Optional)* Check **reorder flags** — products at or under their reorder
   level are flagged so you know what's running low.

---

## 🛒 Throughout the day — ringing sales

On the POS screen:

- **Add items** — scan a barcode or tap a tile. Multi-variant products
  (size/colour) open the **variant picker**; out-of-stock variants are blocked.
- **Attach a customer** (optional) — required to earn/redeem **loyalty points**
  and to pay with **store credit (wallet)**.
- **Apply a coupon** if the customer has one.
- **Take payment** — cash, card, **wallet/store credit**, or a **split** across
  methods. Enter cash tendered and it shows the change due.
- **Receipt** — print to the receipt printer or **email** it.

---

## 💵 Cash drawer management (as needed)

- **Cash In / Cash Out** (*Accounts → Cash In/Out*, or the POS drawer action) for
  petty-cash payouts, adding change, supplier payments, etc. Pick a **reason**
  from your editable list (*Settings → Cash reasons*). These adjust the shift's
  expected cash.
- **Wallet top-ups** — load store credit for a customer (posts to the
  store-credit liability account).

---

## 🔁 Other in-day tasks

- **Online orders** arrive with an **alert** (email/SMS) — review and fulfil
  them. Recipients are configurable in settings.
- **Returns / refunds** and **order cancellations** (with the refund prompt).

---

## 🔴 Closing (end of day)

1. **Close the shift** (*Registers → close shift*). Count the drawer and enter the
   **closing amount**. The system computes:

   ```
   Expected = opening float + cash sales + cash in − cash out
   Variance = counted closing amount − expected
   ```

   and shows the **over/short variance**. Add a note for any discrepancy.
2. **Review the day** — *Reports → Payments Summary* (totals by payment method,
   your Z-report) and the sales report. It can be exported.
3. **Bank / cash up** the drawer.

---

## 📅 Periodic (not daily)

- **Reorder** flagged low-stock products (receive stock / purchase orders).
- **New-arrivals broadcast** and the **weekly digest** to subscribers go out on
  the scheduler.

---

## Quick reference

| Task | Where |
| --- | --- |
| Open / close shift | Registers → open/close shift |
| Ring a sale | POS screen |
| Cash in / out | Accounts → Cash In/Out (or POS drawer) |
| Edit cash reasons | Settings → Cash reasons |
| Customer loyalty / store credit | Attach a customer on the sale |
| End-of-day totals | Reports → Payments Summary |
| Low-stock | Reorder flags on the products list |

### Two rules of thumb

- **Open a shift before the first sale** — no shift, no drawer reconciliation.
- **Loyalty points and store credit only apply when a customer is attached** to
  the sale.
