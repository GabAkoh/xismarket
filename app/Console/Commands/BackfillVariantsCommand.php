<?php

namespace App\Console\Commands;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\StockMovement;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Console\Command;

/**
 * Gives every existing product a default variant (mirroring its SKU/price) and
 * moves its product-level stock + movements onto that variant. Makes a catalogue
 * imported before the variant model fully variant-compatible — a non-destructive
 * alternative to wiping and re-importing. Idempotent: products that already have
 * a variant are skipped.
 */
class BackfillVariantsCommand extends Command
{
    protected $signature = 'variants:backfill';

    protected $description = 'Create a default variant for every product that has none';

    public function handle(Tenancy $tenancy): int
    {
        $created = 0;

        foreach (Tenant::all() as $tenant) {
            $tenancy->set($tenant);
            try {
                Product::doesntHave('variants')->orderBy('id')->chunkById(500, function ($products) use (&$created) {
                    foreach ($products as $p) {
                        $variant = ProductVariant::create([
                            'product_id' => $p->id,
                            'sku' => $p->sku,
                            'barcode' => $p->barcode,
                            'sale_price' => $p->sale_price,
                            'cost_price' => $p->cost_price,
                            'position' => 0,
                        ]);

                        // Re-point existing product-level stock + movements to the variant.
                        ProductStock::where('product_id', $p->id)->whereNull('variant_id')->update(['variant_id' => $variant->id]);
                        StockMovement::where('product_id', $p->id)->whereNull('variant_id')->update(['variant_id' => $variant->id]);

                        $created++;
                    }
                });
            } finally {
                $tenancy->forget();
            }
        }

        $this->info("Backfill complete — created {$created} default variant(s).");

        return self::SUCCESS;
    }
}
