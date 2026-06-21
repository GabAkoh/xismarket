<?php

namespace App\Console\Commands;

use App\Models\Inventory\Category;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Group flat product categories into a shallow tree by their leading word:
 * "Boys Shirts", "Boys Shorts", "Boys Jeans" → all parented under a "Boys"
 * category. Only applies to top-level leaf categories that have products, and
 * only for prefixes shared by at least --min of them. Products are not moved —
 * the new parent simply rolls them up for hierarchical browsing. Idempotent.
 */
class GroupCategoriesCommand extends Command
{
    protected $signature = 'categories:group
        {--tenant= : Tenant id or slug (defaults to the only tenant)}
        {--min=3 : Minimum categories sharing a leading word to form a group}
        {--dry : Preview the groups without writing}';

    protected $description = 'Nest flat product categories under a parent per shared leading word';

    public function handle(Tenancy $tenancy): int
    {
        $tenant = $this->resolveTenant();
        if (! $tenant) {
            return self::FAILURE;
        }
        $tenancy->set($tenant);

        $min = max(2, (int) $this->option('min'));

        // Flat product categories = top-level, no children, with active products.
        $leaves = Category::whereNull('parent_id')
            ->whereDoesntHave('children')
            ->withCount(['products as pc' => fn ($q) => $q->where('is_active', true)])
            ->having('pc', '>', 0)
            ->orderBy('name')
            ->get();

        // Bucket by normalised leading word.
        $groups = [];
        foreach ($leaves as $c) {
            $first = preg_split('/\s+/', trim($c->name))[0] ?? '';
            $key = strtolower(preg_replace('/[^a-z0-9]/i', '', $first));
            if ($key === '') {
                continue;
            }
            $groups[$key]['label'] ??= rtrim($first, "'’");   // display name for the parent
            $groups[$key]['members'][] = $c;
        }

        $createdParents = 0;
        $nested = 0;
        $applied = [];

        $run = function () use ($groups, $min, &$createdParents, &$nested, &$applied) {
            foreach ($groups as $g) {
                $members = $g['members'];
                if (count($members) < $min) {
                    continue;
                }
                $label = $g['label'];

                // Reuse a member that is literally named the prefix as the parent.
                $parent = collect($members)->first(fn ($c) => strcasecmp(trim($c->name), $label) === 0);
                if (! $parent && $this->option('dry')) {
                    $parent = new Category(['name' => $label]);
                    $parent->id = -(++$createdParents);
                } elseif (! $parent) {
                    $parent = Category::create(['name' => $label, 'slug' => $this->uniqueSlug($label)]);
                    $createdParents++;
                }

                $childNames = [];
                foreach ($members as $c) {
                    if ($c->id === $parent->id) {
                        continue;
                    }
                    if (! $this->option('dry')) {
                        $c->update(['parent_id' => $parent->id]);
                    }
                    $nested++;
                    $childNames[] = $c->name;
                }
                $applied[$label] = $childNames;
            }
        };

        $this->option('dry') ? $run() : DB::transaction($run);

        // Report the groups that were (or would be) formed.
        foreach ($applied as $label => $children) {
            $this->line("<info>{$label}</info> ← ".count($children).': '.implode(', ', array_slice($children, 0, 6)).(count($children) > 6 ? ', …' : ''));
        }
        $verb = $this->option('dry') ? 'Would create' : 'Created';
        $this->info("\n{$verb} ".count($applied)." parent group(s); {$createdParents} new parent categor(y/ies); {$nested} categories nested.");

        return self::SUCCESS;
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
