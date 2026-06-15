@php $customer = $customer ?? null; @endphp
<x-card>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700">Name</label>
            <input name="name" value="{{ old('name', $customer->name ?? '') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input name="email" type="email" value="{{ old('email', $customer->email ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">ID / Tax number</label>
            <input name="identity_number" value="{{ old('identity_number', $customer->identity_number ?? '') }}" placeholder="National ID, passport or tax no." class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Phone</label>
            <input name="phone" value="{{ old('phone', $customer->phone ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Address</label>
            <input name="address" value="{{ old('address', $customer->address ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
    </div>
    <div class="mt-4">
        <label class="block text-sm font-medium text-slate-700">Notes</label>
        <textarea name="notes" rows="3" class="mt-1 w-full rounded-md border border-slate-300 p-2">{{ old('notes', $customer->notes ?? '') }}</textarea>
    </div>
</x-card>
