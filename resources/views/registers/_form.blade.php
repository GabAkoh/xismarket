@php $register = $register ?? null; @endphp
<x-card>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700">Name</label>
            <input name="name" value="{{ old('name', $register->name ?? '') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Code</label>
            <input name="code" value="{{ old('code', $register->code ?? '') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Warehouse</label>
            <select name="warehouse_id" class="mt-1 w-full rounded-md border border-slate-300 p-2">
                <option value="">— None —</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected((int) old('warehouse_id', $register->warehouse_id ?? 0) === $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $register->is_active ?? true))>
                Active
            </label>
        </div>
    </div>
</x-card>
