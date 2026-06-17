<?php

namespace App\Modules\Ingestion;

use App\Modules\Ingestion\Contracts\AccessTokenProvider;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\DashboardDTO;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Ingestion\Exceptions\NeviraAuthException;
use App\Modules\Ingestion\Exceptions\NeviraRequestException;
use App\Support\Observability\Metrics;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;

/**
 * Implementasi REST NEVIRA di balik interface TransactionSource (OPS-102).
 *
 * - Bearer token dari AccessTokenProvider (config/secret; OPS-108 mengganti dgn token manager).
 * - Retry + backoff untuk transient 429/5xx; hormati header Retry-After pada 429.
 * - 401/403 dilempar sebagai NeviraAuthException (BUKAN transient) → OPS-108.
 * - Paginasi otomatis: kumpulkan semua halaman via next_page_url/last_page.
 * - Akses NEVIRA HANYA via REST API; tidak ada direct DB.
 */
final class NeviraApiSource implements TransactionSource
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly AccessTokenProvider $tokens,
    ) {}

    public function dailyDashboard(int $outletId, CarbonInterface|string $date): DashboardDTO
    {
        $d = $date instanceof CarbonInterface ? $date->format('Y-m-d') : (string) $date;

        $payload = $this->get('/api/reports/dashboard', [
            'id_outlet' => $outletId,
            'start_date' => $d,
            'end_date' => $d,
        ]);

        return DashboardDTO::fromResponse($outletId, $d, $payload);
    }

    public function voidRefunds(int $outletId, DateRange $range): Collection
    {
        // VOID dan REFUND adalah dua status terpisah di NEVIRA (PRD §6.2) → gabungkan.
        $void = $this->collectPages('/api/transactions', [
            'status' => 'VOID',
            'is_void_refund' => 'true',
            'id_outlet' => $outletId,
            'start_date' => $range->startDate(),
            'end_date' => $range->endDate(),
        ]);

        $refund = $this->collectPages('/api/transactions', [
            'status' => 'REFUND',
            'is_void_refund' => 'true',
            'id_outlet' => $outletId,
            'start_date' => $range->startDate(),
            'end_date' => $range->endDate(),
        ]);

        return $void->concat($refund)->values();
    }

    public function unpaid(int $outletId, DateRange $range): Collection
    {
        return $this->collectPages('/api/transactions', [
            'payment_status' => 'UNPAID',
            'id_outlet' => $outletId,
            'start_date' => $range->startDate(),
            'end_date' => $range->endDate(),
        ]);
    }

    public function activeOrders(int $outletId): Collection
    {
        // Param server "order belum selesai" BELUM dikonfirmasi NEVIRA (enum status terminal jg
        // perlu konfirmasi, Epic M) → params configurable + guard sisi-klien (completion_date null).
        $params = (array) config('nevira.active_orders_params', []);

        return $this->collectPages('/api/transactions', $params + ['id_outlet' => $outletId])
            ->filter(fn ($row) => $this->isActiveOrder($row)) // backlog aktif = belum selesai
            ->values();
    }

    /** Backlog aktif = belum selesai. completion_date null = belum selesai (sinyal utama). */
    private function isActiveOrder(array $row): bool
    {
        return ($row['completion_date'] ?? null) === null;
    }

    /**
     * Ikuti paginasi Laravel NEVIRA sampai habis (next_page_url null / current_page ≥ last_page).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function collectPages(string $path, array $query): Collection
    {
        $rows = collect();
        $page = 1;
        $maxPages = (int) config('nevira.max_pages', 1000);

        do {
            $payload = $this->get($path, $query + [
                'per_page' => (int) config('nevira.per_page', 50),
                'page' => $page,
            ]);

            $data = $payload['data'] ?? null;
            if (! is_array($data)) {
                throw new NeviraRequestException(
                    "Response NEVIRA {$path} tak punya array 'data' (page {$page}) — bentuk berubah?"
                );
            }
            $rows = $rows->concat($data);

            $lastPage = (int) ($payload['last_page'] ?? $page);
            $current = (int) ($payload['current_page'] ?? $page);
            $hasNext = ! empty($payload['next_page_url']) && $current < $lastPage;
            $page++;
        } while ($hasNext && $page <= $maxPages);

        return $rows->values();
    }

    /**
     * GET satu request dengan auth + retry transient. Mengembalikan body JSON terdekode.
     *
     * @return array<string, mixed>
     */
    private function get(string $path, array $query): array
    {
        $maxTries = max(1, (int) config('nevira.retry.times', 3));
        $backoff = (array) config('nevira.retry.backoff_ms', [1000, 3000, 9000]);
        $transientAttempt = 0;
        $reauthDone = false;

        while (true) {
            $token = $this->tokens->token();
            Metrics::increment(Metrics::NEVIRA_CALLS);
            $response = $this->http
                ->baseUrl((string) config('nevira.base_url'))
                ->withToken($token)
                ->acceptJson()
                ->timeout((int) config('nevira.timeout', 30))
                ->get($path, $query);

            $status = $response->status();

            // 401 — token mungkin kedaluwarsa. Reaktif: single-flight re-login lalu retry SEKALI.
            // 401/403 BUKAN error transient → tidak masuk jalur backoff 429/5xx.
            if ($status === 401 && ! $reauthDone) {
                $this->tokens->refresh($token); // single-flight; melempar NeviraAuthException bila gagal (no loop)
                $reauthDone = true;

                continue; // retry request asli sekali dgn token baru
            }

            if ($status === 401 || $status === 403) {
                // Sudah re-auth tapi tetap 401, atau 403 (forbidden) → berhenti, bukan loop.
                $this->tokens->forgetToken();
                throw new NeviraAuthException(
                    "NEVIRA auth gagal (HTTP {$status}) pada {$path}".($reauthDone ? ' setelah re-login.' : '.')
                );
            }

            // 429 / 5xx — transient. Backoff lalu retry sampai jatah habis.
            if ($status === 429 || $response->serverError()) {
                $transientAttempt++;
                if ($transientAttempt < $maxTries) {
                    Sleep::for($this->backoffMs($response, $backoff, $transientAttempt))->milliseconds();

                    continue;
                }
            }

            if ($response->failed()) {
                throw new NeviraRequestException(
                    "NEVIRA request gagal (HTTP {$status}) pada {$path}."
                );
            }

            return (array) $response->json();
        }
    }

    /** Tentukan jeda backoff: hormati Retry-After pada 429, jika tidak pakai tabel backoff. */
    private function backoffMs(Response $response, array $backoff, int $attempt): int
    {
        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After');
            if (is_numeric($retryAfter)) {
                return (int) $retryAfter * 1000;
            }
        }

        return (int) ($backoff[$attempt - 1] ?? end($backoff) ?: 1000);
    }
}
