<?php

namespace App\Modules\Identity\Contracts;

use App\Modules\Identity\OmsIdentity;

/**
 * Sumber identitas OMS (System Design §3.10). Hook federasi: implementasi lokal sekarang,
 * SSO ke LBE/ERP kelak TANPA rombak domain. Domain bergantung pada interface ini — bukan
 * Auth facade / User model konkret — agar sumber identitas dapat ditukar.
 *
 * Saat SSO aktif: implementasi me-REFERENSIKAN identitas pusat, tidak menduplikasi master karyawan.
 */
interface IdentityProvider
{
    /** Principal yang sedang ter-autentikasi, atau null bila tamu. */
    public function current(): ?OmsIdentity;

    public function check(): bool;

    /** Apakah principal saat ini punya permission (aksi sensitif). */
    public function can(string $permission): bool;
}
