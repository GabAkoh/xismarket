<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Pos\LoyaltySetting;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    /** Tenant-wide loyalty program configuration. */
    public function edit()
    {
        $loyalty = LoyaltySetting::current();

        return view('loyalty.settings', compact('loyalty'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'is_active' => ['boolean'],
            'earn_rate' => ['required', 'numeric', 'min:0', 'max:9999'],
            'redeem_value' => ['required', 'numeric', 'min:0', 'max:9999'],
            'min_redeem_points' => ['required', 'integer', 'min:0'],
        ]);

        $loyalty = LoyaltySetting::current();
        $loyalty->update([
            'is_active' => $request->boolean('is_active'),
            'earn_rate' => $data['earn_rate'],
            'redeem_value' => $data['redeem_value'],
            'min_redeem_points' => $data['min_redeem_points'],
        ]);

        return redirect()->route('loyalty.settings')->with('status', 'Loyalty program updated.');
    }
}
