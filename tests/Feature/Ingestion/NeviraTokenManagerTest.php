<?php

use App\Modules\Ingestion\Auth\NeviraTokenManager;
use App\Modules\Ingestion\Contracts\AccessTokenProvider;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\Exceptions\NeviraAuthException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OPS-108 · token lifecycle NEVIRA (login 24 jam, re-auth single-flight).
 */

const TOKEN_KEY = 'nevira:access_token';

function seedToken(string $token, int $ageHours): void
{
    Cache::store('array')->put(TOKEN_KEY, [
        'token' => $token,
        'acquired_at' => now()->subHours($ageHours)->timestamp,
    ], now()->addHours(24));
}

function loginCount(): int
{
    return Http::recorded(fn (Request $r) => str_contains($r->url(), '/api/login'))->count();
}

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'nevira.base_url' => 'https://api.nevira.id',
        'nevira.login_path' => '/api/login',
        'nevira.service_username' => 'svc-user-xyz',
        'nevira.service_password' => 'SUPER-SECRET-PW-123',
        'nevira.auth.cache_store' => 'array',
        'nevira.auth.refresh_after_hours' => 23,
        'nevira.auth.lifetime_hours' => 24,
    ]);
    Cache::store('array')->flush();
});

afterEach(fn () => Carbon::setTestNow());

it('binding memilih NeviraTokenManager saat service credential tersedia', function () {
    expect(app(AccessTokenProvider::class))->toBeInstanceOf(NeviraTokenManager::class);
});

// (a) token kedaluwarsa → re-login otomatis, request lanjut
it('(a) token kosong/kedaluwarsa → login otomatis & token dipakai request', function () {
    Http::fake([
        '*/api/login' => Http::response(['token' => 'tok-fresh-1']),
        '*reports/dashboard*' => Http::response(['total_sales' => 999]),
    ]);

    // cache kosong → login proaktif
    $dto = app(TransactionSource::class)->dailyDashboard(120, '2026-06-12');

    expect($dto->get('total_sales'))->toBe(999);
    expect(loginCount())->toBe(1);
    // request dashboard memakai token hasil login
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'reports/dashboard')
        && $r->hasHeader('Authorization', 'Bearer tok-fresh-1'));
});

it('(a2) token mendekati 24 jam (≥23h) → refresh proaktif', function () {
    seedToken('tok-old', ageHours: 23);
    Http::fake(['*/api/login' => Http::response(['token' => 'tok-new'])]);

    $token = app(AccessTokenProvider::class)->token();

    expect($token)->toBe('tok-new');
    expect(loginCount())->toBe(1);
});

it('(a3) token masih muda (<23h) → TIDAK login, pakai cache', function () {
    seedToken('tok-young', ageHours: 1);
    Http::fake(['*/api/login' => Http::response(['token' => 'should-not-be-used'])]);

    $token = app(AccessTokenProvider::class)->token();

    expect($token)->toBe('tok-young');
    expect(loginCount())->toBe(0);
});

// (b) banyak worker bersamaan → re-login SEKALI (single-flight / shared cache)
it('(b) banyak panggilan berbagi cache → login terjadi SEKALI', function () {
    Http::fake(['*/api/login' => Http::response(['token' => 'tok-shared'])]);
    $manager = app(AccessTokenProvider::class);

    $tokens = collect(range(1, 6))->map(fn () => $manager->token());

    expect($tokens->unique()->all())->toBe(['tok-shared']); // semua worker token sama
    expect(loginCount())->toBe(1);                           // login hanya sekali
});

it('(b2) single-flight: peer sudah refresh (cache != stale) → reuse tanpa login', function () {
    seedToken('tok-peer-fresh', ageHours: 0);
    Http::fake(['*/api/login' => Http::response(['token' => 'should-not-login'])]);

    // worker ini 401 dgn token lama 'tok-stale'; peer sudah taruh token baru di cache
    $token = app(AccessTokenProvider::class)->refresh(staleToken: 'tok-stale');

    expect($token)->toBe('tok-peer-fresh');
    expect(loginCount())->toBe(0);
});

it('(b3) refresh dgn stale == cache → benar-benar login ulang sekali', function () {
    seedToken('tok-stale', ageHours: 0);
    Http::fake(['*/api/login' => Http::response(['token' => 'tok-reloged'])]);

    $token = app(AccessTokenProvider::class)->refresh(staleToken: 'tok-stale');

    expect($token)->toBe('tok-reloged');
    expect(loginCount())->toBe(1);
});

