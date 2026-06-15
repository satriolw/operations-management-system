<?php

namespace App\Modules\Ingestion\Contracts;

/**
 * Sumber bearer token NEVIRA. OPS-102 memakai ConfigTokenProvider (token statis dari secret).
 * OPS-108 mengganti dengan token manager (login 24 jam, refresh proaktif, single-flight re-login)
 * tanpa mengubah NeviraApiSource.
 */
interface AccessTokenProvider
{
    public function token(): string;

    /** Dipanggil saat 401 agar provider me-refresh token (no-op untuk token statis). */
    public function forgetToken(): void;
}
