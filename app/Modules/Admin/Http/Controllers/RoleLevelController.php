<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\NeviraRoleLevel;
use App\Modules\Admin\Http\Requests\RoleLevelRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * CRUD master data peta id_role→level (OPS-805). Referensi yang dipakai OPS-601 — dikelola via Admin,
 * tanpa hardcode. JSON ringkas (UI menyusul; data layer + endpoint siap).
 */
class RoleLevelController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(NeviraRoleLevel::query()->orderBy('level', 'desc')->get());
    }

    public function store(RoleLevelRequest $request): JsonResponse
    {
        return response()->json(NeviraRoleLevel::create($request->validated()), 201);
    }

    public function update(RoleLevelRequest $request, NeviraRoleLevel $roleLevel): JsonResponse
    {
        $roleLevel->update($request->validated());

        return response()->json($roleLevel);
    }

    public function destroy(NeviraRoleLevel $roleLevel): JsonResponse
    {
        $roleLevel->delete();

        return response()->json(['deleted' => true]);
    }
}
