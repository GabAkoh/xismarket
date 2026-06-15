<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\StockService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    public function __construct(protected Tenancy $tenancy, protected StockService $stock) {}

    public function index()
    {
        $orders = PurchaseOrder::with(['supplier', 'warehouse'])
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('inventory.purchases.index', compact('orders'));
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('name')->get();
        $warehouses = Warehouse::orderByDesc('is_default')->orderBy('name')->get();
        $products = Product::orderBy('name')->get();

        return view('inventory.purchases.create', compact('suppliers', 'warehouses', 'products'));
    }

    public function store(Request $request)
    {
        $tenantId = $this->tenancy->id();

        $data = $request->validate([
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'order_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data) {
            $order = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'reference' => 'PO-'.strtoupper(Str::random(8)),
                'status' => 'draft',
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'note' => $data['note'] ?? null,
                'user_id' => auth()->id(),
            ]);

            $total = 0;
            foreach ($data['items'] as $item) {
                $lineTotal = round($item['quantity'] * $item['unit_cost'], 2);
                $total += $lineTotal;

                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'line_total' => $lineTotal,
                ]);
            }

            $order->update(['total' => $total]);
        });

        return redirect()->route('purchases.index')->with('status', 'Purchase order created.');
    }

    public function show(PurchaseOrder $purchase)
    {
        $this->authorizeTenant($purchase);
        $purchase->load(['supplier', 'warehouse', 'items.product']);

        return view('inventory.purchases.show', compact('purchase'));
    }

    public function receive(PurchaseOrder $purchase)
    {
        $this->authorizeTenant($purchase);

        if ($purchase->isReceived()) {
            return back()->with('error', 'This purchase order has already been received.');
        }

        $warehouse = $purchase->warehouse ?? Warehouse::default();

        DB::transaction(function () use ($purchase, $warehouse) {
            $purchase->load('items.product');

            foreach ($purchase->items as $item) {
                if (! $item->product) {
                    continue;
                }

                $this->stock->recordMovement(
                    $item->product,
                    $warehouse,
                    'purchase',
                    (float) $item->quantity,
                    (float) $item->unit_cost,
                    $purchase,
                    'Received '.$purchase->reference,
                );
            }

            $purchase->update([
                'status' => 'received',
                'received_at' => now()->toDateString(),
            ]);
        });

        return redirect()->route('purchases.show', $purchase)->with('status', 'Purchase order received and stock updated.');
    }

    protected function authorizeTenant(PurchaseOrder $purchase): void
    {
        abort_unless($purchase->tenant_id === $this->tenancy->id(), 404);
    }
}
