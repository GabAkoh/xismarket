<?php

namespace App\Console\Commands;

use App\Jobs\EmbedProductJob;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductEmbedding;
use App\Models\Tenant;
use App\Services\Search\EmbeddingClient;
use App\Support\Tenancy;
use Illuminate\Console\Command;

/**
 * Backfills semantic-search embeddings for products. Run once after enabling
 * semantic search, and after bulk imports / bulk edits that bypass Eloquent
 * events. The per-product job skips unchanged products, so re-running is cheap.
 */
class BackfillEmbeddingsCommand extends Command
{
    protected $signature = 'search:backfill
        {--tenant= : Tenant id or slug (defaults to the only tenant)}
        {--missing : Only products that have no embedding row yet}
        {--sync : Embed now instead of queueing the per-product jobs}';

    protected $description = 'Compute semantic-search embeddings for products';

    public function handle(Tenancy $tenancy, EmbeddingClient $client): int
    {
        $tenant = $this->resolveTenant();
        if (! $tenant) {
            return self::FAILURE;
        }

        // Set the tenant before the configured() check: the Gemini key can come
        // from the tenant's own AI Tools preference, not just the env fallback.
        $tenancy->set($tenant);

        try {
            if (! $client->configured()) {
                $this->error('Semantic search is not configured. Add a Gemini key in Settings → AI Tools, set EMBEDDINGS_KEY, or use EMBEDDINGS_PROVIDER=stub.');

                return self::FAILURE;
            }

            $query = Product::query()->select('id');
            if ($this->option('missing')) {
                $query->whereNotExists(fn ($q) => $q
                    ->selectRaw('1')
                    ->from('product_embeddings')
                    ->whereColumn('product_embeddings.product_id', 'products.id'));
            }

            $total = (clone $query)->count();
            if ($total === 0) {
                $this->info('Nothing to embed.');

                return self::SUCCESS;
            }

            $sync = (bool) $this->option('sync');
            $this->info(($sync ? 'Embedding' : 'Queueing').' '.$total.' product(s) for '.$tenant->name.'…');
            $bar = $this->output->createProgressBar($total);

            $query->orderBy('id')->chunkById(200, function ($products) use ($tenant, $sync, $bar) {
                foreach ($products as $product) {
                    $job = new EmbedProductJob($tenant->id, $product->id);
                    $sync ? dispatch_sync($job) : dispatch($job);
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine(2);
            $this->info($sync
                ? 'Done. Embeddings are up to date.'
                : 'Queued. Ensure a queue worker is running to process the jobs.');
            $this->line('Products with embeddings: '.ProductEmbedding::count().'.');

            return self::SUCCESS;
        } finally {
            $tenancy->forget();
        }
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
