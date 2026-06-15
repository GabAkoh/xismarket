# xismarket — module build contracts

Single Laravel 13 app, single-database multi-tenancy, pure core + Blade + Alpine +
Tailwind (CDN). No third-party composer packages. These contracts let modules be
built independently and still integrate.

## Conventions (ALL modules must follow)

- **Models**: `App\Models\<Module>\<Name>` in `app/Models/<Module>/`.
- **Controllers**: `App\Http\Controllers\<Module>\<Name>Controller`.
- **Services**: `App\Services\<Module>\<Name>Service`.
- **Tenant scoping**: every tenant-owned model MUST
  `use App\Models\Concerns\BelongsToTenant;` and its table MUST have:
  `$table->foreignId('tenant_id')->constrained()->cascadeOnDelete();` (indexed).
  The trait auto-fills `tenant_id` on create and global-scopes all queries.
- **Money**: `decimal(12,2)`. **Quantities**: `decimal(12,3)`. Rates/percent: `decimal(8,4)`.
- **Migrations**: filename date prefixes by module to avoid collisions & order after
  `tenants` (000001) and `roles` (000002):
  - Inventory: `2025_06_14_0003NN_*`
  - Accounting: `2025_06_14_0004NN_*`
  - POS: `2025_06_14_0005NN_*`
- **Routes**: create `routes/modules/<module>.php` (auto-loaded). Wrap in
  `Route::middleware(['web','auth'])->group(...)` and guard each route with
  `->middleware('permission:<slug>')` using slugs from `App\Support\Permissions`.
- **Views**: `resources/views/<area>/*.blade.php`, `@extends('layouts.app')`,
  use `<x-page-header title="...">`, `<x-card>`, and `@permission('slug') ... @endpermission`.
  Currency: `$currentTenant->currency`.
- **Demo seeders**: `Database\Seeders\Demo\<Module>DemoSeeder` in
  `database/seeders/Demo/`. The current tenant is ALREADY set when these run
  (do not create tenants). Class names auto-discovered by `DemoSeeder`:
  `InventoryDemoSeeder`, `AccountingDemoSeeder`, `PosDemoSeeder`, `UsersDemoSeeder`.
- Do **not** run migrations or edit shared files (`bootstrap/app.php`,
  `layouts/app.blade.php`, `DatabaseSeeder`). Only add your own files.

## Inventory module — PROVIDES (POS depends on these exact signatures)

Models:
- `Inventory\Product`: tenant_id, category_id (nullable), name, sku (unique per tenant),
  barcode (nullable), description, cost_price, sale_price, tax_rate, track_stock (bool),
  is_active (bool), image_path (nullable). Relations: `category()`, `stocks()`,
  `movements()`. Helper `stockIn(Warehouse $w): float`.
- `Inventory\Category`: tenant_id, name, slug, parent_id (nullable).
- `Inventory\Supplier`: tenant_id, name, email, phone, address.
- `Inventory\Warehouse`: tenant_id, name, code, address, is_default (bool).
  Static `Warehouse::default(): Warehouse` returns tenant default (create one if none).
- `Inventory\ProductStock`: tenant_id, product_id, warehouse_id, quantity,
  reorder_level. unique(product_id, warehouse_id).
- `Inventory\StockMovement`: tenant_id, product_id, warehouse_id, type
  (in|out|adjustment|sale|purchase|return), quantity (signed), unit_cost,
  reference_type, reference_id, note, user_id.
- `Inventory\PurchaseOrder` + `Inventory\PurchaseOrderItem`.

Service `App\Services\Inventory\StockService`:
- `recordMovement(Product $p, Warehouse $w, string $type, float $signedQty, ?float $unitCost = null, ?Model $reference = null, ?string $note = null): StockMovement`
  — upserts ProductStock.quantity += signedQty and writes a StockMovement row.
- `quantityFor(Product $p, Warehouse $w): float`.

## Accounting module — PROVIDES (POS depends on these exact signatures)

Models:
- `Accounting\Account`: tenant_id, code (unique per tenant), name, type
  (asset|liability|equity|income|expense), subtype (nullable), is_active.
- `Accounting\JournalEntry`: tenant_id, reference, entry_date, memo, source_type,
  source_id, user_id, posted (bool). `lines()` hasMany.
- `Accounting\JournalLine`: journal_entry_id, account_id, debit, credit, memo.
- `Accounting\Tax`: tenant_id, name, rate, is_active.

Service `App\Services\Accounting\PostingService`:
- `accountByCode(string $code): ?Account`
- `post(array $data): JournalEntry` where `$data = [`
    `'date' => Carbon|string, 'memo' => string, 'reference' => ?string,`
    `'source' => ?Model, 'lines' => [['account' => code|Account, 'debit' => float, 'credit' => float, 'memo' => ?string], ...]]`
  Validates debits == credits, creates entry + lines, posted=true.

**Standard chart of accounts codes (seeded by Accounting, used by POS):**
- 1000 Cash · 1010 Bank · 1200 Accounts Receivable · 1300 Inventory
- 2000 Accounts Payable · 2100 Tax Payable
- 3000 Owner Equity · 3900 Retained Earnings
- 4000 Sales Revenue · 4100 Sales Discounts
- 5000 Cost of Goods Sold · 6000 Operating Expenses

## POS module — CONSUMES Inventory + Accounting

On completed sale: decrement stock via `StockService::recordMovement(type:'sale',
signedQty: -qty, reference: $sale)` for the register's warehouse, then post a journal
entry (guarded by `class_exists(PostingService::class)`):
- Debit 1000 Cash = total; Credit 4000 Sales Revenue = net (subtotal - discount);
  Credit 2100 Tax Payable = tax.
- Debit 5000 COGS = Σ cost; Credit 1300 Inventory = Σ cost.
