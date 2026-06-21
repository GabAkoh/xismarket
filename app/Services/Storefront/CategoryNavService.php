<?php

namespace App\Services\Storefront;

use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use Illuminate\Support\Collection;

/**
 * Shared category-tree helpers for the storefront: builds the tree once and
 * rolls up how many active products sit in each sub-tree, so navigation and
 * filtering can rank/show categories by their whole branch (not just the
 * products filed directly on them).
 */
class CategoryNavService
{
    /**
     * @return array{0: Collection, 1: array<int, int[]>, 2: array<int, int>}
     *         [id => Category, parentId => [childIds], id => subtreeActiveProductCount]
     */
    public function tree(): array
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

    /** Top-level categories that have products in their sub-tree, most stocked first. */
    public function topLevel(int $limit = 6): Collection
    {
        [$byId, , $subtree] = $this->tree();

        return $byId->whereNull('parent_id')
            ->filter(fn ($c) => ($subtree[$c->id] ?? 0) > 0)
            ->sortByDesc(fn ($c) => $subtree[$c->id])
            ->take($limit)
            ->values();
    }

    /** A category id plus every descendant id, from a prebuilt parent=>children map. */
    public function descendants(int $id, array $childrenOf): array
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
}
