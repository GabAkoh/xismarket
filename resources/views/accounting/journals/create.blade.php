@extends('layouts.app')
@section('title', 'New journal entry')

@section('content')
<x-page-header title="New journal entry" />

<form method="POST" action="{{ route('journals.store') }}"
      x-data="journalForm()" @submit="if (! balanced()) { $event.preventDefault(); alert('Debits and credits must balance.'); }">
    @csrf

    <x-card class="mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Date</label>
                <input name="entry_date" type="date" value="{{ old('entry_date', now()->format('Y-m-d')) }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Reference <span class="text-slate-400">(optional)</span></label>
                <input name="reference" value="{{ old('reference') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Memo</label>
                <input name="memo" value="{{ old('memo') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
        </div>
    </x-card>

    <x-card title="Lines">
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b">
                <tr>
                    <th class="py-2">Account</th>
                    <th class="w-32 text-right">Debit</th>
                    <th class="w-32 text-right">Credit</th>
                    <th>Memo</th>
                    <th class="w-10"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(line, i) in lines" :key="i">
                    <tr class="border-b">
                        <td class="py-2 pr-2">
                            <select :name="`lines[${i}][account_id]`" x-model="line.account_id" required class="w-full rounded-md border border-slate-300 p-2">
                                <option value="">— select —</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-2">
                            <input type="number" step="0.01" min="0" :name="`lines[${i}][debit]`" x-model="line.debit"
                                   class="w-full rounded-md border border-slate-300 p-2 text-right tabular-nums">
                        </td>
                        <td class="px-2">
                            <input type="number" step="0.01" min="0" :name="`lines[${i}][credit]`" x-model="line.credit"
                                   class="w-full rounded-md border border-slate-300 p-2 text-right tabular-nums">
                        </td>
                        <td class="px-2">
                            <input :name="`lines[${i}][memo]`" x-model="line.memo" class="w-full rounded-md border border-slate-300 p-2">
                        </td>
                        <td class="text-right">
                            <button type="button" @click="removeLine(i)" x-show="lines.length > 2" class="text-red-500 hover:text-red-700">&times;</button>
                        </td>
                    </tr>
                </template>
            </tbody>
            <tfoot>
                <tr class="font-semibold text-slate-700">
                    <td class="py-3 text-right pr-2">Totals</td>
                    <td class="px-2 text-right tabular-nums" x-text="totalDebit().toFixed(2)"></td>
                    <td class="px-2 text-right tabular-nums" x-text="totalCredit().toFixed(2)"></td>
                    <td class="px-2">
                        <span x-show="balanced()" class="text-green-600 text-xs">Balanced</span>
                        <span x-show="!balanced()" class="text-red-600 text-xs" x-text="'Off by ' + Math.abs(totalDebit() - totalCredit()).toFixed(2)"></span>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <button type="button" @click="addLine()" class="mt-3 rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">+ Add line</button>
    </x-card>

    <div class="mt-4 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Post entry</button>
        <a href="{{ route('journals.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</a>
    </div>
</form>

@push('scripts')
<script>
function journalForm() {
    return {
        lines: [
            { account_id: '', debit: '', credit: '', memo: '' },
            { account_id: '', debit: '', credit: '', memo: '' },
        ],
        addLine() {
            this.lines.push({ account_id: '', debit: '', credit: '', memo: '' });
        },
        removeLine(i) {
            this.lines.splice(i, 1);
        },
        totalDebit() {
            return this.lines.reduce((s, l) => s + (parseFloat(l.debit) || 0), 0);
        },
        totalCredit() {
            return this.lines.reduce((s, l) => s + (parseFloat(l.credit) || 0), 0);
        },
        balanced() {
            return Math.round(this.totalDebit() * 100) === Math.round(this.totalCredit() * 100)
                && this.totalDebit() > 0;
        },
    };
}
</script>
@endpush
@endsection
