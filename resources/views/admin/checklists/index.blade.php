@extends('layouts.app')

@section('title', 'Checklist Operasional')
@section('heading', 'Checklist Operasional')
@section('styles')<link href="{{ asset('css/oms-admin.css') }}" rel="stylesheet">
<style>
    .cl-wrap{max-width:920px;margin:0 auto;padding:28px 24px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}
    .cl-h{font-size:20px;font-weight:800;margin:0 0 4px;}
    .cl-sub{color:#6B7280;font-size:13px;margin:0 0 18px;}
    .card{background:#fff;border:1px solid #E6E9EE;border-radius:12px;padding:16px;margin-bottom:14px;}
    .card h3{font-size:15px;font-weight:700;margin:0 0 4px;}
    .badge{font-size:11px;font-weight:700;border-radius:6px;padding:2px 7px;background:#EBF2FE;color:#1A4BA6;}
    .badge.photo{background:#E7FFDB;color:#1B5E20;}
    ul{margin:8px 0;padding-left:0;list-style:none;}
    li{display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid #F2F5F8;font-size:13px;}
    select,input{border:1px solid #D8DCE3;border-radius:7px;padding:6px 8px;font:inherit;font-size:12.5px;}
    .btn{font:inherit;font-size:12.5px;font-weight:650;border-radius:8px;border:1px solid #2C6FE0;background:#2C6FE0;color:#fff;padding:6px 12px;cursor:pointer;}
    .btn.sec{background:#fff;color:#C0392B;border-color:#E5C4C0;}
    .row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:10px;}
    .flash{background:#E7FFDB;border:1px solid #BfeaA8;color:#1B5E20;border-radius:8px;padding:10px 12px;margin-bottom:16px;font-size:13px;}
    .errbox{background:#FDECEA;border:1px solid #F5C6C0;color:#A1281B;border-radius:8px;padding:10px 12px;margin-bottom:16px;font-size:13px;}
    label.chk{font-size:12.5px;display:flex;gap:5px;align-items:center;}
</style>@endsection

@section('content')
<div class="cl-wrap">
    <h1 class="cl-h">Checklist Operasional</h1>
    <p class="cl-sub">Template per grup (semua outlet) atau khusus outlet. Item ber-foto wajib bukti kamera in-app (anti-palsu).</p>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="errbox">{{ $errors->first() }}</div>@endif

    @foreach ($templates as $t)
        <div class="card">
            <h3>{{ $t->name }} <span class="badge">{{ $t->schedule }}</span>
                <span class="badge">{{ $t->id_outlet ? ('outlet '.$t->id_outlet) : 'grup' }}</span>
                @unless ($t->active)<span class="badge" style="background:#FDECEA;color:#A1281B">nonaktif</span>@endunless
            </h3>
            <ul>
                @foreach ($t->items as $it)
                    <li>
                        <span>{{ $it->order }}. {{ $it->label }}</span>
                        @if ($it->requires_photo)<span class="badge photo">foto</span>@endif
                        <form method="POST" action="{{ route('admin.checklists.items.destroy', $it) }}" style="margin-left:auto">
                            @csrf @method('DELETE')<button class="btn sec" type="submit">×</button>
                        </form>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('admin.checklists.items.store', $t) }}" class="row">
                @csrf
                <input type="text" name="label" placeholder="Item baru" required>
                <label class="chk"><input type="checkbox" name="requires_photo" value="1" checked> wajib foto</label>
                <input type="number" name="order" placeholder="urut" style="width:60px" value="{{ $t->items->count() + 1 }}">
                <button class="btn" type="submit">Tambah item</button>
                <form method="POST" action="{{ route('admin.checklists.destroy', $t) }}" onsubmit="return confirm('Hapus template?')" style="margin-left:auto">
                    @csrf @method('DELETE')<button class="btn sec" type="submit">Hapus template</button>
                </form>
            </form>
        </div>
    @endforeach

    <div class="card">
        <h3>Template baru</h3>
        <form method="POST" action="{{ route('admin.checklists.store') }}" class="row">
            @csrf
            <input type="text" name="name" placeholder="Nama template" required>
            <select name="schedule">@foreach ($schedules as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach</select>
            <input type="number" name="id_outlet" placeholder="id_outlet (kosong=grup)" style="width:170px">
            <label class="chk"><input type="checkbox" name="active" value="1" checked> aktif</label>
            <button class="btn" type="submit">Buat template</button>
        </form>
    </div>
</div>
@endsection
