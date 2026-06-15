<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantProvisioner
{
    public function __construct(protected Tenancy $tenancy) {}

    /**
     * Create a tenant, its default roles, and an owner account.
     */
    public function provision(array $tenantData, array $ownerData): array
    {
        return DB::transaction(function () use ($tenantData, $ownerData) {
            $tenant = Tenant::create([
                'name' => $tenantData['name'],
                'slug' => $this->uniqueSlug($tenantData['name']),
                'email' => $ownerData['email'] ?? null,
                'currency' => $tenantData['currency'] ?? 'USD',
                'plan' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'is_active' => true,
            ]);

            // Activate context so tenant-scoped models fill tenant_id correctly.
            $this->tenancy->set($tenant);

            $roles = $this->seedRoles($tenant);

            $owner = User::create([
                'tenant_id' => $tenant->id,
                'name' => $ownerData['name'],
                'email' => $ownerData['email'],
                'password' => Hash::make($ownerData['password']),
                'is_owner' => true,
                'is_active' => true,
            ]);

            $owner->assignRole($roles['admin']);

            return ['tenant' => $tenant, 'owner' => $owner, 'roles' => $roles];
        });
    }

    /** Seed the default roles + permission grants for a tenant. */
    public function seedRoles(Tenant $tenant): array
    {
        $created = [];

        foreach (Permissions::defaultRoles() as $slug => $definition) {
            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => $definition['name'],
                'slug' => $slug,
                'description' => $definition['description'],
                'is_system' => true,
            ]);

            $role->syncPermissionsBySlug($definition['permissions']);
            $created[$slug] = $role;
        }

        return $created;
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $i = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
