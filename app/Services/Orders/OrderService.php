<?php

namespace App\Services\Orders;

use App\Models\Inventory\Product;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * The online-order lifecycle brain.
 *
 * Owns building Order/OrderItem rows, advancing status, recording payment, and
 * fulfilment — which (mirroring a POS sale) decrements inventory through the
 * Inventory module and posts the matching double-entry journal through the
 * Accounting module. Those cross-module calls are guarded with class_exists so
 * this module compiles and runs even when those modules are absent.
 *
 * Unlike POS, revenue is recognised at FULFILMENT (not at order capture), and an
 * order must be paid in full before it can be fulfilled. Stock is only
 * decremented at fulfilment, so cancelling a pre-fulfilment order reverses
 * nothing.
 */
class OrderService
{
    /** Allowed order statuses in lifecycle order (plus 'cancelled'). */
    public const STATUSES = [
        'pending', 'confirmed', 'preparing', 'ready',
        'dispatched', 'delivered', 'completed', 'cancelled',
    ];

    public function __construct(protected Tenancy $tenancy) {}

    /**
     * Capture a new order inside a single DB transaction.
     *
     * Expected $data shape:
     *   customer_id?      int|null
     *   channel?          string         (defaults to 'online')
     *   fulfillment_type  'delivery'|'pickup'
     *   delivery_fee?     float
     *   contact_name?     string|null
     *   contact_phone?    string|null
     *   address?          string|null
     *   city?             string|null
     *   notes?            string|null
     *   placed_at?        Carbon|string  (defaults to now; lets the seeder backdate)
     *   user_id?          int|null       (defaults to auth user)
     *   items:            [['product_id' => int, 'quantity' => float,
     *                        'unit_price'? => float, 'discount'? => float], ...]
     */
    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $tenantId = $this->tenancy->id();

            // Out-of-stock products cannot be ordered (tracked products at <= 0 on hand).
            $soldOut = $this->outOfStock($data['items'] ?? []);
            if ($soldOut !== []) {
                throw new \RuntimeException(
                    'Out of stock: '.implode(', ', $soldOut).'. Remove '
                    .(count($soldOut) === 1 ? 'it' : 'them').' to place the order.'
                );
            }

            // --- Build line items (with product snapshots) ---
            $lines = [];
            $subtotal = 0.0;         // sum of line gross (before discount, before tax)
            $discountTotal = 0.0;    // sum of line discounts
            $taxTotal = 0.0;

