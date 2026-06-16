<?php

use App\Modules\Admin\Http\Controllers\DeliveryConfigController;
use App\Modules\Admin\Http\Controllers\OutletController;
use App\Modules\Admin\Http\Controllers\UserController;
use App\Modules\Identity\Permissions;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Admin — master data. Gate aksi sensitif: master_data.edit (OPS-801).
Route::middleware(['web', 'auth', 'can:'.Permissions::EDIT_MASTER_DATA])
    ->prefix('admin')->name('admin.')->group(function () {
        // OPS-803 · Edit Outlet
        Route::get('outlets/{outlet}/edit', [OutletController::class, 'edit'])->name('outlets.edit');
        Route::put('outlets/{outlet}', [OutletController::class, 'update'])->name('outlets.update');

        // OPS-804 · Akun WhatsApp & Target Pengiriman
        Route::get('delivery', [DeliveryConfigController::class, 'index'])->name('delivery.index');
        Route::put('delivery-targets/{target}/mode', [DeliveryConfigController::class, 'updateMode'])->name('delivery.mode');

        // OPS-802 · User & Role
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::put('users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.toggle');
    });
