<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'slug', 'description', 'is_system'];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissions->contains('slug', $slug);
    }

    public function syncPermissionsBySlug(array $slugs): void
    {
        $ids = Permission::whereIn('slug', $slugs)->pluck('id')->all();
        $this->permissions()->sync($ids);
    }
}
