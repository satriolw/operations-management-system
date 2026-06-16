@extends('layouts.admin')

@section('title', 'Akun WhatsApp & Target')
@section('styles')<link href="{{ asset('css/oms-wa.css') }}" rel="stylesheet">@endsection

@php
    use Illuminate\Support\Str;
    $obaMeta = [
        'active' => ['cls' => 'badge--good', 'txt' => 'Aktif'],
        'process' => ['cls' => 'badge--warn', 'txt' => 'Proses'],
        'none' => ['cls' => 'badge--neutral', 'txt' => 'Belum'],
    ];
    $acctMeta = [
        'active' => ['cls' => 'st-active', 'txt' => 'Active'],
        'lost' => ['cls' => 'st-lost', 'txt' => 'Lost'],
        'recovering' => ['cls' => 'st-recovering', 'txt' => 'Recovering'],
    ];
    $initials = fn ($s) => Str::upper(Str::substr(collect(explode(' ', trim($s)))->map(fn ($w) => $w[0] ?? '')->take(2)->implode(''), 0, 2));
@endphp

@section('body')
<div class="app">
    <aside class="rail">
        <div class="rail__brand">
            <div class="rail__logo"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.5C12 2.5 5 10 5 15a7 7 0 0 0 14 0c0-5-7-12.5-7-12.5z"/></svg></div>
            <div><b>Less Worry</b><span>OMS · Apique Group</span></div>
        </div>
        <div class="rail__sec">Konfigurasi</div>
        <nav class="nav">
            <a href="#" class="on"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 018.413 3.488 11.82 11.82 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24z"/></svg>Akun WhatsApp</a>
        </nav>
        <div class="rail__user">
            <div class="av">{{ $initials(auth()->user()->name ?? 'U') }}</div>
            <div><b>{{ auth()->user()->name ?? 'User' }}</b><span>{{ auth()->user()?->getRoleNames()->first() ?? 'staf' }}</span></div>
        </div>
    </aside>

    <div class="main">
        <div class="topbar">
            <div>
                <div class="crumb"><span>Konfigurasi</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg><b>Akun WhatsApp &amp; Target</b></div>
                <h1>Akun WhatsApp &amp; Target Pengiriman</h1>
            </div>
        </div>

        <div class="scroll">
            <div class="inner">

                @if (session('status'))
                    <div class="banner on" style="background:linear-gradient(160deg,#E3F1EA,#F4FBF7);border-color:var(--good-bd)">
                        <div class="banner__ico" style="background:var(--good)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></div>
                        <div class="banner__txt"><b style="color:var(--good)">{{ session('status') }}</b></div>
                    </div>
                @endif

                @error('deliver_mode')
                    <div class="banner on">
                        <div class="banner__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg></div>
                        <div class="banner__txt"><b>Mode tidak dapat diubah</b><p>{{ $message }}</p></div>
                    </div>
                @enderror

                {{-- LOST BANNER (data nyata) --}}
                @if ($hasLost)
                    @php $lost = $accounts->firstWhere(fn ($a) => $a->isLost()); @endphp
                    <div class="banner on" id="lostBanner">
                        <div class="banner__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg></div>
                        <div class="banner__txt">
                            <b>Nomor "{{ $lost->label }}" terputus (lost)</b>
                            <p>Sesi WhatsApp untuk <b>{{ $lost->maskedPhone() }}</b> ter-logout. Target yang memakai nomor ini <b>otomatis diturunkan ke mode Hybrid</b> sampai nomor dipulihkan atau diganti.</p>
                        </div>
                        <div class="banner__act">
                            <button type="button" class="btn btn--secondary btn--sm">Pulihkan</button>
                            <button type="button" class="btn btn--warn btn--sm">Ganti nomor</button>
                        </div>
                    </div>
                @endif

                {{-- (1) AKUN WHATSAPP --}}
                <div class="card">
                    <div class="card__h">
                        <div class="ci" style="background:var(--wa-bg);color:var(--wa)"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 018.413 3.488 11.82 11.82 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24z"/></svg></div>
                        <div class="t"><b>Akun WhatsApp</b><span>Nomor pengirim &amp; status Official Business API (OBA)</span></div>
                        <div class="right"><span class="countpill">{{ $accounts->count() }} akun</span></div>
                    </div>
                    <table class="tbl">
                        <thead><tr><th>Label</th><th>Nomor</th><th>Kredensial</th><th>Status OBA</th><th>Status akun</th><th style="text-align:right">Aksi</th></tr></thead>
                        <tbody>
                        @forelse ($accounts as $a)
                            @php $am = $acctMeta[$a->account_status] ?? $acctMeta['active']; $om = $obaMeta[$a->oba_status] ?? $obaMeta['none']; @endphp
                            <tr class="{{ $a->isLost() ? 'rowlost' : '' }}">
                                <td><div class="acct"><div class="av" style="background:var(--lw)">{{ $initials($a->label) }}</div><div><b>{{ $a->label }}</b><span>{{ $a->outlet?->name ?? 'Tanpa outlet' }}</span></div></div></td>
                                <td><div class="num"><span class="lockico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span>{{ $a->maskedPhone() }}</div></td>
                                <td><span class="cred"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg> Tertutup</span></td>
                                <td><span class="badge {{ $om['cls'] }}"><span class="dot"></span>{{ $om['txt'] }}</span></td>
                                <td><span class="statustxt {{ $am['cls'] }}"><span class="d"></span>{{ $am['txt'] }}</span></td>
                                <td><div class="rowact">
                                    @if ($a->isLost())
                                        <button type="button" class="btn btn--warn btn--sm">Pulihkan / Ganti</button>
                                    @else
                                        <button type="button" class="iconbtn" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                    @endif
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="text-align:center;color:var(--ink-3);padding:28px">Belum ada akun WhatsApp.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    <div class="legend">
                        <span class="li"><span class="dot" style="background:var(--good)"></span>OBA Aktif — assisted &amp; full auto tersedia</span>
                        <span class="li"><span class="dot" style="background:var(--warn)"></span>OBA Proses — verifikasi berjalan</span>
                        <span class="li"><span class="dot" style="background:var(--ink-3)"></span>OBA Belum — hanya mode Hybrid</span>
                    </div>
                </div>

                {{-- (2) TARGET PENGIRIMAN --}}
                <div class="card">
                    <div class="card__h">
                        <div class="ci" style="background:var(--pri-50);color:var(--pri)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg></div>
                        <div class="t"><b>Target Pengiriman</b><span>Outlet → investor, channel, mode &amp; template</span></div>
                        <div class="right"><span class="countpill">{{ $targets->count() }} target</span></div>
                    </div>
                    <table class="tbl">
                        <thead><tr><th>Outlet → Investor</th><th>Channel</th><th>Mode pengiriman</th><th>Kesiapan grup</th><th>Template</th><th style="text-align:right">Aksi</th></tr></thead>
                        <tbody>
                        @forelse ($targets as $t)
                            @php
                                $acct = $t->whatsappAccount;
                                $ready = $acct?->obaReady() ?? false;
                                $eff = $t->effectiveMode();
                                $fallback = $t->isFallback();
                                $leadCol = $eff === 'hybrid' ? 'var(--ink-3)' : ($eff === 'assisted' ? 'var(--info)' : 'var(--pri)');
                            @endphp
                            <tr class="{{ $fallback ? 'rowlost' : '' }}">
                                <td><div class="flowcell">
                                    <div class="o"><div class="av" style="background:var(--lw)">{{ $initials($t->outlet?->name ?? 'O') }}</div><div><b>{{ $t->outlet?->name ?? '—' }}</b><br><small>{{ $acct?->label ?? 'Tanpa akun' }}</small></div></div>
                                    <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                                    <div class="o"><div class="av" style="background:#3C4A5C">{{ $initials($t->investor_label) }}</div><div><b>{{ $t->investor_label }}</b><br><small>Investor</small></div></div>
                                </div></td>
                                <td><span class="chan"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 018.413 3.488 11.82 11.82 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24z"/></svg> WhatsApp</span></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.delivery.mode', $t) }}" id="mode-form-{{ $t->id }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="deliver_mode" id="mode-val-{{ $t->id }}" value="{{ $eff }}">
                                    </form>
                                    <div class="modesel">
                                        <button type="button" class="modebtn {{ $fallback ? 'fallback' : '' }}" onclick="toggleMenu({{ $t->id }})">
                                            <span class="lead" style="background:{{ $leadCol }}"></span>
                                            <span class="cap">{{ $modes[$eff]['name'] }}</span>
                                            <span class="chev"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg></span>
                                        </button>
                                        @if ($fallback)<div class="fallnote"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg> Fallback otomatis · nomor lost</div>@endif
                                        <div class="modemenu" id="menu-{{ $t->id }}">
                                            @foreach ($modes as $key => $mo)
                                                @php $locked = $mo['oba'] && ! $ready; $sel = $key === $eff; @endphp
                                                @if ($locked)
                                                    <div class="tip" style="display:block">
                                                        <div class="modeopt locked">
                                                            <span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg></span>
                                                            <div class="mo-t"><b>{{ $mo['name'] }} <span class="lockchip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg> OBA</span></b><span>{{ $mo['desc'] }}</span></div>
                                                        </div>
                                                        <span class="tipbox">Butuh <b>OBA aktif</b> pada nomor pengirim. Status OBA: <b>{{ $obaMeta[$acct?->oba_status ?? 'none']['txt'] }}</b>.</span>
                                                    </div>
                                                @else
                                                    <div class="modeopt {{ $sel ? 'sel' : '' }}" onclick="pickMode({{ $t->id }},'{{ $key }}')">
                                                        <span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg></span>
                                                        <div class="mo-t"><b>{{ $mo['name'] }}</b><span>{{ $mo['desc'] }}</span></div>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($t->group_ready)
                                        <span class="ready yes"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M8 12l2.5 2.5L16 9"/></svg> Siap</span>
                                    @else
                                        <span class="ready no"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg> Belum</span>
                                    @endif
                                </td>
                                <td><span class="tmpl"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg> {{ $t->template_label ?? '—' }}</span></td>
                                <td><div class="rowact"><button type="button" class="iconbtn" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="text-align:center;color:var(--ink-3);padding:28px">Belum ada target pengiriman.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="toast-wrap" id="toastWrap"></div>
@endsection

@section('scripts')
<script>
    let openMenu = null;
    function toggleMenu(id){
        const m = document.getElementById('menu-'+id);
        const willOpen = !m.classList.contains('on');
        document.querySelectorAll('.modemenu.on').forEach(x=>x.classList.remove('on'));
        if(willOpen){ m.classList.add('on'); openMenu = id; } else openMenu = null;
    }
    document.addEventListener('click', e=>{
        if(!e.target.closest('.modesel') && openMenu!==null){
            document.querySelectorAll('.modemenu.on').forEach(x=>x.classList.remove('on')); openMenu = null;
        }
    });
    function pickMode(id, mode){
        document.getElementById('mode-val-'+id).value = mode;
        document.getElementById('mode-form-'+id).submit();
    }
</script>
@endsection
