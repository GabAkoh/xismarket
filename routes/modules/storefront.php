<?php

use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\CustomerAuthController;
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

        // Customer accounts (Sign in / Sign up).
        Route::get('register', [CustomerAuthController::class, 'showRegister'])->name('register');
        Route::post('register', [CustomerAuthController::class, 'register'])->name('register.store');
        Route::get('login', [CustomerAuthController::class, 'showLogin'])->name('login');
        Route::post('login', [CustomerAuthController::class, 'login'])->name('login.store');
        Route::post('logout', [CustomerAuthController::class, 'logout'])->name('logout');

        // Password reset.
        Route::get('forgot-password', [CustomerAuthController::class, 'showForgot'])->name('password.request');
        Route::post('forgot-password', [CustomerAuthController::class, 'sendResetLink'])->name('password.email');
        Route::get('reset-password/{token}', [CustomerAuthController::class, 'showReset'])->name('password.reset');
        Route::post('reset-password', [CustomerAuthController::class, 'resetPassword'])->name('password.update');
        Route::get('account', [CustomerAuthController::class, 'account'])->name('account');
        Route::post('account/orders/{order}/cancel', [CustomerAuthController::class, 'cancelOrder'])->name('account.orders.cancel');

        Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout');
        Route::post('checkout', [CheckoutController::class, 'place'])->name('checkout.place');
        Route::get('order/confirmed', [CheckoutController::class, 'confirmation'])->name('confirmation');
    });
