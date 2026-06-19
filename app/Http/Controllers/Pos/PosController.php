<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\Customer;
use App\Models\Pos\Register;
use App\Models\Pos\Sale;
use App\Services\Pos\SaleService;
use App\Support\Tenancy;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    /** The register / checkout screen. */
    public function index(Request $request)
    {
        $registers = Register::where('is_active', true)->orderBy('name')->get();

        // Pick the requested register, else the first with an open shift, else first active.
        $register = null;
        if ($request->filled('register')) {
            $register = $registers->firstWhere('id', (int) $request->integer('register'));
        }
        $register ??= $registers->first();

        $openShift = $register?->openShift();

        $warehouse = $this->warehouseFor($register);

        $products = Product::query()
            ->where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->get()
            ->map(function (Product $p) use ($warehouse) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'barcode' => $p->barcode,
                    'price' => (float) $p->sale_price,
                    // Stored as a percent (e.g. 8.0); expose as a fraction for the cart math.
                    'tax_rate' => (float) $p->tax_rate / 100,
                    'category' => $p->category?->name,
                    'image' => $p->image_path ? asset('storage/'.$p->image_path) : null,
                    'track_stock' => (bool) $p->track_stock,
                    'stock' => $warehouse && method_exists($p, 'stockIn') ? $p->stockIn($warehouse) : null,
                ];
            })
            ->values();

        $customers = Customer::orderBy('name')
            ->get(['id', 'name', 'phone', 'balance', 'loyalty_points'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'balance' => (float) $c->balance,
                'points' => (int) $c->loyalty_points,
            ])
            ->values();

        $loyalty = \App\Models\Pos\LoyaltySetting::current();

        // How many product columns the register grid shows (configurable).
        $gridColumns = max(2, min(8, (int) $this->tenancy->current()->setting('pos.grid_columns', 4)));

        // Configurable tender options (and which of them are "credit" — owed, not paid).
        $payMethods = $this->tenancy->current()->paymentMethods();
        $creditMethods = $this->tenancy->current()->creditPaymentMethodKeys();

        return view('pos.index', compact('register', 'registers', 'openShift', 'products', 'customers', 'loyalty', 'gridColumns', 'payMethods', 'creditMethods'));
    }

    /** Process a checkout and redirect to the printable receipt. */
    public function checkout(Request $request, SaleService $sales)
    {
        $data = $request->validate([
            'register_id' => ['nullable', 'integer'],
            'shift_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'points_redeemed' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            // 'distinct' guards against the same method being submitted twice.
            'payments.*.method' => ['required', 'string', \Illuminate\Validation\Rule::in($this->allowedPaymentMethods()), 'distinct'],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
        ], [
            'payments.*.method.distinct' => 'Each payment method can only be used once.',
        ]);

        try {
            $sale = $sales->complete($data);
        } catch (\RuntimeException $e) {
            // e.g. insufficient payment or insufficient wallet balance.
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pos.receipt', $sale)
            ->with('status', 'Sale '.$sale->number.' completed.');
    }

    /** Printable receipt for a sale. */
    public function receipt(Sale $sale)
    {
        $this->authorizeTenant($sale);
        $sale->load('items', 'payments', 'customer', 'register', 'user');

        return view('pos.receipt', compact('sale'));
    }

    /** Valid payment method keys for checkout: the configured tenders plus wallet. */
    protected function allowedPaymentMethods(): array
    {
        return array_merge($this->tenancy->current()->paymentMethodKeys(), ['wallet']);
    }

    protected function warehouseFor(?Register $register): ?Warehouse
    {
        if (! class_exists(Warehouse::class)) {
            return null;
        }
        if ($register && $register->warehouse_id) {
            return Warehouse::find($register->warehouse_id) ?? Warehouse::default();
        }

        return Warehouse::default();
    }

    protected function authorizeTenant(Sale $sale): void
    {
        abort_unless($sale->tenant_id === $this->tenancy->id(), 404);
    }
}
