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
        return view('accounting.reports.trial-balance', $this->trialBalanceData());
    }

    /** Download the trial balance as CSV. */
    public function trialBalanceExport()
    {
        $data = $this->trialBalanceData();
        $num = fn ($v) => number_format((float) $v, 2, '.', '');

        return $this->streamCsv('trial-balance.csv', function ($put) use ($data, $num) {
            $put(['Trial balance']);
            $put([]);
            $put(['Code', 'Account', 'Type', 'Debit', 'Credit']);
            foreach ($data['rows'] as $r) {
                $put([$r['account']->code, $r['account']->name, $r['type'], $num($r['debit']), $num($r['credit'])]);
            }
            $put(['Totals', '', '', $num($data['totalDebit']), $num($data['totalCredit'])]);
        });
    }

    /** @return array{rows: \Illuminate\Support\Collection, totalDebit: float, totalCredit: float} */
    protected function trialBalanceData(): array
    {
        $rows = $this->accountSums();

        return [
            'rows' => $rows,
            'totalDebit' => $rows->sum('debit'),
            'totalCredit' => $rows->sum('credit'),
        ];
    }

    /**
     * Profit & Loss for a date range: income vs expense.
     */
    public function profitLoss(Request $request)
    {
        return view('accounting.reports.profit-loss', $this->profitLossData($request));
    }

    /** Download the profit & loss (current range) as CSV. */
    public function profitLossExport(Request $request)
    {
        $data = $this->profitLossData($request);
        $num = fn ($v) => number_format((float) $v, 2, '.', '');
        $filename = 'profit-loss-'.$data['from']->toDateString().'-to-'.$data['to']->toDateString().'.csv';

        return $this->streamCsv($filename, function ($put) use ($data, $num) {
            $put(['Profit & Loss', $data['from']->toDateString().' to '.$data['to']->toDateString()]);
            $put([]);

            $section = function (string $title, $rows, float $total, string $totalLabel) use ($put, $num) {
                $put([$title]);
                $put(['Code', 'Account', 'Amount']);
                foreach ($rows as $r) {
                    $put([$r['account']->code, $r['account']->name, $num($r['amount'])]);
                }
                $put([$totalLabel, '', $num($total)]);
                $put([]);
            };

            $section('Income', $data['income'], $data['totalIncome'], 'Total income');
            $section('Expenses', $data['expense'], $data['totalExpense'], 'Total expenses');
            $put(['Net '.($data['netProfit'] >= 0 ? 'profit' : 'loss'), '', $num($data['netProfit'])]);
        });
    }

    /**
     * @return array{income: \Illuminate\Support\Collection, expense: \Illuminate\Support\Collection, totalIncome: float, totalExpense: float, netProfit: float, from: Carbon, to: Carbon}
     */
    protected function profitLossData(Request $request): array
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

        return compact('income', 'expense', 'totalIncome', 'totalExpense', 'netProfit', 'from', 'to');
    }

    /**
     * Balance sheet as of a date: assets = liabilities + equity.
     */
    public function balanceSheet(Request $request)
    {
        return view('accounting.reports.balance-sheet', $this->balanceSheetData($request));
    }

    /** Download the balance sheet (as-of date) as CSV. */
    public function balanceSheetExport(Request $request)
    {
        $data = $this->balanceSheetData($request);
        $num = fn ($v) => number_format((float) $v, 2, '.', '');
        $filename = 'balance-sheet-as-of-'.$data['asOf']->toDateString().'.csv';

        return $this->streamCsv($filename, function ($put) use ($data, $num) {
            $put(['Balance Sheet', 'As of '.$data['asOf']->toDateString()]);
            $put([]);

            $section = function (string $title, $rows) use ($put, $num) {
                $put([$title]);
                $put(['Code', 'Account', 'Amount']);
                foreach ($rows as $r) {
                    $put([$r['account']->code, $r['account']->name, $num($r['amount'])]);
                }
            };

            $section('Assets', $data['assets']);
            $put(['Total assets', '', $num($data['totalAssets'])]);
            $put([]);
            $section('Liabilities', $data['liabilities']);
            $put(['Total liabilities', '', $num($data['totalLiabilities'])]);
            $put([]);
            $section('Equity', $data['equity']);
            $put(['Net income (current period)', '', $num($data['netIncome'])]);
            $put(['Total equity', '', $num($data['totalEquity'])]);
            $put([]);
            $put(['Total liabilities + equity', '', $num($data['totalLiabilities'] + $data['totalEquity'])]);
        });
    }

    /**
     * @return array{assets: \Illuminate\Support\Collection, liabilities: \Illuminate\Support\Collection, equity: \Illuminate\Support\Collection, netIncome: float, totalAssets: float, totalLiabilities: float, totalEquity: float, asOf: Carbon}
     */
    protected function balanceSheetData(Request $request): array
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

        return compact(
            'assets', 'liabilities', 'equity', 'netIncome',
            'totalAssets', 'totalLiabilities', 'totalEquity', 'asOf'
        );
    }

    /**
     * Accounts Receivable aging: every customer with an unpaid (credit-sale)
     * balance, bucketed by how long it has been outstanding. The grand total
     * reconciles with the 1200 Accounts Receivable control account.
     */
    public function receivables()
    {
        return view('accounting.reports.receivables', $this->receivablesData());
    }

    /** Download the accounts-receivable aging (with invoice detail) as CSV. */
    public function receivablesExport()
    {
        $data = $this->receivablesData();
        $num = fn ($v) => number_format((float) $v, 2, '.', '');

        return $this->streamCsv('accounts-receivable.csv', function ($put) use ($data, $num) {
            $put(['Accounts Receivable aging']);
            $put([]);
            $put(['Customer', 'Current', '31-60', '61-90', '90+', 'Total']);
            foreach ($data['customers'] as $row) {
                $put([
                    $row['customer']?->name ?? 'Unknown customer',
                    $num($row['buckets']['current']),
                    $num($row['buckets']['d31_60']),
                    $num($row['buckets']['d61_90']),
                    $num($row['buckets']['d90']),
                    $num($row['total']),
                ]);
            }
            $t = $data['totalsByBucket'];
            $put(['Totals', $num($t['current']), $num($t['d31_60']), $num($t['d61_90']), $num($t['d90']), $num($data['grandTotal'])]);
            $put([]);

            $put(['Outstanding invoices']);
            $put(['Sale', 'Customer', 'Date', 'Age (days)', 'Total', 'Paid', 'Balance']);
            foreach ($data['customers'] as $row) {
                foreach ($row['items'] as $it) {
                    $put([
                        $it['number'],
                        $row['customer']?->name ?? '—',
                        $it['date']->format('Y-m-d'),
                        $it['days'],
                        $num($it['total']),
                        $num($it['paid']),
                        $num($it['balance']),
                    ]);
                }
            }
            $put([]);
            $put(['A/R control account (1200)', '', '', '', '', '', $num($data['arBalance'])]);
        });
    }

    /** @return array{customers: \Illuminate\Support\Collection, totalsByBucket: array, grandTotal: float, arBalance: float} */
    protected function receivablesData(): array
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

        return compact('customers', 'totalsByBucket', 'grandTotal', 'arBalance');
    }

    /**
     * Account statement (general-ledger detail): every journal line hitting a
     * chosen account within a date range, with an opening balance carried from
     * all prior activity and a running balance down the period. Debits and
     * credits are signed to the account's natural side (asset/expense are
     * debit-natural; liability/equity/income are credit-natural) so the running
     * balance matches the account's reported balance elsewhere.
     */
    public function accountStatement(Request $request)
    {
        [$from, $to] = $this->statementRange($request);

        // Account picker: every account, labelled "code · name".
        $accountOptions = Account::orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn ($a) => (object) ['id' => $a->id, 'name' => $a->code.' · '.$a->name]);

        $account = $request->filled('account_id')
            ? Account::find((int) $request->input('account_id'))
            : null;

        // No account chosen yet — render the picker with an empty state.
        if (! $account) {
            return view('accounting.reports.account-statement', [
                'accountOptions' => $accountOptions,
                'account' => null,
                'from' => $from,
                'to' => $to,
            ]);
        }

        return view('accounting.reports.account-statement',
            ['accountOptions' => $accountOptions] + $this->accountStatementData($account, $from, $to));
    }

    /** Download the current account statement as CSV. */
    public function accountStatementExport(Request $request)
    {
        [$from, $to] = $this->statementRange($request);

        $account = $request->filled('account_id')
            ? Account::find((int) $request->input('account_id'))
            : null;

        // Nothing to export without a chosen account — back to the picker.
        if (! $account) {
            return redirect()->route('reports.account-statement');
        }

        $data = $this->accountStatementData($account, $from, $to);
        $num = fn ($v) => number_format((float) $v, 2, '.', '');
        $filename = 'account-statement-'.$account->code.'-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($data, $account, $from, $to, $num) {
            $out = fopen('php://output', 'w');
            // Explicit args (incl. $escape) — PHP 8.4 deprecates omitting them.
            $put = fn ($row) => fputcsv($out, $row, ',', '"', '');

            $put(['Account statement', $account->code.' · '.$account->name]);
            $put(['Period', $from->toDateString().' to '.$to->toDateString()]);
            $put([]);

            $put(['Date', 'Reference', 'Description', 'Debit', 'Credit', 'Balance']);
            $put(['', '', 'Opening balance', '', '', $num($data['opening'])]);
            foreach ($data['rows'] as $r) {
                $put([
                    $r->date->format('Y-m-d'),
                    $r->reference,
                    $r->memo,
                    $r->debit ? $num($r->debit) : '',
                    $r->credit ? $num($r->credit) : '',
                    $num($r->balance),
                ]);
            }
            $put(['', '', 'Period movement', $num($data['periodDebit']), $num($data['periodCredit']), '']);
            $put(['', '', 'Closing balance', '', '', $num($data['closing'])]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Parse the statement's date window (defaults to the current month). */
    protected function statementRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : Carbon::now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        return [$from, $to];
    }

    /**
     * Build one account's statement for a date window: an opening balance carried
     * from all prior activity, every line in the window with a running balance,
     * and the period/closing totals. Debits and credits are signed to the
     * account's natural side (asset/expense debit-natural; others credit-natural)
     * so the running balance matches the account's reported balance elsewhere.
     *
     * @return array{account: Account, from: Carbon, to: Carbon, opening: float, rows: \Illuminate\Support\Collection, closing: float, periodDebit: float, periodCredit: float, debitNatural: bool}
     */
    protected function accountStatementData(Account $account, Carbon $from, Carbon $to): array
    {
        $debitNatural = in_array($account->type, ['asset', 'expense'], true);
        $signed = fn ($debit, $credit) => $debitNatural ? $debit - $credit : $credit - $debit;

        // Opening balance: net of everything posted before the window.
        $priorLines = JournalLine::where('account_id', $account->id)
            ->whereHas('entry', fn ($e) => $e->where('entry_date', '<', $from));
        $opening = round($signed(
            (float) (clone $priorLines)->sum('debit'),
            (float) $priorLines->sum('credit'),
        ), 2);

        // Lines in the window, chronological (join keeps ordering in SQL).
        $lines = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $account->id)
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.id')
            ->with('entry')
            ->select('journal_lines.*')
            ->get();

        $running = $opening;
        $periodDebit = 0.0;
        $periodCredit = 0.0;

        $rows = $lines->map(function (JournalLine $line) use (&$running, &$periodDebit, &$periodCredit, $signed) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;
            $running = round($running + $signed($debit, $credit), 2);
            $periodDebit += $debit;
            $periodCredit += $credit;

            return (object) [
                'date' => $line->entry->entry_date,
                'reference' => $line->entry->reference,
                'memo' => $line->memo ?: $line->entry->memo,
                'entry_id' => $line->entry->id,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $running,
            ];
        });

        return [
            'account' => $account,
            'from' => $from,
            'to' => $to,
            'opening' => $opening,
            'rows' => $rows,
            'closing' => $running,
            'periodDebit' => round($periodDebit, 2),
            'periodCredit' => round($periodCredit, 2),
            'debitNatural' => $debitNatural,
        ];
    }

    /**
     * Stream a CSV download. The builder is handed a `$put(array $row)` writer;
     * fputcsv args are explicit (incl. $escape) as PHP 8.4 deprecates omitting them.
     *
     * @param  callable(callable(array): void): void  $build
     */
    protected function streamCsv(string $filename, callable $build)
    {
        return response()->streamDownload(function () use ($build) {
            $out = fopen('php://output', 'w');
            $put = fn ($row) => fputcsv($out, $row, ',', '"', '');
            $build($put);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
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
