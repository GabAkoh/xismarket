<?php

use App\Http\Controllers\Pos\CustomerController;
use App\Http\Controllers\Pos\LoyaltyController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Pos\RegisterController;
use App\Http\Controllers\Pos\SalesController;
use App\Http\Controllers\Pos\WalletController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {

    // --- Register / checkout ---
    Route::middleware('permission:pos.use')->group(function () {
        Route::get('pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
        Route::get('pos/receipt/{sale}', [PosController::class, 'receipt'])->name('pos.receipt');
    });

    // --- Sales history ---
    Route::middleware('permission:sales.view')->group(function () {
        Route::get('sales', [SalesController::class, 'index'])->name('sales.index');
        // Before sales/{sale} so the literal segment isn't treated as a sale id.
        Route::get('sales/returns', [SalesController::class, 'returns'])->name('sales.returns');
        Route::get('sales/returns/export', [SalesController::class, 'returnsExport'])->name('sales.returns.export');
        Route::get('sales/{sale}', [SalesController::class, 'show'])->name('sales.show');
    });

    // Recording a follow-up payment on a credit sale is a register action.
    Route::middleware('permission:pos.use')->group(function () {
        Route::post('sales/{sale}/payment', [SalesController::class, 'addPayment'])->name('sales.payment');
    });

    Route::middleware('permission:sales.refund')->group(function () {
        Route::post('sales/{sale}/refund', [SalesController::class, 'refund'])->name('sales.refund');
    });

    // --- Customers ---
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('wallets', [WalletController::class, 'index'])->name('wallets.index');
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        // Numeric constraint so it doesn't capture the literal "customers/create".
        Route::get('customers/{customer}', [CustomerController::class, 'show'])
            ->whereNumber('customer')->name('customers.show');
        Route::get('customers/{customer}/statement', [CustomerController::class, 'statement'])
            ->whereNumber('customer')->name('customers.statement');
    });

    Route::middleware('permission:customers.manage')->group(function () {
        Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

        // Wallet (store credit) + loyalty points.
        Route::get('wallets/bulk', [WalletController::class, 'bulkForm'])->name('wallets.bulk');
        Route::post('wallets/bulk', [WalletController::class, 'bulkStore'])->name('wallets.bulk.store');
        Route::post('customers/{customer}/wallet', [CustomerController::class, 'topUpWallet'])->name('customers.wallet.topup');
        Route::post('customers/{customer}/wallet/withdraw', [CustomerController::class, 'withdrawWallet'])->name('customers.wallet.withdraw');
        Route::post('customers/{customer}/loyalty', [CustomerController::class, 'adjustLoyalty'])->name('customers.loyalty.adjust');

        // Loyalty program settings (tenant-wide).
        Route::get('loyalty/settings', [LoyaltyController::class, 'edit'])->name('loyalty.settings');
        Route::put('loyalty/settings', [LoyaltyController::class, 'update'])->name('loyalty.update');
    });

    // --- Registers & shifts ---
    Route::middleware('permission:registers.manage')->group(function () {
        Route::get('registers', [RegisterController::class, 'index'])->name('registers.index');
        Route::get('registers/create', [RegisterController::class, 'create'])->name('registers.create');
        Route::post('registers', [RegisterController::class, 'store'])->name('registers.store');
        Route::get('registers/{register}/edit', [RegisterController::class, 'edit'])->name('registers.edit');
        Route::put('registers/{register}', [RegisterController::class, 'update'])->name('registers.update');
        Route::delete('registers/{register}', [RegisterController::class, 'destroy'])->name('registers.destroy');

        Route::post('registers/{register}/shift/open', [RegisterController::class, 'openShift'])->name('registers.shift.open');
        Route::post('registers/{register}/shift/close', [RegisterController::class, 'closeShift'])->name('registers.shift.close');
    });
});
