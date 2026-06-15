{{-- Expects: $catalog (grouped permissions), $assigned (array of slugs), optional $role --}}
<x-card>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700">Role name</label>
            <input name="name" value="{{ old('name', $role->name ?? '') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Description</label>
            <input name="description" value="{{ old('description', $role->description ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
    </div>

    <div class="mt-6 space-y-5" x-data>
        @foreach ($catalog as $group => $perms)
            <div>
                <div class="flex items-center justify-between border-b pb-1 mb-2">
                    <h3 class="text-sm font-semibold text-slate-700">{{ $group }}</h3>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    @foreach ($perms as $slug => $label)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="{{ $slug }}"
                                   @checked(in_array($slug, old('permissions', $assigned)))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-card>
<div class="mt-4 flex gap-2">
    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save role</button>
    <a href="{{ route('roles.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</a>
</div>
