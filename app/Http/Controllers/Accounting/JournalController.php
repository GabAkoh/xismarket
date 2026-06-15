<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\PostingService;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    public function __construct(protected PostingService $posting) {}

    public function index()
    {
        $entries = JournalEntry::with('lines')
            ->withSum('lines as total_debit', 'debit')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('accounting.journals.index', compact('entries'));
    }

    public function create()
    {
        $accounts = Account::where('is_active', true)->orderBy('code')->get();

        return view('accounting.journals.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'memo' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        // Map account ids to tenant-scoped Account models (also guards cross-tenant ids).
        $accounts = Account::whereIn('id', collect($data['lines'])->pluck('account_id'))
            ->get()
            ->keyBy('id');

        $lines = [];
        foreach ($data['lines'] as $line) {
            $account = $accounts->get($line['account_id']);
            if (! $account) {
                return back()->withInput()->with('error', 'Invalid account selected.');
            }

            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($debit <= 0 && $credit <= 0) {
                continue; // skip empty rows
            }

            $lines[] = [
                'account' => $account,
                'debit' => $debit,
                'credit' => $credit,
                'memo' => $line['memo'] ?? null,
            ];
        }

        if (count($lines) < 2) {
            return back()->withInput()->with('error', 'A journal entry needs at least two lines with amounts.');
        }

        try {
            $this->posting->post([
                'date' => $data['entry_date'],
                'memo' => $data['memo'] ?? '',
                'reference' => $data['reference'] ?? null,
                'lines' => $lines,
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('journals.index')->with('status', 'Journal entry posted.');
    }

    public function show(JournalEntry $journal)
    {
        abort_unless($journal->tenant_id === app(\App\Support\Tenancy::class)->id(), 404);

        $journal->load(['lines.account', 'user']);

        return view('accounting.journals.show', ['entry' => $journal]);
    }
}
