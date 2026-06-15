<?php

use App\Support\Privacy\PiiPolicy;

/**
 * OPS-705 · serialisasi sinyal tidak boleh membawa PII customer — scrub ke whitelist.
 */

it('scrubSignalPayload membuang PII customer, menyimpan metadata yang diizinkan', function () {
    $raw = [
        // metadata sinyal yang diizinkan
        'transaction_number' => 'INV/120/8134',
        'amount' => 81225,
        'reason' => 'salah input nota',
        'id_cashier' => 181,
        'nota_date' => '2026-06-11',
        // PII customer — HARUS dibuang
        'customer_name' => 'Budi Santoso',
        'customer_phone' => '0812xxxx',
        'customer_address' => 'Jl. Mawar 1',
        'email' => 'budi@mail.com',
    ];

    $clean = PiiPolicy::scrubSignalPayload($raw);

    expect($clean)->toEqual([
        'transaction_number' => 'INV/120/8134',
        'amount' => 81225,
        'reason' => 'salah input nota',
        'id_cashier' => 181,
        'nota_date' => '2026-06-11',
    ]);
    expect($clean)->not->toHaveKeys(['customer_name', 'customer_phone', 'customer_address', 'email']);
});

it('payload tanpa field diizinkan menghasilkan array kosong', function () {
    expect(PiiPolicy::scrubSignalPayload(['customer_name' => 'X', 'foo' => 'bar']))->toBe([]);
});
