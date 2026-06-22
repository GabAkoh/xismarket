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
    <div>
        <label class="block text-sm font-medium text-slate-700">Reorder level</label>
        <input name="reorder_level" type="number" step="0.001" min="0" value="{{ old('reorder_level', $reorderLevel ?? '') }}" placeholder="0" class="mt-1 w-full rounded-md border border-slate-300 p-2">
        <p class="mt-1 text-xs text-slate-400">Flag for reorder when stock drops to this level. Leave blank for none.</p>
    </div>
</div>

<div class="mt-4">
    <label class="block text-sm font-medium text-slate-700">Description</label>
    <textarea name="description" rows="3" class="mt-1 w-full rounded-md border border-slate-300 p-2">{{ old('description', $product->description ?? '') }}</textarea>
</div>

<div class="mt-4" x-data="productImageEditor()" data-store="{{ $currentTenant->name ?? '' }}">
    <label class="block text-sm font-medium text-slate-700">Image</label>

    {{-- Current image (edit mode) — editable / removable until a new one is set --}}
    @if (! empty($product->image_path))
        <div class="mt-2 flex items-center gap-3" x-show="!preview && !editing">
            <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" class="h-16 w-16 rounded-md border border-slate-200 object-cover">
            <button type="button" @click="editExisting('{{ asset('storage/'.$product->image_path) }}')" class="text-sm text-indigo-600 hover:underline">Edit</button>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remove_image" value="1" x-ref="remove">
                Remove
            </label>
        </div>
    @endif

    {{-- The field that submits with the form (set by the editor) + a separate source picker --}}
    <input type="file" name="image" accept="image/*" x-ref="file" class="hidden">
    <input type="file" accept="image/*" x-ref="source" @change="onSource()" class="hidden">

    {{-- Final preview of the edited image --}}
    <template x-if="preview && !editing">
        <div class="mt-2 flex items-center gap-3">
            <img :src="preview" alt="New image preview" class="h-20 w-20 rounded-md border border-slate-200 object-cover">
            <button type="button" @click="reedit()" class="text-sm text-indigo-600 hover:underline">Edit</button>
            <button type="button" @click="clear()" class="text-sm text-red-600 hover:underline">Clear</button>
        </div>
    </template>

    {{-- Live camera --}}
    <div x-show="capturing" x-cloak class="mt-2">
        <video x-ref="video" autoplay playsinline muted class="w-full max-w-sm rounded-md border border-slate-200 bg-black"></video>
        <div class="mt-2 flex gap-2">
            <button type="button" @click="capture()" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Capture</button>
            <button type="button" @click="stopCamera()" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">Cancel</button>
        </div>
    </div>

    {{-- ===== Image editor ===== --}}
    <div x-show="editing" x-cloak class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 max-w-lg">
        {{-- Stage 1: crop / rotate / zoom --}}
        <div x-show="stage === 'crop'">
            <div class="bg-white"><img x-ref="cropImg" class="block max-w-full" style="max-height:320px"></div>
            <div class="mt-2 flex flex-wrap gap-2">
                <button type="button" @click="rotate(-90)" class="rounded-md border border-slate-300 px-2.5 py-1 text-sm hover:bg-white">⟲ Rotate</button>
                <button type="button" @click="rotate(90)" class="rounded-md border border-slate-300 px-2.5 py-1 text-sm hover:bg-white">⟳ Rotate</button>
                <button type="button" @click="zoom(0.1)" class="rounded-md border border-slate-300 px-2.5 py-1 text-sm hover:bg-white">＋ Zoom</button>
                <button type="button" @click="zoom(-0.1)" class="rounded-md border border-slate-300 px-2.5 py-1 text-sm hover:bg-white">－ Zoom</button>
                <button type="button" @click="setAspect(null)" class="rounded-md border border-slate-300 px-2.5 py-1 text-sm hover:bg-white" :class="aspect ? '' : 'ring-2 ring-indigo-400'">Free</button>
                <button type="button" @click="setAspect(1)" class="rounded-md border border-slate-300 px-2.5 py-1 text-sm hover:bg-white" :class="aspect === 1 ? 'ring-2 ring-indigo-400' : ''">1:1</button>
            </div>
            <div class="mt-2 flex gap-2">
                <button type="button" @click="applyCrop()" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Next →</button>
                <button type="button" @click="cancelEdit()" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">Cancel</button>
            </div>
        </div>
        {{-- Stage 2: adjust / background / watermark --}}
        <div x-show="stage === 'adjust'">
            <div class="flex justify-center" style="background:#fff;background-image:linear-gradient(45deg,#eee 25%,transparent 25%),linear-gradient(-45deg,#eee 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#eee 75%),linear-gradient(-45deg,transparent 75%,#eee 75%);background-size:16px 16px;background-position:0 0,0 8px,8px -8px,-8px 0;">
                <canvas x-ref="out" class="block max-w-full" style="max-height:320px"></canvas>
            </div>
            <div class="mt-3 space-y-2 text-sm">
                <label class="flex items-center gap-2"><span class="w-20 text-slate-600">Brightness</span><input type="range" min="0" max="200" x-model.number="adj.brightness" @input="render()" class="flex-1"></label>
                <label class="flex items-center gap-2"><span class="w-20 text-slate-600">Contrast</span><input type="range" min="0" max="200" x-model.number="adj.contrast" @input="render()" class="flex-1"></label>
                <label class="flex items-center gap-2"><span class="w-20 text-slate-600">Saturation</span><input type="range" min="0" max="200" x-model.number="adj.saturate" @input="render()" class="flex-1"></label>
                <label class="flex items-center gap-2"><input type="checkbox" x-model="bg.on" @change="render()"><span class="text-slate-600">Remove / replace background</span></label>
                <label class="flex items-center gap-2" x-show="bg.on"><span class="w-20 text-slate-600">Tolerance</span><input type="range" min="0" max="180" x-model.number="bg.tol" @input="render()" class="flex-1"></label>
                <div x-show="bg.on" class="flex items-center gap-2 flex-wrap">
                    <span class="w-20 text-slate-600">Backdrop</span>
                    <button type="button" @click="backdrop='transparent'; render()" class="rounded border px-2 py-0.5 text-xs" :class="backdrop==='transparent' ? 'ring-2 ring-indigo-400' : 'border-slate-300'">None</button>
                    <button type="button" @click="backdrop='white'; render()" class="rounded border px-2 py-0.5 text-xs" :class="backdrop==='white' ? 'ring-2 ring-indigo-400' : 'border-slate-300'">White</button>
                    <button type="button" @click="backdrop='studio'; render()" class="rounded border px-2 py-0.5 text-xs" :class="backdrop==='studio' ? 'ring-2 ring-indigo-400' : 'border-slate-300'">Studio</button>
                    <button type="button" @click="backdrop='color'; render()" class="rounded border px-2 py-0.5 text-xs" :class="backdrop==='color' ? 'ring-2 ring-indigo-400' : 'border-slate-300'">Colour</button>
                    <input type="color" x-show="backdrop==='color'" x-model="bgColor" @input="render()" class="h-6 w-8 rounded border border-slate-300 p-0">
                </div>
                <label class="flex items-center gap-2"><input type="checkbox" x-model="wm.on" @change="render()"><span class="text-slate-600">Watermark</span></label>
                <input type="text" x-show="wm.on" x-model="wm.text" @input="render()" placeholder="Watermark text" class="w-full rounded-md border border-slate-300 p-1.5 text-sm">
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" @click="resetAdjust()" class="rounded-md border border-slate-300 px-2.5 py-1 text-sm hover:bg-white">Reset</button>
                <button type="button" @click="backToCrop()" class="rounded-md border border-slate-300 px-2.5 py-1 text-sm hover:bg-white">← Crop</button>
                <button type="button" @click="save('cover')" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Save as cover</button>
                <button type="button" @click="save('gallery')" class="rounded-md border border-indigo-300 bg-white px-3 py-1.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">+ Add to gallery</button>
                <button type="button" @click="cancelEdit()" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">Cancel</button>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="mt-2 flex flex-wrap gap-2" x-show="!capturing && !editing">
        <button type="button" @click="pickFile()" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Choose file</button>
        <button type="button" @click="startCamera()" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">📷 Use camera</button>
    </div>

    <canvas x-ref="cap" class="hidden"></canvas>
    <p class="mt-1 text-xs text-slate-400">Choose a file or take a photo, then crop, rotate, adjust, remove the background, or add a watermark. Up to 8&nbsp;MB.</p>
    <p x-show="error" x-cloak class="mt-1 text-xs text-red-600" x-text="error"></p>
    @error('image')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

