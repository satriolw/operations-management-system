<?php

use App\Models\FinancialDocument;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Finance\DocumentService;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120]); // Fatmawati
    config(['finance.outlet_codes' => [120 => '06'], 'finance.division' => 'OPS']);
    $this->svc = app(DocumentService::class);
});

function reqUser(string $role = 'head_store', ?int $outlet = 120): User
{
    $u = tap(User::factory()->create())->assignRole($role);
    if ($outlet !== null) {
        $u->outlets()->attach($outlet);
    }

    return $u;
}

it('membuat Payment Request (outlet) dgn line items + doc_number format §3.2', function () {
    $hs = reqUser();
    $doc = $this->svc->create([
        'doc_type' => 'PAYMENT_REQUEST', 'brand' => 'LW', 'id_outlet' => 120, 'title' => 'Beli sabun',
        'lines' => [
            ['description' => 'Detergen', 'merk_type' => 'Rinso', 'qty' => 2, 'unit_price' => 100000, 'amount' => 200000],
            ['description' => 'Pewangi', 'qty' => 1, 'unit_price' => 50000, 'amount' => 50000],
        ],
    ], $hs, \App\Support\Time\Wib::parse('2026-06-10'));

    expect($doc->status)->toBe('DRAFT')
        ->and((float) $doc->amount)->toBe(250000.0)
        ->and($doc->amount_band)->toBe('LOW')
        ->and($doc->lines)->toHaveCount(2)
        ->and($doc->doc_number)->toBe('260610-LW06/PR/OPS/001');
});

it('kode jenis benar per doc_type (RF, ER, CA, RE)', function () {
    $om = reqUser('operations_manager', null); // akses semua (HEAD_OFFICE & outlet)
    $map = ['REFUND' => 'RF', 'EXPENSE_REPORT' => 'ER', 'CASH_ADVANCE' => 'CA', 'REIMBURSE' => 'RE'];
    foreach ($map as $type => $code) {
        $doc = $this->svc->create(['doc_type' => $type, 'brand' => 'LW', 'id_outlet' => 120, 'title' => $type, 'amount' => 100000],
            $om, \App\Support\Time\Wib::parse('2026-06-10'));
        expect($doc->doc_number)->toContain("/{$code}/OPS/");
    }
});

it('Expense Report: parent CA + running balance lines', function () {
    $hs = reqUser();
    $ca = $this->svc->create(['doc_type' => 'CASH_ADVANCE', 'brand' => 'LW', 'id_outlet' => 120, 'title' => 'CA', 'amount' => 1000000], $hs);

    $er = $this->svc->create([
        'doc_type' => 'EXPENSE_REPORT', 'brand' => 'LW', 'id_outlet' => 120, 'title' => 'Realisasi CA',
        'parent_document_id' => $ca->id,
        'lines' => [
            ['description' => 'Transport', 'qty' => 1, 'unit_price' => 300000, 'amount' => 300000, 'balance' => 700000],
            ['description' => 'Konsumsi', 'qty' => 1, 'unit_price' => 800000, 'amount' => 800000, 'balance' => -100000],
        ],
    ], $hs);

    expect($er->parent->id)->toBe($ca->id)
        ->and((float) $er->lines()->orderBy('sort_order')->get()->last()->balance)->toBe(-100000.0);
});

it('Refund: nevira_transaction_number REFERENSI + PII di payload, tanpa line items', function () {
    $hs = reqUser();
    $doc = $this->svc->create([
        'doc_type' => 'REFUND', 'brand' => 'LW', 'id_outlet' => 120, 'title' => 'Berita Acara Refund',
        'amount' => 75000, 'nevira_transaction_number' => 'INV/121/1779504406359/1',
        'payload' => ['customer_name' => 'Budi', 'customer_phone' => '0812xxxx', 'customer_account' => '123-456'],
        'lines' => [['description' => 'diabaikan', 'amount' => 999]], // Refund tak itemized
    ], $hs);

    expect($doc->nevira_transaction_number)->toBe('INV/121/1779504406359/1')
        ->and($doc->lines)->toHaveCount(0)
        ->and($doc->payload_json['customer_name'])->toBe('Budi');
});

it('scoping (OPS-1003): pengaju tanpa akses outlet → ditolak', function () {
    $hs = reqUser('head_store', null); // tak di-assign outlet mana pun
    expect(fn () => $this->svc->create(['doc_type' => 'PAYMENT_REQUEST', 'brand' => 'LW', 'id_outlet' => 120, 'title' => 'x', 'amount' => 1000], $hs))
        ->toThrow(RuntimeException::class);
});

it('Head Office: scope HEAD_OFFICE → kode HO, id_outlet null (butuh akses-semua)', function () {
    $om = reqUser('operations_manager', null);
    $doc = $this->svc->create(['doc_type' => 'PAYMENT_REQUEST', 'brand' => 'LW', 'scope' => 'HEAD_OFFICE', 'title' => 'HO PR', 'amount' => 1000000],
        $om, \App\Support\Time\Wib::parse('2026-06-10'));

    expect($doc->id_outlet)->toBeNull()
        ->and($doc->doc_number)->toBe('260610-LWHO/PR/OPS/001');
});

it('SEQ reset bulanan: increment dalam bulan, reset bulan baru', function () {
    $hs = reqUser();
    $a = $this->svc->create(['doc_type' => 'PAYMENT_REQUEST', 'brand' => 'LW', 'id_outlet' => 120, 'title' => '1', 'amount' => 1000], $hs, \App\Support\Time\Wib::parse('2026-06-10'));
    $b = $this->svc->create(['doc_type' => 'PAYMENT_REQUEST', 'brand' => 'LW', 'id_outlet' => 120, 'title' => '2', 'amount' => 1000], $hs, \App\Support\Time\Wib::parse('2026-06-20'));
    $c = $this->svc->create(['doc_type' => 'PAYMENT_REQUEST', 'brand' => 'LW', 'id_outlet' => 120, 'title' => '3', 'amount' => 1000], $hs, \App\Support\Time\Wib::parse('2026-07-05'));

    expect($a->doc_number)->toEndWith('/001')
        ->and($b->doc_number)->toEndWith('/002')   // bulan sama → lanjut
        ->and($c->doc_number)->toEndWith('/001');  // bulan baru → reset
});
