<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Auth UI OMS (OPS-806) — Blade custom + guard/sesi Laravel (BUKAN Breeze/Jetstream/Livewire, ADR-015).
 * Pakai user & permission OPS-801. Investor TIDAK login. Tanpa register publik (user dibuat admin).
 */
class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('dashboard') : view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $cred = $request->validated();

        if (! Auth::attempt(['email' => $cred['email'], 'password' => $cred['password']], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau kata sandi salah.'])->onlyInput('email');
        }

        // Nonaktif → tolak (status user OPS-802).
        if (($request->user()->status ?? 'active') !== 'active') {
            Auth::logout();

            return back()->withErrors(['email' => 'Akun nonaktif. Hubungi admin.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
