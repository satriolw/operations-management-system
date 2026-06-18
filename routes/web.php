<?php

use App\Modules\Admin\Http\Controllers\ApprovalChainController;
use App\Modules\Admin\Http\Controllers\AuditConfigController;
use App\Modules\Admin\Http\Controllers\CapacityController;
use App\Modules\Admin\Http\Controllers\ChecklistTemplateController;
use App\Modules\Admin\Http\Controllers\DeliveryConfigController;
use App\Modules\Admin\Http\Controllers\DeliveryTargetController;
use App\Modules\Admin\Http\Controllers\InvestorController;
use App\Modules\Admin\Http\Controllers\WhatsappAccountController;
use App\Modules\Admin\Http\Controllers\OutletController;
use App\Modules\Admin\Http\Controllers\RoleLevelController;
use App\Modules\Admin\Http\Controllers\SlaConfigController;
use App\Modules\Admin\Http\Controllers\TopupConfigController;
use App\Modules\Admin\Http\Controllers\UserController;
use App\Modules\Templating\Http\Controllers\TemplateBuilderController;
use App\Modules\Delivery\Http\Controllers\HybridConfirmationController;
use App\Modules\Identity\Permissions;
use App\Modules\Signals\Http\Controllers\SignalReviewController;
use Illuminate\Support\Facades\Route;

// OPS-806 · pintu masuk: '/' → dashboard (bila auth) atau login.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

// OPS-806 · Auth UI (Blade custom + guard/sesi Laravel; investor tidak login).
Route::middleware('web')->group(function () {
    Route::get('login', [\App\Modules\Identity\Http\Controllers\LoginController::class, 'show'])->middleware('guest')->name('login');
    Route::post('login', [\App\Modules\Identity\Http\Controllers\LoginController::class, 'login'])->middleware('guest');
    Route::post('logout', [\App\Modules\Identity\Http\Controllers\LoginController::class, 'logout'])->middleware('auth')->name('logout');
    Route::get('dashboard', [\App\Modules\Identity\Http\Controllers\DashboardController::class, 'index'])->middleware('auth')->name('dashboard');
});

// OPS-302 · Head Store konfirmasi "Sudah saya kirim" (hybrid). Gate APPROVE_AND_SEND + scoping di controller.
Route::middleware(['web', 'auth'])
    ->put('deliveries/{delivery}/confirm', [HybridConfirmationController::class, 'confirm'])
    ->name('deliveries.confirm');

// OPS-606 · tinjau sinyal (gate REVIEW_SIGNALS + scoping + reviewer≠subjek di request/controller).
Route::middleware(['web', 'auth'])
    ->post('signals/{signal}/review', [SignalReviewController::class, 'review'])
    ->name('signals.review');

// OPS-606 · layar triase sinyal (read; gate REVIEW_SIGNALS + scoping per-outlet di controller).
Route::middleware(['web', 'auth', 'can:'.Permissions::REVIEW_SIGNALS])
    ->get('signals', [SignalReviewController::class, 'index'])
    ->name('signals.index');

// OPS-302 · Preview & Kirim Laporan (read + konfirmasi hybrid via deliveries.confirm; scoping di controller).
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('reports', [\App\Modules\Reporting\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{run}', [\App\Modules\Reporting\Http\Controllers\ReportController::class, 'show'])->name('reports.show');
});

// M2-07 · daftar/riwayat dokumen keuangan + status tracking (scoping per-outlet di controller).
Route::middleware(['web', 'auth'])->prefix('finance')->name('finance.')->group(function () {
    Route::get('documents', [\App\Modules\Finance\Http\Controllers\DocumentController::class, 'index'])->name('documents.index');
    Route::get('documents/{document}', [\App\Modules\Finance\Http\Controllers\DocumentController::class, 'show'])->name('documents.show');

    // M2-06 · lampiran bukti (disk privat + akses ter-scope di controller)
    Route::post('documents/{document}/attachments', [\App\Modules\Finance\Http\Controllers\AttachmentController::class, 'store'])->name('documents.attachments.store');
    Route::get('documents/{document}/attachments/{attachment}/download', [\App\Modules\Finance\Http\Controllers\AttachmentController::class, 'download'])->name('documents.attachments.download');
});

// M3-02 · submission checklist + capture token (anti-palsu; scoping per-outlet di controller).
Route::middleware(['web', 'auth'])->prefix('discipline')->name('discipline.')->group(function () {
    Route::post('runs/{run}/items/{item}/capture-token', [\App\Modules\Discipline\Http\Controllers\ChecklistSubmissionController::class, 'captureToken'])->name('capture-token');
    Route::post('runs/{run}/items/{item}/submit', [\App\Modules\Discipline\Http\Controllers\ChecklistSubmissionController::class, 'submit'])->name('submit');
    Route::get('runs/{run}/submissions/{submission}/photo', [\App\Modules\Discipline\Http\Controllers\ChecklistSubmissionController::class, 'photo'])->name('photo');

    // M3-06 · leaderboard ternormalisasi (scoping per-outlet di controller)
    Route::get('leaderboard', [\App\Modules\Discipline\Http\Controllers\LeaderboardController::class, 'index'])->name('leaderboard');
});

