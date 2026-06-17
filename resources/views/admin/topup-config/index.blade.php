@extends('layouts.admin')

@section('title', 'Kalender Pencairan Saldo NEVIRA')
@section('styles')<link href="{{ asset('css/oms-admin.css') }}" rel="stylesheet">
<style>
    .tc-wrap{max-width:760px;margin:0 auto;padding:28px 24px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}
    .tc-h{font-size:20px;font-weight:800;margin:0 0 4px;}
    .tc-sub{color:#6B7280;font-size:13px;margin:0 0 20px;}
    .tc-card{background:#fff;border:1px solid #E6E9EE;border-radius:12px;padding:20px;}
    .tc-sec{font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#8B93A1;margin:18px 0 10px;}
    .tc-sec:first-child{margin-top:0;}
    .tc-days{display:flex;flex-wrap:wrap;gap:8px;}
    .tc-day{display:flex;align-items:center;gap:6px;border:1px solid #D8DCE3;border-radius:8px;padding:7px 11px;font-size:13px;cursor:pointer;}
    .tc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;}
    .tc-field label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#8B93A1;margin-bottom:4px;}
    .tc-field input{width:100%;box-sizing:border-box;border:1px solid #D8DCE3;border-radius:8px;padding:8px 10px;font:inherit;font-size:13px;}
    .btn{font:inherit;font-size:13px;font-weight:650;border-radius:8px;border:1px solid #2C6FE0;background:#2C6FE0;color:#fff;padding:9px 18px;cursor:pointer;margin-top:18px;}
    .flash{background:#E7FFDB;border:1px solid #BfeaA8;color:#1B5E20;border-radius:8px;padding:10px 12px;margin-bottom:16px;font-size:13px;}
    .errbox{background:#FDECEA;border:1px solid #F5C6C0;color:#A1281B;border-radius:8px;padding:10px 12px;margin-bottom:16px;font-size:13px;}
    .hint{font-size:11.5px;color:#8B93A1;margin:6px 0 0;}
</style>@endsection

@section('body')
@php $days = $config->weekdays(); @endphp
<div class="tc-wrap">
    <h1 class="tc-h">Kalender Pencairan Saldo NEVIRA</h1>
    <p class="tc-sub">Saldo deposit tingkat-merchant (single point of failure). Bottleneck = jadwal pencairan Finance, bukan NEVIRA. Ambang dinyatakan dalam <b>hari-runway</b> (OPS-1204/1205).</p>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="errbox">{{ $errors->first() }}</div>@endif

    <form class="tc-card" method="POST" action="{{ route('admin.topup-config.update') }}">
        @csrf @method('PUT')

        <p class="tc-sec">Hari pencairan</p>
        <div class="tc-days">
            @foreach ($weekdayLabels as $wd => $label)
                <label class="tc-day">
                    <input type="checkbox" name="disbursement_weekdays[]" value="{{ $wd }}" @checked(in_array($wd, $days, true))>
                    {{ $label }}
                </label>
            @endforeach
        </div>
        <p class="hint">Default Senin & Kamis. Gap terburuk Kamis→Senin (akhir pekan) → window Kamis lebih konservatif.</p>

        <p class="tc-sec">Parameter</p>
        <div class="tc-grid">
            <div class="tc-field"><label>Lead pengajuan (jam)</label><input type="number" name="submission_cutoff_lead_hours" min="0" max="168" value="{{ $config->submission_cutoff_lead_hours }}"></div>
            <div class="tc-field"><label>Target ceiling (Rp)</label><input type="number" name="target_ceiling" min="0" value="{{ $config->target_ceiling }}"></div>
            <div class="tc-field"><label>Buffer (hari)</label><input type="number" name="buffer_days" min="0" max="60" value="{{ $config->buffer_days }}"></div>
            <div class="tc-field"><label>Ambang warning (hari-runway)</label><input type="number" name="warning_runway_days" min="0" max="90" value="{{ $config->warning_runway_days }}"></div>
            <div class="tc-field"><label>Ambang kritis (hari-runway)</label><input type="number" name="critical_runway_days" min="0" max="90" value="{{ $config->critical_runway_days }}"></div>
        </div>
        <p class="hint">Warning ≈ gap + buffer; kritis ≈ gap maksimum. Warning harus ≥ kritis.</p>

        <button type="submit" class="btn">Simpan konfigurasi</button>
    </form>
</div>
@endsection
