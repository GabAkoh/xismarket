<?php

namespace App\Console\Commands;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Console\Command;

/**
 * Strip a leading apostrophe (and surrounding whitespace) from product and
 * variant barcodes — an Excel "text marker" artifact from CSV imports that
 * makes the printed barcode not match the physical product when scanned.
 * Barcodes only; SKUs are left alone (they are the import-matching key).
 */
class CleanBarcodesCommand extends Command
{
    protected $signature = 'products:clean-barcodes {--tenant= : Tenant id (default: all)} {--dry-run : Report counts without changing anything}';

    protected $description = 'Strip leading apostrophes from product and variant barcodes';

    public function handle(Tenancy $tenancy): int
    {
        $dry = (bool) $this->option('dry-run');
        $tenants = $this->option('tenant')
            ? Tenant::where('id', $this->option('tenant'))->get()
            : Tenant::all();

        $total = 0;
        foreach ($tenants as $tenant) {
            $tenancy->set($tenant);
            try {
                $p = $this->clean(Product::class, $dry);
                $v = $this->clean(ProductVariant::class, $dry);
                $total += $p + $v;
                $this->line("  {$tenant->name}: products={$p} variants={$v}");
            } finally {
                $tenancy->forget();
            }
        }

        $this->info(($dry ? '[dry-run] ' : '')."Cleaned {$total} barcode(s).");

        return self::SUCCESS;
    }

    /** Clean barcodes that start with an apostrophe; returns the count changed. */
    protected function clean(string $model, bool $dry): int
    {
        $changed = 0;

        $model::query()
            ->whereNotNull('barcode')
            ->where('barcode', 'like', "'%")
            ->chunkById(1000, function ($rows) use (&$changed, $dry) {
                foreach ($rows as $row) {
                    $new = ltrim(trim((string) $row->barcode), "'");
                    $new = $new === '' ? null : $new;
                    if ($new !== $row->barcode) {
                        if (! $dry) {
                            try {
                                $row->update(['barcode' => $new]);
                            } catch (\Throwable $e) {
                                $this->warn('    skip '.$row->barcode.': '.$e->getMessage());

                                continue;
                            }
                        }
                        $changed++;
                    }
                }
            });

        return $changed;
    }
}
