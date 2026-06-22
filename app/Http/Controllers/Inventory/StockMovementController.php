<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Category;
use App\Models\Inventory\StockMovement;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Product movements report: every stock change (in / out / sale / purchase /
 * return / adjustment / import) over a chosen period, with summary totals, a
 * by-type and by-product breakdown, and a paginated movement ledger.
 */
class StockMovementController extends Controller
{
    /** Movement types we know how to label, in display order. */
    public const TYPE_LABELS = [
        'in' => 'Stock in',
        'purchase' => 'Purchase received',
        'return' => 'Return',
        'import' => 'Import',
        'adjustment' => 'Adjustment',
        'sale' => 'Sale',
        'out' => 'Stock out',
    ];

    public function __construct(protected Tenancy $tenancy) {}

    public function report(Request $request)
    {
        $data = $this->reportData($request);
        $data['categories'] = Category::orderBy('name')->get(['id', 'name']);

        return view('inventory.movements.report', $data);
    }

    /** Download a section of the movements report as CSV (?section=detailed|byproduct|bytype|all). */
    public function reportExport(Request $request)
    {
        $data = $this->reportData($request, paginate: false);
        $from = $data['from'];
        $to = $data['to'];

        $num = fn ($v) => number_format((float) $v, 2, '.', '');
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');

        if ($request->input('section') === 'all') {
            return $this->reportExportAll($data, $num, $qty);
        }

        [$name, $header, $rows] = match ($request->input('section')) {
            'byproduct' => ['movements-by-product', ['Product', 'SKU', 'Movements', 'In', 'Out', 'Net', 'Net value'],
                $data['byProduct']->map(fn ($p) => [
                    $p->name, $p->sku, $p->movements, $qty($p->qty_in), $qty($p->qty_out), $qty($p->net), $num($p->net_value),
                ])],
            'bytype' => ['movements-by-type', ['Type', 'Movements', 'In', 'Out', 'Net', 'Value'],
                $data['byType']->map(fn ($t) => [
                    $t->label, $t->movements, $qty($t->qty_in), $qty($t->qty_out), $qty($t->net), $num($t->value),
                ])],
            default => ['stock-movements', ['Date', 'Product', 'SKU', 'Type', 'In', 'Out', 'Unit cost', 'Value', 'Reference', 'User', 'Note'],
                $data['rows']->map(fn ($m) => [
                    Carbon::parse($m->created_at)->format('Y-m-d H:i'),
                    $m->product_name, $m->sku, self::TYPE_LABELS[$m->type] ?? ucfirst($m->type),
                    (float) $m->quantity > 0 ? $qty($m->quantity) : '',
                    (float) $m->quantity < 0 ? $qty(-$m->quantity) : '',
                    $num($m->unit_cost), $num(abs((float) $m->quantity) * (float) $m->unit_cost),
                    $this->referenceLabel($m), $m->user_name ?? '', $m->note,
                ])],
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

    /** One CSV holding every section of the movements report. */
    protected function reportExportAll(array $data, $num, $qty)
    {
        $from = $data['from'];
        $to = $data['to'];
        $s = $data['summary'];
        $filename = 'product-movements-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

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

            $put(['Product movements report', $from->toDateString().' to '.$to->toDateString()]);
            $put([]);
            $section('Summary', ['Metric', 'Value'], [
                ['Movements', $s->movements],
                ['Products moved', $s->products],
                ['Quantity in', $qty($s->qty_in)],
                ['Quantity out', $qty($s->qty_out)],
                ['Net change', $qty($s->net)],
                ['Value in', $num($s->in_value)],
                ['Value out', $num($s->out_value)],
            ]);
            $section('By type', ['Type', 'Movements', 'In', 'Out', 'Net', 'Value'],
                $data['byType']->map(fn ($t) => [$t->label, $t->movements, $qty($t->qty_in), $qty($t->qty_out), $qty($t->net), $num($t->value)]));
            $section('By product', ['Product', 'SKU', 'Movements', 'In', 'Out', 'Net', 'Net value'],
                $data['byProduct']->map(fn ($p) => [$p->name, $p->sku, $p->movements, $qty($p->qty_in), $qty($p->qty_out), $qty($p->net), $num($p->net_value)]));
            $section('Movement log', ['Date', 'Product', 'SKU', 'Type', 'In', 'Out', 'Unit cost', 'Value', 'Reference', 'User', 'Note'],
                $data['rows']->map(fn ($m) => [
                    Carbon::parse($m->created_at)->format('Y-m-d H:i'), $m->product_name, $m->sku,
                    self::TYPE_LABELS[$m->type] ?? ucfirst($m->type),
                    (float) $m->quantity > 0 ? $qty($m->quantity) : '',
                    (float) $m->quantity < 0 ? $qty(-$m->quantity) : '',
                    $num($m->unit_cost), $num(abs((float) $m->quantity) * (float) $m->unit_cost),
                    $this->referenceLabel($m), $m->user_name ?? '', $m->note,
                ]));

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Friendly "Order #12" style label for a movement's polymorphic reference. */
    protected function referenceLabel($m): string
    {
        if (! $m->reference_type) {
            return '';
        }
        $base = class_basename($m->reference_type);

        return $m->reference_id ? $base.' #'.$m->reference_id : $base;
    }

    /**
     * Build the movements report for the request's date range, filterable by
     * category, movement type and product search.
     */
    protected function reportData(Request $request, bool $paginate = true): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->subDays(30)->startOfDay();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        $filters = [
            'category' => $request->input('category'),
            'type' => $request->input('type'),
            'q' => trim((string) $request->input('q')),
        ];

        // A fresh, filtered movements query each time (movements joined to their product).
        $base = function () use ($from, $to, $filters) {
            $q = StockMovement::query()
                ->join('products', 'products.id', '=', 'stock_movements.product_id')
                ->whereBetween('stock_movements.created_at', [$from, $to]);

            if ($filters['category'] !== null && $filters['category'] !== '') {
                $q->where('products.category_id', (int) $filters['category']);
            }
            if ($filters['type'] !== null && $filters['type'] !== '') {
                $q->where('stock_movements.type', $filters['type']);
            }
            if ($filters['q'] !== '') {
                $term = '%'.$filters['q'].'%';
                $q->where(fn ($w) => $w->where('products.name', 'like', $term)
                    ->orWhere('products.sku', 'like', $term)
                    ->orWhere('products.barcode', 'like', $term));
            }

            return $q;
        };

        $inExpr = 'CASE WHEN stock_movements.quantity > 0 THEN stock_movements.quantity ELSE 0 END';
        $outExpr = 'CASE WHEN stock_movements.quantity < 0 THEN -stock_movements.quantity ELSE 0 END';
        $inValExpr = 'CASE WHEN stock_movements.quantity > 0 THEN stock_movements.quantity * stock_movements.unit_cost ELSE 0 END';
        $outValExpr = 'CASE WHEN stock_movements.quantity < 0 THEN -stock_movements.quantity * stock_movements.unit_cost ELSE 0 END';

        $summary = $base()->selectRaw("
            COUNT(*) as movements,
            COUNT(DISTINCT stock_movements.product_id) as products,
            COALESCE(SUM($inExpr), 0) as qty_in,
            COALESCE(SUM($outExpr), 0) as qty_out,
            COALESCE(SUM(stock_movements.quantity), 0) as net,
            COALESCE(SUM($inValExpr), 0) as in_value,
            COALESCE(SUM($outValExpr), 0) as out_value
        ")->first();

        // By movement type.
        $byType = $base()->selectRaw("
            stock_movements.type as type,
            COUNT(*) as movements,
            COALESCE(SUM($inExpr), 0) as qty_in,
            COALESCE(SUM($outExpr), 0) as qty_out,
            COALESCE(SUM(stock_movements.quantity), 0) as net,
            COALESCE(SUM(ABS(stock_movements.quantity) * stock_movements.unit_cost), 0) as value
        ")->groupBy('stock_movements.type')->orderByDesc('movements')->get()
            ->map(fn ($t) => (object) [
                'type' => $t->type,
                'label' => self::TYPE_LABELS[$t->type] ?? ucfirst((string) $t->type),
                'movements' => (int) $t->movements,
                'qty_in' => (float) $t->qty_in, 'qty_out' => (float) $t->qty_out,
                'net' => (float) $t->net, 'value' => (float) $t->value,
            ]);

        // By product — biggest movers first (by movement count, then absolute net).
        $byProduct = $base()->selectRaw("
            stock_movements.product_id as product_id,
            products.name as name, products.sku as sku,
            COUNT(*) as movements,
            COALESCE(SUM($inExpr), 0) as qty_in,
            COALESCE(SUM($outExpr), 0) as qty_out,
            COALESCE(SUM(stock_movements.quantity), 0) as net,
            COALESCE(SUM(stock_movements.quantity * stock_movements.unit_cost), 0) as net_value
        ")->groupBy('stock_movements.product_id', 'products.name', 'products.sku')
            ->orderByDesc('movements')->orderByDesc(DB::raw('ABS(SUM(stock_movements.quantity))'))
            ->get();

        // Detailed movement ledger.
        $rows = $base()
            ->leftJoin('users', 'users.id', '=', 'stock_movements.user_id')
            ->select(
                'stock_movements.id', 'stock_movements.created_at', 'stock_movements.type',
                'stock_movements.quantity', 'stock_movements.unit_cost', 'stock_movements.note',
                'stock_movements.reference_type', 'stock_movements.reference_id',
                'products.name as product_name', 'products.sku as sku', 'users.name as user_name',
            )
            ->orderByDesc('stock_movements.created_at')->orderByDesc('stock_movements.id');

        $rows = $paginate ? $rows->paginate(40)->withQueryString() : $rows->get();

        $types = self::TYPE_LABELS;

        return compact('summary', 'byType', 'byProduct', 'rows', 'from', 'to', 'filters', 'types');
    }
}
