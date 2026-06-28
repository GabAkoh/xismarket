<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Inventory\OdooProductImporter;
use App\Support\Tenancy;
use Illuminate\Console\Command;

/**
 * Set product images from an Odoo export's base64 Image column. Meant for the
 * large base64 CSVs that exceed the web upload limit — drop the file on the
 * server and run this. Matches existing products by SKU, then by name.
 */
class ImportOdooImagesCommand extends Command
{
    protected $signature = 'products:import-odoo-images {path : Path to the Odoo CSV with an Image (base64) column} {--tenant= : Tenant id (default: all tenants)}';

    protected $description = 'Update product images from an Odoo base64 Image export';

    public function handle(OdooProductImporter $importer, Tenancy $tenancy): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $tenants = $this->option('tenant')
            ? Tenant::where('id', $this->option('tenant'))->get()
            : Tenant::all();

        foreach ($tenants as $tenant) {
            $tenancy->set($tenant);
            try {
                $r = $importer->import($path, 'images');
                $this->info("{$tenant->name}: images={$r['images']} skipped={$r['skipped']} errors=".count($r['errors']));
                foreach (array_slice($r['errors'], 0, 5) as $e) {
                    $this->warn('  '.$e);
                }
            } finally {
                $tenancy->forget();
            }
        }

        return self::SUCCESS;
    }
}
