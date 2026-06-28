<?php

namespace App\Console\Commands;

use App\Models\Inventory\Product;
use App\Models\Tenant;
use App\Services\Storefront\NewArrivalsBroadcaster;
use App\Support\Tenancy;
use Illuminate\Console\Command;

/**
 * Emails each store's subscribers the products added in the last week. Runs
 * across all active tenants (it executes outside any web request, so it sets
 * the tenant context per store). Scheduled weekly in routes/console.php.
 */
class SendWeeklyDigestCommand extends Command
{
    protected $signature = 'subscribers:weekly-digest {--days=7 : How many days back counts as "new"}';

    protected $description = 'Email subscribers the products added in the last week';

    public function handle(Tenancy $tenancy, NewArrivalsBroadcaster $broadcaster): int
    {
        $days = max(1, (int) $this->option('days'));
        $queued = 0;

        foreach (Tenant::where('is_active', true)->get() as $tenant) {
            $tenancy->set($tenant);
            try {
                $products = Product::where('is_active', true)
                    ->where('created_at', '>=', now()->subDays($days))
                    ->latest()->take(24)->get();

                if ($products->isEmpty()) {
                    continue;
                }

                $n = $broadcaster->send(
                    $tenant,
                    $products,
                    "Here's what's new this week at {$tenant->name}.",
                    'New this week at '.$tenant->name,
                );
                $queued += $n;

                $this->info("{$tenant->name}: {$products->count()} new product(s) → {$n} subscriber(s).");
            } finally {
                $tenancy->forget();
            }
        }

        $this->info("Weekly digest complete — queued {$queued} email(s).");

        return self::SUCCESS;
    }
}
