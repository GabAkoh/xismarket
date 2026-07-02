<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;

/**
 * Tighten the Cashier role to POS-relevant permissions only (register, sales,
 * products, customers) — dropping the broad access it inherited (coupons,
 * storefront, online payments, loyalty settings, stock valuation, online
 * orders, etc.). One-time; admins can re-adjust any role afterwards in the UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        $slugs = Permissions::defaultRoles()['cashier']['permissions'];
        $ids = Permission::whereIn('slug', $slugs)->pluck('id')->all();

        $roles = Role::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('slug', 'cashier')->orWhere('name', 'Cashier'))
            ->get();

        foreach ($roles as $role) {
            $role->permissions()->sync($ids);
        }
    }

    public function down(): void
    {
        // One-time tightening; prior permissions aren't restored automatically.
    }
};
