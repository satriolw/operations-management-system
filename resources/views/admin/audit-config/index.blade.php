@extends('layouts.app')

@section('title', 'Audit Transaksi')
@section('heading', 'Ambang Audit Transaksi')
@section('subheading', 'Anomali promo / pembayaran / off-price / deposit')

@section('content')
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="flash" style="background:#FBE9E6;border-color:#F2C9C2;color:#C0392B">{{ $errors->first() }}</div>@endif
    @if ($reviewMode)
        <div class="flash" style="background:#FBEED7;border-color:#F0D9A8;color:#A66400">⚠️ Mode <b>"perlu ditinjau"</b> (verification-gated): sinyal audit = flag, BUKAN tuduhan, tanpa auto-aksi — sampai semantik field NEVIRA dikonfirmasi.</div>
    @endif

    @foreach ($outlets as $o)
        @php $c = $config($o->id_outlet); @endphp
        <form method="POST" action="{{ route('admin.audit-config.update', $o) }}" class="kpi" style="margin-bottom:12px;max-width:820px">
            @csrf @method('PUT')
            <b>{{ $o->name }} <span style="color:var(--ink-3);font-weight:500">· id {{ $o->id_outlet }}</span></b>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-top:10px">
                @php $fields = [
                    'promo_leak_pct' => 'Promo % omzet', 'promo_leak_daily_cap' => 'Promo cap/hari (Rp)',
                    'payment_anomaly_min_amount' => 'Payment min (Rp)', 'offprice_tolerance_pct' => 'Off-price toleransi %',
                    'qty_variance_pct' => 'Qty variance %', 'deposit_expiry_lead_days' => 'Deposit lead (hari)',
                ]; @endphp
                @foreach ($fields as $f => $label)
                    <label style="font-size:12px">{{ $label }}<input type="number" step="0.01" name="{{ $f }}" value="{{ $c->$f }}" style="width:100%;padding:7px;border:1px solid var(--border-2);border-radius:8px"></label>
                @endforeach
            </div>
            <button type="submit" class="btn btn--primary" style="margin-top:10px;padding:7px 14px">Simpan</button>
        </form>
    @endforeach
@endsection
