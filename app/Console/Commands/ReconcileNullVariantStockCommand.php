<?php

namespace App\Console\Commands;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockMovement;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Moves product-level (variant_id = NULL) stock onto each product's default
 * variant — the row POS sales draw down and the product-edit stock box reads.
 *
 * Purchase-order receipts historically booked stock against the null-variant
 * row (see the fix in PurchaseOrderController::receive), so received quantity
 * showed as sellable in list/storefront totals (which sum every row) but never
 * appeared in the edit form and was never decremented by sales. This command
 * folds that stranded quantity — and its movement ledger rows — into the
 * default variant, mirroring what variants:backfill did for legacy stock.
 *
 * Read-only by default: prints what it would move. Pass --apply to commit.
 * Idempotent — a second run finds nothing to do.
 */
class ReconcileNullVariantStockCommand extends Command
{
    protected $signature = 'stock:reconcile-null-variants
        {--apply : Persist the changes (otherwise a dry run)}
        {--tenant= : Limit to a single tenant id}';

    protected $description = 'Fold product-level (null-variant) stock onto each product default variant';

    public function handle(Tenancy $tenancy): int
    {
        $apply = (bool) $this->option('apply');
        $only = $this->option('tenant');

        $tenants = Tenant::query()
            ->when($only, fn ($q) => $q->whereKey($only))
            ->get();

        $grandRows = 0;
        $grandQty = 0.0;

        foreach ($tenants as $tenant) {
            $tenancy->set($tenant);
            try {
                [$rows, $qty] = $this->reconcileTenant($tenant, $apply);
                $grandRows += $rows;
                $grandQty += $qty;
            } finally {
                $tenancy->forget();
            }
        }

        $mode = $apply ? 'moved' : 'would move';
        $this->newLine();
        $this->info(sprintf(
            '%s: %s %d stock row(s), %s unit(s) total onto default variants.',
            $apply ? 'Applied' : 'Dry run',
            $mode,
            $grandRows,
            rtrim(rtrim(number_format($grandQty, 3), '0'), '.'),
        ));

        if (! $apply && $grandRows > 0) {
            $this->comment('Re-run with --apply to commit.');
        }

        return self::SUCCESS;
    }

    /** @return array{0:int,1:float} [rows moved, quantity moved] */
    protected function reconcileTenant(Tenant $tenant, bool $apply): array
    {
        // Null-variant stock rows that belong to a product which has a variant.
        $stocks = ProductStock::query()
            ->whereNull('variant_id')
            ->with('product')
            ->get();

        if ($stocks->isEmpty()) {
            return [0, 0.0];
        }

        $moved = 0;
        $qtyMoved = 0.0;
        $reported = false;

        foreach ($stocks as $stock) {
            $product = $stock->product;
            if (! $product) {
                continue; // orphan row — leave it for a separate cleanup
            }

            $variant = $product->defaultVariant();
            if (! $variant) {
                $this->warn("  · skip {$product->name} (#{$product->id}) — no variant to fold into");
                continue;
            }

            if (! $reported) {
                $this->line("Tenant {$tenant->id} ({$tenant->name}):");
                $reported = true;
            }

            $qty = (float) $stock->quantity;
            $this->line(sprintf(
                '  · %-40s wh:%d  %s → variant #%d',
                \Illuminate\Support\Str::limit($product->name, 40),
                $stock->warehouse_id,
                rtrim(rtrim(number_format($qty, 3), '0'), '.'),
                $variant->id,
            ));

            if ($apply) {
                DB::transaction(function () use ($stock, $product, $variant, $qty) {
                    $target = ProductStock::query()
                        ->where('variant_id', $variant->id)
                        ->where('warehouse_id', $stock->warehouse_id)
                        ->first();

                    if ($target) {
                        // Variant already has a row here — merge and drop the null row.
                        $target->increment('quantity', $qty);
                        $stock->delete();
                    } else {
                        // Re-key the null row onto the variant in place.
                        $stock->update(['variant_id' => $variant->id]);
                    }

                    // Re-point the ledger for this product+warehouse so movement
                    // history and on-hand stay consistent.
                    StockMovement::query()
                        ->where('product_id', $product->id)
                        ->where('warehouse_id', $stock->warehouse_id)
                        ->whereNull('variant_id')
                        ->update(['variant_id' => $variant->id]);
                });
            }

            $moved++;
            $qtyMoved += $qty;
        }

        return [$moved, $qtyMoved];
    }
}
