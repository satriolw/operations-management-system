@extends('layouts.app')

@section('title', 'SLA Produksi')
@section('heading', 'SLA Produksi (Nota Terlambat)')
@section('subheading', 'Ambang & mode jam per outlet')

@section('content')
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="flash" style="background:#FBE9E6;border-color:#F2C9C2;color:#C0392B">{{ $errors->first() }}</div>@endif

    <p style="color:var(--ink-3);font-size:12.5px;max-width:640px">Mode <b>business_hours</b>: overdue diukur jam operasional (nota lintas-tutup tak false-positive). <b>wallclock</b>: apa adanya.</p>

    @foreach ($outlets as $o)
        @php $c = $config($o->id_outlet); @endphp
        <form method="POST" action="{{ route('admin.sla-config.update', $o) }}" class="kpi" style="margin-bottom:12px;max-width:760px">
            @csrf @method('PUT')
            <b>{{ $o->name }} <span style="color:var(--ink-3);font-weight:500">· id {{ $o->id_outlet }}</span></b>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:10px">
                <label style="font-size:12px">Mode jam
                    <select name="sla_clock_mode" style="width:100%;padding:7px;border:1px solid var(--border-2);border-radius:8px">
                        <option value="business_hours" @selected($c->sla_clock_mode==='business_hours')>business_hours</option>
                        <option value="wallclock" @selected($c->sla_clock_mode==='wallclock')>wallclock</option>
                    </select></label>
                <label style="font-size:12px">Grace (menit)<input type="number" name="grace_minutes" value="{{ $c->grace_minutes }}" min="0" max="1440" style="width:100%;padding:7px;border:1px solid var(--border-2);border-radius:8px"></label>
                <label style="font-size:12px">Approaching lead<input type="number" name="approaching_lead_minutes" value="{{ $c->approaching_lead_minutes }}" min="0" max="1440" style="width:100%;padding:7px;border:1px solid var(--border-2);border-radius:8px"></label>
                <label style="font-size:12px">Stuck ambang<input type="number" name="stuck_minutes_threshold" value="{{ $c->stuck_minutes_threshold }}" min="0" max="10080" style="width:100%;padding:7px;border:1px solid var(--border-2);border-radius:8px"></label>
                <label style="font-size:12px">Minor overdue<input type="number" name="minor_overdue_minutes" value="{{ $c->minor_overdue_minutes }}" min="0" max="10080" style="width:100%;padding:7px;border:1px solid var(--border-2);border-radius:8px"></label>
            </div>
            <button type="submit" class="btn btn--primary" style="margin-top:10px;padding:7px 14px">Simpan</button>
        </form>
    @endforeach
@endsection
