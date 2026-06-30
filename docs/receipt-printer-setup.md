# Receipt printer setup (silent direct printing)

How to make POS receipts print **straight to the thermal printer with no print
dialog**. This is a one-time setup on the till computer; the app already
auto-prints after each sale.

> **Why a dialog appears by default:** for security, no website can silently
> print from JavaScript — the browser always shows the print dialog. The
> exception is the browser's **kiosk-printing mode**, where `window.print()`
> goes straight to the default printer. The app already calls `window.print()`
> automatically, so once kiosk printing is on, receipts just come out.

---

## 1. Make the receipt printer the Windows default

Settings → Bluetooth & devices → Printers & scanners → your thermal printer →
**Set as default**.

Kiosk printing always prints to the **default** printer, so this must be the
receipt printer.

Also open the printer's **Printing preferences** and set the paper size to your
roll (e.g. 80 mm × receipt / 58 mm) so margins are correct.

---

## 2. Turn on auto-print in the app

POS → **Settings**:

- Tick **"Automatically print the receipt after each completed sale."**
- Set the **Receipt roll width** (58 mm or 80 mm) to match your printer.
- Save.

---

## 3. Launch the browser in kiosk-printing mode

Create a desktop shortcut and **always open the till browser from it**.

**Chrome** — shortcut target:

```
"C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk-printing --user-data-dir="C:\pos-chrome" https://your-store-url/pos
```

**Edge** — same flags with `msedge.exe`:

```
"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" --kiosk-printing --user-data-dir="C:\pos-edge" https://your-store-url/pos
```

Optional: add **`--kiosk`** (full-screen, locks the machine to the POS) and
**`--app=https://your-store-url/pos`** instead of the bare URL for an app-like
window with no tabs/address bar.

---

## Gotchas (these trip people up)

- **The browser reuses a running window.** If Chrome/Edge is already open
  normally, the shortcut just opens a tab in that flag-less instance and printing
  still prompts. The `--user-data-dir="..."` above forces a **separate instance**
  so the flag always applies. (Alternatively, fully quit all browser windows
  first.)
- **Default printer + default paper.** Kiosk printing uses the default printer at
  its default paper size — set both (step 1).
- **One printer only.** Kiosk printing can't pick a printer per print; it always
  uses the system default. Keep the receipt printer as default on the till.

---

## How it flows once set up

```
Complete a sale  →  redirect to the receipt page  →  window.print() fires
                 →  kiosk-printing sends it straight to the default printer
                 →  receipt comes out, no dialog
```

The same applies to the **purchase-order receipt** (🖨 Print receipt on a PO) —
it auto-prints on load through the same mechanism.

---

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| Print dialog still appears | Browser wasn't started with `--kiosk-printing`, or it reused an existing instance — use the shortcut with `--user-data-dir` (or quit all windows first). |
| Prints to the wrong printer | Set the receipt printer as the **Windows default**. |
| Whole screen prints, not just the receipt | Hard-refresh the receipt page (Ctrl+Shift+R) to clear a cached old stylesheet. |
| Wrong width / cut off | Match the app's roll width (POS Settings) and the printer driver's paper size. |
| Nothing prints automatically | Enable **auto-print** in POS Settings. |
