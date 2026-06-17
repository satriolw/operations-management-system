<?php

use App\Modules\Admin\Http\Controllers\CapacityController;
use App\Modules\Admin\Http\Controllers\DeliveryConfigController;
use App\Modules\Admin\Http\Controllers\OutletController;
use App\Modules\Admin\Http\Controllers\RoleLevelController;
use App\Modules\Admin\Http\Controllers\UserController;
use App\Modules\Templating\Http\Controllers\TemplateBuilderController;
use App\Modules\Delivery\Http\Controllers\HybridConfirmationController;
use App\Modules\Identity\Permissions;
use App\Modules\Signals\Http\Controllers\SignalReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// OPS-302 · Head Store konfirmasi "Sudah saya kirim" (hybrid). Gate APPROVE_AND_SEND + scoping di controller.
Route::middleware(['web', 'auth'])
    ->put('deliveries/{delivery}/confirm', [HybridConfirmationController::class, 'confirm'])
    ->name('deliveries.confirm');

// OPS-606 · tinjau sinyal (gate REVIEW_SIGNALS + scoping + reviewer≠subjek di request/controller).
Route::middleware(['web', 'auth'])
    ->post('signals/{signal}/review', [SignalReviewController::class, 'review'])
    ->name('signals.review');

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

        // OPS-805 · master data referensi: peta id_role→level (untuk OPS-601)
        Route::get('role-levels', [RoleLevelController::class, 'index'])->name('role-levels.index');
        Route::post('role-levels', [RoleLevelController::class, 'store'])->name('role-levels.store');
        Route::put('role-levels/{roleLevel}', [RoleLevelController::class, 'update'])->name('role-levels.update');
        Route::delete('role-levels/{roleLevel}', [RoleLevelController::class, 'destroy'])->name('role-levels.destroy');

        // OPS-1101 · Master data kapasitas outlet (Epic K) → dikonsumsi OPS-1103
        Route::get('capacity', [CapacityController::class, 'index'])->name('capacity.index');
        Route::put('outlets/{outlet}/capacity', [CapacityController::class, 'update'])->name('capacity.update');

        // OPS-902 · Template Builder drag & drop (draft via OPS-1004; R7 guard)
        Route::get('templates/{template}/builder', [TemplateBuilderController::class, 'edit'])->name('templates.builder');
        Route::post('templates/{template}/draft', [TemplateBuilderController::class, 'saveDraft'])->name('templates.draft');
        Route::post('templates/{template}/versions/{version}/publish', [TemplateBuilderController::class, 'publish'])->name('templates.publish');
    });
