<?php

use App\Support\Observability\JobTelemetry;
use App\Support\Observability\Metrics;
use Illuminate\Support\Facades\Log;

/**
 * OPS-701 · log terstruktur per job (sukses & gagal), tanpa secret/PII.
 */

beforeEach(function () {
    config(['cache.default' => 'array', 'oms.metrics_cache_store' => 'array']);
    $this->logs = [];
    Log::listen(fn ($e) => $this->logs[] = ['message' => $e->message, 'context' => $e->context]);
});

it('mencatat log sukses terstruktur + latensi saat job berhasil', function () {
    $result = JobTelemetry::run('reporting.generate', [
        'id_outlet' => 120, 'report_date' => '2026-06-12',
    ], fn () => 'done');

    expect($result)->toBe('done');

    $ok = collect($this->logs)->firstWhere('message', 'job.success');
    expect($ok)->not->toBeNull()
        ->and($ok['context']['job'])->toBe('reporting.generate')
        ->and($ok['context']['id_outlet'])->toBe(120)
        ->and($ok['context']['report_date'])->toBe('2026-06-12')
        ->and($ok['context']['result'])->toBe('success')
        ->and($ok['context'])->toHaveKey('duration_ms');

    expect(Metrics::latency('reporting.generate')['count'])->toBe(1);
});

it('mencatat log gagal + latensi lalu melempar ulang saat job gagal', function () {
    expect(fn () => JobTelemetry::run('reporting.generate', ['id_outlet' => 120],
        fn () => throw new RuntimeException('boom')))
        ->toThrow(RuntimeException::class, 'boom');

    $fail = collect($this->logs)->firstWhere('message', 'job.failure');
    expect($fail)->not->toBeNull()
        ->and($fail['context']['result'])->toBe('failure')
        ->and($fail['context']['exception'])->toBe(RuntimeException::class)
        ->and($fail['context']['message'])->toBe('boom')
        ->and($fail['context'])->toHaveKey('duration_ms');

    expect(Metrics::latency('reporting.generate')['count'])->toBe(1);
});

it('TIDAK me-log secret/token/PII dari context', function () {
    JobTelemetry::run('x', [
        'id_outlet' => 120,
        'token' => 'SEKRET-TOKEN',
        'password' => 'PW-RAHASIA',
        'authorization' => 'Bearer ZZZ',
        'customer_name' => 'Budi Santoso',
        'customer_phone' => '0812xxx',
    ], fn () => null);

    $ok = collect($this->logs)->firstWhere('message', 'job.success');
    expect($ok['context'])->toHaveKey('id_outlet')
        ->and($ok['context'])->not->toHaveKeys(['token', 'password', 'authorization', 'customer_name', 'customer_phone']);

    // dan tidak ada nilai rahasia/PII di seluruh log
    $dump = json_encode($this->logs);
    expect($dump)->not->toContain('SEKRET-TOKEN')
        ->and($dump)->not->toContain('PW-RAHASIA')
        ->and($dump)->not->toContain('Budi Santoso');
});

it('sanitize membuang kunci secret & PII (nested)', function () {
    $clean = JobTelemetry::sanitize([
        'id_outlet' => 1,
        'api_key' => 'x',
        'meta' => ['secret' => 'y', 'report_date' => '2026-06-12'],
    ]);

    expect($clean)->toBe(['id_outlet' => 1, 'meta' => ['report_date' => '2026-06-12']]);
});
