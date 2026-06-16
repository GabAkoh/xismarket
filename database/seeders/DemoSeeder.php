<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenancy = app(Tenancy::class);

        $tenant = Tenant::where('slug', 'demo-store')->first();

        if (! $tenant) {
            $result = app(TenantProvisioner::class)->provision(
                tenantData: ['name' => 'NimiKiddies', 'currency' => 'USD'],
                ownerData: [
                    'name' => 'Demo Owner',
                    'email' => 'owner@demo.test',
                    'password' => 'password',
                ],
            );
            $tenant = $result['tenant'];
            // Force a recognizable slug for the demo tenant.
            $tenant->update(['slug' => 'demo-store']);

            $this->command->info('Demo tenant created — login: owner@demo.test / password');
        }

        $tenancy->set($tenant);

        // Module demo seeders register themselves here as they are added.
        foreach ([
            \Database\Seeders\Demo\InventoryDemoSeeder::class,
            \Database\Seeders\Demo\PosDemoSeeder::class,
            \Database\Seeders\Demo\AccountingDemoSeeder::class,
            \Database\Seeders\Demo\OrdersDemoSeeder::class,
            \Database\Seeders\Demo\DeliveryDemoSeeder::class,
            \Database\Seeders\Demo\UsersDemoSeeder::class,
        ] as $seeder) {
            if (class_exists($seeder)) {
                $this->call($seeder);
            }
        }

        $tenancy->forget();
    }
}