{{-- ===== Gallery: additional images (cascading angles / variants / lifestyle) ===== --}}
@php
    $aiUrl = isset($product) && $product->exists ? route('products.ai-image', $product) : null;
    $aiConfigured = app(\App\Services\Images\ImageGenerator::class)->configured();
@endphp
<div class="mt-5" x-data="productGallery({
        existing: @js(isset($product) ? $product->images->map(fn ($i) => ['id' => $i->id, 'url' => $i->url()])->values() : []),
        aiUrl: @js($aiUrl), aiConfigured: @js($aiConfigured),
     })">
    <label class="block text-sm font-medium text-slate-700">More images (gallery)</label>
    <p class="text-xs text-slate-400 mb-2">Extra angles, side/back views, colour variants and lifestyle shots — shown on the storefront product page. Drag to reorder.</p>

    {{-- Existing gallery images (edit mode) --}}
    <div class="flex flex-wrap gap-3" x-show="items.length">
        <template x-for="(it, i) in items" :key="it.id">
            <div class="relative" draggable="true"
                 @dragstart="drag = i" @dragover.prevent @drop="drop(i)"
                 :class="it.remove ? 'opacity-40' : ''">
                <img :src="it.url" class="h-20 w-20 rounded-md border border-slate-200 object-cover cursor-move">
                <button type="button" @click="it.remove = !it.remove"
                        class="absolute -top-2 -right-2 h-5 w-5 rounded-full bg-white border border-slate-300 text-xs leading-none shadow"
                        :title="it.remove ? 'Keep' : 'Remove'" x-text="it.remove ? '↩' : '✕'"></button>
                <button type="button" @click="makeCover(it.id)"
                        class="absolute bottom-0 inset-x-0 bg-black/55 text-white text-[10px] py-0.5 rounded-b-md hover:bg-indigo-600"
                        :class="cover === it.id ? 'bg-indigo-600' : ''"
                        title="Use as the main cover image" x-text="cover === it.id ? '✓ Cover' : 'Make cover'"></button>
            </div>
        </template>
    </div>

    {{-- New uploads preview --}}
    <div class="flex flex-wrap gap-3 mt-3" x-show="newPreviews.length">
        <template x-for="(src, i) in newPreviews" :key="'n'+i">
            <img :src="src" class="h-20 w-20 rounded-md border border-dashed border-indigo-300 object-cover">
        </template>
    </div>

    <div class="mt-3">
        <button type="button" @click="$refs.gallery.click()" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">+ Add images</button>
        <input type="file" name="gallery[]" accept="image/*" multiple x-ref="gallery" @change="onAdd()" class="hidden">
    </div>

    {{-- ===== AI image tools (edit mode only) ===== --}}
    @if ($aiUrl)
        <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50/40 p-3">
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-indigo-700">✨ AI image tools</span>
                <span x-show="aiBusy" x-cloak class="text-xs text-slate-500" x-text="'Generating ' + aiBusy + '…'"></span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5">Runs on the cover image (or first gallery image) and adds the result here.</p>

            @unless ($aiConfigured)
                <p class="mt-2 text-xs text-amber-700 bg-amber-100 rounded px-2 py-1">
                    Not configured yet — set <code>IMAGE_AI_KEY</code> (and optionally <code>IMAGE_AI_PROVIDER</code>) in your environment to enable generation.
                </p>
            @endunless

            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3" :class="aiConfigured ? '' : 'opacity-60 pointer-events-none'">
                {{-- Background --}}
                <div class="flex items-center gap-2">
                    <input x-model="ai.background" placeholder="white" class="w-24 rounded-md border border-slate-300 p-1.5 text-sm">
                    <button type="button" @click="runAi('background', ai.background)" :disabled="aiBusy" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-40">Plain background</button>
                </div>
                {{-- Colour variant --}}
                <div class="flex items-center gap-2">
                    <input x-model="ai.color" placeholder="navy blue" class="w-28 rounded-md border border-slate-300 p-1.5 text-sm">
                    <button type="button" @click="runAi('color', ai.color)" :disabled="aiBusy || !ai.color" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-40">Recolour</button>
                </div>
                {{-- Side / angle --}}
                <div class="flex items-center gap-2">
                    <select x-model="ai.angle" class="rounded-md border border-slate-300 p-1.5 text-sm">
                        <option value="side">Side</option><option value="back">Back</option>
                        <option value="three-quarter">3/4</option><option value="top-down">Top-down</option>
                    </select>
                    <button type="button" @click="runAi('angle', ai.angle)" :disabled="aiBusy" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-40">Add angle view</button>
                </div>
                {{-- Model shot --}}
                <div class="flex items-center gap-2">
                    <button type="button" @click="runAi('model', '')" :disabled="aiBusy" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-40">Lifestyle / model shot</button>
                </div>
            </div>
            <p x-show="aiError" x-cloak class="mt-2 text-xs text-red-600" x-text="aiError"></p>
        </div>
    @endif

    {{-- Hidden state submitted with the form --}}
    <template x-for="it in items.filter(x => x.remove)" :key="'r'+it.id"><input type="hidden" name="remove_gallery[]" :value="it.id"></template>
    <input type="hidden" name="gallery_order" :value="items.filter(x => !x.remove).map(x => x.id).join(',')">
    <input type="hidden" name="make_cover" :value="cover || ''">
    @error('gallery.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

@push('scripts')
<script>
function productGallery(cfg) {
    return {
        items: (cfg.existing || []).map(x => ({ ...x, remove: false })),
        newPreviews: [],
        drag: null,
        cover: '',
        aiUrl: cfg.aiUrl || null,
        aiConfigured: !!cfg.aiConfigured,
        aiBusy: '', aiError: '',
        ai: { background: 'white', color: '', angle: 'side' },
        init() {
            // The image editor can push an edited image straight into the gallery.
            window.addEventListener('gallery-add-file', (e) => this.addFile(e.detail));
        },
        async runAi(operation, detail) {
            if (this.aiBusy || !this.aiUrl) return;
            this.aiBusy = operation; this.aiError = '';
            try {
                const token = document.querySelector('input[name=_token]')?.value;
                const res = await fetch(this.aiUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ operation, detail: detail || '' }),
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) { this.aiError = json.message || ('Generation failed (' + res.status + ').'); return; }
                // The image is already saved to the gallery; show it here.
                this.items.push({ id: json.image.id, url: json.image.url, remove: false });
            } catch (e) {
                this.aiError = 'Generation failed: ' + (e && e.message ? e.message : e);
            } finally {
                this.aiBusy = '';
            }
        },
        addFile(file) {
            const input = this.$refs.gallery, dt = new DataTransfer();
            for (const f of input.files) dt.items.add(f);
            dt.items.add(file);
            input.files = dt.files;
            this.onAdd();
        },
        onAdd() {
            this.newPreviews = Array.from(this.$refs.gallery.files).map(f => URL.createObjectURL(f));
        },
        drop(i) {
            if (this.drag === null || this.drag === i) return;
            const moved = this.items.splice(this.drag, 1)[0];
            this.items.splice(i, 0, moved);
            this.drag = null;
        },
        makeCover(id) { this.cover = (this.cover === id ? '' : id); },
    };
}
</script>
@endpush

