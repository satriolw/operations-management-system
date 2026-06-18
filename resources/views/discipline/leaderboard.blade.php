@extends('layouts.app')

@section('title', 'Leaderboard '.$period)
@section('heading', 'Leaderboard')
@section('styles')<link href="{{ asset('css/oms-admin.css') }}" rel="stylesheet">
<style>
    .lb-wrap{max-width:760px;margin:0 auto;padding:28px 24px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}
    .lb-h{font-size:20px;font-weight:800;margin:0 0 4px;}
    .lb-sub{color:#6B7280;font-size:13px;margin:0 0 18px;}
    table{width:100%;border-collapse:collapse;font-size:13px;background:#fff;border:1px solid #E6E9EE;border-radius:12px;overflow:hidden;}
    th,td{text-align:left;padding:9px 11px;border-bottom:1px solid #EEF1F4;}
    th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#8B93A1;}
    .rank{font-weight:800;color:#1A4BA6;width:48px;}
    .score{font-weight:700;}
    .muted{color:#8B93A1;font-size:11.5px;}
</style>@endsection

@section('content')
<div class="lb-wrap">
    <h1 class="lb-h">Leaderboard · {{ $period }}</h1>
    <p class="lb-sub">Skor ternormalisasi (growth %, revenue per kapasitas, kepatuhan) — bukan revenue absolut. Rata-rata bergerak meredam dorongan akhir periode.</p>

    <table>
        <thead><tr><th>Rank</th><th>Outlet</th><th>Skor</th><th>Komponen</th></tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr>
                <td class="rank">#{{ $r->rank }}</td>
                <td>{{ optional($r->outlet)->name ?? $r->id_outlet }} <span class="muted">({{ $r->id_outlet }})</span></td>
                <td class="score">{{ number_format((float) $r->score, 1) }}</td>
                <td class="muted">
                    @php $b = $r->metric_breakdown_json ?? []; @endphp
                    growth {{ $b['growth'] ?? '—' }} · rev/kap {{ $b['revenue_per_capacity'] ?? '—' }} · patuh {{ $b['compliance'] ?? '—' }}
                </td>
            </tr>
        @empty
            <tr><td colspan="4" style="color:#8B93A1">Belum ada data leaderboard untuk periode/scope ini.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
