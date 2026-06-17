@extends('layouts.admin')

@section('title', 'Dokumen '.($doc->doc_number ?? $doc->id))
@section('styles')<link href="{{ asset('css/oms-admin.css') }}" rel="stylesheet">
<style>
    .ds-wrap{max-width:840px;margin:0 auto;padding:28px 24px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}
    .ds-h{font-size:19px;font-weight:800;margin:0 0 2px;}
    .ds-meta{color:#555E6C;font-size:12.5px;margin-bottom:16px;}
    .card{background:#fff;border:1px solid #E6E9EE;border-radius:12px;padding:16px;margin-bottom:14px;}
    .card h2{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#8B93A1;margin:0 0 10px;}
    table{width:100%;border-collapse:collapse;font-size:12.5px;}
    th,td{text-align:left;padding:7px 9px;border-bottom:1px solid #EEF1F4;}
    .st{font-size:11px;font-weight:700;border-radius:6px;padding:2px 8px;background:#EBF2FE;color:#1A4BA6;}
    .trail li{margin-bottom:6px;font-size:12.5px;}
    a{color:#2C6FE0;text-decoration:none;}
</style>@endsection

@section('body')
<div class="ds-wrap">
    <a href="{{ route('finance.documents.index') }}">← Daftar</a>
    <h1 class="ds-h" style="margin-top:8px">{{ $doc->doc_number ?? '(belum bernomor)' }}</h1>
    <div class="ds-meta">
        {{ $doc->doc_type }} · {{ $doc->brand }} · {{ $doc->scope === 'HEAD_OFFICE' ? 'Head Office' : ('Outlet '.$doc->id_outlet) }}
        · <span class="st">{{ $doc->status }}</span><br>
        Judul: <b>{{ $doc->title }}</b> · Nominal: Rp{{ number_format((float) $doc->amount, 0, ',', '.') }} ({{ $doc->amount_band }})
        · Pengaju: {{ optional($doc->requester)->name ?? '—' }}
        @if ($doc->parent) · Realisasi CA: {{ $doc->parent->doc_number }} @endif
        @if ($doc->nevira_transaction_number) · Nota NEVIRA: {{ $doc->nevira_transaction_number }} @endif
    </div>

    @if ($doc->lines->isNotEmpty())
    <div class="card"><h2>Item</h2>
        <table><thead><tr><th>Deskripsi</th><th>Qty</th><th>Harga</th><th>Jumlah</th>@if ($doc->doc_type === 'EXPENSE_REPORT')<th>Balance</th>@endif</tr></thead>
        <tbody>
        @foreach ($doc->lines as $l)
            <tr><td>{{ $l->description }}</td><td>{{ rtrim(rtrim(number_format($l->qty,2),'0'),'.') }}</td>
                <td>Rp{{ number_format((float)$l->unit_price,0,',','.') }}</td><td>Rp{{ number_format((float)$l->amount,0,',','.') }}</td>
                @if ($doc->doc_type === 'EXPENSE_REPORT')<td>Rp{{ number_format((float)$l->balance,0,',','.') }}</td>@endif</tr>
        @endforeach
        </tbody></table>
    </div>
    @endif

    <div class="card"><h2>Jejak Approval (status tracking)</h2>
        <ul class="trail">
        @forelse ($doc->approvals->sortBy('level') as $a)
            <li>L{{ $a->level }} — <b>{{ optional($a->approver)->name ?? ('#'.$a->approver_user_id) }}</b>
                ({{ $a->approver_role }}) · {{ $a->action }} · {{ optional($a->acted_at)->format('d M Y H:i') }}
                @if ($a->note) · “{{ $a->note }}”@endif</li>
        @empty
            <li style="color:#8B93A1">Belum ada aksi approval. Level berjalan: {{ $doc->current_level }}.</li>
        @endforelse
        </ul>
    </div>
</div>
@endsection
