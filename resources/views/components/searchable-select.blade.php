@props([
    'name',
    'label' => null,
    'options',
    'selected' => '',
    'placeholder' => 'Search…',
    'allowNone' => true,
    'createUrl' => null,
])
@php
    $opts = collect($options)->map(fn ($o) => ['id' => (string) $o->id, 'name' => $o->name])->values();
    $selName = $opts->firstWhere('id', (string) $selected)['name'] ?? '';
@endphp
<div x-data="searchableSelect({
        options: @js($opts),
        selectedId: @js((string) $selected),
        selectedName: @js($selName),
        createUrl: @js($createUrl),
     })" class="relative">
    @if ($label)<label class="block text-sm font-medium text-slate-700">{{ $label }}</label>@endif
    <input type="hidden" name="{{ $name }}" :value="selectedId">
    <input type="text" x-model="query" @focus="open = true" @input="onType()" autocomplete="off"
           placeholder="{{ $placeholder }}"
           {{ $attributes->merge(['class' => 'mt-1 w-full rounded-md border border-slate-300 p-2']) }}
           :class="(!selectedId && query) ? 'border-amber-300' : 'border-slate-300'">
    <div x-show="open" x-cloak @click.outside="open = false"
         class="absolute z-30 mt-1 w-full max-h-56 overflow-y-auto rounded-md border border-slate-200 bg-white shadow-lg">
        @if ($allowNone)
            <button type="button" @click="pick(null)" class="block w-full text-left px-3 py-1.5 text-sm text-slate-500 hover:bg-indigo-50">— None —</button>
        @endif
        <template x-for="o in filtered" :key="o.id">
            <button type="button" @click="pick(o)" class="block w-full text-left px-3 py-1.5 text-sm hover:bg-indigo-50" x-text="o.name"></button>
        </template>
        <template x-if="canCreate">
            <button type="button" @click="create()" class="block w-full text-left px-3 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50">
                <span x-show="!creating">+ Create “<span x-text="query.trim()"></span>”</span>
                <span x-show="creating" x-cloak>Creating…</span>
            </button>
        </template>
        <div x-show="query && !filtered.length && !canCreate" class="px-3 py-1.5 text-sm text-slate-400">No match</div>
    </div>
</div>

@once
@push('scripts')
<script>
function searchableSelect(cfg) {
    return {
        options: cfg.options || [],
        selectedId: cfg.selectedId || '',
        query: cfg.selectedName || '',
        createUrl: cfg.createUrl || '',
        open: false,
        creating: false,
        get filtered() {
            const q = this.query.trim().toLowerCase();
            const list = q ? this.options.filter(o => o.name.toLowerCase().includes(q)) : this.options;
            return list.slice(0, 50);
        },
        get canCreate() {
            const q = this.query.trim();
            return !!this.createUrl && q.length > 0 && !this.options.some(o => o.name.toLowerCase() === q.toLowerCase());
        },
        onType() { this.open = true; this.selectedId = ''; },   // editing clears the pick
        pick(o) {
            this.selectedId = o ? String(o.id) : '';
            this.query = o ? o.name : '';
            this.open = false;
        },
        async create() {
            if (!this.canCreate || this.creating) return;
            this.creating = true;
            try {
                const token = document.querySelector('meta[name=csrf-token]')?.content
                    || document.querySelector('input[name=_token]')?.value;
                const res = await fetch(this.createUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: this.query.trim() }),
                });
                if (res.ok) {
                    const c = await res.json();
                    this.options.push({ id: String(c.id), name: c.name });
                    this.pick({ id: c.id, name: c.name });
                }
            } finally {
                this.creating = false;
            }
        },
    };
}
</script>
@endpush
@endonce
