<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\StockService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function __construct(protected Tenancy $tenancy, protected StockService $stock) {}

    public function index()
    {
        $stocks = ProductStock::with(['product', 'warehouse'])
            ->whereHas('product')
            ->get()
            ->sortBy(fn ($s) => $s->product?->name)
            ->values();

        $products = Product::where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::orderByDesc('is_default')->orderBy('name')->get();

        return view('inventory.stock.index', compact('stocks', 'products', 'warehouses'));
    }

    public function adjust(Request $request)
    {
        $tenantId = $this->tenancy->id();

        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'quantity' => ['required', 'numeric'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);

        // Book against the default variant — the row POS sales draw down and the
        // product-edit stock box reads/writes — so adjustments don't strand on a
        // product-level (null-variant) row.
        $this->stock->recordMovement(
            $product,
            $warehouse,
            'adjustment',
            (float) $data['quantity'],
            null,
            null,
            $data['note'] ?? null,
            $product->defaultVariant()?->id,
        );

        return redirect()->route('stock.index')->with('status', 'Stock adjusted.');
    }
}
