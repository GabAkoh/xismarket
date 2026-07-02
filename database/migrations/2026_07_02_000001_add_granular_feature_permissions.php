<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Split several broad permissions into dedicated ones (categories, stock levels,
 * stock valuation, wallets, loyalty, POS settings, coupons, subscribers,
 * storefront, online payments) and grant each new permission to every role that
 * already holds the broad one — so no role loses access on deploy.
 */
return new class extends Migration
{
    /** New permission => the broad permission a role must already have to inherit it. */
    protected array $map = [
        'categories.view' => 'inventory.view',
        'stock.view' => 'inventory.view',
        'stock.valuation' => 'inventory.view',
        'wallets.view' => 'customers.view',
        'loyalty.manage' => 'customers.manage',
        'pos.settings' => 'registers.manage',
        'coupons.manage' => 'orders.manage',
        'storefront.manage' => 'orders.manage',
        'payments.manage' => 'orders.manage',
        'subscribers.view' => 'orders.view',
    ];

    protected array $catalog = [
        'Inventory' => [
            'categories.view' => 'View categories',
            'stock.view' => 'View stock levels',
            'stock.valuation' => 'View stock valuation',
        ],
        'Point of Sale' => [
            'pos.settings' => 'Manage POS settings (register display, payment methods, cash reasons)',
            'wallets.view' => 'View customer wallets',
            'loyalty.manage' => 'Manage the loyalty program',
        ],
        'Online Orders' => [
            'coupons.manage' => 'Manage discount coupons',
            'subscribers.view' => 'View newsletter subscribers',
            'storefront.manage' => 'Manage storefront content',
            'payments.manage' => 'Manage online payment settings',
        ],
    ];

    public function up(): void
    {
        foreach ($this->catalog as $group => $perms) {
            foreach ($perms as $slug => $label) {
                Permission::updateOrCreate(['slug' => $slug], ['name' => $label, 'group' => $group]);
            }
        }

        $ids = Permission::pluck('id', 'slug');

        // Every role across every tenant.
        foreach (Role::withoutGlobalScopes()->with('permissions:id,slug')->get() as $role) {
            $have = $role->permissions->pluck('slug')->flip();
            $attach = [];
            foreach ($this->map as $newSlug => $oldSlug) {
                if ($have->has($oldSlug) && isset($ids[$newSlug])) {
                    $attach[] = $ids[$newSlug];
                }
            }
            if ($attach) {
                $role->permissions()->syncWithoutDetaching($attach);
            }
        }
    }

    public function down(): void
    {
        // Removing the permission rows also clears their role pivots.
        Permission::whereIn('slug', array_keys($this->map))->delete();
    }
};
