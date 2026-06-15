<x-guest-layout title="Sign in · xismarket">
    <h2 class="text-center text-xl font-bold text-slate-800 mb-6">Sign in to your account</h2>

    @if ($errors->any())
        <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input name="email" type="email" value="{{ old('email') }}" required autofocus
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Password</label>
            <input name="password" type="password" required
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border p-2">
        </div>
        <div class="flex items-center justify-between">
            <label class="flex items-center text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 mr-2"> Remember me
            </label>
        </div>
        <button type="submit"
                class="w-full rounded-md bg-indigo-600 px-4 py-2 text-white font-semibold hover:bg-indigo-700">
            Sign in
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        New here? <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:underline">Create a store</a>
    </p>
</x-guest-layout>
