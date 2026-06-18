<?php

use App\Models\Outlet;
use App\Models\OutletHoliday;
use App\Models\OutletOperatingHour;
use App\Modules\Ingestion\PollScheduler;
use App\Support\Time\Wib;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    $this->poll = app(PollScheduler::class);
    // Senin 2026-06-15 10:00 WIB; outlet buka 08:00–20:00 Senin (weekday 1).
    $this->now = Wib::parse('2026-06-15 10:00:00');
    OutletOperatingHour::create(['id_outlet' => 120, 'weekday' => 1, 'is_closed' => false, 'open_time' => '08:00', 'close_time' => '20:00']);
});

it('boleh poll saat outlet buka & belum ada watermark', function () {
    expect($this->poll->shouldPoll('late_orders', 120, $this->now))->toBeTrue();
});

it('tak boleh poll saat outlet tutup sekarang (di luar jam)', function () {
    $early = Wib::parse('2026-06-15 06:30:00'); // sebelum buka 08:00
    expect($this->poll->shouldPoll('late_orders', 120, $early))->toBeFalse();
});

it('tak boleh poll saat hari libur', function () {
    OutletHoliday::create(['id_outlet' => 120, 'holiday_date' => '2026-06-15']);
    expect($this->poll->shouldPoll('late_orders', 120, $this->now))->toBeFalse();
});

it('watermark menahan poll dalam cadence, lalu lepas setelah lewat', function () {
    config(['nevira.poll_cadence' => ['late_orders' => 15, 'default' => 15]]);

    $this->poll->markPolled('late_orders', 120, $this->now);
    expect($this->poll->shouldPoll('late_orders', 120, $this->now->copy()->addMinutes(10)))->toBeFalse()
        ->and($this->poll->shouldPoll('late_orders', 120, $this->now->copy()->addMinutes(16)))->toBeTrue();
});

it('cadence efektif dari config; fallback ke default lalu 15', function () {
    config(['nevira.poll_cadence' => ['late_orders' => 30, 'default' => 20]]);
    expect($this->poll->cadenceMinutes('late_orders'))->toBe(30)
        ->and($this->poll->cadenceMinutes('tak_ada'))->toBe(20);

    config(['nevira.poll_cadence' => []]);
    expect($this->poll->cadenceMinutes('apa_pun'))->toBe(15);
});

it('jam tak terkonfigurasi → dianggap buka penuh (boleh poll kapan saja)', function () {
    Outlet::factory()->create(['id_outlet' => 200]);
    // tanpa OutletOperatingHour → buka penuh
    $midnight = Wib::parse('2026-06-15 23:30:00');
    expect($this->poll->shouldPoll('late_orders', 200, $midnight))->toBeTrue();
});
