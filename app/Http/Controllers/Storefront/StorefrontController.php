<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Services\Storefront\BestsellerService;
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    public function __construct(protected BestsellerService $bestsellers) {}

    /** Storefront landing page: marketing sections + searchable product catalogue. */
    public function index(Request $request)
    {
        // Category filter chips: top-level categories that have products (most
        // stocked first) — not the full taxonomy. The selected category is kept
        // separately so the page title/highlight works even for a sub-category.
        $chipCategories = class_exists(Category::class)
            ? Category::query()->whereNull('parent_id')
                ->withCount(['products as nav_count' => fn ($q) => $q->where('is_active', true)])
                ->having('nav_count', '>', 0)
                ->orderByDesc('nav_count')->orderBy('name')
                ->take(15)->get()   // the most-stocked sections; the rest are reachable via search/tiles
            : collect();

        $selectedCategory = $request->filled('category') && class_exists(Category::class)
            ? Category::find($request->integer('category'))
            : null;

        // When the visitor is searching or filtering we drop the marketing
        // sections and show a focused results grid instead.
        $filtering = $request->filled('q') || $request->filled('category');

        $products = Product::query()
            ->where('is_active', true)
            // Filtering by a category includes everything in its sub-tree, so a
            // top-level category shows all products in its descendant categories.
            ->when($request->filled('category'), fn ($q) => $q->whereIn(
                'category_id', $this->categoryWithDescendants($request->integer('category'))
            ))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        // Landing-page extras (skipped while filtering to keep the page light).
        $featured = $filtering ? collect() : $this->bestsellerProducts(8);

        $categoryTiles = $filtering ? collect() : $this->categoryTiles();

        return view('storefront.index', compact(
            'products', 'chipCategories', 'selectedCategory', 'featured', 'categoryTiles', 'filtering'
        ));
    }

    /**
     * A category id plus every descendant category id (the whole sub-tree), so
     * selecting a parent category surfaces all products filed under its children.
     *
     * @return array<int, int>
     */
    protected function categoryWithDescendants(int $id): array
    {
        // id => parent_id for the whole (tenant-scoped) category set.
        $parents = Category::pluck('parent_id', 'id');
        $childrenOf = [];
        foreach ($parents as $cid => $pid) {
            if ($pid !== null) {
                $childrenOf[$pid][] = $cid;
            }
        }

        $ids = [];
        $stack = [$id];
        while ($stack) {
            $cur = array_pop($stack);
            if (isset($ids[$cur])) {
                continue;
            }
            $ids[$cur] = true;
            foreach ($childrenOf[$cur] ?? [] as $child) {
                $stack[] = $child;
            }
        }

        return array_keys($ids);
    }

    /**
     * Categories that have at least one active product, each with a count and a
     * representative product image — used for the "Shop by category" tiles.
     */
    protected function categoryTiles()
    {
        if (! class_exists(Category::class)) {
            return collect();
        }

        return Category::query()
            ->withCount(['products as active_products_count' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get()
            ->filter(fn ($c) => $c->active_products_count > 0)
            ->map(fn ($c) => (object) [
                'id' => $c->id,
                'name' => $c->name,
                'count' => $c->active_products_count,
                'image' => Product::where('category_id', $c->id)
                    ->where('is_active', true)
                    ->whereNotNull('image_path')
                    ->value('image_path'),
            ])
            ->take(8)
            ->values();
    }

    /**
     * Bestselling active products, ranked by real units sold. Falls back to the
     * newest products for a store with no sales yet, and tops the row up with
     * newest products when fewer than $limit bestsellers are available.
     */
    protected function bestsellerProducts(int $limit = 8)
    {
        $ids = $this->bestsellers->topProductIds($limit);

        if (empty($ids)) {
            return Product::where('is_active', true)->with('category')->latest()->take($limit)->get();
        }

        $products = Product::where('is_active', true)
            ->with('category')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids))
            ->values();

        if ($products->count() < $limit) {
            $fill = Product::where('is_active', true)
                ->with('category')
                ->whereNotIn('id', $products->pluck('id'))
                ->latest()
                ->take($limit - $products->count())
                ->get();
            $products = $products->concat($fill);
        }

        return $products;
    }

    /**
     * Single product detail. Both route params are declared in URI order
     * ({store}, {product}) so the scalar binding is unambiguous. Manual lookup —
     * route-model binding runs before the tenant middleware resolves the store.
     */
    public function product($store, $product)
    {
        $item = Product::where('id', (int) $product)->where('is_active', true)->firstOrFail();

        return view('storefront.product', ['product' => $item]);
    }
}
