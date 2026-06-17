@extends('layouts.admin')

@section('title', 'Dokumen Keuangan')
@section('styles')<link href="{{ asset('css/oms-admin.css') }}" rel="stylesheet">
<style>
    .fd-wrap{max-width:1080px;margin:0 auto;padding:28px 24px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}
    .fd-h{font-size:20px;font-weight:800;margin:0 0 16px;}
    .filters{display:flex;flex-wrap:wrap;gap:8px;background:#fff;border:1px solid #E6E9EE;border-radius:12px;padding:12px;margin-bottom:16px;}
    .filters select,.filters input{border:1px solid #D8DCE3;border-radius:7px;padding:6px 8px;font:inherit;font-size:12.5px;}
    .btn{font:inherit;font-size:12.5px;font-weight:650;border-radius:8px;border:1px solid #2C6FE0;background:#2C6FE0;color:#fff;padding:6px 13px;cursor:pointer;}
    table{width:100%;border-collapse:collapse;font-size:12.5px;background:#fff;border:1px solid #E6E9EE;border-radius:12px;overflow:hidden;}
    th,td{text-align:left;padding:9px 10px;border-bottom:1px solid #EEF1F4;}
    th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#8B93A1;}
    .st{font-size:11px;font-weight:700;border-radius:6px;padding:2px 8px;}
    .st.FINAL{background:#E7FFDB;color:#1B5E20;} .st.REJECTED{background:#FDECEA;color:#A1281B;}
    .st.DRAFT{background:#EEF1F4;color:#555E6C;} .st.SUBMITTED,.st.APPROVED_L1,.st.APPROVED_L2{background:#EBF2FE;color:#1A4BA6;}
    a{color:#2C6FE0;text-decoration:none;}
</style>@endsection

@section('body')
<div class="fd-wrap">
    <h1 class="fd-h">Dokumen Keuangan</h1>

    <form class="filters" method="GET">
        <select name="doc_type"><option value="">Semua jenis</option>@foreach ($docTypes as $t)<option value="{{ $t }}" @selected(($filters['doc_type'] ?? '') === $t)>{{ $t }}</option>@endforeach</select>
        <select name="status"><option value="">Semua status</option>@foreach ($statuses as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>@endforeach</select>
        <select name="brand"><option value="">Semua brand</option>@foreach (['LW','KWL'] as $b)<option value="{{ $b }}" @selected(($filters['brand'] ?? '') === $b)>{{ $b }}</option>@endforeach</select>
        <input type="number" name="id_outlet" placeholder="id_outlet" value="{{ $filters['id_outlet'] ?? '' }}" style="width:90px">
        <input type="date" name="period_start" value="{{ $filters['period_start'] ?? '' }}">
        <input type="date" name="period_end" value="{{ $filters['period_end'] ?? '' }}">
        <button class="btn" type="submit">Filter</button>
    </form>

    <table>
        <thead><tr><th>Doc Number</th><th>Jenis</th><th>Outlet</th><th>Judul</th><th>Nominal</th><th>Status</th><th>Approval</th></tr></thead>
        <tbody>
        @forelse ($documents as $d)
            <tr>
                <td><a href="{{ route('finance.documents.show', $d) }}">{{ $d->doc_number ?? '—' }}</a></td>
                <td>{{ $d->doc_type }}</td>
                <td>{{ $d->scope === 'HEAD_OFFICE' ? 'HO' : $d->id_outlet }}</td>
                <td>{{ \Illuminate\Support\Str::limit($d->title, 32) }}</td>
                <td>Rp{{ number_format((float) $d->amount, 0, ',', '.') }}</td>
                <td><span class="st {{ $d->status }}">{{ $d->status }}</span></td>
                <td>{{ $d->approvals_count }}× · L{{ $d->current_level }}</td>
            </tr>
        @empty
            <tr><td colspan="7" style="color:#8B93A1">Tak ada dokumen sesuai filter / scope.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div style="margin-top:14px">{{ $documents->links() }}</div>
</div>
@endsection
