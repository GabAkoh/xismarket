<?php

namespace App\Support;

/**
 * Canonical catalogue of permissions, grouped by module.
 * Used by the RBAC seeder and the role-management UI.
 */
class Permissions
{
    /** @return array<string, array<string, string>> group => [slug => label] */
    public static function catalog(): array
    {
        return [
            'Users & Access' => [
                'users.view' => 'View staff',
                'users.manage' => 'Create / edit / deactivate staff',
                'roles.view' => 'View roles',
                'roles.manage' => 'Create / edit roles & permissions',
            ],
            'Inventory' => [
                'inventory.view' => 'View inventory',
                'products.manage' => 'Create / edit products',
                'categories.manage' => 'Manage categories',
                'suppliers.manage' => 'Manage suppliers',
                'warehouses.manage' => 'Manage warehouses',
                'stock.adjust' => 'Adjust stock levels',
                'purchases.view' => 'View purchase orders',
                'purchases.manage' => 'Create / receive purchase orders',
            ],
            'Point of Sale' => [
                'pos.use' => 'Operate the register',
                'sales.view' => 'View sales history',
                'sales.refund' => 'Process refunds',
                'registers.manage' => 'Manage registers & shifts',
                'customers.view' => 'View customers',
                'customers.manage' => 'Manage customers',
            ],
            'Accounting' => [
                'accounting.view' => 'View accounting',
                'accounts.manage' => 'Manage chart of accounts',
                'journals.manage' => 'Create journal entries',
                'taxes.manage' => 'Manage tax rates',
                'reports.view' => 'View financial reports',
            ],
            'Online Orders' => [
                'orders.view' => 'View online orders',
                'orders.manage' => 'Create / edit orders',
                'orders.fulfill' => 'Fulfil, complete & cancel orders',
            ],
            'Delivery' => [
                'deliveries.view' => 'View deliveries',
                'deliveries.manage' => 'Assign, dispatch & complete deliveries',
                'drivers.manage' => 'Manage drivers',
            ],
        ];
    }

    /** Flat list of every permission slug. */
    public static function allSlugs(): array
    {
        $slugs = [];
        foreach (static::catalog() as $perms) {
            foreach ($perms as $slug => $label) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /** Default role => permission-slugs map used when seeding a new tenant. */
    public static function defaultRoles(): array
    {
        return [
            'admin' => [
                'name' => 'Administrator',
                'description' => 'Full access to every module.',
                'permissions' => static::allSlugs(),
            ],
            'manager' => [
                'name' => 'Manager',
                'description' => 'Runs day-to-day operations across modules.',
                'permissions' => [
                    'users.view',
                    'inventory.view', 'products.manage', 'categories.manage',
                    'suppliers.manage', 'warehouses.manage', 'stock.adjust',
                    'purchases.view', 'purchases.manage',
                    'pos.use', 'sales.view', 'sales.refund', 'registers.manage',
                    'customers.view', 'customers.manage',
                    'accounting.view', 'reports.view',
                    'orders.view', 'orders.manage', 'orders.fulfill',
                    'deliveries.view', 'deliveries.manage', 'drivers.manage',
                ],
            ],
            'cashier' => [
                'name' => 'Cashier',
                'description' => 'Operates the point of sale.',
                'permissions' => [
                    'pos.use', 'sales.view', 'inventory.view',
                    'customers.view', 'customers.manage',
                    'orders.view', 'orders.manage',
                ],
            ],
            'accountant' => [
                'name' => 'Accountant',
                'description' => 'Manages the books and financial reports.',
                'permissions' => [
                    'accounting.view', 'accounts.manage', 'journals.manage',
                    'taxes.manage', 'reports.view', 'sales.view', 'purchases.view',
                ],
            ],
        ];
    }
}
