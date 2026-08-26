<?php

namespace Tests\Feature;

use App\Http\Controllers\Inventory\PurchaseOrderController;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Warehouse;
use App\Models\Tenant;
use App\Support\Tenancy;
use Database\Factories\ProductFactory;
use Database\Factories\TenantFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PurchaseOrderReceiveTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = TenantFactory::new()->create();
        app(Tenancy::class)->set($this->tenant);
    }

    /** Build a simple product with its single default variant. */
    protected function makeProduct(): Product
    {
        $product = ProductFactory::new()->create();
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'sale_price' => $product->sale_price,
            'cost_price' => $product->cost_price,
            'position' => 0,
        ]);

        return $product->fresh('variants');
    }

    protected function makePurchaseOrder(Warehouse $warehouse, Product $product, float $qty): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'warehouse_id' => $warehouse->id,
            'reference' => 'PO-TEST1',
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'total' => 0, // keep the accounting posting out of this stock-focused test
        ]);
        $po->items()->create([
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_cost' => 10,
            'line_total' => $qty * 10,
        ]);

        return $po->fresh('items.product');
    }

    public function test_receiving_a_po_credits_the_default_variant_stock(): void
    {
        $warehouse = Warehouse::default();
        $product = $this->makeProduct();
        $variant = $product->defaultVariant();
        $po = $this->makePurchaseOrder($warehouse, $product, 25);

        app(PurchaseOrderController::class)->receive($po);

        // The received quantity lands on the variant row — the one POS sales
        // decrement and the product-edit stock box reads.
        $this->assertSame(25.0, $variant->fresh()->stockIn($warehouse));

        // And there is no stranded product-level (null-variant) stock row.
        $this->assertSame(
            0,
            ProductStock::where('product_id', $product->id)->whereNull('variant_id')->count(),
            'Received stock must not sit on a null-variant row.',
        );

        $this->assertTrue($po->fresh()->isReceived());
    }

    public function test_reconcile_command_folds_legacy_null_variant_stock_onto_the_variant(): void
    {
        $warehouse = Warehouse::default();
        $product = $this->makeProduct();
        $variant = $product->defaultVariant();

        // Simulate stock stranded on the product-level row by the old receive path.
        ProductStock::create([
            'product_id' => $product->id,
            'variant_id' => null,
            'warehouse_id' => $warehouse->id,
            'quantity' => 40,
            'reorder_level' => 0,
        ]);
        // The variant already has some stock (e.g. set via product edit).
        ProductStock::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'reorder_level' => 0,
        ]);

        Artisan::call('stock:reconcile-null-variants', ['--apply' => true, '--tenant' => $this->tenant->id]);
        app(Tenancy::class)->set($this->tenant); // command clears tenancy in its finally block

        // Merged onto the variant, total on-hand preserved (5 + 40).
        $this->assertSame(45.0, $variant->fresh()->stockIn($warehouse));
        $this->assertSame(
            0,
            ProductStock::where('product_id', $product->id)->whereNull('variant_id')->count(),
        );
    }
}
