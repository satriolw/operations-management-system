<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-101 · verifikasi skema output: migrate + rollback berjalan, tabel & indeks wajib ada,
 * tidak ada kolom PII customer.
 *
 * Catatan: TIDAK memakai RefreshDatabase agar bisa menguji migrate:rollback secara eksplisit.
 */

const OPS101_TABLES = [
    'outlets',
    'outlet_baselines',
    'report_runs',
    'report_deliveries',
    'revenue_adjustments',
    'signal_events',
    'cashier_input_scores',
];

/** Apakah ada indeks yang persis mencakup kolom (berurutan) ini? */
function hasCompositeIndex(string $table, array $columns): bool
{
    foreach (Schema::getIndexes($table) as $index) {
        if (array_map('strtolower', $index['columns']) === array_map('strtolower', $columns)) {
            return true;
        }
    }

    return false;
}

beforeEach(function () {
    Artisan::call('migrate:fresh', ['--force' => true]);
});

it('migrasi berjalan dan semua tabel OPS-101 terbentuk', function () {
    foreach (OPS101_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeTrue("tabel {$table} harus ada setelah migrate");
    }
});

it('rollback membongkar semua tabel OPS-101 (down() benar / reversible)', function () {
    Artisan::call('migrate:rollback', ['--force' => true]);

    foreach (OPS101_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeFalse("tabel {$table} harus hilang setelah rollback");
    }
});

it('indeks wajib OPS-101 terpasang', function () {
    // report_runs(id_outlet, report_date) — di sini unique (sekaligus idempotency)
    expect(hasCompositeIndex('report_runs', ['id_outlet', 'report_date']))
        ->toBeTrue('report_runs harus berindeks (id_outlet, report_date)');

    // signal_events(id_outlet, type, detected_at)
    expect(hasCompositeIndex('signal_events', ['id_outlet', 'type', 'detected_at']))
        ->toBeTrue('signal_events harus berindeks (id_outlet, type, detected_at)');

    // revenue_adjustments(restated_for_date)
    expect(hasCompositeIndex('revenue_adjustments', ['restated_for_date']))
        ->toBeTrue('revenue_adjustments harus berindeks (restated_for_date)');
});

it('idempotency ditegakkan di level DB', function () {
    expect(hasCompositeIndex('report_runs', ['id_outlet', 'report_date']))->toBeTrue();
    expect(hasCompositeIndex('report_deliveries', ['report_run_id', 'channel']))->toBeTrue();
    expect(hasCompositeIndex('report_deliveries', ['idempotency_key']))->toBeTrue();
});

it('setiap tabel turunan ber-id_outlet (LBE-ready)', function () {
    foreach (OPS101_TABLES as $table) {
        expect(Schema::hasColumn($table, 'id_outlet'))
            ->toBeTrue("tabel {$table} harus punya kolom id_outlet");
    }
});

it('tidak ada kolom PII customer di mana pun', function () {
    $forbidden = [
        'customer_name', 'customer_phone', 'customer_address', 'customer_email',
        'phone', 'address', 'email', 'telepon', 'alamat', 'nama_customer', 'no_hp', 'hp',
    ];

    foreach (OPS101_TABLES as $table) {
        $columns = array_map('strtolower', Schema::getColumnListing($table));
        foreach ($forbidden as $bad) {
            expect(in_array($bad, $columns, true))
                ->toBeFalse("tabel {$table} tidak boleh punya kolom PII '{$bad}'");
        }
    }
});
