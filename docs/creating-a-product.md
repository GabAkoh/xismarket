# Creating a product

How to add a product in xismarket. The form is Shopify-style — even a simple
product is one variant row, and SKU, price and stock live on the variant(s).

**Where:** *Inventory → Products → New product* (the "Add product" button on the
products list).

---

## 1. Basics

- **Name** (required).
- **Category** — pick from the dropdown, or leave as "None".
- **Tax rate (%)** — defaults to 0; set it if the product is taxable.
- **Reorder level** — optional. When stock drops to this number the product is
  flagged for reorder. Leave blank for none.

---

## 2. Variants (SKU, price & stock)

Every product has at least one variant row.

### Simple product (no options)
- Leave the three **Option name** boxes empty.
- In the single variant row enter **SKU** (use **Generate** for a barcode if you
  want), **Cost**, **Price**, and opening **Stock**. Leave **Active** ticked.

### Variant product (e.g. sizes / colours)
1. Name the axes in the **Option name** boxes — e.g. `Size`, `Colour` (up to 3).
2. Click **+ Add variant** for each combination and fill its **option values**
   (S / Red…), **SKU**, **barcode**, **Cost**, **Price**, **Stock**, **Active**.
3. The **first variant** fills the product's default price/SKU automatically.

> The stock you type here is set immediately (recorded as a stock adjustment).
> For supplier deliveries later, use **Purchase Order → Receive** instead — see
> [receiving stock](./../Taking%20Products%20Into%20Stock.txt).

---

## 3. Description & images

- **Description** — shown on the storefront product page.
- **Image** — choose a file or use the camera, then **crop / rotate / zoom**,
  adjust **brightness / contrast / saturation**, optionally **remove or replace
  the background**, and add a **watermark**. Save it as the **cover** (or add to
  the gallery).
- **More images (gallery)** — extra angles / lifestyle shots; drag to reorder,
  pick a cover, remove. If AI images are configured, the **✨ AI image tools**
  can generate background / recolour / angle / model shots.

---

## 4. Final toggles, then save

- **Track stock** — on for physical goods (enables out-of-stock guards and
  reorder flags).
- **Active** — visible / sellable.
- **Featured** — pins it to the storefront bestsellers.

Click **Save** → "Product added."

---

## After saving

- It appears in *Products*, on the **POS** grid (multi-variant tiles open the
  variant picker), and on the **storefront** (option selector + "from" pricing
  for variant products).
- Print a barcode label from the product (the **🏷 Print label** link).

---

## Quick reference

| Field | Notes |
| --- | --- |
| Name | Required |
| Category | Optional |
| Tax rate | Percent; defaults to 0 |
| Reorder level | Flags low stock; blank = none |
| Option names | Name up to 3 axes for a variant product |
| Variant row | Option values, SKU, barcode, cost, price, stock, active |
| Description | Shown on the storefront |
| Image / gallery | Cover + extra images, with an editor and AI tools |
| Track stock / Active / Featured | Stock control / visibility / bestseller pin |

### Rules of thumb

- **Simple product** → one variant row, no option names.
- **Variant product** → name the option axes, then add a row per combination.
- **Opening stock** is fine to type here; **restocking from a supplier** goes
  through a **Purchase Order → Receive**.
