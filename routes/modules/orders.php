<?php

use App\Http\Controllers\Orders\OrderController;
use App\Http\Controllers\Storefront\StorefrontSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {

    // --- Storefront content (landing-page text shown on the public shop) ---
    Route::middleware('permission:orders.manage')->group(function () {
        Route::get('storefront/settings', [StorefrontSettingsController::class, 'edit'])->name('storefront.settings');
        Route::put('storefront/settings', [StorefrontSettingsController::class, 'update'])->name('storefront.settings.update');
    });

    // --- Order list + detail ---
    Route::middleware('permission:orders.view')->group(function () {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    });

    // --- Order builder (create before {order} so it isn't captured) ---
    Route::middleware('permission:orders.manage')->group(function () {
        Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
    });

    Route::middleware('permission:orders.view')->group(function () {
        Route::get('orders/{order}', [OrderController::class, 'show'])
            ->whereNumber('order')->name('orders.show');
        Route::post('orders/{order}/email-receipt', [OrderController::class, 'emailReceipt'])
            ->whereNumber('order')->name('orders.email-receipt');
    });

    // --- Order actions ---
    Route::middleware('permission:orders.manage')->group(function () {
        Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])
            ->whereNumber('order')->name('orders.status');
        Route::post('orders/{order}/pay', [OrderController::class, 'pay'])
            ->whereNumber('order')->name('orders.pay');
    });

    Route::middleware('permission:orders.fulfill')->group(function () {
        Route::post('orders/{order}/fulfill', [OrderController::class, 'fulfill'])
            ->whereNumber('order')->name('orders.fulfill');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
            ->whereNumber('order')->name('orders.cancel');
    });
});
