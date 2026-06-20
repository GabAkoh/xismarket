<?php

namespace App\Console\Commands;

use App\Models\Inventory\Category;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turn flat, path-named categories (e.g. "Apparel > Clothing > Activewear > Pants",
 * as imported from Shopify's Google product taxonomy) into a real parent tree:
 * intermediate ancestors are created as needed and each leaf is renamed to its
 * short name and linked to its parent. Idempotent — re-running does nothing once
 * the tree exists. Products stay linked to their (now renamed) leaf category.
 */
class NestCategoriesCommand extends Command
{
    protected $signature = 'categories:nest
        {--tenant= : Tenant id or slug (defaults to the only tenant)}
        {--dry : Preview the changes without writing}';

    protected $description = 'Build a category tree from "A > B > C"-style category names';

    /** @var array<string, Category> full-path string => the category that node maps to */
    protected array $nodeByPath = [];

    protected int $created = 0;

    protected int $nested = 0;

    public function handle(Tenancy $tenancy): int
    {
        $tenant = $this->resolveTenant();
        if (! $tenant) {
            return self::FAILURE;
        }
        $tenancy->set($tenant);

        $cats = Category::all();
        $byId = $cats->keyBy('id');

        // Seed: map each category to its full path (reconstructed from the tree for
        // already-nested ones, or its own ">"-name for flat ones). Makes re-runs safe.
        foreach ($cats as $c) {
            $key = $this->fullPath($c, $byId);
            if (! isset($this->nodeByPath[$key])) {
                $this->nodeByPath[$key] = $c;
            }
        }

        // Flat, path-named categories that still need nesting.
        $flat = $cats->filter(fn ($c) => count($this->segments($c->name)) > 1 && ! $c->parent_id);
        $this->info(($this->option('dry') ? '[dry run] ' : '').'Nesting '.$flat->count().' path-named categories…');

        $run = function () use ($flat) {
            foreach ($flat as $c) {
                $segs = $this->segments($c->name);
                $parent = $this->ensurePath(array_slice($segs, 0, -1));
                $leaf = end($segs);

                if ((int) $c->parent_id !== (int) $parent->id || $c->name !== $leaf) {
                    if (! $this->option('dry')) {
                        $c->update(['name' => $leaf, 'parent_id' => $parent->id]);
                    }
                    $this->nested++;
                }
            }
        };

        $this->option('dry') ? $run() : DB::transaction($run);

        $this->info(($this->option('dry') ? 'Would create' : 'Created')." {$this->created} ancestor categ/ies and "
            .($this->option('dry') ? 'nest' : 'nested')." {$this->nested} leaf categor(y/ies).");

        return self::SUCCESS;
    }

    /** Get-or-create the category node for a path (array of segments). */
    protected function ensurePath(array $segs): Category
    {
        $key = implode(' > ', $segs);
        if (isset($this->nodeByPath[$key])) {
            return $this->nodeByPath[$key];
        }

        $parentId = count($segs) > 1 ? $this->ensurePath(array_slice($segs, 0, -1))->id : null;
        $name = end($segs);

        if ($this->option('dry')) {
            // A lightweight stand-in so recursion/keys still work in preview mode.
            $cat = new Category(['name' => $name, 'parent_id' => $parentId]);
            $cat->id = -(++$this->created);
        } else {
            $cat = Category::create(['name' => $name, 'slug' => $this->uniqueSlug($name), 'parent_id' => $parentId]);
            $this->created++;
        }

        return $this->nodeByPath[$key] = $cat;
    }

    /** Full path of a category, walking up the existing parent chain. */
    protected function fullPath(Category $c, $byId): string
    {
        $segments = [];
        $cur = $c;
        $guard = 0;
        while ($cur && $guard++ < 50) {
            $segments = array_merge($this->segments($cur->name), $segments);
            $cur = $cur->parent_id ? ($byId[$cur->parent_id] ?? null) : null;
        }

        return implode(' > ', $segments);
    }

    /** Split a "A > B > C" name into trimmed, non-empty segments. */
    protected function segments(string $name): array
    {
        return array_values(array_filter(array_map('trim', explode('>', $name)), fn ($s) => $s !== ''));
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
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

        return null;
    }
}
