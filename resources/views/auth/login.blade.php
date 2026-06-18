<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk · OMS Less Worry</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('css/oms-app.css') }}" rel="stylesheet">
</head>
<body>
<div class="login">
    <form class="login__card" method="POST" action="{{ route('login') }}">
        @csrf
        <div class="login__logo">LW</div>
        <h1 class="login__title">Masuk ke OMS</h1>
        <p class="login__sub">Operations Management System · Apique Group</p>

        @error('email')<div class="login__err">{{ $message }}</div>@enderror

        <div class="field">
            <label for="email">Email</label>
            <input id="email" class="input @error('email') is-err @enderror" type="email" name="email"
                value="{{ old('email') }}" autocomplete="username" placeholder="nama@lessworry.id" required autofocus>
        </div>
        <div class="field">
            <label for="password">Kata sandi <span class="forgot" title="Hubungi admin untuk reset">Lupa kata sandi?</span></label>
            <input id="password" class="input @error('email') is-err @enderror" type="password" name="password"
                autocomplete="current-password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn--primary btn--lg">Masuk</button>
        <div class="login__foot">🔒 Akun dibuat oleh admin · investor menerima laporan via WhatsApp</div>
    </form>
</div>
</body>
</html>
