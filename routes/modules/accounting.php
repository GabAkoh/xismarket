<?php

use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\Accounting\TaxController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    // Chart of accounts
    Route::middleware('permission:accounting.view')->group(function () {
        Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    });

    Route::middleware('permission:accounts.manage')->group(function () {
        Route::get('accounts/create', [AccountController::class, 'create'])->name('accounts.create');
        Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::get('accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
        Route::put('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
        Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
    });

    // Journal entries (create routes declared before the {journal} wildcard).
    Route::middleware('permission:journals.manage')->group(function () {
        Route::get('journals/create', [JournalController::class, 'create'])->name('journals.create');
        Route::post('journals', [JournalController::class, 'store'])->name('journals.store');
    });

    Route::middleware('permission:accounting.view')->group(function () {
        Route::get('journals', [JournalController::class, 'index'])->name('journals.index');
        Route::get('journals/{journal}', [JournalController::class, 'show'])->name('journals.show');
    });

    // Tax rates
    Route::middleware('permission:taxes.manage')->group(function () {
        Route::get('taxes', [TaxController::class, 'index'])->name('taxes.index');
        Route::get('taxes/create', [TaxController::class, 'create'])->name('taxes.create');
        Route::post('taxes', [TaxController::class, 'store'])->name('taxes.store');
        Route::get('taxes/{tax}/edit', [TaxController::class, 'edit'])->name('taxes.edit');
        Route::put('taxes/{tax}', [TaxController::class, 'update'])->name('taxes.update');
        Route::delete('taxes/{tax}', [TaxController::class, 'destroy'])->name('taxes.destroy');
    });

    // Financial reports
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/trial-balance', [ReportController::class, 'trialBalance'])->name('reports.trial-balance');
        Route::get('reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
        Route::get('reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
        Route::get('reports/receivables', [ReportController::class, 'receivables'])->name('reports.receivables');
    });
});
