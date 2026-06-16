<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\Outlet;
use App\Models\User;
use App\Modules\Admin\Http\Requests\StoreUserRequest;
use App\Modules\Admin\Http\Requests\UpdateUserRequest;
use App\Modules\Identity\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * User & Role (OPS-802). CRUD user OMS + assign role/permission (spatie OPS-801) +
 * assignment outlet (scoping OPS-1003). Email immutable saat edit.
 */
class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->with(['roles', 'outlets'])->orderBy('name')->get();

        return view('admin.users.index', [
            'users' => $users,
            'outlets' => Outlet::query()->orderBy('name')->get(),
            'roleScopes' => Permissions::roleScopes(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make(Str::random(40)), // user set sendiri saat onboarding
            'status' => 'pending',                      // diundang, belum login
        ]);
        $user->syncRoles([$request->input('role')]);
        $this->syncOutlets($user, $request->input('role'), $request->input('outlets', []));

        return redirect()->route('admin.users.index')
            ->with('status', "Undangan dikirim ke {$user->email}.");
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update(['name' => $request->input('name')]); // email immutable
        $user->syncRoles([$request->input('role')]);
        $this->syncOutlets($user, $request->input('role'), $request->input('outlets', []));

        return redirect()->route('admin.users.index')
            ->with('status', "Perubahan {$user->name} disimpan.");
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $user->update(['status' => $user->isInactive() ? 'active' : 'inactive']);

        return redirect()->route('admin.users.index')
            ->with('status', $user->isInactive() ? "{$user->name} dinonaktifkan." : "{$user->name} diaktifkan.");
    }

    /** Admin = akses semua → tanpa baris assignment. Lainnya = sync outlet terpilih. */
    private function syncOutlets(User $user, string $role, array $outlets): void
    {
        $user->outlets()->sync(Permissions::scopeFor($role) === 'all' ? [] : $outlets);
    }
}
