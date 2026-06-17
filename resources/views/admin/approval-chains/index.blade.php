@extends('layouts.admin')

@section('title', 'Rantai Approval Dokumen')
@section('styles')<link href="{{ asset('css/oms-admin.css') }}" rel="stylesheet">
<style>
    .ac-wrap{max-width:960px;margin:0 auto;padding:28px 24px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}
    .ac-h{font-size:20px;font-weight:800;margin:0 0 4px;}
    .ac-sub{color:#6B7280;font-size:13px;margin:0 0 20px;}
    .ac-card{background:#fff;border:1px solid #E6E9EE;border-radius:12px;padding:18px;margin-bottom:16px;}
    table{width:100%;border-collapse:collapse;font-size:13px;}
    th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #EEF1F4;}
    th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#8B93A1;}
    select,input{border:1px solid #D8DCE3;border-radius:7px;padding:6px 8px;font:inherit;font-size:13px;}
    .btn{font:inherit;font-size:13px;font-weight:650;border-radius:8px;border:1px solid #2C6FE0;background:#2C6FE0;color:#fff;padding:7px 14px;cursor:pointer;}
    .btn.sec{background:#fff;color:#C0392B;border-color:#E5C4C0;}
    .flash{background:#E7FFDB;border:1px solid #BfeaA8;color:#1B5E20;border-radius:8px;padding:10px 12px;margin-bottom:16px;font-size:13px;}
    .errbox{background:#FDECEA;border:1px solid #F5C6C0;color:#A1281B;border-radius:8px;padding:10px 12px;margin-bottom:16px;font-size:13px;}
    .badge{font-size:11px;font-weight:700;border-radius:6px;padding:2px 7px;background:#EBF2FE;color:#1A4BA6;}
</style>@endsection

@section('body')
<div class="ac-wrap">
    <h1 class="ac-h">Rantai Approval Dokumen Keuangan</h1>
    <p class="ac-sub">Rantai dipilih per <b>band nominal</b> (LOW &lt;Rp1jt, HIGH ≥Rp1jt) × scope. Tiap level: role <b>atau</b> user. <span class="badge">doc_type kosong = semua jenis</span></p>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="errbox">{{ $errors->first() }}</div>@endif

    <div class="ac-card">
        <table>
            <thead><tr><th>Scope</th><th>Band</th><th>Jenis</th><th>Level</th><th>Role</th><th>User ID</th><th></th></tr></thead>
            <tbody>
            @forelse ($chains as $c)
                <tr>
                    <td>{{ $c->scope }}</td><td>{{ $c->amount_band }}</td>
                    <td>{{ $c->doc_type ?? '— semua —' }}</td><td>{{ $c->level }}</td>
                    <td>{{ $c->approver_role ?? '—' }}</td><td>{{ $c->approver_user_id ?? '—' }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.approval-chains.destroy', $c) }}" onsubmit="return confirm('Hapus level ini?')">
                            @csrf @method('DELETE')
                            <button class="btn sec" type="submit">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="color:#8B93A1">Belum ada rantai. Jalankan seeder default atau tambah di bawah.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="ac-card">
        <h3 style="margin:0 0 12px;font-size:15px">Tambah level</h3>
        <form method="POST" action="{{ route('admin.approval-chains.store') }}">
            @csrf
            <table><tbody><tr>
                <td><select name="scope"><option value="OUTLET">OUTLET</option><option value="HEAD_OFFICE">HEAD_OFFICE</option></select></td>
                <td><select name="amount_band"><option value="LOW">LOW</option><option value="HIGH">HIGH</option></select></td>
                <td><select name="doc_type"><option value="">— semua —</option>@foreach ($docTypes as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach</select></td>
                <td><input type="number" name="level" min="1" max="5" value="1" style="width:60px"></td>
                <td><select name="approver_role"><option value="">—</option>@foreach ($roles as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach</select></td>
                <td><input type="number" name="approver_user_id" placeholder="opsional" style="width:90px"></td>
                <td><button class="btn" type="submit">Tambah</button></td>
            </tr></tbody></table>
        </form>
    </div>
</div>
@endsection
