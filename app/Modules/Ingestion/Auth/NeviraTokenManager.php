<?php

namespace App\Modules\Ingestion\Auth;

use App\Modules\Ingestion\Contracts\AccessTokenProvider;
use App\Modules\Ingestion\Exceptions\NeviraAuthException;
use App\Support\Observability\Metrics;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Token lifecycle NEVIRA — login token 24 jam (OPS-108, System Design §3.2 / ADR-010).
 *
 * - Re-auth = POST login endpoint dgn SERVICE CREDENTIAL (secret store), bukan cuma token.
 * - Token + waktu perolehan di-cache BERSAMA (Redis di prod) → dipakai semua worker.
 * - Proaktif: refresh bila umur token ≥ refresh_after_hours (mendekati 24 jam).
 * - Reaktif (401): single-flight re-login via atomic lock; worker lain menunggu lalu
 *   PAKAI token baru (tidak login berkali-kali).
 * - Gagal login → alert (log tanpa secret) + lempar NeviraAuthException (BERHENTI, tidak loop).
 *
 * Bentuk entri cache: ['token' => string, 'acquired_at' => int(epoch)].
 */
final class NeviraTokenManager implements AccessTokenProvider
{
    public function __construct(private readonly HttpFactory $http) {}

    public function token(): string
    {
        $entry = $this->cache()->get($this->key());
        if ($this->isFresh($entry)) {
            return $entry['token'];
        }

        // Proaktif / awal run: login (single-flight).
        return $this->login(staleToken: is_array($entry) ? ($entry['token'] ?? null) : null);
    }

    public function refresh(?string $staleToken = null): string
    {
        return $this->login(staleToken: $staleToken);
    }

    public function forgetToken(): void
    {
        $this->cache()->forget($this->key());
    }

    /**
     * Login single-flight. Lock atomik memastikan hanya satu worker yang benar-benar
     * memanggil endpoint login; yang lain menunggu lalu memakai token hasil login itu.
     */
    private function login(?string $staleToken): string
    {
        $lock = $this->cache()->lock($this->key().':login', (int) config('nevira.auth.lock_seconds', 15));

        try {
            // block(): tunggu sampai lock didapat (atau timeout → LockTimeoutException).
            return $lock->block((int) config('nevira.auth.lock_wait_seconds', 15), function () use ($staleToken) {
                // Double-check: worker lain mungkin sudah login selagi kita menunggu.
                $entry = $this->cache()->get($this->key());
                if ($this->isFresh($entry) && ($staleToken === null || $entry['token'] !== $staleToken)) {
                    return $entry['token']; // peer sudah refresh → reuse (single-flight)
                }

                return $this->requestLoginAndCache();
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            // Tak dapat lock dalam batas waktu: pakai token cache bila ada & beda dari stale.
            $entry = $this->cache()->get($this->key());
            if ($this->isFresh($entry) && ($staleToken === null || $entry['token'] !== $staleToken)) {
                return $entry['token'];
            }
            Metrics::increment(Metrics::NEVIRA_REAUTH_FAILURES);
            Log::error('NEVIRA re-auth: gagal memperoleh login lock (single-flight).', [
                'cache_key' => $this->key(),
            ]);
            throw new NeviraAuthException('Gagal memperoleh lock re-login NEVIRA (single-flight timeout).');
        }
    }

    /** Panggil endpoint login dgn service credential, simpan token + waktu ke cache. */
    private function requestLoginAndCache(): string
    {
        $username = (string) config('nevira.service_username');
        $password = (string) config('nevira.service_password');

        if ($username === '' || $password === '') {
            // Alert TANPA membocorkan apa pun.
            Metrics::increment(Metrics::NEVIRA_REAUTH_FAILURES);
            Log::error('NEVIRA re-auth gagal: service credential belum dikonfigurasi.');
            throw new NeviraAuthException('Service credential NEVIRA belum dikonfigurasi (secret store).');
        }

        $response = $this->http
            ->baseUrl((string) config('nevira.base_url'))
            ->acceptJson()
            ->timeout((int) config('nevira.timeout', 30))
            ->post((string) config('nevira.login_path', '/api/login'), [
                'username' => $username,
                'password' => $password,
            ]);

        if ($response->failed()) {
            // Alert: log status SAJA, tidak ada credential/token.
            Metrics::increment(Metrics::NEVIRA_REAUTH_FAILURES);
            Log::error('NEVIRA re-auth gagal: login endpoint menolak.', [
                'status' => $response->status(),
                'login_path' => config('nevira.login_path'),
            ]);
            throw new NeviraAuthException("NEVIRA login gagal (HTTP {$response->status()}).");
        }

        $body = (array) $response->json();
        $token = data_get($body, 'token')
            ?? data_get($body, 'access_token')
            ?? data_get($body, 'data.token');

        if (! is_string($token) || $token === '') {
            Metrics::increment(Metrics::NEVIRA_REAUTH_FAILURES);
            Log::error('NEVIRA re-auth gagal: response login tanpa token.', [
                'login_path' => config('nevira.login_path'),
            ]);
            throw new NeviraAuthException('Response login NEVIRA tidak mengandung token.');
        }

        $this->cache()->put(
            $this->key(),
            ['token' => $token, 'acquired_at' => now()->timestamp],
            now()->addHours((int) config('nevira.auth.lifetime_hours', 24)),
        );

        return $token;
    }

    private function isFresh(mixed $entry): bool
    {
        if (! is_array($entry) || empty($entry['token']) || ! isset($entry['acquired_at'])) {
            return false;
        }

        $ageHours = (now()->timestamp - (int) $entry['acquired_at']) / 3600;

        return $ageHours < (int) config('nevira.auth.refresh_after_hours', 23);
    }

    private function cache(): CacheRepository
    {
        return Cache::store(config('nevira.auth.cache_store')); // null → store default (Redis prod)
    }

    private function key(): string
    {
        return (string) config('nevira.auth.cache_key', 'nevira:access_token');
    }
}
