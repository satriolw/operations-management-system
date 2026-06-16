<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    :root { --pri:#2C6FE0; --pri-700:#1A4BA6; --ink:#161A20; --ink-2:#555E6C; --ink-3:#8B93A1;
            --good:#157F57; --warm:#FBF7F0; --line:#E6E9EE; --surface-3:#F1F3F6; }
    * { box-sizing:border-box; margin:0; padding:0; }
    body { width:375px; font-family:"Plus Jakarta Sans",system-ui,sans-serif; background:#fff; color:var(--ink); }
    .card { width:375px; padding:20px 18px; background:var(--warm); }
    .head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; }
    .head b { font-size:17px; font-weight:800; letter-spacing:-.01em; }
    .head .sub { font-size:11px; color:var(--ink-3); font-weight:600; }
    .head .date { font-size:10.5px; color:var(--ink-3); font-weight:600; text-align:right; }
    .hero { background:linear-gradient(150deg,var(--pri),var(--pri-700)); border-radius:14px; padding:16px 16px 14px; color:#fff; margin-bottom:12px; }
    .hero .lab { font-size:11px; font-weight:600; opacity:.85; }
    .hero .val { font-size:27px; font-weight:800; letter-spacing:-.02em; font-variant-numeric:tabular-nums; margin-top:3px; }
    .pp { display:flex; gap:8px; margin-bottom:12px; }
    .pp .box { flex:1; background:#fff; border:1px solid var(--line); border-radius:10px; padding:9px 11px; }
    .pp .box span { font-size:10px; color:var(--ink-3); font-weight:600; display:block; }
    .pp .box b { font-size:13.5px; font-weight:700; font-variant-numeric:tabular-nums; }
    .pp .good b { color:var(--good); }
    .grid { display:flex; gap:8px; margin-bottom:12px; }
    .grid .cell { flex:1; background:#fff; border:1px solid var(--line); border-radius:10px; padding:9px 10px; text-align:center; }
    .grid .cell span { font-size:10px; color:var(--ink-3); font-weight:600; display:block; }
    .grid .cell b { font-size:14px; font-weight:750; font-variant-numeric:tabular-nums; }
    .vol { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:12px; }
    .chip { background:var(--surface-3); border-radius:8px; padding:6px 11px; font-size:12px; font-weight:700; }
    .chip span { color:var(--ink-3); font-weight:600; margin-left:3px; }
    .foot { font-size:9.5px; color:var(--ink-3); font-weight:500; text-align:center; border-top:1px solid var(--line); padding-top:9px; }
</style>
</head>
<body>
<div class="card">
    <div class="head">
        <div><b>{{ $outletName ?: 'Outlet' }}</b><div class="sub">Less Worry · laporan harian</div></div>
        <div class="date">{{ $tanggal }}@if($investor)<br>{{ $investor }}@endif</div>
    </div>

    <div class="hero">
        <div class="lab">Total penjualan</div>
        <div class="val">{{ $totalRp }}</div>
    </div>

    <div class="pp">
        <div class="box good"><span>Terealisasi</span><b>{{ $realizedRp }}</b></div>
        @if($hasPiutang)<div class="box"><span>Piutang</span><b>{{ $piutangRp }}</b></div>@endif
    </div>

    <div class="grid">
        <div class="cell"><span>Transaksi</span><b>{{ $txnCount }}</b></div>
        <div class="cell"><span>Rata2/trx</span><b>{{ $avgTrxRp }}</b></div>
        <div class="cell"><span>Rata2/plg</span><b>{{ $avgCustRp }}</b></div>
    </div>

    @if(count($volumes))
        <div class="vol">
            @foreach($volumes as $label => $val)
                <div class="chip">{{ $val }}<span>{{ $label }}</span></div>
            @endforeach
        </div>
    @endif

    <div class="foot">Data terverifikasi POS NEVIRA · Apique Group</div>
</div>
</body>
</html>
