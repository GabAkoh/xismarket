<?php

namespace App\Http\Controllers;

use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __invoke(Tenancy $tenancy)
    {
        $tenantId = $tenancy->id();
        $stats = [];

        // Each metric is guarded so the dashboard renders even before a
        // module's tables have been migrated.
        if (Schema::hasTable('products')) {
            $stats['products'] = DB::table('products')->where('tenant_id', $tenantId)->count();
        }
        if (Schema::hasTable('product_stocks')) {
            $stats['low_stock'] = DB::table('product_stocks')
                ->where('tenant_id', $tenantId)
                ->whereColumn('quantity', '<=', 'reorder_level')
                ->count();
        }
        if (Schema::hasTable('sales')) {
            $today = DB::table('sales')
                ->where('tenant_id', $tenantId)
                ->whereDate('completed_at', today())
                ->where('status', '!=', 'void');
            $stats['sales_today_count'] = (clone $today)->count();
            $stats['sales_today_total'] = (clone $today)->sum('total');
        }
        if (Schema::hasTable('users')) {
            $stats['staff'] = DB::table('users')->where('tenant_id', $tenantId)->count();
        }

        $recentSales = [];
        if (Schema::hasTable('sales')) {
            $recentSales = DB::table('sales')
                ->where('tenant_id', $tenantId)
                ->orderByDesc('completed_at')
                ->limit(8)
                ->get(['number', 'total', 'status', 'completed_at']);
        }

        // Sales-by-period series for the "Sales over time" chart.
        // 'void' sales are excluded from realized revenue.
        $salesByDay = [];
        $salesByMonth = [];
        if (Schema::hasTable('sales')) {
            $base = fn () => DB::table('sales')
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['void'])
                ->whereNotNull('completed_at');

            // --- Daily: last 14 days (including today), gap-filled ---
            $dayStart = Carbon::today()->subDays(13);
            $dayRows = $base()
                ->where('completed_at', '>=', $dayStart->copy()->startOfDay())
                ->selectRaw('DATE(completed_at) as bucket, SUM(total) as total, COUNT(*) as count')
                ->groupBy('bucket')
                ->get()
                ->keyBy('bucket');

            for ($d = $dayStart->copy(); $d->lte(Carbon::today()); $d->addDay()) {
                $row = $dayRows->get($d->format('Y-m-d'));
                $salesByDay[] = [
                    'label' => $d->format('M d'),
                    'total' => (float) ($row->total ?? 0),
                    'count' => (int) ($row->count ?? 0),
                ];
            }

            // --- Monthly: last 12 months (including current), gap-filled ---
            $monthStart = Carbon::today()->startOfMonth()->subMonths(11);
            $monthRows = $base()
                ->where('completed_at', '>=', $monthStart->copy()->startOfDay())
                ->selectRaw('DATE_FORMAT(completed_at, "%Y-%m") as bucket, SUM(total) as total, COUNT(*) as count')
                ->groupBy('bucket')
                ->get()
                ->keyBy('bucket');

            for ($m = $monthStart->copy(); $m->lte(Carbon::today()->startOfMonth()); $m->addMonth()) {
                $row = $monthRows->get($m->format('Y-m'));
                $salesByMonth[] = [
                    'label' => $m->format('M y'),
                    'total' => (float) ($row->total ?? 0),
                    'count' => (int) ($row->count ?? 0),
                ];
            }
        }

        return view('dashboard', compact('stats', 'recentSales', 'salesByDay', 'salesByMonth'));
    }
}
