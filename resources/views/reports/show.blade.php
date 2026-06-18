@extends('layouts.app')

@section('title', 'Preview Laporan')
@section('heading', 'Preview Laporan')
@section('subheading', $run->report_date.' · '.($run->outlet->name ?? ('outlet '.$run->id_outlet)))
@section('styles')
<style>
    .pv-wrap{max-width:820px;margin:0 auto;padding:24px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}
    .btn{font:inherit;font-size:12.5px;font-weight:650;border-radius:8px;border:1px solid #2C6FE0;background:#2C6FE0;color:#fff;padding:7px 14px;cursor:pointer;}
    .lnk{color:#2C6FE0;text-decoration:none;font-size:12.5px;}
    .card{background:#fff;border:1px solid #E6E9EE;border-radius:12px;padding:18px;margin-bottom:16px;}
    .card h3{margin:0 0 10px;font-size:13px;font-weight:800;color:#374151;}
    pre.msg{white-space:pre-wrap;word-break:break-word;font-family:"IBM Plex Mono",ui-monospace,monospace;font-size:13px;line-height:1.5;background:#FAFBFC;border:1px solid #EEF1F4;border-radius:10px;padding:14px;margin:0;}
    .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;}
    .kpi{background:#FAFBFC;border:1px solid #EEF1F4;border-radius:10px;padding:10px;}
    .kpi b{display:block;font-size:16px;color:#111827;} .kpi span{font-size:11px;color:#6B7280;}
    .dlv{display:flex;align-items:center;gap:10px;border-bottom:1px solid #EEF1F4;padding:10px 0;flex-wrap:wrap;}
    .dlv:last-child{border-bottom:none;}
    .st{font-size:11px;font-weight:700;border-radius:6px;padding:2px 8px;}
    .st.confirmed_sent,.st.sent{background:#E7FFDB;color:#1B5E20;}
    .st.awaiting_confirmation{background:#FFF1E0;color:#9A5B00;}
    .st.failed{background:#FDE7E7;color:#A1281B;}
    .spacer{flex:1;}
    .ok{background:#F2FBED;border:1px solid #D6EFCB;color:#1B5E20;border-radius:8px;padding:8px 12px;margin-bottom:14px;font-size:13px;}
    .hint{font-size:12px;color:#6B7280;margin-top:6px;}
</style>
@endsection

@section('content')
<div class="pv-wrap">
    @if (session('status'))<div class="ok">{{ session('status') }}</div>@endif
    <p><a class="lnk" href="{{ route('reports.index') }}">← Kembali ke daftar</a></p>

    <div class="card">
        <h3>Ringkasan</h3>
        <div class="grid">
            <div class="kpi"><b>Rp{{ number_format((float) $run->total_sales, 0, ',', '.') }}</b><span>Total penjualan</span></div>
            <div class="kpi"><b>Rp{{ number_format((float) $run->realized, 0, ',', '.') }}</b><span>Terealisasi</span></div>
            <div class="kpi"><b>Rp{{ number_format((float) $run->receivable, 0, ',', '.') }}</b><span>Piutang</span></div>
            <div class="kpi"><b>{{ $run->txn_count }}</b><span>Transaksi</span></div>
        </div>
    </div>

    <div class="card">
        <h3>Isi pesan laporan</h3>
        @if (filled($run->payload_text))
            <pre class="msg">{{ $run->payload_text }}</pre>
        @else
            <p class="hint">Belum ada teks laporan tersusun untuk run ini (status: {{ $run->status }}).</p>
        @endif
    </div>

    <div class="card">
        <h3>Pengiriman ke investor</h3>
        @forelse ($run->deliveries as $d)
            <div class="dlv">
                <span class="st {{ $d->status }}">{{ $d->status }}</span>
                <span>{{ strtoupper($d->channel) }} → {{ $d->target ?? '—' }}</span>
                @if ($d->sent_at)<span class="hint">terkirim {{ optional($d->sent_at)->format('d M Y H:i') }} WIB</span>@endif
                <span class="spacer"></span>
                {{-- OPS-302: hanya draft hybrid menunggu + Head Store yang bisa konfirmasi --}}
                @if ($canSend && $d->channel === 'hybrid' && $d->isAwaitingConfirmation())
                    <form method="POST" action="{{ route('deliveries.confirm', $d) }}" onsubmit="return confirm('Tandai laporan ini sudah dikirim ke investor?')">
                        @csrf @method('PUT')
                        <button class="btn" type="submit">Sudah saya kirim</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="hint">Belum ada pengiriman tercatat untuk run ini.</p>
        @endforelse
        @if ($canSend)
            <p class="hint">Mode hybrid: salin isi pesan di atas ke grup WhatsApp investor, lalu tekan "Sudah saya kirim" agar watchdog tahu laporan benar terkirim (OPS-302/704).</p>
        @endif
    </div>
</div>
@endsection
