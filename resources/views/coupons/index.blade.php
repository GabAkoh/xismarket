@extends('layouts.app')
@section('title', 'Coupons')

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp
<x-page-header title="Coupons" subtitle="Discount codes for online checkout and the register">
    <a href="{{ route('coupons.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add coupon</a>
</x-page-header>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Code</th><th>Discount</th><th>Min order</th><th>Used</th><th>Expires</th><th>Status</th><th></th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($coupons as $coupon)
                <tr>
                    <td class="py-3 font-mono font-semibold text-slate-700">{{ $coupon->code }}</td>
                    <td class="text-slate-600">{{ $coupon->label($symbol) }}</td>
                    <td class="text-slate-500">{{ $coupon->min_order > 0 ? $symbol.' '.number_format($coupon->min_order, 2) : '—' }}</td>
                    <td class="text-slate-500">{{ $coupon->used_count }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}</td>
                    <td class="text-slate-500">{{ $coupon->expires_at ? $coupon->expires_at->format('M d, Y') : '—' }}</td>
                    <td>
                        @if (! $coupon->is_active)
                            <span class="text-xs text-slate-400">● Inactive</span>
                        @elseif ($coupon->isExpired())
                            <span class="text-xs text-rose-500">● Expired</span>
                        @elseif ($coupon->hasReachedLimit())
                            <span class="text-xs text-amber-600">● Limit reached</span>
                        @else
                            <span class="text-xs text-green-600">● Active</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('coupons.edit', $coupon) }}" class="text-indigo-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('coupons.destroy', $coupon) }}" onsubmit="return confirm('Delete this coupon?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-slate-400">No coupons yet. Add one to offer a discount code.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $coupons->links() }}</div>
</x-card>
@endsection
