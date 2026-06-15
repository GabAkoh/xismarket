<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-slate-700">Name</label>
        <input name="name" value="{{ old('name', $warehouse->name ?? '') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Code</label>
        <input name="code" value="{{ old('code', $warehouse->code ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Address</label>
        <input name="address" value="{{ old('address', $warehouse->address ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
</div>

<div class="mt-4">
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $warehouse->is_default ?? false))>
        Set as default warehouse
    </label>
</div>
