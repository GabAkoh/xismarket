<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Tenant-wide cash-drawer reasons (Shopify-style cash in / out). Each reason has
 * a direction and the counterpart account its journal posts to. Stored under the
 * tenant's settings JSON (pos.cash_reasons) and read through Tenant::cashReasons().
 */
class CashReasonController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function edit()
    {
        $store = $this->tenancy->current();
        $reasons = $store->cashReasons();

        $accounts = class_exists(\App\Models\Accounting\Account::class)
            ? \App\Models\Accounting\Account::where('is_active', true)->orderBy('code')->get(['code', 'name'])
            : collect();

        return view('pos.cash-reasons', compact('store', 'reasons', 'accounts'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'reasons' => ['nullable', 'array'],
            'reasons.*.key' => ['nullable', 'string', 'max:40'],
            'reasons.*.label' => ['nullable', 'string', 'max:60'],
            'reasons.*.type' => ['nullable', 'in:in,out'],
            'reasons.*.account' => ['nullable', 'string', 'max:20'],
        ]);

        // Clean + de-dupe. Keep the submitted key on rename (preserves history),
        // else derive one from the label. Keys are unique within a direction.
        $reasons = [];
        $seen = [];
        foreach ($request->input('reasons', []) as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $type = ($row['type'] ?? 'in') === 'out' ? 'out' : 'in';
            $key = Str::slug(trim((string) ($row['key'] ?? '')) ?: $label, '_') ?: 'reason';

            $dedupe = $type.':'.$key;
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;

            $reasons[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'account' => trim((string) ($row['account'] ?? '')),
            ];
        }

        if ($reasons === []) {
            return back()->with('error', 'Add at least one cash reason.');
        }

        $store = $this->tenancy->current();
        $settings = $store->settings ?? [];
        $settings['pos'] = array_merge($settings['pos'] ?? [], ['cash_reasons' => $reasons]);
        $store->update(['settings' => $settings]);

        return redirect()->route('cash-reasons.settings')->with('status', 'Cash reasons updated.');
    }
}
