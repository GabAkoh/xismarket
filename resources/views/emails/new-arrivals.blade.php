@php $symbol = $store->currencySymbol() ?? ''; @endphp
<x-mail::message>
@include('emails._logo')

# New arrivals at {{ $store->name }}

@if ($intro)
{{ $intro }}
@else
Fresh in store — take a look at what's just landed.
@endif

<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($products as $p)
<tr>
    <td width="64" style="padding:8px 12px 8px 0; vertical-align:top;">
        @if ($p->image_path)
            <img src="{{ asset('storage/'.$p->image_path) }}" width="56" height="56" alt="" style="border-radius:6px; object-fit:cover; display:block;">
        @endif
    </td>
    <td style="padding:8px 0; vertical-align:top;">
        <a href="{{ route('shop.product', ['store' => $store->slug, 'product' => $p->id]) }}" style="font-weight:600; color:#4f46e5; text-decoration:none;">{{ $p->name }}</a><br>
        <span style="color:#475569;">{{ $symbol }} {{ number_format($p->sale_price, 2) }}</span>
    </td>
</tr>
@endforeach
</table>

<x-mail::button :url="route('shop.home', ['store' => $store->slug])">Shop all</x-mail::button>

{{ $store->name }}

<x-slot:subcopy>
You're receiving this because you joined the {{ $store->name }} community.
[Unsubscribe]({{ route('shop.unsubscribe', ['store' => $store->slug, 'token' => $subscriber->token]) }}) at any time.
</x-slot:subcopy>
</x-mail::message>
