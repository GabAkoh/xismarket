<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(protected CartService $cart) {}

    public function show()
    {
        return view('storefront.cart', [
            'lines' => $this->cart->lines(),
            'totals' => $this->cart->totals(0),
        ]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $product = \App\Models\Inventory\Product::where('is_active', true)->with('variants')->find($data['product_id']);
        if (! $product) {
            return back()->with('error', 'That product is no longer available.');
        }

        // Resolve the chosen variant (or the default), and block sold-out ones.
        $variant = ! empty($data['variant_id'])
            ? $product->variants->firstWhere('id', $data['variant_id'])
            : $product->variants->first();
        if (! $variant) {
            return back()->with('error', 'Please choose an available option.');
        }
        if ($product->track_stock && $variant->isOutOfStock()) {
            $label = $variant->optionLabel() !== '' ? ' ('.$variant->optionLabel().')' : '';

            return back()->with('error', $product->name.$label.' is out of stock.');
        }

        $this->cart->add($product->id, $variant->id, $data['qty'] ?? 1);

        return back()->with('status', 'Added to cart.');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'qty' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $this->cart->setQty($data['product_id'], $data['variant_id'] ?? null, $data['qty']);

        return back()->with('status', 'Cart updated.');
    }

    public function remove(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
        ]);
        $this->cart->remove($data['product_id'], $data['variant_id'] ?? null);

        return back()->with('status', 'Item removed.');
    }

    public function applyCoupon(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);

        $subtotal = $this->cart->totals(0)['subtotal'];
        $eval = app(\App\Services\Marketing\CouponService::class)->evaluate($data['code'], $subtotal);

        if ($eval['error']) {
            return back()->with('error', $eval['error']);
        }

        $this->cart->setCoupon($eval['coupon']->code);

        return back()->with('status', 'Coupon '.$eval['coupon']->code.' applied.');
    }

    public function removeCoupon()
    {
        $this->cart->clearCoupon();

        return back()->with('status', 'Coupon removed.');
    }
}
