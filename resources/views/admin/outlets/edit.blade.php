@extends('layouts.admin')

@section('title', 'Edit Outlet — '.$outlet->name)

@section('content')
    <h1>Edit Outlet</h1>
    <div class="sub">{{ $outlet->name }} · ID outlet {{ $outlet->id_outlet }}</div>

    {{-- STATE: tersimpan --}}
    @if (session('status'))
        <div class="alert ok" role="status">{{ session('status') }}</div>
    @endif

    {{-- STATE: error validasi --}}
    @if ($errors->any())
        <div class="alert err" role="alert">
            Periksa kembali isian:
            <ul style="margin:6px 0 0 18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- STATE: outlet baru, baseline belum ada --}}
    @unless ($hasBaseline)
        <div class="alert info">
            Baseline transaksi belum tersedia untuk outlet ini. Deteksi outlet-diam memakai
            ambang konservatif sampai baseline terbentuk (≈30 hari data).
        </div>
    @endunless

    <form method="POST" action="{{ route('admin.outlets.update', $outlet) }}">
        @csrf
        @method('PUT')

        <div class="card">
            <h2>Status &amp; Jam Laporan</h2>
            <div class="switch">
                <input type="checkbox" id="active" name="active" value="1" @checked(old('active', $outlet->active))>
                <label for="active" style="margin:0;">Outlet aktif (laporan &amp; sinyal berjalan)</label>
            </div>
            <div style="margin-top:14px;">
                <label for="report_time">Jam laporan harian (WIB)</label>
                <input type="time" id="report_time" name="report_time"
                       value="{{ old('report_time', \Illuminate\Support\Str::substr((string) $outlet->report_time, 0, 5)) }}" required>
                @error('report_time') <div class="err-text">{{ $message }}</div> @enderror
            </div>
        </div>

        {{-- Titik cek outlet-diam (DINAMIS: tambah/hapus) + ambang --}}
        <div class="card">
            <h2>Jam Cek Outlet-Diam &amp; Ambang</h2>
            <div id="checkpoints">
                @foreach (old('checkpoints', $outlet->checkpoints->map(fn ($c) => ['hour' => $c->checkpoint_hour, 'threshold' => $c->threshold_pct])->all()) as $i => $c)
                    <div class="row checkpoint-row">
                        <div>
                            <label>Jam (0–23)</label>
                            <input type="number" min="0" max="23" name="checkpoints[{{ $i }}][hour]" value="{{ $c['hour'] }}" required>
                        </div>
                        <div>
                            <label>Ambang (%)</label>
                            <input type="number" min="0" max="100" name="checkpoints[{{ $i }}][threshold]" value="{{ $c['threshold'] }}" required>
                        </div>
                        <div><button type="button" class="rm" onclick="this.closest('.checkpoint-row').remove()">Hapus</button></div>
                    </div>
                @endforeach
            </div>
            <button type="button" class="link" id="add-checkpoint">+ Tambah titik cek</button>
            @error('checkpoints') <div class="err-text">{{ $message }}</div> @enderror
        </div>

        {{-- Jam operasional (jendela; boleh >1 per hari) --}}
        <div class="card">
            <h2>Jam Operasional</h2>
            <div id="operating-hours">
                @foreach (old('operating_hours', $outlet->operatingHours->map(fn ($w) => ['weekday' => $w->weekday, 'open' => \Illuminate\Support\Str::substr((string) $w->open_time, 0, 5), 'close' => \Illuminate\Support\Str::substr((string) $w->close_time, 0, 5)])->all()) as $i => $w)
                    <div class="row oh-row">
                        <div>
                            <label>Hari</label>
                            <select name="operating_hours[{{ $i }}][weekday]">
                                @foreach ($weekdays as $wd => $name)
                                    <option value="{{ $wd }}" @selected((int) $w['weekday'] === $wd)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label>Buka</label><input type="time" name="operating_hours[{{ $i }}][open]" value="{{ $w['open'] }}" required></div>
                        <div><label>Tutup</label><input type="time" name="operating_hours[{{ $i }}][close]" value="{{ $w['close'] }}" required></div>
                        <div><button type="button" class="rm" onclick="this.closest('.oh-row').remove()">Hapus</button></div>
                    </div>
                @endforeach
            </div>
            <button type="button" class="link" id="add-oh">+ Tambah jam operasional</button>
            @error('operating_hours') <div class="err-text">{{ $message }}</div> @enderror
        </div>

        {{-- Hari libur --}}
        <div class="card">
            <h2>Hari Libur</h2>
            <div id="holidays">
                @foreach (old('holidays', $outlet->holidays->map(fn ($h) => ['date' => optional($h->holiday_date)->format('Y-m-d'), 'note' => $h->note])->all()) as $i => $h)
                    <div class="row holiday-row">
                        <div><label>Tanggal</label><input type="date" name="holidays[{{ $i }}][date]" value="{{ $h['date'] }}" required></div>
                        <div><label>Catatan</label><input type="text" name="holidays[{{ $i }}][note]" value="{{ $h['note'] }}" maxlength="120"></div>
                        <div><button type="button" class="rm" onclick="this.closest('.holiday-row').remove()">Hapus</button></div>
                    </div>
                @endforeach
            </div>
            <button type="button" class="link" id="add-holiday">+ Tambah hari libur</button>
        </div>

        <div class="actions">
            <button type="submit" class="primary">Simpan</button>
            <a href="{{ url('/') }}"><button type="button">Batal</button></a>
        </div>
    </form>
