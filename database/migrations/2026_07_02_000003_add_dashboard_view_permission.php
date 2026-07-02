<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Gate the dashboard business summaries behind a dedicated dashboard.view
 * permission, and grant it to management/finance roles (those that already see
 * financial reports) so they keep the summaries. Others (e.g. Cashier) no
 * longer see the figures — the dashboard still opens, just without them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::updateOrCreate(
            ['slug' => 'dashboard.view'],
            ['name' => 'View dashboard summaries', 'group' => 'Dashboard'],
        );

        // Grant to any role that can view financial reports (management/finance).
        $reportsId = Permission::where('slug', 'reports.view')->value('id');
        if (! $reportsId) {
            return;
        }

        foreach (Role::withoutGlobalScopes()->with('permissions:id,slug')->get() as $role) {
            if ($role->permissions->contains('slug', 'reports.view')) {
                $role->permissions()->syncWithoutDetaching([$perm->id]);
            }
        }
    }

    public function down(): void
    {
        Permission::where('slug', 'dashboard.view')->delete();
    }
};
