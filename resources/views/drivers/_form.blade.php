@php $driver = $driver ?? null; @endphp
<x-card>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700">Name</label>
            <input name="name" value="{{ old('name', $driver->name ?? '') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Phone</label>
            <input name="phone" value="{{ old('phone', $driver->phone ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Vehicle</label>
            <input name="vehicle" value="{{ old('vehicle', $driver->vehicle ?? '') }}" placeholder="e.g. Motorbike — Plate 123" class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
        <div class="flex items-center mt-6">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $driver->is_active ?? true)) class="rounded border-slate-300 text-indigo-600">
            <label for="is_active" class="ml-2 text-sm font-medium text-slate-700">Active</label>
        </div>
    </div>
</x-card>
