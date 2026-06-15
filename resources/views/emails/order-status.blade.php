@php $symbol = $store->currency ?? ''; @endphp
<x-mail::message>
# {{ $headline }}

Hi {{ $order->contact_name ?: 'there' }},

{{ $message }}

<x-mail::panel>
**Order {{ $order->number }}** · Total {{ $symbol }} {{ number_format($order->total, 2) }}
@if ($order->fulfillment_type === 'delivery' && $order->address)
<br>Delivering to {{ $order->address }}@if($order->city), {{ $order->city }}@endif
@endif
</x-mail::panel>

@if ($store)
<x-mail::button :url="route('shop.home', ['store' => $store->slug])">
Visit {{ $store->name }}
</x-mail::button>
@endif

Thanks,<br>
{{ $store->name ?? config('app.name') }}
</x-mail::message>
