<?php

namespace App\Http\Controllers;

use App\Jobs\EmbedProductJob;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductEmbedding;
use App\Services\Search\EmbeddingClient;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/**
 * Product-search preferences for the POS Register and storefront:
 *
 *  - Fuzzy search   (search.fuzzy_enabled)    — typo-tolerant lexical matching.
 *  - Smart search   (search.semantic_enabled) — Gemini meaning-based matching;
 *                    on the register this runs in "always" mode (see PosController).
 *
 * Smart search needs a Gemini key (Settings → AI Tools) and per-product
 * embeddings. Turning it on queues an embed of every not-yet-embedded product so
 * it starts working immediately, and a manual re-embed rebuilds them all.
 */
class SearchSettingsController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function edit(EmbeddingClient $embeddings)
    {
        return view('settings.search', [
            'store' => $this->tenancy->current(),
            'keyConfigured' => $embeddings->configured(),
            'embeddedCount' => ProductEmbedding::count(),
            'productCount' => Product::where('is_active', true)->count(),
        ]);
    }

    public function update(Request $request)
    {
        $store = $this->tenancy->current();
        $settings = $store->settings ?? [];

        $nowSemantic = $request->boolean('semantic_enabled');

        $settings['search'] = array_merge($settings['search'] ?? [], [
            'fuzzy_enabled' => $request->boolean('fuzzy_enabled'),
            'semantic_enabled' => $nowSemantic,
        ]);

        $store->update(['settings' => $settings]);

        // Instant embed on enable: whenever smart search is on (and a provider is
        // configured), queue any product that has no vector yet so results aren't
        // empty until a manual backfill. Idempotent — queues 0 when everything is
        // already embedded (and the job short-circuits unchanged products without
        // an API call).
        $queued = ($nowSemantic && app(EmbeddingClient::class)->configured())
            ? $this->queueEmbeddings(missingOnly: true)
            : 0;

        $msg = 'Search settings updated.';
        if ($queued > 0) {
            $msg .= " Embedding {$queued} product(s) in the background — smart search will improve as they finish.";
        }

        return redirect()->route('search.settings')->with('status', $msg);
    }

    /** Re-embed every active product (e.g. after changing the Gemini key/model). */
    public function reembed(EmbeddingClient $embeddings)
    {
        if (! $embeddings->configured()) {
            return redirect()->route('search.settings')
                ->with('error', 'Smart search has no Gemini key — add one in Settings → AI Tools before embedding.');
        }

        $queued = $this->queueEmbeddings(missingOnly: false);

        return redirect()->route('search.settings')
            ->with('status', "Re-embedding {$queued} product(s) in the background.");
    }

    /**
     * Queue an EmbedProductJob for the current tenant's active products.
     * With $missingOnly, skip products that already have an embedding row.
     */
    protected function queueEmbeddings(bool $missingOnly): int
    {
        $tenantId = $this->tenancy->id();

        $query = Product::query()->where('is_active', true)->select('id');
        if ($missingOnly) {
            $query->whereNotExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('product_embeddings')
                ->whereColumn('product_embeddings.product_id', 'products.id'));
        }

        $queued = 0;
        $query->orderBy('id')->chunkById(500, function ($products) use ($tenantId, &$queued) {
            foreach ($products as $product) {
                EmbedProductJob::dispatch($tenantId, $product->id);
                $queued++;
            }
        });

        return $queued;
    }
}
