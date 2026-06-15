<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-slate-700">Name</label>
        <input name="name" value="{{ old('name', $tax->name ?? '') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Rate (%)</label>
        <input name="rate" type="number" step="0.0001" min="0" max="100" value="{{ old('rate', $tax->rate ?? '') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
</div>

<div class="mt-5">
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $tax->is_active ?? true))>
        Active
    </label>
</div>
