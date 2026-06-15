<?php

namespace Database\Seeders\Demo;

use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\StockService;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = app(Tenancy::class)->id();
        $stock = app(StockService::class);

        // Default warehouse.
        $warehouse = Warehouse::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'MAIN'],
            ['name' => 'Main Warehouse', 'is_default' => true, 'address' => '123 Market Street'],
        );

        // Categories.
        $categories = [];
        foreach (['Beverages', 'Snacks', 'Household', 'Personal Care'] as $name) {
            $categories[$name] = Category::firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => Str::slug($name)],
                ['name' => $name],
            );
        }

        // Suppliers.
        $suppliers = [];
        $supplierData = [
            ['name' => 'Global Distributors', 'email' => 'sales@globaldist.test', 'phone' => '+1-555-0100', 'address' => '500 Trade Ave'],
            ['name' => 'FreshLine Foods', 'email' => 'orders@freshline.test', 'phone' => '+1-555-0200', 'address' => '88 Orchard Rd'],
            ['name' => 'HomeGoods Supply', 'email' => 'hello@homegoods.test', 'phone' => '+1-555-0300', 'address' => '12 Industrial Park'],
        ];
        foreach ($supplierData as $data) {
            $suppliers[] = Supplier::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $data['name']],
                $data,
            );
        }

        // Products: [name, sku, category, cost, sale, tax_rate, initial_qty]
        $productData = [
            ['Cola 330ml', 'BEV-001', 'Beverages', 0.40, 1.20, 8.0, 240],
            ['Sparkling Water 500ml', 'BEV-002', 'Beverages', 0.30, 0.90, 8.0, 180],
            ['Orange Juice 1L', 'BEV-003', 'Beverages', 0.95, 2.50, 8.0, 96],
            ['Coffee Beans 250g', 'BEV-004', 'Beverages', 2.80, 6.50, 8.0, 60],
            ['Potato Chips 150g', 'SNK-001', 'Snacks', 0.65, 1.80, 8.0, 150],
            ['Chocolate Bar 100g', 'SNK-002', 'Snacks', 0.50, 1.50, 8.0, 200],
            ['Mixed Nuts 200g', 'SNK-003', 'Snacks', 1.40, 3.80, 8.0, 80],
            ['Granola Bar 6-pack', 'SNK-004', 'Snacks', 1.20, 3.20, 8.0, 70],
            ['Dish Soap 500ml', 'HOM-001', 'Household', 0.90, 2.40, 12.0, 120],
            ['Paper Towels 4-roll', 'HOM-002', 'Household', 1.60, 3.90, 12.0, 90],
            ['Trash Bags 30ct', 'HOM-003', 'Household', 1.10, 2.80, 12.0, 110],
            ['Laundry Detergent 1L', 'HOM-004', 'Household', 2.50, 5.90, 12.0, 55],
            ['Toothpaste 100ml', 'PCR-001', 'Personal Care', 0.85, 2.20, 12.0, 130],
            ['Shampoo 400ml', 'PCR-002', 'Personal Care', 1.70, 4.30, 12.0, 75],
            ['Hand Soap 250ml', 'PCR-003', 'Personal Care', 0.70, 1.90, 12.0, 140],
        ];

        $products = [];
        foreach ($productData as [$name, $sku, $cat, $cost, $sale, $tax, $qty]) {
            $product = Product::firstOrCreate(
                ['tenant_id' => $tenantId, 'sku' => $sku],
                [
                    'category_id' => $categories[$cat]->id,
                    'name' => $name,
                    'cost_price' => $cost,
                    'sale_price' => $sale,
                    'tax_rate' => $tax,
                    'track_stock' => true,
                    'is_active' => true,
                ],
            );

            $products[] = $product;

            // Seed initial stock only if the product has no stock yet.
            if ($product->stocks()->where('warehouse_id', $warehouse->id)->doesntExist()) {
                $stock->recordMovement($product, $warehouse, 'in', (float) $qty, $cost, null, 'Opening stock');
            }
        }

        // A couple of purchase orders.
        $this->seedPurchaseOrder($tenantId, 'PO-DEMO0001', $suppliers[0], $warehouse, [
            [$products[0], 120, 0.40],
            [$products[1], 96, 0.30],
            [$products[4], 100, 0.65],
        ]);

        $this->seedPurchaseOrder($tenantId, 'PO-DEMO0002', $suppliers[2], $warehouse, [
            [$products[8], 60, 0.90],
            [$products[9], 48, 1.60],
        ]);
    }

    /** Create a draft purchase order with line items (idempotent by reference). */
    protected function seedPurchaseOrder(int $tenantId, string $reference, Supplier $supplier, Warehouse $warehouse, array $lines): void
    {
        if (PurchaseOrder::where('tenant_id', $tenantId)->where('reference', $reference)->exists()) {
            return;
        }

        $order = PurchaseOrder::create([
            'tenant_id' => $tenantId,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'reference' => $reference,
            'status' => 'draft',
            'order_date' => now()->subDays(3)->toDateString(),
        ]);

        $total = 0;
        foreach ($lines as [$product, $qty, $cost]) {
            $lineTotal = round($qty * $cost, 2);
            $total += $lineTotal;

            $order->items()->create([
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_cost' => $cost,
                'line_total' => $lineTotal,
            ]);
        }

        $order->update(['total' => $total]);
    }
}
