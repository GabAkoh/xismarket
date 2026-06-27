<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Inventory\OdooProductImporter;
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

    public function __construct(
        public int $tenantId,
        public string $path,   // path on the 'local' disk
    ) {}

    public function handle(OdooProductImporter $importer, Tenancy $tenancy): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            Storage::disk('local')->delete($this->path);

            return;
        }

        $tenancy->set($tenant);

        try {
            $result = $importer->import(Storage::disk('local')->path($this->path));

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
        } finally {
            Storage::disk('local')->delete($this->path);
            $tenancy->forget();
        }
    }
}
