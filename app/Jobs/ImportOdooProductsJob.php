<?php

namespace App\Jobs;

use App\Models\Inventory\Product;
use App\Models\Tenant;
use App\Services\Inventory\OdooProductImporter;
use App\Services\Search\ProductSearch;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Runs an Odoo product-CSV import in the background. Creates only products whose
 * name isn't already here. Writes its result to the shared import-result cache
 * key so the import page shows the summary.
 */
class ImportOdooProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    /** Declared with defaults so already-queued jobs deserialize safely. */
    public string $mode = 'create';

    public bool $replaceImages = false;

    public function __construct(
        public int $tenantId,
        public string $path,   // path on the 'local' disk
        string $mode = 'create',
        bool $replaceImages = false,
    ) {
        $this->mode = in_array($mode, ['update', 'images'], true) ? $mode : 'create';
        $this->replaceImages = $replaceImages;
    }

    public function handle(OdooProductImporter $importer, Tenancy $tenancy): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            Storage::disk('local')->delete($this->path);

            return;
        }

        $tenancy->set($tenant);

        try {
            $result = $importer->import(Storage::disk('local')->path($this->path), $this->mode, $this->replaceImages);

            // Same key the import page reads (shared with the Shopify importer).
            Cache::put(
                ImportShopifyProductsJob::resultKey($this->tenantId),
                $result + ['finished_at' => now()->toDateTimeString()],
                now()->addDay(),
            );

            Log::info('Odoo import complete', [
                'tenant' => $this->tenantId,
                'created' => $result['created'], 'skipped' => $result['skipped'],
                'errors' => array_slice($result['errors'], 0, 10),
            ]);

            // Importers create/update via the query builder and run on the queue
            // worker (console), so the Product observer doesn't re-embed. Refresh
            // the search index and queue embeddings for any product still missing one.
            ProductSearch::bumpVersion($this->tenantId);
            Product::query()
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('product_embeddings')
                    ->whereColumn('product_embeddings.product_id', 'products.id'))
                ->select('id')
                ->chunkById(500, function ($chunk) {
                    foreach ($chunk as $product) {
                        EmbedProductJob::dispatch($this->tenantId, $product->id);
                    }
                });
        } finally {
            Storage::disk('local')->delete($this->path);
            $tenancy->forget();
        }
    }
}
