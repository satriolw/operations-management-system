<?php

namespace App\Modules\Ingestion\Auth;

use App\Modules\Ingestion\Contracts\AccessTokenProvider;
use App\Modules\Ingestion\Exceptions\NeviraAuthException;

/**
 * Token statis dari config/secret (config('nevira.token') ← env NEVIRA_TOKEN).
 * Placeholder OPS-102 — tidak ada token hardcode di kode. Diganti OPS-108.
 */
final class ConfigTokenProvider implements AccessTokenProvider
{
    public function token(): string
    {
        $token = (string) config('nevira.token');

        if ($token === '') {
            throw new NeviraAuthException('NEVIRA token belum dikonfigurasi (config/nevira.php ← NEVIRA_TOKEN).');
        }

        return $token;
    }

    public function forgetToken(): void
    {
        // Token statis tak bisa di-refresh sendiri; OPS-108 mengganti perilaku ini.
    }
}
