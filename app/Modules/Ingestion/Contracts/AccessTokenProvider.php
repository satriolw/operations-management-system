<?php

namespace App\Modules\Ingestion\Contracts;

/**
 * Sumber bearer token NEVIRA. OPS-102 memakai ConfigTokenProvider (token statis dari secret).
 * OPS-108 mengganti dengan token manager (login 24 jam, refresh proaktif, single-flight re-login)
 * tanpa mengubah NeviraApiSource.
 */
interface AccessTokenProvider
{
    /** Token saat ini (login proaktif bila kosong/mendekati kedaluwarsa). */
    public function token(): string;

    /**
     * Re-login reaktif (dipanggil saat 401). Single-flight: bila worker lain sudah
     * me-refresh (token cache != $staleToken yang menyebabkan 401), pakai token baru itu
     * tanpa login ulang. Mengembalikan token yang berlaku.
     */
    public function refresh(?string $staleToken = null): string;

    /** Buang token cache agar token() berikutnya login ulang. */
    public function forgetToken(): void;
}
