<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Pos\OdooCustomerImporter;
use App\Support\Tenancy;
use Illuminate\Console\Command;

/**
 * Import customers from an Odoo Contacts CSV. Adds names not already here
 * (or refreshes contact details with --update). Loyalty/balance are separate.
 */
class ImportOdooCustomersCommand extends Command
{
    protected $signature = 'customers:import-odoo {path : Path to the Odoo Contacts CSV} {--update : Refresh existing customers instead of skipping them} {--tenant= : Tenant id (default: first tenant)}';

    protected $description = 'Import customers from an Odoo Contacts CSV export';

    public function handle(OdooCustomerImporter $importer, Tenancy $tenancy): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $tenant = $this->option('tenant')
            ? Tenant::find($this->option('tenant'))
            : Tenant::first();
        if (! $tenant) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $tenancy->set($tenant);
        $r = $importer->import($path, $this->option('update') ? 'update' : 'create');
        $tenancy->forget();

        $this->info("{$tenant->name}: created={$r['created']} updated={$r['updated']} skipped={$r['skipped']} errors=".count($r['errors']));
        foreach (array_slice($r['errors'], 0, 10) as $e) {
            $this->warn('  '.$e);
        }

        return self::SUCCESS;
    }
}
