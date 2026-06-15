<?php

use App\Http\Controllers\Delivery\DeliveryController;
use App\Http\Controllers\Delivery\DriverController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {

    // --- Delivery board + detail ---
    Route::middleware('permission:deliveries.view')->group(function () {
        Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
        // "deliveries/create" must be declared before the numeric-bound show.
        Route::get('deliveries/{delivery}', [DeliveryController::class, 'show'])
            ->whereNumber('delivery')->name('deliveries.show');
    });

    // --- Creating & advancing deliveries ---
    Route::middleware('permission:deliveries.manage')->group(function () {
        Route::get('deliveries/create', [DeliveryController::class, 'create'])->name('deliveries.create');
        Route::post('deliveries', [DeliveryController::class, 'store'])->name('deliveries.store');

        Route::post('deliveries/{delivery}/assign', [DeliveryController::class, 'assign'])
            ->whereNumber('delivery')->name('deliveries.assign');
        Route::post('deliveries/{delivery}/dispatch', [DeliveryController::class, 'dispatchDelivery'])
            ->whereNumber('delivery')->name('deliveries.dispatch');
        Route::post('deliveries/{delivery}/deliver', [DeliveryController::class, 'deliver'])
            ->whereNumber('delivery')->name('deliveries.deliver');
        Route::post('deliveries/{delivery}/fail', [DeliveryController::class, 'fail'])
            ->whereNumber('delivery')->name('deliveries.fail');
    });

    // --- Drivers (CRUD) ---
    Route::middleware('permission:drivers.manage')->group(function () {
        Route::get('drivers', [DriverController::class, 'index'])->name('drivers.index');
        Route::get('drivers/create', [DriverController::class, 'create'])->name('drivers.create');
        Route::post('drivers', [DriverController::class, 'store'])->name('drivers.store');
        Route::get('drivers/{driver}/edit', [DriverController::class, 'edit'])
            ->whereNumber('driver')->name('drivers.edit');
        Route::put('drivers/{driver}', [DriverController::class, 'update'])
            ->whereNumber('driver')->name('drivers.update');
        Route::delete('drivers/{driver}', [DriverController::class, 'destroy'])
            ->whereNumber('driver')->name('drivers.destroy');
    });
});
