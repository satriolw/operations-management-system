@extends('layouts.admin')

@section('title', 'Edit Outlet — '.$outlet->name)

@php
    use Illuminate\Support\Str;

    // Seed jam cek: dari old() (gagal validasi) atau model.
    $seedTimes = collect(old('checkpoints', $outlet->checkpoints->map(fn ($c) => ['time' => Str::substr((string) $c->check_time, 0, 5)])->all()))
        ->pluck('time')->filter()->values();

    // Seed jam operasional per weekday (urut Senin..Minggu sesuai desain).
    $oldHours = collect(old('operating_hours'));
    $seedDays = collect($weekdayOrder)->map(function ($label, $wd) use ($oldHours, $hoursByDay) {
        $fromOld = $oldHours->firstWhere('weekday', (string) $wd) ?? $oldHours->firstWhere('weekday', $wd);
        if ($fromOld) {
            $closed = filter_var($fromOld['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            return ['weekday' => $wd, 'label' => $label, 'closed' => $closed, 'open' => $fromOld['open'] ?? '', 'close' => $fromOld['close'] ?? ''];
        }
        $row = $hoursByDay[$wd] ?? null;
        return [
            'weekday' => $wd, 'label' => $label,
            'closed' => $row ? (bool) $row->is_closed : ($wd === 0), // Minggu default libur
            'open' => $row && $row->open_time ? Str::substr((string) $row->open_time, 0, 5) : '09:00',
            'close' => $row && $row->close_time ? Str::substr((string) $row->close_time, 0, 5) : '21:00',
        ];
    })->values();

    $seedHolidays = collect(old('holidays', $outlet->holidays->map(fn ($h) => ['date' => optional($h->holiday_date)->format('Y-m-d'), 'note' => $h->note])->all()));
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
            <a href="#" class="on"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M8 4v16"/></svg>Outlet</a>
        </nav>
        <div class="rail__user">
            <div class="av">{{ Str::upper(Str::substr(auth()->user()->name ?? 'U', 0, 2)) }}</div>
            <div><b>{{ auth()->user()->name ?? 'User' }}</b><span>{{ auth()->user()?->getRoleNames()->first() ?? 'staf' }}</span></div>
        </div>
    </aside>

    <div class="main">
        <form method="POST" action="{{ route('admin.outlets.update', $outlet) }}" id="outletForm">
            @csrf
            @method('PUT')

            <div class="topbar">
                <div>
                    <div class="crumb"><a href="{{ url('/') }}">Outlet</a><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg><b>{{ $outlet->name }}</b></div>
                    <h1>Edit Outlet</h1>
                </div>
                <div class="topbar__right">
                    <a class="btn btn--ghost btn--sm" href="{{ url('/') }}">Batal</a>
                    <button type="submit" class="btn btn--primary btn--sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>Simpan
                    </button>
                </div>
            </div>

            <div class="scroll">
                <div class="layout">
                    <div class="formcol">

                        {{-- Identitas --}}
                        <div class="card">
                            <div class="card__h">
                                <div class="ci"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M8 4v16"/></svg></div>
                                <div><b>Identitas Outlet</b><span>Nama &amp; status aktif</span></div>
                            </div>
                            <div class="card__b">
                                <div class="frow">
                                    <div class="field">
                                        <label>Nama outlet</label>
                                        <input class="input" value="{{ $outlet->name }}" disabled>
                                        <span class="hint">ID outlet {{ $outlet->id_outlet }} · dikelola dari NEVIRA.</span>
                                    </div>
                                    <div class="field"><label>Kode / brand</label><input class="input" value="—" disabled><span class="hint">Brand outlet menyusul (OPS-1005).</span></div>
                                    <div class="field full">
                                        <div class="toggle-field">
                                            <div class="tf-txt"><b>Status aktif</b><span>Outlet menerima laporan harian &amp; pemantauan</span></div>
                                            <label class="switch pri"><input type="checkbox" name="active" value="1" @checked(old('active', $outlet->active))><span class="tr"></span></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Pelaporan --}}
                        <div class="card">
                            <div class="card__h">
                                <div class="ci"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg></div>
                                <div><b>Pelaporan Harian</b><span>Kapan laporan investor dikirim</span></div>
                            </div>
                            <div class="card__b">
                                <div class="frow">
                                    <div class="field">
                                        <label>Jam laporan harian <span class="req">*</span></label>
                                        <div class="inwrap inwrap--time">
                                            <span class="pre"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                                            <input class="input tnum @error('report_time') input--err @enderror" type="time" name="report_time" value="{{ old('report_time', Str::substr((string) $outlet->report_time, 0, 5)) }}" required>
                                        </div>
                                        @error('report_time')<span class="hint hint--err">{{ $message }}</span>@else<span class="hint">Laporan rekap dikirim otomatis pada jam ini (WIB).</span>@enderror
                                    </div>
                                    <div class="field">
                                        <label>Mode kirim</label>
                                        <select class="input" disabled><option>Hybrid — konfirmasi manual</option></select>
                                        <span class="hint">Mode diatur per target investor (OPS-804).</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Deteksi Outlet-Diam --}}
                        <div class="card">
                            <div class="card__h">
                                <div class="ci"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M18.7 8a5 5 0 0 0-9.7 1.7c0 3.3 4.5 5.3 4.5 5.3"/></svg></div>
                                <div><b>Deteksi Outlet-Diam</b><span>Jam pengecekan &amp; ambang transaksi</span></div>
                            </div>
                            <div class="card__b">
                                <div class="checkhead">
                                    <span class="lab"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Jam cek outlet-diam</span>
                                    <span class="count" id="timeCount">0 jam</span>
                                </div>
                                <div class="times" id="times"></div>
                                <button type="button" class="addtime" id="addTime"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>Tambah jam cek</button>

                                <div @class(['validbox', 'on' => $errors->has('checkpoints')]) id="validBox">
                                    <div class="validbox__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg></div>
                                    <div><b id="validTitle">Jam cek tumpang tindih</b><p id="validMsg">{{ $errors->first('checkpoints') ?: 'Setiap jam cek harus unik & berjarak minimal 30 menit.' }}</p></div>
                                </div>

                                <div class="frow" style="margin-top:18px">
                                    <div class="field">
                                        <label>Ambang outlet-diam <span class="req">*</span></label>
                                        <div class="inwrap">
                                            <input class="input tnum @error('silent_threshold_pct') input--err @enderror" id="fThreshold" name="silent_threshold_pct" value="{{ old('silent_threshold_pct', $outlet->silent_threshold_pct) }}" required>
                                            <span class="suffix">%</span>
                                        </div>
                                        @error('silent_threshold_pct')<span class="hint hint--err">{{ $message }}</span>@else<span class="hint" id="threshHint">Sinyal muncul bila transaksi &lt; {{ old('silent_threshold_pct', $outlet->silent_threshold_pct) }}% rata-rata jam tersebut.</span>@enderror
                                    </div>
                                    <div class="field">
                                        <label>Basis perbandingan</label>
                                        <select class="input" name="comparison_basis">
                                            @foreach ($comparisonOptions as $val => $lbl)
                                                <option value="{{ $val }}" @selected(old('comparison_basis', $outlet->comparison_basis) === $val)>{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Jam Operasional --}}
                        <div class="card">
                            <div class="card__h">
                                <div class="ci"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
                                <div><b>Jam Operasional</b><span>Per hari &amp; hari libur</span></div>
                            </div>
                            <div class="card__b">
                                <div id="days"></div>
                                <div class="holiday-wrap">
                                    <span class="lab" style="font-size:12px;font-weight:700;color:var(--ink-2);display:flex;align-items:center;gap:6px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>Hari libur khusus</span>
                                    <div class="chips" id="holidays"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SIDEBAR --}}
                    <div class="side">
                        <div @class(['baseline', 'on' => ! $hasBaseline]) id="baseline">
                            <div class="baseline__h">
                                <div class="baseline__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg></div>
                                <b>Baseline belum tersedia</b>
                            </div>
                            <p>Outlet ini belum punya cukup riwayat transaksi. Deteksi outlet-diam memakai ambang konservatif sampai baseline terbentuk (±14 hari). Ambang tetap disimpan.</p>
                        </div>

                        <div class="sumcard">
                            <div class="sumcard__top">
                                <div class="sumcard__av" style="background:var(--lw)">{{ Str::upper(Str::substr($outlet->name, 0, 2)) }}</div>
                                <div><b>{{ $outlet->name }}</b><span class="brandtag brandtag--lw">Less Worry</span></div>
                            </div>
                            <div class="sumcard__body">
                                <div class="sumrow"><span class="k">Status</span><span class="v">
                                    <span class="statuspill {{ old('active', $outlet->active) ? 'active' : 'inactive' }}" id="sumStatus"><span class="d"></span>{{ old('active', $outlet->active) ? 'Aktif' : 'Nonaktif' }}</span>
                                </span></div>
                                <div class="sumrow"><span class="k">Jam laporan</span><span class="v tnum">{{ old('report_time', Str::substr((string) $outlet->report_time, 0, 5)) }}</span></div>
                                <div class="sumrow"><span class="k">Jam cek</span><span class="v tnum" id="sumChecks">0 jam</span></div>
                                <div class="sumrow"><span class="k">Ambang diam</span><span class="v tnum" id="sumThresh">{{ old('silent_threshold_pct', $outlet->silent_threshold_pct) }}%</span></div>
                            </div>
                        </div>

                        <div class="tipcard">
                            <b><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1h6c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2z"/></svg>Tips</b>
                            <p>Letakkan jam cek di awal, tengah, dan sore hari operasional — bukan di jam buka/tutup saat transaksi memang sepi.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="savebar">
                <span class="status" id="saveStatus">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    @if (session('status')) Tersimpan baru saja @else Perubahan terakhir: {{ $outlet->updated_at?->timezone('Asia/Jakarta')->format('d M, H:i') ?? '—' }} @endif
                </span>
                <div class="spacer"></div>
                <a class="btn btn--ghost" href="{{ url('/') }}">Batal</a>
                <button type="submit" class="btn btn--primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>Simpan perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>
