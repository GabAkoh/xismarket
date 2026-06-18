@php $logo = $store?->setting('storefront.logo'); @endphp
@if ($logo)
<img src="{{ asset('storage/'.$logo) }}" alt="{{ $store->name }}" style="max-height:48px;margin:0 auto 12px auto;display:block;">
@endif
