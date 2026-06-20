<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\StockService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    /** Purchasing/spend summary report (totals, status/supplier/warehouse mix, top items). */
    public function report(Request $request)
    {
        return view('inventory.purchases.report', $this->reportData($request));
    }

    /** Download a PO-report breakdown as CSV (?section=daily|status|suppliers|warehouses|products|all). */
    public function reportExport(Request $request)
    {
        $data = $this->reportData($request);
        $from = $data['from'];
        $to = $data['to'];

        if ($request->input('section') === 'all') {
            return $this->reportExportAll($data);
        }

        $num = fn ($v) => number_format((float) $v, 2, '.', '');
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');

        [$name, $header, $rows] = match ($request->input('section')) {
            'status' => ['po-status', ['Status', 'Orders', 'Value'],
                $data['statusRows']->map(fn ($s) => [$s->status, $s->n, $num($s->total)])],
            'suppliers' => ['suppliers', ['Supplier', 'Orders', 'Value'],
                $data['suppliers']->map(fn ($s) => [$s->label, $s->n, $num($s->total)])],
            'warehouses' => ['warehouses', ['Warehouse', 'Orders', 'Value'],
                $data['warehouses']->map(fn ($w) => [$w->label, $w->n, $num($w->total)])],
            'products' => ['top-items', ['Product', 'Qty', 'Cost'],
                $data['top']->map(fn ($p) => [$p->name, $qty($p->qty), $num($p->cost)])],
            default => ['purchases', ['Date', 'Orders', 'Value'],
                $data['daily']->map(fn ($d) => [$d->d, $d->n, $num($d->total)])],
        };

        $filename = $name.'-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** One CSV holding every section of the purchase-orders report. */
    protected function reportExportAll(array $data)
    {
        $from = $data['from'];
        $to = $data['to'];
        $s = $data['summary'];
        $num = fn ($v) => number_format((float) $v, 2, '.', '');
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');

        $filename = 'purchases-report-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($data, $s, $from, $to, $num, $qty) {
            $out = fopen('php://output', 'w');
            $put = fn ($row) => fputcsv($out, $row, ',', '"', '');
            $section = function (string $title, array $header, $rows) use ($put) {
                $put([$title]);
                $put($header);
                foreach ($rows as $row) {
                    $put($row);
                }
                $put([]);
            };

            $put(['Purchase orders report', $from->toDateString().' to '.$to->toDateString()]);
            $put([]);
            $section('Summary', ['Metric', 'Value'], [
                ['Purchase orders', $s['count']],
                ['Average order', $num($s['avg'])],
                ['Purchase value', $num($s['total'])],
                ['Received (count)', $s['received_count']],
                ['Received value', $num($s['received_total'])],
                ['Pending (count)', $s['pending_count']],
                ['Pending value', $num($s['pending_total'])],
            ]);
            $section('Status', ['Status', 'Orders', 'Value'],
                $data['statusRows']->map(fn ($x) => [$x->status, $x->n, $num($x->total)]));
            $section('Suppliers', ['Supplier', 'Orders', 'Value'],
                $data['suppliers']->map(fn ($x) => [$x->label, $x->n, $num($x->total)]));
            $section('Warehouses', ['Warehouse', 'Orders', 'Value'],
                $data['warehouses']->map(fn ($x) => [$x->label, $x->n, $num($x->total)]));
            $section('Top items', ['Product', 'Qty', 'Cost'],
                $data['top']->map(fn ($x) => [$x->name, $qty($x->qty), $num($x->cost)]));
            $section('Daily breakdown', ['Date', 'Orders', 'Value'],
                $data['daily']->map(fn ($x) => [$x->d, $x->n, $num($x->total)]));

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Build the purchase-orders report for the request's date range (bucketed by order_date). */
    protected function reportData(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();
        $fromD = $from->toDateString();
        $toD = $to->toDateString();

        // Basis: 'order' buckets every PO by when it was ordered (committed spend);
        // 'received' buckets only received POs by when they landed in stock.
        $basis = $request->input('basis') === 'received' ? 'received' : 'order';
        $dateCol = $basis === 'received' ? 'received_at' : 'order_date';

        $scope = function ($q) use ($basis, $dateCol, $fromD, $toD) {
            if ($basis === 'received') {
                $q->where('purchase_orders.status', 'received')->whereNotNull('purchase_orders.received_at');
            }

            return $q->whereBetween('purchase_orders.'.$dateCol, [$fromD, $toD]);
        };
        $inRange = fn () => $scope(PurchaseOrder::query());

        $t = $inRange()->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')->first();
        $rec = $inRange()->where('purchase_orders.status', 'received')->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')->first();

        $summary = [
            'count' => (int) $t->count,
            'total' => round((float) $t->total, 2),
            'received_count' => (int) $rec->count,
            'received_total' => round((float) $rec->total, 2),
            'pending_count' => (int) $t->count - (int) $rec->count,
            'pending_total' => round((float) $t->total - (float) $rec->total, 2),
            'avg' => (int) $t->count > 0 ? round((float) $t->total / (int) $t->count, 2) : 0.0,
        ];

        $statusRows = $inRange()->selectRaw('status, COUNT(*) as n, COALESCE(SUM(total), 0) as total')
            ->groupBy('status')->orderByDesc('n')->get();

        $suppliers = $inRange()
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->selectRaw("COALESCE(suppliers.name, 'No supplier') as label, COUNT(*) as n, COALESCE(SUM(purchase_orders.total), 0) as total")
            ->groupBy('label')->orderByDesc('total')->get();

        $warehouses = $inRange()
            ->leftJoin('warehouses', 'warehouses.id', '=', 'purchase_orders.warehouse_id')
            ->selectRaw("COALESCE(warehouses.name, 'Unassigned') as label, COUNT(*) as n, COALESCE(SUM(purchase_orders.total), 0) as total")
            ->groupBy('label')->orderByDesc('total')->get();

        $top = PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', fn ($q) => $scope($q))
            ->join('products', 'products.id', '=', 'purchase_order_items.product_id')
            ->selectRaw('purchase_order_items.product_id, products.name as name,
                COALESCE(SUM(purchase_order_items.quantity), 0) as qty,
                COALESCE(SUM(purchase_order_items.line_total), 0) as cost')
            ->groupBy('purchase_order_items.product_id', 'products.name')
            ->orderByDesc('cost')->limit(10)->get();

        $daily = $inRange()->selectRaw("purchase_orders.{$dateCol} as d, COUNT(*) as n, COALESCE(SUM(total), 0) as total")
            ->groupBy("purchase_orders.{$dateCol}")->orderBy("purchase_orders.{$dateCol}")->get();

        return compact('summary', 'statusRows', 'suppliers', 'warehouses', 'top', 'daily', 'from', 'to', 'basis');
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
