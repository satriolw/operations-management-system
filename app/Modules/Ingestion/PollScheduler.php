<?php

namespace App\Modules\Ingestion;

use App\Modules\Reporting\OutletCalendar;
use App\Support\Time\Wib;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Adaptive polling (OPS-109). Membuat poller sinyal aman dijadwalkan SERING (mis. tiap 10 menit)
 * tanpa kerja/biaya API redundan:
 *
 *  - Gerbang JAM OPERASIONAL: skip outlet yang sedang tutup (latensi rendah saat ramai, diam saat
 *    sepi) — reuse OutletCalendar, tanpa poller baru.
 *  - WATERMARK per (check, outlet): jeda minimum antar-poll (cache TTL). Tick scheduler yang lebih
 *    rapat dari cadence efektif outlet otomatis no-op.
 *
 * Cadence efektif configurable (`config('nevira.poll_cadence')`, menit) — bukan hardcode. Watermark
 * = state operasional (cache), BUKAN kebenaran NEVIRA → aman hilang (paling-paling satu poll ekstra).
 */
final class PollScheduler
{
    public function __construct(
        private readonly Cache $cache,
        private readonly OutletCalendar $calendar,
    ) {}

    /**
     * Boleh poll $check untuk $idOutlet pada $now? True hanya bila outlet BUKA sekarang DAN
     * watermark terakhir lebih tua dari cadence efektif. TIDAK menandai — pemanggil yang
     * berhasil memproses memanggil markPolled() (agar kegagalan tak menyetel watermark).
     */
    public function shouldPoll(string $check, int $idOutlet, CarbonInterface $now): bool
    {
        $now = Wib::normalize($now);

        if (! $this->calendar->isOpenNow($idOutlet, $now)) {
            return false;
        }

        $last = $this->cache->get($this->key($check, $idOutlet));
        if ($last === null) {
            return true;
        }

        $minMinutes = $this->cadenceMinutes($check);

        return Wib::parse($last)->addMinutes($minMinutes)->lte($now);
    }

    /** Catat watermark poll sukses. TTL = 2× cadence (cukup; tak perlu persist permanen). */
    public function markPolled(string $check, int $idOutlet, CarbonInterface $now): void
    {
        $now = Wib::normalize($now);
        $ttl = max(60, $this->cadenceMinutes($check) * 2 * 60); // detik
        $this->cache->put($this->key($check, $idOutlet), $now->toIso8601String(), $ttl);
    }

    /** Cadence efektif (menit) untuk $check; fallback ke 'default' lalu 15. */
    public function cadenceMinutes(string $check): int
    {
        $cfg = (array) config('nevira.poll_cadence', []);

        return (int) ($cfg[$check] ?? $cfg['default'] ?? 15);
    }

    private function key(string $check, int $idOutlet): string
    {
        return "nevira:poll:{$check}:{$idOutlet}";
    }
}
