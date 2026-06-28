<x-mail::message>
@include('emails._logo')

# Welcome{{ $subscriber->name ? ', '.$subscriber->name : '' }}! 🎉

Thanks for joining the **{{ $store->name }}** community. You'll be among the first to hear about new arrivals, exclusive offers and updates.

<x-mail::button :url="route('shop.home', ['store' => $store->slug])">Start shopping</x-mail::button>

See you soon,<br>
{{ $store->name }}

<x-slot:subcopy>
You're receiving this because you subscribed at {{ $store->name }}. If this wasn't you, you can ignore this email.
</x-slot:subcopy>
</x-mail::message>
