<?php

namespace App\Services\Pos;

use App\Models\Pos\Customer;
use App\Models\Pos\LoyaltySetting;
use App\Models\Pos\LoyaltyTransaction;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty-points manager.
 *
 * Points are a marketing liability tracked off the books: earning and redeeming
 * never post journals. Redemption is applied as a discount on the sale (which
 * naturally flows through the sale's revenue posting). Every points change is
 * written to the loyalty_transactions ledger and kept in sync with
 * Customer.loyalty_points under a row lock.
 */
class LoyaltyService
{
    public function __construct(protected Tenancy $tenancy) {}

    public function settings(): LoyaltySetting
    {
        return LoyaltySetting::current();
    }

    /** Award points. Returns null when points <= 0 (nothing to record). */
    public function earn(Customer $customer, int $points, ?string $reason = null, ?Model $source = null, ?int $userId = null): ?LoyaltyTransaction
    {
        if ($points <= 0) {
            return null;
        }

        return $this->apply($customer, $points, 'earn', $reason ?? 'Points earned', $source, $userId);
    }

    /** Redeem points. Throws if the customer doesn't have enough. */
    public function redeem(Customer $customer, int $points, ?string $reason = null, ?Model $source = null, ?int $userId = null): LoyaltyTransaction
    {
        if ($points <= 0) {
            throw new \InvalidArgumentException('Redeem amount must be greater than zero.');
        }

        return $this->apply($customer, -$points, 'redeem', $reason ?? 'Points redeemed', $source, $userId, guard: true);
    }

    /** Manual admin adjustment (can be positive or negative). */
    public function adjust(Customer $customer, int $points, ?string $reason = null, ?int $userId = null): LoyaltyTransaction
    {
        if ($points === 0) {
            throw new \InvalidArgumentException('Adjustment cannot be zero.');
        }

        return $this->apply($customer, $points, 'adjust', $reason ?? 'Manual adjustment', null, $userId, guard: $points < 0);
    }

    protected function apply(Customer $customer, int $signedPoints, string $type, string $reason, ?Model $source, ?int $userId, bool $guard = false): LoyaltyTransaction
    {
        return DB::transaction(function () use ($customer, $signedPoints, $type, $reason, $source, $userId, $guard) {
            $locked = Customer::whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $current = (int) $locked->loyalty_points;

            if ($guard && $current + $signedPoints < 0) {
                throw new \RuntimeException('Insufficient loyalty points.');
            }

            $balanceAfter = max(0, $current + $signedPoints);
            $locked->update(['loyalty_points' => $balanceAfter]);

            $txn = LoyaltyTransaction::create([
                'tenant_id' => $this->tenancy->id(),
                'customer_id' => $locked->id,
                'type' => $type,
                'points' => $signedPoints,
                'points_balance' => $balanceAfter,
                'reason' => $reason,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'user_id' => $userId ?? auth()->id(),
            ]);

            $customer->loyalty_points = $balanceAfter;

            return $txn;
        });
    }
}
