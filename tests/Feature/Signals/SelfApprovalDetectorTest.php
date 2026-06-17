<?php

use App\Models\NeviraRoleLevel;
use App\Models\Outlet;
use App\Models\SignalEvent;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Signals\SelfApprovalDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
        'nevira.service_username' => null, 'nevira.service_password' => null,
    ]);
    Outlet::factory()->create(['id_outlet' => 120]);
});

function fakeVoid(array $void): void
{
    Http::fake(function (Request $r) use ($void) {
        parse_str(parse_url($r->url(), PHP_URL_QUERY) ?? '', $q);
        $data = ($q['status'] ?? '') === 'VOID' ? $void : [];

        return Http::response(['data' => $data, 'last_page' => 1, 'current_page' => 1, 'next_page_url' => null]);
    });
}

function selfApproved(string $no, int $user, int $role): array
{
    return [
        'transaction_number' => $no, 'status' => 'VOID', 'grand_total' => 81225,
        'created_at' => '2026-06-11 13:00:00', 'approve_refund_void_date' => '2026-06-12 13:56:00',
        'void_notes' => 'salah input', 'refund_void_by' => $user, 'refund_void_approved_by' => $user,
        'id_cashier' => $user, 'cashier' => ['id_role' => $role],
    ];
}

function scan120(): \Illuminate\Support\Collection
{
    return app(SelfApprovalDetector::class)->scan(120, new DateRange('2026-06-05', '2026-06-12'));
}

it('self-approval role < Kepala Toko → pelanggaran severity tinggi (kasus user 181)', function () {
    NeviraRoleLevel::create(['id_role' => 37, 'label' => 'Kasir', 'level' => 10, 'dual_authority_allowed' => false]);
    fakeVoid([selfApproved('INV/8134', 181, 37), selfApproved('INV/7971', 181, 37)]);

    $sigs = scan120();

    expect($sigs)->toHaveCount(2);
    $s = SignalEvent::where('ref_transaction_number', 'INV/8134')->first();
    expect($s->type)->toBe('SELF_APPROVAL')
        ->and($s->severity)->toBe('high')
        ->and($s->payload_json['outcome'])->toBe('violation')
        ->and($s->id_cashier)->toBe(181);
});

it('self-approval role >= Kepala Toko → pengecualian SAH (severity rendah, bukan pelanggaran)', function () {
    NeviraRoleLevel::create(['id_role' => 3, 'label' => 'Kepala Toko', 'level' => 50, 'dual_authority_allowed' => true]);
    fakeVoid([selfApproved('INV/OK', 200, 3)]);

    $s = SignalEvent::where('ref_transaction_number', 'INV/OK')->first() ?? scan120()->first();
    expect($s->severity)->toBe('low')
        ->and($s->payload_json['outcome'])->toBe('legitimate');
});

it('role belum dipetakan → perlu ditinjau (high), tidak diblokir', function () {
    fakeVoid([selfApproved('INV/UNK', 500, 99)]); // role 99 tak ada di peta

    $sigs = scan120();
    expect($sigs->first()->payload_json['outcome'])->toBe('needs_review')
        ->and($sigs->first()->severity)->toBe('high');
});

it('bukan self-approval (pemohon ≠ penyetuju) → tidak ada signal', function () {
    fakeVoid([array_merge(selfApproved('INV/NO', 180, 37), ['refund_void_approved_by' => 3])]);

    expect(scan120())->toHaveCount(0)
        ->and(SignalEvent::count())->toBe(0);
});

it('idempoten: scan dua kali → satu signal per transaksi', function () {
    NeviraRoleLevel::create(['id_role' => 37, 'label' => 'Kasir', 'level' => 10, 'dual_authority_allowed' => false]);
    fakeVoid([selfApproved('INV/DUP', 181, 37)]);

    scan120();
    scan120();
    expect(SignalEvent::where('ref_transaction_number', 'INV/DUP')->count())->toBe(1);
});
