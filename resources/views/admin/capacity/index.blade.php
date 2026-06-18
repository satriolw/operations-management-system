@extends('layouts.app')

@section('title', 'Kapasitas Outlet')
@section('heading', 'Kapasitas Outlet')
@section('styles')<link href="{{ asset('css/oms-admin.css') }}" rel="stylesheet">
<style>
    .cap-wrap{max-width:1040px;margin:0 auto;padding:28px 24px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}
    .cap-h{font-size:20px;font-weight:800;margin:0 0 4px;}
    .cap-sub{color:#6B7280;font-size:13px;margin:0 0 20px;}
    .cap-card{background:#fff;border:1px solid #E6E9EE;border-radius:12px;padding:18px;margin-bottom:16px;}
    .cap-card h3{font-size:15px;font-weight:700;margin:0 0 12px;}
    .cap-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;}
    .cap-field label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#8B93A1;margin-bottom:4px;}
    .cap-field input{width:100%;box-sizing:border-box;border:1px solid #D8DCE3;border-radius:8px;padding:8px 10px;font:inherit;font-size:13px;}
    .cap-foot{display:flex;align-items:center;gap:14px;margin-top:14px;}
    .cap-eff{font-size:12.5px;color:#1A4BA6;background:#EBF2FE;border-radius:8px;padding:6px 10px;}
    .cap-eff.warn{color:#A66400;background:#FBEED7;}
    .btn{font:inherit;font-size:13px;font-weight:650;border-radius:8px;border:1px solid #2C6FE0;background:#2C6FE0;color:#fff;padding:8px 16px;cursor:pointer;margin-left:auto;}
    .flash{background:#E7FFDB;border:1px solid #BfeaA8;color:#1B5E20;border-radius:8px;padding:10px 12px;margin-bottom:16px;font-size:13px;}
    .errbox{background:#FDECEA;border:1px solid #F5C6C0;color:#A1281B;border-radius:8px;padding:10px 12px;margin-bottom:16px;font-size:13px;}
    .hint{font-size:11.5px;color:#8B93A1;margin:8px 0 0;}
</style>@endsection

@section('content')
<div class="cap-wrap">
    <h1 class="cap-h">Kapasitas Outlet</h1>
    <p class="cap-sub">Effective capacity (kg/jam) diturunkan dari input: <b>override</b> → <b>mesin × throughput</b> → <b>kg/hari ÷ jam shift</b>. Ambang overload per outlet (OPS-1103).</p>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="errbox">{{ $errors->first() }}</div>@endif

    @foreach ($outlets as $outlet)
        @php $c = $outlet->capacity; $eff = $c?->effectiveKgPerHour(); @endphp
        <form class="cap-card" method="POST" action="{{ route('admin.capacity.update', $outlet) }}">
            @csrf @method('PUT')
            <h3>{{ $outlet->name }} <span style="color:#8B93A1;font-weight:500">· id {{ $outlet->id_outlet }}</span></h3>
            <div class="cap-grid">
                <div class="cap-field"><label>Mesin</label><input type="number" name="machines" min="0" value="{{ $c->machines ?? '' }}"></div>
                <div class="cap-field"><label>Throughput kg/jam/mesin</label><input type="number" step="0.01" name="throughput_kg_per_machine_hour" min="0" value="{{ $c->throughput_kg_per_machine_hour ?? '' }}"></div>
                <div class="cap-field"><label>Jam shift/hari</label><input type="number" step="0.1" name="shift_hours" min="0" max="24" value="{{ $c->shift_hours ?? '' }}"></div>
                <div class="cap-field"><label>kg/hari</label><input type="number" step="0.01" name="kg_per_day" min="0" value="{{ $c->kg_per_day ?? '' }}"></div>
                <div class="cap-field"><label>Override kg/jam</label><input type="number" step="0.01" name="capacity_kg_per_hour" min="0" value="{{ $c->capacity_kg_per_hour ?? '' }}"></div>
                <div class="cap-field"><label>Ambang overload %</label><input type="number" name="overload_threshold_pct" min="1" max="100" value="{{ $c->overload_threshold_pct ?? 80 }}"></div>
            </div>
            <div class="cap-foot">
                @if ($eff !== null)
                    <span class="cap-eff">Effective capacity: <b>{{ rtrim(rtrim(number_format($eff, 2), '0'), '.') }} kg/jam</b></span>
                @else
                    <span class="cap-eff warn">Kapasitas belum dikonfigurasi</span>
                @endif
                <button type="submit" class="btn">Simpan</button>
            </div>
            <p class="hint">Override kg/jam mengalahkan input lain. Kosongkan untuk pakai turunan otomatis.</p>
        </form>
    @endforeach
</div>
@endsection
