<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/**
 * Manage the store's shipping methods — the delivery/pickup options shoppers
 * pick at online checkout and staff pick when creating an online order.
 *
 * Values are persisted under the tenant's settings JSON at
 * settings.storefront.shipping_methods and read back via
 * Tenant::shippingMethods(). Kept under the same key it has always used so the
 * storefront checkout and order flows need no changes; only the editor moved
 * here (Delivery group) out of the larger Storefront content page.
 */
class ShippingMethodController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function edit()
    {
        $store = $this->tenancy->current();

        return view('delivery.shipping-methods', compact('store'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'shipping_methods' => ['nullable', 'array'],
            'shipping_methods.*.label' => ['nullable', 'string', 'max:100'],
            'shipping_methods.*.fee' => ['nullable', 'numeric', 'min:0'],
            'shipping_methods.*.pickup' => ['nullable'],
        ]);

        $store = $this->tenancy->current();
        $settings = $store->settings ?? [];
        $storefront = $settings['storefront'] ?? [];

        // Keep rows that have a label; blanks are dropped.
        $shipping = collect($data['shipping_methods'] ?? [])
            ->map(fn ($m) => [
                'label' => trim((string) ($m['label'] ?? '')),
                'fee' => round((float) ($m['fee'] ?? 0), 2),
                'pickup' => filter_var($m['pickup'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            ->filter(fn ($m) => $m['label'] !== '')
            ->values()->all();

        // An empty list is removed so the built-in defaults (Standard Delivery +
        // Store Pickup) apply again. Other storefront settings are left intact.
        if (! empty($shipping)) {
            $storefront['shipping_methods'] = $shipping;
        } else {
            unset($storefront['shipping_methods']);
        }

        $settings['storefront'] = $storefront;
        $store->update(['settings' => $settings]);

        return redirect()->route('shipping-methods.settings')->with('status', 'Shipping methods updated.');
    }
}
