# Managing online orders

How to handle storefront orders in xismarket, following the order lifecycle.
Everything lives under *Orders*.

---

## When an order comes in

1. A customer checks out on the storefront → an order is created, with its
   **payment status** and a **pickup** or **delivery** type.
2. A **new-order alert** fires to your configured recipients (**email / SMS** —
   set the recipients in settings).
3. Open *Orders* (or click through from the alert) to see it. The list shows
   status and payment at a glance.

---

## Working an order

Open the order from *Orders*, then:

- **Mark paid** — if it wasn't paid online, record the payment (method +
  reference). The order flips to *paid*.
- **Fulfill** — when you pick/pack/ship it (or it's ready for pickup). This is
  the step that **decrements stock** for each line/variant, guarded against
  overselling. Delivery income is posted to the books where applicable.
- **Update status** — move it along its stages as needed.
- **Email receipt** — send the customer a copy.

---

## When something goes wrong

- **Cancel** — for an order you won't fulfil (typically before fulfilment).
- **Refund** — after payment/fulfilment; it **restocks** the items and posts the
  reversing entries. The refund prompt handles store-credit vs cash.
- **Customer self-cancel** — customers can cancel their own order from
  **My Account** within the allowed window, which also notifies you.

---

## Keep an eye on

- **Reports → Orders** (with export) for volumes, fulfilment and revenue.
- The new-order alert **recipients and channels** are editable in settings, so
  the right people get pinged.

---

## Typical flow

```
New order → (Mark paid) → Fulfill → Email receipt → done
```

Exceptions:

- **Cancel** — won't fulfil it.
- **Refund** — already paid/shipped; restocks the items.

---

## Notes

- **Fulfil is the stock-moving step.** Stock isn't deducted until you fulfil, and
  the out-of-stock guard blocks fulfilling more than you have.
- **Refund restocks; a cancel before fulfilment doesn't need restocking** —
  nothing was deducted yet.

---

## Quick reference

| Action | When |
| --- | --- |
| Mark paid | Payment received (if not paid online) |
| Fulfill | Picked/packed/shipped or ready for pickup — deducts stock |
| Update status | Move the order along its stages |
| Email receipt | Send the customer a copy |
| Cancel | Won't fulfil (before fulfilment) |
| Refund | After payment/fulfilment — restocks + reverses the books |
| Orders report | Volumes, fulfilment, revenue (Reports → Orders) |
