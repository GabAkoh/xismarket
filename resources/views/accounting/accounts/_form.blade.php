<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-slate-700">Code</label>
        <input name="code" value="{{ old('code', $account->code ?? '') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2 font-mono">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Name</label>
        <input name="name" value="{{ old('name', $account->name ?? '') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Type</label>
        <select name="type" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(old('type', $account->type ?? '') === $type)>{{ ucfirst($type) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Subtype <span class="text-slate-400">(optional)</span></label>
        <input name="subtype" value="{{ old('subtype', $account->subtype ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
</div>

<div class="mt-5">
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active ?? true))>
        Active
    </label>
</div>
