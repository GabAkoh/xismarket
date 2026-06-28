@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp
<x-card x-data="{ type: '{{ old('type', $coupon->type ?? 'percent') }}' }">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700">Code</label>
            <input name="code" value="{{ old('code', $coupon->code ?? '') }}" required maxlength="40"
                   placeholder="e.g. WELCOME10" class="mt-1 w-full rounded-md border border-slate-300 p-2 uppercase">
            @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Type</label>
            <select name="type" x-model="type" class="mt-1 w-full rounded-md border border-slate-300 p-2">
                <option value="percent">Percentage off</option>
                <option value="fixed">Fixed amount off</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">
                <span x-show="type === 'percent'">Percent off (%)</span>
                <span x-show="type === 'fixed'" x-cloak>Amount off ({{ $symbol }})</span>
            </label>
            <input type="number" name="value" value="{{ old('value', $coupon->value ?? '') }}" required min="0" step="0.01"
                   class="mt-1 w-full rounded-md border border-slate-300 p-2">
            @error('value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Minimum order ({{ $symbol }}, optional)</label>
            <input type="number" name="min_order" value="{{ old('min_order', $coupon->min_order ?? '') }}" min="0" step="0.01"
                   placeholder="0" class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Usage limit (optional)</label>
            <input type="number" name="usage_limit" value="{{ old('usage_limit', $coupon->usage_limit ?? '') }}" min="1" step="1"
                   placeholder="Unlimited" class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Expires on (optional)</label>
            <input type="date" name="expires_at" value="{{ old('expires_at', optional($coupon->expires_at ?? null)->format('Y-m-d')) }}"
                   class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
    </div>
    <label class="mt-4 flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon->is_active ?? true))>
        Active
    </label>
</x-card>
