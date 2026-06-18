<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'OMS') · Less Worry</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    {{-- CSS halaman dimuat DULU; oms-app.css (chrome shell) TERAKHIR agar selektor shell
         (.app/.rail/.topbar/.scroll) menang atas CSS lama (oms-admin/users/wa) — koherensi UX. --}}
    @yield('styles')
    <link href="{{ asset('css/oms-app.css') }}" rel="stylesheet">
</head>
<body>
@php
    $u = auth()->user();
    $roleLabel = $u?->getRoleNames()->first() ?? 'user';
    $scopeAll = $u?->canAccessAllOutlets() ?? false;
    // Item nav role-aware: 'perm' → butuh permission; null → cukup login. Hanya rute GET yang ADA.
    $nav = [
        'Operasional' => [
            ['Dashboard', 'dashboard', null],
        ],
        'Admin · Master Data' => [
            ['Outlet', 'admin.outlets.index', 'master_data.edit'],
            ['User & Role', 'admin.users.index', 'master_data.edit'],
            ['WhatsApp & Target', 'admin.delivery.index', 'master_data.edit'],
            ['Approval Chain', 'admin.approval-chains.index', 'master_data.edit'],
            ['Kapasitas', 'admin.capacity.index', 'master_data.edit'],
            ['Saldo NEVIRA', 'admin.topup-config.index', 'master_data.edit'],
            ['SLA Produksi', 'admin.sla-config.index', 'master_data.edit'],
            ['Audit Transaksi', 'admin.audit-config.index', 'master_data.edit'],
        ],
        'Finance' => [
            ['Dokumen Keuangan', 'finance.documents.index', null],
        ],
        'Discipline' => [
            ['Checklist', 'admin.checklists.index', 'master_data.edit'],
            ['Leaderboard', 'discipline.leaderboard', null],
        ],
    ];
@endphp
<div class="app">
    <aside class="rail">
        <div class="rail__brand">
            <div class="mark">LW</div>
            <div><b>Less Worry</b><span>OMS · Apique Group</span></div>
        </div>
        <div class="rail__scope">
            <div><b>{{ $scopeAll ? 'Semua outlet' : count($u?->assignedOutletIds() ?? []).' outlet' }}</b>
            <span>{{ $scopeAll ? 'akses penuh' : 'binaan Anda' }}</span></div>
        </div>

        <nav class="rail__nav">
            @foreach ($nav as $group => $items)
                @php $visible = collect($items)->filter(fn ($it) => $it[2] === null || auth()->user()->can($it[2])); @endphp
                @if ($visible->isNotEmpty())
                    <div class="rail__label">{{ $group }}</div>
                    @foreach ($visible as $it)
                        <a class="navitem {{ request()->routeIs($it[1]) ? 'is-active' : '' }}" href="{{ route($it[1]) }}">{{ $it[0] }}</a>
                    @endforeach
                @endif
            @endforeach
        </nav>

        <div class="rail__user">
            <span class="avatar">{{ strtoupper(mb_substr($u?->name ?? '?', 0, 2)) }}</span>
            <div><b>{{ $u?->name }}</b><span>{{ $roleLabel }}</span></div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="linkbtn">Keluar</button></form>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <span class="topbar__title">@yield('heading', 'Dashboard')</span>
            <span class="topbar__sub">@yield('subheading', '')</span>
            <span class="topbar__spacer"></span>
            @hasSection('actions')<div class="topbar__actions">@yield('actions')</div>@endif
            <span class="chip"><span class="dot"></span> NEVIRA tersambung</span>
        </header>
        <div class="scroll">
            @yield('content')
        </div>
    </div>
</div>
@yield('scripts')
</body>
</html>
