@extends('layouts.app')

@section('title', 'Laporan & Kirim')
@section('heading', 'Laporan & Kirim')
@section('subheading', 'Preview laporan harian · konfirmasi kirim (hybrid)')
@section('styles')
<style>
    .rp-wrap{max-width:1080px;margin:0 auto;padding:24px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}
    .filters{display:flex;flex-wrap:wrap;gap:8px;background:#fff;border:1px solid #E6E9EE;border-radius:12px;padding:12px;margin-bottom:16px;}
    .filters select,.filters input{border:1px solid #D8DCE3;border-radius:7px;padding:6px 8px;font:inherit;font-size:12.5px;}
    .btn{font:inherit;font-size:12.5px;font-weight:650;border-radius:8px;border:1px solid #2C6FE0;background:#2C6FE0;color:#fff;padding:6px 13px;cursor:pointer;text-decoration:none;}
    table{width:100%;border-collapse:collapse;font-size:12.5px;background:#fff;border:1px solid #E6E9EE;border-radius:12px;overflow:hidden;}
    th,td{text-align:left;padding:9px 10px;border-bottom:1px solid #EEF1F4;}
    th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#8B93A1;}
    .st{font-size:11px;font-weight:700;border-radius:6px;padding:2px 8px;}
    .st.confirmed_sent,.st.sent{background:#E7FFDB;color:#1B5E20;}
    .st.awaiting_confirmation{background:#FFF1E0;color:#9A5B00;}
    .st.failed{background:#FDE7E7;color:#A1281B;}
    a.lnk{color:#2C6FE0;text-decoration:none;}
    .ok{background:#F2FBED;border:1px solid #D6EFCB;color:#1B5E20;border-radius:8px;padding:8px 12px;margin-bottom:14px;font-size:13px;}
</style>
@endsection

@section('content')
<div class="rp-wrap">
    @if (session('status'))<div class="ok">{{ session('status') }}</div>@endif

    <form class="filters" method="GET">
        <select name="status"><option value="">Semua status run</option>
            @foreach (['PENDING','READY','SENT','SKIPPED'] as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>@endforeach
        </select>
        <input type="number" name="id_outlet" placeholder="id_outlet" value="{{ $filters['id_outlet'] ?? '' }}" style="width:90px">
        <input type="date" name="report_date" value="{{ $filters['report_date'] ?? '' }}">
        <button class="btn" type="submit">Filter</button>
    </form>

    <table>
        <thead><tr><th>Tanggal</th><th>Outlet</th><th>Status</th><th>Total</th><th>Realisasi</th><th>Piutang</th><th>Kirim</th><th></th></tr></thead>
        <tbody>
        @forelse ($runs as $r)
            <tr>
                <td><a class="lnk" href="{{ route('reports.show', $r) }}">{{ $r->report_date }}</a></td>
                <td>{{ $r->outlet->name ?? $r->id_outlet }}</td>
                <td>{{ $r->status }}</td>
                <td>Rp{{ number_format((float) $r->total_sales, 0, ',', '.') }}</td>
                <td>Rp{{ number_format((float) $r->realized, 0, ',', '.') }}</td>
                <td>Rp{{ number_format((float) $r->receivable, 0, ',', '.') }}</td>
                <td>
                    @forelse ($r->deliveries as $d)
                        <span class="st {{ $d->status }}">{{ $d->channel }}: {{ $d->status }}</span>
                    @empty
                        <span style="color:#8B93A1">—</span>
                    @endforelse
                </td>
                <td><a class="lnk" href="{{ route('reports.show', $r) }}">Preview →</a></td>
            </tr>
        @empty
            <tr><td colspan="8" style="color:#8B93A1">Tak ada laporan sesuai filter / scope outlet Anda.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div style="margin-top:14px">{{ $runs->links() }}</div>
</div>
@endsection
