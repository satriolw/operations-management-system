<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · OMS</title>
    <style>
        :root { --line:#e3e3e0; --ink:#1a1a18; --muted:#6b6b66; --accent:#1f6feb; --bg:#faf9f7; --ok:#137333; --err:#b3261e; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:ui-sans-serif,system-ui,-apple-system,sans-serif; color:var(--ink); background:var(--bg); }
        .wrap { max-width:760px; margin:0 auto; padding:24px 20px 64px; }
        h1 { font-size:22px; margin:0 0 4px; }
        .sub { color:var(--muted); font-size:13px; margin-bottom:24px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:20px; margin-bottom:16px; }
        .card h2 { font-size:15px; margin:0 0 14px; }
        label { display:block; font-size:13px; font-weight:600; margin-bottom:6px; }
        input[type=time],input[type=date],input[type=number],input[type=text] { font:inherit; padding:8px 10px; border:1px solid var(--line); border-radius:8px; width:100%; max-width:220px; }
        .row { display:flex; gap:10px; align-items:flex-end; margin-bottom:10px; flex-wrap:wrap; }
        .row > div { flex:0 0 auto; }
        .switch { display:flex; align-items:center; gap:8px; }
        .switch input { width:auto; }
        button { font:inherit; cursor:pointer; border-radius:8px; border:1px solid var(--line); background:#fff; padding:8px 12px; }
        button.primary { background:var(--accent); color:#fff; border-color:var(--accent); font-weight:600; }
        button.link { border:none; background:none; color:var(--accent); padding:4px; }
        button.rm { border:none; background:none; color:var(--err); padding:4px 8px; }
        .alert { padding:12px 14px; border-radius:8px; margin-bottom:16px; font-size:14px; }
        .alert.ok { background:#e6f4ea; color:var(--ok); border:1px solid #b7dfc2; }
        .alert.err { background:#fce8e6; color:var(--err); border:1px solid #f3c2bd; }
        .alert.info { background:#fef7e0; color:#7a5a00; border:1px solid #f5e2a8; }
        .err-text { color:var(--err); font-size:12px; margin-top:4px; }
        .actions { display:flex; gap:10px; margin-top:8px; }
    </style>
</head>
<body>
    <div class="wrap">
        @yield('content')
    </div>
    @yield('scripts')
</body>
</html>