// Admin — master data. Gate aksi sensitif: master_data.edit (OPS-801).
Route::middleware(['web', 'auth', 'can:'.Permissions::EDIT_MASTER_DATA])
    ->prefix('admin')->name('admin.')->group(function () {
        // OPS-806 · daftar outlet (index) + OPS-803 · Edit Outlet
        Route::get('outlets', [OutletController::class, 'index'])->name('outlets.index');
        Route::get('outlets/{outlet}/edit', [OutletController::class, 'edit'])->name('outlets.edit');
        Route::put('outlets/{outlet}', [OutletController::class, 'update'])->name('outlets.update');

        // OPS-804 · Akun WhatsApp & Target Pengiriman
        Route::get('delivery', [DeliveryConfigController::class, 'index'])->name('delivery.index');
        Route::put('delivery-targets/{target}/mode', [DeliveryConfigController::class, 'updateMode'])->name('delivery.mode');
        // OPS-804 · CRUD akun WhatsApp + target + investor (master data)
        Route::post('whatsapp-accounts', [WhatsappAccountController::class, 'store'])->name('whatsapp-accounts.store');
        Route::put('whatsapp-accounts/{whatsappAccount}', [WhatsappAccountController::class, 'update'])->name('whatsapp-accounts.update');
        Route::delete('whatsapp-accounts/{whatsappAccount}', [WhatsappAccountController::class, 'destroy'])->name('whatsapp-accounts.destroy');
        Route::post('delivery-targets', [DeliveryTargetController::class, 'store'])->name('delivery-targets.store');
        Route::put('delivery-targets/{target}', [DeliveryTargetController::class, 'update'])->name('delivery-targets.update');
        Route::delete('delivery-targets/{target}', [DeliveryTargetController::class, 'destroy'])->name('delivery-targets.destroy');
        Route::post('investors', [InvestorController::class, 'store'])->name('investors.store');
        Route::put('investors/{investor}', [InvestorController::class, 'update'])->name('investors.update');
        Route::delete('investors/{investor}', [InvestorController::class, 'destroy'])->name('investors.destroy');

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

        // M3-01 · Master template & item checklist (Modul 3 Discipline)
        Route::get('checklists', [ChecklistTemplateController::class, 'index'])->name('checklists.index');
        Route::post('checklists', [ChecklistTemplateController::class, 'store'])->name('checklists.store');
        Route::put('checklists/{checklist}', [ChecklistTemplateController::class, 'update'])->name('checklists.update');
        Route::delete('checklists/{checklist}', [ChecklistTemplateController::class, 'destroy'])->name('checklists.destroy');
        Route::post('checklists/{checklist}/items', [ChecklistTemplateController::class, 'storeItem'])->name('checklists.items.store');
        Route::delete('checklist-items/{item}', [ChecklistTemplateController::class, 'destroyItem'])->name('checklists.items.destroy');

        // M2-02 · Master data rantai approval dokumen keuangan (Modul 2 Finance)
        Route::get('approval-chains', [ApprovalChainController::class, 'index'])->name('approval-chains.index');
        Route::post('approval-chains', [ApprovalChainController::class, 'store'])->name('approval-chains.store');
        Route::put('approval-chains/{approvalChain}', [ApprovalChainController::class, 'update'])->name('approval-chains.update');
        Route::delete('approval-chains/{approvalChain}', [ApprovalChainController::class, 'destroy'])->name('approval-chains.destroy');

        // OPS-1203 · Master data kalender pencairan + ambang saldo NEVIRA (Epic L)
        Route::get('topup-config', [TopupConfigController::class, 'index'])->name('topup-config.index');
        Route::put('topup-config', [TopupConfigController::class, 'update'])->name('topup-config.update');

        // Epic N · Ambang audit transaksi per outlet (OPS-1402..1406)
        Route::get('audit-config', [AuditConfigController::class, 'index'])->name('audit-config.index');
        Route::put('outlets/{outlet}/audit-config', [AuditConfigController::class, 'update'])->name('audit-config.update');

        // OPS-1302 · Master data SLA produksi per outlet (Epic M) → dikonsumsi OPS-1303
        Route::get('sla-config', [SlaConfigController::class, 'index'])->name('sla-config.index');
        Route::put('outlets/{outlet}/sla-config', [SlaConfigController::class, 'update'])->name('sla-config.update');

        // OPS-1101 · Master data kapasitas outlet (Epic K) → dikonsumsi OPS-1103
        Route::get('capacity', [CapacityController::class, 'index'])->name('capacity.index');
        Route::put('outlets/{outlet}/capacity', [CapacityController::class, 'update'])->name('capacity.update');

        // OPS-901 · Kelola template laporan (daftar master + override, buat, hapus)
        Route::get('templates', [\App\Modules\Admin\Http\Controllers\ReportTemplateController::class, 'index'])->name('templates.index');
        Route::post('templates', [\App\Modules\Admin\Http\Controllers\ReportTemplateController::class, 'store'])->name('templates.store');
        Route::delete('templates/{template}', [\App\Modules\Admin\Http\Controllers\ReportTemplateController::class, 'destroy'])->name('templates.destroy');

        // OPS-902 · Template Builder drag & drop (draft via OPS-1004; R7 guard)
        Route::get('templates/{template}/builder', [TemplateBuilderController::class, 'edit'])->name('templates.builder');
        Route::post('templates/{template}/draft', [TemplateBuilderController::class, 'saveDraft'])->name('templates.draft');
        Route::post('templates/{template}/versions/{version}/publish', [TemplateBuilderController::class, 'publish'])->name('templates.publish');
    });