@endsection

@section('scripts')
<script>
    const FORM = document.getElementById('outletForm');
    let times = @json($seedTimes->values());
    let days = @json($seedDays);
    let holidays = @json($seedHolidays->values());
    const flashSaved = @json((bool) session('status'));

    function svg(p, sw){ return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'+(sw||2)+'" stroke-linecap="round" stroke-linejoin="round">'+p+'</svg>'; }
    const CLOCK = '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>';
    const TRASH = '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>';
    const X = '<path d="M18 6L6 18M6 6l12 12"/>';
    function toMin(t){ if(!t) return null; const [h,m]=t.split(':').map(Number); return h*60+m; }

    // ---- jam cek ----
    function renderTimes(){
        const wrap = document.getElementById('times');
        const mins = times.map(toMin);
        const conflict = times.map((t,i)=> mins.some((m,j)=> j!==i && m!==null && mins[i]!==null && Math.abs(m-mins[i])<30));
        wrap.innerHTML = times.map((t,i)=>{
            const m = toMin(t); let gap;
            if(i>0 && m!==null && toMin(times[i-1])!==null){
                const diff = m - toMin(times[i-1]);
                gap = diff<30 ? `<span class="gap bad">⚠ ${diff} mnt dari sebelumnya</span>`
                              : `<span class="gap">+${Math.floor(diff/60)}j ${diff%60>0?(diff%60)+'m':''} dari sebelumnya</span>`;
            } else { gap = `<span class="gap">cek pertama</span>`; }
            return `<div class="timerow ${conflict[i]?'dup':''}">
                <span class="order">${i+1}</span>
                <div class="inwrap inwrap--time"><span class="pre">${svg(CLOCK)}</span>
                    <input class="input tnum" type="time" name="checkpoints[${i}][time]" value="${t}" onchange="updTime(${i},this.value)" required></div>
                ${gap}
                <button type="button" class="delbtn" onclick="delTime(${i})" ${times.length<=1?'disabled':''}>${svg(TRASH)}</button>
            </div>`;
        }).join('');
        document.getElementById('timeCount').textContent = times.length+' jam';
        document.getElementById('sumChecks').textContent = times.length+' jam';
        const hasConflict = conflict.some(Boolean);
        document.getElementById('validBox').classList.toggle('on', hasConflict || @json($errors->has('checkpoints')));
        return hasConflict;
    }
    window.updTime = (i,v)=>{ times[i]=v; renderTimes(); };
    window.delTime = (i)=>{ times.splice(i,1); renderTimes(); };
    document.getElementById('addTime').addEventListener('click', ()=>{
        let last = times.length ? toMin(times[times.length-1]) : 8*60;
        let next = Math.min((last??480)+180, 21*60);
        times.push(String(Math.floor(next/60)).padStart(2,'0')+':'+String(next%60).padStart(2,'0'));
        renderTimes();
    });

    // ---- jam operasional ----
    function renderDays(){
        document.getElementById('days').innerHTML = days.map((d,i)=>`
            <div class="dayrow ${d.closed?'closed':''}" id="day${i}">
                <div class="dayrow__name">
                    <label class="switch pri"><input type="checkbox" ${d.closed?'':'checked'} onchange="toggleDay(${i},this.checked)"><span class="tr"></span></label>
                    <b>${d.label}</b>
                </div>
                <div class="dayrow__hours">
                    <input type="hidden" name="operating_hours[${i}][weekday]" value="${d.weekday}">
                    <input type="hidden" name="operating_hours[${i}][is_closed]" id="closed${i}" value="${d.closed?1:0}">
                    <div class="dayrow__times">
                        <div class="inwrap inwrap--time"><span class="pre">${svg(CLOCK)}</span><input class="input tnum" type="time" name="operating_hours[${i}][open]" value="${d.open||''}"></div>
                        <span class="dash">–</span>
                        <div class="inwrap inwrap--time"><span class="pre">${svg(CLOCK)}</span><input class="input tnum" type="time" name="operating_hours[${i}][close]" value="${d.close||''}"></div>
                    </div>
                    <span class="closedtag">Tutup / libur</span>
                </div>
            </div>`).join('');
    }
    window.toggleDay = (i,open)=>{
        days[i].closed = !open;
        document.getElementById('day'+i).classList.toggle('closed', !open);
        document.getElementById('closed'+i).value = open ? 0 : 1;
    };

    // ---- hari libur ----
    function renderHolidays(){
        const wrap = document.getElementById('holidays');
        wrap.innerHTML = holidays.map((h,i)=>`
            <span class="chip">
                <input type="hidden" name="holidays[${i}][date]" value="${h.date||''}">
                <input type="hidden" name="holidays[${i}][note]" value="${(h.note||'').replace(/"/g,'&quot;')}">
                ${h.date||''}${h.note?' · '+h.note:''}
                <span class="x" onclick="delHoliday(${i})">${svg(X,2.4)}</span>
            </span>`).join('') +
            `<span class="chip chip--add" onclick="addHoliday()">${svg('<path d="M12 5v14M5 12h14"/>',2.2)}Tambah tanggal</span>`;
    }
    window.delHoliday = (i)=>{ holidays.splice(i,1); renderHolidays(); };
    window.addHoliday = ()=>{
        const date = prompt('Tanggal libur (YYYY-MM-DD):');
        if(!date) return;
        const note = prompt('Catatan (opsional):') || '';
        holidays.push({date, note});
        renderHolidays();
    };

    // ---- ringkasan live ----
    const thr = document.getElementById('fThreshold');
    thr.addEventListener('input', ()=>{
        document.getElementById('sumThresh').textContent = (thr.value||'0')+'%';
        const h = document.getElementById('threshHint');
        if(h) h.innerHTML = 'Sinyal muncul bila transaksi &lt; '+(thr.value||'0')+'% rata-rata jam tersebut.';
    });

    // ---- blok submit bila konflik jam cek ----
    FORM.addEventListener('submit', (e)=>{
        if(renderTimes()){ e.preventDefault(); toast('Tidak bisa disimpan — perbaiki jam cek yang tumpang tindih','alert'); }
    });

    // ---- toast ----
    const ICONS = { check:'<path d="M20 6L9 17l-5-5"/>', alert:'<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>', info:'<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>' };
    function toast(msg, icon){
        const wrap = document.getElementById('toastWrap');
        const bg = icon==='check'?'var(--good)':icon==='alert'?'var(--bad)':'#3C4A5C';
        const el = document.createElement('div'); el.className='toast';
        el.innerHTML = `<span class="ti" style="background:${bg}">${svg(ICONS[icon]||ICONS.info,3)}</span>${msg}`;
        wrap.appendChild(el);
        requestAnimationFrame(()=>el.classList.add('show'));
        setTimeout(()=>{ el.classList.remove('show'); setTimeout(()=>el.remove(),250); },2600);
    }

    renderTimes(); renderDays(); renderHolidays();
    if(flashSaved) toast('Pengaturan outlet berhasil disimpan','check');
    @if($errors->has('checkpoints')) toast(@json($errors->first('checkpoints')),'alert'); @endif
</script>
@endsection
