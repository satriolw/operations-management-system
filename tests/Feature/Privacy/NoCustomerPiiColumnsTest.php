<?php

use App\Support\Privacy\PiiPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * OPS-705 · GAGAL bila ada kolom PII customer di tabel output mana pun (aturan emas #3).
 * Memindai SEMUA tabel domain (dinamis), bukan daftar tetap — menangkap tabel baru juga.
 */

function outputTables(): array
{
    return collect(Schema::getTables())
        ->pluck('name')
        ->reject(fn ($t) => in_array($t, PiiPolicy::NON_OUTPUT_TABLES, true))
        ->values()
        ->all();
}

it('tidak ada kolom PII customer di tabel output mana pun', function () {
    $tables = outputTables();
    expect($tables)->not->toBeEmpty(); // pastikan benar-benar memindai sesuatu

    foreach ($tables as $table) {
        foreach (Schema::getColumnListing($table) as $column) {
            expect(PiiPolicy::isForbiddenColumn($column, $table))
                ->toBeFalse("Tabel output '{$table}' punya kolom PII customer terlarang: '{$column}'");
        }
    }
});

it('guard mendeteksi nama kolom PII (sanity policy)', function () {
    expect(PiiPolicy::isForbiddenColumn('customer_name'))->toBeTrue()
        ->and(PiiPolicy::isForbiddenColumn('customer_phone'))->toBeTrue()
        ->and(PiiPolicy::isForbiddenColumn('telepon'))->toBeTrue()
        ->and(PiiPolicy::isForbiddenColumn('alamat_customer'))->toBeTrue()
        ->and(PiiPolicy::isForbiddenColumn('email'))->toBeTrue()
        // field sah TIDAK kena
        ->and(PiiPolicy::isForbiddenColumn('name'))->toBeFalse()        // nama outlet
        ->and(PiiPolicy::isForbiddenColumn('transaction_number'))->toBeFalse()
        ->and(PiiPolicy::isForbiddenColumn('id_cashier'))->toBeFalse()
        ->and(PiiPolicy::isForbiddenColumn('reason'))->toBeFalse();
});
