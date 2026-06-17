<?php

use App\Models\Outlet;
use App\Models\SignalEvent;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Ingestion\DTO\TransactionDTO;
use App\Modules\Signals\Contracts\ReplacementMatcher;
use App\Modules\Signals\OrphanedProductionDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
        'nevira.service_username' => null, 'nevira.service_password' => null,
    ]);
    Outlet::factory()->create(['id_outlet' => 120]);
});

function fakeV(array $void): void
{
    Http::fake(function (Request $r) use ($void) {
        parse_str(parse_url($r->url(), PHP_URL_QUERY) ?? '', $q);

        return Http::response(['data' => ($q['status'] ?? '') === 'VOID' ? $void : [], 'last_page' => 1, 'current_page' => 1, 'next_page_url' => null]);
    });
}

function prod(string $no, int $progress): array
{
    return [
        'transaction_number' => $no, 'status' => 'VOID', 'grand_total' => 120000,
        'created_at' => '2026-06-10 18:00:00', 'approve_refund_void_date' => '2026-06-12 10:00:00',
        'void_notes' => 'customer batal', 'id_cashier' => 181, 'progress_percentage' => $progress,
    ];
}

function scanOrphan(): \Illuminate\Support\Collection
{
    return app(OrphanedProductionDetector::class)->scan(120, new DateRange('2026-06-05', '2026-06-12'));
}

it('void progress > 0 tanpa pengganti → ORPHANED_PRODUCTION perlu ditinjau (kasus 6003)', function () {
    fakeV([prod('INV/6003', 100)]);

    $sigs = scanOrphan();
    expect($sigs)->toHaveCount(1);
    $s = $sigs->first();
    expect($s->type)->toBe('ORPHANED_PRODUCTION')
        ->and($s->payload_json['progress_percentage'])->toBe(100)
        ->and($s->payload_json['label'])->toBe('needs_review');
});

it('progress 0 → tidak diflag (bukan produksi berjalan)', function () {
    fakeV([prod('INV/X', 0)]);
    expect(scanOrphan())->toHaveCount(0);
});

it('abstraksi: matcher menemukan pengganti → tidak diflag', function () {
    app()->instance(ReplacementMatcher::class, new class implements ReplacementMatcher
    {
        public function hasReplacement(TransactionDTO $t): bool
        {
            return true;
        }
    });
    fakeV([prod('INV/HAS', 100)]);

    expect(scanOrphan())->toHaveCount(0)
        ->and(SignalEvent::count())->toBe(0);
});

it('idempoten', function () {
    fakeV([prod('INV/6003', 100)]);
    scanOrphan();
    scanOrphan();
    expect(SignalEvent::where('type', 'ORPHANED_PRODUCTION')->count())->toBe(1);
});
