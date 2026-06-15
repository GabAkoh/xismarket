<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Name</label>
        <input name="name" value="{{ old('name', $supplier->name ?? '') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Email</label>
        <input name="email" type="email" value="{{ old('email', $supplier->email ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Phone</label>
        <input name="phone" value="{{ old('phone', $supplier->phone ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Address</label>
        <input name="address" value="{{ old('address', $supplier->address ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
</div>
