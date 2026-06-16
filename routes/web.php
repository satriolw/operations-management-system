<?php

use App\Modules\Admin\Http\Controllers\DeliveryConfigController;
use App\Modules\Identity\Permissions;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Admin — master data. Gate aksi sensitif: master_data.edit (OPS-801).
Route::middleware(['web', 'auth', 'can:'.Permissions::EDIT_MASTER_DATA])
    ->prefix('admin')->name('admin.')->group(function () {
        // OPS-804 · Akun WhatsApp & Target Pengiriman
        Route::get('delivery', [DeliveryConfigController::class, 'index'])->name('delivery.index');
        Route::put('delivery-targets/{target}/mode', [DeliveryConfigController::class, 'updateMode'])->name('delivery.mode');
    });
