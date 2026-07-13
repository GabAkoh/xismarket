<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Add the purchases.pay permission (pay suppliers / settle vendor bills) and
 * grant it to every role that can already view purchase orders — so the
 * accountant (and manager/admin) can pay vendors without gaining supplier CRUD.
 */
return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::updateOrCreate(
            ['slug' => 'purchases.pay'],
            ['name' => 'Pay suppliers / settle vendor bills', 'group' => 'Inventory'],
        );

        // Roles across every tenant that already hold purchases.view inherit the
        // new permission (keeps existing access intact; adds pay for accountants).
        foreach (Role::withoutGlobalScopes()->with('permissions:id,slug')->get() as $role) {
            if ($role->permissions->contains('slug', 'purchases.view')) {
                $role->permissions()->syncWithoutDetaching([$perm->id]);
            }
        }
    }

    public function down(): void
    {
        // Removing the permission row also clears its role pivots.
        Permission::where('slug', 'purchases.pay')->delete();
    }
};
