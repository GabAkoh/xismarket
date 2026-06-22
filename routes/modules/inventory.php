<?php

use App\Http\Controllers\Inventory\CategoryController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\ProductImportController;
use App\Http\Controllers\Inventory\PurchaseOrderController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Inventory\StockValuationController;
use App\Http\Controllers\Inventory\SupplierController;
use App\Http\Controllers\Inventory\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    // Products
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        // Literal segments — before any products/{product} route.
        Route::get('products/report', [ProductController::class, 'report'])->name('products.report');
        Route::get('products/report/export', [ProductController::class, 'reportExport'])->name('products.report.export');
        Route::get('products/movements', [StockMovementController::class, 'report'])->name('products.movements');
        Route::get('products/movements/export', [StockMovementController::class, 'reportExport'])->name('products.movements.export');
        Route::get('products/valuation', [StockValuationController::class, 'report'])->name('products.valuation');
        Route::get('products/valuation/export', [StockValuationController::class, 'reportExport'])->name('products.valuation.export');
    });
    Route::middleware('permission:products.manage')->group(function () {
        // Import (literal segments — before products/{product} routes).
        Route::get('products/import', [ProductImportController::class, 'form'])->name('products.import');
        Route::post('products/import', [ProductImportController::class, 'import'])->name('products.import.store');

        Route::post('products/bulk', [ProductController::class, 'bulk'])->name('products.bulk');
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::post('products/{product}/ai-image', [\App\Http\Controllers\Inventory\ProductImageAiController::class, 'generate'])->name('products.ai-image');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });

    // Categories
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/export', [CategoryController::class, 'export'])->name('categories.export');
    });
    Route::middleware('permission:categories.manage')->group(function () {
        Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });

    // Suppliers
    Route::middleware('permission:suppliers.manage')->group(function () {
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    // Warehouses
    Route::middleware('permission:warehouses.manage')->group(function () {
        Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::get('warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
        Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::get('warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit');
        Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
        Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
    });

    // Stock levels
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    });
    Route::middleware('permission:stock.adjust')->group(function () {
        Route::post('stock/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
    });

    // Purchase orders
    Route::middleware('permission:purchases.view')->group(function () {
        Route::get('purchases', [PurchaseOrderController::class, 'index'])->name('purchases.index');
        // Literal segments — before purchases/{purchase} so they aren't treated as an id.
        Route::get('purchases/report', [PurchaseOrderController::class, 'report'])->name('purchases.report');
        Route::get('purchases/report/export', [PurchaseOrderController::class, 'reportExport'])->name('purchases.report.export');
        Route::get('purchases/{purchase}', [PurchaseOrderController::class, 'show'])->name('purchases.show');
    });
    Route::middleware('permission:purchases.manage')->group(function () {
        Route::get('purchases-create', [PurchaseOrderController::class, 'create'])->name('purchases.create');
        Route::post('purchases', [PurchaseOrderController::class, 'store'])->name('purchases.store');
        Route::post('purchases/{purchase}/receive', [PurchaseOrderController::class, 'receive'])->name('purchases.receive');
    });
});
