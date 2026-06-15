<?php

use App\Modules\Ingestion\DTO\TransactionDTO;
use App\Modules\Ingestion\Parsing\TransactionParser;
use App\Support\Time\Wib;

/**
 * OPS-103 · parser response transaksi + normalisasi waktu WIB (Risiko R1).
 */

function fixtureRows(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/nevira/{$name}.json")), true)['data'];
}

beforeEach(fn () => $this->parser = new TransactionParser());

// (a) parsing fixture VOID nyata
it('(a) memetakan record VOID nyata dgn benar', function () {
    $dto = $this->parser->fromArray(fixtureRows('transactions_void_page1')[0]);

    expect($dto)->toBeInstanceOf(TransactionDTO::class)
        ->and($dto->transactionNumber)->toBe('INV/120/2026/06/8134')
        ->and($dto->idTransaction)->toBe(8134)
        ->and($dto->isVoid())->toBeTrue()
        ->and($dto->grandTotal)->toBe(81225)
        ->and($dto->reason)->toBe('salah input nota')   // dari void_notes
        ->and($dto->refundVoidBy)->toBe(181)
        ->and($dto->refundVoidApprovedBy)->toBe(181)
        ->and($dto->idCashier)->toBe(181)
        ->and($dto->paymentStatus)->toBe('UNPAID')
        ->and($dto->progressPercentage)->toBe(0)
        ->and($dto->isSelfApproval())->toBeTrue()        // by == approved_by
        ->and($dto->notaDate())->toBe('2026-06-11')
        ->and($dto->approvedDate())->toBe('2026-06-12');
});

// (a) parsing fixture REFUND nyata + toleransi null (void_notes null saat REFUND)
it('(a) memetakan record REFUND nyata & tahan void_notes null', function () {
    $dto = $this->parser->fromArray(fixtureRows('transactions_refund')[0]);

    expect($dto->isRefund())->toBeTrue()
        ->and($dto->grandTotal)->toBe(582560)
        ->and($dto->reason)->toBe('customer minta refund penuh') // dari refund_notes (void_notes null)
        ->and($dto->paymentStatus)->toBe('PAID')
        ->and($dto->refundVoidBy)->toBe(200)
        ->and($dto->refundVoidApprovedBy)->toBe(3)
        ->and($dto->idCashier)->toBe(205)
        ->and($dto->isSelfApproval())->toBeFalse()
        ->and($dto->notaDate())->toBe('2026-06-09');
});

it('(a) collection memetakan banyak record sekaligus', function () {
    $rows = array_merge(fixtureRows('transactions_void_page1'), fixtureRows('transactions_void_page2'));

    $dtos = $this->parser->collection($rows);

    expect($dtos)->toHaveCount(3)
        ->and($dtos->every(fn ($d) => $d instanceof TransactionDTO))->toBeTrue();
    // record 6003 = produksi 100% lalu void (kandidat orphaned, OPS-604)
    expect($dtos->firstWhere('idTransaction', 6003)->progressPercentage)->toBe(100);
});

it('tahan record minim field (semua opsional null, tanpa crash)', function () {
    $dto = $this->parser->fromArray([
        'transaction_number' => 'INV/X/1',
        'created_at' => '2026-06-12 10:00:00',
    ]);

    expect($dto->status)->toBeNull()
        ->and($dto->grandTotal)->toBe(0)
        ->and($dto->reason)->toBeNull()
        ->and($dto->approvedAt)->toBeNull()
        ->and($dto->idCashier)->toBeNull()
        ->and($dto->isSelfApproval())->toBeFalse()
        ->and($dto->progressPercentage)->toBe(0);
});

// (b) batas tengah malam — Risiko R1
it('(b) transaksi 23:30 WIB tetap di tanggal yang sama (tidak tergeser zona)', function () {
    $dto = $this->parser->fromArray([
        'transaction_number' => 'INV/120/midnight',
        'created_at' => '2026-06-12 23:30:00',          // WIB tingkat-transaksi
        'approve_refund_void_date' => '2026-06-12 23:45:00',
    ]);

    expect($dto->notaDate())->toBe('2026-06-12')         // BUKAN 2026-06-13
        ->and($dto->createdAt->format('H:i'))->toBe('23:30')
        ->and($dto->createdAt->timezoneName)->toBe('Asia/Jakarta')
        ->and($dto->approvedDate())->toBe('2026-06-12');
});

it('(b) tepat tengah malam 00:15 WIB tidak mundur ke hari sebelumnya', function () {
    $dto = $this->parser->fromArray([
        'transaction_number' => 'INV/120/0015',
        'created_at' => '2026-06-12 00:15:00',
    ]);

    expect($dto->notaDate())->toBe('2026-06-12');         // BUKAN 2026-06-11
});

it('Wib::parse memperlakukan string tingkat-transaksi sebagai WIB (bukan UTC)', function () {
    $t = Wib::parse('2026-06-12 23:30:00');

    expect($t->timezoneName)->toBe('Asia/Jakarta')
        ->and($t->format('Y-m-d H:i:s'))->toBe('2026-06-12 23:30:00')
        ->and($t->utcOffset())->toBe(420); // +7 jam = 420 menit
});

// R1: timestamp nested "services" (UTC) HARUS lewat jalur terpisah, tak mengubah tgl nota
it('jebakan R1: services UTC dikonversi terpisah (+7), tidak dipakai utk tgl nota', function () {
    $void = fixtureRows('transactions_void_page1')[0];

    // services UTC ber-'Z'
    $servicesUtc = $void['services'][0]['created_at']; // 2026-06-11T06:10:12.000000Z
    $wibFromServices = Wib::fromUtc($servicesUtc);

    // 06:10:12Z == 13:10:12 WIB (+7) — instan sama, jam beda
    expect($wibFromServices->format('Y-m-d H:i:s'))->toBe('2026-06-11 13:10:12');

    // Tgl nota DTO berasal dari field tingkat-transaksi (created_at), bukan services.
    $dto = $this->parser->fromArray($void);
    expect($dto->notaDate())->toBe('2026-06-11')
        ->and($dto->createdAt->format('H:i:s'))->toBe('13:10:12');
});

it('Wib::parseNullable mengembalikan null untuk kosong', function () {
    expect(Wib::parseNullable(null))->toBeNull()
        ->and(Wib::parseNullable(''))->toBeNull()
        ->and(Wib::parseNullable('2026-06-12 08:00:00'))->not->toBeNull();
});