            foreach ($data['items'] ?? [] as $row) {
                $product = Product::find($row['product_id'] ?? null);
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
                $net = round($gross - $discount, 2); // taxable base for this line
                if ($net < 0) {
                    $net = 0.0;
                }
                $lineTax = round($net * $taxRate, 2);
                $lineTotal = round($net + $lineTax, 2);

                $subtotal += $gross;
                $discountTotal += $discount;
                $taxTotal += $lineTax;

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

            $subtotal = round($subtotal, 2);
            $discountTotal = round($discountTotal, 2);
            $taxTotal = round($taxTotal, 2);

            $fulfillmentType = ($data['fulfillment_type'] ?? 'delivery') === 'pickup'
                ? 'pickup'
                : 'delivery';

            // Pickup orders never carry a delivery fee.
            $deliveryFee = $fulfillmentType === 'delivery'
                ? round((float) ($data['delivery_fee'] ?? 0), 2)
                : 0.0;
            if ($deliveryFee < 0) {
                $deliveryFee = 0.0;
            }

            $total = round(($subtotal - $discountTotal) + $taxTotal + $deliveryFee, 2);

            $placedAt = isset($data['placed_at'])
                ? \Illuminate\Support\Carbon::parse($data['placed_at'])
                : now();

            $order = Order::create([
                'tenant_id' => $tenantId,
                'number' => $this->nextNumber(),
                'customer_id' => $data['customer_id'] ?? null,
                'channel' => $data['channel'] ?? 'online',
                'fulfillment_type' => $fulfillmentType,
                'shipping_method' => $data['shipping_method'] ?? null,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'paid_total' => 0,
                'contact_name' => $data['contact_name'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'notes' => $data['notes'] ?? null,
                'user_id' => $data['user_id'] ?? auth()->id(),
                'placed_at' => $placedAt,
            ]);

            foreach ($lines as $line) {
                $line['order_id'] = $order->id;
                OrderItem::create($line);
            }

            return $order->load('items', 'customer');
        });
    }

    /**
     * Advance an order to a new lifecycle status. A completed or cancelled order
     * is terminal and cannot change. No stock/accounting happens here.
     */
    public function updateStatus(Order $order, string $status): Order
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new \RuntimeException('Unknown order status: '.$status.'.');
        }

        if ($order->isCompleted() || $order->isCancelled()) {
            throw new \RuntimeException('A completed or cancelled order cannot change status.');
        }

        $order->update(['status' => $status]);

        return $order;
    }

    /**
     * Mark the order paid in full. No journal is posted here — revenue is
     * recognised at fulfilment. $reference holds a non-sensitive payment note
     * (e.g. "Visa ····4242 · CARD-XYZ" for an online card charge).
     */
    public function markPaid(Order $order, string $method = 'cash', ?string $reference = null): Order
    {
        $order->update([
            'payment_status' => 'paid',
            'payment_method' => $method,
            'payment_reference' => $reference,
            'paid_at' => now(),
            'paid_total' => (float) $order->total,
        ]);

        return $order;
    }

    /**
     * Fulfil a paid order inside a single DB transaction: decrement stock for
     * each tracked line and post the revenue/COGS/delivery journal, then mark
     * the order completed.
     */
    public function fulfill(Order $order): Order
    {
        $order = DB::transaction(function () use ($order) {
            if ($order->isCompleted()) {
                throw new \RuntimeException('This order has already been fulfilled.');
            }
            if ($order->isCancelled()) {
                throw new \RuntimeException('A cancelled order cannot be fulfilled.');
            }
            if (! $order->isPaid()) {
                throw new \RuntimeException('The order must be paid in full before it can be fulfilled.');
            }

            $order->loadMissing('items');

            // --- Cross-module: decrement stock for each tracked line ---
            $this->decrementStock($order);

            // --- Cross-module: post the fulfilment journal ---
            $this->postFulfilmentJournal($order);

            $order->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return $order;
        });

        // A fulfilled order now counts toward bestsellers — invalidate the cache.
        app(\App\Services\Storefront\BestsellerService::class)->forget($this->tenancy->id());

        return $order;
    }

    /**
     * Cancel an order. Only valid before completion; stock was never decremented
     * pre-fulfilment, so there is nothing to reverse.
     */
    public function cancel(Order $order): Order
    {
        if ($order->isCompleted()) {
            throw new \RuntimeException('A completed order cannot be cancelled.');
        }

        $order->update(['status' => 'cancelled']);

        return $order;
    }

    /**
     * Refund a paid order. If it was fulfilled, stock is restored and the
     * fulfilment journal is reversed; an unfulfilled paid order simply has its
     * payment reversed (no journal was posted until fulfilment). Marking the
     * payment 'refunded' also blocks any later fulfilment.
     */
    public function refund(Order $order): Order
    {
        $order = DB::transaction(function () use ($order) {
            if ($order->payment_status === 'refunded') {
                throw new \RuntimeException('This order has already been refunded.');
            }
            if (! $order->isPaid()) {
                throw new \RuntimeException('Only a paid order can be refunded.');
            }

            $order->loadMissing('items');

            // Stock + accounting were only touched at fulfilment.
            if ($order->isCompleted()) {
                $this->restock($order);
                $this->postRefundJournal($order);
            }

            $order->update(['payment_status' => 'refunded', 'refunded_at' => now()]);

            return $order;
        });

        // A refunded order no longer counts toward bestsellers — invalidate.
        app(\App\Services\Storefront\BestsellerService::class)->forget($this->tenancy->id());

        return $order;
    }

    /**
     * Names of out-of-stock products among the given order lines — tracked
     * products whose on-hand quantity in the fulfilment warehouse (the default
     * warehouse, which is where orders draw stock at fulfilment) is <= 0.
     *
     * Public so the checkout can reject sold-out items *before* charging the
     * card. Returns [] when stock can't be determined (no Inventory module /
     * warehouse), keeping the guard a no-op in those setups.
     *
     * @param  array<int,array{product_id?:int}>  $items
     * @return array<int,string>
     */
    public function outOfStock(array $items): array
    {
        if (! class_exists(\App\Models\Inventory\Warehouse::class)) {
            return [];
        }

        $warehouse = \App\Models\Inventory\Warehouse::default();
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

    /** Generate the next per-tenant sequential order number (ORD-0001 …). */
    protected function nextNumber(): string
    {
        $last = Order::query()
            ->where('tenant_id', $this->tenancy->id())
            ->orderByDesc('id')
            ->value('number');

        $seq = 0;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = (int) $m[1];
        }

        return 'ORD-'.str_pad((string) ($seq + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Decrement stock for each fulfilled line through the Inventory StockService.
     * Guarded so Orders stays decoupled from the Inventory module; tracks only
     * products that still exist and have track_stock on.
     */
    protected function decrementStock(Order $order): void
    {
        if (! class_exists(\App\Services\Inventory\StockService::class)
            || ! class_exists(\App\Models\Inventory\Warehouse::class)
            || ! class_exists(Product::class)) {
            return;
        }

        $warehouse = \App\Models\Inventory\Warehouse::default();
        if (! $warehouse) {
            return;
        }

        $stock = app(\App\Services\Inventory\StockService::class);

        foreach ($order->items as $item) {
            if (! $item->product_id) {
                continue;
            }
            $product = Product::find($item->product_id);
            if (! $product || ! $product->track_stock) {
                continue;
            }

            $stock->recordMovement(
                $product,
                $warehouse,
                'sale',
                -1 * (float) $item->quantity,
                (float) $item->unit_cost,
                $order,
                'Order '.$order->number,
            );
        }
    }

    /** Restore stock for each line of a refunded (previously fulfilled) order. */
    protected function restock(Order $order): void
    {
        if (! class_exists(\App\Services\Inventory\StockService::class)
            || ! class_exists(\App\Models\Inventory\Warehouse::class)
            || ! class_exists(Product::class)) {
            return;
        }

        $warehouse = \App\Models\Inventory\Warehouse::default();
        if (! $warehouse) {
            return;
        }

        $stock = app(\App\Services\Inventory\StockService::class);

        foreach ($order->items as $item) {
            if (! $item->product_id) {
                continue;
            }
            $product = Product::find($item->product_id);
            if (! $product || ! $product->track_stock) {
                continue;
            }

            $stock->recordMovement(
                $product,
                $warehouse,
                'return',
                (float) $item->quantity,
                (float) $item->unit_cost,
                $order,
                'Refund '.$order->number,
            );
        }
    }

    /**
     * Post the fulfilment double-entry journal through the Accounting
     * PostingService. Guarded so Orders stays decoupled from the Accounting
     * module. Mirrors a POS sale, adding the delivery-income line.
     *
     *   Dr 1000 Cash             = total
     *   Cr 4000 Sales Revenue    = net (subtotal − discount)
     *   Cr 2100 Tax Payable      = tax (if > 0)
     *   Cr 4200 Delivery Income  = delivery fee (if > 0)
     *   Dr 5000 COGS             = Σ cost (if > 0)
     *   Cr 1300 Inventory        = Σ cost (if > 0)
     *
     * Debits == credits: total + cogs == net + tax + fee + cogs.
     */
    protected function postFulfilmentJournal(Order $order): void
    {
        if (! class_exists(\App\Services\Accounting\PostingService::class)) {
            return;
        }

        $net = round((float) $order->subtotal - (float) $order->discount_total, 2);
        $tax = round((float) $order->tax_total, 2);
        $fee = round((float) $order->delivery_fee, 2);
        $total = round((float) $order->total, 2);
        $cogs = round((float) $order->items->sum(
            fn ($i) => round((float) $i->unit_cost * (float) $i->quantity, 2)
        ), 2);

        $lines = [];
        $lines[] = ['account' => '1000', 'debit' => $total, 'credit' => 0, 'memo' => 'Order payment received'];
        $lines[] = ['account' => '4000', 'debit' => 0, 'credit' => $net, 'memo' => 'Sales revenue'];

        if ($tax > 0) {
            $lines[] = ['account' => '2100', 'debit' => 0, 'credit' => $tax, 'memo' => 'Tax payable'];
        }
        if ($fee > 0) {
            $this->ensureDeliveryIncomeAccount();
            $lines[] = ['account' => '4200', 'debit' => 0, 'credit' => $fee, 'memo' => 'Delivery income'];
        }
        if ($cogs > 0) {
            $lines[] = ['account' => '5000', 'debit' => $cogs, 'credit' => 0, 'memo' => 'Cost of goods sold'];
            $lines[] = ['account' => '1300', 'debit' => 0, 'credit' => $cogs, 'memo' => 'Inventory reduction'];
        }

        app(\App\Services\Accounting\PostingService::class)->post([
            'date' => now(),
            'memo' => 'Order fulfilment '.$order->number,
            'reference' => $order->number,
            'source' => $order,
            'lines' => $lines,
        ]);
    }

    /** Post the reversing journal for an order refund (mirror of fulfilment). */
    protected function postRefundJournal(Order $order): void
    {
        if (! class_exists(\App\Services\Accounting\PostingService::class)) {
            return;
        }

        $net = round((float) $order->subtotal - (float) $order->discount_total, 2);
        $tax = round((float) $order->tax_total, 2);
        $fee = round((float) $order->delivery_fee, 2);
        $total = round((float) $order->total, 2);
        $cogs = round((float) $order->items->sum(
            fn ($i) => round((float) $i->unit_cost * (float) $i->quantity, 2)
        ), 2);

        $lines = [];
        $lines[] = ['account' => '4000', 'debit' => $net, 'credit' => 0, 'memo' => 'Reverse sales revenue'];

        if ($tax > 0) {
            $lines[] = ['account' => '2100', 'debit' => $tax, 'credit' => 0, 'memo' => 'Reverse tax payable'];
        }
        if ($fee > 0) {
            $this->ensureDeliveryIncomeAccount();
            $lines[] = ['account' => '4200', 'debit' => $fee, 'credit' => 0, 'memo' => 'Reverse delivery income'];
        }
        $lines[] = ['account' => '1000', 'debit' => 0, 'credit' => $total, 'memo' => 'Order refund paid'];
        if ($cogs > 0) {
            $lines[] = ['account' => '1300', 'debit' => $cogs, 'credit' => 0, 'memo' => 'Inventory returned'];
            $lines[] = ['account' => '5000', 'debit' => 0, 'credit' => $cogs, 'memo' => 'Reverse COGS'];
        }

        app(\App\Services\Accounting\PostingService::class)->post([
            'date' => now(),
            'memo' => 'Order refund '.$order->number,
            'reference' => $order->number.'-R',
            'source' => $order,
            'lines' => $lines,
        ]);
    }

    /** Make sure the delivery-income account exists for this tenant. */
    public function ensureDeliveryIncomeAccount(): void
    {
        if (! class_exists(\App\Models\Accounting\Account::class)) {
            return;
        }

        \App\Models\Accounting\Account::firstOrCreate(
            ['code' => '4200'],
            [
                'name' => 'Delivery Income',
                'type' => 'income',
                'subtype' => 'operating',
                'is_active' => true,
            ],
        );
    }
}
