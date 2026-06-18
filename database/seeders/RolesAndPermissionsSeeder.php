<?php

namespace Database\Seeders;

use App\Modules\Identity\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seed role & permission OMS (OPS-801). Idempoten (firstOrCreate) — aman dijalankan ulang.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Reset cache LAGI: permission yang baru dibuat di loop atas belum ada di cache,
        // sehingga syncPermissions() (yang resolve by name dari cache) gagal "PermissionDoesNotExist".
        $registrar->forgetCachedPermissions();

        foreach (Permissions::rolePermissions() as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }
    }
}
