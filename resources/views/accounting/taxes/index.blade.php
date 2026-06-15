@extends('layouts.app')
@section('title', 'Tax Rates')

@section('content')
<x-page-header title="Tax Rates">
    @permission('taxes.manage')
        <a href="{{ route('taxes.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add tax rate</a>
    @endpermission
</x-page-header>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Name</th><th class="text-right">Rate</th><th>Status</th><th></th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($taxes as $tax)
                <tr>
                    <td class="py-3 font-medium text-slate-700">{{ $tax->name }}</td>
                    <td class="text-right tabular-nums text-slate-700">{{ number_format($tax->rate, 2) }}%</td>
                    <td>
                        @if ($tax->is_active)
                            <span class="text-xs text-green-600">● Active</span>
                        @else
                            <span class="text-xs text-slate-400">● Inactive</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @permission('taxes.manage')
                            <a href="{{ route('taxes.edit', $tax) }}" class="text-indigo-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('taxes.destroy', $tax) }}" class="inline" onsubmit="return confirm('Delete this tax rate?')">
                                @csrf @method('DELETE')
                                <button class="ml-3 text-red-600 hover:underline">Delete</button>
                            </form>
                        @endpermission
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-4 text-slate-400">No tax rates yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
@endsection
