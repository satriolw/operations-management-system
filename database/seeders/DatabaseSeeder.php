<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Role & permission WAJIB lebih dulu — assignRole() butuh role sudah ada (OPS-801).
        $this->call(RolesAndPermissionsSeeder::class);

        // Super admin awal. Idempoten (updateOrCreate) — aman dijalankan ulang; password di-reset tiap seed.
        // password '12345' otomatis di-hash via cast 'hashed' di model User.
        $admin = User::updateOrCreate(
            ['email' => 'satrio@lessworry.id'],
            [
                'name' => 'Satrio Wibowo',
                'password' => '12345',
                'status' => 'active',
            ]
        );

        if (! $admin->hasRole(Permissions::ROLE_ADMIN)) {
            $admin->assignRole(Permissions::ROLE_ADMIN);
        }

        // Master data lain (idempoten). Ditaruh SETELAH admin agar login tetap bisa walau salah satu gagal.
        $this->call([
            OutletSeeder::class,          // WAJIB: tanpa outlet, pipeline tak jalan & semua halaman kosong
            ApprovalChainSeeder::class,
            DefaultTemplateSeeder::class,
            DefaultChecklistSeeder::class,
        ]);
    }
}
