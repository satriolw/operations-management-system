<?php

use App\Modules\Admin\Http\Controllers\OutletController;
use App\Modules\Identity\Permissions;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Admin — master data (OPS-803). Gate aksi sensitif: master_data.edit (OPS-801).
Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('can:'.Permissions::EDIT_MASTER_DATA)->group(function () {
        Route::get('outlets/{outlet}/edit', [OutletController::class, 'edit'])->name('outlets.edit');
        Route::put('outlets/{outlet}', [OutletController::class, 'update'])->name('outlets.update');
    });
});
