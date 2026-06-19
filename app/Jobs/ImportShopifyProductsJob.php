<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Inventory\ShopifyProductImporter;
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
 * Runs a Shopify product-CSV import in the background so a large file (and its
 * image downloads) never blocks a web request / ties up php-fpm workers.
 */
class ImportShopifyProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Generous timeout for large files + image downloads; don't retry partial work. */
    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public int $tenantId,
        public string $path,            // path on the 'local' disk
        public bool $downloadImages,
    ) {}

    /** Cache key holding the most recent import result for a tenant. */
    public static function resultKey(int $tenantId): string
    {
        return "shopify-import-result:{$tenantId}";
    }

    public function handle(ShopifyProductImporter $importer, Tenancy $tenancy): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            Storage::disk('local')->delete($this->path);

            return;
        }

        // Jobs run outside a web request — establish tenant scope for the import.
        $tenancy->set($tenant);

        try {
            $result = $importer->import(Storage::disk('local')->path($this->path), $this->downloadImages);

            // Stash the outcome so the import page can show it after the job runs.
            Cache::put(self::resultKey($this->tenantId), $result + ['finished_at' => now()->toDateTimeString()], now()->addDay());

            Log::info('Shopify import complete', [
                'tenant' => $this->tenantId,
                'created' => $result['created'], 'updated' => $result['updated'],
                'images' => $result['images'], 'skipped' => $result['skipped'],
                'errors' => array_slice($result['errors'], 0, 10),
            ]);
        } finally {
            Storage::disk('local')->delete($this->path);
            $tenancy->forget();
        }
    }
}
