<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\CartService;
use App\Services\Storefront\MetaConversionsService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartController extends Controller
{
    use \App\Http\Controllers\Storefront\Concerns\BuildsMetaUserIdentity;

    public function __construct(protected CartService $cart) {}

    public function show()
    {
        return view('storefront.cart', [
            'lines' => $this->cart->lines(),
            'totals' => $this->cart->totals(0),
        ]);
    }

    public function add(Request $request, MetaConversionsService $meta)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:999'],
            // Client-generated id shared with the browser Pixel's AddToCart, so
            // Meta de-duplicates the browser + server-side (CAPI) pair.
            'meta_event_id' => ['nullable', 'string', 'max:64'],
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

        $qty = $data['qty'] ?? 1;
        $this->cart->add($product->id, $variant->id, $qty);

        // Meta AddToCart (server, CAPI). Fires for every add path (product page
        // and quick-add cards); the id matches the browser Pixel event when one
        // was sent, else a fresh id keeps it a single server-only conversion.
        $meta->track($request, 'AddToCart', [
            'event_id' => filled($data['meta_event_id'] ?? null) ? $data['meta_event_id'] : 'atc-'.Str::uuid(),
            'event_source_url' => $request->headers->get('referer'),
            'identity' => $this->customerIdentity(),
            'custom_data' => [
                'currency' => (string) app(Tenancy::class)->current()?->currency,
                'value' => round((float) $variant->sale_price * $qty, 2),
                'content_type' => 'product',
                'content_ids' => [(string) $product->id],
                'content_name' => $product->name,
                'contents' => [[
                    'id' => (string) $product->id,
                    'quantity' => (int) $qty,
                    'item_price' => round((float) $variant->sale_price, 2),
                ]],
            ],
        ]);

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
