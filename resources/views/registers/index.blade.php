@extends('layouts.app')
@section('title', 'Registers & Shifts')

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp

<x-page-header title="Registers & Shifts">
    <a href="{{ route('registers.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add register</a>
</x-page-header>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    @forelse ($registers as $register)
        @php $shift = $register->currentShift; @endphp
        <x-card>
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="font-semibold text-slate-800">{{ $register->name }}</h3>
                    <p class="text-xs text-slate-400">Code {{ $register->code }} · {{ $register->warehouse?->name ?? 'No warehouse' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($register->is_active)
                        <span class="text-xs text-green-600">● Active</span>
                    @else
                        <span class="text-xs text-slate-400">● Inactive</span>
                    @endif
                    <a href="{{ route('registers.edit', $register) }}" class="text-indigo-600 hover:underline text-sm">Edit</a>
                </div>
            </div>

            <div class="mt-4 rounded-md border border-slate-100 p-3" x-data="{ open: false }">
                @if ($shift)
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Shift open</span>
                            <p class="text-xs text-slate-400 mt-1">Opened {{ optional($shift->opened_at)->format('d M H:i') }} by {{ $shift->user?->name }}</p>
                            <p class="text-xs text-slate-400">Float: {{ $symbol }}{{ number_format($shift->opening_float, 2) }}</p>
                            @if ($shift->cashMovements()->exists())
                                <p class="text-xs text-slate-400">Cash in: {{ $symbol }}{{ number_format($shift->cashIn(), 2) }} · Cash out: {{ $symbol }}{{ number_format($shift->cashOut(), 2) }}</p>
                            @endif
                        </div>
                        <button @click="open = !open" class="text-sm text-red-600 hover:underline">Close shift</button>
                    </div>
                    <form x-show="open" x-cloak method="POST" action="{{ route('registers.shift.close', $register) }}" class="mt-3 space-y-2">
                        @csrf
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Counted cash (closing amount)</label>
                            <input type="number" step="0.01" min="0" name="closing_amount" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                        </div>
                        <input name="notes" placeholder="Notes (optional)" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                        <button class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-700">Confirm close</button>
                    </form>
                @else
                    <div class="flex items-center justify-between">
                        <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">No open shift</span>
                        <button @click="open = !open" class="text-sm text-indigo-600 hover:underline">Open shift</button>
                    </div>
                    <form x-show="open" x-cloak method="POST" action="{{ route('registers.shift.open', $register) }}" class="mt-3 space-y-2">
                        @csrf
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Opening float</label>
                            <input type="number" step="0.01" min="0" name="opening_float" value="0" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                        </div>
                        <input name="notes" placeholder="Notes (optional)" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                        <button class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Confirm open</button>
                    </form>
                @endif
            </div>

            <form method="POST" action="{{ route('registers.destroy', $register) }}" class="mt-3 text-right" onsubmit="return confirm('Remove this register?')">
                @csrf @method('DELETE')
                <button class="text-xs text-slate-400 hover:text-red-600">Delete register</button>
            </form>
        </x-card>
    @empty
        <x-card class="md:col-span-2">
            <p class="text-center text-slate-400 py-8">No registers yet. Add one to start selling.</p>
        </x-card>
    @endforelse
</div>
@endsection
