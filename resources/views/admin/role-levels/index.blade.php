@extends('layouts.app')

@section('title', 'Peta Role → Level')
@section('heading', 'Peta Role → Level')
@section('subheading', 'Master data id_role NEVIRA → level (OPS-805) · dipakai deteksi self-approval (OPS-601)')
@section('styles')
<style>
    .rl-wrap{max-width:860px;margin:0 auto;padding:24px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}
    .card{background:#fff;border:1px solid #E6E9EE;border-radius:12px;padding:16px;}
    table{width:100%;border-collapse:collapse;font-size:13px;}
    th,td{text-align:left;padding:8px 9px;border-bottom:1px solid #EEF1F4;vertical-align:middle;}
    th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#8B93A1;}
    input{border:1px solid #D8DCE3;border-radius:7px;padding:6px 8px;font:inherit;font-size:12.5px;}
    .b{font:inherit;font-weight:650;font-size:12px;border-radius:7px;border:1px solid #2C6FE0;background:#2C6FE0;color:#fff;padding:6px 12px;cursor:pointer;}
    .b.del{background:#fff;color:#C0392B;border-color:#E5C4C0;}
    .ok{background:#F2FBED;border:1px solid #D6EFCB;color:#1B5E20;border-radius:8px;padding:8px 12px;margin-bottom:14px;font-size:13px;}
    .hint{color:#6B7280;font-size:12px;margin:0 0 14px;}
    .err{color:#A1281B;font-size:12px;margin-bottom:10px;}
</style>
@endsection

@section('content')
<div class="rl-wrap">
    @if (session('status'))<div class="ok">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
    <p class="hint">Level lebih tinggi = wewenang lebih besar. <b>dual_authority</b> = boleh mengajukan & menyetujui sendiri (≥ Kepala Toko). Tanpa peta, OPS-601 menandai "perlu ditinjau".</p>

    <div class="card">
        <table>
            <thead><tr><th>id_role</th><th>Label</th><th>Level</th><th>Dual authority</th><th></th></tr></thead>
            <tbody>
            @foreach ($levels as $lv)
                <tr>
                    <form method="POST" action="{{ route('admin.role-levels.update', $lv) }}">@csrf @method('PUT')
                        <td><input name="id_role" type="number" value="{{ $lv->id_role }}" style="width:80px" required></td>
                        <td><input name="label" value="{{ $lv->label }}" required></td>
                        <td><input name="level" type="number" min="0" max="100" value="{{ $lv->level }}" style="width:70px" required></td>
                        <td><input type="hidden" name="dual_authority_allowed" value="0"><input type="checkbox" name="dual_authority_allowed" value="1" @checked($lv->dual_authority_allowed)></td>
                        <td><button class="b" type="submit">Simpan</button>
                    </form>
                    <form method="POST" action="{{ route('admin.role-levels.destroy', $lv) }}" onsubmit="return confirm('Hapus peta role ini?')" style="display:inline">@csrf @method('DELETE')<button class="b del">Hapus</button></form>
                    </td>
                </tr>
            @endforeach
            <form method="POST" action="{{ route('admin.role-levels.store') }}">@csrf
                <tr>
                    <td><input name="id_role" type="number" placeholder="id_role" style="width:80px" required></td>
                    <td><input name="label" placeholder="mis. Kepala Toko" required></td>
                    <td><input name="level" type="number" min="0" max="100" placeholder="0–100" style="width:70px" required></td>
                    <td><input type="hidden" name="dual_authority_allowed" value="0"><input type="checkbox" name="dual_authority_allowed" value="1"></td>
                    <td><button class="b" type="submit">Tambah</button></td>
                </tr>
            </form>
            </tbody>
        </table>
    </div>
</div>
@endsection
