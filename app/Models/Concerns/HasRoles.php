<?php

namespace App\Models\Concerns;

use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function assignRole(Role|string $role): void
    {
        $role = is_string($role)
            ? Role::where('slug', $role)->firstOrFail()
            : $role;

        $this->roles()->syncWithoutDetaching($role);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    /** All permission slugs granted to the user via any of its roles. */
    public function permissionSlugs(): Collection
    {
        return $this->roles
            ->loadMissing('permissions')
            ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values();
    }

    public function hasPermission(string $slug): bool
    {
        // Super admins and tenant owners bypass granular checks.
        if ($this->is_super_admin || $this->is_owner) {
            return true;
        }

        return $this->permissionSlugs()->contains($slug);
    }
}
