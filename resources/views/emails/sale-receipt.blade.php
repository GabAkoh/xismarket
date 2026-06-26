@php $symbol = $store->currencySymbol() ?? ''; @endphp
<x-mail::message>
@include('emails._logo')

# Thank you{{ $sale->customer?->name ? ', '.$sale->customer->name : '' }}!

Here's your receipt for **{{ $sale->number }}**.

<x-mail::table>
| Item | Qty | Total |
| :--- | :--: | ---: |
@foreach ($sale->items as $item)
| {{ $item->name }} | {{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }} | {{ $symbol }} {{ number_format($item->line_total, 2) }} |
@endforeach
</x-mail::table>

- Subtotal: {{ $symbol }} {{ number_format($sale->subtotal - $sale->discount_total, 2) }}
- Tax: {{ $symbol }} {{ number_format($sale->tax_total, 2) }}
- **Total: {{ $symbol }} {{ number_format($sale->total, 2) }}**
- Paid: {{ $symbol }} {{ number_format($sale->paid_total, 2) }}
@if ($sale->change_due > 0)
- Change: {{ $symbol }} {{ number_format($sale->change_due, 2) }}
@endif
@if ($sale->customer && $sale->points_earned)
- Loyalty points earned: {{ number_format($sale->points_earned) }}
@endif

@if ($sale->completed_at)
<small>{{ $sale->completed_at->format('d M Y H:i') }}</small>
@endif

Thanks for shopping with us,<br>
{{ $store->name ?? config('app.name') }}
@if ($store && ($store->address || $store->phone))

<small>{{ $store->address }}@if($store->address && $store->phone) · @endif{{ $store->phone }}</small>
@endif
</x-mail::message>
