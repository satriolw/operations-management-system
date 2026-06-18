<?php

namespace App\Modules\Discipline;

use App\Models\ChecklistItem;
use App\Models\ChecklistRun;
use App\Models\User;
use App\Support\Time\Wib;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Capture-session token (M3-02, anti-palsu). Server menerbitkan token ber-tanda-tangan saat kamera
 * in-app dibuka untuk (run,item,crew); upload WAJIB menyertakannya. Token: TTL pendek + SEKALI pakai
 * (nonce di-cache). Upload tanpa token sah = foto dari galeri → DITOLAK. Tanda tangan HMAC app key.
 */
final class CaptureTokenService
{
    public function issue(ChecklistRun $run, ChecklistItem $item, User $user): string
    {
        $payload = [
            'r' => (int) $run->id, 'i' => (int) $item->id, 'u' => (int) $user->id,
            'exp' => Wib::normalize(now())->addSeconds((int) config('discipline.capture_token_ttl', 300))->getTimestamp(),
            'n' => (string) Str::uuid(),
        ];
        $json = json_encode($payload);
        $b64 = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');

        return $b64.'.'.$this->sign($b64);
    }

    /** Verifikasi token utk (run,item,user). Single-use: tandai nonce terpakai. */
    public function verify(string $token, ChecklistRun $run, ChecklistItem $item, User $user): bool
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }
        [$b64, $sig] = $parts;
        if (! hash_equals($this->sign($b64), $sig)) {
            return false; // tanda tangan palsu
        }

        $payload = json_decode((string) base64_decode(strtr($b64, '-_', '+/'), true), true);
        if (! is_array($payload)) {
            return false;
        }
        if ((int) ($payload['r'] ?? 0) !== (int) $run->id
            || (int) ($payload['i'] ?? 0) !== (int) $item->id
            || (int) ($payload['u'] ?? 0) !== (int) $user->id) {
            return false; // token untuk konteks lain
        }
        if ((int) ($payload['exp'] ?? 0) < Wib::normalize(now())->getTimestamp()) {
            return false; // kedaluwarsa
        }

        $cacheKey = 'discipline:capture-nonce:'.($payload['n'] ?? '');
        $store = Cache::store(config('oms.metrics_cache_store'));
        if ($store->has($cacheKey)) {
            return false; // sudah dipakai (single-use)
        }
        $store->put($cacheKey, true, now()->addSeconds((int) config('discipline.capture_token_ttl', 300) + 60));

        return true;
    }

    private function sign(string $b64): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return hash_hmac('sha256', $b64, $key);
    }
}
