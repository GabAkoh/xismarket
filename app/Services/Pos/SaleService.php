<?php

namespace App\Services\Pos;

use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\Customer;
use App\Models\Pos\Payment;
use App\Models\Pos\Register;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleItem;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The POS checkout brain.
 *
 * Owns the full sale lifecycle: building the Sale/SaleItem/Payment rows,
 * decrementing inventory through the Inventory module, and posting the
 * matching double-entry journal through the Accounting module. Both of
 * those cross-module calls are guarded with class_exists so this module
 * compiles and runs even when those modules are absent.
 */
class SaleService
{
    public function __construct(protected Tenancy $tenancy) {}

    /**
     * Complete a sale inside a single DB transaction.
     *
     * Expected $data shape:
     *   register_id?  int|null
     *   shift_id?     int|null
     *   customer_id?  int|null
     *   user_id?      int|null      (defaults to auth user)
     *   note?         string|null
     *   completed_at? Carbon|string (defaults to now; lets the seeder backdate)
     *   discount?     float         (overall discount applied on top of line discounts)
     *   items:        [['product_id' => int, 'quantity' => float, 'unit_price'? => float,
     *                    'discount'? => float], ...]
     *   payments:     [['method' => string, 'amount' => float, 'reference'? => string], ...]
     */
    public function complete(array $data): Sale
    {
        $sale = DB::transaction(function () use ($data) {
            $tenantId = $this->tenancy->id();
            $register = isset($data['register_id'])
                ? Register::find($data['register_id'])
                : null;

            $completedAt = isset($data['completed_at'])
                ? Carbon::parse($data['completed_at'])
                : now();

            // The warehouse this register sells from — also used to reject
            // out-of-stock items below and to decrement stock at the end.
            $warehouse = $this->resolveWarehouse($register);

            // Out-of-stock products cannot be sold (tracked products at <= 0 on hand).
            $soldOut = $this->outOfStock($data['items'] ?? [], $warehouse);
            if ($soldOut !== []) {
                throw new \RuntimeException(
                    'Out of stock: '.implode(', ', $soldOut).'. Remove '
                    .(count($soldOut) === 1 ? 'it' : 'them').' to complete the sale.'
                );
            }

            // --- Build line items (with product snapshots) ---
            $lines = [];
            $subtotal = 0.0;     // sum of line nets BEFORE tax, AFTER line discounts
            $taxTotal = 0.0;
            $lineDiscountTotal = 0.0;
            $cogsTotal = 0.0;
            $products = [];      // product_id => Product (for stock movements)

            foreach ($data['items'] ?? [] as $row) {
                $product = Product::find($row['product_id']);
                if (! $product) {
                    continue;
                }

                $qty = round((float) ($row['quantity'] ?? 0), 3);
                if ($qty <= 0) {
                    continue;
                }

                $unitPrice = isset($row['unit_price'])
                    ? round((float) $row['unit_price'], 2)
                    : round((float) $product->sale_price, 2);
                $unitCost = round((float) $product->cost_price, 2);
                // Product tax_rate is stored as a percentage (e.g. 8.0 == 8%).
                $taxRate = (float) $product->tax_rate / 100;
                $discount = round((float) ($row['discount'] ?? 0), 2);

                $gross = round($unitPrice * $qty, 2);
                $net = round($gross - $discount, 2);          // taxable base for this line
                if ($net < 0) {
                    $net = 0.0;
                }
                $lineTax = round($net * $taxRate, 2);
                $lineTotal = round($net + $lineTax, 2);

                $subtotal += $net;
                $taxTotal += $lineTax;
                $lineDiscountTotal += $discount;
                $cogsTotal += round($unitCost * $qty, 2);

                $products[$product->id] = $product;

                $lines[] = [
                    'tenant_id' => $tenantId,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'tax_rate' => $taxRate,
                    'discount' => $discount,
                    'line_total' => $lineTotal,
                ];
            }

            // Resolve the customer up front (needed for wallet + loyalty).
            $customer = isset($data['customer_id'])
                ? Customer::find($data['customer_id'])
                : null;

            // Overall (cart-level) discount applies to the net revenue.
            $overallDiscount = round((float) ($data['discount'] ?? 0), 2);

            // subtotal = gross value before any discount, so that
            // (subtotal - discount_total) == net revenue.
            $subtotal = round($subtotal + $lineDiscountTotal, 2);

            // Net revenue before loyalty redemption is applied.
            $discountBeforeLoyalty = round($lineDiscountTotal + $overallDiscount, 2);
            $netBeforeLoyalty = max(0.0, round($subtotal - $discountBeforeLoyalty, 2));

            // --- Loyalty redemption -> discount (capped to points held & to net) ---
            $loyalty = app(LoyaltyService::class);
            $settings = $loyalty->settings();
            $requestedPoints = (int) ($data['points_redeemed'] ?? 0);
            $pointsRedeemed = 0;
            $loyaltyDiscount = 0.0;
            if ($customer && $settings->is_active && $requestedPoints > 0 && (float) $settings->redeem_value > 0) {
                $maxByPoints = min($requestedPoints, (int) $customer->loyalty_points);
                $maxByValue = (int) floor($netBeforeLoyalty / (float) $settings->redeem_value);
                $pointsRedeemed = max(0, min($maxByPoints, $maxByValue));
                $loyaltyDiscount = $settings->valueOf($pointsRedeemed);
            }

            $discountTotal = round($discountBeforeLoyalty + $loyaltyDiscount, 2);
            $netRevenue = round($subtotal - $discountTotal, 2);
            if ($netRevenue < 0) {
                $netRevenue = 0.0;
            }

            $total = round($netRevenue + $taxTotal, 2);

            // Wallet-funded portion of this sale (capped to the total).
            $walletUsed = 0.0;
            foreach ($data['payments'] ?? [] as $p) {
                if (($p['method'] ?? null) === 'wallet') {
                    $walletUsed += round((float) ($p['amount'] ?? 0), 2);
                }
            }
            $walletUsed = round(min($walletUsed, $total), 2);

            // "Credit" tenders aren't money received — they record an amount the
            // customer owes (accounts receivable). Capped to the sale total.
            $creditKeys = $this->tenancy->current()?->creditPaymentMethodKeys() ?? [];
            $creditOwed = 0.0;
            foreach ($data['payments'] ?? [] as $p) {
                if (in_array($p['method'] ?? null, $creditKeys, true)) {
                    $creditOwed += round((float) ($p['amount'] ?? 0), 2);
                }
            }
            $creditOwed = round(min($creditOwed, $total), 2);

            // Points earned on the realised net revenue.
            $pointsEarned = ($customer && $settings->is_active)
                ? $settings->pointsFor($netRevenue)
                : 0;

            // --- Payments ---
            // Real money received = wallet (clamped) + non-wallet, non-credit tenders.
            $nonWalletPaid = 0.0;
            foreach ($data['payments'] ?? [] as $p) {
                $method = $p['method'] ?? null;
                if ($method === 'wallet' || in_array($method, $creditKeys, true)) {
                    continue;
                }
                $nonWalletPaid += round((float) ($p['amount'] ?? 0), 2);
            }
            $paidTotal = round($walletUsed + round($nonWalletPaid, 2), 2);

            // The sale must be fully accounted for — real payment plus any amount
            // put on credit has to cover the total.
            if ($paidTotal + $creditOwed + 0.001 < $total) {
                throw new \RuntimeException(
                    'Payment is insufficient: '.number_format($paidTotal + $creditOwed, 2)
                    .' tendered against a total of '.number_format($total, 2)
                    .'. The full amount must be paid or put on credit to complete the sale.'
                );
            }

            // The unpaid portion (up to what was put on credit) is owed by the customer.
            $balanceDue = round(min($creditOwed, max(0.0, $total - $paidTotal)), 2);

            if ($balanceDue > 0.001 && ! $customer) {
                throw new \RuntimeException(
                    'A credit sale (balance owing) requires a customer. Select a customer or collect full payment.'
                );
            }

            $status = $balanceDue > 0.001 ? 'partially_paid' : 'completed';
            // Change only ever comes from real over-payment, never from a credit tender.
            $changeDue = round(max(0.0, $paidTotal - ($total - $balanceDue)), 2);

            // --- Persist the Sale ---
            $sale = Sale::create([
                'tenant_id' => $tenantId,
                'register_id' => $register?->id,
                'shift_id' => $data['shift_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $data['user_id'] ?? auth()->id(),
                'number' => $this->nextNumber(),
                'status' => $status,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'total' => $total,
                'paid_total' => $paidTotal,
                'change_due' => $changeDue,
                'balance_due' => $balanceDue,
                'wallet_used' => $walletUsed,
                'loyalty_discount' => $loyaltyDiscount,
                'points_earned' => $pointsEarned,
                'points_redeemed' => $pointsRedeemed,
                'note' => $data['note'] ?? null,
                'completed_at' => $completedAt,
            ]);

            foreach ($lines as $line) {
                $line['sale_id'] = $sale->id;
                SaleItem::create($line);
            }

            // Record the wallet portion as a single clamped line...
            if ($walletUsed > 0) {
                Payment::create([
                    'tenant_id' => $tenantId,
                    'sale_id' => $sale->id,
                    'method' => 'wallet',
                    'amount' => $walletUsed,
                    'reference' => null,
                    'paid_at' => $completedAt,
                ]);
            }
            // ...then every non-wallet payment at its tendered amount. Credit tenders
            // are not money received — they're captured as balance_due, not a Payment.
            foreach ($data['payments'] ?? [] as $p) {
                $method = $p['method'] ?? null;
                if ($method === 'wallet' || in_array($method, $creditKeys, true)) {
                    continue;
                }
                $amount = round((float) ($p['amount'] ?? 0), 2);
                if ($amount <= 0) {
                    continue;
                }
                Payment::create([
                    'tenant_id' => $tenantId,
                    'sale_id' => $sale->id,
                    'method' => $p['method'] ?? 'cash',
                    'amount' => $amount,
                    'reference' => $p['reference'] ?? null,
                    'paid_at' => $completedAt,
                ]);
            }

            // --- Wallet + loyalty side effects (all inside the sale transaction) ---
            if ($customer && $walletUsed > 0) {
                // Throws if the balance is insufficient, rolling back the whole sale.
                app(WalletService::class)->debit($customer, $walletUsed, 'POS sale '.$sale->number, $sale);
            }
            if ($customer && $pointsRedeemed > 0) {
                $loyalty->redeem($customer, $pointsRedeemed, 'Redeemed on sale '.$sale->number, $sale);
            }
            if ($customer && $pointsEarned > 0) {
                $loyalty->earn($customer, $pointsEarned, 'Earned on sale '.$sale->number, $sale);
            }

            // --- Cross-module: decrement stock & post the journal ---
            $this->decrementStock($sale, $products, $lines, $warehouse);
            $this->postSaleJournal($sale, $netRevenue, $taxTotal, $total, $cogsTotal, $walletUsed, $balanceDue, $completedAt);

            return $sale->load('items', 'payments', 'customer');
        });

        // The bestsellers ranking changed — drop its cache so the storefront rebuilds it.
        app(\App\Services\Storefront\BestsellerService::class)->forget($this->tenancy->id());

        return $sale;
    }

    /**
     * Refund an entire sale — convenience wrapper that returns every remaining
     * (not-yet-returned) unit on the sale.
     */
    public function refund(Sale $sale): Sale
    {
        $sale->loadMissing('items');

        $quantities = [];
        foreach ($sale->items as $item) {
            $remaining = $item->returnableQuantity();
            if ($remaining > 0) {
                $quantities[$item->id] = $remaining;
            }
        }

        return $this->processReturn($sale, $quantities);
    }

    /**
     * Process a partial (line-level) or full return.
     *
     * For each returned line it restores stock (type 'return', +qty) and tallies
     * the refund amounts apportioned to the returned quantity, then posts a single
     * reversing journal entry. The sale becomes 'partially_refunded', or 'refunded'
     * once every line is fully returned.
     *
     * Refund revenue is apportioned from the sale's net revenue (which already
     * reflects cart-level and loyalty discounts), so the refunded amount matches
     * what was actually paid; tax is refunded as it was charged.
     *
     * @param  array<int|string, float>  $quantities  sale_item_id => quantity to return
     */
    public function processReturn(Sale $sale, array $quantities): Sale
    {
        $sale = DB::transaction(function () use ($sale, $quantities) {
            $sale->loadMissing('items', 'register', 'customer');

            if ($sale->status === 'refunded') {
                throw new \RuntimeException('This sale has already been fully returned.');
            }
            if ($sale->status === 'partially_paid') {
                throw new \RuntimeException('Settle the outstanding balance before returning items.');
            }

            $warehouse = $this->resolveWarehouse($sale->register);
            $stock = class_exists(\App\Services\Inventory\StockService::class)
                ? app(\App\Services\Inventory\StockService::class)
                : null;

            // The sale's net revenue already reflects cart-level and loyalty
            // discounts; apportion it down to each line so the refunded revenue
            // matches what was actually paid (tax stays as it was charged).
            $totalLineNet = round((float) $sale->items->sum(
                fn ($i) => (float) $i->unit_price * (float) $i->quantity - (float) $i->discount
            ), 2);
            $revenueFactor = $totalLineNet > 0
                ? min(1.0, max(0.0, $sale->netRevenue() / $totalLineNet))
                : 1.0;

            $net = 0.0;
            $tax = 0.0;
            $cogs = 0.0;
            $returnedAny = false;

            foreach ($sale->items as $item) {
                $qty = round((float) ($quantities[$item->id] ?? 0), 3);
                if ($qty <= 0) {
                    continue;
                }

                $returnable = $item->returnableQuantity();
                if ($qty > $returnable + 0.0001) {
                    throw new \RuntimeException(
                        'Cannot return '.rtrim(rtrim(number_format($qty, 3), '0'), '.').' of '.$item->name.
                        ' — only '.rtrim(rtrim(number_format($returnable, 3), '0'), '.').' returnable.'
                    );
                }

                // Apportion the line to the returned quantity. Tax was charged on
                // the line net (before cart/loyalty discounts); revenue is then
                // scaled by the sale-level discount factor.
                $lineQty = (float) $item->quantity;
                $fraction = $lineQty > 0 ? $qty / $lineQty : 0.0;
                $lineNet = round((float) $item->unit_price * $lineQty - (float) $item->discount, 2);
                $rNetPre = round($lineNet * $fraction, 2);
                $rTax = round($rNetPre * (float) $item->tax_rate, 2);
                $rNet = round($rNetPre * $revenueFactor, 2);

                $net += $rNet;
                $tax += $rTax;
                $cogs += round((float) $item->unit_cost * $qty, 2);

                if ($stock && $warehouse && $item->product_id) {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $stock->recordMovement(
                            $product, $warehouse, 'return', $qty,
                            (float) $item->unit_cost, $sale, 'Return '.$sale->number,
                        );
                    }
                }

                $item->returned_quantity = round((float) $item->returned_quantity + $qty, 3);
                $item->save();
                $returnedAny = true;
            }

            if (! $returnedAny) {
                throw new \RuntimeException('Enter a quantity to return for at least one item.');
            }

            $net = round($net, 2);
            $tax = round($tax, 2);
            $cogs = round($cogs, 2);
            $total = round($net + $tax, 2);

            // Fully returned when no line has any returnable quantity left.
            $fully = $sale->items->every(fn ($i) => $i->returnableQuantity() <= 0.0001);

            // Cumulative fraction of the sale's paid value returned so far, used to
            // apportion sale-level wallet/loyalty figures — exact, and 1 when fully
            // returned. $total already reflects the apportioned cart/loyalty discount.
            $refundedSoFar = round((float) $sale->refunded_total + $total, 2);
            $frac = $fully
                ? 1.0
                : ((float) $sale->total > 0 ? min(1.0, $refundedSoFar / (float) $sale->total) : 0.0);

            $customer = $sale->customer;

            // --- Reverse store credit (wallet) for the returned portion ---
            $walletInc = 0.0;
            if ($customer && (float) $sale->wallet_used > 0) {
                $target = round((float) $sale->wallet_used * $frac, 2);
                $walletInc = max(0, round($target - (float) $sale->wallet_refunded, 2));
                if ($walletInc > 0) {
                    app(WalletService::class)->credit($customer, $walletInc, 'Return '.$sale->number, $sale, null, false);
                }
            }

            // --- Reverse loyalty: claw back earned points, return redeemed points ---
            $earnedInc = 0;
            $redeemInc = 0;
            if ($customer) {
                $loyalty = app(LoyaltyService::class);
                if ((int) $sale->points_earned > 0) {
                    $target = (int) round((int) $sale->points_earned * $frac);
                    $earnedInc = max(0, $target - (int) $sale->points_earned_reversed);
                    // Never claw back more points than the customer currently holds.
                    $earnedInc = min($earnedInc, (int) Customer::whereKey($customer->id)->value('loyalty_points'));
                    if ($earnedInc > 0) {
                        $loyalty->adjust($customer, -$earnedInc, 'Reversed points earned — return '.$sale->number);
                    }
                }
                if ((int) $sale->points_redeemed > 0) {
                    $target = (int) round((int) $sale->points_redeemed * $frac);
                    $redeemInc = max(0, $target - (int) $sale->points_redeemed_refunded);
                    if ($redeemInc > 0) {
                        $loyalty->adjust($customer, $redeemInc, 'Returned redeemed points — return '.$sale->number);
                    }
                }
            }

            // Unique-ish reference per return so multiple returns are distinguishable.
            $seq = 1;
            if (class_exists(\App\Models\Accounting\JournalEntry::class)) {
                $seq = \App\Models\Accounting\JournalEntry::where('reference', 'like', $sale->number.'-R%')->count() + 1;
            }
            $ref = $sale->number.'-R'.$seq;

            // Reversing journal — wallet portion restores store-credit liability,
            // the remainder is refunded as cash.
            $this->postRefundJournal($sale, $net, $tax, $total, $cogs, now(), $ref, $walletInc);

            // --- Record the refund as negative payment(s) for drawer/ledger ---
            $cashRefund = max(0, round($total - $walletInc, 2));
            if ($walletInc > 0) {
                Payment::create([
                    'tenant_id' => $this->tenancy->id(), 'sale_id' => $sale->id,
                    'method' => 'wallet', 'amount' => -$walletInc, 'reference' => $ref, 'paid_at' => now(),
                ]);
            }
            if ($cashRefund > 0) {
                Payment::create([
                    'tenant_id' => $this->tenancy->id(), 'sale_id' => $sale->id,
                    'method' => 'cash', 'amount' => -$cashRefund, 'reference' => $ref, 'paid_at' => now(),
                ]);
            }

            $sale->update([
                'status' => $fully ? 'refunded' : 'partially_refunded',
                'refunded_total' => round((float) $sale->refunded_total + $total, 2),
                'wallet_refunded' => round((float) $sale->wallet_refunded + $walletInc, 2),
                'points_earned_reversed' => (int) $sale->points_earned_reversed + $earnedInc,
                'points_redeemed_refunded' => (int) $sale->points_redeemed_refunded + $redeemInc,
            ]);

            return $sale;
        });

        // Returns change realised sales — invalidate the bestsellers cache.
        app(\App\Services\Storefront\BestsellerService::class)->forget($this->tenancy->id());

        return $sale;
    }

