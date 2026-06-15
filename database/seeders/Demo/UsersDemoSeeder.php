<?php

namespace Database\Seeders\Demo;

use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = app(Tenancy::class)->id();

        $staff = [
            ['name' => 'Maria Manager', 'email' => 'manager@demo.test', 'role' => 'manager'],
            ['name' => 'Carl Cashier', 'email' => 'cashier@demo.test', 'role' => 'cashier'],
            ['name' => 'Anna Accountant', 'email' => 'accountant@demo.test', 'role' => 'accountant'],
        ];

        foreach ($staff as $member) {
            $user = User::firstOrCreate(
                ['tenant_id' => $tenantId, 'email' => $member['email']],
                [
                    'name' => $member['name'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ],
            );

            $role = Role::where('slug', $member['role'])->first();
            if ($role) {
                $user->roles()->syncWithoutDetaching($role->id);
            }
        }
    }
}
