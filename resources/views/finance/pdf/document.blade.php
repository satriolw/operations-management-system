<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 12px; color: #1A1F26; margin: 0; }
    .wrap { position: relative; padding: 8px 4px; }
    .watermark { position: fixed; top: 40%; left: 0; right: 0; text-align: center;
        font-size: 96px; font-weight: 800; color: rgba(200,40,40,.12); transform: rotate(-24deg); letter-spacing: 8px; }
    h1 { font-size: 18px; margin: 0 0 2px; }
    .meta { color: #555E6C; font-size: 11px; margin-bottom: 12px; }
    .meta b { color: #1A1F26; }
    table { width: 100%; border-collapse: collapse; margin: 8px 0; }
    th, td { border: 1px solid #D8DCE3; padding: 5px 7px; text-align: left; font-size: 11px; }
    th { background: #F2F5F8; }
    .num { text-align: right; }
    .sec { margin-top: 14px; }
    .sec h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #6B7280; border-bottom: 1px solid #E6E9EE; padding-bottom: 3px; }
    .pay { border: 1px solid #D8DCE3; border-radius: 4px; padding: 8px; margin: 6px 0; font-size: 11px; }
    .approvals td { font-size: 11px; }
    .total { font-weight: 700; }
    .fatp { margin-top: 18px; border-top: 2px solid #1A1F26; padding-top: 8px; }
    .sign { display: inline-block; width: 30%; margin: 14px 1% 0; vertical-align: top; font-size: 11px; }
    .sign .line { border-bottom: 1px solid #1A1F26; height: 34px; margin-bottom: 4px; }
</style>
</head>
<body>
@php $rp = fn ($v) => 'Rp' . number_format((float) $v, 0, ',', '.'); @endphp
<div class="wrap">
    @if ($watermark)<div class="watermark">DRAFT</div>@endif

    <h1>{{ $typeLabel }}</h1>
    <div class="meta">
        <b>{{ $doc->doc_number ?? '(belum bernomor)' }}</b> ·
        {{ $doc->brand }} · {{ $doc->scope === 'HEAD_OFFICE' ? 'Head Office' : ('Outlet ' . $doc->id_outlet) }} ·
        {{ optional($doc->created_at)->format('d M Y') }}<br>
        Judul: <b>{{ $doc->title }}</b> · Cost Center: {{ $doc->cost_center ?? '—' }} · Status: <b>{{ $doc->status }}</b>
    </div>

    @if ($doc->doc_type === 'REFUND')
        {{-- Berita Acara Refund: DATA TRANSAKSI + rekening customer (PII) --}}
        <div class="sec"><h2>Data Transaksi</h2>
            <div class="pay">
                Nama Customer: {{ $payload['customer_name'] ?? '—' }}<br>
                No. Telp: {{ $payload['customer_phone'] ?? '—' }}<br>
                No. Nota NEVIRA: <b>{{ $doc->nevira_transaction_number ?? '—' }}</b> (referensi)<br>
                Tanggal Transaksi: {{ $payload['transaction_date'] ?? '—' }}<br>
                Nominal Refund: {{ $rp($doc->amount) }}<br>
                Alasan: {{ $payload['reason'] ?? '—' }}
            </div>
        </div>
        <div class="sec"><h2>Informasi Pembayaran (Customer)</h2>
            <div class="pay">
                Rekening: {{ $payload['customer_account'] ?? '—' }} · a.n. {{ $payload['customer_name'] ?? '—' }}
            </div>
        </div>
    @else
        @if (! empty($payload['business_purposes']))
            <div class="sec"><h2>Business Purposes</h2><div class="pay">{{ $payload['business_purposes'] }}</div></div>
        @endif

        @if ($doc->doc_type === 'EXPENSE_REPORT' && $doc->parent)
            <div class="meta">Realisasi Cash Advance: <b>{{ $doc->parent->doc_number }}</b> · Jumlah CA: {{ $rp($doc->parent->amount) }}</div>
        @endif

        @if ($doc->lines->isNotEmpty())
        <table>
            <thead><tr>
                <th>No</th><th>Deskripsi</th><th>Merk/Type</th><th class="num">Qty</th>
                <th class="num">Harga</th><th class="num">Jumlah</th>
                @if ($doc->doc_type === 'EXPENSE_REPORT')<th class="num">Balance</th>@endif
            </tr></thead>
            <tbody>
            @foreach ($doc->lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td><td>{{ $line->description }}</td><td>{{ $line->merk_type ?? '—' }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($line->qty, 2), '0'), '.') }}</td>
                    <td class="num">{{ $rp($line->unit_price) }}</td>
                    <td class="num">{{ $rp($line->amount) }}</td>
                    @if ($doc->doc_type === 'EXPENSE_REPORT')<td class="num">{{ $rp($line->balance) }}</td>@endif
                </tr>
            @endforeach
                <tr class="total"><td colspan="5" class="num">TOTAL REALISASI</td><td class="num">{{ $rp($doc->amount) }}</td>
                    @if ($doc->doc_type === 'EXPENSE_REPORT')<td class="num">{{ isset($payload['sisa']) ? $rp($payload['sisa']) : '—' }}</td>@endif</tr>
            </tbody>
        </table>
        @if ($doc->doc_type === 'EXPENSE_REPORT' && isset($payload['sisa']))
            <div class="meta"><b>Sisa: {{ $rp($payload['sisa']) }} — {{ $payload['sisa_label'] }}</b>
                ({{ ($payload['sisa'] ?? 0) < 0 ? 'reimburse ke karyawan' : 'kembali ke perusahaan' }})</div>
        @endif
        @endif

        <div class="sec"><h2>Informasi Pembayaran</h2>
            <div class="pay">{{ $payload['payment_info'] ?? 'Rekening payee — diisi pengaju.' }}</div>
            @if ($doc->doc_type === 'EXPENSE_REPORT')
                <div class="pay">Rekening perusahaan (CA Lebih/Kurang) — {{ $payload['company_payment_info'] ?? '—' }}</div>
                @if (! empty($payload['report_url']))<div class="meta">Link Detail Report: {{ $payload['report_url'] }}</div>@endif
            @endif
        </div>
    @endif

    <div class="sec approvals"><h2>Approval</h2>
        <table>
            <thead><tr><th>Level</th><th>Approver</th><th>Status</th><th>Waktu</th><th>Catatan</th></tr></thead>
            <tbody>
            @forelse ($doc->approvals->sortBy('level') as $a)
                <tr>
                    <td>L{{ $a->level }}</td>
                    <td>{{ optional($a->approver)->name ?? ('#' . $a->approver_user_id) }} <small>({{ $a->approver_role }})</small></td>
                    <td>{{ $a->action }}</td>
                    <td>{{ optional($a->acted_at)->format('d M Y H:i') }}</td>
                    <td>{{ $a->note ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada approval.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- FAT-P Division: blok tanda tangan STATIS, pemrosesan Finance pasca-FINAL (bukan level approval) --}}
    <div class="fatp">
        <h2 style="border:0;margin:0 0 4px;">FAT-P Division (Finance · pasca-FINAL)</h2>
        <div class="sign"><div class="line"></div>Finance / Admin</div>
        <div class="sign"><div class="line"></div>Tax</div>
        <div class="sign"><div class="line"></div>Payment (Diproses/Dibayar)</div>
    </div>
</div>
</body>
</html>
