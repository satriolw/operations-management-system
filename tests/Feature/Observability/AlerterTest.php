<?php

use App\Support\Observability\Alerter;
use App\Support\Observability\Events\OpsAlertRaised;
use App\Support\Observability\Metrics;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

beforeEach(fn () => config(['cache.default' => 'array', 'oms.metrics_cache_store' => 'array']));

it('raise: log channel oms + event OpsAlertRaised + metrik alerts', function () {
    Event::fake([OpsAlertRaised::class]);
    $logged = '';
    Log::listen(function ($e) use (&$logged) { $logged .= ' '.$e->message; });

    Alerter::raise('report.not_delivered', ['id_outlet' => 120]);

    expect($logged)->toContain('alert.report.not_delivered');
    expect(Metrics::get('alerts'))->toBe(1);
    Event::assertDispatched(OpsAlertRaised::class, fn ($e) => $e->code === 'report.not_delivered' && $e->context['id_outlet'] === 120);
});

it('raise menyanitasi secret/PII di context', function () {
    Event::fake([OpsAlertRaised::class]); // partial → MessageLogged tetap diteruskan ke Log::listen
    $captured = null;
    Log::listen(function ($e) use (&$captured) {
        if (str_starts_with($e->message, 'alert.')) {
            $captured = $e->context;
        }
    });

    Alerter::raise('x', ['id_outlet' => 120, 'token' => 'SECRET', 'customer_name' => 'Budi']);

    expect($captured)->toHaveKey('id_outlet')
        ->and($captured)->not->toHaveKeys(['token', 'customer_name']);
});
