@extends('layouts.app')
@section('title', 'Subscribers')

@section('content')
<x-page-header title="Subscribers" subtitle="People who joined your storefront community ({{ number_format($total) }} total)">
    <a href="{{ route('subscribers.export', request()->only('q')) }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Export (CSV)</a>
</x-page-header>

@permission('orders.manage')
<x-card class="mb-4">
    <h2 class="text-sm font-semibold text-slate-700 mb-1">Email new arrivals</h2>
    <p class="text-xs text-slate-400 mb-3">Send your latest products to all subscribers. Send a test to yourself first.</p>
    <form method="POST" action="{{ route('subscribers.broadcast') }}" class="space-y-3"
          onsubmit="return this.test.value === '1' || confirm('Send this to all {{ $total }} subscriber(s)?')"
          x-data="{ }">
        @csrf
        <input type="hidden" name="test" x-ref="test" value="0">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-500 mb-1">Subject (optional)</label>
                <input name="subject" maxlength="150" value="{{ old('subject') }}" placeholder="New arrivals at {{ $currentTenant->name }}"
                       class="w-full rounded-md border border-slate-300 p-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Latest products to feature</label>
                <input type="number" name="count" min="1" max="24" value="{{ old('count', 8) }}"
                       class="w-full rounded-md border border-slate-300 p-2 text-sm">
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Intro message (optional)</label>
            <textarea name="intro" rows="2" maxlength="1000" placeholder="A short note above the products…"
                      class="w-full rounded-md border border-slate-300 p-2 text-sm">{{ old('intro') }}</textarea>
        </div>
        <div class="flex gap-2">
            <button type="submit" @click="$refs.test.value = '0'" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Send to all subscribers</button>
            <button type="submit" @click="$refs.test.value = '1'" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Send test to me</button>
        </div>
    </form>
</x-card>
@endpermission

<x-card class="mb-4">
    <form method="GET" action="{{ route('subscribers.index') }}" class="flex gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search email or name…" class="flex-1 rounded-md border border-slate-300 p-2 text-sm">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Search</button>
        @if (request('q'))
            <a href="{{ route('subscribers.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Clear</a>
        @endif
    </form>
</x-card>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Email</th><th>Name</th><th>Joined</th><th></th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($subscribers as $subscriber)
                <tr>
                    <td class="py-3 font-medium text-slate-700">{{ $subscriber->email }}</td>
                    <td class="text-slate-500">{{ $subscriber->name ?: '—' }}</td>
                    <td class="text-slate-400">{{ optional($subscriber->created_at)->format('M d, Y') ?? '—' }}</td>
                    <td class="text-right">
                        @permission('orders.manage')
                            <form method="POST" action="{{ route('subscribers.destroy', $subscriber) }}" class="inline" onsubmit="return confirm('Remove this subscriber?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Remove</button>
                            </form>
                        @endpermission
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-8 text-center text-slate-400">No subscribers yet. They'll appear here when shoppers join from your storefront.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $subscribers->links() }}</div>
</x-card>
@endsection
