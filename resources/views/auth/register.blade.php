<x-guest-layout title="Create your store · xismarket">
    <h2 class="text-center text-xl font-bold text-slate-800 mb-6">Create your store</h2>

    @if ($errors->any())
        <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">Business name</label>
            <input name="business_name" value="{{ old('business_name') }}" required
                   class="mt-1 block w-full rounded-md border border-slate-300 p-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Currency</label>
            <select name="currency" class="mt-1 block w-full rounded-md border border-slate-300 p-2 focus:border-indigo-500 focus:ring-indigo-500">
                @foreach (['USD','EUR','GBP','NGN','KES','ZAR','INR','CAD','AUD'] as $cur)
                    <option value="{{ $cur }}" @selected(old('currency','USD') === $cur)>{{ $cur }}</option>
                @endforeach
            </select>
        </div>
        <hr class="my-2">
        <div>
            <label class="block text-sm font-medium text-slate-700">Your name</label>
            <input name="name" value="{{ old('name') }}" required
                   class="mt-1 block w-full rounded-md border border-slate-300 p-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input name="email" type="email" value="{{ old('email') }}" required
                   class="mt-1 block w-full rounded-md border border-slate-300 p-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Password</label>
            <input name="password" type="password" required
                   class="mt-1 block w-full rounded-md border border-slate-300 p-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Confirm password</label>
            <input name="password_confirmation" type="password" required
                   class="mt-1 block w-full rounded-md border border-slate-300 p-2 focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <button type="submit"
                class="w-full rounded-md bg-indigo-600 px-4 py-2 text-white font-semibold hover:bg-indigo-700">
            Create store &amp; start free trial
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        Already have an account? <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:underline">Sign in</a>
    </p>
</x-guest-layout>
