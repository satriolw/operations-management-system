@extends('layouts.app')

@section('title', 'Outlet')
@section('heading', 'Outlet')
@section('subheading', 'Master data outlet')

@section('content')
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
    <table class="tbl">
        <thead><tr><th>id_outlet</th><th>Nama</th><th>Jam Laporan</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($outlets as $o)
            <tr>
                <td>{{ $o->id_outlet }}</td>
                <td>{{ $o->name }}</td>
                <td>{{ \Illuminate\Support\Str::substr((string) $o->report_time, 0, 5) }}</td>
                <td>{{ $o->active ? 'Aktif' : 'Nonaktif' }}</td>
                <td><a href="{{ route('admin.outlets.edit', $o) }}" style="color:var(--teal-600);font-weight:650">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="5" style="color:var(--ink-3)">Belum ada outlet.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