it('reaktif 401 → re-login lalu retry request SEKALI dan sukses', function () {
    seedToken('tok-seed', ageHours: 1); // ada token segar → tak ada login proaktif
    $dashHits = 0;
    Http::fake(function (Request $request) use (&$dashHits) {
        if (str_contains($request->url(), '/api/login')) {
            return Http::response(['token' => 'tok-after-401']);
        }
        // dashboard: 401 dulu, lalu 200
        $dashHits++;

        return $dashHits === 1
            ? Http::response('expired', 401)
            : Http::response(['total_sales' => 555]);
    });

    $dto = app(TransactionSource::class)->dailyDashboard(120, '2026-06-12');

    expect($dto->get('total_sales'))->toBe(555);
    expect($dashHits)->toBe(2);          // 401 + retry sukses
    expect(loginCount())->toBe(1);       // re-login sekali
    // retry memakai token baru
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'reports/dashboard')
        && $r->hasHeader('Authorization', 'Bearer tok-after-401'));
});

// (c) re-auth gagal → alert + fallback (exception), bukan retry tak hingga
it('(c) login endpoint menolak → NeviraAuthException + alert, login dicoba SEKALI (tak loop)', function () {
    Http::fake(['*/api/login' => Http::response('server error', 500)]);
    Log::spy();

    expect(fn () => app(AccessTokenProvider::class)->token())
        ->toThrow(NeviraAuthException::class);

    expect(loginCount())->toBe(1); // tidak loop
    Log::shouldHaveReceived('error')->atLeast()->once();
});

it('(c2) 401 berulang meski sudah re-login → berhenti dgn NeviraAuthException (bounded)', function () {
    seedToken('tok-seed', ageHours: 1); // tak ada login proaktif
    $dashHits = 0;
    Http::fake(function (Request $request) use (&$dashHits) {
        if (str_contains($request->url(), '/api/login')) {
            return Http::response(['token' => 'tok-x']);
        }
        $dashHits++;

        return Http::response('still unauthorized', 401); // selalu 401
    });

    expect(fn () => app(TransactionSource::class)->dailyDashboard(120, '2026-06-12'))
        ->toThrow(NeviraAuthException::class);

    expect($dashHits)->toBe(2);    // asli + 1 retry, lalu berhenti (tidak loop)
    expect(loginCount())->toBe(1);
});

it('(c3) 403 forbidden → NeviraAuthException tanpa re-login (bukan token kedaluwarsa)', function () {
    seedToken('tok-young', ageHours: 1);
    Http::fake([
        '*/api/login' => Http::response(['token' => 'nope']),
        '*reports/dashboard*' => Http::response('forbidden', 403),
    ]);

    expect(fn () => app(TransactionSource::class)->dailyDashboard(120, '2026-06-12'))
        ->toThrow(NeviraAuthException::class);

    expect(loginCount())->toBe(0); // 403 tidak memicu re-login
});

// (d) tidak ada kredensial/secret/token di log
it('(d) kegagalan login TIDAK membocorkan credential/token ke log', function () {
    Http::fake(['*/api/login' => Http::response('boom', 500)]);

    $logged = '';
    Log::listen(function ($e) use (&$logged) {
        $logged .= ' '.$e->message.' '.json_encode($e->context);
    });

    try {
        app(AccessTokenProvider::class)->token();
    } catch (NeviraAuthException $e) {
        // diharapkan
    }

    expect($logged)->not->toContain('SUPER-SECRET-PW-123')   // password
        ->and($logged)->not->toContain('svc-user-xyz')        // username
        ->and($logged)->not->toContain('password');           // tak ada kunci kredensial
});

it('(d2) jalur sukses tidak menuliskan token ke log', function () {
    Http::fake([
        '*/api/login' => Http::response(['token' => 'tok-secret-sukses']),
        '*reports/dashboard*' => Http::response(['total_sales' => 1]),
    ]);

    $logged = '';
    Log::listen(function ($e) use (&$logged) {
        $logged .= ' '.$e->message.' '.json_encode($e->context);
    });

    app(TransactionSource::class)->dailyDashboard(120, '2026-06-12');

    expect($logged)->not->toContain('tok-secret-sukses');
});
