# Managing non-POS payments and expenses

Money that doesn't flow through the POS — expenses, supplier payments, customer
debts, owner draws, transfers — is handled with **double-entry journal entries**
(there's no separate "expenses" screen) plus the AP/AR tools.

> Rule of thumb: anything that isn't a sale or a cash-drawer movement → **post a
> journal** (Debit where the money goes, Credit where it came from).

---

## Recording an expense (rent, utilities, supplies…)

*Accounting → Journals → New journal entry*:

- **Debit** the expense account — **6000 Operating Expenses** (or a specific
  sub-account — see the tip below).
- **Credit** where the money came from — **1000 Cash** or **1010 Bank**.
- Add a memo + date, check debits = credits, and **Post**.

**Example — ₦20,000 rent paid from the bank:**

```
Dr 6000 Operating Expenses   20,000
Cr 1010 Bank                 20,000
```

**Tip:** for cleaner reporting, first add expense **sub-accounts** under
Operating Expenses in *Accounting → Accounts* (e.g. `6100 Rent`,
`6200 Utilities`, `6300 Transport`) and debit those instead of the generic 6000.

---

## Paying a supplier

- If you raised a **Purchase Order**, receiving it already created a payable
  (*Cr 2000 Accounts Payable*). When you pay, post a journal:

  ```
  Dr 2000 Accounts Payable   <amount>
  Cr 1000 Cash (or 1010 Bank) <amount>
  ```

- A one-off purchase with no PO → book it directly as an expense/inventory
  journal as above.
- Manage supplier records in *Accounting → Suppliers*.

---

## Collecting money a customer owes (credit sales / on account)

*Customers → open the customer → Receive payment*:

- Enter the amount and method; it **allocates across their unpaid invoices**
  automatically.
- A printable **account statement** shows their running ledger (invoices vs
  payments).

---

## Other non-POS movements (all via journals)

- **Owner draws / capital injected** — use **3000 Owner Equity**.
- **Bank ↔ cash transfers** — `Dr 1010 Bank / Cr 1000 Cash` (or the reverse).
- **Corrections / write-offs** — a balancing journal entry.

---

## Where to review

- *Accounting → Journals* — every manual entry, with its lines.
- *Accounting → Accounts* — each account's balance / ledger (Cash, Bank,
  Expenses, AP, AR…).

---

## Quick reference

| Transaction | Entry |
| --- | --- |
| Pay an expense (cash) | Dr 6000 Operating Expenses / Cr 1000 Cash |
| Pay an expense (bank) | Dr 6000 Operating Expenses / Cr 1010 Bank |
| Pay a supplier (against AP) | Dr 2000 Accounts Payable / Cr 1000 or 1010 |
| Owner takes money out | Dr 3000 Owner Equity / Cr 1000 or 1010 |
| Owner puts money in | Dr 1000 or 1010 / Cr 3000 Owner Equity |
| Move cash to the bank | Dr 1010 Bank / Cr 1000 Cash |
| Customer pays what they owe | Customers → Receive payment (allocates to invoices) |

### Chart-of-accounts cheat sheet

| Code | Account |
| --- | --- |
| 1000 | Cash |
| 1010 | Bank |
| 1200 | Accounts Receivable |
| 1300 | Inventory |
| 2000 | Accounts Payable |
| 2100 | Tax Payable |
| 2200 | Customer Store Credit |
| 3000 | Owner Equity |
| 4000 | Sales |
| 5000 | Cost of Goods Sold |
| 6000 | Operating Expenses |
