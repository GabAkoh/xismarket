<?php

use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Middleware\ResolveStoreTenant;
use Illuminate\Support\Facades\Route;

/*
 * PUBLIC customer-facing storefront. No auth — the tenant is resolved from the
 * {store} slug by ResolveStoreTenant. URL shape: /shop/{store-slug}/...
 */
Route::middleware(['web', ResolveStoreTenant::class])
    ->prefix('shop/{store}')
    ->name('shop.')
    ->group(function () {
        Route::get('/', [StorefrontController::class, 'index'])->name('home');
        Route::get('product/{product}', [StorefrontController::class, 'product'])
            ->whereNumber('product')->name('product');

        Route::get('cart', [CartController::class, 'show'])->name('cart');
        Route::post('cart/add', [CartController::class, 'add'])->name('cart.add');
        Route::post('cart/update', [CartController::class, 'update'])->name('cart.update');
        Route::post('cart/remove', [CartController::class, 'remove'])->name('cart.remove');

        Route::post('subscribe', [StorefrontController::class, 'subscribe'])->name('subscribe');

        Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout');
        Route::post('checkout', [CheckoutController::class, 'place'])->name('checkout.place');
        Route::get('order/confirmed', [CheckoutController::class, 'confirmation'])->name('confirmation');
    });
