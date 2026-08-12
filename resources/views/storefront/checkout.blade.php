@extends('storefront.layout')
@section('title', 'Checkout')

@section('content')
@php $symbol = $store->currencySymbol() ?? ''; @endphp

<h1 class="text-2xl font-bold text-slate-800 mb-5">Checkout</h1>

<form method="POST" action="{{ route('shop.checkout.place') }}"
      x-data="{
          methods: @js(array_map(fn ($m) => ['label' => $m['label'], 'fee' => $m['fee'], 'pickup' => $m['pickup']], $shippingMethods)),
          m: {{ (int) old('shipping_method', 0) }},
          subtotal: {{ $totals['subtotal'] }}, tax: {{ $totals['tax'] }}, discount: {{ $totals['discount'] ?? 0 }},
          method: '{{ old('payment_method', $onlinePayment ? 'online' : 'offline') }}',
          deposit: {{ (float) old('deposit_amount', 0) }},
          minDepositPercent: {{ (int) ($minDepositPercent ?? 0) }},
          get fee() { return Number(this.methods[this.m]?.fee ?? 0); },
          get grandTotal() { return Math.max(0, this.subtotal - this.discount + this.tax + this.fee); },
          get minDeposit() { return this.minDepositPercent > 0 ? Math.max(0.01, Math.round(this.grandTotal * this.minDepositPercent) / 100) : 0.01; },
          get payNow() { return this.method === 'deposit' ? Math.min(Math.max(0, this.deposit || 0), this.grandTotal) : this.grandTotal; },
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
            <select x-model.number="m" class="w-full rounded-md border border-slate-300 p-2.5 text-sm mb-4">
                <template x-for="(method, i) in methods" :key="i">
                    <option :value="i"
                            x-text="(method.pickup ? '🏬 ' : '🚚 ') + method.label + ' — ' + (Number(method.fee) > 0 ? '+{{ $symbol }} ' + Number(method.fee).toFixed(2) : 'Free')"></option>
                </template>
            </select>

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

            @if ($onlinePayment && $requireFull)
                {{-- Strict: the full amount must be paid online to place the order. --}}
                <input type="hidden" name="payment_method" value="online">
                <div class="rounded-md border border-indigo-400 ring-1 ring-indigo-200 p-3">
                    <span class="block text-sm font-medium text-slate-700">💳 Pay now (card)</span>
                    <span class="block text-xs text-slate-500">Secure payment via Paystack — you'll pay the full amount and be redirected to complete it. An email is required for your receipt.</span>
                </div>
            @elseif ($onlinePayment)
                <label class="flex items-start gap-3 rounded-md border p-3 cursor-pointer" :class="method === 'online' ? 'border-indigo-400 ring-1 ring-indigo-200' : 'border-slate-200'">
                    <input type="radio" name="payment_method" value="online" x-model="method" class="mt-1">
                    <span>
                        <span class="block text-sm font-medium text-slate-700">💳 Pay now (card)</span>
                        <span class="block text-xs text-slate-500">Secure payment via Paystack — you'll be redirected to complete it. An email is required for your receipt.</span>
                    </span>
                </label>
                <label class="mt-2 flex items-start gap-3 rounded-md border p-3 cursor-pointer" :class="method === 'deposit' ? 'border-indigo-400 ring-1 ring-indigo-200' : 'border-slate-200'">
                    <input type="radio" name="payment_method" value="deposit" x-model="method" class="mt-1">
                    <span class="flex-1">
                        <span class="block text-sm font-medium text-slate-700">💳 Pay a deposit now (card)</span>
                        <span class="block text-xs text-slate-500">Pay part now by card and settle the balance on delivery.</span>
                        <div x-show="method === 'deposit'" x-cloak class="mt-3">
                            <label class="block text-xs font-medium text-slate-600">Deposit amount</label>
                            <input type="number" name="deposit_amount" step="0.01" :min="minDeposit" :max="grandTotal"
                                   x-model.number="deposit"
                                   x-init="if (! deposit || deposit < minDeposit) deposit = Math.max(minDeposit, Math.round(grandTotal / 2))"
                                   class="mt-1 w-40 rounded-md border border-slate-300 p-2 text-sm">
                            <p class="mt-1 text-xs text-slate-400" x-show="minDepositPercent > 0">
                                Minimum deposit <span x-text="'{{ $symbol }} ' + minDeposit.toFixed(2)"></span> (<span x-text="minDepositPercent"></span>%).
                            </p>
                            <p class="mt-1 text-xs text-slate-500" x-show="payNow > 0">
                                Balance <span x-text="'{{ $symbol }} ' + Math.max(0, grandTotal - payNow).toFixed(2)"></span> due on delivery.
                            </p>
                            @error('deposit_amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </span>
                </label>
                <label class="mt-2 flex items-start gap-3 rounded-md border p-3 cursor-pointer" :class="method === 'offline' ? 'border-indigo-400 ring-1 ring-indigo-200' : 'border-slate-200'">
                    <input type="radio" name="payment_method" value="offline" x-model="method" class="mt-1">
                    <span>
                        <span class="block text-sm font-medium text-slate-700">🚚 Pay on delivery / pickup</span>
                        <span class="block text-xs text-slate-500">Pay when you receive or collect your order.</span>
                    </span>
                </label>
            @else
                <input type="hidden" name="payment_method" value="offline">
                <p class="text-sm text-slate-600">🚚 Pay on delivery or pickup — pay when you receive or collect your order.</p>
            @endif
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
            @if (($totals['discount'] ?? 0) > 0)
                <div class="flex justify-between text-green-600"><dt>Discount ({{ $totals['coupon_code'] }})</dt><dd x-text="'− {{ $symbol }} ' + discount.toFixed(2)"></dd></div>
            @endif
            <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd x-text="'{{ $symbol }} ' + tax.toFixed(2)"></dd></div>
            <div class="flex justify-between" x-show="fee > 0"><dt class="text-slate-500">Shipping</dt><dd x-text="'{{ $symbol }} ' + fee.toFixed(2)"></dd></div>
            <div class="flex justify-between font-bold text-slate-800 pt-2 border-t"><dt>Total</dt>
                <dd x-text="'{{ $symbol }} ' + grandTotal.toFixed(2)"></dd></div>
        </dl>
        <button class="mt-4 w-full rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"
                x-text="method === 'deposit'
                    ? 'Pay deposit · {{ $symbol }} ' + payNow.toFixed(2)
                    : (method === 'online' ? 'Pay · {{ $symbol }} ' : 'Place order · {{ $symbol }} ') + grandTotal.toFixed(2)">
        </button>
        @if ($onlinePayment)
            <p class="text-xs text-slate-400 mt-2 text-center">Card payments are completed securely on Paystack.</p>
        @endif
    </div>
</form>

<script>
    // Meta InitiateCheckout — fires on checkout load when the Pixel is enabled.
    window.xisMetaTrack && xisMetaTrack('InitiateCheckout', {
        content_ids: @js(collect($lines)->pluck('product_id')->map(fn ($id) => (string) $id)->values()),
        content_type: 'product',
        num_items: {{ (int) collect($lines)->sum('qty') }},
        currency: @js($store->currency),
        value: {{ (float) $totals['total'] }},
    });
</script>
@endsection
