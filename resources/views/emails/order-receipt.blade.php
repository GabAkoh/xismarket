@php $symbol = $store->currencySymbol() ?? ''; @endphp
<x-mail::message>
@include('emails._logo')

# Thank you, {{ $order->contact_name ?: 'customer' }}!

Your order **{{ $order->number }}** has been received{{ $order->payment_status === 'paid' ? ' and paid' : '' }}.

@if ($order->payment_status === 'paid')
<x-mail::panel>
✓ **Paid**@if($order->payment_reference) — {{ $order->payment_reference }}@endif
</x-mail::panel>
@else
Payment will be collected on **{{ $order->fulfillment_type === 'delivery' ? 'delivery' : 'pickup' }}**.
@endif

<x-mail::table>
| Item | Qty | Total |
| :--- | :--: | ---: |
@foreach ($order->items as $item)
| {{ $item->name }} | {{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }} | {{ $symbol }} {{ number_format($item->line_total, 2) }} |
@endforeach
</x-mail::table>

- Subtotal: {{ $symbol }} {{ number_format($order->subtotal - $order->discount_total, 2) }}
- Tax: {{ $symbol }} {{ number_format($order->tax_total, 2) }}
@if ($order->fulfillment_type === 'delivery')
- Delivery: {{ $symbol }} {{ number_format($order->delivery_fee, 2) }}
@endif
- **Total: {{ $symbol }} {{ number_format($order->total, 2) }}**

**Fulfilment:** {{ ucfirst($order->fulfillment_type) }}@if($order->fulfillment_type === 'delivery' && $order->address) — {{ $order->address }}@if($order->city), {{ $order->city }}@endif @endif

@if ($store)
<x-mail::button :url="route('shop.home', ['store' => $store->slug])">
Visit {{ $store->name }}
</x-mail::button>
@endif

Thanks,<br>
{{ $store->name ?? config('app.name') }}
@if ($store && ($store->address || $store->phone))

<small>{{ $store->address }}@if($store->address && $store->phone) · @endif{{ $store->phone }}</small>
@endif
</x-mail::message>
