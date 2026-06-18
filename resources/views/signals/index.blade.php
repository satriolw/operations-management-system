@extends('layouts.app')

@section('title', 'Tinjauan Sinyal')
@section('heading', 'Tinjauan Sinyal')
@section('subheading', 'Tindak lanjut signal_events · reviewer ≠ subjek · catatan wajib')
@section('styles')
<style>
    .sg-wrap{max-width:1080px;margin:0 auto;padding:24px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}
    .filters{display:flex;flex-wrap:wrap;gap:8px;background:#fff;border:1px solid #E6E9EE;border-radius:12px;padding:12px;margin-bottom:16px;}
    .filters select,.filters input{border:1px solid #D8DCE3;border-radius:7px;padding:6px 8px;font:inherit;font-size:12.5px;}
    .btn{font:inherit;font-size:12.5px;font-weight:650;border-radius:8px;border:1px solid #2C6FE0;background:#2C6FE0;color:#fff;padding:6px 13px;cursor:pointer;}
    .card{background:#fff;border:1px solid #E6E9EE;border-radius:12px;padding:14px 16px;margin-bottom:10px;}
    .row1{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
    .sev{font-size:11px;font-weight:800;border-radius:6px;padding:2px 8px;text-transform:uppercase;letter-spacing:.03em;}
    .sev.critical{background:#FDE7E7;color:#A1281B;} .sev.high{background:#FFF1E0;color:#9A5B00;}
    .sev.low,.sev.medium{background:#EEF1F4;color:#555E6C;}
    .typ{font-weight:800;font-size:13px;}
    .st{font-size:11px;font-weight:700;border-radius:6px;padding:2px 8px;}
    .st.OPEN{background:#EBF2FE;color:#1A4BA6;} .st.REVIEWED{background:#E7FFDB;color:#1B5E20;} .st.DISMISSED{background:#EEF1F4;color:#555E6C;}
    .meta{color:#6B7280;font-size:12px;margin:6px 0 0;}
    .kv{display:flex;flex-wrap:wrap;gap:5px 14px;font-size:12px;color:#374151;margin-top:6px;}
    .kv b{color:#111827;}
    .reviewed{margin-top:8px;font-size:12px;color:#1B5E20;background:#F2FBED;border:1px solid #D6EFCB;border-radius:8px;padding:6px 9px;}
    .rev-form{margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;align-items:flex-start;border-top:1px dashed #E6E9EE;padding-top:10px;}
    .rev-form select,.rev-form input{border:1px solid #D8DCE3;border-radius:7px;padding:6px 8px;font:inherit;font-size:12.5px;}
    .rev-form input[name=note]{flex:1;min-width:220px;}
    .ok{background:#F2FBED;border:1px solid #D6EFCB;color:#1B5E20;border-radius:8px;padding:8px 12px;margin-bottom:14px;font-size:13px;}
    .spacer{flex:1;}
</style>
@endsection

@section('content')
<div class="sg-wrap">
    @if (session('status'))<div class="ok">{{ session('status') }}</div>@endif

    <form class="filters" method="GET">
        <select name="status"><option value="">Semua status</option>
            @foreach (['OPEN','REVIEWED','DISMISSED'] as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>@endforeach
        </select>
        <select name="severity"><option value="">Semua severity</option>
            @foreach (['critical','high','low'] as $s)<option value="{{ $s }}" @selected(($filters['severity'] ?? '') === $s)>{{ $s }}</option>@endforeach
        </select>
        <select name="type"><option value="">Semua jenis</option>
            @foreach ($types as $t)<option value="{{ $t }}" @selected(($filters['type'] ?? '') === $t)>{{ $t }}</option>@endforeach
        </select>
        <input type="number" name="id_outlet" placeholder="id_outlet" value="{{ $filters['id_outlet'] ?? '' }}" style="width:90px">
        <button class="btn" type="submit">Filter</button>
    </form>

    @forelse ($signals as $sig)
        <div class="card">
            <div class="row1">
                <span class="sev {{ $sig->severity }}">{{ $sig->severity }}</span>
                <span class="typ">{{ $sig->type }}</span>
                <span class="st {{ $sig->status }}">{{ $sig->status }}</span>
                <span class="spacer"></span>
                <span class="meta">{{ optional($sig->detected_at)->format('d M Y H:i') }} WIB · {{ $sig->outlet->name ?? ('outlet '.$sig->id_outlet) }}</span>
            </div>
            <div class="kv">
                @if ($sig->ref_transaction_number)<span>Nota: <b>{{ $sig->ref_transaction_number }}</b></span>@endif
                @if ($sig->id_cashier)<span>id_cashier: <b>{{ $sig->id_cashier }}</b></span>@endif
                @foreach (($sig->payload_json ?? []) as $k => $v)
                    @if (is_scalar($v))<span>{{ $k }}: <b>{{ $v }}</b></span>@endif
                @endforeach
            </div>

            @if ($rev = $lastReview->get($sig->id))
                <div class="reviewed">✓ Ditinjau {{ optional($rev->reviewed_at)->format('d M Y H:i') }} oleh {{ $rev->reviewer->name ?? '—' }} → <b>{{ $rev->outcome }}</b>: {{ $rev->note }}</div>
            @endif

            @if ($canReview && $sig->status === 'OPEN')
                <form class="rev-form" method="POST" action="{{ route('signals.review', $sig) }}">
                    @csrf
                    <select name="outcome" required>
                        <option value="wajar">Wajar (tutup)</option>
                        <option value="ditindaklanjuti">Ditindaklanjuti</option>
                        <option value="eskalasi">Eskalasi</option>
                    </select>
                    <input name="note" placeholder="Catatan tinjauan (wajib, min 3 huruf)" required minlength="3" maxlength="1000">
                    <input name="evidence_path" placeholder="path bukti (opsional)" maxlength="255">
                    <button class="btn" type="submit">Catat tinjauan</button>
                </form>
            @endif
        </div>
    @empty
        <div class="card" style="color:#8B93A1">Tak ada sinyal sesuai filter / scope outlet Anda.</div>
    @endforelse

    <div style="margin-top:14px">{{ $signals->links() }}</div>
</div>
@endsection