@endsection

@section('scripts')
<script>
    // Tambah baris dinamis. Index pakai counter agar nama array unik.
    const weekdays = @json($weekdays);
    let ci = {{ count(old('checkpoints', $outlet->checkpoints)) }};
    let oi = {{ count(old('operating_hours', $outlet->operatingHours)) }};
    let hi = {{ count(old('holidays', $outlet->holidays)) }};

    document.getElementById('add-checkpoint').addEventListener('click', () => {
        const div = document.createElement('div');
        div.className = 'row checkpoint-row';
        div.innerHTML = `<div><label>Jam (0–23)</label><input type="number" min="0" max="23" name="checkpoints[${ci}][hour]" required></div>
            <div><label>Ambang (%)</label><input type="number" min="0" max="100" name="checkpoints[${ci}][threshold]" value="50" required></div>
            <div><button type="button" class="rm" onclick="this.closest('.checkpoint-row').remove()">Hapus</button></div>`;
        document.getElementById('checkpoints').appendChild(div);
        ci++;
    });

    document.getElementById('add-oh').addEventListener('click', () => {
        const opts = weekdays.map((n, i) => `<option value="${i}">${n}</option>`).join('');
        const div = document.createElement('div');
        div.className = 'row oh-row';
        div.innerHTML = `<div><label>Hari</label><select name="operating_hours[${oi}][weekday]">${opts}</select></div>
            <div><label>Buka</label><input type="time" name="operating_hours[${oi}][open]" required></div>
            <div><label>Tutup</label><input type="time" name="operating_hours[${oi}][close]" required></div>
            <div><button type="button" class="rm" onclick="this.closest('.oh-row').remove()">Hapus</button></div>`;
        document.getElementById('operating-hours').appendChild(div);
        oi++;
    });

    document.getElementById('add-holiday').addEventListener('click', () => {
        const div = document.createElement('div');
        div.className = 'row holiday-row';
        div.innerHTML = `<div><label>Tanggal</label><input type="date" name="holidays[${hi}][date]" required></div>
            <div><label>Catatan</label><input type="text" name="holidays[${hi}][note]" maxlength="120"></div>
            <div><button type="button" class="rm" onclick="this.closest('.holiday-row').remove()">Hapus</button></div>`;
        document.getElementById('holidays').appendChild(div);
        hi++;
    });
</script>
@endsection
