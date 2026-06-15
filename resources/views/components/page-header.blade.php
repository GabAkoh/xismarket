@props(['title'])
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-800">{{ $title }}</h1>
        @isset($subtitle)<p class="text-sm text-slate-500">{{ $subtitle }}</p>@endisset
    </div>
    <div class="flex items-center gap-2">{{ $slot }}</div>
</div>
