<?php

use App\Http\Controllers\Marketing\CouponController;
use App\Http\Controllers\Orders\OrderController;
use App\Http\Controllers\Storefront\PaymentSettingsController;
use App\Http\Controllers\Storefront\StorefrontSettingsController;
use App\Http\Controllers\Storefront\SubscriberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {

    // --- Storefront content (landing-page text shown on the public shop) ---
    Route::middleware('permission:storefront.manage')->group(function () {
        Route::get('storefront/settings', [StorefrontSettingsController::class, 'edit'])->name('storefront.settings');
        Route::put('storefront/settings', [StorefrontSettingsController::class, 'update'])->name('storefront.settings.update');
    });

    // --- Online payment (Paystack) keys ---
    Route::middleware('permission:payments.manage')->group(function () {
        Route::get('payments/settings', [PaymentSettingsController::class, 'edit'])->name('payments.settings');
        Route::put('payments/settings', [PaymentSettingsController::class, 'update'])->name('payments.settings.update');
    });

    // --- Community / newsletter subscribers ---
    Route::middleware('permission:subscribers.view')->group(function () {
        Route::get('subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
        Route::get('subscribers/export', [SubscriberController::class, 'export'])->name('subscribers.export');
        Route::post('subscribers/broadcast', [SubscriberController::class, 'broadcast'])->name('subscribers.broadcast');
        Route::delete('subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscribers.destroy');
    });

    // --- Discount coupons (used at storefront checkout and the POS) ---
    Route::middleware('permission:coupons.manage')->group(function () {
        Route::resource('coupons', CouponController::class)->except(['show']);
    });

    // --- Order list + detail ---
    Route::middleware('permission:orders.view')->group(function () {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        // Literal segments — before orders/{order} so they aren't treated as an id.
        Route::get('orders/report', [OrderController::class, 'report'])->name('orders.report');
        Route::get('orders/report/export', [OrderController::class, 'reportExport'])->name('orders.report.export');
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
        Route::post('orders/{order}/refund', [OrderController::class, 'refund'])
            ->whereNumber('order')->name('orders.refund');
    });
});
