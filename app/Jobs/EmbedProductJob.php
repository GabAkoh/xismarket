<?php

namespace App\Jobs;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductEmbedding;
use App\Models\Tenant;
use App\Services\Search\EmbeddingClient;
use App\Services\Search\ProductSearch;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * (Re)computes the semantic-search embedding for a single product and stores it
 * on the product_embeddings table. Cheap to run repeatedly: it skips the
 * provider call when the product's embeddable text is unchanged since last time.
 */
class EmbedProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $tenantId,
        public int $productId,
    ) {}

    public function handle(EmbeddingClient $client, Tenancy $tenancy): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }

        // Set the tenant first: whether a provider is configured can depend on
        // the tenant's own Gemini key (AI Tools preference), not just the env.
        $tenancy->set($tenant);

        try {
            // No provider configured → semantic search is off; nothing to do.
            if (! $client->configured()) {
                return;
            }

            $product = Product::with('category')->find($this->productId);
            if (! $product) {
                return;
            }

            $text = self::embeddableText($product);
            $model = (string) config('services.embeddings.model');
            $hash = hash('sha256', $model.'|'.$text);

            $existing = ProductEmbedding::where('product_id', $product->id)->first();
            if ($existing && $existing->source_hash === $hash) {
                return; // unchanged — avoid a needless provider call
            }

            $vector = $client->embed($text);

            ProductEmbedding::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'model' => $model,
                    'dims' => count($vector),
                    'vector' => ProductEmbedding::pack($vector),
                    'source_hash' => $hash,
                ],
            );

            ProductSearch::bumpVersion($this->tenantId);
        } finally {
            $tenancy->forget();
        }
    }

    /**
     * The natural-language text that represents a product for embedding:
     * name, category, option axes and description. Kept stable so the
     * source-hash short-circuit works.
     */
    public static function embeddableText(Product $product): string
    {
        $parts = [
            $product->name,
            $product->category?->name,
            implode(' ', $product->optionNames()),
            (string) $product->sku,
            (string) $product->description,
        ];

        $parts = array_filter(array_map('trim', $parts), fn ($p) => $p !== '');

        return Str::limit(implode('. ', $parts), 2000, '');
    }
}
