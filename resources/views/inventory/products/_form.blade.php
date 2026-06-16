<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-slate-700">Name</label>
        <input name="name" value="{{ old('name', $product->name ?? '') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">SKU</label>
        <input name="sku" value="{{ old('sku', $product->sku ?? '') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Barcode</label>
        <input name="barcode" value="{{ old('barcode', $product->barcode ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Category</label>
        <select name="category_id" class="mt-1 w-full rounded-md border border-slate-300 p-2">
            <option value="">— None —</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id ?? '') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Cost price ({{ $currentTenant->currencySymbol() }})</label>
        <input name="cost_price" type="number" step="0.01" min="0" value="{{ old('cost_price', $product->cost_price ?? '0.00') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Sale price ({{ $currentTenant->currencySymbol() }})</label>
        <input name="sale_price" type="number" step="0.01" min="0" value="{{ old('sale_price', $product->sale_price ?? '0.00') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Tax rate (%)</label>
        <input name="tax_rate" type="number" step="0.0001" min="0" value="{{ old('tax_rate', $product->tax_rate ?? '0') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </div>
</div>

<div class="mt-4">
    <label class="block text-sm font-medium text-slate-700">Description</label>
    <textarea name="description" rows="3" class="mt-1 w-full rounded-md border border-slate-300 p-2">{{ old('description', $product->description ?? '') }}</textarea>
</div>

<div class="mt-4">
    <label class="block text-sm font-medium text-slate-700">Image</label>
    @if (! empty($product->image_path))
        <div class="mt-2 flex items-center gap-3">
            <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" class="h-16 w-16 rounded-md border border-slate-200 object-cover">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remove_image" value="1">
                Remove current image
            </label>
        </div>
    @endif
    <input type="file" name="image" accept="image/*" class="mt-2 w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
    <p class="mt-1 text-xs text-slate-400">JPG, PNG, WEBP or GIF up to 2&nbsp;MB.</p>
    @error('image')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div class="mt-4 flex flex-wrap gap-6">
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="track_stock" value="1" @checked(old('track_stock', $product->track_stock ?? true))>
        Track stock
    </label>
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))>
        Active
    </label>
</div>
