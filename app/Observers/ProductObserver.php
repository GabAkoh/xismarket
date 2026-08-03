<?php

namespace App\Observers;

use App\Jobs\EmbedProductJob;
use App\Models\Inventory\Product;
use App\Services\Search\ProductSearch;
use Illuminate\Support\Facades\DB;

/**
 * Keeps search fresh when products change through Eloquent. Any write bumps the
 * per-tenant search-index version (so the cached fuzzy index and vector matrix
 * are rebuilt on the next search), and web-request writes queue a re-embed.
 *
 * Bulk/query-builder paths (ProductController@bulk, parts of the Odoo importer)
 * bypass Eloquent events, so those flows trigger `search:backfill` explicitly.
 */
class ProductObserver
{
    public function saved(Product $product): void
    {
        $tenantId = $product->tenant_id;
        ProductSearch::bumpVersion($tenantId);

        // Only queue re-embeds for real staff/web edits; console flows (seeders,
        // imports, tests) index via the search:backfill command instead.
        if (app()->runningInConsole()) {
            return;
        }

        $productId = $product->id;
        DB::afterCommit(function () use ($tenantId, $productId) {
            EmbedProductJob::dispatch($tenantId, $productId);
        });
    }

    public function deleted(Product $product): void
    {
        // The product_embeddings FK cascades the row away; just refresh the index.
        ProductSearch::bumpVersion($product->tenant_id);
    }
}
