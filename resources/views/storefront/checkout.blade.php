@extends('storefront.layout')
@section('title', 'Checkout')

@section('content')
@php $symbol = $store->currencySymbol() ?? ''; @endphp

<h1 class="text-2xl font-bold text-slate-800 mb-5">Checkout</h1>

<form method="POST" action="{{ route('shop.checkout.place') }}"
      x-data="{
          methods: @js(array_map(fn ($m) => ['label' => $m['label'], 'fee' => $m['fee'], 'pickup' => $m['pickup']], $shippingMethods)),
          m: {{ (int) old('shipping_method', 0) }},
          subtotal: {{ $totals['subtotal'] }}, tax: {{ $totals['tax'] }},
          get fee() { return Number(this.methods[this.m]?.fee ?? 0); },
          get pickup() { return !! this.methods[this.m]?.pickup; },
      }"
      class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    @csrf

    <div class="lg:col-span-2 space-y-4">
        <div class="bg-white rounded-lg border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-800 mb-4">Your details</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Full name *</label>
                    <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Phone *</label>
                    <input name="phone" value="{{ old('phone') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-800 mb-4">Shipping method</h2>
            <input type="hidden" name="shipping_method" :value="m">
            <div class="space-y-2 mb-4">
                <template x-for="(method, i) in methods" :key="i">
                    <label class="flex items-center justify-between gap-2 rounded-md border p-3 cursor-pointer"
                           :class="m===i ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-slate-300'">
                        <span class="flex items-center gap-2 text-sm">
                            <input type="radio" :checked="m===i" @change="m=i" class="text-indigo-600">
                            <span x-text="(method.pickup ? '🏬 ' : '🚚 ') + method.label"></span>
                        </span>
                        <span class="text-sm font-medium text-slate-600"
                              x-text="Number(method.fee) > 0 ? '+{{ $symbol }} ' + Number(method.fee).toFixed(2) : 'Free'"></span>
                    </label>
                </template>
            </div>

            <div x-show="!pickup" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700">Delivery address *</label>
                    <input name="address" value="{{ old('address') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
                    @error('address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">City</label>
                    <input name="city" value="{{ old('city') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-slate-700">Order notes</label>
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-md border border-slate-300 p-2">{{ old('notes') }}</textarea>
            </div>
        </div>

        {{-- Payment --}}
        <div class="bg-white rounded-lg border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-800 mb-4">Payment</h2>
            <input type="hidden" name="payment_method" value="card">
            <p class="text-sm text-slate-600 mb-4">💳 Pay securely by card.</p>

            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Card number</label>
                    <input name="card_number" inputmode="numeric" autocomplete="cc-number" placeholder="4242 4242 4242 4242"
                           class="mt-1 w-full rounded-md border border-slate-300 p-2 font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Name on card</label>
                    <input name="card_name" autocomplete="cc-name" value="{{ old('card_name') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Expiry (MM/YY)</label>
                        <input name="card_expiry" autocomplete="cc-exp" placeholder="12/28" class="mt-1 w-full rounded-md border border-slate-300 p-2 font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">CVC</label>
                        <input name="card_cvc" inputmode="numeric" autocomplete="cc-csc" placeholder="123" class="mt-1 w-full rounded-md border border-slate-300 p-2 font-mono">
                    </div>
                </div>
                <p class="text-xs text-slate-400">🔒 Sandbox gateway — no real charge. Try test card <span class="font-mono">4242 4242 4242 4242</span>, any future expiry &amp; CVC. A number ending in <span class="font-mono">0002</span> is declined.</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 p-5 h-fit">
        <h2 class="font-semibold text-slate-800 mb-3">Order summary</h2>
        <ul class="text-sm divide-y mb-3">
            @foreach ($lines as $line)
                <li class="py-2 flex justify-between gap-2">
                    <span class="text-slate-600">{{ $line['qty'] }} × {{ \Illuminate\Support\Str::limit($line['product']->name, 22) }}</span>
                    <span class="text-slate-700">{{ $symbol }} {{ number_format($line['line_total'], 2) }}</span>
                </li>
            @endforeach
        </ul>
        <dl class="text-sm space-y-1.5 border-t pt-2">
            <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd x-text="'{{ $symbol }} ' + subtotal.toFixed(2)"></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd x-text="'{{ $symbol }} ' + tax.toFixed(2)"></dd></div>
            <div class="flex justify-between" x-show="fee > 0"><dt class="text-slate-500">Shipping</dt><dd x-text="'{{ $symbol }} ' + fee.toFixed(2)"></dd></div>
            <div class="flex justify-between font-bold text-slate-800 pt-2 border-t"><dt>Total</dt>
                <dd x-text="'{{ $symbol }} ' + (subtotal + tax + fee).toFixed(2)"></dd></div>
        </dl>
        <button class="mt-4 w-full rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
            Pay <span x-text="'{{ $symbol }} ' + (subtotal + tax + fee).toFixed(2)"></span>
        </button>
        <p class="text-xs text-slate-400 mt-2 text-center">Your card will be charged now.</p>
    </div>
</form>
@endsection