    /**
     * Record a follow-up payment against a partially-paid (credit) sale.
     * Settles the A/R balance and flips the sale to 'completed' once cleared.
     *
     * $data = ['amount' => float, 'method' => 'cash'|'card'|'other'|'wallet', 'reference' => ?string]
     */
    public function addPayment(Sale $sale, array $data): Sale
    {
        return DB::transaction(function () use ($sale, $data) {
            $sale->refresh();

            if ($sale->status !== 'partially_paid') {
                throw new \RuntimeException('This sale is not awaiting payment.');
            }

            $balanceDue = round((float) $sale->total - (float) $sale->paid_total, 2);
            $amount = round((float) ($data['amount'] ?? 0), 2);
            $method = $data['method'] ?? 'cash';

            // One payment per method per sale: reject a method already recorded on
            // this sale, regardless of the amount.
            if ($sale->payments()->where('method', $method)->exists()) {
                throw new \RuntimeException(
                    ucfirst($method).' has already been used on this sale. Settle the balance with a different payment method.'
                );
            }

            // Never accept more than the outstanding balance (no change on a settlement).
            $applied = round(min($amount, $balanceDue), 2);
            if ($applied <= 0) {
                throw new \RuntimeException('Enter a payment amount greater than zero.');
            }

            // Wallet settlement draws down the customer's store credit.
            if ($method === 'wallet') {
                $customer = $sale->customer;
                if (! $customer) {
                    throw new \RuntimeException('This sale has no customer wallet to charge.');
                }
                app(WalletService::class)->debit($customer, $applied, 'Payment for sale '.$sale->number, $sale);
            }

            Payment::create([
                'tenant_id' => $this->tenancy->id(),
                'sale_id' => $sale->id,
                'method' => $method,
                'amount' => $applied,
                'reference' => $data['reference'] ?? null,
                'paid_at' => now(),
            ]);

            $newPaid = round((float) $sale->paid_total + $applied, 2);
            $newBalance = round((float) $sale->total - $newPaid, 2);
            if ($newBalance < 0) {
                $newBalance = 0.0;
            }

            $sale->update([
                'paid_total' => $newPaid,
                'balance_due' => $newBalance,
                'status' => $newBalance <= 0.001 ? 'completed' : 'partially_paid',
            ]);

            $this->postSettlementJournal($sale, $applied, $method, now());

            return $sale->refresh();
        });
    }

