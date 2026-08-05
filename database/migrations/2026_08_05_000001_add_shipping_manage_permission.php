<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Give shipping-method management its own permission. The editor moved out of
 * the Storefront content page (Online Orders) into its own page under Delivery,
 * so it gets a dedicated slug instead of riding on storefront.manage. Every role
 * that already holds storefront.manage inherits it, so no role loses access and
 * it can now be assigned independently from the Roles screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::updateOrCreate(
            ['slug' => 'shipping.manage'],
            ['name' => 'Manage shipping methods', 'group' => 'Delivery'],
        );

        // Roles across every tenant that already manage storefront content
        // inherit the new permission (keeps existing access exactly as it was).
        foreach (Role::withoutGlobalScopes()->with('permissions:id,slug')->get() as $role) {
            if ($role->permissions->contains('slug', 'storefront.manage')) {
                $role->permissions()->syncWithoutDetaching([$perm->id]);
            }
        }
    }

    public function down(): void
    {
        // Removing the permission row also clears its role pivots.
        Permission::where('slug', 'shipping.manage')->delete();
    }
};
