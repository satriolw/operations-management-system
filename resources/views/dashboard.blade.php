@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subheading', 'Ringkasan operasional hari ini')

@section('content')
    <div class="kpis">
        <div class="kpi {{ $high > 0 ? 'alert' : '' }}">
            <div class="lbl">Sinyal terbuka</div>
            <div class="val">{{ $high + $low }} <small>· {{ $high }} tinggi</small></div>
        </div>
        <div class="kpi">
            <div class="lbl">Laporan hari ini</div>
            <div class="val">{{ $reportsDelivered }}<small>/{{ $reportsTotal }} terkirim</small></div>
        </div>
        <div class="kpi">
            <div class="lbl">Nota terlambat</div>
            <div class="val">{{ $lateOrders }}</div>
        </div>
        <div class="kpi">
            <div class="lbl">Outlet</div>
            <div class="val">{{ $outletsVisible }}</div>
        </div>
    </div>

    <div class="section-h">Perlu tindakan</div>
    @if ($clean)
        <div class="empty"><div class="big">✨ Operasional bersih</div>Tidak ada sinyal terbuka atau laporan tertunda hari ini.</div>
    @else
        <div class="actions">
            @if ($reportsPending > 0)
                <div class="action info"><span class="n">{{ $reportsPending }}</span> laporan menunggu terkirim
                    <a href="{{ route('finance.documents.index') }}">Lihat</a></div>
            @endif
            @if ($high + $low > 0)
                <div class="action {{ $high > 0 ? 'bad' : 'warn' }}"><span class="n">{{ $high + $low }}</span> sinyal perlu ditinjau
                    <span style="color:var(--ink-3);font-size:12px;margin-left:6px">{{ $high }} tinggi · {{ $low }} rendah</span></div>
            @endif
            @if ($lateOrders > 0)
                <div class="action warn"><span class="n">{{ $lateOrders }}</span> nota terlambat</div>
            @endif
        </div>
    @endif
@endsection
