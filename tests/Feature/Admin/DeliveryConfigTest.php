<?php

use App\Models\DeliveryTarget;
use App\Models\Outlet;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

function targetWith(WhatsappAccount $acct, string $mode = 'hybrid'): DeliveryTarget
{
    $outlet = Outlet::factory()->create();

    return DeliveryTarget::factory()->create([
        'id_outlet' => $outlet->id_outlet,
        'whatsapp_account_id' => $acct->id,
        'deliver_mode' => $mode,
    ]);
}

it('menolak akses tanpa permission master_data.edit (403)', function () {
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);
    $t = targetWith(WhatsappAccount::factory()->create());

    $this->actingAs($ops)->get(route('admin.delivery.index'))->assertForbidden();
    $this->actingAs($ops)->put(route('admin.delivery.mode', $t), ['deliver_mode' => 'hybrid'])->assertForbidden();
});

it('menampilkan akun & target; kredensial TERSEMBUNYI, nomor ter-mask', function () {
    $acct = WhatsappAccount::factory()->create([
        'label' => 'LW Kemang', 'phone_number' => '+6281234567890', 'credentials_ref' => 'secret://wa/RAHASIA-XYZ',
    ]);
    targetWith($acct);

    $res = $this->actingAs(admin())->get(route('admin.delivery.index'));

    $res->assertOk()
        ->assertSee('Akun WhatsApp')
        ->assertSee('Target Pengiriman')
        ->assertSee('LW Kemang')
        ->assertSee('Tertutup')                          // kredensial label
        ->assertDontSee('secret://wa/RAHASIA-XYZ')        // ref TIDAK bocor
        ->assertDontSee('6281234567890');                 // nomor penuh TIDAK tampil (ter-mask)
});

it('STATE lost: banner muncul saat ada akun lost', function () {
    targetWith(WhatsappAccount::factory()->lost()->create(['label' => 'LW Fatmawati']));
    $this->actingAs(admin())->get(route('admin.delivery.index'))
        ->assertSee('terputus (lost)');
});

it('STATE normal: tanpa akun lost → tak ada banner lost', function () {
    targetWith(WhatsappAccount::factory()->create());
    $this->actingAs(admin())->get(route('admin.delivery.index'))
        ->assertDontSee('terputus (lost)');
});

it('GERBANG: OBA belum aktif → mode assisted DITOLAK (tetap hybrid)', function () {
    $t = targetWith(WhatsappAccount::factory()->obaNone()->create(), 'hybrid');

    $this->actingAs(admin())->put(route('admin.delivery.mode', $t), ['deliver_mode' => 'assisted'])
        ->assertSessionHasErrors('deliver_mode');

    expect($t->refresh()->deliver_mode)->toBe('hybrid');
});

it('GERBANG: full_auto juga ditolak bila OBA belum aktif', function () {
    $t = targetWith(WhatsappAccount::factory()->obaProcess()->create(), 'hybrid');

    $this->actingAs(admin())->put(route('admin.delivery.mode', $t), ['deliver_mode' => 'full_auto'])
        ->assertSessionHasErrors('deliver_mode');
    expect($t->refresh()->deliver_mode)->toBe('hybrid');
});

it('GERBANG: OBA aktif → mode assisted DITERIMA', function () {
    $t = targetWith(WhatsappAccount::factory()->create(), 'hybrid'); // oba active default

    $this->actingAs(admin())->put(route('admin.delivery.mode', $t), ['deliver_mode' => 'assisted'])
        ->assertRedirect(route('admin.delivery.index'))->assertSessionHas('status');
    expect($t->refresh()->deliver_mode)->toBe('assisted');
});

it('FALLBACK: akun lost → mode efektif hybrid meski tersimpan assisted, & assisted ditolak', function () {
    $t = targetWith(WhatsappAccount::factory()->lost()->create(), 'assisted');

    expect($t->effectiveMode())->toBe('hybrid')
        ->and($t->isFallback())->toBeTrue();

    $this->actingAs(admin())->put(route('admin.delivery.mode', $t), ['deliver_mode' => 'assisted'])
        ->assertSessionHasErrors('deliver_mode');
});

it('mode invalid ditolak', function () {
    $t = targetWith(WhatsappAccount::factory()->create());
    $this->actingAs(admin())->put(route('admin.delivery.mode', $t), ['deliver_mode' => 'turbo'])
        ->assertSessionHasErrors('deliver_mode');
});

it('credentials_ref tidak ikut serialisasi model (hidden)', function () {
    $acct = WhatsappAccount::factory()->create(['credentials_ref' => 'secret://wa/HIDE-ME']);
    expect($acct->toArray())->not->toHaveKey('credentials_ref')
        ->and(json_encode($acct))->not->toContain('HIDE-ME');
});
