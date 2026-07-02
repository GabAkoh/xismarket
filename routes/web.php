<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisteredTenantController;
use App\Http\Controllers\BrandingSettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\ManifestController;
use Illuminate\Support\Facades\Route;

// PWA web app manifest (public; branded per tenant).
Route::get('manifest.webmanifest', [ManifestController::class, 'show'])->name('manifest');

Route::get('/', function () {
    // Logged-in staff go to the admin dashboard. Everyone else lands on the
    // configured store's storefront (if set), otherwise the login page.
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    $store = config('storefront.default_store');

    return $store
        ? redirect()->route('shop.home', ['store' => $store])
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredTenantController::class, 'create'])->name('register');
    Route::post('register', [RegisteredTenantController::class, 'store'])->name('register.store');
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // White-label app name + icon, and device allowlist management.
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('settings/branding', [BrandingSettingsController::class, 'edit'])->name('branding.settings');
        Route::put('settings/branding', [BrandingSettingsController::class, 'update'])->name('branding.settings.update');

        Route::get('settings/devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::post('devices/{device}/approve', [DeviceController::class, 'approve'])->name('devices.approve');
        Route::post('devices/{device}/revoke', [DeviceController::class, 'revoke'])->name('devices.revoke');
        Route::put('devices/{device}', [DeviceController::class, 'rename'])->name('devices.rename');
        Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    });
});

// Module routes (routes/modules/*.php) are auto-loaded from bootstrap/app.php.
