<?php

namespace App\Modules\Identity;

use App\Models\NeviraRoleLevel;

/**
 * Akses peta id_role→level (OPS-805) untuk kebijakan self-approval (OPS-601).
 * Mengembalikan null bila id_role belum dipetakan → pemanggil menandai "perlu ditinjau".
 */
final class RoleLevelMap
{
    /** null = id_role belum dipetakan (tak bisa diputuskan). */
    public function allowsDualAuthority(?int $idRole): ?bool
    {
        if ($idRole === null) {
            return null;
        }

        return NeviraRoleLevel::query()->where('id_role', $idRole)->first()?->dual_authority_allowed;
    }

    public function levelFor(?int $idRole): ?int
    {
        if ($idRole === null) {
            return null;
        }

        return NeviraRoleLevel::query()->where('id_role', $idRole)->value('level');
    }
}
