<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Expiring-products report: products whose expiry date falls within a
 * look-ahead window (default two weeks) plus anything already past its date.
 * Surfaces on-hand quantity and stock value so staff can pull or discount
 * stock before it expires. Scope is products that carry an expiry date.
 */
class ExpiringProductController extends Controller
{
    /** Default look-ahead window (days) — two weeks. */
    protected const DEFAULT_DAYS = 14;

    public function __construct(protected Tenancy $tenancy) {}

    /** The report page: filters, summary tiles and a paginated table. */
    public function report(Request $request)
    {
        $data = $this->build($request);
        $data['categories'] = Category::orderBy('name')->get(['id', 'name']);

        return view('inventory.products.expiring', $data);
    }

    /** Stream the report (all matching rows, current filters) as CSV. */
    public function reportExport(Request $request)
    {
        $data = $this->build($request, paginate: false);
        $filename = 'expiring-products-'.now()->toDateString().'.csv';
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');

        return response()->streamDownload(function () use ($data, $qty) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Product', 'SKU', 'Category', 'Status', 'Expiry date', 'Days left',
                'Stock', 'Stock value (cost)'], ',', '"', '');
            foreach ($data['rows'] as $p) {
                $daysLeft = $data['today']->diffInDays($p->expiry_date->copy()->startOfDay(), false);
                fputcsv($out, [
                    $p->name, $p->sku, $p->category?->name ?? '', $p->is_active ? 'Active' : 'Inactive',
                    $p->expiry_date->toDateString(), (int) $daysLeft, $qty($p->total_stock),
                    number_format((float) $p->total_stock * (float) $p->cost_price, 2, '.', ''),
                ], ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Build the report payload (rows + summary + filters) for the current
     * window and filters. Queries stay driver-agnostic (no MySQL-only date
     * functions) so the days-left figure is derived from the cast Carbon date.
     */
    protected function build(Request $request, bool $paginate = true): array
    {
        $days = max(1, min(365, (int) $request->input('days', self::DEFAULT_DAYS)));
        $today = now()->startOfDay();
        $cutoff = $today->copy()->addDays($days);

        $stockSub = DB::table('product_stocks')
            ->select('product_id', DB::raw('SUM(quantity) as qty'))
            ->groupBy('product_id');

        $base = Product::query()
            ->whereNotNull('products.expiry_date')
            ->whereDate('products.expiry_date', '<=', $cutoff->toDateString())
            ->leftJoinSub($stockSub, 'ps', 'ps.product_id', '=', 'products.id');

        // Default to active products; discontinued lines shouldn't clutter the list.
        $status = $request->input('status', 'active');
        if ($status === 'active') {
            $base->where('products.is_active', 1);
        } elseif ($status === 'inactive') {
            $base->where('products.is_active', 0);
        }
        if ($request->filled('category')) {
            $base->whereIn('products.category_id', Category::subtreeIds($request->integer('category')));
        }

        $total = (clone $base)->count('products.id');
        $expired = (clone $base)->whereDate('products.expiry_date', '<', $today->toDateString())->count('products.id');
        $stockValue = (clone $base)
            ->selectRaw('COALESCE(SUM(COALESCE(ps.qty, 0) * products.cost_price), 0) as v')
            ->value('v');

        $summary = (object) [
            'products' => $total,
            'expired' => $expired,
            'expiring' => $total - $expired,
            'stock_value' => $stockValue,
        ];

        $rows = (clone $base)->with('category')
            ->select('products.*', DB::raw('COALESCE(ps.qty, 0) as total_stock'))
            ->orderBy('products.expiry_date')->orderBy('products.name');

        $rows = $paginate ? $rows->paginate(30)->withQueryString() : $rows->get();

        return [
            'rows' => $rows,
            'summary' => $summary,
            'days' => $days,
            'today' => $today,
            'filters' => [
                'category' => $request->input('category'),
                'status' => $status,
            ],
        ];
    }
}
