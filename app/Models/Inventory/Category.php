<?php

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'parent_id', 'name', 'slug',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * This category id plus every descendant id (the whole subtree).
     * One query loads the id/parent map; the tree is then walked in memory.
     *
     * @return array<int, int>
     */
    public static function subtreeIds(int $id): array
    {
        $childrenOf = [];
        foreach (static::query()->get(['id', 'parent_id']) as $c) {
            if ($c->parent_id) {
                $childrenOf[$c->parent_id][] = $c->id;
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

        return array_map('intval', array_keys($ids));
    }
}