<div class="mt-4 flex flex-wrap gap-6">
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="track_stock" value="1" @checked(old('track_stock', $product->track_stock ?? true))>
        Track stock
    </label>
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))>
        Active
    </label>
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured ?? false))>
        Featured (pin to storefront bestsellers)
    </label>
</div>

@push('scripts')
<link rel="stylesheet" href="{{ asset('vendor/cropper/cropper.min.css') }}">
<script src="{{ asset('vendor/cropper/cropper.min.js') }}"></script>
<script>
function productImageEditor() {
    return {
        // capture/camera
        capturing: false, stream: null,
        // editor
        editing: false, stage: 'crop', cropper: null, aspect: null, _src: null, baseCanvas: null,
        adj: { brightness: 100, contrast: 100, saturate: 100 },
        bg: { on: false, tol: 60 },
        backdrop: 'transparent', bgColor: '#ffffff',
        wm: { on: false, text: '' },
        preview: null, error: '',

        init() { this.wm.text = this.$el.dataset.store || ''; },

        // --- Source selection ---
        pickFile() { this.$refs.source.click(); },
        onSource() {
            const f = this.$refs.source.files[0];
            if (f) this.openEditor(URL.createObjectURL(f));
            this.$refs.source.value = '';
        },
        editExisting(url) { this.openEditor(url); },
        reedit() { if (this.preview) this.openEditor(this.preview); },

        // --- Live camera ---
        async startCamera() {
            this.error = '';
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.error = 'Camera is not available here (needs a secure https connection or a supported device).';
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
                this.capturing = true;
                this.$nextTick(() => { this.$refs.video.srcObject = this.stream; });
            } catch (e) {
                this.error = 'Could not access the camera. ' + (e && e.message ? e.message : '');
            }
        },
        capture() {
            const video = this.$refs.video, c = this.$refs.cap;
            const maxDim = 1600;
            const scale = Math.min(1, maxDim / Math.max(video.videoWidth, video.videoHeight));
            c.width = Math.round(video.videoWidth * scale);
            c.height = Math.round(video.videoHeight * scale);
            c.getContext('2d').drawImage(video, 0, 0, c.width, c.height);
            const url = c.toDataURL('image/jpeg', 0.92);
            this.stopCamera();
            this.openEditor(url);
        },
        stopCamera() {
            this.capturing = false;
            if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
        },

        // --- Editor: crop stage ---
        openEditor(src) {
            this.error = '';
            this._src = src;
            this.editing = true;
            this.stage = 'crop';
            this.$nextTick(() => this.initCropper());
        },
        initCropper() {
            const img = this.$refs.cropImg;
            if (this.cropper) { this.cropper.destroy(); this.cropper = null; }
            img.src = this._src;
            this.cropper = new Cropper(img, {
                viewMode: 1, autoCropArea: 1, background: false, movable: true, zoomable: true,
                aspectRatio: this.aspect || NaN,
            });
        },
        rotate(d) { this.cropper && this.cropper.rotate(d); },
        zoom(d) { this.cropper && this.cropper.zoom(d); },
        setAspect(a) { this.aspect = a; this.cropper && this.cropper.setAspectRatio(a || NaN); },
        backToCrop() { this.stage = 'crop'; this.$nextTick(() => this.initCropper()); },

        applyCrop() {
            if (!this.cropper) return;
            let canvas = this.cropper.getCroppedCanvas({ imageSmoothingQuality: 'high' });
            this.baseCanvas = this.downscale(canvas, 1200);
            this.cropper.destroy(); this.cropper = null;
            this.stage = 'adjust';
            this.$nextTick(() => this.render());
        },
        downscale(canvas, maxDim) {
            const m = Math.max(canvas.width, canvas.height);
            if (m <= maxDim) return canvas;
            const s = maxDim / m;
            const out = document.createElement('canvas');
            out.width = Math.round(canvas.width * s);
            out.height = Math.round(canvas.height * s);
            out.getContext('2d').drawImage(canvas, 0, 0, out.width, out.height);
            return out;
        },

        // --- Editor: adjust stage (filters / background / watermark) ---
        render() {
            const base = this.baseCanvas, out = this.$refs.out;
            if (!base || !out) return;
            out.width = base.width; out.height = base.height;
            const w = out.width, h = out.height;

            // 1. Adjusted product onto a temp canvas, keyed transparent if removing bg.
            const tmp = document.createElement('canvas');
            tmp.width = w; tmp.height = h;
            const tctx = tmp.getContext('2d');
            tctx.filter = `brightness(${this.adj.brightness}%) contrast(${this.adj.contrast}%) saturate(${this.adj.saturate}%)`;
            tctx.drawImage(base, 0, 0);
            tctx.filter = 'none';
            if (this.bg.on) this.removeBackground(tctx, w, h);

            // 2. Paint the chosen backdrop (when replacing), then 3. the product over it.
            const ctx = out.getContext('2d');
            ctx.clearRect(0, 0, w, h);
            if (this.bg.on && this.backdrop !== 'transparent') this.paintBackdrop(ctx, w, h);
            ctx.drawImage(tmp, 0, 0);

            if (this.wm.on && this.wm.text) this.drawWatermark(ctx, w, h);
        },
        paintBackdrop(ctx, w, h) {
            if (this.backdrop === 'studio') {
                const g = ctx.createLinearGradient(0, 0, 0, h);
                g.addColorStop(0, '#ffffff'); g.addColorStop(1, '#dbe2ea');
                ctx.fillStyle = g;
            } else if (this.backdrop === 'white') {
                ctx.fillStyle = '#ffffff';
            } else {
                ctx.fillStyle = this.bgColor;
            }
            ctx.fillRect(0, 0, w, h);
        },
        removeBackground(ctx, w, h) {
            // Estimate the background from the border pixels, then key out pixels
            // within `tol` colour-distance of it (best for fairly uniform backdrops).
            const id = ctx.getImageData(0, 0, w, h), d = id.data;
            let r = 0, g = 0, b = 0, n = 0;
            const add = (x, y) => { const i = (y * w + x) * 4; r += d[i]; g += d[i + 1]; b += d[i + 2]; n++; };
            for (let x = 0; x < w; x++) { add(x, 0); add(x, h - 1); }
            for (let y = 0; y < h; y++) { add(0, y); add(w - 1, y); }
            r /= n; g /= n; b /= n;
            const tol = this.bg.tol;
            for (let i = 0; i < d.length; i += 4) {
                const dist = Math.sqrt((d[i] - r) ** 2 + (d[i + 1] - g) ** 2 + (d[i + 2] - b) ** 2);
                if (dist < tol) d[i + 3] = 0;
            }
            ctx.putImageData(id, 0, 0);
        },
        drawWatermark(ctx, w, h) {
            const fs = Math.max(14, Math.round(w * 0.05));
            ctx.font = `bold ${fs}px sans-serif`;
            ctx.textAlign = 'right'; ctx.textBaseline = 'bottom';
            ctx.lineWidth = Math.max(2, fs * 0.08);
            ctx.strokeStyle = 'rgba(0,0,0,0.45)';
            ctx.fillStyle = 'rgba(255,255,255,0.8)';
            const x = w - fs * 0.4, y = h - fs * 0.4;
            ctx.strokeText(this.wm.text, x, y);
            ctx.fillText(this.wm.text, x, y);
        },
        resetAdjust() {
            this.adj = { brightness: 100, contrast: 100, saturate: 100 };
            this.bg = { on: false, tol: 60 };
            this.backdrop = 'transparent'; this.bgColor = '#ffffff';
            this.wm = { on: false, text: this.$el.dataset.store || '' };
            this.render();
        },

        // --- Save / clear ---
        // target: 'cover' replaces the primary image; 'gallery' adds it as an extra image.
        save(target) {
            const out = this.$refs.out;
            // Keep transparency only when removing the background with no replacement backdrop.
            const png = this.bg.on && this.backdrop === 'transparent';
            const type = png ? 'image/png' : 'image/jpeg';
            out.toBlob((blob) => {
                if (!blob) { this.error = 'Could not export the image.'; return; }
                const file = new File([blob], 'product-image.' + (png ? 'png' : 'jpg'), { type });
                if (target === 'gallery') {
                    window.dispatchEvent(new CustomEvent('gallery-add-file', { detail: file }));
                    this.editing = false; this.stage = 'crop';
                    return;
                }
                const dt = new DataTransfer();
                dt.items.add(file);
                this.$refs.file.files = dt.files;   // submits with the form
                if (this.$refs.remove) this.$refs.remove.checked = false;
                this.setPreview(file);
                this.editing = false; this.stage = 'crop';
            }, type, 0.9);
        },
        cancelEdit() {
            if (this.cropper) { this.cropper.destroy(); this.cropper = null; }
            this.editing = false; this.stage = 'crop';
        },
        setPreview(file) {
            if (this.preview && this.preview.startsWith('blob:')) URL.revokeObjectURL(this.preview);
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
