<?php

use App\Models\DeliveryTarget;
use App\Models\Investor;
use App\Models\Outlet;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120]);
});

it('menolak CRUD delivery tanpa master_data.edit (403)', function () {
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);
    $this->actingAs($ops)->post(route('admin.whatsapp-accounts.store'), [])->assertForbidden();
    $this->actingAs($ops)->post(route('admin.investors.store'), [])->assertForbidden();
    $this->actingAs($ops)->post(route('admin.delivery-targets.store'), [])->assertForbidden();
});

it('CRUD akun WhatsApp (credentials_ref = referensi, bukan secret)', function () {
    $this->actingAs(admin())->post(route('admin.whatsapp-accounts.store'), [
        'label' => 'LW Utama', 'phone_number' => '628111', 'oba_status' => 'active', 'account_status' => 'active',
        'credentials_ref' => 'secret://wa/lw-utama', 'active' => 1,
    ])->assertRedirect();
    $a = WhatsappAccount::first();
    expect($a->label)->toBe('LW Utama')->and($a->credentials_ref)->toBe('secret://wa/lw-utama');

    $this->actingAs(admin())->put(route('admin.whatsapp-accounts.update', $a), [
        'label' => 'LW Utama', 'phone_number' => '628111', 'oba_status' => 'process', 'account_status' => 'active',
    ])->assertRedirect();
    expect($a->refresh()->oba_status)->toBe('process');

    $this->actingAs(admin())->delete(route('admin.whatsapp-accounts.destroy', $a))->assertRedirect();
    expect(WhatsappAccount::count())->toBe(0);
});

it('CRUD investor 1:1 outlet — duplikat outlet ditolak', function () {
    $this->actingAs(admin())->post(route('admin.investors.store'), ['name' => 'Pak Andre', 'id_outlet' => 120])->assertRedirect();
    expect(Investor::where('id_outlet', 120)->count())->toBe(1);

    // outlet sama → unique gagal
    $this->actingAs(admin())->from(route('admin.delivery.index'))
        ->post(route('admin.investors.store'), ['name' => 'Lain', 'id_outlet' => 120])->assertSessionHasErrors('id_outlet');
    expect(Investor::count())->toBe(1);
});

it('CRUD target — mode hybrid OK; assisted tanpa OBA siap ditolak (OPS-306)', function () {
    $accNoOba = WhatsappAccount::create(['label' => 'X', 'phone_number' => '62', 'oba_status' => 'none', 'account_status' => 'active', 'active' => true]);

    $this->actingAs(admin())->post(route('admin.delivery-targets.store'), [
        'id_outlet' => 120, 'investor_label' => 'Andre', 'deliver_mode' => 'hybrid',
    ])->assertRedirect();
    expect(DeliveryTarget::count())->toBe(1);

    // assisted dgn akun OBA none → ditolak gerbang OPS-306
    $this->actingAs(admin())->from(route('admin.delivery.index'))->post(route('admin.delivery-targets.store'), [
        'id_outlet' => 120, 'deliver_mode' => 'assisted', 'whatsapp_account_id' => $accNoOba->id,
    ])->assertSessionHasErrors('deliver_mode');
});

it('target assisted dgn akun OBA aktif → diterima', function () {
    $oba = WhatsappAccount::create(['label' => 'OBA', 'phone_number' => '62', 'oba_status' => 'active', 'account_status' => 'active', 'active' => true]);

    $this->actingAs(admin())->post(route('admin.delivery-targets.store'), [
        'id_outlet' => 120, 'deliver_mode' => 'assisted', 'whatsapp_account_id' => $oba->id,
    ])->assertRedirect();
    expect(DeliveryTarget::first()->deliver_mode)->toBe('assisted');
});

it('delivery index render 200 dgn seksi kelola', function () {
    $this->actingAs(admin())->get(route('admin.delivery.index'))->assertOk()
        ->assertSee('Akun WhatsApp')->assertSee('Investor')->assertSee('Target Pengiriman');
});
