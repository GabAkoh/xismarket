<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Operational purchasing reports: products that have sold out, and products
 * that have fallen to/below their reorder level. Both list a suggested order
 * quantity (the shortfall back to the reorder level) and recent demand so
 * buyers can decide what to restock. Scope is stock-tracked products only.
 */
class StockAlertController extends Controller
{
    /** Demand window (days) used for the "units sold" / "per day" signal. */
    protected const LOOKBACK = 90;

    public function __construct(protected Tenancy $tenancy) {}

    /** Products with no stock on hand (on-hand <= 0). */
    public function outOfStock(Request $request)
    {
        return view('inventory.stock-alerts.report', $this->build($request, 'out') + [
            'mode' => 'out',
            'title' => 'Out of stock',
            'subtitle' => 'Stock-tracked products with nothing on hand',
            'reportRoute' => 'products.out-of-stock',
            'exportRoute' => 'products.out-of-stock.export',
        ]);
    }

    /** Products still holding stock but at or below their reorder level. */
    public function belowReorder(Request $request)
    {
        return view('inventory.stock-alerts.report', $this->build($request, 'reorder') + [
            'mode' => 'reorder',
            'title' => 'Below reorder level',
            'subtitle' => 'Stock-tracked products running low — at or under their reorder level',
            'reportRoute' => 'products.reorder',
            'exportRoute' => 'products.reorder.export',
        ]);
    }

    public function outOfStockExport(Request $request)
    {
        return $this->export($request, 'out', 'out-of-stock');
    }

    public function belowReorderExport(Request $request)
    {
        return $this->export($request, 'reorder', 'below-reorder');
    }

    /** Build the report payload (rows + summary + filters) for a mode. */
    protected function build(Request $request, string $mode, bool $paginate = true): array
    {
        $from = now()->subDays(self::LOOKBACK)->startOfDay();
        $to = now()->endOfDay();

        $stockSub = DB::table('product_stocks')
            ->select('product_id', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(reorder_level) as reorder'))
            ->groupBy('product_id');
        $posSub = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', '!=', 'void')->whereBetween('sales.completed_at', [$from, $to])
            ->select('sale_items.product_id', DB::raw('SUM(sale_items.quantity) as qty'), DB::raw('MAX(sales.completed_at) as last'))
            ->groupBy('sale_items.product_id');
        $onlSub = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')->whereBetween('orders.placed_at', [$from, $to])
            ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as qty'), DB::raw('MAX(orders.placed_at) as last'))
            ->groupBy('order_items.product_id');

        // Only stock-tracked products can meaningfully be "out" or "below reorder".
        $base = Product::query()
            ->where('products.track_stock', 1)
            ->leftJoinSub($stockSub, 'ps', 'ps.product_id', '=', 'products.id')
            ->leftJoinSub($posSub, 'pos', 'pos.product_id', '=', 'products.id')
            ->leftJoinSub($onlSub, 'onl', 'onl.product_id', '=', 'products.id');

        // Default to active products; discontinued lines shouldn't clutter a buying list.
        $status = $request->input('status', 'active');
        if ($status === 'active') {
            $base->where('products.is_active', 1);
        } elseif ($status === 'inactive') {
            $base->where('products.is_active', 0);
        }

        if ($request->filled('category')) {
            $base->whereIn('products.category_id', Category::subtreeIds($request->integer('category')));
        }

        if ($mode === 'out') {
            $base->whereRaw('COALESCE(ps.qty, 0) <= 0');
        } else {
            // Low but not empty — empty items belong on the out-of-stock report, not here.
            $base->whereRaw('COALESCE(ps.reorder, 0) > 0 AND COALESCE(ps.qty, 0) > 0 AND COALESCE(ps.qty, 0) <= COALESCE(ps.reorder, 0)');
        }

        // Suggested order = shortfall back up to the reorder level (never negative).
        $shortfall = 'CASE WHEN COALESCE(ps.reorder, 0) - COALESCE(ps.qty, 0) > 0 THEN COALESCE(ps.reorder, 0) - COALESCE(ps.qty, 0) ELSE 0 END';

        $summary = (clone $base)->selectRaw("
            COUNT(*) as products,
            COALESCE(SUM($shortfall), 0) as shortfall,
            COALESCE(SUM(COALESCE(ps.qty, 0) * products.cost_price), 0) as stock_value,
            COALESCE(SUM(COALESCE(pos.qty, 0) + COALESCE(onl.qty, 0)), 0) as units_sold
        ")->first();

        $rows = (clone $base)->with('category')->select('products.*',
            DB::raw('COALESCE(ps.qty, 0) as total_stock'),
            DB::raw('COALESCE(ps.reorder, 0) as reorder_level'),
            DB::raw("($shortfall) as shortfall"),
            DB::raw('COALESCE(pos.qty, 0) + COALESCE(onl.qty, 0) as units_sold'),
            DB::raw('pos.last as pos_last'),
            DB::raw('onl.last as onl_last'),
        )->orderByDesc(DB::raw('COALESCE(pos.qty, 0) + COALESCE(onl.qty, 0)'))->orderBy('products.name');

        $rows = $paginate ? $rows->paginate(30)->withQueryString() : $rows->get();

        return [
            'rows' => $rows,
            'summary' => $summary,
            'lookback' => self::LOOKBACK,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'category' => $request->input('category'),
                'status' => $status,
            ],
        ];
    }

    /** Stream the report (all matching rows, current filters) as CSV. */
    protected function export(Request $request, string $mode, string $slug)
    {
        $data = $this->build($request, $mode, paginate: false);
        $filename = $slug.'-'.now()->toDateString().'.csv';
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');

        return response()->streamDownload(function () use ($data, $num) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Product', 'SKU', 'Category', 'Status', 'Stock', 'Reorder level',
                'Suggested order', 'Units sold ('.self::LOOKBACK.'d)', 'Last sold', 'Stock value (cost)'], ',', '"', '');
            foreach ($data['rows'] as $p) {
                $last = collect([$p->pos_last, $p->onl_last])->filter()->max();
                fputcsv($out, [
                    $p->name, $p->sku, $p->category?->name ?? '', $p->is_active ? 'Active' : 'Inactive',
                    $num($p->total_stock), $num($p->reorder_level), $num($p->shortfall), $num($p->units_sold),
                    $last ? \Illuminate\Support\Carbon::parse($last)->toDateString() : '',
                    number_format((float) $p->total_stock * (float) $p->cost_price, 2, '.', ''),
                ], ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
