<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\Storefront\Subscriber;
use App\Services\Storefront\BestsellerService;
use App\Services\Storefront\CategoryNavService;
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    public function __construct(
        protected BestsellerService $bestsellers,
        protected CategoryNavService $nav,
    ) {}

    /** Storefront landing page: marketing sections + searchable product catalogue. */
    public function index(Request $request)
    {
        $hasCats = class_exists(Category::class);
        // The category tree + how many active products sit in each sub-tree.
        [$byId, $childrenOf, $subtree] = $hasCats ? $this->nav->tree() : [collect(), [], []];

        $selectedCategory = ($hasCats && $request->filled('category'))
            ? ($byId[$request->integer('category')] ?? null)
            : null;

        // When the visitor is searching or filtering we drop the marketing
        // sections and show a focused results grid instead.
        $filtering = $request->filled('q') || $request->filled('category');

        // Filtering by a category includes everything in its sub-tree.
        $descendantIds = $selectedCategory ? $this->nav->descendants($selectedCategory->id, $childrenOf) : [];

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

        $featuredCollections = $filtering ? collect() : $this->featuredCollections();

        return view('storefront.index', compact(
            'products', 'chipCategories', 'selectedCategory', 'breadcrumb', 'featured', 'categoryTiles', 'featuredCollections', 'filtering'
        ));
    }

    /**
     * Curated "Featured Collections" — admin-chosen categories, each with a
     * subtitle and image (a custom upload, or a representative product image
     * from the category's subtree). Configured under storefront settings.
     */
    protected function featuredCollections()
    {
        if (! class_exists(Category::class)) {
            return collect();
        }

        $store = app(\App\Support\Tenancy::class)->current();
        $rows = $store?->setting('storefront.featured_collections', []);
        if (! is_array($rows) || $rows === []) {
            return collect();
        }

        $cats = Category::whereIn('id', collect($rows)->pluck('category_id')->filter())->get()->keyBy('id');

        return collect($rows)->map(function ($r) use ($cats) {
            $cat = $cats->get($r['category_id'] ?? null);
            if (! $cat) {
                return null;
            }

            $image = $r['image'] ?? null;
            if (! $image) {
                $image = Product::whereIn('category_id', Category::subtreeIds($cat->id))
                    ->where('is_active', true)->whereNotNull('image_path')->value('image_path');
            }

            return (object) [
                'id' => $cat->id,
                'name' => $cat->name,
                'subtitle' => trim((string) ($r['subtitle'] ?? '')),
                'image' => $image,
            ];
        })->filter()->values();
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
     * Products for the storefront "Bestsellers" row. Manually featured products
     * are pinned to the front, then real top-sellers (by units sold) fill the
     * remaining slots, then the newest products top it up if still short.
     */
    protected function bestsellerProducts(int $limit = 8)
    {
        // 1. Pinned: products marked "featured".
        $products = Product::where('is_active', true)->where('is_featured', true)
            ->with('category')->latest()->take($limit)->get();

        // 2. Top-sellers by units sold (excluding any already pinned).
        if ($products->count() < $limit) {
            $ids = $this->bestsellers->topProductIds($limit + $products->count());
            $ids = array_values(array_diff($ids, $products->pluck('id')->all()));
            if (! empty($ids)) {
                $sellers = Product::where('is_active', true)->with('category')
                    ->whereIn('id', $ids)->get()
                    ->sortBy(fn ($p) => array_search($p->id, $ids))->values();
                $products = $products->concat($sellers)->take($limit);
            }
        }

        // 3. Newest products to fill any remaining slots.
        if ($products->count() < $limit) {
            $fill = Product::where('is_active', true)->with('category')
                ->whereNotIn('id', $products->pluck('id')->all() ?: [0])
                ->latest()->take($limit - $products->count())->get();
            $products = $products->concat($fill);
        }

        return $products->values();
    }

    /** "Join our community" newsletter signup. */
    public function subscribe(Request $request, $store)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        // Idempotent — re-subscribing with the same email is a no-op success.
        Subscriber::firstOrCreate(
            ['email' => strtolower($data['email'])],
            ['name' => $data['name'] ?? null],
        );

        return back()->with('status', "Thanks for joining the {$this->storeName()} community! We'll be in touch.");
    }

    /** Current store name (tenancy is resolved by the storefront middleware). */
    protected function storeName(): string
    {
        return optional(app(\App\Support\Tenancy::class)->current())->name ?? 'our';
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
