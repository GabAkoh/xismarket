@php $symbol = $store->currencySymbol() ?? ''; @endphp
<x-mail::message>
@include('emails._logo')

# New order received

Order **{{ $order->number }}** was just placed on your storefront{{ $order->payment_status === 'paid' ? ' and paid online' : '' }}.

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
- {{ $order->shipping_method ?: 'Delivery' }}: {{ $symbol }} {{ number_format($order->delivery_fee, 2) }}
@endif
- **Total: {{ $symbol }} {{ number_format($order->total, 2) }}**

**Payment:** {{ $order->payment_status === 'paid'
    ? 'Paid'.($order->payment_reference ? ' — '.$order->payment_reference : '')
    : 'To collect on '.($order->fulfillment_type === 'delivery' ? 'delivery' : 'pickup') }}

@php
    $fulfilment = ucfirst($order->fulfillment_type);
    if ($order->shipping_method) { $fulfilment .= ' · '.$order->shipping_method; }
    if ($order->fulfillment_type === 'delivery' && $order->address) {
        $fulfilment .= ' — '.$order->address.($order->city ? ', '.$order->city : '');
    }
@endphp
**Fulfilment:** {{ $fulfilment }}

**Customer:** {{ $order->contact_name ?: 'Customer' }}@if($order->contact_phone) · {{ $order->contact_phone }}@endif @if($order->customer && $order->customer->email)· {{ $order->customer->email }}@endif

@if ($order->notes)
**Notes:** {{ $order->notes }}
@endif

<x-mail::button :url="config('app.url')">Open dashboard</x-mail::button>

{{ $store->name ?? '' }}
</x-mail::message>
