<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;

/**
 * Split sales reports & payment summaries out from the broad "sales.view"
 * permission into their own "sales.reports" permission. Existing default roles
 * that are meant to see the reports (admin, manager, accountant) get the new
 * permission granted; the cashier keeps sales.view (individual sales) but not
 * the aggregated reports. Custom roles are left untouched — admins can grant
 * the permission in the role-management UI. One-time; safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ensure the permission row exists for every (shared) permissions table.
        $perm = Permission::firstOrCreate(
            ['slug' => 'sales.reports'],
            ['name' => 'View sales reports & payment summaries', 'group' => 'Point of Sale'],
        );

        // Default roles whose seed permissions include sales.reports.
        $eligible = collect(Permissions::defaultRoles())
            ->filter(fn ($def) => in_array('sales.reports', $def['permissions'], true));

        foreach ($eligible as $slug => $def) {
            Role::withoutGlobalScopes()
                ->where(fn ($q) => $q->where('slug', $slug)->orWhere('name', $def['name']))
                ->get()
                ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$perm->id]));
        }
    }

    public function down(): void
    {
        // Detach the permission from all roles, then remove it.
        $perm = Permission::where('slug', 'sales.reports')->first();

        if ($perm) {
            $perm->roles()->detach();
            $perm->delete();
        }
    }
};
