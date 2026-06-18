<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\NeviraRoleLevel;
use App\Modules\Admin\Http\Requests\RoleLevelRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * CRUD master data peta id_role→level (OPS-805). Referensi yang dipakai OPS-601 — dikelola via Admin,
 * tanpa hardcode. Content-negotiation: JSON utk API/test, Blade utk layar Admin (OPS-805 UI).
 */
class RoleLevelController extends Controller
{
    public function index(Request $request)
    {
        $levels = NeviraRoleLevel::query()->orderByDesc('level')->get();

        if ($request->wantsJson()) {
            return response()->json($levels);
        }

        return view('admin.role-levels.index', ['levels' => $levels]);
    }

    public function store(RoleLevelRequest $request)
    {
        $rl = NeviraRoleLevel::create($request->validated());

        return $request->wantsJson()
            ? response()->json($rl, 201)
            : back()->with('status', 'Peta role ditambahkan.');
    }

    public function update(RoleLevelRequest $request, NeviraRoleLevel $roleLevel)
    {
        $roleLevel->update($request->validated());

        return $request->wantsJson()
            ? response()->json($roleLevel)
            : back()->with('status', 'Peta role diperbarui.');
    }

    public function destroy(Request $request, NeviraRoleLevel $roleLevel)
    {
        $roleLevel->delete();

        return $request->wantsJson()
            ? response()->json(['deleted' => true])
            : back()->with('status', 'Peta role dihapus.');
    }
}
