# Receipt printer setup on an Android tablet (Wi-Fi / network printer)

How to print POS receipts from the web POS on an Android tablet to a **network
(Wi-Fi/LAN) thermal receipt printer**, using **RawBT** as the bridge.

> **Why a bridge app is needed:** the POS runs in a browser, and browsers can't
> open a raw connection to a printer's IP. The cloud server can't reach the
> shop's private network either. **RawBT** runs on the tablet, takes the
> browser's print job, and sends it to the printer's IP as ESC/POS. Android
> browsers can't print fully silently, but RawBT gets it to one tap.

---

## 1. Put the printer on Wi-Fi and find its IP

1. Connect the printer to the shop Wi-Fi (via its setup app / WPS / config).
2. **Find its IP:** most thermal printers print a **self-test** with the IP when
   you hold the **Feed** button while powering on. Note it (e.g. `192.168.1.50`).
3. **Set a static IP / DHCP reservation** on the printer so the IP doesn't change
   later (a changed IP is the #1 cause of "queued but won't print").
4. Put the **tablet on the same Wi-Fi** — not a guest network, and the access
   point must not have "AP/client isolation" enabled.

---

## 2. Install RawBT and add the printer by IP

1. Install **RawBT** from the Play Store.
2. RawBT → **Add printer → Network / TCP** → enter:
   - **IP address:** the printer's IP
   - **Port:** `9100` (standard raw ESC/POS port; a few models differ)
3. Set **paper width** to your roll (**58** or **80 mm**).
4. **Print a test page** from RawBT — it should print a ruler/calibration strip.
   If that works, the printer connection is good.

---

## 3. Enable the RawBT *Print Service* (this is what the browser uses)

RawBT has two parts: the **app** (printed the test) and the **Print Service**
(what the browser prints through). They are separate.

1. *Android Settings → Connected devices → Printing → Print services* →
   turn **RawBT ON**.
2. Make sure the **RawBT Print Service** points at the **same network printer
   (IP : 9100)** as the app — if the service has no printer set, browser jobs
   queue forever even though the app's test page works.

---

## 4. Auto-print settings

**In RawBT → Settings:**
- Enable **"Print without preview" / "Silent print" / "Auto print"** so RawBT
  doesn't park jobs in its queue waiting for a tap.

**In the POS → Settings → Register Display:**
- Set **Receipt roll width** to match RawBT (58 or 80 mm).
- Tick **"Automatically print the receipt after each completed sale."**

---

## 5. Paper cutter

The auto-cut is an ESC/POS command RawBT adds — the HTML receipt can't carry it.

1. RawBT → Settings → **Cutter / Cut paper / Auto cut** → **enable** (**Full cut**;
   use **Partial** only if full doesn't work on your model).
2. Set **Feed lines before cut** to about **3–4** so it doesn't slice into the
   last line. Adjust until it cuts cleanly just below the receipt.
3. If nothing cuts: confirm the printer **has a motorised cutter** (many cheap
   58 mm units only have a tear bar). If it has one but won't fire, try the other
   cut type or RawBT's printer command-set option.

---

## 6. First print

Complete a sale → the receipt auto-fires `window.print()` → in the print sheet
pick **RawBT / your printer** (not "Save as PDF") and set it as the **default**.
After that it's one tap (or silent, with auto-print on).

---

## Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| Job **queues but never prints** | RawBT Print Service off (step 3), or the service has no printer set, or wrong/stale IP (re-print self-test; set static IP), or tablet/printer on different Wi-Fi. |
| RawBT **test page won't print** | Connection problem — wrong IP/port, different network, or printer offline/out of paper. |
| Test prints, **receipts don't** | Browser sent the job elsewhere — pick **RawBT** in the print dialog and make it default; enable the Print Service. |
| Prints but **won't cut** | Enable the cutter in RawBT + set feed-lines-before-cut; confirm the printer has a motorised cutter. |
| Prints the **whole screen** | Hard-refresh the receipt page (Ctrl/▼ + reload) to clear a cached old stylesheet. |
| Wrong **width / cut off** | Match RawBT paper width and the POS receipt width (58/80). |

---

## Notes

- The app side is already done: receipts are **58/80 mm formatted**, print **only
  the receipt**, and **auto-print on load** when that setting is on.
- For a **Windows till** (not Android), see
  [receipt-printer-setup.md](./receipt-printer-setup.md) (Chrome `--kiosk-printing`).
- True server-side "print to IP" (no app/dialog) is only possible if the POS runs
  on a machine **on the same network as the printer** — not from the cloud VPS.
