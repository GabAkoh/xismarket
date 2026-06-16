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

<div class="mt-4" x-data="productImageForm()">
    <label class="block text-sm font-medium text-slate-700">Image</label>

    {{-- Current image (edit mode) — hidden once a new one is chosen/captured --}}
    @if (! empty($product->image_path))
        <div class="mt-2 flex items-center gap-3" x-show="!preview">
            <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" class="h-16 w-16 rounded-md border border-slate-200 object-cover">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remove_image" value="1" x-ref="remove">
                Remove current image
            </label>
        </div>
    @endif

    {{-- The actual upload field that submits with the form (set by file picker or camera) --}}
    <input type="file" name="image" accept="image/*" x-ref="file" @change="onFileChange()" class="hidden">

    {{-- Preview of a newly chosen/captured image (editable: clear / retake) --}}
    <template x-if="preview">
        <div class="mt-2 flex items-center gap-3">
            <img :src="preview" alt="New image preview" class="h-20 w-20 rounded-md border border-slate-200 object-cover">
            <button type="button" @click="clear()" class="text-sm text-red-600 hover:underline">Clear</button>
        </div>
    </template>

    {{-- Live camera --}}
    <div x-show="capturing" x-cloak class="mt-2">
        <video x-ref="video" autoplay playsinline muted class="w-full max-w-xs rounded-md border border-slate-200 bg-black"></video>
        <div class="mt-2 flex gap-2">
            <button type="button" @click="capture()" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Capture</button>
            <button type="button" @click="stopCamera()" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">Cancel</button>
        </div>
    </div>

    {{-- Actions --}}
    <div class="mt-2 flex flex-wrap gap-2" x-show="!capturing">
        <button type="button" @click="pickFile()" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Choose file</button>
        <button type="button" @click="startCamera()" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">📷 Use camera</button>
    </div>

    <canvas x-ref="canvas" class="hidden"></canvas>
    <p class="mt-1 text-xs text-slate-400">Choose a file or take a photo with your camera. JPG, PNG, WEBP or GIF up to 2&nbsp;MB.</p>
    <p x-show="error" x-cloak class="mt-1 text-xs text-red-600" x-text="error"></p>
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

@push('scripts')
<script>
function productImageForm() {
    return {
        capturing: false,
        preview: null,   // object URL for a chosen/captured image
        stream: null,
        error: '',

        // --- File picker ---
        pickFile() { this.$refs.file.click(); },
        onFileChange() { this.setPreview(this.$refs.file.files[0] || null); },

        // --- Live camera (getUserMedia) ---
        async startCamera() {
            this.error = '';
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.error = 'Camera is not available here (needs a secure https connection or a supported device).';
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' }, audio: false,
                });
                this.capturing = true;
                this.$nextTick(() => { this.$refs.video.srcObject = this.stream; });
            } catch (e) {
                this.error = 'Could not access the camera. ' + (e && e.message ? e.message : '');
            }
        },
        capture() {
            const video = this.$refs.video, canvas = this.$refs.canvas;
            // Downscale to keep the JPEG comfortably under the 2 MB upload limit.
            const maxDim = 1600;
            const scale = Math.min(1, maxDim / Math.max(video.videoWidth, video.videoHeight));
            const w = Math.round(video.videoWidth * scale);
            const h = Math.round(video.videoHeight * scale);
            canvas.width = w; canvas.height = h;
            canvas.getContext('2d').drawImage(video, 0, 0, w, h);
            canvas.toBlob((blob) => {
                if (!blob) { this.error = 'Capture failed, please try again.'; return; }
                const file = new File([blob], 'product-photo.jpg', { type: 'image/jpeg' });
                const dt = new DataTransfer();
                dt.items.add(file);
                this.$refs.file.files = dt.files;          // submits with the form
                if (this.$refs.remove) this.$refs.remove.checked = false;
                this.setPreview(file);
                this.stopCamera();
            }, 'image/jpeg', 0.85);
        },
        stopCamera() {
            this.capturing = false;
            if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
        },

        // --- Preview helpers ---
        setPreview(file) {
            if (this.preview) URL.revokeObjectURL(this.preview);
            this.preview = file ? URL.createObjectURL(file) : null;
        },
        clear() {
            this.$refs.file.value = '';
            this.setPreview(null);
        },
    };
}
</script>
@endpush
