@extends('layouts.app')
@section('title', 'Product movements')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $money = fn ($v) => $symbol.' '.number_format((float) $v, 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
    $days = max(1, $from->diffInDays($to) + 1);
    $base = array_filter([
        'category' => $filters['category'], 'type' => $filters['type'], 'q' => $filters['q'],
        'from' => $from->toDateString(), 'to' => $to->toDateString(),
    ], fn ($v) => $v !== null && $v !== '');
    $exportUrl = fn ($section) => route('products.movements.export', $base + ['section' => $section]);
    $typeBadge = [
        'in' => 'bg-green-100 text-green-700', 'purchase' => 'bg-green-100 text-green-700',
        'return' => 'bg-teal-100 text-teal-700', 'import' => 'bg-blue-100 text-blue-700',
        'adjustment' => 'bg-amber-100 text-amber-700',
        'sale' => 'bg-indigo-100 text-indigo-700', 'out' => 'bg-red-100 text-red-700',
    ];
    $refLabel = function ($m) {
        if (! $m->reference_type) return '';
        $b = class_basename($m->reference_type);
        return $m->reference_id ? $b.' #'.$m->reference_id : $b;
    };
@endphp

<x-page-header title="Product movements" subtitle="Every stock change over the selected period">
    <a href="{{ $exportUrl('all') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Export all (CSV)</a>
    <a href="{{ route('products.report') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Product report</a>
</x-page-header>

{{-- Filters --}}
<x-card class="mb-4">
    <form method="GET" action="{{ route('products.movements') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">From</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">To</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Category</label>
            <select name="category" class="rounded-md border border-slate-300 p-2 text-sm">
                <option value="">All categories</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected((string) $filters['category'] === (string) $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Type</label>
            <select name="type" class="rounded-md border border-slate-300 p-2 text-sm">
                <option value="">All types</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Product</label>
            <input name="q" value="{{ $filters['q'] }}" placeholder="Name, SKU or barcode" class="rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply</button>
        <a href="{{ route('products.movements') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Reset</a>
    </form>
</x-card>

{{-- Summary --}}
<div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Movements</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ number_format($summary->movements) }}</p>
        <p class="text-xs text-slate-400 mt-1">over {{ number_format($days) }} day(s)</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Products moved</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ number_format($summary->products) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Quantity in</p>
        <p class="mt-1 text-2xl font-bold text-green-600">{{ $qty($summary->qty_in) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Quantity out</p>
        <p class="mt-1 text-2xl font-bold text-red-600">{{ $qty($summary->qty_out) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Net change</p>
        <p class="mt-1 text-2xl font-bold {{ (float) $summary->net < 0 ? 'text-red-600' : 'text-slate-800' }}">{{ $qty($summary->net) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Value in / out</p>
        <p class="mt-1 text-lg font-bold text-slate-800">{{ $money($summary->in_value) }}</p>
        <p class="text-xs text-slate-400">out {{ $money($summary->out_value) }}</p>
    </div>
</div>

{{-- By type + top movers --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <x-card title="By movement type">
        <x-slot:actions><a href="{{ $exportUrl('bytype') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b"><tr><th class="py-2">Type</th><th class="text-right">Moves</th><th class="text-right">In</th><th class="text-right">Out</th><th class="text-right">Net</th><th class="text-right">Value</th></tr></thead>
            <tbody class="divide-y">
                @forelse ($byType as $t)
                    <tr>
                        <td class="py-2"><span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $typeBadge[$t->type] ?? 'bg-slate-100 text-slate-600' }}">{{ $t->label }}</span></td>
                        <td class="py-2 text-right text-slate-500">{{ number_format($t->movements) }}</td>
                        <td class="py-2 text-right text-green-600">{{ $t->qty_in > 0 ? $qty($t->qty_in) : '—' }}</td>
                        <td class="py-2 text-right text-red-600">{{ $t->qty_out > 0 ? $qty($t->qty_out) : '—' }}</td>
                        <td class="py-2 text-right text-slate-700">{{ $qty($t->net) }}</td>
                        <td class="py-2 text-right font-semibold text-slate-700">{{ $money($t->value) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-center text-slate-400">No movements in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <x-card title="Top movers">
        <x-slot:actions><a href="{{ $exportUrl('byproduct') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b"><tr><th class="py-2">Product</th><th class="text-right">Moves</th><th class="text-right">In</th><th class="text-right">Out</th><th class="text-right">Net</th></tr></thead>
            <tbody class="divide-y">
                @forelse ($byProduct->take(12) as $p)
                    <tr>
                        <td class="py-2 font-medium text-slate-700">{{ $p->name }}<div class="text-xs text-slate-400">{{ $p->sku }}</div></td>
                        <td class="py-2 text-right text-slate-500">{{ number_format($p->movements) }}</td>
                        <td class="py-2 text-right text-green-600">{{ $p->qty_in > 0 ? $qty($p->qty_in) : '—' }}</td>
                        <td class="py-2 text-right text-red-600">{{ $p->qty_out > 0 ? $qty($p->qty_out) : '—' }}</td>
                        <td class="py-2 text-right font-semibold {{ (float) $p->net < 0 ? 'text-red-600' : 'text-slate-700' }}">{{ $qty($p->net) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-center text-slate-400">No movements in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($byProduct->count() > 12)
            <p class="mt-2 text-xs text-slate-400">Showing top 12 of {{ number_format($byProduct->count()) }} products — export CSV for the full list.</p>
        @endif
    </x-card>
</div>

{{-- Movement ledger --}}
<x-card title="Movement log">
    <x-slot:actions><a href="{{ $exportUrl('detailed') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr>
                <th class="py-2">Date</th><th>Product</th><th>Type</th>
                <th class="text-right">In</th><th class="text-right">Out</th>
                <th class="text-right">Unit cost</th><th class="text-right">Value</th>
                <th>Reference</th><th>User</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($rows as $m)
                <tr>
                    <td class="py-2 text-slate-500 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($m->created_at)->format('d M Y H:i') }}</td>
                    <td class="py-2 font-medium text-slate-700">{{ $m->product_name }}<div class="text-xs text-slate-400">{{ $m->sku }}</div></td>
                    <td class="py-2"><span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $typeBadge[$m->type] ?? 'bg-slate-100 text-slate-600' }}">{{ $types[$m->type] ?? ucfirst($m->type) }}</span></td>
                    <td class="py-2 text-right text-green-600">{{ (float) $m->quantity > 0 ? $qty($m->quantity) : '—' }}</td>
                    <td class="py-2 text-right text-red-600">{{ (float) $m->quantity < 0 ? $qty(-$m->quantity) : '—' }}</td>
                    <td class="py-2 text-right text-slate-500">{{ $money($m->unit_cost) }}</td>
                    <td class="py-2 text-right text-slate-700">{{ $money(abs((float) $m->quantity) * (float) $m->unit_cost) }}</td>
                    <td class="py-2 text-slate-500">{{ $refLabel($m) ?: '—' }}</td>
                    <td class="py-2 text-slate-500">{{ $m->user_name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-6 text-center text-slate-400">No movements match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-card>
@endsection
