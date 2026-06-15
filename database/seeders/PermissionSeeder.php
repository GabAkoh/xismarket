<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissions::catalog() as $group => $perms) {
            foreach ($perms as $slug => $label) {
                Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $label, 'group' => $group],
                );
            }
        }
    }
}
