@extends('layouts.app')
@section('title', 'Payment methods')

@section('content')
<x-page-header title="Payment methods" subtitle="The tender options cashiers can take at the register." />

<form method="POST" action="{{ route('payment-methods.update') }}" class="max-w-2xl"
      x-data="{
          methods: @js($methods),
          add() { this.methods.push({ key: '', label: '' }); },
          remove(i) { this.methods.splice(i, 1); if (this.methods.length === 0) this.add(); },
      }">
    @csrf @method('PUT')

    <x-card>
        <div class="space-y-2">
            <template x-for="(m, i) in methods" :key="i">
                <div class="flex items-center gap-2">
                    <input type="hidden" :name="`methods[${i}][key]`" :value="m.key">
                    <span class="text-slate-300 text-sm w-5 text-center" x-text="i + 1"></span>
                    <input type="text" :name="`methods[${i}][label]`" x-model="m.label" maxlength="40"
                           placeholder="e.g. Bank Transfer"
                           class="flex-1 rounded-md border border-slate-300 p-2 text-sm">
                    <button type="button" @click="remove(i)" class="text-slate-300 hover:text-red-500 text-sm" title="Remove">✕</button>
                </div>
            </template>
        </div>

        <button type="button" @click="add()" class="mt-3 text-sm text-indigo-600 hover:underline">+ Add method</button>

        <div class="mt-5 rounded-md bg-slate-50 border border-slate-100 p-3 text-xs text-slate-500 space-y-1">
            <p>These appear as tender options on the register and when settling a credit sale. A method can be split across a sale but used only once per sale.</p>
            <p><span class="font-medium text-slate-600">Wallet</span> (store credit) is always available for customers with a balance and isn’t listed here.</p>
            <p>Renaming a method keeps past sales intact; removing one only hides it from new sales.</p>
        </div>
    </x-card>

    <div class="mt-4 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        <a href="{{ route('pos.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Open register</a>
    </div>
</form>
@endsection
