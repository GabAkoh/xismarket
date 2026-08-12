{{--
    Meta Pixel base code + PageView, rendered only when the tenant has Meta
    tracking enabled with a Pixel ID (see MetaConversionsService::browserEnabled).
    Defines window.xisMetaTrack(name, data, eventId) — a thin fbq wrapper that
    passes an eventID so a browser event and its server-side Conversions API twin
    (same event_id) are de-duplicated by Meta.
--}}
@php $metaPixelId = app(\App\Services\Storefront\MetaConversionsService::class)->browserEnabled($store)
        ? app(\App\Services\Storefront\MetaConversionsService::class)->pixelId($store)
        : null; @endphp
@if ($metaPixelId)
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', @js((string) $metaPixelId));
fbq('track', 'PageView');
window.xisMetaTrack = function (name, data, eventId) {
    if (!window.fbq) return;
    fbq('track', name, data || {}, eventId ? { eventID: eventId } : undefined);
};
</script>
<noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id={{ $metaPixelId }}&ev=PageView&noscript=1"></noscript>
@endif
