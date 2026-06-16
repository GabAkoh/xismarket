@extends('layouts.app')
@section('title', 'New Order')

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp

<div x-data="orderBuilder()" class="flex flex-col" style="min-height: calc(100vh - 9rem);">
    <x-page-header title="New Order">
        <a href="{{ route('orders.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</a>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- LEFT: products --}}
        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm flex flex-col">
            <div class="p-4 border-b border-slate-100">
                <input type="text" x-model="search" placeholder="Search products by name, SKU or barcode…"
                       class="w-full rounded-md border border-slate-300 p-2.5 text-sm focus:ring-2 focus:ring-indigo-500"
                       @keydown.enter.prevent="addFirstMatch()">
            </div>
            <div class="p-4 max-h-[55vh] overflow-y-auto">
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <template x-for="p in filteredProducts" :key="p.id">
                        <button type="button" @click="addToCart(p)"
                                class="text-left rounded-lg border border-slate-200 p-3 hover:border-indigo-400 hover:shadow transition">
                            <div class="font-medium text-slate-700 text-sm leading-tight" x-text="p.name"></div>
                            <div class="text-xs text-slate-400 mt-1" x-text="p.sku"></div>
                            <div class="mt-2 font-semibold text-indigo-600 text-sm" x-text="money(p.price)"></div>
                        </button>
                    </template>
                </div>
                <p x-show="filteredProducts.length === 0" class="text-sm text-slate-400 text-center py-10">No products match.</p>
            </div>
        </div>

        {{-- RIGHT: order summary --}}
        <div class="bg-white rounded-lg shadow-sm flex flex-col">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                <h2 class="font-semibold text-slate-800">Order</h2>
                <button type="button" @click="cart=[]" x-show="cart.length" class="text-xs text-red-500 hover:underline">Clear</button>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-2 max-h-[35vh]">
                <p x-show="cart.length === 0" class="text-sm text-slate-400 text-center py-6">Add products to the order.</p>
                <template x-for="(line, i) in cart" :key="line.id">
                    <div class="rounded-md border border-slate-100 p-2">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-slate-700 truncate" x-text="line.name"></div>
                                <div class="text-xs text-slate-400" x-text="money(line.price) + ' ea'"></div>
                            </div>
                            <button type="button" @click="cart.splice(i,1)" class="text-slate-300 hover:text-red-500 text-sm">✕</button>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <div class="flex items-center border border-slate-200 rounded">
                                <button type="button" @click="line.qty = Math.max(0, Math.round((line.qty-1)*1000)/1000)" class="px-2 text-slate-500">−</button>
                                <input type="number" min="0" step="0.001" x-model.number="line.qty" class="w-14 text-center text-sm border-0 focus:ring-0 p-1">
                                <button type="button" @click="line.qty = Math.round((line.qty+1)*1000)/1000" class="px-2 text-slate-500">+</button>
                            </div>
                            <div class="flex items-center gap-1 text-xs text-slate-400">
                                <span>{{ $symbol }}</span>
                                <input type="number" min="0" step="0.01" x-model.number="line.discount" class="w-16 text-right text-xs border border-slate-200 rounded p-1" placeholder="disc">
                            </div>
                            <span class="ml-auto text-sm font-semibold text-slate-700" x-text="money(lineTotal(line))"></span>
                        </div>
                    </div>
                </template>
            </div>

            <div class="border-t border-slate-100 p-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Customer</label>
                    <select x-model.number="customerId" @change="onCustomer()" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                        <option value="">Guest</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="fulfillment='delivery'" :class="fulfillment==='delivery' ? 'bg-indigo-600 text-white' : 'border border-slate-300 text-slate-600'" class="rounded-md px-3 py-2 text-sm font-medium">🚚 Delivery</button>
                    <button type="button" @click="fulfillment='pickup'" :class="fulfillment==='pickup' ? 'bg-indigo-600 text-white' : 'border border-slate-300 text-slate-600'" class="rounded-md px-3 py-2 text-sm font-medium">🏬 Pickup</button>
                </div>

                <div class="space-y-2" x-show="fulfillment==='delivery'" x-cloak>
                    <input type="text" x-model="contactName" placeholder="Contact name" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                    <input type="text" x-model="contactPhone" placeholder="Phone" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                    <input type="text" x-model="address" placeholder="Delivery address" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                    <input type="text" x-model="city" placeholder="City" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-500">Delivery fee</span>
                        <div class="flex items-center gap-1"><span class="text-xs text-slate-400">{{ $symbol }}</span>
                            <input type="number" min="0" step="0.01" x-model.number="deliveryFee" class="w-20 text-right text-sm border border-slate-200 rounded p-1"></div>
                    </div>
                </div>

                <textarea x-model="notes" rows="2" placeholder="Notes (optional)" class="w-full rounded-md border border-slate-300 p-2 text-sm"></textarea>

                <div class="space-y-1 text-sm border-t border-dashed border-slate-200 pt-2">
                    <div class="flex justify-between text-slate-500"><span>Subtotal</span><span x-text="money(subtotal)"></span></div>
                    <div class="flex justify-between text-slate-500"><span>Discount</span><span x-text="'− ' + money(discountTotal)"></span></div>
                    <div class="flex justify-between text-slate-500"><span>Tax</span><span x-text="money(taxTotal)"></span></div>
                    <div class="flex justify-between text-slate-500" x-show="fulfillment==='delivery'"><span>Delivery</span><span x-text="money(deliveryFee)"></span></div>
                    <div class="flex justify-between text-base font-bold text-slate-800 pt-1"><span>Total</span><span x-text="money(total)"></span></div>
                </div>

                <button type="button" @click="submit()" :disabled="cart.length===0 || submitting"
                        class="w-full rounded-md bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-40">
                    <span x-show="!submitting">Create order · <span x-text="money(total)"></span></span>
                    <span x-show="submitting">Saving…</span>
                </button>
            </div>
        </div>
    </div>

    {{-- hidden submit form --}}
    <form x-ref="form" method="POST" action="{{ route('orders.store') }}" class="hidden">
        @csrf
        <input type="hidden" name="customer_id" :value="customerId">
        <input type="hidden" name="channel" value="online">
        <input type="hidden" name="fulfillment_type" :value="fulfillment">
        <input type="hidden" name="delivery_fee" :value="fulfillment==='delivery' ? (deliveryFee||0) : 0">
        <input type="hidden" name="contact_name" :value="contactName">
        <input type="hidden" name="contact_phone" :value="contactPhone">
        <input type="hidden" name="address" :value="address">
        <input type="hidden" name="city" :value="city">
        <input type="hidden" name="notes" :value="notes">
        <template x-for="(line, i) in cart" :key="'f'+line.id">
            <div>
                <input type="hidden" :name="'items['+i+'][product_id]'" :value="line.id">
                <input type="hidden" :name="'items['+i+'][quantity]'" :value="line.qty">
                <input type="hidden" :name="'items['+i+'][unit_price]'" :value="line.price">
                <input type="hidden" :name="'items['+i+'][discount]'" :value="line.discount || 0">
            </div>
        </template>
    </form>