    /**
     * Apply a single received amount across a customer's open (partially-paid)
     * sales, oldest first, settling each one's balance. Any leftover of real
     * money (not a wallet draw-down) is banked as store credit when
     * $remainderToCredit is set — i.e. an advance on account.
     *
     * Each settled sale records a Payment and posts the same settlement journal
     * as a single sale payment (Dr Cash/Wallet, Cr Accounts Receivable).
     *
     * @return array{applied: float, credited: float, allocations: array<int, array{number:string, applied:float, balance:float}>}
     */
    public function receivePayment(Customer $customer, float $amount, string $method = 'cash', ?string $reference = null, bool $remainderToCredit = true): array
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Enter a payment amount greater than zero.');
        }

        return DB::transaction(function () use ($customer, $amount, $method, $reference, $remainderToCredit) {
            $remaining = $amount;

            // A wallet settlement can't draw more than the customer's store credit.
            if ($method === 'wallet') {
                $balance = round((float) Customer::whereKey($customer->id)->value('balance'), 2);
                $remaining = min($remaining, $balance);
                if ($remaining <= 0) {
                    throw new \RuntimeException('This customer has no store-credit balance to apply.');
                }
            }

            $sales = Sale::query()
                ->where('tenant_id', $this->tenancy->id())
                ->where('customer_id', $customer->id)
                ->where('status', 'partially_paid')
                ->orderBy('completed_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $applied = 0.0;
            $allocations = [];

            foreach ($sales as $sale) {
                if ($remaining <= 0.001) {
                    break;
                }

                $balanceDue = round((float) $sale->total - (float) $sale->paid_total, 2);
                if ($balanceDue <= 0) {
                    continue;
                }

                $pay = round(min($remaining, $balanceDue), 2);
                if ($pay <= 0) {
                    continue;
                }

                // Wallet draws down store credit as it settles each sale.
                if ($method === 'wallet') {
                    app(WalletService::class)->debit($customer, $pay, 'Payment for sale '.$sale->number, $sale);
                }

                Payment::create([
                    'tenant_id' => $this->tenancy->id(),
                    'sale_id' => $sale->id,
                    'method' => $method,
                    'amount' => $pay,
                    'reference' => $reference,
                    'paid_at' => now(),
                ]);

                $newPaid = round((float) $sale->paid_total + $pay, 2);
                $newBalance = max(0.0, round((float) $sale->total - $newPaid, 2));
                $sale->update([
                    'paid_total' => $newPaid,
                    'balance_due' => $newBalance,
                    'status' => $newBalance <= 0.001 ? 'completed' : 'partially_paid',
                ]);

                $this->postSettlementJournal($sale, $pay, $method, now());

                $applied = round($applied + $pay, 2);
                $remaining = round($remaining - $pay, 2);
                $allocations[] = ['number' => $sale->number, 'applied' => $pay, 'balance' => $newBalance];
            }

            // Leftover real money becomes store credit (an advance on account).
            $credited = 0.0;
            if ($remaining > 0.001 && $method !== 'wallet' && $remainderToCredit) {
                app(WalletService::class)->credit($customer, $remaining, 'Advance / overpayment received', null, null, true);
                $credited = round($remaining, 2);
            }

            return ['applied' => $applied, 'credited' => $credited, 'allocations' => $allocations];
        });
    }

    /** Post a credit-sale settlement: Dr Cash/Wallet, Cr Accounts Receivable. */
    protected function postSettlementJournal(Sale $sale, float $amount, string $method, Carbon $date): void
    {
        if (! class_exists(\App\Services\Accounting\PostingService::class)) {
            return;
        }

        $debit = '1000';
        $memo = 'Cash received';
        if ($method === 'wallet') {
            app(WalletService::class)->ensureLiabilityAccount();
            $debit = WalletService::LIABILITY_CODE;
            $memo = 'Store credit applied';
        }

        app(\App\Services\Accounting\PostingService::class)->post([
            'date' => $date,
            'memo' => 'Payment for sale '.$sale->number,
            'reference' => $sale->number.'-P',
            'source' => $sale,
            'lines' => [
                ['account' => $debit, 'debit' => $amount, 'credit' => 0, 'memo' => $memo],
                ['account' => '1200', 'debit' => 0, 'credit' => $amount, 'memo' => 'Receivable settled'],
            ],
        ]);
    }

    /** Generate the next per-tenant sequential sale number (INV-0001 …). */
    protected function nextNumber(): string
    {
        $last = Sale::query()
            ->where('tenant_id', $this->tenancy->id())
            ->orderByDesc('id')
            ->value('number');

        $seq = 0;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = (int) $m[1];
        }

        return 'INV-'.str_pad((string) ($seq + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Names of out-of-stock products among the given lines — tracked products
     * whose on-hand quantity in the selling warehouse is <= 0. Returns [] when
     * stock can't be determined (no Inventory module / warehouse), so the guard
     * stays a no-op in those setups.
     *
     * @param  array<int,array{product_id?:int}>  $items
     * @return array<int,string>
     */
    protected function outOfStock(array $items, ?Warehouse $warehouse): array
    {
        if (! $warehouse) {
            return [];
        }

        $names = [];
        foreach ($items as $row) {
            $product = Product::find($row['product_id'] ?? null);
            if (! $product || ! $product->track_stock) {
                continue;
            }
            if ($product->stockIn($warehouse) <= 0) {
                $names[] = $product->name;
            }
        }

        return $names;
    }

    /** Resolve the warehouse a register sells from, falling back to the default. */
    protected function resolveWarehouse(?Register $register): ?Warehouse
    {
        if (! class_exists(\App\Models\Inventory\Warehouse::class)) {
            return null;
        }

        if ($register && $register->warehouse_id) {
            $warehouse = Warehouse::find($register->warehouse_id);
            if ($warehouse) {
                return $warehouse;
            }
        }

        return Warehouse::default();
    }

    /**
     * Decrement stock for each sold line through the Inventory StockService.
     * Guarded so POS stays decoupled from the Inventory module.
     */
    protected function decrementStock(Sale $sale, array $products, array $lines, ?Warehouse $warehouse): void
    {
        if (! $warehouse
            || ! class_exists(\App\Services\Inventory\StockService::class)) {
            return;
        }

        $stock = app(\App\Services\Inventory\StockService::class);

        foreach ($lines as $line) {
            $product = $products[$line['product_id']] ?? null;
            if (! $product || ! $product->track_stock) {
                continue;
            }

            $stock->recordMovement(
                $product,
                $warehouse,
                'sale',
                -1 * (float) $line['quantity'],
                (float) $product->cost_price,
                $sale,
                'Sale '.$sale->number,
            );
        }
    }

    /**
     * Post the sale's double-entry journal through the Accounting PostingService.
     * Guarded so POS stays decoupled from the Accounting module.
     *
     *   Dr 1000 Cash               = total − wallet used − balance due
     *   Dr 2200 Customer Credit    = wallet used   (store credit drawn down)
     *   Dr 1200 Accounts Receivable= balance due   (unpaid portion of a credit sale)
     *   Cr 4000 Sales Revenue      = net (subtotal - discount)
     *   Cr 2100 Tax Payable        = tax_total
     *   Dr 5000 COGS               = Σ cost
     *   Cr 1300 Inventory          = Σ cost
     */
    protected function postSaleJournal(Sale $sale, float $net, float $tax, float $total, float $cogs, float $walletUsed, float $balanceDue, Carbon $date): void
    {
        if (! class_exists(\App\Services\Accounting\PostingService::class)) {
            return;
        }

        $walletUsed = round(min($walletUsed, $total), 2);
        $balanceDue = round(min(max($balanceDue, 0), $total), 2);
        $cashDebit = round($total - $walletUsed - $balanceDue, 2);

        $lines = [];
        if ($cashDebit > 0) {
            $lines[] = ['account' => '1000', 'debit' => $cashDebit, 'credit' => 0, 'memo' => 'Cash received'];
        }
        if ($walletUsed > 0) {
            // Drawing down the customer-deposits liability funded the rest.
            app(WalletService::class)->ensureLiabilityAccount();
            $lines[] = ['account' => WalletService::LIABILITY_CODE, 'debit' => $walletUsed, 'credit' => 0, 'memo' => 'Store credit redeemed'];
        }
        if ($balanceDue > 0) {
            $lines[] = ['account' => '1200', 'debit' => $balanceDue, 'credit' => 0, 'memo' => 'Amount receivable'];
        }
        $lines[] = ['account' => '4000', 'debit' => 0, 'credit' => $net, 'memo' => 'Sales revenue'];

        if (round($tax, 2) > 0) {
            $lines[] = ['account' => '2100', 'debit' => 0, 'credit' => round($tax, 2), 'memo' => 'Tax payable'];
        }

        if (round($cogs, 2) > 0) {
            $lines[] = ['account' => '5000', 'debit' => round($cogs, 2), 'credit' => 0, 'memo' => 'Cost of goods sold'];
            $lines[] = ['account' => '1300', 'debit' => 0, 'credit' => round($cogs, 2), 'memo' => 'Inventory reduction'];
        }

        app(\App\Services\Accounting\PostingService::class)->post([
            'date' => $date,
            'memo' => 'POS sale '.$sale->number,
            'reference' => $sale->number,
            'source' => $sale,
            'lines' => $lines,
        ]);
    }

    /** Post the reversing journal for a refund/return (mirror of the sale entry). */
    protected function postRefundJournal(Sale $sale, float $net, float $tax, float $total, float $cogs, Carbon $date, ?string $reference = null, float $walletPortion = 0): void
    {
        if (! class_exists(\App\Services\Accounting\PostingService::class)) {
            return;
        }

        // Split the refund: store-credit restores the liability, the rest is cash.
        $walletPortion = round(min(max($walletPortion, 0), $total), 2);
        $cashPortion = round($total - $walletPortion, 2);

        $lines = [
            ['account' => '4000', 'debit' => $net, 'credit' => 0, 'memo' => 'Reverse sales revenue'],
        ];

        if ($cashPortion > 0) {
            $lines[] = ['account' => '1000', 'debit' => 0, 'credit' => $cashPortion, 'memo' => 'Cash refunded'];
        }

        if ($walletPortion > 0) {
            app(WalletService::class)->ensureLiabilityAccount();
            $lines[] = ['account' => WalletService::LIABILITY_CODE, 'debit' => 0, 'credit' => $walletPortion, 'memo' => 'Store credit refunded'];
        }

        if (round($tax, 2) > 0) {
            $lines[] = ['account' => '2100', 'debit' => round($tax, 2), 'credit' => 0, 'memo' => 'Reverse tax payable'];
        }

        if (round($cogs, 2) > 0) {
            $lines[] = ['account' => '1300', 'debit' => round($cogs, 2), 'credit' => 0, 'memo' => 'Inventory returned'];
            $lines[] = ['account' => '5000', 'debit' => 0, 'credit' => round($cogs, 2), 'memo' => 'Reverse COGS'];
        }

        app(\App\Services\Accounting\PostingService::class)->post([
            'date' => $date,
            'memo' => 'POS return '.$sale->number,
            'reference' => $reference ?? $sale->number.'-R',
            'source' => $sale,
            'lines' => $lines,
        ]);
    }
}
