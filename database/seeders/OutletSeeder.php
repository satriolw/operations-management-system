<?php

namespace Database\Seeders;

use App\Models\Outlet;
use Illuminate\Database\Seeder;

/**
 * Registry outlet OMS (OPS-105). PK `id_outlet` = id outlet NEVIRA (natural key — HARUS cocok
 * dengan NEVIRA, karena laporan/sinyal menarik data per id_outlet ini). Idempoten (updateOrCreate).
 *
 * ⚠️ Pipeline (laporan harian, sinyal) hanya berjalan untuk outlet AKTIF & terdaftar di sini.
 * Tanpa baris di tabel ini → semua halaman kosong meski NEVIRA tersambung.
 *
 * CATATAN LURD: id_outlet di bawah dikonfirmasi dari SAMPEL transaksi NEVIRA. LENGKAPI sisanya
 * (Lebak Bulus, Fatmawati, Jati Padang, Jagakarsa, Hampton GS, KWL Pamulang, dll.) dengan
 * id_outlet NEVIRA ASLI — jangan menebak id-nya.
 */
class OutletSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = [
            ['id_outlet' => 115, 'name' => 'Kemang'],
            ['id_outlet' => 116, 'name' => 'Cipete'],
            ['id_outlet' => 118, 'name' => 'Tebet'],
            ['id_outlet' => 121, 'name' => 'Pondok Indah'],
            ['id_outlet' => 123, 'name' => 'Park Serpong'],
            // TODO: tambah outlet lain dgn id_outlet NEVIRA asli, mis.
            // ['id_outlet' => XXX, 'name' => 'Lebak Bulus'],
        ];

        foreach ($outlets as $o) {
            Outlet::updateOrCreate(
                ['id_outlet' => $o['id_outlet']],
                [
                    'name' => $o['name'],
                    'report_time' => '20:00',          // jam kirim laporan harian (WIB)
                    'timezone' => 'Asia/Jakarta',
                    'active' => true,
                    // silent_threshold_pct & comparison_basis pakai default DB (40, avg_14d)
                ]
            );
        }
    }
}
