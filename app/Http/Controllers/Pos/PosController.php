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
    /** How many products the register grid loads / shows per search. */
    protected const PRODUCT_LIMIT = 60;

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

        // Only the first page of products is embedded; the rest are fetched on
        // demand from the search endpoint so the register isn't shipping an
        // 8k-product catalogue on every load.
        $products = $this->productQuery($warehouse)
            ->orderBy('name')
            ->limit(self::PRODUCT_LIMIT)
            ->get()
            ->map(fn (Product $p) => $this->productRow($p, $warehouse))
            ->values();

        $productTotal = Product::where('is_active', true)->count();

        $customers = Customer::orderBy('name')
            ->get(['id', 'name', 'phone', 'loyalty_no', 'balance', 'loyalty_points'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'loyalty_no' => $c->loyalty_no,
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

        return view('pos.index', compact('register', 'registers', 'openShift', 'products', 'productTotal', 'customers', 'loyalty', 'gridColumns', 'payMethods', 'creditMethods'));
    }

    /**
     * JSON product search for the register grid. Returns up to PRODUCT_LIMIT
     * matches (exact barcode/SKU first), with the total match count so the UI
     * can show "first N of M". Stock is for the requested register's warehouse.
     */
    public function products(Request $request)
    {
        $register = $request->filled('register')
            ? Register::where('is_active', true)->find($request->integer('register'))
            : null;
        $warehouse = $this->warehouseFor($register);

        $term = trim((string) $request->input('q', ''));
        $query = $this->productQuery($warehouse);

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($w) => $w
                ->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('barcode', 'like', $like));
        }

        // Count the full match set before ordering, then surface exact
        // barcode/SKU hits (a scan) first within the returned page.
        $total = (clone $query)->count();
        if ($term !== '') {
            $query->orderByRaw('CASE WHEN barcode = ? OR sku = ? THEN 0 ELSE 1 END', [$term, $term]);
        }

        $products = $query->orderBy('name')->limit(self::PRODUCT_LIMIT)->get()
            ->map(fn (Product $p) => $this->productRow($p, $warehouse))
            ->values();

        return response()->json(['products' => $products, 'total' => $total]);
    }

    /** Base product query for the register, eager-loading the warehouse stock row. */
    protected function productQuery(?Warehouse $warehouse)
    {
        return Product::query()
            ->where('is_active', true)
            ->with('category')
            ->when($warehouse, fn ($q) => $q->with([
                'stocks' => fn ($s) => $s->where('warehouse_id', $warehouse->id),
            ]));
    }

    /** Shape a product for the register grid / cart. */
    protected function productRow(Product $p, ?Warehouse $warehouse): array
    {
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
            'stock' => $warehouse ? (float) ($p->stocks->first()->quantity ?? 0) : null,
        ];
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
            ->with('status', 'Sale '.$sale->number.' completed.')
            ->with('autoprint', true);
    }

    /** Printable receipt for a sale. */
    public function receipt(Sale $sale)
    {
        $this->authorizeTenant($sale);
        $sale->load('items', 'payments', 'customer', 'register', 'user');

        return view('pos.receipt', compact('sale'));
    }

    /** Optionally email a sale receipt to the customer (or a typed address). */
    public function emailReceipt(Request $request, Sale $sale)
    {
        $this->authorizeTenant($sale);

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $email = $data['email'] ?? $sale->customer?->email;
        if (! $email) {
            return back()->with('error', 'No email address for this sale — enter one to send the receipt.');
        }

        try {
            \Illuminate\Support\Facades\Mail::to($email)->send(
                new \App\Mail\SaleReceiptMail($sale->load('items', 'customer'))
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not send the receipt. '.$e->getMessage());
        }

        return back()->with('status', 'Receipt emailed to '.$email.'.');
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
