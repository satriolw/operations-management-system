@extends('layouts.app')

@section('title', 'Template Laporan')
@section('heading', 'Template Laporan')
@section('subheading', 'Master → override per-outlet (pewarisan) · sunting blok/token di builder')
@section('styles')
<style>
    .tp-wrap{max-width:1000px;margin:0 auto;padding:24px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}
    .card{background:#fff;border:1px solid #E6E9EE;border-radius:12px;padding:16px;margin-bottom:16px;}
    .card h3{margin:0 0 12px;font-size:14px;font-weight:800;}
    table{width:100%;border-collapse:collapse;font-size:13px;}
    th,td{text-align:left;padding:8px 9px;border-bottom:1px solid #EEF1F4;vertical-align:middle;}
    th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#8B93A1;}
    .scope{font-size:11px;font-weight:700;border-radius:6px;padding:2px 8px;}
    .scope.master{background:#EBF2FE;color:#1A4BA6;} .scope.outlet,.scope.target{background:#FFF1E0;color:#9A5B00;}
    .badge{font-size:11px;font-weight:700;border-radius:6px;padding:2px 7px;background:#E7FFDB;color:#1B5E20;}
    .badge.off{background:#EEF1F4;color:#555E6C;}
    input,select{border:1px solid #D8DCE3;border-radius:7px;padding:6px 8px;font:inherit;font-size:12.5px;}
    .b{font:inherit;font-weight:650;font-size:12px;border-radius:7px;border:1px solid #2C6FE0;background:#2C6FE0;color:#fff;padding:6px 12px;cursor:pointer;text-decoration:none;}
    .b.del{background:#fff;color:#C0392B;border-color:#E5C4C0;}
    .b.alt{background:#fff;color:#2C6FE0;}
    .ok{background:#F2FBED;border:1px solid #D6EFCB;color:#1B5E20;border-radius:8px;padding:8px 12px;margin-bottom:14px;font-size:13px;}
    .err{color:#A1281B;font-size:12px;margin-bottom:10px;}
    .grid{display:flex;flex-wrap:wrap;gap:8px;align-items:center;}
</style>
@endsection

@section('content')
<div class="tp-wrap">
    @if (session('status'))<div class="ok">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="err">{{ $errors->first() }}</div>@endif

    <div class="card">
        <h3>Template master (grup)</h3>
        <table>
            <thead><tr><th>Nama</th><th>Token valid</th><th>Aktif</th><th>Approved Meta</th><th style="text-align:right">Aksi</th></tr></thead>
            <tbody>
            @forelse ($masters as $m)
                <tr>
                    <td><span class="scope master">master</span> {{ $m->name }}</td>
                    <td>{{ $m->hasValidTokens() ? '✓' : '⚠ token tak dikenal' }}</td>
                    <td><span class="badge {{ $m->active ? '' : 'off' }}">{{ $m->active ? 'aktif' : 'nonaktif' }}</span></td>
                    <td>{{ $m->meta_template_ref ?: '—' }}</td>
                    <td style="text-align:right" class="grid" >
                        <a class="b alt" href="{{ route('admin.templates.builder', $m) }}">Builder</a>
                        <form method="POST" action="{{ route('admin.templates.destroy', $m) }}" onsubmit="return confirm('Hapus template master ini?')" style="display:inline">@csrf @method('DELETE')<button class="b del">Hapus</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="color:#8B93A1">Belum ada master. Seed default membuat satu.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>Override per-outlet</h3>
        <table>
            <thead><tr><th>Nama</th><th>Outlet</th><th>Mewarisi</th><th>Token valid</th><th style="text-align:right">Aksi</th></tr></thead>
            <tbody>
            @forelse ($overrides as $o)
                <tr>
                    <td><span class="scope {{ $o->scope }}">{{ $o->scope }}</span> {{ $o->name }}</td>
                    <td>{{ $o->id_outlet }}</td>
                    <td>{{ optional($o->parent)->name ?? '—' }}</td>
                    <td>{{ $o->hasValidTokens() ? '✓' : '⚠' }}</td>
                    <td style="text-align:right" class="grid">
                        <a class="b alt" href="{{ route('admin.templates.builder', $o) }}">Builder</a>
                        <form method="POST" action="{{ route('admin.templates.destroy', $o) }}" onsubmit="return confirm('Hapus override ini?')" style="display:inline">@csrf @method('DELETE')<button class="b del">Hapus</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="color:#8B93A1">Belum ada override. Outlet tanpa override memakai master.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>Buat template baru</h3>
        <form method="POST" action="{{ route('admin.templates.store') }}" class="grid">@csrf
            <select name="scope" id="scope" onchange="document.getElementById('ovr').style.display=this.value==='master'?'none':'flex'">
                <option value="master">Master (grup)</option>
                <option value="outlet">Override outlet</option>
            </select>
            <input name="name" placeholder="Nama template" required>
            <input name="meta_template_ref" placeholder="ref approved Meta (opsional)">
            <span id="ovr" class="grid" style="display:none">
                <select name="id_outlet">
                    <option value="">— outlet —</option>
                    @foreach ($outlets as $ot)<option value="{{ $ot->id_outlet }}">{{ $ot->name }} ({{ $ot->id_outlet }})</option>@endforeach
                </select>
                <select name="parent_template_id">
                    <option value="">— mewarisi master —</option>
                    @foreach ($masters as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                </select>
            </span>
            <button class="b" type="submit">Buat &amp; ke builder</button>
        </form>
    </div>
</div>
@endsection
