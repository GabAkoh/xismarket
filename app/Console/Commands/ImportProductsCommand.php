<?php

namespace App\Console\Commands;

use App\Jobs\ImportShopifyProductsJob;
use App\Models\Tenant;
use App\Services\Inventory\ShopifyProductImporter;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportProductsCommand extends Command
{
    protected $signature = 'products:import
        {file : Path to the Shopify products CSV (absolute, or relative to the project root)}
        {--tenant= : Tenant id or slug (defaults to the only tenant)}
        {--images : Download product images}
        {--sync : Run the import now instead of queueing it}';

    protected $description = 'Import products from a Shopify CSV export (no upload needed)';

    public function handle(Tenancy $tenancy): int
    {
        $abs = $this->resolvePath($this->argument('file'));
        if (! $abs) {
            $this->error('File not found: '.$this->argument('file'));

            return self::FAILURE;
        }

        $tenant = $this->resolveTenant();
        if (! $tenant) {
            return self::FAILURE;
        }

        $images = (bool) $this->option('images');

        if ($this->option('sync')) {
            $tenancy->set($tenant);
            $this->info("Importing into {$tenant->name} (running now)…");

            $result = app(ShopifyProductImporter::class)->import($abs, $images);

            $this->table(
                ['Created', 'Updated', 'Images', 'Skipped'],
                [[$result['created'], $result['updated'], $result['images'], $result['skipped']]],
            );
            foreach (array_slice($result['errors'], 0, 20) as $err) {
                $this->warn('• '.$err);
            }
            if (count($result['errors']) > 20) {
                $this->warn('… and '.(count($result['errors']) - 20).' more.');
            }

            return self::SUCCESS;
        }

        // Queue: stage the file on the local disk and dispatch the job.
        $dest = 'imports/cli-'.Str::random(16).'.csv';
        Storage::disk('local')->writeStream($dest, fopen($abs, 'r'));

        ImportShopifyProductsJob::dispatch($tenant->id, $dest, $images);

        $this->info("Queued import into {$tenant->name}.".($images ? ' (with images)' : ''));
        $this->line('Make sure the queue worker is running; watch progress with:');
        $this->line('  docker compose logs -f worker');

        return self::SUCCESS;
    }

    /** Resolve a CLI path (absolute or relative to the project root) to an existing file. */
    protected function resolvePath(string $file): ?string
    {
        foreach ([$file, base_path($file)] as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return realpath($candidate);
            }
        }

        return null;
    }

    protected function resolveTenant(): ?Tenant
    {
        $opt = $this->option('tenant');

        if ($opt) {
            $tenant = is_numeric($opt) ? Tenant::find((int) $opt) : Tenant::where('slug', $opt)->first();
            if (! $tenant) {
                $this->error("Tenant not found: {$opt}");
            }

            return $tenant;
        }

        $tenants = Tenant::all();
        if ($tenants->count() === 1) {
            return $tenants->first();
        }

        $this->error('Multiple tenants exist — choose one with --tenant=<id|slug>.');
        $this->table(['id', 'slug', 'name'], $tenants->map(fn ($t) => [$t->id, $t->slug, $t->name])->all());

        return null;
    }
}
