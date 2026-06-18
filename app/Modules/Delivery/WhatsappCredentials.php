<?php

namespace App\Modules\Delivery;

use App\Models\WhatsappAccount;

/**
 * Resolusi credentials_ref (REFERENSI) → token akses Cloud API (aturan emas #7). Token TIDAK pernah
 * disimpan di DB atau dilog; diambil dari secret store / env via peta config `whatsapp.credentials`,
 * dengan fallback `whatsapp.default_token` (sandbox / satu nomor). Mengembalikan null bila tak ada
 * (pemanggil → DeliveryFailed → fallback hybrid; tak ada kiriman diam-diam).
 */
final class WhatsappCredentials
{
    public function resolve(WhatsappAccount $account): ?string
    {
        $ref = $account->credentials_ref;

        $map = (array) config('whatsapp.credentials', []);
        if ($ref !== null && isset($map[$ref]) && $map[$ref] !== '' && $map[$ref] !== null) {
            return (string) $map[$ref];
        }

        $default = config('whatsapp.default_token');

        return ($default !== null && $default !== '') ? (string) $default : null;
    }
}
