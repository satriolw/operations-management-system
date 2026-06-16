@extends('layouts.admin')

@section('title', 'User & Role')
@section('styles')<link href="{{ asset('css/oms-users.css') }}" rel="stylesheet">@endsection

@php
    use Illuminate\Support\Str;
    $roleMeta = [
        'admin' => ['label' => 'Admin', 'cls' => 'role-admin', 'desc' => 'Akses penuh semua outlet & konfigurasi'],
        'area_manager' => ['label' => 'Area Manager', 'cls' => 'role-area', 'desc' => 'Pantau beberapa outlet dalam area'],
        'head_store' => ['label' => 'Head Store', 'cls' => 'role-head', 'desc' => 'Kelola & kirim laporan satu outlet'],
        'ops' => ['label' => 'Ops', 'cls' => 'role-ops', 'desc' => 'Input transaksi & operasional harian'],
    ];
    $statusMeta = [
        'active' => ['cls' => 'st-active', 'txt' => 'Aktif'],
        'pending' => ['cls' => 'st-pending', 'txt' => 'Menunggu'],
        'inactive' => ['cls' => 'st-inactive', 'txt' => 'Nonaktif'],
    ];
    $initials = fn ($s) => Str::upper(Str::substr(collect(explode(' ', trim($s)))->map(fn ($w) => $w[0] ?? '')->take(2)->implode(''), 0, 2));
    $usersJson = $users->map(fn ($u) => [
        'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
        'role' => $u->roles->pluck('name')->first(),
        'outlets' => $u->outlets->pluck('id_outlet')->values(),
        'status' => $u->status,
    ])->values();
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
            <a href="#" class="on"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>User &amp; Role</a>
        </nav>
        <div class="rail__user">
            <div class="av">{{ $initials(auth()->user()->name ?? 'U') }}</div>
            <div><b>{{ auth()->user()->name ?? 'User' }}</b><span>{{ auth()->user()?->getRoleNames()->first() ?? 'staf' }}</span></div>
        </div>
    </aside>

    <div class="main">
        <div class="topbar">
            <div>
                <div class="crumb"><span>Konfigurasi</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg><b>User &amp; Role</b></div>
                <h1>User &amp; Role</h1>
            </div>
            <div class="topbar__right">
                <button type="button" class="btn btn--primary btn--sm" onclick="openInvite()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>Undang user</button>
            </div>
        </div>

        <div class="scroll">
            <div class="inner">
                @if (session('status'))
                    <div class="errbanner on" style="background:var(--good-bg);border-color:var(--good-bd)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="var(--good)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                        <div><b style="color:var(--good)">{{ session('status') }}</b></div>
                    </div>
                @endif

                <div class="toolbar">
                    <div class="search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                        <input placeholder="Cari nama atau email…" oninput="applyFilter(this.value)">
                    </div>
                    <div class="seg" id="roleFilter">
                        <button type="button" data-f="all" class="on">Semua</button>
                        <button type="button" data-f="admin">Admin</button>
                        <button type="button" data-f="area_manager">Area</button>
                        <button type="button" data-f="head_store">Head Store</button>
                        <button type="button" data-f="ops">Ops</button>
                    </div>
                    <div class="spacer"></div>
                    <span style="font-size:12.5px;color:var(--ink-3);font-weight:600" id="countLbl">{{ $users->count() }} user</span>
                </div>

                <div class="card">
                    <table class="tbl">
                        <thead><tr><th>User</th><th>Role</th><th>Outlet di-assign (scope)</th><th>Status</th><th style="text-align:right">Aksi</th></tr></thead>
                        <tbody id="userBody">
                        @forelse ($users as $u)
                            @php
                                $role = $u->roles->pluck('name')->first();
                                $rm = $roleMeta[$role] ?? ['label' => $role, 'cls' => 'role-ops'];
                                $sm = $statusMeta[$u->status] ?? $statusMeta['active'];
                                $isAdmin = $role === 'admin';
                            @endphp
                            <tr class="{{ $u->isInactive() ? 'inactive' : '' }}"
                                data-role="{{ $role }}" data-name="{{ Str::lower($u->name) }}" data-email="{{ Str::lower($u->email) }}">
                                <td><div class="user"><div class="av" style="background:#3C4A5C">{{ $initials($u->name) }}</div><div><b>{{ $u->name }}</b><span>{{ $u->email }}</span></div></div></td>
                                <td><span class="rolebadge {{ $rm['cls'] }}">{{ $rm['label'] }}</span></td>
                                <td><div class="chips">
                                    @if ($isAdmin)
                                        <span class="ochip ochip--all"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18z"/></svg> Semua outlet</span>
                                    @else
                                        @foreach ($u->outlets->take(3) as $o)
                                            <span class="ochip"><span class="b" style="background:var(--lw)"></span>{{ $o->name }}</span>
                                        @endforeach
                                        @if ($u->outlets->count() > 3)<span class="ochip ochip--more">+{{ $u->outlets->count() - 3 }}</span>@endif
                                        @if ($u->outlets->isEmpty())<span class="ochip ochip--more">belum di-assign</span>@endif
                                    @endif
                                </div></td>
                                <td><span class="statustxt {{ $sm['cls'] }}"><span class="d"></span>{{ $sm['txt'] }}</span></td>
                                <td class="keepfull"><div class="rowact">
                                    <button type="button" class="iconbtn" title="Edit" onclick="openEdit({{ $u->id }})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                    <form method="POST" action="{{ route('admin.users.toggle', $u) }}" style="display:inline">@csrf @method('PUT')
                                        @if ($u->isInactive())
                                            <button type="submit" class="iconbtn good" title="Aktifkan"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v10M5.6 7.6a8 8 0 1 0 12.8 0"/></svg></button>
                                        @else
                                            <button type="submit" class="iconbtn danger" title="Nonaktifkan"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><path d="M12 2v10"/></svg></button>
                                        @endif
                                    </form>
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="text-align:center;color:var(--ink-3);padding:30px">Belum ada user.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL invite/edit --}}
<div class="overlay" id="overlay">
    <form class="modal" method="POST" id="userForm" action="{{ route('admin.users.store') }}">
        @csrf
        <input type="hidden" name="_method" id="formMethod" value="POST">
        <div class="modal__h">
            <div class="mi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg></div>
            <div><b id="modalTitle">Undang user</b><p id="modalSub">Kirim undangan via email. User mengatur kata sandi saat pertama masuk.</p></div>
            <button type="button" class="x" onclick="closeModal()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
        </div>
        <div class="modal__b">
            @if ($errors->any())
                <div class="errbanner on"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg><div><b>Periksa kembali isian</b><p>{{ $errors->first() }}</p></div></div>
            @endif
            <div class="frow">
                <div class="field"><label>Nama lengkap <span class="req">*</span></label>
                    <input class="input @error('name') input--err @enderror" name="name" id="mName" value="{{ old('name') }}" placeholder="mis. Dewi Lestari">
                </div>
                <div class="field"><label>Email <span class="req">*</span></label>
                    <input class="input @error('email') input--err @enderror" name="email" id="mEmail" value="{{ old('email') }}" placeholder="nama@apique.id">
                    <span class="hint" id="emailHint">Email tidak dapat diubah setelah dibuat.</span>
                </div>
            </div>
            <div class="field full" style="margin-top:15px">
                <label>Role <span class="req">*</span></label>
                <div class="rolegrid" id="roleGrid">
                    @foreach ($roleMeta as $key => $rm)
                        <label class="rolecard" data-role="{{ $key }}">
                            <input type="radio" name="role" value="{{ $key }}" style="display:none" @checked(old('role', 'head_store') === $key)>
                            <div class="rc-ico {{ $rm['cls'] }}"></div>
                            <div><b>{{ $rm['label'] }}</b><span>{{ $rm['desc'] }}</span></div>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="field full" style="margin-top:15px">
                <label id="assignLabel">Outlet di-assign <span class="req">*</span></label>
                <div class="assignbox" id="assignBox">
                    <div class="assign-grid">
                        @foreach ($outlets as $o)
                            <label class="opick" data-outlet="{{ $o->id_outlet }}">
                                <input type="checkbox" name="outlets[]" value="{{ $o->id_outlet }}" style="display:none" @checked(collect(old('outlets', []))->contains($o->id_outlet))>
                                <span class="cb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg></span>
                                <span class="b" style="background:var(--lw)"></span>{{ $o->name }}
                            </label>
                        @endforeach
                    </div>
                    <div class="assign-count" id="assignCount"></div>
                </div>
                @error('outlets')<span class="errmsg on"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="modal__f">
            <div class="spacer"></div>
            <button type="button" class="btn btn--ghost" onclick="closeModal()">Batal</button>
            <button type="submit" class="btn btn--primary" id="saveBtn">Kirim undangan</button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    const USERS = @json($usersJson);
    const SCOPES = @json($roleScopes);
    const STORE_URL = @json(route('admin.users.store'));
    const UPDATE_TPL = @json(url('admin/users')) + '/';

    // ---- filter (client-side) ----
    let filter = 'all', search = '';
    function applyFilter(v){ search = (v||'').toLowerCase(); renderFilter(); }
    document.querySelectorAll('#roleFilter button').forEach(b => b.onclick = () => {
        filter = b.dataset.f;
        document.querySelectorAll('#roleFilter button').forEach(x => x.classList.toggle('on', x === b));
        renderFilter();
    });
    function renderFilter(){
        let shown = 0;
        document.querySelectorAll('#userBody tr[data-role]').forEach(tr => {
            const okRole = filter === 'all' || tr.dataset.role === filter;
            const okSearch = !search || tr.dataset.name.includes(search) || tr.dataset.email.includes(search);
            const show = okRole && okSearch;
            tr.style.display = show ? '' : 'none';
            if(show) shown++;
        });
        document.getElementById('countLbl').textContent = shown + ' user';
    }

    // ---- role cards + assign scope ----
    function selectedRole(){ return document.querySelector('input[name=role]:checked')?.value || 'head_store'; }
    function paintRoleCards(){
        document.querySelectorAll('.rolecard').forEach(c => {
            c.classList.toggle('sel', c.querySelector('input').checked);
        });
    }
    function applyScope(){
        const scope = SCOPES[selectedRole()] || 'multi';
        const box = document.getElementById('assignBox');
        const label = document.getElementById('assignLabel');
        const picks = document.querySelectorAll('.opick');
        if(scope === 'all'){
            box.classList.add('disabled');
            label.innerHTML = 'Outlet di-assign';
            picks.forEach(p => { p.querySelector('input').checked = false; });
        } else {
            box.classList.remove('disabled');
            label.innerHTML = 'Outlet di-assign <span class="req">*</span> · ' + (scope === 'single' ? 'pilih satu' : 'bisa beberapa');
        }
        paintPicks();
    }
    function paintPicks(){
        let n = 0;
        document.querySelectorAll('.opick').forEach(p => {
            const on = p.querySelector('input').checked;
            p.classList.toggle('on', on);
            if(on) n++;
        });
        const c = document.getElementById('assignCount');
        if(c) c.textContent = n ? (n + ' outlet dipilih — user hanya melihat data outlet ini.') : 'Belum ada outlet dipilih.';
    }
    document.querySelectorAll('.rolecard').forEach(card => card.addEventListener('click', () => {
        card.querySelector('input').checked = true; paintRoleCards(); applyScope();
    }));
    document.querySelectorAll('.opick').forEach(pick => pick.addEventListener('click', e => {
        e.preventDefault();
        const scope = SCOPES[selectedRole()] || 'multi';
        const input = pick.querySelector('input');
        if(scope === 'single'){
            document.querySelectorAll('.opick input').forEach(i => { i.checked = false; });
            input.checked = true;
        } else {
            input.checked = !input.checked;
        }
        paintPicks();
    }));

    // ---- modal open/close ----
    const overlay = document.getElementById('overlay');
    function setRole(r){ const el = document.querySelector(`input[name=role][value="${r}"]`); if(el) el.checked = true; paintRoleCards(); applyScope(); }
    function setOutlets(ids){ document.querySelectorAll('.opick input').forEach(i => { i.checked = ids.map(String).includes(i.value); }); paintPicks(); }

    function openInvite(){
        document.getElementById('userForm').action = STORE_URL;
        document.getElementById('formMethod').value = 'POST';
        document.getElementById('modalTitle').textContent = 'Undang user';
        document.getElementById('saveBtn').textContent = 'Kirim undangan';
        document.getElementById('mName').value = ''; document.getElementById('mEmail').value = '';
        document.getElementById('mEmail').readOnly = false;
        setRole('head_store'); setOutlets([]);
        overlay.classList.add('on');
    }
    function openEdit(id){
        const u = USERS.find(x => x.id === id); if(!u) return;
        document.getElementById('userForm').action = UPDATE_TPL + id;
        document.getElementById('formMethod').value = 'PUT';
        document.getElementById('modalTitle').textContent = 'Edit user';
        document.getElementById('saveBtn').textContent = 'Simpan perubahan';
        document.getElementById('mName').value = u.name;
        document.getElementById('mEmail').value = u.email;
        document.getElementById('mEmail').readOnly = true; // email immutable
        setRole(u.role); setOutlets(u.outlets);
        overlay.classList.add('on');
    }
    function closeModal(){ overlay.classList.remove('on'); }
    overlay.addEventListener('click', e => { if(e.target === overlay) closeModal(); });
    window.openInvite = openInvite; window.openEdit = openEdit; window.closeModal = closeModal; window.applyFilter = applyFilter;

    // init: jika ada error validasi → buka modal kembali dgn old input
    paintRoleCards(); applyScope(); renderFilter();
    @if ($errors->any())
        document.getElementById('formMethod').value = @json(old('_method', 'POST'));
        document.getElementById('userForm').action = @json(old('_method') === 'PUT' ? url('admin/users') : route('admin.users.store'));
        overlay.classList.add('on');
    @endif
</script>
@endsection
