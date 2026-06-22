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
            'qty' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $this->cart->add($data['product_id'], $data['qty'] ?? 1);

        return back()->with('status', 'Added to cart.');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $this->cart->setQty($data['product_id'], $data['qty']);

        return back()->with('status', 'Cart updated.');
    }

    public function remove(Request $request)
    {
        $data = $request->validate(['product_id' => ['required', 'integer']]);
        $this->cart->remove($data['product_id']);

        return back()->with('status', 'Item removed.');
    }
}
