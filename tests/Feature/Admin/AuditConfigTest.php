<?php

use App\Models\Outlet;
use App\Models\TransactionAuditConfig;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

it('menolak akses audit-config tanpa master_data.edit (403)', function () {
    $o = Outlet::factory()->create(['id_outlet' => 120]);
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);

    $this->actingAs($ops)->get(route('admin.audit-config.index'))->assertForbidden();
    $this->actingAs($ops)->put(route('admin.audit-config.update', $o), [])->assertForbidden();
});

it('index 200 + banner mode perlu-ditinjau + default forOutlet', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    config(['transaction_audit.review_mode' => true]);

    $this->actingAs(admin())->get(route('admin.audit-config.index'))->assertOk()->assertSee('perlu ditinjau', false);
    expect(TransactionAuditConfig::forOutlet(120)->promo_leak_daily_cap)->toBe(500000);
});

it('update ambang audit (updateOrCreate)', function () {
    $o = Outlet::factory()->create(['id_outlet' => 120]);

    $this->actingAs(admin())->put(route('admin.audit-config.update', $o), [
        'promo_leak_pct' => 10, 'promo_leak_daily_cap' => 300000, 'payment_anomaly_min_amount' => 25000,
        'offprice_tolerance_pct' => 3, 'qty_variance_pct' => 15, 'deposit_expiry_lead_days' => 7,
    ])->assertRedirect();

    expect(TransactionAuditConfig::forOutlet(120)->promo_leak_daily_cap)->toBe(300000);
    expect(TransactionAuditConfig::where('id_outlet', 120)->count())->toBe(1);
});

it('tolak pct di luar 0..100', function () {
    $o = Outlet::factory()->create(['id_outlet' => 120]);
    $this->actingAs(admin())->put(route('admin.audit-config.update', $o), [
        'promo_leak_pct' => 150, 'promo_leak_daily_cap' => 0, 'payment_anomaly_min_amount' => 0,
        'offprice_tolerance_pct' => 5, 'qty_variance_pct' => 20, 'deposit_expiry_lead_days' => 14,
    ])->assertSessionHasErrors('promo_leak_pct');
});
