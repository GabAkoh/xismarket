<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/**
 * Tenant-wide POS register display preferences (e.g. how many product columns
 * the register grid shows). Stored under the tenant's settings JSON (pos.*).
 */
class PosSettingsController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function edit()
    {
        $store = $this->tenancy->current();

        return view('pos.settings', compact('store'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'grid_columns' => ['required', 'integer', 'min:2', 'max:8'],
            'receipt_width' => ['required', 'integer', 'in:58,80'],
            'receipt_auto_print' => ['nullable'],
        ]);

        $store = $this->tenancy->current();
        $settings = $store->settings ?? [];
        $settings['pos'] = array_merge($settings['pos'] ?? [], [
            'grid_columns' => (int) $data['grid_columns'],
            'receipt_width' => (int) $data['receipt_width'],
            'receipt_auto_print' => $request->boolean('receipt_auto_print'),
        ]);
        $store->update(['settings' => $settings]);

        return redirect()->route('pos.settings')->with('status', 'Register display updated.');
    }
}
