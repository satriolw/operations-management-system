---
name: scaffold-job
description: Scaffold a new Laravel Queue Job for the OMS modular monolith following project conventions (domain namespace, ShouldQueue, idempotency, WithoutOverlapping, TransactionSource injection) plus a companion Pest test. Use when the user asks to create/add a new queue job, background job, or async task in this repo.
---

# Scaffold a Queue Job (OMS Modul 1)

Generate a new Laravel Queue Job that obeys the CLAUDE.md golden rules, plus a companion Pest test.
Reference implementation already in the repo: `app/Modules/Reporting/Jobs/GenerateDailyReportJob.php`
and its test `tests/Feature/Reporting/GenerateDailyReportJobTest.php` — match that style.

## 1. Gather inputs (ask only if not given)

- **Job name** — StudlyCase, ends with `Job` (e.g. `RevenueAdjustmentJob`).
- **Domain module** — one of `Ingestion`, `Reporting`, `Revenue`, `Signals`, `Delivery`. Pick by purpose;
  if ambiguous, ask. File goes to `app/Modules/<Domain>/Jobs/<Name>.php`.
- **Needs NEVIRA data?** — if yes, the job consumes the `TransactionSource` interface (never a concrete client).
- **Idempotency unit** — usually `(id_outlet, report_date)`; reuse `App\Support\Idempotency\IdempotencyKey`.
  Per-outlet work → key by outlet (+ date). State the unit before writing.
- **Constructor args** — scalars/ids only (jobs are serialized to the queue). Resolve services in `handle()`
  or type-hint interfaces the container can inject into `handle()`.

## 2. Golden rules (CLAUDE.md — non-negotiable)

1. **No direct NEVIRA DB.** If the job reads NEVIRA, depend on `App\Modules\Ingestion\Contracts\TransactionSource`
   (anti-corruption layer) — resolve via `handle(TransactionSource $source)` or constructor of a service, never
   a concrete `NeviraApiSource` / raw HTTP / DB.
2. **No NEVIRA truth persisted, no customer PII.** Persist only derived output + references
   (`transaction_number`, numeric `id_cashier`). For any signal payload use `App\Support\Privacy\PiiPolicy::scrubSignalPayload()`.
3. **All dates WIB.** Use `App\Support\Time\Wib` (`Wib::normalize(now())`, `Wib::parse(...)`). Never raw `now()->format('Y-m-d')`
   for business dates. (Duration/TTL math may use raw timestamps.)
4. **Idempotent.** Re-run/replay must not double-effect. Use `firstOrCreate` + a DB unique index, or a guard that
   skips already-processed work. Key from `IdempotencyKey`.
5. **Observability + active-outlet guard.** Wrap the body in `App\Support\Observability\JobTelemetry::run('<module>.<action>', [...ctx], fn () => ...)`
   (ctx: `id_outlet`, `report_date`, etc — telemetry auto-sanitizes secrets/PII). For per-outlet jobs, early-return if the
   outlet is missing/inactive (see `GenerateDailyReportJob`).

## 3. Job template

```php
<?php

namespace App\Modules\<Domain>\Jobs;

use App\Support\Observability\JobTelemetry;
use App\Support\Time\Wib;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
// use App\Modules\Ingestion\Contracts\TransactionSource;  // only if it reads NEVIRA

class <Name> implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int,int> backoff detik (System Design §3.6) */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $idOutlet,
        public readonly ?string $reportDate = null, // null → hari ini (WIB)
    ) {}

    /** Cegah eksekusi tumpang tindih untuk outlet yang sama (hapus bila tak relevan). */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('oms:<module>-<action>:'.$this->idOutlet))
                ->releaseAfter(60)->expireAfter(900),
        ];
    }

    public function handle(/* TransactionSource $source */): void
    {
        $date = $this->reportDate ?? Wib::normalize(now())->format('Y-m-d');

        JobTelemetry::run('<module>.<action>', [
            'id_outlet' => $this->idOutlet,
            'report_date' => $date,
        ], function () use ($date /*, $source */) {
            // 1) Guard outlet aktif (per-outlet jobs):
            //    $outlet = \App\Models\Outlet::find($this->idOutlet);
            //    if ($outlet === null || ! $outlet->active) { return; }

            // 2) Idempotency: firstOrCreate + unique index ATAU skip bila sudah diproses.
            //    Kunci: \App\Support\Idempotency\IdempotencyKey::reportRun($this->idOutlet, $date)

            // 3) Kerja domain. Bila butuh NEVIRA: $source->dailyDashboard(...) / voidRefunds(...) / unpaid(...).
            //    Persist HANYA output turunan; payload sinyal lewat PiiPolicy::scrubSignalPayload().
        });
    }
}
```

Trim what's unused: drop `WithoutOverlapping`/`middleware()` if no per-outlet overlap risk; drop the
`TransactionSource` import/param if it doesn't read NEVIRA; drop the date logic if dateless.

## 4. Companion Pest test

Create `tests/Feature/<Domain>/<Name>Test.php`. Use `RefreshDatabase`. Cover **happy path** + **one edge case**.
Run the job via `(new <Name>(...))->handle()` (direct) or `<Name>::dispatchSync(...)`. Set `config(['cache.default' => 'array', 'oms.metrics_cache_store' => 'array'])` if it touches metrics. Fake NEVIRA with `Http::fake()` + fixtures in `tests/Fixtures/nevira/` when the job reads NEVIRA; force `ConfigTokenProvider` by nulling `nevira.service_username/password` in config so tests don't depend on `.env`.

```php
<?php

use App\Models\Outlet;
use App\Modules\<Domain>\Jobs\<Name>;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('happy path: <effek yang diharapkan, idempoten>', function () {
    Outlet::factory()->create(['id_outlet' => 120]);

    (new <Name>(120, '2026-06-12'))->handle();
    (new <Name>(120, '2026-06-12'))->handle(); // re-run → TANPA efek ganda

    // expect(... satu efek saja ...);
});

it('edge: outlet non-aktif / tak terdaftar → di-skip tanpa efek', function () {
    Outlet::factory()->inactive()->create(['id_outlet' => 120]);

    (new <Name>(120, '2026-06-12'))->handle();

    // expect(... tidak ada efek ...);
});
```

Pick the edge case that fits the job: inactive/missing outlet, midnight-boundary date (WIB), empty NEVIRA result,
or 401/transient handled by the source. For date logic, ALWAYS add a midnight-boundary assertion (R1).

## 5. Finish

- Run `export PATH="$HOME/.local/bin:$PATH" && php artisan test --filter=<Name>` and report the result.
- If the job is dispatched on a schedule, note that wiring belongs in `routes/console.php` (see `DailyReportScheduler`) — do it only if asked.
- List files created and tick the golden rules (no DB NEVIRA, no PII, WIB, idempotent, observability).
- Commit only if the user asks; one job per ticket/PR.

## Definition of Done
ShouldQueue + tries/backoff · idempotent (verified by test) · TransactionSource (not concrete) if it reads NEVIRA ·
no customer PII · dates via Wib · JobTelemetry wrapper · companion Pest test (happy + edge) green.
