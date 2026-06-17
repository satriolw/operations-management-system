<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Peta id_role NEVIRA → level (OPS-805). Aktor NEVIRA, bukan user OMS.
 */
class NeviraRoleLevel extends Model
{
    protected $fillable = ['id_role', 'label', 'level', 'dual_authority_allowed'];

    protected $casts = [
        'id_role' => 'integer',
        'level' => 'integer',
        'dual_authority_allowed' => 'boolean',
    ];
}
