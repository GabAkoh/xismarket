<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stock valuation report: the current worth of on-hand inventory, valued at
 * cost (and retail), with a per-category breakdown and a per-product detail
 * list. Filterable by category, status, stock state and product search.
 */
class StockValuationController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function report(Request $request)
    {
        $data = $this->reportData($request);
        $data['categories'] = Category::orderBy('name')->get(['id', 'name']);

        return view('inventory.valuation.report', $data);
    }

    /** Download a section of the valuation report as CSV (?section=detailed|category|all). */
    public function reportExport(Request $request)
    {
        $data = $this->reportData($request, paginate: false);
        $num = fn ($v) => number_format((float) $v, 2, '.', '');
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
        $pct = fn ($v) => number_format((float) $v, 1, '.', '');

        if ($request->input('section') === 'all') {
            return $this->reportExportAll($data, $num, $qty, $pct);
        }

        [$name, $header, $rows] = match ($request->input('section')) {
            'category' => ['valuation-by-category', ['Category', 'Products', 'Units', 'Cost value', 'Retail value', 'Potential profit', 'Margin %'],
                $data['byCategory']->map(fn ($c) => [
                    $c->name, $c->products, $qty($c->units), $num($c->cost_value), $num($c->retail_value),
                    $num($c->retail_value - $c->cost_value), $pct($c->margin),
                ])],
            default => ['stock-valuation', ['Product', 'SKU', 'Category', 'On hand', 'Unit cost', 'Cost value', 'Unit price', 'Retail value', 'Margin %'],
                $data['rows']->map(fn ($p) => [
                    $p->name, $p->sku, $p->category?->name ?? '', $qty($p->on_hand),
                    $num($p->cost_price), $num($p->cost_value), $num($p->sale_price), $num($p->retail_value),
                    $pct($p->retail_value > 0 ? ($p->retail_value - $p->cost_value) / $p->retail_value * 100 : 0),
                ])],
        };

        $filename = $name.'-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** One CSV holding every section of the valuation report. */
    protected function reportExportAll(array $data, $num, $qty, $pct)
    {
        $s = $data['summary'];
        $filename = 'stock-valuation-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($data, $s, $num, $qty, $pct) {
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

            $put(['Stock valuation report', 'As of '.now()->toDateString()]);
            $put([]);
            $section('Summary', ['Metric', 'Value'], [
                ['Products', $s->products],
                ['Units on hand', $qty($s->units)],
                ['Cost value', $num($s->cost_value)],
                ['Retail value', $num($s->retail_value)],
                ['Potential profit', $num($s->retail_value - $s->cost_value)],
                ['Margin %', $pct($s->retail_value > 0 ? ($s->retail_value - $s->cost_value) / $s->retail_value * 100 : 0)],
            ]);
            $section('By category', ['Category', 'Products', 'Units', 'Cost value', 'Retail value', 'Potential profit', 'Margin %'],
                $data['byCategory']->map(fn ($c) => [
                    $c->name, $c->products, $qty($c->units), $num($c->cost_value), $num($c->retail_value),
                    $num($c->retail_value - $c->cost_value), $pct($c->margin),
                ]));
            $section('By product', ['Product', 'SKU', 'Category', 'On hand', 'Unit cost', 'Cost value', 'Unit price', 'Retail value', 'Margin %'],
                $data['rows']->map(fn ($p) => [
                    $p->name, $p->sku, $p->category?->name ?? '', $qty($p->on_hand),
                    $num($p->cost_price), $num($p->cost_value), $num($p->sale_price), $num($p->retail_value),
                    $pct($p->retail_value > 0 ? ($p->retail_value - $p->cost_value) / $p->retail_value * 100 : 0),
                ]));

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Build the valuation report: products joined to their summed on-hand
     * quantity, valued at cost and retail. Filterable by category, status,
     * stock state and search term.
     */
    protected function reportData(Request $request, bool $paginate = true): array
    {
        $filters = [
            'category' => $request->input('category'),
            'status' => $request->input('status'),
            // Default to in-stock items — that's what "what is my stock worth" means.
            'stock' => $request->has('stock') ? $request->input('stock') : 'in',
            'q' => trim((string) $request->input('q')),
        ];

        $stockSub = DB::table('product_stocks')
            ->select('product_id', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(reorder_level) as reorder'))
            ->groupBy('product_id');

        $base = function () use ($filters, $stockSub) {
            $q = Product::query()
                ->leftJoinSub($stockSub, 'ps', 'ps.product_id', '=', 'products.id');

            if ($filters['category'] !== null && $filters['category'] !== '') {
                $q->where('products.category_id', (int) $filters['category']);
            }
            if ($filters['status'] === 'active') {
                $q->where('products.is_active', 1);
            } elseif ($filters['status'] === 'inactive') {
                $q->where('products.is_active', 0);
            }
            match ($filters['stock']) {
                'out' => $q->whereRaw('COALESCE(ps.qty, 0) <= 0'),
                'reorder' => $q->whereRaw('COALESCE(ps.reorder, 0) > 0 AND COALESCE(ps.qty, 0) <= COALESCE(ps.reorder, 0)'),
                'in' => $q->whereRaw('COALESCE(ps.qty, 0) > 0'),
                default => null, // 'any'
            };
            if ($filters['q'] !== '') {
                $term = '%'.$filters['q'].'%';
                $q->where(fn ($w) => $w->where('products.name', 'like', $term)
                    ->orWhere('products.sku', 'like', $term)
                    ->orWhere('products.barcode', 'like', $term));
            }

            return $q;
        };

        $summary = $base()->selectRaw('
            COUNT(*) as products,
            COALESCE(SUM(COALESCE(ps.qty, 0)), 0) as units,
            COALESCE(SUM(COALESCE(ps.qty, 0) * products.cost_price), 0) as cost_value,
            COALESCE(SUM(COALESCE(ps.qty, 0) * products.sale_price), 0) as retail_value
        ')->first();

        $byCategory = $base()
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw('
                COALESCE(categories.name, "Uncategorised") as name,
                COUNT(*) as products,
                COALESCE(SUM(COALESCE(ps.qty, 0)), 0) as units,
                COALESCE(SUM(COALESCE(ps.qty, 0) * products.cost_price), 0) as cost_value,
                COALESCE(SUM(COALESCE(ps.qty, 0) * products.sale_price), 0) as retail_value
            ')
            ->groupBy('name')->orderByDesc('cost_value')->get()
            ->map(fn ($c) => (object) [
                'name' => $c->name,
                'products' => (int) $c->products,
                'units' => (float) $c->units,
                'cost_value' => (float) $c->cost_value,
                'retail_value' => (float) $c->retail_value,
                'margin' => (float) $c->retail_value > 0
                    ? ((float) $c->retail_value - (float) $c->cost_value) / (float) $c->retail_value * 100 : 0,
            ]);

        $rows = $base()->with('category:id,name')->select('products.*',
            DB::raw('COALESCE(ps.qty, 0) as on_hand'),
            DB::raw('COALESCE(ps.qty, 0) * products.cost_price as cost_value'),
            DB::raw('COALESCE(ps.qty, 0) * products.sale_price as retail_value'),
        )->orderByDesc(DB::raw('COALESCE(ps.qty, 0) * products.cost_price'))->orderBy('products.name');

        $rows = $paginate ? $rows->paginate(30)->withQueryString() : $rows->get();

        return compact('summary', 'byCategory', 'rows', 'filters');
    }
}
