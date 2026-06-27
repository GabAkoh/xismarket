@extends('layouts.app')
@section('title', 'Register')

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp

<div x-data="posRegister()" x-init="init()" class="flex flex-col" style="height: calc(100vh - 7rem);">

    {{-- Top bar: register + shift status --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold text-slate-800">Register</h1>
            @if ($register)
                <span class="text-sm text-slate-500">{{ $register->name }} ({{ $register->code }})</span>
                @if ($openShift)
                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Shift open</span>
                @else
                    <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">No open shift</span>
                @endif
            @else
                <span class="text-sm text-amber-600">No active register — create one under Registers & Shifts.</span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            {{-- Cash in / Cash out — drawer adjustments on the open shift (Shopify-style) --}}
            @if ($register && $openShift)
                <div x-data="{ show: false, type: 'in', reasons: { in: @js(\App\Models\Pos\CashMovement::IN_REASONS), out: @js(\App\Models\Pos\CashMovement::OUT_REASONS) } }" class="flex items-center gap-2">
                    <button type="button" @click="type = 'in'; show = true"
                            class="rounded-md border border-green-300 bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-700 hover:bg-green-100">Cash in</button>
                    <button type="button" @click="type = 'out'; show = true"
                            class="rounded-md border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">Cash out</button>

                    <div x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="show = false" @keydown.escape.window="show = false">
                        <form method="POST" action="{{ route('pos.cash') }}" class="w-full max-w-sm space-y-3 rounded-lg bg-white p-5 shadow-xl">
                            @csrf
                            <input type="hidden" name="register_id" value="{{ $register->id }}">
                            <input type="hidden" name="type" :value="type">
                            <h3 class="text-sm font-semibold text-slate-800"
                                x-text="type === 'in' ? 'Cash in — add to drawer' : 'Cash out — remove from drawer'"></h3>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Amount ({{ $symbol }})</label>
                                <input type="number" step="0.01" min="0.01" name="amount" required
                                       class="w-full rounded-md border border-slate-300 p-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Reason</label>
                                <select name="reason" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                                    <template x-for="(label, key) in reasons[type]" :key="key">
                                        <option :value="key" x-text="label"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Note (optional)</label>
                                <input type="text" name="note" maxlength="255"
                                       class="w-full rounded-md border border-slate-300 p-2 text-sm">
                            </div>
                            <div class="flex justify-end gap-2 pt-1">
                                <button type="button" @click="show = false" class="rounded-md px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-100">Cancel</button>
                                <button type="submit" class="rounded-md px-3 py-1.5 text-sm font-semibold text-white"
                                        :class="type === 'in' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
                                        x-text="type === 'in' ? 'Record cash in' : 'Record cash out'"></button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if ($registers->count() > 1)
                <form method="GET" action="{{ route('pos.index') }}">
                    <select name="register" onchange="this.form.submit()" class="rounded-md border border-slate-300 p-2 text-sm">
                        @foreach ($registers as $r)
                            <option value="{{ $r->id }}" @selected($register && $r->id === $register->id)>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
    </div>

    <div class="flex-1 grid grid-cols-1 lg:grid-cols-5 gap-4 min-h-0">

        {{-- LEFT: product search + grid --}}
        <div class="lg:col-span-3 bg-white rounded-lg shadow-sm flex flex-col min-h-0">
            <div class="p-4 border-b border-slate-100">
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔎</span>
                    <input type="text" x-ref="scan" x-model="search"
                           placeholder="Scan barcode or search by name / SKU…  (Enter to add)"
                           class="w-full rounded-md border border-slate-300 pl-9 pr-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500"
                           @keydown.enter.prevent="scanOrAddFirst()">
                </div>
                <p x-show="scanError" x-cloak class="mt-1 text-xs text-red-500" x-text="scanError"></p>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                @php
                    // Responsive ramp down from the configured wide-screen column count
                    // (Tailwind CDN generates these literal classes at runtime).
                    $xl = $gridColumns;
                    $lg = max(3, $xl - 1);
                    $sm = max(2, $xl - 2);
                @endphp
                <div class="grid grid-cols-2 sm:grid-cols-{{ $sm }} lg:grid-cols-{{ $lg }} xl:grid-cols-{{ $xl }} gap-2">
                    <template x-for="p in filteredProducts" :key="p.id">
                        <button type="button" @click="addToCart(p)" :disabled="isOut(p)"
                                class="text-left rounded-lg border p-2 transition"
                                :class="isOut(p) ? 'border-slate-200 bg-slate-50 opacity-60 cursor-not-allowed' : 'border-slate-200 hover:border-indigo-400 hover:shadow'">
                            <div class="mb-1.5 aspect-square w-full overflow-hidden rounded bg-slate-100 flex items-center justify-center">
                                <template x-if="p.image">
                                    <img :src="p.image" :alt="p.name" class="h-full w-full object-cover" loading="lazy">
                                </template>
                                <template x-if="!p.image">
                                    <span class="text-xl text-slate-300">📦</span>
                                </template>
                            </div>
                            <div class="font-medium text-slate-700 text-xs leading-tight line-clamp-2" x-text="p.name"></div>
                            <div class="text-[11px] text-slate-400 mt-0.5 truncate" x-text="p.sku"></div>
                            <div class="mt-1 flex items-center justify-between gap-1">
                                <span class="font-semibold text-indigo-600 text-xs" x-text="money(p.price)"></span>
                                <template x-if="p.track_stock && p.stock !== null">
                                    <span class="text-[11px] whitespace-nowrap" :class="isOut(p) ? 'text-red-500 font-medium' : 'text-slate-400'"
                                          x-text="isOut(p) ? 'Sold out' : (p.stock ?? 0)"></span>
                                </template>
                            </div>
                        </button>
                    </template>
                </div>
                <p x-show="filteredProducts.length === 0" class="text-sm text-slate-400 text-center py-10">No products match your search.</p>
            </div>
        </div>

        {{-- RIGHT: cart + checkout --}}
        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm flex flex-col min-h-0">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                <h2 class="font-semibold text-slate-800">Cart</h2>
                <button type="button" @click="clearCart()" x-show="cart.length" class="text-xs text-red-500 hover:underline">Clear</button>
            </div>

            {{-- Scrollable cart body: lines + checkout scroll together so the whole
                 cart (including the Complete button) is always reachable. --}}
            <div class="flex-1 overflow-y-auto min-h-0">

            {{-- Cart lines --}}
            <div class="p-4 space-y-2">
                <p x-show="cart.length === 0" class="text-sm text-slate-400 text-center py-8">Scan or click a product to add it.</p>
                <template x-for="(line, i) in cart" :key="line.id">
                    <div class="rounded-md border border-slate-100 p-2">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-slate-700 truncate" x-text="line.name"></div>
                                <div class="text-xs text-slate-400" x-text="money(line.price) + ' ea'"></div>
                            </div>
                            <button type="button" @click="removeLine(i)" class="text-slate-300 hover:text-red-500 text-sm">✕</button>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <div class="flex items-center border border-slate-200 rounded">
                                <button type="button" @click="dec(line)" class="px-2 text-slate-500">−</button>
                                <input type="number" min="0" step="0.001" x-model.number="line.qty"
                                       class="w-14 text-center text-sm border-0 focus:ring-0 p-1">
                                <button type="button" @click="inc(line)" class="px-2 text-slate-500">+</button>
                            </div>
                            <div class="flex items-center gap-1 text-xs text-slate-400">
                                <span>{{ $symbol }}</span>
                                <input type="number" min="0" step="0.01" x-model.number="line.discount"
                                       class="w-16 text-right text-xs border border-slate-200 rounded p-1" placeholder="disc">
                            </div>
                            <span class="ml-auto text-sm font-semibold text-slate-700" x-text="money(lineTotal(line))"></span>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Totals + checkout --}}
            <div class="border-t border-slate-100 p-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Customer</label>
                    <select x-model.number="customerId" @change="onCustomerChange()" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                        <option value="">Walk-in customer</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                        @endforeach
                    </select>
                    <template x-if="customer">
                        <div class="mt-1 flex items-center justify-between text-xs">
                            <span class="text-indigo-600">Wallet: <span x-text="money(customer.balance)"></span></span>
                            <span class="text-amber-600" x-text="customer.points + ' pts'"></span>
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500">Cart discount</span>
                    <div class="flex items-center gap-1">
                        <span class="text-xs text-slate-400">{{ $symbol }}</span>
                        <input type="number" min="0" step="0.01" x-model.number="cartDiscount"
                               class="w-20 text-right text-sm border border-slate-200 rounded p-1">
                    </div>
                </div>

                {{-- Loyalty redemption --}}
                <template x-if="canRedeem">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-amber-600">Redeem points
                            <button type="button" class="ml-1 text-xs underline" @click="redeemPoints = maxRedeemablePoints">max</button>
                        </span>
                        <div class="flex items-center gap-2">
                            <input type="number" min="0" step="1" x-model.number="redeemPoints"
                                   class="w-20 text-right text-sm border border-slate-200 rounded p-1">
                            <span class="text-xs text-slate-400" x-text="'− ' + money(loyaltyDiscount)"></span>
                        </div>
                    </div>
                </template>

                <div class="space-y-1 text-sm border-t border-dashed border-slate-200 pt-2">
                    <div class="flex justify-between text-slate-500"><span>Subtotal</span><span x-text="money(subtotal)"></span></div>
                    <div class="flex justify-between text-slate-500"><span>Discount</span><span x-text="'− ' + money(discountTotal)"></span></div>
                    <div class="flex justify-between text-slate-500"><span>Tax</span><span x-text="money(taxTotal)"></span></div>
                    <div class="flex justify-between text-base font-bold text-slate-800 pt-1"><span>Total</span><span x-text="money(total)"></span></div>
                </div>

                {{-- Wallet payment --}}
                <template x-if="customer && customer.balance > 0">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-indigo-600">Pay from wallet
                            <button type="button" class="ml-1 text-xs underline" @click="useWallet = maxWallet">max</button>
                        </span>
                        <div class="flex items-center gap-1">
                            <span class="text-xs text-slate-400">{{ $symbol }}</span>
                            <input type="number" min="0" step="0.01" x-model.number="useWallet"
                                   class="w-20 text-right text-sm border border-slate-200 rounded p-1">
                        </div>
                    </div>
                </template>

                {{-- Remaining due via one or more payment methods (split tender) --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-medium text-slate-500">Payment</label>
                        <button type="button" @click="addTender()" x-show="canAddTender" class="text-xs text-indigo-600 hover:underline">+ Add method</button>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(t, i) in tenders" :key="i">
                            <div class="flex items-center gap-2">
                                <select x-model="t.method" @change="enforceUniqueMethod(t)" class="rounded-md border border-slate-300 p-2 text-sm">
                                    <template x-for="m in payMethods" :key="m">
                                        <option :value="m" :disabled="methodTaken(t, m)" x-text="methodLabel(m)"></option>
                                    </template>
                                </select>
                                <div class="relative flex-1">
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-slate-400">{{ $symbol }}</span>
                                    <input type="number" min="0" step="0.01" x-model.number="t.amount"
                                           class="w-full rounded-md border border-slate-300 p-2 pl-7 text-sm text-right" placeholder="0.00">
                                </div>
                                <button type="button" @click="fillDue(t)" class="text-xs text-slate-500 hover:text-indigo-600 whitespace-nowrap" title="Fill the remaining amount due">= due</button>
                                <button type="button" @click="removeTender(i)" x-show="tenders.length > 1" class="text-slate-300 hover:text-red-500 text-sm" title="Remove">✕</button>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="flex justify-between text-sm" x-show="walletApplied > 0">
                    <span class="text-slate-500">Remaining due</span>
                    <span class="font-semibold text-slate-700" x-text="money(remainderDue)"></span>
                </div>
                <div class="flex justify-between text-sm" x-show="changeDue > 0">
                    <span class="text-slate-500">Change due</span>
                    <span class="font-semibold text-slate-700" x-text="money(changeDue)"></span>
                </div>
                <div class="flex justify-between text-sm font-semibold text-amber-600" x-show="balanceOwing > 0">
                    <span>Balance owing</span>
                    <span x-text="money(balanceOwing)"></span>
                </div>
                <p x-show="creditNeedsCustomer" x-cloak class="text-xs text-red-500">Select a customer to record a credit sale.</p>
                <div class="flex justify-between text-sm font-semibold text-red-600" x-show="cart.length && !fullyPaid">
                    <span>Amount due</span>
                    <span x-text="money(amountDue)"></span>
                </div>
                <div class="flex justify-between text-xs text-amber-600" x-show="pointsToEarn > 0">
                    <span>Points earned</span>
                    <span x-text="'+' + pointsToEarn"></span>
                </div>

                <button type="button" @click="submit()"
                        :disabled="!canSubmit"
                        class="w-full rounded-md bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed">
                    <span x-show="!submitting && fullyPaid && balanceOwing <= 0">Complete sale · <span x-text="money(total)"></span></span>
                    <span x-show="!submitting && fullyPaid && balanceOwing > 0">Complete · <span x-text="money(balanceOwing)"></span> on credit</span>
                    <span x-show="!submitting && !fullyPaid">Enter full payment</span>
                    <span x-show="submitting">Processing…</span>
                </button>
            </div>
            </div>{{-- /scrollable cart body --}}
        </div>
    </div>

    {{-- Hidden POST form submitted via JS --}}
    <form x-ref="checkoutForm" method="POST" action="{{ route('pos.checkout') }}" class="hidden">
        @csrf
        <input type="hidden" name="register_id" value="{{ $register?->id }}">
        <input type="hidden" name="shift_id" value="{{ $openShift?->id }}">
        <input type="hidden" name="customer_id" x-bind:value="customerId">
        <input type="hidden" name="discount" x-bind:value="cartDiscount || 0">
        <input type="hidden" name="points_redeemed" x-bind:value="effectivePoints">
        <template x-for="(line, i) in cart" :key="'f'+line.id">
            <div>
                <input type="hidden" :name="'items['+i+'][product_id]'" :value="line.id">
                <input type="hidden" :name="'items['+i+'][quantity]'" :value="line.qty">
                <input type="hidden" :name="'items['+i+'][unit_price]'" :value="line.price">
                <input type="hidden" :name="'items['+i+'][discount]'" :value="line.discount || 0">
            </div>
        </template>
        <template x-for="(pay, i) in paymentsList" :key="'p'+i">
            <div>
                <input type="hidden" :name="'payments['+i+'][method]'" :value="pay.method">
                <input type="hidden" :name="'payments['+i+'][amount]'" :value="pay.amount">
            </div>
        </template>
    </form>
</div>

@push('scripts')
<script>
function posRegister() {
    return {
        products: @json($products),
        customers: @json($customers),
        loyalty: {
            active: {{ $loyalty->is_active ? 'true' : 'false' }},
            redeemValue: {{ (float) $loyalty->redeem_value }},
            earnRate: {{ (float) $loyalty->earn_rate }},
            minRedeem: {{ (int) $loyalty->min_redeem_points }},
        },
        cart: [],
        search: '',
        scanError: '',
        customerId: '',
        cartDiscount: 0,
        redeemPoints: 0,
        useWallet: 0,
        // Split tender: one or more payment lines, each { method, amount }.
        // A method may appear at most once across the lines.
        payMethods: @json(array_values(array_column($payMethods, 'key'))),
        payMethodLabels: @json(array_column($payMethods, 'label', 'key')),
        creditMethods: @json(array_values($creditMethods)),
        tenders: [{ method: @json($payMethods[0]['key'] ?? 'cash'), amount: null }],
        submitting: false,

        init() {
            // Keep the scan field focused so a hardware scanner (keyboard wedge) just works.
            this.$nextTick(() => this.$refs.scan?.focus());
        },

        get customer() {
            return this.customers.find(c => c.id === this.customerId) || null;
        },
        onCustomerChange() {
            // Reset wallet/loyalty inputs when the customer changes.
            this.redeemPoints = 0;
            this.useWallet = 0;
        },

        get filteredProducts() {
            const t = this.search.trim().toLowerCase();
            if (!t) return this.products;
            return this.products.filter(p =>
                (p.name && p.name.toLowerCase().includes(t)) ||
                (p.sku && p.sku.toLowerCase().includes(t)) ||
                (p.barcode && String(p.barcode).toLowerCase().includes(t))
            );
        },
        // Enter / scanner: prefer an EXACT barcode or SKU match, else the first fuzzy match.
        scanOrAddFirst() {
            const t = this.search.trim();
            if (!t) return;
            const low = t.toLowerCase();
            let p = this.products.find(p => p.barcode && String(p.barcode).toLowerCase() === low)
                 || this.products.find(p => p.sku && p.sku.toLowerCase() === low)
                 || this.filteredProducts[0];
            if (p) {
                this.addToCart(p);
                this.search = '';
                this.scanError = '';
            } else {
                this.scanError = 'No product found for "' + t + '".';
            }
            this.$refs.scan?.focus();
        },
        // A tracked product with no quantity on hand can't be sold (mirrors the server guard).
        isOut(p) { return p.track_stock && p.stock !== null && p.stock <= 0; },
        addToCart(p) {
            if (this.isOut(p)) { this.scanError = p.name + ' is out of stock.'; return; }
            const existing = this.cart.find(l => l.id === p.id);
            if (existing) { existing.qty = Math.round((existing.qty + 1) * 1000) / 1000; return; }
            this.cart.push({ id: p.id, name: p.name, price: p.price, tax_rate: p.tax_rate, qty: 1, discount: 0 });
        },
        inc(line) { line.qty = Math.round((line.qty + 1) * 1000) / 1000; },
        dec(line) { line.qty = Math.max(0, Math.round((line.qty - 1) * 1000) / 1000); },
        removeLine(i) { this.cart.splice(i, 1); },
        clearCart() {
            this.cart = []; this.cartDiscount = 0;
            this.tenders = [{ method: this.payMethods[0] || 'cash', amount: null }];
            this.redeemPoints = 0; this.useWallet = 0;
        },

        // --- Split-tender payment lines (each method usable only once) ---
        methodLabel(m) { return this.payMethodLabels[m] || (m.charAt(0).toUpperCase() + m.slice(1)); },
        // True when method `m` is already used by a DIFFERENT line than `t`.
        methodTaken(t, m) {
            return m !== t.method && this.tenders.some(x => x !== t && x.method === m);
        },
        // Reject a duplicate selection: if this line's method collides with
        // another line, snap it to the first method not used elsewhere.
        enforceUniqueMethod(t) {
            const usedByOthers = this.tenders.filter(x => x !== t).map(x => x.method);
            if (usedByOthers.includes(t.method)) {
                const free = this.payMethods.find(m => !usedByOthers.includes(m));
                if (free) t.method = free;
            }
        },
        get canAddTender() {
            return this.tenders.length < this.payMethods.length;
        },
        addTender() {
            const used = this.tenders.map(t => t.method);
            const next = this.payMethods.find(m => !used.includes(m));
            if (next) this.tenders.push({ method: next, amount: null });
        },
        removeTender(i) {
            this.tenders.splice(i, 1);
            if (this.tenders.length === 0) this.addTender();
        },
        // Top this line up so the whole sale is covered (assumes cash for any change).
        fillDue(t) {
            t.amount = Math.round(((parseFloat(t.amount) || 0) + this.amountDue) * 100) / 100;
        },

        lineNet(line) {
            return Math.max(0, (line.price * line.qty) - (parseFloat(line.discount) || 0));
        },
        lineTotal(line) {
            const net = this.lineNet(line);
            return Math.round((net + net * line.tax_rate) * 100) / 100;
        },
        get subtotal() {
            return Math.round(this.cart.reduce((s, l) => s + (l.price * l.qty), 0) * 100) / 100;
        },
        get taxTotal() {
            return Math.round(this.cart.reduce((s, l) => s + this.lineNet(l) * l.tax_rate, 0) * 100) / 100;
        },
        get lineDiscountTotal() {
            return this.cart.reduce((s, l) => s + (parseFloat(l.discount) || 0), 0);
        },
        get discountBeforeLoyalty() {
            return Math.round((this.lineDiscountTotal + (parseFloat(this.cartDiscount) || 0)) * 100) / 100;
        },
        get netBeforeLoyalty() {
            return Math.max(0, Math.round((this.subtotal - this.discountBeforeLoyalty) * 100) / 100);
        },

        // --- Loyalty redemption ---
        get canRedeem() {
            return this.loyalty.active && this.customer
                && this.customer.points >= this.loyalty.minRedeem
                && this.loyalty.minRedeem >= 0
                && this.customer.points > 0
                && this.loyalty.redeemValue > 0;
        },
        get maxRedeemablePoints() {
            if (!this.canRedeem) return 0;
            const byValue = Math.floor(this.netBeforeLoyalty / this.loyalty.redeemValue);
            return Math.max(0, Math.min(this.customer.points, byValue));
        },
        get effectivePoints() {
            if (!this.canRedeem) return 0;
            const want = Math.max(0, Math.floor(parseFloat(this.redeemPoints) || 0));
            return Math.min(want, this.maxRedeemablePoints);
        },
        get loyaltyDiscount() {
            return Math.round(this.effectivePoints * this.loyalty.redeemValue * 100) / 100;
        },
        get discountTotal() {
            return Math.round((this.discountBeforeLoyalty + this.loyaltyDiscount) * 100) / 100;
        },
        get netRevenue() {
            return Math.max(0, Math.round((this.subtotal - this.discountTotal) * 100) / 100);
        },
        get total() {
            return Math.round((this.netRevenue + this.taxTotal) * 100) / 100;
        },
        get pointsToEarn() {
            if (!this.loyalty.active || !this.customer) return 0;
            return Math.floor(this.netRevenue * this.loyalty.earnRate);
        },

        // --- Wallet + remaining payment ---
        get maxWallet() {
            if (!this.customer) return 0;
            return Math.min(this.customer.balance, this.total);
        },
        get walletApplied() {
            if (!this.customer) return 0;
            const want = Math.max(0, parseFloat(this.useWallet) || 0);
            return Math.round(Math.min(want, this.maxWallet) * 100) / 100;
        },
        get remainderDue() {
            return Math.max(0, Math.round((this.total - this.walletApplied) * 100) / 100);
        },
        isCredit(method) { return this.creditMethods.includes(method); },
        // Real money tendered now (excludes credit tenders, which are owed not paid).
        get tenderedTotal() {
            return Math.round(this.tenders.reduce((s, t) =>
                s + (this.isCredit(t.method) ? 0 : (parseFloat(t.amount) || 0)), 0) * 100) / 100;
        },
        // Amount the cashier put on credit (an IOU, not money received).
        get creditTendered() {
            return Math.round(this.tenders.reduce((s, t) =>
                s + (this.isCredit(t.method) ? (parseFloat(t.amount) || 0) : 0), 0) * 100) / 100;
        },
        // Total collected now = wallet + real tender lines.
        get amountPaid() {
            return Math.round((this.walletApplied + this.tenderedTotal) * 100) / 100;
        },
        // The portion left owing = unpaid total, capped to what was put on credit.
        get balanceOwing() {
            return Math.round(Math.min(this.creditTendered, Math.max(0, this.total - this.amountPaid)) * 100) / 100;
        },
        // Sale is covered once real payment + credit meets the total.
        get coveredTotal() {
            return Math.round((this.amountPaid + this.creditTendered) * 100) / 100;
        },
        get amountDue() {
            return Math.max(0, Math.round((this.total - this.coveredTotal) * 100) / 100);
        },
        // Change only comes from real over-payment, never from a credit tender.
        get changeDue() {
            return Math.max(0, Math.round((this.amountPaid - (this.total - this.balanceOwing)) * 100) / 100);
        },
        get fullyPaid() {
            return this.coveredTotal + 0.001 >= this.total;
        },
        // A credit sale (balance owing) must be tied to a customer.
        get creditNeedsCustomer() {
            return this.balanceOwing > 0.001 && !this.customerId;
        },
        // A sale can only be submitted once it is fully covered (paid and/or on credit).
        get canSubmit() {
            if (this.cart.length === 0 || this.submitting) return false;
            return this.fullyPaid && !this.creditNeedsCustomer;
        },
        get paymentsList() {
            const list = [];
            if (this.walletApplied > 0) list.push({ method: 'wallet', amount: this.walletApplied });
            for (const t of this.tenders) {
                const amount = Math.round((parseFloat(t.amount) || 0) * 100) / 100;
                if (amount > 0) list.push({ method: t.method, amount });
            }
            return list;
        },

        money(v) {
            const n = Math.round((v || 0) * 100) / 100;
            // Group thousands with two decimals, e.g. N1,234.50
            return '{{ $symbol }}' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        submit() {
            if (!this.canSubmit) return;
            if (this.cart.some(l => l.qty <= 0)) { alert('Every line needs a quantity greater than zero.'); return; }
            if (this.creditNeedsCustomer) { alert('Select a customer to record a credit sale.'); return; }
            if (!this.fullyPaid) { alert('The sale must be fully covered. Still uncovered: ' + this.money(this.amountDue)); return; }
            this.submitting = true;
            this.$nextTick(() => this.$refs.checkoutForm.submit());
        },
    };
}
</script>
@endpush
@endsection
