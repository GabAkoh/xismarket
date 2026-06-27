@extends('layouts.app')
@section('title', 'Cash reasons')

@section('content')
<x-page-header title="Cash in / out reasons" subtitle="The reasons cashiers pick when adding or removing drawer cash, and the account each one posts to." />

<form method="POST" action="{{ route('cash-reasons.update') }}" class="max-w-3xl"
      x-data="{
          reasons: @js(array_values($reasons)),
          add() { this.reasons.push({ key: '', label: '', type: 'in', account: '' }); },
          remove(i) { this.reasons.splice(i, 1); if (this.reasons.length === 0) this.add(); },
      }">
    @csrf @method('PUT')

    <x-card>
        <div class="flex items-center gap-2 px-1 pb-1 text-xs font-medium uppercase tracking-wider text-slate-400">
            <span class="w-5"></span>
            <span class="w-24">Direction</span>
            <span class="flex-1">Reason label</span>
            <span class="w-60">Posts to account</span>
            <span class="w-5"></span>
        </div>
        <div class="space-y-2">
            <template x-for="(r, i) in reasons" :key="i">
                <div class="flex items-center gap-2">
                    <input type="hidden" :name="`reasons[${i}][key]`" :value="r.key">
                    <span class="text-slate-300 text-sm w-5 text-center" x-text="i + 1"></span>
                    <select :name="`reasons[${i}][type]`" x-model="r.type" class="w-24 rounded-md border border-slate-300 p-2 text-sm">
                        <option value="in">Cash in</option>
                        <option value="out">Cash out</option>
                    </select>
                    <input type="text" :name="`reasons[${i}][label]`" x-model="r.label" maxlength="60"
                           placeholder="e.g. Petty cash expense"
                           class="flex-1 rounded-md border border-slate-300 p-2 text-sm">
                    @if ($accounts->count())
                        <select :name="`reasons[${i}][account]`" x-model="r.account" class="w-60 rounded-md border border-slate-300 p-2 text-sm">
                            <option value="">— Default —</option>
                            @foreach ($accounts as $a)
                                <option value="{{ $a->code }}">{{ $a->code }} · {{ $a->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" :name="`reasons[${i}][account]`" :value="r.account">
                        <span class="w-60 text-xs text-slate-400">Accounting module off</span>
                    @endif
                    <button type="button" @click="remove(i)" class="text-slate-300 hover:text-red-500 text-sm" title="Remove">✕</button>
                </div>
            </template>
        </div>

        <button type="button" @click="add()" class="mt-3 text-sm text-indigo-600 hover:underline">+ Add reason</button>

        <div class="mt-5 rounded-md bg-slate-50 border border-slate-100 p-3 text-xs text-slate-500 space-y-1">
            <p><span class="font-medium text-slate-600">Cash in</span> adds money to the drawer (Dr Cash / Cr the chosen account); <span class="font-medium text-slate-600">Cash out</span> removes it (Dr the chosen account / Cr Cash).</p>
            <p>Leave <span class="font-medium text-slate-600">Posts to account</span> on “Default” to use Owner Equity (cash in) or Operating Expenses (cash out).</p>
            <p>Renaming a reason keeps past movements intact; removing one only hides it from the register.</p>
        </div>
    </x-card>

    <div class="mt-4 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        <a href="{{ route('pos.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Open register</a>
    </div>
</form>
@endsection
