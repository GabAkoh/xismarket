@props(['title' => null, 'actions' => null])
<div {{ $attributes->merge(['class' => 'bg-white rounded-lg shadow-sm']) }}>
    @if ($title || $actions)
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
            <h2 class="font-semibold text-slate-800">{{ $title }}</h2>
            <div>{{ $actions }}</div>
        </div>
    @endif
    <div class="p-5">{{ $slot }}</div>
</div>
