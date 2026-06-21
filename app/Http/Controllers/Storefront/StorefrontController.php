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
        $hasCats = class_exists(Category::class);
        // The category tree + how many active products sit in each sub-tree.
        [$byId, $childrenOf, $subtree] = $hasCats ? $this->categoryTree() : [collect(), [], []];

        $selectedCategory = ($hasCats && $request->filled('category'))
            ? ($byId[$request->integer('category')] ?? null)
            : null;

        // When the visitor is searching or filtering we drop the marketing
        // sections and show a focused results grid instead.
        $filtering = $request->filled('q') || $request->filled('category');

        // Filtering by a category includes everything in its sub-tree.
        $descendantIds = $selectedCategory ? $this->descendantIds($selectedCategory->id, $childrenOf) : [];

        $products = Product::query()
            ->where('is_active', true)
            ->when($selectedCategory, fn ($q) => $q->whereIn('category_id', $descendantIds))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        // Drill-down chips: the children of the current browse level (the selected
        // category if it has stocked sub-categories, else its parent's level, else
        // the top level), keeping only branches that actually have products.
        $browseParentId = null;
        if ($selectedCategory) {
            $stockedKids = collect($childrenOf[$selectedCategory->id] ?? [])
                ->contains(fn ($id) => ($subtree[$id] ?? 0) > 0);
            $browseParentId = $stockedKids ? $selectedCategory->id : $selectedCategory->parent_id;
        }
        $candidateIds = $browseParentId
            ? ($childrenOf[$browseParentId] ?? [])
            : ($hasCats ? $byId->whereNull('parent_id')->pluck('id')->all() : []);
        $chipCategories = collect($candidateIds)
            ->map(fn ($id) => $byId[$id] ?? null)->filter()
            ->filter(fn ($c) => ($subtree[$c->id] ?? 0) > 0)
            ->sortByDesc(fn ($c) => $subtree[$c->id])
            ->take(20)
            ->values();

        // Breadcrumb trail from the root down to the selected category.
        $breadcrumb = collect();
        if ($selectedCategory) {
            $cur = $selectedCategory;
            $guard = 0;
            while ($cur && $guard++ < 20) {
                $breadcrumb->prepend($cur);
                $cur = $cur->parent_id ? ($byId[$cur->parent_id] ?? null) : null;
            }
        }

        // Landing-page extras (skipped while filtering to keep the page light).
        $featured = $filtering ? collect() : $this->bestsellerProducts(8);

        $categoryTiles = $filtering ? collect() : $this->categoryTiles();

        return view('storefront.index', compact(
            'products', 'chipCategories', 'selectedCategory', 'breadcrumb', 'featured', 'categoryTiles', 'filtering'
        ));
    }

    /**
     * Build the category tree once: the id=>Category map, a parent=>[child ids]
     * map, and a id=>(active products in the whole sub-tree) count map.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array, 2: array}
     */
    protected function categoryTree(): array
    {
        $cats = Category::get(['id', 'name', 'parent_id']);
        $byId = $cats->keyBy('id');

        $childrenOf = [];
        foreach ($cats as $c) {
            if ($c->parent_id) {
                $childrenOf[$c->parent_id][] = $c->id;
            }
        }

        $direct = Product::where('is_active', true)->whereNotNull('category_id')
            ->groupBy('category_id')->selectRaw('category_id, COUNT(*) as c')
            ->pluck('c', 'category_id');

        $subtree = [];
        $calc = function ($id) use (&$calc, &$subtree, $childrenOf, $direct) {
            if (array_key_exists($id, $subtree)) {
                return $subtree[$id];
            }
            $subtree[$id] = 0;   // guards against accidental cycles
            $sum = (int) ($direct[$id] ?? 0);
            foreach ($childrenOf[$id] ?? [] as $child) {
                $sum += $calc($child);
            }

            return $subtree[$id] = $sum;
        };
        foreach ($cats as $c) {
            $calc($c->id);
        }

        return [$byId, $childrenOf, $subtree];
    }

    /** A category id plus every descendant id, from a prebuilt parent=>children map. */
    protected function descendantIds(int $id, array $childrenOf): array
    {
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
