<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalLine;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function index()
    {
        return view('accounting.reports.index');
    }

    /**
     * Trial balance: every account with its debit/credit sums.
     */
    public function trialBalance()
    {
        $rows = $this->accountSums();

        $totalDebit = $rows->sum('debit');
        $totalCredit = $rows->sum('credit');

        return view('accounting.reports.trial-balance', compact('rows', 'totalDebit', 'totalCredit'));
    }

    /**
     * Profit & Loss for a date range: income vs expense.
     */
    public function profitLoss(Request $request)
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : Carbon::now()->startOfYear();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $sums = $this->accountSums(fn ($q) => $q
            ->whereHas('entry', fn ($e) => $e->whereBetween('entry_date', [$from, $to])));

        $income = $sums->where('type', 'income')->map(function ($r) {
            $r['amount'] = round($r['credit'] - $r['debit'], 2); // credit-natural

            return $r;
        });
        $expense = $sums->where('type', 'expense')->map(function ($r) {
            $r['amount'] = round($r['debit'] - $r['credit'], 2); // debit-natural

            return $r;
        });

        $totalIncome = $income->sum('amount');
        $totalExpense = $expense->sum('amount');
        $netProfit = round($totalIncome - $totalExpense, 2);

        return view('accounting.reports.profit-loss', compact(
            'income', 'expense', 'totalIncome', 'totalExpense', 'netProfit', 'from', 'to'
        ));
    }

    /**
     * Balance sheet as of a date: assets = liabilities + equity.
     */
    public function balanceSheet(Request $request)
    {
        $asOf = $request->filled('as_of')
            ? Carbon::parse($request->input('as_of'))->endOfDay()
            : Carbon::now()->endOfDay();

        $sums = $this->accountSums(fn ($q) => $q
            ->whereHas('entry', fn ($e) => $e->where('entry_date', '<=', $asOf)));

        $assets = $sums->where('type', 'asset')->map(function ($r) {
            $r['amount'] = round($r['debit'] - $r['credit'], 2);

            return $r;
        });
        $liabilities = $sums->where('type', 'liability')->map(function ($r) {
            $r['amount'] = round($r['credit'] - $r['debit'], 2);

            return $r;
        });
        $equity = $sums->where('type', 'equity')->map(function ($r) {
            $r['amount'] = round($r['credit'] - $r['debit'], 2);

            return $r;
        });

        // Net income (income - expense) rolls into equity on the balance sheet.
        $income = $sums->where('type', 'income')->sum(fn ($r) => $r['credit'] - $r['debit']);
        $expense = $sums->where('type', 'expense')->sum(fn ($r) => $r['debit'] - $r['credit']);
        $netIncome = round($income - $expense, 2);

        $totalAssets = round($assets->sum('amount'), 2);
        $totalLiabilities = round($liabilities->sum('amount'), 2);
        $totalEquity = round($equity->sum('amount') + $netIncome, 2);

        return view('accounting.reports.balance-sheet', compact(
            'assets', 'liabilities', 'equity', 'netIncome',
            'totalAssets', 'totalLiabilities', 'totalEquity', 'asOf'
        ));
    }

    /**
     * Accounts Receivable aging: every customer with an unpaid (credit-sale)
     * balance, bucketed by how long it has been outstanding. The grand total
     * reconciles with the 1200 Accounts Receivable control account.
     */
    public function receivables()
    {
        $today = Carbon::today();
        $bucketKeys = ['current', 'd31_60', 'd61_90', 'd90'];
        $emptyBuckets = array_fill_keys($bucketKeys, 0.0);

        $customers = collect();
        $totalsByBucket = $emptyBuckets;
        $grandTotal = 0.0;

        // POS module owns sales; guard so Accounting stays decoupled from it.
        $saleClass = \App\Models\Pos\Sale::class;
        if (class_exists($saleClass)) {
            $sales = $saleClass::with('customer')
                ->where('status', 'partially_paid')
                ->where('balance_due', '>', 0)
                ->orderBy('completed_at')
                ->get();

            $customers = $sales->groupBy('customer_id')
                ->map(function ($rows) use ($today, $emptyBuckets, &$totalsByBucket, &$grandTotal) {
                    $custBuckets = $emptyBuckets;

                    $items = $rows->map(function ($sale) use ($today) {
                        $date = $sale->completed_at ? Carbon::parse($sale->completed_at) : $today;
                        $days = (int) abs($today->diffInDays($date->copy()->startOfDay()));
                        $bucket = match (true) {
                            $days <= 30 => 'current',
                            $days <= 60 => 'd31_60',
                            $days <= 90 => 'd61_90',
                            default => 'd90',
                        };

                        return [
                            'id' => $sale->id,
                            'number' => $sale->number,
                            'date' => $date,
                            'days' => $days,
                            'total' => (float) $sale->total,
                            'paid' => (float) $sale->paid_total,
                            'balance' => (float) $sale->balance_due,
                            'bucket' => $bucket,
                        ];
                    });

                    foreach ($items as $it) {
                        $custBuckets[$it['bucket']] += $it['balance'];
                    }
                    $custTotal = round(array_sum($custBuckets), 2);

                    foreach ($custBuckets as $k => $v) {
                        $totalsByBucket[$k] += $v;
                    }
                    $grandTotal += $custTotal;

                    return [
                        'customer' => $rows->first()->customer,
                        'items' => $items,
                        'buckets' => array_map(fn ($v) => round($v, 2), $custBuckets),
                        'total' => $custTotal,
                    ];
                })
                ->sortByDesc('total')
                ->values();

            $totalsByBucket = array_map(fn ($v) => round($v, 2), $totalsByBucket);
            $grandTotal = round($grandTotal, 2);
        }

        // Reconcile against the Accounts Receivable control account (1200).
        $arBalance = 0.0;
        if ($ar = Account::where('code', '1200')->first()) {
            $arBalance = round(
                (float) JournalLine::where('account_id', $ar->id)->sum('debit')
                - (float) JournalLine::where('account_id', $ar->id)->sum('credit'),
                2
            );
        }

        return view('accounting.reports.receivables', compact(
            'customers', 'totalsByBucket', 'grandTotal', 'arBalance'
        ));
    }

    /**
     * Sum debit/credit per account (optionally constrained), returning a collection
     * of ['account' => Account, 'type' => string, 'debit' => float, 'credit' => float].
     *
     * @param  (callable(\Illuminate\Database\Eloquent\Builder): mixed)|null  $lineConstraint
     */
    protected function accountSums(?callable $lineConstraint = null)
    {
        $accounts = Account::orderBy('code')->get();

        return $accounts->map(function (Account $account) use ($lineConstraint) {
            $query = JournalLine::where('account_id', $account->id);

            if ($lineConstraint) {
                $lineConstraint($query);
            }

            $debit = (float) (clone $query)->sum('debit');
            $credit = (float) $query->sum('credit');

            return [
                'account' => $account,
                'type' => $account->type,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
            ];
        })->filter(fn ($r) => $r['debit'] != 0 || $r['credit'] != 0)->values();
    }
}