</div>

@push('scripts')
<script>
function orderBuilder() {
    return {
        products: @json($products),
        customers: @json($customers),
        cart: [], search: '', customerId: '',
        fulfillment: 'delivery', deliveryFee: 0,
        contactName: '', contactPhone: '', address: '', city: '', notes: '',
        submitting: false,

        get filteredProducts() {
            const t = this.search.trim().toLowerCase();
            if (!t) return this.products;
            return this.products.filter(p =>
                (p.name && p.name.toLowerCase().includes(t)) ||
                (p.sku && p.sku.toLowerCase().includes(t)) ||
                (p.barcode && String(p.barcode).toLowerCase().includes(t)));
        },
        addFirstMatch() { const m = this.filteredProducts; if (m.length) { this.addToCart(m[0]); this.search=''; } },
        addToCart(p) {
            const e = this.cart.find(l => l.id === p.id);
            if (e) { e.qty = Math.round((e.qty+1)*1000)/1000; return; }
            this.cart.push({ id:p.id, name:p.name, price:p.price, tax_rate:p.tax_rate, qty:1, discount:0 });
        },
        onCustomer() {
            const c = this.customers.find(c => c.id === this.customerId);
            if (c) { this.contactName = c.name || ''; this.contactPhone = c.phone || ''; this.address = c.address || ''; }
        },
        lineNet(l) { return Math.max(0, (l.price*l.qty) - (parseFloat(l.discount)||0)); },
        lineTotal(l) { const n = this.lineNet(l); return Math.round((n + n*l.tax_rate)*100)/100; },
        get subtotal() { return Math.round(this.cart.reduce((s,l)=>s+(l.price*l.qty),0)*100)/100; },
        get discountTotal() { return Math.round(this.cart.reduce((s,l)=>s+(parseFloat(l.discount)||0),0)*100)/100; },
        get taxTotal() { return Math.round(this.cart.reduce((s,l)=>s+this.lineNet(l)*l.tax_rate,0)*100)/100; },
        get total() {
            const fee = this.fulfillment==='delivery' ? (parseFloat(this.deliveryFee)||0) : 0;
            return Math.round(((this.subtotal - this.discountTotal) + this.taxTotal + fee)*100)/100;
        },
        money(v) { return '{{ $symbol }}' + (Math.round((v||0)*100)/100).toFixed(2); },
        submit() {
            if (this.cart.length===0 || this.submitting) return;
            if (this.cart.some(l=>l.qty<=0)) { alert('Every line needs a quantity greater than zero.'); return; }
            this.submitting = true;
            this.$nextTick(() => this.$refs.form.submit());
        },
    };
}
</script>
@endpush
@endsection
